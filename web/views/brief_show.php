<?php
/** @var array $brief */
/** @var array $result */
$type = $result['type'] ?? '';
?>
<div class="page">
  <div class="wrap">
    <p class="stat"><a href="/app">← Dashboard</a> · <span class="badge"><?= e($result['mode'] ?? '') ?></span></p>
    <h1 style="font-size:2rem;max-width:none;margin-top:0.6rem"><?= e($result['title'] ?? 'Ergebnis') ?></h1>
    <p class="muted">Erstellt <?= e(substr($brief['created_at'], 0, 16)) ?> UTC</p>

    <div class="result card-panel" style="margin-top:1.2rem">
      <?php if ($type === 'decision_brief'): ?>
        <h3>Kontext</h3>
        <div class="box"><?= nl2br(e($result['context'] ?? '')) ?></div>

        <h3>Optionen</h3>
        <?php foreach (($result['options'] ?? []) as $opt): ?>
          <div class="box">
            <strong><?= e($opt['name']) ?></strong>
            <div style="margin-top:0.45rem"><em>Pro</em>
              <ul><?php foreach ($opt['pros'] as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul>
            </div>
            <div><em>Contra</em>
              <ul><?php foreach ($opt['cons'] as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul>
            </div>
          </div>
        <?php endforeach; ?>

        <h3>Risiken</h3>
        <ul><?php foreach (($result['risks'] ?? []) as $r): ?><li><?= e($r) ?></li><?php endforeach; ?></ul>

        <h3>Annahmen</h3>
        <ul><?php foreach (($result['assumptions'] ?? []) as $a): ?><li><?= e($a) ?></li><?php endforeach; ?></ul>

        <h3>Empfehlung</h3>
        <div class="box"><?= nl2br(e($result['recommendation'] ?? '')) ?></div>
        <h3>Nächster Schritt</h3>
        <div class="box"><?= nl2br(e($result['next_step'] ?? '')) ?></div>

      <?php elseif ($type === 'offer_score'): ?>
        <?php $scores = $result['scores'] ?? ['price'=>0,'risk'=>0,'fit'=>0]; ?>
        <p><span class="badge <?= e($result['traffic_light'] ?? 'yellow') ?>"><?= e(strtoupper($result['traffic_light'] ?? 'yellow')) ?></span></p>
        <div class="box"><?= nl2br(e($result['summary'] ?? '')) ?></div>
        <div class="score-bars">
          <?php foreach (['price' => 'Preis', 'risk' => 'Risiko', 'fit' => 'Fit'] as $k => $label): ?>
            <div class="score-row">
              <span><?= e($label) ?></span>
              <div class="bar"><span style="width:<?= (int) $scores[$k] ?>%"></span></div>
              <strong><?= (int) $scores[$k] ?></strong>
            </div>
          <?php endforeach; ?>
        </div>
        <h3>Gründe</h3>
        <ul><?php foreach (($result['reasons'] ?? []) as $r): ?><li><?= e($r) ?></li><?php endforeach; ?></ul>
        <h3>Empfehlung</h3>
        <div class="box"><?= nl2br(e($result['recommendation'] ?? '')) ?></div>
        <h3>Nächster Schritt</h3>
        <div class="box"><?= nl2br(e($result['next_step'] ?? '')) ?></div>

      <?php else: ?>
        <p><span class="badge"><?= e(strtoupper($result['go_no_go'] ?? '')) ?></span></p>
        <div class="box"><?= nl2br(e($result['summary'] ?? '')) ?></div>
        <h3>Impacts</h3>
        <?php foreach (($result['impacts'] ?? []) as $imp): ?>
          <div class="box">
            <strong><?= e($imp['area']) ?></strong>
            <span class="badge"><?= e($imp['severity']) ?></span>
            <div style="margin-top:0.35rem"><?= e($imp['detail']) ?></div>
          </div>
        <?php endforeach; ?>
        <h3>Betroffene</h3>
        <ul><?php foreach (($result['affected'] ?? []) as $a): ?><li><?= e($a) ?></li><?php endforeach; ?></ul>
        <h3>Bedingungen</h3>
        <ul><?php foreach (($result['conditions'] ?? []) as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul>
        <h3>Nächster Schritt</h3>
        <div class="box"><?= nl2br(e($result['next_step'] ?? '')) ?></div>
      <?php endif; ?>
    </div>

    <details style="margin-top:1rem" class="muted">
      <summary>Original-Input anzeigen</summary>
      <pre style="white-space:pre-wrap;margin-top:0.7rem;color:var(--muted)"><?= e($brief['input_text']) ?></pre>
    </details>
  </div>
</div>
