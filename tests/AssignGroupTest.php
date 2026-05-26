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
use GlpiPlugin\Timelineticket\AssignGroup;
use Group;
use Ticket;

class AssignGroupTest extends DbTestCase
{
    private function createTicket(): Ticket
    {
        return $this->createItem(Ticket::class, [
            'name'        => 'Test Ticket',
            'content'     => 'Content',
            'entities_id' => 0,
        ]);
    }

    private function createGroup(): Group
    {
        return $this->createItem(Group::class, [
            'name'        => 'Test Group',
            'entities_id' => 0,
        ]);
    }

    public function testAssignGroupCanBeCreatedAndRetrieved(): void
    {
        $this->login('glpi', 'glpi');

        $ticket = $this->createTicket();
        $group  = $this->createGroup();

        $assign = $this->createItem(AssignGroup::class, [
            'tickets_id' => $ticket->getID(),
            'groups_id'  => $group->getID(),
            'date'       => date('Y-m-d H:i:s'),
            'begin'      => 0,
        ]);

        $this->assertGreaterThan(0, $assign->getID());
        $this->assertSame($ticket->getID(), (int) $assign->getField('tickets_id'));
    }

    public function testAssignGroupDelayIsNullOnCreation(): void
    {
        $this->login('glpi', 'glpi');

        $ticket = $this->createTicket();
        $group  = $this->createGroup();

        $assign = $this->createItem(AssignGroup::class, [
            'tickets_id' => $ticket->getID(),
            'groups_id'  => $group->getID(),
            'date'       => date('Y-m-d H:i:s'),
            'begin'      => 0,
        ]);

        $this->assertNull($assign->getField('delay'));
    }

    public function testInsertGroupChangeNewCreatesRecord(): void
    {
        $this->login('glpi', 'glpi');

        $ticket = $this->createTicket();
        $group  = $this->createGroup();

        $assign_group = new AssignGroup();
        $date = date('Y-m-d H:i:s');
        $assign_group->insertGroupChange($ticket, $date, $group->getID(), 'new');

        $count = countElementsInTable(AssignGroup::getTable(), [
            'tickets_id' => $ticket->getID(),
            'groups_id'  => $group->getID(),
        ]);
        $this->assertSame(1, $count);
    }

    public function testInsertGroupChangeDeleteSetsDelay(): void
    {
        $this->login('glpi', 'glpi');

        $ticket = $this->createTicket();
        $group  = $this->createGroup();

        $start_date = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $end_date   = date('Y-m-d H:i:s');

        $assign_group = new AssignGroup();
        $assign_group->insertGroupChange($ticket, $start_date, $group->getID(), 'new');
        $assign_group->insertGroupChange($ticket, $end_date, $group->getID(), 'delete');

        $rows = $assign_group->find([
            'tickets_id' => $ticket->getID(),
            'groups_id'  => $group->getID(),
        ]);
        $row = current($rows);
        $this->assertNotNull($row['delay']);
        $this->assertGreaterThan(0, $row['delay']);
    }

    public function testTicketPurgeRemovesRelatedAssignGroups(): void
    {
        $this->login('glpi', 'glpi');

        $ticket = $this->createTicket();
        $group  = $this->createGroup();

        $this->createItem(AssignGroup::class, [
            'tickets_id' => $ticket->getID(),
            'groups_id'  => $group->getID(),
            'date'       => date('Y-m-d H:i:s'),
            'begin'      => 0,
        ]);

        $tickets_id = $ticket->getID();
        $ticket->delete(['id' => $tickets_id], true);

        $remaining = countElementsInTable(AssignGroup::getTable(), ['tickets_id' => $tickets_id]);
        $this->assertSame(0, $remaining);
    }
}
