<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/environment.php';

/* Conexiones, campos y semáforos del Clarificador. */

$metrics = [
    'solidos' => [
        'label' => 'Sólidos', 'source' => 'mysql', 'field' => 'solidos', 'unit' => '%', 'decimals' => 0,
        'rule' => [
            ['max' => 2.299999, 'status' => 'rojo'],
            ['min' => 2.3, 'max' => 2.499999, 'status' => 'amarillo'],
            ['min' => 2.5, 'max' => 3.5, 'status' => 'verde'],
            ['min' => 3.500001, 'max' => 3.8, 'status' => 'amarillo'],
            ['min' => 3.800001, 'status' => 'rojo'],
        ],
    ],
    'temperatura' => [
        'label' => 'Temperatura', 'source' => 'mysql', 'field' => 'temperatura', 'unit' => '°C', 'decimals' => 0,
        'rule' => [
            ['max' => 53.999999, 'status' => 'rojo'],
            ['min' => 54, 'max' => 55.999999, 'status' => 'amarillo'],
            ['min' => 56, 'max' => 63, 'status' => 'verde'],
            ['min' => 63.000001, 'max' => 68, 'status' => 'amarillo'],
            ['min' => 68.000001, 'status' => 'rojo'],
        ],
    ],
    'flujo' => [
        'label' => 'Flujo', 'source' => 'aveva', 'field' => 'FLUJO_TANQUE_DE_BALANCE', 'unit' => '', 'decimals' => 0,
        'rule' => [
            ['max' => 749.999999, 'status' => 'rojo'],
            ['min' => 750, 'max' => 899.999999, 'status' => 'amarillo'],
            ['min' => 900, 'max' => 1300, 'status' => 'verde'],
            ['min' => 1300.000001, 'status' => 'rojo'],
        ],
    ],
    'flujo_polimero' => [
        'label' => 'Flujo polímero', 'source' => 'aveva', 'field' => 'FLUJO_POLIMERO_CLARIFICADOR', 'unit' => 'LPM', 'decimals' => 0,
        'rule' => [
            ['max' => 9.999999, 'status' => 'rojo'],
            ['min' => 10, 'max' => 10.999999, 'status' => 'amarillo'],
            ['min' => 11, 'max' => 13, 'status' => 'verde'],
            ['min' => 13.000001, 'status' => 'rojo'],
        ],
    ],
    'ntu_entrada' => [
        'label' => 'NTU entrada', 'source' => 'mysql', 'field' => 'ntu_entrada', 'unit' => '', 'decimals' => 0,
        'rule' => [
            ['max' => 59.999999, 'status' => 'rojo'],
            ['min' => 60, 'max' => 600, 'status' => 'verde'],
            ['min' => 600.000001, 'max' => 850, 'status' => 'amarillo'],
            ['min' => 850.000001, 'status' => 'rojo'],
        ],
    ],
    'ntu_salida' => [
        'label' => 'NTU salida', 'source' => 'mysql', 'field' => 'ntu_salida', 'unit' => '', 'decimals' => 0,
        'rule' => [
            ['max' => 3.999999, 'status' => 'rojo'],
            ['min' => 4, 'max' => 4.999999, 'status' => 'amarillo'],
            ['min' => 5, 'max' => 9.999999, 'status' => 'verde'],
            ['min' => 10, 'max' => 12, 'status' => 'amarillo'],
            ['min' => 12.000001, 'status' => 'rojo'],
        ],
    ],
    'ph_entrada' => [
        'label' => 'pH entrada', 'source' => 'mysql', 'field' => 'ph_entrada', 'unit' => '', 'decimals' => 0,
        'rule' => [
            ['max' => 2.799999, 'status' => 'rojo'],
            ['min' => 2.8, 'max' => 2.999999, 'status' => 'amarillo'],
            ['min' => 3, 'max' => 3.8, 'status' => 'verde'],
            ['min' => 3.800001, 'max' => 4, 'status' => 'amarillo'],
            ['min' => 4.000001, 'status' => 'rojo'],
        ],
    ],
    'ph_electrodo' => [
        'label' => 'pH electrodo', 'source' => 'mysql', 'field' => 'ph_electrodo', 'unit' => '', 'decimals' => 0,
        'rule' => [
            ['max' => 4.899999, 'status' => 'rojo'],
            ['min' => 4.9, 'max' => 5.499999, 'status' => 'amarillo'],
            ['min' => 5.5, 'max' => 6.5, 'status' => 'verde'],
            ['min' => 6.500001, 'max' => 7, 'status' => 'amarillo'],
            ['min' => 7.000001, 'status' => 'rojo'],
        ],
    ],
    'ph_salida' => [
        'label' => 'pH salida', 'source' => 'aveva', 'field' => 'PH_REAL', 'unit' => '', 'decimals' => 0,
        'rule' => [
            ['max' => 4.899999, 'status' => 'rojo'],
            ['min' => 4.9, 'max' => 5.499999, 'status' => 'amarillo'],
            ['min' => 5.5, 'max' => 6.5, 'status' => 'verde'],
            ['min' => 6.500001, 'max' => 7, 'status' => 'amarillo'],
            ['min' => 7.000001, 'status' => 'rojo'],
        ],
    ],
    'ce_salida' => [
        'label' => 'CE salida', 'source' => 'mysql', 'field' => 'ce_salida', 'unit' => '', 'decimals' => 0,
        'rule' => [
            ['max' => 6.499999, 'status' => 'verde'],
            ['min' => 6.5, 'max' => 7, 'status' => 'amarillo'],
            ['min' => 7.000001, 'status' => 'rojo'],
        ],
    ],
    'tanque_balance' => [
        'label' => 'Nivel Tanque balance', 'source' => 'aveva', 'field' => 'NIVEL_TANQUE_DE_BALANCE', 'unit' => '', 'decimals' => 0,
        'rule' => [
            ['max' => 29.999999, 'status' => 'rojo'],
            ['min' => 30, 'max' => 49.999999, 'status' => 'amarillo'],
            ['min' => 50, 'max' => 80, 'status' => 'verde'],
            ['min' => 80.000001, 'max' => 90, 'status' => 'amarillo'],
            ['min' => 90.000001, 'status' => 'rojo'],
        ],
    ],
    'viscosidad_integracion' => [
        'label' => 'Viscosidad integración', 'source' => 'integration', 'field' => 'integ_viscosidad', 'unit' => '', 'decimals' => 1,
        'rule' => [
            ['max' => 25, 'status' => 'rojo'],
            ['min' => 25.000001, 'status' => 'verde'],
        ],
    ],
    'flujo_integracion' => [
        'label' => 'Flujo integración', 'source' => 'integration', 'field' => 'integ_flujo', 'unit' => '', 'decimals' => 1,
        'rule' => [
            ['max' => 1.499999, 'status' => 'rojo'],
            ['min' => 1.5, 'max' => 1.999999, 'status' => 'amarillo'],
            ['min' => 2, 'max' => 5, 'status' => 'verde'],
            ['min' => 5.000001, 'max' => 5.5, 'status' => 'amarillo'],
            ['min' => 5.500001, 'status' => 'rojo'],
        ],
    ],
];

return [
    'title' => 'Clarificador',
    'subtitle' => 'Monitoreo en tiempo real',
    'refresh_ms' => max(1000, (int)(getenv('CLARIFICADORES_REFRESH_MS') ?: 60000)),
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
        'table' => 'datos_clarificador',
        'out_of_operation_column' => 'estado_fo',
    ],
    'integration_mysql' => [
        'host' => (string)(getenv('PROCESOS_DB_HOST') ?: ''),
        'port' => (int)(getenv('PROCESOS_DB_PORT') ?: 3306),
        'database' => (string)(getenv('PROCESOS_DB_NAME') ?: 'progel_procesos'),
        'user' => (string)(getenv('PROCESOS_DB_USER') ?: ''),
        'password' => (string)(getenv('PROCESOS_DB_PASS') ?: ''),
        'table' => 'datos_filtracion_integracion',
        'out_of_operation_column' => 'estado_fo_integ',
    ],
    'metrics' => $metrics,
];
