<?php

namespace Tests\Unit\Frontend;

use Tests\TestCase;

/**
 * Guards the Evaluation-page auto-scroll regression: module/board tab helpers
 * must adjust only the overflow scroller's scrollLeft — never Element.scrollIntoView.
 */
class ScrollIntoScrollerSourceTest extends TestCase
{
    public function test_module_and_board_tab_scroll_helpers_avoid_scroll_into_view(): void
    {
        $helper = file_get_contents(resource_path('js/scroll-into-scroller.js'));
        $app = file_get_contents(resource_path('js/app.js'));
        $board = file_get_contents(resource_path('js/pages/gh-order-board.js'));

        $this->assertIsString($helper);
        $this->assertStringContainsString('export function scrollChildIntoScrollerX', $helper);
        $this->assertDoesNotMatchRegularExpression(
            '/\w+\.scrollIntoView\s*\(/',
            $helper,
            'Helper must not invoke Element.scrollIntoView.'
        );

        $this->assertStringContainsString("from './scroll-into-scroller'", $app);
        $this->assertStringContainsString('scrollChildIntoScrollerX(switcher, active', $app);
        $this->assertDoesNotMatchRegularExpression(
            '/\w+\.scrollIntoView\s*\(/',
            $app,
            'Global module tab scroll must not call Element.scrollIntoView (page jump).'
        );

        $this->assertStringContainsString("from '../scroll-into-scroller'", $board);
        $this->assertStringContainsString('scrollChildIntoScrollerX(scroller, active', $board);
        $this->assertDoesNotMatchRegularExpression(
            '/\w+\.scrollIntoView\s*\(/',
            $board,
            'GH board tab scroll must not call Element.scrollIntoView (page jump).'
        );
    }
}
