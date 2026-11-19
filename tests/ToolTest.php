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
use Ticket;

class ToolTest extends DbTestCase
{
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
}
