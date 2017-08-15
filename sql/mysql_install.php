<?php
/**
*   Table definitions for the Banner plugin
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2017 Lee Garner <lee@leegarner.com>
*   @package    donation
*   @version    0.0.2
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

$_SQL['don_donations'] = "CREATE TABLE {$_TABLES['don_donations']} (
  `don_id` int(11) unsigned NOT NULL auto_increment,
  `uid` int(11) unsigned NOT NULL default '0',
  `contrib_name` varchar(255) default NULL,
  `dt` date NOT NULL,
  `camp_id` varchar(40) NOT NULL default '',
  `amount` float(8,2) NOT NULL default '0.00',
  `comment` text,
  `txn_id` varchar(40) default '',
  PRIMARY KEY  (`don_id`)
)";

$_SQL['don_campaigns'] = "CREATE TABLE {$_TABLES['don_campaigns']} (
  `camp_id` varchar(40) NOT NULL,
  `name` varchar(255) default NULL,
  `shortdesc` varchar(255),
  `description` text,
  `startdt` datetime default NULL,
  `enddt` datetime default NULL,
  `enabled` tinyint(1) NOT NULL default '1',
  `amount` float(8,2) NOT NULL default '0.00',
  `goal` float(8,2) unsigned NOT NULL default '0.00',
  `hardgoal` tinyint(1) unsigned NOT NULL default '0',
  `received` float(8,2) NOT NULL default '0.00',
  `blk_show_pct` tinyint(1) NOT NULL default '1',
  `pp_buttons` text,
  PRIMARY KEY  (`camp_id`)
)";

$_UPGRADE_SQL = array(
'0.0.2' => array(
    "ALTER TABLE {$_TABLES['don_campaigns']}
        ADD amount float(8,2) NOT NULL default '0.00' AFTER enabled,
        ADD description text AFTER name,
        ADD shortdesc varchar(255) default NULL after name",
    ),
);

?>
