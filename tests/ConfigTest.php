<?php

/*
 -------------------------------------------------------------------------
 TimelineTicket
 Copyright (C) 2013-2026 by the TimelineTicket Development Team.

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
 --------------------------------------------------------------------------
 */

namespace GlpiPlugin\Timelineticket\Tests;

use Glpi\Tests\DbTestCase;
use GlpiPlugin\Timelineticket\Config;
use GlpiPlugin\Timelineticket\Grouplevel;

class ConfigTest extends DbTestCase
{
    public function testConfigRecordExistsAfterInstall(): void
    {
        $this->login('glpi', 'glpi');

        $config = new Config();
        $found  = $config->getFromDB(1);

        $this->assertTrue($found);
    }

    public function testConfigAddWaitingDefaultIsOne(): void
    {
        $this->login('glpi', 'glpi');

        $config = new Config();
        $config->getFromDB(1);

        $this->assertSame(1, $config->getField('add_waiting'));
    }

    public function testConfigGetIconDelegatesToDisplay(): void
    {
        $icon = Config::getIcon();

        $this->assertSame('ti ti-hourglass', $icon);
    }

    public function testGetTabNameForItemReturnsEmptyForNonGrouplevel(): void
    {
        $this->login('glpi', 'glpi');

        $config = new Config();
        $ticket = new \Ticket();

        $result = $config->getTabNameForItem($ticket);

        $this->assertSame('', $result);
    }

    public function testGetTabNameForItemReturnsLabelForGrouplevel(): void
    {
        $this->login('glpi', 'glpi');

        $grouplevel = $this->createItem(Grouplevel::class, [
            'name'        => 'My Level',
            'entities_id' => 0,
            'rank'        => 1,
        ]);

        $config = new Config();
        $result = $config->getTabNameForItem($grouplevel);

        $this->assertNotEmpty($result);
    }
}
