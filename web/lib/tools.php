<?php

declare(strict_types=1);

function run_decision_brief(string $input, string $title = ''): array
{
    $input = trim($input);
    if (mb_strlen($input) < 40) {
        throw new InvalidArgumentException('Bitte mehr Kontext liefern (mindestens ~40 Zeichen).');
    }

    $system = <<<'SYS'
Du bist Realmatics Decision Brief. Antworte NUR als JSON mit genau diesen Keys:
context (string, max 5 Sätze),
options (array of {name, pros, cons}),
risks (array of strings),
assumptions (array of strings),
recommendation (string),
next_step (string),
confidence (number 0-1).
Maximal 4 Optionen. Sei konkret und entscheidungsorientiert, kein allgemeines Geschwafel.
SYS;

    if (llm_available()) {
        $json = llm_chat($system, "Titel: {$title}\n\nKontext:\n{$input}");
        return normalize_decision($json, $title, 'api');
    }
    return normalize_decision(demo_decision($input, $title), $title, 'demo');
}

function run_offer_score(string $input, string $title = ''): array
{
    $input = trim($input);
    if (mb_strlen($input) < 40) {
        throw new InvalidArgumentException('Bitte Angebotsdetails einfügen (mindestens ~40 Zeichen).');
    }

    $system = <<<'SYS'
Du bist Realmatics Offer Score. Antworte NUR als JSON mit:
summary (string),
scores ({price:0-100, risk:0-100, fit:0-100}),
traffic_light (green|yellow|red),
reasons (array of strings),
recommendation (string),
next_step (string).
Höhere price-Scores = besseres Preis/Leistung. Höhere risk-Scores = höheres Risiko.
SYS;

    if (llm_available()) {
        $json = llm_chat($system, "Titel: {$title}\n\nAngebot:\n{$input}");
        return normalize_offer($json, $title, 'api');
    }
    return normalize_offer(demo_offer($input, $title), $title, 'demo');
}

function run_impact_check(string $input, string $title = ''): array
{
    $input = trim($input);
    if (mb_strlen($input) < 40) {
        throw new InvalidArgumentException('Bitte Change-/Release-Kontext einfügen (mindestens ~40 Zeichen).');
    }

    $system = <<<'SYS'
Du bist Realmatics Change Impact Check. Antworte NUR als JSON mit:
summary (string),
impacts (array of {area, severity: low|medium|high, detail}),
affected (array of strings),
go_no_go (go|no-go|conditional),
conditions (array of strings),
next_step (string).
SYS;

    if (llm_available()) {
        $json = llm_chat($system, "Titel: {$title}\n\nChange:\n{$input}");
        return normalize_impact($json, $title, 'api');
    }
    return normalize_impact(demo_impact($input, $title), $title, 'demo');
}

function normalize_decision(array $json, string $title, string $mode): array
{
    $options = [];
    foreach (($json['options'] ?? []) as $opt) {
        if (!is_array($opt)) {
            continue;
        }
        $options[] = [
            'name' => (string) ($opt['name'] ?? 'Option'),
            'pros' => array_values(array_map('strval', (array) ($opt['pros'] ?? []))),
            'cons' => array_values(array_map('strval', (array) ($opt['cons'] ?? []))),
        ];
    }
    return [
        'type' => 'decision_brief',
        'title' => $title !== '' ? $title : 'Decision Brief',
        'mode' => $mode,
        'context' => (string) ($json['context'] ?? ''),
        'options' => array_slice($options, 0, 4),
        'risks' => array_values(array_map('strval', (array) ($json['risks'] ?? []))),
        'assumptions' => array_values(array_map('strval', (array) ($json['assumptions'] ?? []))),
        'recommendation' => (string) ($json['recommendation'] ?? ''),
        'next_step' => (string) ($json['next_step'] ?? ''),
        'confidence' => (float) ($json['confidence'] ?? 0.55),
    ];
}

function normalize_offer(array $json, string $title, string $mode): array
{
    $scores = (array) ($json['scores'] ?? []);
    $light = (string) ($json['traffic_light'] ?? 'yellow');
    if (!in_array($light, ['green', 'yellow', 'red'], true)) {
        $light = 'yellow';
    }
    return [
        'type' => 'offer_score',
        'title' => $title !== '' ? $title : 'Offer Score',
        'mode' => $mode,
        'summary' => (string) ($json['summary'] ?? ''),
        'scores' => [
            'price' => (int) ($scores['price'] ?? 50),
            'risk' => (int) ($scores['risk'] ?? 50),
            'fit' => (int) ($scores['fit'] ?? 50),
        ],
        'traffic_light' => $light,
        'reasons' => array_values(array_map('strval', (array) ($json['reasons'] ?? []))),
        'recommendation' => (string) ($json['recommendation'] ?? ''),
        'next_step' => (string) ($json['next_step'] ?? ''),
    ];
}

function normalize_impact(array $json, string $title, string $mode): array
{
    $impacts = [];
    foreach (($json['impacts'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $sev = (string) ($row['severity'] ?? 'medium');
        if (!in_array($sev, ['low', 'medium', 'high'], true)) {
            $sev = 'medium';
        }
        $impacts[] = [
            'area' => (string) ($row['area'] ?? 'Allgemein'),
            'severity' => $sev,
            'detail' => (string) ($row['detail'] ?? ''),
        ];
    }
    $go = (string) ($json['go_no_go'] ?? 'conditional');
    if (!in_array($go, ['go', 'no-go', 'conditional'], true)) {
        $go = 'conditional';
    }
    return [
        'type' => 'impact_check',
        'title' => $title !== '' ? $title : 'Change Impact Check',
        'mode' => $mode,
        'summary' => (string) ($json['summary'] ?? ''),
        'impacts' => $impacts,
        'affected' => array_values(array_map('strval', (array) ($json['affected'] ?? []))),
        'go_no_go' => $go,
        'conditions' => array_values(array_map('strval', (array) ($json['conditions'] ?? []))),
        'next_step' => (string) ($json['next_step'] ?? ''),
    ];
}

function demo_decision(string $input, string $title): array
{
    $bullets = extract_bullet_candidates($input);
    $sentences = split_sentences($input);
    $options = [];
    if (count($bullets) >= 2) {
        foreach (array_slice($bullets, 0, 3) as $i => $b) {
            $options[] = [
                'name' => 'Option ' . chr(65 + $i) . ': ' . mb_strimwidth($b, 0, 80, '…'),
                'pros' => ['Direkt aus dem vorliegenden Kontext ableitbar'],
                'cons' => ['Details und Abhängigkeiten noch nicht vollständig belegt'],
            ];
        }
    } else {
        $options = [
            [
                'name' => 'Option A: Jetzt entscheiden und umsetzen',
                'pros' => ['Tempo', 'Klarer Next Step'],
                'cons' => ['Restunsicherheit bleibt'],
            ],
            [
                'name' => 'Option B: 48h gezielte Klärung, dann entscheiden',
                'pros' => ['Weniger Risiko', 'Bessere Datenlage'],
                'cons' => ['Verzögerung'],
            ],
            [
                'name' => 'Option C: Parken / nicht tun',
                'pros' => ['Keine Fehlallokation'],
                'cons' => ['Opportunitätskosten'],
            ],
        ];
    }

    $context = implode(' ', array_slice($sentences, 0, 3));
    if ($context === '') {
        $context = mb_strimwidth($input, 0, 280, '…');
    }

    return [
        'context' => $context,
        'options' => $options,
        'risks' => [
            'Unvollständige Annahmen im Input können die Empfehlung verzerren.',
            'Stakeholder-Abhängigkeiten sind möglicherweise nicht vollständig erfasst.',
        ],
        'assumptions' => [
            'Der gelieferte Text beschreibt den aktuellen Entscheidungsstand hinreichend.',
            'Demo-Modus: ohne API-Key lokal strukturiert — mit API-Key deutlich schärfer.',
        ],
        'recommendation' => 'Option B bevorzugen: offene Punkte in 48 Stunden klären, dann verbindlich entscheiden.',
        'next_step' => 'Liste die 3 größten Unbekannten, owner zuweisen, Deadline setzen, danach Decision Brief erneut laufen lassen.',
        'confidence' => 0.52,
    ];
}

function demo_offer(string $input, string $title): array
{
    $lower = mb_strtolower($input);
    $price = 55;
    $risk = 45;
    $fit = 60;
    if (preg_match('/\b(günstig|rabatt|preiswert|budget)\b/u', $lower)) {
        $price += 15;
    }
    if (preg_match('/\b(teuer|premium|enterprise)\b/u', $lower)) {
        $price -= 10;
        $fit += 5;
    }
    if (preg_match('/\b(risiko|lock-in|kündigung|strafe|haftung)\b/u', $lower)) {
        $risk += 20;
    }
    if (preg_match('/\b(sla|support|referenz|passend|fit)\b/u', $lower)) {
        $fit += 15;
        $risk -= 5;
    }
    $price = max(0, min(100, $price));
    $risk = max(0, min(100, $risk));
    $fit = max(0, min(100, $fit));
    $score = ($price * 0.35) + ((100 - $risk) * 0.35) + ($fit * 0.30);
    $light = $score >= 70 ? 'green' : ($score >= 50 ? 'yellow' : 'red');

    return [
        'summary' => 'Demo-Bewertung auf Basis von Signalwörtern im Angebotstext.',
        'scores' => compact('price', 'risk', 'fit'),
        'traffic_light' => $light,
        'reasons' => [
            'Preis/Leistung heuristisch aus Formulierungen geschätzt.',
            'Risiko aus Klausel-/Risiko-Signalen abgeleitet.',
            'Fit aus Passungs- und Support-Signalen abgeleitet.',
        ],
        'recommendation' => $light === 'green'
            ? 'Angebot ist vertretbar — Verhandlung auf Restpunkte fokussieren.'
            : ($light === 'yellow'
                ? 'Bedingt geeignet — kritische Punkte vor Zusage klären.'
                : 'Aktuell eher ablehnen oder stark nachverhandeln.'),
        'next_step' => 'Drei Gegenfragen an den Vendor schicken: Preisbestandteile, Exit-Klausel, Referenz in ähnlicher Größe.',
    ];
}

function demo_impact(string $input, string $title): array
{
    $lower = mb_strtolower($input);
    $high = preg_match('/\b(prod|production|kunden|breaking|migration|datenbank|auth)\b/u', $lower) === 1;
    $go = $high ? 'conditional' : 'go';

    return [
        'summary' => 'Demo-Impact aus Schlüsselbegriffen im Change-Text.',
        'impacts' => [
            [
                'area' => 'Technik',
                'severity' => $high ? 'high' : 'medium',
                'detail' => $high
                    ? 'Produktive oder sensible Komponenten scheinen betroffen.'
                    : 'Änderungen wirken begrenzt und lokal.',
            ],
            [
                'area' => 'Nutzer / Kunden',
                'severity' => preg_match('/\b(ui|kunden|nutzer|ux)\b/u', $lower) ? 'medium' : 'low',
                'detail' => 'Sichtbarkeit für Endnutzer anhand der Beschreibung geschätzt.',
            ],
            [
                'area' => 'Betrieb',
                'severity' => 'medium',
                'detail' => 'Rollback- und Monitoring-Bedarf vor Go-Live prüfen.',
            ],
        ],
        'affected' => ['Engineering', 'Support', 'ggf. Kundenkommunikation'],
        'go_no_go' => $go,
        'conditions' => [
            'Rollback-Plan schriftlich festhalten',
            'Smoke-Test auf Staging erfolgreich',
            'Owner für Incident-Fenster benennen',
        ],
        'next_step' => 'Checkliste abarbeiten, dann freigeben oder auf No-Go eskalieren.',
    ];
}

function save_brief(int $userId, array $result, string $input): int
{
    $stmt = db()->prepare(
        'INSERT INTO briefs (user_id, type, title, input_text, result_json, mode, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $userId,
        $result['type'],
        $result['title'],
        $input,
        json_encode($result, JSON_UNESCAPED_UNICODE),
        $result['mode'],
        gmdate('c'),
    ]);
    return (int) db()->lastInsertId();
}

function get_brief(int $id, int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM briefs WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function list_briefs(int $userId, int $limit = 30): array
{
    $stmt = db()->prepare('SELECT id, type, title, mode, created_at FROM briefs WHERE user_id = ? ORDER BY id DESC LIMIT ?');
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}
