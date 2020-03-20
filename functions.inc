<?php
/**
 * Common functions for the Donation plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2018 Lee Garner <lee@leegarner.com>
 * @package     donation
 * @version     v0.0.2
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

if (!defined('GVERSION')) {
    die('This file can not be used on its own.');
}

/** @global array $_CONF_DON */
global $_CONF_DON;
$_CONF_DON = config::get_instance()->get_config('donation');

/** Additional config items and table definitions are added here */
require_once __DIR__ . '/donation.php';
if (file_exists(__DIR__ . '/config.php')) {
    /** Include local configuration overrides */
    include_once __DIR__ . '/config.php';
}

define('DON_PI_PATH', __DIR__);
define('DON_ADMIN_URL',
    $_CONF['site_url'].'/admin/plugins/'.$_CONF_DON['pi_name']);
define('DON_URL',  $_CONF['site_url'].'/'.$_CONF_DON['pi_name']);
define('DON_CHECKED', 'checked="checked"');
define('DON_SELECTED', 'selected="selected"');

$_CONF_DON['now'] = new \Date('now', $_CONF['timezone']);

// Include inter-plugin services
require_once __DIR__ . '/services.inc.php';

/**
 * Language file Include
 */
$langpath = __DIR__  . '/language';
$langfile = "{$_CONF['language']}.php";
if (!file_exists("$langpath/$langfile")) {
    $langfile = 'english.php';
}
require_once "$langpath/$langfile";

if (is_file("$langpath/custom/$langfile")) {
    /** Include the custom language file, if any */
    include_once "$langpath/custom/$langfile";
}

spl_autoload_register(function ($class)
{
    if (strpos($class, 'Donation\\') === 0) {
        $class = str_replace('Donation\\', '', $class);
        $path = __DIR__ . '/classes/' . $class . '.class.php';
        if (file_exists($path)) {
            include $path;
        }
    }
});


/**
 * Get additional Javascript into the site header.
 *
 * @return  array   List of paths to Javascript files
 */
function plugin_getheaderjs_donation()
{
    $js_dir = DON_PI_PATH . '/js';
    $files = array(
        "$js_dir/toggleEnabled.js",
    );
    return $files;
}


/**
 * Shows the statistics for the plugin on stats.php.
 * If $showsitestats is 1 then we are to only print the overall stats in
 * the 'site statistics' box otherwise we show the detailed stats for the
 * plugin.
 *
 * @param   integer $showsitestats  Flag to let us know which stats to get
 * @return  string                  HTML to insert in stats page
 */
function plugin_showstats_donation($showsitestats)
{
    global $_CONF, $_USER, $_TABLES, $LANG_DON, $_CONF_DON;

    $retval = '';

    $sql = "SELECT c.camp_id, c.name, SUM(d.amount) as received
            FROM `{$_TABLES['don_campaigns']}` c
            LEFT JOIN `{$_TABLES['don_donations']}` d
            ON d.camp_id=c.camp_id
            WHERE c.enabled = 1
                AND c.start_ts < UNIX_TIMESTAMP()
                AND c.end_ts > UNIX_TIMESTAMP()
                AND (c.hardgoal = 0 OR received < c.goal)
            GROUP BY c.camp_id";
    //COM_errorLog("Sending $sql to DB_query");
    $result = DB_query($sql);
    //COM_errorLog("Query Sent");
    $nrows  = DB_numRows ($result);
    if ($nrows > 0) {
        USES_lib_admin();
        $header_arr = array(
            array('text' => $LANG_DON['camp_name'],
                    'field' => 'name',
                    'header_class' => 'stats-header-title'),
            array('text' => $LANG_DON['received'],
                    'field' => 'received',
                    'header_class' => 'stats-header-count',
                    'field_class' => 'stats-list-count'),
        );
        $data_arr = array();
        $text_arr = array('has_menu'     => false,
                          'title'        => $LANG_DON['camp_mgr'],
        );
        while ($A = DB_fetchArray($result, false)) {
            $A['name'] = "<a href=\"" . COM_buildUrl(DON_URL .
                    "/index.php?mode=detail&amp;id={$A['camp_id']}"). '">' .
                    htmlspecialchars($A['name']) . '</a>';
            $A['received'] = COM_NumberFormat($A['received']);
            $data_arr[] = $A;
        }
        $retval .= ADMIN_simpleList("", $header_arr, $text_arr, $data_arr);
    }
    return $retval;
}


/**
 * This will put an option for the plugin in the command and control
 * block on moderation.php.
 *
 * @return  array   Array containing (pi_name, pi_admin_url, pi_icon_url)
 */
function plugin_cclabel_donation()
{
    global $_CONF, $_CONF_DON;

    if (SEC_hasRights('donation.admin')) {
        return array(
            $_CONF_DON['pi_display_name'],
            DON_ADMIN_URL . '/index.php',
            DON_URL . '/images/donation.png'
        );
    } else {
        return '';
    }
}


/**
 * Returns the administrative menu option for this plugin.
 *
 * @return  mixed   Array (pi_name, pi_admin_url, # of items) or ''
 */
function plugin_getadminoption_donation()
{
    global $_CONF, $_TABLES, $LANG_DON, $_CONF_DON;

    if (SEC_hasRights('donation.admin')) {
        return array(
            $_CONF_DON['pi_display_name'],
            DON_ADMIN_URL . '/index.php',
        );
    } else {
        return '';
    }
}


/**
 * Return the current version of code.
 *
 * @return  string      Current plugin version
 */
function plugin_chkVersion_donation()
{
    global $_CONF_DON;

    return $_CONF_DON['pi_version'];
}


/**
 * Automatically uninstall the plugin.
 *
 * @return  array   Array of plugin elements to remove
 */
function plugin_autouninstall_donation()
{
    global $_CONF_DON;

    $out = array (
        'tables'    => array('don_campaigns', 'don_donations'),
        'groups'    => array($_CONF_DON['pi_name'] . ' Admin'),
        'features'  => array($_CONF_DON['pi_name'] . '.admin',
                        ),
        'php_blocks' => array('phpblock_donation_donations'),
        'vars'      => array($_CONF_DON['pi_name'] . '_gid')
    );
    return $out;
}


/**
 * Returns the URL of the plugin's icon.
 *
 * @return  string  URL of the icon
 */
function plugin_geticon_donation()
{
    global $_CONF;

    return $_CONF['site_url'] . '/donation/images/donation.png';
}


/**
 * Disable this plugin's blocks when the plugin is changed.
 * If the plugin is being enabled, there's no way to know if the blocks
 * were disabled on purpose, so none of them will be re-enabled.
 *
 * @param   boolean $enabled    Plugin's "enabled" state
 */
function plugin_enablestatechange_donation($enabled)
{
    global $_TABLES, $_PLUGINS;

    $enabled = $enabled ? 1 : 0;

    // If trying to enable the plugin but lgLib is not available,
    // take no action and return
    if ($enabled && !in_array('lglib', $_PLUGINS)) {
        // Re-disable the plugin
        DB_change($_TABLES['plugins'], 'pi_enabled', 0, 'pi_name', 'donation');
        $enabled = 0;
    }

    if (!$enabled) {
        $blockfns = array('phpblock_donation_');
        foreach ($blockfns as $blockfn) {
            DB_query("UPDATE {$_TABLES['blocks']}
                SET is_enabled=$enabled
                WHERE phpblockfn LIKE '{$blockfn}%'");
        }
    }
}


/**
 * Receives notification of anothe plugin's deletion.
 *
 * @param   mixed   $id     Item ID
 * @param   string  $type   Plugin Name
 */
function plugin_itemdeleted_donation($id, $type)
{
    global $_TABLES;

    // If the lgLib plugin is disabled or removed, disable thie plugin
    if ($type == 'lglib' && $id == '*') {
        plugin_enablestatechange_donation(false);
        DB_change($_TABLES['plugins'], 'pi_enabled', 0, 'pi_name', 'donation');
    }
}


/**
 * Get the URL to the configuration help file.
 *
 * @param   string  $option     Config option to show
 * @param   string  $doclang    Document language
 * @return  string              URL to document
 */
function plugin_getconfigelementhelp_donation($option, $doclang='english')
{
    global $_CONF, $_CONF_DON;

    $doc_url = LGLIB_getDocURL('config.html', $_CONF_DON['pi_name'], $doclang);
    if (empty($doc_url))
        return false;

    if (!empty($option)) {
        $doc_url .= '#desc_' . $option;
    }
    return array($doc_url, 0);
}


/**
 * Upgrade the plugin.
 *
 * @return  boolean True on success, code for error message otherwise
 */
function plugin_upgrade_donation()
{
    require_once __DIR__ . '/upgrade.inc.php';
    return donation_do_upgrade();
}


/**
 * Display information on one or more current campaigns.
 * Includes a "Donate" button if the Shop plugin is available.
 * Includes the percentage of the goal reached if the campaign is so
 * configured.
 *
 * @return  string      HTML for donation block.
 */
function phpblock_donation_donations()
{
    global $_TABLES, $_CONF, $LANG_DON, $_CONF_DON;

    $retval = '';

    $sql = "SELECT c.*, SUM(d.amount) as received
            FROM {$_TABLES['don_campaigns']} c
            LEFT JOIN {$_TABLES['don_donations']} d
                ON c.camp_id=d.camp_id
            WHERE c.enabled = 1
                AND c.end_ts > UNIX_TIMESTAMP()
                AND (c.goal = 0 OR c.hardgoal = 0 OR c.amount < goal)
            GROUP BY c.camp_id";
    if ($_CONF_DON['num_in_blk'] > 0) {
        $sql .= ' LIMIT ' . (int)$_CONF_DON['num_in_blk'];
    }
    //echo $sql;die;
    $r = DB_query($sql, 1);
    $n = DB_numRows($r);
    if ($n < 1) {
        return '';
    }

    $T = new \Template(DON_PI_PATH . '/templates');

    $i = 0;
    while ($row = DB_fetchArray($r, false)) {

        $T->set_file('block', 'blk_contrib.thtml');
        $T->set_var(array(
            'title'     => $row['name'],
            'description' => $row['shortdesc'],
            'camp_id'   => $row['camp_id'],
            'pi_url'    => DON_URL,
        ) );
        if ($row['goal'] > 0 && $row['blk_show_pct'] == 1) {
            if ($row['received'] < $row['goal']) {
                $pct_received = (int)((float)$row['received'] / (float)$row['goal'] * 100);
            } else {
                $pct_received = 100;
            }
            $T->set_var(array(
                'goal'  => sprintf('%6.2f', (float)$row['goal']),
                'pct'   => $pct_received,
                'collected' => sprintf("%.2f / %.2f", $row['received'], $row['goal']),
                'pct_received' => sprintf($LANG_DON['pct_received'], $row['received'], $row['goal']),
            ) );
        } else {
            $T->clear_var('goal');
        }

        // Do not include the amount to have the donor enter it.
        $vars = array(
                'item_number' => 'donation:' . $row['camp_id'],
                'item_name' => $row['name'],
                'quantity' => 1,
                'return' => DON_URL . '/index.php',
        );

        $C = Donation\Campaign::getInstance($row);
        $T->set_var('donate_btn', $C->getButton());

        if ($i > 0)
            $T->set_var('hr', '<hr />');
        $i++;

        $T->parse('output','block');
        $retval .= $T->finish($T->get_var('output'));
    }
    return $retval;
}


/**
 * Displays donations received in the user's profile.
 *
 * @param   integer  $uid    User ID whose profile is being displayed
 * @param   object   &$T     Pointer to template object
 * @return  string          HTML to include in profile page
 */
function plugin_profilevariablesdisplay_donation($uid, &$T)
{
    global $_CONF_DON, $LANG_DON, $_TABLES, $_CONF;

    $sql = "SELECT sum(d.amount) as total, c.name
            FROM {$_TABLES['don_donations']} d
            LEFT JOIN {$_TABLES['don_campaigns']} c
            ON d.camp_id = c.camp_id
            WHERE d.uid = $uid
            GROUP BY d.camp_id";
    $res = DB_query($sql);
    if (DB_numRows($res) > 0) {
        $LT = new \Template(__DIR__ . '/templates/');
        $LT->set_file(array(
            'block' => 'profileblock.thtml',
        ));
        $LT->set_block('block', 'donation_row', 'drow');

        while ($A = DB_fetchArray($res, false)) {
            $LT->set_var(array(
                'name'      => $A['name'],
                'amount'    => $A['total'],
            ) );
            $LT->parse('drow', 'donation_row', true);
        }

        $LT->parse('output', 'block');
        $existingFields = $T->get_var('plugin_fields');
        $T->set_var('plugin_fields',
                $existingFields . $T->finish($LT->get_var('output')));
    } else {
        return '';
    }
}


/**
 * Create a plugin search object to search this plugin.
 *
 * @param   string  $query      User-supplied query string
 * @param   string  $datestart  Starting date for search
 * @param   string  $dateend    Ending date for search
 * @param   string  $topic      Topic selected for search
 * @param   string  $keyType    Type selected during search
 * @param   string  $author     Limit results to this author (not used)
 * @return  object              Search object to be processed by Search class
 */
function plugin_dopluginsearch_donation(
    $query, $datestart, $dateend, $topic, $keyType, $author
)
{
    global $_CONF, $_TABLES, $_CONF_DON, $LANG_DON;

    if (empty($keyType)) {
        $type = 'all';
    }

    // Encode query for inclusion in result's url
    $htmlquery = urlencode($query);

    $query = DB_escapeString(trim($query));

    $sql = "SELECT
                c.camp_id, c.name as title, c.description as description,
                c.start_ts as date,
                CONCAT('/{$_CONF_DON['pi_name']}/index.php?mode=detail&id=',c.camp_id,'&query=$htmlquery') as url
            FROM {$_TABLES['don_campaigns']} c
            WHERE c.enabled = 1
            AND c.start_ts < UNIX_TIMESTAMP()
            AND c.end_ts > UNIX_TIMESTAMP()
            ";

    $search = new \SearchCriteria($_CONF_DON['pi_name'], $LANG_DON['donations']);
    $columns = array('c.name', 'c.description',
                    );
    list($sql, $ftsql) = $search->buildSearchSQL($keyType, $query, $columns, $sql);
    $search->setSQL($sql);
    return $search;
}


/**
 * Support merging local accounts into remote accounts.
 *
 * @param   integer $origUID    Original (remote) user ID
 * @param   integer $destUID    Merged (local) user ID
 */
function plugin_user_move_donation($origUID, $destUID)
{
    global $_TABLES;

    $origUID = (int)$origUID;
    $destUID = (int)$destUID;

    DB_query("UPDATE {$_TABLES['donation_donations']}
            SET uid = $destUID WHERE uid = $origUID", 1);
}


/**
 * Determine if the shop plugin is installed and integration is enabled.
 *
 * @return  boolean     True if Shop is available, False if not.
 */
function DON_shop_enabled()
{
    static $enabled = NULL;
    if ($enabled === NULL) {
        $enabled = function_exists('plugin_chkVersion_shop') ||
            function_exists('plugin_chkVersion_shop');
    }
    return $enabled;
}

?>
