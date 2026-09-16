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
use CommonDBTM;
use Config;
use DateTime;
use DateTimeZone;
use Entity;
use Glpi\DBAL\QuerySubQuery;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Search\SearchEngine;
use Search;
use Session;
use SLA;
use Ticket;

use function htmlescape;

class Tool
{
    /**
     * Output types the reports render by buffering the cells instead of echoing them.
     *
     * In GLPI 11 the legacy Search::show*() helpers only know how to render HTML: showItem(),
     * showNewLine(), showHeaderItem() and showFooter() return an empty string as soon as the
     * output is not an HTMLSearchOutput, and no header is emitted either. Picking CSV, ODS or
     * XLSX in the output selector of the three reports therefore answered a blank page. The
     * modern path is Glpi\Search\Output\ExportSearchOutput::displayData(), which expects the
     * data structure of the search engine rather than a stream of cells, so the wrappers below
     * keep the call sites of the reports untouched and assemble that structure as the rows go by.
     *
     * @var array<int, int>
     */
    public const EXPORT_OUTPUT_TYPES = [
        Search::CSV_OUTPUT,
        Search::ODS_OUTPUT,
        Search::XLSX_OUTPUT,
    ];

    /** @var array<int, string> Column headers buffered for the current export */
    private static array $export_cols = [];

    /** @var array<int, array<int, string>> Rows buffered for the current export */
    private static array $export_rows = [];

    /** @var array<int, string> Cells of the row being buffered */
    private static array $export_row = [];

    /**
     * Is this output type rendered through the export buffer rather than the legacy helpers?
     *
     * @param int|string $output_type One of the Search::*_OUTPUT constants
     *
     * @return bool
     */
    public static function isExportOutput($output_type): bool
    {
        return in_array((int) $output_type, self::EXPORT_OUTPUT_TYPES, true);
    }

    /**
     * Drop-in replacement for Search::showHeader() that also opens the export buffer.
     *
     * @param int|string $output_type One of the Search::*_OUTPUT constants
     * @param int        $rows
     * @param int        $cols
     * @param bool|int   $fixed
     *
     * @return string
     */
    public static function showHeader($output_type, $rows, $cols, $fixed = 0): string
    {
        if (!self::isExportOutput($output_type)) {
            return Search::showHeader($output_type, $rows, $cols, $fixed);
        }

        self::$export_cols = [];
        self::$export_rows = [];
        self::$export_row  = [];

        return '';
    }

    /**
     * Drop-in replacement for Search::showHeaderItem().
     *
     * @param int|string $output_type One of the Search::*_OUTPUT constants
     * @param mixed      $value
     * @param int        $num
     * @param string     $linkto
     * @param bool|int   $issort
     * @param string     $order
     * @param string     $options
     *
     * @return string
     */
    public static function showHeaderItem(
        $output_type,
        $value,
        &$num,
        $linkto = "",
        $issort = 0,
        $order = "",
        $options = ""
    ): string {
        if (!self::isExportOutput($output_type)) {
            return Search::showHeaderItem($output_type, $value, $num, $linkto, $issort, $order, $options);
        }

        self::$export_cols[] = (string) $value;
        $num++;

        return '';
    }

    /**
     * Drop-in replacement for Search::showNewLine(), opening a buffered row.
     *
     * @param int|string $output_type One of the Search::*_OUTPUT constants
     * @param bool       $odd
     * @param bool       $is_deleted
     *
     * @return string
     */
    public static function showNewLine($output_type, $odd = false, $is_deleted = false): string
    {
        if (!self::isExportOutput($output_type)) {
            return Search::showNewLine($output_type, $odd, $is_deleted);
        }

        self::$export_row = [];

        return '';
    }

    /**
     * Drop-in replacement for Search::showItem().
     *
     * @param int|string $output_type One of the Search::*_OUTPUT constants
     * @param mixed      $value
     * @param int        $num
     * @param int        $row
     * @param string     $extraparam
     *
     * @return string
     */
    public static function showItem($output_type, $value, &$num, $row, $extraparam = ''): string
    {
        if (!self::isExportOutput($output_type)) {
            return Search::showItem($output_type, $value, $num, $row, $extraparam);
        }

        self::$export_row[] = (string) ($value ?? '');
        $num++;

        return '';
    }

    /**
     * Drop-in replacement for Search::showEndLine(), closing the buffered row.
     *
     * @param int|string $output_type     One of the Search::*_OUTPUT constants
     * @param bool       $is_header_line
     *
     * @return string
     */
    public static function showEndLine($output_type, bool $is_header_line = false): string
    {
        if (!self::isExportOutput($output_type)) {
            return Search::showEndLine($output_type, $is_header_line);
        }

        // The header line is closed the same way as a data line, but its cells went to
        // showHeaderItem() and the buffered row is then empty: keeping it would insert a blank
        // line at the top of the file.
        if (self::$export_row !== []) {
            self::$export_rows[] = self::$export_row;
        }
        self::$export_row = [];

        return '';
    }

    /**
     * Drop-in replacement for Search::showFooter(), which sends the file for the export types.
     *
     * @param int|string $output_type One of the Search::*_OUTPUT constants
     * @param string     $title
     * @param int|null   $count
     *
     * @return string
     */
    public static function showFooter($output_type, $title = "", $count = null): string
    {
        if (!self::isExportOutput($output_type)) {
            return Search::showFooter($output_type, $title, $count);
        }

        self::sendExport((int) $output_type);

        return '';
    }

    /**
     * Hand the buffered rows to the export writer of the core.
     *
     * displayData() expects the shape the search engine produces: one entry per column in
     * data.cols, and one "<itemtype>_<column id>" key per cell in data.rows. Feeding it instead
     * of writing the file here is what keeps the plugin on the escaping of the core -- in
     * particular SpreadsheetValueBinder, which neutralises formula injection, and
     * DataExport::normalizeValueForTextExport(), which flattens the cells the reports build as
     * HTML (the requester list joined with line breaks, the link to the ticket) into plain text.
     *
     * @param int $output_type One of the Search::*_OUTPUT constants
     *
     * @return void
     */
    private static function sendExport(int $output_type): void
    {
        $cols = [];
        foreach (self::$export_cols as $index => $name) {
            $cols[] = [
                'name'     => $name,
                'itemtype' => Ticket::class,
                'id'       => $index,
                'meta'     => false,
            ];
        }

        $rows = [];
        foreach (self::$export_rows as $row) {
            $formatted = [];
            foreach ($cols as $index => $col) {
                $formatted[$col['itemtype'] . '_' . $col['id']] = [
                    'displayname' => $row[$index] ?? '',
                ];
            }
            $rows[] = $formatted;
        }

        $count = count($rows);
        $data  = [
            'itemtype' => Ticket::class,
            'search'   => [
                'as_map'       => 0,
                'is_deleted'   => 0,
                'criteria'     => [],
                'metacriteria' => [],
            ],
            'data'     => [
                'totalcount' => $count,
                'count'      => $count,
                'begin'      => 0,
                'end'        => max(0, $count - 1),
                'cols'       => $cols,
                'rows'       => $rows,
            ],
        ];

        SearchEngine::getOutputForLegacyKey($output_type)->displayData($data);

        self::$export_cols = [];
        self::$export_rows = [];
        self::$export_row  = [];
    }

    /**
     * Escape a database value before handing it to the legacy search output helpers.
     *
     * Search::showItem() and Search::showHeaderItem() emit their value verbatim, so HTML
     * escaping is the caller's responsibility. Only the HTML output needs it: the CSV, ODS,
     * XLSX and PDF exports must keep the raw text, otherwise entities leak into the file.
     *
     * @param int|string $output_type One of the Search::*_OUTPUT constants
     * @param mixed      $value       Raw value read from the database
     *
     * @return string
     */
    public static function escapeForOutput($output_type, $value): string
    {
        if ((int) $output_type !== Search::HTML_OUTPUT) {
            return (string) $value;
        }

        return htmlescape((string) $value);
    }

    /**
     * Ticket visibility perimeter of the current session, as query builder criteria.
     *
     * The three "spent time by group" reports list glpi_tickets and count the rows to feed
     * Html::printPager(). Every row is then filtered with can($id, READ), so the rendered list
     * was right but the announced total was not: a profile holding READMY, READGROUP or
     * READASSIGN -- and not READALL -- was told how many closed tickets its entities hold, and
     * paged through mostly empty pages. Ticket::getCriteriaFromProfile() builds that perimeter,
     * with joins of its own on the tu, gt and glpi_ticketvalidations aliases: keeping it inside
     * a sub query is what lets it be combined with the criteria of the report without any of
     * those aliases colliding with the outer ones.
     *
     * getCriteriaFromProfile() returns an empty array both for READALL (everything is visible)
     * and for a profile that may see nothing at all, so the two cases are told apart by an
     * explicit READALL check.
     *
     * @return array<string, QuerySubQuery> Empty for READALL, one condition on the ticket id otherwise
     */
    public static function getTicketVisibilityCriteria(): array
    {
        if (Session::haveRight('ticket', Ticket::READALL)) {
            return [];
        }

        $visibility_criteria = Ticket::getCriteriaFromProfile();
        if (!isset($visibility_criteria['WHERE'])) {
            throw new AccessDeniedHttpException();
        }

        return [
            'glpi_tickets.id' => new QuerySubQuery([
                'SELECT'   => 'glpi_tickets.id',
                'DISTINCT' => true,
                'FROM'     => 'glpi_tickets',
            ] + $visibility_criteria),
        ];
    }

    /**
     * Append a restriction to a WHERE criteria list, dropping the empty ones.
     *
     * The criteria helpers of the reports plugin answer an empty array -- and, for a dropdown
     * left on "all", null -- when the field carries no filter. Pushed as is, an empty array is
     * rendered as "()" by DBmysqlIterator and null makes it throw, so a criteria form submitted
     * blank would answer a SQL error instead of the whole period.
     *
     * @param array<int|string, mixed> $where       Criteria list being built, modified in place
     * @param mixed                    $restriction Restriction returned by a criteria helper
     *
     * @return void
     */
    public static function addWhereCriteria(array &$where, $restriction): void
    {
        if (is_array($restriction) && count($restriction) > 0) {
            $where[] = $restriction;
        }
    }

    /**
     * Return array with all data
     *
     * @param Ticket   $ticket
     * @param CommonDBTM $item Assignment item (AssignGroup or AssignUser)
     * @param int $withblank option to fill blank zones
     *
     * @return array
     */
    public static function getDetails(Ticket $ticket, $item, $withblank = 1)
    {

        $ptState = new AssignState();

        $a_ret     = AssignState::getTotaltimeEnddate($ticket);
        $totaltime = $a_ret['totaltime'];

        $a_states       = [];

        $a_dbstates     = $ptState->find(["tickets_id" => $ticket->getID()], ["date", "id"]);
        $end_previous   = 0;
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
        $a_dbitems      = $item->find(["tickets_id" => $ticket->getID()], ["date"]);
        // $item type is invariant across the loop, so resolve the column once.
        $items_id = null;
        if ($item instanceof AssignGroup) {
            $items_id = 'groups_id';
        } elseif ($item instanceof AssignUser) {
            $items_id = 'users_id';
        }
        foreach ($a_dbitems as $a_dbitem) {
            if (!isset($a_itemsections[$a_dbitem[$items_id]])) {
                $a_itemsections[$a_dbitem[$items_id]] = [];
                $last_statedelay                      = 0;
            } else {
                foreach ($a_itemsections[$a_dbitem[$items_id]] as $data) {
                    $last_statedelay = $data['End'];
                }
            }
            $gbegin = (int) ($a_dbitem['begin'] ?? 0);
            // Use is_null() — PHP 8 changed "0 == ''" to false, breaking the old check
            if (is_null($a_dbitem['delay'])) {
                $gdelay = $totaltime;
            } else {
                $gdelay = $gbegin + (int) $a_dbitem['delay'];
            }
            $mem       = 0;

            foreach ($a_states as $delay => $statusname) {
                if ($mem == 1) {
                    if ($gdelay > $delay) { // all time of the state
                        $a_itemsections[$a_dbitem[$items_id]][] = [
                            'Start'   => $gbegin,
                            'End'     => $delay,
                            "Status"  => $statusname,
                        ];
                        $gbegin                                 = $delay;
                    } elseif ($gdelay == $delay) { // end of status = end of group
                        $a_itemsections[$a_dbitem[$items_id]][] = [
                            'Start'   => $gbegin,
                            'End'     => $delay,
                            "Status"  => $statusname,
                        ];
                        $mem                                    = 2;
                    } else { // end of status is after end of group
                        $a_itemsections[$a_dbitem[$items_id]][] = [
                            'Start'   => $gbegin,
                            'End'     => $gdelay,
                            "Status"  => $statusname,
                        ];
                        $mem                                    = 2;
                    }
                } elseif ($mem == 0
                       && $gbegin <= $delay) {
                    if ($withblank
                    && $gbegin != $last_statedelay) {
                        $a_itemsections[$a_dbitem[$items_id]][] = [
                            'Start'   => $last_statedelay,
                            'End'     => $gbegin,
                            "Status"  => "",
                        ];
                    }
                    if ($gdelay > $delay) { // all time of the state
                        $a_itemsections[$a_dbitem[$items_id]][] = [
                            'Start'   => $gbegin,
                            'End'     => $delay,
                            "Status"  => $statusname,
                        ];
                        $gbegin                                 = $delay;
                        $mem                                    = 1;
                    } elseif ($gdelay == $delay) { // end of status = end of group
                        $a_itemsections[$a_dbitem[$items_id]][] = [
                            'Start'   => $gbegin,
                            'End'     => $delay,
                            "Status"  => $statusname,
                        ];
                        $mem                                    = 2;
                    } else { // end of status is after end of group
                        $a_itemsections[$a_dbitem[$items_id]][] = [
                            'Start'   => $gbegin,
                            'End'     => $gdelay,
                            "Status"  => $statusname,
                        ];
                        $mem                                    = 2;
                    }
                }
            }

            // Fallback: begin >= every recorded status-transition point
            // (group/user assigned during the last — still-running — status period)
            if ($mem === 0 && !empty($a_states)) {
                end($a_states);
                $last_statusname = current($a_states);
                reset($a_states);
                if ($gdelay > $gbegin) {
                    $a_itemsections[$a_dbitem[$items_id]][] = [
                        'Start'  => $gbegin,
                        'End'    => $gdelay,
                        'Status' => $last_statusname,
                    ];
                }
            }
        }
        if ($withblank) {
            end($a_states);
            $verylastdelayStateDB = key($a_states);
            foreach ($a_itemsections as $items_id => $data_f) {

                $statusname = '';
                $a_end      = end($data_f);
                $last       = $a_end['End'] ?? 0;
                if ($ticket->fields['status'] != Ticket::CLOSED
                && $last == $verylastdelayStateDB) {
                    $statusname = $a_end['Status'] ?? $statusname;
                }
                if ($last < $totaltime) {
                    $a_itemsections[$items_id][] = [
                        'Start'   => $last,
                        'End'     => $totaltime,
                        "Status"  => $statusname,
                    ];
                }
            }
        }
        return $a_itemsections;
    }



    public static function getPeriodTime(CommonGLPI $ticket, $start, $end)
    {

        //        $calendar = new Calendar();
        if ($ticket->fields['slas_id_ttr'] != 0) { // Have SLT
            $sla = new SLA();
            $sla->getFromDB($ticket->fields['slas_id_ttr']);
            $totaltime = $sla->getActiveTimeBetween($start, $end);
        } else {
            //            $calendars_id = Entity::getUsedConfig(
            //                'calendars_strategy',
            //                $ticket->fields['entities_id'],
            //                'calendars_id',
            //                0
            //            );
            //            if ($calendars_id != 0) { // Ticket entity have calendar
            //                $calendar->getFromDB($calendars_id);
            //                $totaltime = $calendar->getActiveTimeBetween($start, $end);
            //            } else { // No calendar
            $totaltime = strtotime($end) - strtotime($start);
            //            }
        }
        return $totaltime;
    }
    /**
     * @param $myDate
     *
     * @return false|string
     * @throws \Exception
     */
    public static function convertDateToRightTimezoneForCalendarUse($myDate)
    {
        // We convert the both dates because $date passed in fonction are timezoned but not hours of calendars
        $currTimezone   = new DateTime(date("Y-m-d"));
        $configTimezone = Config::getConfigurationValues('core', ['timezone']);
        $baseTimezone   = 'UTC';
        $tz             = ini_get('date.timezone');
        if (!empty($configTimezone['timezone']) && !is_null($configTimezone['timezone'])) {
            $baseTimezone = $configTimezone['timezone'];
        } elseif (!empty($tz)) {
            $baseTimezone = $tz;
        }

        if ($baseTimezone > 0) {
            $globalConfTimezone = new DateTime('2008-06-21', new DateTimeZone($baseTimezone));
            $timeOffset         = date_offset_get($currTimezone) - date_offset_get($globalConfTimezone);
            return date("Y-m-d H:i:s", (strtotime($myDate) - $timeOffset));
        }
        return $myDate;
    }
}
