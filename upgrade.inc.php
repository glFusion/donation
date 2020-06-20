<?php
/**
 * Upgrade routines for the Donation plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2019 Lee Garner <lee@leegarner.com>
 * @package     donation
 * @version     v0.0.2
 * @license     http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 * @filesource
 */

// Required to get the config values
global $_CONF, $_CONF_DON, $_UPGRADE_SQL;

/** Include the table creation strings */
require_once DON_PI_PATH . "/sql/mysql_install.php";

/**
 * Perform the upgrade starting at the current version.
 * If a version has no upgrade activity, e.g. only a code change,
 * then no upgrade section is required.  The version is bumped in
 * functions.inc.
 * 
 * @param   boolean $dvlp   True to ignore sql errors for development update
 * @return  boolean         True on success, False on failures
 */
function donation_do_upgrade($dvlp=false)
{
    global $_CONF, $_CONF_DON, $_PLUGIN_INFO, $_UPGRADE_SQL, $_TABLES;

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
    $installed_ver = plugin_chkVersion_donation();

    if (!COM_checkVersion($current_ver, '0.0.2')) {
        $current_ver = '0.0.2';
        if (_DON_tableHasColumn('don_campaigns', 'startdt')) {
            // 1. Change to datetime so timestamp doesn't get updated by these changes
            // 2. Add an integer field to get the timestamp value
            $_UPGRADE_SQL[$current_ver][] = "ALTER TABLE {$_TABLES['don_campaigns']} ADD start_ts int(11) unsigned after startdt";
            // 3. Set the int field to the Unix timestamp
            $_UPGRADE_SQL[$current_ver][] = "UPDATE {$_TABLES['don_campaigns']} SET start_ts = UNIX_TIMESTAMP(CONVERT_TZ(`startdt`, '+00:00', @@session.time_zone))";
            // 4. Drop the old timestamp field
            $_UPGRADE_SQL[$current_ver][] = "ALTER TABLE {$_TABLES['don_campaigns']} DROP startdt";
        }
        if (_DON_tableHasColumn('don_campaigns', 'enddt')) {
            // 1. Change to datetime so timestamp doesn't get updated by these changes
            // 2. Add an integer field to get the timestamp value
            $_UPGRADE_SQL[$current_ver][] = "ALTER TABLE {$_TABLES['don_campaigns']} ADD end_ts int(11) unsigned after enddt";
            // 3. Set the int field to the Unix timestamp
            $_UPGRADE_SQL[$current_ver][] = "UPDATE {$_TABLES['don_campaigns']} SET end_ts = UNIX_TIMESTAMP(CONVERT_TZ(`enddt`, '+00:00', @@session.time_zone))";
            // 4. Drop the old timestamp field
            $_UPGRADE_SQL[$current_ver][] = "ALTER TABLE {$_TABLES['don_campaigns']} DROP enddt";
        }

        if (!donation_do_upgrade_sql($current_ver, $dvlp)) return false;
        if (!donation_do_set_version($current_ver)) return false;
    }

    // Final version update to catch updates that don't go through
    // any of the update functions, e.g. code-only updates
    if (!COM_checkVersion($current_ver, $installed_ver)) {
        if (!donation_do_set_version($installed_ver)) {
            return false;
        }
    }

    // Sync any configuration item changes
    include_once 'install_defaults.php';
    plugin_updateconfig_donation();

    COM_errorLog("Successfully updated the {$_CONF_DON['pi_display_name']} Plugin", 1);
    return true;
}


/**
 * Actually perform any sql updates.
 * Gets the sql statements from the $UPGRADE array defined (maybe)
 * in the SQL installation file.
 *
 * @since   v0.0.2
 * @param   string  $version    Version being upgraded TO
 * @param   boolean $ignore_errors  True to ignore errors for dvlupdate
 * @return  boolean     True on success, False on failure
 */
function donation_do_upgrade_sql($version='', $ignore_errors=false)
{
    global $_TABLES, $_UPGRADE_SQL;

    // If no sql statements passed in, return success
    if (!isset($_UPGRADE_SQL[$version]) || !is_array($_UPGRADE_SQL[$version])) {
        return true;
    }

    // Execute SQL now to perform the upgrade
    COM_ErrorLog("--Updating Donation SQL to version $version");
    foreach($_UPGRADE_SQL[$version] as $sql) {
        COM_errorLOG("Donation Plugin $version update: Executing SQL => $sql");
        DB_query($sql, '1');
        if (DB_error()) {
            COM_errorLog("SQL Error during Donation Plugin update",1);
            if (!$ignore_errors) return false;
        }
    }
    return true;
}


/**
 * Update the plugin version number in the database.
 * Called at each version upgrade to keep up to date with
 * successful upgrades.
 *
 * @param   string  $ver    New version to set
 * @return  boolean         True on success, False on failure
 */
function donation_do_set_version($ver)
{
    global $_TABLES, $_CONF_DON, $_PLUGIN_INFO;

    // now update the current version number.
    $sql = "UPDATE {$_TABLES['plugins']} SET
            pi_version = '$ver',
            pi_gl_version = '{$_CONF_DON['gl_version']}',
            pi_homepage = '{$_CONF_DON['pi_url']}'
        WHERE pi_name = '{$_CONF_DON['pi_name']}'";

    $res = DB_query($sql, 1);
    if (DB_error()) {
        COM_errorLog("Error updating the {$_CONF_DON['pi_display_name']} Plugin version",1);
        return false;
    } else {
        COM_errorLog("{$_CONF_DON['pi_display_name']} version set to $ver");
        // Set in-memory config vars to avoid tripping PP_isMinVersion();
        $_CONF_DON['pi_version'] = $ver;
        $_PLUGIN_INFO[$_CONF_DON['pi_name']]['pi_version'] = $ver;
        return true;
    }
}


/**
 * Check if a column exists in a table
 *
 * @param   string  $table      Table Key, defined in shop.php
 * @param   string  $col_name   Column name to check
 * @return  boolean     True if the column exists, False if not
 */
function _DON_tableHasColumn($table, $col_name)
{
    global $_TABLES;

    $col_name = DB_escapeString($col_name);
    $res = DB_query("SHOW COLUMNS FROM {$_TABLES[$table]} LIKE '$col_name'");
    return DB_numRows($res) == 0 ? false : true;
}

?>
