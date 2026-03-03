<?php
// HTML-Generator für Portfolio-Dateien
// Diese Datei wird von admin.php aufgerufen

// Fehlerberichterstattung aktivieren
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($config)) {
    echo "FEHLER: Konfiguration nicht geladen!";
    return;
}

if (empty($config)) {
    echo "FEHLER: Konfiguration ist leer!";
    return;
}

echo "Starte HTML-Generierung mit Konfiguration...\n";

// Funktion zum Generieren der index_vorschau.html
function generateIndexHtml($config) {
    echo "Generiere index_vorschau.html...\n";
    
    $skills_html = '';
    if (isset($config['site']['skills']) && is_array($config['site']['skills'])) {
        foreach ($config['site']['skills'] as $skill) {
            $skills_html .= '<span>' . htmlspecialchars(trim($skill)) . '</span>' . "\n                        ";
        }
    }
    
    $projects_slideshow = '';
    $projects_grid = '';
    
    if (isset($config['projects']) && is_array($config['projects'])) {
        foreach ($config['projects'] as $project) {
            $project_slide = '
                <div class="slide">
                    <div class="project-image-container">
                        <img src="' . htmlspecialchars($project['image'] ?? '') . '" alt="' . htmlspecialchars($project['title'] ?? '') . '">
                    </div>
                    <div class="project-content">
                        <h3>' . htmlspecialchars($project['title'] ?? '') . '</h3>
                        <p>' . htmlspecialchars($project['description'] ?? '') . '</p>
                        <div class="project-links">
                            <a href="' . htmlspecialchars($project['demo_link'] ?? '#') . '" class="project-link">
                                <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="16" height="16">
                                    <path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z" />
                                    <path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z" />
                                </svg>
                                Live Demo
                            </a>
                            <a href="' . htmlspecialchars($project['github_link'] ?? '#') . '" class="project-link">
                                <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="16" height="16">
                                    <path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                                GitHub
                            </a>
                        </div>
                    </div>
                </div>';
            
            $project_card = '
            <div class="project-card">
                <div class="project-image-container">
                    <img src="' . htmlspecialchars($project['image'] ?? '') . '" alt="' . htmlspecialchars($project['title'] ?? '') . '">
                </div>
                <div class="project-content">
                    <h3>' . htmlspecialchars($project['title'] ?? '') . '</h3>
                    <p>' . htmlspecialchars($project['description'] ?? '') . '</p>
                    <div class="project-links">
                        <a href="' . htmlspecialchars($project['demo_link'] ?? '#') . '" class="project-link">
                            <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="16" height="16">
                                <path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z" />
                                <path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z" />
                            </svg>
                            Live Demo
                        </a>
                        <a href="' . htmlspecialchars($project['github_link'] ?? '#') . '" class="project-link">
                            <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="16" height="16">
                                <path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                            GitHub
                        </a>
                    </div>
                </div>
            </div>';
            
            $projects_slideshow .= $project_slide;
            $projects_grid .= $project_card;
        }
    }
    
    $html = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($config['site']['title'] ?? 'Portfolio') . '</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="logo">Portfolio</div>
        <ul class="nav-links">
            <li><a href="#home">Home</a></li>
            <li><a href="#about">Über mich</a></li>
            <li><a href="#projects">Projekte</a></li>
            <li><a href="#contact">Kontakt</a></li>
        </ul>
        <div class="burger">
            <div class="line1"></div>
            <div class="line2"></div>
            <div class="line3"></div>
        </div>
    </nav>

    <section id="home" class="hero">
        <div class="hero-content">
            <h1>' . htmlspecialchars($config['site']['hero_title'] ?? 'Willkommen') . '</h1>
            <p>' . htmlspecialchars($config['site']['hero_subtitle'] ?? 'Portfolio') . '</p>
            <a href="#contact" class="cta-button">Kontaktieren Sie mich</a>
        </div>
    </section>

    <section id="about" class="about">
        <h2>Über mich</h2>
        <div class="about-content">
            <div class="about-text">
                <p>' . htmlspecialchars($config['site']['about_text'] ?? '') . '</p>
                <div class="skills">
                    <h3>Meine Fähigkeiten</h3>
                    <div class="skill-tags">
                        ' . $skills_html . '
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="projects" class="projects">
        <h2>Meine Projekte</h2>
        <div class="slideshow-container">
            <div class="slides">' . $projects_slideshow . '
            </div>
            <button class="slideshow-button prev" aria-label="Vorheriges Projekt">❮</button>
            <button class="slideshow-button next" aria-label="Nächstes Projekt">❯</button>
            <div class="slideshow-dots"></div>
        </div>
        <div class="project-grid">' . $projects_grid . '
        </div>
    </section>

    <section id="contact" class="contact">
        <h2>Kontakt</h2>
        <div class="contact-content">
            <form id="contact-form">
                <div class="form-group">
                    <input type="text" id="name" name="name" required placeholder="Ihr Name">
                </div>
                <div class="form-group">
                    <input type="email" id="email" name="email" required placeholder="Ihre E-Mail">
                </div>
                <div class="form-group">
                    <textarea id="message" name="message" required placeholder="Ihre Nachricht"></textarea>
                </div>
                <div class="form-group checkbox-group">
                    <input type="checkbox" id="privacy" name="privacy" required>
                    <label for="privacy">Ich habe die <a href="datenschutz_vorschau.html" target="_blank">Datenschutzerklärung</a> gelesen und stimme der Verarbeitung meiner Daten zu.</label>
                </div>
                <button type="submit" class="submit-btn">Nachricht senden</button>
            </form>
            <div class="social-links">
                <a href="' . htmlspecialchars($config['social']['github'] ?? '#') . '" class="social-link" title="GitHub">
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">
                        <path d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.17 6.839 9.49.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.604-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.464-1.11-1.464-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.578 9.578 0 0112 6.836c.85.004 1.705.114 2.504.336 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.578.688.48C19.138 20.167 22 16.418 22 12c0-5.523-4.477-10-10-10z"/>
                    </svg>
                </a>
                <a href="' . htmlspecialchars($config['social']['linkedin'] ?? '#') . '" class="social-link" title="LinkedIn">
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">
                        <path d="M19 3a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h14m-.5 15.5v-5.3a3.26 3.26 0 00-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 011.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 001.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 00-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z"/>
                    </svg>
                </a>
                <a href="' . htmlspecialchars($config['social']['twitter'] ?? '#') . '" class="social-link" title="Twitter">
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">
                        <path d="M22.46 6c-.77.35-1.6.58-2.46.69.88-.53 1.56-1.37 1.88-2.38-.83.5-1.75.85-2.72 1.05C18.37 4.5 17.26 4 16 4c-2.35 0-4.27 1.92-4.27 4.29 0 .34.04.67.11.98C8.28 9.09 5.11 7.38 3 4.79c-.37.63-.58 1.37-.58 2.15 0 1.49.75 2.81 1.91 3.56-.71 0-1.37-.2-1.95-.5v.03c0 2.08 1.48 3.82 3.44 4.21a4.22 4.22 0 01-1.93.07 4.28 4.28 0 004 2.98 8.521 8.521 0 01-5.33 1.84c-.34 0-.68-.02-1.02-.06C3.44 20.29 5.7 21 8.12 21 16 21 20.33 14.46 20.33 8.79c0-.19 0-.37-.01-.56.84-.6 1.56-1.36 2.14-2.23z"/>
                    </svg>
                </a>
            </div>
        </div>
    </section>

    <footer>
        <p>' . htmlspecialchars($config['site']['footer_text'] ?? '© 2024 Portfolio') . '</p>
        <p>
            <a href="datenschutz_vorschau.html">Datenschutzerklärung</a> | 
            <a href="impressum_vorschau.html">Impressum</a>
        </p>
    </footer>

    <script src="script.js"></script>
</body>
</html>';
    
    return $html;
}

// Funktion zum Generieren der impressum_vorschau.html
function generateImpressumHtml($config) {
    echo "Generiere impressum_vorschau.html...\n";
    
    $html = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impressum</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="assets/fontawesome/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="logo">Portfolio</div>
        <ul class="nav-links">
            <li><a href="index_vorschau.html">Home</a></li>
            <li><a href="index_vorschau.html#about">Über mich</a></li>
            <li><a href="index_vorschau.html#projects">Projekte</a></li>
            <li><a href="index_vorschau.html#contact">Kontakt</a></li>
        </ul>
    </nav>

    <div class="privacy-content">
        <h1>Impressum</h1>
        
        <section>
            <h2>Angaben gemäß § 5 TMG</h2>
            <p>' . htmlspecialchars($config['impressum']['name'] ?? '[Name]') . '<br>
            ' . htmlspecialchars($config['impressum']['address'] ?? '[Adresse]') . '<br>
            ' . htmlspecialchars($config['impressum']['city'] ?? '[Stadt]') . '</p>
        </section>

        <section>
            <h2>Kontakt</h2>
            <p>Telefon: ' . htmlspecialchars($config['impressum']['phone'] ?? '[Telefon]') . '<br>
            E-Mail: ' . htmlspecialchars($config['impressum']['email'] ?? '[E-Mail]') . '</p>
        </section>

        <section>
            <h2>Umsatzsteuer-ID</h2>
            <p>Umsatzsteuer-Identifikationsnummer gemäß § 27 a Umsatzsteuergesetz:<br>
            ' . htmlspecialchars($config['impressum']['tax_id'] ?? '[Umsatzsteuer-ID]') . '</p>
        </section>

        <section>
            <h2>Berufsbezeichnung und berufsrechtliche Regelungen</h2>
            <p>Berufsbezeichnung: ' . htmlspecialchars($config['impressum']['profession'] ?? '[Beruf]') . '<br>
            Zuständige Kammer: ' . htmlspecialchars($config['impressum']['chamber'] ?? '[Kammer]') . '<br>
            Verliehen in: ' . htmlspecialchars($config['impressum']['country'] ?? '[Land]') . '</p>
        </section>

        <section>
            <h2>Streitschlichtung</h2>
            <p>Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit: <a href="https://ec.europa.eu/consumers/odr/" target="_blank">https://ec.europa.eu/consumers/odr/</a></p>
            <p>Unsere E-Mail-Adresse finden Sie oben im Impressum.</p>
            <p>Wir sind nicht bereit oder verpflichtet, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.</p>
        </section>

        <section>
            <h2>Haftung für Inhalte</h2>
            <p>Als Diensteanbieter sind wir gemäß § 7 Abs.1 TMG für eigene Inhalte auf diesen Seiten nach den allgemeinen Gesetzen verantwortlich. Nach §§ 8 bis 10 TMG sind wir als Diensteanbieter jedoch nicht verpflichtet, übermittelte oder gespeicherte fremde Informationen zu überwachen oder nach Umständen zu forschen, die auf eine rechtswidrige Tätigkeit hinweisen.</p>
            <p>Verpflichtungen zur Entfernung oder Sperrung der Nutzung von Informationen nach den allgemeinen Gesetzen bleiben hiervon unberührt. Eine diesbezügliche Haftung ist jedoch erst ab dem Zeitpunkt der Kenntnis einer konkreten Rechtsverletzung möglich. Bei Bekanntwerden von entsprechenden Rechtsverletzungen werden wir diese Inhalte umgehend entfernen.</p>
        </section>

        <section>
            <h2>Haftung für Links</h2>
            <p>Unser Angebot enthält Links zu externen Websites Dritter, auf deren Inhalte wir keinen Einfluss haben. Deshalb können wir für diese fremden Inhalte auch keine Gewähr übernehmen. Für die Inhalte der verlinkten Seiten ist stets der jeweilige Anbieter oder Betreiber der Seiten verantwortlich. Die verlinkten Seiten wurden zum Zeitpunkt der Verlinkung auf mögliche Rechtsverstöße überprüft. Rechtswidrige Inhalte waren zum Zeitpunkt der Verlinkung nicht erkennbar.</p>
            <p>Eine permanente inhaltliche Kontrolle der verlinkten Seiten ist jedoch ohne konkrete Anhaltspunkte einer Rechtsverletzung nicht zumutbar. Bei Bekanntwerden von Rechtsverletzungen werden wir derartige Links umgehend entfernen.</p>
        </section>

        <section>
            <h2>Urheberrecht</h2>
            <p>Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen dem deutschen Urheberrecht. Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art der Verwertung außerhalb der Grenzen des Urheberrechtes bedürfen der schriftlichen Zustimmung des jeweiligen Autors bzw. Erstellers. Downloads und Kopien dieser Seite sind nur für den privaten, nicht kommerziellen Gebrauch gestattet.</p>
            <p>Soweit die Inhalte auf dieser Seite nicht vom Betreiber erstellt wurden, werden die Urheberrechte Dritter beachtet. Insbesondere werden Inhalte Dritter als solche gekennzeichnet. Sollten Sie trotzdem auf eine Urheberrechtsverletzung aufmerksam werden, bitten wir um einen entsprechenden Hinweis. Bei Bekanntwerden von Rechtsverletzungen werden wir derartige Inhalte umgehend entfernen.</p>
        </section>
    </div>

    <footer>
        <p>' . htmlspecialchars($config['site']['footer_text'] ?? '© 2024 Portfolio') . '</p>
        <p>
            <a href="datenschutz_vorschau.html">Datenschutzerklärung</a> | 
            <a href="impressum_vorschau.html">Impressum</a>
        </p>
    </footer>

    <script src="script.js"></script>
</body>
</html>';
    
    return $html;
}

// Funktion zum Generieren der datenschutz_vorschau.html
function generateDatenschutzHtml($config) {
    echo "Generiere datenschutz_vorschau.html...\n";
    
    $html = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datenschutzerklärung</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="assets/fontawesome/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="logo">Portfolio</div>
        <ul class="nav-links">
            <li><a href="index_vorschau.html">Home</a></li>
            <li><a href="index_vorschau.html#about">Über mich</a></li>
            <li><a href="index_vorschau.html#projects">Projekte</a></li>
            <li><a href="index_vorschau.html#contact">Kontakt</a></li>
        </ul>
    </nav>

    <div class="privacy-content">
        <h1>Datenschutzerklärung</h1>
        
        <section>
            <h2>1. Datenschutz auf einen Blick</h2>
            <h3>Allgemeine Hinweise</h3>
            <p>Die folgenden Hinweise geben einen einfachen Überblick darüber, was mit Ihren personenbezogenen Daten passiert, wenn Sie diese Website besuchen.</p>
        </section>

        <section>
            <h2>2. Datenerfassung auf dieser Website</h2>
            <h3>Cookies</h3>
            <p>Diese Website verwendet keine Cookies.</p>

            <h3>Server-Log-Dateien</h3>
            <p>Der Provider der Seiten erhebt und speichert automatisch Informationen in so genannten Server-Log-Dateien, die Ihr Browser automatisch an uns übermittelt. Dies sind:</p>
            <ul>
                <li>Browsertyp und -version</li>
                <li>Verwendetes Betriebssystem</li>
                <li>Referrer URL</li>
                <li>Hostname des zugreifenden Rechners</li>
                <li>Uhrzeit der Serveranfrage</li>
                <li>IP-Adresse</li>
            </ul>
        </section>

        <section>
            <h2>3. Kontaktformular</h2>
            <p>Wenn Sie uns per Kontaktformular Anfragen zukommen lassen, werden Ihre Angaben aus dem Anfrageformular inklusive der von Ihnen dort angegebenen Kontaktdaten zwecks Bearbeitung der Anfrage und für den Fall von Anschlussfragen bei uns gespeichert. Diese Daten geben wir nicht ohne Ihre Einwilligung weiter.</p>
        </section>

        <section>
            <h2>4. Ihre Rechte</h2>
            <p>Sie haben jederzeit das Recht:</p>
            <ul>
                <li>Auskunft über Ihre gespeicherten personenbezogenen Daten zu erhalten</li>
                <li>Diese berichtigen oder löschen zu lassen</li>
                <li>Die Verarbeitung einzuschränken</li>
                <li>Der Verarbeitung zu widersprechen</li>
                <li>Ihre Daten in einem strukturierten Format zu erhalten</li>
            </ul>
        </section>

        <section>
            <h2>5. Kontakt</h2>
            <p>Bei Fragen zur Erhebung, Verarbeitung oder Nutzung Ihrer personenbezogenen Daten, bei Auskünften, Berichtigung, Sperrung oder Löschung von Daten wenden Sie sich bitte an:</p>
            <p>' . nl2br(htmlspecialchars($config['datenschutz']['contact_info'] ?? '[Kontaktdaten]')) . '</p>
        </section>
    </div>

    <footer>
        <p>' . htmlspecialchars($config['site']['footer_text'] ?? '© 2024 Portfolio') . '</p>
        <p><a href="datenschutz_vorschau.html">Datenschutzerklärung</a></p>
    </footer>

    <script src="script.js"></script>
</body>
</html>';
    
    return $html;
}

// Funktion zum Generieren der neuen Seite
function generateNeueSeiteHtml($config) {
    echo "Generiere neue Seite...\n";
    
    $dateiname = $config['neue_seite']['dateiname'] ?? 'neue-seite';
    $titel = $config['neue_seite']['titel'] ?? 'Neue Seite';
    $inhalt = $config['neue_seite']['inhalt'] ?? '<h1>Neue Seite</h1><p>Inhalt hier...</p>';
    
    $html = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($titel) . '</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="logo">Portfolio</div>
        <ul class="nav-links">
            <li><a href="index_vorschau.html">Home</a></li>
            <li><a href="index_vorschau.html#about">Über mich</a></li>
            <li><a href="index_vorschau.html#projects">Projekte</a></li>
            <li><a href="index_vorschau.html#contact">Kontakt</a></li>
        </ul>
        <div class="burger">
            <div class="line1"></div>
            <div class="line2"></div>
            <div class="line3"></div>
        </div>
    </nav>

    <div class="page-content">
        <div class="container">
            ' . $inhalt . '
        </div>
    </div>

    <footer>
        <p>' . htmlspecialchars($config['site']['footer_text'] ?? '© 2024 Portfolio') . '</p>
        <p>
            <a href="datenschutz_vorschau.html">Datenschutzerklärung</a> | 
            <a href="impressum_vorschau.html">Impressum</a>
        </p>
    </footer>

    <script src="script.js"></script>
</body>
</html>';
    
    return $html;
}

// HTML-Dateien generieren
try {
    echo "Beginne mit der Dateigenerierung...\n";
    
    // index_vorschau.html generieren
    $index_html = generateIndexHtml($config);
    $result1 = file_put_contents('index_vorschau.html', $index_html);
    if ($result1 === false) {
        echo "FEHLER: Konnte index_vorschau.html nicht schreiben!\n";
    } else {
        echo "index_vorschau.html erfolgreich erstellt (" . $result1 . " Bytes)\n";
    }
    
    // impressum_vorschau.html generieren
    $impressum_html = generateImpressumHtml($config);
    $result2 = file_put_contents('impressum_vorschau.html', $impressum_html);
    if ($result2 === false) {
        echo "FEHLER: Konnte impressum_vorschau.html nicht schreiben!\n";
    } else {
        echo "impressum_vorschau.html erfolgreich erstellt (" . $result2 . " Bytes)\n";
    }
    
    // datenschutz_vorschau.html generieren
    $datenschutz_html = generateDatenschutzHtml($config);
    $result3 = file_put_contents('datenschutz_vorschau.html', $datenschutz_html);
    if ($result3 === false) {
        echo "FEHLER: Konnte datenschutz_vorschau.html nicht schreiben!\n";
    } else {
        echo "datenschutz_vorschau.html erfolgreich erstellt (" . $result3 . " Bytes)\n";
    }
    
    // Neue Seite generieren (falls konfiguriert)
    if (isset($config['neue_seite']) && !empty($config['neue_seite']['dateiname'])) {
        $neue_seite_html = generateNeueSeiteHtml($config);
        $dateiname = $config['neue_seite']['dateiname'] . '_vorschau.html';
        $result4 = file_put_contents($dateiname, $neue_seite_html);
        if ($result4 === false) {
            echo "FEHLER: Konnte " . $dateiname . " nicht schreiben!\n";
        } else {
            echo $dateiname . " erfolgreich erstellt (" . $result4 . " Bytes)\n";
        }
    }
    
    echo "HTML-Generierung abgeschlossen.\n";
    
} catch (Exception $e) {
    echo 'EXCEPTION beim Generieren der HTML-Dateien: ' . $e->getMessage() . "\n";
}
?>
