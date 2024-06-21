<?php
/**
 * This file contains the basic functionality for the 'excludelist' addon, including
 * - installing the addon
 * - uninstalling the addon
 * - creating a excludelist
 * - editing a excludelist
 * - deleting a excludelist
 *
 * The main class is broken up as much as possible into sections.
 * There are two addition files:
 * - excludelist_send.php
 * - excludelist_stats.php
 *
 * These files are only included when you are viewing the relevant area(s)
 * to help keep memory usage & processing time to a reasonable limit.
 *
 * It also makes it much easier to work out where things are.
 *
 * @package Interspire_Addons
 * @subpackage Addons_excludelist
 * @author     M Singh Karnawat <mukesh@w3technosoft.com>
 * @author     LiveItExperts <marketing@liveitexperts.com>
 * @copyright  2003-2012 LiveItExperts
 */

/**
 * Make sure the base Interspire_Addons class is defined.
 */
if (! class_exists('Interspire_Addons', false)) {
    require_once(dirname(__FILE__, 2) . '/interspire_addons.php');
}
ini_set('set_time_limit', '400000000');
set_time_limit(0);
require_once(__DIR__ . '/language/language.php');
if (function_exists("jacoblog") == false) {
    function jacoblog($d)
    {
        file_put_contents("jacob_log.txt", print_r($d, true) . PHP_EOL, FILE_APPEND);
    }
}
/**
 * This class handles most things for mta rotation
 * including extra user permissions, menu items (under 'email campaigns' and also in 'stats')
 * and of course processing everything.
 *
 * If you go into a particular area (eg 'sending' a mta rotation campaign), then extra files are included.
 * This helps keep memory usage and processing time to a reasonable limit.
 *
 * @uses Interspire_Addons
 * @uses Interspire_Addons_Exception
 * @uses Addons_excludelist_Send
 */
class Addons_excludelist extends Interspire_Addons
{
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
                //$this->db->RollbackTransaction();
                //throw new Interspire_Addons_Exception("There was a problem running query " . $qry . ": " . $this->db->GetErrorMsg(), Interspire_Addons_Exception::DatabaseError);
            }
        }

        $this->enabled = true;
        $this->configured = true;

        $this->installAddonFiles('excludelist');

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
                throw new Interspire_Addons_Exception("There was a problem running query " . $query . ": " . $this->db->GetErrorMsg(), Interspire_Addons_Exception::DatabaseError);
            }
        }

        foreach ($sequences as $sequencename) {
            $query = 'DROP SEQUENCE [|PREFIX|]' . $sequencename;
            $result = $this->db->Query($query);

            if (! $result) {
                $this->db->RollbackTransaction();
                throw new Interspire_Addons_Exception("There was a problem running query " . $query . ": " . $this->db->GetErrorMsg(), Interspire_Addons_Exception::DatabaseError);
            }
        }

        //unInstall files changes
        $this->uninstallAddonFiles('excludelist');
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
     * This enables the mta rotation addon to work, including displaying in the menu(s), adding it's own permissions etc.
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
        try {
            $status = parent::Enable();
        } catch (Interspire_Addons_Exception $e) {
            throw new Interspire_Addons_Exception($e->getMessage(), $e->getCode());
        }
        return true;
    }

    /**
     * Disable
     * This disables the mta Rotation addon from the control panel.
     * Before it does it, it checks for any non-complete mta rotation sending jobs
     * If any are found, the addon cannot be disabled.
     *
     * If that's ok, it deletes itself from the settings_cron_schedule table and any other settings it created (config_settings table).
     *
     * @uses Interspire_Addons::Disable
     * @uses Interspire_Addons_Exception
     *
     * @return Returns true if the addon was disabled successfully and there are no pending/in progress mta rotation sends.
     * @throws If the parent::Disable method throws an exception, this will just re-throw that error.
     */
    public function Disable()
    {
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
     * check it's not used by mta rotation campaigns.
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
        $my_file = '{%IEM_ADDONS_PATH%}/excludelist/excludelist.php';
        $listeners = [];

        $listeners[] =
            [
                'eventname' => 'IEM_SETTINGSAPI_LOADSETTINGS',
                'trigger_details' => [
                    'Addons_excludelist',
                    'SetSettings',
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
     * Adds a new settings entry called "CRON_MTAROTATATION" to the settings table.
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
    }

    /*
    AddNewLineInExisting
    */
    public function installAddonFiles($addon = "none")
    {
        $install_data_file_schema = IEM_ADDONS_PATH . "/{$addon}/modifier/modifier_points.php";
        if (! file_exists($install_data_file_schema)) {
            $this->log();
            return true;
        } else {
            require_once $install_data_file_schema;
            $dataFilePath = IEM_ADDONS_PATH . "/{$addon}/modifier/install_data.php";
            $fhDPath = fopen($dataFilePath, 'r');
            $install_data_file_content = fread($fhDPath, filesize($dataFilePath));
            fclose($fhDPath);

            $errors = [];
            $adname = ($installer_AddonName ?? "");

            if (isset($data_files) && is_array($data_files) && count($data_files) > 0) {
                $temp_path = IEM_ADDONS_PATH . "/{$addon}/modifier/existing/";
                if (is_writable($temp_path)) {
                    foreach ($data_files as $data_file) {
                        $data_file_to_replace = $data_file['data_file'];
                        echo "MSN" . $data_file_to_replace . "<br/>";
                        $temp_data_file = $temp_path . md5((string) $data_file_to_replace) . substr((string) $data_file_to_replace, -4, 4);
                        if (is_writable($data_file_to_replace)) {
                            if (! file_exists($temp_data_file)) {
                                if (! copy($data_file_to_replace, $temp_data_file)) {
                                    $errors[] = "Error: {$temp_data_file} for {$data_file_to_replace}";
                                }
                            }
                            $content = "";
                            foreach ($data_file['data'] as $lv) {
                                $lv = $this->palace_info($lv);
                                jacoblog($lv);
                                if (! $lv) {
                                    continue;
                                }
                                $line = $lv['line'] - 1;
                                $line_log = $lv['line'];
                                if ($lv['insert'] == 'a') {
                                    $insert = "after";
                                } elseif ($lv['insert'] == 'i') {
                                    $insert = "inline";
                                } elseif ($lv['insert'] == 'r') {
                                    $insert = "replace";
                                } elseif ($lv['insert'] == 'lr') {
                                    $insert = "linereplace";
                                } elseif ($lv['insert'] == 'lrn') {
                                    $insert = "linereplacenocomment";
                                } else {
                                    $insert = "before";
                                }
                                $contentType = 'array';
                                $replaceType = 'default';

                                if ($insert == "linereplace" || $insert == "linereplacenocomment") {
                                    $contentType = 'direct';
                                    $replaceType = 'replace';
                                    if ($content == "") {
                                        $fh = fopen($data_file_to_replace, 'r');
                                        $content = fread($fh, filesize($data_file_to_replace));
                                        fclose($fh);
                                    }
                                    $addon_replacedatacontent = $this->insert_contentfile($lv['replacetext'], $install_data_file_content, $replaceType);
                                } else {
                                    $contentType = 'array';
                                    if ($content == "") {
                                        $content = file($data_file_to_replace, FILE_IGNORE_NEW_LINES);
                                    }
                                }
                                $addon_datacontent = $this->insert_contentfile($lv['content'], $install_data_file_content, $replaceType);

                                if ($data_file['type'] == "tpl") {
                                    $suffix = "<!--";
                                    $postfix = "-->";
                                } elseif ($data_file['type'] == "css") {
                                    $suffix = "/*";
                                    $postfix = "*/";
                                } elseif ($data_file['type'] == "js") {
                                    $suffix = "/*";
                                    $postfix = "*/";
                                } else {
                                    $suffix = "/*";
                                    $postfix = "*/";
                                }
                                if ($insert == "linereplacenocomment") {
                                    $finalsuffix = "";
                                    $finalposfix = "";
                                } else {
                                    $finalsuffix = "{$suffix}LiveIT Addon Main: START {$postfix}";
                                    $finalposfix = "{$suffix}LiveIT Addon Main($adname), {$insert} : END {$postfix}";
                                }
                                if ($data_file['reque']) {
                                    $_a = explode(PHP_EOL, (string) $addon_datacontent);
                                    jacoblog("Count: " . count($_a));
                                    jacoblog($_a);
                                    jacoblog(implode("", $_a));
                                    if (count($_a) < 2) {
                                        $_a = explode("\r", (string) $addon_datacontent);
                                    }
                                }
                                $addon_datacontent = trim(($data_file['reque']) ? implode("", $_a) : $addon_datacontent);
                                $addon_datacontent = "{$finalsuffix}{$addon_datacontent}{$finalposfix}";
                                jacoblog("{$data_file_to_replace}: " . $addon_datacontent);
                                if ($insert == "after") {
                                    $content[$line] = rtrim($content[$line]) . PHP_EOL . $addon_datacontent;
                                } elseif ($insert == "inline") {
                                    $content[$line] = rtrim($content[$line]) . $addon_datacontent;
                                } elseif ($insert == "replace") {
                                    $content[$line] = $addon_datacontent . "//OldData" . rtrim($content[$line]);
                                } elseif ($insert == "linereplace" || $insert == "linereplacenocomment") {
                                    $content = str_replace($addon_replacedatacontent, $addon_datacontent, $content, $bool);
                                } else {
                                    $content[$line] = $addon_datacontent . PHP_EOL . $content[$line];
                                }
                            }
                            $result = $this->contentSave($data_file_to_replace, $content, $contentType);
                            if (! $result) {
                                $errors[] = "ERROR: {$file_to_replace}";
                            }
                        } else {
                            $errors[] = "ERROR: " . realpath($data_file_to_replace) . " is not writable3";
                        }
                    }
                } else {
                    $errors[] = "The folder( {$temp_path} ) is not writable";
                }
            }
            $this->log();
            if (count($errors) > 0) {
                throw new Exception("ERROR: Unable to install addon ( {$adname} ): <br>" . implode("<br>", $errors));
            }
        }
        return true;
    }

    //Uninstall Addon file
    public function uninstallAddonFiles($addon = "none")
    {
        $install_data_file = IEM_ADDONS_PATH . "/{$addon}/modifier/modifier_points.php";
        if (! file_exists($install_data_file)) {
            return true;
        } else {
            require_once $install_data_file;
            $errors = [];
            $adname = ($installer_AddonName ?? "");
            if (isset($data_files) && is_array($data_files) && count($data_files) > 0) {
                $temp_path = IEM_ADDONS_PATH . "/{$addon}/modifier/existing/";
                if (is_writable($temp_path)) {
                    foreach ($data_files as $data_file) {
                        $data_file_to_replace = $data_file['data_file'];
                        $temp_data_file = $temp_path . md5((string) $data_file_to_replace) . substr((string) $data_file_to_replace, -4, 4);
                        if (is_writable($data_file_to_replace)) {
                            if (file_exists($temp_data_file)) {
                                if (! copy($temp_data_file, $data_file_to_replace)) {
                                    $errors[] = "ERROR: {$data_file_to_replace} with {$temp_data_file}";
                                } else {
                                    if (! unlink($temp_data_file)) {
                                        $errors[] = "ERROR: deleting temp data_file: {$temp_data_file} for {$data_file_to_replace}";
                                    }
                                }
                            }
                        } else {
                            $errors[] = "ERROR: {$data_file_to_replace} is not writable1";
                        }
                    }
                } else {
                    $errors[] = "ERROR: ( {$temp_path} ) is not writable2";
                }
            }
            if (count($errors) > 0) {
                throw new Exception("ERROR: Unable to uninstall addon ( {$adname} ): <br>" . implode("<br>", $errors));
            }
        }
        return true;
    }

    //LOG DATA
    private function log()
    {
        return true;
    }

    //place content in file
    public function insert_contentfile($block, $data_content, $pattern = "default")
    {
        if ($pattern == "default") {
            $content_chk1 = md5(random_int(0, mt_getrandmax()) . random_int(0, mt_getrandmax()));
            $content_chk2 = md5(random_int(0, mt_getrandmax()) . random_int(0, mt_getrandmax()));
            $content_chk3 = md5(random_int(0, mt_getrandmax()) . random_int(0, mt_getrandmax()));
            $content_chk4 = md5(random_int(0, mt_getrandmax()) . random_int(0, mt_getrandmax()));
            $data_content = str_replace('(', $content_chk1, (string) $data_content);
            $data_content = str_replace(')', $content_chk2, $data_content);
            $data_content = str_replace('?', $content_chk3, $data_content);
            $data_content = str_replace('.', $content_chk4, $data_content);
            $pattern = "/#COPY_BLOCK_{$block}#([^\.)]+)#FINISH_BLOCK_{$block}#/i";
            $rm = preg_match($pattern, $data_content, $match);
            $data_content = ($rm) ? $match[1] : "";
            $data_content = str_replace($content_chk1, '(', $data_content);
            $data_content = str_replace($content_chk2, ')', $data_content);
            $data_content = str_replace($content_chk3, '?', $data_content);
            $data_content = str_replace($content_chk4, '.', $data_content);
            return trim($data_content);
        } else {
            $contentStart = "#COPY_BLOCK_{$block}#";
            $contentEnd = "#FINISH_BLOCK_{$block}#";
            $positionStart = strpos((string) $data_content, $contentStart);
            $positionEnd = strpos((string) $data_content, $contentEnd);
            $totalChar = intval($positionEnd) - intval($positionStart);
            $data_content = substr((string) $data_content, $positionStart, $totalChar);
            $data_content = str_replace($contentStart, "", $data_content);
            $data_content = str_replace($contentEnd, "", $data_content);
            return trim($data_content);
        }
    }

    //Insert information
    public function palace_info($data)
    {
        if (is_string($data[0])) {
            foreach ($data as $v) {
                $a = explode(";", (string) $v);
                $d = [
                    'line' => $a[1],
                    'insert' => $a[2],
                    'content' => $a[3],
                    'replacetext' => $a[4],
                ];
                if ($this->enableDisable($a[0])) {
                    return $d;
                }
            }
            return false;
        } else {
            return $data;
        }
    }

    //check Addon enable or disable
    public function enableDisable($s)
    {
        $i = false;
        $a = explode(",", (string) $s);
        $addon_system = new Interspire_Addons();
        foreach ($a as $v) {
            if ($v == "default") {
                return true;
            }
            $i = $addon_system->isEnabled($v);
        }
        return $i;
    }

    //Addon content save in files
    public function contentSave($data_file, $content = [], $contentType = "array")
    {
        $result = fopen($data_file, "w+");
        if (! $result) {
            return $result;
        }
        if ($contentType == "direct") {
            fwrite($result, (string) $content);
            fclose($result);
            return true;
        } else {
            foreach ($content as $v) {
                $result = @file_put_contents($data_file, rtrim((string) $v) . PHP_EOL, FILE_APPEND);
            }
        }
        return $result;
    }

    /**
     * GetApi
     * An easy way to include the excludelist api file which does all of the database queries.
     * This is marked as protected so the sub-classes (for sending & stats) can use it.
     *
     * @param string $api Which api to get. It defaults to the 'excludelist' api but can be passed 'excludelist_send' to get that api instead.
     *
     * @return object|false Returns false if the api name is invalid. Otherwise returns the appropriate api object ready for use.
     */
    protected function GetApi($api = 'ExcludeList')
    {
        if (is_array($api) && count($api) > 0) {
            $api = $api[0];
        }
        $path = $this->addon_base_directory . $this->addon_id . '/api/' . strtolower($api) . '.php';
        if (! is_file($path)) {
            $path = SENDSTUDIO_API_DIRECTORY . '/' . strtolower($api) . '.php';
            if (! is_file($path)) {
                return false;
            }
        }

        require_once $path;
        $class = $api . '_API';
        return new $class();
    }

    /**
     * Admin_Action_Default
     * This prints the 'manage page' which shows a list of mta rotations that have been created.
     * If the user has access to create new ones, it also shows a 'create mta rotation' button.
     *
     * @uses GetApi
     * @uses Excludelist_API::GetExcludeList
     */
    public function Admin_Action_Default()
    {
        echo "No Access. Please contact System Administrator.";
    }

    /**
     * FormatNumber
     * Formats the number passed in according to language variables and returns the value.
     *
     * @param int $number Number to format
     * @param int $decimalplaces Number of decimal places to format to
     *
     * @see GetLang
     *
     * @return string The number formatted
     */
    public function FormatNumber($number = 0, $decimalplaces = 0)
    {
        return number_format((float) $number, $decimalplaces, GetLang('NumberFormat_Dec'), GetLang('NumberFormat_Thousands'));
    }
}
