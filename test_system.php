<?php
// Test-Datei für das Backend-System
echo "=== Portfolio Backend System Test ===\n\n";

// PHP-Version prüfen
echo "PHP Version: " . phpversion() . "\n";

// Schreibrechte prüfen
$current_dir = getcwd();
echo "Aktuelles Verzeichnis: " . $current_dir . "\n";
echo "Schreibrechte im Verzeichnis: " . (is_writable($current_dir) ? "JA" : "NEIN") . "\n\n";

// Test: config.json erstellen
echo "=== Test 1: config.json erstellen ===\n";
$test_config = [
    'site' => [
        'title' => 'Test Portfolio',
        'hero_title' => 'Test Titel',
        'hero_subtitle' => 'Test Untertitel',
        'about_text' => 'Test Über mich Text',
        'skills' => ['HTML', 'CSS', 'JavaScript'],
        'footer_text' => '© 2024 Test Portfolio'
    ],
    'projects' => [
        [
            'title' => 'Test Projekt',
            'description' => 'Test Beschreibung',
            'image' => 'https://via.placeholder.com/400x300',
            'demo_link' => '#',
            'github_link' => '#'
        ]
    ],
    'social' => [
        'github' => '#',
        'linkedin' => '#',
        'twitter' => '#'
    ],
    'impressum' => [
        'name' => 'Test Name',
        'address' => 'Test Straße 1',
        'city' => '12345 Test Stadt',
        'phone' => '+49 123 456789',
        'email' => 'test@example.com',
        'tax_id' => 'DE123456789',
        'profession' => 'Webentwickler',
        'chamber' => 'Test Kammer',
        'country' => 'Deutschland'
    ],
    'datenschutz' => [
        'contact_info' => 'Test Name\nTest Straße 1\n12345 Test Stadt\nE-Mail: test@example.com'
    ]
];

$config_result = file_put_contents('config.json', json_encode($test_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
if ($config_result === false) {
    echo "FEHLER: Konnte config.json nicht erstellen!\n";
} else {
    echo "config.json erfolgreich erstellt (" . $config_result . " Bytes)\n";
}

// Test: generate_html.php laden und ausführen
echo "\n=== Test 2: HTML-Generierung ===\n";
if (file_exists('generate_html.php')) {
    echo "generate_html.php gefunden\n";
    
    // Konfiguration laden
    $config = json_decode(file_get_contents('config.json'), true);
    if ($config === null) {
        echo "FEHLER: Konnte config.json nicht lesen!\n";
    } else {
        echo "config.json erfolgreich geladen\n";
        
        // HTML generieren
        ob_start();
        include 'generate_html.php';
        $output = ob_get_clean();
        
        echo "Output von generate_html.php:\n" . $output . "\n";
        
        // Prüfen ob Dateien erstellt wurden
        $files_to_check = ['index_new.html', 'impressum_new.html', 'datenschutz_new.html'];
        foreach ($files_to_check as $file) {
            if (file_exists($file)) {
                echo "✓ " . $file . " erstellt (" . filesize($file) . " Bytes)\n";
            } else {
                echo "✗ " . $file . " NICHT erstellt\n";
            }
        }
    }
} else {
    echo "FEHLER: generate_html.php nicht gefunden!\n";
}

// Test: admin.php prüfen
echo "\n=== Test 3: admin.php prüfen ===\n";
if (file_exists('admin.php')) {
    echo "✓ admin.php gefunden\n";
    
    // Syntax-Check (einfach)
    $admin_content = file_get_contents('admin.php');
    if (strpos($admin_content, '<?php') === 0) {
        echo "✓ admin.php hat korrekten PHP-Start\n";
    } else {
        echo "✗ admin.php hat KEINEN korrekten PHP-Start\n";
    }
    
    if (strpos($admin_content, 'session_start()') !== false) {
        echo "✓ admin.php enthält session_start()\n";
    } else {
        echo "✗ admin.php enthält KEIN session_start()\n";
    }
} else {
    echo "✗ admin.php nicht gefunden!\n";
}

// Zusammenfassung
echo "\n=== Zusammenfassung ===\n";
echo "Test abgeschlossen. Prüfen Sie die Debug-Informationen oben.\n";
echo "Falls Fehler auftreten, stellen Sie sicher, dass:\n";
echo "1. PHP korrekt installiert ist\n";
echo "2. Das Verzeichnis Schreibrechte hat\n";
echo "3. Alle Dateien (admin.php, generate_html.php) vorhanden sind\n";
echo "\nZum Testen des Admin-Bereichs öffnen Sie admin.php in Ihrem Browser.\n";
echo "Standard-Passwort: admin123\n";
?> 