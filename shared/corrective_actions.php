<?php

declare(strict_types=1);

function correctiveActionsQuoteIdentifier(string $identifier): string
{
    if ($identifier === '' || preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
        throw new InvalidArgumentException('Identificador MySQL no válido.');
    }
    return '`' . $identifier . '`';
}

function correctiveActionsConnection(array $database): PDO
{
    $host = trim((string)($database['host'] ?? ''));
    $name = trim((string)($database['database'] ?? ''));
    $user = trim((string)($database['user'] ?? ''));
    if ($host === '' || $name === '' || $user === '') {
        throw new RuntimeException('La conexión de acciones correctivas no está configurada.');
    }
    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('La extensión pdo_mysql no está disponible.');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $host,
        (int)($database['port'] ?? 3306),
        $name
    );

    return new PDO($dsn, $user, (string)($database['password'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 3,
    ]);
}

function correctiveActionsColor($color): string
{
    $normalized = strtolower(trim((string)$color));
    return in_array($normalized, ['danger', 'warning', 'info'], true) ? $normalized : 'action';
}

function correctiveActionsObservations($value): array
{
    $parts = preg_split('/\s*\|\s*/u', trim((string)$value)) ?: [];
    return array_values(array_filter(array_map('trim', $parts), static fn(string $part): bool => $part !== ''));
}

function correctiveActionsTimestamp($value): string
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return '—';
    }
    try {
        return (new DateTimeImmutable($raw))->format('d/m/Y H:i');
    } catch (Throwable $exception) {
        return $raw;
    }
}

function correctiveActionsLoad(array $config): array
{
    $database = (array)($config['database'] ?? []);
    $table = correctiveActionsQuoteIdentifier((string)($config['table'] ?? ''));
    $foreignKey = correctiveActionsQuoteIdentifier((string)($config['foreign_key'] ?? ''));
    $limit = max(1, min(12, (int)($config['limit'] ?? 8)));
    $pdo = correctiveActionsConnection($database);

    $sql = 'SELECT d.`id`, d.' . $foreignKey . ' AS `equipo_id`, e.`nombre` AS `equipo`, '
        . 'd.`fecha_registro`, d.`estado_id`, d.`observaciones`, '
        . 's.`codigo` AS `estado_codigo`, s.`nombre` AS `estado_nombre`, '
        . 's.`descripcion` AS `estado_descripcion`, s.`color` AS `estado_color` '
        . 'FROM ' . $table . ' d '
        . 'INNER JOIN `equipos` e ON e.`id` = d.' . $foreignKey . ' '
        . 'LEFT JOIN `estados` s ON s.`id` = d.`estado_id` '
        . 'WHERE COALESCE(e.`activo`, 1) = 1 '
        . 'AND d.`id` = (SELECT d2.`id` FROM ' . $table . ' d2 '
        . 'WHERE d2.' . $foreignKey . ' = d.' . $foreignKey . ' '
        . 'ORDER BY d2.`fecha_registro` DESC, d2.`id` DESC LIMIT 1) '
        . 'AND (d.`estado_id` IS NOT NULL OR NULLIF(TRIM(d.`observaciones`), \'\') IS NOT NULL) '
        . 'ORDER BY d.' . $foreignKey . ' ASC LIMIT :limit';

    $statement = $pdo->prepare($sql);
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();

    $actions = [];
    foreach ($statement->fetchAll() as $row) {
        $stateName = trim((string)($row['estado_nombre'] ?? ''));
        $actions[] = [
            'id' => (int)($row['id'] ?? 0),
            'equipmentId' => (int)($row['equipo_id'] ?? 0),
            'equipment' => trim((string)($row['equipo'] ?? 'Equipo')) ?: 'Equipo',
            'timestamp' => correctiveActionsTimestamp($row['fecha_registro'] ?? null),
            'stateCode' => trim((string)($row['estado_codigo'] ?? '')),
            'stateName' => $stateName !== '' ? $stateName : 'Acción correctiva',
            'stateDescription' => trim((string)($row['estado_descripcion'] ?? '')),
            'stateColor' => correctiveActionsColor($row['estado_color'] ?? null),
            'observations' => correctiveActionsObservations($row['observaciones'] ?? null),
        ];
    }

    return $actions;
}
