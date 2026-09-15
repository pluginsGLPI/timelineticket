<?php

/**
 * -------------------------------------------------------------------------
 * TimelineTicket
 * Copyright (C) 2013-2026 by the TimelineTicket Development Team.
 *
 * https://github.com/pluginsGLPI/timelineticket
 * ------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of TimelineTicket project.
 *
 * TimelineTicket plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * TimelineTicket plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with TimelineTicket plugin. If not, see <http://www.gnu.org/licenses/>.
 *
 * ------------------------------------------------------------------------
 *
 * @copyright Copyright (C) 2013-2025 TimelineTicket team
 * @license   AGPL License 3.0 or (at your option) any later version
 * @link      https://github.com/pluginsGLPI/timelineticket
 * @package   TimelineTicket plugin
 * @since     2013
 *            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 * --------------------------------------------------------------------------
 */

namespace GlpiPlugin\Timelineticket;

use CommonDBTM;
use CommonGLPI;
use DBConnection;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Migration;
use Throwable;
use Ticket;

class AssignState extends CommonDBTM
{
    public static $rightname = 'plugin_timelineticket_ticket';

    /**
     * Replay the visibility of the parent ticket at class level.
     *
     * These rows carry no entities_id column, so CommonDBTM::checkEntity() returns true
     * without checking anything and the inherited canViewItem() reduces to the global plugin
     * right alone. The screens of the plugin re-check the ticket themselves, but the generic
     * access paths of the core do not: the historical API only calls can($id, READ), which
     * would hand out the assignment map -- tickets, groups, users, delays -- of every entity
     * of the instance. Overriding here means every path inherits the control.
     **/
    public function canViewItem(): bool
    {
        if (!parent::canViewItem()) {
            return false;
        }

        $tickets_id = (int) ($this->fields['tickets_id'] ?? 0);
        $ticket     = new Ticket();

        return $tickets_id > 0 && $ticket->can($tickets_id, READ);
    }

    /**
     * Same reasoning as canViewItem(), on the write side.
     **/
    public function canUpdateItem(): bool
    {
        if (!parent::canUpdateItem()) {
            return false;
        }

        $tickets_id = (int) ($this->fields['tickets_id'] ?? 0);
        $ticket     = new Ticket();

        return $tickets_id > 0 && $ticket->can($tickets_id, UPDATE);
    }

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
        $datedebut = null;
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
        if ($ticket->fields['status'] != Ticket::CLOSED && $last_date !== '') {
            $totaltime += Tool::getPeriodTime(
                $ticket,
                $last_date,
                date("Y-m-d H:i:s"),
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
            $states = [];
            $nb     = 0;
            $new    = null;
            $now    = null;
            $data   = null;

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
                        true,
                    ) . ")",
                    'class'     => $class,
                ];
                $new = $data['new_status'];
                $nb++;
            }

            $states[$now . '_old_status'] = [
                'timestamp' => time(),
                'label'     => Ticket::getStatus($new) . " (" . Html::timestampToString((date(
                    'U',
                ) - strtotime($data['date'])), true) . ")",
                'class'     => 'now',
            ];

            $title = __('Ticket states history', 'timelineticket');
            ob_start();
            Html::showDatesTimelineGraph([
                'title'   => $title,
                'dates'   => $states,
                'add_now' => false,
            ]);
            $graph = ob_get_clean();

            TemplateRenderer::getInstance()->display('@timelineticket/timeline_row.html.twig', [
                'graph' => $graph,
            ]);
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
        $count = count($req);

        ob_start();
        Display::showTimelineGraph($ticket, $item);
        $chart = ob_get_clean();

        $rows       = [];
        $date_begin = [];
        $first      = 0;
        foreach ($req as $data) {
            $status = __('New ticket');
            if ($data['old_status'] != 0) {
                $status = Ticket::getStatus($data['old_status']);
            }

            $date_begin[$first] = $data['date'];
            if (!isset($date_begin[$first - 1])) {
                $olddate = $ticket->fields['date'];
            } else {
                $olddate = $date_begin[$first - 1];
            }
            $begin = strtotime($olddate);

            $rows[] = [
                'old_status' => $status,
                'new_status' => Ticket::getStatus($data['new_status']),
                'begin_date' => Html::convDateTime(date('Y-m-d H:i:s', $begin)),
                'end_date'   => Html::convDateTime($data['date']),
                'delay'      => Html::timestampToString($data['delay'], true),
            ];

            $total += $data['delay'];
            $first++;

            if ($first == $count && $data['new_status'] != Ticket::CLOSED) {
                $rows[] = [
                    'old_status' => Ticket::getStatus($data['new_status']),
                    'new_status' => '',
                    'begin_date' => Html::convDateTime($data['date']),
                    'end_date'   => '',
                    'delay'      => Html::timestampToString((date('U') - strtotime($data['date'])), true),
                ];
                $total += (date('U') - strtotime($data['date']));
            }
        }

        TemplateRenderer::getInstance()->display('@timelineticket/history.html.twig', [
            'rows'  => $rows,
            'chart' => $chart,
        ]);

        return $total;
    }

    /*
     * Function to reconstruct timeline for all tickets
     */
    public function reconstructTimeline($id = 0)
    {
        global $DB;

        // The rebuild deletes the rows it is about to recompute -- the whole table when $id is
        // 0 -- and then re-inserts them one by one from glpi_logs. Any failure in between (PHP
        // time limit, fatal error, database hiccup) used to leave the table truncated or half
        // rebuilt, with no way back and nothing telling the operator. A single transaction makes
        // the operation all or nothing: on error the previous content is restored and the
        // exception is rethrown, so the caller can report the failure.
        $DB->beginTransaction();
        try {
            $this->rebuildTimelineRows((int) $id);
            $DB->commit();
        } catch (Throwable $e) {
            $DB->rollBack();
            throw $e;
        }
    }

    /**
     * Delete and rebuild the state rows of one ticket, or of every ticket when $id is 0.
     *
     * Always called from inside the transaction opened by reconstructTimeline().
     *
     * @param int $id Ticket id, 0 for a global rebuild
     *
     * @return void
     */
    private function rebuildTimelineRows(int $id): void
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

        // The legacy table has to be taken over BEFORE the CREATE TABLE below, not after it.
        // The rename used to sit at the end of the method, guarded by "the target table does
        // not exist yet" -- a condition the create above had just made false for good. The
        // branch was therefore unreachable: an instance still carrying the pre-rename table
        // ended up with an empty _assignstates, its whole status history stranded in an orphan
        // table no code reads any more, and the Timeline tab silently blank on every old ticket.
        if (!$DB->tableExists($table) && $DB->tableExists('glpi_plugin_timelineticket_states')) {
            $DB->doQuery("RENAME TABLE `glpi_plugin_timelineticket_states` TO `$table`;");
        }

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

        // Migrate datas. This used to be wrapped in a foreach over a one-element array whose
        // value was the very table $table already holds, which silently reassigned $table for
        // the rest of the method: same table today, a trap for the next edit.
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

        $query = "ALTER TABLE `$table` CHANGE `delay` `delay` int(11) DEFAULT NULL;";
        $DB->doQuery($query);
    }

    public static function uninstall()
    {
        global $DB;

        $DB->dropTable(self::getTable(), true);
    }
}
