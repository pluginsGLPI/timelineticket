<?php

/*
   ------------------------------------------------------------------------
   TimelineTicket
   Copyright (C) 2013-2022 by the TimelineTicket Development Team.

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
   @copyright Copyright (c) 2013-2022 TimelineTicket team
   @license   AGPL License 3.0 or (at your option) any later version
              http://www.gnu.org/licenses/agpl-3.0-standalone.html
   @link      https://github.com/pluginsGLPI/timelineticket
   @since     2013

   ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginTimelineticketToolbox {


   /**
    * Return array with all data
    *
    * @param Ticket   $ticket
    * @param type     $type 'user' or 'group'
    * @param int|type $withblank option to fill blank zones
    *
    * @return type
    */
   static function getDetails(Ticket $ticket, $type, $withblank = 1) {

      if ($type == 'group') {
         $palette = [
            ['250', '151', '186'],
            ['255', '211', '112'],
            ['183', '210', '118'],
            ['117', '199', '187'],
            ['188', '168', '208'],
            ['186', '213', '118'],
            ['124', '169', '0'],
            ['168', '208', '49'],
            ['239', '215', '113'],
            ['235', '155', '0'],
            ['235', '249', '255'],
            ['193', '228', '250'],
            ['164', '217', '250'],
            ['88', '195', '240'],
            ['0', '156', '231'],
            ['198', '229', '111'],
            ['234', '38', '115'],
            ['245', '122', '160'],
            ['255', '208', '220']
         ];
      } else if ($type == 'user') {
         $palette = [
            ['164', '53', '86'],
            ['137', '123', '78'],
            ['192', '114', '65'],
            ['143', '102', '98'],
            ['175', '105', '93'],
            ['186', '127', '61'],
            ['174', '104', '92'],
            ['213', '113', '63'],
            ['185', '168', '122'],
            ['233', '168', '112'],
            ['199', '133', '99'],
            ['80', '24', '69'],
            ['133', '39', '65'],
            ['120', '22', '61'],
            ['114', '59', '82'],
            ['245', '229', '195']
         ];
      }

      $ptState = new PluginTimelineticketState();

      $a_ret     = PluginTimelineticketDisplay::getTotaltimeEnddate($ticket);
      $totaltime = $a_ret['totaltime'];

      if ($type == 'group') {
         $ptItem = new PluginTimelineticketAssignGroup();
      } else if ($type == 'user') {
         $ptItem = new PluginTimelineticketAssignUser();
      }

      $a_states       = [];
      $a_item_palette = [];
      $a_dbstates     = $ptState->find(["tickets_id" => $ticket->getID()], ["date", "id"]);
      $end_previous   = 0;
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
      $a_itemsections = [];
      $a_dbitems      = $ptItem->find(["tickets_id" => $ticket->getID()], ["date"]);
      foreach ($a_dbitems as $a_dbitem) {

         if ($type == 'group') {
            $items_id = 'groups_id';
         } else if ($type == 'user') {
            $items_id = 'users_id';
         }

         if (!isset($a_itemsections[$a_dbitem[$items_id]])) {
            $a_itemsections[$a_dbitem[$items_id]] = [];
            $last_statedelay                      = 0;
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
         $mem       = 0;

         foreach ($a_states as $delay => $statusname) {
            if ($mem == 1) {
               if ($gdelay > $delay) { // all time of the state
                  $a_itemsections[$a_dbitem[$items_id]][] = [
                     'Start'   => $gbegin,
                     'End'     => $delay,
                     "Caption" => " ",
                     "Status"  => $statusname,
                     "R"       => $color_R,
                     "G"       => $color_G,
                     "B"       => $color_B
                  ];
                  $gbegin                                 = $delay;
               } else if ($gdelay == $delay) { // end of status = end of group
                  $a_itemsections[$a_dbitem[$items_id]][] = [
                     'Start'   => $gbegin,
                     'End'     => $delay,
                     "Caption" => " ",
                     "Status"  => $statusname,
                     "R"       => $color_R,
                     "G"       => $color_G,
                     "B"       => $color_B
                  ];
                  $mem                                    = 2;
               } else { // end of status is after end of group
                  $a_itemsections[$a_dbitem[$items_id]][] = [
                     'Start'   => $gbegin,
                     'End'     => $gdelay,
                     "Caption" => " ",
                     "Status"  => $statusname,
                     "R"       => $color_R,
                     "G"       => $color_G,
                     "B"       => $color_B
                  ];
                  $mem                                    = 2;
               }
            } else if ($mem == 0
                       && $gbegin < $delay) {
               if ($withblank
                   && $gbegin != $last_statedelay) {
                  $a_itemsections[$a_dbitem[$items_id]][] = [
                     'Start'   => $last_statedelay,
                     'End'     => $gbegin,
                     "Caption" => " ",
                     "Status"  => "",
                     "R"       => 235,
                     "G"       => 235,
                     "B"       => 235
                  ];
               }
               if ($gdelay > $delay) { // all time of the state
                  $a_itemsections[$a_dbitem[$items_id]][] = [
                     'Start'   => $gbegin,
                     'End'     => $delay,
                     "Caption" => " ",
                     "Status"  => $statusname,
                     "R"       => $color_R,
                     "G"       => $color_G,
                     "B"       => $color_B
                  ];
                  $gbegin                                 = $delay;
                  $mem                                    = 1;
               } else if ($gdelay == $delay) { // end of status = end of group
                  $a_itemsections[$a_dbitem[$items_id]][] = [
                     'Start'   => $gbegin,
                     'End'     => $delay,
                     "Caption" => " ",
                     "Status"  => $statusname,
                     "R"       => $color_R,
                     "G"       => $color_G,
                     "B"       => $color_B
                  ];
                  $mem                                    = 2;
               } else { // end of status is after end of group
                  $a_itemsections[$a_dbitem[$items_id]][] = [
                     'Start'   => $gbegin,
                     'End'     => $gdelay,
                     "Caption" => " ",
                     "Status"  => $statusname,
                     "R"       => $color_R,
                     "G"       => $color_G,
                     "B"       => $color_B
                  ];
                  $mem                                    = 2;
               }
            }
         }
      }
      if ($withblank) {
         end($a_states);
         $verylastdelayStateDB = key($a_states);
         foreach ($a_itemsections as $items_id => $data_f) {
            $last       = 0;
            $R          = 235;
            $G          = 235;
            $B          = 235;
            $statusname = '';
            $a_end      = end($data_f);
            $last       = $a_end['End'] ?? 0;
            if ($ticket->fields['status'] != Ticket::CLOSED
                && $last == $verylastdelayStateDB) {
               $R          = $a_end['R'] ?? 235;
               $G          = $a_end['G'] ?? 235;
               $B          = $a_end['B'] ?? 235;
               $statusname = $a_end['Status'] ?? $statusname;
            }
            if ($last < $totaltime) {
               $a_itemsections[$items_id][] = [
                  'Start'   => $last,
                  'End'     => $totaltime,
                  "Caption" => " ",
                  "Status"  => $statusname,
                  "R"       => $R,
                  "G"       => $G,
                  "B"       => $B
               ];
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
    * @param        $type
    */
   static function ShowDetail(Ticket $ticket, $type) {

      $ptState = new PluginTimelineticketState();

      if ($type == 'group') {
         $ptItem = new PluginTimelineticketAssignGroup();
      } else if ($type == 'user') {
         $ptItem = new PluginTimelineticketAssignUser();
      }

      $a_states = $ptState->find(["tickets_id" => $ticket->getID()], ["date"]);

      $a_state_delays = [];
      $a_state_num    = [];
      $delay          = 0;

      $list_status = Ticket::getAllStatusArray();

      foreach ($a_states as $array) {
         $delay                  += $array['delay'];
         $a_state_delays[$delay] = $array['old_status'];
         $a_state_num[]          = $delay;
      }
      $a_state_num[] = $delay;

      echo "<table class='tab_cadre_fixe' width='100%'>";
      echo "<tr class='tab_bg_1'>";
      echo "<th colspan='" . (count($list_status) + 1) . "'>";
      echo __('Result details');
      if ($type == 'group') {
         echo " (" . __('Groups in charge of the ticket', 'timelineticket') . ")";
      } else if ($type == 'user') {
         echo " (" . __('Technicians in charge of the ticket', 'timelineticket') . ")";
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
         $a_details = self::getDetails($ticket, 'group', false);
      } else if ($type == 'user') {
         $a_details = self::getDetails($ticket, 'user', false);
      }

      foreach ($a_details as $items_id => $a_detail) {
         $a_status = [];
         foreach ($a_detail as $data) {
            if (!isset($a_status[$data['Status']])) {
               $a_status[$data['Status']] = 0;
            }
            $a_status[$data['Status']] += ($data['End'] - $data['Start']);
         }
         echo "<tr class='tab_bg_1'>";
         if ($type == 'group') {
            echo "<td>" . Dropdown::getDropdownName("glpi_groups", $items_id) . "</td>";
         } else if ($type == 'user') {
            echo "<td>" . getUserName($items_id) . "</td>";
         }
         foreach ($list_status as $status => $name) {
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


   /**
    * @param $myDate
    *
    * @return false|string
    * @throws \Exception
    */
   static function convertDateToRightTimezoneForCalendarUse($myDate) {
      // We convert the both dates because $date passed in fonction are timezoned but not hours of calendars
      $currTimezone   = new DateTime(date("Y-m-d"));
      $configTimezone = Config::getConfigurationValues('core', ['timezone']);
      $baseTimezone   = 'UTC';
      $tz             = ini_get('date.timezone');
      if (!empty($configTimezone['timezone']) && !is_null($configTimezone['timezone'])) {
         $baseTimezone = $configTimezone['timezone'];
      } else if (!empty($tz)) {
         $baseTimezone = $tz;
      }

      if ($baseTimezone > 0) {
         $globalConfTimezone = new DateTime('2008-06-21', new DateTimeZone($baseTimezone));
         $timeOffset         = date_offset_get($currTimezone) - date_offset_get($globalConfTimezone);
         return date("Y-m-d H:i:s", (strtotime($myDate) - $timeOffset));
      }
      return $myDate;
   }
}

