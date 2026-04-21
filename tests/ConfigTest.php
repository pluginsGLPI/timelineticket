<?php

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

        $this->assertSame('1', $config->getField('add_waiting'));
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
