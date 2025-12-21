<?php

/*
   ------------------------------------------------------------------------
   TimelineTicket
   Copyright (C) 2013-2025 by the TimelineTicket Development Team.

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
   @copyright Copyright (C) 2013-2025 TimelineTicket team
   @license   AGPL License 3.0 or (at your option) any later version
              http://www.gnu.org/licenses/agpl-3.0-standalone.html
   @link      https://github.com/pluginsGLPI/timelineticket
   @since     2013

   ------------------------------------------------------------------------
 */

namespace GlpiPlugin\Timelineticket;

use Calendar;
use CommonDBTM;
use CommonGLPI;
use CommonITILObject;
use DateTime;
use Dropdown;
use Entity;
use Html;
use Session;
use Sportlog\GoogleCharts\Charts\Base\Column;
use Sportlog\GoogleCharts\Charts\Base\ColumnType;
use Sportlog\GoogleCharts\Charts\Base\DataTable;
use Sportlog\GoogleCharts\Charts\Options\Common\ChartLabelStyle;
use Sportlog\GoogleCharts\Charts\Options\TimelineChart\TimelineOptions;
use Sportlog\GoogleCharts\ChartService;
use Ticket;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class Display extends CommonDBTM
{

    public static function getTypeName($nb = 0)
    {
        return _n('Timeline of ticket', 'Timeline of tickets', $nb, 'timelineticket');
    }


    public static function getIcon()
    {
        return "ti ti-hourglass";
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item->getType() == 'Ticket'
            && $_SESSION['glpiactiveprofile']['interface'] == 'central') {
            return self::createTabEntry(__('Timeline', 'timelineticket'));
        }
        return '';
    }


    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item->getType() == 'Ticket') {
            self::showForTicket($item);
        }
        return true;
    }

    /**
     * @return array
     */
    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id' => 'common',
            'name' => __('Timeline', 'timelineticket')
        ];

        $tab[] = [
            'id' => '1',
            'table' => 'glpi_plugin_timelineticket_assigngroups',
            'field' => 'groups_id',
            'linkfield' => 'tickets_id',
            'name' => __('Group'),
            'datatype' => 'itemlink',
            'forcegroupby' => true
        ];

        return $tab;
    }


    /**
     * Used to display each status time used for each group/user
     *
     *
     * @param Ticket $ticket
     * @param        $type
     */
    public static function showDetail(Ticket $ticket, $item)
    {
        $ptState = new AssignState();

        $a_states = $ptState->find(["tickets_id" => $ticket->getID()], ["date"]);

        $a_state_delays = [];
        $a_state_num = [];
        $delay = 0;

        $list_status = Ticket::getAllStatusArray();

        foreach ($a_states as $array) {
            $delay += $array['delay'];
            $a_state_delays[$delay] = $array['old_status'];
        }

        echo "<table class='table table-bordered text-center rounded'>";

        echo "<tr class='bg-body-tertiary'>";
        echo "<th colspan='" . (count($list_status) + 1) . "'>";
        echo __('Result details');
        if ($item instanceof AssignGroup) {
            echo " (" . __('Groups in charge of the ticket', 'timelineticket') . ")";
        } elseif ($item instanceof AssignUser) {
            echo " (" . __('Technicians in charge of the ticket', 'timelineticket') . ")";
        }
        echo "</th>";
        echo "</tr>";

        echo "<tr>";
        echo "<td colspan='" . (count($list_status) + 1) . "' style='width:100%'>";
        self::showTimelineGraph($ticket, $item);
        echo "</td>";
        echo "</tr>";

        echo "<tr>";
        echo "<th class='bg-body-secondary'>";
        echo "</th>";
        foreach ($list_status as $name) {
            echo "<th class='bg-body-tertiary'>";
            echo $name;
            echo "</th>";
        }
        echo "</tr>";

        $a_details = Tool::getDetails($ticket, $item, false);

        foreach ($a_details as $items_id => $a_detail) {
            $a_status = [];
            foreach ($a_detail as $data) {
                if (!isset($a_status[$data['Status']])) {
                    $a_status[$data['Status']] = 0;
                }
                $a_status[$data['Status']] += ($data['End'] - $data['Start']);
            }
            echo "<tr>";
            if ($item instanceof AssignGroup) {
                echo "<th class='bg-body-tertiary'>" . Dropdown::getDropdownName("glpi_groups", $items_id) . "</th>";
            } elseif ($item instanceof AssignUser) {
                echo "<th class='bg-body-tertiary'>" . getUserName($items_id) . "</th>";
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

    public static function showForTicket(Ticket $ticket)
    {
        global $DB;

        echo "<div class='card'>";
        echo "<div class='card-header'>";

        echo "<h4 class='card-title d-flex align-items-center'>";
        $icon = self::getIcon();
        echo "<i class='$icon me-2 text-danger'></i>" . __('Timeline', 'timelineticket') . "</h4>";

        echo "<small class='text-muted d-flex align-items-center ms-auto'>";
        $target = PLUGIN_TIMELINETICKET_WEBDIR . "/front/config.form.php";
        Html::showSimpleForm(
            $target,
            'delete_review_from_list',
            _x('button', "Reconstruct history for this ticket", 'timelineticket'),
            [
                'tickets_id' => $ticket->getID(),
                'reconstructTicket' => 'reconstructTicket'
            ],
        //                           'fa-spinner fa-2x'
        );
        echo "</small>";
        echo "</div>";

        echo "<div class='card-header'>";
        echo "<div class='mb-3 d-flex flex-wrap gap-2'>";
        echo __('Used calendar', 'timelineticket')." - "._n('Time range', 'Time ranges', 2) . "&nbsp;: ";
        $calendar = new Calendar();
        $calendars_id = Entity::getUsedConfig(
            'calendars_strategy',
            $ticket->fields['entities_id'],
            'calendars_id',
            0
        );
        if ($calendars_id > 0
            && $calendar->getFromDB($calendars_id)) {
            echo $calendar->getLink();
        } else {
            echo NOT_AVAILABLE;
        }

        // Display ticket have Due date
        if ($ticket->fields['time_to_resolve']
            && $ticket->fields['status'] != CommonITILObject::WAITING
            && (strtotime(date('Y-m-d H:i:s')) - strtotime($ticket->fields['time_to_resolve'])) > 0) {
            $calendar = new Calendar();
            $calendars_id = Entity::getUsedConfig(
                'calendars_strategy',
                $ticket->fields['entities_id'],
                'calendars_id',
                0
            );

            if ($calendars_id > 0
                && $calendar->getFromDB($calendars_id)) {
                if ($ticket->fields['closedate']) {
                    $dateend = $calendar->getActiveTimeBetween(
                        $ticket->fields['time_to_resolve'],
                        $ticket->fields['solvedate']
                    );
                } else {
                    $dateend = $calendar->getActiveTimeBetween(
                        $ticket->fields['time_to_resolve'],
                        date('Y-m-d H:i:s')
                    );
                }
            } else {
                // cas 24/24 - 7/7
                if ($ticket->fields['closedate']) {
                    $dateend = strtotime($ticket->fields['solvedate']) - strtotime($ticket->fields['time_to_resolve']);
                } else {
                    $dateend = strtotime(date('Y-m-d H:i:s')) - strtotime($ticket->fields['time_to_resolve']);
                }
            }
            if ($dateend > 0) {
                echo "<br>";
                echo __('Late') . "&nbsp;: ";
                echo Html::timestampToString($dateend, true);
            }
        }

        echo "</div>";
        echo "</div>";

        echo "<div class='card-body'>";
        echo "<div class='row'>";
        echo "<div class='col-12'>";
        echo "<fieldset class='border p-3 mb-3 rounded'>";
        echo "<div class='row g-2'>";

        $total = AssignState::showHistory($ticket, new AssignState());

        echo "</div>";
        echo "</fieldset>";
        echo "</div>";
        echo "</div>";
        echo "</div>";

        echo "<div class='card-footer d-flex justify-content-between align-items-center'>";
        echo "<div class='text-muted d-flex align-items-center'>";
        echo "<i class='ti ti-info-circle me-1'></i>";
        echo "<span>" . __("Total") . "</span>";
        echo "<span class='ms-2'>";
        echo Html::timestampToString($total, true);
        echo "</span>";
        echo "</div>";
        echo "</div>";


        echo "<div class='card-body'>";
        echo "<div class='row'>";
        echo "<div class='col-12'>";
        echo "<fieldset class='border p-3 mb-3 rounded'>";
        echo "<div class='row g-2'>";

        self::showDetail($ticket, new AssignGroup());

        echo "</div>";
        echo "</fieldset>";
        echo "</div>";
        echo "</div>";
        echo "</div>";


        echo "<div class='card-body'>";
        echo "<div class='row'>";
        echo "<div class='col-12'>";
        echo "<fieldset class='border p-3 mb-3 rounded'>";
        echo "<div class='row g-2'>";

        self::showDetail($ticket, new AssignUser());

        echo "</div>";
        echo "</fieldset>";
        echo "</div>";
        echo "</div>";
        echo "</div>";

        echo "</div>";

        if ($_SESSION['glpi_use_mode'] == Session::DEBUG_MODE) {
            $req = $DB->request([
                'FROM' => 'glpi_plugin_timelineticket_assigngroups',
                'WHERE' => ['tickets_id' => $ticket->getID()],
                'ORDER' => ['id DESC'],
            ]);

            if (count($req) > 0) {
                echo "<br><table class='table table-bordered text-center rounded'>";
                echo "<tr>";
                echo "<th class='bg-body-tertiary' colspan='5'>" . __('DEBUG') . " " . __('Group') . "</th>";
                echo "</tr>";

                echo "<tr class='bg-body-tertiary'>";
                echo "<th>" . __('ID') . "</th>";
                echo "<th>" . __('Date') . "</th>";
                echo "<th>" . __('Group') . "</th>";
                echo "<th>" . __('Begin') . "</th>";
                echo "<th>" . __('Delay', 'timelineticket') . "</th>";
                echo "</tr>";

                foreach ($req as $data) {
                    echo "<tr>";
                    echo "<td>" . $data['id'] . "</td>";
                    echo "<td>" . Html::convDateTime($data['date']) . "</td>";
                    echo "<td>" . Dropdown::getDropdownName("glpi_groups", $data['groups_id']) . "</td>";
                    echo "<td>" . Html::timestampToString($data['begin']) . "</td>";
                    echo "<td>" . Html::timestampToString($data['delay']) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }

            $req = $DB->request([
                'FROM' => 'glpi_plugin_timelineticket_assignusers',
                'WHERE' => ['tickets_id' => $ticket->getID()],
                'ORDER' => ['id DESC'],
            ]);

            if (count($req) > 0) {
                echo "<br><table class='table table-bordered text-center rounded'>";
                echo "<tr class='bg-body-tertiary'>";
                echo "<th colspan='5'>" . __('DEBUG') . " " . __('Technician') . "</th>";
                echo "</tr>";

                echo "<tr class='bg-body-tertiary'>";
                echo "<th>" . __('ID') . "</th>";
                echo "<th>" . __('Date') . "</th>";
                echo "<th>" . __('Technician') . "</th>";
                echo "<th>" . __('Begin') . "</th>";
                echo "<th>" . __('Delay', 'timelineticket') . "</th>";
                echo "</tr>";

                foreach ($req as $data) {
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
    }

    public static function showTimelineGraph(Ticket $ticket, $item)
    {
        global $DB;

        $req = $DB->request([
            'FROM' => $item->getTable(),
            'WHERE' => ['tickets_id' => $ticket->getID()],
            'ORDER' => ['id ASC']
        ]);
        $a_gantt = [];

        foreach ($req as $datareq) {
            if ($item instanceof AssignUser) {
                $a_gantt[$datareq['id']]['users_id'] = $datareq['users_id'];
            } elseif ($item instanceof AssignGroup) {
                $a_gantt[$datareq['id']]['groups_id'] = $datareq['groups_id'];
            } else {
                $a_gantt[$datareq['id']]['old_status'] = $datareq['old_status'];
                $a_gantt[$datareq['id']]['new_status'] = $datareq['new_status'];
            }


//            $calendars_id = Entity::getUsedConfig(
//                'calendars_strategy',
//                $ticket->fields['entities_id'],
//                'calendars_id',
//                0
//            );

            if ($item instanceof AssignState) {
                $end_date = $datareq['date'];
                $str_end_date = strtotime($end_date) - $datareq['delay'];
                $a_gantt[$datareq['id']]['begin_date'] = date('Y-m-d H:i:s', $str_end_date);

                $a_gantt[$datareq['id']]['end_date'] = $datareq['date'];
                $a_gantt[$datareq['id']]['delay'] = $datareq['delay'];
            } else {
                $a_gantt[$datareq['id']]['begin_date'] = $datareq['date'];
                $a_gantt[$datareq['id']]['delay'] = $datareq['delay'];

                if ($datareq['delay'] == 0) {
                    $end_date = $_SESSION["glpi_currenttime"];
                    $a_gantt[$datareq['id']]['end_date'] = $end_date;
                } else {
                    $str_end_date = strtotime($datareq['date']) + $datareq['delay'];
                    $end_date = date('Y-m-d H:i:s', $str_end_date);
                    $a_gantt[$datareq['id']]['end_date'] = $end_date;
                }
            }
        }

//        \Toolbox::logInfo($a_gantt);
        if (count($a_gantt) > 0) {

            $chartService = new ChartService();
//            $calendar = new Calendar();
            $data = new DataTable();

            $data->addColumn(new Column(ColumnType::String, id: 'Task ID'));
            $data->addColumn(new Column(ColumnType::Date, id: 'Start'));
            $data->addColumn(new Column(ColumnType::Date, id: 'End'));

            $date = function ($input): DateTime {
                $year = date('Y', strtotime($input));
                $month = date('m', strtotime($input));
                $day = date('d', strtotime($input));
                $hour = date('H', strtotime($input));
                $minute = date('i', strtotime($input));
                $second = date('s', strtotime($input));

                $result = new DateTime();
                $result->setTimestamp(mktime($hour, $minute, $second, $month, $day, $year));
                return $result;
            };

            $height = 50;
            $i = [];
            $j = [];
            $k = [];
            $first = 0;
            foreach ($a_gantt as $key => $v) {
                if ($item instanceof AssignUser) {
                    $name = getUserName($v['users_id']);

                    if (!in_array($v['users_id'], $i)) {
                        $height += 50;
                    }
                    $i[] = $v['users_id'];
                } elseif ($item instanceof AssignGroup) {
                    $name = Dropdown::getDropdownName("glpi_groups", $v['groups_id']);

                    if (!in_array($v['groups_id'], $j)) {
                        $height += 50;
                    }
                    $j[] = $v['groups_id'];
                } else {
                    if ($v['old_status'] == 0) {
                        $name = __('New ticket');
                    } else {
                        $name = Ticket::getStatus($v['old_status']);
                    }

                    if (!in_array($v['old_status'], $k)) {
                        $height += 50;
                    }
                    $k[] = $v['old_status'];
                }
                $data->addRows([
                    [$name, $date($v['begin_date']), $date($v['end_date'])],
                ]);
                $first++;
                if ($first == count($a_gantt) && $item instanceof AssignState) {
                    if ($v['new_status'] != Ticket::CLOSED) {
                        $name = Ticket::getStatus($v['new_status']);
                        $data->addRows([
                            [$name, $date($v['end_date']), $date(date('Y-m-d H:i:s'))],
                        ]);
                        $height += 50;
                    }
                }
            }

            $chart = $chartService->createTimelineChart('ticket' . get_class($item), $data);
            $chart->options->avoidOverlappingGridLines = false;
            $chart->options->height = $height;
//        if ($item instanceof AssignState) {
//            $chart->options->colors = ['#49bf4d', '#49bf4d', 'orange', '#1b2f62'];
//        }
            $chart->options->timeline = new TimelineOptions(
                rowLabelStyle: new ChartLabelStyle(
                    color: '#333',
                    fontName: 'inter, -apple-system, blinkmacsystemfont, san francisco, segoe ui, roboto, helvetica neue, sans-serif',
                    fontSize: '12px'
                )
            );

            // Draw all charts
            echo "<div style='width:100%'>";
            echo $chartService->render('ticket' . get_class($item));
            echo "</div>";
        }
    }
}
