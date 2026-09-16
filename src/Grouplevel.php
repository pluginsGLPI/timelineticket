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

namespace GlpiPlugin\Timelineticket;

use CommonDropdown;
use DBConnection;
use DbUtils;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Group;
use Migration;
use Session;
use Toolbox;

/**
 * Service levels, declared as a dropdown of the plugin in setup.php.
 *
 * There is deliberately no front/grouplevel.php nor front/grouplevel.form.php. Since GLPI 11
 * those two files are dead weight for a plugin dropdown: LegacyItemtypeRouteListener resolves
 * /marketplace/timelineticket/front/grouplevel[.form].php to this class -- the missing file
 * makes LegacyRouterListener stand down, and the itemtype listener then hands the request to
 * GenericListController for the list and DropdownFormController for the form. Both controllers
 * enforce the rights themselves, through the "dropdown" right this class inherits from
 * CommonDropdown. Adding the front files back would only duplicate that, with the risk of a
 * weaker gate than the one of the core.
 *
 * That reasoning holds for this dropdown and for this dropdown only. It rests on the "dropdown"
 * right of the core carrying, by itself, the whole perimeter of a table of nomenclature. It does
 * not extend to AssignState, AssignGroup and AssignUser, whose only right is the global
 * plugin_timelineticket_ticket: no notion of entity or of ticket visibility is attached to it,
 * and the search engine reads their tables in raw SQL without ever calling their canViewItem().
 * Those three, and the tableless Display, therefore do carry a front/ file of their own, whose
 * only purpose is to refuse the route -- see front/assignstate.php.
 */
class Grouplevel extends CommonDropdown
{
    public static function getTypeName($nb = 0)
    {

        return _n('Service level', 'Service levels', $nb, 'timelineticket');
    }

    public function getAdditionalFields()
    {

        return [['name'  => 'rank',
            'label' => __('Position'),
            'type'  => 'text',
            'list'  => true],
            ['name'  => 'groups',
                'label' => __('List of associated groups', 'timelineticket'),
                'type'  => 'groups',
                'list'  => true]];
    }

    public function displaySpecificTypeField($ID, $field = [], array $options = [])
    {

        switch ($field['type']) {
            case 'groups':
                if (!empty($this->fields[$field['name']])) {
                    $groups = json_decode($this->fields[$field['name']], true);
                    $groups_list = [];
                    if (!empty($groups)) {
                        foreach ($groups as $val) {
                            // getDropdownName() returns the raw stored value (GLPI 10+);
                            // it is HTML-escaped by Twig auto-escaping in the template.
                            $groups_list[] = [
                                'id'   => $val,
                                'name' => Dropdown::getDropdownName("glpi_groups", $val),
                            ];
                        }
                    }
                    TemplateRenderer::getInstance()->display('@timelineticket/grouplevel_groups.html.twig', [
                        'form_url' => Toolbox::getItemTypeFormURL(Config::class),
                        'id'       => $ID,
                        'groups'     => $groups_list,
                        'can_update' => self::canUpdate(),
                    ]);
                }
                break;
        }
    }

    /**
     * Provides search options configuration. Do not rely directly
     * on this, @see CommonDBTM::searchOptions instead.
     *
     * @since 9.3
     *
     * This should be overloaded in Class
     *
     * @return array a *not indexed* array of search options
     *
     * @see https://glpi-developer-documentation.rtfd.io/en/master/devapi/search.html
     **/
    public function rawSearchOptions()
    {

        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id'            => '11',
            'table'         => $this->getTable(),
            'field'         => 'rank',
            'name'          => __('Position'),
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'            => '12',
            'table'         => $this->getTable(),
            'field'         => 'groups',
            'name'          => __('List of associated groups', 'timelineticket'),
            'massiveaction' => 'false',
            'nosearch'      => true,
        ];

        return $tab;
    }

    /**
     * Define tabs to display
     *
     * @param $options array
     *
     * @return array
     */
    public function defineTabs($options = [])
    {

        $ong = [];
        $this->addDefaultFormTab($ong);
        $this->addStandardTab(Config::class, $ong, $options);

        return $ong;
    }


    public static function showAddGroup($item)
    {
        $used = ($item->fields["groups"] == '' ? [] : json_decode($item->fields["groups"], true));

        ob_start();
        Group::dropdown(['name'        => '_groups_id_assign',
            'used'        => $used,
            'entity'      => $item->fields['entities_id'],
            'entity_sons' => $item->fields["is_recursive"],
            'condition'   => ['is_assign' => 1]]);
        $group_dropdown = ob_get_clean();

        TemplateRenderer::getInstance()->display('@timelineticket/grouplevel_addgroup.html.twig', [
            'form_url'       => Toolbox::getItemTypeFormURL(Config::class),
            'id'             => $item->getID(),
            'group_dropdown' => $group_dropdown,
        ]);
    }

    /**
     * Rank of the last service level of the entity perimeter.
     *
     * @return int 0 when the perimeter holds none yet
     **/
    public static function getLastRank(): int
    {
        $dbu      = new DbUtils();
        $restrict = $dbu->getEntitiesRestrictCriteria("glpi_plugin_timelineticket_grouplevels", '', '', true)
                  + ["ORDER" => "rank DESC"] + ["LIMIT" => 1];
        $configs  = $dbu->getAllDataFromTable("glpi_plugin_timelineticket_grouplevels", $restrict);
        foreach ($configs as $config) {
            return (int) $config['rank'];
        }

        return 0;
    }

    public function post_getEmpty()
    {

        $this->fields['rank'] = self::getLastRank() + 1;
    }

    public function prepareInputForUpdate($params)
    {
        $dbu = new DbUtils();
        if (isset($params["add_groups"])) {
            $input = [];

            // Re-validate the posted group at the sink: the display dropdown
            // (showAddGroup) only offers assignable groups within the
            // Grouplevel's entity, but those constraints are not enforced on the
            // POST. Without this, a forged request could store a group from
            // another entity or a non-assignable group, whose name would then
            // leak in the timeline and Gantt labels. Force an integer value too.
            $groups_id_assign = (int) ($params["_groups_id_assign"] ?? 0);
            $group            = new Group();
            if (
                $groups_id_assign <= 0
                || !$group->getFromDB($groups_id_assign)
                || (int) $group->fields['is_assign'] !== 1
                || !Session::haveAccessToEntity(
                    $group->fields['entities_id'],
                    $group->fields['is_recursive'],
                )
                // haveAccessToEntity() above answers for the operator, not for the level: an
                // operator active on two entities could name a group of the second one in a
                // level of the first. Confront the entity of the level as the dropdown does.
                // It is read from the stored row and never from the POST, because this branch
                // writes id and groups only -- a posted entities_id would widen the check
                // without ever moving the level.
                || count($this->filterGroupsOfLevelEntity(
                    [$groups_id_assign],
                    (int) ($this->fields['entities_id'] ?? 0),
                    (int) ($this->fields['is_recursive'] ?? 0),
                )) === 0
            ) {
                // Invalid or out-of-scope group: ignore the add, leave the level untouched.
                return ['id' => $params['id']];
            }

            $restrict = ["id" => $params['id']];
            $configs  = $dbu->getAllDataFromTable("glpi_plugin_timelineticket_grouplevels", $restrict);

            $groups = [];
            if (!empty($configs)) {
                foreach ($configs as $config) {
                    if (!empty($config["groups"])) {
                        $groups = json_decode($config["groups"], true);
                        if (count($groups) > 0) {
                            if (!in_array($groups_id_assign, $groups)) {
                                array_push($groups, $groups_id_assign);
                            }
                        } else {
                            $groups = [$groups_id_assign];
                        }
                    } else {
                        $groups = [$groups_id_assign];
                    }
                }
            }

            $group = json_encode($groups);

            $input['id']     = $params['id'];
            $input['groups'] = $group;
        } elseif (isset($params["delete_groups"])) {
            $restrict = ["id" => $params['id']];
            $configs  = $dbu->getAllDataFromTable("glpi_plugin_timelineticket_grouplevels", $restrict);

            $groups = [];
            if (!empty($configs)) {
                foreach ($configs as $config) {
                    if (!empty($config["groups"])) {
                        $groups = json_decode($config["groups"], true);
                        if (count($groups) > 0) {
                            // The stored identifiers are integers (the add branch above casts
                            // them), the posted one is a string: compare on the same type.
                            $key = array_search((int) ($params["_groups_id_assign"] ?? 0), $groups);
                            if ($key !== false) {
                                unset($groups[$key]);
                            }
                        }
                    }
                }
            }

            if (count($groups) > 0) {
                $group = json_encode($groups);
            } else {
                $group = "";
            }

            $input['id']     = $params['id'];
            $input['groups'] = $group;
        } else {
            $input = $this->sanitizeGroupsColumn($params);
        }
        return parent::prepareInputForUpdate($input);
    }

    public function prepareInputForAdd($input)
    {
        // Creation went through no filtering at all. CommonDropdown hands every posted key to
        // this method, so a direct POST on the dropdown form stored the longtext groups column
        // verbatim -- the very value the update path refuses -- and the group names of another
        // entity then leaked in the timeline and in the Gantt labels of every ticket. The row
        // being created carries no identifier to authorise, so the value itself is what has to
        // be confronted, exactly as on update.
        return parent::prepareInputForAdd($this->sanitizeGroupsColumn($input));
    }

    /**
     * Confront a posted groups column with the perimeter of the session.
     *
     * Grouplevel is a CommonDropdown, so the core form controller hands every posted key
     * straight to prepareInputForAdd()/prepareInputForUpdate(): the longtext groups column is
     * writable verbatim by a direct POST, which walks past the entity and is_assign controls
     * that only guard the add_groups branch. check($id, UPDATE) authorises the row being
     * edited, never the value posted into it, so the value has to be confronted here.
     *
     * @param array<string, mixed> $input Posted input
     *
     * @return array<string, mixed>
     **/
    private function sanitizeGroupsColumn(array $input): array
    {
        if (array_key_exists('groups', $input)) {
            // This path writes the whole row, entity included, so the destination entity is the
            // one the level will actually carry -- read it from the input when it is there, and
            // fall back on the stored row otherwise. Confronting the groups with anything else
            // would either be too strict on a legitimate move between entities, or too lax.
            $filtered        = $this->filterAssignableGroups(
                json_decode((string) $input['groups'], true),
                (int) ($input['entities_id'] ?? $this->fields['entities_id'] ?? 0),
                (int) ($input['is_recursive'] ?? $this->fields['is_recursive'] ?? 0),
            );
            $input['groups'] = count($filtered) > 0 ? json_encode($filtered) : "";
        }

        return $input;
    }


    /**
     * Keep only the group identifiers this session may legitimately store in a service
     * level: existing groups, flagged assignable, and inside its entity perimeter. This
     * is the sink counterpart of the dropdown built by showAddGroup(), whose constraints
     * live in the display only.
     *
     * @param mixed $groups       value decoded from the posted groups column
     * @param int   $entities_id  entity the level belongs to
     * @param int   $is_recursive whether the level is shared with the child entities
     *
     * @return array<int, int>
     **/
    private function filterAssignableGroups($groups, int $entities_id, int $is_recursive): array
    {
        if (!is_array($groups)) {
            // Anything that is not a list of identifiers -- malformed JSON included --
            // empties the column rather than being stored and broken at display time.
            return [];
        }

        $filtered = [];
        $group    = new Group();
        foreach ($groups as $groups_id) {
            if (!is_scalar($groups_id)) {
                continue;
            }

            $groups_id = (int) $groups_id;
            if (
                $groups_id <= 0
                || !$group->getFromDB($groups_id)
                || (int) $group->fields['is_assign'] !== 1
                || !Session::haveAccessToEntity(
                    $group->fields['entities_id'],
                    $group->fields['is_recursive'],
                )
            ) {
                continue;
            }

            $filtered[] = $groups_id;
        }

        // The loop above confronts the perimeter of the operator; the level has its own, and it
        // is the narrower of the two. Keep both, this one as the defence-in-depth guard.
        return $this->filterGroupsOfLevelEntity(
            array_values(array_unique($filtered)),
            $entities_id,
            $is_recursive,
        );
    }

    /**
     * Keep, among the identifiers handed, the groups the service level's own entity may name.
     *
     * Session::haveAccessToEntity() confronts the perimeter of the operator, which is wider
     * than the one of the level. showAddGroup() already restricts its proposals to the entity
     * of the level -- it hands 'entity' and 'entity_sons' to the core dropdown -- so the sink
     * has to replay that same constraint. Without it, an operator active on entities A and B
     * could post a group of B into a level of A; that group name then surfaced in the timeline
     * labels and in the headers of the three "spent time by group" reports, read by users of A
     * who have no access to B at all.
     *
     * The restriction goes through the same core helper as the dropdown rather than being
     * reimplemented here, so display and sink cannot drift apart: a group is in scope when it
     * sits in the entity of the level -- or in one of its children when the level is recursive
     * -- or when it is itself recursive in one of the ancestors of that entity.
     *
     * @param array<int, int> $groups_ids   identifiers already normalized to positive integers
     * @param int             $entities_id  entity the level belongs to
     * @param int             $is_recursive whether the level is shared with the child entities
     *
     * @return array<int, int>
     **/
    private function filterGroupsOfLevelEntity(array $groups_ids, int $entities_id, int $is_recursive): array
    {
        global $DB;

        if (count($groups_ids) === 0) {
            return [];
        }

        $dbu             = new DbUtils();
        $entity_restrict = $is_recursive === 1
            ? $dbu->getSonsOf('glpi_entities', $entities_id)
            : $entities_id;

        $allowed  = [];
        $iterator = $DB->request([
            'SELECT' => 'id',
            'FROM'   => Group::getTable(),
            'WHERE'  => [
                Group::getTable() . '.id' => $groups_ids,
                $dbu->getEntitiesRestrictCriteria(Group::getTable(), '', $entity_restrict, true),
            ],
        ]);
        foreach ($iterator as $row) {
            $allowed[] = (int) $row['id'];
        }

        // Intersect rather than return the rows as they came: the order of the column is the
        // display order of the level, and the database returns them in whichever order it likes.
        return array_values(array_intersect($groups_ids, $allowed));
    }

    public static function install(Migration $migration)
    {
        global $DB;

        $default_charset   = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();
        $table  = self::getTable();

        if (!$DB->tableExists($table)) {
            $query = "CREATE TABLE `$table` (
                        `id` int {$default_key_sign} NOT NULL auto_increment,
                        `entities_id` int {$default_key_sign} NOT NULL DEFAULT '0',
                        `is_recursive` tinyint  NOT NULL default '0',
                        `name` varchar(255) collate utf8mb4_unicode_ci default NULL,
                        `groups` longtext collate utf8mb4_unicode_ci,
                        `rank` smallint NOT NULL default '0',
                        `comment` text collate utf8mb4_unicode_ci,
                        PRIMARY KEY (`id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
    }

    public static function uninstall()
    {
        global $DB;

        $DB->dropTable(self::getTable(), true);
    }
}
