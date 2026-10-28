<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/environment.php';

/*
 * Este archivo concentra todo lo que puede cambiar sin tocar la consulta:
 * conexión, sensores, unidades y rangos de operación.
 */

$flowRule = [
    ['max' => 9.999999, 'status' => 'rojo'],
    ['min' => 10, 'max' => 12.499999, 'status' => 'amarillo'],
    ['min' => 12.5, 'status' => 'verde'],
];
$pressureRule = [
    ['max' => 19.999999, 'status' => 'rojo'],
    ['min' => 20, 'max' => 23.499999, 'status' => 'amarillo'],
    ['min' => 23.5, 'max' => 26.5, 'status' => 'verde'],
    ['min' => 26.500001, 'max' => 27.5, 'status' => 'amarillo'],
    ['min' => 27.500001, 'status' => 'rojo'],
];
$noseTemperatureRule = [
    ['max' => 16.999999, 'status' => 'rojo'],
    ['min' => 17, 'max' => 18.999999, 'status' => 'amarillo'],
    ['min' => 19, 'max' => 25, 'status' => 'verde'],
    ['min' => 25.000001, 'max' => 27, 'status' => 'amarillo'],
    ['min' => 27.000001, 'status' => 'rojo'],
];
$solidsRulesByViscosity = [
    [
        'secondary_min' => 43,
        'rule' => [
            ['max' => 31, 'status' => 'rojo'],
            ['min' => 31.000001, 'max' => 32, 'status' => 'amarillo'],
            ['min' => 32.000001, 'status' => 'verde'],
        ],
    ],
    [
        'secondary_min' => 39,
        'secondary_max' => 42.999999,
        'rule' => [
            ['max' => 33, 'status' => 'rojo'],
            ['min' => 33.000001, 'max' => 35, 'status' => 'amarillo'],
            ['min' => 35.000001, 'status' => 'verde'],
        ],
    ],
    [
        'secondary_max' => 38.999999,
        'rule' => [
            ['max' => 36, 'status' => 'rojo'],
            ['min' => 36.000001, 'max' => 37, 'status' => 'amarillo'],
            ['min' => 37.000001, 'status' => 'verde'],
        ],
    ],
];
$feedTemperatureRule = [
    ['max' => 42.999999, 'status' => 'rojo'],
    ['min' => 43, 'max' => 48, 'status' => 'verde'],
    ['min' => 48.000001, 'max' => 50, 'status' => 'amarillo'],
    ['min' => 50.000001, 'status' => 'rojo'],
];
$downtimeRule = [
    ['max' => 9.999999, 'status' => 'verde'],
    ['min' => 10, 'max' => 30, 'status' => 'amarillo'],
    ['min' => 30.000001, 'status' => 'rojo'],
];

return [
    'title' => 'Votators',
    'subtitle' => 'Monitoreo en tiempo real',
    'refresh_ms' => max(1000, (int)(getenv('VOTATORS_REFRESH_MS') ?: 60000)),
    'corrective_rotation_ms' => max(10000, (int)(getenv('VOTATORS_CORRECTIVE_ROTATION_MS') ?: 120000)),
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
    'mysql_product' => [
        'host' => (string)(getenv('PROCESOS_DB_HOST') ?: ''),
        'port' => (int)(getenv('PROCESOS_DB_PORT') ?: 3306),
        'database' => (string)(getenv('PROCESOS_DB_NAME') ?: 'progel_procesos'),
        'user' => (string)(getenv('PROCESOS_DB_USER') ?: ''),
        'password' => (string)(getenv('PROCESOS_DB_PASS') ?: ''),
        'table' => 'datos_producto',
        'field' => 'churro_viscosidad',
        'order_columns' => ['id_datos_hora', 'id'],
    ],
    'feed_temperature' => [
        'label' => 'TEMP. ENTRADA TANQUE ALIMENTACIÓN V3 Y V4',
        'sensor' => 'TEMPERATURA_ENTRADA_TANQUE_ALIMENTACION_VOTATORS_3_Y_4',
        'unit' => '°C',
        'rule' => $feedTemperatureRule,
    ],
    'corrective_actions' => [
        'database' => [
            'host' => (string)(getenv('PROCESOS_DB_HOST') ?: ''),
            'port' => (int)(getenv('PROCESOS_DB_PORT') ?: 3306),
            'database' => 'bd_extraccion',
            'user' => (string)(getenv('PROCESOS_DB_USER') ?: ''),
            'password' => (string)(getenv('PROCESOS_DB_PASS') ?: ''),
        ],
        'table' => 'datos_votators',
        'foreign_key' => 'votator_id',
        'limit' => 8,
    ],
    'equipment' => [
        'votator_1' => [
            'label' => 'Votator 1',
            'primary' => [
                'flujo' => ['label' => 'Flujo', 'sensor' => 'FLUJO_VOTATOR_1_SA', 'unit' => 'LPM', 'rule' => $flowRule],
                'presion_cuajado' => ['label' => 'Presión cuajado', 'sensor' => 'PRESION_CUAJADO_VOTATOR_1', 'unit' => 'kg/cm²', 'rule' => $pressureRule],
                'temperatura_nariz' => ['label' => 'Temp. nariz', 'sensor' => 'TEMPERATURA_NARIZ_VOTATOR_1', 'unit' => '°C', 'rule' => $noseTemperatureRule],
                'solidos' => ['label' => 'Sólidos', 'sensor' => 'SOLIDOS_DE_VOTATORS', 'unit' => '%', 'conditional_rule' => $solidsRulesByViscosity, 'secondary_label' => 'Visc. churro', 'secondary_sensor' => '__visc_churro'],
                'tiempo_fuera' => ['label' => 'Tiempo fuera', 'sensor' => 'TIEMPO_FUERA_VOTATOR_1', 'unit' => 'min', 'rule' => $downtimeRule],
            ],
        ],
        'votator_2' => [
            'label' => 'Votator 2',
            'primary' => [
                'flujo' => ['label' => 'Flujo', 'sensor' => 'FLUJO_VOTATOR_2_SA', 'unit' => 'LPM', 'rule' => $flowRule],
                'presion_cuajado' => ['label' => 'Presión cuajado', 'sensor' => 'PRESION_CUAJADO_VOTATOR_2_SA', 'unit' => 'kg/cm²', 'rule' => $pressureRule],
                'temperatura_nariz' => ['label' => 'Temp. nariz', 'sensor' => 'TEMPERATURA_NARIZ_VOTATOR_2', 'unit' => '°C', 'rule' => $noseTemperatureRule],
                'solidos' => ['label' => 'Sólidos', 'sensor' => 'SOLIDOS_DE_VOTATORS', 'unit' => '%', 'conditional_rule' => $solidsRulesByViscosity, 'secondary_label' => 'Visc. churro', 'secondary_sensor' => '__visc_churro'],
                'tiempo_fuera' => ['label' => 'Tiempo fuera', 'sensor' => 'TIEMPO_FUERA_VOTATOR_2', 'unit' => 'min', 'rule' => $downtimeRule],
            ],
        ],
        'votator_3' => [
            'label' => 'Votator 3',
            'primary' => [
                'flujo' => ['label' => 'Flujo', 'sensor' => 'FLUJO_VOTATOR_1', 'unit' => 'LPM', 'rule' => $flowRule],
                'presion_cuajado' => ['label' => 'Presión cuajado', 'sensor' => 'PRESION__CUAJADO_VOTATOR_3', 'unit' => 'kg/cm²', 'rule' => $pressureRule],
                'temperatura_nariz' => ['label' => 'Temp. nariz', 'sensor' => 'TEMPERATURA_NARIZ_VOTATOR_3', 'unit' => '°C', 'rule' => $noseTemperatureRule],
                'solidos' => ['label' => 'Sólidos', 'sensor' => 'SOLIDOS_DE_VOTATORS', 'unit' => '%', 'conditional_rule' => $solidsRulesByViscosity, 'secondary_label' => 'Visc. churro', 'secondary_sensor' => '__visc_churro'],
                'tiempo_fuera' => ['label' => 'Tiempo fuera', 'sensor' => 'TIEMPO_FUERA_VOTATOR_3', 'unit' => 'min', 'rule' => $downtimeRule],
            ],
        ],
        'votator_4' => [
            'label' => 'Votator 4',
            'primary' => [
                'flujo' => ['label' => 'Flujo', 'sensor' => 'FLUJO_VOTATOR_2', 'unit' => 'LPM', 'rule' => $flowRule],
                'presion_cuajado' => ['label' => 'Presión cuajado', 'sensor' => 'PRESION__CUAJADO_VOTATOR_4', 'unit' => 'kg/cm²', 'rule' => $pressureRule],
                'temperatura_nariz' => ['label' => 'Temp. nariz', 'sensor' => 'TEMPERATURA_NARIZ_VOTATOR_4', 'unit' => '°C', 'rule' => $noseTemperatureRule],
                'solidos' => ['label' => 'Sólidos', 'sensor' => 'SOLIDOS_DE_VOTATORS', 'unit' => '%', 'conditional_rule' => $solidsRulesByViscosity, 'secondary_label' => 'Visc. churro', 'secondary_sensor' => '__visc_churro'],
                'tiempo_fuera' => ['label' => 'Tiempo fuera', 'sensor' => 'TIEMPO_FUERA_VOTATOR_4', 'unit' => 'min', 'rule' => $downtimeRule],
            ],
        ],
    ],
];
