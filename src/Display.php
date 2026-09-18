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
use DateTime;
use DbUtils;
use Dropdown;
use Entity;
use Glpi\Application\View\TemplateRenderer;
use Glpi\UI\ThemeManager;
use Html;
use Session;
use Sportlog\GoogleCharts\Charts\Base\Column;
use Sportlog\GoogleCharts\Charts\Base\ColumnType;
use Sportlog\GoogleCharts\Charts\Base\DataTable;
use Sportlog\GoogleCharts\Charts\Options\Common\ChartBackgroundColor;
use Sportlog\GoogleCharts\Charts\Options\Common\ChartLabelStyle;
use Sportlog\GoogleCharts\Charts\Options\TimelineChart\TimelineOptions;
use Sportlog\GoogleCharts\ChartService;
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

    /**
     * URL of the Google Charts bootstrap injected by sportlog/google-charts.
     * A local copy of that file is shipped in public/js/google-charts/.
     * Only that bootstrap is served locally: the rendering modules it fetches at draw time
     * (corechart, timeline) cannot be shipped with the plugin, the Google Charts terms of
     * service requiring the library to be loaded from Google's own servers and allowing
     * neither its redistribution nor its self-hosting. The outbound dependency and the
     * content security policy entry it needs are documented in the README, which scopes that
     * entry to https://www.gstatic.com/charts/ rather than to the whole domain, and the tab
     * shows an explicit notice, rather than an empty frame, when the modules cannot be reached.
     */
    private const GOOGLE_CHARTS_REMOTE_LOADER = 'https://www.gstatic.com/charts/loader.js';

    /** Opening of the script the charts library builds around the serialised data table. */
    private const CHART_PAYLOAD_PREFIX = 'GoogleCharts.loadCharts(';

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
     * Neutralise the script breakout vector of the serialised chart payload.
     *
     * The charts library serialises the data table with json_encode() and no flag, then
     * interpolates the result in "GoogleCharts.loadCharts(%s, true);" inside an inline
     * <script> block that this plugin prints through a |raw filter. Angle brackets therefore
     * reach the page verbatim, and a row label holding "</script>" closes the block early --
     * the labels are group names, user names and service level names, all editable from the
     * interface by a user holding the corresponding right.
     *
     * The escaping is done here, at the serialisation boundary where the output context is
     * known, rather than by stripping characters from the labels upstream: inside a JSON
     * string "<" is a valid escape that decodes back to "<", so the label keeps its
     * original text on the chart instead of being silently mangled. JSON carries no angle
     * bracket outside of its strings, so nothing else in the payload is affected.
     *
     * Only the payload script is rewritten, and only between its tags: the template script
     * shipped by the library is plain JavaScript, where angle brackets are comparison
     * operators and must stay untouched.
     *
     * The marker is searched anywhere in the body rather than immediately after the opening
     * tag, and the whole body is rewritten rather than only the tail after it. The previous
     * form reproduced the exact layout of ChartLoader's private CHART_LOAD_SCRIPT constant, so
     * an indentation, an IIFE or a DOMContentLoaded wrapper added by any 1.x release of the
     * library -- composer.json allows them all -- silently disarmed the escaping and the tag
     * was returned verbatim. Rewriting the whole body costs nothing here: the wrapper emitted
     * around the serialised data carries no angle bracket of its own, and should a future
     * release introduce one, the chart breaks visibly instead of shipping an unescaped label.
     *
     * @param string $tag One script tag as returned by ChartLoader::load()
     *
     * @return string|null The hardened tag, or null when this tag carries no payload
     **/
    private static function hardenChartPayload(string $tag): ?string
    {
        $opening_end = strpos($tag, '>');
        $closing     = strrpos($tag, '</script>');
        if ($opening_end === false || $closing === false || $closing <= $opening_end) {
            return null;
        }

        $start   = $opening_end + 1;
        $payload = substr($tag, $start, $closing - $start);
        if (!str_contains($payload, self::CHART_PAYLOAD_PREFIX)) {
            return null;
        }

        return substr($tag, 0, $start)
               . str_replace(['<', '>'], ['\u003C', '\u003E'], $payload)
               . substr($tag, $closing);
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
     * Harden the payload script of a rendered chart markup.
     *
     * ChartService::render() returns the bootstrap tags and the chart container concatenated
     * into a single string, whereas hardenChartPayload() works on one tag at a time -- and has
     * to: the template script the library ships is plain JavaScript, whose angle brackets are
     * comparison operators, so rewriting the whole string at once would break it. Split the
     * markup back into script tags and hand each one over; every tag but the payload comes
     * back untouched.
     *
     * Fail closed: the markup is only handed back when exactly one payload script was found
     * and hardened. Finding none means the library no longer emits what this class knows how
     * to escape, and returning the markup unchanged in that case would publish raw labels
     * inside an inline script -- the very thing the escaping exists to prevent. The caller
     * renders the fallback notice instead, so the failure is visible rather than silent.
     *
     * @param string $markup Markup as returned by ChartService::render()
     *
     * @return string|null The hardened markup, or null when no single payload could be found
     **/
    private static function hardenChartMarkup(string $markup): ?string
    {
        $payloads = 0;
        $hardened = preg_replace_callback(
            '#<script\b[^>]*>.*?</script>#s',
            static function (array $matches) use (&$payloads): string {
                $payload = self::hardenChartPayload($matches[0]);
                if ($payload === null) {
                    return $matches[0];
                }
                $payloads++;

                return $payload;
            },
            $markup,
        );

        if ($hardened === null || $payloads !== 1) {
            trigger_error(
                sprintf(
                    'timelineticket: expected exactly one Google Charts payload script, found %d.'
                    . ' The chart was not rendered; check the sportlog/google-charts version.',
                    $payloads,
                ),
                E_USER_WARNING,
            );

            return null;
        }

        return $hardened;
    }

    public static function showTimelineGraph(Ticket $ticket, $item)
    {
        global $DB;

        // The chart is what pulls https://www.gstatic.com/charts/ into an authenticated page:
        // the bootstrap is served locally, but it resolves its rendering modules from Google at
        // draw time. Leave that egress to the operator rather than making it a side effect of
        // installing the plugin -- see Config::useGoogleCharts(), which is off by default. Both
        // callers buffer this output and hand it to their template as the "chart" variable, so
        // emitting nothing leaves the detail table -- the whole content of the tab -- in place.
        if (!Config::useGoogleCharts()) {
            return;
        }

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

                    // The loop used to assign $name on every iteration, with no break and no
                    // memory of the match: the value finally kept was always the one computed
                    // for the last configured level, so a group belonging to the first level
                    // was labelled with its own name instead. Start from the fallback and stop
                    // on the first level that actually holds the group. The stored column is a
                    // JSON blob, so a malformed row decodes to null, on which in_array() would
                    // raise a TypeError.
                    $name = Dropdown::getDropdownName("glpi_groups", $v['groups_id']);
                    foreach ($mylevels as $levelname => $groups) {
                        if (is_array($groups) && in_array($v['groups_id'], $groups)) {
                            $name = $levelname;
                            break;
                        }
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
            // Google Charts has no dark theme, and a Timeline chart exposes nothing for
            // its time axis or its grid lines -- hAxis is not supported there. Only the
            // canvas and the row labels can be reached from here, which left the axis
            // labels black whatever we did. The stylesheet inverts the whole drawing
            // under a dark palette instead; everything below is therefore still authored
            // for a light canvas, and comes out light-on-dark after inversion.
            // The canvas itself is dropped so the card shows through rather than being
            // inverted into a flat black slab of its own.
            if (ThemeManager::getInstance()->getCurrentTheme()->isDarkTheme()) {
                $chart->options->backgroundColor = new ChartBackgroundColor(fill: 'transparent');
            }
            $chart->options->timeline = new TimelineOptions(
                rowLabelStyle: new ChartLabelStyle(
                    color: '#333',
                    fontName: 'inter, -apple-system, blinkmacsystemfont, san francisco, segoe ui, roboto, helvetica neue, sans-serif',
                    fontSize: '12px',
                ),
            );

            // The library emits a script tag of its own, pointing at the Google Charts
            // bootstrap hosted on gstatic.com. The very same loader is shipped with the
            // plugin, so serve the local copy instead: the bootstrap that runs in an
            // authenticated GLPI page is then versioned and auditable. The loader still
            // resolves the chart modules from gstatic.com at draw time, which is
            // documented in the README.
            // render() emits that bootstrap itself on its first call, and ChartService::load()
            // never sets the flag render() tests -- only render() does. Calling load() first
            // therefore left the flag down and render() emitted the whole bootstrap a second
            // time, that copy being neither hardened nor pointed at the local loader. Render
            // once, and harden what render() returns.
            $markup = str_replace(
                self::GOOGLE_CHARTS_REMOTE_LOADER,
                PLUGIN_TIMELINETICKET_WEBDIR . '/js/google-charts/loader.js',
                $chartService->render('ticket' . get_class($item)),
            );

            // Draw all charts. A null here means the escaping could not be applied, so the
            // template gets no markup at all and shows its fallback notice: better a missing
            // chart than one carrying unescaped labels into an inline script.
            TemplateRenderer::getInstance()->display('@timelineticket/chart.html.twig', [
                'chart' => self::hardenChartMarkup($markup) ?? '',
            ]);
        }
    }
}
