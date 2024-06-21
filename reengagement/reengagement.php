<?php
/**
 * This file contains the basic functionality for the 'reengagement' addon, including
 * - installing the addon
 * - uninstalling the addon
 * - creating a reengagement
 * - editing a reengagement
 * - deleting a reengagement
 *
 * The main class is broken up as much as possible into sections.
 * There are two addition files:
 * - reengagement_send.php
 * - reengagement_stats.php
 *
 * These files are only included when you are viewing the relevant area(s)
 * to help keep memory usage & processing time to a reasonable limit.
 *
 * It also makes it much easier to work out where things are.
 *
 * @package Interspire_Addons
 * @subpackage Addons_reengagement
 */

/**
 * Make sure the base Interspire_Addons class is defined.
 */
if (! class_exists('Interspire_Addons', false)) {
    require_once(dirname(__FILE__, 2) . '/interspire_addons.php');
}

require_once(__DIR__ . '/language/language.php');

/**
 * This class handles most things for re engagementing
 * including extra user permissions, menu items (under 'email campaigns' and also in 'stats')
 * and of course processing everything.
 *
 * If you go into a particular area (eg 'sending' a re engagement campaign), then extra files are included.
 * This helps keep memory usage and processing time to a reasonable limit.
 *
 * @uses Interspire_Addons
 * @uses Interspire_Addons_Exception
 * @uses Addons_reengagement_Send
 */
class Addons_reengagement extends Interspire_Addons
{
    /**
     * minimum_element
     * to work out the "best" performing newsletter/campaign.
     */
    final public const minimum_element = 1;

    /**
     * maximum_element
     * to work out the "best" performing newsletter/campaign.
     */
    final public const maximum_element = 365;

    /*
     * It is the time after a job has started and sent to the first "X" percent of a list/segment
     * but is waiting for the "hours after" time to expire to send the rest.
     */
    public static $jobstatuscodes = ['t'];

    /**
     * Install
     * This addon has to create some database tables to work.
     * It includes the schema files (based on the database type) and creates the bits it needs.
     * Once that's done, it calls the parent Install method to do its work.
     *
     * @uses enabled
     * @uses configured
     * @uses Interspire_Addons::Install
     * @uses Interspire_Addons_Exception
     *
     * @throws Throws an Interspire_Addons_Exception if something in the install process fails.
     * @return true Returns true if everything works ok.
     */
    public function Install()
    {
        $tables = $sequences = [];

        $this->db->StartTransaction();

        require __DIR__ . '/schema.' . SENDSTUDIO_DATABASE_TYPE . '.php';
        foreach ($queries as $query) {
            $qry = str_replace('%%TABLEPREFIX%%', $this->db->TablePrefix, (string) $query);
            $result = $this->db->Query($qry);
            if (! $result) {
                $this->db->RollbackTransaction();
                throw new Interspire_Addons_Exception('There was a problem running query ' . $qry . ': ' . $this->db->GetErrorMsg(), Interspire_Addons_Exception::DatabaseError);
            }
        }

        $this->enabled = true;
        $this->configured = true;
        try {
            $status = parent::Install();
        } catch (Interspire_Addons_Exception $e) {
            $this->db->RollbackTransaction();
            throw new Exception("Unable to install addon {$this->GetId()} " . $e->getMessage());
        }

        $this->db->CommitTransaction();

        return true;
    }

    /**
     * UnInstall
     * Drop tables the addon created.
     * It includes the schema files (based on the database type) and drops the bits it created.
     * Once that's done, it calls the parent UnInstall method to do its work.
     *
     * @uses Interspire_Addons::UnInstall
     * @uses Interspire_Addons_Exception
     *
     * @return Returns true if the addon was uninstalled successfully.
     * @throws Throws an Interspire_Addons_Exception::DatabaseError if one of the tables it created couldn't be removed. If the parent::UnInstall method throws an exception, this will
     * just re-throw that error.
     */
    public function UnInstall()
    {
        $tables = $sequences = [];

        $this->db->StartTransaction();

        try {
            $this->Disable();
        } catch (Interspire_Addons_Exception $e) {
            $this->db->RollbackTransaction();
            throw new Interspire_Addons_Exception($e->getMessage(), $e->getCode());
        }

        require __DIR__ . '/schema.' . SENDSTUDIO_DATABASE_TYPE . '.php';
        foreach ($tables as $tablename) {
            $query = 'DROP TABLE [|PREFIX|]' . $tablename . ' CASCADE';
            $result = $this->db->Query($query);
            if (! $result) {
                $this->db->RollbackTransaction();
                throw new Interspire_Addons_Exception('There was a problem running query ' . $query . ': ' . $this->db->GetErrorMsg(), Interspire_Addons_Exception::DatabaseError);
            }
        }

        foreach ($sequences as $sequencename) {
            $query = 'DROP SEQUENCE [|PREFIX|]' . $sequencename;
            $result = $this->db->Query($query);
            if (! $result) {
                $this->db->RollbackTransaction();
                throw new Interspire_Addons_Exception('There was a problem running query ' . $query . ': ' . $this->db->GetErrorMsg(), Interspire_Addons_Exception::DatabaseError);
            }
        }

        try {
            $status = parent::UnInstall();
        } catch (Interspire_Addons_Exception $e) {
            $this->db->RollbackTransaction();
            throw new Interspire_Addons_Exception($e->getMessage(), $e->getCode());
        }

        $this->db->CommitTransaction();

        return true;
    }

    /**
     * Enable
     * This enables the re engagement addon to work, including displaying in the menu(s), adding it's own permissions etc.
     * It adds an entry to the settings_cron_schedule table
     * so if necessary, the addon can be run via cron instead of the web interface.
     *
     * @uses Interspire_Addons::Enable
     * @uses Interspire_Addons_Exception
     *
     * @return Returns true if the addon was enabled successfully.
     * @throws If the parent::Enable method throws an exception, this will just re-throw that error.
     */
    public function Enable()
    {
        $this->db->Query("INSERT INTO [|PREFIX|]settings_cron_schedule(jobtype, lastrun) VALUES ('" . $this->db->Quote($this->addon_id) . "', 0)");
        try {
            $status = parent::Enable();
        } catch (Interspire_Addons_Exception $e) {
            throw new Interspire_Addons_Exception($e->getMessage(), $e->getCode());
        }
        return true;
    }

    /**
     * Disable
     * This disables the re engagement addon from the control panel.
     * Before it does it, it checks for any non-complete re engagement sending jobs
     * If any are found, the addon cannot be disabled.
     *
     * If that's ok, it deletes itself from the settings_cron_schedule table and any other settings it created (config_settings table).
     *
     * @uses Interspire_Addons::Disable
     * @uses Interspire_Addons_Exception
     *
     * @return Returns true if the addon was disabled successfully and there are no pending/in progress re engagement sends.
     * @throws If the parent::Disable method throws an exception, this will just re-throw that error.
     */
    public function Disable()
    {
        $job_check = "SELECT COUNT(jobid) AS jobcount FROM [|PREFIX|]jobs WHERE jobtype='reengagement' AND jobstatus NOT IN ('c','p')";
        $count = $this->db->FetchOne($job_check);
        if ($count > 0) {
            throw new Interspire_Addons_Exception(GetLang('Addon_reengagement_DisableFailed_SendsInProgress'));
        }

        $this->db->StartTransaction();
        $result = $this->db->Query("DELETE FROM [|PREFIX|]settings_cron_schedule WHERE jobtype='" . $this->db->Quote($this->addon_id) . "'");
        if (! $result) {
            $this->db->RollbackTransaction();
        }
        $result = $this->db->Query("DELETE FROM [|PREFIX|]config_settings WHERE area='" . $this->db->Quote(strtoupper('CRON_' . $this->addon_id)) . "'");
        if (! $result) {
            $this->db->RollbackTransaction();
        }
        $this->db->CommitTransaction();
        try {
            $status = parent::Disable();
        } catch (Interspire_Addons_Exception $e) {
            throw new Interspire_Addons_Exception($e->getMessage(), $e->getCode());
        }
        return true;
    }

    /**
     * GetEventListeners
     * The addon uses quite a few events to place itself in the app and allow it to work.
     *
     * IEM_SENDSTUDIOFUNCTIONS_GENERATEMENULINKS
     * Put itself in the menu
     *
     * IEM_USERAPI_GETPERMISSIONTYPES
     * Add new permissions to control who is allowed to use the addon
     *
     * IEM_SETTINGSAPI_LOADSETTINGS
     * Adds new options to the settings for cron
     *
     * IEM_CRON_RUNADDONS
     * Adds itself to the list of addons that can have cron jobs
     *
     * IEM_SENDSTUDIOFUNCTIONS_CLEANUPOLDQUEUES
     * Cleans up any incomplete "sends" that have been started.
     * For example, get to step 2 or 3 in the "send" process, then either
     * your browser dies, or you navigate away from the process.
     *
     * IEM_NEWSLETTERSAPI_DELETE
     * When a newsletter/email campaign is about to be deleted,
     * check it's not used by re engagement campaigns.
     * If it is, the newsletter/email campaign can't be deleted.
     *
     * IEM_NEWSLETTERS_MANAGENEWSLETTERS
     * Adds any messages created when newsletters/email campaigns
     * are attempted to be deleted.
     *
     * IEM_JOBSAPI_GETJOBLIST
     * Adds itself to the "job list" which is used to show the scheduled sending list
     *
     * IEM_JOBSAPI_GETJOBSTATUS
     * Adds it's own unique job status types
     * (which can then show different status messages)
     *
     * IEM_SCHEDULE_PAUSEJOB
     * Handle it's own "pause job" process.
     * In this case, it needs to update it's own database tables with the new status codes.
     *
     * IEM_SCHEDULE_EDITJOB
     * Print it's own "edit schedule" form to fill out
     *
     * IEM_SCHEDULE_RESUMEJOB
     * Handle it's own "resume job" process.
     * In this case, it needs to update it's own database tables with the new status codes.
     *
     * IEM_SCHEDULE_DELETEJOBS
     * Handle it's own "delete jobs" process
     * In this case, the way user credits are re-allocated is changed.
     *
     * @return array Returns an array containing the listeners, the files to include, the function/methods to run etc.
     */
    public function GetEventListeners()
    {
        $my_file = '{%IEM_ADDONS_PATH%}/reengagement/reengagement.php';
        $listeners = [];

        $listeners[] =
            [
                'eventname' => 'IEM_SENDSTUDIOFUNCTIONS_GENERATEMENULINKS',
                'trigger_details' => [
                    'Addons_reengagement',
                    'SetMenuItems',
                ],
                'trigger_file' => $my_file,
            ];

        $listeners[] =
            [
                'eventname' => 'IEM_USERAPI_GETPERMISSIONTYPES',
                'trigger_details' => [
                    'Interspire_Addons',
                    'GetAddonPermissions',
                ],
                'trigger_file' => $my_file,
            ];

        $listeners[] =
            [
                'eventname' => 'IEM_SETTINGSAPI_LOADSETTINGS',
                'trigger_details' => [
                    'Addons_reengagement',
                    'SetSettings',
                ],
                'trigger_file' => $my_file,
            ];

        $listeners[] =
            [
                'eventname' => 'IEM_CRON_RUNADDONS',
                'trigger_details' => 'Reengagement_Cron_GetJobs',
                'trigger_file' => dirname($my_file) . '/cron.php',
            ];

        $listeners[] =
            [
                'eventname' => 'IEM_NEWSLETTERSAPI_DELETE',
                'trigger_details' => [
                    'Addons_reengagement',
                    'DeleteNewsletters',
                ],
                'trigger_file' => $my_file,
            ];

        $listeners[] =
            [
                'eventname' => 'IEM_JOBSAPI_GETJOBLIST',
                'trigger_details' => [
                    'Addons_reengagement',
                    'GenerateJobListQuery',
                ],
                'trigger_file' => $my_file,
            ];

        $listeners[] =
            [
                'eventname' => 'IEM_JOBSAPI_GETJOBSTATUS',
                'trigger_details' => [
                    'Addons_reengagement',
                    'GetJobStatus',
                ],
                'trigger_file' => $my_file,
            ];

        $listeners[] =
            [
                'eventname' => 'IEM_SCHEDULE_PAUSEJOB',
                'trigger_details' => [
                    'Addons_reengagement',
                    'PauseSchedule',
                ],
                'trigger_file' => $my_file,
            ];

        $listeners[] =
            [
                'eventname' => 'IEM_SCHEDULE_EDITJOB',
                'trigger_details' => [
                    'Addons_reengagement',
                    'EditSchedule',
                ],
                'trigger_file' => $my_file,
            ];

        $listeners[] =
            [
                'eventname' => 'IEM_SCHEDULE_RESUMEJOB',
                'trigger_details' => [
                    'Addons_reengagement',
                    'ResumeSchedule',
                ],
                'trigger_file' => $my_file,
            ];

        $listeners[] =
            [
                'eventname' => 'IEM_SCHEDULE_DELETEJOBS',
                'trigger_details' => [
                    'Addons_reengagement',
                    'DeleteSchedules',
                ],
                'trigger_file' => $my_file,
            ];

        return $listeners;
    }

    /**
     * SetSettings
     * Adds new options to the "cron settings" page and settings database table.
     * Sets the "last run time" for the job to -1 which means "hasn't run".
     *
     * Adds a new settings entry called "CRON_REENGAGEMENT" to the settings table.
     * Also adds the following times to the "run job every" dropdown box:
     * - 1 minute
     * - 2, 5, 10, 15, 20, 30 minutes
     *
     * @param EventData_IEM_SETTINGSAPI_LOADSETTINGS $data The current settings data which is passed in by reference (is an object).
     *
     * @uses EventData_IEM_SETTINGSAPI_LOADSETTINGS
     */
    public static function SetSettings(EventData_IEM_SETTINGSAPI_LOADSETTINGS $data)
    {
        $data->data->Schedule['reengagement'] = [
            'lastrun' => -1,
        ];

        $data->data->reengagement_options = [
            '0' => 'disabled',
            '1' => '1_minute',
            '15' => '15_minutes',
            '20' => '20_minutes',
            '30' => '30_minutes',
            '60' => '1_hour',
            '720' => '12_hours',
            '1440' => '1_day',
        ];

        $data->data->Areas[] = 'CRON_REENGAGEMENT';
    }

    /**
     * SetMenuItems
     * Adds itself to the navigation menu(s).
     *
     * If the user has access to "send email campaigns" in the email campaigns menu,
     * it tries to put "View Re Engagements" under that.
     * If they don't have access to that, then "View Re Engagements" goes at the bottom of the email campaigns menu.
     *
     * If the user has access to "email campaign stats" in the stats menu,
     * it tries to put "Re Engagement Stats" under that.
     * If they don't, then it goes at the bottom of the stats menu.
     *
     * @param EventData_IEM_SENDSTUDIOFUNCTIONS_GENERATEMENULINKS $data The current menu.
     *
     * @uses EventData_IEM_SENDSTUDIOFUNCTIONS_GENERATEMENULINKS
     */
    public static function SetMenuItems(EventData_IEM_SENDSTUDIOFUNCTIONS_GENERATEMENULINKS $data)
    {
        $self = new self();

        $news_reengage_test_menu = [
            'text' => GetLang('Addon_reengagement_Menu_ViewReEngagements'),
            'link' => $self->admin_url,
            'image' => '../addons/reengagement/images/sendreengageemail.png',
            'show' => [
                'CheckAccess' => 'HasAccess',
                'Permissions' => ['reengagement'],
            ],
            'description' => GetLang('Addon_reengagement_Menu_ViewReEngagements_Description'),
        ];

        $menuItems = $data->data;

        $slice_pos = false;

        foreach ($menuItems['autoresponder_button'] as $pos => $autoresponder_menu_item) {
            if ($autoresponder_menu_item['link'] == 'index.php?Page=TriggerEmails') {
                $slice_pos = $pos;
                break;
            }
        }

        /**
         * If the user has access to 'send' campaigns, we want re engagementing under that.
         */
        if ($slice_pos !== false) {
            $newsmenu_slice = array_slice($menuItems['autoresponder_button'], $slice_pos, 1);
            $newsmenu_slice[] = $news_reengage_test_menu;
            array_splice($menuItems['autoresponder_button'], $slice_pos, 1, $newsmenu_slice);
        } else {
            /**
             * They don't have access to send campaigns? Just put it at the end of the campaign menu.
             */
            $menuItems['autoresponder_button'][] = $news_reengage_test_menu;
        }

        $data->data = $menuItems;
    }

    /**
     * RegisterAddonPermissions
     * Registers permissions for this addon to create.
     * This allows an admin user to finely control which parts of re engagements a user can access.
     *
     * Creates the following permissions:
     * - create
     * - edit
     * - delete
     * - send
     * - stats
     *
     * @uses RegisterAddonPermission
     */
    public static function RegisterAddonPermissions()
    {
        self::LoadDescription('reengagement');
        $perms = [
            'reengagement' => [
                'addon_description' => GetLang('ReEngagement_Settings_Header'),
                'create' => [
                    'name' => GetLang('Addon_reengagement_Permission_Create'),
                ],
                'edit' => [
                    'name' => GetLang('Addon_reengagement_Permission_Edit'),
                ],
                'delete' => [
                    'name' => GetLang('Addon_reengagement_Permission_Delete'),
                ],
            ],
        ];
        self::RegisterAddonPermission($perms);
    }

    /**
     * Admin_Action_Default
     * This prints the 'manage page' which shows a list of re engagements that have been created.
     * If the user has access to create new ones, it also shows a 'create re engagement' button.
     *
     * @uses GetApi
     * @uses Reengagement_API::GetReEngagements
     */
    public function Admin_Action_Default()
    {
        $user = IEM::userGetCurrent();

        $this->template_system->Assign('AdminUrl', $this->admin_url, false);

        $create_button = '';
        $create_button_extra_msg = '';
        if ($user->HasAccess('reengagement', 'create')) {
            $create_button = $this->template_system->ParseTemplate('create_button', true);
            $create_button_extra_msg = GetLang('Addon_reengagement_CanCreateMessage');
        }
        $this->template_system->Assign('ReEngagement_Create_Button', $create_button, false);

        if ($user->HasAccess('reengagement', 'delete')) {
            $this->template_system->Assign('ShowDeleteButton', true);
        }

        $flash_messages = GetFlashMessages();

        $this->template_system->Assign('FlashMessages', $flash_messages, false);

        $api = $this->GetApi();
        $userid = $user->Get('userid');
        if ($user->Admin()) {
            $userid = 0;
        }

        $number_of_tests = $api->GetReEngagements($userid, [], true);

        if ($number_of_tests == 0) {
            $curr_template_dir = $this->template_system->GetTemplatePath();

            $this->template_system->SetTemplatePath(SENDSTUDIO_TEMPLATE_DIRECTORY);
            $GLOBALS['Success'] = sprintf(GetLang('Addon_reengagement_NoneAvailable'), $create_button_extra_msg);

            $msg = $this->template_system->ParseTemplate('successmsg', true);
            $this->template_system->SetTemplatePath($curr_template_dir);

            $this->template_system->Assign('Addon_reengagement_Empty', $msg, false);

            $this->template_system->ParseTemplate('manage_empty');
            return;
        }

        $this->template_system->Assign('ApplicationUrl', $this->application_url, false);

        if ($user->HasAccess('newsletters', 'send')) {
            $this->template_system->Assign('ScheduleSendPermission', true);
        }

        if ($user->HasAccess('reengagement', 'send')) {
            $this->template_system->Assign('SendPermission', true);
        }

        if ($user->HasAccess('reengagement', 'edit')) {
            $this->template_system->Assign('EditPermission', true);
        }

        if ($user->HasAccess('reengagement', 'create')) {
            $this->template_system->Assign('CopyPermission', true);
        }

        if ($user->HasAccess('reengagement', 'delete')) {
            $this->template_system->Assign('DeletePermission', true);
        }

        $paging = $this->SetupPaging($this->admin_url, $number_of_tests);
        $this->template_system->Assign('Paging', $paging, false);

        $perpage = $this->GetPerPage();

        $this->template_system->Assign('DateFormat', GetLang('DateFormat'));

        // paging always starts at '1' - so take one off so we get the right offset.
        $page_number = $this->GetCurrentPage() - 1;

        $sortdetails = $this->GetSortDetails();

        $tests = $api->GetReEngagements($userid, $sortdetails, false, $page_number, $perpage);

        foreach ($tests as $p => $test_details) {
            //$tests[$p]['tipheading'] = htmlspecialchars($tip, ENT_QUOTES, SENDSTUDIO_CHARSET);
            $tests[$p]['reengagename'] = htmlspecialchars((string) $test_details['reengagename'], ENT_QUOTES, SENDSTUDIO_CHARSET);
            $tests[$p]['campaign_names'] = htmlspecialchars((string) $test_details['campaign_names'], ENT_QUOTES, SENDSTUDIO_CHARSET);
        }

        $this->template_system->Assign('reEngagements', $tests);
        $this->template_system->ParseTemplate('manage_display');
    }

    /**
     * Admin_Action_Default
     * This prints the 'manage page' which shows a list of re engagements that have been created.
     * If the user has access to create new ones, it also shows a 'create re engagement' button.
     *
     * @uses GetApi
     * @uses Reengagement_API::GetReEngagements
     */
    public function Admin_Action_TransferList()
    {
        $user = IEM::userGetCurrent();
        $reengageid = $this->_getGETRequest('id', null);
        $urlAdd = '&Action=TransferList&id=' . $reengageid;
        $this->template_system->Assign('AdminUrl', $this->admin_url . $urlAdd, false);

        $flash_messages = GetFlashMessages();

        $this->template_system->Assign('FlashMessages', $flash_messages, false);

        $api = $this->GetApi();

        if ($user->Admin()) {
            $userid = 0;
        }

        $number_of_tests = $api->GetReEngagementsTransferList($reengageid, [], true);

        if ($number_of_tests == 0) {
            $curr_template_dir = $this->template_system->GetTemplatePath();

            $this->template_system->SetTemplatePath(SENDSTUDIO_TEMPLATE_DIRECTORY);
            $GLOBALS['Success'] = sprintf(GetLang('Addon_reengagement_Transfer_NoneAvailable'), '');

            $msg = $this->template_system->ParseTemplate('successmsg', true);
            $this->template_system->SetTemplatePath($curr_template_dir);

            $this->template_system->Assign('Addon_reengagement_Transfer_Empty', $msg, false);

            $this->template_system->ParseTemplate('transferlist_empty');
            return;
        }

        $this->template_system->Assign('ApplicationUrl', $this->application_url, false);

        if ($user->HasAccess('reengagement', 'edit')) {
            $this->template_system->Assign('EditPermission', true);
        }

        $paging = $this->SetupPaging($this->admin_url . $urlAdd, $number_of_tests);
        $this->template_system->Assign('Paging', $paging, false);

        $perpage = $this->GetPerPage();

        $this->template_system->Assign('DateFormat', GetLang('DateFormat'));

        // paging always starts at '1' - so take one off so we get the right offset.
        $page_number = $this->GetCurrentPage() - 1;

        $sortdetails = $this->GetSortDetails();

        $tests = $api->GetReEngagementsTransferList($reengageid, $sortdetails, false, $page_number, $perpage);

        foreach ($tests as $p => $test_details) {
            //$tests[$p]['tipheading'] = htmlspecialchars($tip, ENT_QUOTES, SENDSTUDIO_CHARSET);
            $tests[$p]['reengagename'] = htmlspecialchars((string) $test_details['reengagename'], ENT_QUOTES, SENDSTUDIO_CHARSET);
            $tests[$p]['campaign_names'] = htmlspecialchars((string) $test_details['campaign_names'], ENT_QUOTES, SENDSTUDIO_CHARSET);
        }

        $this->template_system->Assign('reEngagements', $tests);
        $this->template_system->ParseTemplate('transferlist_display');
    }

    /**
     * Admin_Action_Create
     * This handles creating a re engagement
     * If we are not posting a form (that is, we're just showing the form), then it shows the form and returns.
     *
     * If we are posting a form, then it will check & save the details posted into the database.
     * It also handles the "Copy" process as that is basically "creating" a new re engagement but based on an existing one.
     *
     * @uses GetApi
     * @uses GetUser
     * @uses _ShowForm
     */
    public function Admin_Action_Create()
    {
        $user = IEM::userGetCurrent();

        $copy = $this->_getGETRequest('Copy', null);
        if ($copy != null) {
            $copy_from = (int) $copy;
            $api = $this->GetApi();
            if (ReEngagement_API::OwnsReEngagements($user->Get('userid'), $copy) || $user->Admin()) {
                $new_name = $api->Copy($copy_from, $user->Get('userid'));
                if ($new_name) {
                    FlashMessage(sprintf(GetLang('Addon_reengagement_copy_successful'), htmlspecialchars((string) $new_name, ENT_QUOTES, SENDSTUDIO_CHARSET)), SS_FLASH_MSG_SUCCESS, $this->admin_url);
                } else {
                    FlashMessage(GetLang('Addon_reengagement_copy_unsuccessful'), SS_FLASH_MSG_ERROR, $this->admin_url);
                }
                return;
            }
            FlashMessage(GetLang('Addon_reengagement_copy_unsuccessful'), SS_FLASH_MSG_ERROR, $this->admin_url);
            return;
        }

        $reengagement_list = $this->_getPOSTRequest('reengagement_list', false);

        if (empty($_POST) || empty($reengagement_list)) {
            return $this->_ShowForm(null, $reengagement_list);
        }

        [$errors, $reengagedetails] = $this->_CheckFormPost();

        if ($errors) {
            return $this->_ShowForm();
        }

        $create_details = [
            'reengagename' => trim((string) $this->_getPOSTRequest('reengagename', null)),
            'reengage_typeof' => $this->_getPOSTRequest('reengage_typeof', null),
            'duration_type' => $this->_getPOSTRequest('duration_type', null),
            'reengagedetails' => $reengagedetails,
            'userid' => $user->Get('userid'),
        ];

        $reengage_api = $this->GetApi();
        $create_result = $reengage_api->Create($create_details);

        if (! $create_result) {
            FlashMessage(GetLang('Addon_reengagement_AddonNotCreated'), SS_FLASH_MSG_ERROR);
            return $this->_ShowForm();
        }

        $redirect_to = $this->admin_url;
        if (isset($_POST['Submit_Send'])) {
            // we are doing a 'Save and Send'
            $redirect_to .= '&Action=Send&id=' . $create_result;
        }
        FlashMessage(GetLang('Addon_reengagement_AddonCreated'), SS_FLASH_MSG_SUCCESS, $redirect_to);
    }

    /**
     * Admin_Action_Edit
     * This handles editing a re engagement
     * If we are not posting a form (that is, we're just showing the form), then it shows the form and returns.
     *
     * If we are posting a form, then it will check & updates the details posted into the database.
     *
     * @uses GetApi
     * @uses GetUser
     * @uses _ShowForm
     */
    public function Admin_Action_Edit()
    {
        $user = IEM::userGetCurrent();

        $reengage_api = $this->GetApi();
        $id = $this->_getGETRequest('id', null);

        if (! ReEngagement_API::OwnsReEngagements($user->Get('userid'), $id) && ! $user->Admin()) {
            FlashMessage(GetLang('NoAccess'), SS_FLASH_MSG_ERROR, $this->admin_url);
            return;
        }

        if (empty($_POST)) {
            if ($id != null) {
                return $this->_ShowForm($id);
            }
            FlashMessage(GetLang('Addon_reengagement_UnableToLoadReEngagement'), SS_FLASH_MSG_ERROR, $this->admin_url);
            return;
        }

        [$errors, $reengagedetails] = $this->_CheckFormPost();

        if ($errors) {
            return $this->_ShowForm($id);
        }

        $new_details = [
            'reengagename' => trim((string) $this->_getPOSTRequest('reengagename', null)),
            'reengage_typeof' => $this->_getPOSTRequest('reengage_typeof', null),
            'duration_type' => $this->_getPOSTRequest('duration_type', null),
            'reengagedetails' => $reengagedetails,
        ];

        $update_result = $reengage_api->Save($id, $new_details);

        if (! $update_result) {
            FlashMessage(GetLang('Addon_reengagement_ReengagementNotUpdated'), SS_FLASH_MSG_ERROR);
            return $this->_ShowForm($id);
        }

        $redirect_to = $this->admin_url;
        if (isset($_POST['Submit_Send'])) {
            // we are doing a 'Save and Send'
            $redirect_to .= '&Action=Send&id=' . $id;
        }
        FlashMessage(GetLang('Addon_reengagement_ReengagementUpdated'), SS_FLASH_MSG_SUCCESS, $redirect_to);
    }

    /**
     * Admin_Action_Delete
     * This function handles what happens when you delete a re engagement.
     * It checks you are doing a form post.
     * Then it grabs the api and passes the id(s) across to the api to delete.
     *
     * It checks what the api returns and creates a flash message based on the result.
     * Eg you can't delete a re engagement campaign while it's sending.
     *
     * After that, it returns you to the 'Manage' page.
     *
     * @uses ReEngagement_API::Delete
     * @see Admin_Action_Default
     * @uses GetApi
     */
    public function Admin_Action_Delete()
    {
        $user = IEM::userGetCurrent();
        $api = $this->GetApi();

        $reengage_ids = $this->_getPOSTRequest('reengageids', null);
        if (is_null($reengage_ids)) {
            $reengage_ids = $this->_getPOSTRequest('reengageid', null);
        }

        if (is_null($reengage_ids)) {
            FlashMessage(GetLang('Addon_reengagement_ChooseReengagementsToDelete'), SS_FLASH_MSG_ERROR, $this->admin_url);
            return;
        }

        $reengage_ids = ReEngagement_API::FilterIntSet($reengage_ids);

        if (! ReEngagement_API::OwnsReEngagements($user->Get('userid'), $reengage_ids) && ! $user->Admin()) {
            FlashMessage(GetLang('NoAccess'), SS_FLASH_MSG_ERROR, $this->admin_url);
            return;
        }

        $deleted = 0;
        $not_deleted = 0;

        foreach ($reengage_ids as $reengage_id) {
            $delete_success = $api->Delete($reengage_id);
            if ($delete_success) {
                $deleted++;
                continue;
            }
            $not_deleted++;
        }

        /**
         * If there are only "delete ok" messages, then just work out the number to show
         * and then create a flash message.
         */
        $url = $this->admin_url;
        if ($not_deleted > 0) {
            $url = null;
        }

        if ($deleted == 1) {
            FlashMessage(GetLang('Addon_reengagement_ReengagementDeleted_One'), SS_FLASH_MSG_SUCCESS, $url);
            if ($not_deleted == 0) {
                return;
            }
        }

        if ($deleted > 1) {
            FlashMessage(sprintf(GetLang('Addon_reengagement_ReengagementDeleted_Many'), self::PrintNumber($deleted)), SS_FLASH_MSG_SUCCESS, $url);
            if ($not_deleted == 0) {
                return;
            }
        }

        if ($not_deleted == 1) {
            $msg = GetLang('Addon_reengagement_ReengagementNotDeleted_One');
        } else {
            $msg = sprintf(GetLang('Addon_reengagement_ReengagementNotDeleted_Many'), self::PrintNumber($not_deleted));
        }
        FlashMessage($msg, SS_FLASH_MSG_ERROR, $this->admin_url);
    }

    /**
     * Admin_Action_Send
     * This handles the sending process.
     * It will handle the multistep process for sending a re engagement campaign.
     * The send method only works out which step you are up to in the send process and then passes it off
     * to other methods to handle the actual work and returns whatever the other methods do (which in most cases is nothing).
     *
     * Of course it checks the step exists before trying to run it,
     * otherwise trying to go to step 146 will cause an error.
     *
     * Everything passes through this method because of the way permissions work for addons.
     *
     * The send processing functionality is actually handled outside of this file in another.
     * The Admin_Action_Send method includes the second file and calls the methods in that file.
     * This helps separate functionality and also helps keep memory to a minimum
     * and also helps with performance.
     *
     * @uses Addons_reengagement_Send
     *
     * @return mixed If a method from the Addons_reengagement_Send class returns something, this method will return that value. Otherwise, it returns nothing.
     */
    public function Admin_Action_Send()
    {
        $user = IEM::userGetCurrent();
        $owner = $user->Get('userid');
        $step = 1;
        $pageStep = $this->_getGETRequest('Step', null);
        if ($pageStep != null) {
            $step = (int) $pageStep;
            if ($step <= 0) {
                $step = 1;
            }
        }

        //$method = 'Show_Send_Step_'.$step;

        $query = "UPDATE [|PREFIX|]reengagements SET jobstatus='i'";
        $query .= ' WHERE reengageid=' . (int) $_GET['id'] . " AND userid='" . $owner . "'";

        $this->db->Query($query);

        $updateJobs = "Update [|PREFIX|]jobs SET jobstatus='i' WHERE jobtype='reengagement' AND jobid=(SELECT jobid from [|PREFIX|]reengagements WHERE reengageid=" . (int) $_GET['id'] . " AND userid='" . $owner . "')";
        $this->db->Query($updateJobs);

        $redirect_to = $this->admin_url;
        //$redirect_to .= '&Page=Addons&Addon=reengagement';

        FlashMessage('ReEngagement Resume Successfully.', SS_FLASH_MSG_SUCCESS, $redirect_to);

        /*require_once dirname(__FILE__) . '/api/reengagement_send.php';

        $send = new Addons_reengagement_Send;

        if (method_exists($send, $method)) {
            return $send->$method();
        }

        /**
         * If the method doesn't exist, take the user back to the default action.
         */
        //FlashMessage(GetLang('Addon_reengagement_Send_InvalidReEngagement'), SS_FLASH_MSG_ERROR, $this->admin_url);
    }

    public function Admin_Action_Pause()
    {
        $user = IEM::userGetCurrent();
        $owner = $user->Get('userid');
        $query = "UPDATE [|PREFIX|]reengagements SET jobstatus='p'";
        $query .= ' WHERE reengageid=' . (int) $_GET['id'] . " AND userid='" . $owner . "'";

        $this->db->Query($query);

        $updateJobs = "Update [|PREFIX|]jobs SET jobstatus='p' WHERE jobtype='reengagement' AND jobid=(SELECT jobid from [|PREFIX|]reengagements WHERE reengageid=" . (int) $_GET['id'] . " AND userid='" . $owner . "')";
        $this->db->Query($updateJobs);

        $redirect_to = $this->admin_url;
        //$redirect_to .= '&Page=Addons&Addon=reengagement';

        FlashMessage('ReEngagement Pause Successfully.', SS_FLASH_MSG_SUCCESS, $redirect_to);
    }

    /**
     * PrintNumber
     * Helper function to print a number out using the right language variable for the thousands separator.
     * Used by sending .
     *
     * @param int $number The number to format.
     *
     * @return string Returns the number formatted per language variables.
     */
    public static function PrintNumber($number = 0)
    {
        return number_format((float) $number, 0, GetLang('NumberFormat_Dec'), GetLang('NumberFormat_Thousands'));
    }

    /**
     * TimeDifference
     * Returns the time difference in an easy format / unit system (eg how many seconds, minutes, hours etc).
     *
     * @param int $timedifference Time difference as an integer to transform.
     *
     * @return string Time difference plus units.
     */
    public function TimeDifference($timedifference)
    {
        if ($timedifference < 60) {
            if ($timedifference == 1) {
                $timechange = GetLang('TimeTaken_Seconds_One');
            } else {
                $timechange = sprintf(GetLang('TimeTaken_Seconds_Many'), self::PrintNumber($timedifference));
            }
        }

        if ($timedifference >= 60 && $timedifference < 3600) {
            $num_mins = floor($timedifference / 60);

            $secs = floor($timedifference % 60);

            if ($num_mins == 1) {
                $timechange = GetLang('TimeTaken_Minutes_One');
            } else {
                $timechange = sprintf(GetLang('TimeTaken_Minutes_Many'), self::PrintNumber($num_mins));
            }

            if ($secs > 0) {
                $timechange .= ', ' . sprintf(GetLang('TimeTaken_Seconds_Many'), self::PrintNumber($secs));
            }
        }

        if ($timedifference >= 3600) {
            $hours = floor($timedifference / 3600);
            $mins = floor($timedifference % 3600) / 60;

            if ($hours == 1) {
                if ($mins == 0) {
                    $timechange = GetLang('TimeTaken_Hours_One');
                } else {
                    $timechange = sprintf(GetLang('TimeTaken_Hours_One_Minutes'), self::PrintNumber($mins));
                }
            }

            if ($hours > 1) {
                if ($mins == 0) {
                    $timechange = sprintf(GetLang('TimeTaken_Hours_Many'), self::PrintNumber($hours));
                } else {
                    $timechange = sprintf(GetLang('TimeTaken_Hours_Many_Minutes'), self::PrintNumber($hours), self::PrintNumber($mins));
                }
            }
        }

        // can expand this futher to years/months etc - the schedule_manage file has it all done in javascript.

        return $timechange;
    }

    /**
     * DeleteNewsletters
     * This is used to check it's ok to delete an email campaign/newsletter from the 'manage email campaigns' page.
     * It's called from inside the newsletter api so if someone tries to delete via the api, this still gets triggered.
     *
     * The data passed in contains two elements:
     * - newsletterid (the id to check isn't being used)
     * - status - a true/false flag about whether it's ok to delete the newsletter or not.
     *
     * @param EventData_IEM_NEWSLETTERSAPI_DELETE $data This contains an array of the newsletterid to check and also a "status" (true/false)
     *
     * @uses Reengagement_API::GetCampaignsUsed
     * @uses EventData_IEM_NEWSLETTERSAPI_DELETE
     */
    public static function DeleteNewsletters(EventData_IEM_NEWSLETTERSAPI_DELETE $data)
    {
        if (! isset($data->newsletterid) || ! isset($data->status)) {
            $data->status = false;
            return;
        }

        require_once __DIR__ . '/api/reengagement.php';
        $api = new Reengagement_API();

        $campaigns_used = $api->GetCampaignsUsed($data->newsletterid);

        if (empty($campaigns_used)) {
            $data->status = true;
            return;
        }

        /**
         * In case we're processing multiple newsletters,
         * kill off the previous messages (if any)
         * to make sure we only set one message.
         */
        GetFlashMessages();

        FlashMessage(GetLang('Addon_reengagement_CampaignsNotDeleted_UsedByReEngagement'), SS_FLASH_MSG_ERROR);
        $data->status = false;
    }

    /**
     * ManageNewsletters
     * This event hook does two things:
     *
     * Adds any flash messages to the current message being displayed (passed in).
     * This is mainly used if newsletters are deleted but they can't be because
     * they are used by re engagement campaigns.
     *
     * Adds a 'Create Re Engagement' button to the page where a use can select two
     * or more campaigns and create a re engagement with these campaigns already
     * selected.
     *
     * @param EventData_IEM_NEWSLETTERS_MANAGENEWSLETTERS $data The current message that's going to be displayed.
     */
    /*public static function ManageNewsletters(EventData_IEM_NEWSLETTERS_MANAGENEWSLETTERS $data)
    {
        // Append any flash messages
        $data->displaymessage .= GetFlashMessages();

        // Append 'Create Re Engagement' button
        $user =& IEM::userGetCurrent();;
        if (!$user->HasAccess('reengagement', 'create')) {
            return;
        }

        $addon = new self();
        $addon->GetTemplateSystem();

        $create_button = $addon->template_system->ParseTemplate('create_button', true);

        $addon->template_system->Assign('alert_msg', GetLang('Addon_reengagement_ChooseList'));
        $addon->template_system->Assign('url', $addon->admin_url . '&Action=Create');
        $button_js = $addon->template_system->ParseTemplate('newsletter_button', true);

        if (!isset($GLOBALS['Newsletters_ExtraButtons'])) {
            $GLOBALS['Newsletters_ExtraButtons'] = '';
        }
        $GLOBALS['Newsletters_ExtraButtons'] .= $create_button;
        $GLOBALS['Newsletters_ExtraButtons'] .= $button_js;
    }*/

    /**
     * GenerateJobListQuery
     * Generates an sql query which gets included when the "scheduled sending" page job list is generated.
     * It's a query by itself but becomes part of a subquery in the calling code.
     *
     * The data passed in contains a few elements:
     * - listids (lists to compare against - ie the lists the user has access to)
     * - countonly - whether the generated query should be for a "countonly" query (for paging)
     * - subqueries - the existing subqueries array
     *
     * If it's a countonly query, then just return the jobid's for the jobtype of 'reengagement'.
     * Since it's going to be part of a subquery, we just need the job id's and the calling code
     * works out the actual count from the generated subquery/subqueries.
     *
     * Basically it works out a query like this for a countonly query:
     * <code>
     * 	select jobid from jobs j inner join jobs_lists jl on (j.jobid=jl.jobid) inner join reengagements s on (s.reengageid=j.fkid)
     * 	where jl.listid in ($listids) and j.jobtype='reengagement'
     * </code>
     *
     * The full query has to return the following columns:
     * - jobid
     * - jobtype ('reengagement')
     * - job description ('re engagement campaign')
     * - the lists the job is sending to
     * - "null" for the subject (since there are potentially multiple subjects)
     * - "null" as the newsletter id (since there are multiple newsletter id's for a re engagement campaign)
     *
     * @param EventData_IEM_JOBSAPI_GETJOBLIST $data The existing data which includes whether the query should be a countonly query, which lists to look at, and the existing subqueries.
     *
     * @uses EventData_IEM_JOBSAPI_GETJOBLIST
     */
    public static function GenerateJobListQuery(EventData_IEM_JOBSAPI_GETJOBLIST $data)
    {
        $listids = $data->listids;
        if (empty($listids)) {
            $listids = ['0'];
        }

        $from_clause = '
			FROM
				[|PREFIX|]jobs j INNER JOIN [|PREFIX|]jobs_lists jl ON (j.jobid=jl.jobid)
				INNER JOIN [|PREFIX|]reengagements s ON (s.reengageid=j.fkid)
			WHERE
				jl.listid in (' . implode(',', $listids) . ") AND
				j.jobtype='reengagement' AND
				j.fktype='reengagement'
			";

        if ($data->countonly) {
            $query = '
				SELECT
					j.jobid
					' . $from_clause;
            $data->subqueries[] = $query;
            return;
        }

        $listname_query = "(SELECT CONCAT('\'', GROUP_CONCAT(name SEPARATOR '\',\''), '\'') FROM [|PREFIX|]lists l INNER JOIN [|PREFIX|]jobs_lists jl ON (l.listid=jl.listid) WHERE jl.jobid=j.jobid AND jl.listid IN (" . implode(',', $listids) . '))';
        if (SENDSTUDIO_DATABASE_TYPE == 'pgsql') {
            $listname_query = "'\'' || array_to_string(array(SELECT l.name FROM [|PREFIX|]lists l INNER JOIN [|PREFIX|]jobs_lists jl ON (l.listid=jl.listid) WHERE jl.jobid=j.jobid AND jl.listid IN (" . implode(',', $listids) . ")), '\',\'') || '\''";
        }

        $query = "
			SELECT
				j.jobid,
				'reengagement' AS jobtype,
				'" . GetLang('Addon_reengagement_Schedule_Description') . "' AS jobdescription,
				reengagename AS name,
				" . $listname_query . ' AS listname,
				null AS subject,
				null AS newsletterid ' . $from_clause;

        if (SENDSTUDIO_DATABASE_TYPE == 'mysql') {
            $query .= ' GROUP BY j.jobid';
        }

        $data->subqueries[] = $query;
    }

    /**
     * PauseSchedule
     * If a scheduled job is a "reengagement" type, then this will pause the schedule
     * and set an appropriate message in the "GLOBALS[Message]" field.
     *
     * If it's not a reengagement job, then this will just return.
     *
     * @param EventData_IEM_SCHEDULE_PAUSEJOB $data The data array contains the jobtype and the jobid to process.
     *
     * @uses InterspireEventData::preventDefault()
     * @uses Reengagement_Send_API::PauseJob()
     */
    public static function PauseSchedule(EventData_IEM_SCHEDULE_PAUSEJOB $data)
    {
        $jobinfo = &$data->jobrecord;
        if ($jobinfo['jobtype'] != 'reengagement') {
            return;
        }

        $data->preventDefault();

        require_once __DIR__ . '/api/reengagement_send.php';
        $send_api = new Reengagement_Send_API();

        $paused = $send_api->PauseJob($jobinfo['jobid']);

        if ($paused) {
            FlashMessage(GetLang('Addon_reengagement_Send_Paused_Success'), SS_FLASH_MSG_SUCCESS);
        } else {
            FlashMessage(GetLang('Addon_reengagement_Send_Paused_Failure'), SS_FLASH_MSG_ERROR);
        }

        $GLOBALS['Message'] = GetFlashMessages();
    }

    /**
     * ResumeSchedule
     * If a scheduled job is a "reengagement" type, then this will resume the schedule
     * and set an appropriate message in the "GLOBALS[Message]" field.
     *
     * If it's not a reengagement job, then this will just return.
     *
     * @param EventData_IEM_SCHEDULE_RESUMEJOB $data The data array contains the jobtype and the jobid to process.
     *
     * @uses InterspireEventData::preventDefault()
     * @uses Reengagement_Send_API::ResumeJob()
     */
    public static function ResumeSchedule(EventData_IEM_SCHEDULE_RESUMEJOB $data)
    {
        $jobinfo = &$data->jobrecord;
        if ($jobinfo['jobtype'] != 'reengagement') {
            return;
        }

        $data->preventDefault();

        require_once __DIR__ . '/api/reengagement_send.php';
        $send_api = new Reengagement_Send_API();

        $resumed = $send_api->ResumeJob($jobinfo['jobid']);
        if ($resumed) {
            FlashMessage(GetLang('Addon_reengagement_Send_Resumed_Success'), SS_FLASH_MSG_SUCCESS);
        } else {
            FlashMessage(GetLang('Addon_reengagement_Send_Resumed_Failure'), SS_FLASH_MSG_ERROR);
        }

        $GLOBALS['Message'] = GetFlashMessages();
    }

    /**
     * DeleteSchedules
     * If scheduled items are going to be deleted, this processes the jobs it needs to.
     *
     * The data passed in contains lots of data:
     * jobids - the job id's that need to be processed
     * message - the current success/failure message
     * success - the current success counter (how many jobs have successfully been deleted previous to getting here)
     * failure - the current failure counter (how many jobs have not successfully been deleted previous to getting here)
     *
     * Any non-"reengagement" job types are skipped
     * Any "in progress" reengagement jobs are skipped and the failure counter is incremented
     * Any jobs that can be deleted are - as well as figuring out whether a job needs to give back any email credits.
     *
     * Any appropriate messages are added to the "message" item in the passed in array.
     *
     * @param EventData_IEM_SCHEDULE_DELETEJOBS $data The data array containing the jobs to process, the current message, success and failure counts.
     *
     * @uses Jobs_API::LoadJob()
     * @uses Stats_API::DeleteUserStats()
     * @uses Stats_API::MarkNewsletterFinished()
     * @uses Reengagement_Send_API::DeleteJob()
     * @uses User_API::ReduceEmails()
     * @uses EventData_IEM_SCHEDULE_DELETEJOBS
     */
    public static function DeleteSchedules(EventData_IEM_SCHEDULE_DELETEJOBS $data)
    {
        $jobids = &$data->jobids;
        $message = &$data->Message;
        $success = &$data->success;
        $failure = &$data->failure;

        $user = IEM::userGetCurrent();

        require_once SENDSTUDIO_API_DIRECTORY . '/jobs.php';
        require_once SENDSTUDIO_API_DIRECTORY . '/stats.php';
        require_once __DIR__ . '/api/reengagement_send.php';

        $jobapi = new Jobs_API();
        $stats_api = new Stats_API();
        $send_api = new Reengagement_Send_API();

        foreach ($jobids as $p => $jobid) {
            $jobinfo = $jobapi->LoadJob($jobid);

            if (empty($jobinfo)) {
                continue;
            }

            if ($jobinfo['jobtype'] !== 'reengagement') {
                continue;
            }

            if ($jobinfo['jobstatus'] == 'i') {
                $failure++;
                unset($jobids[$p]);
                continue;
            }

            $statids = [];
            if (isset($jobinfo['jobdetails']['Stats'])) {
                $statids = array_values($jobinfo['jobdetails']['Stats']);
            }

            /**
             * If there are no stats, then the send hasn't started yet.
             * So just credit the user back with the number of emails they were trying to send.
             * Use 'ReduceEmails' to re-add the credits by using a double negative :)
             */
            if (empty($statids) && $jobinfo['jobstatus'] == 'w') {
                $stats_api->DeleteUserStats($jobinfo['ownerid'], $jobid);
                $user->ReduceEmails(-(int) $jobinfo['jobdetails']['SendSize']);
            }

            /**
             * If a send is started (ie it has stats),
             * but is not completed,
             * We need to mark it as complete.
             *
             * This also credits a user back if they have any limits in place.
             *
             * This needs to happen before we delete the 'job' from the database
             * as deleting the job cleans up the queues/unsent items.
             */
            if (! empty($statids) && $jobinfo['jobstatus'] != 'c') {
                $stats_api->MarkNewsletterFinished($statids, $jobinfo['jobdetails']['SendSize']);

            // Credits needs to be returned too whenever the job is canceled AFTER it has been scheduled, but before it was sent
            } elseif ($jobinfo['jobstatus'] != 'c') {
                $stats_api->RefundFixedCredit($jobid);
            }

            $result = $send_api->DeleteJob($jobid);

            if ($result) {
                $success++;
            } else {
                $failure++;
            }
            unset($jobids[$p]);
        }

        /**
         * Only failure messages get added to the message stack.
         * Successful deletes are handled in the calling code
         * in case:
         * - a non-addon deletes an item
         * - other addons delete their own items
         */
        if ($failure > 0) {
            if ($failure == 1) {
                FlashMessage(GetLang('Addon_reengagement_Schedule_JobDeleteFail'), SS_FLASH_MSG_ERROR);
            } else {
                FlashMessage(sprintf(GetLang('Addon_reengagement_Schedule_JobsDeleteFail'), self::PrintNumber($failure)), SS_FLASH_MSG_SUCCESS);
            }
        }

        $message .= GetFlashMessages();
    }

    /**
     * EditSchedule
     * This prints out the "edit schedule" page
     * which in reality isn't much different to a normal edit schedule page except:
     * - list multiple campaigns (each name being clickable to show a preview of that campaign)
     *
     * The data passed in contains the "jobdetails" array from the job being edited.
     * That array contains the reengageid - which can then be used to work out the campaigns etc being used.
     *
     * If it's not a 'reengagement' job type, this function/method just returns and doesn't do anything.
     *
     * @param EventData_IEM_SCHEDULE_EDITJOB $data The array of jobdetails for the scheduled event being edited.
     *
     * @uses InterspireEventData::preventDefault
     * @uses User_API::GetLists
     * @uses User_API::GetSegmentList
     * @uses SendStudio_Functions::CreateDateTimeBox
     */
    public static function EditSchedule(EventData_IEM_SCHEDULE_EDITJOB $data)
    {
        $jobinfo = &$data->jobrecord;

        if (empty($jobinfo) || ! isset($jobinfo['jobtype'])) {
            FlashMessage(GetLang('Addon_reengagement_Schedule_JobInvalid'), SS_FLASH_MSG_ERROR, self::application_url . 'index.php?Page=Schedule');
            return;
        }

        if ($jobinfo['jobtype'] != 'reengagement') {
            return;
        }

        $data->preventDefault();

        $self = new self();
        $user = IEM::userGetCurrent();

        $reengageid = $jobinfo['jobdetails']['reengageid'];

        /**
         * Check for messages.
         * If there are no flash messages, maybe it's being set in the "GLOBALS[Message]" string instead
         * by the admin/functions/schedule.php file.
         * Check that too :)
         */
        $flash_messages = GetFlashMessages();
        if (isset($GLOBALS['Message'])) {
            $flash_messages .= $GLOBALS['Message'];
        }
        $self->template_system->Assign('FlashMessages', $flash_messages, false);

        $self->template_system->Assign('Jobid', $jobinfo['jobid']);

        require_once SENDSTUDIO_API_DIRECTORY . '/newsletters.php';
        require_once SENDSTUDIO_API_DIRECTORY . '/jobs.php';
        require_once SENDSTUDIO_API_DIRECTORY . '/stats.php';

        $job_api = new Jobs_API();
        $stats_api = new Stats_API();
        $news_api = new Newsletters_API();

        $reengageapi = $self->GetApi();
        $reengagedetails = $reengageapi->Load($reengageid);

        $sendtype = $jobinfo['jobdetails']['sendingto']['sendtype'];

        $sending_to = $jobinfo['jobdetails']['sendingto']['sendids'];

        $sendinglist = [];

        if ($sendtype == 'list') {
            $user_lists = $user->GetLists();
            foreach ($sending_to as $listid) {
                $sendinglist[$listid] = htmlspecialchars((string) $user_lists[$listid]['name'], ENT_QUOTES, SENDSTUDIO_CHARSET);
            }
        }

        if ($sendtype == 'segment') {
            $user_segments = $user->GetSegmentList();
            foreach ($sending_to as $segmentid) {
                $sendinglist[$segmentid] = htmlspecialchars((string) $user_segments[$segmentid]['segmentname'], ENT_QUOTES, SENDSTUDIO_CHARSET);
            }
        }

        /**
         * Get the sendstudio functions file to create the date/time box.
         */
        require_once SENDSTUDIO_FUNCTION_DIRECTORY . '/sendstudio_functions.php';

        /**
         * also need to load the 'send' language file so it can put in the names/descriptions.
         */
        require_once SENDSTUDIO_LANGUAGE_DIRECTORY . '/default/send.php';
        $ssf = new SendStudio_Functions();
        $timebox = $ssf->CreateDateTimeBox($jobinfo['jobtime'], false, 'datetime', true);

        $self->template_system->Assign('ApplicationUrl', $self->application_url, false);

        $self->template_system->Assign('ScheduleTimeBox', $timebox, false);

        $self->template_system->Assign('SendType', $sendtype);

        $self->template_system->Assign('campaigns', $reengagedetails['reengagement_list']);

        $self->template_system->Assign('sendinglist', $sendinglist);

        $self->template_system->ParseTemplate('schedule_edit');
    }

    /**
     * GetJobStatus
     * Returns a "job status" message explaining what the job status means.
     * It looks for specific status codes only this addon uses.
     *
     * @param EventData_IEM_JOBSAPI_GETJOBSTATUS $data The current data which contains the 'jobstatus' code and a message placeholder.
     *
     * @uses jobstatuscodes
     *
     * @uses EventData_IEM_JOBSAPI_GETJOBSTATUS
     */
    public static function GetJobStatus(EventData_IEM_JOBSAPI_GETJOBSTATUS $data)
    {
        /**
         * If it's a status code used by this addon,
         * set the message, stop the event propogation in the calling code and return.
         *
         * If it's not a status code used by this addon, do nothing.
         */
        if (in_array($data->jobstatus, self::$jobstatuscodes)) {
            $data->preventDefault();
            $rand_tipid = random_int(1, 100000);
            $heading = GetLang('Addon_reengagement_Schedule_JobStatus_Timeout');
            $message = GetLang('Addon_reengagement_Schedule_JobStatus_Timeout_TipDetails');
            $tip_message = '<span class="HelpText" onMouseOut="HideHelp(\'reengageDisplayTimeout' . $rand_tipid . '\');" onMouseOver="ShowQuickHelp(\'reengageDisplayTimeout' . $rand_tipid . '\', \'' . $heading . '\', \'' . $message . '\');">' . $heading . '</span><div id="reengageDisplayTimeout' . $rand_tipid . '" style="display: none;"></div>';
            $data->statusmessage = $tip_message;
        }
    }

    /**
     * GetApi
     * An easy way to include the reengagement api file which does all of the database queries.
     * This is marked as protected so the sub-classes (for sending) can use it.
     *
     * @param string $api Which api to get. It defaults to the 'reengagement' api but can be passed 'reengagement_send' to get that api instead.
     *
     * @return object|false Returns false if the api name is invalid. Otherwise returns the appropriate api object ready for use.
     */
    protected function GetApi($api = 'ReEngagement')
    {
        $path = $this->addon_base_directory . $this->addon_id . '/api/' . strtolower($api) . '.php';
        if (! is_file($path)) {
            return false;
        }

        require_once $path;
        $class = $api . '_API';
        return new $class();
    }

    /**
     * getDateFormat
     * Obtains the date format used for re engagementing.
     *
     * @return string The date format ready to be fed to the date() function.
     */
    protected static function getDateFormat()
    {
        return GetLang('DateFormat') . ' ' . GetLang('Stats_TimeFormat');
    }

    /**
     * _ShowForm
     * This is a private function that both creating a re engagement and editing a re engagement uses.
     * If an id is passed through (ie when editing), it will fill in the relevant details in the form for processing.
     * Based on whether an id is passed through and whether a test is successfully loaded, it will also change the form action.
     *
     * It uses the newsletters api to get the newsletters/email campaigns the user has access to so they are shown in the dropdown list.
     * It only shows "live" (active) newsletters the user has access to.
     * If the user creating/editing a re engagement is an admin user, then all live newsletters are shown.
     * If they are not an admin user, then only the live newsletters the user created are shown.
     *
     * @param int $reengageid The re engagement id to load for editing if applicable. If none is supplied, then we assume you're creating a new re engagement.
     * @param array $chosen_list A list of campaign ids to select by default when displaying the form. This will be overridden if $reengageid is provided.
     *
     * @uses GetUser
     * @uses Newsletters_API::GetNewsletters
     * @uses GetApi
     */
    private function _ShowForm($reengageid = null, $chosen_list = [])
    {
        $user = IEM::userGetCurrent();

        $admin_url = $this->admin_url;
        $action = $this->_getGETRequest('Action', null);
        $show_send = true;

        if (! empty($chosen_list)) {
            $chosen_list = array_map('intval', $chosen_list);
            foreach ($chosen_list as $k => $v) {
                unset($chosen_list[$k]);
                $chosen_list[$v] = $v;
            }
        }

        $formtype = 'create';
        $reengage_typeof = '';
        $user_lists = $user->GetLists();
        $this->template_system->Assign('availableLists', $user_lists);

        $this->template_system->Assign('Minimum_Element', self::minimum_element);
        $this->template_system->Assign('Maximum_Element', self::maximum_element);

        if ($reengageid !== null) {
            $reengageid = (int) $reengageid;
            if ($reengageid <= 0) {
                FlashMessage(GetLang('Addon_reengagement_UnableToLoadReEngagement'), SS_FLASH_MSG_ERROR, $this->admin_url);
                return;
            }

            $reengage_api = $this->GetApi();
            $reengage_details = $reengage_api->Load($reengageid);

            if (empty($reengage_details)) {
                FlashMessage(GetLang('Addon_reengagement_UnableToLoadReEngagement'), SS_FLASH_MSG_ERROR, $this->admin_url);
                return;
            }

            if (! isset($reengage_details['reengageid'])) {
                FlashMessage(GetLang('Addon_reengagement_UnableToLoadReEngagement'), SS_FLASH_MSG_ERROR, $this->admin_url);
                return;
            }

            $jobstatus = $reengage_details['jobstatus'];
            if (in_array($jobstatus, $reengage_api->GetSendingJobStatusCodes())) {
                FlashMessage(GetLang('Addon_reengagement_UnableToEdit_SendInProgress'), SS_FLASH_MSG_ERROR, $this->admin_url);
                return;
            }

            $formtype = 'edit';
            $this->template_system->Assign('reengageid', $reengageid);
            $this->template_system->Assign('reengagename', htmlspecialchars((string) $reengage_details['reengagename'], ENT_QUOTES, SENDSTUDIO_CHARSET));
            $this->template_system->Assign('reengage_typeof', htmlspecialchars((string) $reengage_details['reengage_typeof'], ENT_QUOTES, SENDSTUDIO_CHARSET));
            $this->template_system->Assign('max_numberofdays', htmlspecialchars((string) $reengage_details['reengagedetails']['max_numberofdays'], ENT_QUOTES, SENDSTUDIO_CHARSET));
            $this->template_system->Assign('is_removemail', htmlspecialchars((string) $reengage_details['reengagedetails']['is_removemail'], ENT_QUOTES, SENDSTUDIO_CHARSET));
            $this->template_system->Assign('duration_type', htmlspecialchars((string) $reengage_details['duration_type'], ENT_QUOTES, SENDSTUDIO_CHARSET));
            $this->template_system->Assign('reengagement_list', explode(',', htmlspecialchars((string) $reengage_details['reengagedetails']['reengagement_list'], ENT_QUOTES, SENDSTUDIO_CHARSET)));
            $this->template_system->Assign('contactlist', explode(',', htmlspecialchars((string) $reengage_details['reengagedetails']['contactlist'], ENT_QUOTES, SENDSTUDIO_CHARSET)));
            if ($reengage_details['duration_type'] > 1) {
                if ($reengage_details['duration_type'] == 2) {
                    $this->template_system->Assign('duration_once', htmlspecialchars((string) $reengage_details['reengagedetails']['duration_once'], ENT_QUOTES, SENDSTUDIO_CHARSET));
                } elseif ($reengage_details['duration_type'] == 3) {
                    //echo $reengage_details['reengagedetails']['duration_hourly']; die;
                    $this->template_system->Assign('duration_hourly', htmlspecialchars((string) $reengage_details['reengagedetails']['duration_hourly'], ENT_QUOTES, SENDSTUDIO_CHARSET));
                } elseif ($reengage_details['duration_type'] == 4) {
                    $this->template_system->Assign('duration_daily', htmlspecialchars((string) $reengage_details['reengagedetails']['duration_daily'], ENT_QUOTES, SENDSTUDIO_CHARSET));
                } elseif ($reengage_details['duration_type'] == 5) {
                    $this->template_system->Assign('duration_weekly', explode(',', htmlspecialchars((string) $reengage_details['reengagedetails']['duration_weekly'], ENT_QUOTES, SENDSTUDIO_CHARSET)));
                    $this->template_system->Assign('duration_weekly_time', htmlspecialchars((string) $reengage_details['reengagedetails']['duration_weekly_time'], ENT_QUOTES, SENDSTUDIO_CHARSET));
                }
            }
            $chosen_list = $reengage_details['reengagement_list'];
            $max_numberofdays = $reengage_details['reengagedetails']['max_numberofdays'];

            $show_send = (empty($reengage_details['jobstatus']) || $reengage_details['jobstatus'] == 'c');
        } else {
            $this->template_system->Assign('reengagename', htmlspecialchars((string) $_POST['reengagename'], ENT_QUOTES, SENDSTUDIO_CHARSET));
            $this->template_system->Assign('reengage_typeof', htmlspecialchars((string) $_POST['reengage_typeof'], ENT_QUOTES, SENDSTUDIO_CHARSET));
            $this->template_system->Assign('max_numberofdays', htmlspecialchars((string) $_POST['max_numberofdays'], ENT_QUOTES, SENDSTUDIO_CHARSET));
            $this->template_system->Assign('is_removemail', htmlspecialchars((string) $_POST['is_removemail'], ENT_QUOTES, SENDSTUDIO_CHARSET));
            $this->template_system->Assign('duration_type', htmlspecialchars((string) $_POST['duration_type'], ENT_QUOTES, SENDSTUDIO_CHARSET));
            $this->template_system->Assign('reengagement_list', $_POST['reengagement_list']);
            $this->template_system->Assign('contactlist', $_POST['contactlist']);

            if ($_POST['duration_type'] > 1) {
                if ($_POST['duration_type'] == 2) {
                    $this->template_system->Assign('duration_once', htmlspecialchars(date('Y-m-d h:i:s A', strtotime($_POST['duration_once_date'] . ' ' . $_POST['duration_once_hr'] . ':' . $_POST['duration_once_minutes'] . ':00' . $_POST['duration_once_ampm'])), ENT_QUOTES, SENDSTUDIO_CHARSET));
                } elseif ($_POST['duration_type'] == 3) {
                    $this->template_system->Assign('duration_hourly', htmlspecialchars((string) $_POST['duration_hourly'], ENT_QUOTES, SENDSTUDIO_CHARSET));
                } elseif ($_POST['duration_type'] == 4) {
                    $this->template_system->Assign('duration_daily', htmlspecialchars(date('Y-m-d h:i:s A', strtotime(date('Y-m-d') . ' ' . $_POST['duration_daily_hr'] . ':' . $_POST['duration_daily_minutes'] . ':00' . $_POST['duration_daily_ampm'])), ENT_QUOTES, SENDSTUDIO_CHARSET));
                } elseif ($_POST['duration_type'] == 5) {
                    $this->template_system->Assign('duration_weekly', $_POST['duration_weekly']);
                    $this->template_system->Assign('duration_weekly_time', htmlspecialchars(date('Y-m-d h:i:s A', strtotime(date('Y-m-d') . ' ' . $_POST['duration_weekly_hr'] . ':' . $_POST['duration_weekly_minutes'] . ':00' . $_POST['duration_weekly_ampm'])), ENT_QUOTES, SENDSTUDIO_CHARSET));
                }
            }
        }

        $this->template_system->Assign('FormType', $formtype);

        $this->template_system->Assign('TemplateUrl', $this->template_url, false);
        $this->template_system->Assign('BaseAdminUrl', $this->admin_url, false);
        $this->template_system->Assign('AdminUrl', $admin_url, false);
        $this->template_system->Assign('ShowSend', $show_send);

        $flash_messages = GetFlashMessages();

        $this->template_system->Assign('FlashMessages', $flash_messages, false);

        require_once(SENDSTUDIO_API_DIRECTORY . '/newsletters.php');
        $news_api = new Newsletters_API();

        $owner = $user->Get('userid');
        if ($user->Admin()) {
            $owner = 0;
        }

        $campaigns = $news_api->GetLiveNewsletters($owner);

        if (! empty($chosen_list)) {
            foreach ($campaigns as $row => $details) {
                $id = $details['newsletterid'];
                $campaigns[$row]['selected'] = false;
                if (isset($chosen_list[$id])) {
                    $campaigns[$row]['selected'] = true;
                }
            }
        }

        $this->template_system->Assign('action', $action);
        $this->template_system->Assign('campaigns', $campaigns);

        $this->template_system->ParseTemplate('reengagement_form');
    }

    /**
     * _CheckFormPost
     * This is used by both when posting a new re engagement and also when posting an existing re engagement.
     *
     * It checks all fields are filled in.
     * If they aren't, it generates the appropriate flash messages and returns.
     *
     * If there are no errors, the array that gets returned contains all of the reengage details necessary
     *
     * @uses minimum_element
     * @uses maximum_element
     *
     * @return array Returns an array containing whether there were any errors and also a complete reengagedetails array which can then be passed off to the api for creating/saving.
     */
    private function _CheckFormPost()
    {
        $errors = false;

        $fields = [
            'reengagename' => 'FillInField_RssName',
            'reengage_typeof' => 'ChooseTypeOfEngage',
            'duration_type' => 'ChooseRssDuration',
        ];

        $reengagedetails = [];

        foreach ($fields as $fieldname => $lang_var) {
            $field = $this->_getPOSTRequest($fieldname, null);
            if ($field == null) {
                FlashMessage(GetLang('Addon_reengagement_' . $lang_var), SS_FLASH_MSG_ERROR);
                $errors = true;
                continue;
            }
        }

        if (isset($_POST['max_numberofdays'])) {
            $reengagedetails['max_numberofdays'] = 0;
            if (isset($_POST['max_numberofdays'])) {
                $reengagedetails['max_numberofdays'] = (int) $_POST['max_numberofdays'];
            }

            if ($reengagedetails['max_numberofdays'] < self::minimum_element || $reengagedetails['max_numberofdays'] > self::maximum_element) {
                FlashMessage(sprintf(GetLang('Addon_reengagement_Max_Numberof_Days'), self::minimum_element, self::maximum_element), SS_FLASH_MSG_ERROR);
                $errors = true;
            }
        }

        if (isset($_POST['is_removemail'])) {
            $reengagedetails['is_removemail'] = 0;
            if (isset($_POST['is_removemail'])) {
                $reengagedetails['is_removemail'] = $_POST['is_removemail'];
            }
        }

        if (isset($_POST['contactlist'])) {
            $reengagedetails['contactlist'] = implode(',', $_POST['contactlist']);
        }
        if ($reengagedetails['contactlist'] == '') {
            FlashMessage(GetLang('Addon_reengagement_Contact_List_NotExist'), SS_FLASH_MSG_ERROR);
            $errors = true;
        }
        if (isset($_POST['reengagement_list'])) {
            $reengagedetails['reengagement_list'] = implode(',', $_POST['reengagement_list']);
        }
        if ($reengagedetails['reengagement_list'] == '') {
            FlashMessage(GetLang('Addon_reengagement_Contact_List_NotExist'), SS_FLASH_MSG_ERROR);
            $errors = true;
        }

        if (isset($_POST['duration_type']) && $_POST['duration_type'] > 1) {
            if ($_POST['duration_type'] == 2) {
                if (isset($_POST['duration_once_date']) && isset($_POST['duration_once_hr']) && isset($_POST['duration_once_minutes']) && isset($_POST['duration_once_ampm'])) {
                    $reengagedetails['duration_once'] = date('Y-m-d H:i:s');
                    if (isset($_POST['duration_once_date'])) {
                        $reengagedetails['duration_once'] = date('Y-m-d h:i:s A', strtotime($_POST['duration_once_date'] . ' ' . $_POST['duration_once_hr'] . ':' . $_POST['duration_once_minutes'] . ':00' . $_POST['duration_once_ampm']));
                    }
                }
                if (isset($_POST['duration_type']) && $reengagedetails['duration_once'] == '') {
                    FlashMessage(GetLang('Addon_reengagement_Once_DateTime_NotExist'), SS_FLASH_MSG_ERROR);
                    $errors = true;
                }
            }

            if ($_POST['duration_type'] == 3) {
                if (isset($_POST['duration_hourly'])) {
                    $reengagedetails['duration_hourly'] = '1';
                    if (isset($_POST['duration_hourly'])) {
                        $reengagedetails['duration_hourly'] = $_POST['duration_hourly'];
                    }
                }

                if (isset($_POST['duration_type']) && $reengagedetails['duration_hourly'] == '') {
                    FlashMessage(GetLang('Addon_reengagement_Daily_DateTime_NotExist'), SS_FLASH_MSG_ERROR);
                    $errors = true;
                }
            }

            if ($_POST['duration_type'] == 4) {
                if (isset($_POST['duration_daily_hr']) && isset($_POST['duration_daily_minutes']) && isset($_POST['duration_daily_ampm'])) {
                    $reengagedetails['duration_daily'] = date('H:i:s');
                    if (isset($_POST['duration_daily_hr'])) {
                        $reengagedetails['duration_daily'] = date('H:i:s', strtotime('2014-07-15 ' . $_POST['duration_daily_hr'] . ':' . $_POST['duration_daily_minutes'] . ':00' . $_POST['duration_daily_ampm']));
                    }
                }
                if (isset($_POST['duration_type']) && $reengagedetails['duration_daily'] == '') {
                    FlashMessage(GetLang('Addon_reengagement_Daily_DateTime_NotExist'), SS_FLASH_MSG_ERROR);
                    $errors = true;
                }
            }
            if ($_POST['duration_type'] == 5) {
                if (isset($_POST['duration_weekly']) && isset($_POST['duration_weekly_hr']) && isset($_POST['duration_weekly_minutes']) && isset($_POST['duration_weekly_ampm'])) {
                    $reengagedetails['duration_weekly'] = '';
                    if (isset($_POST['duration_weekly'])) {
                        $reengagedetails['duration_weekly'] = implode(',', $_POST['duration_weekly']);
                        $reengagedetails['duration_weekly_time'] = date('H:i:s', strtotime('2014-07-15 ' . $_POST['duration_weekly_hr'] . ':' . $_POST['duration_weekly_minutes'] . ':00' . $_POST['duration_weekly_ampm']));
                    }
                }
                if (isset($_POST['duration_type']) && ($reengagedetails['duration_weekly'] == '' || $reengagedetails['duration_weekly_time'] == '')) {
                    FlashMessage(GetLang('Addon_reengagement_Weekly_DateTime_NotExist'), SS_FLASH_MSG_ERROR);
                    $errors = true;
                }
            }
        }

        return [$errors, $reengagedetails];
    }
}
