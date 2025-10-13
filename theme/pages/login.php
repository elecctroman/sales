<?php
use App\Helpers;

$authState = $auth ?? array('errors' => array(), 'success' => '', 'old' => array());
$emailValue = isset($authState['old']['email']) ? $authState['old']['email'] : '';
?>
<form class="auth-card" method="post" action="<?= htmlspecialchars(Helpers::loginUrl(), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Helpers::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <?php if (!empty($authState['redirect'])): ?>
        <input type="hidden" name="return" value="<?= htmlspecialchars($authState['redirect'], ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <h1>Hesabınıza Giriş Yapın</h1>

    <?php if (!empty($authState['errors'])): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($authState['errors'] as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <label>E-posta
        <input type="email" name="email" value="<?= htmlspecialchars($emailValue) ?>" required>
    </label>
    <label>Şifre
        <input type="password" name="password" required>
    </label>
    <button class="btn btn-primary" type="submit">Giriş Yap</button>
    <p class="auth-card__hint">Hesabınız yok mu? <a href="<?= htmlspecialchars(Helpers::registerUrl(), ENT_QUOTES, 'UTF-8') ?>">Hemen kayıt olun</a>.</p>
</form>
