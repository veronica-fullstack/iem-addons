<?php
/**
 * This is the api file for mta rotations to use.
 * It allows you to create, update, delete, load a mta rotation.
 *
 * @package SendStudio
 * @subpackage ExcludeList
 */

/**
 * This is the mta rotations api class.
 * It handles all of the database stuff (creating/loading/updating/searching).
 *
 * @uses IEM::getDatabase()
 *
 * @package SendStudio
 * @subpackage ExcludeList
 */

class ExcludeList_API extends API
{
    /**
     * $db
     * A local database connection
     *
     * @see __construct
     */
    private $db;

    /**
     * __construct
     * Sets up the database connection for easy use.
     *
     * @uses IEM::getDatabase()
     * @see db
     */
    public function __construct()
    {
        $this->db = IEM::getDatabase();
    }

    public function getExcludeState($email, $excludeType, $exArray)
    {
        $Subscriber_API = $this->GetApi('Subscribers');
        $typeOfExclude = $excludeType ?? 0;
        $isExclude = false;
        if ($typeOfExclude == 1) {
            $listIds = $exArray;
            if (is_array($listIds) && count($listIds) > 0) {
                $contactlistCheck = $Subscriber_API->GetAllListsForEmailAddress($email, $listIds);
                if (count($contactlistCheck) > 0) {
                    $isExclude = true;
                }
            }
        }
        if ($typeOfExclude == 2) {
            $segmentIds = $exArray;
            if (is_array($segmentIds) && count($segmentIds) > 0) {
                $segmentCheck = $Subscriber_API->FetchSubscribersFromSegment(1, 'all', $segmentIds, null, $email);
                if (intval($segmentCheck["count"]) > 0) {
                    $isExclude = true;
                }
            }
        }
        if ($typeOfExclude == 4) { // New Option for Recency
            $numofdaysArr = $exArray;
            if (is_array($numofdaysArr) && count($numofdaysArr) > 0) {
                $numofday = isset($numofdaysArr[0]) ? (int) $numofdaysArr[0] : 0;
                if ($numofday > 0) {
                    $dayspandate = date('Y-m-d', strtotime('-' . $numofday . ' days'));
                    $query = "SELECT subscriberid";
                    $query .= " FROM " . SENDSTUDIO_TABLEPREFIX . "list_subscribers WHERE emailaddress='" . $this->db->Quote($email) . "' AND (unsubscribed = 0 AND bounced = 0)";
                    $result = $this->db->Query($query);
                    $subscribersArr = [];
                    while ($row = $this->db->Fetch($result)) {
                        $subscribersArr[] = $row['subscriberid'];
                    }
                    if (0 < count($subscribersArr)) {
                        $selQry = "SELECT recipient FROM " . SENDSTUDIO_TABLEPREFIX . "stats_autoresponders_recipients WHERE send_status = '1' AND DATE_FORMAT( FROM_UNIXTIME( `sendtime` ) , '%Y-%m-%d' ) >= '" . $dayspandate . "' AND recipient IN (" . implode(',', $subscribersArr) . ")";
                        $sresult = $this->db->Query($selQry);
                        $row_count = $this->db->CountResult($sresult);
                        $this->db->FreeResult($sresult);
                        if ((int) $row_count > 0) {
                            $isExclude = true;
                        }
                    }
                }
            }
        }
        return $isExclude;
    }

    //MSN Modify for Exclude Version
    public function ChooseListImportSubscribers($page = 'Import', $action = 'step2', $autoredirect = true)
    {
        $page = strtolower((string) $page);
        $user = IEM::getCurrentUser();

        $GLOBALS['DisplaySegmentOption'] = 'none';
        if ($page == 'import') {
            $selectSegment = '';
            $segments = $user->GetSegmentList();
            $segmentAPI = $this->GetApi('Segment');
            foreach ($segments as $segmentid => $segmentdetails) {
                $selectSegment .= '<option value="' . $segmentid . '">'
                                    . htmlspecialchars((string) $segmentdetails['segmentname'], ENT_QUOTES, SENDSTUDIO_CHARSET)
                                    . '</option>';
            }
            $GLOBALS['SelectSegment'] = $selectSegment;

            $GLOBALS['DisplaySegmentOption'] = '';
        }
    }

    //MSN Modify for Exclude Version
    public function setExcludeSendCampaginSession()
    {
        $send_details = IEM::sessionGet('SendDetails');
        //MSN Modify for Exclude Version
        $send_details['ExcludeType'] = $_POST['ShowExcludeOptions'];
        $send_details['ExcludeList'] = $_POST['exclude_lists'] ?? 0;
        $send_details['ExcludeSegment'] = $_POST['exclude_segments'] ?? 0;
        $send_details['ExcludeRecency'] = $_POST['numofdays'] ?? 0;
        if (is_array($send_details['ExcludeList']) && count($send_details['ExcludeList']) > 0) {
            $GLOBALS['ExcludeListMsg'] = '<ul>';
            foreach ($send_details['ExcludeList'] as $listid) {
                $query = 'SELECT l.name,COUNT(ls.subscriberid) AS count FROM ' . SENDSTUDIO_TABLEPREFIX . 'lists as l INNER JOIN ' . SENDSTUDIO_TABLEPREFIX . 'list_subscribers as ls ON l.listid = ls.listid WHERE l.listid=\'' . $listid . '\' AND ls.confirmed =1';
                $result = $this->db->Query($query);
                $list = $this->db->Fetch($result);
                $GLOBALS['ExcludeListMsg'] .= '<li>' . $list["count"] . ' Contacts will be excluded from list <strong>' . $list["name"] . '</strong></li>';
            }
            $GLOBALS['ExcludeListMsg'] .= '</ul>';
        } else {
            $GLOBALS['ExcludeListMsg'] = '<ul>';
            $GLOBALS['ExcludeListMsg'] .= '<li>0 Contacts will be excluded from lists</li>';
            $GLOBALS['ExcludeListMsg'] .= '</ul>';
        }
        if (is_array($send_details['ExcludeSegment']) && count($send_details['ExcludeSegment']) > 0) {
            $segAPI = $this->GetApi('Segment');
            $GLOBALS['ExcludeSegmentMsg'] = '<ul>';
            foreach ($send_details['ExcludeSegment'] as $segid) {
                $segment = $segAPI->GetSegmentByID($segid);
                $segmentCnt = $segAPI->GetSubscribersCount($segid, true, false);
                $GLOBALS['ExcludeSegmentMsg'] .= '<li>' . $segmentCnt . ' Contacts will be excluded from segment <strong>' . $segment["segmentname"] . '</strong></li>';
            }
            $GLOBALS['ExcludeSegmentMsg'] .= '</ul>';
        } else {
            $GLOBALS['ExcludeSegmentMsg'] = '<ul>';
            $GLOBALS['ExcludeSegmentMsg'] .= '<li>0 Contacts will be excluded from segments</li>';
            $GLOBALS['ExcludeSegmentMsg'] .= '</ul>';
        }
        print_r($GLOBALS['ExcludeListMsg']);
        IEM::sessionSet('SendDetails', $send_details);
    }

    //MSN Modify for Exclude Version
    public function setExcludeSendAutoresponderSession()
    {
        $send_details = IEM::sessionGet('Autoresponders');
        //MSN Modify for Exclude Version
        $send_details['searchcriteria']['ExcludeType'] = $_POST['ShowExcludeOptions'];
        $send_details['searchcriteria']['ExcludeList'] = $_POST['exclude_lists'] ?? 0;
        $send_details['searchcriteria']['ExcludeSegment'] = $_POST['exclude_segments'] ?? 0;
        $send_details['searchcriteria']['ExcludeRecency'] = $_POST['numofdays'] ?? 0;
        //echo "<PRE>";print_r($send_details); die;
        IEM::sessionSet('Autoresponders', $send_details);
    }

    //MSN Modify for Exclude Version
    public function ChooseListSendCampaign($page = 'Send', $action = 'step2', $autoredirect = true)
    {
        //echo "*********<PRE>";print_r($GLOBALS); echo "</PRE>"; die;
        $page = strtolower((string) $page);
        $action = strtolower((string) $action);
        $user = IEM::getCurrentUser();
        $lists = [];

        if ($page == 'send') {
            $lists = $user->GetLists(false, true);
        } else {
            $lists = $user->GetLists();
        }

        $listids = array_keys($lists);

        if (sizeof($listids) < 1 || $page == '' || $action == '') {
            $GLOBALS['Intro'] = GetLang(ucwords($page) . '_' . ucwords($action));
            $GLOBALS['Lists_AddButton'] = '';

            if ($user->CanCreateList() === true) {
                $GLOBALS['Message'] = $this->PrintSuccess('NoLists', GetLang('ListCreate'));
                $GLOBALS['Lists_AddButton'] = $this->ParseTemplate('List_Create_Button', true, false);
            } else {
                $GLOBALS['Message'] = $this->PrintSuccess('NoLists', GetLang('ListAssign'));
            }
            $this->ParseTemplate('Lists_Manage_Empty');
            return;
        }

        if (sizeof($listids) == 1) {
            if ($autoredirect) {
                $location = 'index.php?Page=' . $page . '&Action=' . $action . '&list=' . current($listids);
                ?>
				<script>
					window.location = '<?php echo $location; ?>';
				</script>
				<?php
                exit();
            }
        }

        $selectlist = '';
        foreach ($lists as $listid => $listdetails) {
            $tempSubscriberCount = $listdetails['subscribecount'];

            if (array_key_exists('unconfirmedsubscribercount', $listdetails)) {
                $tempSubscriberCount = $tempSubscriberCount - intval($listdetails['unconfirmedsubscribercount']);
                if ($tempSubscriberCount < 0) {
                    $tempSubscriberCount = 0;
                }
            }

            if ($tempSubscriberCount == 1) {
                $subscriber_count = GetLang('Subscriber_Count_Active_Confirmed_One');
            } else {
                $subscriber_count = sprintf(GetLang('Subscriber_Count_Active_Confirmed_Many'), $this->FormatNumber($tempSubscriberCount));
            }

            $autoresponder_count = '';

            if (strtolower($page) == 'autoresponders') {
                $autoresponder_count = match ($listdetails['autorespondercount']) {
                    0 => GetLang('Autoresponder_Count_None'),
                    1 => GetLang('Autoresponder_Count_One'),
                    default => sprintf(GetLang('Autoresponder_Count_Many'), $this->FormatNumber($listdetails['autorespondercount'])),
                };
            }
            $selectlist .= '<option value="' . $listid . '">' . htmlspecialchars((string) $listdetails['name'], ENT_QUOTES, SENDSTUDIO_CHARSET) . $subscriber_count . $autoresponder_count . '</option>';
        }
        $GLOBALS['SelectList'] = $selectlist;

        $GLOBALS['DisplaySegmentOption'] = 'none';

        if ($page == 'send' && $user->HasAccess('Segments', 'Send')) {
            $selectSegment = '';
            $segments = $user->GetSegmentList();
            $segmentAPI = $this->GetApi('Segment');
            foreach ($segments as $segmentid => $segmentdetails) {
                $selectSegment .= '<option value="' . $segmentid . '">'
                                    . htmlspecialchars((string) $segmentdetails['segmentname'], ENT_QUOTES, SENDSTUDIO_CHARSET)
                                    . '</option>';
            }
            $GLOBALS['SelectSegment'] = $selectSegment;

            $GLOBALS['DisplaySegmentOption'] = '';
        }
        //echo "*********<PRE>";print_r($GLOBALS); echo "</PRE>"; die;
    }

    //MSN Modify for Exclude Version
    public function PrintSuccess()
    {
        $tpl = GetTemplateSystem();
        $arg_list = func_get_args();
        $langvar = array_shift($arg_list);
        $GLOBALS['Success'] = vsprintf(GetLang($langvar), $arg_list);
        return $tpl->ParseTemplate('SuccessMsg', true, false);
    }

    public function ParseTemplate($templatename = false, $return = false, $recurse = true, $fullpath = null)
    {
        if (! $templatename) {
            return false;
        }

        if (defined('SENDSTUDIO_DEBUG_MODE') && SENDSTUDIO_DEBUG_MODE) {
            echo '<!-- Template Start: "' . $templatename . "\" -->\n\n";
        }

        $GLOBALS['APPLICATION_URL'] = SENDSTUDIO_APPLICATION_URL;
        $GLOBALS['CHARSET'] = SENDSTUDIO_CHARSET;

        if (! isset($GLOBALS['PAGE'])) {
            $GLOBALS['PAGE'] = static::class;
        }

        $temporaryGlobal = [];
        foreach ($this->GlobalAreas as $key => $value) {
            if (isset($GLOBALS[$key])) {
                $temporaryGlobal[$key] = $GLOBALS[$key];
            }
            $GLOBALS[$key] = $value;
        }

        $tpl = GetTemplateSystem();
        if ($templatename === true && ! is_null($fullpath)) {
            $tempPath = dirname((string) $fullpath);
            $tempFile = basename((string) $fullpath);
            if (preg_match('/(.*)\..*$/', $tempFile, $matches)) {
                $tempFile = $matches[1];
            }

            $tpl->SetTemplatePath($tempPath);
            $output = $tpl->ParseTemplate($tempFile, true);
        } else {
            $output = $tpl->ParseTemplate($templatename, true);
        }

        foreach ($this->GlobalAreas as $key => $value) {
            if (isset($temporaryGlobal[$key])) {
                $GLOBALS[$key] = $temporaryGlobal[$key];
            } else {
                unset($GLOBALS[$key]);
            }
        }

        if (! $return) {
            print $output;
            return;
        }
        return $output;
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
