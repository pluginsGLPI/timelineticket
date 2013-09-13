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

class PluginTimelineticketState extends CommonDBTM {


   // Method permitting to delete occurences [if the tracking is deleted]
   function cleanFollowup($ticket) {
      global $DB;

      $query = "DELETE
                FROM `".$this->getTable()."`
                WHERE `tickets_id` = '$ticket' ";
      $DB->query($query);
   }

   

   // Method permitting to save the current status
   function createFollowup(Ticket $ticket, $date, $old_status, $new_status) {
      global $DB;

      // trouver les dates pour le calcul du délai
      $idticket = $ticket->getField("id");

      if (empty($old_status)) {
         $delay = 0;
      } else {
         $query = "SELECT MAX(`date`) AS datedebut
                   FROM `".$this->getTable()."`
                   WHERE `tickets_id` = '$idticket' ";

         $result    = $DB->query($query);
         $datedebut = '';
         if ($result && $DB->numrows($result)) {
            $datedebut = $DB->result($result, 0, 'datedebut');
         }
         $datefin = $date;

         $calendar = new Calendar();
         $calendars_id = EntityData::getUsedConfig('calendars_id', $ticket->fields['entities_id']);

         if (!$datedebut) {
            $delay = 0;
         // Utilisation calendrier
         } else if ($calendars_id>0 && $calendar->getFromDB($calendars_id)) {
            $delay = $calendar->getActiveTimeBetween ($datedebut, $datefin);
         } else {
            // cas 24/24 - 7/7
            $delay = strtotime($datefin)-strtotime($datedebut);
         }
      }
      $this->add(array('tickets_id'  => $ticket->getField("id"),
                       'date'        => $date,
                       'old_status'  => $old_status,
                       'new_status'  => $new_status,
                       'delay'       => $delay));
   }

   
   
   static function showHistory (Ticket $ticket) {
      global $DB, $LANG;
      
      $query = "SELECT *
                FROM `glpi_plugin_timelineticket_states`
                WHERE `tickets_id` = '".$ticket->getField('id')."'
                ORDER BY `id` DESC";

      $req = $DB->request($query);
      if (!$req->numrows()) {
         echo "<tr class='tab_bg_1 center'><td>".$LANG['search'][15]."</td></tr>";
      } else {

         echo "<tr><th>".$LANG['rulesengine'][82]."</th></tr>";
         echo "<tr class='tab_bg_2'><td>";
         
         echo "<table class='tab_cadrehov' width='100%'>";
         echo "<tr>";
         echo "<th>".$LANG['common'][27]."</th>";
         echo "<th>".$LANG['joblist'][0]."</th>";
         echo "<th>".$LANG['plugin_timelineticket'][4]."</th>";
         echo "</tr>";

         foreach ($req as $data) {
            if ($data['old_status'] != '') {
               echo "<tr class='tab_bg_1'>";
               echo "<td>".Html::convDateTime($data['date'])."</td>";
               echo "<td>".Ticket::getStatus($data['old_status'])."</td>";
               echo "<td class='right'>".Html::timestampToString($data['delay'], true)."</td>";
               echo "</tr>";
            }
         }
         
         echo "</table>";
         echo "</td>";
         echo "</tr>";
      }
   }

   
   
   function showTimeline (Ticket $ticket, $params = array()) {
      global $DB, $LANG, $CFG_GLPI;
 
      /* Create and populate the pData object */
      $MyData = new pData();  
      /* Create the pChart object */
      $myPicture = new pImage(820,29,$MyData);
      /* Create the pIndicator object */
      $Indicator = new pIndicator($myPicture);
 
      $myPicture->setFontProperties(array("FontName"=>GLPI_ROOT."/plugins/timelineticket/lib/pChart2.1.3/fonts/pf_arma_five.ttf",
                                          "FontSize"=>6));
 
      /* Define the indicator sections */
      $IndicatorSections   = array();

      $a_states = array('new', 'assign', 'plan', 'waiting', 'solved', 'closed');
      $a_status_color = array();
      $a_status_color['new'] = array('R'=>197, 'G'=>204, 'B'=>79);
      $a_status_color['assign'] = array('R'=>38, 'G'=>174, 'B'=>38);
      $a_status_color['plan'] = array('R'=>255, 'G'=>102, 'B'=>0);
      $a_status_color['waiting'] = array('R'=>229, 'G'=>184, 'B'=>0);
      $a_status_color['solved'] = array('R'=>83, 'G'=>141, 'B'=>184);
      $a_status_color['closed'] = array('R'=>51, 'G'=>51, 'B'=>51);
      
      $delaystatus = array();
      
      foreach ($a_states as $status) {
         $IndicatorSections[$status] = '';
         $delaystatus[$status] = 0;
      }

      $a_status = $this->find("`tickets_id`='".$ticket->getField('id')."'", "`date`");
      $begin = 0;
      
      if ($params['totaltime'] > 0) {
         foreach ($a_status as $data) {
            foreach ($a_states as $statusSection) { 
               $R = 235;
               $G = 235;
               $B = 235;
               $caption = '';
               if ($statusSection == $data['old_status']) {
                  $R = $a_status_color[$statusSection]['R'];
                  $G = $a_status_color[$statusSection]['G'];
                  $B = $a_status_color[$statusSection]['B'];

                  //$caption = $status;
                  $delaystatus[$statusSection] += round(( $data['delay'] * 100) / $params['totaltime'], 2);
               }
               $IndicatorSections[$statusSection][] = array("Start"=>$begin,
                                                            "End"=>($begin + $data['delay']),
                                                            "Caption"=>$caption,
                                                            "R"=>$R,
                                                            "G"=>$G,
                                                            "B"=>$B);
            }
            $begin += $data['delay'];
         }
         if ($ticket->fields['status'] != 'closed') {
            foreach ($a_states as $statusSection) { 
               $R = 235;
               $G = 235;
               $B = 235;
               $caption = ' ';
               if ($statusSection == $ticket->fields['status']) {
                  $R = $a_status_color[$statusSection]['R'];
                  $G = $a_status_color[$statusSection]['G'];
                  $B = $a_status_color[$statusSection]['B'];
                  //$caption = $status;
                  $delaystatus[$statusSection] += round(( ($params['totaltime'] - $begin) * 100) / $params['totaltime'], 2);
               }
               $IndicatorSections[$statusSection][] = array("Start"=>$begin,
                                                            "End"=>($begin + ($params['totaltime'] - $begin)),
                                                            "Caption"=>$caption,
                                                            "R"=>$R,
                                                            "G"=>$G,
                                                            "B"=>$B);
            }
         }
      }
      if (count($a_status) > 1) {
         foreach ($a_states as $status) {
            echo "<tr class='tab_bg_2'>";
            echo "<td width='100'>";
            echo Ticket::getStatus($status);
            echo "<br/>(".$delaystatus[$status]."%)";
            echo "</td>";
            echo "<td>";
            
            if ($ticket->fields['status'] != 'closed') {
               $IndicatorSettings = array("Values"=>array(100,201),
                                          "CaptionPosition"=>INDICATOR_CAPTION_BOTTOM, 
                                          "CaptionLayout"=>INDICATOR_CAPTION_DEFAULT, 
                                          "CaptionR"=>0, 
                                          "CaptionG"=>0,
                                          "CaptionB"=>0,
                                          "DrawLeftHead"=>FALSE, 
                                          "ValueDisplay"=>false, 
                                          "IndicatorSections"=>$IndicatorSections[$status], 
                                          "SectionsMargin" => 0);
               $Indicator->draw(2,2,805,25,$IndicatorSettings);
            } else {
               $IndicatorSettings = array("Values"=>array(100,201),
                                          "CaptionPosition"=>INDICATOR_CAPTION_BOTTOM, 
                                          "CaptionLayout"=>INDICATOR_CAPTION_DEFAULT, 
                                          "CaptionR"=>0, 
                                          "CaptionG"=>0,
                                          "CaptionB"=>0,
                                          "DrawLeftHead"=>FALSE, 
                                          "DrawRightHead"=>FALSE, 
                                          "ValueDisplay"=>false, 
                                          "IndicatorSections"=>$IndicatorSections[$status], 
                                          "SectionsMargin" => 0);
               $Indicator->draw(2,2,814,25,$IndicatorSettings);
            }

            $filename = $uid=Session::getLoginUserID(false)."_test".$status;
            $myPicture->render(GLPI_GRAPH_DIR."/".$filename.".png");

            echo "<img src='".$CFG_GLPI['root_doc']."/front/graph.send.php?file=".$filename.".png'><br/>";
            echo "</td>";
            echo "</tr>";
         }
      }
      // Display ticket have Due date
      if ($ticket->fields['due_date']
              && (strtotime(date('Y-m-d H:i:s') - strtotime($ticket->fields['due_date'])) > 0)) {

         $calendar = new Calendar();
         $calendars_id = EntityData::getUsedConfig('calendars_id', $ticket->fields['entities_id']);

         if ($calendars_id>0 && $calendar->getFromDB($calendars_id)) {
            $duedate = $calendar->getActiveTimeBetween($ticket->fields['date'], 
                                                       $ticket->fields['due_date']);
            if ($ticket->fields['closedate']) {
               $dateend = $calendar->getActiveTimeBetween($ticket->fields['due_date'], 
                                                          $ticket->fields['closedate']);
            } else {
               $dateend = $calendar->getActiveTimeBetween($ticket->fields['due_date'], 
                                                          date('Y-m-d H:i:s'));
            }
         } else {
            // cas 24/24 - 7/7
            $duedate = strtotime($ticket->fields['due_date'])-strtotime($ticket->fields['date']);
            if ($ticket->fields['closedate']) {
               $dateend = strtotime($ticket->fields['closedate'])-strtotime($ticket->fields['due_date']);
            } else {
               $dateend = strtotime(date('Y-m-d H:i:s'))-strtotime($ticket->fields['due_date']);
            }
         }
         echo "<tr class='tab_bg_2'>";
         echo "<td width='100' class='tab_bg_2_2'>";
         echo $LANG['job'][17];
         echo "<br/>(".round(($dateend * 100) / $params['totaltime'], 2)."%)";
         echo "</td>";
         echo "<td>";
         
         $calendar = new Calendar();
         $calendars_id = EntityData::getUsedConfig('calendars_id', $ticket->fields['entities_id']);

         if ($ticket->fields['status'] != 'closed') {
            
            $IndicatorSettings = array("Values"=>array(100,201),
                                       "CaptionPosition"=>INDICATOR_CAPTION_BOTTOM, 
                                       "CaptionLayout"=>INDICATOR_CAPTION_DEFAULT, 
                                       "CaptionR"=>0, 
                                       "CaptionG"=>0,
                                       "CaptionB"=>0,
                                       "DrawLeftHead"=>FALSE, 
                                       "ValueDisplay"=>false, 
                                       "IndicatorSections"=> array(
                                           array(
                                               "Start"    => 0,
                                               "End"      => $duedate,
                                               "Caption"  => "",
                                               "R"        => 235,
                                               "G"        => 235,
                                               "B"        => 235
                                               ),
                                           array(
                                               "Start"    => $duedate,
                                               "End"      => ($dateend + $duedate),
                                               "Caption"  => "",
                                               "R"        => 255,
                                               "G"        => 0,
                                               "B"        => 0
                                               )
                                       ), 
                                       "SectionsMargin" => 0);
            $Indicator->draw(2,2,805,25,$IndicatorSettings);
         } else {
            $IndicatorSettings = array("Values"=>array(100,201),
                                       "CaptionPosition"=>INDICATOR_CAPTION_BOTTOM, 
                                       "CaptionLayout"=>INDICATOR_CAPTION_DEFAULT, 
                                       "CaptionR"=>0, 
                                       "CaptionG"=>0,
                                       "CaptionB"=>0,
                                       "DrawLeftHead"=>FALSE, 
                                       "DrawRightHead"=>FALSE, 
                                       "ValueDisplay"=>false, 
                                       "IndicatorSections"=> array(
                                           array(
                                               "Start"    => 0,
                                               "End"      => $duedate,
                                               "Caption"  => "",
                                               "R"        => 235,
                                               "G"        => 235,
                                               "B"        => 235
                                               ),
                                           array(
                                               "Start"    => $duedate,
                                               "End"      => ($dateend + $duedate),
                                               "Caption"  => "",
                                               "R"        => 255,
                                               "G"        => 0,
                                               "B"        => 0
                                               )
                                       ), 
                                       "SectionsMargin" => 0);
            $Indicator->draw(2,2,814,25,$IndicatorSettings);
         }
         $filename = $uid=Session::getLoginUserID(false)."_testduedate";
         $myPicture->render(GLPI_GRAPH_DIR."/".$filename.".png");

         echo "<img src='".$CFG_GLPI['root_doc']."/front/graph.send.php?file=".$filename.".png'><br/>";
         echo "</td>";
         echo "</tr>";
      }
   }
   
   
   
   /*
    * Function to reconstruct timeline for all tickets
    */
   function reconstructTimeline() {
      global $DB, $LANG;
      
      $ticket = new Ticket();
      $query = "TRUNCATE `".$this->getTable()."`";
      $DB->query($query);
      
      $status_translation = array();
      $status_translation[$LANG['joblist'][9]]  = 'new';
      $status_translation[$LANG['joblist'][18]] = 'assign';
      $status_translation[$LANG['joblist'][19]] = 'plan';
      $status_translation[$LANG['joblist'][26]] = 'waiting';
      $status_translation[$LANG['joblist'][32]] = 'solved';
      $status_translation[$LANG['joblist'][33]] = 'closed';
      foreach (glob(GLPI_ROOT.'/locales/*.php') as $file) {
         include_once($file);
         $status_translation[$LANG['joblist'][9]]  = 'new';
         $status_translation[$LANG['joblist'][18]] = 'assign';
         $status_translation[$LANG['joblist'][19]] = 'plan';
         $status_translation[$LANG['joblist'][26]] = 'waiting';
         $status_translation[$LANG['joblist'][32]] = 'solved';
         $status_translation[$LANG['joblist'][33]] = 'closed';
      }
      //echo "<pre>";print_r($status_translation);
      
      $query = "SELECT * FROM `glpi_tickets`
         ORDER BY `date`";
      $result = $DB->query($query);
      while ($data = $DB->fetch_array($result)) {
         $ticket->getFromDB($data['id']);
         $this->createFollowup($ticket, $data['date'], '', 'new');
         
         $queryl = "SELECT * FROM `glpi_logs`
            WHERE `itemtype`='Ticket'
               AND `items_id`='".$data['id']."'
               AND `id_search_option`='12'
         ORDER BY `id`";
         $resultl=$DB->query($queryl);
         $first = 0;
         while ($datal = $DB->fetch_array($resultl)) {
            
            if ($first == 0) {               
               if ($datal['old_value'] != 'new') {
                  $this->createFollowup($ticket, $data['date'], 'new', $status_translation[$datal['old_value']]);
               }
            } 
            $this->createFollowup($ticket, $datal['date_mod'],
                                 $status_translation[$datal['old_value']], 
                                 $status_translation[$datal['new_value']]);
            
            $first++;
         }
      }      
   }
}
?>
