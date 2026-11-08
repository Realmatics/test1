<?php
/** @var string $view */
/** @var array|null $user */
/** @var array|null $flash */
/** @var array $cfg */
?><!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= e($title ?? ($cfg['app_name'] . ' — Decision Tools')) ?></title>
  <meta name="description" content="Decision Brief, Offer Score und Change Impact Check — spezialisierte Entscheidungstools." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,700&family=Source+Sans+3:wght@400;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/assets/app.css" />
</head>
<body>
  <div class="shell">
    <div class="grain" aria-hidden="true"></div>
    <div class="orb" aria-hidden="true"></div>

    <header>
      <a class="brand" href="/">Real<span>matics</span></a>
      <nav>
        <a href="/#tools">Tools</a>
        <a href="/#preise">Preise</a>
        <?php if ($user): ?>
          <a href="/app">Dashboard</a>
          <a href="/logout">Logout</a>
        <?php else: ?>
          <a href="/login">Login</a>
          <a href="/register">Registrieren</a>
        <?php endif; ?>
      </nav>
    </header>

    <?php if ($flash): ?>
      <div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <?php require view_path($view); ?>

    <footer>
      <div class="wrap">Realmatics — Decision Tools für Teams, die liefern müssen.</div>
    </footer>
  </div>
</body>
</html>
