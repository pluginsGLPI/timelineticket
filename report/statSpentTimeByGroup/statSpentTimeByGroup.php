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

//Options for GLPI 0.71 and newer : need slave db to access the report
$USEDBREPLICATE = 1;
$DBCONNECTION_REQUIRED = 1;

define('GLPI_ROOT', '../../../..');
include (GLPI_ROOT . "/inc/includes.php");

// Instantiate Report with Name
$report = new PluginReportsAutoReport();

//Report's search criterias
$dateYear = date("Y-m-d", mktime(0, 0, 0, date("m"), 1, date("Y") - 1));
$lastday = cal_days_in_month(CAL_GREGORIAN, date("m"), date("Y"));

if (date("d") == $lastday) {
   $dateMonthend = date("Y-m-d", mktime(0, 0, 0, date("m"), date("d"), date("Y")));
   $dateMonthbegin = date("Y-m-d", mktime(0, 0, 0, date("m"), 1, date("Y")));
} else {
   $lastday = cal_days_in_month(CAL_GREGORIAN, date("m") - 1, date("Y"));
   $dateMonthend = date("Y-m-d", mktime(0, 0, 0, date("m") - 1, $lastday, date("Y")));
   $dateMonthbegin = date("Y-m-d", mktime(0, 0, 0, date("m") - 1, 1, date("Y")));
}
$endDate = date("Y-m-d", mktime(0, 0, 0, date("m"), date("d"), date("Y")));


$date = new PluginReportsDateIntervalCriteria($report, '`glpi_tickets`.`closedate`', $LANG['reports'][61]);
$date->setStartDate($dateMonthbegin);
$date->setEndDate($dateMonthend);

//Display criterias form is needed
$report->displayCriteriasForm();

$columns = array('closedate' => array('sorton' => 'closedate'),
                  'id' => array('sorton' => 'id'),
                  'entities_id' => array('sorton' => 'entities_id'),
                  'status' => array('sorton' => 'status'),
                  'date' => array('sorton' => 'date'),
                  'date_mod' => array('sorton' => 'date_mod'),
                  'priority' => array('sorton' => 'priority'),
                  'type' => array('sorton' => 'type'),
                  'itilcategories_id' => array('sorton' => 'itilcategories_id'),
                  'name' => array('sorton' => 'name'),
                  'requesttypes_id' => array('sorton' => 'requesttypes_id')
    );
   
$output_type = HTML_OUTPUT;

if (isset($_POST['list_limit'])) {
   $_SESSION['glpilist_limit'] = $_POST['list_limit'];
   unset($_POST['list_limit']);
}
if (!isset($_REQUEST['sort'])) {
   $_REQUEST['sort'] = "entity";
   $_REQUEST['order'] = "ASC";
}

$limit = $_SESSION['glpilist_limit'];

if (isset($_POST["display_type"])) {
   $output_type = $_POST["display_type"];
   if ($output_type < 0) {
      $output_type = - $output_type;
      $limit = 0;
   }
} //else {
//   $output_type = HTML_OUTPUT;
//}
//Report title
$title = $report->getFullTitle();

// SQL statement
$query = "SELECT glpi_tickets.*  
               FROM `glpi_tickets`
               WHERE `glpi_tickets`.`status` = 'closed'";
$query .= getEntitiesRestrictRequest('AND',"glpi_tickets",'','',false);
$query .= $date->getSqlCriteriasRestriction();
$query .= getOrderBy('closedate', $columns);

$res = $DB->query($query);
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
   echo "<div class='center'><font class='red b'>" . $LANG['search'][15] . "</font></div>";
   Html::footer();
} else if ($output_type == PDF_OUTPUT_PORTRAIT 
               || $output_type == PDF_OUTPUT_LANDSCAPE) {
   include (GLPI_ROOT . "/lib/ezpdf/class.ezpdf.php");
} else if ($output_type == HTML_OUTPUT) {
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
            echo "<input type='hidden' name='" . $key . "[$k]' value='$v' >";
            if (!empty($param)) {
               $param .= "&";
            }
            $param .= $key . "[" . $k . "]=" . urlencode($v);
         }
      } else {
         echo "<input type='hidden' name='$key' value='$val' >";
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
 
$mylevels = array();
$restrict = getEntitiesRestrictRequest('',"glpi_plugin_timelineticket_grouplevels",'','',false);
$restrict .= "ORDER BY rank";
$levels = getAllDatasFromTable("glpi_plugin_timelineticket_grouplevels",$restrict);
if (!empty($levels)) {
   foreach ($levels as $level) {
      $mylevels[$level["name"]] = json_decode($level["groups"], true);
   }
}


if ($res && $nbtot > 0) {

   $nbCols = $DB->num_fields($res);
   $nbrows = $DB->numrows($res);
   $num = 1;
   $link = $_SERVER['PHP_SELF'];
   $order = 'ASC';
   $issort = false;

   echo Search::showHeader($output_type, $nbrows, $nbCols, false);

   echo Search::showNewLine($output_type);
   showTitle($output_type, $num, $LANG['common'][2], 'id', true);
   showTitle($output_type, $num, $LANG['entity'][0], 'entities_id', true);
   showTitle($output_type, $num, $LANG['joblist'][0], 'status', false);
   showTitle($output_type, $num, $LANG['reports'][60], 'date', true);
   showTitle($output_type, $num, $LANG['common'][26], 'date_mod', true);
   showTitle($output_type, $num, $LANG['joblist'][2], 'priority', true);
   showTitle($output_type, $num, $LANG['job'][18], '', false);
   showTitle($output_type, $num, $LANG['common'][17], 'type', true);
   showTitle($output_type, $num, $LANG['common'][36], 'itilcategories_id', true);
   showTitle($output_type, $num, $LANG['common'][57], 'name', true);
   showTitle($output_type, $num, $LANG['reports'][61], 'closedate', true);
   showTitle($output_type, $num, $LANG['job'][44], 'requesttypes_id', true);
   if (!empty($mylevels)) {
      foreach ($mylevels as $key => $val) {
         showTitle($output_type, $num, $key, '', false);
         showTitle($output_type, $num, $LANG['plugin_timelineticket']['statSpentTimeByGroup'][2]."&nbsp;".$key, '', false);
      }
   }
   showTitle($output_type, $num, $LANG['plugin_timelineticket']['statSpentTimeByGroup'][3], 'TOTAL', false);
   echo Search::showEndLine($output_type);

   $row_num = 1;
   while ($data = $DB->fetch_assoc($res)) {
      
      
      //Requesters
      $userdata = '';
      $ticket = new Ticket();
      $ticket->getFromDB($data['id']);

      if ($ticket->countUsers(CommonITILObject::REQUESTER)) {
         foreach ($ticket->getUsers(CommonITILObject::REQUESTER) as $d) {
            $k = $d['users_id'];
            if ($k) {
               $userdata.= getUserName($k);
            }
            if ($ticket->countUsers(CommonITILObject::REQUESTER) > 1) {
               $userdata .= "<br>";
            }
         }
      }
      
      //Time by level group
      $timegroups = array();
      $ticketgroups = array();
      
      $restrict = " `tickets_id` = " . $data["id"]." ORDER BY date";
      $groups = getAllDatasFromTable("glpi_plugin_timelineticket_assigngroups", $restrict);
      if (!empty($groups)) {
         foreach ($groups as $group) {
            if (isset($timegroups[$group["groups_id"]])) {
               if ($group["delay"] != null) {
                  $timegroups[$group["groups_id"]] += $group["delay"];
               } else {
                  $delay = strtotime($data["closedate"]) - strtotime($group["date"]);
                  $timegroups[$group["groups_id"]] += $delay;
               }
            } else {
               if ($group["delay"] != null) {
                  $timegroups[$group["groups_id"]] = $group["delay"];
               } else {
                  $delay = strtotime($data["closedate"]) - strtotime($group["date"]);
                  $timegroups[$group["groups_id"]] = $delay;
               }
            }
            if (!in_array($group["groups_id"],$ticketgroups)) {
               $ticketgroups[] = $group["groups_id"];
            }
         }
      }
      $timelevels = array();
      if (!empty($mylevels) 
            && !empty($timegroups)) {
         foreach ($mylevels as $key => $val) {
            foreach ($timegroups as $group => $time) {
               
               if (is_array($val) 
                     && in_array($group,$val)) {
                  if (isset($timelevels[$key])) {
                     $timelevels[$key] += $time;
                  } else {
                     $timelevels[$key] = $time;
                  }
               }
            }
         }
      }
      
      //Time of task by level group
      $tickettechs = array();
      $restrict = " `tickets_id` = " . $data["id"]." 
                  AND actiontime > 0 ORDER BY date";
      $tasks = getAllDatasFromTable("glpi_tickettasks",$restrict);
      
      if (!empty($tasks)) {
         foreach ($tasks as $task) {

            foreach (Group_User::getUserGroups($task["users_id"]) as $usergroups) {
               if (in_array($usergroups["id"],$ticketgroups)) {
                  if (isset($tickettechs[$usergroups["id"]])) {
                     $tickettechs[$usergroups["id"]] += $task["actiontime"];
                  } else {
                     $tickettechs[$usergroups["id"]] = $task["actiontime"];
                  }
               } 
            }
         }
      }

      $tasklevels = array();
      if (!empty($mylevels) 
            && !empty($tickettechs)) {
         foreach ($mylevels as $key => $val) {
            foreach ($tickettechs as $group => $time) {
               
               if (is_array($val) 
                     && in_array($group,$val)) {
                  if (isset($tasklevels[$key])) {
                     $tasklevels[$key] += $time;
                  } else {
                     $tasklevels[$key] = $time;
                  }
               }
            }
         }
      }
      
      //echo "<pre>";
      //print_r($mylevels);
      //echo "</pre>";
      //echo "<pre>";
      //print_r($timegroups);
      //echo "</pre>";
      //echo "<pre>";
      //print_r($tasklevels);
      //echo "</pre>";
      
      $row_num++;
      $num = 1;
      echo Search::showNewLine($output_type);
      echo Search::showItem($output_type, $data['id'], $num, $row_num);
      echo Search::showItem($output_type,Dropdown::getDropdownName('glpi_entities',$data['entities_id']),$num,$row_num);
      echo Search::showItem($output_type, Ticket::getStatus($data["status"]), $num, $row_num);
      echo Search::showItem($output_type, Html::convDateTime($data['date']), $num, $row_num);
      echo Search::showItem($output_type, Html::convDateTime($data['date_mod']), $num, $row_num);
      echo Search::showItem($output_type, Ticket::getPriorityName($data['priority']), $num, $row_num);
      echo Search::showItem($output_type, $userdata, $num, $row_num);
      echo Search::showItem($output_type, Ticket::getTicketTypeName($data['type']), $num, $row_num);
      echo Search::showItem($output_type, Dropdown::getDropdownName("glpi_itilcategories", $data["itilcategories_id"]), $num, $row_num);
      $out  = $ticket->getLink();
      echo Search::showItem($output_type, $out, $num, $row_num);
      echo Search::showItem($output_type, Html::convDateTime($data['closedate']), $num, $row_num);
      echo Search::showItem($output_type, Dropdown::getDropdownName('glpi_requesttypes', $data["requesttypes_id"]), $num, $row_num);
      $time = 0;
      if (!empty($mylevels)) {
         foreach ($mylevels as $key => $val) {
            if (array_key_exists($key, $timelevels)) {
               $time = $timelevels[$key];
            } else {
               $time = 0;
            }
            if (array_key_exists($key, $tasklevels)) {
               $timetask = $tasklevels[$key];
            } else {
               $timetask = 0;
            }
            echo Search::showItem($output_type, Html::formatNumber($timetask / 3600, 2), $num, $row_num);
            echo Search::showItem($output_type, Html::formatNumber($time / 3600, 2), $num, $row_num);
         }
      }
      $total = strtotime($ticket->fields["closedate"]) - strtotime($ticket->fields["date"]);
      echo Search::showItem($output_type, Html::formatNumber($total / 3600, 2), $num, $row_num);
      echo Search::showEndLine($output_type);
   }
   echo Search::showFooter($output_type, $title);
}

if ($output_type == HTML_OUTPUT) {
   Html::footer();
}

/**
 * Display the column title and allow the sort
 *
 * @param $output_type
 * @param $num
 * @param $title
 * @param $columnname
 * @param bool $sort
 * @return mixed
 */
function showTitle($output_type, &$num, $title, $columnname, $sort = false) {

   if ($output_type != HTML_OUTPUT || $sort == false) {
      echo Search::showHeaderItem($output_type, $title, $num);
      return;
   }
   $order = 'ASC';
   $issort = false;
   if (isset($_REQUEST['sort']) && $_REQUEST['sort'] == $columnname) {
      $issort = true;
      if (isset($_REQUEST['order']) && $_REQUEST['order'] == 'ASC') {
         $order = 'DESC';
      }
   }
   $link = $_SERVER['PHP_SELF'];
   $first = true;
   foreach ($_REQUEST as $name => $value) {
      if (!in_array($name, array('sort', 'order', 'PHPSESSID'))) {
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
 * @return string
 */
function getOrderBy($default, $columns) {

   if (!isset($_REQUEST['order']) || $_REQUEST['order'] != 'DESC') {
      $_REQUEST['order'] = 'ASC';
   }
   $order = $_REQUEST['order'];

   $tab = getOrderByFields($default, $columns);
   if (count($tab) > 0) {
      return " ORDER BY " . $tab . " " . $order;
   }
   return '';
}

/**
 * Get the fields used for order
 *
 * @param $default string, name of the column used by default
 *
 * @return array of column names
 */
function getOrderByFields($default, $columns) {

   if (!isset($_REQUEST['sort'])) {
      $_REQUEST['sort'] = $default;
   }
   $colsort = $_REQUEST['sort'];

   foreach ($columns as $colname => $column) {
      if ($colname == $colsort) {
         return $column['sorton'];
      }
   }
   return array();
}

?>