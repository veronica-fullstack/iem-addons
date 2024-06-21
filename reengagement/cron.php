<?php
/**
 * This file handles cron sending of re engagement campaigns.
 * It includes the functionality to return a list of 'waiting' jobs that are ready to send.
 * It also includes the class which actually processes the re engagement campaigns and does all of the sending.
 *
 * @package SendStudio
 * @subpackage ReEngagement
 */

/**
 * This should never get called outside of the cron system.
 * IEM_CRON_JOB is defined in the main cron/addons.php file,
 * so if it's not available then someone is doing something strange.
 */
if (! defined('IEM_CRON_JOB')) {
    die('You cannot access this file directly.');
}
error_reporting(E_ALL & ~E_NOTICE);
ini_set('memory_limit', '1024M');
/**
 * Reengagement_Cron_GetJobs
 * This is used to work out which jobs need to be run for re engagement sending.
 *
 * It adds an array containing the addon id, the path to this file and the jobs that need to be processed.
 *
 * <code>
 *	$job_details = array (
 *		'addonid' => 'reengagement',
 *		'file' => '/full/path/to/file',
 *		'jobs' => array (
 *			'1',
 *			'2',
 *		);
 *	);
 * </code>
 *
 * This gets the job id's from the reengagements table which are
 * - 'w'aiting to be sent before "now"
 * - 'i'n progress and haven't been updated in at least 30 minutes (means the job crashed or the server crashed)
 * and are approved/finished being set up.
 *
 * @param EventData_IEM_CRON_RUNADDONS $data The current list of cron tasks that need to be processed. This function just adds it's own data to the end.
 *
 * @uses EventData_IEM_CRON_RUNADDONS
 */
function Reengagement_Cron_GetJobs(EventData_IEM_CRON_RUNADDONS $data)
{
    $job_details = [
        'addonid' => 'reengagement',
        'file' => __FILE__,
        'jobs' => [],
    ];

    require_once SENDSTUDIO_API_DIRECTORY . '/api.php';
    $api = new API();

    $timenow = $api->GetServerTime();
    $half_hour_ago = $timenow - (30 * 60);

    $db = IEM::getDatabase();

    processExistingClickOpens($db, $api);

    SyncClickAndOpenSubscribers($db, $api);

    approveforRun($db);
    runReExistingCronJob($db, $timenow);

    $query = 'SELECT jobid FROM ' . $db->TablePrefix . "jobs WHERE jobtype='reengagement' AND (";

    /**
     * get "waiting" jobs
     */
    $query .= " (jobstatus ='w' AND jobtime < " . $timenow . ') OR ';

    /**
     * get "resending" jobs
     */
    $query .= " (jobstatus='r' AND jobtime < " . $timenow . ') OR ';

    /**
     * get "timeout" jobs
     * and have waited their "hours after" time before continuing a send.
     *
     * When a job is marked as "timeout", it changes the jobtime to include the "hours after" time
     * so here we don't need to do any calculations.
     */
    $query .= " (jobstatus='t' AND jobtime < " . $timenow . ') OR ';

    /**
     * Get jobs that haven't been updated in half an hour.
     * This is in case a job has broken (eg the db went down or server was rebooted mid-send).
     */
    $query .= " (jobstatus='i' AND jobtime < " . $timenow . ' AND lastupdatetime < ' . $half_hour_ago . ')';

    /**
     * and only get approved jobs
     * which are ones that have been completely set up.
     */
    $query .= ') AND (approved > 0)';

    $result = $db->Query($query);
    while ($row = $db->Fetch($result)) {
        $job_details['jobs'][] = (int) $row['jobid'];
    }

    $data->jobs_to_run[] = $job_details;
}

function SyncClickAndOpenSubscribers($db, $api)
{
    $selectLastSyncTime = "SELECT Variable_Value as LastSync FROM [|PREFIX|]reengagements_config WHERE `Variable_Name`='Last_Sync'";
    $rsLastSyncTime = $db->Query($selectLastSyncTime);
    $lastSyncTime = 0;
    while ($rowLastSyncTime = $db->Fetch($rsLastSyncTime)) {
        $lastSyncTime = $rowLastSyncTime['LastSync'];
    }
    $timenow = $api->GetServerTime();
    $timeDiffer = $timenow - $lastSyncTime;

    if ($timeDiffer > 1000) {
        //$CountLinkedData = "SELECT count(DISTINCT resub.subscriberid) AS TotalCounts FROM [|PREFIX|]list_subscribers as resub LEFT JOIN [|PREFIX|]stats_linkclicks As stslnk ON (stslnk.subscriberid = resub.subscriberid) LEFT JOIN [|PREFIX|]stats_emailopens as stsopn ON (stsopn.subscriberid = resub.subscriberid) WHERE (stslnk.clicktime>=".$lastSyncTime." OR stsopn.opentime > ".$lastSyncTime.")";

        $CountLinkedData = 'SELECT COUNT(resub.subscriberid) AS TotalCounts FROM [|PREFIX|]reengagements_list AS rlst LEFT JOIN [|PREFIX|]list_subscribers as resub ON (rlst.listid=resub.listid) WHERE ((SELECT MAX(opentime) AS opntime FROM [|PREFIX|]stats_emailopens WHERE subscriberid=resub.subscriberid) >= rlst.last_track OR (SELECT MAX(clicktime) AS clktime FROM [|PREFIX|]stats_linkclicks WHERE subscriberid=resub.subscriberid) > rlst.last_track)';
        $rsCountLinked = $db->Query($CountLinkedData);

        $totalRecords = 0;
        while ($rowCountLinked = $db->Fetch($rsCountLinked)) {
            $totalRecords = $rowCountLinked['TotalCounts'];
        }

        $startFrom = 0;

        if ($totalRecords > 0) {
            $limit = 500;
            $start = 0;
            while ($start < $totalRecords) {
                //$LinkedDataQry = "SELECT resub.listid, resub.subscriberid, resub.subscribedate, MAX(stsopn.opentime) As MaxOpenTime, MAX(stslnk.clicktime) As MaxClickTime, stsopn.opentime As MinOpenTime, stslnk.clicktime As MinClickTime FROM [|PREFIX|]reengagements_list AS rlst LEFT JOIN [|PREFIX|]list_subscribers as resub ON (rlst.listid=resub.listid) LEFT JOIN [|PREFIX|]stats_linkclicks As stslnk ON (stslnk.subscriberid = resub.subscriberid) LEFT JOIN [|PREFIX|]stats_emailopens as stsopn ON (stsopn.subscriberid = resub.subscriberid) WHERE (stslnk.clicktime>=rlst.last_sync OR stsopn.opentime > rlst.last_sync) GROUP BY resub.subscriberid LIMIT ".$start.", ".$limit;

                $LinkedDataQry = 'SELECT resub.listid, resub.subscriberid, resub.subscribedate, (SELECT MAX(opentime) AS opntime FROM email_stats_emailopens WHERE subscriberid=resub.subscriberid) AS MaxOpenTime, (SELECT MAX(clicktime) AS clktime FROM email_stats_linkclicks WHERE subscriberid=resub.subscriberid) AS MaxClickTime FROM email_reengagements_list AS rlst LEFT JOIN email_list_subscribers as resub ON (rlst.listid=resub.listid) WHERE ((SELECT MAX(opentime) AS opntime FROM email_stats_emailopens WHERE subscriberid=resub.subscriberid) >= rlst.last_track OR (SELECT MAX(clicktime) AS clktime FROM email_stats_linkclicks WHERE subscriberid=resub.subscriberid) > rlst.last_track) LIMIT ' . $start . ', ' . $limit;

                $rsLinkedData = $db->Query($LinkedDataQry);
                $start = $start + $limit;
                $minTime = 0;

                while ($rowLinkedData = $db->Fetch($rsLinkedData)) {
                    $minTimeClick = $rowLinkedData['MaxClickTime'];
                    $minTimeOpen = $rowLinkedData['MinOpenTime'];
                    if ($minTimeClick != 0) {
                        $minTime = $minTimeClick;
                    }
                    if ($minTimeOpen < $minTimeClick && $minTimeClick != 0) {
                        $minTime = $minTimeOpen;
                    }

                    $updateLinkClickOpen = "INSERT INTO [|PREFIX|]reengagements_subscriber  (`listid`,`subscriberid`,`last_click`,`last_open`,`subscribedate`) VALUES ('" . $rowLinkedData['listid'] . "','" . $rowLinkedData['subscriberid'] . "','" . $rowLinkedData['MaxClickTime'] . "','" . $rowLinkedData['MaxOpenTime'] . "','" . $rowLinkedData['subscribedate'] . "') ON DUPLICATE KEY UPDATE last_click='" . $rowLinkedData['MaxClickTime'] . "', last_open='" . $rowLinkedData['MaxOpenTime'] . "'";

                    $updateLinks = $db->Query($updateLinkClickOpen);
                    $startFrom++;
                }

                if ($minTime > 0) {
                    $updateSyncTime = "UPDATE [|PREFIX|]reengagements_config SET Variable_Value='" . $minTime . "' WHERE `Variable_Name`='Last_Sync'";
                    $updateSync = $db->Query($updateSyncTime);
                    $updateSyncTime = "UPDATE [|PREFIX|]reengagements_list SET last_track='" . $timenow . "'";
                    $updateSync = $db->Query($updateSyncTime);
                }
            }
            $timenow = $api->GetServerTime();
            $updateSyncTime = "UPDATE [|PREFIX|]reengagements_config SET Variable_Value='" . $timenow . "' WHERE `Variable_Name`='Last_Sync'";
            $updateSync = $db->Query($updateSyncTime);
        }
    }
}
function processExistingClickOpens($db, $api)
{
    $selectLastSyncTime = "SELECT Variable_Value as Last_List_Sync FROM [|PREFIX|]reengagements_config WHERE `Variable_Name`='Last_List_Sync'";
    $rsLastSyncTime = $db->Query($selectLastSyncTime);
    $lastSyncTime = 0;
    while ($rowLastSyncTime = $db->Fetch($rsLastSyncTime)) {
        $lastSyncTime = $rowLastSyncTime['Last_List_Sync'];
    }
    $timenow = $api->GetServerTime();
    $timeDiffer = $timenow - $lastSyncTime;
    if ($timeDiffer > 1000) {
        $query = 'SELECT reengagedetails FROM [|PREFIX|]reengagements';

        $reengagementListRS = $db->Query($query);
        $reengagementListIds = '';

        while ($row = $db->Fetch($reengagementListRS)) {
            $dataOfReengage = unserialize($row['reengagedetails']);
            if ($reengagementListIds == '') {
                $reengagementListIds = $dataOfReengage['reengagement_list'];
            } else {
                $reengagementListIds = $reengagementListIds . ',' . $dataOfReengage['reengagement_list'];
            }
        }
        //echo "<pre>";var_dump($reengagementListIds);echo "</pre>";
        //echo "1<br/>";
        $getAllListIdsArray = explode(',', (string) $reengagementListIds);
        $getAllListIdsUniqueArray = array_unique($getAllListIdsArray);
        $listIdRunner = [];
        $lsPlus = 0;
        $listIds = implode(',', $getAllListIdsUniqueArray);
        $queryTwo = 'SELECT ls.listid, relst.last_sync, ls.subscribecount FROM [|PREFIX|]lists as ls LEFT JOIN [|PREFIX|]reengagements_list as relst ON (relst.listid = ls.listid) WHERE ls.listid in (' . $listIds . ')';
        $listRS = $db->Query($queryTwo);
        while ($listRow = $db->Fetch($listRS)) {
            //echo "<pre>";var_dump($listRow);echo "</pre>";
            if (date('Y-m-d', $listRow['last_sync']) != date('Y-m-d')) {
                $listIdRunner[$lsPlus]['listid'] = $listRow['listid'];
                if ($listRow['last_sync'] == null) {
                    $listRow['last_sync'] = 0;
                }
                $listIdRunner[$lsPlus]['last_sync'] = $listRow['last_sync'];
                $listIdRunner[$lsPlus]['subscribecount'] = $listRow['subscribecount'];
                $lsPlus++;
            }
        }
        //echo "<pre>";var_dump($listIdRunner);echo "</pre>";
        //echo "2<br/>";
        foreach ($listIdRunner as $valLs) {
            $lastSync = '';
            $timenow = $api->GetServerTime();
            if ($valLs['last_sync'] != 0 && $valLs['last_sync'] != '') {
                $lastSync = 'subscribedate >= ' . $valLs['last_sync'] . ' AND ';
                $insertList = "UPDATE [|PREFIX|]reengagements_list SET `sync_status`='p' WHERE `listid`='" . $valLs['listid'] . "'";
                $insertListRs = $db->Query($insertList);
            } else {
                $insertList = "INSERT INTO [|PREFIX|]reengagements_list (`listid`,`total_records`,`last_sync`,`sync_status`) VALUES ('" . $valLs['listid'] . "','" . $valLs['subscribecount'] . "','0','n') ON DUPLICATE KEY UPDATE last_sync=last_sync, `sync_status`='p'";
                $insertListRs = $db->Query($insertList);
            }
            $queryCount = 'SELECT count(subscriberid) As TotalRecords FROM [|PREFIX|]list_subscribers WHERE ' . $lastSync . " listid='" . $valLs['listid'] . "'";
            $listRecordsRS = $db->Query($queryCount);
            while ($listRecrods = $db->Fetch($listRecordsRS)) {
                $totalRecords = $listRecrods['TotalRecords'];
            }
            //echo "Total Records: ".$totalRecords."<br/>";
            if ($totalRecords > 0) {
                $limit = 500;
                $start = 0;
                if ($totalRecords > $limit) {
                    while ($start < $totalRecords) {
                        $queryThree = 'SELECT subscriberid, listid, subscribedate FROM [|PREFIX|]list_subscribers WHERE ' . $lastSync . " listid='" . $valLs['listid'] . "' Order By subscriberid ASC Limit " . $start . ',' . $limit;

                        $subscriberRS = $db->Query($queryThree);
                        $start = $start + $limit;
                        $lastSubscribe = 0;
                        while ($subscribeRecrods = $db->Fetch($subscriberRS)) {
                            $insertSubscribe = "INSERT INTO [|PREFIX|]reengagements_subscriber (`listid`,`subscriberid`,`last_click`,`last_open`,`subscribedate`) VALUES ('" . $subscribeRecrods['listid'] . "','" . $subscribeRecrods['subscriberid'] . "','','','" . $subscribeRecrods['subscribedate'] . "')";

                            $insertSubscribeRs = $db->Query($insertSubscribe);
                            $lastSubscribe = $subscribeRecrods['subscribedate'];
                        }
                        if ($start > $totalRecords) {
                            $syncstatus = 'c';
                        } else {
                            $syncstatus = 'p';
                        }
                        $timenow = $api->GetServerTime();
                        if (intval($lastSubscribe) != 0) {
                            $insertList = "UPDATE [|PREFIX|]reengagements_list SET `last_sync`='" . intval($lastSubscribe) . "',`sync_status`='" . $syncstatus . "' WHERE `listid`='" . $valLs['listid'] . "'";
                            $insertListRs = $db->Query($insertList);
                        }
                    }
                }
            }
            $timenow = $api->GetServerTime();
            $insertList = "UPDATE [|PREFIX|]reengagements_list SET `last_sync`='" . $timenow . "',`sync_status`='c' WHERE `listid`='" . $valLs['listid'] . "'";
            $insertListRs = $db->Query($insertList);
        }
        //echo "3<br/>";
        $timenow = $api->GetServerTime();
        $updateSyncTime = "UPDATE [|PREFIX|]reengagements_config SET Variable_Value='" . $timenow . "' WHERE `Variable_Name`='Last_List_Sync'";
        $updateSync = $db->Query($updateSyncTime);
    }
}
function approveforRun($db)
{
    $query = 'SELECT reengageid, reengagedetails FROM ' . $db->TablePrefix . "reengagements WHERE jobstatus='n'";
    $reengagementListRS = $db->Query($query);

    while ($row = $db->Fetch($reengagementListRS)) {
        $dataOfReengage = unserialize($row['reengagedetails']);
        $reengagementListIds = $dataOfReengage['reengagement_list'];

        if ($reengagementListIds != '') {
            $queryForCheck = 'SELECT listid, sync_status FROM ' . $db->TablePrefix . 'reengagements_list WHERE listid in (' . $reengagementListIds . ')';

            $rsForCheck = $db->Query($queryForCheck);
            $isOkay = false;
            $totalList = 0;
            $newListId = [];
            while ($rowOfQry = $db->Fetch($rsForCheck)) {
                $totalList++;
                if ($rowOfQry['sync_status'] != 'c') {
                    $isOkay = false;
                    break;
                }
                $newListId[] = $rowOfQry['listid'];
                $isOkay = true;
            }
            $inEngage = explode(',', (string) $reengagementListIds);
            sort($inEngage);
            /*echo "<pre>";var_dump($inEngage);echo "</pre>";
            echo "Array Second";
            echo "<pre>";var_dump($newListId);echo "</pre>";
            echo "TOTAL:".$totalList."; AC:".count($inEngage);*/
            if ($totalList != count($inEngage) && $isOkay == true) {
                $isOkay = false;
            }
            if ($isOkay == true) {
                $updateReengage = 'UPDATE ' . $db->TablePrefix . "reengagements SET jobstatus=NULL WHERE reengageid='" . $row['reengageid'] . "'";
                $updateReng = $db->Query($updateReengage);
            }
        }
    }
}

function runReExistingCronJob($db, $timenow)
{
    $queryOfNewJob = 'SELECT DISTINCT reengage.reengageid as reengagedis, reengage.* FROM ' . $db->TablePrefix . "reengagements as reengage  WHERE duration_type > 1 AND (jobstatus='c' OR jobstatus='w' OR jobstatus IS NULL)";
    $resultOfNewJob = $db->Query($queryOfNewJob);
    $newAllCronJob = [];
    while ($row = $db->Fetch($resultOfNewJob)) {
        $dataRows['reengagedetails'] = unserialize($row['reengagedetails']);
        $newJobs = false;
        if ($row['duration_type'] >= 2) {
            $newSendOn = 0;

            if ($row['duration_type'] == 2 && $row['lastsent'] == '0') {
                $sendTime = strtotime(date('Y-m-d ', $timenow) . $dataRows['reengagedetails']['duration_once']);
                if ($timenow > $sendTime) {
                    $newSendOn = strtotime((string) $dataRows['reengagedetails']['duration_once']);
                }
            } elseif ($row['duration_type'] == 3) {
                $lastSent = $row['lastsent'];
                $plusHrs = $dataRows['reengagedetails']['duration_hourly'];
                $newSendOn = $lastSent + ($plusHrs * 3600);
            } elseif ($row['duration_type'] == 4) {
                if (intval($row['lastsent']) > 0) {
                    $lastSent = strtotime(date('Y-m-d', $row['lastsent']));
                } else {
                    $lastSent = 0;
                }

                $nextDay = strtotime(date('Y-m-d', $timenow));
                $sendTime = strtotime(date('Y-m-d ', $timenow) . $dataRows['reengagedetails']['duration_daily']);
                if (($row['lastsent'] == 0 && $timenow > $sendTime) || ($lastSent > 0 && $lastSent < $nextDay && $timenow > $sendTime)) {
                    $newSendOn = strtotime(date('Y-m-d ', $timenow) . $dataRows['reengagedetails']['duration_daily']);
                }
            } elseif ($row['duration_type'] == 5) {
                if (intval($row['lastsent']) > 0) {
                    $lastSent = strtotime(date('Y-m-d', $row['lastsent']));
                } else {
                    $lastSent = 0;
                }
                $nextDay = strtotime(date('Y-m-d', $timenow));
                $weekDays = explode(',', (string) $dataRows['reengagedetails']['duration_weekly']);
                if (in_array(date('N'), $weekDays)) {
                    $sendTime = strtotime(date('Y-m-d ', $timenow) . $dataRows['reengagedetails']['duration_weekly_time']);
                    if ((intval($lastSent) == 0 && $timenow > $sendTime) || ($lastSent > 0 && $lastSent < $nextDay && $timenow > $sendTime)) {
                        $newSendOn = strtotime(date('Y-m-d ', $timenow) . $dataRows['reengagedetails']['duration_weekly_time']);
                    }
                }
            } elseif ($row['duration_type'] == 6) {
                if (intval($row['lastsent']) > 0) {
                    $lastSent = strtotime(date('Y-m-d', $row['lastsent']));
                } else {
                    $lastSent = 0;
                }
                $plusMonth = date('Y-m-d', strtotime('+1 month', $lastSent));

                if ($lastSent == 0 || ($lastSent > 0 && $plusMonth <= strtotime(date('Y-m-d H:i:s', $timenow)))) {
                    $newSendOn = $timenow;
                }
            }
            if ($newSendOn != 0 && $newSendOn <= $timenow) {
                $newJobs = true;
            }
        }
        if ($newJobs == true) {
            $getSizeOfList = 'SELECT SUM(subscribecount) As TotalContacts FROM ' . $db->TablePrefix . 'lists WHERE listid IN(' . $dataRows['reengagedetails']['reengagement_list'] . ')';
            $resultSizeList = $db->Query($getSizeOfList);
            $returnSizeList = $db->Fetch($resultSizeList);
            $setOnJobCron = [];
            $setOnJobCron['jobtype'] = 'reengagement';
            $setOnJobCron['jobstatus'] = 'w';
            $setOnJobCron['jobtime'] = strtotime(date('Y-m-d H:i:s'));
            $setOnJobCron['fkid'] = $row['reengageid'];
            $setOnJobCron['fktype'] = 'reengagement';
            $setOnJobCron['ownerid'] = $row['userid'];
            $setOnJobCron['approved'] = '1';
            $setOnJobCron['authorisedtosend'] = '1';

            $setOnJobCron['jobdetails']['reengageid'] = $row['reengageid'];
            $setOnJobCron['jobdetails']['sendingto'] = [
                'sendtype' => 'list',
                'sendids' => explode(',', (string) $dataRows['reengagedetails']['reengagement_list']),
                'Lists' => explode(',', (string) $dataRows['reengagedetails']['reengagement_list']),
            ];
            $setOnJobCron['jobdetails']['sendsize'] = $returnSizeList['TotalContacts'];
            $setOnJobCron['jobdetails']['Charset'] = 'UTF-8';
            $setOnJobCron['jobdetails']['To_FirstName'] = false;
            $setOnJobCron['jobdetails']['To_LastName'] = false;

            $setOnJobCron['jobdetails']['SendStartTime'] = (float) $newSendOn;

            $setOnJobCron['jobdetails']['EmailResults'] = [
                'success' => 0,
                'total' => 0,
                'failure' => 0,
            ];
            $setOnJobCron['jobdetails']['NotifyOwner'] = 0;
            $setOnJobCron['jobdetails']['TrackLinks'] = 1;
            $setOnJobCron['jobdetails']['TrackOpens'] = 1;
            $setOnJobCron['jobdetails']['Multipart'] = 0;
            $setOnJobCron['jobdetails']['EmbedImages'] = 0;
            $setOnJobCron['jobdetails']['SendCriteria'] = [
                'Status' => 'a',
                'List' => explode(',', (string) $dataRows['reengagedetails']['reengagement_list']),
            ];
            $setOnJobCron['jobdetails']['Lists'] = explode(',', (string) $dataRows['reengagedetails']['reengagement_list']);
            $setOnJobCron['jobdetails']['isRemoveContact'] = $dataRows['reengagedetails']['is_removemail'];
            $typeof = 'b';
            if (strtolower((string) $dataRows['reengage_typeof']) == 'not open') {
                $typeof = 'o';
            }
            if (strtolower((string) $dataRows['reengage_typeof']) == 'not click') {
                $typeof = 'c';
            }
            $setOnJobCron['jobdetails']['TypeOfNotOpen'] = $typeof;
            $setOnJobCron['jobdetails']['TransferOnLists'] = explode(',', (string) $dataRows['reengagedetails']['contactlist']);
            $setOnJobCron['jobdetails']['SendSize'] = $returnSizeList['TotalContacts'];
            $setOnJobCron['jobdetails']['NumberOfDays'] = $dataRows['reengagedetails']['max_numberofdays'];

            $newAllCronJob[] = $setOnJobCron;
        }
    }
    if (count($newAllCronJob) > 0) {
        require_once SENDSTUDIO_API_DIRECTORY . '/jobs.php';
        $jobapi = new Jobs_API();
        foreach ($newAllCronJob as $valCronJob) {
            $updateRSSJob = 'UPDATE ' . $db->TablePrefix . "reengagements SET `jobstatus` = 'w' WHERE reengageid='" . $valCronJob['fkid'] . "'";
            $updatedRss = $db->Query($updateRSSJob);

            $jobcreated = $jobapi->Create('reengagement', $valCronJob['jobdetails']['SendStartTime'], $valCronJob['ownerid'], $valCronJob['jobdetails'], 'reengagement', $valCronJob['fkid'], $valCronJob['jobdetails']['Lists']);
            $job_id = $jobcreated;
            $jobapi->ApproveJob($job_id, $valCronJob['ownerid'], $valCronJob['ownerid']);
        }
    }
}

/**
 * Make sure the parent class is included.
 * We use it to switch between the newsletters we're sending, set up custom fields and so on.
 */
require_once __DIR__ . '/api/reengagement_send.php';


/**
 * This class handles sending re engagement campaigns via cron.
 * It uses the Reengagement_Send_API to cache/switch between each newsletter being sent.
 * It also uses the main classes (Jobs_API, Send_API) to handle job/queue/send processing.
 *
 * This needs to handle two types of re engagement sending:
 *
 * 1) sending a number of emails evenly across a whole number of subscribers
 *
 * @uses Reengagement_Send_API
 * @uses Jobs_API
 * @uses Send_API
 * @uses Stats_API
 */
class Jobs_Cron_API_Reengagement extends Reengagement_Send_API
{
    /**
     * send_limit
     * The maximum number of recipients/subscribers to load in to memory at once.
     * This allows the job to process a bunch of recipients at once
     * (one db query to load this number of subscribers)
     * but not take up all available memory.
     *
     * @usedby _ActionJob
     */
    final public const send_limit = 500;

    /**
     * _reengagement_api
     * A placeholder variable for the reengagement api
     *
     *
     * @see _ProcessJob
     */
    private ?\Reengagement_API $_reengagement_api = null;

    /**
     * _subscribers_api
     * A placeholder variable for the subscriber api
     * This is mainly used when setting up a send ready to go:
     * - set up the queue
     * - remove duplicates
     * - remove banned emails
     * etc
     *
     * @var Subscribers_API
     *
     * @see ProcessJobs
     * @see _ProcessJob
     */
    private $_subscribers_api;

    /**
     * _stats_api
     * A placeholder variable for the stats api
     * Used to record user stats, marking a newsletter as "finished" sending etc.
     *
     * @var Stats_API
     *
     * @see ProcessJobs
     * @see ProcessJob
     * @see _FinishJob
     */
    private $_stats_api;

    /**
     * __construct
     * This constructor does nothing itself.
     * It calls the parent constructor to get it to set whatever it needs up.
     *
     * @uses Reengagement_Send_API::__construct
     */
    public function __construct()
    {
        parent::__construct();
        set_time_limit(0);
        error_reporting(E_ALL & ~E_NOTICE);
        ini_set('memory_limit', '1024M');
    }

    /**
     * ProcessJobs
     * This takes an array of job id's to process and goes through them one by one
     * to send a re engagement campaign.
     * It also sets up some local variables for caching/easy use.
     *
     * This method just basically loops over each job in the array and gives it to _ProcessJob
     * In between each job it processes, it clears the "cached" elements in the Reengagement_Send_API
     *
     * If any of the job id's are invalid (non-numeric), they are just discarded.
     *
     * @param array $jobs An array of job id's to process.
     *
     * @see Reengagement_Cron_GetJobs
     * @see _subscribers_api
     * @see _stats_api
     * @uses ForgetSend
     * @uses _ProcessJob
     * @uses CheckIntVars
     */
    public function ProcessJobs($jobs = [])
    {
        $jobs = $this->CheckIntVars($jobs);
        if (empty($jobs)) {
            return;
        }

        require_once __DIR__ . '/api/reengagement.php';
        require_once SENDSTUDIO_API_DIRECTORY . '/subscribers.php';
        require_once SENDSTUDIO_API_DIRECTORY . '/stats.php';

        $this->_reengagement_api = new Reengagement_API();
        $this->_subscribers_api = new Subscribers_API();
        $this->_stats_api = new Stats_API();
        //$this->processExistingClickOpens();
        $this->SyncClickAndOpenSubscribers();
        foreach ($jobs as $jobid) {
            $this->ForgetSend();
            $this->_ProcessJob($jobid);
        }
    }

    public function processExistingClickOpens()
    {
        $selectLastSyncTime = "SELECT Variable_Value as Last_List_Sync FROM [|PREFIX|]reengagements_config WHERE `Variable_Name`='Last_List_Sync'";
        $rsLastSyncTime = $this->Db->Query($selectLastSyncTime);
        $lastSyncTime = 0;
        while ($rowLastSyncTime = $this->Db->Fetch($rsLastSyncTime)) {
            $lastSyncTime = $rowLastSyncTime['Last_List_Sync'];
        }
        $timenow = $this->GetServerTime();

        $timeDiffer = $timenow - $lastSyncTime;

        if ($timeDiffer > 1000) {
            $query = 'SELECT reengagedetails FROM [|PREFIX|]reengagements';
            $reengagementListRS = $this->Db->Query($query);
            $reengagementListIds = '';

            while ($row = $this->Db->Fetch($reengagementListRS)) {
                $dataOfReengage = unserialize($row['reengagedetails']);
                if ($reengagementListIds == '') {
                    $reengagementListIds = $dataRows['reengagement_list'];
                } else {
                    $reengagementListIds = $reengagementListIds . ',' . $dataRows['reengagement_list'];
                }
            }

            $getAllListIdsArray = explode(',', (string) $reengagementListIds);
            $getAllListIdsUniqueArray = array_unique($getAllListIdsArray);
            $listIdRunner = [];
            $lsPlus = 0;
            $listIds = implode(',', $getAllListIdsUniqueArray);
            $queryTwo = 'SELECT ls.listid, relst.last_sync, ls.subscribecount FROM [|PREFIX|]lists as ls LEFT JOIN [|PREFIX|]reengagements_list as relst ON (relst.listid = ls.listid) WHERE ls.listid in (' . $listIds . ')';
            $listRS = $this->Db->Query($queryTwo);
            while ($listRow = $this->Db->Fetch($listRS)) {
                if (date('Y-m-d', $listRow['last_sync']) != date('Y-m-d')) {
                    $listIdRunner[$lsPlus]['listid'] = $listRow['listid'];
                    $listIdRunner[$lsPlus]['last_sync'] = $listRow['last_sync'];
                    $listIdRunner[$lsPlus]['subscribecount'] = $listRow['subscribecount'];
                }
            }
            foreach ($listIdRunner as $valLs) {
                $lastSync = '';
                $timenow = $this->GetServerTime();
                if ($valLs['last_sync'] != null) {
                    $lastSync = 'subscribedate > ' . $valLs['last_sync'] . ' AND ';
                    $insertList = "UPDATE [|PREFIX|]reengagements_list SET `last_sync`='" . $timenow . "',`sync_status`='p' WHERE `listid`='" . $valLs['listid'] . "'";
                    $insertListRs = $this->Db->Query($insertList);
                } else {
                    $insertList = "INSERT INTO [|PREFIX|]reengagements_list (`listid`,`total_records`,`last_sync`,`sync_status`) VALUES ('" . $valLs['listid'] . "','" . $valLs['subscribecount'] . "','0','n')";
                    $insertListRs = $this->Db->Query($insertList);
                }
                $queryCount = 'SELECT count(subscriberid) As TotalRecords FROM [|PREFIX|]list_subscribers WHERE ' . $lastSync . " listid='" . $valLs['listid'] . "'";
                $listRecordsRS = $this->Db->Query($queryCount);
                while ($listRecrods = $this->Db->Fetch($listRecordsRS)) {
                    $totalRecords = $listRecrods['TotalRecords'];
                }

                if ($totalRecords > 0) {
                    $limit = 500;
                    $start = 0;
                    if ($totalRecords > $limit) {
                        while ($start < $totalRecords) {
                            $queryThree = 'SELECT subscriberid, listid, subscribedate FROM [|PREFIX|]list_subscribers WHERE ' . $lastSync . " listid='" . $valLs['listid'] . "' Limit " . $start . ',' . $limit;
                            $subscriberRS = $this->Db->Query($queryThree);
                            $start = $start + $limit;
                            while ($subscribeRecrods = $this->Db->Fetch($subscriberRS)) {
                                $insertSubscribe = "INSERT INTO [|PREFIX|]reengagements_subscriber (`listid`,`subscriberid`,`last_click`,`last_open`,`subscribedate`) VALUES ('" . $subscriberRS['listid'] . "','" . $subscriberRS['subscriberid'] . "','','','" . $subscriberRS['subscribedate'] . "')";
                                $insertSubscribeRs = $this->Db->Query($insertSubscribe);
                            }
                            if ($start > $totalRecords) {
                                $syncstatus = 'c';
                            } else {
                                $syncstatus = 'p';
                            }
                            $timenow = $this->GetServerTime();
                            $insertList = "UPDATE [|PREFIX|]reengagements_list SET `last_sync`='" . $timenow . "',`sync_status`='" . $syncstatus . "' WHERE `listid`='" . $valLs['listid'] . "'";
                            $insertListRs = $this->Db->Query($insertList);
                        }
                    }
                } else {
                    $timenow = $this->GetServerTime();
                    $insertList = "UPDATE [|PREFIX|]reengagements_list SET `last_sync`='" . $timenow . "',`sync_status`='c' WHERE `listid`='" . $valLs['listid'] . "'";
                    $insertListRs = $this->Db->Query($insertList);
                }
            }
            $timenow = $this->GetServerTime();
            $updateSyncTime = "UPDATE [|PREFIX|]reengagements_config SET Variable_Value='" . $timenow . "' WHERE `Variable_Name`='Last_List_Sync'";
            $updateSync = $this->Db->Query($updateSyncTime);
        }
    }

    public function SyncClickAndOpenSubscribers()
    {
        $selectLastSyncTime = "SELECT Variable_Value as LastSync FROM [|PREFIX|]reengagements_config WHERE `Variable_Name`='Last_Sync'";
        $rsLastSyncTime = $this->Db->Query($selectLastSyncTime);
        $lastSyncTime = 0;
        while ($rowLastSyncTime = $this->Db->Fetch($rsLastSyncTime)) {
            $lastSyncTime = $rowLastSyncTime['LastSync'];
        }
        $timenow = $this->GetServerTime();
        $timeDiffer = $timenow - $lastSyncTime;

        if ($timeDiffer > 1000) {
            $CountLinkedData = 'SELECT COUNT(resub.subscriberid) AS TotalCounts FROM [|PREFIX|]reengagements_list AS rlst LEFT JOIN [|PREFIX|]list_subscribers as resub ON (rlst.listid=resub.listid) WHERE ((SELECT MAX(opentime) AS opntime FROM [|PREFIX|]stats_emailopens WHERE subscriberid=resub.subscriberid) >= rlst.last_track OR (SELECT MAX(clicktime) AS clktime FROM [|PREFIX|]stats_linkclicks WHERE subscriberid=resub.subscriberid) > rlst.last_track)';
            $rsCountLinked = $this->Db->Query($CountLinkedData);

            $totalRecords = 0;
            while ($rowCountLinked = $this->Db->Fetch($rsCountLinked)) {
                $totalRecords = $rowCountLinked['TotalCounts'];
            }
            //echo "<br/>Total Records: ".$totalRecords. "<br/>";echo "STOP IT<br/>";
            $startFrom = 0;

            if ($totalRecords > 0) {
                $limit = 500;
                $start = 0;
                while ($start < $totalRecords) {
                    $LinkedDataQry = 'SELECT resub.listid, resub.subscriberid, resub.subscribedate, (SELECT MAX(opentime) AS opntime FROM email_stats_emailopens WHERE subscriberid=resub.subscriberid) AS MaxOpenTime, (SELECT MAX(clicktime) AS clktime FROM email_stats_linkclicks WHERE subscriberid=resub.subscriberid) AS MaxClickTime FROM email_reengagements_list AS rlst LEFT JOIN email_list_subscribers as resub ON (rlst.listid=resub.listid) WHERE ((SELECT MAX(opentime) AS opntime FROM email_stats_emailopens WHERE subscriberid=resub.subscriberid) >= rlst.last_track OR (SELECT MAX(clicktime) AS clktime FROM email_stats_linkclicks WHERE subscriberid=resub.subscriberid) > rlst.last_track) LIMIT ' . $start . ', ' . $limit;

                    $rsLinkedData = $this->Db->Query($LinkedDataQry);
                    $start = $start + $limit;
                    $minTime = 0;

                    while ($rowLinkedData = $this->Db->Fetch($rsLinkedData)) {
                        $minTimeClick = $rowLinkedData['MaxClickTime'];
                        $minTimeOpen = $rowLinkedData['MinOpenTime'];
                        if ($minTimeClick != 0) {
                            $minTime = $minTimeClick;
                        }
                        if ($minTimeOpen < $minTimeClick && $minTimeClick != 0) {
                            $minTime = $minTimeOpen;
                        }

                        $updateLinkClickOpen = "INSERT INTO [|PREFIX|]reengagements_subscriber  (`listid`,`subscriberid`,`last_click`,`last_open`,`subscribedate`) VALUES ('" . $rowLinkedData['listid'] . "','" . $rowLinkedData['subscriberid'] . "','" . $rowLinkedData['MaxClickTime'] . "','" . $rowLinkedData['MaxOpenTime'] . "','" . $rowLinkedData['subscribedate'] . "') ON DUPLICATE KEY UPDATE last_click='" . $rowLinkedData['MaxClickTime'] . "', last_open='" . $rowLinkedData['MaxOpenTime'] . "'";

                        $updateLinks = $this->Db->Query($updateLinkClickOpen);
                        $startFrom++;
                    }

                    if ($minTime > 0) {
                        $updateSyncTime = "UPDATE [|PREFIX|]reengagements_config SET Variable_Value='" . $minTime . "' WHERE `Variable_Name`='Last_Sync'";
                        $updateSync = $this->Db->Query($updateSyncTime);
                        $updateSyncTime = "UPDATE [|PREFIX|]reengagements_list SET last_track='" . $timenow . "'";
                        $updateSync = $this->Db->Query($updateSyncTime);
                    }
                }
                $timenow = $this->GetServerTime();
                $updateSyncTime = "UPDATE [|PREFIX|]reengagements_config SET Variable_Value='" . $timenow . "' WHERE `Variable_Name`='Last_Sync'";
                $updateSync = $this->Db->Query($updateSyncTime);
            }
        }
    }

    public function SyncClickAndOpenSubscribers_Old()
    {
        $selectLastSyncTime = "SELECT Variable_Value as LastSync FROM [|PREFIX|]reengagements_config WHERE `Variable_Name`='Last_Sync'";
        $rsLastSyncTime = $this->Db->Query($selectLastSyncTime);
        $lastSyncTime = 0;
        while ($rowLastSyncTime = $this->Db->Fetch($rsLastSyncTime)) {
            $lastSyncTime = $rowLastSyncTime['LastSync'];
        }

        $CountLinkedData = 'SELECT count(DISTINCT resub.subscriberid) AS TotalCounts FROM [|PREFIX|]list_subscribers as resub LEFT JOIN [|PREFIX|]stats_linkclicks As stslnk ON (stslnk.subscriberid = resub.subscriberid) LEFT JOIN [|PREFIX|]stats_emailopens as stsopn ON (stsopn.subscriberid = resub.subscriberid) WHERE (stslnk.clicktime>=' . $lastSyncTime . ' OR stsopn.opentime > ' . $lastSyncTime . ')';
        $rsCountLinked = $this->Db->Query($CountLinkedData);

        $totalRecords = 0;
        while ($rowCountLinked = $this->Db->Fetch($rsCountLinked)) {
            $totalRecords = $rowCountLinked['TotalCounts'];
        }
        //echo "Total Records: ".$totalRecords. "<br/>";echo "STOP IT";die;
        $startFrom = 0;
        if ($totalRecords > 0) {
            $limit = 500;
            $start = 0;
            while ($start < $totalRecords) {
                $LinkedDataQry = 'SELECT resub.listid, resub.subscriberid, resub.subscribedate, MAX(stsopn.opentime) As MaxOpenTime, MAX(stslnk.clicktime) As MaxClickTime, stsopn.opentime As MinOpenTime, stslnk.clicktime As MinClickTime FROM [|PREFIX|]list_subscribers as resub LEFT JOIN [|PREFIX|]stats_linkclicks As stslnk ON (stslnk.subscriberid = resub.subscriberid) LEFT JOIN [|PREFIX|]stats_emailopens as stsopn ON (stsopn.subscriberid = resub.subscriberid) WHERE (stslnk.clicktime>=' . $lastSyncTime . ' OR stsopn.opentime > ' . $lastSyncTime . ') GROUP BY resub.subscriberid LIMIT ' . $start . ', ' . $limit;

                $rsLinkedData = $this->Db->Query($LinkedDataQry);
                $start = $start + $limit;
                $minTime = 0;
                while ($rowLinkedData = $this->Db->Fetch($rsLinkedData)) {
                    $minTimeClick = $rowLinkedData['MaxClickTime'];
                    $minTimeOpen = $rowLinkedData['MinOpenTime'];
                    if ($minTimeClick != 0) {
                        $minTime = $minTimeClick;
                    }
                    if ($minTimeOpen < $minTimeClick && $minTimeClick != 0) {
                        $minTime = $minTimeOpen;
                    }

                    $updateLinkClickOpen = "INSERT INTO [|PREFIX|]reengagements_subscriber  (`listid`,`subscriberid`,`last_click`,`last_open`,`subscribedate`) VALUES ('" . $rowLinkedData['listid'] . "','" . $rowLinkedData['subscriberid'] . "','" . $rowLinkedData['MaxClickTime'] . "','" . $rowLinkedData['MaxOpenTime'] . "','" . $rowLinkedData['subscribedate'] . "') ON DUPLICATE KEY UPDATE last_click='" . $rowLinkedData['MaxClickTime'] . "', last_open='" . $rowLinkedData['MaxOpenTime'] . "'";
                    $updateLinks = $this->Db->Query($updateLinkClickOpen);
                    $startFrom++;
                }
                $timenow = $this->GetServerTime();
                if ($minTime > 0) {
                    $updateSyncTime = "UPDATE [|PREFIX|]reengagements_config SET Variable_Value='" . $minTime . "' WHERE `Variable_Name`='Last_Sync'";
                    $updateSync = $this->Db->Query($updateSyncTime);
                }
            }
            $timenow = $this->GetServerTime();
            $updateSyncTime = "UPDATE [|PREFIX|]reengagements_config SET Variable_Value='" . $timenow . "' WHERE `Variable_Name`='Last_Sync'";
            $updateSync = $this->Db->Query($updateSyncTime);
        }
        //echo "<br/>Counted:".$startFrom."<br/>Total Records: ".$totalRecords. "<br/>";echo "STOP IT";die;
    }

    /**
     * _FinishJob
     * This does a few cleanup jobs.
     * - Marks the job as complete in stats
     * - Clears out any unsent recipients from the "queue".
     * - Calls the parent FinishJob method to do it's work.
     *
     * @uses _stats_api
     * @uses Stats_API::MarkNewsletterFinished
     * @uses ClearQueue
     * @uses FinishJob
     */
    public function _FinishJob()
    {
        /**
         * Pass all of the stats through to the stats api.
         *
         * Since the stats contains an array of:
         * newsletterid => statid
         *
         * we just need to pass through the statid's.
         */
        $this->_stats_api->MarkNewsletterFinished(array_values($this->jobdetails['Stats']), $this->jobdetails['SendSize']);

        $this->ClearQueue($this->jobdetails['SendQueue'], 'reengagement');

        $this->FinishJob($this->_jobid, $this->jobdetails['reengageid']);
    }

    public function countNewsletterNotOpenClick($triggerInfoData)
    {
        $afterStrTime = strtotime(date('Y-m-d 00:00:00')) - (86400 * $triggerInfoData['numberofdays']);
        if ($triggerInfoData['notopenclicked'] == 'o') {
            $query = 'SELECT count(ls.subscriberid) As TotalRecords FROM (SELECT MAX(inResubs.last_open) As LastOpen, MIN(inls.unsubscribeconfirmed) As isUnsubscriber, MAX(inls.subscribedate) As lstSubscribedate, inls.emailaddress, MAX(inls.bounced) As MaxBounce, MAX(inls.unsubscribeconfirmed) As MaxUnsubscribe FROM [|PREFIX|]list_subscribers as inls LEFT JOIN [|PREFIX|]reengagements_subscriber as inResubs ON (inls.subscriberid=inResubs.subscriberid) WHERE inls.listid IN (' . $triggerInfoData['nopenlistid'] . ') GROUP BY inls.emailaddress) AS inMaxDta  LEFT JOIN [|PREFIX|]list_subscribers as ls ON (ls.emailaddress=inMaxDta.emailaddress) LEFT JOIN [|PREFIX|]reengagements_subscriber AS resubs ON (ls.subscriberid=resubs.subscriberid) WHERE ls.emailaddress=inMaxDta.emailaddress AND ls.listid IN (' . $triggerInfoData['nopenlistid'] . ") AND inMaxDta.isUnsubscriber = 0 AND ls.confirmed='1' AND ls.unsubscribeconfirmed='0' AND inMaxDta.MaxBounce='0' AND inMaxDta.MaxUnsubscribe='0' AND (inMaxDta.LastOpen < " . $afterStrTime . ' OR inMaxDta.LastOpen IS NULL) AND inMaxDta.lstSubscribedate < ' . $afterStrTime;
        } elseif ($triggerInfoData['notopenclicked'] == 'c') {
            $query = 'SELECT count(ls.subscriberid) As TotalRecords  FROM (SELECT MAX(inResubs.last_click) As LastClick, MIN(inls.unsubscribeconfirmed) As isUnsubscriber, MAX(inls.subscribedate) As lstSubscribedate, inls.emailaddress, MAX(inls.bounced) As MaxBounce, MAX(inls.unsubscribeconfirmed) As MaxUnsubscribe FROM [|PREFIX|]list_subscribers as inls LEFT JOIN [|PREFIX|]reengagements_subscriber as inResubs ON (inls.subscriberid=inResubs.subscriberid) WHERE inls.listid IN (' . $triggerInfoData['nopenlistid'] . ') GROUP BY inls.emailaddress) AS inMaxDta LEFT JOIN [|PREFIX|]list_subscribers as ls ON (ls.emailaddress=inMaxDta.emailaddress) LEFT JOIN [|PREFIX|]reengagements_subscriber AS resubs ON (ls.subscriberid=resubs.subscriberid) WHERE ls.emailaddress=inMaxDta.emailaddress AND ls.listid IN (' . $triggerInfoData['nopenlistid'] . ") AND inMaxDta.isUnsubscriber = 0 AND ls.confirmed='1' AND ls.unsubscribeconfirmed='0' AND inMaxDta.MaxBounce='0' AND inMaxDta.MaxUnsubscribe='0' AND (inMaxDta.LastClick < " . $afterStrTime . ' OR inMaxDta.LastClick IS NULL) AND inMaxDta.lstSubscribedate < ' . $afterStrTime;
        } else {
            $query = 'SELECT count(ls.subscriberid) As TotalRecords FROM (SELECT MAX(inResubs.last_open) As LastOpen, MAX(inResubs.last_click) As LastClick, MIN(inls.unsubscribeconfirmed) As isUnsubscriber, MAX(inls.subscribedate) As lstSubscribedate, inls.emailaddress, MAX(inls.bounced) As MaxBounce, MAX(inls.unsubscribeconfirmed) As MaxUnsubscribe FROM [|PREFIX|]list_subscribers as inls LEFT JOIN [|PREFIX|]reengagements_subscriber as inResubs ON (inls.subscriberid=inResubs.subscriberid) WHERE inls.listid IN (' . $triggerInfoData['nopenlistid'] . ') GROUP BY inls.emailaddress) AS inMaxDta LEFT JOIN [|PREFIX|]list_subscribers as ls ON (ls.emailaddress=inMaxDta.emailaddress) LEFT JOIN [|PREFIX|]reengagements_subscriber AS resubs ON (ls.subscriberid=resubs.subscriberid) WHERE ls.emailaddress=inMaxDta.emailaddress AND ls.listid IN (' . $triggerInfoData['nopenlistid'] . ") AND inMaxDta.isUnsubscriber = 0 AND ls.confirmed='1' AND ls.unsubscribeconfirmed='0' AND inMaxDta.MaxBounce='0' AND inMaxDta.MaxUnsubscribe='0' AND (inMaxDta.LastOpen < " . $afterStrTime . ' OR inMaxDta.LastOpen IS NULL) AND (inMaxDta.LastClick < ' . $afterStrTime . ' OR inMaxDta.LastClick IS NULL) AND inMaxDta.lstSubscribedate < ' . $afterStrTime;
        }

        $subscribeIdRS = $this->Db->Query($query);
        $subscriberIds = 0;

        while ($row = $this->Db->Fetch($subscribeIdRS)) {
            $subscriberIds = $row['TotalRecords'];
        }

        return $subscriberIds;
    }

    public function checkNewsletterNotOpenClick($triggerInfoData, $limit_start, $limit_send)
    {
        $afterStrTime = strtotime(date('Y-m-d 00:00:00')) - (86400 * $triggerInfoData['numberofdays']);
        if ($triggerInfoData['notopenclicked'] == 'o') {
            //$query = "SELECT ls.subscriberid, ls.emailaddress FROM [|PREFIX|]list_subscribers AS ls LEFT OUTER JOIN [|PREFIX|]stats_emailopens AS steo ON(ls.subscriberid = steo.subscriberid AND steo.opentime>".$afterStrTime.") WHERE ls.listid IN (".$triggerInfoData['nopenlistid'].") AND steo.subscriberid IS NULL";
            $query = 'SELECT ls.subscriberid, ls.emailaddress, ((' . $this->GetServerTime() . ' - inMaxDta.LastOpen)/86400) As UseTime FROM (SELECT MAX(inResubs.last_open) As LastOpen, MIN(inls.unsubscribeconfirmed) As isUnsubscriber, MAX(inls.subscribedate) As lstSubscribedate, inls.emailaddress, MAX(inls.bounced) As MaxBounce, MAX(inls.unsubscribeconfirmed) As MaxUnsubscribe FROM [|PREFIX|]list_subscribers as inls LEFT JOIN [|PREFIX|]reengagements_subscriber as inResubs ON (inls.subscriberid=inResubs.subscriberid) WHERE inls.listid IN (' . $triggerInfoData['nopenlistid'] . ') GROUP BY inls.emailaddress) AS inMaxDta LEFT JOIN [|PREFIX|]list_subscribers as ls ON (ls.emailaddress=inMaxDta.emailaddress) LEFT JOIN [|PREFIX|]reengagements_subscriber AS resubs ON (ls.subscriberid=resubs.subscriberid) WHERE ls.emailaddress=inMaxDta.emailaddress AND ls.listid IN (' . $triggerInfoData['nopenlistid'] . ") AND inMaxDta.isUnsubscriber = 0 AND ls.confirmed='1' AND ls.unsubscribeconfirmed='0' AND inMaxDta.MaxBounce='0' AND inMaxDta.MaxUnsubscribe='0' AND (inMaxDta.LastOpen < " . $afterStrTime . ' OR inMaxDta.LastOpen IS NULL) AND inMaxDta.lstSubscribedate < ' . $afterStrTime . ' LIMIT ' . $limit_start . ', ' . $limit_send;
        } elseif ($triggerInfoData['notopenclicked'] == 'c') {
            //$query = "SELECT ls.subscriberid, ls.emailaddress FROM [|PREFIX|]list_subscribers AS ls LEFT OUTER JOIN [|PREFIX|]stats_linkclicks AS stlc ON(ls.subscriberid = stlc.subscriberid AND stlc.clicktime>".$afterStrTime.") WHERE ls.listid IN (".$triggerInfoData['nopenlistid'].") AND stlc.subscriberid IS NULL";
            $query = 'SELECT ls.subscriberid, ls.emailaddress, ((' . $this->GetServerTime() . ' - inMaxDta.LastClick)/86400) As UseTime FROM (SELECT MAX(inResubs.last_click) As LastClick, MIN(inls.unsubscribeconfirmed) As isUnsubscriber, MAX(inls.subscribedate) As lstSubscribedate, inls.emailaddress, MAX(inls.bounced) As MaxBounce, MAX(inls.unsubscribeconfirmed) As MaxUnsubscribe FROM [|PREFIX|]list_subscribers as inls LEFT JOIN [|PREFIX|]reengagements_subscriber as inResubs ON (inls.subscriberid=inResubs.subscriberid) WHERE inls.listid IN (' . $triggerInfoData['nopenlistid'] . ') GROUP BY inls.emailaddress) AS inMaxDta LEFT JOIN [|PREFIX|]list_subscribers as ls ON (ls.emailaddress=inMaxDta.emailaddress) LEFT JOIN [|PREFIX|]reengagements_subscriber AS resubs ON (ls.subscriberid=resubs.subscriberid) WHERE ls.emailaddress=inMaxDta.emailaddress AND ls.listid IN (' . $triggerInfoData['nopenlistid'] . ") AND inMaxDta.isUnsubscriber = 0 AND ls.confirmed='1' AND ls.unsubscribeconfirmed='0' AND inMaxDta.MaxBounce='0' AND inMaxDta.MaxUnsubscribe='0' AND (inMaxDta.LastClick < " . $afterStrTime . ' OR inMaxDta.LastClick IS NULL) AND inMaxDta.lstSubscribedate < ' . $afterStrTime . ' LIMIT ' . $limit_start . ', ' . $limit_send;
        } else {
            //$query = "SELECT ls.subscriberid, ls.emailaddress FROM [|PREFIX|]list_subscribers AS ls LEFT OUTER JOIN [|PREFIX|]stats_linkclicks AS stlc ON(ls.subscriberid = stlc.subscriberid AND stlc.clicktime>".$afterStrTime.") LEFT OUTER JOIN [|PREFIX|]stats_emailopens AS steo ON(ls.subscriberid = steo.subscriberid AND steo.opentime>".$afterStrTime.") WHERE ls.listid IN (".$triggerInfoData['nopenlistid'].") AND stlc.subscriberid IS NULL AND steo.subscriberid IS NULL";

            $query = 'SELECT ls.subscriberid, ls.emailaddress, CONCAT((' . $this->GetServerTime() . " - inMaxDta.LastOpen)/86400, ',', (" . $this->GetServerTime() . ' - inMaxDta.LastClick)/86400) As UseTime FROM (SELECT MAX(inResubs.last_open) As LastOpen, MAX(inResubs.last_click) As LastClick, MIN(inls.unsubscribeconfirmed) As isUnsubscriber, MAX(inls.subscribedate) As lstSubscribedate, inls.emailaddress, MAX(inls.bounced) As MaxBounce, MAX(inls.unsubscribeconfirmed) As MaxUnsubscribe FROM [|PREFIX|]list_subscribers as inls LEFT JOIN [|PREFIX|]reengagements_subscriber as inResubs ON (inls.subscriberid=inResubs.subscriberid) WHERE inls.listid IN (' . $triggerInfoData['nopenlistid'] . ') GROUP BY inls.emailaddress) AS inMaxDta LEFT JOIN [|PREFIX|]list_subscribers as ls ON (ls.emailaddress=inMaxDta.emailaddress) LEFT JOIN [|PREFIX|]reengagements_subscriber AS resubs ON (ls.subscriberid=resubs.subscriberid) WHERE ls.emailaddress=inMaxDta.emailaddress AND ls.listid IN (' . $triggerInfoData['nopenlistid'] . ") AND inMaxDta.isUnsubscriber = 0 AND ls.confirmed='1' AND ls.unsubscribeconfirmed='0' AND inMaxDta.MaxBounce='0' AND inMaxDta.MaxUnsubscribe='0' AND (inMaxDta.LastOpen < " . $afterStrTime . ' OR inMaxDta.LastOpen IS NULL) AND (inMaxDta.LastClick < ' . $afterStrTime . ' OR inMaxDta.LastClick IS NULL) AND inMaxDta.lstSubscribedate < ' . $afterStrTime . ' LIMIT ' . $limit_start . ', ' . $limit_send;
        }

        $subscribeIdRS = $this->Db->Query($query);
        if ($subscribeIdRS == false) {
            return false;
        }

        $subscriberIds = [];

        while ($row = $this->Db->Fetch($subscribeIdRS)) {
            $clickOpenMessage = 'Never Open/Click';
            if ($triggerInfoData['notopenclicked'] == 'o') {
                if ($row['UseTime'] > 10000) {
                    $clickOpenMessage = 'Never Opened!';
                } else {
                    $clickOpenMessage = number_format($row['UseTime'], 0) . ' Days Before Open.';
                }
            } elseif ($triggerInfoData['notopenclicked'] == 'c') {
                if ($row['UseTime'] > 10000) {
                    $clickOpenMessage = 'Never Clicked!';
                } else {
                    $clickOpenMessage = number_format($row['UseTime'], 0) . ' Days Before Click.';
                }
            } else {
                $clickOpenMessage = '';
                $twoParts = explode(',', (string) $row['UseTime']);
                if (intval($twoParts[0]) > 10000) {
                    $clickOpenMessage = 'Never Opened & ';
                } else {
                    $clickOpenMessage = number_format($twoParts[0], 0) . ' Days Before Open. ';
                }
                if (intval($twoParts[1]) > 10000) {
                    $clickOpenMessage .= 'Never Clicked.';
                } else {
                    $clickOpenMessage .= number_format($twoParts[1], 0) . ' Days Before Click.';
                }
            }
            $row['UseTime'] = $clickOpenMessage;
            $subscriberIds[] = $row;
        }

        return $subscriberIds;
    }

        public function checkAutoresponderNotOpenClick($triggerInfoData)
        {
            $afterStrTime = strtotime(date('Y-m-d 00:00:00')) - (86400 * $triggerInfoData['numberofdays']);
            if ($triggerInfoData['autonotopenclicked'] == 'o') {
                $query = 'SELECT ls.subscriberid, ls.emailaddress FROM [|PREFIX|]list_subscribers AS ls LEFT OUTER JOIN [|PREFIX|]stats_emailopens AS steo ON(ls.subscriberid = steo.subscriberid AND steo.opentime>' . $afterStrTime . ' AND steo.statid IN (SELECT CAST(GROUP_CONCAT(sta.statid) AS CHAR) As AllQueue FROM [|PREFIX|]autoresponders as autores LEFT JOIN [|PREFIX|]stats_autoresponders as sta ON (autores.autoresponderid=sta.autoresponderid))) WHERE ls.listid IN (' . $triggerInfoData['nopen_autocontlist'] . ') AND steo.subscriberid IS NULL';
            } elseif ($triggerInfoData['autonotopenclicked'] == 'c') {
                $query = 'SELECT ls.subscriberid, ls.emailaddress FROM [|PREFIX|]list_subscribers AS ls LEFT OUTER JOIN [|PREFIX|]stats_linkclicks AS stlc ON(ls.subscriberid = stlc.subscriberid AND stlc.clicktime>' . $afterStrTime . ' AND stlc.statid IN (SELECT CAST(GROUP_CONCAT(sta.statid) AS CHAR) As AllQueue FROM [|PREFIX|]autoresponders as autores LEFT JOIN [|PREFIX|]stats_autoresponders as sta ON (autores.autoresponderid=sta.autoresponderid))) WHERE ls.listid IN (' . $triggerInfoData['nopen_autocontlist'] . ') AND stlc.subscriberid IS NULL';
            } else {
                $query = 'SELECT ls.subscriberid, ls.emailaddress FROM [|PREFIX|]list_subscribers AS ls LEFT OUTER JOIN [|PREFIX|]stats_linkclicks AS stlc ON(ls.subscriberid = stlc.subscriberid AND stlc.clicktime>' . $afterStrTime . ' AND stlc.statid IN (SELECT CAST(GROUP_CONCAT(sta.statid) AS CHAR) As AllQueue FROM [|PREFIX|]autoresponders as autores LEFT JOIN [|PREFIX|]stats_autoresponders as sta ON (autores.autoresponderid=sta.autoresponderid))) LEFT OUTER JOIN [|PREFIX|]stats_emailopens AS steo ON(ls.subscriberid = steo.subscriberid AND steo.opentime>' . $afterStrTime . ' AND steo.statid IN (SELECT CAST(GROUP_CONCAT(sta.statid) AS CHAR) As AllQueue FROM [|PREFIX|]autoresponders as autores LEFT JOIN [|PREFIX|]stats_autoresponders as sta ON (autores.autoresponderid=sta.autoresponderid))) WHERE ls.listid IN (' . $triggerInfoData['nopen_autocontlist'] . ') AND stlc.subscriberid IS NULL AND steo.subscriberid IS NULL';
            }

            $subscribeIdRS = $this->Db->Query($query);
            if ($subscribeIdRS == false) {
                return false;
            }

            $subscriberIds = [];

            while ($row = $this->Db->Fetch($subscribeIdRS)) {
                $subscriberIds[] = $row;
            }

            return $subscriberIds;
        }

    /**
     * NotifyOwner
     * Sends an email to the list owner(s) to tell them what's going on with a send.
     * eg:
     * - send has started
     * - send has finished
     * - send has been paused
     *
     * @param string $jobstatus The new jobstatus. This is used to work out the subject/message for the notification email.
     *
     * @uses SendStudio_Functions::PrintTime
     * @uses SendStudio_Functions::FormatNumber
     * @uses emailssent
     * @uses Send_API::NotifyOwner
     *
     * @return mixed Returns the status from the parent NotifyOwner call.
     */
    public function NotifyOwner($jobstatus = 's', $message = '')
    {
        $notify_subject = $this->reengage_details['reengagename'];

        require_once __DIR__ . '/language/language.php';

        $time = $this->sendstudio_functions->PrintTime();

        switch ($jobstatus) {
            case 'c':
                $subject = sprintf(GetLang('Addon_reengagement_Job_Subject_Complete'), $notify_subject);
                if ($this->emailssent == 1) {
                    $message = sprintf(GetLang('Addon_reengagement_Job_Message_Complete_One'), $notify_subject, $time);
                } else {
                    $message = sprintf(GetLang('Addon_reengagement_Job_Message_Complete_Many'), $notify_subject, $time, $this->sendstudio_functions->FormatNumber($this->emailssent));
                }
                break;
            case 'p':
                $subject = sprintf(GetLang('Addon_reengagement_Job_Subject_Paused'), $notify_subject);
                if ($this->emailssent == 1) {
                    $message = sprintf(GetLang('Addon_reengagement_Job_Message_Paused_One'), $notify_subject, $time);
                } else {
                    $message = sprintf(GetLang('Addon_reengagement_Job_Message_Paused_Many'), $notify_subject, $time, $this->sendstudio_functions->FormatNumber($this->emailssent));
                }
                break;

            case 't':
                $percent = 0;

                $subject = sprintf(GetLang('Addon_reengagement_Job_Subject_Timeout'), $notify_subject, $percent);
                if ($this->emailssent == 1) {
                    $message = sprintf(GetLang('Addon_reengagement_Job_Message_Timeout_One'), $notify_subject, $percent);
                } else {
                    $message = sprintf(GetLang('Addon_reengagement_Job_Message_Timeout_Many'), $notify_subject, $percent, $this->sendstudio_functions->FormatNumber($this->emailssent));
                }
                break;

            default:
                $subject = sprintf(GetLang('Addon_reengagement_Job_Subject_Started'), $notify_subject);
                $message = sprintf(GetLang('Addon_reengagement_Job_Message_Started'), $notify_subject, $time);
        }

        $this->notify_email = [
            'subject' => $subject,
            'message' => $message,
        ];

        $this->jobstatus = $jobstatus;

        return parent::NotifyOwner(null, null);
    }

    /**
     * ForgetSend
     * Forgets (or resets) the class variables between each re engagement campaign being sent.
     *
     * @uses Reengagement_Send_API::ForgetSend
     *
     * @return mixed Returns the status from the parent ForgetSend method.
     */
    protected function ForgetSend()
    {
        return parent::ForgetSend();
    }

    /**
     * _ProcessJob
     * This method does the "setup work" for a re engagement campaign.
     *
     * If a job is passed in that hasn't been started before, it will set everything up:
     * - create a "queue" of recipients
     * - clean the queue (remove banned/duplicate recipients etc)
     * - set up stats for each newsletter in the re engagement campaign
     * - save stats for the user sending the campaign to take off credits etc
     *
     * If a job is passed in that has been set up before, it just loads the data up.
     *
     * Once it has done either of those, it gives the details to the Reengagement_Send_API class
     * and then calls _ActionJob.
     * Based on what that returns, it will either mark the job as complete or not.
     *
     * @param int $jobid The specific job id we're going to process.
     *
     * @uses _jobid
     * @uses StartJob
     * @uses PauseJob
     * @uses LoadJob
     * @uses GetUser
     * @uses GetJobQueue
     * @uses CreateQueue
     * @uses JobQueue
     * @uses GetSubscribersFromSegment
     * @uses GetSubscribers
     * @uses RemoveDuplicatesInQueue
     * @uses RemoveBannedEmails
     * @uses RemoveUnsubscribedEmails
     * @uses QueueSize
     * @uses _FinishJob
     * @uses _ActionJob
     *
     * @return boolean Returns whether the job was processed or not. If a job could not be processed, it returns false. Otherwise it returns true.
     */
    private function _ProcessJob($jobid = 0)
    {
        if ($jobid <= 0) {
            return false;
        }
        $this->_jobid = $jobid;

        /**
         * Load the job, then start it.
         * We need to do this so when we call "StartJob" we can give it the reengageid to "start" as well.
         */
        $jobinfo = $this->LoadJob($jobid);

        $jobdetails = $jobinfo['jobdetails'];

        /**
         * Need to load the reengage campaign
         * before starting the job
         * so if we're in "t"imeout mode,
         * we can look at the stats
         * We also need the weighting's from the reengage campaign
         * to work it out.
         */
        $this->reengage_details = $this->_reengagement_api->Load($jobdetails['reengageid']);

        if (! $this->StartJob($jobid, $jobdetails['reengageid'])) {
            $this->PauseJob($jobid);
            return false;
        }

        // ----- "Login" to the system as the job's owner.
        $user = GetUser($jobinfo['ownerid']);
        IEM::userLogin($jobinfo['ownerid'], false);
        // -----

        $queueid = false;
        // if there's no queue, start one up.
        if (! $queueid = $this->GetJobQueue($jobid)) {
            $sendqueue = $this->CreateQueue('reengagement');
            $queueok = $this->JobQueue($jobid, $sendqueue);
            $send_criteria = $jobdetails['SendCriteria'];

            $queueinfo = [
                'queueid' => $sendqueue,
                'queuetype' => 'reengagement',
                'ownerid' => $jobinfo['ownerid'],
            ];

            if (isset($jobdetails['Segments']) && is_array($jobdetails['Segments'])) {
                $this->_subscribers_api->GetSubscribersFromSegment($jobdetails['Segments'], false, $queueinfo, 'nosort');
            } else {
                $this->_subscribers_api->GetSubscribers($send_criteria, [], false, $queueinfo, $sendqueue);
            }

            if (SENDSTUDIO_DATABASE_TYPE == 'pgsql') {
                $this->Db->OptimizeTable(SENDSTUDIO_TABLEPREFIX . 'queues');
            }

            $this->_subscribers_api->RemoveDuplicatesInQueue($sendqueue, 'reengagement', $jobdetails['Lists']);

            $this->_subscribers_api->RemoveBannedEmails($jobdetails['Lists'], $sendqueue, 'reengagement');

            $this->_subscribers_api->RemoveUnsubscribedEmails($jobdetails['Lists'], $sendqueue, 'reengagement');

            if (SENDSTUDIO_DATABASE_TYPE == 'pgsql') {
                $this->Db->OptimizeTable(SENDSTUDIO_TABLEPREFIX . 'queues');
            }

            $jobdetails['SendSize'] = $this->_subscribers_api->QueueSize($sendqueue, 'reengagement');

            $jobdetails['Stats'] = [];

            $jobdetails['SendQueue'] = $sendqueue;

            $this->Set('jobdetails', $jobdetails);

            $this->UpdateJobDetails();

            /**
             * This is to process the 'queueid' later in the code.
             */
            $queueid = $sendqueue;

            // This will make sure that the credit warning emails are also being send out from reengagement
            API_USERS::creditEvaluateWarnings($user->GetNewAPI());
        }

        $this->Db->OptimizeTable(SENDSTUDIO_TABLEPREFIX . 'queues');

        $queuesize = $this->_subscribers_api->QueueSize($queueid, 'reengagement');

        $this->_queueid = $queueid;

        $this->Set('statids', $jobdetails['Stats']);
        $this->Set('jobdetails', $jobdetails);
        $this->Set('jobowner', $jobinfo['ownerid']);

        /**
         * There's nothing left? Just mark it as done.
         */
        if ($queuesize == 0) {
            $this->_FinishJob();
            IEM::userLogout();
            return true;
        }
        $finished = $this->_ActionJob($jobid, $queueid, $jobdetails);

        if ($finished) {
            $this->_FinishJob();
        }

        IEM::userLogout();

        return true;
    }

    /**
     * _ActionJob
     * This loads the recipients/subscribers from the database
     * and loops through them to send them an email.
     *
     * Between each email being sent, it switches to another newsletter (if necessary)
     * so each recipient gets something different.
     *
     * @param int $jobid The job we are processing
     * @param int $queueid The job queue we are processing (this is where the recipient id's are all stored).
     *
     * @uses SetupJob
     * @uses NotifyOwner
     * @uses SetupAllNewsletters
     * @uses send_limit
     * @uses FetchFromQueue
     * @uses SetupCustomFields
     * @uses SetupNewsletter
     * @uses SendToRecipient
     * @uses Paused
     * @uses JobPaused
     * @uses TimeoutJob
     * @see _FinishJob
     *
     * @return boolean Returns whether the job has completely finished sending or not. This is used by _FinishJob to mark everything as done if necessary.
     */
    private function _ActionJob($jobid, $queueid, $jobdetails)
    {
        if (! $this->SetupJob($jobid, $queueid)) {
            return false;
        }

        $this->NotifyOwner(null, null);

        $emails_sent = 0;

        $this->Db->StartTransaction();

        $paused = false;

        /**
         * We need to work out how many to send to.
         *
         * Normally we just get 500 subscribers.
         * If that limit is set, we need the minimum to send to.
         *
         * Each time an email is sent, that number will be decreased
         * just in case the job is paused or the server kills it off,
         * it won't get reset to the "maximum" for the job
         * (which could end up emailing everyone).
         */
        $send_limit = self::send_limit;
        $triggerInfoData['numberofdays'] = $jobdetails['NumberOfDays'];
        $triggerInfoData['nopenlistid'] = implode(',', $jobdetails['SendCriteria']['List']);
        $triggerInfoData['nopen_autocontlist'] = implode(',', $jobdetails['SendCriteria']['List']);
        $triggerInfoData['notopenclicked'] = $jobdetails['TypeOfNotOpen'];
        $triggerInfoData['autonotopenclicked'] = $jobdetails['TypeOfNotOpen'];

        //$notNewsletter = $this->checkNewsletterNotOpenClick($triggerInfoData);

        //$notAutoresponder = $this->checkAutoresponderNotOpenClick($triggerInfoData);
        $recipients = [];
        $start_from = 0;
        $isPause = $jobdetails['isRemoveContact'];
        $totalRecords = $this->countNewsletterNotOpenClick($triggerInfoData);
        //echo "<br/>Total Records: ".$totalRecords."<br/><br/>";
        while ($notNewsletter = $this->checkNewsletterNotOpenClick($triggerInfoData, $start_from, $send_limit)) {
            if ((empty($notNewsletter) && $totalRecords < $start_from) || ($isPause == 'on' && $start_from > 30000)) {
                $paused = false;
                break;
            }

            $start_from = $start_from + $send_limit;
            //echo "Run:".$start_from."     ";
            if ((count($notNewsletter) > 0 && is_array($notNewsletter))) {
                $recipients = $notNewsletter;
            }

            $sent_to_recipients = [];

            foreach ($recipients as $recipientval) {
                $numberOfDaysUse = $recipientval['UseTime'];
                $send_results = $this->SendToRecipient($recipientval['subscriberid'], $queueid, $numberOfDaysUse);

                $sent_to_recipients[] = $recipientval['subscriberid'];
                //echo ",".$emails_sent." ";
                $emails_sent++;

                /**
                 * Whether to check if the job has been paused or not.
                 * We want to do that at the last possible moment..
                 */
                $check_paused = false;

                /**
                 * update lastupdatedtime so we can track what's going on.
                 * This is used so we can see if the server has crashed or the cron job has been stopped in mid-send.
                 *
                 * @see FetchJob
                 */
                if ($this->userpause > 0 || ($this->userpause == 0 && (($emails_sent % 5) == 0))) {
                    $query = 'UPDATE ' . SENDSTUDIO_TABLEPREFIX . "jobs SET lastupdatetime='" . $this->GetServerTime() . "' WHERE jobid='" . (int) $jobid . "'";
                    $this->Db->Query($query);
                    $this->Db->CommitTransaction();
                    $this->Db->StartTransaction();
                    $emails_sent = 0;
                    $check_paused = true;
                }

                // we should only need to pause if we successfully sent.
                if ($send_results['success'] > 0) {
                    $this->Pause();
                }

                /**
                 * See if the job has been paused or not through the control panel.
                 * If it has, break out of the recipient loop
                 * Then clean up the recipients we have sent to successfully
                 * then break out of the send 'job'.
                 */
                if ($check_paused) {
                    $paused = $this->JobPaused($jobid);
                    if ($paused) {
                        break;
                    }
                }
            }
            if (! empty($sent_to_recipients)) {
                $query = 'DELETE FROM ' . SENDSTUDIO_TABLEPREFIX . "queues WHERE queueid='" . $queueid . "' AND queuetype='reengagement' AND recipient NOT IN (" . implode(',', $sent_to_recipients) . ") AND processed='1'";
                $this->Db->Query($query);
            }
            if (! empty($sent_to_recipients)) {
                $query = 'DELETE FROM ' . SENDSTUDIO_TABLEPREFIX . "queues WHERE queueid='" . $queueid . "' AND queuetype='reengagement' AND recipient IN (" . implode(',', $sent_to_recipients) . ") AND processed='1'";
                $this->Db->Query($query);
            }
            //echo "<br/>Total Records: ".$totalRecords."<br/>";
            //echo "<br/>Start Records: ".$start_from."<br/>";
            if ($totalRecords < $start_from) {
                $paused = false;
                break;
            }
            if ($paused) {
                break;
            }
        }

        $this->Db->CommitTransaction();

        /**
         * By default, mark the job as "complete" and finished.
         */
        $jobstatus = 'c';
        $finished = true;

        if ($paused) {
            $jobstatus = 'p';
            $finished = true;
        }

        $this->Email_API->SMTP_Logout();

        $this->NotifyOwner($jobstatus, null);

        return $finished;
    }
}
