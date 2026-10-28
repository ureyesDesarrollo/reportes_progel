<?php

declare(strict_types=1);

/*
 * Flujo directo del reporte:
 * AVEVA y MySQL -> evaluación de rangos -> arreglo para la vista y live.php.
 */

$config = $config ?? require __DIR__ . '/config.php';
require_once dirname(__DIR__, 2) . '/shared/corrective_actions.php';

function secadoresQuoteSqlServer(string $identifier): string
{
    if ($identifier === '' || preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
        throw new InvalidArgumentException('Identificador SQL Server no válido.');
    }
    return '[' . $identifier . ']';
}

function secadoresQuoteMysql(string $identifier): string
{
    if ($identifier === '' || preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
        throw new InvalidArgumentException('Identificador MySQL no válido.');
    }
    return '`' . $identifier . '`';
}

function secadoresStatus(?float $value, array $bands): string
{
    if ($value === null || $bands === []) {
        return 'gris';
    }
    foreach ($bands as $band) {
        $min = isset($band['min']) ? (float)$band['min'] : null;
        $max = isset($band['max']) ? (float)$band['max'] : null;
        if (($min === null || $value >= $min) && ($max === null || $value <= $max)) {
            return in_array($band['status'] ?? '', ['verde', 'amarillo', 'rojo'], true)
                ? (string)$band['status']
                : 'gris';
        }
    }
    return 'rojo';
}

function secadoresAdjustmentToGreen(?float $value, array $bands, int $decimals = 1): array
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

    $decimals = max(0, $decimals);
    $scale = 10 ** $decimals;
    $minimumStep = 1 / $scale;
    $required = ceil(max(0.0, $distance - 0.000000001) * $scale) / $scale;
    return [
        'direction' => $direction,
        'formatted' => number_format(max($minimumStep, $required), $decimals, '.', ','),
    ];
}

function secadoresField(string $id, string $label, $rawValue, string $unit, array $bands, float $factor = 1.0, bool $showAdjustment = true, int $decimals = 1): array
{
    $decimals = max(0, $decimals);
    $value = is_numeric($rawValue) ? (float)$rawValue * $factor : null;
    $outOfOperation = $value !== null && $value < 1;
    $adjustment = $outOfOperation || !$showAdjustment
        ? ['direction' => '', 'formatted' => '']
        : secadoresAdjustmentToGreen($value, $bands, $decimals);
    return [
        'id' => $id,
        'label' => $label,
        'value' => $value,
        'formatted' => $value === null ? '—' : ($outOfOperation ? 'F.O' : number_format($value, $decimals, '.', ',')),
        'unit' => $unit,
        'statusKey' => $outOfOperation ? 'gris' : secadoresStatus($value, $bands),
        'outOfOperation' => $outOfOperation,
        'bands' => $bands,
        'direction' => $adjustment['direction'],
        'adjustment' => $adjustment['formatted'],
    ];
}

function secadoresTimestamp($value): string
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

function secadoresFreshnessStatus($value): ?string
{
    try {
        if ($value instanceof DateTimeInterface) {
            $capturedAt = DateTimeImmutable::createFromInterface($value);
        } else {
            $raw = trim((string)$value);
            if ($raw === '') {
                return null;
            }
            $capturedAt = new DateTimeImmutable($raw);
        }
        $ageSeconds = (new DateTimeImmutable('now'))->getTimestamp() - $capturedAt->getTimestamp();
        if ($ageSeconds > 172800) {
            return 'rojo';
        }
        if ($ageSeconds > 86400) {
            return 'amarillo';
        }
    } catch (Throwable $exception) {
        return null;
    }

    return null;
}

function secadoresRelativeAge($value): string
{
    try {
        if ($value instanceof DateTimeInterface) {
            $capturedAt = DateTimeImmutable::createFromInterface($value);
        } else {
            $raw = trim((string)$value);
            if ($raw === '') {
                return 'Sin fecha';
            }
            $capturedAt = new DateTimeImmutable($raw);
        }

        $ageSeconds = max(0, (new DateTimeImmutable('now'))->getTimestamp() - $capturedAt->getTimestamp());
        $days = (int)floor($ageSeconds / 86400);
        if ($days < 1) {
            return 'Hace menos de 1 día';
        }
        return $days === 1 ? 'Hace 1 día' : 'Hace ' . $days . ' días';
    } catch (Throwable $exception) {
        return 'Sin fecha';
    }
}

function secadoresMostCriticalStatus(string $current, ?string $freshness): string
{
    if ($freshness === null || $current === 'gris') {
        return $current;
    }

    $severity = ['verde' => 1, 'amarillo' => 2, 'rojo' => 3];
    return ($severity[$freshness] ?? 0) > ($severity[$current] ?? 0) ? $freshness : $current;
}

function secadoresSensorNames(array $config): array
{
    $names = [];
    foreach ((array)($config['tunnels'] ?? []) as $tunnel) {
        foreach ((array)($tunnel['utilities'] ?? []) as $field) {
            if (($field['source'] ?? '') === 'aveva') {
                $names[] = (string)($field['sensor'] ?? '');
            }
        }
        foreach ((array)($tunnel['rooms'] ?? []) as $room) {
            $names[] = (string)($room['temperature'] ?? '');
            $names[] = (string)($room['humidity'] ?? '');
        }
    }
    return array_values(array_unique(array_filter($names)));
}

function secadoresReadAveva(array $config): array
{
    $database = (array)($config['aveva'] ?? []);
    $server = trim((string)($database['server'] ?? ''));
    $name = trim((string)($database['database'] ?? ''));
    $user = trim((string)($database['user'] ?? ''));
    if ($server === '' || $name === '' || $user === '') {
        throw new RuntimeException('AVEVA no está configurado.');
    }
    if (!extension_loaded('pdo_sqlsrv')) {
        throw new RuntimeException('La extensión pdo_sqlsrv no está disponible.');
    }

    $timestamp = secadoresQuoteSqlServer((string)($database['timestamp_field'] ?? 'Time_Stamp'));
    $columns = [$timestamp . ' AS [__timestamp]'];
    foreach (secadoresSensorNames($config) as $sensor) {
        $columns[] = secadoresQuoteSqlServer($sensor);
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
        . ' FROM ' . secadoresQuoteSqlServer((string)($database['table'] ?? 'TREND001'))
        . ' WHERE CAST(' . $timestamp . ' AS date) = CAST(GETDATE() AS date)'
        . ' ORDER BY ' . $timestamp . ' DESC';
    $row = $pdo->query($sql)->fetch();
    return is_array($row) ? $row : [];
}

function secadoresMysql(array $database): PDO
{
    $host = trim((string)($database['host'] ?? ''));
    $name = trim((string)($database['database'] ?? ''));
    $user = trim((string)($database['user'] ?? ''));
    if ($host === '' || $name === '' || $user === '') {
        throw new RuntimeException('MySQL no está configurado.');
    }
    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('La extensión pdo_mysql no está disponible.');
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, (int)($database['port'] ?? 3306), $name);
    return new PDO($dsn, $user, (string)($database['password'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 3,
    ]);
}

function secadoresReadMysql(array $config, array &$warnings): array
{
    $values = ['caudal' => [], 'caudal_fecha' => [], 'humedad_churro' => []];

    try {
        $pdo = secadoresMysql((array)($config['mysql_procesos'] ?? []));
        $sql = 'SELECT `hum_ultima`, `estado_fo` FROM `calidad_humedad_secadores` WHERE `secador` = :secador ORDER BY `creado_en` DESC, `id` DESC LIMIT 1';
        $stmt = $pdo->prepare($sql);
        foreach (['tunel_1' => '1', 'tunel_2' => '2'] as $tunnelKey => $dryer) {
            $stmt->execute(['secador' => $dryer]);
            $humidity = $stmt->fetch() ?: [];
            $outOfOperation = in_array(strtolower(trim((string)($humidity['estado_fo'] ?? '0'))), ['1', 'true', 'si', 'sí', 'yes'], true);
            $values['humedad_churro'][$tunnelKey] = $outOfOperation ? null : ($humidity['hum_ultima'] ?? null);
        }
    } catch (Throwable $exception) {
        $warnings[] = 'No fue posible consultar los datos de proceso.';
        error_log('[reportes-progel:secadores:procesos] ' . $exception->getMessage());
    }

    try {
        $pdo = secadoresMysql((array)($config['mysql_secadores'] ?? []));
        $sql = 'SELECT `caudal`, `fecha_hora` FROM `secadores_caudal_registros` WHERE `tunel_key` = :tunel ORDER BY `fecha_hora` DESC LIMIT 1';
        $stmt = $pdo->prepare($sql);
        foreach (['tunel_1', 'tunel_2'] as $tunnelKey) {
            $stmt->execute(['tunel' => $tunnelKey]);
            $row = $stmt->fetch() ?: [];
            $values['caudal'][$tunnelKey] = $row['caudal'] ?? null;
            $values['caudal_fecha'][$tunnelKey] = $row['fecha_hora'] ?? null;
        }
    } catch (Throwable $exception) {
        $warnings[] = 'No fue posible consultar los caudales.';
        error_log('[reportes-progel:secadores:caudal] ' . $exception->getMessage());
    }

    return $values;
}

$warnings = [];
$correctiveActions = [];
$correctiveWarning = '';
$avevaRow = [];
try {
    $avevaRow = secadoresReadAveva($config);
} catch (Throwable $exception) {
    $warnings[] = 'La fuente AVEVA no está disponible temporalmente.';
    error_log('[reportes-progel:secadores:aveva] ' . $exception->getMessage());
}
$mysqlValues = secadoresReadMysql($config, $warnings);
try {
    $correctiveActions = correctiveActionsLoad((array)($config['corrective_actions'] ?? []));
} catch (Throwable $exception) {
    $correctiveWarning = 'Las acciones correctivas no están disponibles temporalmente.';
    error_log('[reportes-progel:secadores:acciones] ' . $exception->getMessage());
}

$tunnels = [];
foreach ((array)($config['tunnels'] ?? []) as $tunnelKey => $tunnelConfig) {
    $utilities = [];
    foreach ((array)($tunnelConfig['utilities'] ?? []) as $fieldKey => $fieldConfig) {
        $source = (string)($fieldConfig['source'] ?? 'aveva');
        if ($source === 'caudal') {
            $rawValue = $mysqlValues['caudal'][$tunnelKey] ?? null;
        } elseif ($source === 'humedad_churro') {
            $rawValue = $mysqlValues['humedad_churro'][$tunnelKey] ?? null;
        } else {
            $rawValue = $avevaRow[$fieldConfig['sensor']] ?? null;
        }
        $utilities[$fieldKey] = secadoresField(
            $tunnelKey . '.utility.' . $fieldKey,
            (string)$fieldConfig['label'],
            $rawValue,
            (string)$fieldConfig['unit'],
            (array)$fieldConfig['rule'],
            (float)($fieldConfig['factor'] ?? 1),
            (bool)($fieldConfig['show_adjustment'] ?? true)
        );
        if ($source === 'caudal') {
            $capturedAt = $mysqlValues['caudal_fecha'][$tunnelKey] ?? null;
            $utilities[$fieldKey]['timestamp'] = secadoresTimestamp($capturedAt);
            $utilities[$fieldKey]['ageLabel'] = secadoresRelativeAge($capturedAt);
            $utilities[$fieldKey]['statusKey'] = secadoresMostCriticalStatus(
                (string)$utilities[$fieldKey]['statusKey'],
                secadoresFreshnessStatus($capturedAt)
            );
        }
    }

    $rooms = [];
    foreach ((array)($tunnelConfig['rooms'] ?? []) as $roomNumber => $roomConfig) {
        $temperature = null;
        if (!empty($roomConfig['temperature'])) {
            $temperature = secadoresField(
                $tunnelKey . '.room.' . $roomNumber . '.temperature',
                'Temp.',
                $avevaRow[$roomConfig['temperature']] ?? null,
                '°C',
                (array)$roomConfig['temperature_rule'],
                1.0,
                true,
                0
            );
        }

        $humidity = null;
        if (!empty($roomConfig['humidity'])) {
            $humidity = secadoresField(
                $tunnelKey . '.room.' . $roomNumber . '.humidity',
                'Hum.',
                $avevaRow[$roomConfig['humidity']] ?? null,
                '%',
                [],
                1.0,
                false,
                0
            );
        }

        $rooms[$roomNumber] = ['label' => 'REC ' . $roomNumber, 'temperature' => $temperature, 'humidity' => $humidity];
    }

    $tunnels[$tunnelKey] = [
        'key' => $tunnelKey,
        'label' => (string)$tunnelConfig['label'],
        'utilities' => $utilities,
        'rooms' => $rooms,
    ];
}

return [
    'ok' => $avevaRow !== [],
    'title' => (string)($config['title'] ?? 'Secadores'),
    'timestamp' => secadoresTimestamp($avevaRow['__timestamp'] ?? null),
    'refresh_ms' => max(10000, (int)($config['refresh_ms'] ?? 60000)),
    'rotation_ms' => max(10000, (int)($config['rotation_ms'] ?? 60000)),
    'warnings' => array_values(array_unique($warnings)),
    'tunnels' => $tunnels,
    'corrective_actions' => $correctiveActions,
    'corrective_warning' => $correctiveWarning,
];
