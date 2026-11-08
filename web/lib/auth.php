<?php

declare(strict_types=1);

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('error', 'Bitte zuerst anmelden.');
        redirect('/login');
    }
    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function register_user(string $email, string $name, string $password): array
{
    $email = strtolower(trim($email));
    $name = trim($name);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Bitte eine gültige E-Mail angeben.');
    }
    if (strlen($name) < 2) {
        throw new InvalidArgumentException('Bitte einen Namen angeben.');
    }
    if (strlen($password) < 8) {
        throw new InvalidArgumentException('Passwort mindestens 8 Zeichen.');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = db()->prepare(
        'INSERT INTO users (email, name, password_hash, plan, trial_briefs_remaining, created_at)
         VALUES (?, ?, ?, \'starter\', 5, ?)'
    );
    try {
        $stmt->execute([$email, $name, $hash, gmdate('c')]);
    } catch (PDOException $e) {
        throw new InvalidArgumentException('Diese E-Mail ist bereits registriert.');
    }

    $id = (int) db()->lastInsertId();
    $user = db()->query('SELECT * FROM users WHERE id = ' . $id)->fetch();
    return $user;
}

function attempt_login(string $email, string $password): array
{
    $email = strtolower(trim($email));
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        throw new InvalidArgumentException('E-Mail oder Passwort ist falsch.');
    }
    return $user;
}

function set_user_plan(int $userId, string $plan): void
{
    if (!isset(plans()[$plan])) {
        throw new InvalidArgumentException('Unbekannter Plan.');
    }
    db()->prepare('UPDATE users SET plan = ? WHERE id = ?')->execute([$plan, $userId]);
}
