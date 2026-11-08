<?php

declare(strict_types=1);

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true,
]);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/plans.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/llm.php';
require_once __DIR__ . '/tools.php';

function render(string $view, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $user = current_user();
    $flash = take_flash();
    $cfg = config();
    require __DIR__ . '/../views/layout.php';
}

function view_path(string $view): string
{
    return __DIR__ . '/../views/' . $view . '.php';
}
