<?php
/**
 * Administrative entry point for the Donation plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2021 Lee Garner <lee@leegarner.com>
 * @package     donation
 * @version     v0.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Import core glFusion functions */
require_once('../../../lib-common.php');
use Donation\Config;

/**
 * MAIN
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
    'delbutton_x',
    // Views to display
    'campaigns', 'editcampaign', 'editdonation', 'donations', 'campaigns',
);
foreach($expected as $provided) {
    if (isset($_POST[$provided])) {
        $action = $provided;
        $actionval = $_POST[$provided];
        break;
    } elseif (isset($_GET[$provided])) {
        $action = $provided;
        $actionval = $_GET[$provided];
        break;
    }
}

// Get the campaign and donation IDs, if any
$camp_id = isset($_REQUEST['camp_id']) ?
        COM_sanitizeID($_REQUEST['camp_id'], false) : '';
$don_id = isset($_REQUEST['don_id']) ? (int)$_REQUEST['don_id'] : 0;
$content = '';      // initialize variable for page content

switch ($action) {
case 'savecampaign':
    $old_camp_id = isset($_POST['old_camp_id']) ? $_POST['old_camp_id'] : '';
    $C = Donation\Campaign::getInstance($old_camp_id);
    $C->Save($_POST);
    COM_refresh(Config::get('admin_url') . '/index.php?campaigns');
    break;

case 'deletecampaign':
    Donation\Campaign::Delete($camp_id);
    COM_refresh(Config::get('admin_url') . '/index.php?campaigns');
    break;

case 'savedonation':
    $D = new Donation\Donation($don_id);
    $D->Save($_POST);
    COM_refresh(Config::get('admin_url') . '/index.php?donations&camp_id=' . $camp_id);
    break;

case 'deletedonation':
    $D = new Donation\Donation($don_id);
    // Set camp_id to stay on the donations page for the campaign
    $camp_id = $D->getCampaiginID();
    Donation\Donation::Delete($don_id);
    COM_refresh(Config::get('admin_url') . '/index.php?donations');
    break;

case 'delbutton_x':     // deleting multiple items
    if (isset($_GET['donations'])) {
        Donation\Donation::deleteMulti($_POST['delitem']);
        COM_refresh(Config::get('admin_url') . '/index.php?donations=x&camp_id=' . $_GET['camp_id']);
    } elseif (isset($_GET['campaigns'])) {
        $type = 'campaigns';
    }
    break;

default:
    $view = $action;
    break;
}

// Display the correct page content
switch ($view) {
case 'editcampaign':
    $C = Donation\Campaign::getInstance($camp_id);
    $content .= $C->Edit();
    break;

case 'editdonation':
    $don_id = (int)$actionval;
    $D = new Donation\Donation($don_id);
    $content .= $D->Edit();
    break;

case 'donations':
    $content .= Donation\Donation::adminList($camp_id);
    break;

case 'campaigns':
default:
    $view = 'campaigns';
    $content .= Donation\Campaign::adminList();
    break;
}

$display = COM_siteHeader();
$display .= Donation\Menu::Admin($view);
$display .= $content;
$display .= COM_siteFooter();
echo $display;

?>
