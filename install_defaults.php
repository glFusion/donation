<?php
/**
*   Installation defaults for the Donation plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2018 Lee Garner <lee@leegarner.com>
*   @package    donation
*   @version    0.0.3
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*   GNU Public License v2 or later
*   @filesource
*/

if (!defined('GVERSION')) {
    die('This file can not be used on its own!');
}

/**
 *  Default settings for the Donation plugin.
 *
 *  Initial Installation Defaults used when loading the online configuration
 *  records. These settings are only used during the initial installation
 *  and not referenced any more once the plugin is installed
 *
 *  @global array $donationConfigData;
 */
global $donationConfigData;
$donationConfigData = array(
    array(
        'name' => 'sg_main',
        'default_value' => NULL,
        'type' => 'subgroup',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'donation',
    ),
    array(
        'name' => 'fs_main',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'donation',
    ),
    array(
        'name' => 'pp_use_donation',
        'default_value' => 0,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 3,
        'sort' => 10,
        'set' => true,
        'group' => 'donation',
    ),
    array(
        'name' => 'num_in_blk',
        'default_value' => 1,
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 20,
        'set' => true,
        'group' => 'donation',
    ),
);


/**
 *  Initialize Donation plugin configuration
 *
 *  Creates the database entries for the configuation if they don't exist.
 *
 *  @param  integer $group_id   Group ID to use as the plugin's admin group
 *  @return boolean             true: success; false: an error occurred
 */
function plugin_initconfig_donation($group_id = 0)
{
    global $donationConfigData;

    $c = config::get_instance();
    if (!$c->group_exists('donation')) {
        USES_lib_install();
        foreach ($donationConfigData AS $cfgItem) {
            _addConfigItem($cfgItem);
        }
    }
    return true;
}


/**
 * Sync the configuration in the DB to the above configs
 */
function plugin_updateconfig_donation()
{
    global $donationConfigData;

    USES_lib_install();
    _update_config('donation', $donationConfigData);
}

?>
