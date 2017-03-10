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

class PluginTimelineticketToolbox {


   /**
    * Return array with all data
    *
    * @param Ticket $ticket
    * @param type $type 'user' or 'group'
    * @param int|type $withblank option to fill blank zones
    * @return type
    */
   static function getDetails(Ticket $ticket, $type, $withblank=1) {

      if ($type == 'group') {
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
      } else if ($type == 'user') {
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
      }

      $ptState = new PluginTimelineticketState();

      $a_ret = PluginTimelineticketDisplay::getTotaltimeEnddate($ticket);
      $totaltime = $a_ret['totaltime'];

      if ($type == 'group') {
         $ptItem = new PluginTimelineticketAssignGroup();
      } else if ($type == 'user') {
         $ptItem = new PluginTimelineticketAssignUser();
      }

      $a_states = array();
      $a_item_palette = array();
      $a_dbstates = $ptState->find("`tickets_id`='".$ticket->getField('id')."'", "date");
      $end_previous = 0;
      foreach ($a_dbstates as $a_dbstate) {
         $end_previous += $a_dbstate['delay'];
         if ($a_dbstate['old_status'] == '') {
            $a_dbstate['old_status'] = 0;
         }
         if (isset($a_states[$end_previous])) {
            $end_previous++;
         }
         $a_states[$end_previous] = $a_dbstate['old_status'];
      }
      if (isset($a_dbstate['new_status'])
              && $a_dbstate['new_status'] != Ticket::CLOSED) {
         $a_states[$totaltime] = $a_dbstate['new_status'];
      }
      $a_itemsections = array();
      $a_dbitems = $ptItem->find("`tickets_id`='".$ticket->getField('id')."'", "`date`");
      foreach ($a_dbitems as $a_dbitem) {

         if ($type == 'group') {
            $items_id = 'groups_id';
         } else if ($type == 'user') {
            $items_id = 'users_id';
         }

         if (!isset($a_itemsections[$a_dbitem[$items_id]])) {
            $a_itemsections[$a_dbitem[$items_id]] = array();
            $last_statedelay = 0;
         } else {
            foreach ($a_itemsections[$a_dbitem[$items_id]] as $data) {
               $last_statedelay = $data['End'];
            }
         }
         if (!isset($a_item_palette[$a_dbitem[$items_id]])) {
            $a_item_palette[$a_dbitem[$items_id]] = array_shift($palette);
         }
         $color_R = $a_item_palette[$a_dbitem[$items_id]][0];
         $color_G = $a_item_palette[$a_dbitem[$items_id]][1];
         $color_B = $a_item_palette[$a_dbitem[$items_id]][2];

         $gbegin = $a_dbitem['begin'];
         if ($a_dbitem['delay'] == '') {
            $gdelay = $totaltime;
         } else {
            $gdelay = $a_dbitem['begin'] + $a_dbitem['delay'];
         }
         $mem = 0;
         $old_delay = 0;
         foreach ($a_states as $delay=>$statusname) {
            if ($mem == 1) {
               if ($gdelay > $delay) { // all time of the state
                  $a_itemsections[$a_dbitem[$items_id]][] = array(
                      'Start' => $gbegin,
                      'End'   => $delay,
                      "Caption"=>"",
                      "Status" => $statusname,
                      "R"=>$color_R,
                      "G"=>$color_G,
                      "B"=>$color_B
                  );
                  $gbegin = $delay;
               } else if ($gdelay == $delay) { // end of status = end of group
                  $a_itemsections[$a_dbitem[$items_id]][] = array(
                      'Start' => $gbegin,
                      'End'   => $delay,
                      "Caption"=>"",
                      "Status" => $statusname,
                      "R"=>$color_R,
                      "G"=>$color_G,
                      "B"=>$color_B
                  );
                  $mem = 2;
               } else { // end of status is after end of group
                  $a_itemsections[$a_dbitem[$items_id]][] = array(
                      'Start' => $gbegin,
                      'End'   => $gdelay,
                      "Caption"=>"",
                      "Status" => $statusname,
                      "R"=>$color_R,
                      "G"=>$color_G,
                      "B"=>$color_B
                  );
                  $mem = 2;
               }
            } else if ($mem == 0
                    && $gbegin < $delay) {
               if ($withblank
                       && $gbegin != $last_statedelay) {
                  $a_itemsections[$a_dbitem[$items_id]][] = array(
                      'Start' => $last_statedelay,
                      'End'   => $gbegin,
                      "Caption"=>"",
                      "Status" => "",
                      "R"=>235,
                      "G"=>235,
                      "B"=>235
                  );
               }
               if ($gdelay > $delay) { // all time of the state
                  $a_itemsections[$a_dbitem[$items_id]][] = array(
                      'Start' => $gbegin,
                      'End'   => $delay,
                      "Caption"=>"",
                      "Status" => $statusname,
                      "R"=>$color_R,
                      "G"=>$color_G,
                      "B"=>$color_B
                  );
                  $gbegin = $delay;
                  $mem = 1;
               } else if ($gdelay == $delay) { // end of status = end of group
                  $a_itemsections[$a_dbitem[$items_id]][] = array(
                      'Start' => $gbegin,
                      'End'   => $delay,
                      "Caption"=>"",
                      "Status" => $statusname,
                      "R"=>$color_R,
                      "G"=>$color_G,
                      "B"=>$color_B
                  );
                  $mem = 2;
               } else { // end of status is after end of group
                  $a_itemsections[$a_dbitem[$items_id]][] = array(
                      'Start' => $gbegin,
                      'End'   => $gdelay,
                      "Caption"=>"",
                      "Status" => $statusname,
                      "R"=>$color_R,
                      "G"=>$color_G,
                      "B"=>$color_B
                  );
                  $mem = 2;
               }
            }
            $old_delay = $delay;
         }
      }
      if ($withblank) {
         end($a_states);
         $verylastdelayStateDB = key($a_states);
         foreach ($a_itemsections as $items_id=>$data_f) {
               $last = 0;
               $R = 235;
               $G = 235;
               $B = 235;
               $statusname = '';
            $a_end = end($data_f);
               $last = $a_end['End'];
               if ($ticket->fields['status'] != Ticket::CLOSED
                       && $last == $verylastdelayStateDB) {
                  $R = $a_end['R'];
                  $G = $a_end['G'];
                  $B = $a_end['B'];
                  $statusname = $a_end['Status'];
               }
               if ($last < $totaltime) {
                  $a_itemsections[$items_id][] = array(
                      'Start' => $last,
                      'End'   => $totaltime,
                      "Caption"=>"",
                      "Status" => $statusname,
                      "R"=>$R,
                      "G"=>$G,
                      "B"=>$B
                  );
               }
            }
         }
      return $a_itemsections;
   }


   /**
    * Used to display each status time used for each group/user
    *
    *
    * @param Ticket $ticket
    * @param $type
    */
   static function ShowDetail(Ticket $ticket, $type) {

      $ptState = new PluginTimelineticketState();

      if ($type == 'group') {
         $ptItem = new PluginTimelineticketAssignGroup();
      } else if ($type == 'user') {
         $ptItem = new PluginTimelineticketAssignUser();
      }

      $a_states = $ptState->find("`tickets_id`='".$ticket->getField('id')."'", "`date`");

      $a_state_delays = array();
      $a_state_num = array();
      $delay = 0;

      $list_status = Ticket::getAllStatusArray();

      $status = "new";
      foreach ($a_states as $array) {
         $delay += $array['delay'];
         $a_state_delays[$delay] = $array['old_status'];
         $a_state_num[] = $delay;
      }
      $a_state_num[] = $delay;
      $last_delay = $delay;

      $a_groups = $ptItem->find("`tickets_id`='".$ticket->getField('id')."'", "`date`");

      echo "<table class='tab_cadre_fixe' width='100%'>";
      echo "<tr class='tab_bg_1'>";
      echo "<th colspan='".(count($list_status) + 1)."'>";
      _e('Result details');
      if ($type == 'group') {
         echo " (".__('Groups in charge of the ticket', 'timelineticket').")";
      } else if ($type == 'user') {
         echo " (".__('Technicians in charge of the ticket', 'timelineticket').")";
      }
      echo "</th>";
      echo "</tr>";

      echo "</tr>";
      echo "<th>";
      echo "</th>";
      foreach ($list_status as $name) {
         echo "<th>";
         echo $name;
         echo "</th>";
      }
      echo "</tr>";

      if ($type == 'group') {
         $a_details = PluginTimelineticketToolbox::getDetails($ticket, 'group', false);
      } else if ($type == 'user') {
         $a_details = PluginTimelineticketToolbox::getDetails($ticket, 'user', false);
      }

      foreach ($a_details as $items_id=>$a_detail) {
         $a_status = array();
         foreach ($a_detail as $data) {
            if (!isset($a_status[$data['Status']])) {
               $a_status[$data['Status']] = 0;
            }
            $a_status[$data['Status']] += ($data['End'] - $data['Start']);
         }
         echo "<tr class='tab_bg_1'>";
         if ($type == 'group') {
            echo "<td>".Dropdown::getDropdownName("glpi_groups", $items_id)."</td>";
         } else if ($type == 'user') {
            echo "<td>".getUserName($items_id)."</td>";
         }
         foreach ($list_status as $status=>$name) {
            echo "<td>";
            if (isset($a_status[$status])) {
               echo Html::timestampToString($a_status[$status], true);
            }
            echo "</td>";
         }
         echo "</tr>";
      }
      echo "</table>";
   }

}
?>