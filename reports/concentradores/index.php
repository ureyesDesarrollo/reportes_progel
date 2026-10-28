<?php

declare(strict_types=1);

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$config = require __DIR__ . '/config.php';

try {
  $report = require __DIR__ . '/build_report.php';
} catch (Throwable $e) {
  error_log('[reportes-progel:concentradores] ' . $e->getMessage());
  $report = [
    'titulo' => 'Concentradores',
    'concentradores' => [],
    'meta' => ['intervaloActualizacion' => 60000, 'warnings' => ['La información no está disponible temporalmente.']],
    'version' => time(),
  ];
}

$titulo = (string)($report['titulo'] ?? 'Concentradores');
$concentradores = (array)($report['concentradores'] ?? []);
$visibleEquipment = array_fill_keys((array)($config['equipos_vista_principal'] ?? []), true);
$mainConcentradores = $visibleEquipment !== []
  ? array_intersect_key($concentradores, $visibleEquipment)
  : array_diff_key($concentradores, ['invertido' => true]);
$meta = (array)($report['meta'] ?? []);
$version = (int)($report['version'] ?? time());
$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$metricClass = static function (array $metric): string {
  $class = (string)($metric['status']['class'] ?? 'unavailable');
  return preg_match('/^[a-z0-9-]+$/', $class) === 1 ? $class : 'unavailable';
};
$renderDirection = static function (array $metric) use ($e): string {
  $direction = in_array(($metric['direction'] ?? ''), ['up', 'down'], true)
    ? (string)$metric['direction']
    : '';
  $adjustment = $direction !== '' ? trim((string)($metric['adjustment'] ?? '')) : '';
  $action = $direction === 'up' ? 'Subir' : ($direction === 'down' ? 'Bajar' : '');
  $arrow = $direction === 'up' ? '↑' : ($direction === 'down' ? '↓' : '');
  $label = $direction !== '' ? $action . ' ' . $adjustment . ' para llegar a verde' : 'En rango verde';

  return '<span class="concentradores-direction' . ($direction !== '' ? ' is-visible direction-' . $e($direction) : '')
    . '" title="' . $e($label) . '" aria-label="' . $e($label) . '">'
    . '<strong data-direction-arrow>' . $e($arrow) . '</strong>'
    . '<span><em data-direction-action>' . $e($action) . '</em><b data-direction-value>' . $e($adjustment) . '</b></span>'
    . '</span>';
};
$invertidoMetricGroups = [
  'General' => [
    'flujo_entrada_evaporador',
    'flujo_salida_evaporador',
    'temperatura_precalentamiento',
    'nivel_tanque_alimentacion',
  ],
  'Etapa 1' => [
    'flujo_etapa_1_2',
    'temperatura_etapa_1',
    'vacio_etapa_1',
    'presion_etapa_1',
    'nivel_etapa_1',
    'valvula_control_temperatura_etapa_1',
    'valvula_control_nivel_etapa_1',
  ],
  'Etapa 2' => [
    'flujo_etapa_2_3',
    'vacio_etapa_2',
    'presion_etapa_2',
    'nivel_etapa_2',
    'moyno_control_nivel_etapa_2',
  ],
  'Etapa 3' => [
    'vacio_etapa_3',
    'presion_etapa_3',
    'nivel_etapa_3',
    'moyno_control_nivel_etapa_3',
  ],
  'Agua / vapor' => [
    'temperatura_salida_agua',
    'presion_agua_enfriamiento',
    'presion_vapor',
  ],
  'Controles' => [
    'valvula_control_flujo_entrada',
    'valvula_control_precalentamiento',
    'valvula_control_presion',
  ],
];
$primaryMetricKeys = array_fill_keys(['flujo', 'temperatura', 'solidos_entrada', 'solidos_salida'], true);
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title><?= $e($titulo) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/dashboard.css?v=<?= urlencode((string)$version) ?>">
  <script src="../../assets/js/display-mode.js?v=<?= urlencode((string)max($version, (int)(@filemtime(__DIR__ . '/../../assets/js/display-mode.js') ?: 0))) ?>"></script>
  <style>
    :root {
      color-scheme: dark;
      --cc-bg: #08131b;
      --cc-panel: #13212b;
      --cc-inner: #1b2b36;
      --cc-line: #314451;
      --cc-text: #f4f7fa;
      --cc-muted: #cbd8e2;
      --cc-blue: #163354;
      --cc-green: #2e8b57;
      --cc-yellow: #facc15;
      --cc-red: #c94436;
      --cc-gray: #64748b;
    }
    * { box-sizing: border-box; }
    html, body { margin: 0; min-width: 1280px; min-height: 100%; background: var(--cc-bg); }
    body { color: var(--cc-text); font-family: Inter, Arial, Helvetica, sans-serif; }
    .dashboard {
      display: flex;
      width: 100vw !important;
      max-width: none !important;
      min-width: 1280px;
      min-height: 100vh;
      flex-direction: column;
      margin: 0 !important;
      padding: 8px !important;
      background: var(--cc-bg);
    }
    .concentradores-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 14px;
      flex: 0 0 auto;
      margin-bottom: 7px;
    }
    .concentradores-heading { display: flex; align-items: center; min-width: 0; }
    .concentradores-heading h1 { margin: 0; font-size: clamp(28px, 2.2vw, 40px); line-height: 1; }
    .concentradores-heading p { margin: 4px 0 0; color: var(--cc-muted); font-size: clamp(13px, 1vw, 17px); }
    .concentradores-header-actions { display: flex; align-items: center; gap: 9px; }
    .concentradores-count, .back-btn {
      padding: 8px 13px;
      border: 0;
      border-radius: 999px;
      font-size: 13px;
      font-weight: 800;
      white-space: nowrap;
    }
    .concentradores-count { color: #7dd3fc; background: #203957; }
    .back-btn { display: inline-flex; align-items: center; gap: 7px; color: var(--cc-text); background: var(--cc-inner); text-decoration: none; }
    .concentradores-exec-warning {
      display: flex;
      align-items: center;
      gap: 0;
      margin-bottom: 9px;
      padding: 9px 12px;
      border-radius: 10px;
      color: #111827;
      background: var(--cc-yellow);
      font-size: 13px;
      font-weight: 800;
    }
    .concentradores-exec-grid {
      display: grid;
      min-height: 0;
      flex: 1 1 auto;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      grid-template-rows: repeat(2, minmax(0, 1fr));
      gap: 8px;
      align-items: stretch;
    }
    .concentradores-exec-panel {
      min-width: 0;
      overflow: hidden;
      padding: 7px;
      border: 1px solid var(--cc-line);
      border-radius: 15px;
      background: var(--cc-panel);
    }
    .concentradores-exec-panel[data-concentrator="invertido"] { display: none; grid-column: 1 / -1; }
    .concentradores-exec-grid.is-invertido-screen {
      grid-template-columns: minmax(0, 1fr);
      grid-template-rows: minmax(0, 1fr);
    }
    .concentradores-exec-grid.is-invertido-screen > .concentradores-exec-panel:not([data-concentrator="invertido"]) { display: none; }
    .concentradores-exec-grid.is-invertido-screen > .concentradores-exec-panel[data-concentrator="invertido"] {
      display: block;
      height: 100%;
    }
    .concentradores-exec-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin: -7px -7px 6px;
      padding: 7px 10px;
      border-bottom: 1px solid var(--cc-line);
      background: #1d3442;
    }
    .concentradores-exec-head.ok,
    .concentradores-exec-head.warning,
    .concentradores-exec-head.danger,
    .concentradores-exec-head.neutral,
    .concentradores-exec-head.info,
    .concentradores-exec-head.unavailable { color: var(--cc-text); background: var(--cc-inner); }
    .concentradores-exec-head h2 { margin: 0; color: inherit; font-size: clamp(23px, 1.55vw, 30px); line-height: 1; }
    .concentradores-exec-head span {
      display: inline-flex;
      min-width: 38px;
      min-height: 22px;
      align-items: center;
      justify-content: center;
      padding: 4px 9px;
      border-radius: 999px;
      color: var(--cc-text);
      background: var(--cc-gray);
      font-size: 12px;
      font-weight: 900;
    }
    .concentradores-exec-head span.is-offline { background: rgba(15,23,42,.34); }
    .concentradores-exec-head.ok span { background: var(--cc-green); }
    .concentradores-exec-head.warning span { color: #111827; background: var(--cc-yellow); }
    .concentradores-exec-head.danger span { background: var(--cc-red); }
    .concentradores-exec-head.neutral span,
    .concentradores-exec-head.info span { background: var(--cc-blue); }
    .concentradores-exec-head.unavailable span { background: var(--cc-gray); }
    .concentradores-section-title {
      margin: 5px 2px 4px;
      color: var(--cc-muted);
      font-size: 12px;
      font-weight: 900;
      letter-spacing: .08em;
      text-transform: uppercase;
    }
    .concentradores-primary-metrics,
    .concentradores-secondary-metrics {
      display: grid;
      gap: 5px;
    }
    .concentradores-primary-metrics { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .concentradores-secondary-metrics { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .concentradores-secondary-metrics[data-count="10"] { grid-template-columns: repeat(5, minmax(0, 1fr)); }
    .concentradores-secondary-metrics[data-count="9"] { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .concentradores-secondary-metrics[data-count="11"] { grid-template-columns: repeat(12, minmax(0, 1fr)); }
    .concentradores-secondary-metrics[data-count="11"] > .concentradores-exec-metric { grid-column: span 3; }
    .concentradores-secondary-metrics[data-count="11"] > .concentradores-exec-metric:nth-last-child(-n + 3) { grid-column: span 4; }
    .concentradores-exec-metric {
      display: grid;
      grid-template-columns: minmax(0, 1fr);
      align-items: center;
      gap: 0;
      min-width: 0;
      min-height: 70px;
      padding: 6px 7px;
      border: 1px solid #28577e;
      border-radius: 11px;
      color: var(--cc-text);
      background: var(--cc-blue);
      transition: color .2s ease, background-color .2s ease;
    }
    .concentradores-exec-metric.ok { border-color: #43a36d; background: var(--cc-green); }
    .concentradores-exec-metric.warning { border-color: var(--cc-yellow); color: #111827; background: var(--cc-yellow); }
    .concentradores-exec-metric.danger { border-color: #e35d50; background: var(--cc-red); }
    .concentradores-exec-metric.neutral,
    .concentradores-exec-metric.info { border-color: #2563eb; background: var(--cc-blue); }
    .concentradores-exec-metric.unavailable { border-color: var(--cc-gray); background: #334653; }
    .concentradores-exec-metric-body { min-width: 0; }
    .concentradores-exec-metric-label {
      display: flex;
      align-items: flex-start;
      justify-content: flex-start;
      gap: 0;
      min-width: 0;
      font-size: clamp(11px, .68vw, 13px);
      font-weight: 900;
      line-height: 1.08;
      text-transform: uppercase;
    }
    .concentradores-exec-metric-label > [data-field="label"] {
      min-width: 0;
      overflow-wrap: normal;
    }
    .concentradores-exec-metric-value {
      max-width: 100%;
      overflow: hidden;
      margin-top: 5px;
      font-size: clamp(20px, 1.45vw, 28px);
      font-variant-numeric: tabular-nums;
      font-weight: 900;
      line-height: 1;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .concentradores-exec-reading {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      min-width: 0;
    }
    .concentradores-direction {
      display: none;
      flex: 0 0 auto;
      min-width: 78px;
      height: 52px;
      padding: 4px 8px;
      align-items: center;
      justify-content: center;
      gap: 5px;
      border: 2px solid #fff;
      border-radius: 10px;
      color: #fff;
      background: #172c3a;
      box-shadow: 0 0 0 2px rgba(255,255,255,.15);
    }
    .concentradores-direction.is-visible { display: inline-flex; }
    .concentradores-direction > strong {
      font-size: 34px;
      font-weight: 950;
      line-height: .8;
    }
    .concentradores-direction > span { display: flex; flex-direction: column; align-items: flex-start; line-height: 1; }
    .concentradores-direction em {
      color: #fff;
      font-size: 10px;
      font-style: normal;
      font-weight: 950;
      letter-spacing: .04em;
      text-transform: uppercase;
    }
    .concentradores-direction b { color: #fff; font-size: 18px; font-variant-numeric: tabular-nums; font-weight: 950; }
    .concentradores-primary-metrics .concentradores-direction { min-width: 74px; height: 62px; padding-inline: 5px; gap: 3px; }
    .concentradores-primary-metrics .concentradores-direction > strong { font-size: 38px; }
    .concentradores-primary-metrics .concentradores-direction em { font-size: 9px; }
    .concentradores-primary-metrics .concentradores-direction b { font-size: 19px; }
    .concentradores-primary-metrics .concentradores-exec-metric:has(.concentradores-direction.is-visible) .concentradores-exec-metric-value {
      flex: 1 1 auto;
      min-width: 0;
      font-size: clamp(24px, 1.4vw, 27px);
    }
    .concentradores-primary-metrics .concentradores-exec-metric[data-metric^="solidos"]:has(.concentradores-direction.is-visible) .concentradores-exec-metric-value {
      font-size: 22px;
    }
    .concentradores-primary-metrics .concentradores-exec-metric { min-height: 104px; padding: 8px 10px; }
    .concentradores-primary-metrics .concentradores-exec-metric-label { font-size: clamp(15px, 1vw, 19px); }
    .concentradores-primary-metrics .concentradores-exec-metric-value { font-size: clamp(35px, 2.5vw, 48px); }
    .concentradores-secondary-metrics .concentradores-exec-metric { min-height: 72px; }
    .concentradores-invertido-layout {
      display: grid;
      height: calc(100% - 44px);
      grid-template-columns: repeat(3, minmax(0, 1fr));
      grid-template-rows: repeat(2, minmax(0, 1fr));
      gap: 8px;
    }
    .concentradores-stage {
      min-width: 0;
      padding: 8px;
      border: 1px solid var(--cc-line);
      border-radius: 12px;
      background: #0f1d26;
    }
    .concentradores-stage h3 {
      display: flex;
      align-items: center;
      gap: 7px;
      margin: 0 0 7px;
      font-size: 13px;
      font-weight: 900;
      text-transform: uppercase;
    }
    .concentradores-stage h3::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: #38bdf8; }
    .concentradores-stage-metrics { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 7px; }
    .concentradores-exec-panel[data-concentrator="invertido"] .concentradores-exec-metric { min-height: 82px; }
    .concentradores-exec-panel[data-concentrator="invertido"] .concentradores-exec-metric-value { font-size: clamp(19px, 1.35vw, 25px); }
    body.display-mode .dashboard { padding: 8px; }
    @media (min-width: 1280px) and (min-height: 900px) {
      html, body { height: 100%; overflow: hidden; }
      .dashboard { height: 100vh; min-height: 0; overflow: hidden; }
    }
    @media (max-width: 1599px) {
      .concentradores-primary-metrics .concentradores-exec-metric-label { font-size: 14px; }
      .concentradores-primary-metrics .concentradores-exec-metric-value { font-size: 30px; }
    }
  </style>
  <link rel="stylesheet" href="../../assets/css/report-responsive.css?v=<?= urlencode((string)(@filemtime(__DIR__ . '/../../assets/css/report-responsive.css') ?: time())) ?>">
</head>

<body>
  <div class="dashboard" data-report-responsive>
    <header class="concentradores-header">
      <div class="concentradores-heading">
        <div>
          <h1 data-screen-title><?= $e($titulo) ?></h1>
          <p>Monitoreo en tiempo real · actualización cada <?= $e((int)ceil(((int)($meta['intervaloActualizacion'] ?? 60000)) / 1000)) ?> s</p>
        </div>
      </div>
      <div class="concentradores-header-actions">
        <span class="concentradores-count" data-screen-count><?= count($mainConcentradores) ?> equipos</span>
        <a href="../index.php" class="back-btn">Regresar</a>
      </div>
    </header>

    <div data-warnings>
      <?php foreach ((array)($meta['warnings'] ?? []) as $warning): ?>
        <div class="concentradores-exec-warning"><?= $e($warning) ?></div>
      <?php endforeach; ?>
    </div>

    <section class="concentradores-exec-grid" aria-label="Lecturas por concentrador">
      <?php foreach ($concentradores as $concentrador): ?>
        <?php $panelStatusClass = $metricClass(['status' => (array)($concentrador['status'] ?? [])]); ?>
        <article class="concentradores-exec-panel" data-concentrator="<?= $e($concentrador['key'] ?? '') ?>">
          <header class="concentradores-exec-head <?= $e($panelStatusClass) ?>">
            <h2><?= $e($concentrador['nombre'] ?? 'Concentrador') ?></h2>
            <span data-field="operation-status" class="<?= !empty($concentrador['fuera_operacion']) ? 'is-offline' : '' ?>">
              <?= $e(!empty($concentrador['fuera_operacion']) ? 'FO' : ($concentrador['status']['label'] ?? 'Lectura')) ?>
            </span>
          </header>
          <?php $metricas = (array)($concentrador['metricas'] ?? []); ?>
          <?php if ((string)($concentrador['key'] ?? '') === 'invertido'): ?>
            <div class="concentradores-invertido-layout">
              <?php $renderedMetrics = []; ?>
              <?php foreach ($invertidoMetricGroups as $groupTitle => $groupMetrics): ?>
                <section class="concentradores-stage">
                  <h3><?= $e($groupTitle) ?></h3>
                  <div class="concentradores-stage-metrics">
                    <?php foreach ($groupMetrics as $metricKey): ?>
                      <?php if (!isset($metricas[$metricKey])) continue; ?>
                      <?php $metric = (array)$metricas[$metricKey]; ?>
                      <?php $renderedMetrics[$metricKey] = true; ?>
                      <div class="concentradores-exec-metric <?= $e($metricClass($metric)) ?>" data-metric="<?= $e($metricKey) ?>">
                        <div class="concentradores-exec-metric-body">
                          <div class="concentradores-exec-metric-label">
                            <span data-field="label"><?= $e($metric['label'] ?? $metricKey) ?></span>
                          </div>
                          <div class="concentradores-exec-reading"><div class="concentradores-exec-metric-value" data-field="value"><?= $e($metric['formatted'] ?? '-') ?></div><?= $renderDirection($metric) ?></div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </section>
              <?php endforeach; ?>
              <?php $remainingMetrics = array_diff_key($metricas, $renderedMetrics); ?>
              <?php if (!empty($remainingMetrics)): ?>
                <section class="concentradores-stage">
                  <h3>Otros</h3>
                  <div class="concentradores-stage-metrics">
                    <?php foreach ($remainingMetrics as $metric): ?>
                      <?php $metric = (array)$metric; ?>
                      <?php $metricKey = (string)($metric['key'] ?? ''); ?>
                      <div class="concentradores-exec-metric <?= $e($metricClass($metric)) ?>" data-metric="<?= $e($metricKey) ?>">
                        <div class="concentradores-exec-metric-body">
                          <div class="concentradores-exec-metric-label">
                            <span data-field="label"><?= $e($metric['label'] ?? $metricKey) ?></span>
                          </div>
                          <div class="concentradores-exec-reading"><div class="concentradores-exec-metric-value" data-field="value"><?= $e($metric['formatted'] ?? '-') ?></div><?= $renderDirection($metric) ?></div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </section>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <?php $primaryMetrics = array_intersect_key($metricas, $primaryMetricKeys); ?>
            <?php $secondaryMetrics = array_diff_key($metricas, $primaryMetricKeys); ?>
            <h3 class="concentradores-section-title">Proceso</h3>
            <div class="concentradores-primary-metrics">
              <?php foreach ($primaryMetrics as $metric): ?>
                <?php $metricKey = (string)($metric['key'] ?? ''); ?>
                <div class="concentradores-exec-metric <?= $e($metricClass((array)$metric)) ?>" data-metric="<?= $e($metricKey) ?>">
                  <div class="concentradores-exec-metric-body">
                    <div class="concentradores-exec-metric-label">
                      <span data-field="label"><?= $e($metric['label'] ?? $metricKey) ?></span>
                    </div>
                    <div class="concentradores-exec-reading"><div class="concentradores-exec-metric-value" data-field="value"><?= $e($metric['formatted'] ?? '-') ?></div><?= $renderDirection((array)$metric) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <?php if ($secondaryMetrics !== []): ?>
              <h3 class="concentradores-section-title">Servicios y control</h3>
              <div class="concentradores-secondary-metrics" data-count="<?= count($secondaryMetrics) ?>">
                <?php foreach ($secondaryMetrics as $metric): ?>
                  <?php $metricKey = (string)($metric['key'] ?? ''); ?>
                  <div class="concentradores-exec-metric <?= $e($metricClass((array)$metric)) ?>" data-metric="<?= $e($metricKey) ?>">
                    <div class="concentradores-exec-metric-body">
                      <div class="concentradores-exec-metric-label">
                        <span data-field="label"><?= $e($metric['label'] ?? $metricKey) ?></span>
                      </div>
                      <div class="concentradores-exec-reading"><div class="concentradores-exec-metric-value" data-field="value"><?= $e($metric['formatted'] ?? '-') ?></div><?= $renderDirection((array)$metric) ?></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </section>
  </div>

  <script>
    const refreshMs = <?= (int)($meta['intervaloActualizacion'] ?? 60000) ?>;
    const screenRotationMs = <?= max(1000, (int)($config['rotacion_pantallas_ms'] ?? 120000)) ?>;
    const dashboardGrid = document.querySelector('.concentradores-exec-grid');
    const screenTitle = document.querySelector('[data-screen-title]');
    const screenCount = document.querySelector('[data-screen-count]');
    const hasInvertidoScreen = Boolean(dashboardGrid?.querySelector('[data-concentrator="invertido"]'));
    let invertidoVisible = new URLSearchParams(window.location.search).get('pantalla') === 'invertido' && hasInvertidoScreen;

    function showScreen(showInvertido) {
      invertidoVisible = Boolean(showInvertido && hasInvertidoScreen);
      dashboardGrid?.classList.toggle('is-invertido-screen', invertidoVisible);
      if (screenTitle) screenTitle.textContent = invertidoVisible ? 'Concentrador Invertido' : <?= json_encode($titulo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
      if (screenCount) screenCount.textContent = invertidoVisible ? '1 equipo' : <?= json_encode(count($mainConcentradores) . ' equipos', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    }

    showScreen(invertidoVisible);
    if (hasInvertidoScreen) {
      window.setInterval(() => showScreen(!invertidoVisible), screenRotationMs);
    }

    function metricClass(metric) {
      const statusClass = metric?.status?.class || 'unavailable';
      return ['ok', 'warning', 'danger', 'neutral', 'info', 'unavailable'].includes(statusClass) ? statusClass : 'unavailable';
    }

    function updateDirection(card, direction, adjustment) {
      const cue = card?.querySelector('.concentradores-direction');
      if (!cue) return;

      const validDirection = direction === 'up' || direction === 'down' ? direction : '';
      const amount = validDirection ? String(adjustment || '') : '';
      const action = validDirection === 'up' ? 'Subir' : (validDirection === 'down' ? 'Bajar' : '');
      const label = validDirection ? `${action} ${amount} para llegar a verde` : 'En rango verde';

      cue.classList.toggle('is-visible', validDirection !== '');
      cue.classList.toggle('direction-up', validDirection === 'up');
      cue.classList.toggle('direction-down', validDirection === 'down');
      cue.title = label;
      cue.setAttribute('aria-label', label);

      const arrow = cue.querySelector('[data-direction-arrow]');
      const actionNode = cue.querySelector('[data-direction-action]');
      const valueNode = cue.querySelector('[data-direction-value]');
      if (arrow) arrow.textContent = validDirection === 'up' ? '↑' : (validDirection === 'down' ? '↓' : '');
      if (actionNode) actionNode.textContent = action;
      if (valueNode) valueNode.textContent = amount;
    }

    function updateWarnings(warnings) {
      const container = document.querySelector('[data-warnings]');
      if (!container) return;
      container.innerHTML = '';
      (warnings || []).forEach((warning) => {
        const item = document.createElement('div');
        item.className = 'concentradores-exec-warning';
        item.textContent = warning;
        container.appendChild(item);
      });
    }

    function updateReport(report) {
      updateWarnings(report?.meta?.warnings || []);
      const concentradores = report?.concentradores || {};

      Object.values(concentradores).forEach((concentrador) => {
        const panel = document.querySelector(`[data-concentrator="${CSS.escape(concentrador.key)}"]`);
        if (!panel) return;

        const panelHead = panel.querySelector('.concentradores-exec-head');
        if (panelHead) panelHead.className = `concentradores-exec-head ${metricClass({ status: concentrador.status || {} })}`;
        const operationStatus = panel.querySelector('[data-field="operation-status"]');
        if (operationStatus) {
          operationStatus.textContent = concentrador.fuera_operacion ? 'FO' : (concentrador?.status?.label || 'Lectura');
          operationStatus.classList.toggle('is-offline', Boolean(concentrador.fuera_operacion));
        }

        Object.values(concentrador.metricas || {}).forEach((metric) => {
          const card = panel.querySelector(`[data-metric="${CSS.escape(metric.key)}"]`);
          if (!card) return;

          card.className = `concentradores-exec-metric ${metricClass(metric)}`;
          const label = card.querySelector('[data-field="label"]');
          const value = card.querySelector('[data-field="value"]');
          if (label) label.textContent = metric.label || metric.key || '';
          if (value) value.textContent = metric.formatted || '-';
          updateDirection(card, metric.direction, metric.adjustment);
        });
      });
    }

    async function refreshReport() {
      try {
        const response = await fetch(`data.php?t=${Date.now()}`, { cache: 'no-store' });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        updateReport(await response.json());
      } catch (error) {
        updateWarnings([`No se pudo actualizar concentradores: ${error.message}`]);
      }
    }

    window.setInterval(refreshReport, refreshMs);
  </script>
</body>

</html>
