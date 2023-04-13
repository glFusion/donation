<?php
/**
 * Class to handle donations.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2023 Lee Garner <lee@leegarner.com>
 * @package     donation
 * @version     v0.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Donation;
use glFusion\Database\Database;
use glFusion\Log\Log;


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


    /**
     * Read campaign data from the database, or create
     * a blank entry with default values
     *
     * @param   integer $don_id     Optional donation ID to read
     */
    public function __construct($don_id = 0)
    {
        global $_USER, $_TABLES;

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
    public function Read(int $don_id) : self
    {
        global $_TABLES;

        try {
            $A = Database::getInstance()->conn->executeQuery(
                "SELECT * FROM {$_TABLES['don_donations']} WHERE don_id = ?",
                array($don_id),
                array(Database::INTEGER)
            )->fetchAssociative();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $A =- false;
        }
        if (is_array($A)) {
            $this->setVars(new DataArray($A));
        }
    }


    /**
     * Set all the variables in this object from values provided.
     *
     * @param   DataArray   $A  Array of values, either from $_POST or database
     * @return  object      $this
     */
    public function setVars(DataArray $A) : self
    {
        $this->don_id = $A->getInt('don_id');
        $this->uid = $A->getInt('uid');
        $this->contrib_name = $A->getString('contrib_name');
        if (isset($A['tm'])) {  // from a form with separate date/time fields
            $A['dt'] .= ' ' . $A['tm'];
        }
        $this->setDate($A->getString('dt'));
        $this->camp_id = $A->getString('camp_id');
        $this->setAmount($A->getFloat('amount'));
        $this->comment = $A->getString('comment');
        $this->txn_id = $A->getString('txn_id');
        return $this;
    }


    /**
     * Delete a donation.
     *
     * @param   integer $don_id Donation record ID
     * @return  boolean     True on success, False on error
     */
    public static function Delete(int $don_id) : bool
    {
        global $_TABLES;

        try {
            Database::getInstance()->conn->delete(
                $_TABLES['don_donations'],
                array('don_id' => $don_id),
                array(Database::INTEGER)
            );
            return true;
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Delete multiple donation records at once.
     *
     * @param   array   $delitem    Array of donation record IDs
     * @return  boolean     True on success, False on error
     */
    public static function deleteMulti(array $delitem) : bool
    {
        global $_TABLES;

        try {
            Database::getInstance()->conn->executeStatement(
                $sql = "DELETE FROM {$_TABLES['don_donations']} WHERE don_id IN ?",
                array($delitems),
                array(Database::PARAM_INT_ARRAY)
            );
            return true;
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Create the editing form for a donation.
     *
     * @return  string      HTML for edit form
     */
    public function Edit()
    {
        global $_CONF;

        $T = new \Template(__DIR__ . '/../templates');
        $T->set_file('editform', 'donationform.thtml');
        $T->set_var(array(
            'pi_url'        => Config::get('url'),
            'help_url'      => Config::get('url') . '/docs/campaignform_help.html',
            'action_url'    => Config::get('admin_url') . '/index.php',
            'don_id'            => $this->don_id,
            'contributor_select' => $this->UserDropdown($this->uid),
            'contrib_name'  => $this->contrib_name,
            //'dt'            => $this->dt->format('Y-m-d', true),
            //'tm'            => $this->dt->format('H:i', true),
            'dt_tm'         => $this->dt->format('Y-m-d H:i', true),
            'comment'       => $this->comment,
            'amount'        => $this->amount > 0 ? $this->amount : '',
            'campaign_select' =>
                        Campaign::DropDown($this->camp_id),
            'txn_id'        => $this->txn_id,
            'doc_url'       => DON_getDocUrl(
                'donationform.html',
                $_CONF['language']
            ),
        ) );

        $T->parse ('output', 'editform');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Save this donation.
     *
     * @param   array   $A  Array of values from $_POST (optional)
     */
    public function Save(?DataArray $A=NULL) : bool
    {
        global $_TABLES, $LANG_DON;

        if ($A) {
            $this->setVars($A);
        }

        $dt = $this->dt;
        if (empty($dt))
            $dt = 'NULL';
        else
            $dt = "'" . DB_escapeString($dt) . "'";

        if (
            empty($this->contrib_name) &&
            $this->uid > 0
        ) {
            $this->contrib_name = COM_getDisplayName($this->uid);
        }


        $values = array(
            'camp_id' => $this->camp_id,
            'uid' => $this->uid,
            'contrib_name' => $this->contrib_name,
            'comment' => $this->comment,
            'dt' => $dt,
            'txn_id' => $this->txn_id,
            'amount' => $this->amount,
        );
        $types = array(
            Database::STRING,
            Database::INTEGER,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
        );

        $db = Database::getInstance();
        try {
            if ($this->don_id == 0) {
                $db->conn->insert(
                    $_TABLES['don_donations'],
                    $values,
                    $types
                );
                $this->don_id = $db->conn->lastInsertId();
            } else {
                $types[] = Database::INTEGER;
                $db->conn->update(
                    $_TABLES['don_donations'],
                    $values,
                    array('don_id' => $this->don_id),
                    $types
                );
            }
            return true;
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Return the option elements for a user selection dropdown.
     *
     * @param   string  $sel    Campaign ID to show as selected
     * @return  string          HTML for option statements
     */
    public static function UserDropDown(int $sel=0) : string
    {
        global $_TABLES;

        return COM_optionList($_TABLES['users'], 'uid,username', $sel, 1);
    }


    /**
     * Get the total amount received for a campaign.
     *
     * @param   string  $camp_id    Campaign ID
     * @return  float               Total received
     */
    public static function totalReceived(string $camp_id) : float
    {
        global $_TABLES;

        return (float)Database::getInstance()->getItem(
            $_TABLES['don_donations'],
            'SUM(amount)',
            array('camp_id' => $camp_id)
        );
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
    public function setDate($dt_str)
    {
        global $_CONF;

        $this->dt = new \Date($dt_str, $_CONF['timezone']);
        return $this;
    }


    /**
     * Set the contributing user ID.
     *
     * @param   integer $uid        User ID
     * @return  object  $this
     */
    public function setUid($uid=0)
    {
        global $_USER;

        if ($uid == 0) {
            $uid = $_USER['uid'];
        }
        $this->uid = (int)$uid;
        return $this;
    }


    /**
     * Set the donation amount.
     *
     * @param   float   $amount     Amount donated
     * @return  object  $this
     */
    public function setAmount($amount)
    {
        $this->amount = (float)$amount;
        return $this;
    }


    /**
     * Set the contributor name.
     *
     * @param   string  $name   Contributor name
     * @return  object  $this
     */
    public function setContributorName($name)
    {
        $this->contrib_name = $name;
        return $this;
    }


    /**
     * Set the related campaign ID.
     *
     * @param   string  $camp_id    Campaign ID
     * @return  object  $this
     */
    public function setCampaignID($camp_id)
    {
        $this->camp_id = $camp_id;
        return $this;
    }


    /**
     * Set the comment text field value.
     *
     * @param   string  $text       Comment text
     * @return  object  $this
     */
    public function setComment($text)
    {
        $this->comment = $text;
        return $this;
    }


    /**
     * Set the transaction ID for this donation.
     *
     * @param   string  $id         Transaction ID
     * @return  object  $this
     */
    public function setTxnId($id)
    {
        $this->txn_id = $id;
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
        global $LANG_DON;

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
            'form_url' => Config::get('admin_url') .
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
        global $_CONF, $LANG_ACCESS, $LANG_DON;

        $retval = '';

        switch($fieldname) {
        case 'edit':
            $retval .= COM_createLink(
                '<i class="uk-icon uk-icon-edit tooltip" title="' .
                    $LANG_DON['edit_item'] . '"></i>',
                Config::get('admin_url') .
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
            $status = PLG_callFunctionForOnePlugin(
                'service_getUrl_shop',
                array(
                    1 => array(
                        'type'  => 'ipn',
                        'id'    => $fieldvalue,
                    ),
                    2 => &$output,
                    3 => &$svc_msg,
                )
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
                Config::get('admin_url') .
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


    /**
     * Update all donation records from an old campaign ID to a new one.
     * Called from Campaign::Save() if the campaign ID was changed.
     *
     * @param   string  $old_id     Original campaign ID
     * @param   string  $new_id     New campaign ID
     * @return  boolean     True on success, False on error
     */
    public static function updateCampaignIDs(string $old_id, string $new_id) : bool
    {
        global $_TABLES;

        try {
            Database::getInstance()->conn->update(
                $_TABLES['don_donations'],
                array('camp_id' => $new_id),
                array('camp_id' => $old_id),
                array(Database::STRING, Database::STRING)
            );
            return true;
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
    }

}
