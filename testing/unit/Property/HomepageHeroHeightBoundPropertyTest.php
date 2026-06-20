<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for compact homepage hero height constraints.
 *
 * **Validates: Requirements 2.1, 2.2**
 *
 * Property 2: Hero Height Bound
 * For all viewport widths, rendered hero markup applies breakpoint-specific
 * constraints that keep desktop height at or below 360px and mobile height at
 * or below 220px while preserving the selected aspect-ratio container.
 */
class HomepageHeroHeightBoundPropertyTest extends TestCase
{
    private const ITERATIONS = 500;
    private const MOBILE_MAX_HEIGHT = 220;
    private const DESKTOP_MAX_HEIGHT = 360;

    private string $homepageTemplate;

    protected function setUp(): void
    {
        $this->homepageTemplate = (string) file_get_contents(__DIR__ . '/../../../index.php');
    }

    /**
     * @test
     * Property 2: Hero Height Bound
     * **Validates: Requirements 2.1, 2.2**
     */
    public function heroMarkupKeepsBreakpointHeightBoundsAndAspectRatio(): void
    {
        $heroMarkup = $this->extractHeroContainerMarkup();
        $classes = $this->extractClassAttribute($heroMarkup);

        $this->assertMatchesRegularExpression(
            '/\bmax-h-\[(\d+)px\]/',
            $classes,
            'Hero container must declare a mobile-first max-height class.'
        );
        $this->assertMatchesRegularExpression(
            '/\bmd:max-h-\[(\d+)px\]/',
            $classes,
            'Hero container must declare a desktop breakpoint max-height class.'
        );
        $this->assertMatchesRegularExpression(
            '/style="[^"]*aspect-ratio:\s*1200\s*\/\s*380\s*;?[^"]*"/',
            $heroMarkup,
            'Hero container must preserve the selected aspect-ratio wrapper.'
        );
        $this->assertStringContainsString('overflow-hidden', $classes, 'Oversized uploaded banners must be clipped within the hero bound.');
        $this->assertStringContainsString('object-contain', $heroMarkup, 'Hero images must preserve full banner image aspect ratio.');

        $mobileMaxHeight = $this->extractMaxHeight($classes, '');
        $desktopMaxHeight = $this->extractMaxHeight($classes, 'md:');

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $viewportWidth = random_int(1, 2400);
            $applicableBound = $viewportWidth >= 768 ? self::DESKTOP_MAX_HEIGHT : self::MOBILE_MAX_HEIGHT;
            $appliedMaxHeight = $viewportWidth >= 768 ? $desktopMaxHeight : $mobileMaxHeight;

            $this->assertLessThanOrEqual(
                $applicableBound,
                $appliedMaxHeight,
                "Hero max height must stay within the breakpoint bound for viewport {$viewportWidth}px."
            );
        }
    }

    private function extractHeroContainerMarkup(): string
    {
        $pattern = '/<div class="[^"]*max-h-\[\d+px\][^"]*md:max-h-\[\d+px\][^"]*"\s+style="[^"]*aspect-ratio:[\s\S]*?<\/div>\s*<\/a>/';
        $this->assertSame(1, preg_match($pattern, $this->homepageTemplate, $matches), 'Compact hero container markup was not found.');

        return $matches[0];
    }

    private function extractClassAttribute(string $markup): string
    {
        $this->assertSame(1, preg_match('/class="([^"]*)"/', $markup, $matches), 'Hero container class attribute was not found.');

        return $matches[1];
    }

    private function extractMaxHeight(string $classes, string $prefix): int
    {
        $pattern = '/\b' . preg_quote($prefix, '/') . 'max-h-\[(\d+)px\]/';
        $this->assertSame(1, preg_match($pattern, $classes, $matches), "Hero {$prefix}max-height class was not found.");

        return (int) $matches[1];
    }
}
