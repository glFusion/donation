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
use Donation\Config;

// If plugin is installed but not enabled, display an error and exit gracefully
if (!in_array('donation', $_PLUGINS)) {
    COM_404();
    exit;
}

COM_setArgNames(array('mode', 'id', 'page', 'query'));
$Request = Donation\Models\Request::getInstance();
$mode = COM_applyFilter($Request->getString('mode', COM_getArgument('mode')));
$id = COM_sanitizeID($Request->getString('id', COM_getArgument('id')));
$query = $Request->getString('query', COM_getArgument('query'));
$page = $Request->getInt('page', (int)COM_getArgument('page'));

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
            $T = new \Template(Config::get('path') . '/templates');
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
            if ($C->getBlkShowPct()) {
                if ($C->getGoal() == 0) {
                    $pct_rcvd = '';
                } elseif ($C->getReceived() < $C->getGoal()) {
                    $pct_rcvd = sprintf(
                        $LANG_DON['pct_received'],
                        $C->getReceived(),
                        $C->getGoal()
                    );
                } else {
                    $pct_rcvd = '';
                }
                $T->set_var('pct_rcvd', $pct_rcvd);
            }
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
$msg = $Request->getInt('msg');
if ($msg > 0) {
    echo  COM_showMessage($msg, Donation\Config::PI_NAME);
}
echo $content;
echo COM_siteFooter(true);


/**
 * Display a list of campaigns that are accepting donations.
 *
 * @return  string      HTML for campaign list
 */
function DONATION_CampaignList()
{
    global $_TABLES, $LANG_DON, $_CONF;

    $Campaigns = Donation\Campaign::getAllActive();
    if (count($Campaigns) < 1) {
        return '<span class="info">'.$LANG_DON['no_open_campaigns'].'</span>';
    }

    $T = new Template(Config::get('path') . '/templates');
    $T->set_file('camplist', 'campaign_list.thtml');
    $T->set_block('camplist', 'CampaignBlk', 'CBlk');
    foreach ($Campaigns as $C) {
        // Skip campaigns that have reached their hard goal cutoff
        $received = $C->getReceived();
        $goal = $C->getGoal();
        if ($C->isHardGoal() && $received >= $goal) {
            // Goal reached, do not display
            continue;
        }

        $have_pct_recvd = true;
        if ($goal == 0 || !$C->getBlkShowPct()) {
            $have_pct_recvd = false;
            $pct_recvd = 100;
        } elseif ($received < $goal) {
            $pct_recvd = ($received / $goal) * 100;
        } else {
            $pct_recvd = 100;
        }

        $status = PLG_callFunctionForOnePlugin(
            'service_formatAmount_shop',
            array(
                1 => array(
                    'amount' => $goal,
                ),
                2 => &$output,
                3 => &$svc_msg,
            )
        );
        if ($status == PLG_RET_OK) {
            $goal = $output;
        }
        $received_txt = sprintf($LANG_DON['amt_received'], $received, $goal, $pct_recvd);
        $startdt  = new Date($C->getStart(), $_CONF['timezone']);
        $enddt    = new Date($C->getEnd(), $_CONF['timezone']);
        $T->set_var(array(
            'camp_id'       => $C->getID(),
            'name'          => $C->getName(),
            'startdt'       => $startdt->toMySQL(true),
            'enddt'         => $enddt->toMySQL(true),
            'shortdscp'     => $C->getShortDscp(),
            'dscp'          => $C->getDscp(),
            'received_txt'  => $received_txt,
            'have_pct_received' => $have_pct_recvd,
            'donate_btn'    => $C->getButton(),
            'pi_url'        => Config::get('url'),
        ) );
        $T->parse('CBlk', 'CampaignBlk', true);
    }
    $T->parse('output','camplist');
    return $T->finish($T->get_var('output'));
}
