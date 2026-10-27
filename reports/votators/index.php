<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';
$report = require __DIR__ . '/build_report.php';
$captureMode = isset($_GET['capture']);
$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

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
    .vc-temperature-source { display: block; margin-bottom: 2px; color: #7dd3fc; font-size: 13px; font-weight: 900; letter-spacing: .09em; text-transform: uppercase; }
    .vc-temperature-title { display: block; overflow: hidden; color: #f8fafc; font-size: clamp(24px, 1.75vw, 34px); font-weight: 900; text-overflow: ellipsis; white-space: nowrap; }
    .vc-temperature-reading { min-width: 150px; text-align: right; }
    .vc-temperature-value-row { display: flex; align-items: center; justify-content: flex-end; gap: 12px; }
    .vc-temperature-value { display: block; color: #fff; font-size: clamp(44px, 3.45vw, 66px); font-variant-numeric: tabular-nums; font-weight: 900; line-height: .95; white-space: nowrap; }
    .vc-temperature-value small { font-size: .48em; }
    .vc-temperature-time { display: block; margin-top: 4px; color: #cbd8e2; font-size: 11px; white-space: nowrap; }
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
    .vc-unit { min-width: 0; overflow: hidden; display: flex; flex-direction: column; padding: 9px; border: 1px solid var(--vc-line); border-radius: 15px; background: var(--vc-panel); }
    .vc-unit-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin: -9px -9px 9px; padding: 9px 12px; color: var(--vc-text); background: var(--vc-inner); border-bottom: 1px solid var(--vc-line); }
    .vc-unit-head, .vc-field { transition: background-color .2s ease, color .2s ease; }
    .vc-unit-head h2 { font-size: clamp(46px, 3.2vw, 62px); }
    .vc-state { padding: 5px 10px; border-radius: 999px; color: var(--vc-muted); background: #263946; font-size: 17px; font-weight: 900; }
    .vc-fields { display: grid; grid-template-columns: minmax(0, 1fr); gap: 7px; flex: 1 1 auto; min-height: 0; }
    .vc-field { min-width: 0; display: flex; flex-direction: column; justify-content: center; gap: 3px; padding: 10px 12px; border-radius: 11px; background: var(--vc-inner); }
    .vc-field.status-verde { background: var(--vc-green); }
    .vc-field.status-amarillo { color: #111827; background: var(--vc-yellow); }
    .vc-field.status-rojo { background: var(--vc-red); }
    .vc-field.status-gris { background: #334653; }
    .vc-field-heading { min-width: 0; }
    .vc-field-value-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .vc-label { min-width: 0; font-size: clamp(30px, 2.15vw, 42px); font-weight: 800; }
    .vc-value { max-width: 100%; overflow: hidden; font-size: clamp(44px, 3.35vw, 64px); font-variant-numeric: tabular-nums; line-height: 1; text-overflow: ellipsis; white-space: nowrap; }
    .vc-value small { font-size: .52em; }
    .vc-field[data-field-key="flujo"] .vc-value { font-size: clamp(40px, 3vw, 58px); }
    .vc-field[data-field-key="flujo"] .vc-value small { font-size: .4em; }
    .vc-field[data-field-key="presion_cuajado"] .vc-value { font-size: clamp(38px, 2.7vw, 52px); }
    .vc-field[data-field-key="presion_cuajado"] .vc-value small { font-size: .32em; }
    .vc-direction { --vc-cue: #f8fafc; --vc-cue-glow: rgba(248,250,252,.28); display: none; flex: 0 0 auto; min-width: 184px; height: 86px; padding: 8px 16px; align-items: center; justify-content: center; gap: 14px; border: 4px solid var(--vc-cue); border-radius: 17px; color: #f8fafc; background: #172c3a; font-size: 60px; line-height: 1; animation: vc-cue-pulse 2.4s ease-in-out infinite; }
    .vc-direction.is-visible { display: inline-flex; }
    .vc-direction > i,
    .vc-direction b { color: var(--vc-cue); }
    .vc-direction-copy { display: inline-flex; flex-direction: column; align-items: flex-start; gap: 2px; white-space: nowrap; }
    .vc-direction em { color: #fff; font-size: 18px; font-style: normal; font-weight: 950; letter-spacing: .07em; text-transform: uppercase; }
    .vc-direction b { font-size: 38px; font-variant-numeric: tabular-nums; font-weight: 950; white-space: nowrap; }
    .vc-field.status-amarillo .vc-direction,
    .vc-temperature.status-amarillo .vc-direction { color: #f8fafc; background: #172c3a; }
    .vc-temperature-direction { min-width: 174px; height: 78px; padding-block: 7px; font-size: 52px; }
    @keyframes vc-cue-pulse {
      0%, 100% { box-shadow: 0 0 0 0 transparent; }
      50% { box-shadow: 0 0 0 5px var(--vc-cue-glow); }
    }
    @media (prefers-reduced-motion: reduce) {
      .vc-direction { animation: none; }
    }
    .vc-secondary { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px; margin-top: 7px; }
    .vc-mini { min-width: 0; padding: 7px 5px; border-radius: 9px; text-align: center; background: var(--vc-inner); }
    .vc-mini strong { display: block; overflow: hidden; font-size: clamp(24px, 1.75vw, 34px); font-variant-numeric: tabular-nums; text-overflow: ellipsis; white-space: nowrap; }
    .vc-mini strong small { font-size: .65em; }
    .vc-mini span { display: block; margin-top: 3px; color: var(--vc-muted); font-size: clamp(18px, 1.25vw, 24px); }
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
    @media (max-width: 1200px) {
      .vc-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); grid-template-rows: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 820px) {
      .votators-report { min-height: 0; }
      .vc-top-sensors { grid-template-columns: 1fr; }
      .vc-grid { grid-template-columns: 1fr; grid-template-rows: none; }
      .vc-unit:nth-child(n) { grid-column: auto; grid-row: auto; }
      .vc-unit { min-height: 390px; }
    }
    @media (max-width: 520px) {
      .votators-report { padding: 7px; }
      .vc-heading-icon, .vc-count { display: none; }
      .vc-header { align-items: flex-start; }
      .vc-temperature { gap: 8px; padding: 9px 10px; }
      .vc-temperature-title { overflow: visible; font-size: 16px; line-height: 1.12; white-space: normal; }
      .vc-temperature-reading { min-width: 120px; }
      .vc-temperature-value { font-size: 27px; }
      .vc-back { padding: 7px 10px; }
      .vc-fields { grid-template-columns: 1fr; }
      .vc-field { min-height: 105px; }
      .vc-field-value-row { gap: 7px; }
      .vc-direction { min-width: 166px; height: 80px; padding-inline: 11px; gap: 11px; font-size: 54px; }
      .vc-direction em { font-size: 16px; }
      .vc-direction b { font-size: 34px; }
      .vc-temperature-direction { min-width: 152px; height: 72px; font-size: 46px; }
      .vc-field[data-field-key="flujo"] .vc-value { font-size: 42px; }
      .vc-field[data-field-key="flujo"] .vc-value small { font-size: .36em; }
      .vc-field[data-field-key="presion_cuajado"] .vc-value { font-size: 39px; }
      .vc-field[data-field-key="presion_cuajado"] .vc-value small { font-size: .28em; }
      .vc-secondary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .vc-unit { min-height: 0; }
      .vc-footer { align-items: flex-start; flex-direction: column; }
    }
  </style>
  <script src="../../assets/js/display-mode.js?v=<?= urlencode((string)(@filemtime(__DIR__ . '/../../assets/js/display-mode.js') ?: time())) ?>"></script>
</head>
<body class="<?= $captureMode ? 'capture-mode' : '' ?>">
  <main class="votators-report">
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
                <div class="vc-field-heading"><span class="vc-label"><?= $e($field['label']) ?></span></div>
                <div class="vc-field-value-row"><?= $renderValue((array)$field) ?><?= $renderDirection((string)($field['direction'] ?? ''), (string)($field['adjustment'] ?? '')) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="vc-secondary">
            <?php foreach ((array)$equipment['secundarios'] as $field): ?><div class="vc-mini" data-field-key="<?= $e($field['key']) ?>"><?= $renderValue((array)$field) ?><span><?= $e($field['label']) ?></span></div><?php endforeach; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  </main>
  <script>
    (() => {
      const intervalMs = <?= (int)$report['intervalo_actualizacion_ms'] ?>;
      const captureMode = <?= $captureMode ? 'true' : 'false' ?>;
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
        refreshDashboard();
        window.setInterval(refreshDashboard, intervalMs);
      }
    })();
  </script>
</body>
</html>
