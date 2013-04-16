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

   
   return true;
}


function plugin_timelineticket_uninstall() {
  global $DB;

   $tables = array ("glpi_plugin_timelineticket_states",
                    "glpi_plugin_timelineticket_assigngroups",
                    "glpi_plugin_timelineticket_assignusers");

   foreach ($tables as $table) {
      $query = "DROP TABLE IF EXISTS `$table`;";
      $DB->query($query) or die($DB->error());
   }
}

/*
function plugin_timelineticket_get_headings($item) {
   global $LANG;

   switch (get_Class($item)) {
      case 'Ticket' :
         if ($item->getField('id')>0 && haveRight('config','r')) {
            return array(1 => $LANG['plugin_timelineticket'][3]);
         }
   }
   return false;
}


function plugin_timelineticket_headings_action($item) {

   switch (get_Class($item)) {
      case 'Ticket' :
         if ($item->getField('id')>0 && haveRight('config','r')) {
            return array(1 => array('PluginTimelineticketState','showForTicket'));
         }
         break;
   }
   return false;
}


function plugin_timelineticket_headings($item,$withtemplate=0){
}


function plugin_timelineticket_ticket_add(Ticket $item) {

   // Instantiation of the object from the class PluginTimelineticketStates
   $followups = new PluginTimelineticketState();

   $followups->createFollowup($item, $item->input['date'], '', 'new');

   if ($item->input['status'] != 'new') {
      $followups->createFollowup($item, $item->input['date'], 'new', $item->input['status']);
   }
}
*/

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


function plugin_timelineticket_getAddSearchOptions($itemtype) {
   global $LANG;

   Plugin::loadLang('timelineticket');

   $sopt = array();
   if ($itemtype == 'Ticket') {
      $sopt[1000]['table']         = 'glpi_plugin_timelineticket_followups';
      $sopt[1000]['field']         = 'solved_delay';
      $sopt[1000]['linkfield']     = '';
      $sopt[1000]['name']          = $LANG['plugin_timelineticket'][2];
      $sopt[1000]['datatype']      = 'timestamp';
      $sopt[1000]['forcegroupby']  = true;
      $sopt[1000]['usehaving']     = true;
   }
   return $sopt;
}


function plugin_timelineticket_addSelect($type,$ID,$num) {

   $searchopt = &Search::getOptions($type);
   $table = $searchopt[$ID]["table"];
   $field = $searchopt[$ID]["field"];

   switch ($table.".".$field) {
      case "glpi_plugin_timelineticket_followups.solved_delay" :
         return "(SUM(glpi_plugin_timelineticket_followups.delay)
                    * COUNT(DISTINCT glpi_plugin_timelineticket_followups.id)
                    / COUNT(glpi_plugin_timelineticket_followups.id)) AS ITEM_$num, ";
   }

   return "";
}


function plugin_timelineticket_addLeftJoin($type,$ref_table,$new_table,$linkfield) {

   switch ($new_table) {
      case "glpi_plugin_timelineticket_followups" :
         return " LEFT JOIN `$new_table` ON (`$ref_table`.`id` = `$new_table`.`tickets_id`
                       AND `$new_table`.`old_status` IN ('new','assign','plan')) ";
   }
   return "";
}
?>