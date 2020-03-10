<?php
/**
 * Public entry point for the Donation plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2019 Lee Garner <lee@leegarner.com>
 * @package     donation
 * @version     v0.0.1
 * @license     http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 * @filesource
 */

/** Import core glFusion libraries */
require_once '../lib-common.php';

// If plugin is installed but not enabled, display an error and exit gracefully
if (!in_array('donation', $_PLUGINS)) {
    COM_404();
    exit;
}

// Retrieve and sanitize input variables.  Typically _GET, but may be _POSTed.
COM_setArgNames(array('mode', 'id', 'page', 'query'));

// Get any message ID
if (isset($_REQUEST['msg'])) {
    $msg = COM_applyFilter($_REQUEST['msg']);
} else {
    $msg = '';
}

if (isset($_REQUEST['mode'])) {
    $mode = COM_applyFilter($_REQUEST['mode']);
} else {
    $mode = COM_getArgument('mode');
}
if (isset($_REQUEST['id'])) {
    $id = COM_sanitizeID($_REQUEST['id']);
} else {
    $id = COM_applyFilter(COM_getArgument('id'));
}
if (isset($_REQUEST['query'])) {
    $query = $_REQUEST['query'];
} else {
    $query = COM_getArgument('query');
}
$page = COM_getArgument('page');

// Assume that the 'mode' is also (or only) the desired page to display
if (empty($mode)) $id='';
if (empty($page)) $page = $mode;

$content = '';
$pageTitle = $LANG_DON['campaign'];  // Set basic page title

// Start by processing the specified action, if any
switch ($mode) {
}

// After the action is finished, display the requested page
switch ($page) {
case 'detail':
    $result = '<span class="alert">' . $LANG_DON['invalid_id_req'] . '</span>';
    if (!empty($id)) {
        $C = Donation\Campaign::getInstance($id);
        if ($C->isEnabled()) {
            $T = new \Template(DON_PI_PATH . '/templates');
            $T->set_file('page', 'campaign_detail.thtml');
            if (!empty($query)) {
                $name = COM_highlightQuery($C->getName(), $query);
                $descrip = COM_highlightQuery($C->getDscp(), $query);
                $shortdesc = COM_highlightQuery($C->getShortDscp(), $query);
            } else {
                $name = $C->getName();
                $descrip = $C->getDscp();
                $shortdesc = $C->getShortDscp();
            }
            $T->set_var(array(
                'camp_name'         => $name,
                'camp_shortdesc'    => $shortdesc,
                'camp_description'  => $descrip,
                'buttons'           => $C->getButton(),
                'start_dt'          => $C->getStart()->toMySQL(true),
                'end_dt'            => $C->getEnd()->toMySQL(true),
            ) );
            $T->parse('output', 'page');
            $pageTitle = $LANG_DON['campaign'] . '::' . $C->getName();
            $result = $T->finish($T->get_var('output'));
        }
    }
    $content .= $result;
    break;

case 'thanks':
    $message = $LANG_DON['thanks'];
    $content .= COM_showMessageText($message, $LANG_DON['thanks_title'], true, 'success');
    $view = 'productlist';
    break;

default:
    $content .= DONATION_CampaignList();
    break;

}   // switch ($page)

echo COM_siteHeader('menu', $pageTitle);
if ($msg != '')
    echo  COM_showMessage($msg, $_CONF_DON['pi_name']);
echo $content;
echo COM_siteFooter(true);


/**
 * Display a list of campaigns that are accepting donations.
 *
 * @return  string      HTML for campaign list
 */
function DONATION_CampaignList()
{
    global $_TABLES, $_CONF_DON, $LANG_DON;

    // Get all open campaigns
    $sql = "SELECT c.*, SUM(d.amount) as received
            FROM {$_TABLES['don_campaigns']} c
            LEFT JOIN {$_TABLES['don_donations']} d
                ON d.camp_id=c.camp_id
            WHERE c.enabled = 1
            AND c.start_ts < UNIX_TIMESTAMP()
            AND c.end_ts > UNIX_TIMESTAMP()
            GROUP BY c.camp_id";
    //echo $sql;die;
    $res = DB_query($sql);
    if (!$res || DB_numRows($res) < 1)
        return '<span class="info">'.$LANG_DON['no_open_campaigns'].'</span>';

    $T = new Template(DON_PI_PATH . '/templates');
    $T->set_file('camplist', 'campaign_list.thtml');
    $T->set_block('camplist', 'CampaignBlk', 'CBlk');
    while ($A = DB_fetchArray($res, false)) {
        $C = Donation\Campaign::getInstance($A);
        // Skip campaigns that have reached their hard goal cutoff
        $received = (float)$A['received'];
        $goal = (float)$A['goal'];
        if ($C->isHardGoal() && $received >= $goal) continue;

        $have_pct_recvd = true;
        if ($goal == 0) {
            $have_pct_recvd = false;
        } elseif ($received < $goal) {
            $pct_recvd = ($received / $goal) * 100;
        } else {
            $pct_recvd = 100;
        }

        $status = LGLIB_invokeService(
            'shop', 'formatAmount',
            $A['received'],
            $received,
            $svc_msg
        );
        if ($status !== PLG_RET_OK) {
            $received = $A['received'];
        }

        $status = LGLIB_invokeService(
            'shop', 'formatAmount',
            $A['goal'],
            $goal,
            $svc_msg
        );
        if ($status !== PLG_RET_OK) {
            $received = $A['goal'];
        }
        $received_txt = sprintf($LANG_DON['amt_received'], $received, $goal, $pct_recvd);
        $startdt  = new \Date($A['startdt'], $_CONF['timezone']);
        $enddt    = new \Date($A['enddt'], $_CONF['timezone']);
        $T->set_var(array(
            'camp_id'       => $A['camp_id'],
            'name'          => $A['name'],
            'startdt'       => $startdt->toMySQL(true),
            'enddt'         => $enddt->toMySQL(true),
            'description'   => $A['description'],
            'received_txt'  => $received_txt,
            'have_pct_received' => $have_pct_recvd,
            'donate_btn'    => $C->getButton(),
            'pi_url'        => DON_URL,
        ) );
        $T->parse('CBlk', 'CampaignBlk', true);
    }
    $T->parse('output','camplist');
    return $T->finish($T->get_var('output'));
}

?>
