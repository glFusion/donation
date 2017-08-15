<?php
/**
*   Upgrade routines for the Donation plugin
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2017 Lee Garner <lee@leegarner.com>
*   @package    donation
*   @version    0.0.2
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

// Required to get the config values
global $_CONF, $_CONF_DON, $_DB_dbms, $_UPGRADE_SQL;

/** Include the table creation strings */
require_once DON_PI_PATH . "/sql/{$_DB_dbms}_install.php";

/**
*   Perform the upgrade starting at the current version.
*   If a version has no upgrade activity, e.g. only a code change,
*   then no upgrade section is required.  The version is bumped in
*   functions.inc.
*
*   @param  string  $current_ver Current installed version to be upgraded
*   @return integer Error code, 0 for success
*/
function donation_do_upgrade()
{
    global $_CONF, $_CONF_DON, $_PLUGIN_INFO;

    if (isset($_PLUGIN_INFO[$_CONF_DON['pi_name']])) {
        if (is_array($_PLUGIN_INFO[$_CONF_DON['pi_name']])) {
            // glFusion > 1.6.5
            $current_ver = $_PLUGIN_INFO[$_CONF_DON['pi_name']]['pi_version'];
        } else {
            // legacy
            $current_ver = $_PLUGIN_INFO[$_CONF_DON['pi_name']];
        }
    } else {
        return false;
    }
    $installed_ver = plugin_chkVersion_photocomp();

    if (!COM_checkVersion($current_ver, '0.0.2')) {
        $current_ver = '0.0.2';
        if (!donation_do_upgrade_sql($current_ver)) return false;
        if (!donation_do_set_version($current_ver)) return false;
    }

    // Final version update to catch updates that don't go through
    // any of the update functions, e.g. code-only updates
    if (!COM_checkVersion($current_ver, $installed_ver)) {
        if (!donation_do_set_version($installed_ver)) {
            return false;
        }
    }
    COM_errorLog("Successfully updated the {$_CONF_DON['pi_display_name']} Plugin", 1);
    return true;
}


/**
*   Actually perform any sql updates.
*   Gets the sql statements from the $UPGRADE array defined (maybe)
*   in the SQL installation file.
*
*   @since  version 0.0.2
*   @param  string  $version    Version being upgraded TO
*   @param  array   $sql        Array of SQL statement(s) to execute
*/
function donation_do_upgrade_sql($version='')
{
    global $_TABLES, $_UPGRADE_SQL;

    // If no sql statements passed in, return success
    if (!is_array($_UPGRADE_SQL[$version]))
        return true;

    // Execute SQL now to perform the upgrade
    COM_ErrorLog("--Updating Donation SQL to version $version");
    foreach($_UPGRADE_SQL[$version] as $sql) {
        COM_errorLOG("Donation Plugin $version update: Executing SQL => $sql");
        DB_query($sql, '1');
        if (DB_error()) {
            COM_errorLog("SQL Error during Donation Plugin update",1);
            return false;
        }
    }
    return true
}

?>
