<?php
/**
 * Class to handle donation campaigns.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2022 Lee Garner <lee@leegarner.com>
 * @package     donation
 * @version     v0.1.2
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Donation;

/**
 * Class to manage donation campaigns.
 * @package donation
 */
class Campaign
{
    /** Minimum possible date for a campaign to start.
     * @const string */
    const MIN_DATE      = '1970-01-01';

    /** Maximum possible date for a campaign to end.
     * @const string */
    const MAX_DATE      = '2037-12-31';

    /** Minimum possible time.
     * @const string */
    const MIN_TIME      = '00:00:00';

    /** Maximum possible time.
     * @const string */
    const MAX_TIME      = '23:59:59';

    /** Flag to indicate a new campaign record.
     * @var boolean */
    private $isNew = 1;

    /** Array of button types needed.
     * @var array */
    private $btn_types = array('donation');

    /** Campaign ID.
     * @var string */
    private $camp_id = '';

    /** Starting date.
     * @var object */
    private $start = NULL;

    /** Ending date.
     * @var object */
    private $end = NULL;

    /** Campaign name.
     * @var string */
    private $name = '';

    /** Short one-line description.
     * @var string */
    private $shortdscp = '';

    /** Full text description.
     * @var string */
    private $dscp;

    /** Donation goal for the campaign.
     * @var float */
    private $goal = 0;

    /** Suggested donation amount.
     * @var float */
    private $amount = 0;

    /** Indicate that the campaign is accepting donations.
     * @var boolean */
    private $enabled = 1;

    /** Indicate that donations will not be accepted once the goal is reached.
     * @var boolean */
    private $hardgoal = 0;

    /** Show the percentage received in the block?
     * @var boolean */
    private $blk_show_pct = 0;

    /** Amount received so far. Taken from the provided data if available.
     * @var float */
    private $received = 0;


    /**
     * Constructor.
     * Read campaign data from the database, or create a blank entry
     * with default values
     *
     * @param   string  $id     Optional ID of existing campaign
     */
    public function __construct($id='')
    {
        global $_USER, $_TABLES;

        if (is_array($id)) {
            $this->setVars($id);
            $this->isNew = 0;
        } else {
            $this->camp_id = COM_sanitizeID($id, false);
            if ($this->camp_id != '') {
                $this->Read($this->camp_id);
            } else {
                $this->setStart();
                $this->setEnd();
            }
        }
    }


    /**
     * Read a single campaign record into the object.
     *
     * @param   string  $id     ID of the campaign to retrieve
     */
    public function Read($id)
    {
        global $_TABLES;

        $id = COM_sanitizeID($id, false);
        $sql = "SELECT c.*, (
                SELECT SUM(amount) FROM {$_TABLES['don_donations']} d
                WHERE d.camp_id = c.camp_id
            ) as received
            FROM {$_TABLES['don_campaigns']} c
            WHERE c.camp_id='$id'";
        $res = DB_query($sql, 1);
        $A = DB_fetchArray($res, false);
        if (!empty($A)) {
            $this->setVars($A);
            $this->isNew = false;
        } else {
            $this->setStart();
            $this->setEnd();
        }
    }


    /**
     * Get a campaign instance.
     * Temp function just instantiates a new instance. Caching will come.
     *
     * @param   mixed   $campaign   Campaign ID or record
     * @return  object              Campaign object
     */
    public static function getInstance($campaign)
    {
        return new self($campaign);
    }


    /**
     * Get all currently-active campaigns.
     *
     * @return  array       Array of Campaign objects
     */
    public static function getAllActive()
    {
        global $_TABLES;

        $retval = array();
        $sql = "SELECT c.*, (
                SELECT SUM(amount) FROM {$_TABLES['don_donations']} d
                WHERE d.camp_id = c.camp_id
            ) as received
            FROM {$_TABLES['don_campaigns']} c
            WHERE c.enabled = 1
            AND c.end_ts > UNIX_TIMESTAMP()
            AND c.start_ts < UNIX_TIMESTAMP()";
        $res = DB_query($sql);
        if (!$res) {
            return $retval;
        }
        while ($A = DB_fetchArray($res, false)) {
            // Check that the goal isn't reached
            if (!$A['hardgoal'] || $A['received'] < $A['goal']) {
                $retval[$A['camp_id']] = new self($A);
            }
        }
        return $retval;
    }


    /**
     * Get all campaigns to which a specific user has donated.
     *
     * @param   integer $uid    User ID
     * @return  array       Array of Campaign objects
     */
    public static function getByUser($uid)
    {
        global $_TABLES;

        $retval = array();
        $uid = (int)$uid;
        $sql = "SELECT c.camp_id, MAX(c.name) AS name,
            MAX(c.shortdscp) AS shortdscp, MAX(c.dscp) AS dscp,
            MAX(c.start_ts) AS start_ts, MAX(c.end_ts) AS end_ts,
            MAX(c.enabled) AS enabled, MAX(c.goal) AS goal,
            MAX(c.hardgoal) AS hardgoal, MAX(blk_show_pct) AS blk_show_pct,
            MAX(c.pp_buttons) AS pp_buttons,
            SUM(d.amount) as received
            FROM {$_TABLES['don_donations']} d
            LEFT JOIN {$_TABLES['don_campaigns']} c
            ON d.camp_id = c.camp_id
            WHERE d.uid = $uid
            GROUP BY c.camp_id";
        $res = DB_query($sql);
        if (!$res) {
            return $retval;
        }
        while ($A = DB_fetchArray($res, false)) {
            $retval[$A['camp_id']] = new self($A);
        }
        return $retval;
    }


    /**
     * Get the search SQL.
     *
     * @param   string  $query      Query string
     * @return  string      SQL query
     */
    public static function getSearchSql($query)
    {
        global $_TABLES;

        $htmlquery = urlencode($query);
        return "SELECT
                camp_id, name as title, dscp as description,
                start_ts as date,
                CONCAT('/" . Config::PI_NAME . "/index.php?mode=detail&id=',camp_id,'&query=$htmlquery') as url
            FROM {$_TABLES['don_campaigns']}
            WHERE enabled = 1
            AND start_ts < UNIX_TIMESTAMP()
            AND end_ts > UNIX_TIMESTAMP()";
    }


    /**
     * Set all the variables in this object from values provided.
     *
     * @param   array   $A      Array of values
     * @param   boolean $fromDB True if reading from DB, false for a form
     */
    public function setVars($A, $fromDB=true)
    {
        global $_CONF;

        if (!is_array($A)) {
            return;
        }

        $this->camp_id = $A['camp_id'];
        //$this->startdt = $A['startdt'];
        //$this->enddt = $A['enddt'];
        $this->name = $A['name'];
        $this->shortdscp = $A['shortdscp'];
        $this->dscp = $A['dscp'];
        $this->enabled = isset($A['enabled']) ? (int)$A['enabled'] : 0;
        $this->hardgoal = isset($A['hardgoal']) ? (int)$A['hardgoal'] : 0;
        $this->blk_show_pct = isset($A['blk_show_pct']) ? (int)$A['blk_show_pct'] : 0;
        $this->setGoal($A['goal']);
        if (isset($A['received'])) {
            $this->received = (float)$A['received'];
        }

        if ($fromDB) {
            $this->setStart($A['start_ts']);
            $this->setEnd($A['end_ts']);
        } else {
            if (empty($A['end_date'])) $A['end_date'] = self::MAX_DATE;
            if (empty($A['end_time'])) $A['end_time'] = self::MAX_TIME;
            if (empty($A['start_date'])) $A['start_date'] = self::MIN_DATE;
            if (empty($A['start_time'])) $A['start_time'] = self::MIN_TIME;
            $this->setStart($A['start_date'] . ' ' . $A['start_time']);
            $this->setEnd($A['end_date'] . ' ' . $A['end_time']);
        }
    }


    /**
     * Update the 'enabled' value for a campaign.
     *
     * @param   integer $oldval     Original value
     * @param   string  $id         Campaign ID
     * @return  integer             New value, old value on error
     */
    public static function toggleEnabled($oldval, $id='')
    {
        global $_TABLES;

        $newval = $oldval == 1 ? 0 : 1;
        $id = DB_escapeString($id);
        DB_change($_TABLES['don_campaigns'],
                'enabled', $newval,
                'camp_id', $id);
        return DB_error() ? $oldval : $newval;
    }


    /**
     * Delete a campaign.
     * Can be called as Campaign::Delete($id).
     *
     * @param   string  $id ID of campaign to delete, this object if empty
     */
    public function Delete($id='')
    {
        global $_TABLES;

        if ($id == '') {
            if (is_object($this)) {
                $id = $this->camp_id;
            } else {
                return;
            }
        }

        if (!self::isUsed($id)) {
            DB_delete($_TABLES['don_campaigns'], 'camp_id', trim($id));
        }
    }


    /**
     * Determine if this campaign has any donations belonging to it.
     * Can also be called as self::isUsed($id).
     *
     * @param   string  $id ID of campaign to check, this object if empty.
     * @return  boolean     True if this has baners, False if unused
     */
    public static function isUsed($id='')
    {
        global $_TABLES;

        if (DB_count($_TABLES['don_donations'], 'camp_id',
            DB_escapeString($id)) > 0) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Create the editing form for this campaign.
     *
     * @return  string      HTML for edit form
     */
    public function Edit()
    {
        global $_CONF, $LANG24, $LANG_postmodes;

        $T = new \Template(__DIR__ . '/../templates');
        $T->set_file('editform', 'campaignform.thtml');

        // Set up the wysiwyg editor, if available
        switch (PLG_getEditorType()) {
        case 'ckeditor':
            $T->set_var('show_htmleditor', true);
            PLG_requestEditor('donation','donation_entry','ckeditor_donation.thtml');
            PLG_templateSetVars('donation_entry', $T);
            SEC_setCookie(
                $_CONF['cookie_name'] . 'adveditor',
                SEC_createTokenGeneral('advancededitor'),
                time() + 1200,
                $_CONF['cookie_path'],
                $_CONF['cookiedomain'],
                $_CONF['cookiesecure'],
                false
            );
            break;
        case 'tinymce' :
            $T->set_var('show_htmleditor',true);
            PLG_requestEditor('donation','donation_entry','tinymce_donation.thtml');
            PLG_templateSetVars('donation_entry', $T);
            break;
        default :
            // don't support others right now
            $T->set_var('show_htmleditor', false);
            break;
        }

        if ($this->end->toMySQL(true) == self::MAX_DATE . ' ' . self::MAX_TIME) {
            $end_dt = '';
            $end_tm = '';
        } else {
            $end_dt = $this->end->format('Y-m-d', true);
            $end_tm = $this->end->format('H:i', true);
        }
        if ($this->start->toMySQL(true) == self::MIN_DATE . ' ' . self::MIN_TIME) {
            $st_dt = '';
            $st_tm = '';
        } else {
            $st_dt = $this->start->format('Y-m-d', true);
            $st_tm = $this->start->format('H:i', true);
        }

        $T->set_var(array(
            'help_url'      => Config::get('url') . '/docs/campaignform_help.html',
            'action_url'    => Config::get('admin_url') . '/index.php',
            'camp_id'       => $this->camp_id,
            'old_camp_id'   => $this->camp_id,
            'name'          => $this->name,
            'shortdscp'     => $this->shortdscp,
            'dscp'          => $this->dscp,
            'start_date'    => $st_dt,
            'end_date'      => $end_dt,
            'start_time'    => $st_tm,
            'end_time'      => $end_tm,
            //'startdt'       => $this->startdt,
            //'enddt'         => $this->enddt,
            'chk_enabled'   => $this->enabled == 1 ? DON_CHECKED : '',
            'chk_hardgoal'  => $this->getHardgoal() ? DON_CHECKED : '',
            'chk_blk_show_pct'  => $this->getBlkShowPct() ? DON_CHECKED : '',
            'goal'          => $this->goal,
            'doc_url'       => LGLIB_getDocURL(
                'campaignform.html',
                Config::PI_NAME,
                $_CONF['language']
            ),
        ) );
        $T->parse ('output', 'editform');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Save this campaign.
     *
     * @param   array   $A  Array of values from $_POST (optional)
     */
    public function Save($A='')
    {
        global $_TABLES, $LANG_DON;

        if (is_array($A)) {
            $this->setVars($A, false);
        }
        $old_camp_id = $A['old_camp_id'];
        if ($this->camp_id == '') {
            $this->camp_id = COM_makeSid();
        }

        // If the old and new campaign IDs are different (always true
        // for new records), check that the new ID isn't already in use.
        if (
            $old_camp_id != $this->camp_id &&
            DB_count(
                $_TABLES['don_campaigns'],
                'camp_id',
                $this->camp_id
            ) > 0
        ) {
            return $LANG_DON['duplicate_camp_id'];
        }

        $update_don_ids = false;
        if ($this->isNew) {
            $sql1 = "INSERT INTO {$_TABLES['don_campaigns']} SET
                    camp_id = '" . DB_escapeString($this->camp_id) . "',";
            $sql3 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['don_campaigns']} SET";
            if ($old_camp_id != $this->camp_id) {
                $sql1 .= " camp_id = '" . DB_escapeString($this->camp_id) . "',";
                $update_don_ids = true;
            }
            $sql3 = "WHERE camp_id='" . DB_escapeString($old_camp_id) . "'";
        }
        $sql = $sql1 .
                " name = '" . DB_escapeString($this->name) . "',
                shortdscp = '" . DB_escapeString($this->shortdscp) . "',
                dscp = '" . DB_escapeString($this->dscp) . "',
                start_ts = " . $this->start->toUnix() . ",
                end_ts = " . $this->end->toUnix() . ",
                goal = {$this->goal},
                hardgoal = {$this->getHardgoal()},
                blk_show_pct = {$this->getBlkShowPct()},
                enabled = {$this->isEnabled()} " .
                $sql3;
        //echo $sql;die;
        DB_query($sql);
        if (!DB_error()) {
            if ($update_don_ids) {
                Donation::updateCampaignIDs($old_camp_id, $this->camp_id);
            }
            PLG_itemSaved($this->camp_id, Config::PI_NAME);
        }
    }


    /**
     * Create a Shop button for donations.
     * Used if the Shop plugin is not installed or available.
     *
     * @param   integer $limit  Limit on number of buttons (default=unlimited)
     * @return  string      HTML for a Shop donation button
     */
    public function getButton(?int $limit=NULL) : string
    {
        global $_TABLES, $_CONF, $_USER;

        $button = '';

        if (DON_shop_enabled()) {
            $vars = array(
                'item_number' => 'donation:' . $this->camp_id,
                'item_name' => $this->name,
                'quantity' => 1,
                'return' => Config::get('url') . '/index.php?mode=thanks&id=' . urlencode($this->name),
                'btn_type' => 'donation',
                'cancel_return' => Config::get('url') . '/index.php',
            );
            if (Config::get('pp_use_donation')) {
                // Set the Shop command if configured.
                $vars['cmd'] = '_donations';
            }
            $status = LGLIB_invokeService(
                'shop',
                'genButton',
                $vars,
                $output,
                $svc_msg
            );
            if ($status == PLG_RET_OK && is_array($output) && !empty($output)) {
                $cnt = 0;       // count buttons supplied
                $limit = (int)$limit;
                foreach ($output as $btn) {
                    if (!empty($btn)) {
                        if ($limit > 0) {
                            if (++$cnt > $limit) {
                                // quit if limit is reached
                                break;
                            }
                        }
                        $button .= $btn . LB;
                    }
                }
            }
        }
        return $button;
    }


    /**
     * Return the option elements for a campaign selection dropdown.
     *
     * @param   string  $sel    Campaign ID to show as selected
     * @param   integer $access Access level required
     * @return  string          HTML for option statements
     */
    public static function DropDown($sel='', $access=3)
    {
        global $_TABLES;

        $retval = '';
        $sel = COM_sanitizeID($sel, false);
        $access = (int)$access;

        // Retrieve the campaigns to which the current user has access
        $sql = "SELECT c.camp_id, c.name
                FROM {$_TABLES['don_campaigns']} c ";
                //COM_getPermSQL('AND', 0, $access, 'c') .
        //echo $sql;
        $result = DB_query($sql);

        while ($row = DB_fetchArray($result)) {
            $selected = $row['camp_id'] == $sel ? DON_SELECTED : '';
            $retval .= "<option value=\"" .
                        htmlspecialchars($row['camp_id']) .
                        "\"$selected>" .
                        htmlspecialchars($row['name']) .
                        "</option>\n";
        }
        return $retval;
    }


    /**
     * Get the campaign record ID.
     *
     * @return  string      Campaign ID
     */
    public function getID()
    {
        return $this->camp_id;
    }


    /**
     * Check if this campaign is active.
     * Enabled is true, no hard goal or amount received < goal.
     *
     * @return  boolean     True if active, False if not
     */
    public function isActive()
    {
        if (!$this->isEnabled()) {
            return false;
        }
        if (
            $this->isHardGoal() &&
            $this->received >= $this->goal
        ) {
            return false;
        }
        return true;
    }


    /**
     * Check if this campaign is enabled.
     *
     * @return  integer     1 if enabled, 0 if not.
     */
    public function isEnabled()
    {
        return $this->enabled ? 1 : 0;
    }


    /**
     * Check whether this campaign has a hard cutoff at the goal.
     *
     * @return  integer     1 if the goal is a hard stop, 0 if not
     */
    public function isHardGoal()
    {
        return $this->hardgoal ? 1 : 0;
    }


    /**
     * Get the name of the campaign.
     *
     * @return  string      Campaign name
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     * Get the short description of the campaign.
     *
     * @return  string      Short description
     */
    public function getShortDscp()
    {
        return $this->shortdscp;
    }


    /**
     * Get the full description of the campaign.
     *
     * @return  string      Full description
     */
    public function getDscp()
    {
        return $this->dscp;
    }


    /**
     * Set the starting date object.
     *
     * @param   string  $value  Starting date string
     * @return  object  $this
     */
    private function setStart($value=NULL)
    {
        global $_CONF;

        if (empty($value)) {
            $value = self::MIN_DATE . ' ' . self::MIN_TIME;
        }
        $this->start = new \Date($value, $_CONF['timezone']);
        return $this;
    }


    /**
     * Set the ending  date object.
     *
     * @param   string  $value  Starting date string
     * @return  object  $this
     */
    private function setEnd($value=NULL)
    {
        global $_CONF;

        if (empty($value)) {
            $value = self::MAX_DATE . ' ' . self::MAX_TIME;
        }
        $this->end = new \Date($value, $_CONF['timezone']);
        return $this;
    }


    /**
     * Set the goal as a floating-point number.
     *
     * @uses    self::fixFloat()
     * @param   mixed   $val    Value to set
     * @return  object  $this
     */
    private function setGoal($val)
    {
        $this->goal = self::fixFloat($val);
        return $this;
    }


    /**
     * Check if there is a starting date, where start is not the minimum date.
     *
     * @return  boolean     True if a valid starting date, False if not
     */
    public function hasStart()
    {
        return $this->start->toMySQL(true) == self::MIN_DATE . ' ' . self::MIN_TIME ? false : true;
    }


    /**
     * Get the starting date.
     *
     * @return  object      Starting date object
     */
    public function getStart()
    {
        return $this->start;
    }


    /**
     * Get the ending date.
     *
     * @return  object      Ending date object
     */
    public function getEnd()
    {
        return $this->end;
    }


    /**
     * Get the campaign ID.
     *
     * @return  string  Campaign ID
     */
    public function getCampaignID()
    {
        return $this->camp_id;
    }


    /**
     * Check if this is a new, uninitialized record.
     *
     * @return  integer     1 if a new record, 0 if existing
     */
    public function isNew()
    {
        return $this->isNew ? 1 : 0;
    }


    /**
     * Get the integer value for the "show percent in block" flag.
     *
     * @return  integer     Zero if disabled, 1 if enabled
     */
    public function getBlkShowPct()
    {
        return $this->blk_show_pct ? 1 : 0;
    }


    /**
     * Get the integer value for the "stop when goal is reached" flag.
     *
     * @return  integer     Zero if disabled, 1 if enabled
     */
    public function getHardgoal()
    {
        return $this->hardgoal ? 1 : 0;
    }


    /**
     * Get the amount received to date.
     *
     * @return   float      Amount received
     */
    public function getReceived()
    {
        return (float)$this->received;
    }


    /**
     * Get the goal for the campaign.
     *
     * @return  float       Goal amount
     */
    public function getGoal()
    {
        return (float)$this->goal;
    }


    /**
     * Create an admin list of campaigns.
     *
     * @return  string  HTML for list
     */
    public static function adminList()
    {
        global $_CONF, $_TABLES, $LANG_ADMIN, $LANG_ACCESS;
        global $LANG_DON;

        USES_lib_admin();
        $retval = '';

        $header_arr = array(      # display 'text' and use table field 'field'
            array(
                'field' => 'edit',
                'text' => $LANG_ADMIN['edit'],
                'sort' => false,
                'align', 'center',
            ),
            array(
                'field' => 'enabled',
                'text' => $LANG_DON['enabled'],
                'sort' => false,
                'align', 'center',
            ),
            array(
                'field' => 'name',
                'text' => $LANG_DON['camp_name'],
                'sort' => true,
            ),
            array(
                'field' => 'start_ts',
                'text' => $LANG_DON['startdate'],
                'sort' => true,
            ),
            array(
                'field' => 'end_ts',
                'text' => $LANG_DON['enddate'],
                'sort' => true,
            ),
            array(
                'field' => 'goal',
                'text' => $LANG_DON['goal'],
                'sort' => true,
            ),
            array(
                'field' => 'received',
                'text' => $LANG_DON['received'],
                'sort' => true,
            ),
            array(
                'text' => $LANG_ADMIN['delete'] . '&nbsp;' .
                    '<i class="uk-icon uk-icon-question-circle tooltip" '.
                    'title="' . $LANG_DON['hlp_camp_del'] . '"></i>',
                'field' => 'delete', 'sort' => false,
                'align' => 'center',
            ),
        );

        $defsort_arr = array(
            'field' => 'start_ts',
            'direction' => 'DESC',
        );
        $text_arr = array(
            'has_extras' => true,
            'form_url' => Config::get('admin_url') . '/index.php?type=campaigns',
        );

        //$options = array('chkdelete' => 'true', 'chkfield' => 'camp_id');

        $query_arr = array(
            'table' => 'don_campaigns',
            'sql' => "SELECT c.*, (SELECT SUM(amount)
                    FROM {$_TABLES['don_donations']} d
                    WHERE d.camp_id = c.camp_id) as received
                    FROM {$_TABLES['don_campaigns']} c",
            'query_fields' => array('name', 'shortdscp', 'dscp'),
            'default_filter' => 'WHERE 1=1',
        );
        //echo $query_arr['sql'];die;}
        $options = array();
        $form_arr = array();
        $retval .= ADMIN_list(
            'donation_campaignlist',
            array(__CLASS__, 'getListField'),
            $header_arr,
            $text_arr, $query_arr, $defsort_arr, '', '',
            $options, $form_arr
        );
        return $retval;
    }


    /**
     * Get a single field for the Campaign admin list.
     *
     * @param   string  $fieldname  Name of field
     * @param   mixed   $fieldvalue Value of field
     * @param   array   $A          Array of all fields
     * @param   array   $icon_arr   Array of system icons
     * @return  string              HTML content for field display
     */
    public static function getListField($fieldname, $fieldvalue, $A, $icon_arr)
    {
        global $_CONF, $LANG_ACCESS, $LANG_DON;

        static $Dt = NULL;
        $retval = '';

        if ($Dt === NULL) $Dt = new \Date('now', $_CONF['timezone']);

        switch($fieldname) {
        case 'edit':
            $retval .= COM_createLink(
                '<i class="uk-icon uk-icon-edit" tooltip" title="' .
                    $LANG_DON['edit_item'] . '"></i>',
                Config::get('admin_url') .
                    '/index.php?editcampaign=x&amp;camp_id=' . $A['camp_id']
            );
            break;

        case 'delete':
            if (!self::isUsed($A['camp_id'])) {
                $retval = COM_createLink(
                    '<i class="uk-icon uk-icon-trash uk-text-danger" ' .
                        ' tooltip" title="' . $LANG_DON['delete'] . '"></i>',
                    Config::get('admin_url') .
                        "/index.php?deletecampaign=x&amp;camp_id={$A['camp_id']}",
                    array(
                        'onclick' => 'return confirm(\'' . $LANG_DON['q_del_item'] . '\');',
                    )
                );
            }
            break;

        case 'enabled':
            if ($fieldvalue == '1') {
                $switch = ' checked="checked"';
                $enabled = 1;
            } else {
                $switch = '';
                $enabled = 0;
            }
            $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"ena_check\"
                id=\"togenabled{$A['camp_id']}\" class=\"tooltip\" title=\"{$LANG_DON['ena_or_disa']}\"
                onclick='DON_toggle(this,\"{$A['camp_id']}\",\"campaign\");' />" . LB;
            break;

        case 'goal':
        case 'received':
            $retval = '<span class="text-align:right;">' .
                sprintf("%6.2f", $fieldvalue) .
                '</span>';
            break;
        case 'start_ts':
        case 'end_ts':
            $Dt->setTimestamp($fieldvalue);
            $retval = $Dt->toMySQL(true);
            break;

        case 'name':
            $retval = COM_createLink(
                $fieldvalue,
                Config::get('admin_url') .
                '/index.php?donations=x&camp_id=' . $A['camp_id']
            );
            break;

        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }


    /**
     * Sanitize a floating point number from a string.
     *
     * @param   string  $val    Value as entered
     * @return  float       Sanitized floating-point value
     */
    private static function fixFloat($val)
    {
        return filter_var(
            $val,
            FILTER_SANITIZE_NUMBER_FLOAT,
            FILTER_FLAG_ALLOW_FRACTION
        );
    }
}

