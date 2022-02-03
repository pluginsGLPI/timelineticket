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

class PluginTimelineticketAssignGroup extends CommonDBTM {


   /*
    * type = new or delete
    */
   function createGroup(Ticket $ticket, $date, $groups_id, $type) {

      $calendar = new Calendar();

      if ($type == 'new') {
         $calendars_id = Entity::getUsedConfig('calendars_strategy', $ticket->fields['entities_id'], 'calendars_id', 0);
         if ($calendars_id > 0 && $calendar->getFromDB($calendars_id)) {
            $begin = $calendar->getActiveTimeBetween(
               PluginTimelineticketToolbox::convertDateToRightTimezoneForCalendarUse($ticket->fields['date']), 
               PluginTimelineticketToolbox::convertDateToRightTimezoneForCalendarUse($date)
            );
         } else {
            // cas 24/24 - 7/7
            $begin = strtotime($date) - strtotime($ticket->fields['date']);
         }

         $this->add(['tickets_id' => $ticket->getField("id"),
                     'date'       => $date,
                     'groups_id'  => $groups_id,
                     'begin'      => $begin]);

      } else if ($type == 'delete') {
         $a_dbentry = $this->find(["tickets_id" => $ticket->getField("id"),
                                   "groups_id"  => $groups_id,
                                   "delay"      => NULL], [], 1);
         if (count($a_dbentry) == 1) {
            $input        = current($a_dbentry);
            $calendars_id = Entity::getUsedConfig('calendars_strategy', $ticket->fields['entities_id'], 'calendars_id', 0);
            if ($calendars_id > 0 && $calendar->getFromDB($calendars_id)) {
               $input['delay'] = $calendar->getActiveTimeBetween(
                  PluginTimelineticketToolbox::convertDateToRightTimezoneForCalendarUse($input['date']),
                  PluginTimelineticketToolbox::convertDateToRightTimezoneForCalendarUse($date)
               );
            } else {
               // cas 24/24 - 7/7
               $input['delay'] = strtotime($date) - strtotime($input['date']);
            }
            $this->update($input);
         }
      }
   }


   function showTimeline(CommonGLPI $ticket, $params = []) {
      global $CFG_GLPI;

      /* Create and populate the pData object */
      $MyData = new CpChart\Data();
      /* Create the pChart object */
      $myPicture = new CpChart\Image(820, 29, $MyData);
      /* Create the pIndicator object */
      $Indicator = new CpChart\Chart\Indicator($myPicture);
      $myPicture->setFontProperties(["FontName" => "pf_arma_five.ttf", "FontSize" => 6]);
      /* Define the indicator sections */
      $IndicatorSections = [];
      $_groupsfinished   = [];

      $a_groups_list     = [];
      $IndicatorSections = PluginTimelineticketToolbox::getDetails($ticket, 'group');
      foreach ($IndicatorSections as $groups_id => $data) {
         $a_groups_list[$groups_id] = $groups_id;

         $a_end = end($data);
         if (isset($a_end['R']) && $a_end['R'] == 235
             && isset($a_end['G']) && $a_end['G'] == 235
             && isset($a_end['B']) && $a_end['B'] == 235) {
            $_groupsfinished[$groups_id] = true;
         } else {
            $_groupsfinished[$groups_id] = false;
         }
      }

      echo "<tr>";
      echo "<th colspan='2'>";
      if (count($a_groups_list) > 1) {
         echo __('Groups in charge of the ticket', 'timelineticket');
      } else {
         echo __('Group in charge of the ticket');
      }
      echo "</th>";
      echo "</tr>";

      $mylevels = [];
      $dbu      = new DbUtils();
      $restrict = $dbu->getEntitiesRestrictCriteria("glpi_plugin_timelineticket_grouplevels", '', '', true) +
                  ["ORDER" => 'rank'];
      $levels   = $dbu->getAllDataFromTable("glpi_plugin_timelineticket_grouplevels", $restrict);
      if (!empty($levels)) {
         foreach ($levels as $level) {
            if (!empty($level["groups"])) {
               $groups                   = json_decode($level["groups"], true);
               $mylevels[$level["name"]] = $groups;
            }
         }
      }

      $ticketlevels = [];
      foreach ($IndicatorSections as $groups_id => $array) {
         foreach ($mylevels as $name => $groups) {
            if (in_array($groups_id, $groups)) {
               $ticketlevels[$name][] = $groups_id;
            }
         }
      }
      //No levels
      if (sizeof($ticketlevels) == 0) {

         foreach ($IndicatorSections as $groups_id => $array) {
            $ticketlevels[0][] = $groups_id;
         }
      }
      ksort($ticketlevels);
      foreach ($ticketlevels as $name => $groups) {
         if (!isset($ticketlevels[0])) {
            echo "<tr>";
            echo "<th colspan='2'>";
            echo $name;
            echo "</th>";
            echo "</tr>";
         }
         foreach ($IndicatorSections as $groups_id => $array) {

            if (in_array($groups_id, $groups)) {
               echo "<tr class='tab_bg_2'>";
               echo "<td width='100'>";
               echo Dropdown::getDropdownName("glpi_groups", $groups_id);
               echo "</td>";
               echo "<td>";
               if ($ticket->fields['status'] != Ticket::CLOSED
                   && $_groupsfinished[$groups_id] != 0) {

                  $IndicatorSettings = ["Values"            => [100, 201],
                                        "CaptionPosition"   => INDICATOR_CAPTION_BOTTOM,
                                        "CaptionLayout"     => INDICATOR_CAPTION_DEFAULT,
                                        "CaptionR"          => 0,
                                        "CaptionG"          => 0,
                                        "CaptionB"          => 0,
                                        "DrawLeftHead"      => false,
                                        "DrawRightHead"     => true,
                                        "ValueDisplay"      => false,
                                        "IndicatorSections" => $array,
                                        "SectionsMargin"    => 0];
                  if (is_array($array)) {
                     foreach ($array as $arr) {
                        if ($arr['End'] > $arr['Start']) {
                           $Indicator->draw(2, 2, 805, 25, $IndicatorSettings);
                        }
                     }
                  }
               } else {
                  $IndicatorSettings = ["Values"            => [100, 201],
                                        "CaptionPosition"   => INDICATOR_CAPTION_BOTTOM,
                                        "CaptionLayout"     => INDICATOR_CAPTION_DEFAULT,
                                        "CaptionR"          => 0,
                                        "CaptionG"          => 0,
                                        "CaptionB"          => 0,
                                        "DrawLeftHead"      => false,
                                        "DrawRightHead"     => false,
                                        "ValueDisplay"      => false,
                                        "IndicatorSections" => $array,
                                        "SectionsMargin"    => 0];
                  if (is_array($array)) {
                     foreach ($array as $arr) {
                        if ($arr['End'] > $arr['Start']) {
                           $Indicator->draw(2, 2, 814, 25, $IndicatorSettings);
                        }
                     }
                  }
               }

               $filename = Session::getLoginUserID(false) . "_testgroup" . $groups_id;
               $myPicture->render(GLPI_GRAPH_DIR . "/" . $filename . ".png");

               echo "<img src='" . PLUGIN_TIMELINETICKET_WEBDIR . "/front/graph.send.php?file=" . $filename . ".png'><br/>";
               echo "</td>";
               echo "</tr>";
            }
         }
      }

      // Return list for unit tests
      return $IndicatorSections;
   }


   static function showGroupTimeline(Ticket $ticket) {
      global $DB;

      $query = "SELECT *
                FROM `glpi_plugin_timelineticket_assigngroups`
                WHERE `tickets_id` = '" . $ticket->getField('id') . "'
                ORDER BY `id` ASC";

      $req = $DB->request($query);
      if ($req->numrows()) {
         echo "<tr class='tab_bg_2'>";
         echo "<td>";
         $groups = [];
         $nb     = 0;
         $size   = count($req);
         foreach ($req as $data) {
            $nb++;
            $date  = strtotime($data['date']);
            $class = 'checked';
            if ($size == $nb) {
               $class = 'now';
            }
            $groups[$date . '_groups_id'] = [
               'timestamp' => $date,
               'label'     => Dropdown::getDropdownName("glpi_groups", $data['groups_id']) . " (" . Html::timestampToString($data['delay'], true) . ")",
               'class'     => $class];

         }
         $title = __('Ticket assign group history', 'timelineticket');
         echo "<div class='center'>";
         Html::showDatesTimelineGraph([
                                         'title'   => $title,
                                         'dates'   => $groups,
                                         'add_now' => false,
                                      ]);
         echo "</div>";
         echo "</td>";
         echo "</tr>";

      }
   }

   static function addGroupTicket(Group_Ticket $item) {

      if ($item->fields['type'] == 2) {
         $ptAssignGroup = new PluginTimelineticketAssignGroup();
         $ticket        = new Ticket();
         $ticket->getFromDB($item->fields['tickets_id']);
         $calendar     = new Calendar();
         $calendars_id = Entity::getUsedConfig('calendars_strategy', $ticket->fields['entities_id'], 'calendars_id', 0);
         $datedebut    = $ticket->fields['date'];
         if ($calendars_id > 0 && $calendar->getFromDB($calendars_id)) {
            $delay = $calendar->getActiveTimeBetween($datedebut, $_SESSION["glpi_currenttime"]);
         } else {
            // cas 24/24 - 7/7
            $delay = strtotime($_SESSION["glpi_currenttime"]) - strtotime($datedebut);
         }
         $ok = 1;

         $ptConfig = new PluginTimelineticketConfig();
         $ptConfig->getFromDB(1);
         if ($ptConfig->fields["add_waiting"] == 0
             && $ticket->fields["status"] == Ticket::WAITING) {
            $ok = 0;
         }
         if ($ok) {
            $input               = [];
            $input['tickets_id'] = $item->fields['tickets_id'];
            $input['groups_id']  = $item->fields['groups_id'];
            $input['date']       = $_SESSION["glpi_currenttime"];
            $input['begin']      = $delay;
            $ptAssignGroup->add($input);
         }
      }
   }


   static function checkAssignGroup(Ticket $ticket) {
      global $DB;

      $ok       = 0;
      $ptConfig = new PluginTimelineticketConfig();
      $ptConfig->getFromDB(1);
      if ($ptConfig->fields["add_waiting"] == 0) {
         $ok = 1;
      }

      if ($ok && in_array("status", $ticket->updates)
          && isset($ticket->oldvalues["status"])
          && $ticket->oldvalues["status"] == Ticket::WAITING) {
         if ($ticket->countGroups(CommonITILActor::ASSIGN)) {
            foreach ($ticket->getGroups(CommonITILActor::ASSIGN) as $d) {
               $ptAssignGroup = new PluginTimelineticketAssignGroup();
               $calendar      = new Calendar();
               $calendars_id  = Entity::getUsedConfig('calendars_strategy', $ticket->fields['entities_id'], 'calendars_id', 0);
               $datedebut     = $ticket->fields['date'];
               if ($calendars_id > 0 && $calendar->getFromDB($calendars_id)) {
                  $delay = $calendar->getActiveTimeBetween($datedebut, $_SESSION["glpi_currenttime"]);
               } else {
                  // cas 24/24 - 7/7
                  $delay = strtotime($_SESSION["glpi_currenttime"]) - strtotime($datedebut);
               }

               $input               = [];
               $input['tickets_id'] = $ticket->getID();
               $input['groups_id']  = $d["groups_id"];
               $input['date']       = $_SESSION["glpi_currenttime"];
               $input['begin']      = $delay;
               $ptAssignGroup->add($input);
            }
         }
      } else if ($ok && in_array("status", $ticket->updates)
                 && isset($ticket->fields["status"])
                 && $ticket->fields["status"] == Ticket::WAITING) {
         if ($ticket->countGroups(CommonITILActor::ASSIGN)) {
            foreach ($ticket->getGroups(CommonITILActor::ASSIGN) as $d) {

               $calendar      = new Calendar();
               $calendars_id  = Entity::getUsedConfig('calendars_strategy', $ticket->fields['entities_id'], 'calendars_id', 0);
               $ptAssignGroup = new PluginTimelineticketAssignGroup();
               $query         = "SELECT MAX(`date`) AS datedebut, id
                         FROM `" . $ptAssignGroup->getTable() . "`
                         WHERE `tickets_id` = '" . $ticket->getID() . "'
                           AND `groups_id`='" . $d["groups_id"] . "'
                           AND `delay` IS NULL";

               $result    = $DB->query($query);
               $datedebut = '';
               $input     = [];
               if ($result && $DB->numrows($result)) {
                  $datedebut   = $DB->result($result, 0, 'datedebut');
                  $input['id'] = $DB->result($result, 0, 'id');
               } else {
                  return;
               }

               if (!$datedebut) {
                  $delay = 0;
                  // Utilisation calendrier
               } else if ($calendars_id > 0 && $calendar->getFromDB($calendars_id)) {
                  $delay = $calendar->getActiveTimeBetween($datedebut, $_SESSION["glpi_currenttime"]);
               } else {
                  // cas 24/24 - 7/7
                  $delay = strtotime($_SESSION["glpi_currenttime"]) - strtotime($datedebut);
               }

               $input['delay'] = $delay;
               $ptAssignGroup->update($input);
            }
         }
      } else if (in_array("status", $ticket->updates)
                 && isset($ticket->input["status"])
                 && ($ticket->input["status"] == Ticket::SOLVED
                     || $ticket->input["status"] == Ticket::CLOSED)) {
         if ($ticket->countGroups(CommonITILActor::ASSIGN)) {
            foreach ($ticket->getGroups(CommonITILActor::ASSIGN) as $d) {

               $calendar      = new Calendar();
               $calendars_id  = Entity::getUsedConfig('calendars_strategy', $ticket->fields['entities_id'], 'calendars_id', 0);
               $ptAssignGroup = new PluginTimelineticketAssignGroup();
               $query         = "SELECT MAX(`date`) AS datedebut, id
                         FROM `" . $ptAssignGroup->getTable() . "`
                         WHERE `tickets_id` = '" . $ticket->getID() . "'
                           AND `groups_id`='" . $d["groups_id"] . "'
                           AND `delay` IS NULL";

               $result    = $DB->query($query);
               $datedebut = '';
               $input     = [];
               if ($result && $DB->numrows($result)) {
                  $datedebut   = $DB->result($result, 0, 'datedebut');
                  $input['id'] = $DB->result($result, 0, 'id');
               } else {
                  return;
               }

               if (!$datedebut) {
                  $delay = 0;
                  // Utilisation calendrier
               } else if ($calendars_id > 0 && $calendar->getFromDB($calendars_id)) {
                  $delay = $calendar->getActiveTimeBetween($datedebut, $_SESSION["glpi_currenttime"]);
               } else {
                  // cas 24/24 - 7/7
                  $delay = strtotime($_SESSION["glpi_currenttime"]) - strtotime($datedebut);
               }

               $input['delay'] = $delay;
               $ptAssignGroup->update($input);
            }
         }
      }
   }


   static function deleteGroupTicket(Group_Ticket $item) {
      global $DB;

      $ticket        = new Ticket();
      $ptAssignGroup = new PluginTimelineticketAssignGroup();

      $ticket->getFromDB($item->fields['tickets_id']);

      $calendar     = new Calendar();
      $calendars_id = Entity::getUsedConfig('calendars_strategy', $ticket->fields['entities_id'], 'calendars_id', 0);

      $query = "SELECT MAX(`date`) AS datedebut, id
                FROM `" . $ptAssignGroup->getTable() . "`
                WHERE `tickets_id` = '" . $item->fields['tickets_id'] . "'
                  AND `groups_id`='" . $item->fields['groups_id'] . "'
                  AND `delay` IS NULL";

      $result    = $DB->query($query);
      $datedebut = '';
      $input     = [];
      if ($result && $DB->numrows($result)) {
         $datedebut   = $DB->result($result, 0, 'datedebut');
         $input['id'] = $DB->result($result, 0, 'id');
      } else {
         return;
      }

      if (!$datedebut) {
         $delay = 0;
         // Utilisation calendrier
      } else if ($calendars_id > 0 && $calendar->getFromDB($calendars_id)) {
         $delay = $calendar->getActiveTimeBetween($datedebut, $_SESSION["glpi_currenttime"]);
         if($delay == 0 || is_null($delay)){
            $delay = 1;
         }
      } else {
         // cas 24/24 - 7/7
         $delay = strtotime($_SESSION["glpi_currenttime"]) - strtotime($datedebut);
      }

      $input['delay'] = $delay;
      $ptAssignGroup->update($input);

   }


   /*
   * Function to reconstruct timeline for all tickets
   */

   function reconstrucTimeline($id = 0) {
      global $DB;

      if ($id == 0 ) {
         $query = "TRUNCATE `" . $this->getTable() . "`";
         $DB->query($query);
      } else {
         $query = "DELETE FROM `" . $this->getTable() . "` 
                  WHERE `tickets_id` = $id";
         $DB->query($query);
      }
      $where = "";
      if ($id > 0 ) {
         $where = "WHERE `id` = $id ";
      }
      $query  = "SELECT `id`
               FROM `glpi_tickets` $where";
      $result = $DB->query($query);

      while ($data = $DB->fetchArray($result)) {

         $queryGroup = "SELECT * FROM `glpi_logs`";
         $queryGroup .= " WHERE `itemtype_link` = 'Group'";
         $queryGroup .= " AND `items_id` = " . $data['id'];
         $queryGroup .= " AND `itemtype` = 'Ticket'";
         $queryGroup .= " ORDER BY date_mod ASC";

         $resultGroup = $DB->query($queryGroup);

         if ($resultGroup) {
            while ($dataGroup = $DB->fetchArray($resultGroup)) {
               if ($dataGroup['new_value'] != null) {
                  $start     = Toolbox::strpos($dataGroup['new_value'], "(");
                  $end       = Toolbox::strpos($dataGroup['new_value'], ")");
                  $length    = $end - $start;
                  $groups_id = Toolbox::substr($dataGroup['new_value'], $start + 1, $length - 1);

                  $group = new Group();
                  if ($group->getFromDB($groups_id)) {
                     if ($group->fields['is_requester'] == 0
                         && $group->fields['is_assign'] == 1) {

                        $ticket = new Ticket();
                        $ticket->getFromDB($data['id']);
                        $this->createGroup($ticket, $dataGroup['date_mod'], $groups_id, 'new');
                     }
                  }
               } else if ($dataGroup['old_value'] != null) {
                  $start     = Toolbox::strpos($dataGroup['old_value'], "(");
                  $end       = Toolbox::strpos($dataGroup['old_value'], ")");
                  $length    = $end - $start;
                  $groups_id = Toolbox::substr($dataGroup['old_value'], $start + 1, $length - 1);

                  $group = new Group();
                  if ($group->getFromDB($groups_id)) {
                     if ($group->fields['is_requester'] == 0
                         && $group->fields['is_assign'] == 1) {
                        $ticket = new Ticket();
                        $ticket->getFromDB($data['id']);
                        $this->createGroup($ticket, $dataGroup['date_mod'], $groups_id, 'delete');
                     }
                  }
               }
            }
         }
      }
   }
}

