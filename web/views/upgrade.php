<?php
/** @var array $user */
/** @var array $cfg */
?>
<div class="page">
  <div class="wrap">
    <h1 style="font-size:2rem;max-width:none">Upgrade</h1>
    <p class="section-intro">Aktueller Plan: <strong><?= e(plan_of($user['plan'])['name']) ?></strong>.</p>

    <div class="grid-2">
      <div class="card-panel">
        <h3>Team — 79 € / Monat</h3>
        <p class="muted">Add-ons freischalten, mehr Briefs, Historie.</p>
        <?php if (!empty($cfg['stripe_payment_link_team'])): ?>
          <p style="margin-top:0.8rem"><a class="cta" href="<?= e($cfg['stripe_payment_link_team']) ?>" target="_blank" rel="noopener">Mit Stripe upgraden</a></p>
        <?php else: ?>
          <p style="margin-top:0.8rem">
            <a class="cta" href="mailto:<?= e($cfg['admin_email']) ?>?subject=Upgrade%20Team&body=Account:%20<?= rawurlencode($user['email']) ?>">Upgrade anfragen</a>
          </p>
        <?php endif; ?>
      </div>
      <div class="card-panel">
        <h3>Pro — 149 € / Monat</h3>
        <p class="muted">Mehr Volumen und API-ready Betrieb.</p>
        <?php if (!empty($cfg['stripe_payment_link_pro'])): ?>
          <p style="margin-top:0.8rem"><a class="cta" href="<?= e($cfg['stripe_payment_link_pro']) ?>" target="_blank" rel="noopener">Mit Stripe upgraden</a></p>
        <?php else: ?>
          <p style="margin-top:0.8rem">
            <a class="cta" href="mailto:<?= e($cfg['admin_email']) ?>?subject=Upgrade%20Pro&body=Account:%20<?= rawurlencode($user['email']) ?>">Upgrade anfragen</a>
          </p>
        <?php endif; ?>
      </div>
    </div>

    <div class="card-panel" style="margin-top:1.2rem">
      <h3>Admin-Freischaltung</h3>
      <p class="muted">Für dich als Betreiber: Plan direkt setzen mit Setup-Key aus <code>config.php</code>.</p>
      <form method="post" action="/upgrade/activate" style="margin-top:0.8rem">
        <?= csrf_field() ?>
        <label>Setup-Key
          <input type="password" name="setup_key" required />
        </label>
        <label>Plan
          <select name="plan">
            <option value="starter">Starter</option>
            <option value="team">Team</option>
            <option value="pro">Pro</option>
          </select>
        </label>
        <button class="btn" type="submit">Plan setzen</button>
      </form>
    </div>
  </div>
</div>
