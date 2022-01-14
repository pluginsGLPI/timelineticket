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

//Options for GLPI 0.71 and newer : need slave db to access the report
$USEDBREPLICATE        = 1;
$DBCONNECTION_REQUIRED = 1;

include("../../../../inc/includes.php");

// Instantiate Report with Name
$report = new PluginReportsAutoReport(__("statSpentTimeProcessingByGroup_report_title", "timelineticket"));
//Report's search criterias
$dateYear = date("Y-m-d", mktime(0, 0, 0, date("m"), 1, date("Y") - 1));
$lastday  = cal_days_in_month(CAL_GREGORIAN, date("m"), date("Y"));

if (date("d") == $lastday) {
   $dateMonthend   = date("Y-m-d", mktime(0, 0, 0, date("m"), date("d"), date("Y")));
   $dateMonthbegin = date("Y-m-d", mktime(0, 0, 0, date("m"), 1, date("Y")));
} else {
   $lastday        = cal_days_in_month(CAL_GREGORIAN, date("m") - 1, date("Y"));
   $dateMonthend   = date("Y-m-d", mktime(0, 0, 0, date("m") - 1, $lastday, date("Y")));
   $dateMonthbegin = date("Y-m-d", mktime(0, 0, 0, date("m") - 1, 1, date("Y")));
}
$endDate = date("Y-m-d", mktime(0, 0, 0, date("m"), date("d"), date("Y")));


$date = new PluginReportsDateIntervalCriteria($report, '`glpi_tickets`.`closedate`', __('Closing date'));
$date->setStartDate($dateMonthbegin);
$date->setEndDate($dateMonthend);

$type        = new PluginReportsTicketTypeCriteria($report, 'type', __('Type'));
$category    = new PluginReportsTicketCategoryCriteria($report, 'itilcategories_id', __('Category'));
$requesttype = new PluginReportsRequestTypeCriteria($report, 'requesttypes_id', __('Request source'));

//Display criterias form is needed
$report->displayCriteriasForm();

$columns = ['id'                => ['sorton' => 'id'],
                 'date'              => ['sorton' => 'date'],
                 'closedate'         => ['sorton' => 'closedate'],
                 'priority'          => ['sorton' => 'priority'],
                 'type'              => ['sorton' => 'type'],
                 'requesttypes_id'   => ['sorton' => 'requesttypes_id'],
                 'itilcategories_id' => ['sorton' => 'itilcategories_id'],
                 'slas_id_ttr'       => ['sorton' => 'slas_id_ttr'],
];

$output_type = Search::HTML_OUTPUT;

if (isset($_POST['list_limit'])) {
   $_SESSION['glpilist_limit'] = $_POST['list_limit'];
   unset($_POST['list_limit']);
}
if (!isset($_REQUEST['sort'])) {
   $_REQUEST['sort']  = "closedate";
   $_REQUEST['order'] = "ASC";
}

$limit = $_SESSION['glpilist_limit'];

if (isset($_POST["display_type"])) {
   $output_type = $_POST["display_type"];
   if ($output_type < 0) {
      $output_type = -$output_type;
      $limit       = 0;
   }
}

global $DB, $HEADER_LOADED, $CFG_GLPI;
//Report title
$title = $report->getFullTitle();
$dbu   = new DbUtils();

// SQL statement
$query = "SELECT glpi_tickets.*  
               FROM `glpi_tickets`
               WHERE `glpi_tickets`.`status` = '" . Ticket::CLOSED . "'";
$query .= $dbu->getEntitiesRestrictRequest('AND', "glpi_tickets", '', '', false);
$query .= $date->getSqlCriteriasRestriction();
$query .= $category->getSqlCriteriasRestriction();
if (isset($_POST['requesttypes_id']) && $_POST['requesttypes_id'] > 0) {
   $query .= $requesttype->getSqlCriteriasRestriction();
}
$query .= getOrderBy('closedate', $columns);

$res   = $DB->query($query);
$nbtot = ($res ? $DB->numrows($res) : 0);
if ($limit) {
   $start = (isset($_GET["start"]) ? $_GET["start"] : 0);
   if ($start >= $nbtot) {
      $start = 0;
   }
   if ($start > 0 || $start + $limit < $nbtot) {
      $res = $DB->query($query . " LIMIT $start,$limit");
   }
} else {
   $start = 0;
}

if ($nbtot == 0) {
   if (!$HEADER_LOADED) {
      Html::header($title, $_SERVER['PHP_SELF'], "utils", "report");
      Report::title();
   }
   echo "<div class='center red b'>" . __('No item found') . "</div>";
   Html::footer();
} else if ($output_type == Search::PDF_OUTPUT_PORTRAIT
           || $output_type == Search::PDF_OUTPUT_LANDSCAPE
) {
   include(GLPI_ROOT . "/lib/ezpdf/class.ezpdf.php");
} else if ($output_type == Search::HTML_OUTPUT) {
   if (!$HEADER_LOADED) {
      Html::header($title, $_SERVER['PHP_SELF'], "utils", "report");
      Report::title();
   }

   echo "<div class='center'>";

   echo "<table class='tab_cadre_fixe'>";
   echo "<tr><th>$title</th></tr>\n";

   echo "<tr class='tab_bg_2 center'><td class='center'>";
   echo "<form method='POST' action='" . $_SERVER["PHP_SELF"] . "?start=$start'>\n";

   $param = "";
   foreach ($_POST as $key => $val) {
      if (is_array($val)) {
         foreach ($val as $k => $v) {
            $name =  $key . "[$k]";
            echo Html::hidden($name, ['value' => $v]);
            if (!empty($param)) {
               $param .= "&";
            }
            $param .= $key . "[" . $k . "]=" . urlencode($v);
         }
      } else {
         echo Html::hidden($key, ['value' => $val]);
         if (!empty($param)) {
            $param .= "&";
         }
         $param .= "$key=" . urlencode($val);
      }
   }
   Dropdown::showOutputFormat();
   Html::closeForm();
   echo "</td></tr>";
   echo "</table></div>";

   Html::printPager($start, $nbtot, $_SERVER['PHP_SELF'], $param);
}

if ($res && $nbtot > 0) {

   $mylevels = [];
   $restrict = $dbu->getEntitiesRestrictCriteria("glpi_plugin_timelineticket_grouplevels", '', '', true) +
               ["ORDER" => "rank"];
   $levels = $dbu->getAllDataFromTable("glpi_plugin_timelineticket_grouplevels", $restrict);
   if (!empty($levels)) {
      foreach ($levels as $level) {
         $mylevels[$level["name"]] = json_decode($level["groups"], true);
      }
   }

   $nbCols = $DB->numFields($res);
   $nbrows = $DB->numrows($res);
   $num    = 1;
   $link   = $_SERVER['PHP_SELF'];
   $order  = 'ASC';
   $issort = false;

   echo Search::showHeader($output_type, $nbrows, $nbCols, false);

   echo Search::showNewLine($output_type);
   showTitle($output_type, $num, __('id'), 'id', true);
   showTitle($output_type, $num, __('Opening date'), 'date', true);
   showTitle($output_type, $num, __('Closing date'), 'closedate', true);
   showTitle($output_type, $num, __('Priority'), 'priority', true);
   showTitle($output_type, $num, __('Type'), 'type', true);
   showTitle($output_type, $num, __('Request source'), 'requesttypes_id', true);
   showTitle($output_type, $num, __('Category'), 'itilcategories_id', true);
   showTitle($output_type, $num, __('SLA'), 'slas_id_ttr', true);


   if (!empty($mylevels)) {
      foreach ($mylevels as $key => $val) {
         showTitle($output_type, $num, __('Duration by "in progress"', 'timelineticket') . "&nbsp;" . $key, '', false);
      }
   }
   echo Search::showEndLine($output_type);

   $row_num = 1;
   while ($data = $DB->fetchAssoc($res)) {

      $ticket = new Ticket();
      $ticket->getFromDB($data['id']);

      $timelevels = [];
      if (!empty($mylevels)) {
         foreach ($mylevels as $key => $val) {
            if (is_array($val)) {
               foreach ($val as $group => $groups_id) {

                  $a_details = getDetails($ticket, $groups_id);
                  $a_status = [];
                  foreach ($a_details as $time) {
                     if ($time['Status'] == Ticket::ASSIGNED || $time['Status'] == Ticket::PLANNED) {
                        if (isset($timelevels[$key])) {
                           $timelevels[$key] += ($time['End'] - $time['Start']);
                        } else {
                           $timelevels[$key] = ($time['End'] - $time['Start']);
                        }
                     }

                  }
               }
            }
         }
      }

      $row_num++;
      $num = 1;
      echo Search::showNewLine($output_type);

      $link = "<a href='".$CFG_GLPI["root_doc"].
                "/front/ticket.form.php?id=".$data["id"]."'>".$data['id']."</a>";
      echo Search::showItem($output_type, $link, $num, $row_num);
      echo Search::showItem($output_type, Html::convDateTime($data['date']), $num, $row_num);
      echo Search::showItem($output_type, Html::convDateTime($data['closedate']), $num, $row_num);
      echo Search::showItem($output_type, Ticket::getPriorityName($data['priority']), $num, $row_num);
      echo Search::showItem($output_type, Ticket::getTicketTypeName($data['type']), $num, $row_num);
      echo Search::showItem($output_type, Dropdown::getDropdownName('glpi_requesttypes', $data["requesttypes_id"]), $num, $row_num);
      echo Search::showItem($output_type, Dropdown::getDropdownName("glpi_itilcategories", $data["itilcategories_id"]), $num, $row_num);
      echo Search::showItem($output_type, Dropdown::getDropdownName('glpi_slas', $data["slas_id_ttr"]), $num, $row_num);

      $time = 0;
      if (!empty($mylevels)) {
         foreach ($mylevels as $key => $val) {
            if (array_key_exists($key, $timelevels)) {
               $time = $timelevels[$key];
            } else {
               $time = 0;
            }

            if ($output_type == Search::HTML_OUTPUT
                || $output_type == Search::PDF_OUTPUT_PORTRAIT
                || $output_type == Search::PDF_OUTPUT_LANDSCAPE) {
               echo Search::showItem($output_type, Html::timestampToString($time), $num, $row_num);
            } else {
               echo Search::showItem($output_type, Html::formatNumber($time / 3600, false, 5), $num, $row_num);
            }
         }
      }

      echo Search::showEndLine($output_type);
   }
   echo Search::showFooter($output_type, $title);
}

if ($output_type == Search::HTML_OUTPUT) {
   Html::footer();
}

/**
 * Display the column title and allow the sort
 *
 * @param      $output_type
 * @param      $num
 * @param      $title
 * @param      $columnname
 * @param bool $sort
 *
 * @return mixed
 */
function showTitle($output_type, &$num, $title, $columnname, $sort = false) {

   if ($output_type != Search::HTML_OUTPUT || $sort == false) {
      echo Search::showHeaderItem($output_type, $title, $num);
      return;
   }
   $order  = 'ASC';
   $issort = false;
   if (isset($_REQUEST['sort']) && $_REQUEST['sort'] == $columnname) {
      $issort = true;
      if (isset($_REQUEST['order']) && $_REQUEST['order'] == 'ASC') {
         $order = 'DESC';
      }
   }
   $link  = $_SERVER['PHP_SELF'];
   $first = true;
   foreach ($_REQUEST as $name => $value) {
      if (!in_array($name, ['sort', 'order', 'PHPSESSID'])) {
         $link .= ($first ? '?' : '&amp;');
         $link .= $name . '=' . urlencode($value);
         $first = false;
      }
   }
   $link .= ($first ? '?' : '&amp;') . 'sort=' . urlencode($columnname);
   $link .= '&amp;order=' . $order;
   echo Search::showHeaderItem($output_type, $title, $num, $link, $issort, ($order == 'ASC' ? 'DESC' : 'ASC'));
}

/**
 * Build the ORDER BY clause
 *
 * @param $default string, name of the column used by default
 * @param $columns
 *
 * @return string
 */
function getOrderBy($default, $columns) {

   if (!isset($_REQUEST['order']) || $_REQUEST['order'] != 'DESC') {
      $_REQUEST['order'] = 'ASC';
   }
   $order = $_REQUEST['order'];

   $sort = isset($_REQUEST['sort'])?$_REQUEST['sort']:$default;

   //   $tab = getOrderByFields($default, $columns);
   //   if (is_array($tab) && count($tab) > 0) {
   return " ORDER BY " . $sort . " " . $order;
   //   }
   return '';
}

/**
 * Get the fields used for order
 *
 * @param $default string, name of the column used by default
 *
 * @param $columns
 *
 * @return array of column names
 */
//function getOrderByFields($default, $columns) {
//
//   if (!isset($_REQUEST['sort'])) {
//      $_REQUEST['sort'] = $default;
//   }
//   $colsort = $_REQUEST['sort'];
//
//   foreach ($columns as $colname => $column) {
//      if ($colname == $colsort) {
//         return $column['sorton'];
//      }
//   }
//   return [];
//}

function getDetails(Ticket $ticket, $groups_id) {

   $ptState = new PluginTimelineticketState();

   $a_ret     = PluginTimelineticketDisplay::getTotaltimeEnddate($ticket);
   $totaltime = $a_ret['totaltime'];

   $ptItem = new PluginTimelineticketAssignGroup();

   $a_states     = [];
   $a_dbstates   = $ptState->find(["tickets_id" => $ticket->getField('id')], ['date', 'id']);
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

   $a_itemsections = [];
   $a_dbitems      = $ptItem->find(["tickets_id" => $ticket->getField('id'),
                                    'groups_id' => $groups_id], ['date']);
   foreach ($a_dbitems as $a_dbitem) {

      if (!isset($a_itemsections)) {
         $a_itemsections[$a_dbitem['groups_id']] = [];
         $last_statedelay                        = 0;
      } else {
         foreach ($a_itemsections as $data) {
            $last_statedelay = $data['End'];
         }
      }

      $gbegin = $a_dbitem['begin'];
      if ($a_dbitem['delay'] == '') {
         $gdelay = $totaltime;
      } else {
         $gdelay = $a_dbitem['begin'] + $a_dbitem['delay'];
      }
      $mem       = 0;
      $old_delay = 0;
      foreach ($a_states as $delay => $statusname) {
         if ($mem == 1) {
            if ($gdelay > $delay) { // all time of the state
               $a_itemsections[] = [
                  'Start'   => $gbegin,
                  'End'     => $delay,
                  "Caption" => "",
                  "Status"  => $statusname,

               ];
               $gbegin                                   = $delay;
            } else if ($gdelay == $delay) { // end of status = end of group
               $a_itemsections[] = [
                  'Start'   => $gbegin,
                  'End'     => $delay,
                  "Caption" => "",
                  "Status"  => $statusname,

               ];
               $mem                                      = 2;
            } else { // end of status is after end of group
               $a_itemsections[] = [
                  'Start'   => $gbegin,
                  'End'     => $gdelay,
                  "Caption" => "",
                  "Status"  => $statusname,

               ];
               $mem                                      = 2;
            }
         } else if ($mem == 0
                    && $gbegin < $delay) {
            if ($gdelay > $delay) { // all time of the state
               $a_itemsections[] = [
                  'Start'   => $gbegin,
                  'End'     => $delay,
                  "Caption" => "",
                  "Status"  => $statusname,

               ];
               $gbegin                                   = $delay;
               $mem                                      = 1;
            } else if ($gdelay == $delay) { // end of status = end of group
               $a_itemsections[] = [
                  'Start'   => $gbegin,
                  'End'     => $delay,
                  "Caption" => "",
                  "Status"  => $statusname,

               ];
               $mem                                      = 2;
            } else { // end of status is after end of group
               $a_itemsections[] = [
                  'Start'   => $gbegin,
                  'End'     => $gdelay,
                  "Caption" => "",
                  "Status"  => $statusname,

               ];
               $mem                                      = 2;
            }
         }
         $old_delay = $delay;
      }
   }

   return $a_itemsections;
}

