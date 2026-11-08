<?php
/**
 * Kopiere diese Datei nach config.php und passe die Werte an.
 * config.php niemals öffentlich committen.
 */
return [
    'app_name' => 'Realmatics',
    'app_url' => '', // z.B. https://deine-domain.de  (leer = auto)
    'admin_email' => 'hello@realmatics.example',

    // OpenAI-kompatible API (optional). Leer = Demo-Modus mit lokalem Entscheider.
    'openai_api_key' => '',
    'openai_base_url' => 'https://api.openai.com/v1',
    'openai_model' => 'gpt-4o-mini',

    // Geheimer Setup-Key zum Freischalten von Team/Pro ohne Stripe (nur du).
    'admin_setup_key' => 'change-me-now',

    // Optional: Stripe Payment Links (wenn gesetzt, werden Upgrade-Buttons darauf verlinkt)
    'stripe_payment_link_team' => '',
    'stripe_payment_link_pro' => '',
];
