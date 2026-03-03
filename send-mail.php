<?php
header('Content-Type: application/json');

// Überprüfe ob es sich um eine POST-Anfrage handelt
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt']);
    exit;
}

// Hole und bereinige die Formulardaten
$name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);

// Validiere die E-Mail-Adresse
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültige E-Mail-Adresse']);
    exit;
}

// Überprüfe ob alle Felder ausgefüllt sind
if (empty($name) || empty($email) || empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Bitte füllen Sie alle Felder aus']);
    exit;
}

// E-Mail-Einstellungen
$to = "michael.kleiter@gmx.de";
$subject = "Kontaktanfrage von " . $name;
$headers = "From: " . $email . "\r\n";
$headers .= "Reply-To: " . $email . "\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

// E-Mail-Inhalt
$email_content = "Name: " . $name . "\n";
$email_content .= "E-Mail: " . $email . "\n\n";
$email_content .= "Nachricht:\n" . $message;

// Versuche die E-Mail zu senden
if (mail($to, $subject, $email_content, $headers)) {
    echo json_encode(['success' => true, 'message' => 'Vielen Dank für Ihre Nachricht! Ich werde mich bald bei Ihnen melden.']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Beim Senden der E-Mail ist ein Fehler aufgetreten. Bitte versuchen Sie es später erneut.']);
}
?> 