<?php

declare(strict_types=1);

function llm_available(): bool
{
    return trim((string) (config()['openai_api_key'] ?? '')) !== '';
}

function llm_chat(string $system, string $user, float $temperature = 0.2): array
{
    $key = trim((string) (config()['openai_api_key'] ?? ''));
    if ($key === '') {
        throw new RuntimeException('Kein API-Key konfiguriert.');
    }

    $base = rtrim((string) config()['openai_base_url'], '/');
    $model = (string) config()['openai_model'];
    $payload = [
        'model' => $model,
        'temperature' => $temperature,
        'response_format' => ['type' => 'json_object'],
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ],
    ];

    $ch = curl_init($base . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 90,
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno || $raw === false) {
        throw new RuntimeException('LLM-Anfrage fehlgeschlagen (Netzwerk).');
    }
    $decoded = json_decode($raw, true);
    if ($status >= 400) {
        $msg = $decoded['error']['message'] ?? ('HTTP ' . $status);
        throw new RuntimeException('LLM-Fehler: ' . $msg);
    }
    $content = $decoded['choices'][0]['message']['content'] ?? '';
    $json = json_decode($content, true);
    if (!is_array($json)) {
        throw new RuntimeException('LLM lieferte kein gültiges JSON.');
    }
    return $json;
}

function split_sentences(string $text): array
{
    $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';
    if ($text === '') {
        return [];
    }
    $parts = preg_split('/(?<=[.!?])\s+/u', $text) ?: [];
    return array_values(array_filter(array_map('trim', $parts), fn ($s) => $s !== ''));
}

function extract_bullet_candidates(string $text): array
{
    $lines = preg_split('/\R/u', $text) ?: [];
    $out = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (preg_match('/^(?:[-*•]|\d+[.)])\s+(.+)$/u', $line, $m)) {
            $out[] = trim($m[1]);
        }
    }
    return $out;
}
