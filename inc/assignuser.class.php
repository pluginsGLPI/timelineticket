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

if (!defined('GLPI_ROOT')){
   die("Sorry. You can't access directly to this file");
}

class PluginTimelineticketAssignUser extends CommonDBTM {

   /*
    * type = new or delete
    */
   function createUser(Ticket $ticket, $date, $users_id, $type) {

      $calendar = new Calendar();

      if ($type == 'new') {
         $calendars_id = Entity::getUsedConfig('calendars_id', $ticket->fields['entities_id']);
         if ($calendars_id>0 && $calendar->getFromDB($calendars_id)) {
            $begin = $calendar->getActiveTimeBetween ($ticket->fields['date'], $date);
         } else {
            // cas 24/24 - 7/7
            $begin = strtotime($date)-strtotime($ticket->fields['date']);
         }

         $this->add(array('tickets_id'  => $ticket->getField("id"),
                          'date'        => $date,
                          'users_id'   => $users_id,
                          'begin'       => $begin));

      } else if ($type == 'delete') {
         $a_dbentry = $this->find("`tickets_id`='".$ticket->getField("id")."'
            AND `users_id`='".$users_id."'
            AND `delay` IS NULL", "", 1);
         if (count($a_dbentry) == 1) {
            $input = current($a_dbentry);
            $calendars_id = Entity::getUsedConfig('calendars_id', $ticket->fields['entities_id']);
            if ($calendars_id>0 && $calendar->getFromDB($calendars_id)) {
               $input['delay'] = $calendar->getActiveTimeBetween ($input['date'], $date);
            } else {
               // cas 24/24 - 7/7
               $input['delay'] = strtotime($date)-strtotime($input['date']);
            }
            $this->update($input);
         }
      }
   }



   function showTimeline($ticket, $params = array()) {
      global $CFG_GLPI;

      /* Create and populate the pData object */
      $MyData = new pData();
      /* Create the pChart object */
      $myPicture = new pImage(820,29,$MyData);
      /* Create the pIndicator object */
      $Indicator = new pIndicator($myPicture);
      $myPicture->setFontProperties(array("FontName"=>GLPI_ROOT."/plugins/timelineticket/lib/pChart2.1.4/fonts/pf_arma_five.ttf","FontSize"=>6));
      /* Define the indicator sections */
      $IndicatorSections = array();
      $_usersfinished = array();

      $a_users_list = array();
      $IndicatorSections = PluginTimelineticketToolbox::getDetails($ticket, 'user');
      foreach ($IndicatorSections as $users_id=>$data) {
         $a_users_list[$users_id] = $users_id;

         $a_end = end($data);
         if ($a_end['R'] == 235
                 && $a_end['G'] == 235
                 && $a_end['B'] == 235) {
            $_usersfinished[$users_id] = true;
         } else {
            $_usersfinished[$users_id] = false;
         }
      }

      echo "<tr>";
      echo "<th colspan='2'>";
      if (count($a_users_list) > 1) {
         _e('Technicians in charge of the ticket', 'timelineticket');
      } else {
         _e('Technician in charge of the ticket');
      }
      echo"</th>";
      echo "</tr>";

      foreach ($IndicatorSections as $users_id =>$array) {
         echo "<tr class='tab_bg_2'>";
         echo "<td width='100'>";
         echo getUserName($users_id);
         echo "</td>";
         echo "<td>";
         if ($ticket->fields['status'] != Ticket::CLOSED
                 && $_usersfinished[$users_id] != 0) {

            $IndicatorSettings = array("Values"=>array(100,201),
                                       "CaptionPosition"=>INDICATOR_CAPTION_BOTTOM,
                                       "CaptionLayout"=>INDICATOR_CAPTION_DEFAULT,
                                       "CaptionR"=>0,
                                       "CaptionG"=>0,
                                       "CaptionB"=>0,
                                       "DrawLeftHead"=>false,
                                       "DrawRightHead"=>true,
                                       "ValueDisplay"=>false,
                                       "IndicatorSections"=>$array,
                                       "SectionsMargin" => 0);
            $Indicator->draw(2,2,805,25,$IndicatorSettings);
         } else {
            $IndicatorSettings = array("Values"=>array(100,201),
                                       "CaptionPosition"=>INDICATOR_CAPTION_BOTTOM,
                                       "CaptionLayout"=>INDICATOR_CAPTION_DEFAULT,
                                       "CaptionR"=>0,
                                       "CaptionG"=>0,
                                       "CaptionB"=>0,
                                       "DrawLeftHead"=>false,
                                       "DrawRightHead"=>false,
                                       "ValueDisplay"=>false,
                                       "IndicatorSections"=>$array,
                                       "SectionsMargin" => 0);
            $Indicator->draw(2,2,814,25,$IndicatorSettings);
         }

         $filename = $uid=Session::getLoginUserID(false)."_testuser".$users_id;
         $myPicture->render(GLPI_GRAPH_DIR."/".$filename.".png");


         echo "<img src='".$CFG_GLPI['root_doc']."/front/graph.send.php?file=".$filename.".png'><br/>";
         echo "</td>";
         echo "</tr>";
      }

   }



   static function addUserTicket(Ticket_User $item) {

      if ($item->fields['type'] == 2) {
         $ptAssignUser = new PluginTimelineticketAssignUser();
         $ticket = new Ticket();
         $ticket->getFromDB($item->fields['tickets_id']);
         $calendar = new Calendar();
         $calendars_id = Entity::getUsedConfig('calendars_id', $ticket->fields['entities_id']);
         $datedebut = $ticket->fields['date'];
         if ($calendars_id>0 && $calendar->getFromDB($calendars_id)) {
            $delay = $calendar->getActiveTimeBetween ($datedebut, $_SESSION["glpi_currenttime"]);
         } else {
            // cas 24/24 - 7/7
            $delay = strtotime($_SESSION["glpi_currenttime"])-strtotime($datedebut);
         }

         $ok = 1;

         $ptConfig = new PluginTimelineticketConfig();
         $ptConfig->getFromDB(1);
         if ($ptConfig->fields["add_waiting"] == 0
               && $ticket->fields["status"] == Ticket::WAITING) {
            $ok = 0;
         }
         if ($ok) {
            $input = array();
            $input['tickets_id'] = $item->fields['tickets_id'];
            $input['users_id'] = $item->fields['users_id'];
            $input['date'] = $_SESSION["glpi_currenttime"];
            $input['begin'] = $delay;
            $ptAssignUser->add($input);
         }
      }
   }

   static function checkAssignUser(Ticket $ticket) {
      global $DB;

      $ok = 0;
      $ptConfig = new PluginTimelineticketConfig();
      $ptConfig->getFromDB(1);
      if ($ptConfig->fields["add_waiting"] == 0) {
         $ok = 1;
      }

      if ($ok && in_array("status", $ticket->updates)
            && isset($ticket->oldvalues["status"])
               && $ticket->oldvalues["status"] == Ticket::WAITING) {
         if ($ticket->countUsers(CommonITILActor::ASSIGN)) {
            foreach ($ticket->getUsers(CommonITILActor::ASSIGN) as $d) {
               $ptAssignUser = new PluginTimelineticketAssignUser();
               $calendar = new Calendar();
               $calendars_id = Entity::getUsedConfig('calendars_id', $ticket->fields['entities_id']);
               $datedebut = $ticket->fields['date'];
               if ($calendars_id>0 && $calendar->getFromDB($calendars_id)) {
                  $delay = $calendar->getActiveTimeBetween ($datedebut, $_SESSION["glpi_currenttime"]);
               } else {
                  // cas 24/24 - 7/7
                  $delay = strtotime($_SESSION["glpi_currenttime"])-strtotime($datedebut);
               }

               $input = array();
               $input['tickets_id'] = $ticket->getID();
               $input['users_id'] = $d["users_id"];
               $input['date'] = $_SESSION["glpi_currenttime"];
               $input['begin'] = $delay;
               $ptAssignUser->add($input);
            }
         }
      } else if ($ok && in_array("status", $ticket->updates)
            && isset($ticket->fields["status"])
               && $ticket->fields["status"] == Ticket::WAITING) {
         if ($ticket->countUsers(CommonITILActor::ASSIGN)) {
            foreach ($ticket->getUsers(CommonITILActor::ASSIGN) as $d) {

               $calendar = new Calendar();
               $calendars_id = Entity::getUsedConfig('calendars_id', $ticket->fields['entities_id']);
               $ptAssignUser = new PluginTimelineticketAssignUser();
               $query = "SELECT MAX(`date`) AS datedebut, id
                         FROM `".$ptAssignUser->getTable()."`
                         WHERE `tickets_id` = '".$ticket->getID()."'
                           AND `users_id`='".$d["users_id"]."'
                           AND `delay` IS NULL";

               $result    = $DB->query($query);
               $datedebut = '';
               $input = array();
               if ($result && $DB->numrows($result)) {
                  $datedebut = $DB->result($result, 0, 'datedebut');
                  $input['id'] = $DB->result($result, 0, 'id');
               } else {
                  return;
               }

               if (!$datedebut) {
                  $delay = 0;
               // Utilisation calendrier
               } else if ($calendars_id>0 && $calendar->getFromDB($calendars_id)) {
                  $delay = $calendar->getActiveTimeBetween ($datedebut, $_SESSION["glpi_currenttime"]);
               } else {
                  // cas 24/24 - 7/7
                  $delay = strtotime($_SESSION["glpi_currenttime"])-strtotime($datedebut);
               }

               $input['delay'] = $delay;
               $ptAssignUser->update($input);
            }
         }
       } else if (in_array("status", $ticket->updates)
            && isset($ticket->input["status"])
               && ($ticket->input["status"] == Ticket::SOLVED 
                     || $ticket->input["status"] == Ticket::CLOSED)) {
         if ($ticket->countUsers(CommonITILActor::ASSIGN)) {
            foreach ($ticket->getUsers(CommonITILActor::ASSIGN) as $d) {

               $calendar = new Calendar();
               $calendars_id = Entity::getUsedConfig('calendars_id', $ticket->fields['entities_id']);
               $ptAssignUser = new PluginTimelineticketAssignUser();
               $query = "SELECT MAX(`date`) AS datedebut, id
                         FROM `".$ptAssignUser->getTable()."`
                         WHERE `tickets_id` = '".$ticket->getID()."'
                           AND `users_id`='".$d["users_id"]."'
                           AND `delay` IS NULL";

               $result    = $DB->query($query);
               $datedebut = '';
               $input = array();
               if ($result && $DB->numrows($result)) {
                  $datedebut = $DB->result($result, 0, 'datedebut');
                  $input['id'] = $DB->result($result, 0, 'id');
               } else {
                  return;
               }

               if (!$datedebut) {
                  $delay = 0;
               // Utilisation calendrier
               } else if ($calendars_id>0 && $calendar->getFromDB($calendars_id)) {
                  $delay = $calendar->getActiveTimeBetween ($datedebut, $_SESSION["glpi_currenttime"]);
               } else {
                  // cas 24/24 - 7/7
                  $delay = strtotime($_SESSION["glpi_currenttime"])-strtotime($datedebut);
               }

               $input['delay'] = $delay;
               $ptAssignUser->update($input);
            }
         }
      }
   }

   static function deleteUserTicket(Ticket_User $item) {
      global $DB;

      $ticket = new Ticket();
      $ptAssignUser = new PluginTimelineticketAssignUser();

      $ticket->getFromDB($item->fields['tickets_id']);

      $calendar = new Calendar();
      $calendars_id = Entity::getUsedConfig('calendars_id', $ticket->fields['entities_id']);

      $query = "SELECT MAX(`date`) AS datedebut, id
                FROM `".$ptAssignUser->getTable()."`
                WHERE `tickets_id` = '".$item->fields['tickets_id']."'
                  AND `users_id`='".$item->fields['users_id']."'
                  AND `delay` IS NULL";

      $result    = $DB->query($query);
      $datedebut = '';
      $input = array();
      if ($result && $DB->numrows($result)) {
         $datedebut = $DB->result($result, 0, 'datedebut');
         $input['id'] = $DB->result($result, 0, 'id');
      } else {
         return;
      }

      if (!$datedebut) {
         $delay = 0;
      // Utilisation calendrier
      } else if ($calendars_id>0 && $calendar->getFromDB($calendars_id)) {
         $delay = $calendar->getActiveTimeBetween ($datedebut, $_SESSION["glpi_currenttime"]);
      } else {
         // cas 24/24 - 7/7
         $delay = strtotime($_SESSION["glpi_currenttime"])-strtotime($datedebut);
      }

      $input['delay'] = $delay;
      $ptAssignUser->update($input);

   }
}

?>