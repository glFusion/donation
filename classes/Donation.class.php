<?php
/**
*   Class to handle donations.
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
class Donation
{
    private $properties = array();
    private $isNew;

    /** Constructor.
    *
    *   Read campaign data from the database, or create
    *   a blank entry with default values
    *
    *   @param  integer $don_id     Optional donation ID to read
    */
    public function __construct($don_id = 0)
    {
        global $_USER, $_TABLES, $_CONF_DON;

        $this->isNew = true;    // Assume new entry until we read one
        $this->don_id = $don_id;

        if ($this->don_id != 0) {
            $this->Read($this->don_id);
        } else {
            // Set default values
            $this->uid = 0;
            $this->dt = date('Y-m-d H:i:s', time());
            $this->comment = '';
            $this->amount = 0;
            $this->camp_id = '';
        }
    }


    /**
    *   Read a single campaign record into the object.
    *
    *   @param  string  $id     ID of the campaign to retrieve
    */
    public function Read($don_id)
    {
        global $_TABLES;

        $A = DB_fetchArray(DB_query("
            SELECT * FROM {$_TABLES['don_donations']}
            WHERE don_id='" . (int)$don_id . "'"), false);
        if (!empty($A)) {
            $this->setVars($A);
            $this->isNew = false;
        }
    }


    /**
    *   Set a property's value.
    *
    *   @param  string  $var    Name of property to set.
    *   @param  mixed   $value  New value for property.
    */
    public function __set($key, $value)
    {
        switch ($key) {
        case 'don_id':
        case 'uid':
            $this->properties[$key] = (int)$value;
            break;

        case 'camp_id':
            $this->properties[$key] = COM_sanitizeID($value, false);
            break;

        case 'comment':
        case 'dt':
        case 'contrib_name':
        case 'txn_id':
            $this->properties[$key] = trim($value);
            break;

        case 'amount':
            $this->properties[$key] = (float)$value;
            break;

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
    *
    *   @param  array   $A  Array of values, either from $_POST or database
    */
    public function setVars($A)
    {
        if (!is_array($A))
            return;

        $this->don_id = $A['don_id'];
        $this->uid = $A['uid'];
        $this->contrib_name = $A['contrib_name'];
        $this->dt = $A['dt'];
        $this->camp_id = $A['camp_id'];
        $this->amount = $A['amount'];
        $this->comment = $A['comment'];
        $this->txn_id = $A['txn_id'];
    }


    /**
    *   Delete a donation.
    *   Can be called as self::Delete($id).
    *
    *   @param  string  $id ID of campaign to delete, this object if empty
    */
    public static function Delete($don_id = 0)
    {
        global $_TABLES;

        DB_delete($_TABLES['don_donations'],
                'don_id', $don_id);
    }


    /**
    *   Create the editing form for this campaign.
    *
    *   @return string      HTML for edit form
    */
    public function Edit()
    {
        global $_CONF, $_CONF_DON;

        $T = DON_getTemplate('donationform', 'editform');
        $T->set_var(array(
            'pi_url'        => DON_URL,
            'help_url'      => DON_URL . '/docs/campaignform_help.html',
            'action_url'    => DON_ADMIN_URL . '/index.php',
            'don_id'            => $this->don_id,
            'contributor_select' => $this->UserDropdown($this->uid),
            'contrib_name'  => $this->contrib_name,
            'dt'            => $this->dt,
            'comment'       => $this->comment,
            'amount'        => $this->amount,
            'campaign_select' =>
                        Campaign::DropDown($this->camp_id),
            'txn_id'        => $this->txn_id,
            'doc_url'       => LGLIB_getDocURL('donationform.html',
                                $_CONF_DON['pi_name'],
                                $_CONF['language']),
        ) );

        $T->parse ('output', 'editform');
        return $T->finish($T->get_var('output'));
    }


    /**
    *   Save this donation.
    *
    *   @param  array   $A  Array of values from $_POST (optional)
    */
    public function Save($A='')
    {
        global $_TABLES, $LANG_DON;

        if (is_array($A))
            $this->setVars($A);

        $dt = $this->dt;
        if (empty($dt))
            $dt = 'NULL';
        else
            $dt = "'" . DB_escapeString($dt) . "'";

        if (empty($this->properties['contrib_name']) &&
                $this->properties['uid'] > 0) {
            $this->contrib_name = COM_getDisplayName($this->uid);
        }

        $don_id = $this->don_id;
        if ($this->isNew || $don_id == 0) {

            $sql = "INSERT INTO {$_TABLES['don_donations']} (
                    uid, contrib_name, dt, amount, comment, camp_id
                ) VALUES (
                    '". $this->uid . "',
                    '". $this->contrib_name . "',
                    $dt,
                    " . $this->amount . ",
                    '" . $this->comment . "',
                    '" . DB_escapeString($this->camp_id) . "'
                )";
        } else {
            $sql = "UPDATE {$_TABLES['don_donations']}
            SET
                camp_id='" . DB_escapeString($this->camp_id) . "',
                uid='" . $this->uid . "',
                contrib_name='" . $this->contrib_name . "',
                comment='" . DB_escapeString($this->comment) . "',
                dt=$dt,
                amount=" . $this->amount . "
            WHERE don_id='" . $don_id . "'";
        }
        //echo $sql;die;
        DB_query($sql);
    }


    /**
    *   Return the option elements for a campaign selection dropdown.
    *
    *   @param  string  $sel    Campaign ID to show as selected
    *   @return string          HTML for option statements
    */
    public static function UserDropDown($sel=0)
    {
        global $_TABLES;

        $retval = '';
        $sel = (int)$sel;

        // Retrieve the campaigns to which the current user has access
        $sql = "SELECT uid, username
                FROM {$_TABLES['users']} u ";
        //echo $sql;
        $result = DB_query($sql);

        while ($row = DB_fetchArray($result, false)) {
            $selected = $row['uid'] == $sel ? DON_SELECTED : '';
            $retval .= "<option value=\"{$row['uid']} \"$selected\">" .
                        htmlspecialchars($row['username']) .
                        "</option>\n";
        }
        return $retval;
    }


    /**
    *   Get the total amount received for a campaign.
    *
    *   @param  string  $camp_id    Campaign ID
    *   @return float               Total received
    */
    public static function totalReceived($camp_id)
    {
        global $_TABLES;

        $camp_id = DB_escapeString($camp_id);
        $received = DB_getItem($_TABLES['don_donations'],
                'SUM(amount)', "camp_id = '$camp_id'");
        return $received;
    }

}   // class Donation

?>
