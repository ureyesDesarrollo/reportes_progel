<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';
$report = require __DIR__ . '/build_report.php';
$captureMode = isset($_GET['capture']);
$selectedScreen = trim((string)($_GET['pantalla'] ?? '')) === 'acciones' ? 'acciones' : 'monitor';
$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
require_once dirname(__DIR__, 2) . '/shared/corrective_actions_view.php';

$renderValue = static function (array $field) use ($e): string {
  $value = trim((string)($field['formatted'] ?? '—')) ?: '—';
  $unit = trim((string)($field['unit'] ?? ''));
  return '<strong class="vc-value">' . $e($value) . ($unit !== '' && $value !== '—' ? ' <small>' . $e($unit) . '</small>' : '') . '</strong>';
};

$renderDirection = static function (string $direction, string $adjustment, string $extraClass = '') use ($e): string {
  $validDirection = in_array($direction, ['up', 'down'], true) ? $direction : '';
  $validAdjustment = $validDirection !== '' ? trim($adjustment) : '';
  $label = $validDirection === 'up' ? 'Subir ' . $validAdjustment . ' para llegar a verde' : ($validDirection === 'down' ? 'Bajar ' . $validAdjustment . ' para llegar a verde' : 'En rango verde');
  $action = $validDirection === 'up' ? 'Subir' : ($validDirection === 'down' ? 'Bajar' : '');
  $icon = $validDirection !== '' ? 'fa-arrow-' . $validDirection . '-long' : 'fa-minus';
  return '<span class="vc-direction ' . $e($extraClass) . ($validDirection !== '' ? ' is-visible direction-' . $e($validDirection) : '') . '" title="' . $e($label) . '" aria-label="' . $e($label) . '"><i class="fa-solid ' . $e($icon) . '"></i><span class="vc-direction-copy"><em>' . $e($action) . '</em><b>' . $e($validAdjustment) . '</b></span></span>';
};
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $e($report['titulo']) ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/dashboard.css">
  <link rel="stylesheet" href="../../assets/css/corrective-actions.css?v=<?= urlencode((string)(@filemtime(__DIR__ . '/../../assets/css/corrective-actions.css') ?: time())) ?>">
  <style>
    :root { color-scheme: dark; }
    * { box-sizing: border-box; }
    html, body { margin: 0; min-height: 100%; background: #08131b; }
    body { color: #f4f7fa; font-family: Inter, Arial, Helvetica, sans-serif; }
    .votators-report {
      --vc-page: #08131b;
      --vc-panel: #13212b;
      --vc-inner: #1b2b36;
      --vc-line: #314451;
      --vc-text: #f4f7fa;
      --vc-muted: #cbd8e2;
      --vc-red: #c94436;
      --vc-yellow: #facc15;
      --vc-green: #2e8b57;
      --vc-gray: #64748b;
      display: grid;
      grid-template-rows: auto auto minmax(0, 1fr);
      gap: 10px;
      width: 100%;
      min-height: 100vh;
      padding: 10px;
      background: var(--vc-page);
    }
    .vc-header { display: flex; align-items: center; justify-content: space-between; gap: 14px; }
    .vc-heading { display: flex; align-items: center; gap: 12px; }
    .vc-heading-icon { display: grid; width: 42px; height: 42px; place-items: center; border-radius: 12px; color: #08131b; background: #f4f7fa; font-size: 21px; }
    .vc-header h1, .vc-header p, .vc-unit h2 { margin: 0; }
    .vc-header h1 { font-size: clamp(32px, 2.5vw, 48px); line-height: 1; }
    .vc-header p { margin-top: 4px; color: var(--vc-muted); font-size: clamp(15px, 1.12vw, 21px); }
    .vc-header-actions { display: flex; align-items: center; gap: 9px; }
    .vc-count, .vc-back { padding: 8px 13px; border-radius: 999px; font-size: 15px; font-weight: 800; white-space: nowrap; }
    .vc-count { color: #7dd3fc; background: #203957; }
    .vc-back { color: #f4f7fa; background: #1b2b36; text-decoration: none; }
    .vc-warning { grid-row: 2; padding: 7px 10px; border-radius: 8px; color: #111827; background: var(--vc-yellow); font-weight: 800; }
    .vc-warning.is-hidden { display: none; }
    .vc-top-sensors { grid-row: 1; display: grid; grid-template-columns: minmax(0, 1fr); gap: 10px; min-width: 0; }
    .vc-temperature { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 14px; min-width: 0; padding: 10px 16px; border: 1px solid #2563eb; border-radius: 14px; background: #163354; }
    .vc-temperature-icon { display: grid; width: 46px; height: 46px; place-items: center; border-radius: 12px; color: #082f49; background: #7dd3fc; font-size: 25px; transition: color .2s ease, background-color .2s ease; }
    .vc-temperature-copy { min-width: 0; }
    .vc-temperature-source { display: block; margin-bottom: 2px; color: #7dd3fc; font-size: clamp(13px, .65vw, 17px); font-weight: 900; letter-spacing: .09em; text-transform: uppercase; }
    .vc-temperature-title { display: block; overflow: hidden; color: #f8fafc; font-size: clamp(28px, 1.8vw, 46px); font-weight: 900; text-overflow: ellipsis; white-space: nowrap; }
    .vc-temperature-reading { min-width: 150px; text-align: right; }
    .vc-temperature-value-row { display: flex; align-items: center; justify-content: flex-end; gap: 12px; }
    .vc-temperature-value { display: block; color: #fff; font-size: clamp(52px, 3.5vw, 90px); font-variant-numeric: tabular-nums; font-weight: 900; line-height: .95; white-space: nowrap; }
    .vc-temperature-value small { font-size: .48em; }
    .vc-temperature-time { display: block; margin-top: 4px; color: #cbd8e2; font-size: clamp(11px, .55vw, 15px); white-space: nowrap; }
    .vc-temperature-warning { grid-column: 2 / -1; color: #fde68a; font-size: 11px; font-weight: 700; }
    .vc-temperature.status-verde { border-color: #43a36d; background: var(--vc-green); }
    .vc-temperature.status-amarillo { border-color: #facc15; color: #111827; background: var(--vc-yellow); }
    .vc-temperature.status-rojo { border-color: #e35d50; background: var(--vc-red); }
    .vc-temperature.status-gris { border-color: #2563eb; background: #163354; }
    .vc-temperature.status-verde .vc-temperature-icon { color: #14532d; background: rgba(255,255,255,.82); }
    .vc-temperature.status-amarillo .vc-temperature-icon { color: #713f12; background: rgba(255,255,255,.72); }
    .vc-temperature.status-rojo .vc-temperature-icon { color: #7f1d1d; background: rgba(255,255,255,.82); }
    .vc-temperature.status-amarillo .vc-temperature-source,
    .vc-temperature.status-amarillo .vc-temperature-title,
    .vc-temperature.status-amarillo .vc-temperature-value,
    .vc-temperature.status-amarillo .vc-temperature-time { color: #111827; }
    .vc-grid { grid-row: 3; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); grid-template-rows: minmax(0, 1fr); gap: 10px; min-height: 0; }
    .vc-screen { grid-column: 1 / -1; grid-row: 1 / -1; display: none; min-width: 0; min-height: 0; }
    .vc-screen.is-active { display: grid; }
    .vc-monitor-screen { grid-template-rows: auto auto minmax(0, 1fr); gap: 10px; }
    .vc-corrective-screen.is-active { display: block; }
    .vc-unit { min-width: 0; overflow: hidden; display: flex; flex-direction: column; padding: 9px; border: 1px solid var(--vc-line); border-radius: 15px; background: var(--vc-panel); }
    .vc-unit-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin: -9px -9px 9px; padding: 9px 12px; color: var(--vc-text); background: var(--vc-inner); border-bottom: 1px solid var(--vc-line); }
    .vc-unit-head, .vc-field { transition: background-color .2s ease, color .2s ease; }
    .vc-unit-head h2 { font-size: clamp(52px, 3.2vw, 82px); }
    .vc-state { padding: 6px 12px; border-radius: 999px; color: var(--vc-muted); background: #263946; font-size: clamp(17px, 1vw, 25px); font-weight: 900; }
    .vc-fields { display: grid; grid-template-columns: minmax(0, 1fr); gap: 7px; flex: 1 1 auto; min-height: 0; }
    .vc-field { min-width: 0; display: flex; flex-direction: column; justify-content: center; gap: 3px; padding: 10px 12px; border-radius: 11px; background: var(--vc-inner); }
    .vc-field.status-verde { background: var(--vc-green); }
    .vc-field.status-amarillo { color: #111827; background: var(--vc-yellow); }
    .vc-field.status-rojo { background: var(--vc-red); }
    .vc-field.status-gris { background: #334653; }
    .vc-field-heading { min-width: 0; display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .vc-secondary-label { flex: 0 0 auto; padding: 6px 10px; border-radius: 999px; color: #f8fafc; background: rgba(8,19,27,.38); font-size: clamp(15px, .9vw, 23px); font-weight: 850; white-space: nowrap; }
    .vc-secondary-label strong { margin-left: 4px; font-size: 1.12em; font-variant-numeric: tabular-nums; }
    .vc-field.status-amarillo .vc-secondary-label { color: #111827; background: rgba(255,255,255,.48); }
    .vc-field-value-row { min-height: 86px; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .vc-label { min-width: 0; font-size: clamp(36px, 2.5vw, 64px); font-weight: 800; }
    .vc-value { max-width: 100%; overflow: hidden; font-size: clamp(58px, 4.1vw, 104px); font-variant-numeric: tabular-nums; line-height: 1; text-overflow: ellipsis; white-space: nowrap; }
    .vc-value small { font-size: .52em; }
    .vc-field[data-field-key="flujo"] .vc-value { font-size: clamp(54px, 3.6vw, 90px); }
    .vc-field[data-field-key="flujo"] .vc-value small { font-size: .4em; }
    .vc-field[data-field-key="presion_cuajado"] .vc-value { font-size: clamp(50px, 3.25vw, 82px); }
    .vc-field[data-field-key="presion_cuajado"] .vc-value small { font-size: .32em; }
    .vc-direction { --vc-cue: #f8fafc; --vc-cue-glow: rgba(248,250,252,.28); display: none; flex: 0 0 auto; min-width: clamp(184px, 10.5vw, 230px); height: clamp(86px, 5vw, 112px); padding: 8px 16px; align-items: center; justify-content: center; gap: clamp(12px, .7vw, 18px); border: 4px solid var(--vc-cue); border-radius: 17px; color: #f8fafc; background: #172c3a; font-size: clamp(60px, 3.8vw, 82px); line-height: 1; animation: vc-cue-pulse 2.4s ease-in-out infinite; }
    .vc-direction.is-visible { display: inline-flex; }
    .vc-field .vc-direction { display: none; }
    .vc-field .vc-direction.is-visible { display: inline-flex; }
    .vc-field[data-field-key="tiempo_fuera"] .vc-direction { display: none; }
    .vc-direction > i,
    .vc-direction b { color: var(--vc-cue); }
    .vc-direction-copy { display: inline-flex; flex-direction: column; align-items: flex-start; gap: 2px; white-space: nowrap; }
    .vc-direction em { color: #fff; font-size: clamp(18px, 1vw, 25px); font-style: normal; font-weight: 950; letter-spacing: .07em; text-transform: uppercase; }
    .vc-direction b { font-size: clamp(38px, 2vw, 50px); font-variant-numeric: tabular-nums; font-weight: 950; white-space: nowrap; }
    .vc-field.status-amarillo .vc-direction,
    .vc-temperature.status-amarillo .vc-direction { color: #f8fafc; background: #172c3a; }
    .vc-temperature-direction { min-width: clamp(184px, 10vw, 220px); height: clamp(82px, 4.8vw, 108px); padding-block: 7px; font-size: clamp(56px, 3.6vw, 80px); }
    @keyframes vc-cue-pulse {
      0%, 100% { box-shadow: 0 0 0 0 transparent; }
      50% { box-shadow: 0 0 0 5px var(--vc-cue-glow); }
    }
    @media (prefers-reduced-motion: reduce) {
      .vc-direction { animation: none; }
    }
    .vc-field[data-field-key="tiempo_fuera"] .vc-value { font-size: clamp(54px, 3.6vw, 90px); }
    .vc-field[data-field-key="tiempo_fuera"] .vc-value small { font-size: .42em; }
    .vc-read-time { display: flex; align-items: center; justify-content: flex-end; gap: 5px; margin-top: 6px; color: var(--vc-muted); font-size: 11px; }
    .vc-footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; color: var(--vc-muted); font-size: 12px; }
    .vc-legend { display: flex; flex-wrap: wrap; gap: 12px; }
    .vc-legend span { display: inline-flex; align-items: center; gap: 5px; }
    body.capture-mode .votators-report { padding: 6px; gap: 6px; }
    body.capture-mode .vc-top-sensors { gap: 6px; }
    body.capture-mode .vc-back { display: none; }
    body.capture-mode .vc-grid { gap: 6px; }
    body.capture-mode .vc-unit { padding: 7px; border-radius: 11px; }
    body.capture-mode .vc-unit-head { margin: -7px -7px 7px; padding: 7px 10px; }
    body.capture-mode .vc-field { padding: 7px 9px; }
    @media (min-width: 1600px) and (max-height: 1200px) {
      .vc-temperature-source { font-size: 13px; }
      .vc-temperature-title { font-size: 34px; }
      .vc-temperature-value { font-size: 66px; }
      .vc-temperature-time { font-size: 11px; }
      .vc-unit-head h2 { font-size: 62px; }
      .vc-state { font-size: 17px; }
      .vc-secondary-label { font-size: 17px; }
      .vc-label { font-size: 42px; }
      .vc-value { font-size: 64px; }
      .vc-field[data-field-key="flujo"] .vc-value { font-size: 58px; }
      .vc-field[data-field-key="presion_cuajado"] .vc-value { font-size: 52px; }
      .vc-field[data-field-key="tiempo_fuera"] .vc-value { font-size: 58px; }
      .vc-direction { min-width: 184px; height: 86px; gap: 14px; font-size: 60px; }
      .vc-direction em { font-size: 18px; }
      .vc-direction b { font-size: 38px; }
      .vc-temperature-direction { min-width: 174px; height: 78px; font-size: 52px; }
    }
    @media (max-width: 1599px) {
      .votators-report { height: auto; min-height: 100vh; }
      .vc-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); grid-template-rows: none; align-items: stretch; }
      .vc-unit { min-height: 0; }
      .vc-fields { grid-template-columns: repeat(2, minmax(0, 1fr)); grid-auto-rows: minmax(132px, auto); }
      .vc-field[data-field-key="tiempo_fuera"] { grid-column: 1 / -1; }
      .vc-label { font-size: clamp(30px, 2.7vw, 40px); line-height: 1.05; }
      .vc-value { font-size: clamp(44px, 4.1vw, 56px); }
      .vc-field-value-row { min-height: 78px; }
      .vc-direction { min-width: 142px; height: 78px; padding: 7px 10px; gap: 9px; font-size: 52px; }
      .vc-field .vc-direction { width: 142px; flex-basis: 142px; }
      .vc-direction em { font-size: 15px; }
      .vc-direction b { font-size: 34px; }
      .vc-field[data-field-key="flujo"] .vc-value { font-size: clamp(42px, 3.4vw, 46px); }
      .vc-field[data-field-key="solidos"] .vc-value { font-size: clamp(44px, 3.8vw, 52px); }
      .vc-field[data-field-key="presion_cuajado"] .vc-value { font-size: clamp(38px, 3.35vw, 46px); }
      .vc-field[data-field-key="presion_cuajado"] .vc-value small { font-size: .27em; }
    }
    @media (max-width: 900px) {
      .votators-report { min-height: 0; }
      .vc-top-sensors { grid-template-columns: 1fr; }
      .vc-grid { grid-template-columns: 1fr; grid-template-rows: none; }
      .vc-unit:nth-child(n) { grid-column: auto; grid-row: auto; }
      .vc-temperature { grid-template-columns: auto minmax(0, 1fr); }
      .vc-temperature-reading { grid-column: 1 / -1; min-width: 0; }
      .vc-temperature-value-row { justify-content: space-between; }
      .vc-temperature-title { overflow: visible; line-height: 1.08; text-overflow: clip; white-space: normal; }
      .vc-unit { min-height: 0; }
      .vc-label { font-size: 36px; }
      .vc-value { font-size: 64px; }
      .vc-field[data-field-key="flujo"] .vc-value { font-size: 58px; }
      .vc-field[data-field-key="presion_cuajado"] .vc-value { font-size: 56px; }
      .vc-field[data-field-key="solidos"] .vc-value { font-size: 60px; }
      .vc-field[data-field-key="tiempo_fuera"] .vc-value { font-size: 58px; }
    }
    @media (max-width: 600px) {
      .vc-fields { grid-template-columns: 1fr; grid-auto-rows: auto; }
      .vc-field[data-field-key="tiempo_fuera"] { grid-column: auto; }
      .vc-field { min-height: 108px; }
    }
    @media (max-width: 520px) {
      .votators-report { padding: 7px; }
      .vc-heading-icon, .vc-count { display: none; }
      .vc-header { align-items: flex-start; }
      .vc-temperature { gap: 8px; padding: 10px; }
      .vc-temperature-title { font-size: 23px; }
      .vc-temperature-value { font-size: 46px; }
      .vc-back { padding: 7px 10px; }
      .vc-fields { grid-template-columns: 1fr; }
      .vc-field { min-height: 112px; }
      .vc-field-value-row { gap: 7px; }
      .vc-direction { min-width: 166px; height: 80px; padding-inline: 11px; gap: 11px; font-size: 54px; }
      .vc-field .vc-direction { width: 166px; flex-basis: 166px; }
      .vc-direction em { font-size: 16px; }
      .vc-direction b { font-size: 34px; }
      .vc-temperature-direction { min-width: 152px; height: 72px; font-size: 46px; }
      .vc-label { font-size: 36px; }
      .vc-value { font-size: 62px; }
      .vc-field[data-field-key="flujo"] .vc-value { font-size: 56px; }
      .vc-field[data-field-key="flujo"] .vc-value small { font-size: .36em; }
      .vc-field[data-field-key="solidos"] .vc-value { font-size: 58px; }
      .vc-field[data-field-key="presion_cuajado"] .vc-value { font-size: 54px; }
      .vc-field[data-field-key="presion_cuajado"] .vc-value small { font-size: .28em; }
      .vc-unit { min-height: 0; }
      .vc-footer { align-items: flex-start; flex-direction: column; }
    }
  </style>
  <link rel="stylesheet" href="../../assets/css/report-responsive.css?v=<?= urlencode((string)(@filemtime(__DIR__ . '/../../assets/css/report-responsive.css') ?: time())) ?>">
</head>
<body class="<?= $captureMode ? 'capture-mode' : '' ?>">
  <main class="votators-report" data-report-responsive>
    <section class="vc-screen vc-monitor-screen<?= $selectedScreen === 'monitor' ? ' is-active' : '' ?>" data-vc-screen="monitor" aria-hidden="<?= $selectedScreen === 'monitor' ? 'false' : 'true' ?>">
    <section class="vc-top-sensors" aria-label="Temperatura de alimentación de Votators">
    <?php $feedTemperature = (array)($report['temperatura_alimentacion'] ?? []); ?>
    <?php $feedStatus = in_array($feedTemperature['statusKey'] ?? '', ['verde', 'amarillo', 'rojo'], true) ? (string)$feedTemperature['statusKey'] : 'gris'; ?>
    <section class="vc-temperature status-<?= $e($feedStatus) ?>" data-live-sensor="feed" aria-label="Temperatura de entrada del tanque de alimentación de Votators 3 y 4">
      <span class="vc-temperature-icon"><i class="fa-solid fa-temperature-three-quarters"></i></span>
      <div class="vc-temperature-copy"><span class="vc-temperature-source">Sensor AVEVA</span><span class="vc-temperature-title">TEMP. ENTRADA TANQUE ALIMENTACIÓN V3 Y V4</span></div>
      <div class="vc-temperature-reading"><div class="vc-temperature-value-row"><?= $renderDirection((string)($feedTemperature['direction'] ?? ''), (string)($feedTemperature['adjustment'] ?? ''), 'vc-temperature-direction') ?><strong class="vc-temperature-value"><?= $e($feedTemperature['formatted'] ?? '—') ?><?php if (($feedTemperature['formatted'] ?? '—') !== '—'): ?> <small><?= $e($feedTemperature['unit'] ?? '°C') ?></small><?php endif; ?></strong></div><span class="vc-temperature-time">Última lectura <?= $e($feedTemperature['fecha'] ?? '—') ?></span></div>
      <?php if (trim((string)($report['temperatura_warning'] ?? '')) !== ''): ?><span class="vc-temperature-warning"><?= $e($report['temperatura_warning']) ?></span><?php endif; ?>
    </section>
    </section>
    <?php $warning = trim((string)($report['warning'] ?? '')); ?>
    <div class="vc-warning<?= $warning === '' ? ' is-hidden' : '' ?>"><?= $e($warning) ?></div>

    <section class="vc-grid" aria-label="Estado de los Votators">
      <?php foreach ((array)$report['equipos'] as $equipment): ?>
        <article class="vc-unit" data-equipment-key="<?= $e($equipment['key']) ?>" aria-label="<?= $e($equipment['label']) ?>, <?= $e($equipment['statusLabel']) ?>">
          <header class="vc-unit-head"><h2><?= $e($equipment['label']) ?></h2><span class="vc-state"><?= $e($equipment['statusLabel']) ?></span></header>
          <div class="vc-fields">
            <?php foreach ((array)$equipment['principales'] as $field):
              $fieldStatus = in_array($field['statusKey'] ?? '', ['verde', 'amarillo', 'rojo'], true) ? (string)$field['statusKey'] : 'gris';
            ?>
              <div class="vc-field status-<?= $e($fieldStatus) ?>" data-field-key="<?= $e($field['key']) ?>">
                <div class="vc-field-heading"><span class="vc-label"><?= $e($field['label']) ?></span><?php if (trim((string)($field['secondaryLabel'] ?? '')) !== ''): ?><span class="vc-secondary-label"><?= $e($field['secondaryLabel']) ?> <strong><?= $e($field['secondaryFormatted'] ?? '—') ?></strong></span><?php endif; ?></div>
                <div class="vc-field-value-row"><?= $renderValue((array)$field) ?><?= $renderDirection((string)($field['direction'] ?? ''), (string)($field['adjustment'] ?? '')) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
    </section>
    <section class="vc-screen vc-corrective-screen<?= $selectedScreen === 'acciones' ? ' is-active' : '' ?>" data-vc-screen="acciones" aria-hidden="<?= $selectedScreen === 'acciones' ? 'false' : 'true' ?>">
      <?php correctiveActionsRender((array)($report['corrective_actions'] ?? []), (string)($report['corrective_warning'] ?? ''), 'votators'); ?>
    </section>
  </main>
  <script src="../../assets/js/corrective-actions.js?v=<?= urlencode((string)(@filemtime(__DIR__ . '/../../assets/js/corrective-actions.js') ?: time())) ?>"></script>
  <script>
    (() => {
      const intervalMs = <?= (int)$report['intervalo_actualizacion_ms'] ?>;
      const correctiveRotationMs = <?= (int)$report['intervalo_acciones_ms'] ?>;
      const captureMode = <?= $captureMode ? 'true' : 'false' ?>;
      const fixedScreen = <?= isset($_GET['pantalla']) ? 'true' : 'false' ?>;
      const screens = Array.from(document.querySelectorAll('[data-vc-screen]'));
      let activeScreen = Math.max(0, screens.findIndex(screen => screen.classList.contains('is-active')));
      let updating = false;

      const statusClasses = ['status-verde', 'status-amarillo', 'status-rojo', 'status-gris'];
      const setStatus = (element, status) => {
        if (!element) return;
        element.classList.remove(...statusClasses);
        element.classList.add(`status-${statusClasses.includes(`status-${status}`) ? status : 'gris'}`);
      };
      const setValue = (container, field) => {
        const value = container?.querySelector('.vc-value');
        if (!value) return;
        value.replaceChildren(document.createTextNode(field.formatted || '—'));
        if (field.unit && field.formatted !== '—') {
          const unit = document.createElement('small');
          unit.textContent = ` ${field.unit}`;
          value.append(unit);
        }
        const secondary = container?.querySelector('.vc-secondary-label strong');
        if (secondary) secondary.textContent = field.secondaryFormatted || '—';
      };
      const setDirection = (container, direction, adjustment) => {
        const cue = container?.querySelector('.vc-direction');
        const icon = cue?.querySelector('i');
        const action = cue?.querySelector('em');
        const amount = cue?.querySelector('b');
        if (!cue || !icon || !action || !amount) return;
        const validDirection = direction === 'up' || direction === 'down' ? direction : '';
        const validAdjustment = validDirection ? String(adjustment || '') : '';
        const label = validDirection === 'up' ? `Subir ${validAdjustment} para llegar a verde` : (validDirection === 'down' ? `Bajar ${validAdjustment} para llegar a verde` : 'En rango verde');
        cue.classList.toggle('is-visible', validDirection !== '');
        cue.classList.toggle('direction-up', validDirection === 'up');
        cue.classList.toggle('direction-down', validDirection === 'down');
        cue.title = label;
        cue.setAttribute('aria-label', label);
        icon.className = `fa-solid ${validDirection ? `fa-arrow-${validDirection}-long` : 'fa-minus'}`;
        action.textContent = validDirection === 'up' ? 'Subir' : (validDirection === 'down' ? 'Bajar' : '');
        amount.textContent = validAdjustment;
      };

      const applyLiveData = (data) => {
        Object.entries(data.equipment || {}).forEach(([equipmentKey, equipment]) => {
          const card = document.querySelector(`[data-equipment-key="${CSS.escape(equipmentKey)}"]`);
          if (!card) return;
          const header = card.querySelector('.vc-unit-head');
          const state = header?.querySelector('.vc-state');
          if (state) state.textContent = equipment.statusLabel || 'Sin datos';

          Object.entries(equipment.fields || {}).forEach(([fieldKey, field]) => {
            const fieldCard = card.querySelector(`[data-field-key="${CSS.escape(fieldKey)}"]`);
            if (!fieldCard) return;
            if (fieldCard.classList.contains('vc-field')) setStatus(fieldCard, field.statusKey);
            setValue(fieldCard, field);
            setDirection(fieldCard, field.direction, field.adjustment);
          });

          const readTime = card.querySelector('.vc-read-time');
          if (readTime) {
            const icon = document.createElement('i');
            icon.className = 'fa-regular fa-clock';
            readTime.replaceChildren(icon, document.createTextNode(` Última lectura ${equipment.timestamp || '—'}`));
          }
        });

        const feedCard = document.querySelector('[data-live-sensor="feed"]');
        const feedTemperature = data.feedTemperature || {};
        setStatus(feedCard, feedTemperature.statusKey);
        setDirection(feedCard, feedTemperature.direction, feedTemperature.adjustment);
        const feedTemperatureValue = feedCard?.querySelector('.vc-temperature-value');
        if (feedTemperatureValue) {
          feedTemperatureValue.replaceChildren(document.createTextNode(feedTemperature.formatted || '—'));
          if (feedTemperature.unit && feedTemperature.formatted !== '—') {
            const unit = document.createElement('small');
            unit.textContent = ` ${feedTemperature.unit}`;
            feedTemperatureValue.append(unit);
          }
        }
        const feedTemperatureTime = feedCard?.querySelector('.vc-temperature-time');
        if (feedTemperatureTime) feedTemperatureTime.textContent = `Última lectura ${feedTemperature.timestamp || '—'}`;

        const generalReading = document.querySelector('.vc-general-reading');
        if (generalReading) generalReading.textContent = `Lectura general ${data.timestamp || '—'}`;
        window.CorrectiveActions?.update(
          document.querySelector('[data-corrective-actions]'),
          data.correctiveActions || [],
          data.correctiveWarning || ''
        );
      };

      const showScreen = index => {
        activeScreen = index % screens.length;
        screens.forEach((screen, screenIndex) => {
          const active = screenIndex === activeScreen;
          screen.classList.toggle('is-active', active);
          screen.setAttribute('aria-hidden', active ? 'false' : 'true');
        });
      };

      const refreshDashboard = async () => {
        if (captureMode || updating || document.hidden) return;
        updating = true;
        try {
          const response = await fetch(`live.php?_=${Date.now()}`, { cache: 'no-store' });
          if (!response.ok) throw new Error(`HTTP ${response.status}`);
          const data = await response.json();
          if (data.ok) window.requestAnimationFrame(() => applyLiveData(data));
        } catch (error) {
          console.debug('Actualización silenciosa pendiente.', error);
        } finally {
          updating = false;
        }
      };

      if (!captureMode) {
        showScreen(activeScreen);
        refreshDashboard();
        window.setInterval(refreshDashboard, intervalMs);
        if (!fixedScreen && screens.length > 1) window.setInterval(() => showScreen(activeScreen + 1), correctiveRotationMs);
      }
    })();
  </script>
</body>
</html>
