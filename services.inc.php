<?php
/**
 * Shop integration functions for the Donation plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2021 Lee Garner <lee@leegarner.com>
 * @package     donation
 * @version     v0.2.0
 * @since       v0.0.2
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
use Donation\Config;

/**
 * Get information about a specific item.
 *
 * @param   array   $args       Item Info (pi_name, item_type, item_id)
 * @param   array   &$output    Array of output data
 * @param   string  &$svc_msg   Unused
 * @return  integer     Return value
 */
function service_productinfo_donation($args, &$output, &$svc_msg)
{
    global $_TABLES, $LANG_PHOTO, $LANG_DON;

    // $args should be an array of item info
    if (
        !is_array($args) ||
        !isset($args['item_id']) ||
        !is_array($args['item_id'])
    ) {
        return PLG_RET_ERROR;
    }

    $camp_id = $args['item_id'][0];
    $C = Donation\Campaign::getInstance($camp_id);

    // Create a return array with values to be populated later.
    // The actual shop product ID is photocomp:type:id
    $output = array(
        'product_id' => 'donation:' . $camp_id,
        'id' => 'donation:' . $camp_id,
        'name' => 'Unknown',
        'short_description' => 'Unknown Donation Item',
        'description'       => '',
        'price' => '0.00',
        'taxable' => 0,
        'have_detail_svc' => false,  // Tell Shop to use it's detail page wrapper
        'fixed_q' => 1,         // Purchase qty fixed at 1
        'isUnique' => true,     // Only on purchase of this item allowed
        'supportsRatings' => false,
        'cancel_url' => Config::get('url') . '/index.php',
        'add_cart' => false,    // cannot use the Shop cart
        'url' => Config::get('url') . '/index.php?mode=detail&id=' . $camp_id,
        'custom_price' => false,
    );
    if (!$C->isNew()) {
        $dscp = $LANG_DON['donation'] . ': ' . $C->getDscp();
        $output['name'] = $LANG_DON['donation'] . ': ' . $C->getName();
        $output['short_description'] = $output['name'];
        $output['description'] = $dscp;
        $output['override_price'] = 1;
        $output['btn_text'] = $LANG_DON['donate'];
        if (Config::get('pp_use_donation')) {
            $output['btn_type'] = 'donation';
        }
        return PLG_RET_OK;
    } else {
        // Invalid campaign ID requested
        return PLG_RET_ERROR;
    }
}


/**
 * Get the products under a given category.
 *
 * @param   string  $cat    Name of category (unused)
 * @return  array           Array of product info, empty string if none
 */
function service_getproducts_donation($args, &$output, &$svc_msg)
{
    global $_TABLES, $_USER;

    // Initialize the return value as empty.
    $output = array();

    // If we're not configured to show campaigns in the Shop catalog,
    // just return
    if (Config::get('show_in_shop_cat') != 1) {
        return PLG_RET_OK;  // nothing to show is a valid return
    }

    $Campaigns = Donation\Campaign::getAllActive();
    foreach ($Campaigns as $P) {
        $output[] = array(
            'id' => 'donation:' . $P->getCampaignID(),
            'item_id' => $P->getID(),
            'name' => $P->getName(),
            'short_description' => $P->getShortDscp(),
            'description' => $P->getDscp(),
            'price' => '0.00',
            'buttons' => array('donation' => $P->GetButton()),
            'url' => Config::get('url') . '/index.php?mode=detail&amp;id=' .
                        urlencode($P->getID()),
            'have_detail_svc' => true,  // Tell Shop to use it's detail page wrapper
            'img_url' => '',
            'add_cart' => false,    // cannot use the Shop cart
        );
    }
    //var_dump($output);die;
    return PLG_RET_OK;
}


/**
 * Handle the purchase of a product via IPN message.
 *
 * @param   array   $args       Item Info (pi_name, item_type, item_id)
 * @param   array   &$output    Array of output data
 * @param   string  &$svc_msg   Unused
 * @return  integer     Return value
 */
function service_handlePurchase_donation($args, &$output, &$svc_msg)
{
    global $_CONF, $_TABLES, $LANG_DON;

    $item = $args['item'];
    $ipn_data = $args['ipn_data'];
    $item_id = explode(':', $item['item_id']);

    // Must have an item ID following the plugin name
    if (!is_array($item_id) || !isset($item_id[1])) {
        return PLG_RET_ERROR;
    }

    $item_id[1] = COM_sanitizeID($item_id[1], false);
    $C = Donation\Campaign::getInstance($item_id[1]);
    if ($C->isNew()) {
        return PLG_RET_ERROR;
    }

    // Donations typically have no fixed price, so take the
    // payment amount sent by Shop
    $amount = (float)$ipn_data['pmt_gross'];

    // Initialize the return array
    $output = array(
        'product_id' => implode(':', $item),
        'name' => $LANG_DON['donation'] . ':' . $C->getName(),
        'short_description' => $LANG_DON['donation'] . ': ' . $C->getName(),
        'description' => $LANG_DON['donation'] . ': ' . $C->getShortDscp(),
        'price' =>  $amount,
        'expiration' => NULL,
        'download' => 0,
        'file' => '',
    );

    // User ID is returned in the 'custom' field, so make sure it's numeric.
    // If not, try to get it from the payer's email address. This will yield
    // zero if not found.
    if (is_numeric($ipn_data['uid'])) {
        $uid = (int)$ipn_data['uid'];
    } else {
        $uid = (int)DB_getItem(
            $_TABLES['users'],
            'uid',
            "email = '{$ipn_data['payer_email']}'"
        );
        if ($uid < 1) $uid = 1;     // set to anonymous if not found
    }

    $memo = isset($ipn_data['memo']) ? $ipn_data['memo'] : '';
    if (isset($ipn_data['payer_name'])) {
        $pp_contrib = $ipn_data['payer_name'];
    } elseif (isset($ipn_data['first_name']) && isset($ipn_data['last_name']) ) {
        $pp_contrib = $ipn_data['first_name'] . ' ' . $ipn_data['last_name'];
    } else {
        $pp_contrib = 'Unknown';
    }
    $D = new Donation\Donation;
    $D->setUid($uid)
        ->setCampaignID($C->getID())
        ->setContributorName($pp_contrib)
        ->setAmount($amount)
        ->setTxnId($ipn_data['txn_id'])
        ->setComment($memo)
        ->Save();
    return PLG_RET_OK;
}

