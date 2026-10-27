<?php

declare(strict_types=1);

$envBool = static function (string $name, bool $default): bool {
    $value = getenv($name);
    if ($value === false || trim($value) === '') {
        return $default;
    }
    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
};

$flowRule = [
    'bands' => [
        ['max' => 10, 'status' => 'rojo'],
        ['min' => 10.5, 'max' => 11.499999, 'status' => 'amarillo'],
        ['min' => 11.5, 'max' => 12.5, 'status' => 'verde'],
        ['min' => 12.500001, 'max' => 12.999999, 'status' => 'amarillo'],
        ['min' => 13, 'status' => 'rojo'],
    ],
];
$pressureRule = [
    'bands' => [
        ['min' => 24, 'max' => 26, 'status' => 'verde'],
        ['min' => 23, 'max' => 23.999999, 'status' => 'amarillo'],
        ['min' => 26.000001, 'max' => 27, 'status' => 'amarillo'],
        ['max' => 22.999999, 'status' => 'rojo'],
        ['min' => 27.000001, 'status' => 'rojo'],
    ],
];
$noseTemperatureRule = [
    'bands' => [
        ['max' => 11.999999, 'status' => 'rojo'],
        ['min' => 12, 'max' => 13.999999, 'status' => 'amarillo'],
        ['min' => 14, 'max' => 19, 'status' => 'verde'],
        ['min' => 19.000001, 'max' => 23, 'status' => 'amarillo'],
        ['min' => 23.000001, 'status' => 'rojo'],
    ],
];
$solidsRule = [
    'bands' => [
        ['max' => 34.999999, 'status' => 'rojo'],
        ['min' => 35, 'max' => 37, 'status' => 'amarillo'],
        ['min' => 37.000001, 'status' => 'verde'],
    ],
];
$feedTemperatureRule = [
    'bands' => [
        ['max' => 42.999999, 'status' => 'rojo'],
        ['min' => 43, 'max' => 48, 'status' => 'verde'],
        ['min' => 48.000001, 'max' => 50, 'status' => 'amarillo'],
        ['min' => 50.000001, 'status' => 'rojo'],
    ],
];

$primary = static function (string $flow, string $pressure, string $noseTemperature) use ($flowRule, $pressureRule, $noseTemperatureRule, $solidsRule): array {
    return [
        'flujo' => ['label' => 'Flujo', 'sensor' => $flow, 'unit' => 'LPM', 'rule' => $flowRule],
        'presion_cuajado' => ['label' => 'Presión cuajado', 'sensor' => $pressure, 'unit' => 'kg/cm²', 'rule' => $pressureRule],
        'temperatura_nariz' => ['label' => 'Temp. nariz', 'sensor' => $noseTemperature, 'unit' => '°C', 'rule' => $noseTemperatureRule],
        'solidos' => ['label' => 'Sólidos', 'sensor' => 'SOLIDOS_DE_VOTATORS', 'unit' => '%', 'rule' => $solidsRule],
    ];
};
$secondary = static function (string $amperage, ?string $downtime = null): array {
    $fields = [
        'amperaje_reductor' => ['label' => 'Amp. reductor', 'sensor' => $amperage, 'unit' => 'A'],
    ];
    if ($downtime !== null) {
        $fields['tiempo_fuera'] = ['label' => 'Tiempo fuera', 'sensor' => $downtime, 'unit' => ''];
    } else {
        $fields['tiempo_fuera'] = ['label' => 'Tiempo fuera', 'sensor' => '', 'unit' => ''];
    }
    return $fields;
};

return [
    'title' => 'Votators',
    'subtitle' => 'Monitoreo en tiempo real',
    'refresh_ms' => max(1000, (int)(getenv('VOTATORS_REFRESH_MS') ?: 60000)),
    'aveva' => [
        'server' => (string)(getenv('AVEVA_DB_HOST') ?: ''),
        'port' => (int)(getenv('AVEVA_DB_PORT') ?: 1433),
        'database' => (string)(getenv('AVEVA_DB_NAME') ?: ''),
        'user' => (string)(getenv('AVEVA_DB_USER') ?: ''),
        'password' => (string)(getenv('AVEVA_DB_PASS') ?: ''),
        'encrypt' => $envBool('AVEVA_DB_ENCRYPT', false),
        'trust_server_certificate' => $envBool('AVEVA_DB_TRUST_SERVER_CERTIFICATE', true),
        'login_timeout' => max(1, (int)(getenv('AVEVA_DB_LOGIN_TIMEOUT') ?: 5)),
        'table' => (string)(getenv('AVEVA_DB_TABLE') ?: 'TREND001'),
        'timestamp_field' => (string)(getenv('AVEVA_DB_TIMESTAMP_FIELD') ?: 'Time_Stamp'),
    ],
    'feed_temperature' => [
        'label' => 'TEMP. ENTRADA TANQUE ALIMENTACIÓN V3 Y V4',
        'sensor' => 'TEMPERATURA_ENTRADA_TANQUE_ALIMENTACION_VOTATORS_3_Y_4',
        'unit' => '°C',
        'rule' => $feedTemperatureRule,
    ],
    'equipment' => [
        'votator_1' => [
            'label' => 'Votator 1',
            'primary' => $primary('FLUJO_VOTATOR_1_SA', 'PRESION_CUAJADO_VOTATOR_1', 'TEMPERATURA_NARIZ_VOTATOR_1'),
            'secondary' => $secondary('CORRIENTE_DE_EXTRUSOR_V1_SA', 'TIEMPO_FUERA_VOTATOR_1'),
        ],
        'votator_2' => [
            'label' => 'Votator 2',
            'primary' => $primary('FLUJO_VOTATOR_2_SA', 'PRESION_CUAJADO_VOTATOR_2_SA', 'TEMPERATURA_NARIZ_VOTATOR_2'),
            'secondary' => $secondary('CORRIENTE_DE_EXTRUSOR', 'TIEMPO_FUERA_VOTATOR_2'),
        ],
        'votator_3' => [
            'label' => 'Votator 3',
            'primary' => $primary('FLUJO_VOTATOR_1', 'PRESION__CUAJADO_VOTATOR_3', 'TEMPERATURA_NARIZ_VOTATOR_3'),
            'secondary' => $secondary('CORRIENTE_VOTATOR_3'),
        ],
        'votator_4' => [
            'label' => 'Votator 4',
            'primary' => $primary('FLUJO_VOTATOR_2', 'PRESION__CUAJADO_VOTATOR_4', 'TEMPERATURA_NARIZ_VOTATOR_4'),
            'secondary' => $secondary('CORRIENTE_VOTATOR_4'),
        ],
    ],
];

