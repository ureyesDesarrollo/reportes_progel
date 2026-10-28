<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';
$report = require __DIR__ . '/build_report.php';
$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
require_once dirname(__DIR__, 2) . '/shared/corrective_actions_view.php';

$selectedTunnel = trim((string)($_GET['secador'] ?? ''));
$actionsSelected = $selectedTunnel === 'acciones';
if (!$actionsSelected && !isset($report['tunnels'][$selectedTunnel])) {
    $selectedTunnel = '';
}
$captureMode = isset($_GET['capture']);
$tunnels = $actionsSelected
    ? []
    : ($selectedTunnel !== ''
    ? [$selectedTunnel => $report['tunnels'][$selectedTunnel]]
    : (array)$report['tunnels']);
$showCorrectiveActions = $selectedTunnel === '' || $actionsSelected;

$renderDirection = static function (array $field) use ($escape): string {
    $direction = in_array($field['direction'] ?? '', ['up', 'down'], true)
        ? (string)$field['direction']
        : '';
    if ($direction === '') {
        return '';
    }

    $action = $direction === 'up' ? 'Subir' : 'Bajar';
    $icon = $direction === 'up' ? '↑' : '↓';
    $adjustment = trim((string)($field['adjustment'] ?? ''));
    return '<span class="dryer-direction direction-' . $escape($direction) . '" aria-label="' . $escape($action . ' ' . $adjustment . ' para llegar a verde') . '">'
        . '<span class="dryer-direction-arrow" aria-hidden="true">' . $escape($icon) . '</span>'
        . '<span><em>' . $escape($action) . '</em><b>' . $escape($adjustment) . '</b></span>'
        . '</span>';
};

$renderField = static function (?array $field, string $class = '') use ($escape, $renderDirection): void {
    if ($field === null) {
        return;
    }
    $status = in_array($field['statusKey'] ?? '', ['verde', 'amarillo', 'rojo'], true)
        ? (string)$field['statusKey']
        : 'gris';
    $value = trim((string)($field['formatted'] ?? '—')) ?: '—';
    $unit = !in_array($value, ['—', 'F.O'], true) ? trim((string)($field['unit'] ?? '')) : '';

    echo '<div class="dryer-field status-' . $escape($status) . ' ' . $escape($class) . '" data-field-id="' . $escape($field['id'] ?? '') . '">';
    echo '<div class="dryer-field-copy"><span class="dryer-label">' . $escape($field['label'] ?? '') . '</span>';
    echo '<strong class="dryer-value"><span class="value-text">' . $escape($value) . '</span>';
    echo '<small class="value-unit">' . ($unit !== '' ? ' ' . $escape($unit) : '') . '</small>';
    echo '</strong>';
    if (array_key_exists('ageLabel', $field)) {
        echo '<span class="dryer-captured-at">' . $escape($field['ageLabel'] ?? 'Sin fecha') . '</span>';
    }
    echo '</div>';
    echo $renderDirection($field);
    echo '</div>';
};
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Secadores | Reportes Progel</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/secadores.css?v=<?= urlencode((string)(@filemtime(__DIR__ . '/../../assets/css/secadores.css') ?: time())) ?>">
  <link rel="stylesheet" href="../../assets/css/corrective-actions.css?v=<?= urlencode((string)(@filemtime(__DIR__ . '/../../assets/css/corrective-actions.css') ?: time())) ?>">
  <link rel="stylesheet" href="../../assets/css/report-responsive.css?v=<?= urlencode((string)(@filemtime(__DIR__ . '/../../assets/css/report-responsive.css') ?: time())) ?>">
</head>
<body class="<?= $captureMode ? 'capture-mode' : '' ?> <?= $selectedTunnel !== '' ? 'fixed-tunnel' : '' ?>">
<main class="dryer-dashboard" data-report-responsive>
  <header class="dryer-header">
    <div class="dryer-title">
      <span class="dryer-title-icon"><i class="fa-solid fa-wind"></i></span>
      <div><span class="eyebrow">PROGEL · MONITOREO EN TIEMPO REAL</span><h1><?= $escape($report['title']) ?></h1></div>
    </div>
    <div class="header-actions">
      <?php if ($selectedTunnel === '' && count($tunnels) > 1): ?><span class="rotation-status"><i class="fa-solid fa-repeat"></i> <b id="rotation-label">Cambio en 60 s</b></span><?php endif; ?>
      <span class="reading-time">Última lectura <strong id="report-timestamp"><?= $escape($report['timestamp']) ?></strong></span>
      <?php if (!$captureMode): ?><a href="../"><i class="fa-solid fa-table-cells-large"></i> Reportes</a><?php endif; ?>
    </div>
  </header>

  <?php $warningText = implode(' ', (array)$report['warnings']); ?>
  <div class="dryer-warning <?= $warningText === '' ? 'is-empty' : '' ?>"><?= $escape($warningText) ?></div>

  <section class="dryer-tunnels" aria-live="polite">
    <?php $tunnelIndex = 0; ?>
    <?php foreach ($tunnels as $tunnelKey => $tunnel): ?>
      <article class="dryer-tunnel<?= $tunnelIndex === 0 ? ' is-active' : '' ?>" data-tunnel="<?= $escape($tunnelKey) ?>" aria-hidden="<?= $tunnelIndex === 0 ? 'false' : 'true' ?>">
        <header class="tunnel-heading">
          <div><span>SECADOR</span><h2><?= $escape($tunnel['label']) ?></h2></div>
          <span class="tunnel-state"><i class="fa-solid fa-circle-play"></i> Secado continuo</span>
        </header>

        <section class="process-grid" aria-label="Variables principales de <?= $escape($tunnel['label']) ?>">
          <section class="dryer-utilities">
            <h3><i class="fa-solid fa-gauge-high"></i> Servicios</h3>
            <div class="utility-grid">
              <?php foreach ((array)$tunnel['utilities'] as $field): ?><?php $renderField((array)$field, 'utility-field'); ?><?php endforeach; ?>
            </div>
          </section>
        </section>

        <section class="room-grid" aria-label="Variables por recámara de <?= $escape($tunnel['label']) ?>">
          <?php foreach ((array)$tunnel['rooms'] as $room): ?>
            <?php if (!empty($room['temperature']) || !empty($room['humidity'])): ?>
              <?php $hasHumidity = !empty($room['humidity']); ?>
              <article class="dryer-room <?= $hasHumidity ? 'room-paired' : 'room-single' ?>">
                <h3><?= $escape($room['label']) ?></h3>
                <div class="room-fields <?= $hasHumidity ? 'paired' : 'single' ?>">
                  <?php if (!empty($room['temperature'])): ?><?php $renderField((array)$room['temperature'], 'room-field temperature-field'); ?><?php endif; ?>
                  <?php if ($hasHumidity): ?><?php $renderField((array)$room['humidity'], 'room-field humidity-field'); ?><?php endif; ?>
                </div>
              </article>
            <?php endif; ?>
          <?php endforeach; ?>
        </section>
      </article>
      <?php $tunnelIndex++; ?>
    <?php endforeach; ?>
    <?php if ($showCorrectiveActions): ?>
      <article class="dryer-tunnel corrective-screen<?= $tunnelIndex === 0 ? ' is-active' : '' ?>" data-tunnel="acciones" aria-hidden="<?= $tunnelIndex === 0 ? 'false' : 'true' ?>">
        <?php correctiveActionsRender((array)($report['corrective_actions'] ?? []), (string)($report['corrective_warning'] ?? ''), 'secadores'); ?>
      </article>
    <?php endif; ?>
  </section>

  <?php if ($selectedTunnel === '' && count($tunnels) > 1): ?>
    <nav class="tunnel-pages" aria-label="Pantallas de secadores">
      <?php foreach ($tunnels as $tunnelKey => $tunnel): ?><span data-page="<?= $escape($tunnelKey) ?>"><?= $escape($tunnel['label']) ?></span><?php endforeach; ?>
      <span data-page="acciones">Acciones</span>
    </nav>
  <?php endif; ?>
</main>

<script src="../../assets/js/corrective-actions.js?v=<?= urlencode((string)(@filemtime(__DIR__ . '/../../assets/js/corrective-actions.js') ?: time())) ?>"></script>
<script>
(() => {
  const refreshMs = <?= (int)$report['refresh_ms'] ?>;
  const rotationMs = <?= (int)$report['rotation_ms'] ?>;
  const captureMode = <?= $captureMode ? 'true' : 'false' ?>;
  const fixedTunnel = <?= $selectedTunnel !== '' ? 'true' : 'false' ?>;
  const tunnels = Array.from(document.querySelectorAll('.dryer-tunnel'));
  let activeTunnel = 0;
  let rotationStartedAt = Date.now();
  let updating = false;

  const statusClasses = ['status-verde', 'status-amarillo', 'status-rojo', 'status-gris'];

  function collectFields(value, fields = {}) {
    if (!value || typeof value !== 'object') return fields;
    if (value.id && Object.prototype.hasOwnProperty.call(value, 'formatted')) fields[value.id] = value;
    Object.values(value).forEach(child => collectFields(child, fields));
    return fields;
  }

  function setDirection(card, field) {
    const oldCue = card.querySelector('.dryer-direction');
    if (oldCue) oldCue.remove();
    if (!['up', 'down'].includes(field.direction)) return;

    const action = field.direction === 'up' ? 'Subir' : 'Bajar';
    const cue = document.createElement('span');
    cue.className = `dryer-direction direction-${field.direction}`;
    cue.setAttribute('aria-label', `${action} ${field.adjustment || ''} para llegar a verde`);
    const icon = document.createElement('span');
    icon.className = 'dryer-direction-arrow';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = field.direction === 'up' ? '↑' : '↓';
    const copy = document.createElement('span');
    const verb = document.createElement('em');
    verb.textContent = action;
    const amount = document.createElement('b');
    amount.textContent = field.adjustment || '';
    copy.append(verb, amount);
    cue.append(icon, copy);
    card.append(cue);
  }

  function applyLiveData(data) {
    const fields = collectFields(data);
    document.querySelectorAll('[data-field-id]').forEach(card => {
      const field = fields[card.dataset.fieldId];
      if (!field) return;
      card.classList.remove(...statusClasses);
      card.classList.add('status-' + (['verde', 'amarillo', 'rojo'].includes(field.statusKey) ? field.statusKey : 'gris'));
      const value = card.querySelector('.value-text');
      if (value) value.textContent = field.formatted || '—';
      const unit = card.querySelector('.value-unit');
      if (unit) unit.textContent = field.unit && !['—', 'F.O'].includes(field.formatted) ? ` ${field.unit}` : '';
      const capturedAt = card.querySelector('.dryer-captured-at');
      if (capturedAt) capturedAt.textContent = field.ageLabel || 'Sin fecha';
      setDirection(card, field);
    });
    const timestamp = document.getElementById('report-timestamp');
    if (timestamp) timestamp.textContent = data.timestamp || '—';
    window.CorrectiveActions?.update(
      document.querySelector('[data-corrective-actions]'),
      data.corrective_actions || [],
      data.corrective_warning || ''
    );
  }

  async function refresh() {
    if (captureMode || updating || document.hidden) return;
    updating = true;
    try {
      const response = await fetch('live.php?t=' + Date.now(), {cache: 'no-store'});
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      applyLiveData(await response.json());
    } catch (error) {
      // Se conserva la última lectura visible cuando una actualización falla.
    } finally {
      updating = false;
    }
  }

  function showTunnel(index) {
    activeTunnel = index % tunnels.length;
    tunnels.forEach((tunnel, tunnelIndex) => {
      const active = tunnelIndex === activeTunnel;
      tunnel.classList.toggle('is-active', active);
      tunnel.setAttribute('aria-hidden', active ? 'false' : 'true');
    });
    document.querySelectorAll('[data-page]').forEach((page, pageIndex) => page.classList.toggle('is-active', pageIndex === activeTunnel));
    rotationStartedAt = Date.now();
  }

  function updateRotationLabel() {
    const label = document.getElementById('rotation-label');
    if (!label) return;
    const remaining = Math.max(0, Math.ceil((rotationMs - (Date.now() - rotationStartedAt)) / 1000));
    label.textContent = `Cambio en ${remaining} s`;
  }

  showTunnel(0);
  if (!captureMode) {
    refresh();
    window.setInterval(refresh, refreshMs);
    if (!fixedTunnel && tunnels.length > 1) {
      window.setInterval(() => showTunnel(activeTunnel + 1), rotationMs);
      window.setInterval(updateRotationLabel, 1000);
      updateRotationLabel();
    }
  }
})();
</script>
</body>
</html>
