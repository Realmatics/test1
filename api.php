<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$apiKey = 'AIzaSyDln7gFAN3ZtpmTqHqZ78kV1A90vq3ZMKw';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['message']) || empty($input['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit;
}

$message = $input['message'];
$fileData = $input['fileData'] ?? null;
$fileName = $input['fileName'] ?? null;
$mimeType = $input['mimeType'] ?? null;
$model = $input['model'] ?? 'gemma-3-1b'; // Standard: gemma-3-1b

// Für Gemma-Modelle: "-it" am Ende anhängen
if (strpos($model, 'gemma') === 0) {
    $model = $model . '-it';
}

// Prepare the request payload for Gemini API
// Modell wird vom Frontend übergeben
$url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

$parts = [
    ['text' => $message]
];

// If file data is provided, add it to the request
if ($fileData && $fileName && $mimeType) {
    $filePart = [
        'inline_data' => [
            'mime_type' => $mimeType,
            'data' => $fileData
        ]
    ];
    $parts[] = $filePart;
}

$payload = [
    'contents' => [
        [
            'parts' => $parts
        ]
    ]
];

// Initialize cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'x-goog-api-key: ' . $apiKey
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode(['error' => 'cURL Error: ' . $curlError]);
    exit;
}

if ($httpCode !== 200) {
    http_response_code($httpCode);
    $errorDetails = json_decode($response, true);
    echo json_encode([
        'error' => 'API Error',
        'message' => $errorDetails['error']['message'] ?? 'Unknown error',
        'status' => $errorDetails['error']['status'] ?? 'Unknown status',
        'details' => $errorDetails,
        'httpCode' => $httpCode,
        'rawResponse' => $response
    ]);
    exit;
}

$responseData = json_decode($response, true);

if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
    $text = $responseData['candidates'][0]['content']['parts'][0]['text'];
    echo json_encode([
        'success' => true,
        'response' => $text
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'error' => 'Unexpected API response format',
        'message' => 'Die API-Antwort hat ein unerwartetes Format',
        'response' => $responseData,
        'rawResponse' => $response
    ]);
}
?>