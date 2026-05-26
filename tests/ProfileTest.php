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

use GlpiPlugin\Timelineticket\Profile;
use PHPUnit\Framework\TestCase;

class ProfileTest extends TestCase
{
    public function testGetAllRightsReturnsSingleEntry(): void
    {
        $rights = Profile::getAllRights();

        $this->assertCount(1, $rights);
    }

    public function testGetAllRightsContainsExpectedField(): void
    {
        $rights = Profile::getAllRights();

        $this->assertSame('plugin_timelineticket_ticket', $rights[0]['field']);
    }

    public function testGetAllRightsContainsReadAndUpdateRights(): void
    {
        $rights = Profile::getAllRights();

        $this->assertArrayHasKey(READ, $rights[0]['rights']);
        $this->assertArrayHasKey(UPDATE, $rights[0]['rights']);
    }

    public function testTranslateARightReturnsZeroForEmpty(): void
    {
        $this->assertSame(0, Profile::translateARight(''));
    }

    public function testTranslateARightReturnsReadConstantForR(): void
    {
        $this->assertSame(READ, Profile::translateARight('r'));
    }

    public function testTranslateARightReturnsAllstandardrightForW(): void
    {
        $expected = ALLSTANDARDRIGHT + READNOTE + UPDATENOTE;

        $this->assertSame($expected, Profile::translateARight('w'));
    }

    public function testTranslateARightReturnsZeroForUnknown(): void
    {
        $this->assertSame(0, Profile::translateARight('x'));
    }

    public function testTranslateARightReturnsStringZeroAsIs(): void
    {
        $this->assertSame('0', Profile::translateARight('0'));
    }

    public function testTranslateARightReturnsStringOneAsIs(): void
    {
        $this->assertSame('1', Profile::translateARight('1'));
    }
}
