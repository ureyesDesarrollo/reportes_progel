<?php

declare(strict_types=1);

/* Rangos operativos heredados del reporte Concentradores. */

$exactFlow = static function (float $target, float $yellowMin): array {
    return [
        'modo' => 'bandas',
        'leyenda' => number_format($target, 0),
        'bandas' => [
            ['min' => $target, 'max' => $target, 'estado' => 'verde'],
            ['min' => $yellowMin, 'max' => $target - 0.000001, 'estado' => 'amarillo'],
            ['max' => $yellowMin - 0.000001, 'estado' => 'rojo'],
            ['min' => $target + 0.000001, 'estado' => 'rojo'],
        ],
    ];
};

return [
    'invertido' => ['flujo' => ['modo' => 'minimo', 'verde_min' => 60, 'amarillo_min' => 50]],
    'flujo' => [
        'concentrador_1' => $exactFlow(34, 30),
        'concentrador_2' => $exactFlow(35, 31),
        'concentrador_3' => $exactFlow(35, 31),
        'concentrador_4' => $exactFlow(38, 31),
    ],
    'vacio' => [
        'modo' => 'bandas', 'leyenda' => '>525',
        'bandas' => [
            ['min' => 525.000001, 'estado' => 'verde'],
            ['min' => 490, 'max' => 525, 'estado' => 'amarillo'],
            ['max' => 489.999999, 'estado' => 'rojo'],
        ],
    ],
    'temperatura' => [
        'modo' => 'bandas', 'leyenda' => '<50',
        'bandas' => [
            ['max' => 49.999999, 'estado' => 'verde'],
            ['min' => 50, 'max' => 54, 'estado' => 'amarillo'],
            ['min' => 54.000001, 'estado' => 'rojo'],
        ],
    ],
    'solidos_entrada' => [
        'modo' => 'bandas', 'leyenda' => '21–22',
        'bandas' => [
            ['min' => 21, 'max' => 22, 'estado' => 'verde'],
            ['min' => 19, 'max' => 20.999999, 'estado' => 'amarillo'],
            ['max' => 18.999999, 'estado' => 'rojo'],
            ['min' => 22.000001, 'estado' => 'rojo'],
        ],
    ],
    'solidos_salida' => [
        'modo' => 'bandas', 'leyenda' => '≥37',
        'bandas' => [
            ['min' => 37, 'estado' => 'verde'],
            ['min' => 34, 'max' => 36.999999, 'estado' => 'amarillo'],
            ['max' => 33.999999, 'estado' => 'rojo'],
        ],
    ],
    'corriente' => [
        'concentrador_1' => ['modo' => 'bandas', 'leyenda' => '<85', 'bandas' => [['max' => 84.999999, 'estado' => 'verde'], ['min' => 85, 'max' => 86.999999, 'estado' => 'amarillo'], ['min' => 87, 'estado' => 'rojo']]],
        'concentrador_2' => ['modo' => 'bandas', 'leyenda' => '<60', 'bandas' => [['max' => 59.999999, 'estado' => 'verde'], ['min' => 60, 'max' => 60.999999, 'estado' => 'amarillo'], ['min' => 61, 'estado' => 'rojo']]],
        'concentrador_3' => ['modo' => 'bandas', 'leyenda' => '<60', 'bandas' => [['max' => 59.999999, 'estado' => 'verde'], ['min' => 60, 'max' => 61.999999, 'estado' => 'amarillo'], ['min' => 62, 'estado' => 'rojo']]],
        'concentrador_4' => ['modo' => 'bandas', 'leyenda' => '<60', 'bandas' => [['max' => 59.999999, 'estado' => 'verde'], ['min' => 60, 'max' => 61.999999, 'estado' => 'amarillo'], ['min' => 62, 'estado' => 'rojo']]],
    ],
    'presion_vapor' => [
        'modo' => 'bandas', 'leyenda' => '<32',
        'bandas' => [
            ['max' => 31.999999, 'estado' => 'verde'],
            ['min' => 32, 'max' => 32, 'estado' => 'amarillo'],
            ['min' => 32.000001, 'estado' => 'rojo'],
        ],
    ],
];
