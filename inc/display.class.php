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

class PluginTimelineticketDisplay extends CommonDBTM {

   public static function getTypeName($nb = 0) {

      return _n('Timeline of ticket', 'Timeline of tickets', $nb, 'timelineticket');
   }

   /**
    * @return array
    */
   function rawSearchOptions() {

      $tab = [];

      $tab[] = [
         'id'   => 'common',
         'name' => __('Timeline', 'timelineticket')
      ];

      $tab[] = [
         'id'           => '1',
         'table'        => 'glpi_plugin_timelineticket_assigngroups',
         'field'        => 'groups_id',
         'linkfield'    => 'tickets_id',
         'name'         => __('Group'),
         'datatype'     => 'itemlink',
         'forcegroupby' => true
      ];

      return $tab;
   }


   static function showForTicket(Ticket $ticket) {
      global $CFG_GLPI, $DB;



      echo "<table class='tab_cadre_fixe'>";
      echo "<tr><th class='center'>";
      $target = PLUGIN_TIMELINETICKET_WEBDIR . "/front/config.form.php";
      Html::showSimpleForm($target, 'delete_review_from_list',
                           _sx('button', 'Reconstruct history for this ticket', 'timelineticket'),
                           ['tickets_id' => $ticket->getID(),
                            'reconstructTicket' => 'reconstructTicket'],
//                           'fa-spinner fa-2x'
      );
      echo "</th></tr>";
      echo "<tr><th>" . __('Summary') . "</th></tr>";

      echo "<tr class='tab_bg_1 center'><td>" . _n('Time range', 'Time ranges', 2) . "&nbsp;: ";
      $calendar     = new Calendar();
      $calendars_id = Entity::getUsedConfig('calendars_strategy', $ticket->fields['entities_id'], 'calendars_id', 0);
      if ($calendars_id > 0 && $calendar->getFromDB($calendars_id)) {
         echo $calendar->getLink();
      } else {
         echo NOT_AVAILABLE;
      }
      echo "</td></tr>";

      PluginTimelineticketState::showHistory($ticket);

      // Display ticket have Due date
      if ($ticket->fields['time_to_resolve']
          && $ticket->fields['status'] != CommonITILObject::WAITING
          && (strtotime(date('Y-m-d H:i:s')) - strtotime($ticket->fields['time_to_resolve'])) > 0) {

         $calendar     = new Calendar();
         $calendars_id = Entity::getUsedConfig('calendars_strategy', $ticket->fields['entities_id'], 'calendars_id', 0);

         if ($calendars_id > 0 && $calendar->getFromDB($calendars_id)) {
            if ($ticket->fields['closedate']) {
               $dateend = $calendar->getActiveTimeBetween($ticket->fields['time_to_resolve'],
                                                          $ticket->fields['solvedate']);
            } else {
               $dateend = $calendar->getActiveTimeBetween($ticket->fields['time_to_resolve'],
                                                          date('Y-m-d H:i:s'));
            }
         } else {
            // cas 24/24 - 7/7
            if ($ticket->fields['closedate']) {
               $dateend = strtotime($ticket->fields['solvedate']) - strtotime($ticket->fields['time_to_resolve']);
            } else {
               $dateend = strtotime(date('Y-m-d H:i:s')) - strtotime($ticket->fields['time_to_resolve']);
            }
         }
         if($dateend > 0) {
            echo "<tr>";
            echo "<th>" . __('Late') . "</th>";
            echo "</tr>";
            echo "<tr>";
            echo "<td align='center' class='tab_bg_2_2'>" .
                 Html::timestampToString($dateend, true) . "</td>";
            echo "</tr>";
         }
      }

      echo "</table>";

      echo "<table class='tab_cadre_fixe'>";
      echo "<tr>";
      echo "<th colspan='2'>" . __('Status') . "</th>";
      echo "</tr>";

      $a_data = PluginTimelineticketDisplay::getTotaltimeEnddate($ticket);

      $totaltime = $a_data['totaltime'];
      $end_date  = $a_data['end_date'];

      $params = ['totaltime' => $totaltime,
                      'end_date'  => $end_date];

      $ptState = new PluginTimelineticketState();
      $ptState->showTimeline($ticket, $params);
      $ptAssignGroup = new PluginTimelineticketAssignGroup();
      $ptAssignGroup->showTimeline($ticket, $params);
      $ptAssignUser = new PluginTimelineticketAssignUser();
      $ptAssignUser->showTimeline($ticket, $params);
      echo "</table>";

      PluginTimelineticketToolbox::ShowDetail($ticket, 'group');
      PluginTimelineticketToolbox::ShowDetail($ticket, 'user');

      if ($_SESSION['glpi_use_mode'] == Session::DEBUG_MODE) {
         echo "<br><table class='tab_cadre_fixe'>";
         echo "<tr>";
         echo "<th colspan='5'>" . __('DEBUG') . " " . __('Group') . "</th>";
         echo "</tr>";

         echo "<tr>";
         echo "<th>" . __('ID') . "</th>";
         echo "<th>" . __('Date') . "</th>";
         echo "<th>" . __('Group') . "</th>";
         echo "<th>" . __('Begin') . "</th>";
         echo "<th>" . __('Delay', 'timelineticket') . "</th>";
         echo "</tr>";
         $query = "SELECT *
                         FROM `glpi_plugin_timelineticket_assigngroups`
                         WHERE `tickets_id` = '" . $ticket->getID() . "'";

         $result = $DB->query($query);
         while ($data = $DB->fetchAssoc($result)) {

            echo "<tr class='tab_bg_1'>";
            echo "<td>" . $data['id'] . "</td>";
            echo "<td>" . Html::convDateTime($data['date']) . "</td>";
            echo "<td>" . Dropdown::getDropdownName("glpi_groups", $data['groups_id']) . "</td>";
            echo "<td>" . Html::timestampToString($data['begin']) . "</td>";
            echo "<td>" . Html::timestampToString($data['delay']) . "</td>";
            echo "</tr>";

         }
         echo "</table>";

         echo "<br><table class='tab_cadre_fixe'>";
         echo "<tr>";
         echo "<th colspan='5'>" . __('DEBUG') . " " . __('Technician') . "</th>";
         echo "</tr>";

         echo "<tr>";
         echo "<th>" . __('ID') . "</th>";
         echo "<th>" . __('Date') . "</th>";
         echo "<th>" . __('Technician') . "</th>";
         echo "<th>" . __('Begin') . "</th>";
         echo "<th>" . __('Delay', 'timelineticket') . "</th>";
         echo "</tr>";
         $query = "SELECT *
                         FROM `glpi_plugin_timelineticket_assignusers`
                         WHERE `tickets_id` = '" . $ticket->getID() . "'";

         $result = $DB->query($query);
         while ($data = $DB->fetchAssoc($result)) {

            echo "<tr class='tab_bg_1'>";
            echo "<td>" . $data['id'] . "</td>";
            echo "<td>" . Html::convDateTime($data['date']) . "</td>";
            echo "<td>" . getUserName($data['users_id']) . "</td>";
            echo "<td>" . Html::timestampToString($data['begin']) . "</td>";
            echo "<td>" . Html::timestampToString($data['delay']) . "</td>";
            echo "</tr>";

         }
         echo "</table>";
      }
   }


   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      if ($item->getType() == 'Ticket'
          && $_SESSION['glpiactiveprofile']['interface'] == 'central') {
         return __('Timeline', 'timelineticket');
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

      if ($item->getType() == 'Ticket') {
         self::showForTicket($item);
      }
      return true;
   }


   static function getTotaltimeEnddate(CommonGLPI $ticket) {

      $totaltime = 0;

      $ptState   = new PluginTimelineticketState();
      $a_states  = $ptState->find(["tickets_id" => $ticket->getField('id')], ["date"]);
      $last_date = '';
      foreach ($a_states as $a_state) {
         $totaltime += $a_state['delay'];
         $last_date = $a_state['date'];
      }
      //if ($a_state['delay'] == 0) {
      //   $actual = strtotime(date('Y-m-d H:i:s'))-strtotime($a_state['date']);
      //   $totaltime += $actual;
      //}
      if ($ticket->fields['status'] != Ticket::CLOSED
          && isset($a_state['date'])) {
         $totaltime += PluginTimelineticketDisplay::getPeriodTime($ticket,
                                                                  $a_state['date'],
                                                                  date("Y-m-d H:i:s"));
      }
      $end_date = $totaltime;

      return ['totaltime' => $totaltime,
                   'end_date'  => $end_date];
   }


   static function getPeriodTime(CommonGLPI $ticket, $start, $end) {

      $calendar = new Calendar();
      if ($ticket->fields['slas_id_ttr'] != 0) { // Have SLT
         $sla = new SLA();
         $sla->getFromDB($ticket->fields['slas_id_ttr']);
         $totaltime = $sla->getActiveTimeBetween($start, $end);
      } else {
         $calendars_id = Entity::getUsedConfig('calendars_strategy', $ticket->fields['entities_id'], 'calendars_id', 0);
         if ($calendars_id != 0) { // Ticket entity have calendar

            $calendar->getFromDB($calendars_id);
            $totaltime = $calendar->getActiveTimeBetween($start, $end);
         } else { // No calendar
            $totaltime = strtotime($end) - strtotime($start);
         }
      }
      return $totaltime;
   }
}
