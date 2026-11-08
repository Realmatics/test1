<?php

declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';
$method = request_method();

try {
    route($method, $path);
} catch (Throwable $e) {
    if ($method === 'POST' && str_starts_with($path, '/app/')) {
        flash('error', $e->getMessage());
        redirect($path);
    }
    http_response_code(500);
    echo 'Fehler: ' . e($e->getMessage());
}

function route(string $method, string $path): void
{
    if ($path === '/') {
        render('home', ['view' => 'home', 'title' => 'Realmatics — Decision Tools']);
        return;
    }

    if ($path === '/login') {
        if ($method === 'POST') {
            verify_csrf();
            try {
                $user = attempt_login(post_string('email'), (string) ($_POST['password'] ?? ''));
                login_user($user);
                flash('success', 'Willkommen zurück.');
                redirect('/app');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
                redirect('/login');
            }
        }
        render('login', ['view' => 'login', 'title' => 'Login — Realmatics']);
        return;
    }

    if ($path === '/register') {
        if ($method === 'POST') {
            verify_csrf();
            try {
                $user = register_user(post_string('email'), post_string('name'), (string) ($_POST['password'] ?? ''));
                login_user($user);
                flash('success', 'Account erstellt. Du hast 5 Trial-Briefs.');
                redirect('/app');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
                redirect('/register');
            }
        }
        render('register', ['view' => 'register', 'title' => 'Registrieren — Realmatics']);
        return;
    }

    if ($path === '/logout') {
        logout_user();
        redirect('/');
    }

    if ($path === '/app') {
        $user = require_login();
        render('dashboard', [
            'view' => 'dashboard',
            'title' => 'Dashboard — Realmatics',
            'user' => $user,
            'plan' => plan_of($user['plan']),
            'remaining' => remaining_briefs($user),
            'briefs' => list_briefs((int) $user['id']),
            'apiMode' => llm_available(),
        ]);
        return;
    }

    if ($path === '/app/decision') {
        handle_tool('decision_brief', '/app/decision', 'Decision Brief', 'Füge Mails, Notizen oder ein Entscheidungsproblem ein.', 'Was ist die Entscheidung? Welche Optionen liegen auf dem Tisch? Welche Constraints gibt es?');
        return;
    }

    if ($path === '/app/offer') {
        $user = require_login();
        if (!user_can_use_addon($user)) {
            flash('error', 'Offer Score ist ab Team freigeschaltet.');
            redirect('/upgrade');
        }
        handle_tool('offer_score', '/app/offer', 'Offer Score', 'Füge Angebots- oder Vendor-Text ein.', 'Preis, Leistungen, Laufzeit, Risiken, Alternativen…');
        return;
    }

    if ($path === '/app/impact') {
        $user = require_login();
        if (!user_can_use_addon($user)) {
            flash('error', 'Change Impact Check ist ab Team freigeschaltet.');
            redirect('/upgrade');
        }
        handle_tool('impact_check', '/app/impact', 'Change Impact Check', 'Beschreibe den geplanten Change oder Release.', 'Was ändert sich? Welche Systeme/Kunden sind betroffen? Deadline?');
        return;
    }

    if (preg_match('#^/app/brief/(\d+)$#', $path, $m)) {
        $user = require_login();
        $brief = get_brief((int) $m[1], (int) $user['id']);
        if (!$brief) {
            flash('error', 'Brief nicht gefunden.');
            redirect('/app');
        }
        $result = json_decode($brief['result_json'], true) ?: [];
        render('brief_show', [
            'view' => 'brief_show',
            'title' => ($result['title'] ?? 'Brief') . ' — Realmatics',
            'brief' => $brief,
            'result' => $result,
        ]);
        return;
    }

    if ($path === '/upgrade') {
        $user = require_login();
        render('upgrade', [
            'view' => 'upgrade',
            'title' => 'Upgrade — Realmatics',
            'user' => $user,
            'cfg' => config(),
        ]);
        return;
    }

    if ($path === '/upgrade/activate' && $method === 'POST') {
        $user = require_login();
        verify_csrf();
        $key = (string) ($_POST['setup_key'] ?? '');
        $plan = post_string('plan', 'starter');
        if (!hash_equals((string) config()['admin_setup_key'], $key)) {
            flash('error', 'Setup-Key ungültig.');
            redirect('/upgrade');
        }
        set_user_plan((int) $user['id'], $plan);
        flash('success', 'Plan auf ' . plan_of($plan)['name'] . ' gesetzt.');
        redirect('/app');
    }

    http_response_code(404);
    echo 'Seite nicht gefunden.';
}

function handle_tool(string $type, string $action, string $title, string $hint, string $placeholder): void
{
    $user = require_login();

    if (request_method() === 'POST') {
        verify_csrf();
        $input = post_string('input');
        $briefTitle = post_string('title');
        try {
            consume_brief_credit($user);
            $result = match ($type) {
                'decision_brief' => run_decision_brief($input, $briefTitle),
                'offer_score' => run_offer_score($input, $briefTitle),
                'impact_check' => run_impact_check($input, $briefTitle),
                default => throw new RuntimeException('Unbekanntes Tool.'),
            };
            $id = save_brief((int) $user['id'], $result, $input);
            flash('success', 'Auswertung fertig.');
            redirect('/app/brief/' . $id);
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
            redirect($action);
        }
    }

    render('tool_form', [
        'view' => 'tool_form',
        'title' => $title . ' — Realmatics',
        'toolTitle' => $title,
        'toolHint' => $hint,
        'action' => $action,
        'placeholder' => $placeholder,
    ]);
}
