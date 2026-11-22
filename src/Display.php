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

use Calendar;
use CommonGLPI;
use CommonITILObject;
use DbUtils;
use Dropdown;
use Entity;
use Glpi\Application\View\TemplateRenderer;
use Glpi\Toolbox\FrontEnd;
use Html;
use Session;
use ITILFollowup;
use Ticket;
use TicketTask;
use TicketValidation;
use User;

/**
 * Renders the timeline tab of a ticket. Everything here is static presentation: there is no
 * glpi_plugin_timelineticket_displays table and plugin_timelineticket_install() creates none,
 * so the class descends from CommonGLPI -- which is all Plugin::registerClass() asks for an
 * addtabon registration -- and not from CommonDBTM, whose whole contract is a table. The
 * inheritance it carried made it look like a queryable itemtype to the generic routes of
 * GLPI 11 and had it answer 1146 on a table that never existed; front/display.php closes the
 * list route, and GenericFormController::checkIsValidClass() now closes the form one on its
 * own.
 */
class Display extends CommonGLPI
{
    public static $rightname = 'plugin_timelineticket_ticket';

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

    // rawSearchOptions() stood here and described a column of
    // glpi_plugin_timelineticket_assigngroups -- another class's table -- for a class that has
    // none. Nothing read it: the two ticket search options the plugin publishes come from
    // plugin_timelineticket_getAddSearchOptions(), which gates them on the plugin right. All
    // it did was tell the generic list route of GLPI 11 that this class was worth searching.

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

        // Reconstruct button (Html::showSimpleForm emits its own form + CSRF token).
        // The tab opens on plugin_timelineticket_ticket READ, but rebuilding deletes and
        // re-inserts the rows of the ticket, so front/config.form.php requires UPDATE on it.
        // Offering the button to a reader only produced a refusal: ask the same question here
        // as the controller does, the way the global rebuild buttons already do.
        $reconstruct_button = '';
        if ($ticket->can($ticket->getID(), UPDATE)) {
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
        }

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
            'info_message'       => Config::useCalendar()
                ? __('This view displays time spent by status, group, technician. The display uses the working hours of the calendar', 'timelineticket')
                : __('This view displays time spent by status, group, technician. The display does not use working hours', 'timelineticket'),
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
        // Respect GLPI validation visibility: TicketValidation::canView() requires a
        // dedicated 'ticketvalidation' right (create/validate/purge), independent of
        // the ticket-view right that gates this tab. Without this guard the swimlane
        // would disclose the requester name, the approval decision, and a comment
        // excerpt to users the core Validation tab hides them from. Mirrors the
        // canViewItem() gating on the followups/tasks blocks above.
        if (TicketValidation::canView()) {
            $valid_iter = $DB->request([
                'SELECT' => ['id', 'submission_date', 'users_id', 'status', 'comment_submission'],
                'FROM'   => 'glpi_ticketvalidations',
                'WHERE'  => ['tickets_id' => $ticket_id],
                'ORDER'  => ['submission_date ASC'],
            ]);
            foreach ($valid_iter as $row) {
                $author = new User();
                $author->getFromDB((int) $row['users_id']);
                // Ask the core for the label rather than keep a table of our own: the one that
                // stood here was indexed 0/1/2 while glpi_ticketvalidations.status carries the
                // constants of CommonITILValidation (NONE = 1, WAITING = 2, ACCEPTED = 3,
                // REFUSED = 4). Not one real value was named correctly -- a validation still
                // waiting (2) read "Granted", and both an acceptance (3) and a refusal (4) fell
                // through to the default and read "Waiting". getStatus() covers the four
                // constants and follows the translations of the core.
                $vstatus = (string) TicketValidation::getStatus((int) $row['status']);
                $all_events[] = [
                    'ts'      => strtotime($row['submission_date']),
                    'label'   => $author->getFriendlyName(),
                    'excerpt' => $vstatus . (($row['comment_submission'] ?? '') !== ''
                        ? ' — ' . mb_strimwidth(strip_tags((string) $row['comment_submission']), 0, 40, '…')
                        : ''),
                    'type'    => 'validation',
                ];
            }
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
                'style' => 'color:' . self::toThemedForeground('#395bae')
                    . ';border-color:' . self::toThemedForeground('#395bae'),
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
                'style' => 'color:' . self::toThemedForeground('#7c3aed')
                    . ';border-color:' . self::toThemedForeground('#7c3aed'),
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
                'bg_color'  => self::toThemedBackground($colors['bg']),
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

    /**
     * Wrap a colour so that it stays exactly as written under a light palette and
     * shifts towards the theme surface under a dark one.
     *
     * The mix ratio lives in the plugin stylesheet: --tt-dark-bg-mix is 0% on :root
     * and 80% under [data-glpi-theme-dark="1"]. At 0% the mix yields the original
     * colour byte for byte, so nothing changes for light palettes. Colours carrying
     * an alpha channel are handled by color-mix() as well, which is what the pale
     * translucent lane backgrounds rely on.
     *
     * The result contains none of the five characters Twig escapes in an HTML
     * context, so it survives {{ ... }} in a style attribute untouched.
     */
    private static function toThemedBackground(string $color): string
    {
        return 'color-mix(in srgb, ' . $color . ', var(--tblr-bg-surface) var(--tt-dark-bg-mix, 0%))';
    }

    /**
     * Same idea for a foreground colour: --tt-dark-fg-mix lifts it towards the theme
     * text colour under a dark palette, where several of the type accents fell below
     * the readable contrast ratio on a black background.
     */
    private static function toThemedForeground(string $color): string
    {
        return 'color-mix(in srgb, ' . $color . ', var(--tblr-body-color) var(--tt-dark-fg-mix, 0%))';
    }

    /**
     * Periods of the timeline chart of a ticket, one row per status, group or technician
     * ($item), drawn by public/js/timeline_chart.js.
     *
     * The chart is drawn on wall-clock dates, while the delays may be counted in the opening
     * hours of the ticket calendar (Config::useCalendar()): the dates are never derived from
     * the delays by plain addition.
     *
     * @param AssignState|AssignGroup|AssignUser $item
     *
     * @return array{rows: list<string>, periods: list<array{row: int, begin: string, end: string, duration: string}>}
     */
    public static function getChartData(Ticket $ticket, $item): array
    {
        global $DB;

        $mylevels = [];
        if ($item instanceof AssignGroup) {
            $dbu = new DbUtils();
            $restrict = $dbu->getEntitiesRestrictCriteria("glpi_plugin_timelineticket_grouplevels", '', '', true) +
                ["ORDER" => 'rank'];
            foreach ($dbu->getAllDataFromTable("glpi_plugin_timelineticket_grouplevels", $restrict) as $level) {
                if (!empty($level["groups"])) {
                    $mylevels[$level["name"]] = json_decode($level["groups"], true);
                }
            }
        }

        $rows    = [];
        $periods = [];
        // Bars sharing a label share a row, in the order the labels first appear
        $add = static function (string $name, string $begin, string $end, int $seconds) use (&$rows, &$periods): void {
            $row = array_search($name, $rows, true);
            if ($row === false) {
                $rows[] = $name;
                $row    = count($rows) - 1;
            }
            $periods[] = [
                'row'      => $row,
                'begin'    => $begin,
                'end'      => $end,
                'duration' => Html::timestampToString($seconds, true),
            ];
        };

        $now           = $_SESSION["glpi_currenttime"];
        $previous_date = null;
        $last_state    = null;
        foreach (
            $DB->request([
                'FROM'  => $item->getTable(),
                'WHERE' => ['tickets_id' => $ticket->getID()],
                'ORDER' => ['id ASC'],
            ]) as $data
        ) {
            if ($item instanceof AssignState) {
                $name = $data['old_status'] == 0 ? __('New ticket') : Ticket::getStatus($data['old_status']);
                // A status starts where the previous one ended, the first one at the creation
                // of the ticket
                $add($name, $previous_date ?? $ticket->fields['date'], $data['date'], (int) $data['delay']);
                $previous_date = $data['date'];
                $last_state    = $data;
                continue;
            }

            if ($item instanceof AssignUser) {
                $name = getUserName($data['users_id']);
            } else {
                // The level holding the group, its own name otherwise. The stored column is a
                // JSON blob: a malformed row decodes to null, on which in_array() would raise
                // a TypeError.
                $name = Dropdown::getDropdownName("glpi_groups", $data['groups_id']);
                foreach ($mylevels as $levelname => $groups) {
                    if (is_array($groups) && in_array($data['groups_id'], $groups)) {
                        $name = $levelname;
                        break;
                    }
                }
            }

            // Only a NULL delay is still open: a row closed with a zero delay (released in the
            // same second, or outside the opening hours) ends where it starts
            if ($data['delay'] === null) {
                $add($name, $data['date'], $now, Tool::getActiveTime($ticket, $data['date'], $now));
            } else {
                $delay = (int) $data['delay'];
                $add($name, $data['date'], Tool::getEndDate($ticket, $data['date'], $delay), $delay);
            }
        }

        // The status the ticket is in, up to now, unless it is closed
        if ($last_state !== null && $last_state['new_status'] != Ticket::CLOSED) {
            $add(
                Ticket::getStatus($last_state['new_status']),
                $last_state['date'],
                $now,
                Tool::getActiveTime($ticket, $last_state['date'], $now),
            );
        }

        return ['rows' => $rows, 'periods' => $periods];
    }

    /**
     * Timeline chart of a ticket, drawn in the browser with the ECharts library of GLPI
     * (public/lib/echarts.js, the one of the dashboards): no request leaves the instance.
     * public/js/timeline_chart.js loads the library on the pages that hold a chart only.
     *
     * @param AssignState|AssignGroup|AssignUser $item
     */
    public static function showTimelineGraph(Ticket $ticket, $item)
    {
        $chart = self::getChartData($ticket, $item);
        if ($chart['periods'] === []) {
            return;
        }

        TemplateRenderer::getInstance()->display('@timelineticket/chart.html.twig', [
            'chart'       => $chart,
            // Same URL as Html::script() would print, cache key included
            'echarts_src' => Html::getPrefixedUrl('lib/echarts.js')
                . '?v=' . FrontEnd::getVersionCacheKey(GLPI_VERSION),
        ]);
    }
}
