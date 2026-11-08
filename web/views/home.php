<section class="hero" id="start">
  <div class="wrap">
    <h1>Werkzeuge für klare Entscheidungen.</h1>
    <p class="lede">Nicht irgendein Chat — ein fester Ablauf für Optionen, Risiken und den nächsten Schritt.</p>
    <a class="cta" href="<?= $user ? '/app/decision' : '/register' ?>">Ersten Decision Brief erstellen</a>
  </div>
</section>

<section id="tools">
  <div class="wrap">
    <h2>Ein Kern. Zwei Add-ons.</h2>
    <p class="section-intro">Starte mit Decision Brief. Schalte Offer Score und Change Impact Check dazu, wenn du sie brauchst.</p>
    <div class="stack">
      <article class="item">
        <div class="tag">Kern</div>
        <div>
          <h3>Decision Brief</h3>
          <p>Aus Mails, PDFs und Notizen eine einseitige Entscheidung: Optionen, Annahmen, Risiken, Empfehlung, Next Step.</p>
        </div>
      </article>
      <article class="item">
        <div class="tag">Add-on</div>
        <div>
          <h3>Offer Score</h3>
          <p>Angebote und Vendor-Vergleiche bewerten — Ampel für Preis, Risiko und Fit, nachvollziehbar begründet.</p>
        </div>
      </article>
      <article class="item">
        <div class="tag">Add-on</div>
        <div>
          <h3>Change Impact Check</h3>
          <p>Vor Releases und Prozessänderungen: was bricht, wen betrifft’s, Go oder No-Go.</p>
        </div>
      </article>
    </div>
  </div>
</section>

<section id="warum">
  <div class="wrap">
    <h2>„Das kann meine KI doch auch?“</h2>
    <p class="section-intro">Stimmt — Zugang zu Mails und Dateien reicht oft für eine Meinung. Realmatics macht daraus eine wiederholbare Entscheidung.</p>
    <div class="diff-points">
      <p>Fester Output statt freier Antwort: immer dieselbe Brief-Struktur, die du teilen und abhaken kannst.</p>
      <p>Entscheidungsspezifische Bewertung — Optionen, Risiken und Empfehlung statt allgemeinem Zusammenfassen.</p>
      <p>Nachvollziehbarkeit für Teams: Historie, Templates und Add-ons für Angebote sowie Change-Impact.</p>
    </div>
  </div>
</section>

<section id="preise">
  <div class="wrap">
    <h2>Preise</h2>
    <p class="section-intro">Transparent starten. Add-ons kommen ab Team dazu.</p>
    <table class="pricing-table">
      <thead>
        <tr>
          <th>Plan</th>
          <th>Preis</th>
          <th>Für wen</th>
          <th>Enthalten</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (plans() as $plan): ?>
          <tr>
            <th scope="row"><?= e($plan['name']) ?></th>
            <td class="price"><?= (int) $plan['price'] ?> € / Monat</td>
            <td><?= e($plan['audience']) ?></td>
            <td>
              <?= e($plan['blurb']) ?>
              <?php if ($plan['id'] === 'starter'): ?>
                <span class="note">Plus 5 Trial-Briefs zum Start.</span>
              <?php elseif ($plan['addons']): ?>
                <span class="note">Add-ons freigeschaltet.</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="trial">Neu registrieren: <strong>Starter inkl. 5 Trial-Briefs</strong> — sofort nutzbar.</p>
    <div class="form-actions" style="margin-top:1.25rem">
      <a class="cta" href="/register">Jetzt starten</a>
      <a class="btn btn-ghost" href="/login">Ich habe schon einen Account</a>
    </div>
  </div>
</section>
