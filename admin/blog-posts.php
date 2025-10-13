<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Helpers;
use App\Blog;

Auth::requireRoles(array('super_admin', 'admin', 'content'));

$feedback = Helpers::getFlash('blog_posts_feedback', array('errors' => array(), 'success' => ''));
$errors = array();
$success = '';
$csrfToken = Helpers::csrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';
    $token = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyiniz.';
    } else {
        $userId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
        switch ($action) {
            case 'create_post':
                $payload = array(
                    'title' => $_POST['title'] ?? '',
                    'slug' => $_POST['slug'] ?? '',
                    'summary' => $_POST['summary'] ?? '',
                    'content' => $_POST['content'] ?? '',
                    'status' => $_POST['status'] ?? 'draft',
                    'category_id' => $_POST['category_id'] ?? null,
                    'cover_image' => $_POST['cover_image'] ?? '',
                    'meta_title' => $_POST['meta_title'] ?? '',
                    'meta_description' => $_POST['meta_description'] ?? '',
                    'meta_keywords' => $_POST['meta_keywords'] ?? '',
                    'tags' => $_POST['tags'] ?? '',
                    'published_at' => $_POST['published_at'] ?? null,
                    'reading_time' => $_POST['reading_time'] ?? null,
                    'canonical_url' => $_POST['canonical_url'] ?? '',
                );
                try {
                    Blog::createPost($payload, $userId);
                    $success = 'Blog yazısı oluşturuldu.';
                } catch (\Throwable $exception) {
                    $errors[] = 'Yazı kaydedilirken bir hata oluştu.';
                }
                break;

            case 'update_post':
                $postId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                $payload = array(
                    'title' => $_POST['title'] ?? '',
                    'slug' => $_POST['slug'] ?? '',
                    'summary' => $_POST['summary'] ?? '',
                    'content' => $_POST['content'] ?? '',
                    'status' => $_POST['status'] ?? 'draft',
                    'category_id' => $_POST['category_id'] ?? null,
                    'cover_image' => $_POST['cover_image'] ?? '',
                    'meta_title' => $_POST['meta_title'] ?? '',
                    'meta_description' => $_POST['meta_description'] ?? '',
                    'meta_keywords' => $_POST['meta_keywords'] ?? '',
                    'tags' => $_POST['tags'] ?? '',
                    'published_at' => $_POST['published_at'] ?? null,
                    'reading_time' => $_POST['reading_time'] ?? null,
                    'canonical_url' => $_POST['canonical_url'] ?? '',
                );
                if ($postId <= 0) {
                    $errors[] = 'Düzenlenecek yazı bulunamadı.';
                } else {
                    try {
                        Blog::updatePost($postId, $payload, $userId);
                        $success = 'Blog yazısı güncellendi.';
                    } catch (\Throwable $exception) {
                        $errors[] = 'Yazı güncellenemedi.';
                    }
                }
                break;

            case 'delete_post':
                $postId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                if ($postId <= 0) {
                    $errors[] = 'Yazı bulunamadı.';
                } else {
                    Blog::deletePost($postId, $userId);
                    $success = 'Blog yazısı arşivlendi.';
                }
                break;

            case 'publish_post':
                $postId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                $publishAt = isset($_POST['publish_at']) ? (string)$_POST['publish_at'] : null;
                if ($postId <= 0) {
                    $errors[] = 'Yazı bulunamadı.';
                } else {
                    Blog::setStatus($postId, 'published', $publishAt, $userId);
                    $success = 'Yazı yayınlandı.';
                }
                break;

            case 'archive_post':
                $postId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                if ($postId <= 0) {
                    $errors[] = 'Yazı bulunamadı.';
                } else {
                    Blog::setStatus($postId, 'archived', null, $userId);
                    $success = 'Yazı arşive taşındı.';
                }
                break;

            case 'create_category':
            case 'update_category':
                $categoryId = $action === 'update_category' ? (int)($_POST['id'] ?? 0) : null;
                $payload = array(
                    'name' => $_POST['name'] ?? '',
                    'slug' => $_POST['slug'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'is_active' => !empty($_POST['is_active']) ? 1 : 0,
                );
                Blog::saveCategory($payload, $categoryId, $userId);
                $success = 'Kategori kaydedildi.';
                break;

            case 'delete_category':
                $categoryId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                if ($categoryId <= 0) {
                    $errors[] = 'Kategori bulunamadı.';
                } else {
                    Blog::deleteCategory($categoryId, $userId);
                    $success = 'Kategori arşive taşındı.';
                }
                break;
        }
    }

    Helpers::setFlash('blog_posts_feedback', array('errors' => $errors, 'success' => $success));
    Helpers::redirect('/admin/blog-posts.php');
}

$filters = array();
if (!empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}
if (!empty($_GET['category'])) {
    $filters['category'] = $_GET['category'];
}
if (!empty($_GET['q'])) {
    $filters['query'] = trim((string)$_GET['q']);
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

$postsData = Blog::paginatePosts($filters, $perPage, $offset, true);
$posts = $postsData['items'];
$totalPosts = $postsData['total'];

$categories = Blog::categories();
$tags = Blog::tags(40);

$editPostId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editingPost = $editPostId > 0 ? Blog::findPost($editPostId) : null;

$editCategoryId = isset($_GET['edit_category']) ? (int)$_GET['edit_category'] : 0;
$editingCategory = null;
if ($editCategoryId > 0) {
    foreach ($categories as $category) {
        if ((int)$category['id'] === $editCategoryId) {
            $editingCategory = $category;
            break;
        }
    }
}

$pageTitle = 'Blog Yönetimi';
include __DIR__ . '/templates/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Blog Yönetimi</h1>
        <a href="<?= htmlspecialchars(Helpers::blogUrl(), ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="btn btn-outline-primary btn-sm">Blogu görüntüle</a>
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

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <strong><?= $editingPost ? 'Blog Yazısını Düzenle' : 'Yeni Blog Yazısı' ?></strong>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="<?= $editingPost ? 'update_post' : 'create_post' ?>">
                        <?php if ($editingPost): ?>
                            <input type="hidden" name="id" value="<?= (int)$editingPost['id'] ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">Başlık</label>
                            <input type="text" class="form-control" name="title" required value="<?= htmlspecialchars($editingPost['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Slug</label>
                            <input type="text" class="form-control" name="slug" value="<?= htmlspecialchars($editingPost['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="yazi-basligi">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Kategori</label>
                                <select class="form-select" name="category_id">
                                    <option value="">Kategori seçin</option>
                                    <?php $postCategory = $editingPost['category_id'] ?? null; ?>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= (int)$category['id'] ?>"<?= (int)$postCategory === (int)$category['id'] ? ' selected' : '' ?>><?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Durum</label>
                                <?php $postStatus = $editingPost['status'] ?? 'draft'; ?>
                                <select class="form-select" name="status">
                                    <option value="draft"<?= $postStatus === 'draft' ? ' selected' : '' ?>>Taslak</option>
                                    <option value="scheduled"<?= $postStatus === 'scheduled' ? ' selected' : '' ?>>Zamanlanmış</option>
                                    <option value="published"<?= $postStatus === 'published' ? ' selected' : '' ?>>Yayında</option>
                                    <option value="archived"<?= $postStatus === 'archived' ? ' selected' : '' ?>>Arşiv</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Okuma süresi</label>
                                <input type="number" class="form-control" name="reading_time" min="1" value="<?= isset($editingPost['reading_time']) ? (int)$editingPost['reading_time'] : '' ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Özet</label>
                            <textarea class="form-control" name="summary" rows="2"><?= htmlspecialchars($editingPost['summary'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">İçerik</label>
                            <textarea class="form-control" name="content" rows="10" data-editor="richtext"><?= htmlspecialchars($editingPost['content'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kapak Görseli</label>
                            <input type="url" class="form-control" name="cover_image" value="<?= htmlspecialchars($editingPost['cover_image'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Yayın Tarihi</label>
                                <input type="datetime-local" class="form-control" name="published_at" value="<?= isset($editingPost['published_at']) && $editingPost['published_at'] ? date('Y-m-d\TH:i', strtotime($editingPost['published_at'])) : '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Canonical URL</label>
                                <input type="url" class="form-control" name="canonical_url" value="<?= htmlspecialchars($editingPost['canonical_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="https://...">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Etiketler</label>
                            <?php
                            $tagString = '';
                            if (!empty($editingPost['tags'])) {
                                $tagString = implode(', ', array_map(function ($tag) {
                                    return $tag['slug'];
                                }, $editingPost['tags']));
                            }
                            ?>
                            <input type="text" class="form-control" name="tags" value="<?= htmlspecialchars($tagString, ENT_QUOTES, 'UTF-8') ?>" placeholder="etiket1, etiket2">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Meta Başlık</label>
                            <input type="text" class="form-control" name="meta_title" value="<?= htmlspecialchars($editingPost['meta_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Meta Açıklama</label>
                            <textarea class="form-control" name="meta_description" rows="2"><?= htmlspecialchars($editingPost['meta_description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Meta Anahtar Kelimeler</label>
                            <input type="text" class="form-control" name="meta_keywords" value="<?= htmlspecialchars($editingPost['meta_keywords'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary"><?= $editingPost ? 'Yazıyı Güncelle' : 'Yazıyı Kaydet' ?></button>
                            <?php if ($editingPost): ?>
                                <a href="/admin/blog-posts.php" class="btn btn-outline-secondary">Yeni yazı</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <strong><?= $editingCategory ? 'Kategoriyi Düzenle' : 'Yeni Kategori' ?></strong>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="<?= $editingCategory ? 'update_category' : 'create_category' ?>">
                        <?php if ($editingCategory): ?>
                            <input type="hidden" name="id" value="<?= (int)$editingCategory['id'] ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">Adı</label>
                            <input type="text" class="form-control" name="name" required value="<?= htmlspecialchars($editingCategory['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Slug</label>
                            <input type="text" class="form-control" name="slug" value="<?= htmlspecialchars($editingCategory['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="kategori-adi">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Açıklama</label>
                            <textarea class="form-control" name="description" rows="2"><?= htmlspecialchars($editingCategory['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="category-active"<?= empty($editingCategory['is_active']) ? '' : ' checked' ?>>
                            <label class="form-check-label" for="category-active">Aktif</label>
                        </div>
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-outline-primary"><?= $editingCategory ? 'Kategoriyi Güncelle' : 'Kategori Ekle' ?></button>
                            <?php if ($editingCategory): ?>
                                <a class="btn btn-outline-secondary" href="/admin/blog-posts.php">Yeni kategori</a>
                            <?php endif; ?>
                        </div>
                    </form>
                    <?php if ($categories): ?>
                        <hr>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($categories as $category): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <div class="small text-muted">Slug: <?= htmlspecialchars($category['slug'], ENT_QUOTES, 'UTF-8') ?></div>
                                    </div>
                                    <div class="btn-group">
                                        <a class="btn btn-sm btn-outline-secondary" href="/admin/blog-posts.php?edit_category=<?= (int)$category['id'] ?>">Düzenle</a>
                                        <form method="post" onsubmit="return confirm('Kategori arşive taşınacak. Devam edilsin mi?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Sil</button>
                                        </form>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <form class="row g-2 align-items-end" method="get">
                <div class="col-md-3">
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
                    <label class="form-label">Kategori</label>
                    <select class="form-select" name="category">
                        <?php $currentCategory = $_GET['category'] ?? ''; ?>
                        <option value="">Tümü</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category['slug'], ENT_QUOTES, 'UTF-8') ?>"<?= $currentCategory === $category['slug'] ? ' selected' : '' ?>><?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Arama</label>
                    <input type="text" class="form-control" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Başlık veya özet">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100">Filtrele</button>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Başlık</th>
                        <th>Durum</th>
                        <th>Kategori</th>
                        <th>Yayın Tarihi</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$posts): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Henüz blog yazısı bulunmuyor.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($posts as $post): ?>
                            <tr>
                                <td><?= (int)$post['id'] ?></td>
                                <td>
                                    <a class="fw-semibold text-decoration-none" href="/admin/blog-posts.php?edit=<?= (int)$post['id'] ?>"><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?></a>
                                    <div class="small text-muted">Slug: <?= htmlspecialchars($post['slug'], ENT_QUOTES, 'UTF-8') ?></div>
                                </td>
                                <td><span class="badge bg-light text-uppercase text-dark"><?= htmlspecialchars($post['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= htmlspecialchars($post['category_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= !empty($post['published_at']) ? date('d.m.Y H:i', strtotime($post['published_at'])) : '-' ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-link" target="_blank" href="<?= htmlspecialchars(Helpers::blogPostUrl($post), ENT_QUOTES, 'UTF-8') ?>">Görüntüle</a>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
                                        <?php if ($post['status'] !== 'published'): ?>
                                            <input type="hidden" name="action" value="publish_post">
                                            <button type="submit" class="btn btn-sm btn-success">Yayınla</button>
                                        <?php else: ?>
                                            <input type="hidden" name="action" value="archive_post">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">Arşivle</button>
                                        <?php endif; ?>
                                    </form>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Yazı arşive taşınacak. Devam edilsin mi?');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="action" value="delete_post">
                                        <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Sil</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php $totalPages = $totalPosts > 0 ? (int)ceil($totalPosts / $perPage) : 1; ?>
        <?php if ($totalPages > 1): ?>
            <div class="card-footer d-flex justify-content-end">
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php $query = $_GET; $query['page'] = $i; ?>
                            <li class="page-item<?= $i === $page ? ' active' : '' ?>">
                                <a class="page-link" href="<?= htmlspecialchars('/admin/blog-posts.php?' . http_build_query($query), ENT_QUOTES, 'UTF-8') ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
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
