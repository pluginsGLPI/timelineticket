<?php

/*
 -------------------------------------------------------------------------
 TimelineTicket
 Copyright (C) 2013-2026 by the TimelineTicket Development Team.

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
 --------------------------------------------------------------------------
 */

namespace GlpiPlugin\Timelineticket;

use Calendar;
use CommonDBTM;
use CommonGLPI;
use CommonITILObject;
use DateTime;
use DbUtils;
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
use ITILFollowup;
use Ticket;
use TicketTask;
use User;

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
            return self::createTabEntry(self::getTypeName(1));
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
            'name' => self::getTypeName(1)
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
        echo "<i class='$icon me-2 text-danger'></i>" . _n("Timeline of ticket", "Timeline of tickets", 1, "timelineticket") . "</h4>";

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
                echo "<br><span class='red'>";
                echo __('Late') . "&nbsp;: ";
                echo Html::timestampToString($dateend, true);
                echo "</span>";
            }
        }

        echo "</div>";
        echo "</div>";

        echo "<div class='alert alert-secondary'>";
        echo "<i class='ti ti-info-circle'></i>";
        echo "&nbsp;";
        echo __('This view displays time spent by status, group, technician. The display does not use working hours', 'timelineticket');
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

        // Swimlane: one lane per ticket status, cards = group/user assignments
        echo "<div class='card-body'>";
        echo "<div class='row'>";
        echo "<div class='col-12'>";

        echo "<div class='card-header' class='w-auto px-2 fs-6 fw-bold'>";
        echo "<i class='ti ti-layout-rows me-1'></i>";
        echo __('Assignment swimlane', 'timelineticket');
        echo "</div>";
        echo "<div class='card-body'>";
        echo "<fieldset class='border p-3 mb-3 rounded'>";
        self::showSwimlane($ticket);
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

    /**
     * Build a swimlane diagram:
     * - One lane per existing GLPI ticket status (getAllStatusArray)
     * - Cards = group / technician assignments placed in the status that was
     *   active when the assignment was logged (determined via assignstates history)
     */
    public static function showSwimlane(Ticket $ticket): void
    {
        global $DB;

        $ticket_id = $ticket->getID();

        // ── 1. Build an ordered timeline of status intervals from assignstates ──
        // Each interval: ['status' => int, 'begin_ts' => int, 'end_ts' => int]
        $states_iter = $DB->request([
            'FROM'  => 'glpi_plugin_timelineticket_assignstates',
            'WHERE' => ['tickets_id' => $ticket_id],
            'ORDER' => ['id ASC'],
        ]);

        $intervals = [];
        $prev_ts   = strtotime($ticket->fields['date']);
        $last_new  = null;

        foreach ($states_iter as $row) {
            $end_ts = strtotime($row['date']);
            $intervals[] = [
                'status'   => (int) $row['old_status'],
                'begin_ts' => $prev_ts,
                'end_ts'   => $end_ts,
            ];
            $prev_ts  = $end_ts;
            $last_new = (int) $row['new_status'];
        }

        // Add the current/final status interval (still running or closed)
        if ($last_new !== null) {
            $final_end = ($ticket->fields['status'] == Ticket::CLOSED && $ticket->fields['closedate'])
                ? strtotime($ticket->fields['closedate'])
                : strtotime($_SESSION['glpi_currenttime']);
            $intervals[] = [
                'status'   => $last_new,
                'begin_ts' => $prev_ts,
                'end_ts'   => $final_end,
            ];
        }

        // ── 2. Load group assignments ────────────────────────────────────────
        $groups_iter = $DB->request([
            'FROM'  => 'glpi_plugin_timelineticket_assigngroups',
            'WHERE' => ['tickets_id' => $ticket_id],
            'ORDER' => ['id ASC'],
        ]);
        $all_events = [];
        foreach ($groups_iter as $row) {
            $all_events[] = [
                'ts'    => strtotime($row['date']),
                'label' => Dropdown::getDropdownName('glpi_groups', (int) $row['groups_id']),
                'type'  => 'group',
            ];
        }

        // ── 3. Load technician assignments ───────────────────────────────────
        $users_iter = $DB->request([
            'FROM'  => 'glpi_plugin_timelineticket_assignusers',
            'WHERE' => ['tickets_id' => $ticket_id],
            'ORDER' => ['id ASC'],
        ]);
        foreach ($users_iter as $row) {
            $user_obj = new User();
            $user_obj->getFromDB((int) $row['users_id']);
            $all_events[] = [
                'ts'    => strtotime($row['date']),
                'label' => $user_obj->getFriendlyName(),
                'type'  => 'user',
            ];
        }

        // ── 3b. Load followups ────────────────────────────────────────────────
        $followups_iter = $DB->request([
            'SELECT' => ['id', 'date', 'users_id', 'is_private', 'content'],
            'FROM'   => 'glpi_itilfollowups',
            'WHERE'  => ['items_id' => $ticket_id, 'itemtype' => 'Ticket'],
            'ORDER'  => ['date ASC'],
        ]);
        foreach ($followups_iter as $row) {
            $author = new User();
            $author->getFromDB((int) $row['users_id']);
            $all_events[] = [
                'ts'         => strtotime($row['date']),
                'label'      => $author->getFriendlyName(),
                'is_private' => (bool) $row['is_private'],
                'excerpt'    => mb_strimwidth(strip_tags((string) $row['content']), 0, 60, '…'),
                'type'       => 'followup',
            ];
        }

        // ── 3c. Load tasks ────────────────────────────────────────────────────
        $tasks_iter = $DB->request([
            'SELECT' => ['id', 'date', 'users_id', 'is_private', 'content', 'state'],
            'FROM'   => 'glpi_tickettasks',
            'WHERE'  => ['tickets_id' => $ticket_id],
            'ORDER'  => ['date ASC'],
        ]);
        foreach ($tasks_iter as $row) {
            $author = new User();
            $author->getFromDB((int) $row['users_id']);
            $all_events[] = [
                'ts'         => strtotime($row['date']),
                'label'      => $author->getFriendlyName(),
                'is_private' => (bool) $row['is_private'],
                'excerpt'    => mb_strimwidth(strip_tags((string) $row['content']), 0, 60, '…'),
                'type'       => 'task',
            ];
        }

        // ── 3d. Load solutions ────────────────────────────────────────────────
        $solutions_iter = $DB->request([
            'SELECT' => ['id', 'date_creation', 'users_id', 'content', 'status'],
            'FROM'   => 'glpi_itilsolutions',
            'WHERE'  => ['items_id' => $ticket_id, 'itemtype' => 'Ticket'],
            'ORDER'  => ['date_creation ASC'],
        ]);
        foreach ($solutions_iter as $row) {
            $author = new User();
            $author->getFromDB((int) $row['users_id']);
            $all_events[] = [
                'ts'      => strtotime($row['date_creation']),
                'label'   => $author->getFriendlyName(),
                'excerpt' => mb_strimwidth(strip_tags((string) $row['content']), 0, 60, '…'),
                'type'    => 'solution',
            ];
        }

        // ── 3e. Load validations ──────────────────────────────────────────────
        $valid_iter = $DB->request([
            'SELECT' => ['id', 'submission_date', 'users_id', 'status', 'comment_submission'],
            'FROM'   => 'glpi_ticketvalidations',
            'WHERE'  => ['tickets_id' => $ticket_id],
            'ORDER'  => ['submission_date ASC'],
        ]);
        foreach ($valid_iter as $row) {
            $author = new User();
            $author->getFromDB((int) $row['users_id']);
            $status_labels = [
                0 => __('Waiting'),
                1 => __('Refused'),
                2 => __('Granted'),
            ];
            $vstatus = $status_labels[(int) $row['status']] ?? __('Waiting');
            $all_events[] = [
                'ts'      => strtotime($row['submission_date']),
                'label'   => $author->getFriendlyName(),
                'excerpt' => $vstatus . (($row['comment_submission'] ?? '') !== ''
                    ? ' — ' . mb_strimwidth(strip_tags((string) $row['comment_submission']), 0, 40, '…')
                    : ''),
                'type'    => 'validation',
            ];
        }

        // ── 4. Resolve which status was active for each event ────────────────
        // Returns the status constant active at a given timestamp.
        $status_at = static function (int $ts) use ($intervals): ?int {
            foreach ($intervals as $iv) {
                if ($ts >= $iv['begin_ts'] && $ts <= $iv['end_ts']) {
                    return $iv['status'];
                }
            }
            return null;
        };

        // ── 5. Lanes = ALL existing GLPI ticket statuses ─────────────────────
        $all_statuses = Ticket::getAllStatusArray();

        $status_colors = [
            Ticket::INCOMING => ['bg' => '#e4f0d8', 'hdr' => '#8baf93'],
            Ticket::APPROVAL   => ['bg' => '#ebebeb', 'hdr' => '#8cabdb'],
            Ticket::ASSIGNED => ['bg' => '#dbae8c6b', 'hdr' => '#dbae8c'],
            Ticket::PLANNED  => ['bg' => '#162a5a47', 'hdr' => '#1b2f62'],
            Ticket::WAITING  => ['bg' => '#ffa50026', 'hdr' => 'orange'],
            Ticket::SOLVED   => ['bg' => '#e4f0d8', 'hdr' => '#3d9a50'],
            Ticket::CLOSED   => ['bg' => '#ebebeb', 'hdr' => '#8a8a8a'],
        ];

        // Build lanes keyed by status, pre-populate with empty event arrays
        $lanes = [];
        foreach ($all_statuses as $status_id => $status_label) {
            $lanes[$status_id] = [
                'label'  => $status_label,
                'events' => [],
            ];
        }

        // ── 5b. Pre-assign a unique card ID to each event, keyed by timestamp+type
        // so that arrow order follows chronology, not lane render order.
        $uid = 'tt' . $ticket_id;
        $global_seq = 0;
        // Give each event a stable card ID based on a global counter (assigned
        // here, before lane distribution) so the JS can reference them in
        // chronological order regardless of which lane they land in.
        foreach ($all_events as &$ev) {
            $ev['card_id'] = "{$uid}-c-{$global_seq}";
            $global_seq++;
        }
        unset($ev);

        // Fill event cards into the correct lane
        foreach ($all_events as $ev) {
            // Force specific types to their canonical lane regardless of timestamp
            if ($ev['type'] === 'solution') {
                $status = Ticket::SOLVED;
            } elseif ($ev['type'] === 'validation') {
                $status = Ticket::APPROVAL;
            } else {
                $status = $status_at($ev['ts']);
                // Fallback: unmatchable event → current ticket status
                if ($status === null) {
                    $status = (int) $ticket->fields['status'];
                }
            }
            if (isset($lanes[$status])) {
                $lanes[$status]['events'][] = $ev;
            }
        }

        // Build chronologically-ordered ID lists per type for the JS arrow chains.
        // $all_events is already ordered by DB id ASC (= chronological).
        $group_ids      = [];
        $user_ids       = [];
        $followup_ids   = [];
        $task_ids       = [];
        $solution_ids   = [];
        $validation_ids = [];
        foreach ($all_events as $ev) {
            switch ($ev['type']) {
                case 'group':
                    $group_ids[] = $ev['card_id'];
                    break;
                case 'user':
                    $user_ids[] = $ev['card_id'];
                    break;
                case 'followup':
                    $followup_ids[] = $ev['card_id'];
                    break;
                case 'task':
                    $task_ids[] = $ev['card_id'];
                    break;
                case 'solution':
                    $solution_ids[] = $ev['card_id'];
                    break;
                case 'validation':
                    $validation_ids[] = $ev['card_id'];
                    break;
            }
        }

        // ── 6. Render ─────────────────────────────────────────────────────────
        // Filter toolbar
        $duid = htmlspecialchars($uid);
        echo "<div class='tt-toolbar mb-2 d-flex gap-2'>";
        echo "<button type='button' class='btn btn-sm btn-outline-secondary tt-filter-btn active'
                data-uid='{$duid}' data-filter='all'>" . __('All') . "</button>";
        echo "<button type='button' class='btn btn-sm btn-outline-primary tt-filter-btn'
                data-uid='{$duid}' data-filter='group' style='color:#395bae;border-color:#395bae'>
                <i class='ti ti-users me-1'></i>" . _n('Group', 'Groups', 2) . "</button>";
        echo "<button type='button' class='btn btn-sm btn-outline-danger tt-filter-btn'
                data-uid='{$duid}' data-filter='user'>
                <i class='ti ti-user me-1'></i>" . _n('Technician', 'Technicians', 2, 'timelineticket') . "</button>";
        echo "<button type='button' class='btn btn-sm btn-outline-info tt-filter-btn'
                data-uid='{$duid}' data-filter='followup'>
                <i class='ti ti-message me-1'></i>" . _n('Followup', 'Followups', 2) . "</button>";
        echo "<button type='button' class='btn btn-sm btn-outline-warning tt-filter-btn'
                data-uid='{$duid}' data-filter='task'>
                <i class='ti ti-checkbox me-1'></i>" . _n('Task', 'Tasks', 2) . "</button>";
        echo "<button type='button' class='btn btn-sm btn-outline-success tt-filter-btn'
                data-uid='{$duid}' data-filter='solution'>
                <i class='ti ti-check me-1'></i>" . _n('Solution', 'Solutions', 2) . "</button>";
        echo "<button type='button' class='btn btn-sm btn-outline-secondary tt-filter-btn'
                data-uid='{$duid}' data-filter='validation' style='color:#7c3aed;border-color:#7c3aed'>
                <i class='ti ti-shield-check me-1'></i>" . __('Validation') . "</button>";
        echo "</div>";

        echo "<div class='tt-swimlane-wrap' id='{$uid}-wrap'>";
        echo "<div class='tt-swimlane' id='{$uid}-swimlane'>";

        foreach ($lanes as $status_id => $lane) {
            $colors = $status_colors[$status_id] ?? ['bg' => '#f5f5f5', 'hdr' => '#999'];

            echo "<div class='tt-lane'>";
            echo "<div class='tt-lane-hdr' style='background:" . htmlspecialchars($colors['hdr']) . "'>";
            echo htmlspecialchars($lane['label']);
            echo "</div>";

            echo "<div class='tt-lane-body' style='background:" . htmlspecialchars($colors['bg']) . "'>";
            foreach ($lane['events'] as $ev) {
                $type = $ev['type'];
                switch ($type) {
                    case 'group':
                        $cls  = 'tt-card-group';
                        $tlbl = __('Group');
                        break;
                    case 'user':
                        $cls  = 'tt-card-user';
                        $tlbl = __('Technician');
                        break;
                    case 'followup':
                        $cls  = 'tt-card-followup' . (($ev['is_private'] ?? false) ? ' tt-card-private' : '');
                        $tlbl = ($ev['is_private'] ?? false) ? _n('Followup', 'Followups', 1)." (".__('Private').")" : _n('Followup', 'Followups', 1);
                        break;
                    case 'task':
                        $cls  = 'tt-card-task' . (($ev['is_private'] ?? false) ? ' tt-card-private' : '');
                        $tlbl = ($ev['is_private'] ?? false) ? _n('Task', 'Tasks', 1)." (".__('Private').")" : _n('Task', 'Tasks', 1);
                        break;
                    case 'solution':
                        $cls  = 'tt-card-solution';
                        $tlbl = _n('Solution', 'Solutions', 1);
                        break;
                    case 'validation':
                        $cls  = 'tt-card-validation';
                        $tlbl = __('Validation');
                        break;
                    default:
                        $cls  = 'tt-card-task';
                        $tlbl = _n('Task', 'Tasks', 1);
                }
                echo "<div class='tt-card $cls' id='" . htmlspecialchars($ev['card_id']) . "'>";
                echo "<div class='tt-card-type'>" . htmlspecialchars($tlbl) . "</div>";
                echo "<div class='tt-card-name'>" . htmlspecialchars($ev['label']) . "</div>";
                if (!empty($ev['excerpt'])) {
                    echo "<div class='tt-card-excerpt'>" . htmlspecialchars($ev['excerpt']) . "</div>";
                }
                echo "<div class='tt-card-date'>" . Html::convDateTime(date('Y-m-d H:i:s', $ev['ts'])) . "</div>";
                echo "</div>";
            }
            echo "</div>"; // tt-lane-body
            echo "</div>"; // tt-lane
        }

        echo "<svg class='tt-arrows' id='{$uid}-svg' xmlns='http://www.w3.org/2000/svg'>";
        echo "<defs>";
        foreach ([
            'group'      => '#3a7bbf',
            'user'       => '#e05555',
            'followup'   => '#0891b2',
            'task'       => '#b45309',
            'solution'   => '#16a34a',
            'validation' => '#7c3aed',
        ] as $name => $color) {
            echo "<marker id='{$uid}-ah-{$name}' markerWidth='8' markerHeight='6' refX='8' refY='3' orient='auto'>";
            echo "<polygon points='0 0,8 3,0 6' fill='{$color}'/></marker>";
        }
        echo "</defs></svg>";

        echo "</div>"; // tt-swimlane
        echo "</div>"; // tt-swimlane-wrap

        echo "<script>
(function() {
    var uid           = " . json_encode($uid) . ";
    var grpIds        = " . json_encode(array_values($group_ids)) . ";
    var userIds       = " . json_encode(array_values($user_ids)) . ";
    var followupIds   = " . json_encode(array_values($followup_ids)) . ";
    var taskIds       = " . json_encode(array_values($task_ids)) . ";
    var solutionIds   = " . json_encode(array_values($solution_ids)) . ";
    var validationIds = " . json_encode(array_values($validation_ids)) . ";

    // ── helpers (module-level so both drawArrows and applyFilter can use them) ──

    function cardRect(id) {
        var wrap = document.getElementById(uid + '-wrap');
        var el   = document.getElementById(id);
        if (!wrap || !el) return null;
        var wr = wrap.getBoundingClientRect();
        var r  = el.getBoundingClientRect();
        return {
            left   : r.left   - wr.left,
            right  : r.right  - wr.left,
            top    : r.top    - wr.top,
            bottom : r.bottom - wr.top,
            midX   : r.left   - wr.left + r.width  / 2,
            midY   : r.top    - wr.top  + r.height / 2,
        };
    }

    // Per-type horizontal offset (px) so parallel chains don't overlap on vertical segments.
    // 6 types × 12px apart, centred around 0: -30, -18, -6, +6, +18, +30
    var TYPE_OFFSETS = { group: -30, user: -18, followup: -6, task: 6, solution: 18, validation: 30 };

    function laneTop(cardId) {
        var el = document.getElementById(cardId);
        if (!el) return null;
        var lane = el.closest('.tt-lane');
        if (!lane) return null;
        var wrap = document.getElementById(uid + '-wrap');
        return lane.getBoundingClientRect().top - wrap.getBoundingClientRect().top;
    }

    // off      = per-type lateral offset (keeps parallel chains apart across the whole swimlane)
    // exitOff  = per-segment exit side-step (+half gap): separates the in and out arrows at each card
    function addArrow(svg, fromId, toId, color, markerSuffix, off, exitOff) {
        var f = cardRect(fromId);
        var t = cardRect(toId);
        if (!f || !t) return;

        var fEl      = document.getElementById(fromId);
        var tEl      = document.getElementById(toId);
        var fLane    = fEl ? fEl.closest('.tt-lane') : null;
        var tLane    = tEl ? tEl.closest('.tt-lane') : null;
        var sameLane = (fLane && tLane && fLane === tLane);
        var fLaneTop = laneTop(fromId);
        var tLaneTop = laneTop(toId);
        var backward = (!sameLane && tLaneTop !== null && fLaneTop !== null && tLaneTop < fLaneTop);

        var x1, y1, x2, y2, cx, cy, gutter, pathD;

        if (sameLane) {
            var rowGap = t.midY - f.midY;
            if (Math.abs(rowGap) < 25) {
                // Same visual row: exit right, enter left
                x1 = f.right; y1 = f.midY + off + exitOff;
                x2 = t.left;  y2 = t.midY + off - exitOff;
                cx = (x1 + x2) / 2;
                pathD = 'M' + x1 + ',' + y1 + ' C' + cx + ',' + y1 + ' ' + cx + ',' + y2 + ' ' + x2 + ',' + y2;
            } else if (rowGap > 0) {
                // Wrapped to next row (forward): exit right → gutter → enter left
                x1 = f.right;  y1 = f.midY + off;
                x2 = t.left;   y2 = t.midY + off;
                gutter = Math.max(f.right, t.right) + 18 + Math.abs(off);
                pathD = 'M' + x1 + ',' + y1
                      + ' C' + gutter + ',' + y1
                      + ' '  + gutter + ',' + y2
                      + ' '  + x2    + ',' + y2;
            } else {
                // Wrapped backward (going up): exit left → gutter-left → enter right
                x1 = f.left;  y1 = f.midY + off;
                x2 = t.right; y2 = t.midY + off;
                gutter = Math.min(f.left, t.left) - 18 - Math.abs(off);
                pathD = 'M' + x1 + ',' + y1
                      + ' C' + gutter + ',' + y1
                      + ' '  + gutter + ',' + y2
                      + ' '  + x2    + ',' + y2;
            }
        } else if (!backward) {
            // Forward: exit bottom, enter top — offset horizontally
            x1 = f.midX + off + exitOff; y1 = f.bottom;
            x2 = t.midX + off - exitOff; y2 = t.top;
            cy = (y1 + y2) / 2;
            pathD = 'M' + x1 + ',' + y1 + ' C' + x1 + ',' + cy + ' ' + x2 + ',' + cy + ' ' + x2 + ',' + y2;
        } else {
            // Backward: exit top, enter bottom — offset horizontally
            x1 = f.midX + off - exitOff; y1 = f.top;
            x2 = t.midX + off + exitOff; y2 = t.bottom;
            cy = (y1 + y2) / 2;
            pathD = 'M' + x1 + ',' + y1 + ' C' + x1 + ',' + cy + ' ' + x2 + ',' + cy + ' ' + x2 + ',' + y2;
        }

        var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', pathD);
        path.setAttribute('stroke', color);
        path.setAttribute('fill', 'none');
        path.setAttribute('stroke-width', '1.8');
        if (backward) {
            path.setAttribute('stroke-dasharray', '3,4');
            path.setAttribute('stroke-opacity', '0.6');
        } else {
            path.setAttribute('stroke-dasharray', '5,3');
        }
        path.setAttribute('marker-end', 'url(#' + uid + '-ah-' + markerSuffix + ')');
        svg.appendChild(path);
    }

    function clearArrows(svg) {
        // Remove only <path> elements, leaving <defs> intact
        var paths = svg.querySelectorAll('path');
        paths.forEach(function(p) { svg.removeChild(p); });
    }

    // ── main draw / filter ───────────────────────────────────────────────────

    // exitOff: half the gap between the out arrow and the in arrow at each shared card (px)
    var EXIT_OFF = 5;

    function drawChain(svg, ids, color, marker, typeOffset) {
        for (var i = 0; i < ids.length - 1; i++) {
            addArrow(svg, ids[i], ids[i + 1], color, marker, typeOffset, EXIT_OFF);
        }
    }

    function drawArrows(filter) {
        var svg = document.getElementById(uid + '-svg');
        if (!svg) return;
        clearArrows(svg);
        if (filter === 'all' || filter === 'group')
            drawChain(svg, grpIds,        '#3a7bbf', 'group',      TYPE_OFFSETS.group);
        if (filter === 'all' || filter === 'user')
            drawChain(svg, userIds,       '#e05555', 'user',       TYPE_OFFSETS.user);
        if (filter === 'all' || filter === 'followup')
            drawChain(svg, followupIds,   '#0891b2', 'followup',   TYPE_OFFSETS.followup);
        if (filter === 'all' || filter === 'task')
            drawChain(svg, taskIds,       '#b45309', 'task',       TYPE_OFFSETS.task);
        if (filter === 'all' || filter === 'solution')
            drawChain(svg, solutionIds,   '#16a34a', 'solution',   TYPE_OFFSETS.solution);
        if (filter === 'all' || filter === 'validation')
            drawChain(svg, validationIds, '#7c3aed', 'validation', TYPE_OFFSETS.validation);
    }

    function applyFilter(filter) {
        var wrap = document.getElementById(uid + '-wrap');
        if (!wrap) return;

        var types = ['group', 'user', 'followup', 'task', 'solution', 'validation'];
        types.forEach(function(t) {
            var show = (filter === 'all' || filter === t);
            wrap.querySelectorAll('.tt-card-' + t).forEach(function(el) {
                el.style.display = show ? '' : 'none';
            });
        });

        drawArrows(filter);
    }

    // Toolbar buttons
    document.querySelectorAll('.tt-filter-btn[data-uid=' + JSON.stringify(uid) + ']').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.tt-filter-btn[data-uid=' + JSON.stringify(uid) + ']').forEach(function(b) {
                b.classList.remove('active');
            });
            btn.classList.add('active');
            applyFilter(btn.getAttribute('data-filter'));
        });
    });

    var attempts = 0;
    function tryDraw() {
        var wrap = document.getElementById(uid + '-wrap');
        if (wrap && wrap.offsetHeight > 0) {
            drawArrows('all');
        } else if (attempts++ < 20) {
            requestAnimationFrame(tryDraw);
        }
    }
    requestAnimationFrame(tryDraw);
})();
</script>";
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

        if ($item instanceof AssignGroup) {
            $mylevels = [];
            $dbu = new DbUtils();
            $restrict = $dbu->getEntitiesRestrictCriteria("glpi_plugin_timelineticket_grouplevels", '', '', true) +
                ["ORDER" => 'rank'];
            $levels = $dbu->getAllDataFromTable("glpi_plugin_timelineticket_grouplevels", $restrict);
            if (!empty($levels)) {
                foreach ($levels as $level) {
                    if (!empty($level["groups"])) {
                        $groups = json_decode($level["groups"], true);
                        $mylevels[$level["name"]] = $groups;
                    }
                }
            }
            $ticketlevels = [];
        }

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

                    if (count($mylevels) > 0) {
                        foreach ($mylevels as $levelname => $groups) {
                            if (in_array($v['groups_id'], $groups)) {
                                $name = $levelname;
                            } else {
                                $name = Dropdown::getDropdownName("glpi_groups", $v['groups_id']);
                            }
                        }
                    } else {
                        $name = Dropdown::getDropdownName("glpi_groups", $v['groups_id']);
                    }




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
