<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 600);
ini_set('memory_limit', '512M');

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
    public $onProgress = null;

    public function __construct($url, $targetDir, $maxPages = 200, $maxDepth = 5) {
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
        $this->log[] = "Max. Seiten: " . $this->maxPages;

        foreach (['css', 'js', 'images', 'fonts'] as $d) {
            @mkdir($this->targetDir . '/' . $d, 0755, true);
        }

        // Phase 1: Startseite laden und ALLE internen Links sammeln
        $this->log[] = "";
        $this->log[] = "🔍 Phase 1: Startseite analysieren und alle internen Links finden...";
        $this->discoverAllPages();
        $queueSize = count($this->pageQueue);
        $this->log[] = "📋 $queueSize Seiten zum Kopieren gefunden";

        // Phase 2: Download raw HTML for all pages first (minimal memory)
        $this->log[] = "";
        $this->log[] = "📥 Phase 2: HTML herunterladen (" . count($this->pageQueue) . " Seiten)...";
        $pagesToProcess = [];
        while (!empty($this->pageQueue) && count($pagesToProcess) < $this->maxPages) {
            $item = array_shift($this->pageQueue);
            $normalized = $this->normalizePageUrl($item['url']);
            if (isset($this->crawledPages[$normalized])) continue;
            $this->crawledPages[$normalized] = true;
            if (!$this->isSameSite($normalized)) continue;

            $html = $this->fetchUrl($normalized);
            if (!$html) { $html = $this->fetchUrl($normalized . '/'); }
            if (!$html) continue;
            $ct = $this->lastContentType ?? '';
            if (stripos($ct, 'text/html') === false && !preg_match('/<html/i', substr($html, 0, 1000))) { unset($html); continue; }

            $pageName = $this->urlToFilename($normalized);
            file_put_contents($this->targetDir . '/' . $pageName . '.raw', $html);
            $pagesToProcess[] = ['url' => $normalized, 'name' => $pageName, 'depth' => $item['depth']];
            $this->stats['pages']++;
            $this->emitProgress("⬇️ " . $this->stats['pages'] . "/" . min(count($this->pageQueue) + count($pagesToProcess), $this->maxPages) . " " . $pageName);
            unset($html);
        }

        // Phase 2b: Process each page (assets, CSS, images) one at a time
        $this->log[] = "";
        $this->log[] = "📥 Phase 2b: Assets verarbeiten (" . count($pagesToProcess) . " Seiten)...";
        foreach ($pagesToProcess as $i => $page) {
            $rawFile = $this->targetDir . '/' . $page['name'] . '.raw';
            if (!file_exists($rawFile)) continue;
            $html = file_get_contents($rawFile);
            
            $this->log[] = "";
            $this->log[] = "📄 Seite " . ($i+1) . ": " . $page['name'];
            $this->emitProgress("📄 Seite " . ($i+1) . "/" . count($pagesToProcess) . ": " . $page['name']);

            $html = $this->processCSS($html, $page['url']);
            $html = $this->processJS($html);
            $html = $this->processImages($html, $page['url']);
            $html = $this->processFonts($html);
            $html = $this->processInlineAndDataStyles($html, $page['url']);
            $html = $this->cleanupHTML($html);

            file_put_contents($this->targetDir . '/' . $page['name'], $html);
            @unlink($rawFile);
            unset($html);
            gc_collect_cycles();
        }

        // Phase 3: Links in allen Seiten umschreiben
        $this->log[] = "";
        $this->log[] = "🔗 Phase 3: Links umschreiben...";
        $this->rewriteAllPageLinks();

        // Phase 4: Schutz hinzufügen
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

    private function discoverAllPages() {
        $startUrl = $this->sourceUrl;
        $html = $this->fetchUrl($startUrl);
        if (!$html) {
            $html = $this->fetchUrl($startUrl . '/');
            if (!$html) return;
        }

        $discovered = [];
        $discovered[$this->normalizePageUrl($startUrl)] = true;

        $this->extractInternalLinks($html, $startUrl, $discovered);

        // Also scan from discovered pages (depth 1) for more links
        $firstPassUrls = array_keys($discovered);
        foreach ($firstPassUrls as $url) {
            if ($url === $this->normalizePageUrl($startUrl)) continue;
            if (count($discovered) >= $this->maxPages) break;

            $pageHtml = $this->fetchUrl($url);
            if (!$pageHtml) continue;
            if (stripos($this->lastContentType ?? '', 'text/html') === false && !preg_match('/<html/i', substr($pageHtml, 0, 1000))) continue;

            $this->extractInternalLinks($pageHtml, $url, $discovered);
            unset($pageHtml);
        }

        // Build queue: startpage first, then all discovered
        $this->pageQueue = [['url' => $startUrl, 'depth' => 0]];
        foreach ($discovered as $url => $true) {
            if ($url === $this->normalizePageUrl($startUrl)) continue;
            $this->pageQueue[] = ['url' => $url, 'depth' => 1];
        }
    }

    private function extractInternalLinks($html, $pageUrl, &$discovered) {
        preg_match_all('/href=["\']([^"\'#]+)["\']/', $html, $matches);
        foreach ($matches[1] as $href) {
            $resolved = $this->resolveRelativePath($pageUrl, $href);
            if (!$resolved) continue;
            $resolved = preg_replace('/#.*$/', '', $resolved);
            if (empty($resolved)) continue;
            if (!$this->isSameSite($resolved)) continue;

            $ext = strtolower(pathinfo(parse_url($resolved, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf','zip','doc','docx','xls','xlsx','png','jpg','jpeg','gif','svg','css','js','ico','woff','woff2','ttf','eot','mp4','mp3'])) continue;

            $norm = $this->normalizePageUrl($resolved);
            if (!isset($discovered[$norm]) && count($discovered) < $this->maxPages) {
                $discovered[$norm] = true;
            }
        }
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

        // <link rel="preload" as="image">
        $html = preg_replace_callback(
            '/<link[^>]*rel=["\']preload["\'][^>]*href=["\']([^"\']+)["\'][^>]*as=["\']image["\'][^>]*>/i',
            function($m) use ($pageUrl) {
                $resolved = $this->resolveRelativePath($pageUrl, $m[1]);
                $local = $this->downloadAsset($resolved, 'images');
                if ($local) { $this->stats['images']++; return preg_replace('/href=["\'][^"\']+["\']/', 'href="' . $local . '"', $m[0]); }
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

        // Check 5+6+7+8: Scan ALL pages for broken refs, images, bg-images
        $htmlFiles = glob($this->targetDir . '/*.html');
        $pageCount = $htmlFiles ? count($htmlFiles) : 0;
        $totalBrokenRefs = 0;
        $totalBgImages = 0;
        $totalBgBroken = 0;
        $pageDetails = [];

        $this->log[] = "";
        $this->log[] = "📋 Detailprüfung aller $pageCount Seiten:";

        foreach ($htmlFiles as $file) {
            $pageName = basename($file);
            if ($pageName === 'check.html') continue;

            $content = file_get_contents($file);
            if ($content === false) continue;

            // Broken local src/href
            $pageBroken = 0;
            $pageBrokenList = [];
            preg_match_all('/(?:src|href)=["\'](?!http|#|data:|mailto:|tel:|javascript:)([^"\']+)["\']/i', $content, $refs);
            foreach ($refs[1] as $ref) {
                $clean = preg_replace('/\?.*$/', '', $ref);
                if (!empty($clean) && !file_exists($this->targetDir . '/' . $clean)) {
                    $pageBroken++;
                    if (count($pageBrokenList) < 3) $pageBrokenList[] = $clean;
                }
            }
            $totalBrokenRefs += $pageBroken;

            // Broken img src specifically
            $imgBroken = 0;
            preg_match_all('/<img[^>]*src=["\']([^"\']+)["\']/i', $content, $imgRefs);
            foreach ($imgRefs[1] as $ref) {
                if (preg_match('/^(http|data:|#)/', $ref)) continue;
                $clean = preg_replace('/\?.*$/', '', $ref);
                if (!file_exists($this->targetDir . '/' . $clean)) $imgBroken++;
            }

            // CSS background-images
            $bgTotal = 0;
            $bgBroken = 0;
            preg_match_all('/background(?:-image)?\s*:\s*[^;]*url\(\s*["\']?([^"\')\s]+)["\']?\s*\)/i', $content, $bgRefs);
            foreach ($bgRefs[1] as $ref) {
                if (strpos($ref, 'data:') === 0) continue;
                $bgTotal++;
                $clean = preg_replace('/\?.*$/', '', $ref);
                if (!file_exists($this->targetDir . '/' . $clean)) $bgBroken++;
            }
            $totalBgImages += $bgTotal;
            $totalBgBroken += $bgBroken;

            // Internal navigation links that point to non-existent local pages
            $linksBroken = 0;
            $linksTotal = 0;
            preg_match_all('/<a[^>]*href=["\']([^"\'#]+\.html)["\']/', $content, $linkRefs);
            foreach ($linkRefs[1] as $ref) {
                $linksTotal++;
                if (!file_exists($this->targetDir . '/' . $ref)) $linksBroken++;
            }

            $icon = ($imgBroken === 0 && $bgBroken <= 2 && $linksBroken === 0) ? "✅" : "⚠️";
            $detail = "$icon $pageName";
            $issues = [];
            if ($pageBroken > 0) $issues[] = "$pageBroken fehlende Refs";
            if ($imgBroken > 0) $issues[] = "$imgBroken fehlende Bilder";
            if ($bgBroken > 0) $issues[] = "$bgBroken fehlende BG-Bilder";
            if ($linksBroken > 0) $issues[] = "$linksBroken/$linksTotal tote Seiten-Links";
            if (!empty($issues)) $detail .= " (" . implode(', ', $issues) . ")";
            $this->log[] = "  " . $detail;

            $pageDetails[] = [
                'name' => $pageName,
                'size' => filesize($file),
                'broken_refs' => $pageBroken,
                'broken_refs_list' => $pageBrokenList,
                'broken_imgs' => $imgBroken,
                'bg_total' => $bgTotal,
                'bg_broken' => $bgBroken,
                'links_total' => $linksTotal,
                'links_broken' => $linksBroken,
                'ok' => ($imgBroken === 0 && $bgBroken <= 2 && $linksBroken === 0)
            ];

            unset($content);
        }

        $checks['page_count'] = $pageCount;
        $checks['broken_refs'] = $totalBrokenRefs;
        $checks['bg_images_total'] = $totalBgImages;
        $checks['bg_images_broken'] = $totalBgBroken;
        $checks['page_details'] = $pageDetails;

        $pagesOk = count(array_filter($pageDetails, function($p) { return $p['ok']; }));
        $this->log[] = "";
        $this->log[] = "📊 $pagesOk/$pageCount Seiten ohne Fehler";
        $this->log[] = "📊 Gesamt: $totalBrokenRefs fehlende Refs, $totalBgBroken/$totalBgImages fehlende BG-Bilder";

        // Total directory size
        $totalSize = $this->dirSize($this->targetDir);
        $checks['total_size'] = $totalSize;
        $this->log[] = "ℹ️ Gesamtgröße: " . $this->formatSize($totalSize);

        // Generate visual check page
        $this->generateCheckPage($pageDetails);
        $this->log[] = "🔍 check.html generiert (visuelle Prüfseite mit Unterseiten-Report)";

        // Overall score
        $score = 0;
        if ($indexExists) $score += 15;
        if ($cssCount > 0) $score += 5;
        if ($imgCount >= 3) $score += 15;
        if ($indexSize > 10000) $score += 10;
        if ($totalBrokenRefs < 5) $score += 10; elseif ($totalBrokenRefs < 20) $score += 5;
        if ($pageCount > 1) $score += 10;
        $bgRatio = $totalBgImages > 0 ? ($totalBgImages - $totalBgBroken) / $totalBgImages : 1;
        if ($bgRatio >= 0.95) $score += 15; elseif ($bgRatio >= 0.8) $score += 10; elseif ($bgRatio >= 0.5) $score += 5;
        if ($pagesOk === $pageCount) $score += 20; elseif ($pagesOk >= $pageCount * 0.8) $score += 15; elseif ($pagesOk > $pageCount * 0.5) $score += 8;

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
        $html = preg_replace('/<link[^>]*href=["\']\/\/gmpg\.org[^"\']*["\'][^>]*>/i', '', $html);
        $html = str_replace('</head>',
            '    <meta name="robots" content="noindex, nofollow">' . "\n" . '</head>', $html);
        return $html;
    }

    private function generateCheckPage($pageDetails = []) {
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

        // Build page report table
        $pageReport = '';
        if (!empty($pageDetails)) {
            $pageReport = '<table class="report-table"><tr><th>Seite</th><th>Größe</th><th>Fehlende Refs</th><th>Fehlende Bilder</th><th>BG-Bilder</th><th>Seiten-Links</th><th>Status</th></tr>';
            foreach ($pageDetails as $pd) {
                $status = $pd['ok'] ? '<span style="color:#28a745">✅ OK</span>' : '<span style="color:#dc3545">⚠️ Probleme</span>';
                $bgText = $pd['bg_total'] > 0 ? ($pd['bg_broken'] . '/' . $pd['bg_total'] . ' fehlen') : '-';
                $linksText = ($pd['links_total'] ?? 0) > 0 ? (($pd['links_broken'] ?? 0) . '/' . ($pd['links_total'] ?? 0) . ' fehlen') : '-';
                $linksStyle = ($pd['links_broken'] ?? 0) > 0 ? ' style="color:#dc3545;font-weight:bold"' : '';
                $brokenDetail = '';
                if (!empty($pd['broken_refs_list'])) {
                    $brokenDetail = '<br><small style="color:#999">' . implode(', ', array_map('htmlspecialchars', $pd['broken_refs_list'])) . '</small>';
                }
                $pageReport .= '<tr><td><a href="' . htmlspecialchars($pd['name']) . '" target="_blank">' . htmlspecialchars($pd['name']) . '</a></td>'
                    . '<td>' . $this->formatSize($pd['size']) . '</td>'
                    . '<td>' . $pd['broken_refs'] . $brokenDetail . '</td>'
                    . '<td>' . $pd['broken_imgs'] . '</td>'
                    . '<td>' . $bgText . '</td>'
                    . '<td' . $linksStyle . '>' . $linksText . '</td>'
                    . '<td>' . $status . '</td></tr>';
            }
            $pageReport .= '</table>';
        }

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="robots" content="noindex, nofollow">
<title>Qualitätsprüfung</title><style>
body{font-family:Arial,sans-serif;margin:20px;background:#f5f5f5}
h1{color:#333}h2{color:#555;border-bottom:2px solid #007cba;padding-bottom:8px}
.stats{display:flex;gap:15px;flex-wrap:wrap;margin:15px 0}
.stat{background:white;padding:15px 20px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.1);text-align:center}
.stat .num{font-size:28px;font-weight:bold;color:#007cba}
.stat .label{font-size:12px;color:#888;margin-top:4px}
.report-table{width:100%;border-collapse:collapse;background:white;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.1);margin:15px 0}
.report-table th{background:#007cba;color:white;padding:10px 12px;text-align:left;font-size:13px}
.report-table td{padding:8px 12px;border-bottom:1px solid #eee;font-size:13px}
.report-table tr:hover{background:#f8f9fa}
.report-table a{color:#007cba}
.img-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;margin:15px 0}
.img-card{background:white;border-radius:6px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.1);cursor:pointer}
.img-card img{width:100%;height:130px;object-fit:cover}
.img-card.broken{border:3px solid #dc3545}
.img-card.broken img{display:none}
.img-card.broken::before{content:"❌ FEHLT";display:block;height:130px;line-height:130px;text-align:center;color:#dc3545;font-weight:bold;background:#fff5f5}
.img-info{padding:4px 8px;font-size:10px;color:#666;word-break:break-all}
.page-link{display:inline-block;background:#007cba;color:white;padding:6px 14px;border-radius:4px;text-decoration:none;margin:3px;font-size:13px}
.page-link:hover{background:#005a87}
.tab-nav{display:flex;gap:5px;margin:15px 0}.tab-btn{padding:8px 18px;background:#eee;border:none;border-radius:5px 5px 0 0;cursor:pointer;font-size:13px}.tab-btn.active{background:#007cba;color:white}.tab-panel{display:none}.tab-panel.active{display:block}
</style></head><body>
<h1>🔍 Qualitätsprüfung</h1>
<div class="stats">
<div class="stat"><div class="num">' . count($pages) . '</div><div class="label">Seiten</div></div>
<div class="stat"><div class="num">' . $count . '</div><div class="label">Bilder</div></div>
<div class="stat"><div class="num">' . count(glob($this->targetDir . '/css/*')) . '</div><div class="label">CSS</div></div>
<div class="stat"><div class="num">' . count(glob($this->targetDir . '/fonts/*')) . '</div><div class="label">Schriften</div></div>
<div class="stat"><div class="num">' . count(glob($this->targetDir . '/js/*')) . '</div><div class="label">JS</div></div>
</div>

<div class="tab-nav">
<button class="tab-btn active" onclick="showTab(\'pages\',this)">📋 Seiten-Report</button>
<button class="tab-btn" onclick="showTab(\'images\',this)">🖼️ Bilder-Galerie</button>
<button class="tab-btn" onclick="showTab(\'links\',this)">🔗 Seiten-Links</button>
</div>

<div id="tab-pages" class="tab-panel active">
<h2>Seiten-Report</h2>
<p>Jede kopierte Seite wird auf fehlende Referenzen, Bilder und CSS-Hintergründe geprüft.</p>
' . $pageReport . '
</div>

<div id="tab-images" class="tab-panel">
<h2>Alle Bilder (' . $count . ')</h2>
<p>Rot umrandet = fehlt/kaputt.</p>
<div class="img-grid">' . $imgHtml . '</div>
</div>

<div id="tab-links" class="tab-panel">
<h2>Seiten-Links</h2>
' . $pageLinks . '
</div>

<script>
function showTab(id,btn){document.querySelectorAll(".tab-panel").forEach(function(p){p.classList.remove("active")});document.querySelectorAll(".tab-btn").forEach(function(b){b.classList.remove("active")});document.getElementById("tab-"+id).classList.add("active");btn.classList.add("active")}
document.querySelectorAll(".img-card img").forEach(function(img){img.addEventListener("click",function(){window.open(img.src,"_blank")})});
</script>
</body></html>';
        file_put_contents($this->targetDir . '/check.html', $html);
    }

    private function emitProgress($msg) {
        if ($this->onProgress && is_callable($this->onProgress)) {
            ($this->onProgress)($msg);
        }
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

// API Endpoint - Streamed output to avoid timeouts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clone_url'])) {
    // Disable output buffering for streaming
    while (ob_get_level()) ob_end_flush();
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');

    $url = trim($_POST['clone_url']);
    if (empty($url)) {
        echo "data: " . json_encode(['type' => 'error', 'message' => 'Keine URL angegeben.']) . "\n\n";
        flush();
        exit;
    }
    if (!preg_match('#^https?://#', $url)) $url = 'https://' . $url;

    $maxPages = intval($_POST['max_pages'] ?? 200);
    $maxDepth = intval($_POST['max_depth'] ?? 5);
    $maxPages = max(1, min(500, $maxPages));
    $maxDepth = max(1, min(10, $maxDepth));

    $dirName = $_POST['dir_name'] ?? '';
    $dirName = preg_replace('/[^a-zA-Z0-9_-]/', '', $dirName);
    if (empty($dirName)) $dirName = WebsiteCloner::generateDirName($url);

    $targetDir = __DIR__ . '/' . $dirName;
    if (is_dir($targetDir)) {
        $dirName .= '_' . date('His');
        $targetDir = __DIR__ . '/' . $dirName;
    }

    $cloner = new WebsiteCloner($url, $targetDir, $maxPages, $maxDepth);

    // Progress callback
    $cloner->onProgress = function($msg) {
        echo "data: " . json_encode(['type' => 'progress', 'message' => $msg]) . "\n\n";
        flush();
    };

    $result = $cloner->clone_site();
    $result['directory'] = $dirName;

    echo "data: " . json_encode(['type' => 'done', 'result' => $result]) . "\n\n";
    flush();
    exit;
}

// Status check endpoint
if (isset($_GET['status']) && isset($_GET['dir'])) {
    header('Content-Type: application/json');
    $dir = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['dir']);
    $statusFile = __DIR__ . '/' . $dir . '/.clone_status.json';
    if (file_exists($statusFile)) {
        echo file_get_contents($statusFile);
    } else {
        echo json_encode(['status' => 'unknown']);
    }
    exit;
}
?>
