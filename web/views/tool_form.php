<?php
/** @var string $toolTitle */
/** @var string $toolHint */
/** @var string $action */
/** @var string $placeholder */
?>
<div class="page">
  <div class="wrap">
    <h1 style="font-size:2rem;max-width:none"><?= e($toolTitle) ?></h1>
    <p class="section-intro"><?= e($toolHint) ?></p>

    <form method="post" action="<?= e($action) ?>" class="card-panel">
      <?= csrf_field() ?>
      <label>Titel (optional)
        <input type="text" name="title" maxlength="120" placeholder="z.B. Vendor-Auswahl Q2" />
      </label>
      <label>Kontext
        <textarea name="input" required placeholder="<?= e($placeholder) ?>"></textarea>
      </label>
      <div class="form-actions">
        <button class="cta" type="submit">Auswerten</button>
        <a class="btn btn-ghost" href="/app">Zurück</a>
      </div>
    </form>
  </div>
</div>
