<?php

declare(strict_types=1);

/*
 * Flujo del reporte:
 * 1. Lee una sola fila de AVEVA.
 * 2. Evalúa cada lectura con los rangos de config.php.
 * 3. Devuelve el arreglo que consumen index.php y live.php.
 */

$config = $config ?? require __DIR__ . '/config.php';
require_once dirname(__DIR__, 2) . '/shared/corrective_actions.php';

function votatorsQuoteIdentifier(string $identifier): string
{
    if ($identifier === '' || preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
        throw new InvalidArgumentException('Identificador AVEVA no válido.');
    }
    return '[' . $identifier . ']';
}

function votatorsStatusLabel(string $status): string
{
    return [
        'verde' => 'En objetivo',
        'amarillo' => 'Alerta',
        'rojo' => 'Crítico',
        'gris' => 'Sin datos',
    ][$status] ?? 'Sin datos';
}

function votatorsEvaluate(?float $value, array $bands): array
{
    if ($value === null) {
        return ['key' => 'gris', 'label' => 'Sin dato'];
    }
    if ($bands === []) {
        return ['key' => 'gris', 'label' => 'Lectura'];
    }

    foreach ($bands as $band) {
        $min = isset($band['min']) ? (float)$band['min'] : null;
        $max = isset($band['max']) ? (float)$band['max'] : null;
        if (($min === null || $value >= $min) && ($max === null || $value <= $max)) {
            $status = in_array($band['status'] ?? '', ['verde', 'amarillo', 'rojo'], true)
                ? (string)$band['status']
                : 'gris';
            return ['key' => $status, 'label' => votatorsStatusLabel($status)];
        }
    }

    return ['key' => 'rojo', 'label' => 'Crítico'];
}

function votatorsAdjustmentToGreen(?float $value, array $bands): array
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

        $min = isset($band['min']) ? (float)$band['min'] : null;
        $max = isset($band['max']) ? (float)$band['max'] : null;
        if (($min === null || $value >= $min) && ($max === null || $value <= $max)) {
            return ['direction' => '', 'formatted' => ''];
        }
        if ($min !== null && $value < $min && ($min - $value) < $distance) {
            $direction = 'up';
            $distance = $min - $value;
        }
        if ($max !== null && $value > $max && ($value - $max) < $distance) {
            $direction = 'down';
            $distance = $value - $max;
        }
    }

    if ($direction === '' || !is_finite($distance)) {
        return ['direction' => '', 'formatted' => ''];
    }

    $required = ceil(max(0.0, $distance - 0.000000001) * 10) / 10;
    return [
        'direction' => $direction,
        'formatted' => number_format(max(0.1, $required), 1, '.', ','),
    ];
}

function votatorsField(string $key, array $fieldConfig, array $row): array
{
    $sensor = trim((string)($fieldConfig['sensor'] ?? ''));
    $value = $sensor !== '' && is_numeric($row[$sensor] ?? null) ? (float)$row[$sensor] : null;
    $secondarySensor = trim((string)($fieldConfig['secondary_sensor'] ?? ''));
    $secondaryValue = $secondarySensor !== '' && is_numeric($row[$secondarySensor] ?? null)
        ? round((float)$row[$secondarySensor], 1)
        : null;
    $displayValue = $value === null ? null : round($value, 1);
    $bands = (array)($fieldConfig['rule'] ?? []);
    if (array_key_exists('conditional_rule', $fieldConfig)) {
        $bands = [];
        if ($secondaryValue !== null) {
            foreach ((array)$fieldConfig['conditional_rule'] as $conditionalRule) {
                $secondaryMin = isset($conditionalRule['secondary_min']) ? (float)$conditionalRule['secondary_min'] : null;
                $secondaryMax = isset($conditionalRule['secondary_max']) ? (float)$conditionalRule['secondary_max'] : null;
                if (
                    ($secondaryMin === null || $secondaryValue >= $secondaryMin)
                    && ($secondaryMax === null || $secondaryValue <= $secondaryMax)
                ) {
                    $bands = (array)($conditionalRule['rule'] ?? []);
                    break;
                }
            }
        }
    }
    $status = votatorsEvaluate($displayValue, $bands);
    // Tiempo fuera usa únicamente el color de la tarjeta. No requiere flecha
    // porque el propio valor en minutos ya comunica la condición operativa.
    $adjustment = $key === 'tiempo_fuera'
        ? ['direction' => '', 'formatted' => '']
        : votatorsAdjustmentToGreen($displayValue, $bands);

    return [
        'key' => $key,
        'label' => (string)($fieldConfig['label'] ?? $key),
        'value' => $displayValue,
        'formatted' => $displayValue === null ? '—' : number_format($displayValue, 1, '.', ','),
        'unit' => (string)($fieldConfig['unit'] ?? ''),
        'statusKey' => $status['key'],
        'statusLabel' => $status['label'],
        'direction' => $adjustment['direction'],
        'adjustment' => $adjustment['formatted'],
        'secondaryLabel' => (string)($fieldConfig['secondary_label'] ?? ''),
        'secondaryFormatted' => $secondaryValue === null ? '—' : number_format($secondaryValue, 1, '.', ','),
    ];
}

function votatorsTimestamp($value): string
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

function votatorsSensorNames(array $config): array
{
    $names = [(string)($config['feed_temperature']['sensor'] ?? '')];
    foreach ((array)($config['equipment'] ?? []) as $equipment) {
        foreach ((array)($equipment['primary'] ?? []) as $field) {
            if ((string)($field['source'] ?? 'aveva') !== 'aveva') {
                continue;
            }
            $names[] = (string)($field['sensor'] ?? '');
        }
    }
    return array_values(array_unique(array_filter($names)));
}

function votatorsMysqlIdentifier(string $identifier): string
{
    if ($identifier === '' || preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
        throw new InvalidArgumentException('Identificador MySQL no válido.');
    }
    return '`' . $identifier . '`';
}

function votatorsReadLatestChurroViscosity(array $config): ?float
{
    $database = (array)($config['mysql_product'] ?? []);
    $host = trim((string)($database['host'] ?? ''));
    $name = trim((string)($database['database'] ?? ''));
    $user = trim((string)($database['user'] ?? ''));
    if ($host === '' || $name === '' || $user === '') {
        throw new RuntimeException('La conexión de viscosidad de churro no está configurada.');
    }
    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('La extensión pdo_mysql no está disponible.');
    }

    $table = votatorsMysqlIdentifier((string)($database['table'] ?? 'datos_producto'));
    $field = votatorsMysqlIdentifier((string)($database['field'] ?? 'churro_viscosidad'));
    $order = [];
    foreach ((array)($database['order_columns'] ?? ['id_datos_hora', 'id']) as $column) {
        $order[] = votatorsMysqlIdentifier((string)$column) . ' DESC';
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $host,
        max(1, (int)($database['port'] ?? 3306)),
        $name
    );
    $pdo = new PDO($dsn, $user, (string)($database['password'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 3,
    ]);
    $sql = 'SELECT ' . $field . ' AS `value` FROM ' . $table
        . ' WHERE ' . $field . ' IS NOT NULL AND ' . $field . ' <> 0'
        . ' ORDER BY ' . implode(', ', $order) . ' LIMIT 1';
    $row = $pdo->query($sql)->fetch();

    return is_numeric($row['value'] ?? null) ? (float)$row['value'] : null;
}

function votatorsReadLatest(array $config): array
{
    $database = (array)($config['aveva'] ?? []);
    $server = trim((string)($database['server'] ?? ''));
    $name = trim((string)($database['database'] ?? ''));
    $user = trim((string)($database['user'] ?? ''));

    if ($server === '' || $name === '' || $user === '') {
        throw new RuntimeException('La conexión AVEVA no está configurada.');
    }
    if (!extension_loaded('pdo_sqlsrv')) {
        throw new RuntimeException('La extensión pdo_sqlsrv no está disponible.');
    }

    $table = votatorsQuoteIdentifier((string)($database['table'] ?? 'TREND001'));
    $timestamp = votatorsQuoteIdentifier((string)($database['timestamp_field'] ?? 'Time_Stamp'));
    $columns = [$timestamp . ' AS [__timestamp]'];
    foreach (votatorsSensorNames($config) as $sensor) {
        $columns[] = votatorsQuoteIdentifier($sensor);
    }

    $dsn = sprintf(
        'sqlsrv:Server=%s,%d;Database=%s;Encrypt=%s;TrustServerCertificate=%s;LoginTimeout=%d',
        $server,
        max(1, (int)($database['port'] ?? 1433)),
        $name,
        !empty($database['encrypt']) ? 'yes' : 'no',
        !empty($database['trust_server_certificate']) ? 'yes' : 'no',
        max(1, (int)($database['login_timeout'] ?? 5))
    );

    $pdo = new PDO($dsn, $user, (string)($database['password'] ?? ''), [
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

function votatorsBuildReport(array $config, array $row, bool $available, string $warning = ''): array
{
    $timestamp = votatorsTimestamp($row['__timestamp'] ?? null);
    $equipment = [];

    foreach ((array)($config['equipment'] ?? []) as $equipmentKey => $equipmentConfig) {
        $primary = [];
        foreach ((array)($equipmentConfig['primary'] ?? []) as $fieldKey => $fieldConfig) {
            $primary[] = votatorsField((string)$fieldKey, (array)$fieldConfig, $row);
        }

        $priority = ['gris' => 0, 'verde' => 1, 'amarillo' => 2, 'rojo' => 3];
        $equipmentStatus = 'gris';
        foreach ($primary as $field) {
            $fieldStatus = (string)($field['statusKey'] ?? 'gris');
            if (($priority[$fieldStatus] ?? 0) > ($priority[$equipmentStatus] ?? 0)) {
                $equipmentStatus = $fieldStatus;
            }
        }

        $equipment[] = [
            'key' => (string)$equipmentKey,
            'label' => (string)($equipmentConfig['label'] ?? $equipmentKey),
            'statusKey' => $equipmentStatus,
            'statusLabel' => votatorsStatusLabel($equipmentStatus),
            'ultimaLectura' => $timestamp,
            'principales' => $primary,
        ];
    }

    $feed = votatorsField('feed_temperature', (array)($config['feed_temperature'] ?? []), $row);
    $feed['fecha'] = $timestamp;

    return [
        'ok' => $available,
        'titulo' => (string)($config['title'] ?? 'Votators'),
        'subtitulo' => (string)($config['subtitle'] ?? 'Monitoreo en tiempo real'),
        'warning' => $warning,
        'temperatura_warning' => '',
        'temperatura_alimentacion' => $feed,
        'equipos' => $equipment,
        'ultima_lectura' => $timestamp,
        'intervalo_actualizacion_ms' => max(1000, (int)($config['refresh_ms'] ?? 60000)),
        'intervalo_acciones_ms' => max(10000, (int)($config['corrective_rotation_ms'] ?? 120000)),
    ];
}

$row = [];
$available = true;
$warnings = [];
try {
    $row = votatorsReadLatest($config);
} catch (Throwable $exception) {
    $available = false;
    $warnings[] = 'La fuente AVEVA no está disponible temporalmente.';
    error_log('[reportes-progel:votators] ' . $exception->getMessage());
}

try {
    $row['__visc_churro'] = votatorsReadLatestChurroViscosity($config);
} catch (Throwable $exception) {
    $row['__visc_churro'] = null;
    $warnings[] = 'La viscosidad de churro no está disponible temporalmente.';
    error_log('[reportes-progel:votators:visc-churro] ' . $exception->getMessage());
}

$report = votatorsBuildReport($config, $row, $available, implode(' ', $warnings));

$report['corrective_actions'] = [];
$report['corrective_warning'] = '';
try {
    $report['corrective_actions'] = correctiveActionsLoad((array)($config['corrective_actions'] ?? []));
} catch (Throwable $exception) {
    $report['corrective_warning'] = 'Las acciones correctivas no están disponibles temporalmente.';
    error_log('[reportes-progel:votators:acciones] ' . $exception->getMessage());
}

return $report;
