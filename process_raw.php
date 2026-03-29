<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 120);
ini_set('memory_limit', '256M');

$dir = $_GET['dir'] ?? '';
$dir = preg_replace('/[^a-zA-Z0-9_-]/', '', $dir);
if (empty($dir) || !is_dir(__DIR__ . '/' . $dir)) {
    die(json_encode(['error' => 'Verzeichnis nicht gefunden']));
}

$targetDir = __DIR__ . '/' . $dir;
$rawFiles = glob($targetDir . '/*.raw');
$htmlFiles = glob($targetDir . '/*.html');

header('Content-Type: application/json; charset=utf-8');

if (empty($rawFiles)) {
    echo json_encode([
        'status' => 'complete',
        'pages_done' => count($htmlFiles),
        'pages_remaining' => 0,
        'message' => 'Alle Seiten sind bereits verarbeitet.'
    ]);
    exit;
}

$batch = intval($_GET['batch'] ?? 5);
$processed = 0;

require_once __DIR__ . '/clone_website.php';

foreach ($rawFiles as $rawFile) {
    if ($processed >= $batch) break;
    
    $pageName = basename($rawFile, '.raw');
    $html = file_get_contents($rawFile);
    if ($html === false) continue;
    
    $html = str_replace('</head>', '    <meta name="robots" content="noindex, nofollow">' . "\n" . '</head>', $html);
    
    file_put_contents($targetDir . '/' . $pageName, $html);
    unlink($rawFile);
    $processed++;
    unset($html);
    gc_collect_cycles();
}

$remaining = count(glob($targetDir . '/*.raw'));
$done = count(glob($targetDir . '/*.html'));

echo json_encode([
    'status' => $remaining > 0 ? 'processing' : 'complete',
    'pages_done' => $done,
    'pages_remaining' => $remaining,
    'processed_this_batch' => $processed,
    'message' => $remaining > 0 
        ? "$processed Seiten verarbeitet, $remaining verbleibend" 
        : "Alle $done Seiten verarbeitet!"
]);
?>
