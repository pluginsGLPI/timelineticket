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

use CommonDBTM;
use CommonGLPI;
use DBConnection;
use Html;
use Migration;
use Ticket;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class AssignState extends CommonDBTM
{
    public static function addAssignState(Ticket $ticket)
    {
        // Instantiation of the object from the class AssignState
        $ptState = new self();


        $id = $ptState->add(['tickets_id' => $ticket->getID(),
            'date'       => $ticket->input['date'],
            'old_status' => 0,
            'new_status' => Ticket::INCOMING,
            'delay'      => 0]);

        if ($ticket->fields['status'] != Ticket::INCOMING && $id > 0) {
            $ptState->add(['tickets_id' => $ticket->getID(),
                'date'       => $ticket->input['date'],
                'old_status' => Ticket::INCOMING,
                'new_status' => $ticket->fields['status'],
                'delay'      => 0]);
        }
    }

    public static function addNewAssignState(Ticket $ticket)
    {
        global $DB;
        // Instantiation of the object from the class AssignState
        $ptState = new self();

        $iterator = $DB->request([
            'SELECT' => ['MAX' => 'date AS datedebut', 'id'],
            'FROM' => self::getTable(),
            'WHERE' => [
                'tickets_id' => $ticket->getID(),
            ],
        ]);
        if (count($iterator) > 0) {
            foreach ($iterator as $data) {
                $datedebut = $data['datedebut'];
            }
        } else {
            return;
        }

        if (!$datedebut) {
            $delay = 0;
            // Utilisation calendrier
//                    } elseif ($calendars_id > 0
// && $calendar->getFromDB($calendars_id)) {
//                        $delay = $calendar->getActiveTimeBetween($datedebut, $_SESSION["glpi_currenttime"]);
        } else {
            // cas 24/24 - 7/7
            $delay = strtotime($_SESSION["glpi_currenttime"]) - strtotime($datedebut);
        }
        //Test for plugin_timelineticket_ticket_add (already added)
        if ($ticket->fields['date_mod'] != $ticket->fields['date_creation']) {
            $ptState->add(['tickets_id' => $ticket->getID(),
                'date'       => $_SESSION["glpi_currenttime"],
                'old_status' => $ticket->oldvalues['status'],
                'new_status' => $ticket->fields['status'],
                'delay'      => $delay]);
        }


    }


    public static function getTotaltimeEnddate(CommonGLPI $ticket)
    {

        $totaltime = 0;

        $ptState   = new self();
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
            $totaltime += Tool::getPeriodTime(
                $ticket,
                $a_state['date'],
                date("Y-m-d H:i:s")
            );
        }
        $end_date = $totaltime;

        return ['totaltime' => $totaltime,
            'end_date'  => $end_date];
    }


    public static function showStateTimeline(Ticket $ticket)
    {
        global $DB;

        $req = $DB->request([
            'FROM'   => self::getTable(),
            'WHERE'  => ['tickets_id' => $ticket->getField('id')],
            'ORDER'  => 'id ASC',
        ]);

        if (count($req)) {
            echo "<tr class='tab_bg_2'>";
            echo "<td>";
            $states = [];
            $nb     = 0;
            $new    = null;

            foreach ($req as $data) {
                $date  = strtotime($data['date']);
                $now   = time();
                $class = 'checked';
                if (0 == $nb) {
                    $class = 'creation';
                }
                $states[$date . '_old_status'] = [
                    'timestamp' => $date,
                    'label'     => Ticket::getStatus($data['old_status']) . " (" . Html::timestampToString(
                        $data['delay'],
                        true
                    ) . ")",
                    'class'     => $class,
                ];
                $new = $data['new_status'];
                $nb++;
            }

            $states[$now . '_old_status'] = [
                'timestamp' => time(),
                'label'     => Ticket::getStatus($new) . " (" . Html::timestampToString((date(
                    'U'
                ) - strtotime($data['date'])), true) . ")",
                'class'     => 'now',
            ];

            $title = __('Ticket states history', 'timelineticket');
            echo "<div class='center'>";
            Html::showDatesTimelineGraph([
                'title'   => $title,
                'dates'   => $states,
                'add_now' => false,
            ]);
            echo "</div>";
            echo "</td>";
            echo "</tr>";
        }
    }



    public static function showHistory(Ticket $ticket, $item)
    {
        global $DB;

        $ticketId = $ticket->getField('id');

        $req = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['tickets_id' => $ticketId],
            'ORDER' => ['id ASC'],
        ]);
        $total = 0;
        $colspan = 5;
        echo "<table class='table table-bordered text-center rounded'>";
        if (count($req) === 0) {
            echo "<tr class='bg-body-tertiary'><td>" . __s('No results found') . "</td></tr>";
        } else {
            echo "<tr class='bg-body-tertiary'><th colspan='$colspan'>" . __('Result details');
            echo " (" . __('Statuses', 'timelineticket') . ")";
            echo "</th></tr>";

            echo "<tr>";
            echo "<td colspan='$colspan' style='width:100%'>";
            Display::showTimelineGraph($ticket, $item);
            echo "</td>";
            echo "</tr>";

            echo "<tr class='bg-body-tertiary'>";
            echo "<th>" . __('Old status', 'timelineticket') . "</th>";
            echo "<th>" . __('New status', 'timelineticket') . "</th>";
            echo "<th>" . __('Begin date') . "</th>";
            echo "<th>" . __('End date') . "</th>";
            echo "<th class='right'>" . __('Delay', 'timelineticket') . "</th>";
            echo "</tr>";

            $first = 0;
            foreach ($req as $data) {

                $status = __('New ticket');
                if ($data['old_status'] != 0) {
                    $status = Ticket::getStatus($data['old_status']);
                }
                echo "<tr>";
                echo "<td>" . $status . "</td>";
                echo "<td>" . Ticket::getStatus($data['new_status']) . "</td>";

                $date_begin[$first] = $data['date'];

                if (!isset($date_begin[$first - 1])) {
                    $olddate = $ticket->fields['date'];;
                } else {
                    $olddate = $date_begin[$first - 1];
                }
                $begin = strtotime($olddate);

                echo "<td>" . Html::convDateTime(date('Y-m-d H:i:s', $begin)) . "</td>";
                echo "<td>" . Html::convDateTime($data['date']) . "</td>";
                echo "<td class='right'>" . Html::timestampToString($data['delay'], true) . "</td>";
                echo "</tr>";

                $total += $data['delay'];

                $first++;

                if ($first == count($req)) {
                    if ($data['new_status'] != Ticket::CLOSED) {
                        echo "<tr class='tab_bg_1'>";
                        echo "<td>" . Ticket::getStatus($data['new_status']) . "</td>";
                        echo "<td></td>";
                        echo "<td>" . Html::convDateTime($data['date']) . "</td>";
                        echo "<td></td>";
                        echo "<td class='right'>" . Html::timestampToString(
                                (date('U') - strtotime($data['date'])),
                                true
                            ) . "</td>";
                        echo "</tr>";
                        $total += (date('U') - strtotime($data['date']));
                    }
                }
            }
        }
        echo "</table>";

        return $total;
    }

    /*
     * Function to reconstruct timeline for all tickets
     */
    public function reconstructTimeline($id = 0)
    {
        global $DB;

        $ticket = new Ticket();
        if ($id == 0) {
            $DB->delete($this->getTable(), [1]);
        } else {
            $DB->delete($this->getTable(), ['tickets_id' => $id]);
        }

        $criteria = [
            'SELECT' => '*',
            'FROM' => 'glpi_tickets',
        ];
        if ($id > 0) {
            $criteria['WHERE'] = ['id' => $id];
        }
        $iterator = $DB->request($criteria);

        foreach ($iterator as $data) {
            $queryl = [
                'SELECT' => '*',
                'FROM' => 'glpi_logs',
                'WHERE' => [
                    'items_id' => $data['id'],
                    'itemtype' => 'Ticket',
                    'id_search_option' => 12,
                ],
                'ORDERBY' => 'date_mod ASC',
            ];

            $resultl = $DB->request($queryl);

            if (count($resultl) > 0) {
                $first = 0;

                foreach ($resultl as $datal) {

                    $date_mod[$first] = $datal['date_mod'];

                    if (count($resultl) == 1) {
                        $delay = strtotime($datal['date_mod']) - strtotime($data['date']);
                    } else {

                        if (!isset($date_mod[$first - 1])) {
                            $olddate = $data['date'];
                        } else {
                            $olddate = $date_mod[$first - 1];
                        }
                        $delay = strtotime($datal['date_mod']) - strtotime($olddate);
                    }

                    $this->add(['tickets_id' => $data['id'],
                        'date'       => $datal['date_mod'],
                        'old_status' => $datal['old_value'],
                        'new_status' => $datal['new_value'],
                        'delay'      => $delay]);


                    $first++;
                }
            }
        }
    }

    public static function install(Migration $migration)
    {
        global $DB;

        $default_charset   = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();
        $table  = self::getTable();

        if (!$DB->tableExists($table)) {
            $query = "CREATE TABLE `$table` (
                        `id` int {$default_key_sign} NOT NULL auto_increment,
                        `tickets_id` int {$default_key_sign} NOT NULL DEFAULT '0',
                        `date` timestamp NULL DEFAULT NULL,
                        `old_status` varchar(255) DEFAULT NULL,
                        `new_status` varchar(255) DEFAULT NULL,
                        `delay` int(11) NULL,
                        PRIMARY KEY (`id`),
                        KEY `tickets_id` (`tickets_id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }

        $status =  ['new'           => Ticket::INCOMING,
            'assign'        => Ticket::ASSIGNED,
            'plan'          => Ticket::PLANNED,
            'waiting'       => Ticket::WAITING,
            'solved'        => Ticket::SOLVED,
            'closed'        => Ticket::CLOSED];

        // Update field in tables
        foreach (['glpi_plugin_timelineticket_assignstates'] as $table) {
            // Migrate datas
            foreach ($status as $old => $new) {
                $query = "UPDATE `$table`
                   SET `old_status` = '$new'
                   WHERE `old_status` = '$old'";
                $DB->doQuery($query);

                $query = "UPDATE `$table`
                   SET `new_status` = '$new'
                   WHERE `new_status` = '$old'";
                $DB->doQuery($query);
            }
        }

        $query = "ALTER TABLE `$table` CHANGE `delay` `delay` int(11) DEFAULT NULL;";
        $DB->doQuery($query);

        if (!$DB->tableExists("glpi_plugin_timelineticket_assignstates")
                && $DB->tableExists("glpi_plugin_timelineticket_states")) {
            $query = "RENAME TABLE `glpi_plugin_timelineticket_states` TO `glpi_plugin_timelineticket_assignstates`;";
            $DB->doQuery($query);
        }
    }

    public static function uninstall()
    {
        global $DB;

        $DB->dropTable(self::getTable(), true);
    }
}
