<?php
/**
 * Class to handle donations.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2020 Lee Garner <lee@leegarner.com>
 * @package     donation
 * @version     v0.0.2
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Donation;

/**
*   Class to manage donation campaigns
*   @package donation
*/
class Donation
{
    /** Donation record ID.
     * @var integer */
    private $don_id = 0;

    /** User ID of the donor.
     * @var integer */
    private $uid = 0;

    /** Campaign ID.
     * @var string */
    private $camp_id = '';

    /** Comment submitted with the donation.
     * @var string */
    private $comment = '';

    /** Date of the donation.
     * @var object */
    private $dt = NULL;

    /** Contributer's name.
     * @var string */
    private $contrib_name = '';

    /** Transaction ID, obtained from the payment gateway.
     * @var string */
    private $txn_id = '';

    /** Donation amount
     * @var float */
    private $amount = 0;

    /** Flag to indicate a new donation record.
     * @var boolean */
    private $isNew = 1;

    /**
     * Read campaign data from the database, or create
     * a blank entry with default values
     *
     * @param   integer $don_id     Optional donation ID to read
     */
    public function __construct($don_id = 0)
    {
        global $_USER, $_TABLES, $_CONF_DON;

        $this->don_id = (int)$don_id;

        if ($this->don_id != 0) {
            $this->Read($this->don_id);
        } else {
            // Set default values
            $this->setDate('now');
        }
    }


    /**
     * Read a single campaign record into the object.
     *
     * @param   integer $don_id     Donation record ID
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
     * Set all the variables in this object from values provided.
     *
     * @param   array   $A  Array of values, either from $_POST or database
     */
    public function setVars($A)
    {
        if (!is_array($A))
            return;

        $this->don_id = (int)$A['don_id'];
        $this->uid = (int)$A['uid'];
        $this->contrib_name = $A['contrib_name'];
        if (isset($A['tm'])) {  // from a form with separate date/time fields
            $A['dt'] .= ' ' . $A['tm'];
        }
        $this->setDate($A['dt']);
        $this->camp_id = $A['camp_id'];
        $this->amount = (float)$A['amount'];
        $this->comment = $A['comment'];
        $this->txn_id = $A['txn_id'];
    }


    /**
     * Delete a donation.
     * Can be called as self::Delete($id).
     *
     * @param  integer  $don_id Donation record ID
     */
    public static function Delete($don_id = 0)
    {
        global $_TABLES;

        DB_delete($_TABLES['don_donations'], 'don_id', (int)$don_id);
    }


    /**
     * Delete multiple donation records at once.
     *
     * @param   array   $delitem    Array of donation record IDs
     */
    public static function deleteMulti($delitem)
    {
        global $_TABLES;

        $delitem = array_map('intval', $delitem);
        $items = implode(',', $delitem);
        if (!empty($items)) {
            $sql = "DELETE FROM {$_TABLES['don_donations']}
                WHERE don_id IN ($items)";
            DB_query($sql);
        }
    }


    /**
     * Create the editing form for a donation.
     *
     * @return  string      HTML for edit form
     */
    public function Edit()
    {
        global $_CONF, $_CONF_DON;

        $T = new \Template(__DIR__ . '/../templates');
        $T->set_file('editform', 'donationform.thtml');
        $T->set_var(array(
            'pi_url'        => DON_URL,
            'help_url'      => DON_URL . '/docs/campaignform_help.html',
            'action_url'    => DON_ADMIN_URL . '/index.php',
            'don_id'            => $this->don_id,
            'contributor_select' => $this->UserDropdown($this->uid),
            'contrib_name'  => $this->contrib_name,
            'dt'            => $this->dt->format('Y-m-d', true),
            'tm'            => $this->dt->format('H:i', true),
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
     * Save this donation.
     *
     * @param   array   $A  Array of values from $_POST (optional)
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
     * Return the option elements for a user selection dropdown.
     *
     * @param   string  $sel    Campaign ID to show as selected
     * @return  string          HTML for option statements
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
     * Get the total amount received for a campaign.
     *
     * @param   string  $camp_id    Campaign ID
     * @return  float               Total received
     */
    public static function totalReceived($camp_id)
    {
        global $_TABLES;

        $camp_id = DB_escapeString($camp_id);
        $received = DB_getItem($_TABLES['don_donations'],
                'SUM(amount)', "camp_id = '$camp_id'");
        return $received;
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
     * Set the donation date object from a provided date string.
     *
     * @param   string  $dt_str     Datetime string
     * @return  object  $this
     */
    private function setDate($dt_str)
    {
        global $_CONF;

        $this->dt = new \Date($dt_str, $_CONF['timezone']);
        return $this;
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
     * Create an admin list of donations for a campaign.
     *
     * @param   string  $camp_id    Campaign ID to filter
     * @return  string  HTML for list
     */
    public static function adminList($camp_id='')
    {
        global $_CONF, $_TABLES, $LANG_ADMIN, $LANG_ACCESS;
        global $_CONF_DON, $LANG_DON;

        USES_lib_admin();
        $retval = '';

        $header_arr = array(      // display 'text' and use table field 'field'
            array(
                'field' => 'edit',
                'text' => $LANG_ADMIN['edit'],
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'field' => 'dt',
                'text' => $LANG_DON['date'],
                'sort' => true,
            ),
            array(
                'field' => 'uid',
                'text' => $LANG_DON['contributor'],
                'sort' => true,
            ),
            array(
                'field' => 'amount',
                'text' => $LANG_DON['amount'],
                'sort' => true,
            ),
            array(
                'field' => 'txn_id',
                'text' => $LANG_DON['txn_id'],
                'sort' => true),
            array(
                'field' => 'delete',
                'text' => $LANG_DON['delete'],
                'align' => 'center',
            ),
        );

        $C = Campaign::getInstance($camp_id);
        $title = $LANG_DON['campaign'] . " :: {$C->getName()}";
        if ($C->hasStart()) {
            $title .= ' (' . $C->getStart()->toMySQL(true) . ')';
        }

        $text_arr = array(
            'has_extras' => true,
            'form_url' => DON_ADMIN_URL .
                '/index.php?donations=x&camp_id='.$camp_id,
        );
        $options = array('chkdelete' => 'true', 'chkfield' => 'don_id');
        $defsort_arr = array('field' => 'dt', 'direction' => 'desc');
        $query_arr = array(
            'table' => 'don_donations',
            'sql' => "SELECT * FROM {$_TABLES['don_donations']}",
            'query_fields' => array(),
            'default_filter' => "WHERE camp_id ='".DB_escapeString($camp_id)."'",
        );
        $form_arr = array();
        $retval .= '<h3>' . $title . '</h3>';
        $retval .= ADMIN_list(
            'donation_donationlist',
            array(__CLASS__, 'getListField'),
            $header_arr,
            $text_arr, $query_arr, $defsort_arr, '', '',
            $options, $form_arr
        );
        return $retval;
    }
    
    
    /**
     * Get a single field for the Donation admin list.
     *
     * @param   string  $fieldname  Name of field
     * @param   mixed   $fieldvalue Value of field
     * @param   array   $A          Array of all fields
     * @param   array   $icon_arr   Array of system icons
     * @return  string              HTML content for field display
     */
    public static function getListField($fieldname, $fieldvalue, $A, $icon_arr)
    {
        global $_CONF, $LANG_ACCESS, $LANG_DON, $_CONF_DON;

        $retval = '';

        switch($fieldname) {
        case 'edit':
            $retval .= COM_createLink(
                '<i class="uk-icon uk-icon-edit tooltip" title="' .
                    $LANG_DON['edit_item'] . '"></i>',
                DON_ADMIN_URL .
                    '/index.php?editdonation=' . $A['don_id'] . '&amp;camp_id=' . $A['camp_id']
            );
        break;

        case 'amount':
            $retval = '<span class="text-align:right;">' .
                sprintf("%6.2f", $fieldvalue) . '</span>';
            break;

        case 'uid':
            $retval = COM_getDisplayName($fieldvalue);
            break;

        case 'txn_id':
            $status = LGLIB_invokeService(
                'shop', 'getUrl',
                array(
                    'type'  => 'ipn',
                    'id'    => $fieldvalue,
                ),
                $output, $svc_msg
            );
            if ($status == PLG_RET_OK) {
                $retval = COM_createLink($fieldvalue, $output);
            } else {
                $retval = $fieldvalue;
            }
            break;

        case 'delete':
            $retval = COM_createLink(
                '<i class="uk-icon uk-icon-trash uk-text-danger" ' .
                    ' tooltip" title="' . $LANG_DON['delete'] . '"></i>',
                DON_ADMIN_URL .
                    "/index.php?deletedonation=x&amp;don_id={$A['don_id']}",
                array(
                    'onclick' => 'return confirm(\'' . $LANG_DON['q_del_item'] . '\');',
                )
            );
            break;

        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }

}

?>
