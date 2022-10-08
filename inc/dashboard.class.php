<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 timelineticket plugin for GLPI
 Copyright (C) 2018 by the timelineticket Development Team.

 https://github.com/pluginsGLPI/timelineticket
 -------------------------------------------------------------------------

 LICENSE

 This file is part of timelineticket.

 timelineticket is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 timelineticket is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with timelineticket. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

/**
 * Class PluginTimelineticketDashboard
 */
class PluginTimelineticketDashboard extends CommonGLPI {

   public $widgets = [];
   private $options;
   private $datas, $form;

   /**
    * PluginTimelineticketDashboard constructor.
    * @param array $options
    */
   function __construct($options = []) {
      $this->options = $options;
   }

   /**
    * @return array
    */
   function getWidgetsForItem() {
      $widgets = [
         __('Line charts', "mydashboard") => [
            $this->getType() . "1" => ["title"   => __("Number of assignments per technician to a ticket", "timelineticket"),
                                       "icon"    => "ti ti-chart-bar",
                                       "comment" => __("Number of time where a technician has been affected to a ticket", 'timelineticket')]
         ],
      ];
      return $widgets;
   }

   /**
    * @param $widgetId
    * @return PluginMydashboardDatatable
    */
   function getWidgetContentForItem($widgetId, $opt = []) {

      switch ($widgetId) {
         case $this->getType() . "1":
            if (Plugin::isPluginActive("timelineticket")) {
               $name    = 'AffectionTechBarChart';
               $widget = new PluginMydashboardHtml();
               $title  = __("Number of assignments per technician to a ticket", "timelineticket");
                $comment = "";
               $widget->setWidgetComment($comment);

               $preference = new PluginMydashboardPreference();
               $preference->getFromDB(Session::getLoginUserID());
               $preferences = $preference->fields;
               $criterias = ['entities_id',
                             'is_recursive',
                             'type',
                             'multiple_time',
                             'begin',
                             'end',
                             'technicians_groups_id'];

               $opt['begin'] = isset($opt['begin']) ? $opt['begin'] : date('Y-m-d H:i:s', strtotime('-1 year'));;
               $opt['end'] = isset($opt['end']) ? $opt['end'] : date('Y-m-d H:i:s');
               $params  = ["preferences" =>$preferences,
                           "criterias"   => $criterias,
                           "opt"         => $opt];
               $options = PluginMydashboardHelper::manageCriterias($params);


               $opt  = $options['opt'];
               $crit = $options['crit'];
               if (!isset($opt['technicians_groups_id']) || (isset($opt["technicians_groups_id"])
                                                             && count($opt["technicians_groups_id"]) == 0) && count($_SESSION['glpigroups']) > 0) {
                  $opt['technicians_groups_id'] = $_SESSION['glpigroups'];
               }
               $entities_id_criteria       = $crit['entity'];
               $sons_criteria              = $crit['sons'];
               $time_per_tech = self::getNumberAffectationPerTech($options);
               $labels = [];
               switch ($opt['multiple_time']){
                  case "MONTH":
                     $begin = new DateTime( $opt['begin'] );
                     $end = new DateTime( $opt['end'] );
                     $interval = new DateInterval('P1M');
                     $dateint= new DatePeriod($begin, $interval ,$end);
                     foreach ($dateint as $m) {


                        $labels[] = $m->format('m') . "/" . $m->format('Y');
                     }
                     break;
                  case "WEEK":
                     $begin = new DateTime( $opt['begin'] );
                     $end = new DateTime( $opt['end'] );
                     $interval = new DateInterval('P1W');
                     $dateint= new DatePeriod($begin, $interval ,$end);
                     foreach ($dateint as $w) {


                        $labels[] = "S".$w->format('W') . " - " . $w->format('Y');
                     }
                     break;
                  case "DAY":
                     for($i = strtotime($opt['begin']); $i <= strtotime($opt['end']); $i+=86400)
                     {
                        $labels[] = date("d/m/Y",$i);
                     }
                     break;
               }


               $nb_bar = 0;
               foreach ($time_per_tech as $tech_id => $tickets) {
                  $nb_bar++;
               }

               $i       = 0;
               $dataset = [];
               foreach ($time_per_tech as $tech_id => $times) {
                  unset($time_per_tech[$tech_id]);
                  $username  = getUserName($tech_id);
                   $dataset['data'][] = array_values($times);
                   $dataset['type']   = 'bar';
                   $dataset['name']   = $username;
                  $i++;
               }
               $dataLineset = json_encode($dataset);
               $labelsLine  = json_encode($labels);

               $graph_datas = ['title'   => $title,
                               'comment' => $comment,
                               'name'   => $name,
                               'ids'    => json_encode([]),
                               'data'   => $dataLineset,
                               'labels' => $labelsLine];
//               $graph_criterias = ['entities_id' => $entities_id_criteria,
//                                   'sons'        => $sons_criteria,
//                                   'type'        => $opt['type'],
//                                   //                                'year'        => $year_criteria,
//                                   'begin'       => $opt['begin'],
//                                   'end'         => $opt['end'],
//                                   'technicians_groups_id'         => $opt['technicians_groups_id'],
//                                   'multiple_time'         => $opt['multiple_time'],
//                                   'widget'      => $widgetId];
               $graph = PluginMydashboardBarChart::launchGraph($graph_datas, []);

               $params = ["widgetId"  => $widgetId,
                          "name"      => $name,
                          "onsubmit"  => true,
                          "opt"       => $opt,
                          "criterias" => $criterias,
                          "export"    => true,
                          "canvas"    => true,
                          "nb"        =>20];
               $widget->setWidgetHeader(PluginMydashboardHelper::getGraphHeader($params));


               $widget->setWidgetTitle($title);
               $widget->toggleWidgetRefresh();


               $widget->setWidgetHtmlContent(
                  $graph
               );

               return $widget;

            }
            break;

      }
   }

   /**
    * @param $params
    *
    * @return array
    */
   private static function getNumberAffectationPerTech($params) {
      global $DB;

      $time_per_tech = [];

      $opt               = $params['opt'];
      $crit              = $params['crit'];
      $type_criteria     = $crit['type'];
      $entities_criteria = $crit['entities_id'];

      $techlist = [];
      $selected_group = [];
      if (isset($opt["technicians_groups_id"])
          && count($opt["technicians_groups_id"]) > 0) {
         $selected_group = $opt['technicians_groups_id'];
      } else if (count($_SESSION['glpigroups']) > 0) {
         $selected_group = $_SESSION['glpigroups'];
      }
      if (count($selected_group) > 0) {
         $groups             = implode(",", $selected_group);
         $query_group_member = "SELECT `glpi_groups_users`.`users_id`"
                               . "FROM `glpi_groups_users` "
                               . "LEFT JOIN `glpi_groups` ON (`glpi_groups_users`.`groups_id` = `glpi_groups`.`id`) "
                               . "WHERE `glpi_groups_users`.`groups_id` IN (" . $groups . ") AND `glpi_groups`.`is_assign` = 1 "
                               . " GROUP BY `glpi_groups_users`.`users_id`";

         $result_gu = $DB->query($query_group_member);

         while ($data = $DB->fetchAssoc($result_gu)) {
            $techlist[] = $data['users_id'];
         }
      }

      if(!empty($techlist)) {
         $is_deleted = "`glpi_tickets`.`is_deleted` = 0";
         switch ($opt["multiple_time"]) {
            case "MONTH":
               $begin    = new DateTime($opt['begin']);
               $end      = new DateTime($opt['end']);
               $interval = new DateInterval('P1M');
               $dateint  = new DatePeriod($begin, $interval, $end);


               $condition_tech = ' AND `glpi_plugin_timelineticket_assignusers`.`users_id` IN (';
               $i              = 0;
               foreach ($techlist as $techid) {
                  if ($i != 0) {
                     $condition_tech .= ', ' . $techid;
                  } else {
                     $condition_tech .= '' . $techid;
                  }
                  $i++;
               }
               $condition_tech .= ")";

               //                  $time_per_tech[$techid][$key] = 0;

               $querym_ai   = "SELECT  COUNT(*) as numberAssignation,DATE_FORMAT(`glpi_plugin_timelineticket_assignusers`.`date`, '%m/%Y') as dkey,
                                            `glpi_plugin_timelineticket_assignusers`.`users_id` as users_id
                              FROM `glpi_plugin_timelineticket_assignusers`
                              LEFT JOIN `glpi_tickets` ON (`glpi_tickets`.`id` = `glpi_plugin_timelineticket_assignusers`.`tickets_id` AND $is_deleted)";
               $querym_ai   .= "WHERE ";
               $querym_ai   .= "(
                              
                                  (`glpi_plugin_timelineticket_assignusers`.`date`) >= '" . $opt['begin'] . "'
                                 AND (`glpi_plugin_timelineticket_assignusers`.`date`) <= '" . $opt['end'] . "' 
                                 {$condition_tech} "
                               . $entities_criteria . $type_criteria
                               . ")";
               $querym_ai   .= " GROUP BY DATE_FORMAT(`glpi_plugin_timelineticket_assignusers`.`date`, '%m/%Y'),`glpi_plugin_timelineticket_assignusers`.`users_id`;
                              ";
               $result_ai_q = $DB->query($querym_ai);
               while ($data = $DB->fetchAssoc($result_ai_q)) {
                  //               $time_per_tech[$techid][$key] += (self::TotalTpsPassesArrondis($data['actiontime_date'] / 3600 / 8));
                  if ($data['numberAssignation'] > 0) {
                     $time_per_tech[$data['users_id']][$data['dkey']] = $data['numberAssignation'];
                  } else {
                     $time_per_tech[$data['users_id']][$data['dkey']] = 0;
                  }
               }
               foreach ($dateint as $w) {
                  $key = $w->format('m') . "/" . $w->format('Y');
                  foreach ($techlist as $techid) {
                     if (!isset($time_per_tech[$techid][$key])) {
                        $time_per_tech[$techid][$key] = 0;
                     }
                  }
               }


               break;
            case "WEEK":
               $begin    = new DateTime($opt['begin']);
               $end      = new DateTime($opt['end']);
               $interval = new DateInterval('P1W');
               $dateint  = new DatePeriod($begin, $interval, $end);

               $condition_tech = ' AND `glpi_plugin_timelineticket_assignusers`.`users_id` IN (';
               $i              = 0;
               foreach ($techlist as $techid) {
                  if ($i != 0) {
                     $condition_tech .= ', ' . $techid;
                  } else {
                     $condition_tech .= '' . $techid;
                  }
                  $i++;
               }
               $condition_tech .= ")";


               $querym_ai   = "SELECT  COUNT(glpi_plugin_timelineticket_assignusers.id) as numberAssignation,
                                    YEAR(`glpi_plugin_timelineticket_assignusers`.`date`) as dyear, 
                                    WEEk(`glpi_plugin_timelineticket_assignusers`.`date`) as dweek,
                                            `glpi_plugin_timelineticket_assignusers`.`users_id` as users_id
                              FROM `glpi_plugin_timelineticket_assignusers`
                              LEFT JOIN `glpi_tickets` ON (`glpi_tickets`.`id` = `glpi_plugin_timelineticket_assignusers`.`tickets_id` AND $is_deleted)";
               $querym_ai   .= "WHERE ";
               $querym_ai   .= "(
                               
                                  (`glpi_plugin_timelineticket_assignusers`.`date`) >= '" . $opt['begin'] . "'
                                 AND (`glpi_plugin_timelineticket_assignusers`.`date`) <= '" . $opt['end'] . "'
                                 {$condition_tech}"
                               . $entities_criteria
                               . ")";
               $querym_ai   .= "GROUP BY  YEAR(`glpi_plugin_timelineticket_assignusers`.`date`) ,
                                    WEEk(`glpi_plugin_timelineticket_assignusers`.`date`),`glpi_plugin_timelineticket_assignusers`.`users_id` ;
                              ";
               $result_ai_q = $DB->query($querym_ai);
               while ($data = $DB->fetchAssoc($result_ai_q)) {
                  //               $time_per_tech[$techid][$key] += (self::TotalTpsPassesArrondis($data['actiontime_date'] / 3600 / 8));
                  if ($data['numberAssignation'] > 0) {
                     $key                                    = $data["dweek"] . "/" . $data['dyear'];
                     $time_per_tech[$data['users_id']][$key] = $data['numberAssignation'];
                  }
               }
               foreach ($dateint as $w) {
                  $key = $w->format('W') . "/" . $w->format('Y');
                  foreach ($techlist as $techid) {
                     if (!isset($time_per_tech[$techid][$key])) {
                        $time_per_tech[$techid][$key] = 0;
                     }
                  }
               }


               break;
            case "DAY":

               $condition_tech = ' AND `glpi_plugin_timelineticket_assignusers`.`users_id` IN (';
               $i              = 0;
               foreach ($techlist as $techid) {
                  if ($i != 0) {
                     $condition_tech .= ', ' . $techid;
                  } else {
                     $condition_tech .= '' . $techid;
                  }
                  $i++;
               }
               $condition_tech .= ")";

               $querym_ai   = "SELECT  COUNT(glpi_plugin_timelineticket_assignusers.id) as numberAssignation,
                                            DATE_FORMAT(`glpi_plugin_timelineticket_assignusers`.`date`,'%d/%m/%Y') as dkey,
                                            `glpi_plugin_timelineticket_assignusers`.`users_id` as users_id
                              FROM `glpi_plugin_timelineticket_assignusers`
                              LEFT JOIN `glpi_tickets` ON (`glpi_tickets`.`id` = `glpi_plugin_timelineticket_assignusers`.`tickets_id` AND $is_deleted)";
               $querym_ai   .= "WHERE ";
               $querym_ai   .= "(
                                 (`glpi_plugin_timelineticket_assignusers`.`date`) >= '" . $opt['begin'] . "'
                                 AND (`glpi_plugin_timelineticket_assignusers`.`date`) <= '" . $opt['end'] . "' 
                                 {$condition_tech} "
                               . $entities_criteria
                               . ")";
               $querym_ai   .= "GROUP BY  DATE_FORMAT(`glpi_plugin_timelineticket_assignusers`.`date`,'%d %m %Y'), 
               `glpi_plugin_timelineticket_assignusers`.`users_id`;
                              ";
               $result_ai_q = $DB->query($querym_ai);
               while ($data = $DB->fetchAssoc($result_ai_q)) {
                  //               $time_per_tech[$techid][$key] += (self::TotalTpsPassesArrondis($data['actiontime_date'] / 3600 / 8));
                  if ($data['numberAssignation'] > 0) {
                     $time_per_tech[$data['users_id']][$data['dkey']] = $data['numberAssignation'];
                  }
               }

               for ($i = strtotime($opt['begin']); $i <= strtotime($opt['end']); $i += 86400) {
                  $key = date("d/m/Y", $i);
                  foreach ($techlist as $techid) {
                     if (!isset($time_per_tech[$techid][$key])) {
                        $time_per_tech[$techid][$key] = 0;
                     }
                  }
               }


               break;

         }
      }
      return $time_per_tech;
   }

}



