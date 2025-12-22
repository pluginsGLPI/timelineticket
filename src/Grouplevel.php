<?php

/*
   ------------------------------------------------------------------------
   TimelineTicket
   Copyright (C) 2013-2025 by the TimelineTicket Development Team.

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

   ------------------------------------------------------------------------
 */

namespace GlpiPlugin\Timelineticket;

use CommonDropdown;
use DBConnection;
use DbUtils;
use Dropdown;
use Group;
use Html;
use Migration;
use Toolbox;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

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
                    if (!empty($groups)) {
                        echo "<table class='tab_cadre_fixe' cellpadding='5'>";
                        foreach ($groups as $key => $val) {
                            echo "<tr class='tab_bg_1 center'>";
                            echo "<td>";
                            echo Dropdown::getDropdownName("glpi_groups", $val);
                            echo "</td>";
                            echo "<td>";
                            Html::showSimpleForm(
                                Toolbox::getItemTypeFormURL(Config::class),
                                'delete_groups',
                                _x('button', 'Delete permanently'),
                                [
                                    'delete_groups' => 'delete_groups',
                                    'id' => $ID,
                                    '_groups_id_assign' => $val,
                                ],
                                'ti-trash'
                            );
                            echo " </td>";
                            echo "</tr>";
                        }

                        echo "</table>";
                    } else {
                        echo __('None');
                    }
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

        echo "<form action='" . Toolbox::getItemTypeFormURL(Config::class) . "' method='post'>";
        echo "<table class='tab_cadre_fixe' cellpadding='5'>";
        echo "<tr class='tab_bg_1 center'>";
        echo "<th>" . __('Group') . "</th>";
        echo "<th>&nbsp;</th>";
        echo "</tr>";
        echo "<tr class='tab_bg_1 center'>";
        echo "<td>";

        $used = ($item->fields["groups"] == '' ? [] : json_decode($item->fields["groups"], true));

        Group::dropdown(['name'        => '_groups_id_assign',
            'used'        => $used,
            'entity'      => $item->fields['entities_id'],
            'entity_sons' => $item->fields["is_recursive"],
            'condition'   => ['is_assign' => 1]]);

        echo "</td>";
        echo "<td>";
        echo Html::hidden('id', ['value' => $item->getID()]);
        echo Html::submit(_sx('button', 'Add'), ['name' => 'add_groups', 'class' => 'btn btn-primary']);
        echo "</td>";
        echo "</tr>";
        echo "</table>";
        Html::closeForm();
    }

    public function getLaskRank()
    {
        $dbu      = new DbUtils();
        $restrict = $dbu->getEntitiesRestrictCriteria("glpi_plugin_timelineticket_grouplevels", '', '', true)
                  + ["ORDER" => "rank DESC"] + ["LIMIT" => 1];
        $configs  = $dbu->getAllDataFromTable("glpi_plugin_timelineticket_grouplevels", $restrict);
        if (!empty($configs)) {
            foreach ($configs as $config) {
                return $config['rank'];
            }
        }
    }

    public function post_getEmpty()
    {

        $this->fields['rank'] = self::getLaskRank() + 1;
    }

    public function prepareInputForUpdate($params)
    {
        $dbu = new DbUtils();
        if (isset($params["add_groups"])) {
            $input = [];

            $restrict = ["id" => $params['id']];
            $configs  = $dbu->getAllDataFromTable("glpi_plugin_timelineticket_grouplevels", $restrict);

            $groups = [];
            if (!empty($configs)) {
                foreach ($configs as $config) {
                    if (!empty($config["groups"])) {
                        $groups = json_decode($config["groups"], true);
                        if (count($groups) > 0) {
                            if (!in_array($params["_groups_id_assign"], $groups)) {
                                array_push($groups, $params["_groups_id_assign"]);
                            }
                        } else {
                            $groups = [$params["_groups_id_assign"]];
                        }
                    } else {
                        $groups = [$params["_groups_id_assign"]];
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
                            if (($key = array_search($params["_groups_id_assign"], $groups)) !== false) {
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
            $input = $params;
        }
        return $input;
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
