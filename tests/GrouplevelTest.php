<?php

namespace GlpiPlugin\Timelineticket\Tests;

use Glpi\Tests\DbTestCase;
use GlpiPlugin\Timelineticket\Grouplevel;

class GrouplevelTest extends DbTestCase
{
    public function testGetTypeNameSingular(): void
    {
        $this->assertSame('Service level', Grouplevel::getTypeName(1));
    }

    public function testGetTypeNamePlural(): void
    {
        $this->assertSame('Service levels', Grouplevel::getTypeName(2));
    }

    public function testGrouplevelCanBeCreatedAndRetrieved(): void
    {
        $this->login('glpi', 'glpi');

        $grouplevel = $this->createItem(Grouplevel::class, [
            'name'        => 'Level 1',
            'entities_id' => 0,
            'rank'        => 1,
        ]);

        $this->assertGreaterThan(0, $grouplevel->getID());
        $this->assertSame('Level 1', $grouplevel->getField('name'));
    }

    public function testGrouplevelCanBeUpdated(): void
    {
        $this->login('glpi', 'glpi');

        $grouplevel = $this->createItem(Grouplevel::class, [
            'name'        => 'Old Name',
            'entities_id' => 0,
            'rank'        => 1,
        ]);

        $this->updateItem(Grouplevel::class, $grouplevel->getID(), [
            'name' => 'New Name',
        ]);

        $grouplevel->getFromDB($grouplevel->getID());
        $this->assertSame('New Name', $grouplevel->getField('name'));
    }

    public function testGrouplevelCanBeDeleted(): void
    {
        $this->login('glpi', 'glpi');

        $grouplevel = $this->createItem(Grouplevel::class, [
            'name'        => 'To Delete',
            'entities_id' => 0,
            'rank'        => 2,
        ]);
        $id = $grouplevel->getID();

        $grouplevel->delete(['id' => $id], true);

        $remaining = countElementsInTable(Grouplevel::getTable(), ['id' => $id]);
        $this->assertSame(0, $remaining);
    }

    public function testGetAdditionalFieldsContainsRankAndGroups(): void
    {
        $grouplevel = new Grouplevel();
        $fields = $grouplevel->getAdditionalFields();

        $field_names = array_column($fields, 'name');
        $this->assertContains('rank', $field_names);
        $this->assertContains('groups', $field_names);
    }

    public function testPrepareInputForUpdateAddsGroupToEmptyList(): void
    {
        $this->login('glpi', 'glpi');

        $grouplevel = $this->createItem(Grouplevel::class, [
            'name'        => 'Group Level',
            'entities_id' => 0,
            'rank'        => 3,
            'groups'      => '',
        ]);

        $result = $grouplevel->prepareInputForUpdate([
            'id'                => $grouplevel->getID(),
            'add_groups'        => 'add_groups',
            '_groups_id_assign' => 1,
        ]);

        $decoded = json_decode($result['groups'], true);
        $this->assertContains(1, $decoded);
    }

    public function testPrepareInputForUpdateDeletesGroupFromList(): void
    {
        $this->login('glpi', 'glpi');

        $grouplevel = $this->createItem(Grouplevel::class, [
            'name'        => 'Group Level Delete',
            'entities_id' => 0,
            'rank'        => 4,
            'groups'      => json_encode([1, 2]),
        ]);

        $result = $grouplevel->prepareInputForUpdate([
            'id'                => $grouplevel->getID(),
            'delete_groups'     => 'delete_groups',
            '_groups_id_assign' => 1,
        ]);

        $decoded = json_decode($result['groups'], true);
        $this->assertNotContains(1, $decoded);
        $this->assertContains(2, $decoded);
    }

    public function testPrepareInputForUpdateDoesNotAddDuplicateGroup(): void
    {
        $this->login('glpi', 'glpi');

        $grouplevel = $this->createItem(Grouplevel::class, [
            'name'        => 'Group Level No Dup',
            'entities_id' => 0,
            'rank'        => 5,
            'groups'      => json_encode([1]),
        ]);

        $result = $grouplevel->prepareInputForUpdate([
            'id'                => $grouplevel->getID(),
            'add_groups'        => 'add_groups',
            '_groups_id_assign' => 1,
        ]);

        $decoded = json_decode($result['groups'], true);
        $this->assertCount(1, $decoded);
    }

    public function testPrepareInputForUpdateDeleteLastGroupReturnsEmptyString(): void
    {
        $this->login('glpi', 'glpi');

        $grouplevel = $this->createItem(Grouplevel::class, [
            'name'        => 'Group Level One',
            'entities_id' => 0,
            'rank'        => 6,
            'groups'      => json_encode([1]),
        ]);

        $result = $grouplevel->prepareInputForUpdate([
            'id'                => $grouplevel->getID(),
            'delete_groups'     => 'delete_groups',
            '_groups_id_assign' => 1,
        ]);

        $this->assertSame('', $result['groups']);
    }

    public function testRawSearchOptionsContainsRankOption(): void
    {
        $grouplevel = new Grouplevel();
        $options = $grouplevel->rawSearchOptions();

        $ids = array_column($options, 'id');
        $this->assertContains('11', $ids);
    }

    public function testRawSearchOptionsContainsGroupsOption(): void
    {
        $grouplevel = new Grouplevel();
        $options = $grouplevel->rawSearchOptions();

        $ids = array_column($options, 'id');
        $this->assertContains('12', $ids);
    }
}
