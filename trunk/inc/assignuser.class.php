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
         $calendars_id = EntityData::getUsedConfig('calendars_id', $ticket->fields['entities_id']);
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
            $calendars_id = EntityData::getUsedConfig('calendars_id', $ticket->fields['entities_id']);
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
      global $CFG_GLPI, $LANG;
      
      $palette = array(
            array('164', '53', '86'),
            array('137', '123', '78'),
            array('192', '114', '65'),
            array('143', '102', '98'),
            array('175', '105', '93'),
            array('186', '127', '61'),
            array('174', '104', '92'),
            array('213', '113', '63'),
            array('185', '168', '122'),
            array('233', '168', '112'),
            array('199', '133', '99'),
            array('80', '24', '69'),
            array('133', '39', '65'),
            array('120', '22', '61'),
            array('114', '59', '82'),
            array('245', '229', '195')
          );

      /* Create and populate the pData object */
      $MyData = new pData();  
      /* Create the pChart object */
      $myPicture = new pImage(820,29,$MyData);
      /* Create the pIndicator object */
      $Indicator = new pIndicator($myPicture);
      $myPicture->setFontProperties(array("FontName"=>GLPI_ROOT."/plugins/timelineticket/lib/pChart2.1.3/fonts/pf_arma_five.ttf","FontSize"=>6));
      /* Define the indicator sections */
      $IndicatorSections = array();
      $_usersfinished = array();
      
      $a_users = $this->find("`tickets_id`='".$ticket->getField('id')."'", "`date`");
      $a_user_end = array();
      $a_users_list = array();
      $a_user_palette = array();
      foreach($a_users as $data) {
         if (!isset($a_user_palette[$data['users_id']])) {
            $a_user_palette[$data['users_id']] = array_shift($palette);
         }
         $color_R = $a_user_palette[$data['users_id']][0];
         $color_G = $a_user_palette[$data['users_id']][1];
         $color_B = $a_user_palette[$data['users_id']][2];
         
         $a_users_list[$data['users_id']] = $data['users_id'];
         if (!isset($a_user_end[$data['users_id']])) {
            $a_user_end[$data['users_id']] = 0;
         }
         if ($data['begin'] != $a_user_end[$data['users_id']]) {
            $IndicatorSections[$data['users_id']][] = array(
                        "Start"=>$a_user_end[$data['users_id']],
                        "End"=>$data['begin'],
                        "Caption"=>"",
                        "R"=>235, 
                        "G"=>235,
                        "B"=>235);
         }
         if (is_null($data['delay'])) {
            $data['delay'] = $params['totaltime'] - $data['begin'];
            $_usersfinished[$data['users_id']] = false;
         } else {
            $_usersfinished[$data['users_id']] = true;
         }
         $IndicatorSections[$data['users_id']][] = array(
                        "Start"=>$data['begin'],
                        "End"=>($data['begin'] + $data['delay']),
                        "Caption"=>"",
                        "R"=>$color_R,
                        "G"=>$color_G,
                        "B"=>$color_B);
         $a_user_end[$data['users_id']] = ($data['begin'] + $data['delay']);
      }
      echo "<pre>";

      foreach ($a_users_list as $users_id) {
         if ($a_user_end[$users_id] != $params['end_date']) {
            $IndicatorSections[$users_id][] = array("Start"=>$a_user_end[$users_id],
                                                   "End"=>($params['end_date']),
                                                   "Caption"=>"",
                                                   "R"=>235,
                                                   "G"=>235,
                                                   "B"=>235);
         }
      }

      echo "<tr>";
      echo "<th colspan='2'>";
      if (count($a_users_list) > 1) {
         echo $LANG['plugin_timelineticket'][16];
      } else {
         echo $LANG['setup'][239];
      }
      echo"</th>";
      echo "</tr>";
      
      foreach ($IndicatorSections as $users_id =>$array) {
         echo "<tr class='tab_bg_2'>";
         echo "<td width='100'>";
         echo getUsername($users_id);
         echo "</td>";
         echo "<td>";
         if ($ticket->fields['status'] != 'closed'
                 && $_usersfinished[$users_id] === false) {

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
         $calendars_id = EntityData::getUsedConfig('calendars_id', $ticket->fields['entities_id']);
         $datedebut = $ticket->fields['date'];
         if ($calendars_id>0 && $calendar->getFromDB($calendars_id)) {
            $delay = $calendar->getActiveTimeBetween ($datedebut, date('Y-m-d H:i:s'));
         } else {
            // cas 24/24 - 7/7
            $delay = strtotime(date('Y-m-d H:i:s'))-strtotime($datedebut);
         }
         
         $ok = 1;
         
         $ptConfig = new PluginTimelineticketConfig();
         $ptConfig->getFromDB(1);
         if ($ptConfig->fields["drop_waiting"] == 1
               && $ticket->fields["status"] == "waiting") {
            $ok = 0;
         }
         if ($ok) {
            $input = array();
            $input['tickets_id'] = $item->fields['tickets_id'];
            $input['users_id'] = $item->fields['users_id'];
            $input['date'] = date('Y-m-d H:i:s');
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
      if ($ptConfig->fields["drop_waiting"] == 1) {
         $ok = 1;
      }
 
      if ($ok && in_array("status", $ticket->updates)
            && isset($ticket->oldvalues["status"])
               && $ticket->oldvalues["status"] == "waiting") {
         if ($ticket->countUsers(CommonITILObject::ASSIGN)) {
            foreach ($ticket->getUsers(CommonITILObject::ASSIGN) as $d) {
               $ptAssignUser = new PluginTimelineticketAssignUser();
               $calendar = new Calendar();
               $calendars_id = EntityData::getUsedConfig('calendars_id', $ticket->fields['entities_id']);
               $datedebut = $ticket->fields['date'];
               if ($calendars_id>0 && $calendar->getFromDB($calendars_id)) {
                  $delay = $calendar->getActiveTimeBetween ($datedebut, date('Y-m-d H:i:s'));
               } else {
                  // cas 24/24 - 7/7
                  $delay = strtotime(date('Y-m-d H:i:s'))-strtotime($datedebut);
               }
   
               $input = array();
               $input['tickets_id'] = $ticket->getID();
               $input['users_id'] = $d["users_id"];
               $input['date'] = date('Y-m-d H:i:s');
               $input['begin'] = $delay;
               $ptAssignUser->add($input);
            }
         }
      } else if ($ok && in_array("status", $ticket->updates) 
            && isset($ticket->fields["status"])
               && $ticket->fields["status"] == "waiting") {
         if ($ticket->countUsers(CommonITILObject::ASSIGN)) {
            foreach ($ticket->getUsers(CommonITILObject::ASSIGN) as $d) {
               
               $calendar = new Calendar();
               $calendars_id = EntityData::getUsedConfig('calendars_id', $ticket->fields['entities_id']);
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
                  $delay = $calendar->getActiveTimeBetween ($datedebut, date('Y-m-d H:i:s'));
               } else {
                  // cas 24/24 - 7/7
                  $delay = strtotime(date('Y-m-d H:i:s'))-strtotime($datedebut);
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
      $calendars_id = EntityData::getUsedConfig('calendars_id', $ticket->fields['entities_id']);

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
         $delay = $calendar->getActiveTimeBetween ($datedebut, date('Y-m-d H:i:s'));
      } else {
         // cas 24/24 - 7/7
         $delay = strtotime(date('Y-m-d H:i:s'))-strtotime($datedebut);
      }

      $input['delay'] = $delay;
      $ptAssignUser->update($input);      
      
   }
   
   
   
   /**
    * Used to display each status time used for each technician
    * 
    * 
    * @param Ticket $ticket
    */
   function ShowDetail(Ticket $ticket) {
      global $LANG;
      
      echo "<br/>";
      $ptState = new PluginTimelineticketState();
      
      $a_states = $ptState->find("`tickets_id`='".$ticket->getField('id')."'", "`date`");
      
      $a_state_delays = array();
      $a_state_num = array();
      $delay = 0;
      
      $status = "new";
      foreach ($a_states as $array) {
         $delay += $array['delay'];
         $a_state_delays[$delay] = $array['new_status'];
         $a_state_num[] = $delay;
      }
      $a_state_num[] = $delay;
      $last_delay = $delay;
      
      $a_users = $this->find("`tickets_id`='".$ticket->getField('id')."'", "`date`");

      echo "<table class='tab_cadrehov' width='100%'>";
      echo "<tr class='tab_bg_1'>";
      echo "<th colspan='2'>";
      echo $LANG['rulesengine'][82];
      echo " (".$LANG['plugin_timelineticket'][16].")";
      echo "</th>";
      echo "</tr>";

      foreach ($a_users as $a_user) {
         echo "<tr class='tab_bg_1'>";
         echo "<td>".getUsername($a_user['users_id'])."</td>";
         echo "<td>";
         
         $begin = $a_user['begin'];
         $delay = $a_user['delay'];
         if (is_null($a_user['delay'])) {
            $delay = $last_delay;
         }
         
         $num = 2;
         foreach ($a_state_delays as $key_delay=>$value_status) {
            if ($begin >= $a_state_num[$num]
                    || $delay == 0) {
               // nothing
            } else if ($begin > $key_delay) {
               if (($begin + $delay) >= ($a_state_num[$num])) {
                  // all end of delay of this state
                  echo Ticket::getStatus($value_status)." : ".Html::timestampToString(
                          ($a_state_num[$num] - $begin), true)."<br/>";
                  $delay = $delay - ($a_state_num[$num] - $begin);
                  $begin = $a_state_num[$num];
               } else {
                  // Part of status
                  $begin_delay = $begin - $key_delay;
                  $end_delay = $a_state_num[$num] - ($begin + $delay);
                  echo Ticket::getStatus($value_status)." : ".Html::timestampToString(
                          ($a_state_num[$num] - $begin_delay - $end_delay), true)."<br/>";
                  $begin += $a_state_num[$num] - $begin_delay - $end_delay;
                  $delay = 0;
               }
            } else { // $begin == $key_delay
               if (($begin + $delay) >= ($a_state_num[$num])) {
                  // All delay of this state
                  echo Ticket::getStatus($value_status)." : ".Html::timestampToString(
                          ($a_state_num[$num] - $key_delay), true)."<br/>";
                  $delay -= ($a_state_num[$num] - $key_delay);
                  $begin = $a_state_num[$num];
               } else {
                  // Part of status
                  
                  echo Ticket::getStatus($value_status)." : ".Html::timestampToString(
                          $delay, true)."<br/>";
                  $begin += $delay;
                  $delay = 0;
               }
            }
            $num++;
        }
         echo "</td>";
         echo "</tr>";
      }
      echo "</table>";      
   }
}

?>