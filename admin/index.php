<?php
/**
 * Administrative entry point for the Donation plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2023 Lee Garner <lee@leegarner.com>
 * @package     donation
 * @version     v0.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Import core glFusion functions */
require_once('../../../lib-common.php');
use Donation\Config;
use Donation\Models\Request;


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
$Request = Request::getInstance();
list($action, $actionval) = $Request->getAction($expected, 'campaigns');

// Get the campaign and donation IDs, if any
$camp_id = $Request->getString('camp_id');
$don_id = $Request->getInt('don_id');
$content = '';      // initialize variable for page content

switch ($action) {
case 'savecampaign':
    $old_camp_id = $Request->getString('old_camp_id');
    $C = Donation\Campaign::getInstance($old_camp_id);
    $C->Save($Request);
    echo COM_refresh(Config::get('admin_url') . '/index.php?campaigns');
    break;

case 'deletecampaign':
    $C = new Donation\Campaign($camp_id);
    if ($C->getID() == $camp_id) {
        $C->Delete();
    }
    echo COM_refresh(Config::get('admin_url') . '/index.php?campaigns');
    break;

case 'savedonation':
    $D = new Donation\Donation($don_id);
    $D->Save($Request);
    echo COM_refresh(Config::get('admin_url') . '/index.php?donations&camp_id=' . $camp_id);
    break;

case 'deletedonation':
    $D = new Donation\Donation($don_id);
    $D->Delete();
    echo COM_refresh(Config::get('admin_url') . '/index.php?donations');
    break;

case 'delbutton_x':     // deleting multiple items
    if (isset($_GET['donations'])) {
        Donation\Donation::deleteMulti($Request->getArray('delitem'));
        COM_refresh(
            Config::get('admin_url') . '/index.php?donations=x&camp_id=' . $Request->getString('camp_id')
        );
    } elseif (isset($_GET['campaigns'])) {
        $view = 'campaigns';
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
