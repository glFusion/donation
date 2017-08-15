<?php
/**
*   Installation defaults for the Donation plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2017 Lee Garner <lee@leegarner.com>
*   @package    donation
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*   GNU Public License v2 or later
*   @filesource
*/

if (!defined('GVERSION')) {
    die('This file can not be used on its own!');
}

/**
 *  Daily Quote default settings
 *
 *  Initial Installation Defaults used when loading the online configuration
 *  records. These settings are only used during the initial installation
 *  and not referenced any more once the plugin is installed
 *  @global array $_DON_DEFAULT
 *
 */
global $_DON_DEFAULT, $_CONF_DON;
$_DON_DEFAULT = array(
    'pp_use_donation' => 0,
);


/**
 *  Initialize Daily Quote plugin configuration
 *
 *  Creates the database entries for the configuation if they don't already
 *  exist. Initial values will be taken from $_CONF_DON if available (e.g. from
 *  an old config.php), uses $_DON_DEFAULT otherwise.
 *
 *  @param  integer $group_id   Group ID to use as the plugin's admin group
 *  @return boolean             true: success; false: an error occurred
 */
function plugin_initconfig_donation($group_id = 0)
{
    global $_CONF, $_CONF_DON, $_DON_DEFAULT;

    if (is_array($_CONF_DON) && (count($_CONF_DON) > 1)) {
        $_DON_DEFAULT = array_merge($_DON_DEFAULT, $_CONF_DON);
    }

    $c = config::get_instance();

    if (!$c->group_exists($_CONF_DON['pi_name'])) {

        $c->add('sg_main', NULL, 'subgroup', 0, 0, NULL, 0, true, 
                $_CONF_DON['pi_name']);
        $c->add('fs_paypal', NULL, 'fieldset', 0, 0, NULL, 0, true, 
                $_CONF_DON['pi_name']);

        $c->add('pp_use_donation', $_DON_DEFAULT['pp_use_donation'],
                'select', 0, 0, 3, 10, true, $_CONF_DON['pi_name']);
    }

    return true;
}

?>
