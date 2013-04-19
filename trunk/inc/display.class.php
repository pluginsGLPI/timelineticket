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

class PluginTimelineticketDisplay extends CommonDBTM {

   static function showForTicket (Ticket $ticket) {
      global $DB, $LANG, $CFG_GLPI;

      echo "<table class='tab_cadre_fixe'>";
      echo "<tr><th>".$LANG['job'][37]."</th></tr>";

      echo "<tr class='tab_bg_1 center'><td>".$LANG['calendar'][10]."&nbsp;: ";
      $calendar = new Calendar();
      $calendars_id = EntityData::getUsedConfig('calendars_id', $ticket->fields['entities_id']);
      if ($calendars_id > 0 && $calendar->getFromDB($calendars_id)) {
         echo $calendar->getLink();
      } else {
         echo NOT_AVAILABLE;
      }
      echo "</td></tr>";

      PluginTimelineticketState::showHistory($ticket);

      // Display ticket have Due date
      if ($ticket->fields['due_date']
              && (strtotime(date('Y-m-d H:i:s')) - strtotime($ticket->fields['due_date'])) > 0) {

         $calendar = new Calendar();
         $calendars_id = EntityData::getUsedConfig('calendars_id', $ticket->fields['entities_id']);

         if ($calendars_id>0 && $calendar->getFromDB($calendars_id)) {
            if ($ticket->fields['closedate']) {
               $dateend = $calendar->getActiveTimeBetween($ticket->fields['due_date'], 
                                                          $ticket->fields['closedate']);
            } else {
               $dateend = $calendar->getActiveTimeBetween($ticket->fields['due_date'], 
                                                          date('Y-m-d H:i:s'));
            }
         } else {
            // cas 24/24 - 7/7
            if ($ticket->fields['closedate']) {
               $dateend = strtotime($ticket->fields['closedate'])-strtotime($ticket->fields['due_date']);
            } else {
               $dateend = strtotime(date('Y-m-d H:i:s'))-strtotime($ticket->fields['due_date']);
            }
         }
         echo "<tr>";
         echo "<th>".$LANG['job'][17]."</th>";
         echo "</tr>";
         echo "<tr>";
         echo "<td align='center' class='tab_bg_2_2'>".
                 Html::timestampToString($dateend, true)."</td>";
         echo "</tr>";
      }
      
      echo "</table>";
      
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr>";
      echo "<th colspan='2'>".$LANG['joblist'][0]."</th>";
      echo "</tr>";
      
      /* pChart library inclusions */
      include(GLPI_ROOT."/plugins/timelineticket/lib/pChart2.1.3/class/pData.class.php");
      include(GLPI_ROOT."/plugins/timelineticket/lib/pChart2.1.3/class/pDraw.class.php");
      include(GLPI_ROOT."/plugins/timelineticket/lib/pChart2.1.3/class/pImage.class.php");
      include(GLPI_ROOT."/plugins/timelineticket/lib/pChart2.1.3/class/pIndicator.class.php");

      $totaltime = 0;
      $end = date("Y-m-d H:i:s");
      if ($ticket->fields['status'] == 'closed') {
         $end = $ticket->fields['closedate'];
      }
      
      if ($ticket->fields['slas_id'] != 0) { // Have SLA
         $sla = new SLA();
         $sla->getFromDB($ticket->fields['slas_id']);
         $totaltime = $sla->getActiveTimeBetween($ticket->fields['date'], $end);
      } else {
         $calendars_id = EntityData::getUsedConfig('calendars_id', $ticket->fields['entities_id']);
         if ($calendars_id != 0) { // Ticket entity have calendar
            $calendar = new Calendar();
            $calendar->getFromDB($calendars_id);
            $totaltime = $calendar->getActiveTimeBetween($ticket->fields['date'], $end);
         } else { // No calendar
            $totaltime = strtotime($end) - strtotime($ticket->fields['date']);
         }
      }
      
      $end_date = '';
      if ($ticket->fields['status'] != 'closed') {
         $end_date = strtotime(date('Y-m-d H:i:s')) - strtotime($ticket->fields['date']);
      } else {
         $end_date = strtotime($ticket->fields['closedate']) - strtotime($ticket->fields['date']);
      }
      $params = array('totaltime' => $totaltime,
                        'end_date' => $end_date);

      $ptState = new PluginTimelineticketState();
      $ptState->showTimeline($ticket, $params);
      $ptAssignGroup = new PluginTimelineticketAssignGroup();
      $ptAssignGroup->showTimeline($ticket, $params);
      $ptAssignUser = new PluginTimelineticketAssignUser();
      $ptAssignUser->showTimeline($ticket, $params);
      echo "</table>";
   }
   


   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
      global $LANG;
      
      if ($item->getType() == 'Ticket') {
         if ($item->getField('id')>0 
            && Session::haveRight('config','r')) {
            return array(1 => $LANG['plugin_timelineticket'][15]);
         }
      }
      return '';
   }
   


   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

      if ($item->getType() == 'Ticket') {
         $prof = new self();
         if ($item->getField('id')>0 
            && Session::haveRight('config','r')) {
            self::showForTicket($item);
         }
      }
      return true;
   }
}

?>