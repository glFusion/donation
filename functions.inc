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
 * Returns the items for this plugin that should appear on the main menu.
 *
 * NOTE: this MUST return the url/value pairs in the following format
 *      $<arrayname>[<label>] = <url>
 *
 * @return  array   Associative array of menu items to be added to menu
 */
function plugin_getmenuitems_donation()
{
    global $LANG_DON, $_CONF_DON;

    if ($_CONF_DON['show_in_menu']) {
        return array(
            $LANG_DON['donations'] => DON_URL . '/index.php',
        );
    } else {
        return NULL;
    }
}


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

    $Campaigns = Donation\Campaign::getAllActive();
    $nrows = count($Campaigns);
    if ($nrows > 0) {
        USES_lib_admin();
        $header_arr = array(
            array(
                'text' => $LANG_DON['camp_name'],
                'field' => 'name',
                'header_class' => 'stats-header-title',
            ),
            array(
                'text' => $LANG_DON['goal'],
                'field' => 'goal',
                'header_class' => 'stats-header-count',
                'field_class' => 'stats-list-count',
            ),
            array(
                'text' => $LANG_DON['received'],
                'field' => 'received',
                'header_class' => 'stats-header-count',
                'field_class' => 'stats-list-count',
            ),
        );
        $data_arr = array();
        $text_arr = array(
            'has_menu'     => false,
            'title'        => $LANG_DON['camp_mgr'],
        );

        foreach ($Campaigns as $C) {
            $data_arr[] = array(
                'name' => COM_createLink(
                    htmlspecialchars($C->getName()),
                    COM_buildUrl(
                        DON_URL . '/index.php?mode=detail&id=' . $C->getID()
                    )
                ),
                'received' => COM_NumberFormat($C->getReceived(), 2),
                'goal' => COM_NumberFormat($C->getGoal(), 2),
            );
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

    $Campaigns = Donation\Campaign::getAllActive();
    if (count($Campaigns) < 1) {
        return '';
    }

    $T = new \Template(DON_PI_PATH . '/templates');
    $i = 0;
    $limit = (int)$_CONF_DON['num_in_blk'];
    foreach ($Campaigns as $C) {
        $T->set_file('block', 'blk_contrib.thtml');
        $T->set_var(array(
            'title'     => $C->getName(),
            'description' => $C->getShortDscp(),
            'camp_id'   => $C->getID(),
            'pi_url'    => DON_URL,
        ) );
        if ($C->getGoal() > 0 && $C->getBlkShowPct()) {
            if ($C->getReceived() < $C->getGoal()) {
                $pct_received = (int)(((float)$C->getReceived() / (float)$C->getGoal()) * 100);
            } else {
                $pct_received = 100;
            }
            $T->set_var(array(
                'goal'  => sprintf('%6.2f', (float)$C->getGoal()),
                'pct'   => $pct_received,
                'collected' => sprintf("%.2f / %.2f", $C->getReceived(), $C->getGoal()),
                'pct_received' => sprintf($LANG_DON['pct_received'], $C->getReceived(), $C->getGoal()),
            ) );
        } else {
            $T->clear_var('goal');
        }

        // Do not include the amount to have the donor enter it.
        $vars = array(
                'item_number' => 'donation:' . $C->getID(),
                'item_name' => $C->getName(),
                'quantity' => 1,
                'return' => DON_URL . '/index.php',
        );
        $T->set_var('donate_btn', $C->getButton());

        if ($i > 0) {
            $T->set_var('hr', '<hr />');
        }
        $i++;
        $T->parse('output','block');
        $retval .= $T->finish($T->get_var('output'));

        if ($limit > 0 && $i > $limit) {
            break;
        }
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

    $Campaigns = Donation\Campaign::getByUser($uid);
    if (count($Campaigns) > 0) {
        $LT = new \Template(__DIR__ . '/templates/');
        $LT->set_file(array(
            'block' => 'profileblock.thtml',
        ));
        $LT->set_block('block', 'donation_row', 'drow');

        foreach ($Campaigns as $C) {
            $LT->set_var(array(
                'name'      => $C->getName(),
                'amount'    => COM_numberFormat($C->getReceived(), 2),
            ) );
            $LT->parse('drow', 'donation_row', true);
        }
        $LT->parse('output', 'block');
        $existingFields = $T->get_var('plugin_fields');
        $T->set_var(
            'plugin_fields',
            $existingFields . $T->finish($LT->get_var('output'))
        );
    } else {
        return;
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
    $query = DB_escapeString(trim($query));
    $sql = Donation\Campaign::getSearchSql($query);
    $search = new \SearchCriteria($_CONF_DON['pi_name'], $LANG_DON['donations']);
    $columns = array(
        'name',
        'shortdscp',
        'dscp',
    );
    list($sql, $ftsql) = $search->buildSearchSQL($keyType, $query, $columns, $sql);
    $search->setSQL($sql);
    return $search;
}


/**
 * Provides the getItemInfo function for campaigns.
 *
 * @param   string  $id         Product ID
 * @param   string  $what       Comma-separated list of fields to return
 * @param   integer $uid        User ID
 * @param   array   $options    Not used
 * @return  array           Array of product information
 */
function plugin_getiteminfo_donation($id, $what, $uid=0, $options=array())
{
    global $_TABLES, $_CONF, $LANG_ADVT;

    $properties = explode(',', $what);
    $fields = array();
    $retval = array();
    $buildingSearchIndex = false;
    foreach ($properties as $p) {
        switch ($p) {
        case 'search_index':
            $buildingSearchIndex = true;
            break;
        case 'id':
            $fields[] = 'camp_id';
            break;
        case 'title':
            $fields[] = 'name';
            break;
        case 'content':
        case 'description':
            $fields[] = 'dscp';
            break;
        case 'searchidx':
            $fields[] = 'name';
            $fields[] = 'shortdscp';
            $fields[] = 'dscp';
            break;
        case 'date':
            $fields[] = 'start_ts';
            break;
        }
    }
    if (count($fields) == 0) {
        return $retval;
    }
    $fields = array_unique($fields);
    $fields = implode(',', $fields);
    $retval = array();
    $sql = "SELECT $fields
            FROM {$_TABLES['don_campaigns']} ";
    if ($id == '*') {
        if ( $buildingSearchIndex ) {
            $where = ' WHERE exp_date > UNIX_TIMESTAMP() ';
        } else {
            $where = ' WHERE 1=1 ';
        }
    } else {
        $where = " WHERE (camp_id = '" . DB_escapeString($id) . "')";
    }
    //echo $sql;die;
    $res = DB_query($sql . $where);
    while ($A = DB_fetchArray($res, false)) {
        foreach ($properties as $p) {
            switch ($p) {
            case 'id':
                $props[$p] = $A['camp_id'];
                break;
            case 'excerpt':
            case 'description':
            case 'content':
                $props[$p] = $A['dscp'];
                break;
            case 'title':
                $props[$p] = $A['name'];
                break;
            case 'date':
                $props[$p] = $A['start_ts'];
                break;
            case 'searchidx':
                $props[$p] = $A['name'] . ' ' . $A['shortdscp'] . ' ' .
                        $A['dscp'];
                break;
            case 'url':
                $props[$p] = COM_buildUrl($_CONF['site_url'] .
                    '/donation/index.php?mode=detail&id=' . urlencode($A['camp_id'])
                );
                break;
            default:
                $props[$p] = isset($A[$p]) ? $A[$p] : NULL;
                break;
            }
        }
        $mapped = array();
        foreach ($props as $key => $value) {
            if ($id == '*') {
                if ($value != '') {
                    $mapped[$key] = $value;
                }
            } else {
                $mapped[$key] = $value;
            }
        }

        if ($id == '*') {
            $retval[] = $mapped;
        } else {
            $retval = $mapped;
            break;
        }
    }

    if (($id != '*') && (count($retval) == 1)) {
        $tRet = array_values($retval);
        $retval = $tRet[0];
    }
    if ($retval === '' || (is_array($retval) && count($retval) == 0)) {
        return NULL;
    } else {
        return $retval;
    }
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
