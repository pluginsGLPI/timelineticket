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
$report = new PluginReportsAutoReport(__("statResolvedSpentTimeByGroup_report_title", "timelineticket"));
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


$date = new PluginReportsDateIntervalCriteria($report, '`glpi_tickets`.`solvedate`', __('Date of solving'));
$date->setStartDate($dateMonthbegin);
$date->setEndDate($dateMonthend);

//Display criterias form is needed
$report->displayCriteriasForm();

$columns = ['solvedate'                  => ['sorton' => 'solvedate'],
            'id'                         => ['sorton' => 'id'],
            'entities_id'                => ['sorton' => 'entities_id'],
            'status'                     => ['sorton' => 'status'],
            'date'                       => ['sorton' => 'date'],
            'date_mod'                   => ['sorton' => 'date_mod'],
            'priority'                   => ['sorton' => 'priority'],
            'type'                       => ['sorton' => 'type'],
            'itilcategories_id'          => ['sorton' => 'itilcategories_id'],
            'name'                       => ['sorton' => 'name'],
            'requesttypes_id'            => ['sorton' => 'requesttypes_id'],
            'takeintoaccount_delay_stat' => ['sorton' => 'takeintoaccount_delay_stat'],
            'slas_id_ttr'                => ['sorton' => 'slas_id_ttr'],
];

$output_type = Search::HTML_OUTPUT;

if (isset($_POST['list_limit'])) {
   $_SESSION['glpilist_limit'] = $_POST['list_limit'];
   unset($_POST['list_limit']);
}
if (!isset($_REQUEST['sort'])) {
   $_REQUEST['sort']  = "solvedate";
   $_REQUEST['order'] = "ASC";
}

$limit = $_SESSION['glpilist_limit'];

if (isset($_POST["display_type"])) {
   $output_type = $_POST["display_type"];
   if ($output_type < 0) {
      $output_type = -$output_type;
      $limit       = 0;
   }
} //else {
//   $output_type = Search::HTML_OUTPUT;
//}

global $DB, $HEADER_LOADED;
//Report title
$title = $report->getFullTitle();
$dbu   = new DbUtils();
// SQL statement
$query = "SELECT glpi_tickets.*  
               FROM `glpi_tickets`
               WHERE (`glpi_tickets`.`status` = '" . Ticket::SOLVED . "' OR `glpi_tickets`.`status` = '" . Ticket::CLOSED . "')";
$query .= $dbu->getEntitiesRestrictRequest('AND', "glpi_tickets", '', '', false);
$query .= $date->getSqlCriteriasRestriction();
$query .= getOrderBy('solvedate', $columns);

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
   echo "<div class='center red b'>" . __('No item found') . "</div>";
   Html::footer();
} else if ($output_type == Search::PDF_OUTPUT_PORTRAIT
           || $output_type == Search::PDF_OUTPUT_LANDSCAPE) {
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
   $levels   = $dbu->getAllDataFromTable("glpi_plugin_timelineticket_grouplevels", $restrict);
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
   showTitle($output_type, $num, __('Entity'), 'entities_id', true);
   showTitle($output_type, $num, __('Status'), 'status', false);
   showTitle($output_type, $num, __('Opening date'), 'date', true);
   showTitle($output_type, $num, __('Last update'), 'date_mod', true);
   showTitle($output_type, $num, __('Priority'), 'priority', true);
   showTitle($output_type, $num, _n('Requester', 'Requesters', 2), '', false);
   showTitle($output_type, $num, __('Type'), 'type', true);
   showTitle($output_type, $num, __('Category'), 'itilcategories_id', true);
   showTitle($output_type, $num, __('Title'), 'name', true);
   showTitle($output_type, $num, __('Date of solving'), 'solvedate', true);
   showTitle($output_type, $num, __('Solved by', 'timelineticket'), '', false);
   showTitle($output_type, $num, __('Solved by (Group)', 'timelineticket'), '', false);
   showTitle($output_type, $num, __('Request source'), 'requesttypes_id', true);
   showTitle($output_type, $num, __('Take into account time'), 'takeintoaccount_delay_stat', true);
   showTitle($output_type, $num, __('SLA'), 'slas_id_ttr', true);
   showTitle($output_type, $num, __('Time to resolve exceedeed'), '', true);
   if (!empty($mylevels)) {
      foreach ($mylevels as $key => $val) {
         //         showTitle($output_type, $num, __('Tasks number by', 'timelineticket') . "&nbsp;" . $key, '', false);
         //         showTitle($output_type, $num, __('Tasks duration by', 'timelineticket') . "&nbsp;" . $key, '', false);
         showTitle($output_type, $num, __('Duration by', 'timelineticket') . "&nbsp;" . $key, '', false);
      }
   }
   showTitle($output_type, $num, __('Total waiting duration of ticket', 'timelineticket'), 'waiting_duration', false);
   showTitle($output_type, $num, __('Total duration of ticket', 'timelineticket'), 'TOTAL', false);
   echo Search::showEndLine($output_type);

   $row_num = 1;
   while ($data = $DB->fetchAssoc($res)) {


      //Requesters
      $userdata = '';
      $ticket   = new Ticket();
      $ticket->getFromDB($data['id']);

      if ($ticket->countUsers(CommonITILActor::REQUESTER)) {
         foreach ($ticket->getUsers(CommonITILActor::REQUESTER) as $d) {
            $k = $d['users_id'];
            if ($k) {
               $userdata .= getUserName($k);
            }
            if ($ticket->countUsers(CommonITILActor::REQUESTER) > 1) {
               $userdata .= "<br>";
            }
         }
      }

      //Time by level group
      $timegroups   = [];
      $ticketgroups = [];

      $restrict = ["tickets_id" => $data["id"]] + ["ORDER" => "date"];
      $groups   = $dbu->getAllDataFromTable("glpi_plugin_timelineticket_assigngroups", $restrict);
      if (!empty($groups)) {
         foreach ($groups as $group) {
            if (isset($timegroups[$group["groups_id"]])) {
               if ($group["delay"] != null) {
                  $timegroups[$group["groups_id"]] += $group["delay"];
               } else {
                  $calendar     = new Calendar();
                  $calendars_id = Entity::getUsedConfig('calendars_strategy', $ticket->fields['entities_id'], 'calendars_id', 0);
                  if ($calendars_id > 0 && $calendar->getFromDB($calendars_id)) {
                     $delay = $calendar->getActiveTimeBetween($group["date"], $data["closedate"]);

                  } else {
                     $delay = strtotime($data["closedate"]) - strtotime($group["date"]);
                  }

                  if ($delay < 0) {
                     $delay = 0;
                  }

                  $timegroups[$group["groups_id"]] += $delay;
               }
            } else {
               if ($group["delay"] != null) {
                  $timegroups[$group["groups_id"]] = $group["delay"];
               } else {
                  $calendar     = new Calendar();
                  $calendars_id = Entity::getUsedConfig('calendars_strategy', $ticket->fields['entities_id'], 'calendars_id', 0);
                  if ($calendars_id > 0 && $calendar->getFromDB($calendars_id)) {
                     $delay = $calendar->getActiveTimeBetween($group["date"], $data["closedate"]);

                  } else {
                     $delay = strtotime($data["closedate"]) - strtotime($group["date"]);
                  }

                  if ($delay < 0) {
                     $delay = 0;
                  }
                  $timegroups[$group["groups_id"]] = $delay;
               }
            }
            if (!in_array($group["groups_id"], $ticketgroups)) {
               $ticketgroups[] = $group["groups_id"];
            }
         }
      }
      $timelevels = [];
      if (!empty($mylevels)
          && !empty($timegroups)) {
         foreach ($mylevels as $key => $val) {
            foreach ($timegroups as $group => $time) {

               if (is_array($val)
                   && in_array($group, $val)) {
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
      $tickettechs = [];

      $restrict = ["tickets_id" => $data["id"], "actiontime" => ['>', 0]] + ["ORDER" => "date"];

      $tasks = $dbu->getAllDataFromTable("glpi_tickettasks", $restrict);

      if (!empty($tasks)) {
         foreach ($tasks as $task) {

            foreach (Group_User::getUserGroups($task["users_id"]) as $usergroups) {
               if (in_array($usergroups["id"], $ticketgroups)) {
                  if (isset($tickettechs[$usergroups["id"]])) {
                     $tickettechs[$usergroups["id"]] += $task["actiontime"];
                  } else {
                     $tickettechs[$usergroups["id"]] = $task["actiontime"];
                  }
               }
            }
         }
      }

      $tasklevels   = [];
      $nbtasklevels = [];
      if (!empty($mylevels)
          && !empty($tickettechs)) {
         foreach ($mylevels as $key => $val) {
            foreach ($tickettechs as $group => $time) {

               if (is_array($val)
                   && in_array($group, $val)) {
                  if (isset($tasklevels[$key])) {
                     $tasklevels[$key]   += $time;
                     $nbtasklevels[$key] += 1;
                  } else {
                     $tasklevels[$key]   = $time;
                     $nbtasklevels[$key] = 1;
                  }
               }
            }
         }
      }

      $is_late         = 0;
      $time_to_resolve = strtotime($data['time_to_resolve']);
      $solvedate       = strtotime($data['solvedate']);
      $now             = time();
      $goal_solvedate  = ($solvedate > 0 ? $solvedate : $now);
      if ($time_to_resolve != false && $time_to_resolve < $goal_solvedate) {
         $is_late = 1;
      }

      $row_num++;
      $num = 1;
      echo Search::showNewLine($output_type);
      //show ID ticket
      echo Search::showItem($output_type, $data['id'], $num, $row_num);
      //show Entity ticket
      echo Search::showItem($output_type, Dropdown::getDropdownName('glpi_entities', $data['entities_id']), $num, $row_num);
      //show ticket status
      echo Search::showItem($output_type, Ticket::getStatus($data["status"]), $num, $row_num);
      //show creation date ticket
      echo Search::showItem($output_type, Html::convDateTime($data['date']), $num, $row_num);
      //show modification date ticket
      echo Search::showItem($output_type, Html::convDateTime($data['date_mod']), $num, $row_num);
      //show priority ticket
      echo Search::showItem($output_type, Ticket::getPriorityName($data['priority']), $num, $row_num);
      //show requester ticket
      echo Search::showItem($output_type, $userdata, $num, $row_num);
      //show type ticket
      echo Search::showItem($output_type, Ticket::getTicketTypeName($data['type']), $num, $row_num);
      //show category ticket
      echo Search::showItem($output_type, Dropdown::getDropdownName("glpi_itilcategories", $data["itilcategories_id"]), $num, $row_num);
      //show title and link ticket
      $out = $ticket->getLink();
      echo Search::showItem($output_type, $out, $num, $row_num);
      //show solve date ticket
      echo Search::showItem($output_type, Html::convDateTime($data['solvedate']), $num, $row_num);
      //show solver ticket
      $users_id_solver = 0;
      $iterator        = $DB->request([
                                         'SELECT' => 'users_id',
                                         'FROM'   => 'glpi_itilsolutions',
                                         'WHERE'  => [
                                            'items_id' => $data["id"],
                                            'itemtype' => 'Ticket',
                                            'status'   => CommonITILValidation::ACCEPTED,
                                         ],
                                         'ORDER'  => 'id DESC',
                                         'LIMIT'  => 1
                                      ]);
         foreach ($iterator as $datasolution) {
         $users_id_solver = $datasolution['users_id'];
         iterator->next();
      }
      echo Search::showItem($output_type, getUserName($users_id_solver), $num, $row_num);

      //show group solver ticket

      $groups = "";

      foreach ($ticket->getGroups(CommonITILActor::ASSIGN) as $current_group) {
         $groups.= Dropdown::getDropdownName("glpi_groups", $current_group["groups_id"]);
         $groups.= "<br>";
      }

      echo Search::showItem($output_type, $groups, $num, $row_num);
      //show request source ticket
      echo Search::showItem($output_type, Dropdown::getDropdownName('glpi_requesttypes', $data["requesttypes_id"]), $num, $row_num);

      if ($output_type == Search::HTML_OUTPUT
          || $output_type == Search::PDF_OUTPUT_PORTRAIT
          || $output_type == Search::PDF_OUTPUT_LANDSCAPE) {
         echo Search::showItem($output_type, Html::timestampToString($data["takeintoaccount_delay_stat"]), $num, $row_num);
      } else {
         echo Search::showItem($output_type, convertTimestamp($data["takeintoaccount_delay_stat"]), $num, $row_num);
      }
      echo Search::showItem($output_type, Dropdown::getDropdownName('glpi_slas', $data["slas_id_ttr"]), $num, $row_num);
      echo Search::showItem($output_type, Dropdown::getYesNo($is_late), $num, $row_num);
      $time = 0;
      if (!empty($mylevels)) {

         foreach ($mylevels as $key => $val) {
            if (array_key_exists($key, $timelevels)) {
               $time = $timelevels[$key];

               $a_details     = PluginTimelineticketToolbox::getDetails($ticket, 'group', false);
               $waiting_group = 0;
               $solved_group = 0;
               $time_passed = 0;
               foreach ($a_details as $items_id => $a_detail) {

                  if (in_array($items_id, $val)) {
                     $a_status = [];
                     foreach ($a_detail as $data) {
                        if (!isset($a_status[$data['Status']])) {
                           $a_status[$data['Status']] = 0;
                        }
                        $a_status[$data['Status']] += ($data['End'] - $data['Start']);
                     }
                     $list_status = Ticket::getAllStatusArray();
                     foreach ($list_status as $status => $name) {
                        if (isset($a_status[$status]) && $status == Ticket::WAITING) {
                           $waiting_group += $a_status[$status];
                        }
                        if (isset($a_status[$status]) && $status == Ticket::SOLVED) {
                           $solved_group += $a_status[$status];
                        }
                        if (isset($a_status[$status]) && $status != Ticket::SOLVED && $status != Ticket::CLOSED && $status != Ticket::WAITING && $status != 0) {
                           $time_passed += $a_status[$status];
                        }
                     }
                  }
               }
               $time = $time - $waiting_group;
               $time = $time - $solved_group;

               $time = $time_passed;

            } else {
               $time = 0;
            }
            //            if (array_key_exists($key, $tasklevels)) {
            //               $timetask = $tasklevels[$key];
            //            } else {
            //               $timetask = 0;
            //            }
            //            if (array_key_exists($key, $nbtasklevels)) {
            //               $nbtasks = $nbtasklevels[$key];
            //            } else {
            //               $nbtasks = 0;
            //            }
            //            if ($output_type == Search::HTML_OUTPUT
            //                || $output_type == Search::PDF_OUTPUT_PORTRAIT
            //                || $output_type == Search::PDF_OUTPUT_LANDSCAPE) {
            //               echo Search::showItem($output_type, $nbtasks, $num, $row_num);
            //               echo Search::showItem($output_type, Html::timestampToString($timetask), $num, $row_num);
            //            } else {
            //               echo Search::showItem($output_type, $nbtasks, $num, $row_num);
            //               echo Search::showItem($output_type, convertTimestamp($timetask), $num, $row_num);
            //            }
            if($time<0){
               $time = $time_passed;
            }
            if ($output_type == Search::HTML_OUTPUT
                || $output_type == Search::PDF_OUTPUT_PORTRAIT
                || $output_type == Search::PDF_OUTPUT_LANDSCAPE) {
               echo Search::showItem($output_type, Html::timestampToString($time), $num, $row_num);
            } else {
               echo Search::showItem($output_type, convertTimestamp($time), $num, $row_num);
            }
         }
      }

      $waiting = $ticket->fields["waiting_duration"];
      if ($output_type == Search::HTML_OUTPUT
          || $output_type == Search::PDF_OUTPUT_PORTRAIT
          || $output_type == Search::PDF_OUTPUT_LANDSCAPE) {
         echo Search::showItem($output_type, Html::timestampToString($waiting), $num, $row_num);
      } else {
         echo Search::showItem($output_type, convertTimestamp($waiting), $num, $row_num);
      }

      $total = $ticket->fields["solve_delay_stat"];
      if ($output_type == Search::HTML_OUTPUT
          || $output_type == Search::PDF_OUTPUT_PORTRAIT
          || $output_type == Search::PDF_OUTPUT_LANDSCAPE) {
         echo Search::showItem($output_type, Html::timestampToString($total), $num, $row_num);
      } else {
         echo Search::showItem($output_type, convertTimestamp($total), $num, $row_num);
      }
      echo Search::showEndLine($output_type);
   }
   echo Search::showFooter($output_type, $title);
}

if ($output_type == Search::HTML_OUTPUT) {
   Html::footer();
}


function convertTimestamp($tps) {
   // Calcul du jour
   $j = $tps / 86400;
   $j = floor($j);
   // Calcul des heures
   //   $h      = $tps / 86400;
   //   $h      = $h - $j;
   //   $h      *= 24;
   //   $heures = floor($h); // On crée une nouvelle variable pour garder $h qui va nous servir après.
   // Calcul des minutes
   //   $mn  = $h - $heures;
   //   $mn  *= 60;
   //   $min = floor($mn);
   //   // Calcul des secondes
   //   $s = $mn - $min;
   //   $s *= 60;
   //   $s = floor($s);
   date_default_timezone_set('UTC');
   //   $j = date('d',$tps);
   $heures = date('H', $tps);
   $min    = date('i', $tps);
   $s      = date('s', $tps);
   // Echo
   return sprintf('%s:%s:%s:%s', $j, $heures, $min, $s);
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
         $link  .= ($first ? '?' : '&amp;');
         $link  .= $name . '=' . urlencode($value);
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
   $sort  = isset($_REQUEST['sort']) ? $_REQUEST['sort'] : $default;

   //   $tab = getOrderByFields($default, $columns);
   if (in_array($sort, $columns)) {
      return " ORDER BY " . $sort . " " . $order;
   }
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

