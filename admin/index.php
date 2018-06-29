<?php
/**
*   Administrative entry point for the Donation plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2017 Lee Garner <lee@leegarner.com>
*   @package    donation
*   @version    0.0.2
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

/** Import core glFusion functions */
require_once('../../../lib-common.php');

USES_lib_admin();

/**
*   Basic admin menu for Donation administration
*
*   @param  string  $view   Current View
*   @return string          HTML for admin menu
*/
function DON_adminMenu($view='')
{
    global $_CONF, $LANG_ADMIN, $LANG_DON, $_CONF_DON;

    $retval = '';
    switch ($view) {
    case 'campaigns':
    default:
        $act_campaigns = true;
        $act_newcamp = false;
        $act_newdon = false;
        break;

    case 'editcampaign':
        $act_campaigns = false;
        $act_newcamp = true;
        $act_newdon  = false;
        break;

    case 'editdonation':
        $act_campaigns = false;
        $act_newcamp = false;
        $act_newdon = true;
        break;
    }

    $menu_arr = array(
        array(  'url' => $_CONF['site_admin_url'],
                'text' => $LANG_ADMIN['admin_home'],
        ),
        array(  'url' => DON_ADMIN_URL . '/index.php',
                'text' => $LANG_DON['campaigns'],
                'active' => $act_campaigns,
        ),
        array(  'url' => DON_ADMIN_URL . '/index.php?editcampaign=x',
                'text' => $LANG_DON['new_campaign'],
                'active' => $act_newcamp,
        ),
        array(  'url' => DON_ADMIN_URL . '/index.php?editdonation=x',
                'text' => $LANG_DON['new_donation'],
                'active' => $act_newdon,
        ),
        /*array('url' => DON_ADMIN_URL . '/index.php?resetbuttons=x',
              'text' => $LANG_DON['reset_buttons']),*/
    );
    $T = new \Template(DON_PI_PATH . '/templates');
    $T->set_file('page', 'admin.thtml');
    $T->set_var(array(
        'header'    => $LANG_DON['don_mgr'],
        'pi_url'    => DON_URL,
        'pi_icon'   => plugin_geticon_donation(),
        'plugin'    => $_CONF_DON['pi_name'],
        'version'   => $_CONF_DON['pi_version'],
    ) );
    $T->parse('output','page');
    $retval .= $T->finish($T->get_var('output'));
    $retval .= ADMIN_createMenu($menu_arr, '',
            plugin_geticon_donation());
    return $retval;
}


/**
*   Create an admin list of donations for a campaign
*
*   @return string  HTML for list
*/
function DON_donationList($camp_id)
{
    global $_CONF, $_TABLES, $LANG_ADMIN, $LANG_ACCESS;
    global $_CONF_DON, $LANG_DON;

    $retval = '';

    $header_arr = array(      // display 'text' and use table field 'field'
        array('field' => 'edit',
            'text' => $LANG_ADMIN['edit'], 'sort' => false, 'align' => 'center'),
        array('field' => 'dt',
            'text' => $LANG_DON['date'], 'sort' => true),
        array('field' => 'uid',
            'text' => $LANG_DON['contributor'], 'sort' => true),
        array('field' => 'amount',
            'text' => $LANG_DON['amount'], 'sort' => true),
        array('field' => 'txn_id',
            'text' => $LANG_DON['txn_id'], 'sort' => true),
        array('field' => 'delete',
            'text' => $LANG_DON['delete'], 'align' => 'center'),
    );

    $C = new \Donation\Campaign($camp_id);
    $title = $LANG_DON['campaign'] . " :: $C->name";
    if (!empty($C->startdt)) {
        $title .= ' (' . $C->startdt . ')';
    }

    $text_arr = array(
        'has_extras' => true,
        'form_url' => DON_ADMIN_URL .
                '/index.php?donations=x&camp_id='.$camp_id,
    );
    $options = array('chkdelete' => 'true', 'chkfield' => 'don_id');
    $defsort_arr = array('field' => 'dt', 'direction' => 'desc');
    $query_arr = array('table' => 'don_donations',
        'sql' => "SELECT *
                    FROM {$_TABLES['don_donations']}",
        'query_fields' => array(),
        'default_filter' => "WHERE camp_id ='".DB_escapeString($camp_id)."'",
    );
    $form_arr = array();
    $retval .= '<h3>' . $title . '</h3>';
    $retval .= ADMIN_list('donation_donationlist', 'DON_donation_getListField', $header_arr,
                    $text_arr, $query_arr, $defsort_arr, '', '',
                    $options, $form_arr);
    return $retval;
}


/**
*   Get a single field for the Donation admin list.
*
*   @param  string  $fieldname  Name of field
*   @param  mixed   $fieldvalud Value of field
*   @param  array   $A          Array of all fields
*   @param  array   $icon_arr   Array of system icons
*   @return string              HTML content for field display
*/
function DON_donation_getListField($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $LANG_ACCESS, $LANG_DON, $_CONF_DON;

    $retval = '';

    switch($fieldname) {
    case 'edit':
        $retval .= COM_createLink('<i class="' . DON_getIcon('edit') . ' tooltip" title="' .
                    $LANG_DON['edit_item'] . '"></i>',
                DON_ADMIN_URL .
                '/index.php?editdonation=x&amp;camp_id=' . $A['camp_id']
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
        $status = LGLIB_invokeService('paypal', 'getUrl',
                array('type'=>'ipn', 'id'=>$fieldvalue),
                $output, $svc_msg);
        if ($status == PLG_RET_OK) {
            $retval = COM_createLink($fieldvalue, $output);
        } else {
            $retval = $fieldvalue;
        }
        break;

    case 'delete':
        $retval = COM_createLink('<i class="' . DON_getIcon('trash', 'danger') .
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


/**
*   Create an admin list of campaigns.
*
*   @return string  HTML for list
*/
function DON_campaignList()
{
    global $_CONF, $_TABLES, $LANG_ADMIN, $LANG_ACCESS;
    global $_CONF_DON, $LANG_DON;

    $retval = '';

    $header_arr = array(      # display 'text' and use table field 'field'
        array('field' => 'edit',
            'text' => $LANG_ADMIN['edit'], 'sort' => false, 'align', 'center'),
        array('field' => 'enabled',
            'text' => $LANG_DON['enabled'], 'sort' => false, 'align', 'center'),
        array('field' => 'name',
            'text' => $LANG_DON['camp_name'], 'sort' => true),
        array('field' => 'startdt',
            'text' => $LANG_DON['startdate'], 'sort' => true),
        array('field' => 'enddt',
            'text' => $LANG_DON['enddate'], 'sort' => true),
        array('field' => 'goal',
            'text' => $LANG_DON['goal'], 'sort' => true),
        array('field' => 'received',
            'text' => $LANG_DON['received'], 'sort' => true),
        array('text' => $LANG_ADMIN['delete'],
            'field' => 'delete', 'sort' => false,
            'align' => 'center'),
   );

    $defsort_arr = array('field' => 'startdt', 'direction' => 'desc');

    $text_arr = array(
        'has_extras' => true,
        'form_url' => DON_ADMIN_URL . '/index.php?type=campaigns',
    );

    //$options = array('chkdelete' => 'true', 'chkfield' => 'camp_id');

    $query_arr = array('table' => 'don_campaigns',
        'sql' => "SELECT c.*, (SELECT SUM(amount)
                FROM {$_TABLES['don_donations']} d
                WHERE d.camp_id = c.camp_id) as received
                 FROM {$_TABLES['don_campaigns']} c",
        'query_fields' => array('name', 'description'),
        'default_filter' => 'WHERE 1=1'
    );
    $options = array();
    $form_arr = array();
    $retval .= ADMIN_list('donation_campaignlist', 'DON_campaign_getListField', $header_arr,
                    $text_arr, $query_arr, $defsort_arr, '', '',
                    $options, $form_arr);
    return $retval;
}


/**
*   Get a single field for the Campaign admin list.
*
*   @param  string  $fieldname  Name of field
*   @param  mixed   $fieldvalud Value of field
*   @param  array   $A          Array of all fields
*   @param  array   $icon_arr   Array of system icons
*   @return string              HTML content for field display
*/
function DON_campaign_getListField($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $LANG_ACCESS, $LANG_DON, $_CONF_DON;

    $retval = '';

    switch($fieldname) {
    case 'edit':
        $retval .= COM_createLink('<i class="' . DON_getIcon('edit') . ' tooltip" title="' .
                    $LANG_DON['edit_item'] . '"></i>',
                DON_ADMIN_URL .
                '/index.php?editcampaign=x&amp;camp_id=' . $A['camp_id']
        );
        break;

    case 'delete':
        if (!Donation\Campaign::isUsed($A['camp_id'])) {
            $retval = COM_createLink('<i class="' . DON_getIcon('trash', 'danger') .
                ' tooltip" title="' . $LANG_DON['delete'] . '"></i>',
                DON_ADMIN_URL .
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
                sprintf("%6.2f", $fieldvalue) . '</span>';
        break;
    case 'startdt':
    case 'enddt':
        $retval = $fieldvalue;
        break;

    case 'name':
        $retval = COM_createLink($fieldvalue,
                DON_ADMIN_URL .
                '/index.php?donations=x&camp_id=' . $A['camp_id']);
        break;

    default:
        $retval = $fieldvalue;
        break;
    }

    return $retval;
}



/**
*   MAIN
*/
// If plugin is installed but not enabled, display an error and exit gracefully
if (!in_array('donation', $_PLUGINS)) {
    COM_404();
    exit;
}

// Only let admin users access this page
if (!SEC_hasRights('donation.admin')) {
    COM_errorLog("Attempted unauthorized access the Donation Admin page." .
        " User id: {$_USER['uid']}, Username: {$_USER['username']}, " .
        " IP: $REMOTE_ADDR", 1);
    COM_404();
    exit;
}

$action = '';
$expected = array(
    // Actions to perform
    'savecampaign', 'deletecampaign', 'savedonation', 'deletedonation',
    'resetbuttons',
    // Views to display
    'campaigns', 'editcampaign', 'editdonation', 'donations', 'campaigns',
);
foreach($expected as $provided) {
    if (isset($_POST[$provided])) {
        $action = $provided;
        $var = $_POST[$provided];
        break;
    } elseif (isset($_GET[$provided])) {
        $action = $provided;
        $var = $_GET[$provided];
        break;
    }
}

// Get the campaign and donation IDs, if any
$camp_id = isset($_REQUEST['camp_id']) ?
                COM_sanitizeID($_REQUEST['camp_id'], false) : '';
$don_id = isset($_REQUEST['don_id']) ?
                (int)$_REQUEST['don_id'] : 0;
$content = '';      // initialize variable for page content

switch ($action) {
case 'savecampaign':
    $old_camp_id = isset($_POST['old_camp_id']) ? $_POST['old_camp_id'] : '';
    $C = new Donation\Campaign($old_camp_id);
    $C->Save($_POST);
    $view = 'campaigns';
    break;

case 'deletecampaign':
    Donation\Campaign::Delete($camp_id);
    $view = 'campaigns';
    break;

case 'savedonation':
    $D = new Donation\Donation($don_id);
    $D->Save($_POST);
    $view = 'donations';
    break;

case 'deletedonation':
    $D = new Donation\Donation($don_id);
    // Set camp_id to stay on the donations page for the campaign
    $camp_id = $D->camp_id;
    Donation\Donation::Delete($don_id);
    $view = 'donations';
    break;

case 'resetbuttons':
    $sql = "SELECT camp_id FROM {$_TABLES['don_campaigns']}";
    $res = DB_query($sql);
    $P = new Donation\Campaign();
    while ($A = DB_fetchArray($res, false)) {
        $P->Read($A['camp_id']);
        $P->Save();
    }
    $view = 'campaigne';
    break;

default:
    $view = $action;
    break;
}

// Display the correct page content
switch ($view) {
case 'editcampaign':
    $C = new Donation\Campaign($camp_id);
    $content .= $C->Edit();
    break;

case 'editdonation':
    $D = new Donation\Donation($don_id);
    $content .= $D->Edit();
    break;

case 'donations':
    $content .= DON_donationList($camp_id);
    break;

case 'campaigns':
default:
    $view = 'campaigns';
    $content .= DON_campaignList();
    break;
}

$display = COM_siteHeader();
$display .= DON_adminMenu($view);
$display .= $content;
$display .= COM_siteFooter();
echo $display;

?>
