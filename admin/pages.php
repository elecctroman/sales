<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\Helpers;
use App\PageRepository;

Auth::requireRoles(array('super_admin', 'admin', 'content'));

$currentUser = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$errors = array();
$success = '';
$editingPage = null;
$editingParam = isset($_GET['edit']) ? trim((string)$_GET['edit']) : '';
$editingId = ctype_digit($editingParam) ? (int)$editingParam : 0;

PageRepository::ensureDefaultPages();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string)$_POST['action'] : '';
    $token = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'İstek doğrulanamadı. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        try {
            switch ($action) {
                case 'save_page':
                    $pageId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                    $title = isset($_POST['title']) ? trim((string)$_POST['title']) : '';
                    $slug = isset($_POST['slug']) ? trim((string)$_POST['slug']) : '';
                    $content = isset($_POST['content']) ? (string)$_POST['content'] : '';
                    $metaTitle = isset($_POST['meta_title']) ? trim((string)$_POST['meta_title']) : '';
                    $metaDescription = isset($_POST['meta_description']) ? trim((string)$_POST['meta_description']) : '';
                    $metaKeywords = isset($_POST['meta_keywords']) ? trim((string)$_POST['meta_keywords']) : '';
                    $statusInput = isset($_POST['status']) ? strtolower(trim((string)$_POST['status'])) : 'draft';
                    $publishedAt = isset($_POST['published_at']) ? trim((string)$_POST['published_at']) : '';

                    if ($title === '') {
                        throw new \InvalidArgumentException('Sayfa başlığı zorunludur.');
                    }

                    if (!in_array($statusInput, array('draft', 'published', 'archived'), true)) {
                        $statusInput = 'draft';
                    }

                    $payload = array(
                        'id' => $pageId > 0 ? $pageId : null,
                        'title' => $title,
                        'slug' => $slug,
                        'content' => $content,
                        'meta_title' => $metaTitle,
                        'meta_description' => $metaDescription,
                        'meta_keywords' => $metaKeywords,
                        'status' => $statusInput,
                    );

                    if ($statusInput === 'published' && $publishedAt !== '') {
                        $payload['published_at'] = $publishedAt;
                    }

                    $saved = PageRepository::save($payload);
                    $editingPage = $saved;
                    $editingId = isset($saved['id']) ? (int)$saved['id'] : 0;
                    $success = $pageId > 0 ? 'Sayfa güncellendi.' : 'Yeni sayfa oluşturuldu.';

                    if ($currentUser && isset($saved['id'])) {
                        AuditLog::record(
                            (int)$currentUser['id'],
                            'page.save',
                            'page',
                            (int)$saved['id'],
                            sprintf('Sayfa kaydedildi: %s', $title)
                        );
                    }
                    break;

                case 'delete_page':
                    $pageId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                    if ($pageId <= 0) {
                        throw new \InvalidArgumentException('Silinecek sayfa bulunamadı.');
                    }

                    $page = PageRepository::findById($pageId);
                    if (!$page) {
                        throw new \RuntimeException('Sayfa kaydı bulunamadı.');
                    }

                    $defaultSlugs = array_keys(PageRepository::defaultPages());
                    if (isset($page['slug']) && in_array($page['slug'], $defaultSlugs, true)) {
                        throw new \RuntimeException('Varsayılan sayfalar silinemez.');
                    }

                    if (!PageRepository::delete($pageId)) {
                        throw new \RuntimeException('Sayfa silinemedi.');
                    }

                    if ($editingId === $pageId) {
                        $editingId = 0;
                        $editingPage = null;
                    }

                    $success = 'Sayfa silindi.';

                    if ($currentUser) {
                        AuditLog::record(
                            (int)$currentUser['id'],
                            'page.delete',
                            'page',
                            $pageId,
                            sprintf('Sayfa silindi: #%d', $pageId)
                        );
                    }
                    break;

                default:
                    throw new \InvalidArgumentException('Bilinmeyen işlem talep edildi.');
            }
        } catch (\InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        } catch (\Throwable $exception) {
            $errors[] = 'İşlem başarısız: ' . $exception->getMessage();
        }
    }
}

if ($editingPage === null && $editingParam !== '') {
    if ($editingId > 0) {
        $editingPage = PageRepository::findById($editingId);
    }
    if (!$editingPage) {
        $editingPage = PageRepository::findBySlug($editingParam);
    }
    if ($editingPage && isset($editingPage['id'])) {
        $editingId = (int)$editingPage['id'];
    }
    if (!$editingPage) {
        $errors[] = 'Düzenlenecek sayfa bulunamadı.';
        $editingId = 0;
    }
}

$pages = PageRepository::all();
$defaultPageSlugs = array_keys(PageRepository::defaultPages());
$csrfToken = Helpers::csrfToken();
$pageTitle = 'Sayfa Yönetimi';

require __DIR__ . '/templates/header.php';
?>
<div class="app-content">
    <div class="page-head">
        <h1>Sabit Sayfalar</h1>
        <p>Yasal metinleri, kampanya sayfalarını ve diğer statik içerikleri yönetin.</p>
    </div>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success" role="alert">
            <?= Helpers::sanitize($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= Helpers::sanitize($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Mevcut Sayfalar</strong>
                    <a href="/admin/pages.php" class="btn btn-sm btn-outline-secondary">Yeni Sayfa Oluştur</a>
                </div>
                <div class="card-body p-0">
                    <?php if ($pages): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Başlık</th>
                                        <th>Slug</th>
                                        <th>Durum</th>
                                        <th>Güncelleme</th>
                                        <th class="text-end">İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pages as $page): ?>
                                        <?php
                                            $pageId = isset($page['id']) ? (int)$page['id'] : 0;
                                            $pageTitleValue = isset($page['title']) ? (string)$page['title'] : '';
                                            $slugValue = isset($page['slug']) ? (string)$page['slug'] : '';
                                            $statusValue = isset($page['status']) ? (string)$page['status'] : 'draft';
                                            $updatedAt = isset($page['updated_at']) && $page['updated_at'] ? $page['updated_at'] : (isset($page['created_at']) ? $page['created_at'] : null);
                                            $updatedLabel = '-';
                                            if ($updatedAt) {
                                                $updatedTs = strtotime($updatedAt);
                                                if ($updatedTs) {
                                                    $updatedLabel = date('d.m.Y H:i', $updatedTs);
                                                }
                                            }
                                            $isDefault = in_array($slugValue, $defaultPageSlugs, true);
                                        ?>
                                        <tr>
                                            <td>
                                                <a href="/admin/pages.php?edit=<?= $pageId ?>" class="fw-semibold">
                                                    <?= Helpers::sanitize($pageTitleValue !== '' ? $pageTitleValue : 'İsimsiz Sayfa') ?>
                                                </a>
                                            </td>
                                            <td><code><?= Helpers::sanitize($slugValue) ?></code></td>
                                            <td>
                                                <?php if ($statusValue === 'published'): ?>
                                                    <span class="badge bg-success">Yayında</span>
                                                <?php elseif ($statusValue === 'archived'): ?>
                                                    <span class="badge bg-dark">Arşivde</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Taslak</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= Helpers::sanitize($updatedLabel) ?></td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="/admin/pages.php?edit=<?= $pageId ?>" class="btn btn-outline-primary">Düzenle</a>
                                                    <?php if (!$isDefault): ?>
                                                        <form method="post" onsubmit="return confirm('Bu sayfayı kalıcı olarak silmek istediğinize emin misiniz?');">
                                                            <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                                                            <input type="hidden" name="action" value="delete_page">
                                                            <input type="hidden" name="id" value="<?= $pageId ?>">
                                                            <button type="submit" class="btn btn-outline-danger">Sil</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <?php if ($statusValue === 'published' && $slugValue !== ''): ?>
                                                        <a class="btn btn-outline-secondary" target="_blank" rel="noopener" href="<?= Helpers::sanitize(Helpers::pageUrl($slugValue, true)) ?>">Görüntüle</a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">Henüz sayfa oluşturulmamış.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header">
                    <strong><?= $editingPage ? 'Sayfayı Düzenle' : 'Yeni Sayfa' ?></strong>
                </div>
                <div class="card-body">
                    <form method="post" class="vstack gap-3">
                        <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                        <input type="hidden" name="action" value="save_page">
                        <input type="hidden" name="id" value="<?= $editingPage && isset($editingPage['id']) ? (int)$editingPage['id'] : 0 ?>">

                        <div>
                            <label class="form-label" for="page_title">Başlık</label>
                            <input type="text" class="form-control" id="page_title" name="title" required maxlength="191" value="<?= Helpers::sanitize($editingPage && isset($editingPage['title']) ? (string)$editingPage['title'] : '') ?>">
                        </div>

                        <div>
                            <label class="form-label" for="page_slug">Kısa Adres (Slug)</label>
                            <input type="text" class="form-control" id="page_slug" name="slug" maxlength="150" placeholder="otomatik" value="<?= Helpers::sanitize($editingPage && isset($editingPage['slug']) ? (string)$editingPage['slug'] : '') ?>">
                        </div>

                        <div>
                            <label class="form-label" for="page_content">İçerik</label>
                            <textarea class="form-control" id="page_content" name="content" rows="10" placeholder="HTML içeriği burada düzenleyebilirsiniz."><?= $editingPage && isset($editingPage['content']) ? htmlspecialchars((string)$editingPage['content'], ENT_QUOTES, 'UTF-8') : '' ?></textarea>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="page_status">Durum</label>
                                <?php $statusCurrent = $editingPage && isset($editingPage['status']) ? (string)$editingPage['status'] : ($editingPage && !empty($editingPage['published_at']) ? 'published' : 'draft'); ?>
                                <select class="form-select" id="page_status" name="status">
                                    <option value="draft"<?= $statusCurrent === 'draft' ? ' selected' : '' ?>>Taslak</option>
                                    <option value="published"<?= $statusCurrent === 'published' ? ' selected' : '' ?>>Yayında</option>
                                    <option value="archived"<?= $statusCurrent === 'archived' ? ' selected' : '' ?>>Arşiv</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="page_published_at">Yayın Tarihi</label>
                                <input type="text" class="form-control" id="page_published_at" name="published_at" placeholder="otomatik" value="<?= Helpers::sanitize($editingPage && isset($editingPage['published_at']) && $editingPage['published_at'] ? (string)$editingPage['published_at'] : '') ?>">
                            </div>
                        </div>

                        <div class="accordion" id="pageSeoSettings">
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="pageSeoHeading">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#pageSeoCollapse" aria-expanded="false" aria-controls="pageSeoCollapse">
                                        SEO Ayarları
                                    </button>
                                </h2>
                                <div id="pageSeoCollapse" class="accordion-collapse collapse" aria-labelledby="pageSeoHeading" data-bs-parent="#pageSeoSettings">
                                    <div class="accordion-body">
                                        <div class="mb-3">
                                            <label class="form-label" for="page_meta_title">Meta Başlığı</label>
                                            <input type="text" class="form-control" id="page_meta_title" name="meta_title" maxlength="191" value="<?= Helpers::sanitize($editingPage && isset($editingPage['meta_title']) ? (string)$editingPage['meta_title'] : '') ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label" for="page_meta_description">Meta Açıklaması</label>
                                            <textarea class="form-control" id="page_meta_description" name="meta_description" rows="2" maxlength="255"><?= Helpers::sanitize($editingPage && isset($editingPage['meta_description']) ? (string)$editingPage['meta_description'] : '') ?></textarea>
                                        </div>
                                        <div class="mb-0">
                                            <label class="form-label" for="page_meta_keywords">Meta Anahtar Kelimeleri</label>
                                            <textarea class="form-control" id="page_meta_keywords" name="meta_keywords" rows="2" placeholder="Virgülle ayrılmış anahtar kelimeler."><?= Helpers::sanitize($editingPage && isset($editingPage['meta_keywords']) ? (string)$editingPage['meta_keywords'] : '') ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <?php if ($editingPage && isset($editingPage['slug']) && $editingPage['slug'] !== ''): ?>
                                <a class="btn btn-link" target="_blank" rel="noopener" href="<?= Helpers::sanitize(Helpers::pageUrl((string)$editingPage['slug'], true)) ?>">Sayfayı görüntüle</a>
                            <?php else: ?>
                                <span></span>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary">Kaydet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/templates/footer.php';
