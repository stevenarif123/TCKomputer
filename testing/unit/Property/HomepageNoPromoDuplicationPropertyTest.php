<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for homepage promo shortcut duplication.
 *
 * **Validates: Requirements 2.3, 2.4, 3.1**
 *
 * Property 3: No Promo Duplication
 * For generated promo banner settings and render states, each configured
 * promo shortcut appears at most once in the homepage top area.
 */
class HomepageNoPromoDuplicationPropertyTest extends TestCase
{
    private const ITERATIONS = 300;

    /** @test */
    public function configuredPromoShortcutsAppearAtMostOnceInHomepageTopArea(): void
    {
        $homepageTemplate = (string) file_get_contents(__DIR__ . '/../../../index.php');

        $this->assertSame(
            1,
            substr_count($homepageTemplate, 'foreach ($promoBanners as $pb)'),
            'Homepage top area must render the configured promo shortcut collection in exactly one place.'
        );

        for ($iteration = 0; $iteration < self::ITERATIONS; $iteration++) {
            $settings = $this->generatePromoBannerSettings($iteration);
            $renderStates = $this->generateRenderStates();
            $shortcuts = extractHomepagePromoShortcuts($settings, $renderStates['limit']);
            $topAreaHtml = $this->renderHomepageTopArea($shortcuts, $renderStates['includeHero']);

            foreach ($this->configuredPromoFingerprints($settings, $renderStates['limit']) as $fingerprint => $source) {
                $count = substr_count($topAreaHtml, 'data-promo-fingerprint="' . sanitizeOutput($fingerprint) . '"');

                $this->assertLessThanOrEqual(
                    1,
                    $count,
                    sprintf(
                        'Promo shortcut %s appeared %d times in homepage top area for settings: %s and render state: %s',
                        json_encode($source),
                        $count,
                        json_encode($settings),
                        json_encode($renderStates)
                    )
                );
            }
        }
    }

    /** @return array<string,string> */
    private function generatePromoBannerSettings(int $iteration): array
    {
        $settings = [];
        $previous = null;

        for ($slot = 1; $slot <= 3; $slot++) {
            $state = mt_rand(0, 4);

            if ($state === 0) {
                $settings["promo_banner_{$slot}_title"] = str_repeat(' ', mt_rand(0, 4));
                $settings["promo_banner_{$slot}_desc"] = "Ignored Desc {$iteration}-{$slot}";
                $settings["promo_banner_{$slot}_link"] = "/ignored-{$iteration}-{$slot}";
                $settings["promo_banner_{$slot}_icon"] = 'hide_source';
                continue;
            }

            if ($state === 1 && $previous !== null) {
                $promo = $previous;
            } else {
                $promo = [
                    'title' => "Promo Source {$iteration}-{$slot}-" . mt_rand(1, 9999),
                    'desc' => mt_rand(0, 1) === 1 ? "Desc Source {$iteration}-{$slot}" : '',
                    'link' => mt_rand(0, 1) === 1 ? "/products?promo={$iteration}-{$slot}" : '',
                    'icon' => mt_rand(0, 1) === 1 ? 'campaign' : '',
                ];
                $previous = $promo;
            }

            $settings["promo_banner_{$slot}_title"] = $this->pad($promo['title']);
            $settings["promo_banner_{$slot}_desc"] = $this->pad($promo['desc']);
            $settings["promo_banner_{$slot}_link"] = $this->pad($promo['link']);
            $settings["promo_banner_{$slot}_icon"] = $this->pad($promo['icon']);
        }

        return $settings;
    }

    /** @return array{limit:int,includeHero:bool} */
    private function generateRenderStates(): array
    {
        return [
            'limit' => mt_rand(0, 3),
            'includeHero' => mt_rand(0, 1) === 1,
        ];
    }

    /** @param array<int,array{title:string,desc:string,link:string,icon:string,index:int}> $shortcuts */
    private function renderHomepageTopArea(array $shortcuts, bool $includeHero): string
    {
        $html = '<section data-homepage-top-area="1">';
        $html .= $includeHero ? '<div class="hero-slides"></div>' : '';
        $html .= '<div class="promo-shortcut-grid">';

        foreach ($shortcuts as $shortcut) {
            $fingerprint = $this->fingerprint($shortcut['title'], $shortcut['desc'], $shortcut['link']);
            $html .= '<a data-promo-fingerprint="' . sanitizeOutput($fingerprint) . '" href="' . sanitizeOutput($shortcut['link'] !== '' ? $shortcut['link'] : '#') . '">';
            $html .= '<span>' . sanitizeOutput($shortcut['title']) . '</span>';
            $html .= '</a>';
        }

        $html .= '</div></section>';

        return $html;
    }

    /** @return array<string,array{slot:int,title:string,desc:string,link:string}> */
    private function configuredPromoFingerprints(array $settings, int $limit): array
    {
        $configured = [];

        for ($slot = 1; $slot <= max(0, $limit); $slot++) {
            $title = trim((string) ($settings["promo_banner_{$slot}_title"] ?? ''));
            if ($title === '') {
                continue;
            }

            $desc = trim((string) ($settings["promo_banner_{$slot}_desc"] ?? ''));
            $link = trim((string) ($settings["promo_banner_{$slot}_link"] ?? ''));
            $configured[$this->fingerprint($title, $desc, $link)] = [
                'slot' => $slot,
                'title' => $title,
                'desc' => $desc,
                'link' => $link,
            ];
        }

        return $configured;
    }

    private function fingerprint(string $title, string $desc, string $link): string
    {
        return strtolower(trim($title) . "\n" . trim($desc) . "\n" . trim($link));
    }

    private function pad(string $value): string
    {
        return str_repeat(' ', mt_rand(0, 2)) . $value . str_repeat(' ', mt_rand(0, 2));
    }
}
