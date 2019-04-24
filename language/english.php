<?php
/**
*   Default English Language file for the Donation plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2017 Lee Garner <lee@leegarner.com>
*   @package    donation
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

/**
* The plugin's lang array
* @global array $LANG_BANNER
*/
$LANG_DON = array(
'camp_id'       => 'Campaign ID',
'camp_name'     => 'Campaign Name',
'camp_mgr'      => 'Donation Campaigns',
'don_mgr'       => 'Donation Manager',
'enabled'       => 'Enabled',
'disabled'      => 'Disabled',
'ena_or_disa'   => 'Enable or Disable',
'shortdesc'     => 'Short Description',
'description'   => 'Description',
'campaigns'     => 'Campaigns',
'campaign'      => 'Campaign',
'donations'     => 'Donations',
'startdate'     => 'Starting Date',
'enddate'       => 'Ending Date',
'campaign_info' => 'Campaign Information',
'donation_info' => 'Donation Information',
'goal'          => 'Goal',
'submit'        => 'Submit',
'clearform'     => 'Reset Form',
'cancel'        => 'Cancel',
'stop_after_goal' => 'Stop after goal is reached?',
'blk_show_pct'  => 'Show progress bar in block?',
'admin_hdr'     => 'Donation Administration',
'admin_msg1'    => 'Click on a campaign name to access donations received.',
'don_list_header' => 'Edit or Delete donation records',
'received'      => 'Received',
'date'          => 'Date',
'date_selector' => 'Click for a date selector',
'contributor'   => 'Contributor',
'amount'        => 'Amount',
'sug_amount'    => 'Suggested Amount',
'fixed_amount'    => 'Fixed Donation Amount',
'amount_opt'    => 'Set amount to zero to allow the contributer to enter any amount',
'comment'       => 'Comment',
'donation'      => 'Donation',
'new_campaign'  => 'New Campaign',
'new_donation'  => 'New Donation',
'email_subject' => 'New Donation Received',
'email_msg'     => 'A donation has been received.',
'reset_buttons' => 'Reset Paypal Buttons',
'block_title'   => 'Donations Made',
'open_campaigns' => 'Campaigns Accepting Donations',
'no_open_campaigns' => 'No campaigns are currently accpeting donations',
'open_campaigns_desc' => 'You may donate to any of the listed fundraising campaigns by clicking the "Donate" button.  Thank you for your support!',
'contribute'    => 'Contribute',
'txn_id'        => 'Transaction ID',
'delete'        => 'Delete',
'q_del_item'    => 'Are you sure that you want to delete this item?',
'invalid_id_req' => 'Invalid campaign ID requested.',
'edit_item'     => 'Edit Item',
'thanks_title'  => 'Thank you',
'thanks'        => 'Thank you for your donation',
'msg_item_updated' => 'Item has been updated.',
'msg_item_nochange' => 'Item has not been changed.',
'hlp_campaign'  => 'Select the campagn from the list',
'hlp_contributor'   => 'Select a site user, or enter the contributor&apos;s name',
'hlp_amount'    => 'Enter the amount of the donation',
'hlp_transid'   => 'Enter a transaction ID, if applicable',
'hlp_comment'   => 'Enter a general comment here',
'pct_received'  => '%.02f Received of %.02f',
'amt_received'  => '%1$s (%3$.02f%%) of %2$s Received',
'donate' => 'Donate',
'datepicker' => 'Click for Date Selector',
'timepicker' => 'Click for Time Selector',
);

// Messages for the plugin upgrade
$PLG_donation_MESSAGE06 = 'Plugin upgrade not supported.';

// Localization of the Admin Configuration UI
$LANG_configsections['donation'] = array(
    'label' => 'Donations',
    'title' => 'Donations Configuration',
);

$LANG_confignames['donation'] = array(
    'pp_use_donation'   => 'Use Donation Paypal Button Type',
    'num_in_blk'        => 'Number of campaigns in block (0=unlimited)',
);

$LANG_configsubgroups['donation'] = array(
    'sg_main' => 'Main Settings',
);

$LANG_fs['donation'] = array(
    'fs_main' => 'Main Settings',
    'fs_paypal' => 'Paypal Settings',
);

// Note: entries 0, 1, and 12 are the same as in $LANG_configselects['Core']
$LANG_configselects['donation'] = array(
    0 => array('True' => 1, 'False' => 0),
    1 => array('True' => TRUE, 'False' => FALSE),
    3 => array('Yes' => 1, 'No' => 0),
    //5 => array('Top of Page' => 1, 'Below Featured Article' => 2, 'Bottom of Page' => 3),
    //12 => array('No access' => 0, 'Read-Only' => 2, 'Read-Write' => 3),
);

?>
