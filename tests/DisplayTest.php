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

namespace GlpiPlugin\Timelineticket\Tests;

use GlpiPlugin\Timelineticket\Display;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

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

    /**
     * Build the markup ChartService::render() returns: the loader tag, the plain JavaScript
     * template the library ships, the serialised payload, then the chart container.
     */
    private static function renderedMarkup(string $payload): string
    {
        return '<script src="/plugins/timelineticket/js/google-charts/loader.js"></script>'
               . '<script>const GoogleCharts = {}; if (a < b && c > d) { GoogleCharts.x = 1; }</script>'
               . '<script>GoogleCharts.loadCharts(' . $payload . ', true);</script>'
               . '<div id="ticketAssignState"></div>';
    }

    private static function harden(string $markup): ?string
    {
        $method = new ReflectionMethod(Display::class, 'hardenChartMarkup');
        $method->setAccessible(true);

        return $method->invoke(null, $markup);
    }

    public function testHardenChartMarkupEscapesAnglesOfThePayloadOnly(): void
    {
        $payload = json_encode(['charts' => [['label' => '<!--<script>alert(1)</script>']]]);
        $hardened = self::harden(self::renderedMarkup($payload));

        // The payload keeps no angle bracket of its own, so no label can flip the HTML parser.
        $this->assertStringContainsString('<', $hardened);
        $this->assertStringNotContainsString('<!--', $hardened);

        // The template script is plain JavaScript: its comparison operators must survive.
        $this->assertStringContainsString('if (a < b && c > d)', $hardened);
    }

    /**
     * ChartService::load() does not raise the flag that render() tests, so calling it before
     * render() made the library emit the whole bootstrap a second time -- unhardened, and
     * pointing back at gstatic.com. Display now renders once; guard that here.
     */
    public function testHardenChartMarkupEmitsEachScriptTagOnce(): void
    {
        $hardened = self::harden(self::renderedMarkup('{"charts":[]}'));

        $this->assertSame(3, substr_count($hardened, '<script'));
        $this->assertSame(1, substr_count($hardened, ' src="'));
        $this->assertSame(1, substr_count($hardened, 'GoogleCharts.loadCharts('));
        $this->assertStringNotContainsString('gstatic.com', $hardened);
    }

    /**
     * The library serialises the labels with a bare json_encode(), so a label holding
     * "</script>" closes the inline block. Whatever wrapper the library puts around the
     * serialised data, that sequence must never reach the page verbatim.
     */
    public function testHardenChartMarkupNeverEmitsAClosingScriptFromALabel(): void
    {
        $payload  = json_encode(['charts' => [['label' => 'A</script><img src=x onerror=alert(1)>']]]);
        $hardened = self::harden(self::renderedMarkup($payload));

        $this->assertNotNull($hardened);
        // Three real tags close; the label must not have added a fourth.
        $this->assertSame(3, substr_count($hardened, '</script>'));
        $this->assertStringNotContainsString('onerror=alert(1)>', $hardened);
    }

    /**
     * The marker used to be matched immediately after the opening tag, reproducing the exact
     * layout of a private constant of sportlog/google-charts. composer.json allows any 1.x, so
     * a release adding an indentation or a wrapper would have disarmed the escaping silently.
     * Hardening must survive those shapes.
     */
    public function testHardenChartMarkupSurvivesAReformattedLibraryWrapper(): void
    {
        $payload = json_encode(['charts' => [['label' => '<script>alert(1)</script>']]]);
        $markup  = '<script src="/plugins/timelineticket/js/google-charts/loader.js"></script>'
                   . '<script>const GoogleCharts = {}; if (a < b && c > d) { GoogleCharts.x = 1; }</script>'
                   . "<script>\n  document.addEventListener('DOMContentLoaded', function () {\n"
                   . '    GoogleCharts.loadCharts(' . $payload . ", true);\n  });\n</script>"
                   . '<div id="ticketAssignState"></div>';

        $hardened = self::harden($markup);

        $this->assertNotNull($hardened);
        $this->assertSame(3, substr_count($hardened, '</script>'));
        $this->assertStringNotContainsString('<script>alert(1)', $hardened);
        // The plain-JavaScript template script still has to come through untouched.
        $this->assertStringContainsString('if (a < b && c > d)', $hardened);
    }

    /**
     * Fail closed: markup carrying no payload at all means the library no longer emits what
     * this class knows how to escape. Returning it unchanged would publish raw labels, so the
     * caller must get null and render its fallback notice instead.
     */
    public function testHardenChartMarkupFailsClosedWhenNoPayloadIsFound(): void
    {
        $markup = '<script src="/plugins/timelineticket/js/google-charts/loader.js"></script>'
                  . '<div id="ticketAssignState"></div>';

        $this->assertNull(@self::harden($markup));
    }
}
