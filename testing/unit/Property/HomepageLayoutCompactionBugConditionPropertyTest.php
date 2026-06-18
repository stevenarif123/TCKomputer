<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/db.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Exploration Test for Homepage Layout Compaction Bug.
 *
 * **Validates: Requirements 1.1, 1.2, 1.3, 1.4, 2.1, 2.2, 2.3, 2.4**
 *
 * Property 1: Bug Condition - Homepage top-section over-height and loose spacing.
 *
 * This exploration property intentionally encodes compact expectations so it FAILS on unfixed code,
 * surfacing counterexamples that confirm the bug exists.
 */
class HomepageLayoutCompactionBugConditionPropertyTest extends TestCase
{
    private const ITERATIONS = 80;

    /** @var array<string,int> */
    private const SPACING_TO_PX = [
        '0' => 0,
        '0.5' => 2,
        '1' => 4,
        '1.5' => 6,
        '2' => 8,
        '2.5' => 10,
        '3' => 12,
        '3.5' => 14,
        '4' => 16,
        '5' => 20,
        '6' => 24,
        '7' => 28,
        '8' => 32,
    ];

    private string $homepageTemplate;
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->homepageTemplate = (string) file_get_contents(__DIR__ . '/../../../index.php');
        $this->pdo = getDBConnection();
    }

    /**
     * @return array{width:int,height:int,device:string}
     */
    private function generateRepresentativeViewport(): array
    {
        $mobile = [
            ['width' => 360, 'height' => 640, 'device' => 'mobile'],
            ['width' => 375, 'height' => 812, 'device' => 'mobile'],
            ['width' => 390, 'height' => 844, 'device' => 'mobile'],
            ['width' => 414, 'height' => 896, 'device' => 'mobile'],
        ];

        $desktop = [
            ['width' => 1024, 'height' => 768, 'device' => 'desktop'],
            ['width' => 1280, 'height' => 720, 'device' => 'desktop'],
            ['width' => 1366, 'height' => 768, 'device' => 'desktop'],
            ['width' => 1440, 'height' => 900, 'device' => 'desktop'],
        ];

        // Scoped generation: always include both mobile and desktop landscape of homepage behavior.
        $pool = mt_rand(0, 1) === 0 ? $mobile : $desktop;
        return $pool[array_rand($pool)];
    }

    /**
     * @return array{categoryCount:int}
     */
    private function getExistingHomepageDataSummary(): array
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM categories WHERE is_active=1');
        $categoryCount = (int) $stmt->fetchColumn();

        return [
            'categoryCount' => $categoryCount,
        ];
    }

    private function spacingPxFromToken(string $tokenPrefix, int $fallback, ?string $templateScope = null): int
    {
        $source = $templateScope ?? $this->homepageTemplate;
        $pattern = '/\b' . preg_quote($tokenPrefix, '/') . '([0-9]+(?:\.[0-9]+)?)\b/';
        if (preg_match($pattern, $source, $matches) === 1) {
            $token = $matches[1];
            if (isset(self::SPACING_TO_PX[$token])) {
                return self::SPACING_TO_PX[$token];
            }
        }

        return $fallback;
    }

    private function extractHeroAspectRatioHeightPx(int $fallback): int
    {
        if (preg_match('/aspect-ratio:\s*1200\s*\/\s*([0-9]+(?:\.[0-9]+)?)/', $this->homepageTemplate, $matches) === 1) {
            return (int) round((1200.0 / 1200.0) * (float) $matches[1]);
        }

        return $fallback;
    }

    private function promoBannerEstimatedHeightPx(): int
    {
        if (strpos($this->homepageTemplate, 'Promo Banners Grid') === false) {
            return 0;
        }

        $cardPaddingPx = $this->spacingPxFromToken('p-', 10);
        return 72 + ($cardPaddingPx * 2);
    }

    private function categorySectionTemplateScope(): string
    {
        if (preg_match('/<section id="categories"[\s\S]*?<\/section>/', $this->homepageTemplate, $matches) === 1) {
            return $matches[0];
        }

        return $this->homepageTemplate;
    }

    private function categorySectionEstimatedHeightPx(bool $isDesktop): int
    {
        $categoryScope = $this->categorySectionTemplateScope();

        $titleMbPx = $isDesktop
            ? $this->spacingPxFromToken('md:mb-', 16, $categoryScope)
            : $this->spacingPxFromToken('mb-', 6, $categoryScope);

        $containerPyPx = $isDesktop
            ? (2 * $this->spacingPxFromToken('md:py-', 6, $categoryScope))
            : (2 * $this->spacingPxFromToken('py-', 4, $categoryScope));

        $scrollPbPx = $this->spacingPxFromToken('pb-', 8, $categoryScope);
        $scrollPtPx = $this->spacingPxFromToken('pt-', 4, $categoryScope);

        $desktopTileWidthPx = 125;
        $mobileTileWidthPx = 95;
        $tileHeightPx = $isDesktop ? $desktopTileWidthPx : $mobileTileWidthPx;

        $headingHeightPx = $isDesktop ? 30 : 18;

        return $containerPyPx + $headingHeightPx + $titleMbPx + $scrollPbPx + $scrollPtPx + $tileHeightPx;
    }

    /**
     * @param array{width:int,height:int,device:string} $viewport
     * @param array{categoryCount:int} $homepageData
     * @return array<string,int|string>
     */
    private function measureLayoutMetrics(array $viewport, array $homepageData): array
    {
        $isDesktop = $viewport['width'] >= 768;

        $interComponentGapPx = $isDesktop
            ? $this->spacingPxFromToken('md:space-y-', 0)
            : $this->spacingPxFromToken('space-y-', 8);

        $categoryTileGapPx = $this->spacingPxFromToken('gap-', 0, $this->categorySectionTemplateScope());

        $heroHeightPx = $isDesktop ? $this->extractHeroAspectRatioHeightPx(240) : 0;
        $promoGridHeightPx = $isDesktop ? $this->promoBannerEstimatedHeightPx() : 0;
        $categorySectionHeightPx = $this->categorySectionEstimatedHeightPx($isDesktop);

        $topComponentCount = $isDesktop ? 3 : 1;
        $topSectionHeightPx = $heroHeightPx
            + $promoGridHeightPx
            + $categorySectionHeightPx
            + (($topComponentCount - 1) * $interComponentGapPx);

        $firstProductVisibilityPositionPx = $topSectionHeightPx + ($isDesktop ? 12 : 8);

        $initialFeaturedCategoryItems = $isDesktop
            ? min($homepageData['categoryCount'], 8)
            : min($homepageData['categoryCount'], 4);

        return [
            'viewportWidth' => $viewport['width'],
            'viewportHeight' => $viewport['height'],
            'device' => $viewport['device'],
            'topSectionHeightPx' => (int) $topSectionHeightPx,
            'interComponentGapPx' => (int) $interComponentGapPx,
            'initialFeaturedCategoryItems' => (int) $initialFeaturedCategoryItems,
            'categoryTileGapPx' => (int) $categoryTileGapPx,
            'firstProductVisibilityPositionPx' => (int) $firstProductVisibilityPositionPx,
            // Compact behavior targets (expected behavior)
            'maxCompactTopSectionHeightPx' => $isDesktop ? 520 : 190,
            'maxCompactInterComponentGapPx' => 8,
            'maxInitialFeaturedCategoryItems' => $isDesktop ? 8 : 4,
            'maxCompactCategoryTileGapPx' => 0,
        ];
    }

    /**
     * Bug condition predicate from the spec.
     *
     * @param array<string,int|string> $layoutMetrics
     */
    private function isBugCondition(array $layoutMetrics): bool
    {
        return
            ((int) $layoutMetrics['topSectionHeightPx']) > ((int) $layoutMetrics['maxCompactTopSectionHeightPx'])
            || ((int) $layoutMetrics['interComponentGapPx']) > ((int) $layoutMetrics['maxCompactInterComponentGapPx'])
            || ((int) $layoutMetrics['initialFeaturedCategoryItems']) > ((int) $layoutMetrics['maxInitialFeaturedCategoryItems'])
            || ((int) $layoutMetrics['categoryTileGapPx']) > ((int) $layoutMetrics['maxCompactCategoryTileGapPx']);
    }

    /**
     * Property 1: compact behavior should hold across representative mobile and desktop viewports.
     * On unfixed code, this is expected to fail and provide a concrete counterexample.
     *
     * @test
     */
    public function homepageTopSectionCompactionExplorationProperty(): void
    {
        $homepageData = $this->getExistingHomepageDataSummary();
        $firstCounterexample = null;

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $viewport = $this->generateRepresentativeViewport();
            $metrics = $this->measureLayoutMetrics($viewport, $homepageData);

            if ($this->isBugCondition($metrics)) {
                $firstCounterexample = [
                    'viewport' => [
                        'width' => $metrics['viewportWidth'],
                        'height' => $metrics['viewportHeight'],
                        'device' => $metrics['device'],
                    ],
                    'measured_top_height_px' => $metrics['topSectionHeightPx'],
                    'measured_inter_component_gap_px' => $metrics['interComponentGapPx'],
                    'measured_category_tile_gap_px' => $metrics['categoryTileGapPx'],
                    'initial_featured_category_items' => $metrics['initialFeaturedCategoryItems'],
                    'first_product_visibility_position_px' => $metrics['firstProductVisibilityPositionPx'],
                    'compact_targets' => [
                        'max_top_height_px' => $metrics['maxCompactTopSectionHeightPx'],
                        'max_gap_px' => $metrics['maxCompactInterComponentGapPx'],
                        'max_initial_category_items' => $metrics['maxInitialFeaturedCategoryItems'],
                        'max_category_tile_gap_px' => $metrics['maxCompactCategoryTileGapPx'],
                    ],
                ];
                break;
            }
        }

        $this->assertNull(
            $firstCounterexample,
            "Bug condition detected (expected on unfixed code):\n" . json_encode($firstCounterexample, JSON_PRETTY_PRINT)
        );
    }
}
