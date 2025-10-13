<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Helpers;
use App\Page;

Auth::requireRoles(array('super_admin', 'admin', 'content'));

$feedback = Helpers::getFlash('pages_feedback', array('errors' => array(), 'success' => ''));
$errors = array();
$success = '';
$csrfToken = Helpers::csrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';
    $token = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $userId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;

        switch ($action) {
            case 'create_page':
                $payload = array(
                    'title' => $_POST['title'] ?? '',
                    'slug' => $_POST['slug'] ?? '',
                    'summary' => $_POST['summary'] ?? '',
                    'content' => $_POST['content'] ?? '',
                    'status' => $_POST['status'] ?? 'draft',
                    'visibility' => $_POST['visibility'] ?? 'public',
                    'meta_title' => $_POST['meta_title'] ?? '',
                    'meta_description' => $_POST['meta_description'] ?? '',
                    'meta_keywords' => $_POST['meta_keywords'] ?? '',
                    'template' => $_POST['template'] ?? 'default',
                    'hero_image' => $_POST['hero_image'] ?? '',
                    'parent_id' => $_POST['parent_id'] ?? null,
                    'published_at' => $_POST['published_at'] ?? null,
                );

                try {
                    $pageId = Page::create($payload, $userId);
                    $success = 'Sayfa başarıyla oluşturuldu.';
                } catch (\Throwable $exception) {
                    $errors[] = 'Sayfa kaydedilirken bir hata oluştu.';
                }
                break;

            case 'update_page':
                $pageId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                $payload = array(
                    'title' => $_POST['title'] ?? '',
                    'slug' => $_POST['slug'] ?? '',
                    'summary' => $_POST['summary'] ?? '',
                    'content' => $_POST['content'] ?? '',
                    'status' => $_POST['status'] ?? 'draft',
                    'visibility' => $_POST['visibility'] ?? 'public',
                    'meta_title' => $_POST['meta_title'] ?? '',
                    'meta_description' => $_POST['meta_description'] ?? '',
                    'meta_keywords' => $_POST['meta_keywords'] ?? '',
                    'template' => $_POST['template'] ?? 'default',
                    'hero_image' => $_POST['hero_image'] ?? '',
                    'parent_id' => $_POST['parent_id'] ?? null,
                    'published_at' => $_POST['published_at'] ?? null,
                );

                if ($pageId <= 0) {
                    $errors[] = 'Düzenlenecek sayfa bulunamadı.';
                } else {
                    try {
                        Page::update($pageId, $payload, $userId);
                        $success = 'Sayfa güncellendi.';
                    } catch (\Throwable $exception) {
                        $errors[] = 'Sayfa güncellenemedi.';
                    }
                }
                break;

            case 'delete_page':
                $pageId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                if ($pageId <= 0) {
                    $errors[] = 'Silinecek sayfa bulunamadı.';
                } else {
                    Page::delete($pageId, $userId);
                    $success = 'Sayfa arşive taşındı.';
                }
                break;

            case 'publish_page':
                $pageId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                $publishAt = isset($_POST['publish_at']) ? (string)$_POST['publish_at'] : null;
                if ($pageId <= 0) {
                    $errors[] = 'Sayfa bulunamadı.';
                } else {
                    Page::setStatus($pageId, 'published', $publishAt, $userId);
                    $success = 'Sayfa yayınlandı.';
                }
                break;

            case 'archive_page':
                $pageId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                if ($pageId <= 0) {
                    $errors[] = 'Sayfa bulunamadı.';
                } else {
                    Page::setStatus($pageId, 'archived', null, $userId);
                    $success = 'Sayfa arşivlendi.';
                }
                break;
        }
    }

    Helpers::setFlash('pages_feedback', array(
        'errors' => $errors,
        'success' => $success,
    ));

    Helpers::redirect('/admin/pages.php');
}

$filters = array();
if (!empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}
if (!empty($_GET['visibility'])) {
    $filters['visibility'] = $_GET['visibility'];
}
if (!empty($_GET['q'])) {
    $filters['query'] = trim((string)$_GET['q']);
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$list = Page::paginate($filters, $perPage, $offset);
$pages = $list['items'];
$totalPages = $list['total'];

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editing = $editId > 0 ? Page::find($editId) : null;
$parentOptions = Page::hierarchyOptions($editing ? (int)$editing['id'] : null);

$pageTitle = 'Sayfa Yönetimi';
include __DIR__ . '/templates/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Sayfa Yönetimi</h1>
        <a href="/sayfa/" target="_blank" class="btn btn-outline-primary btn-sm">Siteyi görüntüle</a>
    </div>

    <?php if (!empty($feedback['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($feedback['success'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if (!empty($feedback['errors'])): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($feedback['errors'] as $message): ?>
                    <li><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <strong><?= $editing ? 'Sayfayı Düzenle' : 'Yeni Sayfa Oluştur' ?></strong>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="<?= $editing ? 'update_page' : 'create_page' ?>">
                        <?php if ($editing): ?>
                            <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">Başlık</label>
                            <input type="text" class="form-control" name="title" required value="<?= htmlspecialchars($editing['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Slug</label>
                            <input type="text" class="form-control" name="slug" value="<?= htmlspecialchars($editing['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="ornek-sayfa">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Özet</label>
                            <textarea class="form-control" name="summary" rows="2"><?= htmlspecialchars($editing['summary'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">İçerik</label>
                            <textarea class="form-control" name="content" rows="8" data-editor="richtext"><?= htmlspecialchars($editing['content'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Üst Sayfa</label>
                            <?php $currentParentId = isset($editing['parent_id']) ? (int)$editing['parent_id'] : null; ?>
                            <select class="form-select" name="parent_id">
                                <option value="">— Ana Sayfa —</option>
                                <?php foreach ($parentOptions as $option): ?>
                                    <?php
                                        $optionId = (int)$option['id'];
                                        $isSelected = $currentParentId !== null && $currentParentId === $optionId;
                                        $label = str_repeat('— ', (int)$option['depth']) . $option['title'];
                                    ?>
                                    <option value="<?= $optionId ?>"<?= $isSelected ? ' selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col">
                                <label class="form-label">Durum</label>
                                <select class="form-select" name="status">
                                    <?php $status = $editing['status'] ?? 'draft'; ?>
                                    <option value="draft"<?= $status === 'draft' ? ' selected' : '' ?>>Taslak</option>
                                    <option value="scheduled"<?= $status === 'scheduled' ? ' selected' : '' ?>>Zamanlanmış</option>
                                    <option value="published"<?= $status === 'published' ? ' selected' : '' ?>>Yayında</option>
                                    <option value="archived"<?= $status === 'archived' ? ' selected' : '' ?>>Arşiv</option>
                                </select>
                            </div>
                            <div class="col">
                                <label class="form-label">Görünürlük</label>
                                <?php $visibility = $editing['visibility'] ?? 'public'; ?>
                                <select class="form-select" name="visibility">
                                    <option value="public"<?= $visibility === 'public' ? ' selected' : '' ?>>Herkese Açık</option>
                                    <option value="private"<?= $visibility === 'private' ? ' selected' : '' ?>>Özel</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Yayın Tarihi</label>
                            <input type="datetime-local" class="form-control" name="published_at" value="<?= isset($editing['published_at']) && $editing['published_at'] ? date('Y-m-d\TH:i', strtotime($editing['published_at'])) : '' ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Şablon</label>
                            <input type="text" class="form-control" name="template" value="<?= htmlspecialchars($editing['template'] ?? 'default', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kapak Görseli</label>
                            <input type="url" class="form-control" name="hero_image" value="<?= htmlspecialchars($editing['hero_image'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="https://...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Meta Başlık</label>
                            <input type="text" class="form-control" name="meta_title" value="<?= htmlspecialchars($editing['meta_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Meta Açıklama</label>
                            <textarea class="form-control" name="meta_description" rows="2"><?= htmlspecialchars($editing['meta_description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Meta Anahtar Kelimeler</label>
                            <input type="text" class="form-control" name="meta_keywords" value="<?= htmlspecialchars($editing['meta_keywords'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="kelime1, kelime2">
                        </div>
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary"><?= $editing ? 'Güncelle' : 'Kaydet' ?></button>
                            <?php if ($editing): ?>
                                <a class="btn btn-outline-secondary" href="/admin/pages.php">Yeni sayfa</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <form class="row g-2 align-items-end" method="get">
                        <div class="col-md-4">
                            <label class="form-label">Durum</label>
                            <select class="form-select" name="status">
                                <?php $currentStatus = $_GET['status'] ?? ''; ?>
                                <option value="">Tümü</option>
                                <option value="draft"<?= $currentStatus === 'draft' ? ' selected' : '' ?>>Taslak</option>
                                <option value="scheduled"<?= $currentStatus === 'scheduled' ? ' selected' : '' ?>>Zamanlanmış</option>
                                <option value="published"<?= $currentStatus === 'published' ? ' selected' : '' ?>>Yayında</option>
                                <option value="archived"<?= $currentStatus === 'archived' ? ' selected' : '' ?>>Arşiv</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Görünürlük</label>
                            <select class="form-select" name="visibility">
                                <?php $currentVisibility = $_GET['visibility'] ?? ''; ?>
                                <option value="">Tümü</option>
                                <option value="public"<?= $currentVisibility === 'public' ? ' selected' : '' ?>>Herkese Açık</option>
                                <option value="private"<?= $currentVisibility === 'private' ? ' selected' : '' ?>>Özel</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Arama</label>
                            <input type="text" class="form-control" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Başlık veya slug">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-outline-primary w-100">Filtrele</button>
                        </div>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Başlık</th>
                                <th>Slug</th>
                                <th>Durum</th>
                                <th>Görünürlük</th>
                                <th>Yayın Tarihi</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$pages): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Henüz sayfa oluşturulmadı.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pages as $pageItem): ?>
                                    <tr>
                                        <td><?= (int)$pageItem['id'] ?></td>
                                        <td>
                                            <a href="/admin/pages.php?edit=<?= (int)$pageItem['id'] ?>" class="fw-semibold text-decoration-none"><?= htmlspecialchars($pageItem['title'], ENT_QUOTES, 'UTF-8') ?></a>
                                            <div class="small text-muted">Şablon: <?= htmlspecialchars($pageItem['template'] ?? 'default', ENT_QUOTES, 'UTF-8') ?></div>
                                        </td>
                                        <td><code><?= htmlspecialchars($pageItem['slug'], ENT_QUOTES, 'UTF-8') ?></code></td>
                                        <td><span class="badge bg-light text-dark text-uppercase"><?= htmlspecialchars($pageItem['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td><?= htmlspecialchars($pageItem['visibility'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= !empty($pageItem['published_at']) ? date('d.m.Y H:i', strtotime($pageItem['published_at'])) : '-' ?></td>
                                        <td class="text-end">
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="id" value="<?= (int)$pageItem['id'] ?>">
                                                <?php if ($pageItem['status'] !== 'published'): ?>
                                                    <input type="hidden" name="action" value="publish_page">
                                                    <button type="submit" class="btn btn-sm btn-success">Yayınla</button>
                                                <?php else: ?>
                                                    <input type="hidden" name="action" value="archive_page">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Arşivle</button>
                                                <?php endif; ?>
                                            </form>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Sayfa arşive taşınacak. Emin misiniz?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="action" value="delete_page">
                                                <input type="hidden" name="id" value="<?= (int)$pageItem['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Sil</button>
                                            </form>
                                            <a class="btn btn-sm btn-link" target="_blank" href="<?= htmlspecialchars(Helpers::pageUrl($pageItem['slug']), ENT_QUOTES, 'UTF-8') ?>">Görüntüle</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$editorScript = 'https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js';
if (!isset($GLOBALS['pageScripts']) || !is_array($GLOBALS['pageScripts'])) {
    $GLOBALS['pageScripts'] = array();
}
if (!in_array($editorScript, $GLOBALS['pageScripts'], true)) {
    $GLOBALS['pageScripts'][] = $editorScript;
}
if (!isset($GLOBALS['pageInlineScripts']) || !is_array($GLOBALS['pageInlineScripts'])) {
    $GLOBALS['pageInlineScripts'] = array();
}
$GLOBALS['pageInlineScripts'][] = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
    if (typeof ClassicEditor === 'undefined') {
        return;
    }

    document.querySelectorAll('textarea[data-editor="richtext"]').forEach(function (element) {
        if (element.dataset.editorLoaded) {
            return;
        }

        ClassicEditor.create(element, {
            toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', '|', 'undo', 'redo']
        }).then(function () {
            element.dataset.editorLoaded = '1';
        }).catch(function (error) {
            console.error('Metin editörü yüklenemedi:', error);
        });
    });
});
JS;

include __DIR__ . '/templates/footer.php';
