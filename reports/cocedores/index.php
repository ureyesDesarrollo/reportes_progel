<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';
$report = require __DIR__ . '/build_report.php';
$captureMode = isset($_GET['capture']);
$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

$renderField = static function (array $field) use ($e): string {
    $status = in_array($field['statusKey'] ?? '', ['verde', 'amarillo', 'rojo'], true)
        ? (string)$field['statusKey']
        : 'gris';
    $value = (string)($field['formatted'] ?? '—');
    $unit = $value !== '—' && $value !== 'F.O.' ? trim((string)($field['unit'] ?? '')) : '';
    $direction = in_array($field['direction'] ?? '', ['up', 'down'], true) ? (string)$field['direction'] : '';
    $action = $direction === 'up' ? 'Subir' : ($direction === 'down' ? 'Bajar' : '');
    $icon = $direction === 'up' ? 'fa-arrow-up-long' : 'fa-arrow-down-long';

    $html = '<div class="cc-field status-' . $e($status) . '" data-field-key="' . $e($field['key'] ?? '') . '">';
    $html .= '<span class="cc-label">' . $e($field['label'] ?? '') . '</span>';
    $html .= '<div class="cc-reading"><strong class="cc-value">' . $e($value);
    if ($unit !== '') {
        $html .= ' <small>' . $e($unit) . '</small>';
    }
    $html .= '</strong>';
    $html .= '<span class="cc-direction' . ($direction !== '' ? ' is-visible' : '') . '">';
    $html .= '<i class="fa-solid ' . $e($icon) . '"></i><span><em>' . $e($action) . '</em><b>' . $e($field['adjustment'] ?? '') . '</b></span></span>';
    $html .= '</div></div>';
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
  <link rel="stylesheet" href="../../assets/css/cocedores.css">
  <link rel="stylesheet" href="../../assets/css/report-responsive.css?v=<?= urlencode((string)(@filemtime(__DIR__ . '/../../assets/css/report-responsive.css') ?: time())) ?>">
</head>
<body class="<?= $captureMode ? 'capture-mode' : '' ?>">
  <main class="cocedores-report" data-report-responsive data-live-url="live.php" data-refresh-ms="<?= (int)$report['refreshMs'] ?>" data-capture="<?= $captureMode ? '1' : '0' ?>">
    <header class="cc-header">
      <div class="cc-heading">
        <span class="cc-heading-icon"><i class="fa-solid fa-fire-burner"></i></span>
        <div><h1><?= $e($report['title']) ?></h1><p><?= $e($report['subtitle']) ?></p></div>
      </div>
      <div class="cc-header-meta">
        <span><i class="fa-solid fa-wave-square"></i> AVEVA <b data-timestamp="aveva"><?= $e($report['avevaTimestamp']) ?></b></span>
        <span><i class="fa-solid fa-database"></i> Bitácora <b data-timestamp="mysql"><?= $e($report['mysqlTimestamp']) ?></b></span>
        <a class="cc-back" href="../"><i class="fa-solid fa-arrow-left"></i> Reportes</a>
      </div>
    </header>

    <div class="cc-warning<?= trim((string)$report['warning']) === '' ? ' is-hidden' : '' ?>" data-warning><?= $e($report['warning']) ?></div>

    <section class="cc-summary" aria-label="Temperaturas generales de Cocedores">
      <?php foreach ((array)$report['headerMetrics'] as $field): ?>
        <article class="cc-summary-card" data-header-key="<?= $e($field['key']) ?>">
          <span class="cc-summary-icon"><i class="fa-solid fa-temperature-three-quarters"></i></span>
          <?= $renderField((array)$field) ?>
        </article>
      <?php endforeach; ?>
    </section>

    <section class="cc-grid" aria-label="Estado de los nueve Cocedores">
      <?php foreach ((array)$report['equipment'] as $equipment): ?>
        <article class="cc-unit" data-equipment-key="<?= $e($equipment['key']) ?>">
          <header class="cc-unit-head">
            <h2><?= $e($equipment['label']) ?></h2>
            <span class="cc-state"><?= $e($equipment['statusLabel']) ?></span>
          </header>
          <div class="cc-fields">
            <?php foreach ((array)$equipment['fields'] as $field): ?>
              <?= $renderField((array)$field) ?>
            <?php endforeach; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  </main>
  <script src="../../assets/js/cocedores.js"></script>
</body>
</html>
