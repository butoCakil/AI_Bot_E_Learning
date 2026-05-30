<?php
require_once '../config/config.php';
session_start();

header('Content-Type: application/json');

if (empty($_SESSION['guru_id'])) {
    echo json_encode(['status' => 'error', 'pesan' => 'Akses ditolak.']);
    exit;
}

$body     = json_decode(file_get_contents('php://input'), true);
$provider = $body['provider'] ?? '';
$model    = $body['model']    ?? '';

if (!$provider || !$model) {
    echo json_encode(['status' => 'error', 'pesan' => 'Provider atau model tidak valid.']);
    exit;
}

$preview = '';

if ($provider === 'groq') {
    $payload = json_encode([
        'model'      => $model,
        'messages'   => [['role' => 'user', 'content' => 'Apa itu dioda? Jawab 1 kalimat saja.']],
        'max_tokens' => 50,
    ]);
    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . GROQ_API_KEY,
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT    => 10,
    ]);
    $resp   = curl_exec($ch);
    $result = json_decode($resp, true);
    curl_close($ch);

    if (isset($result['choices'][0]['message']['content'])) {
        $preview = substr($result['choices'][0]['message']['content'], 0, 100) . '...';
        echo json_encode(['status' => 'ok', 'preview' => $preview]);
    } else {
        $err = $result['error']['message'] ?? 'Model tidak merespons.';
        echo json_encode(['status' => 'error', 'pesan' => $err]);
    }

} elseif ($provider === 'gemini') {
    $payload = json_encode([
        'contents' => [['parts' => [['text' => 'Apa itu dioda? Jawab 1 kalimat saja.']]]]
    ]);
    $ch = curl_init(GEMINI_API_URL . '?key=' . GEMINI_API_KEY);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp   = curl_exec($ch);
    $result = json_decode($resp, true);
    curl_close($ch);

    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        $preview = substr($result['candidates'][0]['content']['parts'][0]['text'], 0, 100) . '...';
        echo json_encode(['status' => 'ok', 'preview' => $preview]);
    } else {
        $err = $result['error']['message'] ?? 'Model tidak merespons.';
        echo json_encode(['status' => 'error', 'pesan' => $err]);
    }

} else {
    echo json_encode(['status' => 'error', 'pesan' => 'Provider tidak dikenali.']);
}
