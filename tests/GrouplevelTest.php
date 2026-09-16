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
use GlpiPlugin\Timelineticket\Grouplevel;
use Group;

class GrouplevelTest extends DbTestCase
{
    /**
     * A service level only ever stores groups the operator may actually assign, inside its own
     * entity: prepareInputForAdd() and prepareInputForUpdate() both confront existence, the
     * is_assign flag and the two entity perimeters before writing the column. The tests below
     * used to name the group "1", which satisfies none of that on a freshly installed test
     * database -- the identifier either does not exist or is not flagged assignable in the root
     * entity -- so the column came back empty and every expectation read as a regression of the
     * filter rather than as a missing fixture. Build the group the test needs instead of naming
     * one by number.
     */
    private function createAssignableGroup(string $name): int
    {
        $group = $this->createItem(Group::class, [
            'name'         => $name,
            'entities_id'  => 0,
            'is_recursive' => 0,
            'is_assign'    => 1,
        ]);

        return (int) $group->getID();
    }

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

        $groups_id = $this->createAssignableGroup('Assignable group to add');

        $grouplevel = $this->createItem(Grouplevel::class, [
            'name'        => 'Group Level',
            'entities_id' => 0,
            'rank'        => 3,
            'groups'      => '',
        ]);

        $result = $grouplevel->prepareInputForUpdate([
            'id'                => $grouplevel->getID(),
            'add_groups'        => 'add_groups',
            '_groups_id_assign' => $groups_id,
        ]);

        $decoded = json_decode($result['groups'], true);
        $this->assertContains($groups_id, $decoded);
    }

    public function testPrepareInputForUpdateDeletesGroupFromList(): void
    {
        $this->login('glpi', 'glpi');

        $removed_id = $this->createAssignableGroup('Assignable group to remove');
        $kept_id    = $this->createAssignableGroup('Assignable group to keep');

        $grouplevel = $this->createItem(Grouplevel::class, [
            'name'        => 'Group Level Delete',
            'entities_id' => 0,
            'rank'        => 4,
            'groups'      => json_encode([$removed_id, $kept_id]),
        ]);

        $result = $grouplevel->prepareInputForUpdate([
            'id'                => $grouplevel->getID(),
            'delete_groups'     => 'delete_groups',
            '_groups_id_assign' => $removed_id,
        ]);

        $decoded = json_decode($result['groups'], true);
        $this->assertNotContains($removed_id, $decoded);
        $this->assertContains($kept_id, $decoded);
    }

    public function testPrepareInputForUpdateDoesNotAddDuplicateGroup(): void
    {
        $this->login('glpi', 'glpi');

        $groups_id = $this->createAssignableGroup('Assignable group already listed');

        $grouplevel = $this->createItem(Grouplevel::class, [
            'name'        => 'Group Level No Dup',
            'entities_id' => 0,
            'rank'        => 5,
            'groups'      => json_encode([$groups_id]),
        ]);

        $result = $grouplevel->prepareInputForUpdate([
            'id'                => $grouplevel->getID(),
            'add_groups'        => 'add_groups',
            '_groups_id_assign' => $groups_id,
        ]);

        $decoded = json_decode($result['groups'], true);
        $this->assertCount(1, $decoded);
    }

    public function testPrepareInputForUpdateDeleteLastGroupReturnsEmptyString(): void
    {
        $this->login('glpi', 'glpi');

        $groups_id = $this->createAssignableGroup('Assignable group alone');

        $grouplevel = $this->createItem(Grouplevel::class, [
            'name'        => 'Group Level One',
            'entities_id' => 0,
            'rank'        => 6,
            'groups'      => json_encode([$groups_id]),
        ]);

        $result = $grouplevel->prepareInputForUpdate([
            'id'                => $grouplevel->getID(),
            'delete_groups'     => 'delete_groups',
            '_groups_id_assign' => $groups_id,
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
