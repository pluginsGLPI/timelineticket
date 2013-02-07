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

class PluginTimelineticketAssignGroup extends CommonDBTM {

   
   /*
    * type = new or delete
    */
   function createGroup(Ticket $ticket, $date, $groups_id, $type) {

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
                          'groups_id'   => $groups_id,
                          'begin'       => $begin));
         
      } else if ($type == 'delete') {
         $a_dbentry = $this->find("`tickets_id`='".$ticket->getField("id")."'
            AND `groups_id`='".$groups_id."'
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
   
   
   
   function showTimeline($tickets_id) {
      global $CFG_GLPI, $LANG;
      
      $palette = array(
            array('250', '151', '186'),
            array('255', '211', '112'),
            array('183', '210', '118'),
            array('117', '199', '187'),
            array('188', '168', '208'),
            array('186', '213', '118'),
            array('124', '169', '0'),
            array('168', '208', '49'),
            array('239', '215', '113'),
            array('235', '155', '0'),
            array('235', '249', '255'),
            array('193', '228', '250'),
            array('164', '217', '250'),
            array('88', '195', '240'),
            array('0', '156', '231'),
            array('198', '229', '111'),
            array('234', '38', '115'),
            array('245', '122', '160'),
            array('255', '208', '220')
         );


      
      $ticket = new Ticket();
      $ticket->getFromDB($tickets_id);
      
      $end = date("Y-m-d H:i:s");
      if ($ticket->fields['status'] == 'closed') {
          $end = $ticket->fields['closedate'];
      }

      $totaltime = 0;
      if ($ticket->fields['slas_id'] != 0) { // Have SLA
         $sla = new SLA();
         $sla->getFromDB($ticket->fields['slas_id']);
         $currenttime = $sla->getActiveTimeBetween($ticket->fields['date'], date('Y-m-d H:i:s'));
         $totaltime = $sla->getActiveTimeBetween($ticket->fields['date'], $end);
      } else {
         $calendars_id = EntityData::getUsedConfig('calendars_id', $ticket->fields['entities_id']);
         if ($calendars_id != 0) { // Ticket entity have calendar
            $calendar = new Calendar();
            $calendar->getFromDB($calendars_id);
            $currenttime = $calendar->getActiveTimeBetween($ticket->fields['date'], date('Y-m-d H:i:s'));
            $totaltime = $calendar->getActiveTimeBetween($ticket->fields['date'], $end);
         } else { // No calendar
            $currenttime = strtotime(date('Y-m-d H:i:s')) - strtotime($ticket->fields['date']);
            $totaltime = strtotime($end) - strtotime($ticket->fields['date']);
         }
      }
      
      /* pChart library inclusions */
      include_once(GLPI_ROOT."/plugins/timelineticket/lib/pChart2.1.3/class/pData.class.php");
      include_once(GLPI_ROOT."/plugins/timelineticket/lib/pChart2.1.3/class/pDraw.class.php");
      include_once(GLPI_ROOT."/plugins/timelineticket/lib/pChart2.1.3/class/pImage.class.php");
      include_once(GLPI_ROOT."/plugins/timelineticket/lib/pChart2.1.3/class/pIndicator.class.php");

      /* Create and populate the pData object */
      $MyData = new pData();  
      /* Create the pChart object */
      $myPicture = new pImage(820,29,$MyData);
      /* Create the pIndicator object */
      $Indicator = new pIndicator($myPicture);
      $myPicture->setFontProperties(array("FontName"=>GLPI_ROOT."/plugins/timelineticket/lib/pChart2.1.3/fonts/pf_arma_five.ttf","FontSize"=>6));
      /* Define the indicator sections */
      $IndicatorSections = array();
      $_groupsfinished = array();

      $end_date = '';
      if ($ticket->fields['status'] != 'closed') {
         $end_date = strtotime(date('Y-m-d H:i:s')) - strtotime($ticket->fields['date']);
      } else {
         $end_date = strtotime($ticket->fields['closedate']) - strtotime($ticket->fields['date']);
      }      
      
      $a_groups = $this->find("`tickets_id`='".$ticket->getField('id')."'", "`date`");
      $a_group_end = array();
      $a_groups_list = array();
      $a_group_palette = array();
      foreach($a_groups as $data) {
         if (!isset($a_group_palette[$data['groups_id']])) {
            $a_group_palette[$data['groups_id']] = array_shift($palette);
         }
         $color_R = $a_group_palette[$data['groups_id']][0];
         $color_G = $a_group_palette[$data['groups_id']][1];
         $color_B = $a_group_palette[$data['groups_id']][2];
         
         $a_groups_list[$data['groups_id']] = $data['groups_id'];
         if (!isset($a_group_end[$data['groups_id']])) {
            $a_group_end[$data['groups_id']] = 0;
         }
         if ($data['begin'] != $a_group_end[$data['groups_id']]) {
            $IndicatorSections[$data['groups_id']][] = array(
                  "Start"=>$a_group_end[$data['groups_id']],
                  "End"=>$data['begin'],
                  "Caption"=>"",
                  "R"=>235,
                  "G"=>235,
                  "B"=>235);
         }
         if (is_null($data['delay'])) {
            $data['delay'] = $totaltime - $data['begin'];
            $_groupsfinished[$data['groups_id']] = false;
         } else {
            $_groupsfinished[$data['groups_id']] = true;
         }
         $IndicatorSections[$data['groups_id']][] = array(
                  "Start"=>$data['begin'],
                  "End"=>($data['begin'] + $data['delay']),
                  "Caption"=>"",
                  "R"=>$color_R,
                  "G"=>$color_G,
                  "B"=>$color_B);
         $a_group_end[$data['groups_id']] = ($data['begin'] + $data['delay']);
      }
      echo "<pre>";

      foreach ($a_groups_list as $groups_id) {
         if ($a_group_end[$groups_id] != $end_date) {
            $IndicatorSections[$groups_id][] = array("Start"=>$a_group_end[$groups_id],"End"=>($end_date ),"Caption"=>"","R"=>235,"G"=>235,"B"=>235);
         }
      }

      echo "<tr>";
      echo "<th colspan='2'>".$LANG['setup'][248]."</th>";
      echo "</tr>";
      
      foreach ($IndicatorSections as $groups_id=>$array) {
         echo "<tr>";
         echo "<td width='100'>";
         echo Dropdown::getDropdownName("glpi_groups", $groups_id);
         echo "</td>";
         echo "<td>";
         if ($ticket->fields['status'] != 'closed'
                 && $_groupsfinished[$groups_id] === false) {

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

         $filename = $uid=Session::getLoginUserID(false)."_testgroup".$groups_id;
         $myPicture->render(GLPI_GRAPH_DIR."/".$filename.".png");


         echo "<img src='".$CFG_GLPI['root_doc']."/front/graph.send.php?file=".$filename.".png'><br/>";
         echo "</td>";
         echo "</tr>";
      }
      
   }
   
   
   
   static function addGroupTicket(Group_Ticket $item) {
      if ($item->fields['type'] == 2) {
         $ptAssignGroup = new PluginTimelineticketAssignGroup();
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
                  
         $input = array();
         $input['tickets_id'] = $item->fields['tickets_id'];
         $input['groups_id'] = $item->fields['groups_id'];
         $input['date'] = date('Y-m-d H:i:s');
         $input['begin'] = $delay;
         $ptAssignGroup->add($input);
      }
   }
   
   
   static function deleteGroupTicket(Group_Ticket $item) {
      global $DB;
      
      $ticket = new Ticket();
      $ptAssignGroup = new PluginTimelineticketAssignGroup();
      
      $ticket->getFromDB($item->fields['tickets_id']);
      
      $calendar = new Calendar();
      $calendars_id = EntityData::getUsedConfig('calendars_id', $ticket->fields['entities_id']);

      $query = "SELECT MAX(`date`) AS datedebut, id
                FROM `".$ptAssignGroup->getTable()."`
                WHERE `tickets_id` = '".$item->fields['tickets_id']."' 
                  AND `groups_id`='".$item->fields['groups_id']."'
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
      $ptAssignGroup->update($input);      
      
   }
}

?>