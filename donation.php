<?php
/**
*   Table names and other global configuraiton values.
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2020 Lee Garner <lee@leegarner.com>
*   @package    donation
*   @version    0.0.4
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/** @global array $_TABLES */
global $_TABLES;
/** @global string $_DB_table_prefix */
global $_DB_table_prefix;

// Static configuration items
$_CONF_DON['pi_version'] = '0.0.4';
$_CONF_DON['pi_name'] = 'donation';
$_CONF_DON['gl_version'] = '1.7.0';
$_CONF_DON['pi_url'] = 'http://www.leegarner.com';
$_CONF_DON['pi_display_name'] = 'Donations';

$DON_prefix = $_DB_table_prefix . $_CONF_DON['pi_name'] . '_';

// Table definitions
$_TABLES['don_campaigns']  = $DON_prefix . 'campaigns';
$_TABLES['don_donations']  = $DON_prefix . 'donations';

?>
