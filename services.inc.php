<?php
/**
 * Shop integration functions for the Donation plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2017 Lee Garner <lee@leegarner.com>
 * @package     donation
 * @version     v0.0.2
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */


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
    global $_TABLES, $LANG_PHOTO, $LANG_DON, $_CONF_DON;

    // $args should be an array of item info
    if (
        !is_array($args) ||
        !isset($args['item_id']) ||
        !is_array($args['item_id'])
    ) {
        return PLG_RET_ERROR;
    }

    $camp_id = $args['item_id'][0];

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
        'cancel_url' => DON_URL . '/index.php',
        'add_cart' => false,    // cannot use the Shop cart
        'url' => DON_URL . '/index.php?mode=detail&id=' . $camp_id,
    );
    $C = Donation\Campaign::getInstance($camp_id);
    if (!$C->isNew()) {
        $dscp = $LANG_DON['donation'] . ': ' . $C->getDscp();
        $output['name'] = $LANG_DON['donation'] . ': ' . $C->getName();
        $output['short_description'] = $output['name'];
        $output['description'] = $dscp;
        $output['override_price'] = 1;
        $output['btn_text'] = $LANG_DON['donate'];
        if ($_CONF_DON['pp_use_donation']) {
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
    global $_TABLES, $_USER, $_CONF_DON;

    // Initialize the return value as empty.
    $products = '';

    $_CONF_DON['show_in_pp_cat'] = 1;
    // If we're not configured to show campaigns in the Shop catalog,
    // just return
    if ($_CONF_DON['show_in_pp_cat'] != 1) {
        return $products;
    }

    $sql = "SELECT c.*, SUM(d.amount) as received
            FROM {$_TABLES['don_campaigns']} c
            LEFT JOIN {$_TABLES['don_donations']} d
            ON c.camp_id = d.camp_id
            WHERE c.enabled = 1
            AND c.end_ts > UNIX_TIMESTAMP()
            AND c.start_ts < UNIX_TIMESTAMP()";
    $result = DB_query($sql);
    if (!$result) {
        return PLG_RET_ERROR;
    }

    $output = array();
    while ($A = DB_fetchArray($result)) {
        $P = Donation\Campaign::getInstance($A);
        if (!$P->isActive()) {
            continue;
        }
        $output[] = array(
            'id' => 'donation:' . $P->getCampaignID(),
            'item_id' => $P->getID(),
            'name' => $P->getName(),
            'short_description' => $P->getShortDscp(),
            'description' => $P->getDscp(),
            'price' => $P->getAmount(),
            'buttons' => array('donation' => $P->GetButton()),
            'url' => DON_URL . '/index.php?mode=detail&amp;id=' .
                        urlencode($A['camp_id']),
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
    global $_CONF, $_CONF_DON, $_TABLES, $LANG_DON;

    $item = $args['item'];
    $ipn_data = $args['ipn_data'];
    $item_id = explode(':', $item['item_id']);

    // Must have an item ID following the plugin name
    if (!is_array($item_id) || !isset($item_id[1])) {
        return PLG_RET_ERROR;
    }

    $item_id[1] = COM_sanitizeID($item_id[1], false);
    $sql = "SELECT * FROM {$_TABLES['don_campaigns']}
            WHERE camp_id='{$item_id[1]}'";
    $res = DB_query($sql, 1);
    $A = DB_fetchArray($res, false);
    if (empty($A)) {
        $A = array(
            'camp_id'   => '',
            'name'      => 'Miscellaneous',
            'description' => '',
            'price'     => 0,
        );
    }

    // Donations typically have no fixed price, so take the
    // payment amount sent by Shop
    $amount = (float)$ipn_data['payment_gross'];

    // Initialize the return array
    $output = array(
        'product_id' => implode(':', $item),
        'name' => $LANG_DON['donation'] . ':' . $A['name'],
        'short_description' => $LANG_DON['donation'] . ': ' . $A['name'],
        'description' => $LANG_DON['donation'] . ': ' . $A['shortdscp'],
        'price' =>  $amount,
        'expiration' => NULL,
        'download' => 0,
        'file' => '',
    );

    // User ID is returned in the 'custom' field, so make sure it's numeric.
    // If not, try to get it from the payer's email address. This will yield
    // zero if not found.
    if (is_numeric($ipn_data['custom']['uid'])) {
        $uid = (int)$ipn_data['custom']['uid'];
    } else {
        $uid = (int)DB_getItem(
            $_TABLES['users'],
            'uid',
            "email = '{$ipn_data['payer_email']}'"
        );
        if ($uid < 1) $uid = 1;     // set to anonymous if not found
    }

    $memo = isset($ipn_data['memo']) ? $ipn_data['memo'] : '';
    $memo = DB_escapeString($memo);
    if (isset($ipn_data['payer_name'])) {
        $pp_contrib = $ipn_data['payer_name'];
    } elseif (isset($ipn_data['first_name']) && isset($ipn_data['last_name']) ) {
        $pp_contrib = $ipn_data['first_name'] . ' ' . $ipn_data['last_name'];
    } else {
        $pp_contrib = 'Unknown';
    }
    $sql = "INSERT INTO {$_TABLES['don_donations']} (
                uid, contrib_name, dt, camp_id, amount, txn_id, comment
            ) VALUES (
                '$uid',
                '" . DB_escapeString($pp_contrib) . "',
                '{$_CONF['_now']->toMySQL(true)}',
                '{$item_id[1]}',
                '{$amount}',
                '" . DB_escapeString($ipn_data['txn_id']) . "',
                '$memo'
            )";
    DB_query($sql, 1);     // Execute event record update

    $sql = "UPDATE {$_TABLES['don_campaigns']}
            SET amount = amount + $amount
            WHERE camp_id = '{$item_id[1]}'";
    DB_query($sql, 1);
    return PLG_RET_OK;
}

?>
