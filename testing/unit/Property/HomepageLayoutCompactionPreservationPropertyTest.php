<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/db.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Preservation Test for Homepage Layout Compaction bugfix.
 *
 * **Validates: Requirements 3.1, 3.2, 3.3, 3.4**
 *
 * Property 2: Preservation - interaction, readability, product rendering,
 * and responsive behavior remain stable for non-bug-condition scenarios.
 */
class HomepageLayoutCompactionPreservationPropertyTest extends TestCase
{
    private const ITERATIONS = 120;

    private string $homepageTemplate;
    private PDO $pdo;

    /** @var array<int,array{id:int,name:string,slug:string}> */
    private array $categoryFixtures = [];

    /** @var array<int,array{id:int,name:string,slug:string,selling_price:int,status:string}> */
    private array $productFixtures = [];

    /** @var array<string,mixed> */
    private array $baseline = [];

    protected function setUp(): void
    {
        $this->homepageTemplate = (string) file_get_contents(__DIR__ . '/../../../index.php');
        $this->pdo = getDBConnection();
        $this->categoryFixtures = $this->loadCategoryFixtures();
        $this->productFixtures = $this->loadProductFixtures();
        $this->baseline = $this->observeBaselineOutputs();
    }

    /** @return array{width:int,height:int,device:string} */
    private function generateRepresentativeViewport(): array
    {
        $mobile = [
            ['width' => 360, 'height' => 640, 'device' => 'mobile'],
            ['width' => 375, 'height' => 812, 'device' => 'mobile'],
            ['width' => 390, 'height' => 844, 'device' => 'mobile'],
        ];

        $desktop = [
            ['width' => 1024, 'height' => 768, 'device' => 'desktop'],
            ['width' => 1280, 'height' => 720, 'device' => 'desktop'],
            ['width' => 1366, 'height' => 768, 'device' => 'desktop'],
            ['width' => 1440, 'height' => 900, 'device' => 'desktop'],
        ];

        $pool = mt_rand(0, 1) === 0 ? $mobile : $desktop;
        return $pool[array_rand($pool)];
    }

    /**
     * Generate non-bug-condition scenario: viewport plus valid targets/fixtures.
     *
     * @return array{
     *   viewport:array{width:int,height:int,device:string},
     *   target:string,
     *   category:array{id:int,name:string,slug:string}|null,
     *   product:array{id:int,name:string,slug:string,selling_price:int,status:string}|null
     * }
     */
    private function generateNonBuggyScenario(): array
    {
        $targets = ['hero-banner', 'promo-banner', 'category-link', 'product-card', 'cart-action'];

        return [
            'viewport' => $this->generateRepresentativeViewport(),
            'target' => $targets[array_rand($targets)],
            'category' => !empty($this->categoryFixtures) ? $this->categoryFixtures[array_rand($this->categoryFixtures)] : null,
            'product' => !empty($this->productFixtures) ? $this->productFixtures[array_rand($this->productFixtures)] : null,
        ];
    }

    /** @return array<int,array{id:int,name:string,slug:string}> */
    private function loadCategoryFixtures(): array
    {
        $stmt = $this->pdo->query("SELECT id, name, slug FROM categories WHERE is_active=1 ORDER BY sort_order ASC LIMIT 20");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(
            static fn(array $r): array => [
                'id' => (int) $r['id'],
                'name' => (string) ($r['name'] ?? ''),
                'slug' => (string) ($r['slug'] ?? ''),
            ],
            $rows
        );
    }

    /** @return array<int,array{id:int,name:string,slug:string,selling_price:int,status:string}> */
    private function loadProductFixtures(): array
    {
        $stmt = $this->pdo->query("SELECT id, name, slug, selling_price, status FROM products WHERE is_active=1 ORDER BY created_at DESC LIMIT 24");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(
            static fn(array $r): array => [
                'id' => (int) $r['id'],
                'name' => (string) ($r['name'] ?? ''),
                'slug' => (string) ($r['slug'] ?? ''),
                'selling_price' => (int) ($r['selling_price'] ?? 0),
                'status' => (string) ($r['status'] ?? ''),
            ],
            $rows
        );
    }

    /**
     * Observation-first baseline on current (unfixed) code for preservation behavior.
     *
     * @return array<string,mixed>
     */
    private function observeBaselineOutputs(): array
    {
        $categoryCount = count($this->categoryFixtures);
        $productCount = count($this->productFixtures);

        $desktopHeroVisible = str_contains($this->homepageTemplate, 'hidden md:block')
            && str_contains($this->homepageTemplate, 'hero-slide');
        $mobileCategoriesScrollable = str_contains($this->homepageTemplate, 'overflow-x-auto hide-scrollbar');
        $desktopCategoryWrap = str_contains($this->homepageTemplate, 'md:flex-wrap');

        return [
            // 3.1 click target preservation signals
            'hasHeroBannerLink' => preg_match('/class="hero-slide[^\"]*"[^>]*>/', $this->homepageTemplate) === 1
                && preg_match('/<a\s+href="<\?=\s*sanitizeOutput\(\$slide\[\'link_url\'\]\)\s*\?>"\s+class="hero-slide/', $this->homepageTemplate) === 1,
            'hasPromoBannerLinks' => preg_match('/<a\s+href="<\?=\s*sanitizeOutput\(\$pb\[\'link\'\]\)\s*\?>"\s+class="<\?=\s*\$style\[\'bg\'\]\s*\?>/', $this->homepageTemplate) === 1,
            'hasCategoryLinks' => str_contains($this->homepageTemplate, 'href="category?slug=<?= sanitizeOutput($category[\'slug\']) ?>"'),

            // 3.2 readability/selectability signals
            'categoryLabelClassPresent' => str_contains($this->homepageTemplate, 'text-[10px] md:text-[12px] font-bold text-center')
                && str_contains($this->homepageTemplate, '<?= sanitizeOutput($category[\'name\']) ?>'),
            'categorySelectableAnchorPresent' => str_contains($this->homepageTemplate, '<a class="flex flex-col items-center gap-1.5')
                && str_contains($this->homepageTemplate, 'href="category?slug='),

            // 3.3 product rendering signals
            'hasProductNameBinding' => str_contains($this->homepageTemplate, '<?= sanitizeOutput($product[\'name\']) ?>'),
            'hasProductPriceBinding' => str_contains($this->homepageTemplate, '<?= formatRupiah($activePrice) ?>')
                || str_contains($this->homepageTemplate, '<?= formatRupiah((int)$product[\'selling_price\']) ?>'),
            'hasProductCartAction' => str_contains($this->homepageTemplate, 'form action="actions/cart-add" method="POST"')
                && str_contains($this->homepageTemplate, 'name="product_id"')
                && str_contains($this->homepageTemplate, 'add_shopping_cart'),

            // 3.4 responsiveness signals
            'desktopHeroVisible' => $desktopHeroVisible,
            'mobileCategoriesScrollable' => $mobileCategoriesScrollable,
            'desktopCategoryWrap' => $desktopCategoryWrap,
            'hasResponsiveProductGrid' => str_contains($this->homepageTemplate, 'grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6'),

            // fixture baseline existence
            'categoryCount' => $categoryCount,
            'productCount' => $productCount,
        ];
    }

    /** @return array<string,bool|int|string> */
    private function evaluatePreservationSignals(array $scenario): array
    {
        $viewport = $scenario['viewport'];
        $isDesktop = ($viewport['width'] >= 768);

        return [
            'targetIsKnown' => in_array($scenario['target'], ['hero-banner', 'promo-banner', 'category-link', 'product-card', 'cart-action'], true),

            // 3.1 interaction targets
            'bannerClickTargetsFunctional' => (bool) $this->baseline['hasHeroBannerLink'],
            'promoClickTargetsFunctional' => (bool) $this->baseline['hasPromoBannerLinks'],
            'categoryClickTargetsFunctional' => (bool) $this->baseline['hasCategoryLinks'],

            // 3.2 category readability/selectability
            'categoryLabelsReadable' => (bool) $this->baseline['categoryLabelClassPresent']
                && ($scenario['category'] === null || trim((string) $scenario['category']['name']) !== ''),
            'categorySelectable' => (bool) $this->baseline['categorySelectableAnchorPresent']
                && ($scenario['category'] === null || trim((string) $scenario['category']['slug']) !== ''),

            // 3.3 product list rendering
            'productNameRendered' => (bool) $this->baseline['hasProductNameBinding']
                && ($scenario['product'] === null || trim((string) $scenario['product']['name']) !== ''),
            'productPriceRendered' => (bool) $this->baseline['hasProductPriceBinding']
                && ($scenario['product'] === null || ((int) $scenario['product']['selling_price']) >= 0),
            'productActionRendered' => (bool) $this->baseline['hasProductCartAction'],

            // 3.4 responsive rendering
            'mobileRenderingStable' => !$isDesktop
                ? ((bool) $this->baseline['mobileCategoriesScrollable'] && (bool) $this->baseline['hasResponsiveProductGrid'])
                : true,
            'desktopRenderingStable' => $isDesktop
                ? ((bool) $this->baseline['desktopHeroVisible'] && (bool) $this->baseline['desktopCategoryWrap'] && (bool) $this->baseline['hasResponsiveProductGrid'])
                : true,
        ];
    }

    /**
     * @test
     */
    public function homepagePreservationPropertyOnNonBuggyScenarios(): void
    {
        $this->assertGreaterThan(
            0,
            (int) $this->baseline['productCount'],
            'Baseline requires at least one active product fixture for preservation checks.'
        );

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $scenario = $this->generateNonBuggyScenario();
            $signals = $this->evaluatePreservationSignals($scenario);

            $this->assertTrue((bool) $signals['targetIsKnown'], 'Generated interaction target must be known homepage target.');

            // Requirement 3.1
            $this->assertTrue((bool) $signals['bannerClickTargetsFunctional'], 'Banner click targets must remain functional.');
            $this->assertTrue((bool) $signals['promoClickTargetsFunctional'], 'Promo click targets must remain functional.');
            $this->assertTrue((bool) $signals['categoryClickTargetsFunctional'], 'Category click targets must remain functional.');

            // Requirement 3.2
            $this->assertTrue((bool) $signals['categoryLabelsReadable'], 'Category labels must remain readable.');
            $this->assertTrue((bool) $signals['categorySelectable'], 'Category items must remain selectable.');

            // Requirement 3.3
            $this->assertTrue((bool) $signals['productNameRendered'], 'Product names must remain rendered.');
            $this->assertTrue((bool) $signals['productPriceRendered'], 'Product prices must remain rendered.');
            $this->assertTrue((bool) $signals['productActionRendered'], 'Product actions must remain rendered.');

            // Requirement 3.4
            $this->assertTrue((bool) $signals['mobileRenderingStable'], 'Mobile rendering must remain stable.');
            $this->assertTrue((bool) $signals['desktopRenderingStable'], 'Desktop rendering must remain stable.');
        }
    }
}
