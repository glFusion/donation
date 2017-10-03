<?php
/**
*   Common AJAX functions.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2017 Lee Garner <lee@leegarner.com>
*   @package    donation
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/** Include required glFusion common functions */
require_once '../../../lib-common.php';

// This is for administrators only
if (!SEC_hasRights('donation.admin')) {
    COM_accessLog("User {$_USER['username']} tried to illegally access the donation AJAX functions.");
    exit;
}
COM_errorLog(print_r($_POST,true));

switch ($_POST['action']) {
case 'toggleEnabled':
    switch ($_POST['type']) {
    case 'campaign':
        $newval = Donation\Campaign::ToggleEnabled($_POST['oldval'], $_POST['id']);
        if ($newval != $_POST['oldval']) {
            $message = sprintf($LANG_DON['msg_item_updated'],
                $LANG_DON[$_POST['type']],
                $newval ? $LANG_DON['enabled'] : $LANG_DON['disabled']);
        } else {
            $message = $LANG_DON['msg_item_nochange'];
        }
        $retval = array(
            'id'    => $_POST['id'],
            'newval' => $newval,
            'statusMessage' => $message,
        );
        break;

     default:
        exit;
    }
}

if (is_array($retval) && !empty($retval)) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    //A date in the past
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    echo json_encode($retval);
}

?>
