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

namespace GlpiPlugin\Timelineticket\Tests;

use Calendar;
use CalendarSegment;
use Entity;
use Glpi\Tests\DbTestCase;
use GlpiPlugin\Timelineticket\Config;
use GlpiPlugin\Timelineticket\Tool;
use ReflectionProperty;
use SLA;
use SLM;
use Ticket;

class ToolTest extends DbTestCase
{
    public function tearDown(): void
    {
        // Config::useCalendar() keeps the option for the request: the rollback of the test
        // transaction does not reach it
        (new ReflectionProperty(Config::class, 'use_calendar'))->setValue(null, null);

        parent::tearDown();
    }

    /**
     * Ticket of the root entity, whose calendar only opens on Monday from 08:00 to 12:00.
     */
    private function createTicketWithMorningCalendar(): Ticket
    {
        $calendar = $this->createItem(Calendar::class, [
            'name'         => 'Monday morning',
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);
        $this->createItem(CalendarSegment::class, [
            'calendars_id' => $calendar->getID(),
            'entities_id'  => 0,
            'is_recursive' => 1,
            'day'          => 1,
            'begin'        => '08:00:00',
            'end'          => '12:00:00',
        ]);
        $this->updateItem(Entity::class, 0, ['calendars_id' => $calendar->getID()]);

        return $this->createItem(Ticket::class, [
            'name'        => 'Calendar ticket',
            'content'     => 'Content',
            'entities_id' => 0,
        ]);
    }

    public function testActiveTimeIsWallClockWhenOptionIsOff(): void
    {
        $this->login('glpi', 'glpi');
        $ticket = $this->createTicketWithMorningCalendar();

        // 2026-11-16 is a Monday: 06:00 -> 10:00 is four hours of wall-clock time.
        $this->assertSame(4 * HOUR_TIMESTAMP, Tool::getActiveTime($ticket, '2026-11-16 06:00:00', '2026-11-16 10:00:00'));
    }

    public function testActiveTimeFollowsCalendarWhenOptionIsOn(): void
    {
        $this->login('glpi', 'glpi');
        $ticket = $this->createTicketWithMorningCalendar();
        $this->updateItem(Config::class, 1, ['use_calendar' => 1]);

        // Only 08:00 -> 10:00 is inside the opening hours.
        $this->assertSame(2 * HOUR_TIMESTAMP, Tool::getActiveTime($ticket, '2026-11-16 06:00:00', '2026-11-16 10:00:00'));
        // Assigned and released outside the opening hours: nothing is counted (issue #47).
        $this->assertSame(0, Tool::getActiveTime($ticket, '2026-11-16 18:00:00', '2026-11-16 20:00:00'));
    }

    public function testPeriodTimeIsCountedAsTheRecordedDelays(): void
    {
        $this->login('glpi', 'glpi');
        $ticket = $this->createTicketWithMorningCalendar();

        // A resolution SLA with its own calendar, the morning one
        $calendars_id = $ticket->getCalendar();
        $slm = $this->createItem(SLM::class, [
            'name'         => 'SLM',
            'entities_id'  => 0,
            'calendars_id' => $calendars_id,
        ]);
        $sla = $this->createItem(SLA::class, [
            'name'                => 'Resolution',
            'entities_id'         => 0,
            'slms_id'             => $slm->getID(),
            'type'                => SLM::TTR,
            'number_time'         => 4,
            'definition_time'     => 'hour',
            'calendars_id'        => $calendars_id,
            'use_ticket_calendar' => 0,
        ], ['calendars_id', 'use_ticket_calendar']);
        $ticket->fields['slas_id_ttr'] = $sla->getID();

        // Option off: wall-clock time, as the recorded delays, whatever the SLA
        $this->assertSame(4 * HOUR_TIMESTAMP, Tool::getPeriodTime($ticket, '2026-11-16 06:00:00', '2026-11-16 10:00:00'));

        // Option on: the opening hours of the calendar of the SLA
        $this->updateItem(Config::class, 1, ['use_calendar' => 1]);
        $this->assertSame(2 * HOUR_TIMESTAMP, Tool::getPeriodTime($ticket, '2026-11-16 06:00:00', '2026-11-16 10:00:00'));
    }

    public function testEndDateIsTheReverseOfActiveTime(): void
    {
        $this->login('glpi', 'glpi');
        $ticket = $this->createTicketWithMorningCalendar();

        $this->assertSame('2026-11-16 08:00:00', Tool::getEndDate($ticket, '2026-11-16 06:00:00', 2 * HOUR_TIMESTAMP));

        $this->updateItem(Config::class, 1, ['use_calendar' => 1]);
        // Two working hours from 06:00: the calendar opens at 08:00, so they end at 10:00
        $this->assertSame('2026-11-16 10:00:00', Tool::getEndDate($ticket, '2026-11-16 06:00:00', 2 * HOUR_TIMESTAMP));
        // No working time (released outside the opening hours): the period ends where it starts
        $this->assertSame('2026-11-16 18:00:00', Tool::getEndDate($ticket, '2026-11-16 18:00:00', 0));
    }
}
