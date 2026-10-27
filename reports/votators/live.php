<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$config = require __DIR__ . '/config.php';
$report = require __DIR__ . '/build_report.php';
$equipment = [];

foreach ((array)($report['equipos'] ?? []) as $item) {
    $fields = [];
    foreach (array_merge((array)($item['principales'] ?? []), (array)($item['secundarios'] ?? [])) as $field) {
        $fields[(string)($field['key'] ?? '')] = $field;
    }
    $equipment[(string)($item['key'] ?? '')] = [
        'statusKey' => (string)($item['statusKey'] ?? 'gris'),
        'statusLabel' => (string)($item['statusLabel'] ?? 'Sin datos'),
        'timestamp' => (string)($item['ultimaLectura'] ?? '—'),
        'fields' => $fields,
    ];
}

$feed = (array)($report['temperatura_alimentacion'] ?? []);
$response = [
    'ok' => !empty($report['ok']),
    'timestamp' => (string)($report['ultima_lectura'] ?? '—'),
    'feedTemperature' => [
        'formatted' => (string)($feed['formatted'] ?? '—'),
        'unit' => (string)($feed['unit'] ?? '°C'),
        'timestamp' => (string)($feed['fecha'] ?? '—'),
        'statusKey' => (string)($feed['statusKey'] ?? 'gris'),
        'direction' => (string)($feed['direction'] ?? ''),
        'adjustment' => (string)($feed['adjustment'] ?? ''),
    ],
    'equipment' => $equipment,
];

if (empty($report['ok'])) {
    http_response_code(503);
    $response['error'] = 'La fuente de datos no está disponible temporalmente.';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

