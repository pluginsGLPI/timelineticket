<?php

/*
   ------------------------------------------------------------------------
   TimelineTicket
   Copyright (C) 2013-2013 by the TimelineTicket Development Team.

   https://forge.indepnet.net/projects/timelineticket
   ------------------------------------------------------------------------

   LICENSE

   This file is part of TimelineTicket project.

   TimelineTicket plugin is free software: you can redistribute it and/or modify
   it under the terms of the GNU Affero General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.

   TimelineTicket plugin is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
   GNU Affero General Public License for more details.

   You should have received a copy of the GNU Affero General Public License
   along with TimelineTicket plugin. If not, see <http://www.gnu.org/licenses/>.

   ------------------------------------------------------------------------

   @package   TimelineTicket plugin
   @copyright Copyright (c) 2013-2013 TimelineTicket team
   @license   AGPL License 3.0 or (at your option) any later version
              http://www.gnu.org/licenses/agpl-3.0-standalone.html
   @link      https://forge.indepnet.net/projects/timelineticket
   @since     2013

   ------------------------------------------------------------------------
 */

function plugin_timelineticket_install() {
   global $DB, $LANG;

   $migration = new Migration(160);

   // installation

   if (!TableExists("glpi_plugin_timelineticket_states")) {
      $query = "CREATE TABLE `glpi_plugin_timelineticket_states` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `tickets_id` int(11) NOT NULL,
                  `date` datetime DEFAULT NULL,
                  `old_status` varchar(255) DEFAULT NULL,
                  `new_status` varchar(255) DEFAULT NULL,
                  `delay` INT( 11 ) NULL,
                  PRIMARY KEY (`id`),
                  KEY `tickets_id` (`tickets_id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

     $DB->query($query) or die($DB->error());
   }
   if (!TableExists("glpi_plugin_timelineticket_assigngroups")) {
      $query = "CREATE TABLE `glpi_plugin_timelineticket_assigngroups` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `tickets_id` int(11) NOT NULL,
                  `date` datetime DEFAULT NULL,
                  `groups_id` varchar(255) DEFAULT NULL,
                  `begin` INT( 11 ) NULL,
                  `delay` INT( 11 ) NULL,
                  PRIMARY KEY (`id`),
                  KEY `tickets_id` (`tickets_id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

     $DB->query($query) or die($DB->error());

   }

   if (!TableExists("glpi_plugin_timelineticket_assignusers")) {
      $query = "CREATE TABLE `glpi_plugin_timelineticket_assignusers` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `tickets_id` int(11) NOT NULL,
                  `date` datetime DEFAULT NULL,
                  `users_id` varchar(255) DEFAULT NULL,
                  `begin` INT( 11 ) NULL,
                  `delay` INT( 11 ) NULL,
                  PRIMARY KEY (`id`),
                  KEY `tickets_id` (`tickets_id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

     $DB->query($query) or die($DB->error());
   }
   
   if (!TableExists("glpi_plugin_timelineticket_grouplevels")) {
      $query = "CREATE TABLE IF NOT EXISTS `glpi_plugin_timelineticket_grouplevels` (
               `id` int(11) NOT NULL auto_increment,
               `entities_id` int(11) NOT NULL DEFAULT '0',
               `is_recursive` tinyint(1) NOT NULL default '0',
               `name` varchar(255) collate utf8_unicode_ci default NULL,
               `groups` longtext collate utf8_unicode_ci,
               `rank` smallint(6) NOT NULL default '0',
               `comment` text collate utf8_unicode_ci,
               PRIMARY KEY (`id`)
             ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
      $DB->query($query) or die($DB->error());
   }

   
   return true;
}

function plugin_timelineticket_uninstall() {
   global $DB;

   $tables = array("glpi_plugin_timelineticket_states",
       "glpi_plugin_timelineticket_assigngroups",
       "glpi_plugin_timelineticket_assignusers",
       "glpi_plugin_timelineticket_grouplevels");

   foreach ($tables as $table) {
      $query = "DROP TABLE IF EXISTS `$table`;";
      $DB->query($query) or die($DB->error());
   }
}

function plugin_timelineticket_ticket_update(Ticket $item) {
   
   if (in_array('status',$item->updates)) {
      // Instantiation of the object from the class PluginTimelineticketStates
      $ptState = new PluginTimelineticketState();

      // Insertion the changement in the database
      $ptState->createFollowup($item, 
                               $_SESSION["glpi_currenttime"],
                               $item->oldvalues['status'], 
                               $item->fields['status']);
      // calcul du délai + insertion dans la table
   }

//   $ptAssignGroup = new PluginTimelineticketAssignGroup();
//   $ptAssignGroup->updateTicket($item);
}


function plugin_timelineticket_ticket_add(Ticket $item) {

   // Instantiation of the object from the class PluginTimelineticketStates
   $followups = new PluginTimelineticketState();

   $followups->createFollowup($item, $item->input['date'], '', 'new');

   if ($item->input['status'] != 'new') {
      $followups->createFollowup($item, $item->input['date'], 'new', $item->input['status']);
   }
}


function plugin_timelineticket_ticket_purge(Ticket $item) {

   // Instantiation of the object from the class PluginTimelineticketStates
   $followups = new PluginTimelineticketState();
   // Deletion of the followups
   $followups->cleanFollowup($item->getField("id"));
}

function plugin_timelineticket_getDropdown() {
   global $LANG;

   $plugin = new Plugin();

   if ($plugin->isActivated("timelineticket"))
      return array('PluginTimelineticketGrouplevel'=>$LANG['plugin_timelineticket']['config'][5]);
   else
      return array();
}

// Define dropdown relations
function plugin_timelineticket_getDatabaseRelations() {

   $plugin = new Plugin();
   if ($plugin->isActivated("timelineticket"))
      return array("glpi_entities" => array("glpi_plugin_timelineticket_grouplevels" => "entities_id"));
   else
      return array();
}

function plugin_timelineticket_giveItem($type,$ID,$data,$num) {
	global $CFG_GLPI,$DB,$LANG;

	$searchopt=&Search::getOptions($type);
	$table=$searchopt[$ID]["table"];
	$field=$searchopt[$ID]["field"];

	switch ($table.'.'.$field) {
		case "glpi_plugin_timelineticket_grouplevels.groups" :
			if (empty($data["ITEM_$num"])) {
				$out=$LANG['common'][49];
			} else {
				$out= "";
				$groups = json_decode($data["ITEM_$num"], true);
            if (!empty($groups)) {
               foreach ($groups as $key => $val) {
                  $out .= Dropdown::getDropdownName("glpi_groups", $val)."<br>";
               }
            }
         }
         return $out;
         break;
	}
	return "";
}

?>