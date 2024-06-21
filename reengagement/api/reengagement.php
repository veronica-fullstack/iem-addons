<?php
/**
 * This is the api file for re engagements to use.
 * It allows you to create, update, delete, load a re engagement.
 *
 * @package SendStudio
 * @subpackage ReEngagements
 */

/**
 * This is the re engagements api class.
 * It handles all of the database stuff (creating/loading/updating/searching).
 *
 * @uses IEM::getDatabase()
 *
 * @package SendStudio
 * @subpackage ReEngagements
 */
class ReEngagement_API extends API
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

    /**
     * Create
     * Create a new re engagement in the database.
     *
     * The details are all passed in as an array:
     * <code>
     * $reengage_campaign_details = array (
     * 	'reengagename' => 'My re engagement campaign name',
     * 	'reengagement_list' => array (
     * 			1
     * 		),
     * 	'userid' => 0,
     * 	'reengage_typeof' => 'http://feed.domain.com',
     * 	'reengagedetails' => array (
     * 		'max_numberofdays' => 10,
     * 	),
     * );
     * </code>
     *
     * reengagement_list is an array of campaign id's to include in the re engagement.
     * userid is the id of the user creating the campaign.
     * @param array $reengage_campaign_details The details for the reengage campaign as an array.
     *
     * @return false if any of the required details are missing.
     */
    public function Create($reengage_campaign_details = [])
    {
        $required_fields = ['reengagename',  'userid', 'reengage_typeof', 'duration_type', 'reengagedetails'];

        foreach ($required_fields as $field) {
            if (! isset($reengage_campaign_details[$field])) {
                return false;
            }
        }

        if ($reengage_campaign_details['reengage_typeof'] == '') {
            return false;
        }

        if ($reengage_campaign_details['duration_type'] == '') {
            return false;
        }

        /**
         * Check the userid is a valid id.
         */
        $userid = (int) $reengage_campaign_details['userid'];
        if ($userid <= 0) {
            return false;
        }

        $timenow = $this->GetServerTime();

        $this->db->StartTransaction();

        $query = 'INSERT INTO [|PREFIX|]reengagements';
        $query .= ' (reengagename, reengage_typeof, duration_type, reengagedetails, createdate, userid, jobstatus)';
        $query .= ' VALUES';
        $query .= " ('" . $this->db->Quote($reengage_campaign_details['reengagename']) . "', '" . $this->db->Quote($reengage_campaign_details['reengage_typeof']) . "', '" . $this->db->Quote($reengage_campaign_details['duration_type']) . "', '" . $this->db->Quote(serialize($reengage_campaign_details['reengagedetails'])) . "', " . $timenow . ', ' . $userid . ", 'n')";

        $result = $this->db->Query($query);
        if (! $result) {
            $this->db->RollBackTransaction();
            return false;
        }

        $reengage_id = $this->db->LastId('[|PREFIX|]reengagements_sequence');

        $this->db->CommitTransaction();
        return $reengage_id;
    }

    /**
     * Copy
     * This copies a re engagement campaign almost exactly
     * The only things that aren't copied are:
     * - the "name" - which gets a prefix ("CopyPrefix" language variable).
     * - the create time (gets set to "now")
     * - who created the re engagement campaign (passed in)
     *
     * @param int $old_id The old re engagement id to copy
     * @param int $copied_by Who is copying the re engagement so it can be correctly assigned.
     *
     * @return boolean Returns false if invalid id's are passed through, or if anything goes wrong in the copy database queries.
     * Otherwise if everything is OK, returns the new re engagement name.
     */
    public function Copy($old_id = 0, $copied_by = 0)
    {
        $old_id = (int) $old_id;
        if ($old_id <= 0) {
            return false;
        }

        $copied_by = (int) $copied_by;
        if ($copied_by <= 0) {
            return false;
        }

        $timenow = $this->GetServerTime();

        $this->db->StartTransaction();

        $query = 'INSERT INTO [|PREFIX|]reengagements';
        $query .= ' (reengagename, reengage_typeof, duration_type, reengagedetails, createdate, userid, jobstatus)';
        $query .= ' SELECT ' . $this->db->Concat("'" . GetLang('CopyPrefix') . "'", 'reengagename') . ' AS reengagename, reengage_typeof, duration_type, reengagedetails, ' . $timenow . ', ' . $copied_by . ", 'n'";
        $query .= ' FROM [|PREFIX|]reengagements WHERE reengageid=' . $old_id;

        $result = $this->db->Query($query);
        if (! $result) {
            $this->db->RollBackTransaction();
            return false;
        }

        $reengage_id = $this->db->LastId('[|PREFIX|]reengagements_sequence');
        if ($reengage_id <= 0) {
            $this->db->RollBackTransaction();
            return false;
        }

        $this->db->CommitTransaction();

        $query = 'SELECT reengagename FROM [|PREFIX|]reengagements WHERE reengageid=' . intval($reengage_id);
        return $this->db->FetchOne($query);
    }

    /**
     * Delete
     * Deletes a re engagement from the database.
     * It can only do one at a time as it checks each one to make sure there are no "jobs" left over that are:
     * - in progress
     * - waiting to be sent
     * - paused
     *
     * If they are any of those statuses, the job needs to be cleaned up first.
     *
     * This is done as a separate action as user credits need to be re-allocated depending on the job's status and where it's up to.
     * For example, a job that has sent 100 out of 1,000 emails can re-credit the user with 900 emails.
     *
     * @param int $reengageid The re engagement campaign to delete.
     *
     * @return boolean Returns true if the id was deleted from the database, otherwise false.
     */
    public function Delete($reengageid = 0)
    {
        $reengageid = (int) $reengageid;
        if ($reengageid <= 0) {
            return false;
        }

        $this->db->StartTransaction();

        $query = 'SELECT jobstatus FROM [|PREFIX|]reengagements WHERE reengageid=' . $reengageid;
        $jobstatus = $this->db->FetchOne($query);
        if (! in_array($jobstatus, ['c', 'w', 'p', 'n', null, '', false])) {
            return false;
        }

        /**
         * Clean up any completed jobs for these re engagements.
         */
        $query = "DELETE FROM [|PREFIX|]jobs WHERE jobtype='reengagement' AND jobid IN (SELECT jobid FROM [|PREFIX|]reengagements WHERE reengageid=" . $reengageid . ')';
        $result = $this->db->Query($query);
        if (! $result) {
            $this->db->RollBackTransaction();
            return false;
        }

        $query = 'DELETE FROM [|PREFIX|]reengagements_listinfo WHERE reengageid=' . $reengageid . '';
        $result = $this->db->Query($query);
        if (! $result) {
            $this->db->RollBackTransaction();
            return false;
        }

        $query = 'DELETE FROM [|PREFIX|]reengagements WHERE reengageid=' . $reengageid;
        $result = $this->db->Query($query);
        if (! $result) {
            $this->db->RollBackTransaction();
            return false;
        }

        $this->db->CommitTransaction();
        return true;
    }

    /**
     * Load a re engagement based on the id
     * and return the details back to the calling object
     *
     * If the re engagement can be loaded the array looks like this:
     * <code>
     * $reengage_details = array (
     * 	'reengageid' => $reengageid,
     * 	'reengagename' => 'Re Engagement name',
     * 	'reengagement_list' => array (
     * 			'campaignid' => 'Campaign Name'
     * 		),
     * 	'reengage_typeof' => 'http://feeds.domain.com',
     * 	'reengagedetails' => array (
     * 		'max_numberofdays' => 10
     * 	),
     * );
     * </code>
     *
     * @param int $reengageid The re engagement id to load.
     *
     * @return array Returns an array containing the re engagement details. If the id is invalid (it can't be loaded), then an empty array is returned.
     */
    public function Load($reengageid = 0)
    {
        $reengageid = (int) $reengageid;
        if ($reengageid <= 0) {
            return [];
        }

        $return = [];

        $query = 'SELECT * FROM [|PREFIX|]reengagements WHERE reengageid=' . $reengageid;
        $result = $this->db->Query($query);
        $return = $this->db->Fetch($result);

        $return['reengagedetails'] = unserialize($return['reengagedetails']);

        $return['reengagement_list'] = [];
        /*
        $query = "SELECT newsletterid, name FROM [|PREFIX|]newsletters n INNER JOIN [|PREFIX|]reengagement_list spt ON (n.newsletterid=spt.campaignid) WHERE spt.reengageid=" . $reengageid . " ORDER BY n.name ASC";
        $result = $this->db->Query($query);
        while ($row = $this->db->Fetch($result)) {
            $return['reengagement_list'][$row['newsletterid']] = $row['name'];
        }
        */
        return $return;
    }

    /**
     * Save
     * Updates a re engagement campaign in the database to have new information.
     *
     * @param int $reengageid The re engagement id to update
     * @param array $reengagedetails The re engagement details to use, which includes the name, re engagement type, campaigns to include etc.
     *
     * @see Create
     *
     * @return boolean Returns false if the reengageid is invalid or if anything goes wrong in the update process(es).
     * Returns true if everything works.
     */
    public function Save($reengageid = 0, $reengagedetails = [])
    {
        $reengageid = (int) $reengageid;
        if ($reengageid <= 0) {
            return false;
        }

        $this->db->StartTransaction();

        $query = 'UPDATE [|PREFIX|]reengagements SET ';
        $query .= " reengagename='" . $this->db->Quote($reengagedetails['reengagename']) . "', ";
        $query .= " reengage_typeof='" . $this->db->Quote($reengagedetails['reengage_typeof']) . "', ";
        $query .= " duration_type='" . $this->db->Quote($reengagedetails['duration_type']) . "', ";
        if ($reengagedetails['duration_type'] == 2) {
            $query .= ' lastsent=0, ';
        }
        $query .= " reengagedetails='" . $this->db->Quote(serialize($reengagedetails['reengagedetails'])) . "', jobstatus='n' ";
        $query .= ' WHERE reengageid=' . $reengageid;
        $result = $this->db->Query($query);
        if (! $result) {
            $this->db->RollBackTransaction();
            return false;
        }

        $this->db->CommitTransaction();
        return true;
    }

    /**
     * GetReEngagements
     * Returns an array of re engagement details.
     * It also sorts the results in a particular order.
     * The sort details include a field name and an order.
     * The field name defaults to the reengagename, but can be one of the extra fields:
     * - createdate
     * - reengage_typeof
     * - lastsent
     *
     * The sort order has to either be 'asc' or 'desc' and defaults to 'asc'.
     *
     * The sort details are passed through as an array:
     * <code>
     * $sortinfo = array (
     * 	'sort' => $fieldname,
     * 	'direction' => $direction,
     * );
     * </code>
     *
     * @param int $userid The userid who created the re engagements. If set to 0, it includes all users.
     * @param array $sortinfo How to sort the results.
     * @param boolean $countonly Whether to only return a count of how many tests there are, or whether to return the array of re engagement details.
     * @param int $start The start position (passed to sql as the offset)
     * @param int $result_limit The number of results to return (passed to sql as the limit).
     *
     * @return mixed Returns the number of re engagements if you are only doing a count.
     * Otherwise, returns an array of re engagement details including the name, create date, reengage type, last sent date, email campaign names used by the re engagement etc.
     */
    public function GetReEngagements($userid = 0, $sortinfo = [], $countonly = false, $start = 0, $result_limit = 10)
    {
        $userid = (int) $userid;

        if ($countonly) {
            $query = 'SELECT COUNT(reengageid) AS count FROM [|PREFIX|]reengagements';
            if ($userid > 0) {
                $query .= ' WHERE userid=' . $userid;
            }
            return $this->db->FetchOne($query);
        }

        $tests = [];

        $pg_campaign_list = "array_to_string(array(SELECT name FROM [|PREFIX|]newsletters n INNER JOIN [|PREFIX|]reengagement_list stc ON (n.newsletterid=stc.campaignid) WHERE stc.reengageid=st.reengageid ORDER BY n.name ASC), ', ') AS campaign_names";

        $mysql_campaign_list = "(SELECT GROUP_CONCAT(name SEPARATOR ', ') FROM [|PREFIX|]newsletters n INNER JOIN [|PREFIX|]reengagement_list stc ON (n.newsletterid=stc.campaignid) WHERE stc.reengageid=st.reengageid ORDER BY n.name ASC) AS campaign_names";

        $campaign_list_query = $mysql_campaign_list;
        if (SENDSTUDIO_DATABASE_TYPE == 'pgsql') {
            $campaign_list_query = $pg_campaign_list;
        }

        $query = 'SELECT reengageid, reengagename, createdate, reengage_typeof, duration_type, jobid, jobstatus, reengagedetails, lastsent';
        $query .= ', CASE WHEN lastsent > 0 THEN 0 ELSE 1 END AS lastsent_check';
        $query .= ' FROM [|PREFIX|]reengagements st';
        if ($userid > 0) {
            $query .= ' WHERE userid=' . $userid;
        }

        $valid_fields = [
            'reengagename',
            'createdate',
            'reengage_typeof',
            'duration_type',
            'lastsent',
        ];

        $valid_directions = [
            'asc',
            'desc',
        ];

        $order_field = 'reengagename';
        $order_direction = 'ASC';

        if (isset($sortinfo['SortBy'])) {
            $field = strtolower((string) $sortinfo['SortBy']);
            if (in_array($field, $valid_fields)) {
                $order_field = $field;
            }
        }

        if (isset($sortinfo['Direction'])) {
            $dir = strtolower((string) $sortinfo['Direction']);
            if (in_array($dir, $valid_directions)) {
                $order_direction = $dir;
            }
        }

        $query .= ' ORDER BY ';
        if ($order_field == 'lastsent') {
            $query .= 'lastsent_check ' . $order_direction . ', ';
        }
        $query .= $order_field . ' ' . $order_direction;
        $query .= $this->db->AddLimit(($start * $result_limit), $result_limit);

        $result = $this->db->Query($query);
        while ($row = $this->db->Fetch($result)) {
            $row['reengagedetails'] = unserialize($row['reengagedetails']);
            if ($row['reengage_typeof'] == 'Both') {
                $row['reengage_typeof'] = 'Not Open / Not Click';
            }
            $row['nameoflists'] = $this->getNameofList($row['reengagedetails']['reengagement_list']);
            $tests[] = $row;
        }
        return $tests;
    }

    /**
     * GetReEngagementsTransferList
     * Returns an array of re engagement details.
     * It also sorts the results in a particular order.
     * The sort details include a field name and an order.
     * The field name defaults to the reengagename, but can be one of the extra fields:
     * - createdate
     * - reengage_typeof
     * - lastsent
     *
     * The sort order has to either be 'asc' or 'desc' and defaults to 'asc'.
     *
     * The sort details are passed through as an array:
     * <code>
     * $sortinfo = array (
     * 	'sort' => $fieldname,
     * 	'direction' => $direction,
     * );
     * </code>
     *
     * @param array $sortinfo How to sort the results.
     * @param boolean $countonly Whether to only return a count of how many tests there are, or whether to return the array of re engagement details.
     * @param int $start The start position (passed to sql as the offset)
     * @param int $result_limit The number of results to return (passed to sql as the limit).
     *
     * @return mixed Returns the number of re engagements if you are only doing a count.
     * Otherwise, returns an array of re engagement details including the name, create date, reengage type, last sent date, email campaign names used by the re engagement etc.
     */
    public function GetReEngagementsTransferList($reengageid = 0, $sortinfo = [], $countonly = false, $start = 0, $result_limit = 10)
    {
        $reengageid = (int) $reengageid;

        if ($countonly) {
            $query = 'SELECT count(DISTINCT lssub.emailaddress) AS count FROM [|PREFIX|]reengagements_listinfo AS relsinfo LEFT JOIN [|PREFIX|]list_subscribers AS lssub ON (relsinfo.subscriberid=lssub.subscriberid)';
            if ($reengageid > 0) {
                $query .= ' WHERE reengageid=' . $reengageid;
            }
            //$query .= " GROUP BY lssub.emailaddress";

            return $this->db->FetchOne($query);
        }

        $tests = [];

        $query = "SELECT listinfo_id, lssub.emailaddress, (SELECT GROUP_CONCAT(' ', name) As ListNames FROM [|PREFIX|]lists as ls WHERE find_in_set(ls.listid, relsinfo.listids)) AS lists, numberofdays, transferdate";
        $query .= ' FROM [|PREFIX|]reengagements_listinfo AS relsinfo LEFT JOIN [|PREFIX|]list_subscribers AS lssub ON (relsinfo.subscriberid=lssub.subscriberid)';
        if ($reengageid > 0) {
            $query .= ' WHERE reengageid=' . $reengageid;
        }

        $valid_fields = [
            'listinfo_id',
            'emailaddress',
            'lists',
            'numberofdays',
            'transferdate',
        ];

        $valid_directions = [
            'asc',
            'desc',
        ];

        $order_field = 'emailaddress';
        $order_direction = 'ASC';

        if (isset($sortinfo['SortBy'])) {
            $field = strtolower((string) $sortinfo['SortBy']);
            if (in_array($field, $valid_fields)) {
                $order_field = $field;
            }
        }

        if (isset($sortinfo['Direction'])) {
            $dir = strtolower((string) $sortinfo['Direction']);
            if (in_array($dir, $valid_directions)) {
                $order_direction = $dir;
            }
        }
        $query .= ' GROUP BY lssub.emailaddress';
        $query .= ' ORDER BY ';
        if ($order_field == 'transferdate') {
            $query .= 'transferdate ' . $order_direction . ', ';
        }
        $query .= $order_field . ' ' . $order_direction;
        $query .= $this->db->AddLimit(($start * $result_limit), $result_limit);

        $result = $this->db->Query($query);
        $tests = [];
        while ($row = $this->db->Fetch($result)) {
            $tests[] = $row;
        }
        return $tests;
    }

    public function getNameofList($listIds = [])
    {
        if (empty($listIds)) {
            return 'No List';
        }
        $selectedList = 'No List';
        $listData = $listIds;
        $query = "SELECT GROUP_CONCAT(' ', name) As SelList FROM [|PREFIX|]lists WHERE listid in (" . $listData . ')';
        $result = $this->db->Query($query);
        while ($row = $this->db->Fetch($result)) {
            $selectedList = trim((string) $row['SelList']);
        }
        return $selectedList;
    }

    /**
     * GetSendingJobStatusCodes
     * This returns an array of job status codes which is used to check if a job can be deleted or not.
     * If a re engagement is actually sending, the re engagement can't be deleted.
     *
     * It is an array containing two status codes:
     * - i (in progress)
     * - r (re-sending the job if any emails fail)
     *
     * @return array Returns an array of job status codes which indicate a 'sending' status.
     */
    public function GetSendingJobStatusCodes()
    {
        return ['i', 'r'];
    }

    /**
     * GetCampaignsUsed
     * This returns an array of distinct campaign id's used by all re engagements.
     *
     * If supplied, the id's passed in are used to restrict the search.
     * This is used to check which campaigns are allowed to be deleted (ie unused by re engagement campaigns).
     *
     * If the id's to restrict to are not supplied (or not int id's),
     * then all distinct campaign id's (newsletter id's) are returned.
     *
     * @param array $campaign_ids The campaign id's to specifically search for. If none are supplied, then all campaign id's are returned.
     *
     * @return array Returns an array of campaign id's currently used by re engagement campaigns.
     */
    public function GetCampaignsUsed($campaign_ids = [])
    {
        if (! is_array($campaign_ids)) {
            $campaign_ids = [$campaign_ids];
        }

        foreach ($campaign_ids as $p => $id) {
            if (! is_numeric($id)) {
                unset($campaign_ids[$p]);
                continue;
            }
        }

        $query = 'SELECT DISTINCT campaignid FROM [|PREFIX|]reengagement_list';
        if (! empty($campaign_ids)) {
            $query .= ' WHERE campaignid IN (' . implode(',', $campaign_ids) . ')';
        }

        $ids_used = [];
        $result = $this->db->Query($query);
        while ($row = $this->db->Fetch($result)) {
            $ids_used[] = $row['campaignid'];
        }
        return $ids_used;
    }

    /**
     * OwnsReEngagements
     * Checks whether a user owns a set of re engagements.
     *
     * @param int $user_id The user ID whose permission to check.
     * @param array|int $reengage_ids A re engagement ID or an array of re engagement IDs.
     *
     * @return boolean True if the user owns all the re engagements, otherwise false.
     */
    public static function OwnsReEngagements($user_id, $reengage_ids)
    {
        $reengage_ids = self::FilterIntSet($reengage_ids);
        $db = IEM::getDatabase();
        $id_list = implode(', ', $reengage_ids);
        $query = "SELECT COUNT(*) FROM [|PREFIX|]reengagements WHERE reengageid IN ({$id_list}) AND userid = " . intval($user_id);
        if ($db->FetchOne($query) == count($reengage_ids)) {
            return true;
        }
        return false;
    }

    /**
     * OwnsJobs
     * Checks whether the given user owns all the jobs passed in.
     *
     * @param int $user_id The user to test is the owner of the jobs.
     * @param array|int $job_ids The list of job IDs to test.
     *
     * @return boolean True if all the jobs are owned by the user, otherwise false.
     */
    public static function OwnsJobs($user_id, $job_ids)
    {
        $job_ids = self::FilterIntSet($job_ids);
        $db = IEM::getDatabase();
        $id_list = implode(', ', $job_ids);
        $query = "SELECT COUNT(*) FROM [|PREFIX|]jobs WHERE jobid IN ({$id_list}) AND ownerid = " . intval($user_id);
        if ($db->FetchOne($query) == count($job_ids)) {
            return true;
        }
        return false;
    }

    /**
     * FilterIntSet
     * Sanitises a set of integers.
     *
     * @param array|int $items An integer or array of integers.
     *
     * @return An array of unique, sanitised integers.
     */
    public static function FilterIntSet($items)
    {
        if (! is_array($items)) {
            $items = [$items];
        }
        return array_unique(array_map('intval', $items));
    }
}
