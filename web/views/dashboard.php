<?php
/** @var array $user */
/** @var array $plan */
/** @var int $remaining */
/** @var array $briefs */
/** @var bool $apiMode */
?>
<div class="page">
  <div class="wrap-wide">
    <h1 style="font-size:2.2rem;max-width:none">Dashboard</h1>
    <p class="section-intro">Hallo <?= e($user['name']) ?> — Plan <strong><?= e($plan['name']) ?></strong>.</p>

    <div class="grid-3" style="margin-bottom:1.5rem">
      <div class="card-panel">
        <div class="stat">Verbleibende Briefs</div>
        <strong style="font-size:1.6rem"><?= (int) $remaining ?></strong>
      </div>
      <div class="card-panel">
        <div class="stat">Modus</div>
        <strong><?= $apiMode ? 'API (OpenAI)' : 'Demo (lokal)' ?></strong>
        <p class="muted" style="margin-top:0.4rem;font-size:0.9rem">
          <?= $apiMode
            ? 'Live-Auswertung über konfigurierten API-Key.'
            : 'Funktioniert ohne Key. Für Kundenqualität API-Key in config.php setzen.' ?>
        </p>
      </div>
      <div class="card-panel">
        <div class="stat">Add-ons</div>
        <strong><?= user_can_use_addon($user) ? 'Freigeschaltet' : 'Ab Team' ?></strong>
        <?php if (!user_can_use_addon($user)): ?>
          <p style="margin-top:0.6rem"><a class="btn btn-ghost" href="/upgrade">Upgrade</a></p>
        <?php endif; ?>
      </div>
    </div>

    <div class="grid-3" style="margin-bottom:2rem">
      <a class="card-panel" href="/app/decision" style="text-decoration:none;color:inherit">
        <div class="tag">Kern</div>
        <h3 style="margin:0.35rem 0">Decision Brief</h3>
        <p class="muted">Entscheidung mit Optionen, Risiken und Next Step.</p>
      </a>
      <a class="card-panel" href="/app/offer" style="text-decoration:none;color:inherit;<?= user_can_use_addon($user) ? '' : 'opacity:0.55' ?>">
        <div class="tag">Add-on</div>
        <h3 style="margin:0.35rem 0">Offer Score</h3>
        <p class="muted">Angebote bewerten mit Ampel.</p>
      </a>
      <a class="card-panel" href="/app/impact" style="text-decoration:none;color:inherit;<?= user_can_use_addon($user) ? '' : 'opacity:0.55' ?>">
        <div class="tag">Add-on</div>
        <h3 style="margin:0.35rem 0">Change Impact Check</h3>
        <p class="muted">Go / No-Go vor Changes.</p>
      </a>
    </div>

    <h2>Letzte Ergebnisse</h2>
    <?php if (!$briefs): ?>
      <p class="muted" style="margin-top:0.7rem">Noch keine Briefs. Starte mit einem Decision Brief.</p>
    <?php else: ?>
      <table class="table-list" style="margin-top:0.7rem">
        <thead>
          <tr>
            <th>Titel</th>
            <th>Typ</th>
            <th>Modus</th>
            <th>Datum</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($briefs as $b): ?>
            <tr>
              <td><a href="/app/brief/<?= (int) $b['id'] ?>"><?= e($b['title']) ?></a></td>
              <td><?= e($b['type']) ?></td>
              <td><span class="badge"><?= e($b['mode']) ?></span></td>
              <td><?= e(substr($b['created_at'], 0, 16)) ?> UTC</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
