<?php

declare(strict_types=1);

namespace ReportesProgel\Votators;

use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

final class VotatorsService
{
    private array $config;
    private AvevaRepository $repository;

    public function __construct(array $config, AvevaRepository $repository)
    {
        $this->config = $config;
        $this->repository = $repository;
    }

    public function build(): array
    {
        $row = $this->repository->latest($this->sensorNames());
        $timestamp = $this->formatTimestamp($row['__timestamp'] ?? null);
        $equipment = [];

        foreach ((array)($this->config['equipment'] ?? []) as $equipmentKey => $equipmentConfig) {
            $primary = [];
            foreach ((array)($equipmentConfig['primary'] ?? []) as $fieldKey => $fieldConfig) {
                $primary[] = $this->field((string)$fieldKey, (array)$fieldConfig, $row);
            }
            $secondary = [];
            foreach ((array)($equipmentConfig['secondary'] ?? []) as $fieldKey => $fieldConfig) {
                $secondary[] = $this->field((string)$fieldKey, (array)$fieldConfig, $row);
            }
            $status = $this->worstStatus($primary);
            $equipment[] = [
                'key' => (string)$equipmentKey,
                'label' => (string)($equipmentConfig['label'] ?? $equipmentKey),
                'statusKey' => $status['key'],
                'statusLabel' => $status['label'],
                'ultimaLectura' => $timestamp,
                'principales' => $primary,
                'secundarios' => $secondary,
            ];
        }

        $feed = $this->field('feed_temperature', (array)($this->config['feed_temperature'] ?? []), $row);
        $feed['fecha'] = $timestamp;

        return $this->baseReport([
            'ok' => true,
            'equipos' => $equipment,
            'temperatura_alimentacion' => $feed,
            'ultima_lectura' => $timestamp,
        ]);
    }

    public function empty(string $warning): array
    {
        $equipment = [];
        foreach ((array)($this->config['equipment'] ?? []) as $equipmentKey => $equipmentConfig) {
            $primary = [];
            foreach ((array)($equipmentConfig['primary'] ?? []) as $fieldKey => $fieldConfig) {
                $primary[] = $this->emptyField((string)$fieldKey, (array)$fieldConfig);
            }
            $secondary = [];
            foreach ((array)($equipmentConfig['secondary'] ?? []) as $fieldKey => $fieldConfig) {
                $secondary[] = $this->emptyField((string)$fieldKey, (array)$fieldConfig);
            }
            $equipment[] = [
                'key' => (string)$equipmentKey,
                'label' => (string)($equipmentConfig['label'] ?? $equipmentKey),
                'statusKey' => 'gris',
                'statusLabel' => 'Sin datos',
                'ultimaLectura' => '—',
                'principales' => $primary,
                'secundarios' => $secondary,
            ];
        }
        $feed = $this->emptyField('feed_temperature', (array)($this->config['feed_temperature'] ?? []));
        $feed['fecha'] = '—';

        return $this->baseReport([
            'ok' => false,
            'warning' => $warning,
            'equipos' => $equipment,
            'temperatura_alimentacion' => $feed,
            'ultima_lectura' => '—',
        ]);
    }

    private function baseReport(array $data): array
    {
        return array_replace([
            'ok' => false,
            'titulo' => (string)($this->config['title'] ?? 'Votators'),
            'subtitulo' => (string)($this->config['subtitle'] ?? 'Monitoreo en tiempo real'),
            'equipos' => [],
            'warning' => '',
            'temperatura_warning' => '',
            'temperatura_alimentacion' => [],
            'ultima_lectura' => '—',
            'intervalo_actualizacion_ms' => max(1000, (int)($this->config['refresh_ms'] ?? 60000)),
        ], $data);
    }

    private function sensorNames(): array
    {
        $names = [];
        $feedSensor = trim((string)(($this->config['feed_temperature'] ?? [])['sensor'] ?? ''));
        if ($feedSensor !== '') {
            $names[] = $feedSensor;
        }
        foreach ((array)($this->config['equipment'] ?? []) as $equipment) {
            foreach (['primary', 'secondary'] as $section) {
                foreach ((array)($equipment[$section] ?? []) as $field) {
                    $sensor = trim((string)($field['sensor'] ?? ''));
                    if ($sensor !== '') {
                        $names[] = $sensor;
                    }
                }
            }
        }
        return array_values(array_unique($names));
    }

    private function field(string $key, array $config, array $row): array
    {
        $sensor = trim((string)($config['sensor'] ?? ''));
        $value = $sensor !== '' && is_numeric($row[$sensor] ?? null) ? (float)$row[$sensor] : null;
        $status = $this->evaluate($value, (array)($config['rule'] ?? []));
        $adjustment = $this->adjustmentToGreen($value, (array)($config['rule'] ?? []));

        return [
            'key' => $key,
            'label' => (string)($config['label'] ?? $key),
            'value' => $value,
            'formatted' => $value === null ? '—' : number_format($value, 2, '.', ','),
            'unit' => (string)($config['unit'] ?? ''),
            'statusKey' => $status['key'],
            'statusLabel' => $status['label'],
            'direction' => $adjustment['direction'],
            'adjustment' => $adjustment['formatted'],
        ];
    }

    private function emptyField(string $key, array $config): array
    {
        return [
            'key' => $key,
            'label' => (string)($config['label'] ?? $key),
            'value' => null,
            'formatted' => '—',
            'unit' => (string)($config['unit'] ?? ''),
            'statusKey' => 'gris',
            'statusLabel' => 'Sin dato',
            'direction' => '',
            'adjustment' => '',
        ];
    }

    private function evaluate(?float $value, array $rule): array
    {
        if ($value === null) {
            return ['key' => 'gris', 'label' => 'Sin dato'];
        }
        foreach ((array)($rule['bands'] ?? []) as $band) {
            $min = isset($band['min']) && is_numeric($band['min']) ? (float)$band['min'] : null;
            $max = isset($band['max']) && is_numeric($band['max']) ? (float)$band['max'] : null;
            if (($min === null || $value >= $min) && ($max === null || $value <= $max)) {
                $key = in_array(($band['status'] ?? ''), ['verde', 'amarillo', 'rojo'], true)
                    ? (string)$band['status']
                    : 'gris';
                return ['key' => $key, 'label' => $this->statusLabel($key)];
            }
        }
        return $rule === []
            ? ['key' => 'gris', 'label' => 'Lectura']
            : ['key' => 'rojo', 'label' => 'Crítico'];
    }

    private function adjustmentToGreen(?float $value, array $rule): array
    {
        if ($value === null) {
            return ['direction' => '', 'formatted' => ''];
        }
        $direction = '';
        $distance = INF;
        foreach ((array)($rule['bands'] ?? []) as $band) {
            if (($band['status'] ?? '') !== 'verde') {
                continue;
            }
            $min = isset($band['min']) && is_numeric($band['min']) ? (float)$band['min'] : null;
            $max = isset($band['max']) && is_numeric($band['max']) ? (float)$band['max'] : null;
            if (($min === null || $value >= $min) && ($max === null || $value <= $max)) {
                return ['direction' => '', 'formatted' => ''];
            }
            if ($min !== null && $value < $min && ($min - $value) < $distance) {
                $distance = $min - $value;
                $direction = 'up';
            }
            if ($max !== null && $value > $max && ($value - $max) < $distance) {
                $distance = $value - $max;
                $direction = 'down';
            }
        }
        if ($direction === '' || !is_finite($distance)) {
            return ['direction' => '', 'formatted' => ''];
        }
        $required = ceil(max(0.0, $distance - 0.000000001) * 100) / 100;
        return [
            'direction' => $direction,
            'formatted' => number_format(max(0.01, $required), 2, '.', ','),
        ];
    }

    private function worstStatus(array $fields): array
    {
        $priority = ['gris' => 0, 'verde' => 1, 'amarillo' => 2, 'rojo' => 3];
        $worst = 'gris';
        foreach ($fields as $field) {
            $key = (string)($field['statusKey'] ?? 'gris');
            if (($priority[$key] ?? 0) > ($priority[$worst] ?? 0)) {
                $worst = $key;
            }
        }
        return ['key' => $worst, 'label' => $this->statusLabel($worst)];
    }

    private function statusLabel(string $key): string
    {
        return [
            'verde' => 'En objetivo',
            'amarillo' => 'Alerta',
            'rojo' => 'Crítico',
            'gris' => 'Sin datos',
        ][$key] ?? 'Sin datos';
    }

    private function formatTimestamp($value): string
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
}

