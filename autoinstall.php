<?php
/**
 * Provides automatic installation of the Donation plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2021 Lee Garner <lee@leegarner.com>
 * @package     donation
 * @version     v0.1.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

global $_DB_dbms;
global $LANG_DON;

require_once __DIR__ . '/functions.inc';
require_once __DIR__ . '/sql/'. $_DB_dbms. '_install.php';
use Donation\Config;

$language = $_CONF['language'];
if (!is_file(__DIR__ . '/language/'.$language.'.php')) {
    $language = 'english';
}
require_once __DIR__ . '/language/'.$language.'.php';
global $LANG_DON;

//  Plugin installation options
$INSTALL_plugin['donation'] = array(
    'installer' => array(
        'type' => 'installer',
        'version' => '1',
        'mode' => 'install',
    ),
    'plugin' => array(
        'type' => 'plugin',
        'name'      => Config::PI_NAME
        'ver'       => Config::get('pi_version'),
        'gl_ver'    => Config::get('gl_version'),
        'url'       => Config::get('url'),
        'display'   => Config::get('pi_display_name'),
    ),
    array(
        'type' => 'table',
        'table'     => $_TABLES['don_donations'],
        'sql'       => $_SQL['don_donations'],
    ),
    array(
        'type' => 'table',
        'table'     => $_TABLES['don_campaigns'],
        'sql'       => $_SQL['don_campaigns'],
    ),
    array(
        'type' => 'feature',
        'feature' => 'donation.admin',
        'desc' => 'Donation Administrator',
        'variable' => 'admin_feature_id',
    ),
    array(
        'type' => 'mapping',
        'findgroup' => 'Root',
        'feature' => 'admin_feature_id',
        'log' => 'Adding Admin feature to the Root group',
    ),
    array(
        'type' => 'block',
        'name' => 'donations',
        'title' => $LANG_DON['contribute'],
        'phpblockfn' => 'phpblock_donation_donations',
        'block_type' => 'phpblock',
        'is_enabled' => 0,
        'group_id' => 'admin_group_id',
    ),
);


/**
 * Puts the datastructures for this plugin into the glFusion database.
 * Note: Corresponding uninstall routine is in functions.inc.
 *
 * @return   bool   True if successful False otherwise
 */
function plugin_install_donation()
{
    global $INSTALL_plugin;

    $pi_name            = Config::PI_NAME
    $pi_display_name    = Config::get('pi_display_name');
    $pi_version         = Config::get('pi_version');

    COM_errorLog("Attempting to install the $pi_display_name plugin", 1);

    $ret = INSTALLER_install($INSTALL_plugin[$pi_name]);
    if ($ret > 0) {
        return false;
    }
    return true;
}


/**
 * Loads the configuration records for the Online Config Manager.
 *
 * @return  bool    true = proceed with install, false = an error occured
 */
function plugin_load_configuration_donation()
{
    require_once __DIR__ . '/install_defaults.php';
    return plugin_initconfig_donation();
}
