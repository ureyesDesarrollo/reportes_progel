<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';
$report = require __DIR__ . '/build_report.php';
$captureMode = isset($_GET['capture']);
$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$fields = (array)($report['fields'] ?? []);
$clarifierFields = [];
$integrationFields = [];

foreach ($fields as $field) {
    if (str_ends_with((string)($field['key'] ?? ''), '_integracion')) {
        $integrationFields[] = (array)$field;
        continue;
    }
    $clarifierFields[] = (array)$field;
}

$renderField = static function (array $field) use ($e): string {
    $status = in_array($field['statusKey'] ?? '', ['verde', 'amarillo', 'rojo'], true) ? (string)$field['statusKey'] : 'gris';
    $value = (string)($field['formatted'] ?? '—');
    $unit = $value !== '—' && $value !== 'F.O.' ? trim((string)($field['unit'] ?? '')) : '';
    $direction = in_array($field['direction'] ?? '', ['up', 'down'], true) ? (string)$field['direction'] : '';
    $action = $direction === 'up' ? 'Subir' : ($direction === 'down' ? 'Bajar' : '');
    $icon = $direction === 'up' ? 'fa-arrow-up-long' : 'fa-arrow-down-long';
    $html = '<article class="cl-field status-' . $e($status) . '" data-field-key="' . $e($field['key'] ?? '') . '">';
    $html .= '<header><span class="cl-label">' . $e($field['label'] ?? '') . '</span></header>';
    $html .= '<div class="cl-reading"><strong class="cl-value">' . $e($value);
    if ($unit !== '') $html .= ' <small>' . $e($unit) . '</small>';
    $html .= '</strong><span class="cl-direction' . ($direction !== '' ? ' is-visible' : '') . '"><i class="fa-solid ' . $e($icon) . '"></i><span><em>' . $e($action) . '</em><b>' . $e($field['adjustment'] ?? '') . '</b></span></span></div></article>';
    return $html;
};
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $e($report['title']) ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/clarificadores.css">
  <link rel="stylesheet" href="../../assets/css/report-responsive.css?v=<?= urlencode((string)(@filemtime(__DIR__ . '/../../assets/css/report-responsive.css') ?: time())) ?>">
</head>
<body class="<?= $captureMode ? 'capture-mode' : '' ?>">
  <main class="clarificador-report" data-report-responsive data-live-url="live.php" data-refresh-ms="<?= (int)$report['refreshMs'] ?>" data-capture="<?= $captureMode ? '1' : '0' ?>">
    <header class="cl-main-header">
      <div class="cl-heading"><span class="cl-heading-icon"><i class="fa-solid fa-filter"></i></span><div><h1><?= $e($report['title']) ?></h1><p><?= $e($report['subtitle']) ?></p></div></div>
      <div class="cl-meta">
        <span class="cl-state"><?= $e($report['statusLabel']) ?></span>
        <a class="cl-back" href="../"><i class="fa-solid fa-arrow-left"></i> Reportes</a>
      </div>
    </header>
    <div class="cl-warning<?= trim((string)$report['warning']) === '' ? ' is-hidden' : '' ?>" data-warning><?= $e($report['warning']) ?></div>
    <section class="cl-grid" aria-label="Parámetros del Clarificador">
      <?php foreach ($clarifierFields as $field): ?>
        <?= $renderField($field) ?>
      <?php endforeach; ?>
      <?php if ($integrationFields !== []): ?>
        <section class="cl-integration-group" aria-label="Parámetros de Integración">
          <h2><i class="fa-solid fa-code-merge"></i> Integración</h2>
          <div class="cl-integration-fields">
            <?php foreach ($integrationFields as $field): ?>
              <?= $renderField($field) ?>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>
    </section>
  </main>
  <script src="../../assets/js/clarificadores.js"></script>
</body>
</html>
