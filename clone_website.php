<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 120);
ini_set('memory_limit', '256M');

class WebsiteCloner {
    private $sourceUrl;
    private $targetDir;
    private $baseUrl;
    private $host;
    private $scheme;
    private $downloaded = [];
    private $log = [];
    
    public function __construct($url, $targetDir) {
        $parsed = parse_url($url);
        $this->scheme = $parsed['scheme'] ?? 'https';
        $this->host = $parsed['host'] ?? '';
        $this->baseUrl = $this->scheme . '://' . $this->host;
        $this->sourceUrl = rtrim($url, '/');
        $this->targetDir = rtrim($targetDir, '/');
    }
    
    public function clone_site() {
        $this->log[] = "Starte Kopie von: " . $this->sourceUrl;
        
        if (!is_dir($this->targetDir)) {
            mkdir($this->targetDir, 0755, true);
        }
        mkdir($this->targetDir . '/css', 0755, true);
        mkdir($this->targetDir . '/js', 0755, true);
        mkdir($this->targetDir . '/images', 0755, true);
        mkdir($this->targetDir . '/fonts', 0755, true);
        
        $html = $this->fetchUrl($this->sourceUrl);
        if (!$html) {
            $this->log[] = "FEHLER: Konnte Seite nicht laden!";
            return ['success' => false, 'log' => $this->log];
        }
        $this->log[] = "HTML geladen (" . strlen($html) . " Bytes)";
        
        $html = $this->downloadAndRewriteCSS($html);
        $html = $this->downloadAndRewriteJS($html);
        $html = $this->downloadAndRewriteImages($html);
        $html = $this->downloadAndRewriteFonts($html);
        $html = $this->rewriteInternalLinks($html);
        $html = $this->processInlineStyles($html);
        $html = $this->cleanupHTML($html);
        
        file_put_contents($this->targetDir . '/index.html', $html);
        $this->log[] = "index.html gespeichert";
        
        $this->addProtection();
        
        $this->log[] = "Fertig! " . count($this->downloaded) . " Dateien heruntergeladen.";
        return ['success' => true, 'log' => $this->log, 'files' => count($this->downloaded)];
    }
    
    private function fetchUrl($url) {
        if (isset($this->downloaded[$url])) return null;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_ENCODING => '',
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 400 && $response !== false) {
            return $response;
        }
        $this->log[] = "Fehler beim Laden: $url (HTTP $httpCode)";
        return null;
    }
    
    private function resolveUrl($relativeUrl) {
        $relativeUrl = trim($relativeUrl);
        if (empty($relativeUrl) || $relativeUrl === '#') return null;
        if (strpos($relativeUrl, 'data:') === 0) return null;
        if (strpos($relativeUrl, 'mailto:') === 0) return null;
        if (strpos($relativeUrl, 'tel:') === 0) return null;
        if (strpos($relativeUrl, 'javascript:') === 0) return null;
        
        if (strpos($relativeUrl, '//') === 0) {
            return $this->scheme . ':' . $relativeUrl;
        }
        if (preg_match('#^https?://#', $relativeUrl)) {
            return $relativeUrl;
        }
        if (strpos($relativeUrl, '/') === 0) {
            return $this->baseUrl . $relativeUrl;
        }
        return $this->sourceUrl . '/' . $relativeUrl;
    }
    
    private function downloadAsset($url, $subdir, $customName = null) {
        $fullUrl = $this->resolveUrl($url);
        if (!$fullUrl) return null;
        if (isset($this->downloaded[$fullUrl])) return $this->downloaded[$fullUrl];
        
        $content = $this->fetchUrl($fullUrl);
        if (!$content) return null;
        
        if ($customName) {
            $filename = $customName;
        } else {
            $parsed = parse_url($fullUrl);
            $path = $parsed['path'] ?? '/file';
            $filename = basename($path);
            $filename = preg_replace('/\?.*$/', '', $filename);
            if (empty(pathinfo($filename, PATHINFO_EXTENSION))) {
                $filename .= '.css';
            }
            $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        }
        
        $counter = 0;
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $saveName = $filename;
        while (file_exists($this->targetDir . '/' . $subdir . '/' . $saveName)) {
            $counter++;
            $saveName = $base . '_' . $counter . '.' . $ext;
        }
        
        file_put_contents($this->targetDir . '/' . $subdir . '/' . $saveName, $content);
        $localPath = $subdir . '/' . $saveName;
        $this->downloaded[$fullUrl] = $localPath;
        $this->log[] = "  ↓ $localPath";
        
        return $localPath;
    }
    
    private function downloadAndRewriteCSS(&$html) {
        // <link rel="stylesheet" href="...">
        $html = preg_replace_callback(
            '/<link[^>]*rel=["\']stylesheet["\'][^>]*href=["\']([^"\']+)["\'][^>]*>/i',
            function($matches) {
                $url = $matches[1];
                $localPath = $this->downloadCSSWithDeps($url);
                if ($localPath) {
                    return '<link rel="stylesheet" href="' . $localPath . '">';
                }
                return $matches[0];
            },
            $html
        );
        
        // Also catch href before rel
        $html = preg_replace_callback(
            '/<link[^>]*href=["\']([^"\']+\.css[^"\']*)["\'][^>]*>/i',
            function($matches) {
                if (strpos($matches[0], 'stylesheet') === false && strpos($matches[0], 'style') === false) {
                    return $matches[0];
                }
                $url = $matches[1];
                $localPath = $this->downloadCSSWithDeps($url);
                if ($localPath) {
                    return '<link rel="stylesheet" href="' . $localPath . '">';
                }
                return $matches[0];
            },
            $html
        );
        
        // Inline <style> blocks with url() references
        $html = preg_replace_callback(
            '/<style[^>]*>(.*?)<\/style>/si',
            function($matches) {
                $css = $this->rewriteCSSUrls($matches[1], $this->sourceUrl);
                return '<style>' . $css . '</style>';
            },
            $html
        );
        
        return $html;
    }
    
    private function downloadCSSWithDeps($url) {
        $fullUrl = $this->resolveUrl($url);
        if (!$fullUrl || isset($this->downloaded[$fullUrl])) {
            return $this->downloaded[$fullUrl] ?? null;
        }
        
        $content = $this->fetchUrl($fullUrl);
        if (!$content) return null;
        
        $content = $this->rewriteCSSUrls($content, $fullUrl);
        
        $parsed = parse_url($fullUrl);
        $filename = basename($parsed['path'] ?? 'style.css');
        $filename = preg_replace('/\?.*$/', '', $filename);
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        if (empty(pathinfo($filename, PATHINFO_EXTENSION))) {
            $filename .= '.css';
        }
        
        $counter = 0;
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $saveName = $filename;
        while (file_exists($this->targetDir . '/css/' . $saveName)) {
            $counter++;
            $saveName = $base . '_' . $counter . '.' . $ext;
        }
        
        file_put_contents($this->targetDir . '/css/' . $saveName, $content);
        $localPath = 'css/' . $saveName;
        $this->downloaded[$fullUrl] = $localPath;
        $this->log[] = "  ↓ $localPath";
        
        return $localPath;
    }
    
    private function resolveRelativePath($base, $relative) {
        $relative = trim($relative);
        if (preg_match('#^https?://#', $relative)) return $relative;
        if (strpos($relative, '//') === 0) return $this->scheme . ':' . $relative;
        if (strpos($relative, 'data:') === 0) return $relative;
        
        $parsed = parse_url($base);
        $baseScheme = $parsed['scheme'] ?? $this->scheme;
        $baseHost = $parsed['host'] ?? $this->host;
        $basePath = $parsed['path'] ?? '/';
        
        if (strpos($relative, '/') === 0) {
            return $baseScheme . '://' . $baseHost . $relative;
        }
        
        $baseDir = rtrim(dirname($basePath), '/');
        $combined = $baseDir . '/' . $relative;
        
        $parts = explode('/', $combined);
        $resolved = [];
        foreach ($parts as $part) {
            if ($part === '..') {
                array_pop($resolved);
            } elseif ($part !== '.' && $part !== '') {
                $resolved[] = $part;
            }
        }
        
        return $baseScheme . '://' . $baseHost . '/' . implode('/', $resolved);
    }
    
    private function rewriteCSSUrls($css, $cssFileUrl) {
        return preg_replace_callback(
            '/url\s*\(\s*["\']?([^"\')\s]+)["\']?\s*\)/i',
            function($matches) use ($cssFileUrl) {
                $assetUrl = trim($matches[1]);
                if (strpos($assetUrl, 'data:') === 0) return $matches[0];
                
                $assetUrl = $this->resolveRelativePath($cssFileUrl, $assetUrl);
                
                $ext = strtolower(pathinfo(parse_url($assetUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
                $fontExts = ['woff', 'woff2', 'ttf', 'eot', 'otf', 'svg'];
                $imgExts = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico'];
                
                if (in_array($ext, $fontExts)) {
                    $localPath = $this->downloadAsset($assetUrl, 'fonts');
                    if ($localPath) return 'url(../' . $localPath . ')';
                } elseif (in_array($ext, $imgExts)) {
                    $localPath = $this->downloadAsset($assetUrl, 'images');
                    if ($localPath) return 'url(../' . $localPath . ')';
                } else {
                    $localPath = $this->downloadAsset($assetUrl, 'images');
                    if ($localPath) return 'url(../' . $localPath . ')';
                }
                
                return $matches[0];
            },
            $css
        );
    }
    
    private function downloadAndRewriteJS(&$html) {
        return preg_replace_callback(
            '/<script[^>]*src=["\']([^"\']+)["\'][^>]*><\/script>/i',
            function($matches) {
                $url = $matches[1];
                $localPath = $this->downloadAsset($url, 'js');
                if ($localPath) {
                    return '<script src="' . $localPath . '"></script>';
                }
                return $matches[0];
            },
            $html
        );
    }
    
    private function downloadAndRewriteImages(&$html) {
        // <img src="...">
        $html = preg_replace_callback(
            '/(<img[^>]*\s)src=["\']([^"\']+)["\']/i',
            function($matches) {
                $url = $matches[2];
                $localPath = $this->downloadAsset($url, 'images');
                if ($localPath) {
                    return $matches[1] . 'src="' . $localPath . '"';
                }
                return $matches[0];
            },
            $html
        );
        
        // srcset
        $html = preg_replace_callback(
            '/srcset=["\']([^"\']+)["\']/i',
            function($matches) {
                $srcset = $matches[1];
                $parts = explode(',', $srcset);
                $newParts = [];
                foreach ($parts as $part) {
                    $part = trim($part);
                    if (preg_match('/^(\S+)(\s+.*)$/', $part, $m)) {
                        $localPath = $this->downloadAsset($m[1], 'images');
                        if ($localPath) {
                            $newParts[] = $localPath . $m[2];
                        } else {
                            $newParts[] = $part;
                        }
                    }
                }
                return 'srcset="' . implode(', ', $newParts) . '"';
            },
            $html
        );
        
        // background-image in style attributes
        $html = preg_replace_callback(
            '/style=["\']([^"\']*background[^"\']*)["\']/',
            function($matches) {
                $style = preg_replace_callback(
                    '/url\s*\(\s*["\']?([^"\')\s]+)["\']?\s*\)/i',
                    function($m) {
                        $localPath = $this->downloadAsset($m[1], 'images');
                        if ($localPath) return 'url(' . $localPath . ')';
                        return $m[0];
                    },
                    $matches[1]
                );
                return 'style="' . $style . '"';
            },
            $html
        );
        
        // Favicon and apple-touch-icon
        $html = preg_replace_callback(
            '/<link[^>]*rel=["\'](?:icon|shortcut icon|apple-touch-icon)["\'][^>]*href=["\']([^"\']+)["\'][^>]*>/i',
            function($matches) {
                $url = $matches[1];
                $localPath = $this->downloadAsset($url, 'images');
                if ($localPath) {
                    return preg_replace('/href=["\'][^"\']+["\']/', 'href="' . $localPath . '"', $matches[0]);
                }
                return $matches[0];
            },
            $html
        );
        
        return $html;
    }
    
    private function downloadAndRewriteFonts(&$html) {
        // Google Fonts and other font stylesheets
        $html = preg_replace_callback(
            '/<link[^>]*href=["\']([^"\']*fonts[^"\']*)["\'][^>]*>/i',
            function($matches) {
                $url = $matches[1];
                if (strpos($url, 'googleapis.com') !== false || strpos($url, 'gstatic.com') !== false) {
                    $localPath = $this->downloadCSSWithDeps($url);
                    if ($localPath) {
                        return '<link rel="stylesheet" href="' . $localPath . '">';
                    }
                }
                return $matches[0];
            },
            $html
        );
        return $html;
    }
    
    private function processInlineStyles(&$html) {
        return preg_replace_callback(
            '/style=["\']([^"\']*url\s*\([^)]+\)[^"\']*)["\']/',
            function($matches) {
                $style = preg_replace_callback(
                    '/url\s*\(\s*["\']?([^"\')\s]+)["\']?\s*\)/i',
                    function($m) {
                        $resolved = $this->resolveUrl($m[1]);
                        if ($resolved) {
                            $localPath = $this->downloadAsset($resolved, 'images');
                            if ($localPath) return 'url(' . $localPath . ')';
                        }
                        return $m[0];
                    },
                    $matches[1]
                );
                return 'style="' . $style . '"';
            },
            $html
        );
    }
    
    private function rewriteInternalLinks(&$html) {
        // Rewrite internal links to #
        $html = preg_replace_callback(
            '/(<a[^>]*\s)href=["\'](' . preg_quote($this->baseUrl, '/') . '[^"\']*)["\']/',
            function($matches) {
                $path = str_replace($this->baseUrl, '', $matches[2]);
                if (empty($path) || $path === '/') {
                    return $matches[1] . 'href="index.html"';
                }
                return $matches[1] . 'href="#" data-original-href="' . htmlspecialchars($matches[2]) . '"';
            },
            $html
        );
        
        // Also rewrite relative internal links
        $html = preg_replace_callback(
            '/(<a[^>]*\s)href=["\']\/([^"\']*)["\']/',
            function($matches) {
                if (empty($matches[2])) {
                    return $matches[1] . 'href="index.html"';
                }
                return $matches[1] . 'href="#" data-original-href="' . htmlspecialchars($this->baseUrl . '/' . $matches[2]) . '"';
            },
            $html
        );
        
        return $html;
    }
    
    private function addProtection() {
        $gate = '<?php
session_start();
$password = "vorschau2024";
if (isset($_POST["pw"]) && $_POST["pw"] === $password) { $_SESSION["gate_ok"] = true; }
if (!empty($_SESSION["gate_ok"])) { readfile(__DIR__ . "/index.html"); exit; }
?><!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="robots" content="noindex, nofollow"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Zugang</title><style>body{font-family:Arial,sans-serif;background:#f4f4f4;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0}.box{background:#fff;padding:40px;border-radius:10px;box-shadow:0 2px 15px rgba(0,0,0,.1);text-align:center;max-width:400px}h2{margin-top:0;color:#333}input[type=password]{width:100%;padding:12px;border:1px solid #ddd;border-radius:5px;box-sizing:border-box;font-size:16px;margin:15px 0}button{background:#007cba;color:#fff;padding:12px 30px;border:none;border-radius:5px;cursor:pointer;font-size:16px;width:100%}button:hover{background:#005a87}p{color:#888;font-size:13px}</style></head><body><div class="box"><h2>Geschützter Bereich</h2><p>Bitte Passwort eingeben.</p><form method="POST"><input type="password" name="pw" placeholder="Passwort" autofocus required><button type="submit">Zugang</button></form></div></body></html>';
        file_put_contents($this->targetDir . '/gate.php', $gate);
        
        file_put_contents($this->targetDir . '/robots.txt', "User-agent: *\nDisallow: /\n");
        
        $htaccess = "Header set X-Robots-Tag \"noindex, nofollow\"\nDirectoryIndex gate.php\nRewriteEngine On\nRewriteRule ^index\\.html$ gate.php [L]\n";
        file_put_contents($this->targetDir . '/.htaccess', $htaccess);
        
        file_put_contents($this->targetDir . '/.clone_info', json_encode([
            'source' => $this->sourceUrl,
            'date' => date('Y-m-d H:i:s')
        ]));
        
        $this->log[] = "Schutz hinzugefügt (gate.php, robots.txt, .htaccess)";
    }
    
    private function cleanupHTML(&$html) {
        // Remove analytics, tracking scripts
        $html = preg_replace('/<script[^>]*google-analytics[^>]*>.*?<\/script>/si', '', $html);
        $html = preg_replace('/<script[^>]*gtag[^>]*>.*?<\/script>/si', '', $html);
        $html = preg_replace('/<noscript[^>]*>.*?<\/noscript>/si', '', $html);
        
        // Block indexing
        $html = str_replace('</head>', 
            '    <meta name="robots" content="noindex, nofollow">' . "\n" . '</head>', 
            $html);
        
        return $html;
    }
    
    public static function generateDirName($url) {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? 'unknown';
        $host = preg_replace('/^www\./', '', $host);
        $host = preg_replace('/\.[a-z]{2,4}$/', '', $host);
        $host = preg_replace('/[^a-z0-9-]/', '-', strtolower($host));
        $host = trim($host, '-');
        return $host;
    }
}

// API-Endpunkt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clone_url'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $url = trim($_POST['clone_url']);
    if (empty($url)) {
        echo json_encode(['success' => false, 'log' => ['Keine URL angegeben.']]);
        exit;
    }
    
    if (!preg_match('#^https?://#', $url)) {
        $url = 'https://' . $url;
    }
    
    $dirName = WebsiteCloner::generateDirName($url);
    $targetDir = __DIR__ . '/' . $dirName;
    
    if (is_dir($targetDir)) {
        $dirName .= '-' . date('His');
        $targetDir = __DIR__ . '/' . $dirName;
    }
    
    $cloner = new WebsiteCloner($url, $targetDir);
    $result = $cloner->clone_site();
    $result['directory'] = $dirName;
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
?>
