<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 300);
ini_set('memory_limit', '1024M');

class WebsiteCloner {
    private $sourceUrl;
    private $targetDir;
    private $baseUrl;
    private $host;
    private $scheme;
    private $downloadedAssets = [];
    private $crawledPages = [];
    private $pageQueue = [];
    private $log = [];
    private $maxPages;
    private $maxDepth;
    private $stats = ['pages' => 0, 'css' => 0, 'js' => 0, 'images' => 0, 'fonts' => 0, 'errors' => 0];

    public function __construct($url, $targetDir, $maxPages = 30, $maxDepth = 3) {
        $parsed = parse_url($url);
        $this->scheme = $parsed['scheme'] ?? 'https';
        $this->host = $parsed['host'] ?? '';
        $this->baseUrl = $this->scheme . '://' . $this->host;
        $this->sourceUrl = rtrim($url, '/');
        $this->targetDir = rtrim($targetDir, '/');
        $this->maxPages = $maxPages;
        $this->maxDepth = $maxDepth;
    }

    public function clone_site() {
        $this->log[] = "=== Website-Kopie gestartet ===";
        $this->log[] = "Quelle: " . $this->sourceUrl;
        $this->log[] = "Max. Seiten: " . $this->maxPages . " | Max. Tiefe: " . $this->maxDepth;

        foreach (['css', 'js', 'images', 'fonts'] as $d) {
            @mkdir($this->targetDir . '/' . $d, 0755, true);
        }

        $this->pageQueue[] = ['url' => $this->sourceUrl, 'depth' => 0];
        $this->pageQueue[] = ['url' => $this->sourceUrl . '/', 'depth' => 0];

        // Pass 1: Crawl all pages and download assets
        while (!empty($this->pageQueue) && $this->stats['pages'] < $this->maxPages) {
            $item = array_shift($this->pageQueue);
            $this->crawlPage($item['url'], $item['depth']);
        }

        // Pass 2: Rewrite all internal links now that all pages are known
        $this->rewriteAllPageLinks();

        $this->addProtection();

        $this->log[] = "";
        $this->log[] = "=== Zusammenfassung ===";
        $this->log[] = "Seiten: " . $this->stats['pages'];
        $this->log[] = "CSS: " . $this->stats['css'];
        $this->log[] = "JS: " . $this->stats['js'];
        $this->log[] = "Bilder: " . $this->stats['images'];
        $this->log[] = "Schriften: " . $this->stats['fonts'];
        $this->log[] = "Fehler: " . $this->stats['errors'];
        $this->log[] = "Dateien gesamt: " . count($this->downloadedAssets);

        $quality = $this->runQualityCheck();

        return [
            'success' => true,
            'log' => $this->log,
            'files' => count($this->downloadedAssets) + $this->stats['pages'],
            'stats' => $this->stats,
            'quality' => $quality
        ];
    }

    private function crawlPage($url, $depth) {
        $normalized = $this->normalizePageUrl($url);
        if (isset($this->crawledPages[$normalized])) return;
        if ($depth > $this->maxDepth) return;
        if (!$this->isSameSite($normalized)) return;

        $this->crawledPages[$normalized] = true;

        $html = $this->fetchUrl($normalized);
        if (!$html) return;

        $contentType = $this->lastContentType ?? '';
        if (stripos($contentType, 'text/html') === false && !preg_match('/<html/i', substr($html, 0, 1000))) {
            return;
        }

        $this->stats['pages']++;
        $pageName = $this->urlToFilename($normalized);
        $this->log[] = "";
        $this->log[] = "📄 Seite " . $this->stats['pages'] . ": " . $pageName . " (Tiefe $depth)";

        $html = $this->processCSS($html, $normalized);
        $html = $this->processJS($html);
        $html = $this->processImages($html, $normalized);
        $html = $this->processFonts($html);
        $html = $this->processInlineAndDataStyles($html, $normalized);

        if ($depth < $this->maxDepth) {
            $this->discoverLinks($html, $normalized, $depth);
        }

        $html = $this->cleanupHTML($html);

        file_put_contents($this->targetDir . '/' . $pageName, $html);
        unset($html);
        gc_collect_cycles();
    }

    private function discoverLinks(&$html, $pageUrl, $depth) {
        preg_match_all('/<a[^>]*href=["\']([^"\'#]+)["\'][^>]*>/i', $html, $matches);
        foreach ($matches[1] as $href) {
            $resolved = $this->resolveRelativePath($pageUrl, $href);
            if (!$resolved) continue;
            $resolved = preg_replace('/#.*$/', '', $resolved);
            $resolved = rtrim($resolved, '/');
            if (empty($resolved)) continue;
            if (!$this->isSameSite($resolved)) continue;

            $ext = strtolower(pathinfo(parse_url($resolved, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'css', 'js'])) continue;

            $norm = $this->normalizePageUrl($resolved);
            if (!isset($this->crawledPages[$norm])) {
                $this->pageQueue[] = ['url' => $norm, 'depth' => $depth + 1];
            }
        }
    }

    private function isSameSite($url) {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        return ($host === $this->host || $host === 'www.' . $this->host || 'www.' . $host === $this->host);
    }

    private function normalizePageUrl($url) {
        $url = preg_replace('/#.*$/', '', $url);
        $url = preg_replace('/\?.*$/', '', $url);
        return rtrim($url, '/');
    }

    private function urlToFilename($url) {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '/';
        $path = trim($path, '/');

        if (empty($path) || $path === '') return 'index.html';

        $path = preg_replace('/[^a-zA-Z0-9\/_-]/', '_', $path);
        $path = str_replace('/', '_', $path);
        $path = trim($path, '_');

        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if (empty($ext) || !in_array($ext, ['html', 'htm', 'php'])) {
            $path .= '.html';
        }
        return $path;
    }

    // --- Asset Processing ---

    private function processCSS(&$html, $pageUrl) {
        $html = preg_replace_callback(
            '/<link[^>]*href=["\']([^"\']+)["\'][^>]*>/i',
            function($m) use ($pageUrl) {
                if (stripos($m[0], 'stylesheet') === false && !preg_match('/\.css(\?|$)/i', $m[1])) return $m[0];
                $local = $this->downloadCSSWithDeps($m[1], $pageUrl);
                if ($local) return '<link rel="stylesheet" href="' . $local . '">';
                return $m[0];
            },
            $html
        );

        $html = preg_replace_callback(
            '/<style[^>]*>(.*?)<\/style>/si',
            function($m) use ($pageUrl) {
                return '<style>' . $this->rewriteCSSUrls($m[1], $pageUrl, true) . '</style>';
            },
            $html
        );

        return $html;
    }

    private function downloadCSSWithDeps($url, $contextUrl) {
        $fullUrl = $this->resolveRelativePath($contextUrl, $url);
        if (!$fullUrl || isset($this->downloadedAssets[$fullUrl])) {
            return $this->downloadedAssets[$fullUrl] ?? null;
        }

        $content = $this->fetchUrl($fullUrl);
        if (!$content) return null;

        $content = $this->rewriteCSSUrls($content, $fullUrl);

        // Handle @import
        $content = preg_replace_callback(
            '/@import\s+(?:url\s*\()?["\']?([^"\')\s;]+)["\']?\)?[^;]*;/i',
            function($m) use ($fullUrl) {
                $importLocal = $this->downloadCSSWithDeps($m[1], $fullUrl);
                if ($importLocal) return '@import url("../' . $importLocal . '");';
                return $m[0];
            },
            $content
        );

        $filename = $this->safeFilename(parse_url($fullUrl, PHP_URL_PATH) ?? 'style.css', 'css');
        file_put_contents($this->targetDir . '/css/' . $filename, $content);
        $localPath = 'css/' . $filename;
        $this->downloadedAssets[$fullUrl] = $localPath;
        $this->stats['css']++;
        $this->log[] = "  📦 CSS: $filename";
        return $localPath;
    }

    private function rewriteCSSUrls($css, $contextUrl, $isInline = false) {
        return preg_replace_callback(
            '/url\s*\(\s*["\']?([^"\')\s]+)["\']?\s*\)/i',
            function($m) use ($contextUrl, $isInline) {
                $assetUrl = trim($m[1]);
                if (strpos($assetUrl, 'data:') === 0) return $m[0];
                $resolved = $this->resolveRelativePath($contextUrl, $assetUrl);
                if (!$resolved) return $m[0];

                $ext = strtolower(pathinfo(preg_replace('/\?.*$/', '', parse_url($resolved, PHP_URL_PATH) ?? ''), PATHINFO_EXTENSION));
                $fontExts = ['woff', 'woff2', 'ttf', 'eot', 'otf'];
                $imgExts = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico', 'avif'];

                if (in_array($ext, $fontExts)) {
                    $local = $this->downloadAsset($resolved, 'fonts');
                    $this->stats['fonts']++;
                } elseif (in_array($ext, $imgExts)) {
                    $local = $this->downloadAsset($resolved, 'images');
                    $this->stats['images']++;
                } else {
                    $local = $this->downloadAsset($resolved, 'images');
                }
                if ($local) {
                    $prefix = $isInline ? '' : '../';
                    return 'url(' . $prefix . $local . ')';
                }
                return $m[0];
            },
            $css
        );
    }

    private function processJS(&$html) {
        return preg_replace_callback(
            '/<script[^>]*src=["\']([^"\']+)["\'][^>]*><\/script>/i',
            function($m) {
                $local = $this->downloadAsset($this->resolveRelativePath($this->sourceUrl, $m[1]), 'js');
                if ($local) {
                    $this->stats['js']++;
                    return '<script src="' . $local . '"></script>';
                }
                return $m[0];
            },
            $html
        );
    }

    private function processImages(&$html, $pageUrl) {
        // <img src>
        $html = preg_replace_callback(
            '/(<img[^>]*\s)src=["\']([^"\']+)["\']/i',
            function($m) use ($pageUrl) {
                $resolved = $this->resolveRelativePath($pageUrl, $m[2]);
                $local = $this->downloadAsset($resolved, 'images');
                if ($local) { $this->stats['images']++; return $m[1] . 'src="' . $local . '"'; }
                return $m[0];
            },
            $html
        );

        // srcset - only keep largest image to save bandwidth/memory
        $html = preg_replace_callback(
            '/srcset=["\']([^"\']+)["\']/i',
            function($m) use ($pageUrl) {
                $parts = explode(',', $m[1]);
                $best = null;
                $bestW = 0;
                foreach ($parts as $part) {
                    $part = trim($part);
                    if (preg_match('/^(\S+)\s+(\d+)w/', $part, $p)) {
                        if (intval($p[2]) > $bestW) { $bestW = intval($p[2]); $best = $p[1]; }
                    } elseif (!$best && preg_match('/^(\S+)/', $part, $p)) {
                        $best = $p[1];
                    }
                }
                if ($best) {
                    $resolved = $this->resolveRelativePath($pageUrl, $best);
                    $local = $this->downloadAsset($resolved, 'images');
                    if ($local) { $this->stats['images']++; return 'srcset="' . $local . '"'; }
                }
                return $m[0];
            },
            $html
        );

        // <source srcset> (picture elements)
        $html = preg_replace_callback(
            '/(<source[^>]*\s)srcset=["\']([^"\']+)["\']/i',
            function($m) use ($pageUrl) {
                $resolved = $this->resolveRelativePath($pageUrl, $m[2]);
                $local = $this->downloadAsset($resolved, 'images');
                if ($local) { $this->stats['images']++; return $m[1] . 'srcset="' . $local . '"'; }
                return $m[0];
            },
            $html
        );

        // <video poster> and <video src>
        $html = preg_replace_callback(
            '/(<video[^>]*\s)(poster|src)=["\']([^"\']+)["\']/i',
            function($m) use ($pageUrl) {
                $resolved = $this->resolveRelativePath($pageUrl, $m[3]);
                $local = $this->downloadAsset($resolved, 'images');
                if ($local) return $m[1] . $m[2] . '="' . $local . '"';
                return $m[0];
            },
            $html
        );

        // Favicon / icons
        $html = preg_replace_callback(
            '/<link[^>]*rel=["\'](?:icon|shortcut icon|apple-touch-icon)[^"\']*["\'][^>]*href=["\']([^"\']+)["\'][^>]*>/i',
            function($m) use ($pageUrl) {
                $resolved = $this->resolveRelativePath($pageUrl, $m[1]);
                $local = $this->downloadAsset($resolved, 'images');
                if ($local) return preg_replace('/href=["\'][^"\']+["\']/', 'href="' . $local . '"', $m[0]);
                return $m[0];
            },
            $html
        );

        // og:image meta
        $html = preg_replace_callback(
            '/<meta[^>]*property=["\']og:image["\'][^>]*content=["\']([^"\']+)["\']/i',
            function($m) use ($pageUrl) {
                $resolved = $this->resolveRelativePath($pageUrl, $m[1]);
                $local = $this->downloadAsset($resolved, 'images');
                if ($local) return str_replace($m[1], $local, $m[0]);
                return $m[0];
            },
            $html
        );

        return $html;
    }

    private function processInlineAndDataStyles(&$html, $pageUrl) {
        // style="...background-image: url(...)..."
        $html = preg_replace_callback(
            '/style=["\']([^"\']*(?:background|url)[^"\']*)["\']/',
            function($m) use ($pageUrl) {
                $style = preg_replace_callback(
                    '/url\s*\(\s*["\']?([^"\')\s]+)["\']?\s*\)/i',
                    function($u) use ($pageUrl) {
                        if (strpos($u[1], 'data:') === 0) return $u[0];
                        $resolved = $this->resolveRelativePath($pageUrl, $u[1]);
                        $local = $this->downloadAsset($resolved, 'images');
                        if ($local) { $this->stats['images']++; return 'url(' . $local . ')'; }
                        return $u[0];
                    },
                    $m[1]
                );
                return 'style="' . $style . '"';
            },
            $html
        );

        // data-* lazy-load attributes (WordPress, RevSlider, VC, etc.)
        $lazyAttrs = [
            'data-bg', 'data-background', 'data-src', 'data-lazy-src',
            'data-lazyload', 'data-lazy', 'data-image', 'data-thumb',
            'data-vc-parallax-image', 'data-placeholder-image',
            'data-original', 'data-full', 'data-large-file',
            'data-medium-file', 'data-bg-url'
        ];
        $attrPattern = implode('|', array_map(function($a) { return preg_quote($a, '/'); }, $lazyAttrs));
        $html = preg_replace_callback(
            '/(' . $attrPattern . ')\s*=\s*["\']([^"\']+)["\']/i',
            function($m) use ($pageUrl) {
                $resolved = $this->resolveRelativePath($pageUrl, $m[2]);
                $local = $this->downloadAsset($resolved, 'images');
                if ($local) { $this->stats['images']++; return $m[1] . '="' . $local . '"'; }
                return $m[0];
            },
            $html
        );

        return $html;
    }

    private function processFonts(&$html) {
        $html = preg_replace_callback(
            '/<link[^>]*href=["\']([^"\']*fonts\.googleapis[^"\']*)["\'][^>]*>/i',
            function($m) {
                $local = $this->downloadCSSWithDeps($m[1], $this->sourceUrl);
                if ($local) return '<link rel="stylesheet" href="' . $local . '">';
                return $m[0];
            },
            $html
        );
        return $html;
    }

    private function rewriteAllPageLinks() {
        $this->log[] = "";
        $this->log[] = "🔗 Pass 2: Links in allen " . $this->stats['pages'] . " Seiten umschreiben...";
        $htmlFiles = glob($this->targetDir . '/*.html');
        foreach ($htmlFiles as $file) {
            $html = file_get_contents($file);
            if ($html === false) continue;
            $html = $this->rewritePageLinks($html);
            file_put_contents($file, $html);
            unset($html);
            gc_collect_cycles();
        }
        $this->log[] = "✅ Links umgeschrieben";
    }

    private function rewritePageLinks(&$html) {
        // Internal links → local HTML files
        $html = preg_replace_callback(
            '/(<a[^>]*\s)href=["\']([^"\'#]+)(#[^"\']*)?["\']/i',
            function($m) {
                $url = $m[2];
                $anchor = $m[3] ?? '';
                $resolved = $this->resolveRelativePath($this->sourceUrl, $url);
                if (!$resolved) return $m[0];

                if ($this->isSameSite($resolved)) {
                    $norm = $this->normalizePageUrl($resolved);
                    if (isset($this->crawledPages[$norm])) {
                        $filename = $this->urlToFilename($norm);
                        return $m[1] . 'href="' . $filename . $anchor . '"';
                    }
                    return $m[1] . 'href="#" data-original="' . htmlspecialchars($resolved) . '"';
                }
                return $m[0];
            },
            $html
        );
        return $html;
    }

    // --- Quality Check System ---

    private function runQualityCheck() {
        $this->log[] = "";
        $this->log[] = "=== Qualitätsprüfung ===";
        $checks = [];

        // Check 1: index.html exists
        $indexExists = file_exists($this->targetDir . '/index.html');
        $checks['index_exists'] = $indexExists;
        $this->log[] = ($indexExists ? "✅" : "❌") . " index.html vorhanden";

        // Check 2: CSS files downloaded
        $cssCount = count(glob($this->targetDir . '/css/*.css'));
        $checks['css_count'] = $cssCount;
        $this->log[] = ($cssCount > 0 ? "✅" : "⚠️") . " $cssCount CSS-Dateien";

        // Check 3: Images downloaded
        $imgFiles = glob($this->targetDir . '/images/*');
        $imgCount = $imgFiles ? count($imgFiles) : 0;
        $checks['image_count'] = $imgCount;
        $this->log[] = ($imgCount > 0 ? "✅" : "⚠️") . " $imgCount Bilder";

        // Check 4: HTML size reasonable
        $indexSize = $indexExists ? filesize($this->targetDir . '/index.html') : 0;
        $checks['index_size'] = $indexSize;
        $this->log[] = ($indexSize > 5000 ? "✅" : "⚠️") . " index.html Größe: " . $this->formatSize($indexSize);

        // Check 5: No broken local references
        $brokenRefs = 0;
        if ($indexExists) {
            $content = file_get_contents($this->targetDir . '/index.html');
            preg_match_all('/(?:src|href)=["\'](?!http|#|data:|mailto:|tel:|javascript:)([^"\']+)["\']/i', $content, $refs);
            foreach ($refs[1] as $ref) {
                $ref = preg_replace('/\?.*$/', '', $ref);
                if (!file_exists($this->targetDir . '/' . $ref)) {
                    $brokenRefs++;
                }
            }
        }
        $checks['broken_refs'] = $brokenRefs;
        $this->log[] = ($brokenRefs === 0 ? "✅" : "⚠️") . " $brokenRefs fehlende lokale Referenzen in index.html";

        // Check 6: Subpages
        $htmlFiles = glob($this->targetDir . '/*.html');
        $pageCount = $htmlFiles ? count($htmlFiles) : 0;
        $checks['page_count'] = $pageCount;
        $this->log[] = "ℹ️ $pageCount HTML-Seiten gesamt";

        // Check 7: Total directory size
        $totalSize = $this->dirSize($this->targetDir);
        $checks['total_size'] = $totalSize;
        $this->log[] = "ℹ️ Gesamtgröße: " . $this->formatSize($totalSize);

        // Check 8: Verify inline CSS background-images are accessible
        $bgBroken = 0;
        $bgTotal = 0;
        if ($indexExists) {
            $content = file_get_contents($this->targetDir . '/index.html');
            preg_match_all('/background-image:\s*url\(([^)]+)\)/', $content, $bgRefs);
            foreach ($bgRefs[1] as $ref) {
                $ref = trim($ref, '"\'');
                $bgTotal++;
                if (!file_exists($this->targetDir . '/' . $ref)) {
                    $bgBroken++;
                }
            }
            unset($content);
        }
        $checks['bg_images_total'] = $bgTotal;
        $checks['bg_images_broken'] = $bgBroken;
        $this->log[] = ($bgBroken === 0 ? "✅" : "⚠️") . " CSS background-images: $bgTotal gefunden, $bgBroken fehlen";

        // Generate visual check page
        $this->generateCheckPage();
        $this->log[] = "🔍 check.html generiert (visuelle Prüfseite)";

        // Overall score
        $score = 0;
        if ($indexExists) $score += 20;
        if ($cssCount > 0) $score += 10;
        if ($imgCount >= 3) $score += 20;
        if ($indexSize > 10000) $score += 15;
        if ($brokenRefs < 5) $score += 10;
        if ($pageCount > 1) $score += 10;
        if ($bgBroken === 0 && $bgTotal > 0) $score += 15;

        $checks['score'] = $score;
        $grade = $score >= 90 ? 'A' : ($score >= 70 ? 'B' : ($score >= 50 ? 'C' : 'D'));
        $checks['grade'] = $grade;
        $this->log[] = "";
        $this->log[] = "🏆 Qualitätsnote: $grade ($score/100)";

        return $checks;
    }

    // --- Utility Methods ---

    private $lastContentType = '';

    private function fetchUrl($url) {
        if (!$url) return null;
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
        $this->lastContentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?? '';
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 400 && $response !== false) return $response;
        $this->stats['errors']++;
        return null;
    }

    private function downloadAsset($url, $subdir) {
        if (!$url) return null;
        if (isset($this->downloadedAssets[$url])) return $this->downloadedAssets[$url];

        $filename = $this->safeFilename(parse_url($url, PHP_URL_PATH) ?? 'file', $subdir === 'fonts' ? 'woff2' : 'png');
        $savePath = $this->targetDir . '/' . $subdir . '/' . $filename;

        $ch = curl_init();
        $fp = fopen($savePath, 'w');
        if (!$fp) return null;
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if ($httpCode < 200 || $httpCode >= 400) {
            @unlink($savePath);
            $this->stats['errors']++;
            return null;
        }

        $localPath = $subdir . '/' . $filename;
        $this->downloadedAssets[$url] = $localPath;
        return $localPath;
    }

    private function resolveRelativePath($base, $relative) {
        $relative = trim($relative);
        if (empty($relative) || $relative === '#') return null;
        foreach (['data:', 'mailto:', 'tel:', 'javascript:', '{'] as $skip) {
            if (strpos($relative, $skip) === 0) return null;
        }

        if (preg_match('#^https?://#', $relative)) return $relative;
        if (strpos($relative, '//') === 0) return $this->scheme . ':' . $relative;

        $parsed = parse_url($base);
        $bScheme = $parsed['scheme'] ?? $this->scheme;
        $bHost = $parsed['host'] ?? $this->host;

        if (strpos($relative, '/') === 0) return $bScheme . '://' . $bHost . $relative;

        $basePath = $parsed['path'] ?? '/';
        $baseDir = rtrim(dirname($basePath), '/');
        $combined = $baseDir . '/' . $relative;

        $parts = explode('/', $combined);
        $resolved = [];
        foreach ($parts as $p) {
            if ($p === '..') { array_pop($resolved); }
            elseif ($p !== '.' && $p !== '') { $resolved[] = $p; }
        }
        return $bScheme . '://' . $bHost . '/' . implode('/', $resolved);
    }

    private function safeFilename($path, $defaultExt) {
        $filename = basename(preg_replace('/\?.*$/', '', $path));
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        if (empty(pathinfo($filename, PATHINFO_EXTENSION))) {
            $filename .= '.' . $defaultExt;
        }
        if (strlen($filename) > 100) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $filename = substr(md5($filename), 0, 16) . '.' . $ext;
        }

        $counter = 0;
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $saveName = $filename;
        while (file_exists($this->targetDir . '/css/' . $saveName) ||
               file_exists($this->targetDir . '/js/' . $saveName) ||
               file_exists($this->targetDir . '/images/' . $saveName) ||
               file_exists($this->targetDir . '/fonts/' . $saveName)) {
            $counter++;
            $saveName = $base . '_' . $counter . '.' . $ext;
        }
        return $saveName;
    }

    private function addProtection() {
        $gate = '<?php
session_start();
$password = "vorschau2024";
if (isset($_POST["pw"]) && $_POST["pw"] === $password) { $_SESSION["gate_ok"] = true; }
if (!empty($_SESSION["gate_ok"])) {
    $page = isset($_GET["p"]) ? basename($_GET["p"]) : "index.html";
    if (file_exists(__DIR__ . "/" . $page)) { readfile(__DIR__ . "/" . $page); } else { readfile(__DIR__ . "/index.html"); }
    exit;
}
?><!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="robots" content="noindex, nofollow"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Zugang</title><style>body{font-family:Arial,sans-serif;background:#f4f4f4;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0}.box{background:#fff;padding:40px;border-radius:10px;box-shadow:0 2px 15px rgba(0,0,0,.1);text-align:center;max-width:400px}h2{margin-top:0;color:#333}input[type=password]{width:100%;padding:12px;border:1px solid #ddd;border-radius:5px;box-sizing:border-box;font-size:16px;margin:15px 0}button{background:#007cba;color:#fff;padding:12px 30px;border:none;border-radius:5px;cursor:pointer;font-size:16px;width:100%}button:hover{background:#005a87}p{color:#888;font-size:13px}</style></head><body><div class="box"><h2>Geschützter Bereich</h2><p>Bitte Passwort eingeben.</p><form method="POST"><input type="password" name="pw" placeholder="Passwort" autofocus required><button type="submit">Zugang</button></form></div></body></html>';
        file_put_contents($this->targetDir . '/gate.php', $gate);
        file_put_contents($this->targetDir . '/robots.txt', "User-agent: *\nDisallow: /\n");
        file_put_contents($this->targetDir . '/.htaccess',
            "Header set X-Robots-Tag \"noindex, nofollow\"\nDirectoryIndex gate.php\nRewriteEngine On\nRewriteRule ^index\\.html$ gate.php [L]\n");
        file_put_contents($this->targetDir . '/.clone_info', json_encode([
            'source' => $this->sourceUrl, 'date' => date('Y-m-d H:i:s'),
            'pages' => $this->stats['pages'], 'files' => count($this->downloadedAssets)
        ]));
        $this->log[] = "🔒 Schutz: gate.php, robots.txt, .htaccess, noindex";
    }

    private function cleanupHTML(&$html) {
        $html = preg_replace('/<script[^>]*google[^>]*>.*?<\/script>/si', '', $html);
        $html = preg_replace('/<script[^>]*gtag[^>]*>.*?<\/script>/si', '', $html);
        $html = preg_replace('/<script[^>]*analytics[^>]*>.*?<\/script>/si', '', $html);
        $html = preg_replace('/<script[^>]*facebook[^>]*>.*?<\/script>/si', '', $html);
        $html = str_replace('</head>',
            '    <meta name="robots" content="noindex, nofollow">' . "\n" . '</head>', $html);
        return $html;
    }

    private function generateCheckPage() {
        $imgFiles = glob($this->targetDir . '/images/*');
        $imgHtml = '';
        $count = 0;
        if ($imgFiles) {
            foreach ($imgFiles as $f) {
                $name = basename($f);
                $size = $this->formatSize(filesize($f));
                $count++;
                $imgHtml .= "<div class='img-card'><img src='images/$name' onerror=\"this.parentElement.classList.add('broken')\" loading='lazy'><div class='img-info'>$name<br><small>$size</small></div></div>\n";
            }
        }

        $pages = glob($this->targetDir . '/*.html');
        $pageLinks = '';
        if ($pages) {
            foreach ($pages as $p) {
                $name = basename($p);
                if ($name === 'check.html') continue;
                $size = $this->formatSize(filesize($p));
                $pageLinks .= "<a href='$name' target='_blank' class='page-link'>$name ($size)</a>\n";
            }
        }

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="robots" content="noindex, nofollow">
<title>Qualitätsprüfung</title><style>
body{font-family:Arial,sans-serif;margin:20px;background:#f5f5f5}
h1{color:#333}h2{color:#555;border-bottom:2px solid #007cba;padding-bottom:8px}
.stats{display:flex;gap:15px;flex-wrap:wrap;margin:15px 0}
.stat{background:white;padding:15px 20px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.1);text-align:center}
.stat .num{font-size:28px;font-weight:bold;color:#007cba}
.stat .label{font-size:12px;color:#888;margin-top:4px}
.img-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;margin:15px 0}
.img-card{background:white;border-radius:6px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.1)}
.img-card img{width:100%;height:150px;object-fit:cover}
.img-card.broken{border:3px solid #dc3545}
.img-card.broken img{display:none}
.img-card.broken::before{content:"❌ FEHLT";display:block;height:150px;line-height:150px;text-align:center;color:#dc3545;font-weight:bold;background:#fff5f5}
.img-info{padding:6px 10px;font-size:11px;color:#666;word-break:break-all}
.page-link{display:inline-block;background:#007cba;color:white;padding:6px 14px;border-radius:4px;text-decoration:none;margin:3px;font-size:13px}
.page-link:hover{background:#005a87}
</style></head><body>
<h1>🔍 Qualitätsprüfung</h1>
<div class="stats">
<div class="stat"><div class="num">' . count($pages) . '</div><div class="label">Seiten</div></div>
<div class="stat"><div class="num">' . $count . '</div><div class="label">Bilder</div></div>
<div class="stat"><div class="num">' . count(glob($this->targetDir . '/css/*')) . '</div><div class="label">CSS</div></div>
<div class="stat"><div class="num">' . count(glob($this->targetDir . '/fonts/*')) . '</div><div class="label">Schriften</div></div>
<div class="stat"><div class="num">' . count(glob($this->targetDir . '/js/*')) . '</div><div class="label">JS</div></div>
</div>
<h2>Seiten</h2>' . $pageLinks . '
<h2>Alle Bilder (' . $count . ')</h2>
<p>Bilder mit rotem Rand = fehlen/kaputt. Klicken Sie auf ein Bild, um es in voller Größe zu sehen.</p>
<div class="img-grid">' . $imgHtml . '</div>
<script>document.querySelectorAll(".img-card img").forEach(function(img){img.addEventListener("click",function(){window.open(img.src,"_blank")})});</script>
</body></html>';
        file_put_contents($this->targetDir . '/check.html', $html);
    }

    private function formatSize($bytes) {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }

    private function dirSize($dir) {
        $size = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
            if ($file->isFile()) $size += $file->getSize();
        }
        return $size;
    }

    public static function generateDirName($url) {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? 'unknown';
        $host = preg_replace('/^www\./', '', $host);
        $host = preg_replace('/\.[a-z]{2,4}$/', '', $host);
        $host = preg_replace('/[^a-z0-9]/', '', strtolower($host));
        return 'site' . substr(md5($host . date('His')), 0, 6);
    }
}

// API Endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clone_url'])) {
    header('Content-Type: application/json; charset=utf-8');

    $url = trim($_POST['clone_url']);
    if (empty($url)) {
        echo json_encode(['success' => false, 'log' => ['Keine URL angegeben.']]);
        exit;
    }
    if (!preg_match('#^https?://#', $url)) $url = 'https://' . $url;

    $maxPages = intval($_POST['max_pages'] ?? 30);
    $maxDepth = intval($_POST['max_depth'] ?? 3);
    $maxPages = max(1, min(100, $maxPages));
    $maxDepth = max(1, min(5, $maxDepth));

    $dirName = $_POST['dir_name'] ?? '';
    $dirName = preg_replace('/[^a-zA-Z0-9_-]/', '', $dirName);
    if (empty($dirName)) $dirName = WebsiteCloner::generateDirName($url);

    $targetDir = __DIR__ . '/' . $dirName;
    if (is_dir($targetDir)) {
        $dirName .= '_' . date('His');
        $targetDir = __DIR__ . '/' . $dirName;
    }

    $cloner = new WebsiteCloner($url, $targetDir, $maxPages, $maxDepth);
    $result = $cloner->clone_site();
    $result['directory'] = $dirName;

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
?>
