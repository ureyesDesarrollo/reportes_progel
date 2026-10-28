<?php

declare(strict_types=1);

$config = $config ?? require __DIR__ . '/config.php';

function clarificadorSqlIdentifier(string $identifier): string
{
    if ($identifier === '' || preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
        throw new InvalidArgumentException('Identificador AVEVA no válido.');
    }
    return '[' . $identifier . ']';
}

function clarificadorMysqlIdentifier(string $identifier): string
{
    if ($identifier === '' || preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
        throw new InvalidArgumentException('Identificador MySQL no válido.');
    }
    return '`' . $identifier . '`';
}

function clarificadorStatusLabel(string $status): string
{
    return ['verde' => 'En objetivo', 'amarillo' => 'Alerta', 'rojo' => 'Crítico', 'gris' => 'Sin datos'][$status] ?? 'Sin datos';
}

function clarificadorEvaluate(?float $value, array $bands): array
{
    if ($value === null) {
        return ['key' => 'gris', 'label' => 'Sin dato'];
    }
    foreach ($bands as $band) {
        $min = array_key_exists('min', $band) ? (float)$band['min'] : null;
        $max = array_key_exists('max', $band) ? (float)$band['max'] : null;
        if (($min === null || $value >= $min) && ($max === null || $value <= $max)) {
            $status = in_array($band['status'] ?? '', ['verde', 'amarillo', 'rojo'], true) ? (string)$band['status'] : 'gris';
            return ['key' => $status, 'label' => clarificadorStatusLabel($status)];
        }
    }
    return ['key' => 'rojo', 'label' => 'Crítico'];
}

function clarificadorAdjustment(?float $value, array $bands, int $decimals): array
{
    if ($value === null) {
        return ['direction' => '', 'formatted' => ''];
    }
    $direction = '';
    $distance = INF;
    foreach ($bands as $band) {
        if (($band['status'] ?? '') !== 'verde') {
            continue;
        }
        $min = array_key_exists('min', $band) ? (float)$band['min'] : null;
        $max = array_key_exists('max', $band) ? (float)$band['max'] : null;
        if (($min === null || $value >= $min) && ($max === null || $value <= $max)) {
            return ['direction' => '', 'formatted' => ''];
        }
        if ($min !== null && $value < $min && $min - $value < $distance) {
            $direction = 'up';
            $distance = $min - $value;
        }
        if ($max !== null && $value > $max && $value - $max < $distance) {
            $direction = 'down';
            $distance = $value - $max;
        }
    }
    if ($direction === '' || !is_finite($distance)) {
        return ['direction' => '', 'formatted' => ''];
    }
    $factor = 10 ** max(0, $decimals);
    $required = ceil(max(0.0, $distance - 0.000000001) * $factor) / $factor;
    return [
        'direction' => $direction,
        'formatted' => number_format(max(1 / $factor, $required), $decimals, '.', ','),
    ];
}

function clarificadorField(string $key, array $metric, $rawValue, bool $outOfOperation): array
{
    $decimals = max(0, (int)($metric['decimals'] ?? 1));
    if ($outOfOperation) {
        return [
            'key' => $key,
            'label' => (string)($metric['label'] ?? $key),
            'source' => (string)($metric['source'] ?? 'mysql'),
            'formatted' => 'F.O.', 'unit' => '', 'statusKey' => 'gris',
            'statusLabel' => 'Fuera de operación', 'direction' => '', 'adjustment' => '',
        ];
    }
    $value = is_numeric($rawValue) ? (float)$rawValue : null;
    $bands = (array)($metric['rule'] ?? []);
    $status = clarificadorEvaluate($value, $bands);
    $adjustment = clarificadorAdjustment($value, $bands, $decimals);
    return [
        'key' => $key,
        'label' => (string)($metric['label'] ?? $key),
        'source' => (string)($metric['source'] ?? 'mysql'),
        'formatted' => $value === null ? '—' : number_format($value, $decimals, '.', ','),
        'unit' => (string)($metric['unit'] ?? ''),
        'statusKey' => $status['key'],
        'statusLabel' => $status['label'],
        'direction' => $adjustment['direction'],
        'adjustment' => $adjustment['formatted'],
    ];
}

function clarificadorTimestamp($value): string
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('d/m/Y H:i:s');
    }
    $raw = trim((string)$value);
    if ($raw === '') {
        return '—';
    }
    try {
        return (new DateTimeImmutable($raw))->format('d/m/Y H:i:s');
    } catch (Throwable $exception) {
        return $raw;
    }
}

function clarificadorIsOutOfOperation($value): bool
{
    if ($value === null) {
        return false;
    }
    $normalized = mb_strtolower(trim((string)$value), 'UTF-8');
    return in_array($normalized, ['1', 'true', 'si', 'sí', 's', 'yes', 'y', 'fo', 'fuera', 'fuera de operacion', 'fuera de operación'], true);
}

function clarificadorResolve(string $field, array $rows)
{
    foreach ($rows as $row) {
        $value = $row[$field] ?? null;
        if ($value !== null && (!is_numeric($value) || (float)$value !== 0.0)) {
            return $value;
        }
    }
    return $rows[0][$field] ?? null;
}

function clarificadorReadAveva(array $config): array
{
    $database = (array)($config['aveva'] ?? []);
    if (trim((string)($database['server'] ?? '')) === '' || trim((string)($database['database'] ?? '')) === '' || trim((string)($database['user'] ?? '')) === '') {
        throw new RuntimeException('La conexión AVEVA no está configurada.');
    }
    if (!extension_loaded('pdo_sqlsrv')) {
        throw new RuntimeException('La extensión pdo_sqlsrv no está disponible.');
    }
    $fields = [];
    foreach ((array)($config['metrics'] ?? []) as $metric) {
        if (($metric['source'] ?? '') === 'aveva') {
            $fields[] = (string)($metric['field'] ?? '');
        }
    }
    $fields = array_values(array_unique(array_filter($fields)));
    $table = clarificadorSqlIdentifier((string)($database['table'] ?? 'TREND001'));
    $timestamp = clarificadorSqlIdentifier((string)($database['timestamp_field'] ?? 'Time_Stamp'));
    $columns = [$timestamp . ' AS [__timestamp]'];
    foreach ($fields as $field) {
        $columns[] = clarificadorSqlIdentifier($field);
    }
    $dsn = sprintf(
        'sqlsrv:Server=%s,%d;Database=%s;Encrypt=%s;TrustServerCertificate=%s;LoginTimeout=%d',
        (string)$database['server'], max(1, (int)($database['port'] ?? 1433)), (string)$database['database'],
        !empty($database['encrypt']) ? 'yes' : 'no', !empty($database['trust_server_certificate']) ? 'yes' : 'no',
        max(1, (int)($database['login_timeout'] ?? 5))
    );
    $pdo = new PDO($dsn, (string)$database['user'], (string)($database['password'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $sql = 'SELECT TOP (1) ' . implode(', ', $columns) . ' FROM ' . $table
        . ' WHERE CAST(' . $timestamp . ' AS date) = CAST(GETDATE() AS date) ORDER BY ' . $timestamp . ' DESC';
    $row = $pdo->query($sql)->fetch();
    return is_array($row) ? $row : [];
}

function clarificadorReadMysql(array $database): array
{
    if (trim((string)($database['host'] ?? '')) === '' || trim((string)($database['database'] ?? '')) === '' || trim((string)($database['user'] ?? '')) === '') {
        throw new RuntimeException('La conexión MySQL no está configurada.');
    }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', (string)$database['host'], max(1, (int)($database['port'] ?? 3306)), (string)$database['database']);
    $pdo = new PDO($dsn, (string)$database['user'], (string)($database['password'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $table = clarificadorMysqlIdentifier((string)($database['table'] ?? 'datos_clarificador'));
    $columns = $pdo->query('SHOW COLUMNS FROM ' . $table)->fetchAll() ?: [];
    $names = array_map(static fn(array $row): string => (string)($row['Field'] ?? ''), $columns);
    $hasDataHour = in_array('id_datos_hora', $names, true);
    $join = $hasDataHour ? ' INNER JOIN `datos_hora` dh ON dh.`id` = d.`id_datos_hora`' : '';
    $timestamp = $hasDataHour ? ', COALESCE(dh.`hora_exacta`, dh.`fecha_creacion`) AS `__timestamp`' : '';
    $today = $hasDataHour ? ' WHERE DATE(COALESCE(dh.`hora_exacta`, dh.`fecha_creacion`)) = CURDATE()' : '';
    $order = [];
    if (in_array('id_datos_hora', $names, true)) $order[] = 'd.`id_datos_hora` DESC';
    if (in_array('id', $names, true)) $order[] = 'd.`id` DESC';
    $orderSql = $order !== [] ? ' ORDER BY ' . implode(', ', $order) : '';
    $rows = $pdo->query('SELECT d.*' . $timestamp . ' FROM ' . $table . ' d' . $join . $today . $orderSql . ' LIMIT 300')->fetchAll() ?: [];
    return $rows;
}

function clarificadorWorst(array $fields): array
{
    $priority = ['gris' => 0, 'verde' => 1, 'amarillo' => 2, 'rojo' => 3];
    $worst = 'gris';
    foreach ($fields as $field) {
        $status = (string)($field['statusKey'] ?? 'gris');
        if (($priority[$status] ?? 0) > ($priority[$worst] ?? 0)) $worst = $status;
    }
    return ['key' => $worst, 'label' => clarificadorStatusLabel($worst)];
}

function clarificadorBuildReport(array $config): array
{
    $warnings = [];
    $avevaOk = true;
    $mysqlOk = true;
    $integrationOk = true;
    try {
        $aveva = clarificadorReadAveva($config);
    } catch (Throwable $exception) {
        $avevaOk = false;
        $aveva = [];
        $warnings[] = 'AVEVA no está disponible.';
        error_log('[reportes-progel:clarificador:aveva] ' . $exception->getMessage());
    }
    try {
        $mysqlRows = clarificadorReadMysql((array)($config['mysql'] ?? []));
    } catch (Throwable $exception) {
        $mysqlOk = false;
        $mysqlRows = [];
        $warnings[] = 'La bitácora del Clarificador no está disponible.';
        error_log('[reportes-progel:clarificador:mysql] ' . $exception->getMessage());
    }
    try {
        $integrationRows = clarificadorReadMysql((array)($config['integration_mysql'] ?? []));
    } catch (Throwable $exception) {
        $integrationOk = false;
        $integrationRows = [];
        $warnings[] = 'Los datos de integración no están disponibles.';
        error_log('[reportes-progel:clarificador:integracion] ' . $exception->getMessage());
    }
    $latestMysql = (array)($mysqlRows[0] ?? []);
    $foColumn = (string)($config['mysql']['out_of_operation_column'] ?? 'estado_fo');
    $outOfOperation = clarificadorIsOutOfOperation($latestMysql[$foColumn] ?? null);
    $latestIntegration = (array)($integrationRows[0] ?? []);
    $integrationFoColumn = (string)($config['integration_mysql']['out_of_operation_column'] ?? 'estado_fo_integ');
    $integrationOutOfOperation = clarificadorIsOutOfOperation($latestIntegration[$integrationFoColumn] ?? null);
    $fields = [];
    foreach ((array)($config['metrics'] ?? []) as $key => $metric) {
        $fieldName = (string)($metric['field'] ?? '');
        $source = (string)($metric['source'] ?? 'mysql');
        if ($source === 'aveva') {
            $raw = $aveva[$fieldName] ?? null;
        } elseif ($source === 'integration') {
            $raw = clarificadorResolve($fieldName, $integrationRows);
        } else {
            $raw = clarificadorResolve($fieldName, $mysqlRows);
        }
        $fieldOutOfOperation = $source === 'integration' ? $integrationOutOfOperation : $outOfOperation;
        $fields[(string)$key] = clarificadorField((string)$key, (array)$metric, $raw, $fieldOutOfOperation);
    }
    $status = $outOfOperation && $integrationOutOfOperation
        ? ['key' => 'gris', 'label' => 'Fuera de operación']
        : clarificadorWorst(array_values($fields));
    return [
        'ok' => $avevaOk || $mysqlOk || $integrationOk,
        'title' => (string)($config['title'] ?? 'Clarificador'),
        'subtitle' => (string)($config['subtitle'] ?? 'Monitoreo en tiempo real'),
        'warning' => implode(' ', $warnings),
        'statusKey' => $status['key'],
        'statusLabel' => $status['label'],
        'outOfOperation' => $outOfOperation && $integrationOutOfOperation,
        'avevaTimestamp' => clarificadorTimestamp($aveva['__timestamp'] ?? null),
        'mysqlTimestamp' => clarificadorTimestamp($latestMysql['__timestamp'] ?? null),
        'fields' => $fields,
        'refreshMs' => max(1000, (int)($config['refresh_ms'] ?? 60000)),
    ];
}

return clarificadorBuildReport($config);
