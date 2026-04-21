<?php

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
