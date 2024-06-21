<?php
/**
 * This is the api file for re engagements to use.
 *
 * @author Fredrick Gabelmann <fredrick.gabelmann@interspire.com>
 *
 * @package SendStudio
 * @subpackage ReEngagements
 */

/**
 * Include the init file if we need to.
 * This allows us to use the base functions and settings.
 *
 * @uses init.php
 */
if (! class_exists('Send_API', false)) {
    require_once(dirname(__FILE__, 4) . '/functions/api/send.php');
}
require_once SENDSTUDIO_API_DIRECTORY . '/subscribers.php';
require_once SENDSTUDIO_API_DIRECTORY . '/subscribers.php';

/**
 * This handles sending a re engagement campaign.
 * It is mostly used as a cache for multiple email objects, multiple newsletters etc to be saved in.
 * It handles common functionality for popup window sending and also cron re engagement sending.
 *
 * It also handles pausing, deleting, resuming and starting a re engagement send.
 *
 * @package SendStudio
 * @subpackage ReEngagements
 */
class ReEngagement_Send_API extends Send_API
{
    /**
     * reengagecampaign_details
     * This is a 'cache' of the reengage campaign details we are currently sending.
     * This is going to be used mainly for notifying the list owner's of a job's start/finish
     *
     * @usedby _ProcessJob
     * @usedby _ActionJob
     * @usedby ForgetSend
     */
    protected $reengagecampaign_details = [];

    /**
     * queuetype
     * This is used by the parent Send_API to work out which queue to process/clean up.
     *
     * @var string Always set to 'reengagement'
     *
     * @see Send_API
     * @see Send_API::SendToRecipient
     * @see Jobs_API::MarkAsProcessed
     */
    protected $queuetype = 'reengagement';

    /**
     * statids is an array of:
     * - newsletterid => statid
     * so each newsletter being sent has it's own stat id.
     *
     * @Var Array $statids An array of newsletter => statid relationships.
     *
     * @see SetupNewsletter
     * @see SetupEmail
     */
    protected $statids = [];

    /**
     * _jobid
     * The current job id being processed.
     */
    protected $_jobid = 0;

    /**
     * _queueid
     * The current queueid being processed.
     */
    protected $_queueid = 0;

    /**
     * _sending_newsletter
     * Which newsletter id we are sending.
     * This is used to work out which custom fields to give to the email api for replacement.
     *
     * @var int Which newsletter id we are currently processing.
     *
     * @see SetupNewsletter
     * @see SetupCustomFields
     * @see SendToRecipient
     * @see SetupEmail
     */
    private int $_sending_newsletter = -1;

    /**
     * __construct
     * Does nothing apart from call the parent constructor.
     *
     * @uses Send_API::__construct
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * SetupJob
     * This sets up some local class variables after checking a proper job/queue has been loaded.
     * It mainly works out what 'pausetime' to use between each email being sent.
     *
     * @param int $jobid The job we are processing
     * @param int $queueid The job queue we are processing
     *
     * @uses IsQueue
     * @uses _jobid
     * @uses _queueid
     * @uses GetUser
     * @uses jobowner
     * @uses User_API::perhour
     * @uses userpause
     *
     * @return boolean Returns false if the queue is invalid. Otherwise sets the appropriate class variables and then returns true.
     */
    public function SetupJob($jobid = 0, $queueid = 0)
    {
        $is_queue = $this->IsQueue($queueid, $this->queuetype);
        if (! $is_queue) {
            return false;
        }

        $this->_jobid = $jobid;
        $this->_queueid = $queueid;

        $this->user = GetUser($this->jobowner);

        return true;
    }

    /**
     * SendToRecipient
     * Sends an email to a particular recipient.
     *
     * It calls SetupEmail to load the right email into the Email_API class variable
     * then calls the Send_API::SendToRecipient method.
     *
     * After that has been called, it updates job details which includes how many emails were sent per newsletter
     * and how many emails are left to send overall.
     *
     * Then it calls UpdateJobDetails which actually saves the new details into the database.
     *
     * @param int $recipient The recipient we are sending to. This is passed to the Send_API::SendToRecipient method to do it's work.
     * @param int $queueid The queue we are processing. This is passed to the Send_API::SendToRecipient method to do it's work.
     *
     * @uses jobdetails
     * @uses UpdateJobDetails
     * @see language/language.php for descriptions and error codes you can use here.
     * @see API::Save_Unsent_Recipient as well.
     *
     * @return array Returns an array of the mailing results, same as Send_API::SendToRecipient
     */
    public function SendToRecipient($recipient = 0, $queueid = 0, $numberOfDaysUse = 0)
    {
        /**
         * These are for the send_api sendtorecipient method to use.
         */
        $newListId = $this->jobdetails['TransferOnLists'];
        $numberOfDays = $numberOfDaysUse;
        $reengageid = $this->jobdetails['reengageid'];

        $this->_ProcessJob_AddList($newListId, $recipient, $numberOfDays, $reengageid);

        if ($this->jobdetails['isRemoveContact'] == 'on') {
            $nopenlistid = implode(',', $this->jobdetails['SendCriteria']['List']);
            $this->_ProcessJob_RemoveList($recipient, $nopenlistid);
        }
        $mail_result['success'] = 1;
        /**
         * Once we've worked out whether the email was sent or not,
         * save the details so we can update stats later on with the right results.
         *
         * Right now, we only care about success/failure.
         */
        if ($mail_result['success'] > 0) {
            $this->jobdetails['sendinfo']['email_results'][$this->_sending_newsletter]['success']++;
        } else {
            $this->jobdetails['sendinfo']['email_results'][$this->_sending_newsletter]['fail']++;
        }
        $this->jobdetails['sendinfo']['sendsize_left']--;

        $this->UpdateJobDetails();

        return $mail_result;
    }

    /**
     * UpdateJobDetails
     * This updates the job details information in the database.
     * This allows us to keep up to date with the last newsletter that was sent in the list to send.
     * That in turn means if we pause a re engagement send (or it dies if it's running via scheduled sending)
     * we'll know where to start back up again and we should get a pretty even spread of campaigns.
     *
     * @uses jobdetails
     * @uses _jobid
     *
     * @return boolean Returns whether the update query worked or not.
     */
    public function UpdateJobDetails()
    {
        $query = "UPDATE [|PREFIX|]jobs SET jobdetails='" . $this->Db->Quote(serialize($this->jobdetails)) . "' WHERE jobid=" . (int) $this->_jobid;
        $result = $this->Db->Query($query);
        if ($result) {
            return true;
        }
        return false;
    }

    /**
     * StartJob
     * Marks a job as 'started' in the database
     * both in the 'jobs' table and in the 'reengagements' table for ease of access.
     * If the jobstatus is 'i' it also updates the 'lastsent' field for the re engagement table.
     *
     * @param int $jobid The job we are starting
     * @param int $reengageid The re engagement we are starting
     * @param string $jobstatus The new jobstatus. It should be 'i' but can also be 'w' for 'w'aiting to be sent.
     *
     * @uses Jobs_API::StartJob
     *
     * @return boolean Returns true if the parent startjob method returns true, and the reengagements table was successfully updated. Returns false if any of those conditions fail.
     */
    public function StartJob($jobid = 0, $reengageid = 0, $jobstatus = 'i')
    {
        $this->Db->StartTransaction();
        if ($jobstatus == 'i') {
            $status = parent::StartJob($jobid);
            if (! $status) {
                $this->Db->RollbackTransaction();
                return false;
            }
        }
        $query = "UPDATE [|PREFIX|]reengagements SET jobstatus='" . $this->Db->Quote($jobstatus) . "', jobid=" . (int) $jobid;
        if ($jobstatus == 'i') {
            $query .= ', lastsent=' . $this->GetServerTime();
        }
        $query .= ' WHERE reengageid=' . (int) $reengageid;
        $result = $this->Db->Query($query);
        if ($result) {
            $this->Db->CommitTransaction();
            return true;
        }
        $this->Db->RollbackTransaction();
        return false;
    }

    /**
     * FinishJob
     * Marks a job as 'finished' in the database.
     * Updates both the 'jobs' table and the 'reengagements' table for ease of access.
     * It also updates the statistics table with the current time for the "finishtime".
     *
     * @param int $jobid The job we are finishing
     * @param int $reengageid The re engagement we are finishing
     *
     * @uses Jobs_API::FinishJob
     *
     * @return boolean Returns true if the parent finishjob method returns true, and the reengagements table was successfully updated. Returns false if any of those conditions fail.
     */
    public function FinishJob($jobid = 0, $reengageid = 0)
    {
        $this->Db->StartTransaction();
        $status = parent::FinishJob($jobid);
        if (! $status) {
            $this->Db->RollbackTransaction();
            return false;
        }

        $reengageid = (int) $reengageid;

        $queries = [];
        $queries[] = "UPDATE [|PREFIX|]reengagements SET jobstatus='c', jobid=" . (int) $jobid . ' WHERE reengageid=' . $reengageid;
        $queries[] = 'UPDATE [|PREFIX|]reengagement_statistics SET finishtime=' . $this->GetServerTime() . ' WHERE reengageid=' . $reengageid . ' AND jobid=' . $jobid;

        foreach ($queries as $query) {
            $result = $this->Db->Query($query);
            if (! $result) {
                $this->Db->RollbackTransaction();
                return false;
            }
        }
        $this->Db->CommitTransaction();
        return true;
    }

    /**
     * PauseJob
     * Pauses a job in the database.
     * Updates both the 'jobs' table and the 'reengagements' table.
     *
     * If no reengageid is supplied, it uses the _GetRssByJobid method to work out which reengage we are processing.
     *
     * @param int $jobid The job we are pausing
     * @param int $reengageid The reengageid we are pausing. If not supplied, it is worked out.
     *
     * @uses Jobs_API::PauseJob
     * @uses _GetRssByJobid
     *
     * @return boolean Returns true if the parent 'pausejob' method returns true and if we're able to find an appropriate re engagement (and we update that re engagement with the right info).
     * Returns false if any of those conditions fail.
     */
    public function PauseJob($jobid = 0, $reengageid = 0)
    {
        $this->Db->StartTransaction();
        $status = parent::PauseJob($jobid);
        if (! $status) {
            $this->Db->RollbackTransaction();
            return false;
        }

        $reengageid = (int) $reengageid;
        $jobid = (int) $jobid;

        if ($reengageid <= 0) {
            $reengageid = $this->_GetRssByJobid($jobid);
        }

        if ($reengageid <= 0) {
            $this->Db->RollbackTransaction();
            return false;
        }

        $query = "UPDATE [|PREFIX|]reengagements SET jobstatus='p', jobid=" . $jobid . ' WHERE reengageid=' . $reengageid;
        $result = $this->Db->Query($query);
        if ($result) {
            $this->Db->CommitTransaction();
            return true;
        }
        $this->Db->RollbackTransaction();
        return false;
    }

    /**
     * ResumeJob
     * Marks a job as 'resumed' in the database.
     * Updates both the 'jobs' table and the 'reengagements' table.
     *
     * If no reengageid is supplied, it uses the _GetRssByJobid method to work out which reengage we are processing.
     *
     * @param int $jobid The job we are resuming.
     * @param int $reengageid The reengageid we are resuming. If not supplied, it is worked out.
     *
     * @uses Jobs_API::ResumeJob
     * @uses _GetRssByJobid
     *
     * @return boolean Returns true if the parent 'resumejob' method returns true and if we're able to find an appropriate re engagement (and we update that re engagement with the right info).
     * Returns false if any of those conditions fail.
     */
    public function ResumeJob($jobid = 0, $reengageid = 0)
    {
        $this->Db->StartTransaction();
        $status = parent::ResumeJob($jobid);
        if (! $status) {
            $this->Db->RollbackTransaction();
            return false;
        }

        $reengageid = (int) $reengageid;

        if ($reengageid <= 0) {
            $reengageid = $this->_GetRssByJobid($jobid);
        }

        if ($reengageid <= 0) {
            $this->Db->RollbackTransaction();
            return false;
        }

        $query = "UPDATE [|PREFIX|]reengagements SET jobstatus='w' WHERE reengageid=" . $reengageid;
        $result = $this->Db->Query($query);
        if ($result) {
            $this->Db->CommitTransaction();
            return true;
        }
        $this->Db->RollbackTransaction();
        return false;
    }

    /**
     * DeleteJob
     * Deletes a job from the 'jobs' table and also updates the 'reengagements' table with the new jobstatus/jobid.
     *
     * If no reengageid is supplied, it uses the _GetRssByJobid method to work out which reengage we are processing.
     *
     * @param int $jobid The job we are deleting.
     * @param int $reengageid The reengageid we are deleting. If not supplied, it is worked out.
     *
     * @uses Jobs_API::Delete
     * @uses _GetRssByJobid
     *
     * @return boolean Returns true if the parent 'delete' method returns true and if we're able to find an appropriate re engagement (and we update that re engagement with the right info).
     * Returns false if any of those conditions fail.
     */
    public function DeleteJob($jobid = 0, $reengageid = 0)
    {
        $reengageid = (int) $reengageid;

        if ($reengageid <= 0) {
            $reengageid = $this->_GetRssByJobid($jobid);
        }

        if ($reengageid <= 0) {
            return false;
        }

        $this->Db->StartTransaction();

        $status = parent::Delete($jobid);
        if (! $status) {
            $this->Db->RollbackTransaction();
            return false;
        }

        $query = 'UPDATE [|PREFIX|]reengagements SET jobstatus=null, jobid=0 WHERE reengageid=' . $reengageid;
        $result = $this->Db->Query($query);
        if ($result) {
            $this->Db->CommitTransaction();
            return true;
        }
        $this->Db->RollbackTransaction();
        return false;
    }

    /**
     * SaveRssStats
     * This saves re engagement statistics into the database
     * It also saves the reengage send -> stats_newsletter statid relationship
     *
     * This is mainly because you can delete 'jobs' (which normally contains this data)
     * but we need this to be permanent for displaying later on.
     *
     * @param int $reengageid The reengage we are saving stats for.
     * @param int $jobid The job we are sending
     * @param array $statids The newsletter_stats statid's that were created in the send setup.
     *
     * @return boolean Returns true if the stats can be saved, otherwise false (or if any data is invalid it returns false).
     */
    public function SaveRssStats($reengageid = 0, $jobid = 0, $statids = [])
    {
        $reengageid = (int) $reengageid;
        $jobid = (int) $jobid;
        $statids = $this->CheckIntVars($statids);

        if ($reengageid <= 0 || $jobid <= 0 || empty($statids)) {
            return false;
        }

        $this->Db->StartTransaction();

        $query = 'INSERT INTO [|PREFIX|]reengagement_statistics (reengageid, jobid, starttime, finishtime, hiddenby)
			VALUES
			(' . $reengageid . ', ' . $jobid . ', ' . $this->GetServerTime() . ', 0, 0)';

        $result = $this->Db->Query($query);
        if (! $result) {
            $this->Db->RollbackTransaction();
            return false;
        }

        $reengage_statid = $this->Db->LastId('[|PREFIX|]reengagement_statistics_sequence');
        if ($reengage_statid <= 0) {
            $this->Db->RollbackTransaction();
            return false;
        }

        foreach ($statids as $statid) {
            $query = 'INSERT INTO [|PREFIX|]reengagement_statistics_newsletters (reengage_statid, newsletter_statid) VALUES (' . $reengage_statid . ', ' . $statid . ')';
            $result = $this->Db->Query($query);
            if (! $result) {
                $this->Db->RollbackTransaction();
                return false;
            }
        }

        $this->Db->CommitTransaction();
        return true;
    }

    /**
     * TimeoutJob
     * Marks a job for "timeout".
     * A "timeout" job status is the state between the first X % has been sent to a list
     * and when the rest of the newsletter(s) should be sent to the other (100-X)% of the list.
     *
     * A job is "timed out" from the end of the first X%, this method works out the delay before sending the rest.
     *
     * @param int $jobid The job we are timing out.
     * @param int $reengageid The reengage campaign we're timing out. If not supplied, it is worked out.
     * @param int $hoursafter The number of hours to time out the campaign for. If not supplied, it is worked out.
     *
     * @uses _GetRssByJobid
     * @uses Reengagement_API::Load
     *
     * @return boolean Returns true if the job can be timed out and an appropriate hoursafter delay is set (or calculated). If the re engagement can't be found, or an invalid hoursafter
     * delay is passed in/calculated, it will return false.
     */
    protected function TimeoutJob($jobid = 0, $reengageid = null, $hoursafter = null)
    {
        $jobid = (int) $jobid;
        if ($jobid <= 0) {
            return false;
        }

        if ($reengageid === null) {
            $reengageid = $this->_GetRssByJobid($jobid);
        }

        $reengageid = (int) $reengageid;
        if ($reengageid <= 0) {
            return false;
        }

        if ($hoursafter === null) {
            $reengage_api = new Reengagement_API();
            $reengage_details = $reengage_api->Load($reengageid);
            $hoursafter = $reengage_details['reengagedetails']['hoursafter'];
        }

        $hoursafter = (int) $hoursafter;

        if ($hoursafter <= 0) {
            return false;
        }

        $this->Db->StartTransaction();

        $new_jobtime = $this->GetServerTime() + ($hoursafter * 3600);

        $query = "UPDATE [|PREFIX|]jobs SET jobstatus='t', jobtime=" . $new_jobtime . ' WHERE jobid=' . $jobid;
        $result = $this->Db->Query($query);
        if (! $result) {
            $this->Db->RollbackTransaction();
            return false;
        }

        $query = "UPDATE [|PREFIX|]reengagements SET jobstatus='t' WHERE reengageid=" . $reengageid;
        $result = $this->Db->Query($query);
        if (! $result) {
            $this->Db->RollbackTransaction();
            return false;
        }

        $this->Db->CommitTransaction();
        return true;
    }

    /**
     * ForgetSend
     * Forgets the current re engagement campaign being sent.
     * This is used between re engagement campaigns being sent in one cron run.
     *
     * @uses ResetSend
     * @uses newsletters
     * @uses statids
     * @uses custom_fields_to_replace
     * @uses to_customfields
     * @uses _sending_newsletter
     * @uses _jobid
     * @uses _queueid
     * @uses jobdetails
     * @uses reengagecampaign_details
     */
    protected function ForgetSend()
    {
        $this->statids = [];
        $this->custom_fields_to_replace = [];
        $this->to_customfields = [];
        $this->_sending_newsletter = -1;
        $this->_jobid = 0;
        $this->_queueid = 0;
        $this->jobdetails = [];
        $this->reengagecampaign_details = [];
    }

    /**
     * _ProcessJob_AddList
     * Add subscriber to another list(s)
     *
     * This function will return an associative array with the following value:
     * - error => Boolean => Indicates whether or not the function is successful
     * - halt => Boolean => Indicates whether or not to halt the operation
     *
     * @param integer $subscriberid Subscriber ID to be copied to another list
     * @return array Returns an array of the status (see comment above)
     */
    private function _ProcessJob_AddList($listid, $subscriberid, $numberOfDays, $reengageid)
    {
        $return = [
            'error' => true,
            'halt' => false,
        ];

        $listapi = new Lists_API();
        $subscriberapi = new Subscribers_API();
        $subscriber_record = $subscriberapi->LoadSubscriberList($subscriberid);
        if (empty($subscriber_record)) {
            trigger_error("Cannot check database for particular subscriber ({$subscriberid})");
            //$this->_log("Cannot check database for particular subscriber ({$subscriberid})");
            $return['halt'] = true;
            $return['error'] = true;
            return $return;
        }

        $subscriber_customfields = (isset($subscriber_record['CustomFields']) && is_array($subscriber_record['CustomFields'])) ? $subscriber_record['CustomFields'] : [];

        $lists = $listid;
        if (! is_array($lists)) {
            $lists = [$lists];
        }

        $this->Db->StartTransaction();
        foreach ($lists as $list) {
            if ($list == $subscriber_record['listid']) {
                continue;
            }

            $duplicate = $subscriberapi->IsSubscriberOnList($subscriber_record['emailaddress'], $list);

            if ($duplicate) {
                $unsubscribed_check = $subscriberapi->IsUnSubscriber(false, $list, $duplicate);
                if ($unsubscribed_check) {
                    ////$this->_log('Cannot add contact to this list: Is already in the list as unsubscriber');
                }
                ////$this->_log('Cannot add contact to this list: Is already in the list');

                continue;
            }

            [$banned, $msg] = $subscriberapi->IsBannedSubscriber($subscriber_record['emailaddress'], $list, false);
            if ($banned) {
                //$this->_log('Cannot add contact to this list: Email is banned to be added to the list');
                continue;
            }

            // ----- Save subscriber and custom fields
            $this->Db->StartTransaction();

            $this->confirmcode = false;
            //$this->confirmed = $subscriber_record['confirmed'];
            $subscriberapi->confirmed = 1;
            $this->confirmdate = 0;
            $subscriberid = $subscriberapi->AddToList($subscriber_record['emailaddress'], $list);
            if (! $subscriberid) {
                $this->Db->RollbackTransaction();
                //$this->_log('Cannot add contact to this list: API returned FALSE value');
                continue;
            }
            $updtQry = "UPDATE [|PREFIX|]list_subscribers SET subscribedate='" . $this->GetServerTime() . "' WHERE subscriberid='" . $subscriberid . "'";
            $resultUpdtQry = $this->Db->Query($updtQry);

            $insertQry = "INSERT INTO [|PREFIX|]reengagements_listinfo (reengageid, listids, subscriberid, numberofdays, transferdate) VALUES ('" . $reengageid . "','" . implode(',', $lists) . "','" . $subscriberid . "', '" . $numberOfDays . "','" . $this->GetServerTime() . "')";
            $result = $this->Db->Query($insertQry);

            $ListCustomFields = $listapi->GetCustomFields($list);
            $allfieldok = true;

            if (! empty($ListCustomFields)) {
                $transferred = [];
                // Match custom field
                foreach ($subscriber_customfields as $field) {
                    // Found an exact match
                    if (array_key_exists($field['fieldid'], $lists)) {
                        $subscriberapi->SaveSubscriberCustomField($subscriberid, $field['fieldid'], $field['data']);
                        $transferred[] = $field['fieldid'];
                        continue;
                    }

                    // Check if there are any "name" and "type" match
                    foreach ($ListCustomFields as $fieldid => $listfield) {
                        if ((strtolower((string) $listfield['name']) == strtolower((string) $field['fieldname'])) && ($listfield['fieldtype'] == $field['fieldtype'])) {
                            $subscriberapi->SaveSubscriberCustomField($subscriberid, $fieldid, $field['data']);
                            $transferred[] = $field['fieldid'];
                            continue;
                        }
                    }
                }

                // Check if list required fields are all added in
                $allfieldok = true;
                foreach ($ListCustomFields as $fieldid => $field) {
                    if ($field['required'] && ! in_array($fieldid, $transferred)) {
                        $allfieldok = false;
                        break;
                    }
                }
            }

            if ($allfieldok) {
                $this->Db->CommitTransaction();
            } else {
                //$this->_log('Cannot add contact to this list: Not all of the required custom fields are available to copied across');
                $this->Db->RollbackTransaction();
                continue;
            }
            // -----
        }
        $this->Db->CommitTransaction();

        // Record log
        //if (!$this->RecordLogActions("NotOk", $subscriberid, 'addlist')) {
        //$this->_log('Cannot write log to the database...');
        //}

        $return['error'] = false;
        return $return;
    }

    /**
     * _ProcessJob_RemoveList
     * Remove subscribers from list
     *
     * This function will return an associative array with the following value:
     * - error => Boolean => Indicates whether or not the function is successful
     * - halt => Boolean => Indicates whether or not to halt the operation
     *
     * @param integer $subscriberid Subscriber ID to be removed
     * @return array Returns an array of the status (see comment above)
     */
    private function _ProcessJob_RemoveList($subscriberid, $nopenlistid)
    {
        $return = [
            'error' => true,
            'halt' => false,
        ];

        $subscriberapi = new Subscribers_API();
        $subscriber_record = $subscriberapi->LoadSubscriberList($subscriberid);

        $query = 'SELECT subscriberid, listid FROM [|PREFIX|]list_subscribers WHERE listid IN (' . $nopenlistid . ") AND emailaddress = '" . $subscriber_record['emailaddress'] . "' AND confirmed='1' AND unsubscribeconfirmed='0'";
        $result = $this->Db->Query($query);
        while ($row = $this->Db->Fetch($result)) {
            $return[]['subscriberid'] = $row['subscriberid'];
            $subscriberidWhile = $row['subscriberid'];
            $subscriberidListId = $row['listid'];
            [$status, $msg] = $subscriberapi->DeleteSubscriber('', $subscriberidListId, $subscriberidWhile);
        }

        if (! $status) {
            //$this->_log('Unable to delete subscriber from list.. Reason given: ' . $msg);
            return $return;
        }

        // Record log
        //if (!$this->RecordLogActions("action", $subscriberid, 'removelist')) {
        //$this->_log('Cannot write log to the database...');
        //}

        $return['error'] = false;
        $return['halt'] = false;
        return $return;
    }

    /**
     * _GetRssByJobid
     * Gets a re engagement id based on the jobid passed in.
     * This is used if we only have a jobid to go by and no reengageid (eg we're editing/deleting a 'scheduled job')
     *
     * @param int $jobid The job we're actioning.
     *
     * @return int Returns the reengageid the job is for - comes from the fkid field from the tables table.
     */
    private function _GetRssByJobid($jobid = 0)
    {
        return $this->Db->FetchOne('SELECT fkid FROM [|PREFIX|]jobs WHERE jobid=' . (int) $jobid . " AND fktype='reengagement'");
    }
}
