<?php

declare(strict_types=1);

/*
 * Carga la configuración local sin depender de Docker ni de librerías externas.
 * Las variables definidas por Apache o el sistema tienen prioridad sobre .env.
 */

$environmentFile = dirname(__DIR__) . '/.env';

if (is_readable($environmentFile)) {
    $lines = file($environmentFile, FILE_IGNORE_NEW_LINES);

    foreach ($lines ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || $line[0] === ';' || strpos($line, '=') === false) {
            continue;
        }

        [$name, $value] = array_map('trim', explode('=', $line, 2));
        if (preg_match('/^[A-Z_][A-Z0-9_]*$/i', $name) !== 1 || getenv($name) !== false) {
            continue;
        }

        $length = strlen($value);
        if ($length >= 2) {
            $first = $value[0];
            $last = $value[$length - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

$timezone = trim((string)(getenv('TZ') ?: 'America/Mexico_City'));
if ($timezone !== '' && in_array($timezone, timezone_identifiers_list(), true)) {
    date_default_timezone_set($timezone);
}
