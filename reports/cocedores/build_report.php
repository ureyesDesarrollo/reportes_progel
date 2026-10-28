<?php

declare(strict_types=1);

$config = $config ?? require __DIR__ . '/config.php';

function cocedoresSqlServerIdentifier(string $identifier): string
{
    if ($identifier === '' || preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
        throw new InvalidArgumentException('Identificador AVEVA no válido.');
    }
    return '[' . $identifier . ']';
}

function cocedoresMysqlIdentifier(string $identifier): string
{
    if ($identifier === '' || preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
        throw new InvalidArgumentException('Identificador MySQL no válido.');
    }
    return '`' . $identifier . '`';
}

function cocedoresStatusLabel(string $status): string
{
    return [
        'verde' => 'En objetivo',
        'amarillo' => 'Alerta',
        'rojo' => 'Crítico',
        'gris' => 'Sin datos',
    ][$status] ?? 'Sin datos';
}

function cocedoresEvaluate(?float $value, array $bands): array
{
    if ($value === null) {
        return ['key' => 'gris', 'label' => 'Sin dato'];
    }

    foreach ($bands as $band) {
        $min = array_key_exists('min', $band) ? (float)$band['min'] : null;
        $max = array_key_exists('max', $band) ? (float)$band['max'] : null;
        if (($min === null || $value >= $min) && ($max === null || $value <= $max)) {
            $status = in_array($band['status'] ?? '', ['verde', 'amarillo', 'rojo'], true)
                ? (string)$band['status']
                : 'gris';
            return ['key' => $status, 'label' => cocedoresStatusLabel($status)];
        }
    }

    return ['key' => 'rojo', 'label' => 'Crítico'];
}

function cocedoresAdjustmentToGreen(?float $value, array $bands, int $decimals): array
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

function cocedoresField(string $key, array $fieldConfig, $rawValue, bool $outOfOperation = false): array
{
    $value = is_numeric($rawValue) ? (float)$rawValue : null;
    $decimals = max(0, (int)($fieldConfig['decimals'] ?? 1));
    $unit = (string)($fieldConfig['unit'] ?? '');
    $bands = (array)($fieldConfig['rule'] ?? []);

    if ($outOfOperation) {
        return [
            'key' => $key,
            'label' => (string)($fieldConfig['label'] ?? $key),
            'formatted' => 'F.O.',
            'unit' => '',
            'statusKey' => 'gris',
            'statusLabel' => 'Fuera de operación',
            'direction' => '',
            'adjustment' => '',
        ];
    }

    $status = cocedoresEvaluate($value, $bands);
    $adjustment = cocedoresAdjustmentToGreen($value, $bands, $decimals);
    return [
        'key' => $key,
        'label' => (string)($fieldConfig['label'] ?? $key),
        'formatted' => $value === null ? '—' : number_format($value, $decimals, '.', ','),
        'unit' => $unit,
        'statusKey' => $status['key'],
        'statusLabel' => $status['label'],
        'direction' => $adjustment['direction'],
        'adjustment' => $adjustment['formatted'],
    ];
}

function cocedoresTimestamp($value): string
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

function cocedoresIsOutOfOperation($value): bool
{
    if ($value === null) {
        return false;
    }
    $normalized = mb_strtolower(trim((string)$value), 'UTF-8');
    return in_array($normalized, ['1', 'true', 'si', 'sí', 's', 'yes', 'y', 'fo', 'fuera', 'fuera de operacion', 'fuera de operación'], true);
}

function cocedoresResolveMetric(string $column, array $rows)
{
    foreach ($rows as $row) {
        $value = $row[$column] ?? null;
        if ($value !== null && (!is_numeric($value) || (float)$value !== 0.0)) {
            return $value;
        }
    }
    return $rows[0][$column] ?? null;
}

function cocedoresReadAveva(array $config): array
{
    $database = (array)($config['aveva'] ?? []);
    if (trim((string)($database['server'] ?? '')) === '' || trim((string)($database['database'] ?? '')) === '' || trim((string)($database['user'] ?? '')) === '') {
        throw new RuntimeException('La conexión AVEVA no está configurada.');
    }
    if (!extension_loaded('pdo_sqlsrv')) {
        throw new RuntimeException('La extensión pdo_sqlsrv no está disponible.');
    }

    $sensors = [];
    foreach ((array)($config['header_metrics'] ?? []) as $metric) {
        $sensors[] = (string)($metric['sensor'] ?? '');
    }
    foreach ((array)($config['equipment'] ?? []) as $equipment) {
        $sensors[] = (string)($equipment['flow']['sensor'] ?? '');
    }
    $sensors = array_values(array_unique(array_filter($sensors)));

    $table = cocedoresSqlServerIdentifier((string)($database['table'] ?? 'TREND001'));
    $timestamp = cocedoresSqlServerIdentifier((string)($database['timestamp_field'] ?? 'Time_Stamp'));
    $columns = [$timestamp . ' AS [__timestamp]'];
    foreach ($sensors as $sensor) {
        $columns[] = cocedoresSqlServerIdentifier($sensor);
    }

    $dsn = sprintf(
        'sqlsrv:Server=%s,%d;Database=%s;Encrypt=%s;TrustServerCertificate=%s;LoginTimeout=%d',
        (string)$database['server'],
        max(1, (int)($database['port'] ?? 1433)),
        (string)$database['database'],
        !empty($database['encrypt']) ? 'yes' : 'no',
        !empty($database['trust_server_certificate']) ? 'yes' : 'no',
        max(1, (int)($database['login_timeout'] ?? 5))
    );
    $pdo = new PDO($dsn, (string)$database['user'], (string)($database['password'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $sql = 'SELECT TOP (1) ' . implode(', ', $columns)
        . ' FROM ' . $table
        . ' WHERE CAST(' . $timestamp . ' AS date) = CAST(GETDATE() AS date)'
        . ' ORDER BY ' . $timestamp . ' DESC';
    $row = $pdo->query($sql)->fetch();
    return is_array($row) ? $row : [];
}

function cocedoresReadMysql(array $config): array
{
    $database = (array)($config['mysql'] ?? []);
    if (trim((string)($database['host'] ?? '')) === '' || trim((string)($database['database'] ?? '')) === '' || trim((string)($database['user'] ?? '')) === '') {
        throw new RuntimeException('La conexión MySQL no está configurada.');
    }
    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('La extensión pdo_mysql no está disponible.');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        (string)$database['host'],
        max(1, (int)($database['port'] ?? 3306)),
        (string)$database['database']
    );
    $pdo = new PDO($dsn, (string)$database['user'], (string)($database['password'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5,
    ]);

    $table = cocedoresMysqlIdentifier((string)($database['table'] ?? 'datos_cocedores'));
    $numberColumn = cocedoresMysqlIdentifier((string)($database['number_column'] ?? 'numero_cocedor'));
    $columns = $pdo->query('SHOW COLUMNS FROM ' . $table)->fetchAll() ?: [];
    $columnNames = array_map(static fn(array $row): string => (string)($row['Field'] ?? ''), $columns);
    $hasDataHour = in_array('id_datos_hora', $columnNames, true);

    $join = $hasDataHour ? ' INNER JOIN `datos_hora` dh ON dh.`id` = d.`id_datos_hora`' : '';
    $timestampSelect = $hasDataHour ? ', COALESCE(dh.`hora_exacta`, dh.`fecha_creacion`) AS `__timestamp`' : '';
    $today = $hasDataHour ? ' AND DATE(COALESCE(dh.`hora_exacta`, dh.`fecha_creacion`)) = CURDATE()' : '';
    $order = [];
    if (in_array('id_datos_hora', $columnNames, true)) {
        $order[] = 'd.`id_datos_hora` DESC';
    }
    if (in_array('id', $columnNames, true)) {
        $order[] = 'd.`id` DESC';
    }
    $orderSql = $order !== [] ? ' ORDER BY ' . implode(', ', $order) : '';
    $sql = 'SELECT d.*' . $timestampSelect . ' FROM ' . $table . ' d' . $join
        . ' WHERE d.' . $numberColumn . ' = :number' . $today . $orderSql . ' LIMIT 300';
    $statement = $pdo->prepare($sql);
    $rowsByNumber = [];
    foreach ((array)($config['equipment'] ?? []) as $equipment) {
        $number = (int)($equipment['number'] ?? 0);
        if ($number <= 0) {
            continue;
        }
        $statement->execute(['number' => $number]);
        $rowsByNumber[$number] = $statement->fetchAll() ?: [];
    }
    return $rowsByNumber;
}

function cocedoresWorstStatus(array $fields): array
{
    $priority = ['gris' => 0, 'verde' => 1, 'amarillo' => 2, 'rojo' => 3];
    $worst = 'gris';
    foreach ($fields as $field) {
        $status = (string)($field['statusKey'] ?? 'gris');
        if (($priority[$status] ?? 0) > ($priority[$worst] ?? 0)) {
            $worst = $status;
        }
    }
    return ['key' => $worst, 'label' => cocedoresStatusLabel($worst)];
}

function cocedoresBuildReport(array $config): array
{
    $warnings = [];
    $avevaAvailable = true;
    $mysqlAvailable = true;
    try {
        $avevaRow = cocedoresReadAveva($config);
    } catch (Throwable $exception) {
        $avevaAvailable = false;
        $avevaRow = [];
        $warnings[] = 'AVEVA no está disponible.';
        error_log('[reportes-progel:cocedores:aveva] ' . $exception->getMessage());
    }
    try {
        $mysqlRows = cocedoresReadMysql($config);
    } catch (Throwable $exception) {
        $mysqlAvailable = false;
        $mysqlRows = [];
        $warnings[] = 'La bitácora de Cocedores no está disponible.';
        error_log('[reportes-progel:cocedores:mysql] ' . $exception->getMessage());
    }

    $avevaTimestamp = cocedoresTimestamp($avevaRow['__timestamp'] ?? null);
    $headerMetrics = [];
    foreach ((array)($config['header_metrics'] ?? []) as $key => $metricConfig) {
        $sensor = (string)($metricConfig['sensor'] ?? '');
        $field = cocedoresField((string)$key, (array)$metricConfig, $avevaRow[$sensor] ?? null);
        $field['timestamp'] = $avevaTimestamp;
        $headerMetrics[(string)$key] = $field;
    }

    $equipmentReport = [];
    $latestMysqlTimestamp = '—';
    foreach ((array)($config['equipment'] ?? []) as $key => $equipmentConfig) {
        $number = (int)($equipmentConfig['number'] ?? 0);
        $rows = (array)($mysqlRows[$number] ?? []);
        $latestRow = (array)($rows[0] ?? []);
        $outColumn = (string)($config['mysql']['out_of_operation_column'] ?? 'estado_fo');
        $outOfOperation = cocedoresIsOutOfOperation($latestRow[$outColumn] ?? null);
        $mysqlTimestamp = cocedoresTimestamp($latestRow['__timestamp'] ?? null);
        if ($latestMysqlTimestamp === '—' && $mysqlTimestamp !== '—') {
            $latestMysqlTimestamp = $mysqlTimestamp;
        }

        $flowConfig = (array)($equipmentConfig['flow'] ?? []);
        $flowSensor = (string)($flowConfig['sensor'] ?? '');
        $fields = [
            'flujo' => cocedoresField('flujo', $flowConfig, $avevaRow[$flowSensor] ?? null, $outOfOperation),
        ];
        foreach ((array)($config['mysql_metrics'] ?? []) as $metricKey => $metricConfig) {
            $column = (string)($metricConfig['column'] ?? '');
            $fields[(string)$metricKey] = cocedoresField(
                (string)$metricKey,
                (array)$metricConfig,
                cocedoresResolveMetric($column, $rows),
                $outOfOperation
            );
        }
        $status = cocedoresWorstStatus(array_values($fields));
        if ($outOfOperation) {
            $status = ['key' => 'gris', 'label' => 'Fuera de operación'];
        }

        $equipmentReport[(string)$key] = [
            'key' => (string)$key,
            'label' => (string)($equipmentConfig['label'] ?? $key),
            'statusKey' => $status['key'],
            'statusLabel' => $status['label'],
            'outOfOperation' => $outOfOperation,
            'fields' => $fields,
        ];
    }

    return [
        'ok' => $avevaAvailable || $mysqlAvailable,
        'title' => (string)($config['title'] ?? 'Cocedores'),
        'subtitle' => (string)($config['subtitle'] ?? 'Monitoreo en tiempo real'),
        'warning' => implode(' ', $warnings),
        'avevaTimestamp' => $avevaTimestamp,
        'mysqlTimestamp' => $latestMysqlTimestamp,
        'headerMetrics' => $headerMetrics,
        'equipment' => $equipmentReport,
        'refreshMs' => max(1000, (int)($config['refresh_ms'] ?? 60000)),
    ];
}

return cocedoresBuildReport($config);
