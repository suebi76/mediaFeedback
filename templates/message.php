<div class="card" style="max-width:760px; margin:2rem auto; padding:2rem; text-align:center;">
    <div class="badge">Info</div>
    <h1><?= e($title ?? 'Hinweis') ?></h1>
    <p class="muted"><?= e($message ?? '') ?></p>
    <a class="btn" href="<?= V2_BASE_URL ?>/dashboard">Zum Dashboard</a>
</div>
