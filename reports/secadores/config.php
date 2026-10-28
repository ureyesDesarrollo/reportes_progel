<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/environment.php';

/*
 * Configuración completa del tablero de Secadores.
 * Para agregar o cambiar un sensor sólo debe modificarse este archivo.
 */

$rules = [
    'velocidad' => [
        ['min' => 14.5, 'max' => 15, 'status' => 'verde', 'label' => '14.5–15'],
        ['min' => 13, 'max' => 14.499999, 'status' => 'amarillo', 'label' => '13–<14.5'],
        ['max' => 12.999999, 'status' => 'rojo', 'label' => '<13'],
        ['min' => 15.000001, 'status' => 'rojo', 'label' => '>15'],
    ],
    'caudal_1' => [
        ['min' => 90000, 'status' => 'verde', 'label' => '≥90k'],
        ['min' => 89000, 'max' => 89999.999999, 'status' => 'amarillo', 'label' => '89–<90k'],
        ['max' => 88999.999999, 'status' => 'rojo', 'label' => '<89k'],
    ],
    'caudal_2' => [
        ['min' => 74000, 'max' => 82500, 'status' => 'verde', 'label' => '74–82.5k'],
        ['min' => 70000, 'max' => 73999.999999, 'status' => 'amarillo', 'label' => '70–<74k'],
        ['min' => 82500.000001, 'max' => 83999.999999, 'status' => 'amarillo', 'label' => '>82.5–84k'],
        ['max' => 69999.999999, 'status' => 'rojo', 'label' => '<70k'],
        ['min' => 84000, 'status' => 'rojo', 'label' => '≥84k'],
    ],
    'agua_suministro' => [
        ['min' => 90.000001, 'status' => 'verde', 'label' => '>90'],
        ['min' => 85, 'max' => 90, 'status' => 'amarillo', 'label' => '85–90'],
        ['max' => 84.999999, 'status' => 'rojo', 'label' => '<85'],
    ],
    'agua_retorno' => [
        ['min' => 80, 'status' => 'verde', 'label' => '≥80'],
        ['min' => 75, 'max' => 79.999999, 'status' => 'amarillo', 'label' => '75–<80'],
        ['max' => 74.999999, 'status' => 'rojo', 'label' => '<75'],
    ],
    'presion_vapor' => [
        ['min' => 3, 'max' => 3.2, 'status' => 'verde', 'label' => '3–3.2'],
        ['min' => 2.8, 'max' => 2.999999, 'status' => 'amarillo', 'label' => '2.8–<3'],
        ['max' => 2.799999, 'status' => 'rojo', 'label' => '<2.8'],
        ['min' => 3.200001, 'status' => 'rojo', 'label' => '>3.2'],
    ],
    'humedad_churro' => [
        ['min' => 12, 'status' => 'verde', 'label' => '≥12'],
        ['min' => 10, 'max' => 11.999999, 'status' => 'amarillo', 'label' => '10–<12'],
        ['max' => 9.999999, 'status' => 'rojo', 'label' => '<10'],
    ],
];

$temperatureTargets = [1 => 38, 2 => 42, 3 => 45, 4 => 48, 5 => 52, 6 => 56, 7 => 60, 8 => 62];
$temperatureTargetRule = static function (float $target): array {
    $yellowLow = $target - 1;
    $yellowHigh = $target + 1;

    return [
        ['min' => $target, 'max' => $target, 'status' => 'verde', 'label' => (string)$target],
        ['min' => $yellowLow, 'max' => $target - 0.000001, 'status' => 'amarillo', 'label' => $yellowLow . '–<' . $target],
        ['min' => $target + 0.000001, 'max' => $yellowHigh, 'status' => 'amarillo', 'label' => '>' . $target . '–' . $yellowHigh],
        ['max' => $yellowLow - 0.000001, 'status' => 'rojo', 'label' => '<' . $yellowLow],
        ['min' => $yellowHigh + 0.000001, 'status' => 'rojo', 'label' => '>' . $yellowHigh],
    ];
};
$temperatureRules = [];
foreach ($temperatureTargets as $room => $target) {
    $temperatureRules[$room] = $temperatureTargetRule((float)$target);
}

return [
    'title' => 'Secadores',
    'refresh_ms' => max(10000, (int)(getenv('SECADORES_REFRESH_MS') ?: 60000)),
    // Tiempo que permanece visible cada túnel antes de mostrar el siguiente.
    'rotation_ms' => max(10000, (int)(getenv('SECADORES_ROTATION_MS') ?: 60000)),
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
    'mysql_secadores' => ['host' => (string)(getenv('SECADORES_DB_HOST') ?: ''), 'port' => (int)(getenv('SECADORES_DB_PORT') ?: 3306), 'database' => (string)(getenv('SECADORES_DB_NAME') ?: 'bd_secadores'), 'user' => (string)(getenv('SECADORES_DB_USER') ?: ''), 'password' => (string)(getenv('SECADORES_DB_PASS') ?: '')],
    'mysql_procesos' => ['host' => (string)(getenv('PROCESOS_DB_HOST') ?: ''), 'port' => (int)(getenv('PROCESOS_DB_PORT') ?: 3306), 'database' => (string)(getenv('PROCESOS_DB_NAME') ?: 'progel_procesos'), 'user' => (string)(getenv('PROCESOS_DB_USER') ?: ''), 'password' => (string)(getenv('PROCESOS_DB_PASS') ?: '')],
    'corrective_actions' => [
        'database' => [
            'host' => (string)(getenv('PROCESOS_DB_HOST') ?: ''),
            'port' => (int)(getenv('PROCESOS_DB_PORT') ?: 3306),
            'database' => 'bd_extraccion',
            'user' => (string)(getenv('PROCESOS_DB_USER') ?: ''),
            'password' => (string)(getenv('PROCESOS_DB_PASS') ?: ''),
        ],
        'table' => 'datos_secadores',
        'foreign_key' => 'secador_id',
        'limit' => 8,
    ],
    'tunnels' => [
        'tunel_1' => [
            'label' => 'Túnel 1',
            'utilities' => [
                'velocidad' => ['label' => 'Vel. banda', 'source' => 'aveva', 'sensor' => 'FUNCIONAMIENTO_BANDA_TRANSPORTADORA', 'factor' => 0.312, 'unit' => '', 'rule' => $rules['velocidad']],
                'caudal' => ['label' => 'Caudal aire', 'source' => 'caudal', 'sensor' => 'caudal', 'unit' => '', 'rule' => $rules['caudal_1']],
                'agua_suministro' => ['label' => 'Agua suministro', 'source' => 'aveva', 'sensor' => 'TEMPERATURA_ENTRADA_AGUA_CALIENTE', 'unit' => '°C', 'rule' => $rules['agua_suministro'], 'show_adjustment' => false],
                'agua_retorno' => ['label' => 'Agua retorno', 'source' => 'aveva', 'sensor' => 'TEMPERATURA_RETORNO_AGUA_CALIENTE', 'unit' => '°C', 'rule' => $rules['agua_retorno'], 'show_adjustment' => false],
                'presion_vapor' => ['label' => 'Pres. vapor', 'source' => 'aveva', 'sensor' => 'PRESION_DE_VAPOR', 'unit' => 'bar(a)', 'rule' => $rules['presion_vapor'], 'show_adjustment' => false],
                'humedad_churro' => ['label' => 'Hum. churro', 'source' => 'humedad_churro', 'unit' => '%', 'rule' => $rules['humedad_churro']],
            ],
            'rooms' => [
                1 => ['temperature' => 'TEMPERATURA_ZONA_1', 'temperature_rule' => $temperatureRules[1], 'humidity' => 'HUMEDAD_ENTRADA_SECADOR_1'],
                2 => ['temperature' => 'TEMPERATURA_ZONA_2', 'temperature_rule' => $temperatureRules[2], 'humidity' => 'SECADOR_AS_HUMEDAD_ZONA_2_INFERIOR'],
                3 => ['temperature' => 'TEMPERATURA_ZONA_3', 'temperature_rule' => $temperatureRules[3]],
                4 => ['temperature' => 'TEMPERATURA_ZONA_4', 'temperature_rule' => $temperatureRules[4], 'humidity' => 'SECADOR_AS_HUMEDAD_ZONA_4_INFERIOR'],
                5 => ['temperature' => 'TEMPERATURA_ZONA_5', 'temperature_rule' => $temperatureRules[5]],
                6 => ['temperature' => 'TEMPERATURA_ZONA_6', 'temperature_rule' => $temperatureRules[6]],
                7 => ['temperature' => 'TEMPERATURA_ZONA_7', 'temperature_rule' => $temperatureRules[7]],
                8 => ['temperature' => 'TEMPERATURA_ZONA_8', 'temperature_rule' => $temperatureRules[8], 'humidity' => 'SECADOR_AS_HUMEDAD_ZONA_8_INFERIOR'],
            ],
        ],
        'tunel_2' => [
            'label' => 'Túnel 2',
            'utilities' => [
                'velocidad' => ['label' => 'Vel. banda', 'source' => 'aveva', 'sensor' => 'T5VELB', 'unit' => '', 'rule' => $rules['velocidad']],
                'caudal' => ['label' => 'Caudal aire', 'source' => 'caudal', 'sensor' => 'caudal', 'unit' => '', 'rule' => $rules['caudal_2']],
                'agua_suministro' => ['label' => 'Agua suministro', 'source' => 'aveva', 'sensor' => 'ENTRADA_DE_AGUA_CALIENTE_DE_TUNEL_1', 'unit' => '°C', 'rule' => $rules['agua_suministro'], 'show_adjustment' => false],
                'agua_retorno' => ['label' => 'Agua retorno', 'source' => 'aveva', 'sensor' => 'RETORNO_DE_AGUA_CALIENTE_DE_TUNEL_1', 'unit' => '°C', 'rule' => $rules['agua_retorno'], 'show_adjustment' => false],
                'presion_vapor' => ['label' => 'Pres. vapor', 'source' => 'aveva', 'sensor' => 'PRESION_DE_ENTRADA_DE_VAPOR_TUNEL1', 'unit' => 'bar(a)', 'rule' => $rules['presion_vapor'], 'show_adjustment' => false],
                'humedad_churro' => ['label' => 'Hum. churro', 'source' => 'humedad_churro', 'unit' => '%', 'rule' => $rules['humedad_churro']],
            ],
            'rooms' => [
                1 => ['temperature' => 'T5THVACZ1PV', 'temperature_rule' => $temperatureRules[1], 'humidity' => 'T5HHVACZ1PV'],
                2 => ['temperature' => 'TEMPERATURA_RECAMARA_2_TUNEL_1', 'temperature_rule' => $temperatureRules[2], 'humidity' => 'T5SHZ2PV'],
                3 => ['temperature' => 'TEMPERATURA_RECAMARA_3_TUNEL_1', 'temperature_rule' => $temperatureRules[3]],
                4 => ['temperature' => 'TEMPERATURA_RECAMARA_4_TUNEL_1', 'temperature_rule' => $temperatureRules[4], 'humidity' => 'T5SHZ4PV'],
                5 => ['temperature' => 'TEMPERATURA_RECAMARA_5_TUNEL_1', 'temperature_rule' => $temperatureRules[5]],
                6 => ['temperature' => 'TEMPERATURA_RECAMARA_6_TUNEL_1', 'temperature_rule' => $temperatureRules[6]],
                7 => ['temperature' => 'TEMPERATURA_RECAMARA_7_TUNEL_1', 'temperature_rule' => $temperatureRules[7]],
                8 => ['temperature' => 'TEMPERATURA_RECAMARA_8_TUNEL_1', 'temperature_rule' => $temperatureRules[8], 'humidity' => 'T5SHZ8PV'],
            ],
        ],
    ],
];
