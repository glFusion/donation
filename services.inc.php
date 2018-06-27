<?php
/**
*   Paypal integration functions for the Donation plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2017 Lee Garner <lee@leegarner.com>
*   @package    donation
*   @version    0.0.2
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/


/**
*   Get information about a specific item.
*
*   @param  array   $args       Item Info (pi_name, item_type, item_id)
*   @param  array   &$output    Array of output data
*   @param  string  &$svc_msg   Unused
*   @return integer     Return value
*/
function service_productinfo_donation($args, &$output, &$svc_msg)
{
    global $_TABLES, $LANG_PHOTO, $LANG_DON;

    // $args should be an array of item info
    if (!is_array($args)) return PLG_RET_ERROR;

    // Create a return array with values to be populated later.
    // The actual paypal product ID is photocomp:type:id
    $output = array('product_id' => implode(':', $args),
            'name' => 'Unknown',
            'short_description' => 'Unknown Donation Item',
            'price' => '0.00',
    );

    if (isset($args[1]) && !empty($args[1])) {
        $args[1] = COM_sanitizeID($args[1]);
        $info = DB_fetchArray(DB_query(
                "SELECT camp_id, name, description
                FROM {$_TABLES['don_campaigns']}
                WHERE camp_id='{$args[1]}'"), false);
        if (!empty($info)) {
            $descrip = $LANG_DON['donation'] . ': ' . $info['description'];
            $output['short_description'] = $descrip;
            $output['name'] = $LANG_DON['donation'] . ': ' . $info['name'];
            $output['description'] = $descrip;
            $output['override_price'] = 1;
        }
    }

    return PLG_RET_OK;
}


/**
*   Get the products under a given category.
*
*   @param  string  $cat    Name of category (unused)
*   @return array           Array of product info, empty string if none
*/
function service_getproducts_donation($cat='')
{
    global $_TABLES, $_USER, $_CONF_DON;

    // Initialize the return value as empty.
    $products = '';

    $_CONF_DON['show_in_pp_cat'] = 1;
    // If we're not configured to show campaigns in the Paypal catalog,
    // just return
    if ($_CONF_DON['show_in_pp_cat'] != 1) {
        return $products;
    }

    $sql = "SELECT c.camp_id
            FROM {$_TABLES['don_campaigns']} c
            WHERE c.enabled = 1
            AND (c.enddt > '{$_CONF_DON['now']}' OR c.enddt IS NULL)
            AND (c.startdt < '{$_CONF_DON['now']}' OR c.startdt IS NULL)";
    $result = DB_query($sql);
    if (!$result)
        return PLG_RET_ERROR;

    $output = array();
    $P = new DonationCampaign();
    while ($A = DB_fetchArray($result)) {
        $P->Read($A['camp_id']);
        $output[] = array(
            'id' => 'donation:' . $P->camp_id,
            'name' => $P->name,
            'short_description' => $P->description,
            'price' => 0,
            'buttons' => array('donation' => $P->GetButton()),
            'url' => DON_URL . '/index.php?mode=detail&amp;id=' .
                        urlencode($A['camp_id']),
        );
    }
    return PLG_RET_OK;
}


/**
*   Handle the purchase of a product via IPN message.
*
*   @param  array   $args       Item Info (pi_name, item_type, item_id)
*   @param  array   &$output    Array of output data
*   @param  string  &$svc_msg   Unused
*   @return integer     Return value
*/
function service_handlePurchase_donation($args, &$output, &$svc_msg)
{
    global $_CONF, $_CONF_DON, $_TABLES, $LANG_DON;

    $item = $args['item'];
    $paypal_data = $args['ipn_data'];
    $item_id = explode(':', $item['item_id']);

    // Must have an item ID following the plugin name
    if (!is_array($item_id) || !isset($item_id[1]))
        return PLG_RET_ERROR;

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
    // payment amount sent by Paypal
    $amount = (float)$paypal_data['pmt_gross'];

    // Initialize the return array
    $output = array('product_id' => implode(':', $item),
            'name' => $LANG_DON['donation'] . ':' . $A['name'],
            'short_description' => $LANG_DON['donation'] . ': ' . $A['name'],
            'description' => $LANG_DON['donation'] . ': ' . $A['shortdesc'],
            'price' =>  $amount,
            'expiration' => NULL,
            'download' => 0,
            'file' => '',
    );

    // User ID is returned in the 'custom' field, so make sure it's numeric.
    // If not, try to get it from the payer's email address. This will yield
    // zero if not found.
    if (is_numeric($paypal_data['custom']['uid'])) {
        $uid = (int)$paypal_data['custom']['uid'];
    } else {
        $uid = (int)DB_getItem($_TABLES['users'], 'email', $paypal_data['payer_email']);
        if ($uid < 1) $uid = 1;     // set to anonymous if not found
    }

    $memo = isset($paypal_data['memo']) ? $paypal_data['memo'] : '';
    $memo = DB_escapeString($memo);
    if (isset($paypal_data['payer_name'])) {
        $pp_contrib = $paypal_data['payer_name'];
    } elseif (isset($paypal_data['first_name']) &&
                isset($paypal_data['last_name']) ) {
        $pp_contrib = $paypal_data['first_name'] . ' ' .
            $paypal_data['last_name'];
    } else {
        $pp_contrib = 'Unknown';
    }

    $sql = "INSERT INTO {$_TABLES['don_donations']} (
                uid, contrib_name, dt, camp_id, amount, txn_id, comment
            ) VALUES (
                '$uid',
                '" . DB_escapeString($pp_contrib) . "',
                '{$_CONF_DON['now']}',
                '{$item_id[1]}',
                '{$amount}',
                '" . DB_escapeString($paypal_data['txn_id']) . "',
                '$memo'
            )";
    DB_query($sql, 1);     // Execute event record update

    $sql = "UPDATE {$_TABLES['don_campaigns']}
            SET received=received + $amount
            WHERE camp_id = '{$item_id[1]}'";
    DB_query($sql, 1);
    return PLG_RET_OK;
}

?>
