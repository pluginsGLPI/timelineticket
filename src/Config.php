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

use CommonDBTM;
use CommonGLPI;
use DBConnection;
use Dropdown;
use Html;
use Migration;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class Config extends CommonDBTM
{
    public function showReconstructForm()
    {

        echo "<form method='POST' action=\"" . $this->getFormURL() . "\">";

        echo "<table class='tab_cadre_fixe'>";

        echo "<tr>";
        echo "<th class='center'>";
        echo __('Setup');
        echo "&nbsp;" . __('(Can take many time if you have many tickets)', 'timelineticket');
        echo "</th>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td class='center'>";

        echo "<br>";

        echo Html::submit(_sx(
            'button',
            'Reconstruct states timeline for all tickets',
            'timelineticket'
        ), ['name' => 'reconstructStates', 'class' => 'btn btn-primary']);

        echo "<br>";
        echo "<br>";

        echo Html::submit(_sx(
            'button',
            'Reconstruct technician groups timeline for all tickets',
            'timelineticket'
        ), ['name' => 'reconstructGroups', 'class' => 'btn btn-primary']);


        echo "<br>";
        echo "<br>";

        echo Html::submit(_sx(
            'button',
            'Reconstruct technicians timeline for all tickets',
            'timelineticket'
        ), ['name' => 'reconstructUsers', 'class' => 'btn btn-primary']);

        echo "</td>";
        echo "</tr>";
        echo "</table>";
        Html::closeForm();
    }


    public function showConfigForm()
    {

        echo "<form method='POST' action=\"" . $this->getFormURL() . "\">";

        echo "<table class='tab_cadre_fixe'>";

        echo "<tr><th colspan='2'>";
        echo __('Flags', 'timelineticket');
        echo "</th></tr>";

        echo "<tr class='tab_bg_1 top'>";
        echo "<td>" . __(
            'Input time on groups / users when ticket is waiting',
            'timelineticket'
        ) . "</td>";
        echo "<td>";
        Dropdown::showYesNo("add_waiting", $this->fields["add_waiting"]);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'><td>";
        echo Html::hidden('id', ['value' => 1]);
        echo Html::submit(_sx('button', 'Save'), ['name' => 'update', 'class' => 'btn btn-primary']);
        echo "</td></tr>";
        echo "</table>";
        Html::closeForm();
    }


    public static function getIcon()
    {
        return Display::getIcon();
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {

        // can exists for template
        if ($item->getType() == Grouplevel::class) {
            return self::createTabEntry(_sx('button', 'Add an item'));
        }
        return '';
    }


    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {

        Grouplevel::showAddGroup($item);
        return true;
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
                        `add_waiting` int {$default_key_sign} NOT NULL DEFAULT '1',
                        PRIMARY KEY  (`id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }

        $conf = new self();
        if (!$conf->getFromDB(1)) {
            $conf->add([
                'id'          => 1,
                'add_waiting' => 1]);
        }
    }

    public static function uninstall()
    {
        global $DB;

        $DB->dropTable(self::getTable(), true);
    }
}
