<?php

namespace App;

use App\Helpers;
use App\Settings;

class Homepage
{
    /**
     * Return the default slider configuration used when no custom settings exist.
     *
     * @return array<string,mixed>
     */
    public static function defaultSliderConfig(): array
    {
        return array(
            'mainSlides' => array(
                array(
                    'badge' => 'Magic Chess Go Go',
                    'title' => 'Lancelot Kılıç Ustası',
                    'subtitle' => 'Elmas satın al ve sezonu domine et.',
                    'cta_text' => 'Hemen Satın Al',
                    'cta_url' => Helpers::categoryUrl('mobile-legends'),
                    'image' => '/theme/assets/images/banners/UBQs76PGwFIW2p4C5BjDIqKIRpNDhFvXaxmskag4.webp',
                    'media_image' => '/theme/assets/images/site/FoOS2ikJSxstFsWSfNWZEdnTQl0ziBY0dmn4QnX3.webp',
                    'footer_logo' => '',
                ),
                array(
                    'badge' => 'Valorant Night Market',
                    'title' => 'En sevdiğin skinleri kap',
                    'subtitle' => 'Haftalık VP paketlerinde %30’a varan indirim.',
                    'cta_text' => 'İndirimi Gör',
                    'cta_url' => Helpers::categoryUrl('valorant'),
                    'image' => '/theme/assets/images/banners/ZxEuccRk6DKtKgYohlCo6KcVxl9KnZYg2lkvxXX4.webp',
                    'media_image' => '/theme/assets/images/site/KYQdPJDHaWihG3n7Sf3sHseGTRMT3xtmVGlDvNOj.webp',
                    'footer_logo' => '',
                ),
                array(
                    'badge' => 'Adobe & Office',
                    'title' => 'Premium üretkenlik paketi',
                    'subtitle' => 'Adobe Creative Cloud + Office 365 tek pakette.',
                    'cta_text' => 'Paketi İncele',
                    'cta_url' => Helpers::categoryUrl('design-tools'),
                    'image' => '/theme/assets/images/banners/wfH6NIajXtDYa9gGwozg7tx6Jut7W0DefDCaPHar.webp',
                    'media_image' => '/theme/assets/images/site/obCqriZHgv5AeK7LzXnQE3DNCm3Vw2wndCflf2mF.webp',
                    'footer_logo' => '',
                ),
            ),
            'sideBanners' => array(
                array(
                    'title' => 'Mobile Legends',
                    'subtitle' => 'Elmaslarını anında yükle.',
                    'cta_text' => 'Elmas Satın Al',
                    'cta_url' => Helpers::categoryUrl('mobile-legends'),
                    'image' => '/theme/assets/images/banners/ZxEuccRk6DKtKgYohlCo6KcVxl9KnZYg2lkvxXX4.webp',
                    'footer_logo' => '',
                ),
                array(
                    'title' => 'Wartune Ultra',
                    'subtitle' => 'Yeni sezon görevleri seni bekliyor.',
                    'cta_text' => 'Elmas Satın Al',
                    'cta_url' => Helpers::categoryUrl('wartune'),
                    'image' => '/theme/assets/images/banners/UBQs76PGwFIW2p4C5BjDIqKIRpNDhFvXaxmskag4.webp',
                    'footer_logo' => '',
                ),
            ),
            'instagram' => array(
                'headline' => 'Bizi Instagram’da takip et',
                'subhead' => 'İndirimi kaçırma, kampanyaları ilk sen keşfet.',
                'handle' => '@dijipincom',
                'handle_url' => 'https://instagram.com/dijipincom',
                'cta_text' => 'Takip Et',
                'cta_url' => 'https://instagram.com/dijipincom',
                'background' => 'linear-gradient(90deg, #fd3a84 0%, #ffa751 100%)',
            ),
            'categories' => array(
                array(
                    'title' => 'PUBG Mobile',
                    'subtitle' => 'UC paketleri',
                    'image' => '/theme/assets/images/site/843JlXv47N4zBwrqTjLgP9B0kXgNSl3O14oHtESl.webp',
                    'link' => Helpers::categoryUrl('pubg'),
                    'footer_logo' => '',
                ),
                array(
                    'title' => 'Mobile Legends',
                    'subtitle' => 'Elmas & kupon',
                    'image' => '/theme/assets/images/site/KYQdPJDHaWihG3n7Sf3sHseGTRMT3xtmVGlDvNOj.webp',
                    'link' => Helpers::categoryUrl('mobile-legends'),
                    'footer_logo' => '',
                ),
                array(
                    'title' => 'Valorant',
                    'subtitle' => 'VP & kodlar',
                    'image' => '/theme/assets/images/site/FoOS2ikJSxstFsWSfNWZEdnTQl0ziBY0dmn4QnX3.webp',
                    'link' => Helpers::categoryUrl('valorant'),
                    'footer_logo' => '',
                ),
                array(
                    'title' => 'Wartune',
                    'subtitle' => 'Ultra paket',
                    'image' => '/theme/assets/images/site/obCqriZHgv5AeK7LzXnQE3DNCm3Vw2wndCflf2mF.webp',
                    'link' => Helpers::categoryUrl('wartune'),
                    'footer_logo' => '/theme/assets/images/site/logo-light.svg',
                ),
                array(
                    'title' => 'Age of Empires',
                    'subtitle' => 'Mobile gold',
                    'image' => '/theme/assets/images/site/KreWJFMqBI43i90m6TEODfrRY6BJoEjftSp84I5B.webp',
                    'link' => Helpers::categoryUrl('age-of-empires'),
                    'footer_logo' => '/theme/assets/images/site/logo-light.svg',
                ),
            ),
        );
    }

    /**
     * Load slider configuration, falling back to defaults where necessary.
     *
     * @return array<string,mixed>
     */
    public static function loadSliderConfig(): array
    {
        $defaults = self::defaultSliderConfig();
        $raw = Settings::get('homepage_slider_config');

        if ($raw === null || trim($raw) === '') {
            return $defaults;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $defaults;
        }

        $config = $defaults;

        if (!empty($decoded['mainSlides']) && is_array($decoded['mainSlides'])) {
            $config['mainSlides'] = self::normaliseSlideCollection($decoded['mainSlides'], true);
        }

        if (!empty($decoded['sideBanners']) && is_array($decoded['sideBanners'])) {
            $config['sideBanners'] = self::normaliseSlideCollection($decoded['sideBanners'], false);
        }

        if (!empty($decoded['instagram']) && is_array($decoded['instagram'])) {
            $instagram = self::normaliseInstagram($decoded['instagram']);
            if ($instagram) {
                $config['instagram'] = $instagram;
            }
        }

        if (!empty($decoded['categories']) && is_array($decoded['categories'])) {
            $categories = self::normaliseCategories($decoded['categories']);
            if ($categories) {
                $config['categories'] = $categories;
            }
        }

        return $config;
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @param bool $includeMedia
     * @return array<int,array<string,string>>
     */
    private static function normaliseSlideCollection(array $items, bool $includeMedia): array
    {
        $slides = array();

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $title = isset($item['title']) ? trim((string)$item['title']) : '';
            $subtitle = isset($item['subtitle']) ? trim((string)$item['subtitle']) : '';
            $image = isset($item['image']) ? trim((string)$item['image']) : '';

            if ($title === '' && $image === '') {
                continue;
            }

            $slide = array(
                'title' => $title,
                'subtitle' => $subtitle,
                'badge' => isset($item['badge']) ? trim((string)$item['badge']) : '',
                'cta_text' => isset($item['cta_text']) ? trim((string)$item['cta_text']) : '',
                'cta_url' => isset($item['cta_url']) ? trim((string)$item['cta_url']) : '',
                'image' => $image,
                'footer_logo' => isset($item['footer_logo']) ? trim((string)$item['footer_logo']) : '',
            );

            if ($includeMedia) {
                $slide['media_image'] = isset($item['media_image']) ? trim((string)$item['media_image']) : '';
            }

            $slides[] = $slide;
        }

        return $slides ?: array();
    }

    /**
     * @param array<string,mixed> $instagram
     * @return array<string,string>
     */
    private static function normaliseInstagram(array $instagram): array
    {
        return array(
            'headline' => isset($instagram['headline']) ? trim((string)$instagram['headline']) : '',
            'subhead' => isset($instagram['subhead']) ? trim((string)$instagram['subhead']) : '',
            'handle' => isset($instagram['handle']) ? trim((string)$instagram['handle']) : '',
            'handle_url' => isset($instagram['handle_url']) ? trim((string)$instagram['handle_url']) : '',
            'cta_text' => isset($instagram['cta_text']) ? trim((string)$instagram['cta_text']) : '',
            'cta_url' => isset($instagram['cta_url']) ? trim((string)$instagram['cta_url']) : '',
            'background' => isset($instagram['background']) ? trim((string)$instagram['background']) : '',
        );
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array<string,string>>
     */
    private static function normaliseCategories(array $items): array
    {
        $categories = array();

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $title = isset($item['title']) ? trim((string)$item['title']) : '';
            $image = isset($item['image']) ? trim((string)$item['image']) : '';
            if ($title === '' && $image === '') {
                continue;
            }

            $categories[] = array(
                'title' => $title,
                'subtitle' => isset($item['subtitle']) ? trim((string)$item['subtitle']) : '',
                'image' => $image,
                'link' => isset($item['link']) ? trim((string)$item['link']) : '',
                'footer_logo' => isset($item['footer_logo']) ? trim((string)$item['footer_logo']) : '',
            );
        }

        return $categories ?: array();
    }
}
