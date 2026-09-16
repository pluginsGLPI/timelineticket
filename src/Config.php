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
        Dropdown::showYesNo("use_google_charts", $this->fields["use_google_charts"] ?? 0);
        $use_google_charts_dropdown = ob_get_clean();

        TemplateRenderer::getInstance()->display('@timelineticket/config.html.twig', [
            'form_url'                   => $this->getFormURL(),
            'add_waiting_dropdown'       => $add_waiting_dropdown,
            'use_google_charts_dropdown' => $use_google_charts_dropdown,
        ]);
    }

    /**
     * Whether the administrator has allowed the Google Charts rendering of the timeline.
     *
     * The chart bootstrap is served locally, but it resolves its rendering modules from
     * https://www.gstatic.com/charts/ when the chart is drawn: third-party JavaScript, neither
     * versioned nor pinnable by integrity -- the loader builds the module URLs itself, so no SRI
     * applies -- runs in an authenticated GLPI page with access to its DOM and session cookies.
     * The terms of service of Google Charts forbid redistributing or self-hosting those modules,
     * so the dependency cannot be removed; it can only be made a decision of the operator. It is
     * off unless explicitly turned on: an instance on a closed network, or one bound by a data
     * sovereignty requirement, must not reach out to Google merely because the plugin is
     * installed, and every opening of the tab discloses its IP address and Referer to Google.
     * The detail table of the tab is rendered either way, so the tab keeps its content.
     *
     * Reads fail closed: a missing row or a table not yet migrated answers no.
     **/
    public static function useGoogleCharts(): bool
    {
        $config = new self();
        if (!$config->getFromDB(1)) {
            return false;
        }

        return (int) ($config->fields['use_google_charts'] ?? 0) === 1;
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
                        `use_google_charts` tinyint NOT NULL DEFAULT '0',
                        PRIMARY KEY  (`id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }

        // The column arrived after the table: an instance installed before it carries the table
        // without the flag. addField() checks the column itself and queues nothing when it is
        // already there, so this covers the fresh install and the upgrade alike. The plugin
        // never calls executeMigration(), each class flushing its own table instead.
        // It defaults to 0: the charts reach out to www.gstatic.com at draw time, which is the
        // operator's call to make, not a side effect of upgrading -- see useGoogleCharts().
        $migration->addField($table, 'use_google_charts', 'bool', ['value' => 0]);
        $migration->migrationOneTable($table);

        $conf = new self();
        if (!$conf->getFromDB(1)) {
            $conf->add([
                'id'                => 1,
                'add_waiting'       => 1,
                'use_google_charts' => 0]);
        }
    }

    public static function uninstall()
    {
        global $DB;

        $DB->dropTable(self::getTable(), true);
    }
}
