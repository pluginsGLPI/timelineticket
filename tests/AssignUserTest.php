<?php

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
