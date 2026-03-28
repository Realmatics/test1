<?php
session_start();

// Fehlerberichterstattung aktivieren für Debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Einfache Authentifizierung (in Produktion sollte dies sicherer sein)
$admin_password = 'admin123'; // Ändern Sie dieses Passwort!

if (isset($_POST['login'])) {
    if ($_POST['password'] === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $error = 'Falsches Passwort!';
    }
}

if (isset($_POST['logout'])) {
    // Vorschau-Dateien beim Ausloggen löschen
    $preview_files = ['index_vorschau.html', 'impressum_vorschau.html', 'datenschutz_vorschau.html'];
    foreach ($preview_files as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
    
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Prüfen ob eingeloggt
if (!isset($_SESSION['admin_logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 50px; }
            .login-container { max-width: 400px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
            .form-group { margin-bottom: 20px; }
            label { display: block; margin-bottom: 5px; font-weight: bold; }
            input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
            button { background: #007cba; color: white; padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; width: 100%; }
            button:hover { background: #005a87; }
            .error { color: red; margin-bottom: 15px; }
            
            /* Quill Editor Styling */
            .quill-editor { 
                background: white; 
                border: 1px solid #ddd; 
                border-radius: 4px; 
                margin-bottom: 10px;
            }
            .ql-toolbar { border-top: 1px solid #ddd !important; }
            .ql-container { border-bottom: 1px solid #ddd !important; min-height: 150px; }
            .ql-layout { 
                background: #007bff; 
                color: white; 
                border: none; 
                padding: 5px 8px; 
                margin: 2px; 
                border-radius: 3px; 
                font-size: 11px;
                cursor: pointer;
            }
            .ql-layout:hover { background: #0056b3; }
            
            /* CKEditor Styling */
            .ck-editor__editable {
                min-height: 400px;
            }
            .ck-editor__main {
                min-height: 400px;
            }
            
            /* Custom CKEditor Styles */
            .category {
                background: #007bff;
                color: white;
                padding: 5px 10px;
                border-radius: 3px;
                font-size: 0.9em;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            
            .info-box {
                background: #e7f3ff;
                border: 1px solid #b3d9ff;
                border-radius: 5px;
                padding: 15px;
                margin: 10px 0;
            }
            
            .side-quote {
                border-left: 4px solid #007bff;
                background: #f8f9fa;
                padding: 20px;
                margin: 20px 0;
                font-style: italic;
                position: relative;
            }
            
            .marker {
                background: yellow;
                padding: 2px 4px;
                border-radius: 2px;
            }
            
            .spoiler {
                background: #333;
                color: #333;
                padding: 2px 4px;
                border-radius: 2px;
                transition: color 0.3s;
            }
            
            .spoiler:hover {
                color: white;
            }
            
            .fancy-code {
                border-radius: 8px;
                padding: 20px;
                margin: 15px 0;
                font-family: 'Courier New', monospace;
                position: relative;
            }
            
            .fancy-code-dark {
                background: #2d3748;
                color: #e2e8f0;
                border: 1px solid #4a5568;
            }
            
            .fancy-code-bright {
                background: #f7fafc;
                color: #2d3748;
                border: 1px solid #e2e8f0;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h2>Admin Login</h2>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label for="password">Passwort:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" name="login">Einloggen</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Konfigurationsdatei laden
$config_file = 'config.json';
$debug_messages = [];

if (!file_exists($config_file)) {
    // Standard-Konfiguration erstellen
    $default_config = [
        'site' => [
            'title' => 'Mein Portfolio',
            'hero_title' => 'Willkommen in meinem Portfolio',
            'hero_subtitle' => 'Webentwickler & Designer',
            'about_text' => 'Ich bin ein leidenschaftlicher Webentwickler mit Fokus auf moderne und benutzerfreundliche Webanwendungen.',
            'skills' => ['HTML5', 'CSS3', 'JavaScript', 'React', 'Node.js'],
            'footer_text' => '© 2024 Mein Portfolio. Alle Rechte vorbehalten.'
        ],
        'projects' => [
            [
                'title' => 'E-Commerce Plattform',
                'description' => 'Eine moderne E-Commerce-Lösung mit React und Node.js. Features: Warenkorb, Zahlungsabwicklung, Benutzerauthentifizierung und ein intuitives Admin-Dashboard.',
                'image' => 'https://images.pexels.com/photos/34577/pexels-photo.jpg?auto=compress&cs=tinysrgb&w=1200&h=800&dpr=2',
                'demo_link' => '#',
                'github_link' => '#'
            ],
            [
                'title' => 'Task Management App',
                'description' => 'Eine intuitive Task-Management-Anwendung mit Drag & Drop-Funktionalität, Echtzeit-Updates und Team-Kollaboration. Entwickelt mit React und Firebase.',
                'image' => 'https://images.pexels.com/photos/1181406/pexels-photo-1181406.jpeg?auto=compress&cs=tinysrgb&w=1200&h=800&dpr=2',
                'demo_link' => '#',
                'github_link' => '#'
            ],
            [
                'title' => 'Social Media Dashboard',
                'description' => 'Ein umfassendes Dashboard für Social Media Analytics mit interaktiven Grafiken und Echtzeit-Datenvisualisierung. Integriert mit verschiedenen Social Media APIs.',
                'image' => 'https://images.pexels.com/photos/669615/pexels-photo-669615.jpeg?auto=compress&cs=tinysrgb&w=1200&h=800&dpr=2',
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
            'name' => '[Ihr vollständiger Name]',
            'address' => '[Ihre Straße und Hausnummer]',
            'city' => '[PLZ und Ort]',
            'phone' => '[Ihre Telefonnummer]',
            'email' => '[Ihre E-Mail-Adresse]',
            'tax_id' => '[Ihre Umsatzsteuer-ID]',
            'profession' => '[Ihre Berufsbezeichnung]',
            'chamber' => '[Name der zuständigen Kammer]',
            'country' => '[Land der Verleihung]'
        ],
        'datenschutz' => [
            'contact_info' => '[Ihre Kontaktdaten hier einfügen]'
        ],
        'neue_seite' => [
            'titel' => 'Meine neue Seite',
            'dateiname' => 'neue-seite',
            'inhalt' => '<h1>Willkommen auf meiner neuen Seite</h1><p>Hier können Sie Ihren Inhalt erstellen...</p>'
        ]
    ];
    
    $result = file_put_contents($config_file, json_encode($default_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    if ($result === false) {
        $debug_messages[] = 'FEHLER: Konnte config.json nicht erstellen. Prüfen Sie die Schreibrechte!';
    } else {
        $debug_messages[] = 'config.json wurde erfolgreich erstellt.';
    }
}

$config = json_decode(file_get_contents($config_file), true);
if ($config === null) {
    $debug_messages[] = 'FEHLER: Konnte config.json nicht lesen oder parsen!';
    $config = [];
}

// Debug: POST-Daten anzeigen
if ($_POST && !isset($_POST['logout'])) {
    $debug_messages[] = 'POST-Request empfangen. Keys: ' . implode(', ', array_keys($_POST));
    if (!$_SESSION['admin_logged_in']) {
        $debug_messages[] = 'FEHLER: Nicht eingeloggt!';
    }
}

// Formular verarbeiten
if ($_POST && isset($_POST['save_config']) && $_SESSION['admin_logged_in']) {
    $debug_messages[] = 'Formular wurde abgesendet...';
    
    try {
        // Konfiguration aktualisieren
        $config['site']['title'] = $_POST['site_title'] ?? '';
        $config['site']['hero_title'] = $_POST['hero_title'] ?? '';
        $config['site']['hero_subtitle'] = $_POST['hero_subtitle'] ?? '';
        $config['site']['about_text'] = $_POST['about_text'] ?? '';
        $config['site']['skills'] = array_filter(array_map('trim', explode(',', $_POST['skills'] ?? '')));
        $config['site']['footer_text'] = $_POST['footer_text'] ?? '';
        
        // Social Media Links
        $config['social']['github'] = $_POST['github'] ?? '';
        $config['social']['linkedin'] = $_POST['linkedin'] ?? '';
        $config['social']['twitter'] = $_POST['twitter'] ?? '';
        
        // Projekte
        $config['projects'] = [];
        if (isset($_POST['project_title']) && is_array($_POST['project_title'])) {
            for ($i = 0; $i < count($_POST['project_title']); $i++) {
                if (!empty($_POST['project_title'][$i])) {
                    $config['projects'][] = [
                        'title' => $_POST['project_title'][$i] ?? '',
                        'description' => $_POST['project_description'][$i] ?? '',
                        'image' => $_POST['project_image'][$i] ?? '',
                        'demo_link' => $_POST['project_demo'][$i] ?? '',
                        'github_link' => $_POST['project_github'][$i] ?? ''
                    ];
                }
            }
        }
        
        // Impressum
        $config['impressum']['name'] = $_POST['impressum_name'] ?? '';
        $config['impressum']['address'] = $_POST['impressum_address'] ?? '';
        $config['impressum']['city'] = $_POST['impressum_city'] ?? '';
        $config['impressum']['phone'] = $_POST['impressum_phone'] ?? '';
        $config['impressum']['email'] = $_POST['impressum_email'] ?? '';
        $config['impressum']['tax_id'] = $_POST['impressum_tax_id'] ?? '';
        $config['impressum']['profession'] = $_POST['impressum_profession'] ?? '';
        $config['impressum']['chamber'] = $_POST['impressum_chamber'] ?? '';
        $config['impressum']['country'] = $_POST['impressum_country'] ?? '';
        
        // Datenschutz
        $config['datenschutz']['contact_info'] = $_POST['datenschutz_contact'] ?? '';
        
        // Neue Seite
        $config['neue_seite']['titel'] = $_POST['neue_seite_titel'] ?? '';
        $config['neue_seite']['dateiname'] = $_POST['neue_seite_dateiname'] ?? '';
        $config['neue_seite']['inhalt'] = $_POST['neue_seite_inhalt'] ?? '';
        
        $debug_messages[] = 'Konfigurationsdaten wurden verarbeitet...';
        
        // Konfiguration speichern
        $json_result = file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        if ($json_result === false) {
            $debug_messages[] = 'FEHLER: Konnte config.json nicht speichern!';
        } else {
            $debug_messages[] = 'config.json wurde erfolgreich gespeichert (' . $json_result . ' Bytes).';
        }
        
        // HTML-Dateien generieren
        $debug_messages[] = 'Starte HTML-Generierung...';
        
        if (file_exists('generate_html.php')) {
            $debug_messages[] = 'generate_html.php gefunden, lade Datei...';
            
            // Capture any output from the include
            ob_start();
            include 'generate_html.php';
            $include_output = ob_get_clean();
            
            if (!empty($include_output)) {
                $debug_messages[] = 'Output von generate_html.php: ' . $include_output;
            }
            
            // Prüfen ob Dateien erstellt wurden
            $generated_files = [];
            if (file_exists('index_vorschau.html')) {
                $generated_files[] = 'index_vorschau.html (' . filesize('index_vorschau.html') . ' Bytes)';
            }
            if (file_exists('impressum_vorschau.html')) {
                $generated_files[] = 'impressum_vorschau.html (' . filesize('impressum_vorschau.html') . ' Bytes)';
            }
            if (file_exists('datenschutz_vorschau.html')) {
                $generated_files[] = 'datenschutz_vorschau.html (' . filesize('datenschutz_vorschau.html') . ' Bytes)';
            }
            
            if (!empty($generated_files)) {
                $debug_messages[] = 'Erfolgreich generierte Dateien: ' . implode(', ', $generated_files);
                $success = 'Konfiguration gespeichert und HTML-Dateien generiert!';
            } else {
                $debug_messages[] = 'FEHLER: Keine HTML-Dateien wurden generiert!';
            }
        } else {
            $debug_messages[] = 'FEHLER: generate_html.php nicht gefunden!';
        }
        
    } catch (Exception $e) {
        $debug_messages[] = 'EXCEPTION: ' . $e->getMessage();
    }
}

// Funktion zum Extrahieren von Inhalten aus den originalen HTML-Dateien
function extractContentFromHTML() {
    $config = [];
    
    // index.html analysieren
    if (file_exists('index.html')) {
        $html = file_get_contents('index.html');
        
        // Title extrahieren
        if (preg_match('/<title>(.*?)<\/title>/s', $html, $matches)) {
            $config['site']['title'] = trim(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'));
        }
        
        // Hero-Titel extrahieren
        if (preg_match('/<section[^>]*id="home"[^>]*>.*?<h1[^>]*>(.*?)<\/h1>/s', $html, $matches)) {
            $config['site']['hero_title'] = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8'));
        }
        
        // Hero-Untertitel extrahieren
        if (preg_match('/<section[^>]*id="home"[^>]*>.*?<h1[^>]*>.*?<\/h1>.*?<p[^>]*>(.*?)<\/p>/s', $html, $matches)) {
            $config['site']['hero_subtitle'] = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8'));
        }
        
        // Über mich Text extrahieren
        if (preg_match('/<section[^>]*id="about"[^>]*>.*?<div[^>]*class="about-text"[^>]*>.*?<p[^>]*>(.*?)<\/p>/s', $html, $matches)) {
            $config['site']['about_text'] = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8'));
        }
        
        // Skills extrahieren
        $skills = [];
        if (preg_match('/<div[^>]*class="skill-tags"[^>]*>(.*?)<\/div>/s', $html, $matches)) {
            if (preg_match_all('/<span[^>]*>(.*?)<\/span>/s', $matches[1], $skill_matches)) {
                foreach ($skill_matches[1] as $skill) {
                    $skills[] = trim(html_entity_decode(strip_tags($skill), ENT_QUOTES, 'UTF-8'));
                }
            }
        }
        $config['site']['skills'] = $skills;
        
        // Footer-Text extrahieren
        if (preg_match('/<footer[^>]*>.*?<p[^>]*>(.*?)<\/p>/s', $html, $matches)) {
            $config['site']['footer_text'] = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8'));
        } else if (preg_match('/&copy;.*?<\/p>/s', $html, $matches)) {
            $config['site']['footer_text'] = trim(html_entity_decode(strip_tags($matches[0]), ENT_QUOTES, 'UTF-8'));
        }
        
        // Social Media Links extrahieren
        if (preg_match('/<div[^>]*class="social-links"[^>]*>(.*?)<\/div>/s', $html, $matches)) {
            $social_html = $matches[1];
            
            // GitHub Link
            if (preg_match('/<a[^>]*href="([^"]*)"[^>]*title="GitHub"/s', $social_html, $github_match)) {
                $config['social']['github'] = $github_match[1];
            }
            
            // LinkedIn Link
            if (preg_match('/<a[^>]*href="([^"]*)"[^>]*title="LinkedIn"/s', $social_html, $linkedin_match)) {
                $config['social']['linkedin'] = $linkedin_match[1];
            }
            
            // Twitter Link
            if (preg_match('/<a[^>]*href="([^"]*)"[^>]*title="Twitter"/s', $social_html, $twitter_match)) {
                $config['social']['twitter'] = $twitter_match[1];
            }
        }
        
        // Projekte extrahieren - vereinfachter Ansatz
        $projects = [];
        
        // Alle H3-Titel finden (Projekttitel)
        if (preg_match_all('/<h3[^>]*>(.*?)<\/h3>/s', $html, $title_matches)) {
            foreach ($title_matches[1] as $index => $title) {
                $project_title = trim(strip_tags($title));
                
                // Nur Projekttitel verwenden (nicht andere H3s)
                if (in_array($project_title, ['E-Commerce Plattform', 'Task Management App', 'Social Media Dashboard']) || 
                    strpos($project_title, 'Projekt') !== false || 
                    strpos($project_title, 'App') !== false || 
                    strpos($project_title, 'Dashboard') !== false ||
                    strpos($project_title, 'Plattform') !== false) {
                    
                    $project = [
                        'title' => html_entity_decode($project_title, ENT_QUOTES, 'UTF-8'),
                        'description' => '',
                        'image' => '',
                        'demo_link' => '#',
                        'github_link' => '#'
                    ];
                    
                    // Beschreibung suchen (nächster P-Tag nach dem H3)
                    $title_pos = strpos($html, '<h3');
                    if ($title_pos !== false) {
                        $after_title = substr($html, $title_pos);
                        if (preg_match('/<h3[^>]*>' . preg_quote($project_title, '/') . '<\/h3>.*?<p[^>]*>(.*?)<\/p>/s', $after_title, $desc_match)) {
                            $project['description'] = trim(html_entity_decode(strip_tags($desc_match[1]), ENT_QUOTES, 'UTF-8'));
                        }
                    }
                    
                    // Bild suchen (IMG-Tag vor dem H3)
                    $before_title = substr($html, 0, $title_pos + 1000); // Bereich um den Titel
                    if (preg_match_all('/<img[^>]*src="([^"]*)"[^>]*>/s', $before_title, $img_matches)) {
                        // Letztes Bild vor dem Titel nehmen
                        $project['image'] = end($img_matches[1]);
                    }
                    
                    $projects[] = $project;
                }
            }
        }
        
        // Falls keine Projekte gefunden wurden, Standard-Projekte aus der HTML-Struktur extrahieren
        if (empty($projects)) {
            $default_projects = [
                [
                    'title' => 'E-Commerce Plattform',
                    'description' => 'Eine moderne E-Commerce-Lösung mit React und Node.js. Features: Warenkorb, Zahlungsabwicklung, Benutzerauthentifizierung und ein intuitives Admin-Dashboard.',
                    'image' => 'https://images.pexels.com/photos/34577/pexels-photo.jpg?auto=compress&cs=tinysrgb&w=1200&h=800&dpr=2',
                    'demo_link' => '#',
                    'github_link' => '#'
                ],
                [
                    'title' => 'Task Management App',
                    'description' => 'Eine intuitive Task-Management-Anwendung mit Drag & Drop-Funktionalität, Echtzeit-Updates und Team-Kollaboration. Entwickelt mit React und Firebase.',
                    'image' => 'https://images.pexels.com/photos/1181406/pexels-photo-1181406.jpeg?auto=compress&cs=tinysrgb&w=1200&h=800&dpr=2',
                    'demo_link' => '#',
                    'github_link' => '#'
                ],
                [
                    'title' => 'Social Media Dashboard',
                    'description' => 'Ein umfassendes Dashboard für Social Media Analytics mit interaktiven Grafiken und Echtzeit-Datenvisualisierung. Integriert mit verschiedenen Social Media APIs.',
                    'image' => 'https://images.pexels.com/photos/669615/pexels-photo-669615.jpeg?auto=compress&cs=tinysrgb&w=1200&h=800&dpr=2',
                    'demo_link' => '#',
                    'github_link' => '#'
                ]
            ];
            $projects = $default_projects;
        }
        
        $config['projects'] = $projects;
    }
    
    // impressum.html analysieren
    if (file_exists('impressum.html')) {
        $html = file_get_contents('impressum.html');
        
        // Name, Adresse, Stadt extrahieren
        if (preg_match('/<h2>Angaben gemäß § 5 TMG<\/h2>\s*<p[^>]*>(.*?)<\/p>/s', $html, $matches)) {
            $address_lines = explode('<br>', $matches[1]);
            $config['impressum']['name'] = trim(html_entity_decode(strip_tags($address_lines[0] ?? ''), ENT_QUOTES, 'UTF-8'));
            $config['impressum']['address'] = trim(html_entity_decode(strip_tags($address_lines[1] ?? ''), ENT_QUOTES, 'UTF-8'));
            $config['impressum']['city'] = trim(html_entity_decode(strip_tags($address_lines[2] ?? ''), ENT_QUOTES, 'UTF-8'));
        }
        
        // Telefon und E-Mail extrahieren
        if (preg_match('/<h2>Kontakt<\/h2>\s*<p[^>]*>Telefon:\s*(.*?)<br>\s*E-Mail:\s*(.*?)<\/p>/s', $html, $matches)) {
            $config['impressum']['phone'] = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8'));
            $config['impressum']['email'] = trim(html_entity_decode(strip_tags($matches[2]), ENT_QUOTES, 'UTF-8'));
        }
        
        // Umsatzsteuer-ID extrahieren
        if (preg_match('/<h2>Umsatzsteuer-ID<\/h2>\s*<p[^>]*>.*?<br>\s*(.*?)<\/p>/s', $html, $matches)) {
            $config['impressum']['tax_id'] = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8'));
        }
        
        // Berufsbezeichnung extrahieren
        if (preg_match('/<h2>Berufsbezeichnung und berufsrechtliche Regelungen<\/h2>\s*<p[^>]*>Berufsbezeichnung:\s*(.*?)<br>\s*Zuständige Kammer:\s*(.*?)<br>\s*Verliehen in:\s*(.*?)<\/p>/s', $html, $matches)) {
            $config['impressum']['profession'] = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8'));
            $config['impressum']['chamber'] = trim(html_entity_decode(strip_tags($matches[2]), ENT_QUOTES, 'UTF-8'));
            $config['impressum']['country'] = trim(html_entity_decode(strip_tags($matches[3]), ENT_QUOTES, 'UTF-8'));
        }
    }
    
    // datenschutz.html analysieren
    if (file_exists('datenschutz.html')) {
        $html = file_get_contents('datenschutz.html');
        
        // Kontaktdaten extrahieren
        if (preg_match('/<h2>5\. Kontakt<\/h2>.*?<p[^>]*>\[Ihre Kontaktdaten hier einfügen\]<\/p>/s', $html, $matches)) {
            $config['datenschutz']['contact_info'] = '[Ihre Kontaktdaten hier einfügen]';
        } else if (preg_match('/<h2>5\. Kontakt<\/h2>.*?<p[^>]*>(.*?)<\/p>/s', $html, $matches)) {
            $config['datenschutz']['contact_info'] = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8'));
        }
    }
    
    return $config;
}

// Inhalte automatisch aus HTML-Dateien laden (nur beim ersten Start, nicht nach dem Speichern)
if (!isset($_POST['save_config'])) {
    try {
        $extracted_config = extractContentFromHTML();
        
        // Standard-Werte für fehlende Felder setzen
        $default_config = [
            'site' => [
                'title' => 'Mein Portfolio',
                'hero_title' => 'Willkommen in meinem Portfolio',
                'hero_subtitle' => 'Webentwickler & Designer',
                'about_text' => 'Ich bin ein leidenschaftlicher Webentwickler mit Fokus auf moderne und benutzerfreundliche Webanwendungen.',
                'skills' => ['HTML5', 'CSS3', 'JavaScript', 'React', 'Node.js'],
                'footer_text' => '© 2024 Mein Portfolio. Alle Rechte vorbehalten.'
            ],
            'projects' => [],
            'social' => [
                'github' => '#',
                'linkedin' => '#',
                'twitter' => '#'
            ],
            'impressum' => [
                'name' => '[Ihr vollständiger Name]',
                'address' => '[Ihre Straße und Hausnummer]',
                'city' => '[PLZ und Ort]',
                'phone' => '[Ihre Telefonnummer]',
                'email' => '[Ihre E-Mail-Adresse]',
                'tax_id' => '[Ihre Umsatzsteuer-ID]',
                'profession' => '[Ihre Berufsbezeichnung]',
                'chamber' => '[Name der zuständigen Kammer]',
                'country' => '[Land der Verleihung]'
            ],
            'datenschutz' => [
                'contact_info' => '[Ihre Kontaktdaten hier einfügen]'
            ]
        ];
        
        // Extrahierte Werte mit Standard-Werten zusammenführen
        function array_merge_recursive_distinct($array1, $array2) {
            $merged = $array1;
            foreach ($array2 as $key => $value) {
                if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                    $merged[$key] = array_merge_recursive_distinct($merged[$key], $value);
                } else if (!empty($value)) {
                    $merged[$key] = $value;
                }
            }
            return $merged;
        }
        
        $final_config = array_merge_recursive_distinct($default_config, $extracted_config);
        
        // Konfiguration für die Anzeige verwenden (aber nicht automatisch speichern)
        $config = $final_config;
        $debug_messages[] = "Inhalte automatisch aus originalen HTML-Dateien geladen. Gefundene Projekte: " . count($final_config['projects']);
        
    } catch (Exception $e) {
        $debug_messages[] = "Fehler beim automatischen Extrahieren: " . $e->getMessage();
    }
} else {
    // Nach dem Speichern: Zeige die gespeicherten Inhalte aus config.json an
    $debug_messages[] = "Zeige gespeicherte Inhalte aus config.json an (Vorschau-Variante).";
}

// Prüfen ob Vorschau-Dateien existieren
function previewFilesExist() {
    return file_exists('index_vorschau.html') && 
           file_exists('impressum_vorschau.html') && 
           file_exists('datenschutz_vorschau.html');
}

$preview_exists = previewFilesExist();

// Vorschau freigeben (HTML-Dateien überschreiben)
if (isset($_POST['release_preview']) && $_SESSION['admin_logged_in']) {
    try {
        $released_files = [];
        $errors = [];
        
        // index_vorschau.html → index.html
        if (file_exists('index_vorschau.html')) {
            if (copy('index_vorschau.html', 'index.html')) {
                $released_files[] = 'index.html';
            } else {
                $errors[] = 'index.html konnte nicht überschrieben werden';
            }
        } else {
            $errors[] = 'index_vorschau.html nicht gefunden';
        }
        
        // impressum_vorschau.html → impressum.html
        if (file_exists('impressum_vorschau.html')) {
            if (copy('impressum_vorschau.html', 'impressum.html')) {
                $released_files[] = 'impressum.html';
            } else {
                $errors[] = 'impressum.html konnte nicht überschrieben werden';
            }
        } else {
            $errors[] = 'impressum_vorschau.html nicht gefunden';
        }
        
        // datenschutz_vorschau.html → datenschutz.html
        if (file_exists('datenschutz_vorschau.html')) {
            if (copy('datenschutz_vorschau.html', 'datenschutz.html')) {
                $released_files[] = 'datenschutz.html';
            } else {
                $errors[] = 'datenschutz.html konnte nicht überschrieben werden';
            }
        } else {
            $errors[] = 'datenschutz_vorschau.html nicht gefunden';
        }
        
        if (!empty($released_files)) {
            $success_message = "Vorschau erfolgreich freigegeben! Überschriebene Dateien: " . implode(', ', $released_files);
            
            // Nach erfolgreicher Freigabe: Vorschau-Dateien löschen
            $preview_files_to_delete = ['index_vorschau.html', 'impressum_vorschau.html', 'datenschutz_vorschau.html'];
            foreach ($preview_files_to_delete as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
            
            // Preview-Status aktualisieren
            $preview_exists = false;
        }
        
        if (!empty($errors)) {
            $error_message = "Fehler beim Freigeben: " . implode(', ', $errors);
        }
        
    } catch (Exception $e) {
        $error_message = "Fehler beim Freigeben der Vorschau: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portfolio Admin</title>
    <!-- Quill.js CSS und JS -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    
    <!-- CKEditor 5 mit allen Features -->
    <script src="https://cdn.ckeditor.com/ckeditor5/40.0.0/super-build/ckeditor.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f4f4f4; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #eee; }
        .logout-btn { background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; }
        .logout-btn:hover { background: #c82333; }
        .section { margin-bottom: 40px; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .section h2 { margin-top: 0; color: #333; border-bottom: 2px solid #007cba; padding-bottom: 10px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        .form-group textarea { height: 100px; resize: vertical; }
        .project-item { border: 1px solid #eee; padding: 15px; margin-bottom: 15px; border-radius: 5px; background: #f9f9f9; }
        .project-item h4 { margin-top: 0; color: #007cba; }
        .btn-add { background: #28a745; color: white; padding: 8px 15px; border: none; border-radius: 3px; cursor: pointer; margin-bottom: 15px; }
        .btn-add:hover { background: #218838; }
        .btn-remove { background: #dc3545; color: white; padding: 5px 10px; border: none; border-radius: 3px; cursor: pointer; float: right; }
        .btn-remove:hover { background: #c82333; }
        .save-btn { background: #007cba; color: white; padding: 15px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        .save-btn:hover { background: #005a87; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .debug { background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .debug h3 { margin-top: 0; color: #495057; }
        .debug ul { margin: 0; padding-left: 20px; }
        .debug li { margin-bottom: 5px; font-family: monospace; font-size: 12px; }
        .tabs { display: flex; margin-bottom: 20px; }
        .tab { padding: 10px 20px; background: #eee; border: 1px solid #ddd; cursor: pointer; margin-right: 5px; border-radius: 5px 5px 0 0; user-select: none; }
        .tab:hover { background: #ddd; }
        .tab.active { background: #007cba; color: white; }
        .tab.active:hover { background: #005a87; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Portfolio Administration</h1>
            <div style="display: flex; gap: 10px;">
                <button type="button" onclick="window.open('index_vorschau.html', '_blank')" 
                        style="background: <?php echo $preview_exists ? '#28a745' : '#6c757d'; ?>; color: white; padding: 10px 15px; border: none; border-radius: 5px; cursor: <?php echo $preview_exists ? 'pointer' : 'not-allowed'; ?>;" 
                        <?php echo $preview_exists ? '' : 'disabled'; ?>>
                    👁️ Vorschau prüfen
                </button>
                <form method="POST" style="margin: 0;">
                    <button type="submit" name="release_preview" 
                            onclick="<?php echo $preview_exists ? "return confirm('Möchten Sie die Vorschau-Dateien (_vorschau) als Live-Version freigeben? Dies überschreibt die aktuellen HTML-Dateien!')" : 'return false'; ?>" 
                            style="background: <?php echo $preview_exists ? '#fd7e14' : '#6c757d'; ?>; color: white; padding: 10px 15px; border: none; border-radius: 5px; cursor: <?php echo $preview_exists ? 'pointer' : 'not-allowed'; ?>;" 
                            <?php echo $preview_exists ? '' : 'disabled'; ?>>
                        🚀 Vorschau freigeben
                    </button>
                </form>
                <button type="button" onclick="window.open('index.html', '_blank')" style="background: #17a2b8; color: white; padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer;">
                    🌐 Zur Website
                </button>
                <form method="POST" style="margin: 0;">
                    <button type="submit" name="logout" style="background: #dc3545; color: white; padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer;">
                        🚪 Ausloggen
                    </button>
                </form>
            </div>
        </div>

        <?php if (!empty($debug_messages)): ?>
            <div class="debug">
                <h3>Debug-Informationen:</h3>
                <ul>
                    <?php foreach ($debug_messages as $message): ?>
                        <li><?php echo htmlspecialchars($message); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
            <script>
                // Buttons nach erfolgreichem Speichern aktivieren
                enablePreviewButtons();
            </script>
        <?php endif; ?>
        
        <?php if (isset($success_message)): ?>
            <div class="success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php if (strpos($success_message, 'freigegeben') !== false): ?>
                <script>
                    // Buttons nach Freigabe deaktivieren
                    disablePreviewButtons();
                </script>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="tabs">
            <div class="tab active">Allgemein</div>
            <div class="tab">Projekte</div>
            <div class="tab">Impressum</div>
            <div class="tab">Datenschutz</div>
            <div class="tab">Neue Seite</div>
            <div class="tab">Website kopieren</div>
        </div>

        <form method="POST">
            <!-- Allgemeine Einstellungen -->
            <div id="general" class="tab-content active">
                <div class="section">
                    <h2>Website-Grunddaten</h2>
                    <div class="form-group">
                        <label for="site_title">Website-Titel:</label>
                        <input type="text" id="site_title" name="site_title" value="<?php echo htmlspecialchars($config['site']['title'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="hero_title">Hero-Titel:</label>
                        <input type="text" id="hero_title" name="hero_title" value="<?php echo htmlspecialchars($config['site']['hero_title'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="hero_subtitle">Hero-Untertitel:</label>
                        <input type="text" id="hero_subtitle" name="hero_subtitle" value="<?php echo htmlspecialchars($config['site']['hero_subtitle'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="about_text">Über mich Text:</label>
                        <div id="about_text_editor" class="quill-editor"></div>
                        <textarea id="about_text" name="about_text" style="display: none;"><?php echo htmlspecialchars($config['site']['about_text'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="skills">Fähigkeiten (kommagetrennt):</label>
                        <input type="text" id="skills" name="skills" value="<?php echo htmlspecialchars(implode(', ', $config['site']['skills'] ?? [])); ?>">
                    </div>
                    <div class="form-group">
                        <label for="footer_text">Footer-Text:</label>
                        <input type="text" id="footer_text" name="footer_text" value="<?php echo htmlspecialchars($config['site']['footer_text'] ?? ''); ?>">
                    </div>
                </div>

                <div class="section">
                    <h2>Social Media Links</h2>
                    <div class="form-group">
                        <label for="github">GitHub URL:</label>
                        <input type="text" id="github" name="github" value="<?php echo htmlspecialchars($config['social']['github'] ?? ''); ?>" placeholder="https://github.com/username oder #">
                    </div>
                    <div class="form-group">
                        <label for="linkedin">LinkedIn URL:</label>
                        <input type="text" id="linkedin" name="linkedin" value="<?php echo htmlspecialchars($config['social']['linkedin'] ?? ''); ?>" placeholder="https://linkedin.com/in/username oder #">
                    </div>
                    <div class="form-group">
                        <label for="twitter">Twitter URL:</label>
                        <input type="text" id="twitter" name="twitter" value="<?php echo htmlspecialchars($config['social']['twitter'] ?? ''); ?>" placeholder="https://twitter.com/username oder #">
                    </div>
                </div>
            </div>

            <!-- Projekte -->
            <div id="projects" class="tab-content">
                <div class="section">
                    <h2>Projekte</h2>
                    <button type="button" class="btn-add" onclick="addProject()">Neues Projekt hinzufügen</button>
                    <div id="projects-container">
                        <?php if (isset($config['projects']) && is_array($config['projects'])): ?>
                            <?php foreach ($config['projects'] as $index => $project): ?>
                                <div class="project-item">
                                    <button type="button" class="btn-remove" onclick="removeProject(this)">Entfernen</button>
                                    <h4>Projekt <?php echo $index + 1; ?></h4>
                                    <div class="form-group">
                                        <label>Titel:</label>
                                        <input type="text" name="project_title[]" value="<?php echo htmlspecialchars($project['title'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Beschreibung:</label>
                                        <div class="quill-editor project-editor" data-index="<?php echo $index; ?>"></div>
                                        <textarea name="project_description[]" style="display: none;"><?php echo htmlspecialchars($project['description'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Bild-URL:</label>
                                        <input type="text" name="project_image[]" value="<?php echo htmlspecialchars($project['image'] ?? ''); ?>" placeholder="https://example.com/image.jpg">
                                    </div>
                                    <div class="form-group">
                                        <label>Demo-Link:</label>
                                        <input type="text" name="project_demo[]" value="<?php echo htmlspecialchars($project['demo_link'] ?? ''); ?>" placeholder="https://example.com oder #">
                                    </div>
                                    <div class="form-group">
                                        <label>GitHub-Link:</label>
                                        <input type="text" name="project_github[]" value="<?php echo htmlspecialchars($project['github_link'] ?? ''); ?>" placeholder="https://github.com/user/repo oder #">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Impressum -->
            <div id="impressum" class="tab-content">
                <div class="section">
                    <h2>Impressum</h2>
                    <div class="form-group">
                        <label for="impressum_name">Vollständiger Name:</label>
                        <input type="text" id="impressum_name" name="impressum_name" value="<?php echo htmlspecialchars($config['impressum']['name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="impressum_address">Straße und Hausnummer:</label>
                        <input type="text" id="impressum_address" name="impressum_address" value="<?php echo htmlspecialchars($config['impressum']['address'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="impressum_city">PLZ und Ort:</label>
                        <input type="text" id="impressum_city" name="impressum_city" value="<?php echo htmlspecialchars($config['impressum']['city'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="impressum_phone">Telefonnummer:</label>
                        <input type="text" id="impressum_phone" name="impressum_phone" value="<?php echo htmlspecialchars($config['impressum']['phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="impressum_email">E-Mail-Adresse:</label>
                        <input type="text" id="impressum_email" name="impressum_email" value="<?php echo htmlspecialchars($config['impressum']['email'] ?? ''); ?>" placeholder="ihre.email@example.com">
                    </div>
                    <div class="form-group">
                        <label for="impressum_tax_id">Umsatzsteuer-ID:</label>
                        <input type="text" id="impressum_tax_id" name="impressum_tax_id" value="<?php echo htmlspecialchars($config['impressum']['tax_id'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="impressum_profession">Berufsbezeichnung:</label>
                        <input type="text" id="impressum_profession" name="impressum_profession" value="<?php echo htmlspecialchars($config['impressum']['profession'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="impressum_chamber">Zuständige Kammer:</label>
                        <input type="text" id="impressum_chamber" name="impressum_chamber" value="<?php echo htmlspecialchars($config['impressum']['chamber'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="impressum_country">Land der Verleihung:</label>
                        <input type="text" id="impressum_country" name="impressum_country" value="<?php echo htmlspecialchars($config['impressum']['country'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Datenschutz -->
            <div id="datenschutz" class="tab-content">
                <div class="section">
                    <h2>Datenschutzerklärung</h2>
                    <div class="form-group">
                        <label for="datenschutz_contact">Kontaktdaten:</label>
                        <div id="datenschutz_contact_editor" class="quill-editor"></div>
                        <textarea id="datenschutz_contact" name="datenschutz_contact" style="display: none;"><?php echo htmlspecialchars($config['datenschutz']['contact_info'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Neue Seite -->
            <div id="neue_seite" class="tab-content">
                <div class="section">
                    <h2>Neue Seite erstellen</h2>
                    <div class="form-group">
                        <label for="neue_seite_titel">Seitentitel:</label>
                        <input type="text" id="neue_seite_titel" name="neue_seite_titel" value="<?php echo htmlspecialchars($config['neue_seite']['titel'] ?? 'Meine neue Seite'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="neue_seite_dateiname">Dateiname (ohne .html):</label>
                        <input type="text" id="neue_seite_dateiname" name="neue_seite_dateiname" value="<?php echo htmlspecialchars($config['neue_seite']['dateiname'] ?? 'neue-seite'); ?>" placeholder="z.B. neue-seite">
                    </div>
                    <div class="form-group">
                        <label for="neue_seite_inhalt">Seiteninhalt:</label>
                        <button type="button" onclick="reloadCKEditor()" style="margin-bottom: 10px; padding: 5px 10px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer;">🔄 Editor neu laden</button>
                        <div id="neue_seite_editor"></div>
                        <textarea id="neue_seite_inhalt" name="neue_seite_inhalt" style="display: none;"><?php echo htmlspecialchars($config['neue_seite']['inhalt'] ?? '<h1>Willkommen auf meiner neuen Seite</h1><p>Hier können Sie Ihren Inhalt erstellen...</p>'); ?></textarea>
                    </div>
                </div>
            </div>

            <button type="submit" name="save_config" class="save-btn">Speichern und Vorschau generieren</button>
        </form>

        <!-- Website kopieren (außerhalb des Hauptformulars) -->
        <div id="clone_website" class="tab-content">
            <div class="section">
                <h2>Website kopieren</h2>
                <p style="color: #666; margin-bottom: 20px;">Geben Sie eine URL ein, um eine fremde Website als funktionsfähige Kopie in ein Unterverzeichnis zu laden. Die Kopie enthält alle CSS-Styles, Bilder, Schriften und JavaScript-Dateien.</p>
                <div class="form-group">
                    <label for="clone_url">Website-URL:</label>
                    <input type="text" id="clone_url" placeholder="https://www.beispiel.de/" style="font-size: 16px; padding: 12px;">
                </div>
                <button type="button" id="clone_btn" onclick="cloneWebsite()" style="background: #6f42c1; color: white; padding: 15px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">
                    🌐 Website kopieren
                </button>
                <div id="clone_status" style="margin-top: 20px; display: none;">
                    <div id="clone_spinner" style="display: none; padding: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px;">
                        <strong>⏳ Kopiervorgang läuft...</strong>
                        <p>Dies kann je nach Website-Größe 10-60 Sekunden dauern.</p>
                    </div>
                    <div id="clone_result" style="display: none; padding: 20px; border-radius: 5px; margin-top: 10px;"></div>
                    <div id="clone_log" style="display: none; margin-top: 10px; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px;"></div>
                </div>
                <div style="margin-top: 30px;">
                    <h3>Kopierte Websites</h3>
                    <div id="cloned_sites_list">
                        <?php
                        $dirs = glob(__DIR__ . '/*/.clone_info');
                        $cloned = [];
                        foreach ($dirs as $infoFile) {
                            $dirPath = dirname($infoFile);
                            $dirName = basename($dirPath);
                            $info = json_decode(file_get_contents($infoFile), true);
                            $source = $info['source'] ?? '';
                            $date = $info['date'] ?? '';
                            if ($source) {
                                $cloned[] = ['dir' => $dirName, 'source' => $source, 'date' => $date];
                            }
                        }
                        if (empty($cloned)): ?>
                            <p style="color: #999;">Noch keine Websites kopiert.</p>
                        <?php else:
                            foreach ($cloned as $site): ?>
                                <div style="padding: 10px 15px; margin: 5px 0; background: #f0f0f0; border-radius: 5px; display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong><a href="<?php echo htmlspecialchars($site['dir']); ?>/index.html" target="_blank"><?php echo htmlspecialchars($site['dir']); ?></a></strong>
                                        <span style="color: #888; font-size: 12px; margin-left: 10px;">von: <?php echo htmlspecialchars($site['source']); ?></span>
                                    </div>
                                    <a href="<?php echo htmlspecialchars($site['dir']); ?>/index.html" target="_blank" style="background: #28a745; color: white; padding: 5px 12px; border-radius: 3px; text-decoration: none; font-size: 13px;">Öffnen ↗</a>
                                </div>
                            <?php endforeach;
                        endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentTab = 'general';
        
        // Funktion zum Aktivieren der Vorschau-Buttons
        function enablePreviewButtons() {
            const previewButton = document.querySelector('button[onclick*="index_vorschau.html"]');
            const releaseButton = document.querySelector('button[name="release_preview"]');
            
            if (previewButton) {
                previewButton.style.background = '#28a745';
                previewButton.style.cursor = 'pointer';
                previewButton.disabled = false;
            }
            
            if (releaseButton) {
                releaseButton.style.background = '#fd7e14';
                releaseButton.style.cursor = 'pointer';
                releaseButton.disabled = false;
                releaseButton.onclick = function() {
                    return confirm('Möchten Sie die Vorschau-Dateien (_vorschau) als Live-Version freigeben? Dies überschreibt die aktuellen HTML-Dateien!');
                };
            }
        }
        
        // Funktion zum Deaktivieren der Vorschau-Buttons
        function disablePreviewButtons() {
            const previewButton = document.querySelector('button[onclick*="index_vorschau.html"]');
            const releaseButton = document.querySelector('button[name="release_preview"]');
            
            if (previewButton) {
                previewButton.style.background = '#6c757d';
                previewButton.style.cursor = 'not-allowed';
                previewButton.disabled = true;
            }
            
            if (releaseButton) {
                releaseButton.style.background = '#6c757d';
                releaseButton.style.cursor = 'not-allowed';
                releaseButton.disabled = true;
                releaseButton.onclick = function() {
                    return false;
                };
            }
        }
        
        // Globale Variable für Quill Editoren
        var quillEditors = {};
        
        // Initialisierung beim Laden der Seite
        document.addEventListener('DOMContentLoaded', function() {
            // Tab-System initialisieren
            showTab('general');
            
            // Quill.js HTML-Editor initialisieren
            
            // Toolbar-Konfiguration mit Spalten-Layouts
            var toolbarOptions = [
                [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'color': [] }, { 'background': [] }],
                [{ 'align': [] }],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                [{ 'indent': '-1'}, { 'indent': '+1' }],
                ['link', 'image', 'video'],
                ['blockquote', 'code-block'],
                ['clean'],
                ['columns-2', 'columns-3', 'sidebar'] // Custom buttons für Layouts
            ];
            
            // Über mich Editor
            if (document.getElementById('about_text_editor')) {
                try {
                    quillEditors.about = new Quill('#about_text_editor', {
                        theme: 'snow',
                        modules: {
                            toolbar: toolbarOptions
                        }
                    });
                    
                    console.log('Quill Editor für "Über mich" erfolgreich erstellt');
                    
                    // Inhalt aus Textarea laden
                    var aboutContent = document.getElementById('about_text').value;
                    if (aboutContent) {
                        quillEditors.about.root.innerHTML = aboutContent;
                    }
                    
                    // Bei Änderungen Textarea aktualisieren
                    quillEditors.about.on('text-change', function() {
                        document.getElementById('about_text').value = quillEditors.about.root.innerHTML;
                    });
                    
                    // Layout-Buttons nach der Editor-Initialisierung hinzufügen
                    quillEditors.about.on('editor-ready', function() {
                        console.log('Quill Editor ist bereit, füge Layout-Buttons hinzu...');
                        addLayoutButtons();
                    });
                    
                    // Fallback falls editor-ready Event nicht funktioniert
                    setTimeout(function() {
                        console.log('Fallback: Versuche Layout-Buttons hinzuzufügen...');
                        addLayoutButtons();
                    }, 2000);
                    
                } catch (error) {
                    console.error('Fehler beim Erstellen des Quill Editors:', error);
                }
            }
            
            // Datenschutz Editor
            if (document.getElementById('datenschutz_contact_editor')) {
                quillEditors.datenschutz = new Quill('#datenschutz_contact_editor', {
                    theme: 'snow',
                    modules: {
                        toolbar: toolbarOptions
                    }
                });
                
                var datenschutzContent = document.getElementById('datenschutz_contact').value;
                if (datenschutzContent) {
                    quillEditors.datenschutz.root.innerHTML = datenschutzContent;
                }
                
                quillEditors.datenschutz.on('text-change', function() {
                    document.getElementById('datenschutz_contact').value = quillEditors.datenschutz.root.innerHTML;
                });
            }
            
            // Projekt-Editoren initialisieren
            initProjectEditors();
            
            // CKEditor für neue Seite initialisieren (mit Verzögerung)
            setTimeout(function() {
                initCKEditor();
            }, 500);
            
            // Tab-Click-Events hinzufügen
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach((tab, index) => {
                tab.addEventListener('click', function() {
                    const tabNames = ['general', 'projects', 'impressum', 'datenschutz', 'neue_seite', 'clone_website'];
                    showTab(tabNames[index]);
                });
            });
        });
        
        function initProjectEditors() {
            // Bestehende Projekt-Editoren initialisieren
            document.querySelectorAll('.project-editor').forEach(function(editorDiv, index) {
                var textarea = editorDiv.nextElementSibling;
                var editorId = 'project-editor-' + index;
                editorDiv.id = editorId;
                
                quillEditors['project-' + index] = new Quill('#' + editorId, {
                    theme: 'snow',
                    modules: {
                        toolbar: [
                            [{ 'header': [1, 2, 3, false] }],
                            ['bold', 'italic', 'underline'],
                            [{ 'color': [] }],
                            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                            ['link', 'image'],
                            ['clean']
                        ]
                    }
                });
                
                // Inhalt laden
                if (textarea.value) {
                    quillEditors['project-' + index].root.innerHTML = textarea.value;
                }
                
                // Änderungen synchronisieren
                quillEditors['project-' + index].on('text-change', function() {
                    textarea.value = quillEditors['project-' + index].root.innerHTML;
                });
            });
        }
        
        function addColumnLayout(type) {
            var range = quillEditors.about.getSelection();
            if (range) {
                var html = '';
                switch(type) {
                    case '2-columns':
                        html = '<div style="display: flex; gap: 20px; margin: 10px 0;"><div style="flex: 1; padding: 10px; border: 1px dashed #ccc;"><h3>Linke Spalte</h3><p>Inhalt der linken Spalte...</p></div><div style="flex: 1; padding: 10px; border: 1px dashed #ccc;"><h3>Rechte Spalte</h3><p>Inhalt der rechten Spalte...</p></div></div>';
                        break;
                    case '3-columns':
                        html = '<div style="display: flex; gap: 15px; margin: 10px 0;"><div style="flex: 1; padding: 10px; border: 1px dashed #ccc;"><h4>Spalte 1</h4><p>Inhalt...</p></div><div style="flex: 1; padding: 10px; border: 1px dashed #ccc;"><h4>Spalte 2</h4><p>Inhalt...</p></div><div style="flex: 1; padding: 10px; border: 1px dashed #ccc;"><h4>Spalte 3</h4><p>Inhalt...</p></div></div>';
                        break;
                    case 'sidebar':
                        html = '<div style="display: flex; gap: 20px; margin: 10px 0;"><div style="flex: 2; padding: 10px; border: 1px dashed #ccc;"><h3>Hauptinhalt</h3><p>Hier steht der Hauptinhalt...</p></div><div style="flex: 1; background: #f5f5f5; padding: 15px; border: 1px dashed #ccc;"><h4>Sidebar</h4><p>Zusätzliche Informationen...</p></div></div>';
                        break;
                }
                quillEditors.about.clipboard.dangerouslyPasteHTML(range.index, html);
            }
        }
        
        function addLayoutButtonsAlternative() {
            console.log('Alternative Layout-Button Methode...');
            
            // Erstelle Buttons außerhalb der Toolbar
            var aboutEditor = document.querySelector('#about_text_editor');
            if (aboutEditor && !aboutEditor.querySelector('.custom-layout-buttons')) {
                var buttonContainer = document.createElement('div');
                buttonContainer.className = 'custom-layout-buttons';
                buttonContainer.style.cssText = 'margin-bottom: 10px; padding: 10px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px;';
                buttonContainer.innerHTML = `
                    <strong>Spalten-Layouts:</strong>
                    <button type="button" onclick="insertLayout('2-columns')" style="margin: 0 5px; padding: 5px 10px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer;">📱 2 Spalten</button>
                    <button type="button" onclick="insertLayout('3-columns')" style="margin: 0 5px; padding: 5px 10px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer;">📊 3 Spalten</button>
                    <button type="button" onclick="insertLayout('sidebar')" style="margin: 0 5px; padding: 5px 10px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer;">📋 Sidebar</button>
                `;
                
                aboutEditor.parentNode.insertBefore(buttonContainer, aboutEditor);
                console.log('Alternative Layout-Buttons hinzugefügt');
            }
        }
        
        function insertLayout(type) {
            console.log('Layout einfügen:', type);
            if (quillEditors.about) {
                var html = '';
                switch(type) {
                    case '2-columns':
                        html = '<div style="display: flex; gap: 20px; margin: 10px 0;"><div style="flex: 1; padding: 10px; border: 1px dashed #ccc;"><h3>Linke Spalte</h3><p>Inhalt der linken Spalte...</p></div><div style="flex: 1; padding: 10px; border: 1px dashed #ccc;"><h3>Rechte Spalte</h3><p>Inhalt der rechten Spalte...</p></div></div>';
                        break;
                    case '3-columns':
                        html = '<div style="display: flex; gap: 15px; margin: 10px 0;"><div style="flex: 1; padding: 10px; border: 1px dashed #ccc;"><h4>Spalte 1</h4><p>Inhalt...</p></div><div style="flex: 1; padding: 10px; border: 1px dashed #ccc;"><h4>Spalte 2</h4><p>Inhalt...</p></div><div style="flex: 1; padding: 10px; border: 1px dashed #ccc;"><h4>Spalte 3</h4><p>Inhalt...</p></div></div>';
                        break;
                    case 'sidebar':
                        html = '<div style="display: flex; gap: 20px; margin: 10px 0;"><div style="flex: 2; padding: 10px; border: 1px dashed #ccc;"><h3>Hauptinhalt</h3><p>Hier steht der Hauptinhalt...</p></div><div style="flex: 1; background: #f5f5f5; padding: 15px; border: 1px dashed #ccc;"><h4>Sidebar</h4><p>Zusätzliche Informationen...</p></div></div>';
                        break;
                }
                
                var range = quillEditors.about.getSelection();
                if (range) {
                    quillEditors.about.clipboard.dangerouslyPasteHTML(range.index, html);
                } else {
                    // Falls keine Selektion, am Ende einfügen
                    var length = quillEditors.about.getLength();
                    quillEditors.about.clipboard.dangerouslyPasteHTML(length - 1, html);
                }
                console.log('Layout eingefügt');
            }
        }

        function addLayoutButtons() {
            console.log('addLayoutButtons aufgerufen');
            
            // Prüfe ob Quill Editor existiert
            if (!quillEditors.about) {
                console.log('Quill Editor noch nicht initialisiert');
                return;
            }
            
            // Buttons für Spalten-Layouts hinzufügen
            var aboutEditor = document.querySelector('#about_text_editor');
            var aboutToolbar = aboutEditor ? aboutEditor.querySelector('.ql-toolbar') : null;
            console.log('Editor Element:', aboutEditor);
            console.log('Toolbar gefunden:', aboutToolbar);
            
            if (aboutToolbar) {
                // Prüfen ob Buttons bereits existieren
                if (aboutToolbar.querySelector('.layout-buttons')) {
                    console.log('Layout-Buttons bereits vorhanden');
                    return;
                }
                
                var layoutGroup = document.createElement('span');
                layoutGroup.className = 'ql-formats layout-buttons';
                layoutGroup.innerHTML = `
                    <button type="button" class="ql-layout" data-layout="2-columns" title="Zwei Spalten">📱 2 Spalten</button>
                    <button type="button" class="ql-layout" data-layout="3-columns" title="Drei Spalten">📊 3 Spalten</button>
                    <button type="button" class="ql-layout" data-layout="sidebar" title="Sidebar Layout">📋 Sidebar</button>
                `;
                aboutToolbar.appendChild(layoutGroup);
                console.log('Layout-Buttons hinzugefügt');
                
                // Event Listener für Layout-Buttons
                layoutGroup.querySelectorAll('.ql-layout').forEach(function(btn) {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        var layoutType = this.getAttribute('data-layout');
                        console.log('Layout-Button geklickt:', layoutType);
                        addColumnLayout(layoutType);
                    });
                });
            } else {
                console.log('Toolbar nicht gefunden - versuche es später nochmal');
                // Nur 3 Versuche, dann aufgeben
                if (!addLayoutButtons.attempts) {
                    addLayoutButtons.attempts = 0;
                }
                addLayoutButtons.attempts++;
                
                if (addLayoutButtons.attempts < 3) {
                    setTimeout(function() {
                        addLayoutButtons();
                    }, 1000);
                } else {
                    console.log('Aufgegeben - Quill Editor Toolbar konnte nicht gefunden werden');
                    console.log('Versuche alternative Methode...');
                    addLayoutButtonsAlternative();
                }
            }
        }

        function showTab(tabName) {
            console.log('showTab aufgerufen mit:', tabName);
            currentTab = tabName;
            
            // Alle Tabs und Inhalte verstecken
            var tabs = document.querySelectorAll('.tab');
            var contents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(function(tab) {
                tab.classList.remove('active');
            });
            
            contents.forEach(function(content) {
                content.classList.remove('active');
            });
            
            // Aktiven Tab und Inhalt anzeigen
            const activeTab = document.querySelector(`.tab:nth-child(${getTabIndex(tabName)})`);
            if (activeTab) {
                activeTab.classList.add('active');
                console.log('Tab aktiviert:', activeTab);
            } else {
                console.log('Tab nicht gefunden für:', tabName);
            }
            
            const activeContent = document.getElementById(tabName);
            if (activeContent) {
                activeContent.classList.add('active');
                console.log('Content aktiviert:', activeContent);
                
                // CKEditor neu initialisieren wenn "Neue Seite" Tab geöffnet wird
                if (tabName === 'neue_seite') {
                    setTimeout(function() {
                        console.log('Initialisiere CKEditor für neue Seite Tab...');
                        initCKEditor();
                    }, 100);
                }
            } else {
                console.log('Content nicht gefunden für:', tabName);
            }
        }
        
        function getTabIndex(tabName) {
            const tabMap = {
                'general': 1,
                'projects': 2,
                'impressum': 3,
                'datenschutz': 4,
                'neue_seite': 5,
                'clone_website': 6
            };
            return tabMap[tabName] || 1;
        }

        function cloneWebsite() {
            var url = document.getElementById('clone_url').value.trim();
            if (!url) { alert('Bitte eine URL eingeben!'); return; }
            
            var statusDiv = document.getElementById('clone_status');
            var spinnerDiv = document.getElementById('clone_spinner');
            var resultDiv = document.getElementById('clone_result');
            var logDiv = document.getElementById('clone_log');
            var btn = document.getElementById('clone_btn');
            
            statusDiv.style.display = 'block';
            spinnerDiv.style.display = 'block';
            resultDiv.style.display = 'none';
            logDiv.style.display = 'none';
            btn.disabled = true;
            btn.style.opacity = '0.5';
            btn.textContent = '⏳ Wird kopiert...';
            
            var formData = new FormData();
            formData.append('clone_url', url);
            
            fetch('clone_website.php', { method: 'POST', body: formData })
                .then(function(resp) { return resp.json(); })
                .then(function(data) {
                    spinnerDiv.style.display = 'none';
                    resultDiv.style.display = 'block';
                    
                    if (data.success) {
                        resultDiv.style.background = '#d4edda';
                        resultDiv.style.border = '1px solid #c3e6cb';
                        resultDiv.style.color = '#155724';
                        resultDiv.innerHTML = '<strong>✅ Website erfolgreich kopiert!</strong><br>' +
                            'Verzeichnis: <strong>' + data.directory + '</strong><br>' +
                            'Dateien: ' + data.files + '<br><br>' +
                            '<a href="' + data.directory + '/index.html" target="_blank" style="background: #28a745; color: white; padding: 8px 16px; border-radius: 5px; text-decoration: none;">🌐 Kopie öffnen</a>';
                    } else {
                        resultDiv.style.background = '#f8d7da';
                        resultDiv.style.border = '1px solid #f5c6cb';
                        resultDiv.style.color = '#721c24';
                        resultDiv.innerHTML = '<strong>❌ Fehler beim Kopieren!</strong>';
                    }
                    
                    if (data.log && data.log.length > 0) {
                        logDiv.style.display = 'block';
                        logDiv.innerHTML = '<strong>Log:</strong><br>' + data.log.join('<br>');
                    }
                    
                    btn.disabled = false;
                    btn.style.opacity = '1';
                    btn.textContent = '🌐 Website kopieren';
                })
                .catch(function(err) {
                    spinnerDiv.style.display = 'none';
                    resultDiv.style.display = 'block';
                    resultDiv.style.background = '#f8d7da';
                    resultDiv.style.border = '1px solid #f5c6cb';
                    resultDiv.style.color = '#721c24';
                    resultDiv.innerHTML = '<strong>❌ Fehler:</strong> ' + err.message;
                    btn.disabled = false;
                    btn.style.opacity = '1';
                    btn.textContent = '🌐 Website kopieren';
                });
        }

        function addProject() {
            var container = document.getElementById('projects-container');
            var projectCount = container.children.length + 1;
            
            var projectHtml = `
                <div class="project-item">
                    <button type="button" class="btn-remove" onclick="removeProject(this)">Entfernen</button>
                    <h4>Projekt ${projectCount}</h4>
                    <div class="form-group">
                        <label>Titel:</label>
                        <input type="text" name="project_title[]" value="">
                    </div>
                    <div class="form-group">
                        <label>Beschreibung:</label>
                        <div class="quill-editor project-editor-template"></div>
                        <textarea name="project_description[]" style="display: none;"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Bild-URL:</label>
                        <input type="text" name="project_image[]" value="" placeholder="https://example.com/image.jpg">
                    </div>
                    <div class="form-group">
                        <label>Demo-Link:</label>
                        <input type="text" name="project_demo[]" value="" placeholder="https://example.com oder #">
                    </div>
                    <div class="form-group">
                        <label>GitHub-Link:</label>
                        <input type="text" name="project_github[]" value="" placeholder="https://github.com/user/repo oder #">
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', projectHtml);
            
            // Neuen Editor für das neue Projekt initialisieren
            var newEditorDiv = container.lastElementChild.querySelector('.project-editor-template');
            var newTextarea = container.lastElementChild.querySelector('textarea');
            var newEditorId = 'new-project-editor-' + projectCount;
            
            newEditorDiv.id = newEditorId;
            newEditorDiv.className = 'quill-editor project-editor';
            
            quillEditors['new-project-' + projectCount] = new Quill('#' + newEditorId, {
                theme: 'snow',
                modules: {
                    toolbar: [
                        [{ 'header': [1, 2, 3, false] }],
                        ['bold', 'italic', 'underline'],
                        [{ 'color': [] }],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        ['link', 'image'],
                        ['clean']
                    ]
                }
            });
            
            // Änderungen synchronisieren
            quillEditors['new-project-' + projectCount].on('text-change', function() {
                newTextarea.value = quillEditors['new-project-' + projectCount].root.innerHTML;
            });
        }

        function removeProject(button) {
            button.parentElement.remove();
        }
        
        function reloadCKEditor() {
            console.log('CKEditor wird neu geladen...');
            if (window.ckEditor) {
                window.ckEditor.destroy().then(() => {
                    console.log('Alter Editor zerstört, erstelle neuen...');
                    setTimeout(function() {
                        initCKEditor();
                    }, 100);
                });
            } else {
                console.log('Kein bestehender Editor gefunden, erstelle neuen...');
                initCKEditor();
            }
        }
        
        // CKEditor initialisieren
        function initCKEditor() {
            if (document.getElementById('neue_seite_editor')) {
                // Zuerst prüfen ob bereits ein Editor existiert und ihn zerstören
                if (window.ckEditor) {
                    window.ckEditor.destroy().then(() => {
                        createCKEditor();
                    });
                } else {
                    createCKEditor();
                }
            }
        }
        
        function createCKEditor() {
            CKEDITOR.ClassicEditor
                    .create(document.querySelector('#neue_seite_editor'), {
                        toolbar: {
                            items: [
                                'heading',
                                'style',
                                '|',
                                'bold',
                                'italic',
                                'underline',
                                'strikethrough',
                                'subscript',
                                'superscript',
                                'code',
                                '|',
                                'fontSize',
                                'fontFamily',
                                'fontColor',
                                'fontBackgroundColor',
                                'highlight',
                                '|',
                                'alignment',
                                '|',
                                'numberedList',
                                'bulletedList',
                                'todoList',
                                'outdent',
                                'indent',
                                '|',
                                'link',
                                'insertImage',
                                'insertTable',
                                'mediaEmbed',
                                'pageBreak',
                                'horizontalLine',
                                '|',
                                'blockQuote',
                                'codeBlock',
                                'htmlEmbed',
                                '|',
                                'specialCharacters',
                                'insertTemplate',
                                '|',
                                'findAndReplace',
                                'selectAll',
                                '|',
                                'undo',
                                'redo',
                                '|',
                                'sourceEditing'
                            ],
                            shouldNotGroupWhenFull: true
                        },
                        language: 'de',
                        
                        // Erweiterte Überschriften
                        heading: {
                            options: [
                                { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
                                { model: 'heading1', view: 'h1', title: 'Heading 1', class: 'ck-heading_heading1' },
                                { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
                                { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' },
                                { model: 'heading4', view: 'h4', title: 'Heading 4', class: 'ck-heading_heading4' },
                                { model: 'heading5', view: 'h5', title: 'Heading 5', class: 'ck-heading_heading5' },
                                { model: 'heading6', view: 'h6', title: 'Heading 6', class: 'ck-heading_heading6' }
                            ]
                        },
                        
                        // Schriftarten
                        fontFamily: {
                            options: [
                                'default',
                                'Arial, Helvetica, sans-serif',
                                'Courier New, Courier, monospace',
                                'Georgia, serif',
                                'Lucida Sans Unicode, Lucida Grande, sans-serif',
                                'Tahoma, Geneva, sans-serif',
                                'Times New Roman, Times, serif',
                                'Trebuchet MS, Helvetica, sans-serif',
                                'Verdana, Geneva, sans-serif',
                                'Impact, Charcoal, sans-serif',
                                'Comic Sans MS, cursive'
                            ],
                            supportAllValues: true
                        },
                        
                        // Schriftgrößen
                        fontSize: {
                            options: [
                                9, 10, 11, 12, 13, 14, 15, 16, 18, 20, 22, 24, 26, 28, 30, 32, 34, 36, 48, 60, 72
                            ],
                            supportAllValues: true
                        },
                        
                        // Erweiterte Bild-Optionen
                        image: {
                            toolbar: [
                                'imageTextAlternative',
                                'toggleImageCaption',
                                '|',
                                'imageStyle:inline',
                                'imageStyle:wrapText',
                                'imageStyle:breakText',
                                '|',
                                'imageStyle:block',
                                'imageStyle:side',
                                '|',
                                'resizeImage',
                                'linkImage'
                            ],
                            resizeOptions: [
                                {
                                    name: 'resizeImage:original',
                                    label: 'Original',
                                    value: null
                                },
                                {
                                    name: 'resizeImage:25',
                                    label: '25%',
                                    value: '25'
                                },
                                {
                                    name: 'resizeImage:50',
                                    label: '50%',
                                    value: '50'
                                },
                                {
                                    name: 'resizeImage:75',
                                    label: '75%',
                                    value: '75'
                                }
                            ]
                        },
                        
                        // Erweiterte Tabellen-Optionen
                        table: {
                            contentToolbar: [
                                'tableColumn',
                                'tableRow',
                                'mergeTableCells',
                                'tableCellProperties',
                                'tableProperties'
                            ]
                        },
                        
                        // Link-Optionen
                        link: {
                            decorators: {
                                addTargetToExternalLinks: {
                                    mode: 'automatic',
                                    callback: url => /^(https?:)?\/\//.test(url),
                                    attributes: {
                                        target: '_blank',
                                        rel: 'noopener noreferrer'
                                    }
                                }
                            }
                        },
                        
                        // Code-Block Sprachen
                        codeBlock: {
                            languages: [
                                { language: 'plaintext', label: 'Plain text' },
                                { language: 'c', label: 'C' },
                                { language: 'cs', label: 'C#' },
                                { language: 'cpp', label: 'C++' },
                                { language: 'css', label: 'CSS' },
                                { language: 'diff', label: 'Diff' },
                                { language: 'html', label: 'HTML' },
                                { language: 'java', label: 'Java' },
                                { language: 'javascript', label: 'JavaScript' },
                                { language: 'php', label: 'PHP' },
                                { language: 'python', label: 'Python' },
                                { language: 'ruby', label: 'Ruby' },
                                { language: 'typescript', label: 'TypeScript' },
                                { language: 'xml', label: 'XML' },
                                { language: 'json', label: 'JSON' },
                                { language: 'sql', label: 'SQL' },
                                { language: 'bash', label: 'Bash' }
                            ]
                        },
                        
                        // Styles
                        style: {
                            definitions: [
                                {
                                    name: 'Article category',
                                    element: 'h3',
                                    classes: ['category']
                                },
                                {
                                    name: 'Info box',
                                    element: 'p',
                                    classes: ['info-box']
                                },
                                {
                                    name: 'Side quote',
                                    element: 'blockquote',
                                    classes: ['side-quote']
                                },
                                {
                                    name: 'Marker',
                                    element: 'span',
                                    classes: ['marker']
                                },
                                {
                                    name: 'Spoiler',
                                    element: 'span',
                                    classes: ['spoiler']
                                },
                                {
                                    name: 'Code (dark)',
                                    element: 'pre',
                                    classes: ['fancy-code', 'fancy-code-dark']
                                },
                                {
                                    name: 'Code (bright)',
                                    element: 'pre',
                                    classes: ['fancy-code', 'fancy-code-bright']
                                }
                            ]
                        },
                        
                        // HTML Embed erlauben
                        htmlEmbed: {
                            showPreviews: true
                        },
                        
                        // Templates
                        template: {
                            definitions: [
                                {
                                    title: 'Zwei Spalten Layout',
                                    description: 'Layout mit zwei gleichen Spalten',
                                    data: '<div style="display: flex; gap: 20px;"><div style="flex: 1;"><h3>Linke Spalte</h3><p>Inhalt der linken Spalte...</p></div><div style="flex: 1;"><h3>Rechte Spalte</h3><p>Inhalt der rechten Spalte...</p></div></div>'
                                },
                                {
                                    title: 'Drei Spalten Layout',
                                    description: 'Layout mit drei gleichen Spalten',
                                    data: '<div style="display: flex; gap: 15px;"><div style="flex: 1;"><h4>Spalte 1</h4><p>Inhalt...</p></div><div style="flex: 1;"><h4>Spalte 2</h4><p>Inhalt...</p></div><div style="flex: 1;"><h4>Spalte 3</h4><p>Inhalt...</p></div></div>'
                                },
                                {
                                    title: 'Hero Section',
                                    description: 'Große Hero-Sektion mit Titel und Button',
                                    data: '<div style="text-align: center; padding: 60px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 10px;"><h1 style="font-size: 3em; margin-bottom: 20px;">Willkommen</h1><p style="font-size: 1.2em; margin-bottom: 30px;">Ihre Beschreibung hier...</p><a href="#" style="background: white; color: #667eea; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">Call to Action</a></div>'
                                },
                                {
                                    title: 'Info Box',
                                    description: 'Hervorgehobene Informationsbox',
                                    data: '<div style="background: #f8f9fa; border-left: 4px solid #007bff; padding: 20px; margin: 20px 0;"><h4 style="color: #007bff; margin-top: 0;">💡 Wichtiger Hinweis</h4><p style="margin-bottom: 0;">Hier steht eine wichtige Information...</p></div>'
                                },
                                {
                                    title: 'Testimonial',
                                    description: 'Kundenbewertung oder Zitat',
                                    data: '<blockquote style="background: #f9f9f9; border-left: 4px solid #ccc; margin: 20px 0; padding: 20px; font-style: italic;"><p style="font-size: 1.1em; margin-bottom: 15px;">"Das ist ein großartiges Testimonial von einem zufriedenen Kunden."</p><footer style="text-align: right; font-weight: bold;">— Kunde Name, Firma</footer></blockquote>'
                                }
                            ]
                        }
                    })
                    .then(editor => {
                        window.ckEditor = editor;
                        
                        // Inhalt aus Textarea laden
                        const textarea = document.getElementById('neue_seite_inhalt');
                        if (textarea.value) {
                            editor.setData(textarea.value);
                        }
                        
                        // Bei Änderungen Textarea aktualisieren
                        editor.model.document.on('change:data', () => {
                            textarea.value = editor.getData();
                        });
                        
                        console.log('CKEditor mit allen Features erfolgreich initialisiert');
                        console.log('Verfügbare Plugins:', Object.keys(editor.plugins._plugins));
                    })
                    .catch(error => {
                        console.error('Fehler beim Initialisieren von CKEditor:', error);
                        console.log('Versuche Fallback...');
                        // Fallback zu einfacherem Editor
                        initSimpleCKEditor();
                    });
        }
        
        function initSimpleCKEditor() {
            // Fallback falls Super-Build nicht funktioniert
            if (typeof ClassicEditor !== 'undefined') {
                ClassicEditor
                    .create(document.querySelector('#neue_seite_editor'), {
                        toolbar: [
                            'heading', '|',
                            'bold', 'italic', 'underline', 'strikethrough', '|',
                            'fontSize', 'fontColor', 'fontBackgroundColor', '|',
                            'alignment', '|',
                            'numberedList', 'bulletedList', 'outdent', 'indent', '|',
                            'link', 'insertImage', 'insertTable', 'mediaEmbed', '|',
                            'blockQuote', 'codeBlock', '|',
                            'undo', 'redo', '|',
                            'sourceEditing'
                        ],
                        language: 'de'
                    })
                    .then(editor => {
                        window.ckEditor = editor;
                        
                        const textarea = document.getElementById('neue_seite_inhalt');
                        if (textarea.value) {
                            editor.setData(textarea.value);
                        }
                        
                        editor.model.document.on('change:data', () => {
                            textarea.value = editor.getData();
                        });
                        
                        console.log('CKEditor Fallback erfolgreich initialisiert');
                    })
                    .catch(error => {
                        console.error('Auch Fallback-Editor fehlgeschlagen:', error);
                    });
            }
        }
    </script>
</body>
</html>