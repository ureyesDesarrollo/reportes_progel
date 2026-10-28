<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/environment.php';

/*
 * Configuración del reporte de Cocedores.
 * Aquí se concentran conexiones, sensores, campos y rangos operativos.
 */

$flowSmallRule = [
    ['max' => 129.999999, 'status' => 'rojo'],
    ['min' => 130, 'max' => 139.999999, 'status' => 'amarillo'],
    ['min' => 140, 'max' => 155, 'status' => 'verde'],
    ['min' => 155.000001, 'max' => 165, 'status' => 'amarillo'],
    ['min' => 165.000001, 'status' => 'rojo'],
];
$flowLargeRule = [
    ['max' => 149.999999, 'status' => 'rojo'],
    ['min' => 150, 'max' => 159.999999, 'status' => 'amarillo'],
    ['min' => 160, 'max' => 175, 'status' => 'verde'],
    ['min' => 175.000001, 'max' => 185, 'status' => 'amarillo'],
    ['min' => 185.000001, 'status' => 'rojo'],
];
$inputTemperatureRule = [
    ['max' => 58.999999, 'status' => 'rojo'],
    ['min' => 59, 'max' => 59.999999, 'status' => 'amarillo'],
    ['min' => 60, 'max' => 68, 'status' => 'verde'],
    ['min' => 68.000001, 'max' => 70, 'status' => 'amarillo'],
    ['min' => 70.000001, 'status' => 'rojo'],
];
$outputTemperatureRule = [
    ['max' => 51.999999, 'status' => 'rojo'],
    ['min' => 52, 'max' => 54.999999, 'status' => 'amarillo'],
    ['min' => 55, 'max' => 63, 'status' => 'verde'],
    ['min' => 63.000001, 'max' => 64, 'status' => 'amarillo'],
    ['min' => 64.000001, 'status' => 'rojo'],
];
$ntuRule = [
    ['max' => 59.999999, 'status' => 'rojo'],
    ['min' => 60, 'max' => 600, 'status' => 'verde'],
    ['min' => 600.000001, 'max' => 850, 'status' => 'amarillo'],
    ['min' => 850.000001, 'status' => 'rojo'],
];
$solidsRule = [
    ['max' => 2.299999, 'status' => 'rojo'],
    ['min' => 2.3, 'max' => 2.499999, 'status' => 'amarillo'],
    ['min' => 2.5, 'max' => 3.5, 'status' => 'verde'],
    ['min' => 3.500001, 'max' => 3.8, 'status' => 'amarillo'],
    ['min' => 3.800001, 'status' => 'rojo'],
];
$phRule = [
    ['max' => 2.799999, 'status' => 'rojo'],
    ['min' => 2.8, 'max' => 2.999999, 'status' => 'amarillo'],
    ['min' => 3, 'max' => 3.8, 'status' => 'verde'],
    ['min' => 3.800001, 'max' => 4, 'status' => 'amarillo'],
    ['min' => 4.000001, 'status' => 'rojo'],
];

$equipment = [];
$flowSensors = [
    1 => 'Flujo_cocedor_1',
    2 => 'Flujo_cocedor_2',
    3 => 'Flujo_cocedor_3',
    4 => 'Flujo_cocedor_4',
    5 => 'Flujo_cocedor_5',
    6 => 'Flujo_cocedor_6',
    7 => 'Flujo_cocedor_7',
    8 => 'FLUJO_COCEDOR_8',
    9 => 'FLUJO_COCEDOR_9',
];

foreach ($flowSensors as $number => $sensor) {
    $equipment['cocedor_' . $number] = [
        'number' => $number,
        'label' => 'Cocedor ' . $number,
        'flow' => [
            'label' => 'Flujo',
            'sensor' => $sensor,
            'unit' => 'LPM',
            'decimals' => 0,
            'rule' => $number >= 6 ? $flowLargeRule : $flowSmallRule,
        ],
    ];
}

return [
    'title' => 'Cocedores',
    'subtitle' => 'Monitoreo en tiempo real',
    'refresh_ms' => max(1000, (int)(getenv('COCEDORES_REFRESH_MS') ?: 60000)),
    'aveva' => [
        'server' => (string)(getenv('AVEVA_DB_HOST') ?: ''),
        'port' => (int)(getenv('AVEVA_DB_PORT') ?: 1433),
        'database' => (string)(getenv('AVEVA_DB_NAME') ?: ''),
        'user' => (string)(getenv('AVEVA_DB_USER') ?: ''),
        'password' => (string)(getenv('AVEVA_DB_PASS') ?: ''),
        'encrypt' => filter_var(getenv('AVEVA_DB_ENCRYPT') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'trust_server_certificate' => filter_var(getenv('AVEVA_DB_TRUST_SERVER_CERTIFICATE') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'login_timeout' => max(1, (int)(getenv('AVEVA_DB_LOGIN_TIMEOUT') ?: 5)),
        'table' => (string)(getenv('AVEVA_DB_TABLE') ?: 'TREND001'),
        'timestamp_field' => (string)(getenv('AVEVA_DB_TIMESTAMP_FIELD') ?: 'Time_Stamp'),
    ],
    'mysql' => [
        'host' => (string)(getenv('PROCESOS_DB_HOST') ?: ''),
        'port' => (int)(getenv('PROCESOS_DB_PORT') ?: 3306),
        'database' => (string)(getenv('PROCESOS_DB_NAME') ?: 'progel_procesos'),
        'user' => (string)(getenv('PROCESOS_DB_USER') ?: ''),
        'password' => (string)(getenv('PROCESOS_DB_PASS') ?: ''),
        'table' => 'datos_cocedores',
        'number_column' => 'numero_cocedor',
        'out_of_operation_column' => 'estado_fo',
    ],
    'header_metrics' => [
        'temperatura_entrada' => [
            'label' => 'Temperatura entrada',
            'sensor' => 'COCEDORES_TEMPERATURA_DE_ENTRADA',
            'unit' => '°C',
            'decimals' => 0,
            'rule' => $inputTemperatureRule,
        ],
        'temperatura_salida' => [
            'label' => 'Temperatura salida',
            'sensor' => 'COCEDORES_TEMPERATURA_DE_SALIDA',
            'unit' => '°C',
            'decimals' => 0,
            'rule' => $outputTemperatureRule,
        ],
    ],
    'mysql_metrics' => [
        'ntu' => ['label' => 'NTU', 'column' => 'ntu', 'unit' => '', 'decimals' => 0, 'rule' => $ntuRule],
        'solidos' => ['label' => 'Sólidos', 'column' => 'solidos', 'unit' => '%', 'decimals' => 0, 'rule' => $solidsRule],
        'ph' => ['label' => 'pH', 'column' => 'ph', 'unit' => '', 'decimals' => 1, 'rule' => $phRule],
    ],
    'equipment' => $equipment,
];
