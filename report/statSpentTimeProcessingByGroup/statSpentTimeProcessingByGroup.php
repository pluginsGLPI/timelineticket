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

//Options for GLPI 0.71 and newer : need slave db to access the report
use Glpi\Exception\Http\NotFoundHttpException;
use GlpiPlugin\Reports\AutoReport;
use GlpiPlugin\Reports\DateIntervalCriteria;
use GlpiPlugin\Reports\RequestTypeCriteria;
use GlpiPlugin\Reports\TicketCategoryCriteria;
use GlpiPlugin\Reports\TicketTypeCriteria;
use GlpiPlugin\Timelineticket\AssignGroup;
use GlpiPlugin\Timelineticket\AssignState;
use GlpiPlugin\Timelineticket\Display;
use GlpiPlugin\Timelineticket\Tool;

// Authorization: this report is a direct entry point reachable by forging its URL, which bypasses
// the reports-plugin menu gate. Require the plugin's ticket read right before running any query or
// emitting output, consistent with the display gate enforced across the rest of the plugin.
Session::checkRight('plugin_timelineticket_ticket', READ);

// Everything below is rendered by the classes of the reports plugin, and the menu that normally
// reaches this file belongs to it. Called by a forged URL while that plugin is inactive, the
// script died on a fatal "class not found" and printed a stack trace instead of an answer.
if (!Plugin::isPluginActive('reports')) {
    throw new NotFoundHttpException();
}

// Safe self URL for reflected form actions / pager links. Never echo $_SERVER['PHP_SELF'] raw
// (reflected-XSS vector); under the GLPI 11 router it also resolves to the front controller, so
// derive the real request path from REQUEST_URI, drop the query string, and HTML-escape it.
$self_url = htmlspecialchars(strtok($_SERVER['REQUEST_URI'] ?? '', '?'), ENT_QUOTES);

$USEDBREPLICATE        = 1;
$DBCONNECTION_REQUIRED = 1;


// Instantiate Report with Name
$report = new AutoReport(__("statSpentTimeProcessingByGroup_report_title", "timelineticket"));
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


$date = new DateIntervalCriteria($report, '`glpi_tickets`.`closedate`', __('Closing date'));
$date->setStartDate($dateMonthbegin);
$date->setEndDate($dateMonthend);

$type        = new TicketTypeCriteria($report, 'type', __('Type'));
$category    = new TicketCategoryCriteria($report, 'itilcategories_id', __('Category'));
$requesttype = new RequestTypeCriteria($report, 'requesttypes_id', __('Request source'));

//Display criterias form is needed
$report->displayCriteriasForm();

$columns = ['id'                => ['sorton' => 'id'],
    'date'              => ['sorton' => 'date'],
    'closedate'         => ['sorton' => 'closedate'],
    'priority'          => ['sorton' => 'priority'],
    'type'              => ['sorton' => 'type'],
    'requesttypes_id'   => ['sorton' => 'requesttypes_id'],
    'itilcategories_id' => ['sorton' => 'itilcategories_id'],
    'slas_id_ttr'       => ['sorton' => 'slas_id_ttr'],
];

$output_type = Search::HTML_OUTPUT;

if (isset($_POST['list_limit'])) {
    // Clamp before writing: this is a GLPI-wide session preference, and the value went in
    // unbounded. A zero or negative one made the pagination test below false, so the render
    // loop walked every closed ticket of the perimeter, each row costing a Ticket::can(), two
    // getAllDataFromTable() and a getUserGroups() per task. The bad value also stuck in the
    // session and degraded every core list for that user afterwards.
    $_SESSION['glpilist_limit'] = min(max((int) $_POST['list_limit'], 5), 1000);
    unset($_POST['list_limit']);
}
if (!isset($_REQUEST['sort'])) {
    $_REQUEST['sort']  = "closedate";
    $_REQUEST['order'] = "ASC";
}

$limit = (int) $_SESSION['glpilist_limit'];

if (isset($_POST["display_type"])) {
    // Tool::showHeader()/showItem() hand this value to
    // SearchEngine::getOutputForLegacyKey(int $output_type), which throws on an unknown
    // key and raises a TypeError on a non numeric one. Confront it with the list of
    // supported modes and fall back to HTML, keeping the negative sign that means
    // "every page".
    // The two PDF formats are no longer accepted. The branch answering them included
    // lib/ezpdf/class.ezpdf.php, a library removed from GLPI 11: any reader of the plugin
    // could pick "PDF" in the output selector and get a fatal error on a half sent page,
    // repeatedly and at no cost. Rendering through Glpi\Search\Output\Pdf would mean
    // rewriting the whole report around displayData(); until then the format is refused
    // and the request falls back to HTML rather than failing.
    // The "names list" format is refused too. Its output is a single column of item names meant
    // to be pasted back into a search field, which says nothing of a report whose point is the
    // durations spread over a dozen columns, and the legacy helpers answered it with a blank
    // page anyway.
    // HTML is still rendered by the legacy Search helpers. CSV, ODS and XLSX go through the
    // Tool::show*() wrappers, which buffer the cells and hand them to the export classes of the
    // core: those helpers only know how to write HTML and used to return an empty string for
    // every other output, so the three export formats answered an empty file.
    $allowed_output_types = [
        Search::HTML_OUTPUT,
        Search::CSV_OUTPUT,
        Search::ODS_OUTPUT,
        Search::XLSX_OUTPUT,
    ];
    $posted_output_type = is_numeric($_POST["display_type"]) ? (int) $_POST["display_type"] : Search::HTML_OUTPUT;
    $output_type        = in_array(abs($posted_output_type), $allowed_output_types, true)
        ? $posted_output_type
        : Search::HTML_OUTPUT;
    if ($output_type < 0) {
        $output_type = -$output_type;
        $limit       = 0;
    }
}

global $DB, $HEADER_LOADED, $CFG_GLPI;
//Report title
$title = $report->getFullTitle();
$dbu   = new DbUtils();

// SQL statement
// Tickets moved to the trash are excluded, as src/Dashboard.php already does: they used to
// keep appearing in the three reports with their requesters, and skewed the durations.
// The statement used to be assembled by concatenating strings and run through doQuery(): every
// fragment -- the entity restriction, the criteria of the reports plugin, the visibility
// perimeter, the sort -- carried its own quoting, and a single one getting it wrong was an
// injection. The query builder quotes the values itself and the fragments reach it as criteria.
$where = [
    'glpi_tickets.status'     => Ticket::CLOSED,
    'glpi_tickets.is_deleted' => 0,
];
Tool::addWhereCriteria($where, $dbu->getEntitiesRestrictCriteria('glpi_tickets', '', '', false));
Tool::addWhereCriteria($where, $date->getNewSqlCriteriasRestriction());
Tool::addWhereCriteria($where, $category->getNewSqlCriteriasRestriction());
if (isset($_POST['requesttypes_id']) && $_POST['requesttypes_id'] > 0) {
    Tool::addWhereCriteria($where, $requesttype->getNewSqlCriteriasRestriction());
}
// Replay the core's ticket visibility perimeter inside the query, so the row count that feeds
// Html::printPager() below describes the same set as the rows actually rendered. The per row
// can($id, READ) further down stays in place as defence in depth.
Tool::addWhereCriteria($where, Tool::getTicketVisibilityCriteria());

$criteria = [
    'SELECT' => 'glpi_tickets.*',
    'FROM'   => 'glpi_tickets',
    'WHERE'  => $where,
];
$order_criteria = getOrderByCriteria('closedate', $columns);
if ($order_criteria !== []) {
    $criteria['ORDER'] = $order_criteria;
}

$iterator = $DB->request($criteria);
$nbtot    = count($iterator);
if ($limit) {
    $start = (int) ($_GET["start"] ?? 0);
    if ($start >= $nbtot) {
        $start = 0;
    }
    if ($start > 0 || $start + $limit < $nbtot) {
        $iterator = $DB->request($criteria + ['START' => $start, 'LIMIT' => $limit]);
    }
} else {
    $start = 0;
}

if ($nbtot == 0) {
    if (!$HEADER_LOADED) {
        Html::header($title, $_SERVER['PHP_SELF'], "utils", "report");
        Report::title();
    }
    echo "<div class='center red b'>" . __s('No results found') . "</div>";
    Html::footer();
} elseif ($output_type == Search::HTML_OUTPUT) {
    if (!$HEADER_LOADED) {
        Html::header($title, $_SERVER['PHP_SELF'], "utils", "report");
        Report::title();
    }

    echo "<div class='center'>";

    echo "<table class='tab_cadre_fixe'>";
    echo "<tr><th>" . htmlescape($title) . "</th></tr>\n";

    echo "<tr class='tab_bg_2 center'><td class='center'>";
    echo "<form method='POST' action='" . $self_url . "?start=$start'>\n";

    $param = "";
    foreach ($_POST as $key => $val) {
        // The criteria form above is closed by Html::closeForm(), which emits a hidden
        // _glpi_csrf_token: the token was therefore part of $_POST and ended up both in
        // the regenerated hidden fields and in the $param string that printPager()
        // publishes in every pagination href. A session token thus reached the browser
        // history, the proxy logs and the Referer header. Internal _glpi_* fields have
        // no business in a report URL, and every generated form gets a fresh token.
        if (str_starts_with((string) $key, '_glpi_')) {
            continue;
        }
        // urlencode() raises a TypeError on an array, so a criterion nested two levels deep
        // -- groups[0][0]=1, which nothing prevents from being posted -- interrupted the
        // rendering on a fatal error in the middle of an already sent page. The sort link
        // builder further down already carries this guard; it simply had never been
        // reported here. The key is encoded too, so a bracket or a separator sent as a
        // field name cannot forge an extra parameter in the pagination URL.
        if (is_array($val)) {
            foreach ($val as $k => $v) {
                if (!is_scalar($v)) {
                    continue;
                }
                $name =  $key . "[$k]";
                echo Html::hidden($name, ['value' => $v]);
                if (!empty($param)) {
                    $param .= "&";
                }
                $param .= urlencode((string) $key) . "[" . urlencode((string) $k) . "]="
                          . urlencode((string) $v);
            }
        } else {
            if (!is_scalar($val)) {
                continue;
            }
            echo Html::hidden($key, ['value' => $val]);
            if (!empty($param)) {
                $param .= "&";
            }
            $param .= urlencode((string) $key) . "=" . urlencode((string) $val);
        }
    }
    Dropdown::showOutputFormat();
    Html::closeForm();
    echo "</td></tr>";
    echo "</table></div>";

    Html::printPager($start, $nbtot, $self_url, $param);
}

if ($nbtot > 0) {

    $mylevels = [];
    $restrict = $dbu->getEntitiesRestrictCriteria("glpi_plugin_timelineticket_grouplevels", '', '', true) +
                ["ORDER" => "rank"];
    $levels = $dbu->getAllDataFromTable("glpi_plugin_timelineticket_grouplevels", $restrict);
    if (!empty($levels)) {
        foreach ($levels as $level) {
            $mylevels[$level["name"]] = json_decode($level["groups"], true);
        }
    }

    $nbCols = count($DB->listFields('glpi_tickets'));
    // Replay the core's per ticket visibility. The query only carries the entity restriction, so
    // a profile holding READMY, READGROUP or READASSIGN on tickets -- and not READALL -- used to
    // receive every closed ticket of its entities: requesters, category, durations and a link to
    // the ticket form. can($id, READ) is what the core uses at every other rendering point: it
    // confronts the global right, the per item rules and the entity, where canViewItem() alone
    // would skip the first two. Filtering here rather than inside the row loop below keeps the
    // header counter consistent with the rows actually rendered. The query itself now carries the
    // same perimeter (Tool::getTicketVisibilityCriteria()), so the pager total printed above
    // describes the same set; this loop stays in place as defence in depth.
    $visible_rows    = [];
    $visible_tickets = [];
    foreach ($iterator as $data) {
        $ticket = new Ticket();
        if (!$ticket->can($data['id'], READ)) {
            continue;
        }
        $visible_rows[]               = $data;
        $visible_tickets[$data['id']] = $ticket;
    }

    $nbrows = count($visible_rows);
    $num    = 1;

    echo Tool::showHeader($output_type, $nbrows, $nbCols, false);

    echo Tool::showNewLine($output_type);
    showTitle($output_type, $num, __('id'), 'id', true);
    showTitle($output_type, $num, __('Opening date'), 'date', true);
    showTitle($output_type, $num, __('Closing date'), 'closedate', true);
    showTitle($output_type, $num, __('Priority'), 'priority', true);
    showTitle($output_type, $num, __('Type'), 'type', true);
    showTitle($output_type, $num, __('Request source'), 'requesttypes_id', true);
    showTitle($output_type, $num, __('Category'), 'itilcategories_id', true);
    showTitle($output_type, $num, __('SLA'), 'slas_id_ttr', true);


    if (!empty($mylevels)) {
        foreach ($mylevels as $key => $val) {
            // Service level names come from the database and land in a <th> through showHeaderItem(),
            // which does not escape its value.
            showTitle($output_type, $num, __('Duration by "in progress"', 'timelineticket') . "&nbsp;" . Tool::escapeForOutput($output_type, $key), '', false);
        }
    }
    echo Tool::showEndLine($output_type);

    $row_num = 1;
    foreach ($visible_rows as $data) {

        $ticket = $visible_tickets[$data['id']];

        $timelevels = [];
        if (!empty($mylevels)) {
            foreach ($mylevels as $key => $val) {
                if (is_array($val)) {
                    foreach ($val as $group => $groups_id) {

                        $a_details = getDetails($ticket, $groups_id);
                        $a_status = [];
                        foreach ($a_details as $time) {
                            if ($time['Status'] == Ticket::ASSIGNED || $time['Status'] == Ticket::PLANNED) {
                                if (isset($timelevels[$key])) {
                                    $timelevels[$key] += ($time['End'] - $time['Start']);
                                } else {
                                    $timelevels[$key] = ($time['End'] - $time['Start']);
                                }
                            }

                        }
                    }
                }
            }
        }

        $row_num++;
        $num = 1;
        echo Tool::showNewLine($output_type);

        $link = "<a href='" . $CFG_GLPI["root_doc"] .
                  "/front/ticket.form.php?id=" . (int) $data["id"] . "'>" . (int) $data['id'] . "</a>";
        echo Tool::showItem($output_type, $link, $num, $row_num);
        echo Tool::showItem($output_type, Html::convDateTime($data['date']), $num, $row_num);
        echo Tool::showItem($output_type, Html::convDateTime($data['closedate']), $num, $row_num);
        echo Tool::showItem($output_type, Ticket::getPriorityName($data['priority']), $num, $row_num);
        echo Tool::showItem($output_type, Ticket::getTicketTypeName($data['type']), $num, $row_num);
        echo Tool::showItem($output_type, Tool::escapeForOutput($output_type, Dropdown::getDropdownName('glpi_requesttypes', $data["requesttypes_id"])), $num, $row_num);
        echo Tool::showItem($output_type, Tool::escapeForOutput($output_type, Dropdown::getDropdownName("glpi_itilcategories", $data["itilcategories_id"])), $num, $row_num);
        echo Tool::showItem($output_type, Tool::escapeForOutput($output_type, Dropdown::getDropdownName('glpi_slas', $data["slas_id_ttr"])), $num, $row_num);

        $time = 0;
        if (!empty($mylevels)) {
            foreach ($mylevels as $key => $val) {
                if (array_key_exists($key, $timelevels)) {
                    $time = $timelevels[$key];
                } else {
                    $time = 0;
                }

                if ($output_type == Search::HTML_OUTPUT
                    || $output_type == Search::PDF_OUTPUT_PORTRAIT
                    || $output_type == Search::PDF_OUTPUT_LANDSCAPE) {
                    echo Tool::showItem($output_type, Html::timestampToString($time), $num, $row_num);
                } else {
                    echo Tool::showItem($output_type, Html::formatNumber($time / 3600, false, 5), $num, $row_num);
                }
            }
        }

        echo Tool::showEndLine($output_type);
    }
    echo Tool::showFooter($output_type, $title);
}

if ($output_type == Search::HTML_OUTPUT) {
    Html::footer();
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
function showTitle($output_type, &$num, $title, $columnname, $sort = false)
{

    if ($output_type != Search::HTML_OUTPUT || $sort == false) {
        echo Tool::showHeaderItem($output_type, $title, $num);
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
    // Reflected sort links: use the escaped request path, not raw PHP_SELF (reflected-XSS vector).
    $link  = htmlspecialchars(strtok($_SERVER['REQUEST_URI'] ?? '', '?'), ENT_QUOTES);
    $first = true;
    foreach ($_REQUEST as $name => $value) {
        // urlencode() raises a TypeError on an array, and the key was concatenated raw,
        // letting URL separators through: skip anything that is not a scalar and encode
        // both sides of the pair.
        if (!is_scalar($value)) {
            continue;
        }
        if (!in_array($name, ['sort', 'order', 'PHPSESSID'])) {
            $link .= ($first ? '?' : '&amp;');
            $link .= urlencode((string) $name) . '=' . urlencode((string) $value);
            $first = false;
        }
    }
    $link .= ($first ? '?' : '&amp;') . 'sort=' . urlencode($columnname);
    $link .= '&amp;order=' . $order;
    echo Tool::showHeaderItem($output_type, $title, $num, $link, $issort, ($order == 'ASC' ? 'DESC' : 'ASC'));
}

/**
 * Build the ORDER clause, as query builder criteria.
 *
 * @param string                      $default Column sorted on when the request carries none
 * @param array<string, array<mixed>> $columns Sortable columns of the report
 *
 * @return array<int, string>
 */
function getOrderByCriteria($default, $columns)
{

    if (!isset($_REQUEST['order']) || $_REQUEST['order'] != 'DESC') {
        $_REQUEST['order'] = 'ASC';
    }
    $order = $_REQUEST['order'];
    // array_key_exists() raises a TypeError on a non scalar key: sort[]=x in the query
    // string answered 500 with a stack trace instead of falling back to the default column.
    $sort  = isset($_REQUEST['sort']) && is_scalar($_REQUEST['sort'])
        ? (string) $_REQUEST['sort']
        : $default;

    // The column name is confronted with the sortable columns of the report and the direction
    // is one of two literals, so the string handed to the builder carries nothing from the
    // request that has not been whitelisted first.
    if (array_key_exists($sort, $columns)) {
        return [$sort . ' ' . $order];
    }

    // Fall back to the report's own default column, never to no ORDER BY at all: the query
    // carries a LIMIT/OFFSET, and MySQL guarantees no stable order without an ORDER BY, so an
    // unrecognised sort parameter made rows repeat across pages while others vanished.
    if (array_key_exists($default, $columns)) {
        return [$default . ' ' . $order];
    }
    return [];
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

function getDetails(Ticket $ticket, $groups_id)
{

    $ptState = new AssignState();

    $a_ret     = AssignState::getTotaltimeEnddate($ticket);
    $totaltime = $a_ret['totaltime'];

    $ptItem = new AssignGroup();

    $a_states     = [];
    $a_dbstates   = $ptState->find(["tickets_id" => $ticket->getField('id')], ['date', 'id']);
    $end_previous = 0;
    foreach ($a_dbstates as $a_dbstate) {
        $end_previous += $a_dbstate['delay'];
        if ($a_dbstate['old_status'] == '') {
            $a_dbstate['old_status'] = 0;
        }
        if (isset($a_states[$end_previous])) {
            $end_previous++;
        }
        $a_states[$end_previous] = $a_dbstate['old_status'];
    }
    if (isset($a_dbstate['new_status'])
        && $a_dbstate['new_status'] != Ticket::CLOSED) {
        $a_states[$totaltime] = $a_dbstate['new_status'];
    }

    $a_itemsections = [];
    $a_dbitems      = $ptItem->find(["tickets_id" => $ticket->getField('id'),
        'groups_id' => $groups_id], ['date']);
    foreach ($a_dbitems as $a_dbitem) {

        if (!isset($a_itemsections)) {
            $a_itemsections[$a_dbitem['groups_id']] = [];
            $last_statedelay                        = 0;
        } else {
            foreach ($a_itemsections as $data) {
                $last_statedelay = $data['End'];
            }
        }

        $gbegin = $a_dbitem['begin'];
        if ($a_dbitem['delay'] == '') {
            $gdelay = $totaltime;
        } else {
            $gdelay = $a_dbitem['begin'] + $a_dbitem['delay'];
        }
        $mem       = 0;
        $old_delay = 0;
        foreach ($a_states as $delay => $statusname) {
            if ($mem == 1) {
                if ($gdelay > $delay) { // all time of the state
                    $a_itemsections[] = [
                        'Start'   => $gbegin,
                        'End'     => $delay,
                        "Caption" => "",
                        "Status"  => $statusname,

                    ];
                    $gbegin                                   = $delay;
                } elseif ($gdelay == $delay) { // end of status = end of group
                    $a_itemsections[] = [
                        'Start'   => $gbegin,
                        'End'     => $delay,
                        "Caption" => "",
                        "Status"  => $statusname,

                    ];
                    $mem                                      = 2;
                } else { // end of status is after end of group
                    $a_itemsections[] = [
                        'Start'   => $gbegin,
                        'End'     => $gdelay,
                        "Caption" => "",
                        "Status"  => $statusname,

                    ];
                    $mem                                      = 2;
                }
            } elseif ($mem == 0
                       && $gbegin < $delay) {
                if ($gdelay > $delay) { // all time of the state
                    $a_itemsections[] = [
                        'Start'   => $gbegin,
                        'End'     => $delay,
                        "Caption" => "",
                        "Status"  => $statusname,

                    ];
                    $gbegin                                   = $delay;
                    $mem                                      = 1;
                } elseif ($gdelay == $delay) { // end of status = end of group
                    $a_itemsections[] = [
                        'Start'   => $gbegin,
                        'End'     => $delay,
                        "Caption" => "",
                        "Status"  => $statusname,

                    ];
                    $mem                                      = 2;
                } else { // end of status is after end of group
                    $a_itemsections[] = [
                        'Start'   => $gbegin,
                        'End'     => $gdelay,
                        "Caption" => "",
                        "Status"  => $statusname,

                    ];
                    $mem                                      = 2;
                }
            }
            $old_delay = $delay;
        }
    }

    return $a_itemsections;
}
