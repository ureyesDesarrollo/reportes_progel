<?php

declare(strict_types=1);

return [
    'groups' => [
        'produccion' => [
            'title' => 'Producción',
            'description' => 'Monitoreo operativo de equipos y condiciones del proceso en planta.',
            'icon' => 'fa-industry',
            'color' => '#0f766e',
        ],
    ],
    'subgroups' => [
        'produccion-monitoreo' => [
            'group' => 'produccion',
            'title' => 'Producción Monitoreo',
            'description' => 'Equipos y variables de preparación del proceso.',
            'icon' => 'fa-sliders',
            'color' => '#0f766e',
        ],
    ],
    'reports' => [
        [
            'slug' => 'cocedores',
            'title' => 'Cocedores',
            'description' => 'Flujo, NTU, sólidos, pH y temperaturas de operación de los nueve Cocedores.',
            'icon' => 'fa-fire-burner',
            'color' => '#0f766e',
            'groups' => ['produccion'],
            'subgroup' => 'produccion-monitoreo',
            'url' => './cocedores/',
            'enabled' => true,
        ],
        [
            'slug' => 'clarificadores',
            'title' => 'Clarificador',
            'description' => 'Variables fisicoquímicas, flujos y nivel del tanque de balance del Clarificador.',
            'icon' => 'fa-filter',
            'color' => '#0f766e',
            'groups' => ['produccion'],
            'subgroup' => 'produccion-monitoreo',
            'url' => './clarificadores/',
            'enabled' => true,
        ],
        [
            'slug' => 'concentradores',
            'title' => 'Concentradores',
            'description' => 'Lecturas operativas, sólidos, flujo, vacío, temperaturas y variables auxiliares de Concentradores.',
            'icon' => 'fa-industry',
            'color' => '#0f766e',
            'groups' => ['produccion'],
            'subgroup' => 'produccion-monitoreo',
            'url' => './concentradores/',
            'enabled' => true,
        ],
        [
            'slug' => 'votators',
            'title' => 'Votators',
            'description' => 'Variables de operación y semáforos en tiempo real de los cuatro Votators.',
            'icon' => 'fa-sliders',
            'color' => '#0f766e',
            'groups' => ['produccion'],
            'subgroup' => 'produccion-monitoreo',
            'url' => './votators/',
            'enabled' => true,
        ],
        [
            'slug' => 'secadores',
            'title' => 'Secadores',
            'description' => 'Monitoreo por túnel de recámaras, servicios y acciones para llegar al objetivo.',
            'icon' => 'fa-fan',
            'color' => '#0f766e',
            'groups' => ['produccion'],
            'subgroup' => 'produccion-monitoreo',
            'url' => './secadores/',
            'enabled' => true,
        ],
        
    ],
];
