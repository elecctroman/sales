<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\BlogRepository;
use App\Helpers;

Auth::requireRoles(array('super_admin', 'admin', 'content'));

$currentUser = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$errors = array();
$success = '';
$editingPost = null;
$editingId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string)$_POST['action'] : '';
    $token = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'Geçersiz istek doğrulaması. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        try {
            switch ($action) {
                case 'save_post':
                    $postId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                    $title = isset($_POST['title']) ? trim((string)$_POST['title']) : '';
                    $slug = isset($_POST['slug']) ? trim((string)$_POST['slug']) : '';
                    $excerpt = isset($_POST['excerpt']) ? trim((string)$_POST['excerpt']) : '';
                    $content = isset($_POST['content']) ? (string)$_POST['content'] : '';
                    $author = isset($_POST['author_name']) ? trim((string)$_POST['author_name']) : '';
                    $featuredImage = isset($_POST['featured_image']) ? trim((string)$_POST['featured_image']) : '';
                    $seoTitle = isset($_POST['seo_title']) ? trim((string)$_POST['seo_title']) : '';
                    $seoDescription = isset($_POST['seo_description']) ? trim((string)$_POST['seo_description']) : '';
                    $seoKeywords = isset($_POST['seo_keywords']) ? trim((string)$_POST['seo_keywords']) : '';
                    $status = isset($_POST['status']) ? strtolower(trim((string)$_POST['status'])) : 'draft';
                    $isPublished = $status === 'published';
                    $publishedAtInput = isset($_POST['published_at']) ? trim((string)$_POST['published_at']) : '';

                    $payload = array(
                        'id' => $postId > 0 ? $postId : null,
                        'title' => $title,
                        'slug' => $slug,
                        'excerpt' => $excerpt,
                        'content' => $content,
                        'author_name' => $author,
                        'featured_image' => $featuredImage,
                        'seo_title' => $seoTitle,
                        'seo_description' => $seoDescription,
                        'seo_keywords' => $seoKeywords,
                        'is_published' => $isPublished,
                    );

                    if ($isPublished && $publishedAtInput !== '') {
                        $payload['published_at'] = $publishedAtInput;
                    }

                    $saved = BlogRepository::save($payload);
                    $editingPost = $saved;
                    $editingId = isset($saved['id']) ? (int)$saved['id'] : 0;
                    $success = $postId > 0 ? 'Blog yazısı güncellendi.' : 'Blog yazısı oluşturuldu.';

                    if ($currentUser) {
                        AuditLog::record(
                            (int)$currentUser['id'],
                            'blog_post.save',
                            'blog_post',
                            $editingId,
                            sprintf('Blog yazısı kaydedildi: %s', $title)
                        );
                    }
                    break;

                case 'delete_post':
                    $postId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                    if ($postId <= 0) {
                        throw new \InvalidArgumentException('Silinecek blog yazısı seçilemedi.');
                    }

                    if (!BlogRepository::delete($postId)) {
                        throw new \RuntimeException('Blog yazısı silinemedi.');
                    }

                    if ($editingId === $postId) {
                        $editingId = 0;
                        $editingPost = null;
                    }

                    $success = 'Blog yazısı silindi.';

                    if ($currentUser) {
                        AuditLog::record(
                            (int)$currentUser['id'],
                            'blog_post.delete',
                            'blog_post',
                            $postId,
                            sprintf('Blog yazısı silindi: #%d', $postId)
                        );
                    }
                    break;

                case 'toggle_publish':
                    $postId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                    $target = isset($_POST['target']) ? (string)$_POST['target'] : '';
                    if ($postId <= 0) {
                        throw new \InvalidArgumentException('Geçersiz blog yazısı seçildi.');
                    }

                    $shouldPublish = $target === 'publish';
                    if (!BlogRepository::setPublished($postId, $shouldPublish)) {
                        throw new \RuntimeException('Yayın durumu güncellenemedi.');
                    }

                    $success = $shouldPublish ? 'Blog yazısı yayınlandı.' : 'Blog yazısı taslağa alındı.';
                    if ($editingId === $postId) {
                        $editingPost = BlogRepository::findById($postId);
                    }

                    if ($currentUser) {
                        AuditLog::record(
                            (int)$currentUser['id'],
                            $shouldPublish ? 'blog_post.publish' : 'blog_post.unpublish',
                            'blog_post',
                            $postId,
                            sprintf('Blog yazısı yayın durumu değişti: #%d', $postId)
                        );
                    }
                    break;

                default:
                    throw new \InvalidArgumentException('Bilinmeyen işlem talep edildi.');
            }
        } catch (\InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        } catch (\Throwable $exception) {
            $errors[] = 'İşlem tamamlanamadı: ' . $exception->getMessage();
        }
    }
}

if ($editingId > 0 && $editingPost === null) {
    $editingPost = BlogRepository::findById($editingId);
    if (!$editingPost) {
        $errors[] = 'Düzenlenecek blog yazısı bulunamadı.';
        $editingId = 0;
    }
}

$posts = BlogRepository::all(100);
$csrfToken = Helpers::csrfToken();
$pageTitle = 'Blog Yönetimi';

require __DIR__ . '/templates/header.php';
?>
<div class="app-content">
    <div class="page-head">
        <h1>Blog Yazıları</h1>
        <p>Yeni makaleler oluşturun, mevcut içerikleri güncelleyin veya yayın durumlarını yönetin.</p>
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
                    <strong>Yayınlanan İçerikler</strong>
                    <a href="/admin/blog-posts.php" class="btn btn-sm btn-outline-secondary">Yeni Yazı Oluştur</a>
                </div>
                <div class="card-body p-0">
                    <?php if ($posts): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Başlık</th>
                                        <th>Durum</th>
                                        <th>Yayın Tarihi</th>
                                        <th class="text-end">İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($posts as $post): ?>
                                    <?php
                                        $postId = isset($post['id']) ? (int)$post['id'] : 0;
                                        $postTitle = isset($post['title']) ? (string)$post['title'] : '';
                                        $postStatus = !empty($post['is_published']) ? 'published' : 'draft';
                                        $publishedAt = isset($post['published_at']) ? $post['published_at'] : null;
                                        $publishedLabel = '-';
                                        if ($publishedAt) {
                                            $publishedTs = strtotime($publishedAt);
                                            if ($publishedTs) {
                                                $publishedLabel = date('d.m.Y H:i', $publishedTs);
                                            }
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <a href="/admin/blog-posts.php?edit=<?= $postId ?>" class="fw-semibold">
                                                <?= Helpers::sanitize($postTitle !== '' ? $postTitle : 'İsimsiz Yazı') ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if ($postStatus === 'published'): ?>
                                                <span class="badge bg-success">Yayında</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Taslak</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= Helpers::sanitize($publishedLabel) ?></td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="/admin/blog-posts.php?edit=<?= $postId ?>" class="btn btn-outline-primary">Düzenle</a>
                                                <form method="post" onsubmit="return confirm('Bu yazının yayın durumunu değiştirmek istediğinize emin misiniz?');">
                                                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="toggle_publish">
                                                    <input type="hidden" name="id" value="<?= $postId ?>">
                                                    <input type="hidden" name="target" value="<?= $postStatus === 'published' ? 'unpublish' : 'publish' ?>">
                                                    <button type="submit" class="btn btn-outline-warning">
                                                        <?= $postStatus === 'published' ? 'Taslak Yap' : 'Yayınla' ?>
                                                    </button>
                                                </form>
                                                <form method="post" onsubmit="return confirm('Bu blog yazısını kalıcı olarak silmek istediğinize emin misiniz?');">
                                                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="delete_post">
                                                    <input type="hidden" name="id" value="<?= $postId ?>">
                                                    <button type="submit" class="btn btn-outline-danger">Sil</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">Henüz blog içeriği eklenmemiş.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header">
                    <strong><?= $editingPost ? 'Blog Yazısını Düzenle' : 'Yeni Blog Yazısı' ?></strong>
                </div>
                <div class="card-body">
                    <form method="post" class="vstack gap-3">
                        <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                        <input type="hidden" name="action" value="save_post">
                        <input type="hidden" name="id" value="<?= $editingPost && isset($editingPost['id']) ? (int)$editingPost['id'] : 0 ?>">

                        <div>
                            <label class="form-label" for="title">Başlık</label>
                            <input type="text" class="form-control" id="title" name="title" required maxlength="255" value="<?= Helpers::sanitize($editingPost && isset($editingPost['title']) ? (string)$editingPost['title'] : '') ?>">
                        </div>

                        <div>
                            <label class="form-label" for="slug">Kısa Adres (Slug)</label>
                            <input type="text" class="form-control" id="slug" name="slug" maxlength="255" placeholder="otomatik" value="<?= Helpers::sanitize($editingPost && isset($editingPost['slug']) ? (string)$editingPost['slug'] : '') ?>">
                            <div class="form-text">Slug boş bırakılırsa başlıktan otomatik üretilir.</div>
                        </div>

                        <div>
                            <label class="form-label" for="excerpt">Özet</label>
                            <textarea class="form-control" id="excerpt" name="excerpt" rows="3" maxlength="600" placeholder="Listelerde gösterilecek kısa açıklama."><?= Helpers::sanitize($editingPost && isset($editingPost['excerpt']) ? (string)$editingPost['excerpt'] : '') ?></textarea>
                        </div>

                        <div>
                            <label class="form-label" for="content">İçerik</label>
                            <textarea class="form-control" id="content" name="content" rows="10" required><?= $editingPost && isset($editingPost['content']) ? htmlspecialchars((string)$editingPost['content'], ENT_QUOTES, 'UTF-8') : '' ?></textarea>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="author_name">Yazar Adı</label>
                                <input type="text" class="form-control" id="author_name" name="author_name" maxlength="150" value="<?= Helpers::sanitize($editingPost && isset($editingPost['author_name']) ? (string)$editingPost['author_name'] : '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="status">Durum</label>
                                <?php $currentStatus = $editingPost && !empty($editingPost['is_published']) ? 'published' : (isset($editingPost['status']) ? (string)$editingPost['status'] : 'draft'); ?>
                                <select class="form-select" id="status" name="status">
                                    <option value="draft"<?= $currentStatus === 'draft' ? ' selected' : '' ?>>Taslak</option>
                                    <option value="published"<?= $currentStatus === 'published' ? ' selected' : '' ?>>Yayınla</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="form-label" for="featured_image">Öne Çıkan Görsel URL</label>
                            <input type="text" class="form-control" id="featured_image" name="featured_image" maxlength="255" value="<?= Helpers::sanitize($editingPost && isset($editingPost['featured_image']) ? (string)$editingPost['featured_image'] : '') ?>">
                            <div class="form-text">Tam URL veya /theme/... ile başlayan göreli adres.</div>
                        </div>

                        <div>
                            <label class="form-label" for="published_at">Yayın Tarihi</label>
                            <input type="text" class="form-control" id="published_at" name="published_at" placeholder="otomatik" value="<?= Helpers::sanitize($editingPost && isset($editingPost['published_at']) && $editingPost['published_at'] ? (string)$editingPost['published_at'] : '') ?>">
                            <div class="form-text">Boş bırakırsanız yayınlanan yazılar için otomatik olarak şimdi atanır.</div>
                        </div>

                        <div class="accordion" id="seoSettings">
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="seoHeading">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#seoCollapse" aria-expanded="false" aria-controls="seoCollapse">
                                        SEO Ayarları
                                    </button>
                                </h2>
                                <div id="seoCollapse" class="accordion-collapse collapse" aria-labelledby="seoHeading" data-bs-parent="#seoSettings">
                                    <div class="accordion-body">
                                        <div class="mb-3">
                                            <label class="form-label" for="seo_title">SEO Başlığı</label>
                                            <input type="text" class="form-control" id="seo_title" name="seo_title" maxlength="255" value="<?= Helpers::sanitize($editingPost && isset($editingPost['seo_title']) ? (string)$editingPost['seo_title'] : '') ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label" for="seo_description">SEO Açıklaması</label>
                                            <textarea class="form-control" id="seo_description" name="seo_description" rows="2" maxlength="255"><?= Helpers::sanitize($editingPost && isset($editingPost['seo_description']) ? (string)$editingPost['seo_description'] : '') ?></textarea>
                                        </div>
                                        <div class="mb-0">
                                            <label class="form-label" for="seo_keywords">SEO Anahtar Kelimeleri</label>
                                            <textarea class="form-control" id="seo_keywords" name="seo_keywords" rows="2" placeholder="Virgülle ayrılmış anahtar kelimeler."><?= Helpers::sanitize($editingPost && isset($editingPost['seo_keywords']) ? (string)$editingPost['seo_keywords'] : '') ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <?php if ($editingPost && isset($editingPost['url'])): ?>
                                <a class="btn btn-link" target="_blank" rel="noopener" href="<?= Helpers::sanitize($editingPost['url']) ?>">Yazıyı görüntüle</a>
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
