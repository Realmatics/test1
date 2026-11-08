<?php

declare(strict_types=1);

function plans(): array
{
    return [
        'starter' => [
            'id' => 'starter',
            'name' => 'Starter',
            'price' => 29,
            'audience' => 'Solo',
            'monthly_briefs' => 30,
            'addons' => false,
            'api' => false,
            'blurb' => '30 Decision Briefs, Export',
        ],
        'team' => [
            'id' => 'team',
            'name' => 'Team',
            'price' => 79,
            'audience' => '3–10 Personen',
            'monthly_briefs' => 120,
            'addons' => true,
            'api' => false,
            'blurb' => 'Templates, Historie, Offer Score & Change Impact Check',
        ],
        'pro' => [
            'id' => 'pro',
            'name' => 'Pro',
            'price' => 149,
            'audience' => 'Ops / Agency',
            'monthly_briefs' => 400,
            'addons' => true,
            'api' => true,
            'blurb' => 'Alles aus Team, mehr Volumen, API-ready',
        ],
    ];
}

function plan_of(string $id): array
{
    $all = plans();
    return $all[$id] ?? $all['starter'];
}

function user_can_use_addon(array $user): bool
{
    return (bool) plan_of($user['plan'])['addons'];
}

function usage_for(int $userId, ?string $month = null): int
{
    $month ??= month_key();
    $stmt = db()->prepare('SELECT briefs_used FROM usage_monthly WHERE user_id = ? AND month = ?');
    $stmt->execute([$userId, $month]);
    $row = $stmt->fetch();
    return (int) ($row['briefs_used'] ?? 0);
}

function remaining_briefs(array $user): int
{
    $plan = plan_of($user['plan']);
    $used = usage_for((int) $user['id']);
    $monthlyLeft = max(0, (int) $plan['monthly_briefs'] - $used);
    // Trial briefs are extra on top for new accounts still holding trial credits.
    return $monthlyLeft + max(0, (int) $user['trial_briefs_remaining']);
}

function consume_brief_credit(array &$user): void
{
    $pdo = db();
    $month = month_key();
    $plan = plan_of($user['plan']);
    $used = usage_for((int) $user['id'], $month);

    if ($used < (int) $plan['monthly_briefs']) {
        $pdo->prepare(
            'INSERT INTO usage_monthly (user_id, month, briefs_used) VALUES (?, ?, 1)
             ON CONFLICT(user_id, month) DO UPDATE SET briefs_used = briefs_used + 1'
        )->execute([(int) $user['id'], $month]);
        return;
    }

    if ((int) $user['trial_briefs_remaining'] > 0) {
        $pdo->prepare('UPDATE users SET trial_briefs_remaining = trial_briefs_remaining - 1 WHERE id = ?')
            ->execute([(int) $user['id']]);
        $user['trial_briefs_remaining'] = (int) $user['trial_briefs_remaining'] - 1;
        return;
    }

    throw new RuntimeException('Monatliches Brief-Kontingent aufgebraucht. Bitte upgraden.');
}
