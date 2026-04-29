<?php

namespace GlpiPlugin\Timelineticket\Tests;

use Glpi\Tests\DbTestCase;
use GlpiPlugin\Timelineticket\AssignState;
use Ticket;

class AssignStateTest extends DbTestCase
{
    private function createTicket(): Ticket
    {
        $ticket = $this->createItem(Ticket::class, [
            'name'        => 'Test Ticket',
            'content'     => 'Content',
            'entities_id' => 0,
        ]);
        return $ticket;
    }

    public function testAssignStateCanBeCreatedAndRetrieved(): void
    {
        $this->login('glpi', 'glpi');

        $ticket = $this->createTicket();

        $state = $this->createItem(AssignState::class, [
            'tickets_id' => $ticket->getID(),
            'date'       => date('Y-m-d H:i:s'),
            'old_status' => 0,
            'new_status' => Ticket::INCOMING,
            'delay'      => 0,
        ]);

        $this->assertGreaterThan(0, $state->getID());
        $this->assertSame($ticket->getID(), (int) $state->getField('tickets_id'));
    }

    public function testAssignStateCanBeDeleted(): void
    {
        $this->login('glpi', 'glpi');

        $ticket = $this->createTicket();

        $state = $this->createItem(AssignState::class, [
            'tickets_id' => $ticket->getID(),
            'date'       => date('Y-m-d H:i:s'),
            'old_status' => 0,
            'new_status' => Ticket::INCOMING,
            'delay'      => 0,
        ]);
        $id = $state->getID();

        $state->delete(['id' => $id], true);

        $remaining = countElementsInTable(AssignState::getTable(), ['id' => $id]);
        $this->assertSame(0, $remaining);
    }

    public function testGetTotaltimeEnddateReturnsZeroForNoStates(): void
    {
        $this->login('glpi', 'glpi');

        $ticket = $this->createTicket();

        // Le hook crée automatiquement un AssignState à la création du ticket.
        // On le supprime pour tester le cas "aucun état".
        global $DB;
        $DB->delete(AssignState::getTable(), ['tickets_id' => $ticket->getID()]);

        $result = AssignState::getTotaltimeEnddate($ticket);

        $this->assertSame(0, $result['totaltime']);
    }

    public function testGetTotaltimeEnddateAccumulatesDelays(): void
    {
        $this->login('glpi', 'glpi');

        $ticket = $this->createTicket();

        $now = date('Y-m-d H:i:s');

        $this->createItem(AssignState::class, [
            'tickets_id' => $ticket->getID(),
            'date'       => $now,
            'old_status' => 0,
            'new_status' => Ticket::INCOMING,
            'delay'      => 100,
        ]);

        $this->createItem(AssignState::class, [
            'tickets_id' => $ticket->getID(),
            'date'       => $now,
            'old_status' => Ticket::INCOMING,
            'new_status' => Ticket::CLOSED,
            'delay'      => 200,
        ]);

        $ticket->getFromDB($ticket->getID());
        $result = AssignState::getTotaltimeEnddate($ticket);

        $this->assertSame(300, $result['totaltime']);
    }

    public function testTicketPurgeRemovesRelatedAssignStates(): void
    {
        $this->login('glpi', 'glpi');

        $ticket = $this->createTicket();

        $this->createItem(AssignState::class, [
            'tickets_id' => $ticket->getID(),
            'date'       => date('Y-m-d H:i:s'),
            'old_status' => 0,
            'new_status' => Ticket::INCOMING,
            'delay'      => 0,
        ]);

        $tickets_id = $ticket->getID();
        $ticket->delete(['id' => $tickets_id], true);

        $remaining = countElementsInTable(AssignState::getTable(), ['tickets_id' => $tickets_id]);
        $this->assertSame(0, $remaining);
    }
}
