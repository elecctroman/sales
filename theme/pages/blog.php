<section class="blog">
    <div class="section-header">
        <div>
            <h1>Blog</h1>
            <small>Guides and industry news</small>
        </div>
        <a class="section-link" href="/">Back to home</a>
    </div>
    <div class="blog__grid">
        <?php foreach ($posts ?? [] as $post): ?>
            <article class="blog-card">
                <?php if (!empty($post['image'])): ?>
                    <img src="<?= htmlspecialchars($post['image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>">
                <?php endif; ?>
                <small><?= htmlspecialchars($post['date']) ?></small>
                <h3><?= htmlspecialchars($post['title']) ?></h3>
                <p><?= htmlspecialchars($post['excerpt']) ?></p>
                <a href="#">Read more</a>
            </article>
        <?php endforeach; ?>
    </div>
</section>
