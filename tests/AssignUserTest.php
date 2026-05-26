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

namespace GlpiPlugin\Timelineticket\Tests;

use Glpi\Tests\DbTestCase;
use GlpiPlugin\Timelineticket\AssignUser;
use Ticket;
use User;

class AssignUserTest extends DbTestCase
{
    private function createTicket(): Ticket
    {
        return $this->createItem(Ticket::class, [
            'name'        => 'Test Ticket',
            'content'     => 'Content',
            'entities_id' => 0,
        ]);
    }

    public function testAssignUserCanBeCreatedAndRetrieved(): void
    {
        $this->login('glpi', 'glpi');

        $ticket = $this->createTicket();

        $assign = $this->createItem(AssignUser::class, [
            'tickets_id' => $ticket->getID(),
            'users_id'   => 2,
            'date'       => date('Y-m-d H:i:s'),
            'begin'      => 0,
        ]);

        $this->assertGreaterThan(0, $assign->getID());
        $this->assertSame($ticket->getID(), (int) $assign->getField('tickets_id'));
    }

    public function testAssignUserDelayIsNullOnCreation(): void
    {
        $this->login('glpi', 'glpi');

        $ticket = $this->createTicket();

        $assign = $this->createItem(AssignUser::class, [
            'tickets_id' => $ticket->getID(),
            'users_id'   => 2,
            'date'       => date('Y-m-d H:i:s'),
            'begin'      => 0,
        ]);

        $this->assertNull($assign->getField('delay'));
    }

    public function testInsertUserChangeNewCreatesRecord(): void
    {
        $this->login('glpi', 'glpi');

        $ticket = $this->createTicket();

        $assign_user = new AssignUser();
        $date = date('Y-m-d H:i:s');
        $assign_user->insertUserChange($ticket, $date, 2, 'new');

        $count = countElementsInTable(AssignUser::getTable(), [
            'tickets_id' => $ticket->getID(),
            'users_id'   => 2,
        ]);
        $this->assertSame(1, $count);
    }

    public function testInsertUserChangeDeleteSetsDelay(): void
    {
        $this->login('glpi', 'glpi');

        $ticket = $this->createTicket();

        $start_date = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $end_date   = date('Y-m-d H:i:s');

        $assign_user = new AssignUser();
        $assign_user->insertUserChange($ticket, $start_date, 2, 'new');
        $assign_user->insertUserChange($ticket, $end_date, 2, 'delete');

        $rows = $assign_user->find([
            'tickets_id' => $ticket->getID(),
            'users_id'   => 2,
        ]);
        $row = current($rows);
        $this->assertNotNull($row['delay']);
        $this->assertGreaterThan(0, $row['delay']);
    }

    public function testTicketPurgeRemovesRelatedAssignUsers(): void
    {
        $this->login('glpi', 'glpi');

        $ticket = $this->createTicket();

        $this->createItem(AssignUser::class, [
            'tickets_id' => $ticket->getID(),
            'users_id'   => 2,
            'date'       => date('Y-m-d H:i:s'),
            'begin'      => 0,
        ]);

        $tickets_id = $ticket->getID();
        $ticket->delete(['id' => $tickets_id], true);

        $remaining = countElementsInTable(AssignUser::getTable(), ['tickets_id' => $tickets_id]);
        $this->assertSame(0, $remaining);
    }
}
