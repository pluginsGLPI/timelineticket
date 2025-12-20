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
use CommonGLPI;
use Config;
use DateTime;
use DateTimeZone;
use Entity;
use SLA;
use Ticket;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class Tool
{

    /**
     * Return array with all data
     *
     * @param Ticket   $ticket
     * @param      $type 'user' or 'group'
     * @param int $withblank option to fill blank zones
     *
     * @return
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
        foreach ($a_dbitems as $a_dbitem) {
            if ($item instanceof AssignGroup) {
                $items_id = 'groups_id';
            } elseif ($item instanceof AssignUser) {
                $items_id = 'users_id';
            }

            if (!isset($a_itemsections[$a_dbitem[$items_id]])) {
                $a_itemsections[$a_dbitem[$items_id]] = [];
                $last_statedelay                      = 0;
            } else {
                foreach ($a_itemsections[$a_dbitem[$items_id]] as $data) {
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
                       && $gbegin < $delay) {
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
