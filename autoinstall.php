<?php
/**
*   Provides automatic installation of the Banner plugin
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2017 Lee Garner <lee@leegarner.com>
*   @package    donation
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

global $_DB_dbms;
global $LANG_DON;

require_once __DIR__ . '/functions.inc';
require_once __DIR__ . '/sql/'. $_DB_dbms. '_install.php';

$language = $_CONF['language'];
if (!is_file(__DIR__ . '/language/'.$language.'.php')) {
    $language = 'english';
}
require_once __DIR__ . '/language/'.$language.'.php';
global $LANG_DON;

//  Plugin installation options
$INSTALL_plugin['donation'] = array(
    'installer' => array('type' => 'installer',
            'version' => '1',
            'mode' => 'install'),

    'plugin' => array('type' => 'plugin',
            'name'      => $_CONF_DON['pi_name'],
            'ver'       => $_CONF_DON['pi_version'],
            'gl_ver'    => $_CONF_DON['gl_version'],
            'url'       => $_CONF_DON['pi_url'],
            'display'   => $_CONF_DON['pi_display_name']),

    array('type' => 'table',
            'table'     => $_TABLES['don_donations'],
            'sql'       => $_SQL['don_donations']),

    array('type' => 'table',
            'table'     => $_TABLES['don_campaigns'],
            'sql'       => $_SQL['don_campaigns']),

    array('type' => 'group',
            'group' => 'donation Admin',
            'desc' => 'Users in this group can administer the Donation plugin',
            'variable' => 'admin_group_id',
            'admin' => true,
            'addroot' => true),

    array('type' => 'feature',
            'feature' => 'donation.admin',
            'desc' => 'Donation Administrator',
            'variable' => 'admin_feature_id'),

    array('type' => 'mapping',
            'group' => 'admin_group_id',
            'feature' => 'admin_feature_id',
            'log' => 'Adding Admin feature to the admin group'),

    array('type' => 'block',
            'name' => 'donations',
            'title' => $LANG_DON['contribute'],
            'phpblockfn' => 'phpblock_donation_donations',
            'block_type' => 'phpblock',
            'is_enabled' => 0,
            'group_id' => 'admin_group_id'),
);


/**
* Puts the datastructures for this plugin into the glFusion database
*
* Note: Corresponding uninstall routine is in functions.inc
*
* @return   boolean True if successful False otherwise
*
*/
function plugin_install_donation()
{
    global $INSTALL_plugin, $_CONF_DON;

    $pi_name            = $_CONF_DON['pi_name'];
    $pi_display_name    = $_CONF_DON['pi_display_name'];
    $pi_version         = $_CONF_DON['pi_version'];

    COM_errorLog("Attempting to install the $pi_display_name plugin", 1);

    $ret = INSTALLER_install($INSTALL_plugin[$pi_name]);
    if ($ret > 0) {
        return false;
    }

    return true;
}


/**
*   Perform post-installation operations.
*   Copies the documentation.
*/
function plugin_postinstall_donation()
{

}


/**
* Loads the configuration records for the Online Config Manager
*
* @return   boolean     true = proceed with install, false = an error occured
*/
function plugin_load_configuration_donation()
{
    global $_CONF, $_CONF_DON, $_TABLES;

    require_once __DIR__ . '/install_defaults.php';

    // Get the admin group ID that was saved previously.
    $group_id = (int)DB_getItem($_TABLES['groups'], 'grp_id',
            "grp_name='{$_CONF_DON['pi_name']} Admin'");

    return plugin_initconfig_donation($group_id);
}

?>
