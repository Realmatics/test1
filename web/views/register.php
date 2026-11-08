<div class="page">
  <div class="wrap" style="max-width:28rem">
    <h1 style="font-size:2rem;max-width:none">Registrieren</h1>
    <p class="section-intro">Starter inkl. 5 Trial-Briefs — sofort Decision Briefs erstellen.</p>
    <form method="post" action="/register" class="card-panel">
      <?= csrf_field() ?>
      <label>Name
        <input type="text" name="name" required autocomplete="name" />
      </label>
      <label>E-Mail
        <input type="email" name="email" required autocomplete="email" />
      </label>
      <label>Passwort (min. 8 Zeichen)
        <input type="password" name="password" required minlength="8" autocomplete="new-password" />
      </label>
      <div class="form-actions">
        <button class="cta" type="submit">Account erstellen</button>
        <a class="btn btn-ghost" href="/login">Zur Login-Seite</a>
      </div>
    </form>
  </div>
</div>
