<?php

namespace GlpiPlugin\Timelineticket\Tests;

use GlpiPlugin\Timelineticket\Display;
use PHPUnit\Framework\TestCase;

class DisplayTest extends TestCase
{
    public function testGetTypeNameSingular(): void
    {
        $this->assertSame('Timeline of ticket', Display::getTypeName(1));
    }

    public function testGetTypeNamePlural(): void
    {
        $this->assertSame('Timeline of tickets', Display::getTypeName(2));
    }

    public function testGetIconReturnsTiHourglass(): void
    {
        $this->assertSame('ti ti-hourglass', Display::getIcon());
    }
}
