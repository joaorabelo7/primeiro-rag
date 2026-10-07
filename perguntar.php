<?php
header('Content-Type: application/json; charset=utf-8');

// Lê a pergunta enviada pelo navegador
$entrada = json_decode(file_get_contents('php://input'), true);
$pergunta = trim($entrada['query'] ?? '');

if ($pergunta === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Pergunta vazia']);
    exit;
}

// Chama o RAG em Python
$ch = curl_init('http://127.0.0.1:8000/query');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode(['query' => $pergunta]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 120, // o LLM local pode demorar
]);

$resposta = curl_exec($ch);
$status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($resposta === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Não foi possível falar com o serviço de IA']);
    exit;
}

curl_close($ch);
http_response_code($status);
echo $resposta;