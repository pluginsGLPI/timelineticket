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

use CommonDBTM;
use CommonGLPI;
use DBConnection;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Migration;

class Config extends CommonDBTM
{
    /**
     * Value of the "use_calendar" option, read once per request by useCalendar(): it is asked
     * for every time a time is counted, for each row of a search or each log entry of a rebuild.
     * Forgotten whenever the configuration is written (post_addItem() / post_updateItem()).
     */
    private static ?bool $use_calendar = null;

    public function showReconstructForm()
    {
        TemplateRenderer::getInstance()->display('@timelineticket/config_reconstruct.html.twig', [
            'form_url' => $this->getFormURL(),
        ]);
    }


    public function showConfigForm()
    {
        ob_start();
        Dropdown::showYesNo("add_waiting", $this->fields["add_waiting"]);
        $add_waiting_dropdown = ob_get_clean();

        ob_start();
        Dropdown::showYesNo("use_calendar", $this->fields["use_calendar"] ?? 0);
        $use_calendar_dropdown = ob_get_clean();

        TemplateRenderer::getInstance()->display('@timelineticket/config.html.twig', [
            'form_url'              => $this->getFormURL(),
            'add_waiting_dropdown'  => $add_waiting_dropdown,
            'use_calendar_dropdown' => $use_calendar_dropdown,
        ]);
    }


    /**
     * Whether times are counted in the opening hours of the ticket calendar.
     *
     * Off by default: since the GLPI 11 version the plugin counts wall-clock time, and an
     * upgrade must not change the figures an instance already reports on.
     * Reads fail closed: a missing row or a table not yet migrated answers no.
     **/
    public static function useCalendar(): bool
    {
        if (self::$use_calendar === null) {
            $config = new self();
            self::$use_calendar = $config->getFromDB(1)
                && (int) ($config->fields['use_calendar'] ?? 0) === 1;
        }

        return self::$use_calendar;
    }

    public function post_addItem()
    {
        self::$use_calendar = null;
    }

    public function post_updateItem($history = true)
    {
        self::$use_calendar = null;
    }


    public static function getIcon()
    {
        return Display::getIcon();
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {

        // can exists for template
        if ($item->getType() == Grouplevel::class && Grouplevel::canUpdate()) {
            return self::createTabEntry(_sx('button', 'Add an item'));
        }
        return '';
    }


    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        // This tab only holds the "add a group" form: do not render it to a profile
        // that is not allowed to update the service level it belongs to.
        if (!Grouplevel::canUpdate()) {
            return false;
        }

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
                        `use_calendar` tinyint NOT NULL DEFAULT '0',
                        PRIMARY KEY  (`id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }

        // The column arrived after the table: an instance installed before it carries the table
        // without the flag. addField() checks the column itself and queues nothing when it is
        // already there, so this covers the fresh install and the upgrade alike. The plugin
        // never calls executeMigration(), each class flushing its own table instead.
        $migration->addField($table, 'use_calendar', 'bool', ['value' => 0]);
        // The timeline is drawn with the ECharts library of GLPI since 12.0.1 / 11.6.1: the
        // Google Charts option, which made the tab reach www.gstatic.com, is gone
        $migration->dropField($table, 'use_google_charts');
        $migration->migrationOneTable($table);

        $conf = new self();
        if (!$conf->getFromDB(1)) {
            $conf->add([
                'id'           => 1,
                'add_waiting'  => 1,
                'use_calendar' => 0]);
        }
    }

    public static function uninstall()
    {
        global $DB;

        $DB->dropTable(self::getTable(), true);
    }
}
