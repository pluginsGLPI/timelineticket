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
use Glpi\Application\View\TemplateRenderer;
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
        // Re-check the plugin right at render time, mirroring plugin_timelineticket_item_stats.
        // The tab is only registered when the right is held (evaluated at session init), but
        // do not rely solely on that gate: enforce it again here before disclosing the timeline.
        if (!Session::haveRightsOr('plugin_timelineticket_ticket', [READ, UPDATE])) {
            return false;
        }
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
            'name' => self::getTypeName(1),
        ];

        $tab[] = [
            'id' => '1',
            'table' => 'glpi_plugin_timelineticket_assigngroups',
            'field' => 'groups_id',
            'linkfield' => 'tickets_id',
            'name' => __('Group'),
            'datatype' => 'itemlink',
            'forcegroupby' => true,
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

        $subtitle = '';
        if ($item instanceof AssignGroup) {
            $subtitle = __('Groups in charge of the ticket', 'timelineticket');
        } elseif ($item instanceof AssignUser) {
            $subtitle = __('Technicians in charge of the ticket', 'timelineticket');
        }

        ob_start();
        self::showTimelineGraph($ticket, $item);
        $chart = ob_get_clean();

        $a_details = Tool::getDetails($ticket, $item, false);

        $rows = [];
        foreach ($a_details as $items_id => $a_detail) {
            $a_status = [];
            foreach ($a_detail as $data) {
                if (!isset($a_status[$data['Status']])) {
                    $a_status[$data['Status']] = 0;
                }
                $a_status[$data['Status']] += ($data['End'] - $data['Start']);
            }

            // getDropdownName() / getUserName() return raw DB values; Twig
            // auto-escaping applies them safely in the template.
            $label = '';
            if ($item instanceof AssignGroup) {
                $label = Dropdown::getDropdownName("glpi_groups", $items_id);
            } elseif ($item instanceof AssignUser) {
                $label = getUserName($items_id);
            }

            $cells = [];
            foreach ($list_status as $status => $name) {
                $cells[] = isset($a_status[$status])
                    ? Html::timestampToString($a_status[$status], true)
                    : '';
            }

            $rows[] = ['label' => $label, 'cells' => $cells];
        }

        TemplateRenderer::getInstance()->display('@timelineticket/detail.html.twig', [
            'subtitle' => $subtitle,
            'colspan'  => count($list_status) + 1,
            'statuses' => array_values($list_status),
            'chart'    => $chart,
            'rows'     => $rows,
        ]);
    }

    public static function showForTicket(Ticket $ticket)
    {
        global $DB;

        // Reconstruct button (Html::showSimpleForm emits its own form + CSRF token)
        ob_start();
        Html::showSimpleForm(
            PLUGIN_TIMELINETICKET_WEBDIR . "/front/config.form.php",
            'delete_review_from_list',
            _x('button', "Reconstruct history for this ticket", 'timelineticket'),
            [
                'tickets_id' => $ticket->getID(),
                'reconstructTicket' => 'reconstructTicket',
            ],
        );
        $reconstruct_button = ob_get_clean();

        // Used calendar link
        $calendar = new Calendar();
        $calendars_id = Entity::getUsedConfig(
            'calendars_strategy',
            $ticket->fields['entities_id'],
            'calendars_id',
            0,
        );
        if ($calendars_id > 0
            && $calendar->getFromDB($calendars_id)) {
            $calendar_link = $calendar->getLink();
        } else {
            $calendar_link = NOT_AVAILABLE;
        }

        // Late info (ticket has a due date already passed)
        $late = '';
        if ($ticket->fields['time_to_resolve']
            && $ticket->fields['status'] != CommonITILObject::WAITING
            && (strtotime(date('Y-m-d H:i:s')) - strtotime($ticket->fields['time_to_resolve'])) > 0) {
            $calendar = new Calendar();
            $calendars_id = Entity::getUsedConfig(
                'calendars_strategy',
                $ticket->fields['entities_id'],
                'calendars_id',
                0,
            );

            if ($calendars_id > 0
                && $calendar->getFromDB($calendars_id)) {
                if ($ticket->fields['closedate']) {
                    $dateend = $calendar->getActiveTimeBetween(
                        $ticket->fields['time_to_resolve'],
                        $ticket->fields['solvedate'],
                    );
                } else {
                    $dateend = $calendar->getActiveTimeBetween(
                        $ticket->fields['time_to_resolve'],
                        date('Y-m-d H:i:s'),
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
                $late = Html::timestampToString($dateend, true);
            }
        }

        // History table (also returns the accumulated total delay)
        ob_start();
        $total = AssignState::showHistory($ticket, new AssignState());
        $history = ob_get_clean();

        // Group / technician detail tables
        ob_start();
        self::showDetail($ticket, new AssignGroup());
        $detail_group = ob_get_clean();

        ob_start();
        self::showDetail($ticket, new AssignUser());
        $detail_user = ob_get_clean();

        // Swimlane visualization (markup + coupled inline JS generated server-side)
        ob_start();
        self::showSwimlane($ticket);
        $swimlane = ob_get_clean();

        // Debug tables (only in GLPI debug mode)
        $debug        = ($_SESSION['glpi_use_mode'] == Session::DEBUG_MODE);
        $debug_groups = [];
        $debug_users  = [];
        if ($debug) {
            $req = $DB->request([
                'FROM' => 'glpi_plugin_timelineticket_assigngroups',
                'WHERE' => ['tickets_id' => $ticket->getID()],
                'ORDER' => ['id DESC'],
            ]);
            foreach ($req as $data) {
                $debug_groups[] = [
                    'id'    => $data['id'],
                    'date'  => Html::convDateTime($data['date']),
                    // raw DB value, auto-escaped by Twig in the template
                    'name'  => Dropdown::getDropdownName("glpi_groups", $data['groups_id']),
                    'begin' => Html::timestampToString($data['begin']),
                    'delay' => Html::timestampToString($data['delay']),
                ];
            }

            $req = $DB->request([
                'FROM' => 'glpi_plugin_timelineticket_assignusers',
                'WHERE' => ['tickets_id' => $ticket->getID()],
                'ORDER' => ['id DESC'],
            ]);
            foreach ($req as $data) {
                $debug_users[] = [
                    'id'    => $data['id'],
                    'date'  => Html::convDateTime($data['date']),
                    // raw DB value, auto-escaped by Twig in the template
                    'name'  => getUserName($data['users_id']),
                    'begin' => Html::timestampToString($data['begin']),
                    'delay' => Html::timestampToString($data['delay']),
                ];
            }
        }

        TemplateRenderer::getInstance()->display('@timelineticket/ticket_timeline.html.twig', [
            'icon'               => self::getIcon(),
            'reconstruct_button' => $reconstruct_button,
            'calendar_link'      => $calendar_link,
            'late'               => $late,
            'info_message'       => __('This view displays time spent by status, group, technician. The display does not use working hours', 'timelineticket'),
            'history'            => $history,
            'total'              => Html::timestampToString($total, true),
            'detail_group'       => $detail_group,
            'detail_user'        => $detail_user,
            'swimlane'           => $swimlane,
            'debug'              => $debug,
            'debug_groups'       => $debug_groups,
            'debug_users'        => $debug_users,
        ]);
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
            // Respect GLPI private-item visibility: skip followups the current user
            // is not allowed to see (private ones authored by others without the
            // SEEPRIVATE right). canViewItem() applies the core visibility rules.
            $followup = new ITILFollowup();
            if (!$followup->getFromDB((int) $row['id']) || !$followup->canViewItem()) {
                continue;
            }
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
            // Respect GLPI private-item visibility: skip tasks the current user is
            // not allowed to see. canViewItem() applies the core visibility rules
            // (SEEPRIVATE right / public task / author).
            $task = new TicketTask();
            if (!$task->getFromDB((int) $row['id']) || !$task->canViewItem()) {
                continue;
            }
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
        // Build the filter-toolbar descriptors. Every label/class/style is handed
        // to the Twig template, which auto-escapes each value on output.
        $filters = [
            [
                'key'   => 'all',
                'btn'   => 'btn-outline-secondary active',
                'icon'  => '',
                'style' => '',
                'label' => __('All'),
            ],
            [
                'key'   => 'group',
                'btn'   => 'btn-outline-primary',
                'icon'  => 'ti ti-users',
                'style' => 'color:#395bae;border-color:#395bae',
                'label' => _n('Group', 'Groups', 2),
            ],
            [
                'key'   => 'user',
                'btn'   => 'btn-outline-danger',
                'icon'  => 'ti ti-user',
                'style' => '',
                'label' => _n('Technician', 'Technicians', 2, 'timelineticket'),
            ],
            [
                'key'   => 'followup',
                'btn'   => 'btn-outline-info',
                'icon'  => 'ti ti-message',
                'style' => '',
                'label' => _n('Followup', 'Followups', 2),
            ],
            [
                'key'   => 'task',
                'btn'   => 'btn-outline-warning',
                'icon'  => 'ti ti-checkbox',
                'style' => '',
                'label' => _n('Task', 'Tasks', 2),
            ],
            [
                'key'   => 'solution',
                'btn'   => 'btn-outline-success',
                'icon'  => 'ti ti-check',
                'style' => '',
                'label' => _n('Solution', 'Solutions', 2),
            ],
            [
                'key'   => 'validation',
                'btn'   => 'btn-outline-secondary',
                'icon'  => 'ti ti-shield-check',
                'style' => 'color:#7c3aed;border-color:#7c3aed',
                'label' => __('Validation'),
            ],
        ];

        // Build the lane descriptors (each with its ordered cards) for the template.
        $render_lanes = [];
        foreach ($lanes as $status_id => $lane) {
            $colors = $status_colors[$status_id] ?? ['bg' => '#f5f5f5', 'hdr' => '#999'];
            $cards  = [];
            foreach ($lane['events'] as $ev) {
                switch ($ev['type']) {
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
                        $tlbl = ($ev['is_private'] ?? false) ? _n('Followup', 'Followups', 1) . " (" . __('Private') . ")" : _n('Followup', 'Followups', 1);
                        break;
                    case 'task':
                        $cls  = 'tt-card-task' . (($ev['is_private'] ?? false) ? ' tt-card-private' : '');
                        $tlbl = ($ev['is_private'] ?? false) ? _n('Task', 'Tasks', 1) . " (" . __('Private') . ")" : _n('Task', 'Tasks', 1);
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
                $cards[] = [
                    'cls'        => $cls,
                    'card_id'    => $ev['card_id'],
                    'type_label' => $tlbl,
                    'name'       => $ev['label'],
                    'excerpt'    => $ev['excerpt'] ?? '',
                    'date'       => Html::convDateTime(date('Y-m-d H:i:s', $ev['ts'])),
                ];
            }
            $render_lanes[] = [
                'label'     => $lane['label'],
                'hdr_color' => $colors['hdr'],
                'bg_color'  => $colors['bg'],
                'cards'     => $cards,
            ];
        }

        // SVG arrow-head marker descriptors (one colour per event type).
        $markers = [
            ['name' => 'group',      'color' => '#3a7bbf'],
            ['name' => 'user',       'color' => '#e05555'],
            ['name' => 'followup',   'color' => '#0891b2'],
            ['name' => 'task',       'color' => '#b45309'],
            ['name' => 'solution',   'color' => '#16a34a'],
            ['name' => 'validation', 'color' => '#7c3aed'],
        ];

        // Chronological id chains consumed by the external JS arrow renderer.
        $chains = [
            'group'      => array_values($group_ids),
            'user'       => array_values($user_ids),
            'followup'   => array_values($followup_ids),
            'task'       => array_values($task_ids),
            'solution'   => array_values($solution_ids),
            'validation' => array_values($validation_ids),
        ];

        // All markup lives in the Twig template (auto-escaped); the arrow-drawing
        // logic is the external ES module js/swimlane.js (add_javascript_module),
        // which reads uid + chains from the wrap element's data-* attributes. No
        // inline <script> is emitted (CSP friendly).
        TemplateRenderer::getInstance()->display('@timelineticket/swimlane.html.twig', [
            'uid'     => $uid,
            'filters' => $filters,
            'lanes'   => $render_lanes,
            'markers' => $markers,
            'chains'  => json_encode($chains),
        ]);
    }

    public static function showTimelineGraph(Ticket $ticket, $item)
    {
        global $DB;

        $req = $DB->request([
            'FROM' => $item->getTable(),
            'WHERE' => ['tickets_id' => $ticket->getID()],
            'ORDER' => ['id ASC'],
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
                    fontSize: '12px',
                ),
            );

            // Draw all charts
            TemplateRenderer::getInstance()->display('@timelineticket/chart.html.twig', [
                'chart' => $chartService->render('ticket' . get_class($item)),
            ]);
        }
    }
}
