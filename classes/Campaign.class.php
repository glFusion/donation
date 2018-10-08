<?php
/**
*   Class to handle donation campaigns
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2017 Lee Garner <lee@leegarner.com>
*   @package    donation
*   @version    0.1.1
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
namespace Donation;

/**
*   Class to manage donation campaigns
*   @package donation
*/
class Campaign
{
    const MIN_DATETIME  = '1970-01-01 00:00:00';
    const MAX_DATETIME  = '2037-12-31 23:59:59';
    const MIN_DATE      = '1970-01-01';
    const MAX_DATE      = '2037-12-31';
    const MIN_TIME      = '00:00:00';
    const MAX_TIME      = '23:59:59';

    private $properties = array();
    private $isNew;
    private $btn_types = array('donation');


    /**
    *   Constructor.
    *   Read campaign data from the database, or create a blank entry
    *   with default values
    *
    *   @param  string  $id     Optional ID of existing campaign
    */
    public function __construct($id='')
    {
        global $_USER, $_TABLES, $_CONF_DON;

        $this->isNew = true;    // Assume new entry until we read one
        if (is_array($id)) {
            $this->setVars($id);
        } else {
            $this->camp_id = COM_sanitizeID($id, false);
            if ($this->camp_id != '') {
                $this->Read($this->camp_id);
            } else {
                // Set default values
                $this->enabled = 1;
                $this->start = '';
                $this->end = '';
            }
        }
    }


    /**
    *   Read a single campaign record into the object
    *   @param  string  $id     ID of the campaign to retrieve
    */
    public function Read($id)
    {
        global $_TABLES;

        $sql = "SELECT * FROM {$_TABLES['don_campaigns']}
            WHERE camp_id='" . COM_sanitizeID($id, false) . "'";
        $res = DB_query($sql, 1);
        $A = DB_fetchArray($res, false);
        if (!empty($A)) {
            $this->setVars($A);
            $this->isNew = false;
        }
    }


    /**
    *   Get a campaign instance
    *   Temp function just instantiates a new instance. Caching will come.
    *
    *   @param  mixed   $campaign   Campaign ID or record
    *   @return object              Campaign object
    */
    public static function getInstance($campaign)
    {
        return new self($campaign);
    }


    /**
    *   Set a property's value.
    *
    *   @param  string  $var    Name of property to set.
    *   @param  mixed   $value  New value for property.
    */
    public function __set($key, $value)
    {
        global $_CONF;

        switch ($key) {
        case 'camp_id':
        case 'old_camp_id':
            $this->properties[$key] = COM_sanitizeID($value, false);
            break;

        case 'start':
            if (empty($value)) {
                $value = self::MIN_DATETIME;
            }
            $this->properties[$key] = new \Date($value, $_CONF['timezone']);
            break;

        case 'end':
            if (empty($value)) {
                $value = self::MAX_DATETIME;
            }
            $this->properties[$key] = new \Date($value, $_CONF['timezone']);
            break;

        case 'pp_buttons':
            if (!empty($value))
                $this->buttons = $value;
            break;

        case 'name':
        case 'shortdesc':
        case 'description':
        //case 'startdt':
        //case 'enddt':
            $this->properties[$key] = trim($value);
            break;

        case 'goal':
        case 'amount':
            $this->properties[$key] = (float)$value;
            break;

        case 'use_pp':
        case 'enabled':
        case 'hardgoal':
        case 'blk_show_pct':
            $this->properties[$key] = $value == 1 ? 1 : 0;
            break;

        case 'buttons':
            if (is_array($value)) {
                $this->properties['buttons'] = $value;
            } else {
                $this->properties['buttons'] = unserialize($value);
            }
        }
    }


    /**
    *   Get the value of a property.
    *
    *   @param  string  $var    Name of property to retrieve.
    *   @return mixed           Value of property, NULL if undefined.
    */
    public function __get($var)
    {
        if (array_key_exists($var, $this->properties)) {
            return $this->properties[$var];
        } else {
            return NULL;
        }
    }


    /**
    *   Set all the variables in this object from values provided.
    *   @param  array   $A  Array of values, either from $_POST or database
    */
    public function setVars($A, $fromDB=true)
    {
        global $_CONF;

        if (!is_array($A))
            return;

        $this->camp_id = $A['camp_id'];
        //$this->startdt = $A['startdt'];
        //$this->enddt = $A['enddt'];
        $this->name = $A['name'];
        $this->shortdesc = $A['shortdesc'];
        $this->description = $A['description'];
        $this->goal = $A['goal'];
        $this->enabled = isset($A['enabled']) ? $A['enabled'] : 0;
        $this->hardgoal = isset($A['hardgoal']) ? $A['hardgoal'] : 0;
        $this->blk_show_pct = $A['blk_show_pct'];
        //$this->pp_buttons = $A['pp_buttons'];
        $this->amount = $A['amount'];

        if ($fromDB) {
            $this->start = $A['start_ts'];
            $this->end   = $A['end_ts'];
        } else {
            if (empty($A['end_date'])) $A['end_date'] = self::MAX_DATE;
            if (empty($A['end_time'])) $A['end_time'] = self::MAX_TIME;
            if (empty($A['start_date'])) $A['start_date'] = self::MIN_DATE;
            if (empty($A['start_time'])) $A['start_time'] = self::MIN_TIME;
            $this->start = $A['start_date'] . ' ' . $A['start_time'];
            $this->end = $A['end_date'] . ' ' . $A['end_time'];
        }
    }


    /**
    *   Update the 'enabled' value for a campaign.
    *
    *   @param  integer $oldval     Original value
    *   @param  string  $id         Campaign ID
    *   @return integer             New value, old value on error
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
    *   Delete a campaign.
    *   Can be called as Campaign::Delete($id).
    *   @param  string  $id ID of campaign to delete, this object if empty
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
    *   Determine if this campaign has any donations belonging to it.
    *   Can also be called as self::isUsed($id).
    *   @param  string  $id ID of campaign to check, this object if empty.
    *   @return boolean     True if this has baners, False if unused
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
    *   Create the editing form for this campaign.
    *
    *   @return string      HTML for edit form
    */
    public function Edit()
    {
        global $_CONF, $_CONF_DON, $LANG24, $LANG_postmodes;

        $T = DON_getTemplate('campaignform', 'editform');

        // Set up the wysiwyg editor, if available
        switch (PLG_getEditorType()) {
        case 'ckeditor':
            $T->set_var('show_htmleditor', true);
            PLG_requestEditor('donation','donation_entry','ckeditor_donation.thtml');
            PLG_templateSetVars('donation_entry', $T);
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

        if ($this->end->toMySQL(true) == self::MAX_DATETIME) {
            $end_dt = '';
            $end_tm = '';
        } else {
            $end_dt = $this->end->format('Y-m-d', true);
            $end_tm = $this->end->format('H:i', true);
        }
        if ($this->start->toMySQL(true) == self::MIN_DATETIME) {
            $st_dt = '';
            $st_tm = '';
        } else {
            $st_dt = $this->start->format('Y-m-d', true);
            $st_tm = $this->start->format('H:i', true);
        }

        $T->set_var(array(
            'help_url'      => DON_URL . '/docs/campaignform_help.html',
            'action_url'    => DON_ADMIN_URL . '/index.php',
            'camp_id'       => $this->camp_id,
            'old_camp_id'   => $this->camp_id,
            'name'          => $this->name,
            'shortdesc'     => $this->shortdesc,
            'description'   => $this->description,
            'start_date'    => $st_dt,
            'end_date'      => $end_dt,
            'start_time'    => $st_tm,
            'end_time'      => $end_tm,
            //'startdt'       => $this->startdt,
            //'enddt'         => $this->enddt,
            'chk_enabled'   => $this->enabled == 1 ? DON_CHECKED : '',
            'chk_hardgoal'  => $this->hardgoal == 1 ? DON_CHECKED : '',
            'chk_blk_show_pct'  => $this->blk_show_pct == 1 ? DON_CHECKED : '',
            'goal'          => $this->goal,
            'doc_url'       => LGLIB_getDocURL('campaignform.html',
                                $_CONF_DON['pi_name'],
                                $_CONF['language']),
            'amount'        => $this->amount,
        ) );
        $T->parse ('output', 'editform');
        return $T->finish($T->get_var('output'));
    }


    /**
    *   Save this campaign
    *   @param  array   $A  Array of values from $_POST (optional)
    **/
    public function Save($A='')
    {
        global $_TABLES, $LANG_DON;

        if (is_array($A)) {
            $this->setVars($A, false);
        }
        $this->old_camp_id = $A['old_camp_id'];
        if ($this->camp_id == '') {
            $this->camp_id = COM_makeSid();
        }

        if ($this->isNew) {
            if (DB_count($_TABLES['don_campaigns'], 'camp_id',
                        $this->old_camp_id) > 0) {
                return $LANG_DON['duplicate_camp_id'];
            }
            $sql1 = "INSERT INTO {$_TABLES['don_campaigns']} SET
                    camp_id = '" . DB_escapeString($this->camp_id) . "',";
            $sql3 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['don_campaigns']} SET";
            if ($this->camp_id != '' && $this->old_camp_id != $this->camp_id) {
                $sql1 .= " camp_id = '" . DB_escapeString($this->camp_id) . "',";
            }
            $sql3 = "WHERE camp_id='" . DB_escapeString($this->old_camp_id) . "'";
        }
        $sql = $sql1 .
                " name = '" . DB_escapeString($this->name) . "',
                shortdesc = '" . DB_escapeString($this->shortdesc) . "',
                description = '" . DB_escapeString($this->description) . "',
                start_ts = " . $this->start->toUnix() . ",
                end_ts = " . $this->end->toUnix() . ",
                goal = {$this->goal},
                hardgoal = {$this->hardgoal},
                amount = {$this->amount},
                blk_show_pct = {$this->blk_show_pct},
                enabled = {$this->enabled} " .
                $sql3;
        //echo $sql;die;
        DB_query($sql);
    }


    /**
    *   Create a PayPal button for donations.
    *   Used if the PayPal plugin is not installed or available.
    *
    *   @return string      HTML for a PayPal donation button
    */
    public function getButton()
    {
        global $_TABLES, $_CONF, $_CONF_DON, $_USER;

        $button = '';

        if (DONATION_PAYPAL_ENABLED) {
            $vars = array(
                'item_number' => 'donation:' . $this->camp_id,
                'item_name' => $this->name,
                'quantity' => 1,
                'return' => DON_URL . '/index.php?mode=thanks&id=' . urlencode($this->name),
                'btn_type' => 'donation',
            );
            if ($_CONF_DON['pp_use_donation']) {
                // Set the PayPal command if configured.
                $vars['cmd'] = '_donations';
            }
            if ($this->amount > 0) {
                $vars['amount'] = $this->amount;
            }
            $status = LGLIB_invokeService('paypal', 'genButton', $vars,
                $output, $svc_msg);
            if ($status == PLG_RET_OK && !empty($output)) {
                $button = $output[0];
            }
        }
        return $button;
    }


    /**
    *   Return the option elements for a campaign selection dropdown.
    *
    *   @param  string  $sel    Campaign ID to show as selected
    *   @return string          HTML for option statements
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
    *   Check if this campaign is enabled.
    *
    *   @return boolean     true if enabled, false if not.
    */
    public function isEnabled()
    {
        return $this->enabled == 1 ? true : false;
    }

}   // class Campaign

?>
