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

use Glpi\Tests\DbTestCase;
use GlpiPlugin\Timelineticket\AssignGroup;
use GlpiPlugin\Timelineticket\AssignState;
use GlpiPlugin\Timelineticket\AssignUser;
use GlpiPlugin\Timelineticket\Config;
use GlpiPlugin\Timelineticket\Display;
use Group;
use Html;
use ReflectionProperty;
use Ticket;

/**
 * Data of the ECharts timeline (Display::getChartData()) and its markup.
 */
class DisplayChartTest extends DbTestCase
{
    private const NOW = '2026-11-16 12:00:00';

    public function tearDown(): void
    {
        // Config::useCalendar() keeps the option for the request: the rollback of the test
        // transaction does not reach it
        (new ReflectionProperty(Config::class, 'use_calendar'))->setValue(null, null);

        parent::tearDown();
    }

    /**
     * Ticket opened at 08:00, its history rows left to the test, "now" at noon (after the
     * login, which resets it)
     */
    private function createTicket(): Ticket
    {
        global $DB;

        $ticket = $this->createItem(Ticket::class, [
            'name'        => 'Chart ticket',
            'content'     => 'Chart ticket',
            'entities_id' => 0,
            'date'        => '2026-11-16 08:00:00',
        ], ['date']);
        $DB->update(Ticket::getTable(), ['date' => '2026-11-16 08:00:00'], ['id' => $ticket->getID()]);
        $ticket->getFromDB($ticket->getID());
        foreach ([AssignState::getTable(), AssignGroup::getTable(), AssignUser::getTable()] as $table) {
            $DB->delete($table, ['tickets_id' => $ticket->getID()]);
        }
        $_SESSION['glpi_currenttime'] = self::NOW;

        return $ticket;
    }

    public function testStatusesFollowEachOtherUpToNow(): void
    {
        global $DB;

        $this->login('glpi', 'glpi');
        $ticket = $this->createTicket();
        foreach (
            [
                ['2026-11-16 08:00:00', 0, Ticket::INCOMING, 0],
                ['2026-11-16 10:00:00', Ticket::INCOMING, Ticket::ASSIGNED, 2 * HOUR_TIMESTAMP],
                ['2026-11-16 11:30:00', Ticket::ASSIGNED, Ticket::WAITING, 90 * MINUTE_TIMESTAMP],
            ] as [$date, $old, $new, $delay]
        ) {
            $DB->insert(AssignState::getTable(), [
                'tickets_id' => $ticket->getID(),
                'date'       => $date,
                'old_status' => $old,
                'new_status' => $new,
                'delay'      => $delay,
            ]);
        }

        $chart = Display::getChartData($ticket, new AssignState());

        $this->assertSame(
            [__('New ticket'), Ticket::getStatus(Ticket::INCOMING), Ticket::getStatus(Ticket::ASSIGNED), Ticket::getStatus(Ticket::WAITING)],
            $chart['rows'],
        );
        // Each status starts where the previous one ended; the current one runs up to now
        $this->assertSame(
            [
                [0, '2026-11-16 08:00:00', '2026-11-16 08:00:00'],
                [1, '2026-11-16 08:00:00', '2026-11-16 10:00:00'],
                [2, '2026-11-16 10:00:00', '2026-11-16 11:30:00'],
                [3, '2026-11-16 11:30:00', self::NOW],
            ],
            array_map(static fn(array $period): array => [$period['row'], $period['begin'], $period['end']], $chart['periods']),
        );
        $this->assertSame(Html::timestampToString(90 * MINUTE_TIMESTAMP, true), $chart['periods'][2]['duration']);
    }

    public function testGroupPeriodsShareTheirRow(): void
    {
        global $DB;

        $this->login('glpi', 'glpi');
        $ticket = $this->createTicket();
        $group  = $this->createItem(Group::class, ['name' => 'Support <b>N1</b>', 'entities_id' => 0]);
        foreach (
            [
                ['2026-11-16 08:00:00', 0],                   // released in the same second
                ['2026-11-16 09:00:00', HOUR_TIMESTAMP],
                ['2026-11-16 11:00:00', null],                // still assigned
            ] as [$date, $delay]
        ) {
            $DB->insert(AssignGroup::getTable(), [
                'tickets_id' => $ticket->getID(),
                'date'       => $date,
                'groups_id'  => $group->getID(),
                'begin'      => 0,
                'delay'      => $delay,
            ]);
        }

        $chart = Display::getChartData($ticket, new AssignGroup());

        $this->assertSame(['Support <b>N1</b>'], $chart['rows']);
        $this->assertSame(
            [
                // A zero delay closes the period where it starts, it does not run up to now
                [0, '2026-11-16 08:00:00', '2026-11-16 08:00:00'],
                [0, '2026-11-16 09:00:00', '2026-11-16 10:00:00'],
                [0, '2026-11-16 11:00:00', self::NOW],
            ],
            array_map(static fn(array $period): array => [$period['row'], $period['begin'], $period['end']], $chart['periods']),
        );
    }

    public function testChartMarkupCarriesItsDataWithoutScript(): void
    {
        global $DB;

        $this->login('glpi', 'glpi');
        $ticket = $this->createTicket();
        $group  = $this->createItem(Group::class, ['name' => 'Support <b>N1</b>', 'entities_id' => 0]);
        $DB->insert(AssignGroup::getTable(), [
            'tickets_id' => $ticket->getID(),
            'date'       => '2026-11-16 09:00:00',
            'groups_id'  => $group->getID(),
            'begin'      => 0,
            'delay'      => HOUR_TIMESTAMP,
        ]);

        ob_start();
        Display::showTimelineGraph($ticket, new AssignGroup());
        $markup = (string) ob_get_clean();

        $this->assertStringContainsString('data-tt-chart="', $markup);
        $this->assertMatchesRegularExpression('#data-tt-echarts-src="[^"]*/lib/echarts\.js\?v=#', $markup);
        // The data travel escaped in an attribute: no script, no label markup
        $this->assertStringNotContainsString('<script', $markup);
        $this->assertStringNotContainsString('<b>', $markup);
    }

    public function testNoChartWithoutHistory(): void
    {
        $this->login('glpi', 'glpi');
        $ticket = $this->createTicket();

        ob_start();
        Display::showTimelineGraph($ticket, new AssignUser());
        $this->assertSame('', trim((string) ob_get_clean()));
    }
}
