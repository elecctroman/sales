<?php
use App\Helpers;

$authState = $auth ?? array('errors' => array(), 'success' => '', 'old' => array());
$nameValue = isset($authState['old']['name']) ? $authState['old']['name'] : '';
$emailValue = isset($authState['old']['email']) ? $authState['old']['email'] : '';
?>
<form class="auth-card" method="post" action="/register.php">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Helpers::csrfToken()) ?>">
    <h1>Yeni Hesap Oluştur</h1>

    <?php if (!empty($authState['errors'])): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($authState['errors'] as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <label>Ad Soyad
        <input type="text" name="name" value="<?= htmlspecialchars($nameValue) ?>" required>
    </label>
    <label>E-posta
        <input type="email" name="email" value="<?= htmlspecialchars($emailValue) ?>" required>
    </label>
    <label>Şifre
        <input type="password" name="password" required>
    </label>
    <label>Şifre Tekrarı
        <input type="password" name="password_confirmation" required>
    </label>
    <button class="btn btn-primary" type="submit">Kayıt Ol</button>
    <p class="auth-card__hint">Zaten hesabınız var mı? <a href="/login.php">Giriş yapın</a>.</p>
</form>
