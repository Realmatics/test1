<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 120);
ini_set('memory_limit', '256M');

header('Content-Type: application/json; charset=utf-8');

$dir = $_GET['dir'] ?? '';
$dir = preg_replace('/[^a-zA-Z0-9_-]/', '', $dir);
if (empty($dir) || !is_dir(__DIR__ . '/' . $dir)) {
    die(json_encode(['error' => 'Verzeichnis nicht gefunden']));
}

$targetDir = __DIR__ . '/' . $dir;
$htmlFiles = glob($targetDir . '/*.html');

$existingPages = [];
foreach ($htmlFiles as $f) {
    $existingPages[basename($f)] = true;
}

$infoFile = $targetDir . '/.clone_info';
$source = '';
if (file_exists($infoFile)) {
    $info = json_decode(file_get_contents($infoFile), true);
    $source = $info['source'] ?? '';
}

$parsed = parse_url($source);
$baseUrl = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');

$fixed = 0;
$totalLinks = 0;

foreach ($htmlFiles as $file) {
    $pageName = basename($file);
    if ($pageName === 'check.html') continue;
    
    $html = file_get_contents($file);
    if ($html === false) continue;
    
    $changed = false;
    
    $html = preg_replace_callback(
        '/(<a[^>]*\s)href=["\']([^"\'#]+)(#[^"\']*)?["\']/i',
        function($m) use ($baseUrl, $existingPages, &$totalLinks, &$changed) {
            $href = $m[2];
            $anchor = $m[3] ?? '';
            $totalLinks++;
            
            $path = '';
            if (strpos($href, $baseUrl) === 0) {
                $path = substr($href, strlen($baseUrl));
            } elseif (strpos($href, '/') === 0 && strpos($href, '//') !== 0) {
                $path = $href;
            }
            
            if (!empty($path)) {
                $path = trim($path, '/');
                if (empty($path)) {
                    $changed = true;
                    return $m[1] . 'href="index.html' . $anchor . '"';
                }
                
                $filename = preg_replace('/[^a-zA-Z0-9\/_-]/', '_', $path);
                $filename = str_replace('/', '_', $filename);
                $filename = trim($filename, '_');
                if (!preg_match('/\.(html|htm|php)$/', $filename)) $filename .= '.html';
                
                if (isset($existingPages[$filename])) {
                    $changed = true;
                    return $m[1] . 'href="' . $filename . $anchor . '"';
                }
            }
            
            return $m[0];
        },
        $html
    );
    
    if ($changed) {
        file_put_contents($file, $html);
        $fixed++;
    }
    
    unset($html);
    gc_collect_cycles();
}

echo json_encode([
    'pages_processed' => count($htmlFiles),
    'pages_with_fixes' => $fixed,
    'total_links_checked' => $totalLinks,
    'existing_pages' => count($existingPages)
]);
?>
