<?php

/*
   ------------------------------------------------------------------------
   TimelineTicket
   Copyright (C) 2013-2016 by the TimelineTicket Development Team.

   https://github.com/pluginsGLPI/timelineticket
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
   @copyright Copyright (c) 2013-2016 TimelineTicket team
   @license   AGPL License 3.0 or (at your option) any later version
              http://www.gnu.org/licenses/agpl-3.0-standalone.html
   @link      https://github.com/pluginsGLPI/timelineticket
   @since     2013

   ------------------------------------------------------------------------
 */

function plugin_timelineticket_getAddSearchOptions($itemtype) {

   $sopt = array();
   if ($itemtype == 'Ticket') {

      $sopt[9131]['table']     = 'glpi_groups';
      $sopt[9131]['field']     = 'completename';
      $sopt[9131]['name']      = "timelineticket - ".__('Assigned to')." - ".__('Group');
      $sopt[9131]['datatype']  = 'dropdown';
      $sopt[9131]['forcegroupby']  = true;
      $sopt[9131]['massiveaction'] = false;
      $sopt[9131]['condition']     = 'is_assign';
      $sopt[9131]['joinparams']    = array('beforejoin'
                                       => array('table' => 'glpi_plugin_timelineticket_assigngroups',
                                                'joinparams'
                                                        => array('jointype'  => 'child')));

      $sopt[9132]['table']     = 'glpi_users';
      $sopt[9132]['field']     = 'name';
      $sopt[9132]['name']      = "timelineticket - ".__('Assigned to')." - ".__('Technician');
      $sopt[9132]['datatype']  = 'dropdown';
      $sopt[9132]['forcegroupby']  = true;
      $sopt[9132]['massiveaction'] = false;
      $sopt[9132]['condition']     = 'is_assign';
      $sopt[9132]['joinparams']    = array('beforejoin'
                                       => array('table' => 'glpi_plugin_timelineticket_assignusers',
                                                'joinparams'
                                                        => array('jointype'  => 'child')));


         $sopt[11001]['table']     = 'glpi_plugin_timelineticket_assigngroups';
         $sopt[11001]['field']     = 'groups_id';
         $sopt[11001]['name']      = "timelineticket-".__('Group')."-".__('Time');
//         $sopt[11001]['datatype']  = 'itemtype';
//         $sopt[11001]['itemlink_type'] = 'PluginFusioninventoryInventoryComputerLib';
         $sopt[11001]['massiveaction'] = FALSE;
         $sopt[11001]['forcegroupby']  = TRUE;
         $sopt[11001]['splititems']    = TRUE;

   }
   return $sopt;
}
function plugin_timelineticket_install() {
   global $DB;

   include_once (GLPI_ROOT."/plugins/timelineticket/inc/profile.class.php");
   include_once (GLPI_ROOT."/plugins/timelineticket/inc/config.class.php");
   $migration = new Migration(160);

   // installation

   if (!TableExists("glpi_plugin_timelineticket_states")) {
      $query = "CREATE TABLE `glpi_plugin_timelineticket_states` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `tickets_id` int(11) NOT NULL DEFAULT '0',
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
                  `tickets_id` int(11) NOT NULL DEFAULT '0',
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
                  `tickets_id` int(11) NOT NULL DEFAULT '0',
                  `date` datetime DEFAULT NULL,
                  `users_id` varchar(255) DEFAULT NULL,
                  `begin` INT(11) NULL,
                  `delay` INT(11) NULL,
                  PRIMARY KEY (`id`),
                  KEY `tickets_id` (`tickets_id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

     $DB->query($query) or die($DB->error());
   }

   if (!TableExists("glpi_plugin_timelineticket_grouplevels")) {
      $query = "CREATE TABLE IF NOT EXISTS `glpi_plugin_timelineticket_grouplevels` (
               `id` int(11) NOT NULL AUTO_INCREMENT,
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

   if (!TableExists("glpi_plugin_timelineticket_configs")) {
      $query = "CREATE TABLE IF NOT EXISTS `glpi_plugin_timelineticket_configs` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `add_waiting` int(11) NOT NULL DEFAULT '1',
              PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
      $DB->query($query) or die($DB->error());
   }

   $status = array ('new'           => Ticket::INCOMING,
                    'assign'        => Ticket::ASSIGNED,
                    'plan'          => Ticket::PLANNED,
                    'waiting'       => Ticket::WAITING,
                    'solved'        => Ticket::SOLVED,
                    'closed'        => Ticket::CLOSED);

   // Update field in tables
   foreach (array('glpi_plugin_timelineticket_states') as $table) {
      // Migrate datas
      foreach ($status as $old => $new) {
         $query = "UPDATE `$table`
                   SET `old_status` = '$new'
                   WHERE `old_status` = '$old'";
         $DB->queryOrDie($query, "0.84 status in $table $old to $new");

         $query = "UPDATE `$table`
                   SET `new_status` = '$new'
                   WHERE `new_status` = '$old'";
         $DB->queryOrDie($query, "0.84 status in $table $old to $new");
      }
   }

   PluginTimelineticketConfig::createFirstConfig();

   if (isset($_SESSION['glpiactiveprofile'])
           && isset($_SESSION['glpiactiveprofile']['id'])) {
      PluginTimelineticketProfile::createFirstAccess($_SESSION['glpiactiveprofile']['id']);
   }
   
   $migration = new Migration("9.1+1.0");
   $migration->dropTable('glpi_plugin_timelineticket_profiles');
   return true;
}

function plugin_timelineticket_uninstall() {
   global $DB;

   include_once(GLPI_ROOT . "/plugins/timelineticket/inc/profile.class.php");

   $tables = array("glpi_plugin_timelineticket_states",
       "glpi_plugin_timelineticket_assigngroups",
       "glpi_plugin_timelineticket_assignusers",
       "glpi_plugin_timelineticket_grouplevels",
       "glpi_plugin_timelineticket_profiles",
       "glpi_plugin_timelineticket_configs");

   foreach ($tables as $table) {
      $query = "DROP TABLE IF EXISTS `$table`;";
      $DB->query($query) or die($DB->error());
   }

   //Delete rights associated with the plugin
   $profileRight = new ProfileRight();
   foreach (PluginTimelineticketProfile::getRightsGeneral() as $right) {
      $profileRight->deleteByCriteria(array('name' => $right['field']));
   }

   PluginTimelineticketProfile::removeRightsFromSession();
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

   PluginTimelineticketAssignGroup::checkAssignGroup($item);
   PluginTimelineticketAssignUser::checkAssignUser($item);
}


function plugin_timelineticket_ticket_add(Ticket $item) {

   // Instantiation of the object from the class PluginTimelineticketStates
   $followups = new PluginTimelineticketState();

   $followups->createFollowup($item, $item->input['date'], '', Ticket::INCOMING);

   if ($item->input['status'] != Ticket::INCOMING) {
      $followups->createFollowup($item, $item->input['date'], Ticket::INCOMING, $item->input['status']);
   }
}


function plugin_timelineticket_ticket_purge(Ticket $item) {

   // Instantiation of the object from the class PluginTimelineticketStates
   $ticketstate = new PluginTimelineticketState();
   $user = new PluginTimelineticketAssignUser();
   $group = new PluginTimelineticketAssignUser();

   $ticketstate->deleteByCriteria(array('tickets_id' => $item->getField("id")));
   $user->deleteByCriteria(array('tickets_id' => $item->getField("id")));
   $group->deleteByCriteria(array('tickets_id' => $item->getField("id")));
}

function plugin_timelineticket_getDropdown() {

   $plugin = new Plugin();

   if ($plugin->isActivated("timelineticket"))
      return array('PluginTimelineticketGrouplevel'=>PluginTimelineticketGrouplevel::getTypeName(2));
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

   $searchopt=&Search::getOptions($type);
   $table=$searchopt[$ID]["table"];
   $field=$searchopt[$ID]["field"];

   switch ($table.'.'.$field) {
      case "glpi_plugin_timelineticket_grouplevels.groups" :
         if (empty($data["ITEM_$num"])) {
            $out=__('None');
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

      case "glpi_plugin_timelineticket_assigngroups.groups_id" :
         $ptAssignGroup = new PluginTimelineticketAssignGroup();
         $group = new Group();
         $ticket = new Ticket();
         $out = "";
         $a_out = array();
         $a_groupname = array();
         if (!isset($data["ITEM_$num"])
                 or !strstr($data["ITEM_$num"], '$$')) {
            return "";
         }
         $splitg = explode("$$$$", $data["ITEM_$num"]);
         foreach ($splitg as $datag) {
            $split = explode("$$", $datag);
            $group->getFromDB($split[0]);
            $ptAssignGroup->getFromDB($split[1]);
            $time = $ptAssignGroup->fields['delay'];
            if ($ptAssignGroup->fields['delay'] === NULL) {
               $ticket->getFromDB($data["ITEM_0"]);

               $calendar = new Calendar();
               $calendars_id = Entity::getUsedConfig('calendars_id', $ticket->fields['entities_id']);
               $datedebut = $ptAssignGroup->fields['date'];
               $enddate = $_SESSION["glpi_currenttime"];
               if ($ticket->fields['status'] == Ticket::CLOSED) {
                  $enddate = $ticket->fields['closedate'];
               }

               if ($calendars_id>0 && $calendar->getFromDB($calendars_id)) {
                  $time = $calendar->getActiveTimeBetween ($datedebut, $enddate);
               } else {
                  // cas 24/24 - 7/7
                  $time = strtotime($enddate)-strtotime($datedebut);
               }
            } else if ($ptAssignGroup->fields['delay'] == 0) {
               $time = 0;
            }
            $a_groupname[$group->fields['id']] = $group->getLink();
            if (isset($a_out[$group->fields['id']])) {
               $a_out[$group->fields['id']] += $time;
            } else {
               $a_out[$group->fields['id']] = $time;
            }
         }
         $a_out_comp = array();
         foreach ($a_out as $groups_id => $time) {
            $a_out_comp[] = $a_groupname[$groups_id]." : ".Html::timestampToString($time, TRUE, FALSE);
         }

         $out = implode("<hr/>", $a_out_comp);
         return $out;
         break;

   }
   return "";
}


function plugin_timelineticket_addLeftJoin($itemtype, $ref_table, $new_table, $linkfield,
                                            &$already_link_tables) {
   switch ($itemtype) {

      case 'Ticket':
            if ($new_table.".".$linkfield ==
                    "glpi_plugin_timelineticket_assigngroups.plugin_timelineticket_assigngroups_id") {
               return " LEFT JOIN `glpi_plugin_timelineticket_assigngroups` "
               . " ON (`glpi_tickets`.`id` = `glpi_plugin_timelineticket_assigngroups`.`tickets_id` )  ";
            }
         break;

   }
   return "";
}

?>