<?php
/**
 * Table names and other global configuraiton values.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2022 Lee Garner <lee@leegarner.com>
 * @package     donation
 * @version     v0.1.2
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** @global array $_TABLES */
global $_TABLES;
/** @global string $_DB_table_prefix */
global $_DB_table_prefix;

// Static configuration items
Donation\Config::set('pi_version', '0.1.2');
Donation\Config::set('gl_version', '1.7.8');

$DON_prefix = $_DB_table_prefix . Donation\Config::PI_NAME . '_';

// Table definitions
$_TABLES['don_campaigns']  = $DON_prefix . 'campaigns';
$_TABLES['don_donations']  = $DON_prefix . 'donations';

