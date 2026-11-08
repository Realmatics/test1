#!/usr/bin/env php
<?php
/**
 * Deploy web/ to FTP root using PRET+PASV (Pure-FTPd friendly).
 * Usage: php scripts/deploy-web.php
 */
declare(strict_types=1);

$host = getenv('FTP_HOST') ?: '';
$user = getenv('FTP_USER') ?: '';
$pass = getenv('FTP_PASSWORD') ?: '';
$port = (int) (getenv('FTP_PORT') ?: 21);
if ($host === '' || $user === '' || $pass === '') {
    fwrite(STDERR, "FTP credentials missing\n");
    exit(1);
}

$root = realpath(__DIR__ . '/../web');
if ($root === false) {
    fwrite(STDERR, "web/ missing\n");
    exit(1);
}

function ftp_read($fp, int $timeout = 25): string
{
    stream_set_timeout($fp, $timeout);
    $data = '';
    while (!feof($fp)) {
        $line = fgets($fp, 8192);
        if ($line === false) {
            break;
        }
        $data .= $line;
        if (preg_match('/^\d{3} /m', $data) && str_ends_with(str_replace("\r", '', $data), "\n")) {
            // complete if last full line is final code
            $lines = preg_split("/\r?\n/", trim($data)) ?: [];
            $last = $lines[count($lines) - 1] ?? '';
            if (preg_match('/^\d{3} /', $last)) {
                return $data;
            }
            if (preg_match('/^\d{3}-/', $lines[0] ?? '') && preg_match('/^\d{3} /', $last)) {
                return $data;
            }
            if (!str_starts_with($lines[0] ?? '', substr($lines[0], 0, 3) . '-')) {
                return $data;
            }
        }
    }
    return $data;
}

function ftp_cmd($fp, string $cmd, int $timeout = 25): string
{
    fwrite($fp, $cmd . "\r\n");
    return ftp_read($fp, $timeout);
}

function ftp_upload_file(string $host, int $port, string $user, string $pass, string $remote, string $local): bool
{
    $content = file_get_contents($local);
    if ($content === false) {
        return false;
    }
    for ($attempt = 1; $attempt <= 6; $attempt++) {
        $fp = @fsockopen($host, $port, $errno, $errstr, 20);
        if (!$fp) {
            fwrite(STDERR, "connect fail: $errstr\n");
            continue;
        }
        ftp_read($fp);
        ftp_cmd($fp, 'USER ' . $user);
        $login = ftp_cmd($fp, 'PASS ' . $pass);
        if (!str_starts_with($login, '230')) {
            fclose($fp);
            fwrite(STDERR, "login fail\n");
            return false;
        }
        // Ensure remote dirs
        $parts = explode('/', trim(dirname($remote), '.'));
        $path = '';
        foreach ($parts as $part) {
            if ($part === '' || $part === '/') {
                continue;
            }
            $path .= '/' . $part;
            $resp = ftp_cmd($fp, 'MKD ' . ltrim($path, '/'));
            // ignore errors if exists
        }

        ftp_cmd($fp, 'PRET STOR ' . ltrim($remote, '/'));
        $pasv = ftp_cmd($fp, 'PASV');
        if (!preg_match('/\((\d+),(\d+),(\d+),(\d+),(\d+),(\d+)\)/', $pasv, $m)) {
            fclose($fp);
            continue;
        }
        $dip = "$m[1].$m[2].$m[3].$m[4]";
        $dport = ((int) $m[5]) * 256 + (int) $m[6];
        $data = @fsockopen($dip, $dport, $eno, $estr, 15);
        if (!$data) {
            fclose($fp);
            usleep(400000);
            continue;
        }
        ftp_cmd($fp, 'TYPE I');
        $stor = ftp_cmd($fp, 'STOR ' . ltrim($remote, '/'), 20);
        if (!str_starts_with($stor, '150') && !str_starts_with($stor, '125')) {
            fclose($data);
            fclose($fp);
            usleep(400000);
            continue;
        }
        fwrite($data, $content);
        fclose($data);
        $final = ftp_read($fp, 30);
        ftp_cmd($fp, 'SIZE ' . ltrim($remote, '/'));
        // SIZE response already in stream? send explicit
        $sizeResp = ftp_cmd($fp, 'SIZE ' . ltrim($remote, '/'));
        fclose($fp);
        if (preg_match('/^213\s+(\d+)/m', $sizeResp, $sm) && (int) $sm[1] === strlen($content)) {
            echo "OK $remote (" . strlen($content) . ")\n";
            return true;
        }
        fwrite(STDERR, "size mismatch $remote attempt $attempt :: $sizeResp\n");
        usleep(500000);
    }
    return false;
}

$skip = ['data/realmatics.sqlite', 'data/realmatics.sqlite-journal'];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$uploaded = 0;
$failed = [];
foreach ($files as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile()) {
        continue;
    }
    $full = $file->getPathname();
    $rel = substr($full, strlen($root) + 1);
    $rel = str_replace('\\', '/', $rel);
    if (str_contains($rel, 'node_modules/')) {
        continue;
    }
    if (in_array($rel, $skip, true)) {
        continue;
    }
    if ($rel === 'config.php') {
        echo "SKIP local config.php\n";
        continue;
    }
    echo "UPLOAD $rel ... ";
    if (!ftp_upload_file($host, $port, $user, $pass, $rel, $full)) {
        echo "FAIL\n";
        $failed[] = $rel;
    } else {
        $uploaded++;
    }
}

// Always upload a server config.php from sample (demo mode unless OPENAI_API_KEY env set)
$setupKey = 'rm-' . bin2hex(random_bytes(6));
$apiKey = getenv('OPENAI_API_KEY') ?: '';
$serverConfig = "<?php\nreturn [\n"
    . "    'app_name' => 'Realmatics',\n"
    . "    'app_url' => '',\n"
    . "    'admin_email' => 'hello@realmatics.example',\n"
    . "    'openai_api_key' => " . var_export($apiKey, true) . ",\n"
    . "    'openai_base_url' => 'https://api.openai.com/v1',\n"
    . "    'openai_model' => 'gpt-4o-mini',\n"
    . "    'admin_setup_key' => " . var_export($setupKey, true) . ",\n"
    . "    'stripe_payment_link_team' => '',\n"
    . "    'stripe_payment_link_pro' => '',\n"
    . "];\n";
$tmpCfg = sys_get_temp_dir() . '/realmatics-config.php';
file_put_contents($tmpCfg, $serverConfig);
echo "UPLOAD config.php ... ";
if (!ftp_upload_file($host, $port, $user, $pass, 'config.php', $tmpCfg)) {
    $failed[] = 'config.php';
    echo "FAIL\n";
} else {
    $uploaded++;
}
@unlink($tmpCfg);

// Remove obsolete static marketing file if present so index.php becomes entry
$ctrl = @fsockopen($host, $port, $errno, $errstr, 20);
if ($ctrl) {
    ftp_read($ctrl);
    ftp_cmd($ctrl, 'USER ' . $user);
    ftp_cmd($ctrl, 'PASS ' . $pass);
    $del = ftp_cmd($ctrl, 'DELE index.html');
    echo "DELE index.html :: " . trim(explode("\n", trim($del))[0] ?? '') . "\n";
    // Ensure data dir exists remotely
    ftp_cmd($ctrl, 'MKD data');
    ftp_cmd($ctrl, 'MKD storage');
    ftp_cmd($ctrl, 'MKD storage/uploads');
    fclose($ctrl);
}

file_put_contents('/opt/cursor/artifacts/realmatics-setup-key.txt', $setupKey . "\n");
echo "Uploaded=$uploaded failed=" . count($failed) . "\n";
echo "ADMIN_SETUP_KEY=$setupKey\n";
if ($failed) {
    fwrite(STDERR, "Failed:\n" . implode("\n", $failed) . "\n");
    exit(2);
}
