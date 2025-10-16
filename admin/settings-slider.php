<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\Helpers;
use App\Homepage;
use App\Settings;

Auth::requireRoles(array('super_admin', 'admin'));

$errors = array();
$success = '';
$currentUser = isset($_SESSION['user']) ? $_SESSION['user'] : null;

$sliderConfig = Homepage::loadSliderConfig();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'Oturum doğrulanamadı. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $mainSlidesInput = isset($_POST['main_slides']) && is_array($_POST['main_slides']) ? $_POST['main_slides'] : array();
        $sideBannersInput = isset($_POST['side_banners']) && is_array($_POST['side_banners']) ? $_POST['side_banners'] : array();
        $categoriesInput = isset($_POST['categories']) && is_array($_POST['categories']) ? $_POST['categories'] : array();
        $instagramInput = isset($_POST['instagram']) && is_array($_POST['instagram']) ? $_POST['instagram'] : array();

        $cleanMainSlides = array();
        foreach ($mainSlidesInput as $item) {
            if (!is_array($item)) {
                continue;
            }
            $title = isset($item['title']) ? trim((string)$item['title']) : '';
            $image = isset($item['image']) ? trim((string)$item['image']) : '';
            if ($title === '' && $image === '') {
                continue;
            }

            $cleanMainSlides[] = array(
                'badge' => isset($item['badge']) ? trim((string)$item['badge']) : '',
                'title' => $title,
                'subtitle' => isset($item['subtitle']) ? trim((string)$item['subtitle']) : '',
                'cta_text' => isset($item['cta_text']) ? trim((string)$item['cta_text']) : '',
                'cta_url' => isset($item['cta_url']) ? trim((string)$item['cta_url']) : '',
                'image' => $image,
                'media_image' => isset($item['media_image']) ? trim((string)$item['media_image']) : '',
                'footer_logo' => isset($item['footer_logo']) ? trim((string)$item['footer_logo']) : '',
            );
        }

        $cleanSideBanners = array();
        foreach ($sideBannersInput as $item) {
            if (!is_array($item)) {
                continue;
            }
            $title = isset($item['title']) ? trim((string)$item['title']) : '';
            $image = isset($item['image']) ? trim((string)$item['image']) : '';
            if ($title === '' && $image === '') {
                continue;
            }

            $cleanSideBanners[] = array(
                'title' => $title,
                'subtitle' => isset($item['subtitle']) ? trim((string)$item['subtitle']) : '',
                'cta_text' => isset($item['cta_text']) ? trim((string)$item['cta_text']) : '',
                'cta_url' => isset($item['cta_url']) ? trim((string)$item['cta_url']) : '',
                'image' => $image,
                'footer_logo' => isset($item['footer_logo']) ? trim((string)$item['footer_logo']) : '',
            );
        }

        $cleanCategories = array();
        foreach ($categoriesInput as $item) {
            if (!is_array($item)) {
                continue;
            }
            $title = isset($item['title']) ? trim((string)$item['title']) : '';
            $image = isset($item['image']) ? trim((string)$item['image']) : '';
            if ($title === '' && $image === '') {
                continue;
            }

            $cleanCategories[] = array(
                'title' => $title,
                'subtitle' => isset($item['subtitle']) ? trim((string)$item['subtitle']) : '',
                'image' => $image,
                'link' => isset($item['link']) ? trim((string)$item['link']) : '',
                'footer_logo' => isset($item['footer_logo']) ? trim((string)$item['footer_logo']) : '',
            );
        }

        $instagramBlock = array(
            'headline' => isset($instagramInput['headline']) ? trim((string)$instagramInput['headline']) : '',
            'subhead' => isset($instagramInput['subhead']) ? trim((string)$instagramInput['subhead']) : '',
            'handle' => isset($instagramInput['handle']) ? trim((string)$instagramInput['handle']) : '',
            'handle_url' => isset($instagramInput['handle_url']) ? trim((string)$instagramInput['handle_url']) : '',
            'cta_text' => isset($instagramInput['cta_text']) ? trim((string)$instagramInput['cta_text']) : '',
            'cta_url' => isset($instagramInput['cta_url']) ? trim((string)$instagramInput['cta_url']) : '',
            'background' => isset($instagramInput['background']) ? trim((string)$instagramInput['background']) : '',
        );

        if (!$cleanMainSlides) {
            $errors[] = 'En az bir ana slider kartı eklemelisiniz.';
        }

        if (!$errors) {
            $payload = array(
                'mainSlides' => array_values($cleanMainSlides),
                'sideBanners' => array_values($cleanSideBanners),
                'instagram' => $instagramBlock,
                'categories' => array_values($cleanCategories),
            );

            Settings::set('homepage_slider_config', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $sliderConfig = Homepage::loadSliderConfig();

            if ($currentUser) {
                AuditLog::record(
                    $currentUser['id'],
                    'settings.slider.update',
                    'settings',
                    null,
                    'Homepage slider settings updated'
                );
            }

            $success = 'Slider ayarları kaydedildi.';
        }
    }
}

Helpers::setPageTitle('Slider Sistemi');

include __DIR__ . '/templates/header.php';
?>
<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Slider Sistemi</h5>
                <small class="text-muted d-block">Anasayfadaki ana slider, Instagram banner ve kategori kartlarını buradan yönetin.</small>
            </div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= Helpers::sanitize($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?= Helpers::sanitize($success) ?>
                    </div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">

                    <div class="mb-5">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="mb-1">Ana Slider Kartları</h6>
                                <small class="text-muted">Soldaki büyük slider alanı için kartlar.</small>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" data-repeatable-add="main-slides" data-template="#template-main-slide">
                                <i class="bi bi-plus"></i> Kart Ekle
                            </button>
                        </div>

                        <div class="repeatable-stack" data-repeatable="main-slides">
                            <?php foreach ($sliderConfig['mainSlides'] as $index => $slide): ?>
                                <div class="repeatable-item card border-0 shadow-sm mb-3" data-repeatable-item>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <strong>Slide #<?= (int)$index + 1 ?></strong>
                                            <button type="button" class="btn btn-link text-danger p-0" data-repeatable-remove>
                                                <i class="bi bi-trash me-1"></i>Kaldır
                                            </button>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label">Üst Rozet (Badge)</label>
                                                <input type="text" class="form-control" name="main_slides[<?= (int)$index ?>][badge]" value="<?= Helpers::sanitize($slide['badge']) ?>">
                                            </div>
                                            <div class="col-md-8">
                                                <label class="form-label">Başlık</label>
                                                <input type="text" class="form-control" name="main_slides[<?= (int)$index ?>][title]" value="<?= Helpers::sanitize($slide['title']) ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Alt Başlık</label>
                                                <input type="text" class="form-control" name="main_slides[<?= (int)$index ?>][subtitle]" value="<?= Helpers::sanitize($slide['subtitle']) ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">CTA Metni</label>
                                                <input type="text" class="form-control" name="main_slides[<?= (int)$index ?>][cta_text]" value="<?= Helpers::sanitize($slide['cta_text']) ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">CTA Linki</label>
                                                <input type="text" class="form-control" name="main_slides[<?= (int)$index ?>][cta_url]" value="<?= Helpers::sanitize($slide['cta_url']) ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Arka Plan Görseli URL</label>
                                                <input type="text" class="form-control" name="main_slides[<?= (int)$index ?>][image]" value="<?= Helpers::sanitize($slide['image']) ?>" placeholder="https:// veya /theme/...">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Ön Plan Karakter Görseli</label>
                                                <input type="text" class="form-control" name="main_slides[<?= (int)$index ?>][media_image]" value="<?= Helpers::sanitize($slide['media_image']) ?>" placeholder="Opsiyonel">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Alt Logo URL</label>
                                                <input type="text" class="form-control" name="main_slides[<?= (int)$index ?>][footer_logo]" value="<?= Helpers::sanitize($slide['footer_logo']) ?>" placeholder="Opsiyonel">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-5">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="mb-1">Sağ Banner Alanı</h6>
                                <small class="text-muted">Ana slider’ın sağındaki dikey banner kartları.</small>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" data-repeatable-add="side-banners" data-template="#template-side-banner">
                                <i class="bi bi-plus"></i> Banner Ekle
                            </button>
                        </div>

                        <div class="repeatable-stack" data-repeatable="side-banners">
                            <?php foreach ($sliderConfig['sideBanners'] as $index => $banner): ?>
                                <div class="repeatable-item card border-0 shadow-sm mb-3" data-repeatable-item>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <strong>Banner #<?= (int)$index + 1 ?></strong>
                                            <button type="button" class="btn btn-link text-danger p-0" data-repeatable-remove>
                                                <i class="bi bi-trash me-1"></i>Kaldır
                                            </button>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Başlık</label>
                                                <input type="text" class="form-control" name="side_banners[<?= (int)$index ?>][title]" value="<?= Helpers::sanitize($banner['title']) ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Alt Başlık</label>
                                                <input type="text" class="form-control" name="side_banners[<?= (int)$index ?>][subtitle]" value="<?= Helpers::sanitize($banner['subtitle']) ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">CTA Metni</label>
                                                <input type="text" class="form-control" name="side_banners[<?= (int)$index ?>][cta_text]" value="<?= Helpers::sanitize($banner['cta_text']) ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">CTA Linki</label>
                                                <input type="text" class="form-control" name="side_banners[<?= (int)$index ?>][cta_url]" value="<?= Helpers::sanitize($banner['cta_url']) ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Alt Logo URL</label>
                                                <input type="text" class="form-control" name="side_banners[<?= (int)$index ?>][footer_logo]" value="<?= Helpers::sanitize($banner['footer_logo']) ?>" placeholder="Opsiyonel">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Görsel URL</label>
                                                <input type="text" class="form-control" name="side_banners[<?= (int)$index ?>][image]" value="<?= Helpers::sanitize($banner['image']) ?>" placeholder="https:// veya /theme/...">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-5">
                        <h6 class="mb-3">Instagram Banner</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Başlık</label>
                                <input type="text" class="form-control" name="instagram[headline]" value="<?= Helpers::sanitize($sliderConfig['instagram']['headline']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Alt Başlık</label>
                                <input type="text" class="form-control" name="instagram[subhead]" value="<?= Helpers::sanitize($sliderConfig['instagram']['subhead']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Arka Plan (gradient renk)</label>
                                <input type="text" class="form-control" name="instagram[background]" value="<?= Helpers::sanitize($sliderConfig['instagram']['background']) ?>" placeholder="linear-gradient(...)">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Instagram Kullanıcı Adı</label>
                                <input type="text" class="form-control" name="instagram[handle]" value="<?= Helpers::sanitize($sliderConfig['instagram']['handle']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Instagram Linki</label>
                                <input type="text" class="form-control" name="instagram[handle_url]" value="<?= Helpers::sanitize($sliderConfig['instagram']['handle_url']) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">CTA Metni</label>
                                <input type="text" class="form-control" name="instagram[cta_text]" value="<?= Helpers::sanitize($sliderConfig['instagram']['cta_text']) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">CTA Linki</label>
                                <input type="text" class="form-control" name="instagram[cta_url]" value="<?= Helpers::sanitize($sliderConfig['instagram']['cta_url']) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mb-5">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="mb-1">Alt Kategori Kartları</h6>
                                <small class="text-muted">Instagram banner altında görünen oyun/ürün kartları.</small>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" data-repeatable-add="categories" data-template="#template-category-card">
                                <i class="bi bi-plus"></i> Kart Ekle
                            </button>
                        </div>

                        <div class="repeatable-stack" data-repeatable="categories">
                            <?php foreach ($sliderConfig['categories'] as $index => $category): ?>
                                <div class="repeatable-item card border-0 shadow-sm mb-3" data-repeatable-item>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <strong>Kategori Kartı #<?= (int)$index + 1 ?></strong>
                                            <button type="button" class="btn btn-link text-danger p-0" data-repeatable-remove>
                                                <i class="bi bi-trash me-1"></i>Kaldır
                                            </button>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label">Başlık</label>
                                                <input type="text" class="form-control" name="categories[<?= (int)$index ?>][title]" value="<?= Helpers::sanitize($category['title']) ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Alt Başlık</label>
                                                <input type="text" class="form-control" name="categories[<?= (int)$index ?>][subtitle]" value="<?= Helpers::sanitize($category['subtitle']) ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Link</label>
                                                <input type="text" class="form-control" name="categories[<?= (int)$index ?>][link]" value="<?= Helpers::sanitize($category['link']) ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Görsel URL</label>
                                                <input type="text" class="form-control" name="categories[<?= (int)$index ?>][image]" value="<?= Helpers::sanitize($category['image']) ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Alt Logo</label>
                                                <input type="text" class="form-control" name="categories[<?= (int)$index ?>][footer_logo]" value="<?= Helpers::sanitize($category['footer_logo']) ?>" placeholder="Opsiyonel">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">Ayarları Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<template id="template-main-slide">
    <div class="repeatable-item card border-0 shadow-sm mb-3" data-repeatable-item>
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <strong>Yeni Slide</strong>
                <button type="button" class="btn btn-link text-danger p-0" data-repeatable-remove>
                    <i class="bi bi-trash me-1"></i>Kaldır
                </button>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Üst Rozet (Badge)</label>
                    <input type="text" class="form-control" name="main_slides[__INDEX__][badge]">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Başlık</label>
                    <input type="text" class="form-control" name="main_slides[__INDEX__][title]" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Alt Başlık</label>
                    <input type="text" class="form-control" name="main_slides[__INDEX__][subtitle]">
                </div>
                <div class="col-md-3">
                    <label class="form-label">CTA Metni</label>
                    <input type="text" class="form-control" name="main_slides[__INDEX__][cta_text]">
                </div>
                <div class="col-md-3">
                    <label class="form-label">CTA Linki</label>
                    <input type="text" class="form-control" name="main_slides[__INDEX__][cta_url]">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Arka Plan Görseli URL</label>
                    <input type="text" class="form-control" name="main_slides[__INDEX__][image]" placeholder="https:// veya /theme/...">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ön Plan Karakter Görseli</label>
                    <input type="text" class="form-control" name="main_slides[__INDEX__][media_image]" placeholder="Opsiyonel">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Alt Logo URL</label>
                    <input type="text" class="form-control" name="main_slides[__INDEX__][footer_logo]" placeholder="Opsiyonel">
                </div>
            </div>
        </div>
    </div>
</template>

<template id="template-side-banner">
    <div class="repeatable-item card border-0 shadow-sm mb-3" data-repeatable-item>
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <strong>Yeni Banner</strong>
                <button type="button" class="btn btn-link text-danger p-0" data-repeatable-remove>
                    <i class="bi bi-trash me-1"></i>Kaldır
                </button>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Başlık</label>
                    <input type="text" class="form-control" name="side_banners[__INDEX__][title]">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Alt Başlık</label>
                    <input type="text" class="form-control" name="side_banners[__INDEX__][subtitle]">
                </div>
                <div class="col-md-4">
                    <label class="form-label">CTA Metni</label>
                    <input type="text" class="form-control" name="side_banners[__INDEX__][cta_text]">
                </div>
                <div class="col-md-4">
                    <label class="form-label">CTA Linki</label>
                    <input type="text" class="form-control" name="side_banners[__INDEX__][cta_url]">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Alt Logo URL</label>
                    <input type="text" class="form-control" name="side_banners[__INDEX__][footer_logo]" placeholder="Opsiyonel">
                </div>
                <div class="col-12">
                    <label class="form-label">Görsel URL</label>
                    <input type="text" class="form-control" name="side_banners[__INDEX__][image]" placeholder="https:// veya /theme/...">
                </div>
            </div>
        </div>
    </div>
</template>

<template id="template-category-card">
    <div class="repeatable-item card border-0 shadow-sm mb-3" data-repeatable-item>
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <strong>Yeni Kategori Kartı</strong>
                <button type="button" class="btn btn-link text-danger p-0" data-repeatable-remove>
                    <i class="bi bi-trash me-1"></i>Kaldır
                </button>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Başlık</label>
                    <input type="text" class="form-control" name="categories[__INDEX__][title]">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Alt Başlık</label>
                    <input type="text" class="form-control" name="categories[__INDEX__][subtitle]">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Link</label>
                    <input type="text" class="form-control" name="categories[__INDEX__][link]" placeholder="/kategori/...">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Görsel URL</label>
                    <input type="text" class="form-control" name="categories[__INDEX__][image]">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Alt Logo URL</label>
                    <input type="text" class="form-control" name="categories[__INDEX__][footer_logo]" placeholder="Opsiyonel">
                </div>
            </div>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const initRepeatable = function (name) {
        const container = document.querySelector('[data-repeatable="' + name + '"]');
        const addButton = document.querySelector('[data-repeatable-add="' + name + '"]');
        if (!container || !addButton) {
            return;
        }

        const templateSelector = addButton.getAttribute('data-template');
        const template = templateSelector ? document.querySelector(templateSelector) : null;
        if (!template) {
            return;
        }

        let nextIndex = container.querySelectorAll('[data-repeatable-item]').length;

        addButton.addEventListener('click', function () {
            const html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
            const wrapper = document.createElement('div');
            wrapper.innerHTML = html.trim();
            const item = wrapper.firstElementChild;
            if (item) {
                container.appendChild(item);
                nextIndex += 1;
            }
        });

        container.addEventListener('click', function (event) {
            const trigger = event.target.closest('[data-repeatable-remove]');
            if (!trigger) {
                return;
            }
            const item = trigger.closest('[data-repeatable-item]');
            if (item) {
                item.remove();
            }
        });
    };

    initRepeatable('main-slides');
    initRepeatable('side-banners');
    initRepeatable('categories');
});
</script>
<?php include __DIR__ . '/templates/footer.php';
