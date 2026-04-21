<?php

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
