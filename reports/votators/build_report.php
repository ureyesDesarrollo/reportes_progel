<?php

declare(strict_types=1);

use ReportesProgel\Votators\AvevaRepository;
use ReportesProgel\Votators\VotatorsService;

$config = $config ?? require __DIR__ . '/config.php';
require_once __DIR__ . '/../../src/AvevaRepository.php';
require_once __DIR__ . '/../../src/VotatorsService.php';

$service = new VotatorsService($config, new AvevaRepository((array)($config['aveva'] ?? [])));

try {
    return $service->build();
} catch (Throwable $exception) {
    error_log('[reportes-progel:votators] ' . $exception->getMessage());
    return $service->empty('La fuente de datos no está disponible temporalmente.');
}

