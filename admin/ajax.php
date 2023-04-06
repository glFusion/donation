<?php
/**
 * Common AJAX functions.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2023 Lee Garner <lee@leegarner.com>
 * @package     donation
 * @version     v0.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 * @filesource
 */

/** Include required glFusion common functions */
require_once '../../../lib-common.php';

// This is for administrators only
if (!COM_isAjax() || !SEC_hasRights('donation.admin')) {
    COM_accessLog("User {$_USER['username']} tried to illegally access the donation AJAX functions.");
    exit;
}

$Request = Donation\Models\Request::getInstance();
switch ($Request->getString('action')) {
case 'toggleEnabled':
    $type = $Request->getString('type');
    switch ($type) {
    case 'campaign':
        $oldval = $Request->getInt('oldval');
        $id = $Request->getString('id');
        $newval = Donation\Campaign::ToggleEnabled($oldval, $id);
        if ($newval != $oldval) {
            $message = sprintf($LANG_DON['msg_item_updated'],
                $LANG_DON[$type],
                $newval ? $LANG_DON['enabled'] : $LANG_DON['disabled']);
        } else {
            $message = $LANG_DON['msg_item_nochange'];
        }
        $retval = array(
            'id'    => $id,
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
