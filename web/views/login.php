<div class="page">
  <div class="wrap" style="max-width:28rem">
    <h1 style="font-size:2rem;max-width:none">Login</h1>
    <p class="section-intro">Willkommen zurück.</p>
    <form method="post" action="/login" class="card-panel">
      <?= csrf_field() ?>
      <label>E-Mail
        <input type="email" name="email" required autocomplete="email" />
      </label>
      <label>Passwort
        <input type="password" name="password" required autocomplete="current-password" />
      </label>
      <div class="form-actions">
        <button class="cta" type="submit">Einloggen</button>
        <a class="btn btn-ghost" href="/register">Registrieren</a>
      </div>
    </form>
  </div>
</div>
