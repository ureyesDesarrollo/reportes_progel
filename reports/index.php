<?php

declare(strict_types=1);

$registry = require __DIR__ . '/../config/reports_registry.php';
$reports = array_values(array_filter(
    (array)($registry['reports'] ?? []),
    static fn(array $report): bool => !isset($report['enabled']) || $report['enabled'] === true
));
$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

$groupsByKey = [];
foreach ((array)($registry['groups'] ?? []) as $groupKey => $group) {
    $groupsByKey[(string)$groupKey] = array_merge((array)$group, [
        'key' => (string)$groupKey,
        'subgroups' => [],
        'direct_reports' => [],
        'report_count' => 0,
    ]);
}

$subgroupsByKey = [];
foreach ((array)($registry['subgroups'] ?? []) as $subgroupKey => $subgroup) {
    $groupKey = trim((string)($subgroup['group'] ?? ''));
    if (!isset($groupsByKey[$groupKey])) {
        continue;
    }

    $subgroupsByKey[(string)$subgroupKey] = array_merge((array)$subgroup, [
        'key' => (string)$subgroupKey,
        'group' => $groupKey,
        'reports' => [],
    ]);
}

foreach ($reports as $report) {
    $subgroupKey = trim((string)($report['subgroup'] ?? ''));
    if ($subgroupKey !== '' && isset($subgroupsByKey[$subgroupKey])) {
        $groupKey = (string)$subgroupsByKey[$subgroupKey]['group'];
        $subgroupsByKey[$subgroupKey]['reports'][] = $report;
        $groupsByKey[$groupKey]['report_count']++;
        continue;
    }

    foreach ((array)($report['groups'] ?? []) as $rawGroupKey) {
        $groupKey = trim((string)$rawGroupKey);
        if (isset($groupsByKey[$groupKey])) {
            $groupsByKey[$groupKey]['direct_reports'][] = $report;
            $groupsByKey[$groupKey]['report_count']++;
        }
    }
}

foreach ($subgroupsByKey as $subgroupKey => $subgroup) {
    if (empty($subgroup['reports'])) {
        continue;
    }
    $groupKey = (string)$subgroup['group'];
    $groupsByKey[$groupKey]['subgroups'][$subgroupKey] = $subgroup;
}

$groupsByKey = array_filter(
    $groupsByKey,
    static fn(array $group): bool => (int)$group['report_count'] > 0
);
$groups = array_values($groupsByKey);

$selectedGroupKey = trim((string)($_GET['grupo'] ?? ''));
$selectedSubgroupKey = trim((string)($_GET['subgrupo'] ?? ''));
$selectedGroup = $groupsByKey[$selectedGroupKey] ?? null;
$selectedSubgroup = null;
if ($selectedGroup !== null && isset($selectedGroup['subgroups'][$selectedSubgroupKey])) {
    $selectedSubgroup = $selectedGroup['subgroups'][$selectedSubgroupKey];
}

if ($selectedGroup === null) {
    $selectedGroupKey = '';
    $selectedSubgroupKey = '';
} elseif ($selectedSubgroup === null) {
    $selectedSubgroupKey = '';
}

if ($selectedSubgroup !== null) {
    $visibleCardCount = count($selectedSubgroup['reports']);
    $countLabel = 'reporte(s)';
} elseif ($selectedGroup !== null) {
    $visibleCardCount = count($selectedGroup['subgroups']) + count($selectedGroup['direct_reports']);
    $countLabel = count($selectedGroup['subgroups']) > 0 && empty($selectedGroup['direct_reports'])
        ? 'subgrupo(s)'
        : 'elemento(s)';
} else {
    $visibleCardCount = count($groups);
    $countLabel = 'grupo(s)';
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Centro de Reportes | Progel</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <script src="../assets/js/display-mode.js?v=<?= urlencode((string)(@filemtime(__DIR__ . '/../assets/js/display-mode.js') ?: time())) ?>"></script>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { min-height: 100vh; color: #0f172a; background: linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%); font-family: Inter, Arial, Helvetica, sans-serif; }
    .page { width: 100%; min-height: 100vh; padding: clamp(24px, 3vw, 48px); }
    .hero { position: relative; display: flex; align-items: center; justify-content: space-between; gap: 32px; margin-bottom: 24px; padding: clamp(22px, 2.4vw, 34px); overflow: hidden; border-radius: 26px; background: linear-gradient(120deg, #ffffff 0%, #f7fbfa 64%, #e5f3f1 100%); box-shadow: 0 12px 32px rgba(15, 23, 42, .07); }
    .hero::after { position: absolute; right: -70px; bottom: -125px; width: 310px; height: 310px; border-radius: 50%; background: rgba(15, 118, 110, .055); content: ''; }
    .hero-copy { position: relative; z-index: 1; min-width: 0; }
    .hero-kicker { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 10px; color: #0f766e; font-size: .78rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
    .hero-kicker::before { width: 24px; height: 3px; border-radius: 999px; background: #0f766e; content: ''; }
    .logo-shell { position: relative; z-index: 1; display: grid; flex: 0 0 auto; min-width: clamp(190px, 17vw, 275px); min-height: 120px; padding: 13px 22px; place-items: center; border-radius: 20px; background: rgba(255, 255, 255, .86); box-shadow: 0 12px 28px rgba(7, 43, 71, .08); }
    .company-logo { width: clamp(165px, 14vw, 235px); height: auto; max-height: 105px; object-fit: contain; }
    .hero h1 { margin-bottom: 10px; color: transparent; background: linear-gradient(135deg, #0f172a 0%, #475569 100%); background-clip: text; -webkit-background-clip: text; font-size: clamp(32px, 3vw, 48px); font-weight: 800; }
    .hero h1 i { margin-right: 10px; color: #0f766e; }
    .hero p { max-width: 850px; color: #64748b; font-size: 1rem; line-height: 1.6; }
    .toolbar { display: flex; align-items: center; gap: 14px; margin-bottom: 24px; }
    .search-box { position: relative; flex: 1; }
    .search-box i { position: absolute; top: 50%; left: 14px; color: #94a3b8; transform: translateY(-50%); }
    .search-box input { width: 100%; padding: 12px 16px 12px 42px; border: 1px solid #dbe2ea; border-radius: 999px; outline: none; background: #fff; font: inherit; }
    .search-box input:focus { border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15, 118, 110, .12); }
    .count-badge { padding: 10px 14px; border: 1px solid #dbe2ea; border-radius: 999px; color: #475569; background: #fff; font-size: .85rem; font-weight: 700; white-space: nowrap; }
    .groups-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 20px; }
    .group-card { display: flex; flex-direction: column; min-height: 220px; padding: 24px; border: 1px solid #e2e8f0; border-radius: 24px; background: #fff; box-shadow: 0 8px 24px rgba(15, 23, 42, .06); transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease; }
    .group-card:hover { border-color: #d4c7f4; box-shadow: 0 16px 34px rgba(15, 23, 42, .1); transform: translateY(-3px); }
    .group-card-top { display: flex; align-items: center; gap: 14px; margin-bottom: 18px; }
    .group-icon { display: grid; width: 56px; height: 56px; flex: 0 0 auto; place-items: center; border-radius: 16px; color: #fff; font-size: 1.35rem; }
    .group-card h2 { margin-bottom: 4px; font-size: 1.15rem; font-weight: 800; }
    .group-meta { color: #94a3b8; font-size: .8rem; font-weight: 700; }
    .group-description { flex: 1; color: #475569; font-size: .94rem; line-height: 1.55; }
    .group-actions { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 20px; }
    .group-link { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 999px; color: #fff; font-size: .9rem; font-weight: 700; text-decoration: none; }
    .reports-panel { padding: clamp(20px, 2.4vw, 32px); border: 1px solid #e2e8f0; border-radius: 24px; background: #fff; box-shadow: 0 8px 24px rgba(15, 23, 42, .06); }
    .reports-panel .group-card { background: #f8fafc; box-shadow: none; }
    .section-heading { margin: 2px 0 14px; color: #334155; font-size: .9rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
    .section-heading-spaced { margin-top: 28px; }
    .panel-header { display: flex; align-items: center; justify-content: space-between; gap: 18px; margin-bottom: 22px; }
    .panel-title { display: flex; align-items: center; gap: 12px; }
    .panel-icon, .report-icon { display: grid; flex: 0 0 auto; place-items: center; border-radius: 14px; color: #fff; background: #0f766e; }
    .panel-icon { width: 50px; height: 50px; font-size: 1.2rem; }
    .panel-header h2 { margin-bottom: 4px; font-size: 1.25rem; font-weight: 800; }
    .panel-header p { color: #64748b; font-size: .92rem; line-height: 1.5; }
    .panel-back { display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px; border: 1px solid #dbe2ea; border-radius: 999px; color: #334155; background: #fff; font-size: .88rem; font-weight: 700; text-decoration: none; white-space: nowrap; }
    .panel-back:hover { border-color: #0f766e; color: #0f766e; }
    .reports-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
    .report-card { display: flex; flex-direction: column; min-height: 230px; padding: 22px; border: 1px solid #e2e8f0; border-radius: 20px; background: #f8fafc; transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease; }
    .report-card:hover { border-color: #b9d8d4; box-shadow: 0 14px 28px rgba(15, 23, 42, .09); transform: translateY(-3px); }
    .report-card-top { display: flex; align-items: center; gap: 13px; margin-bottom: 16px; }
    .report-icon { width: 54px; height: 54px; font-size: 1.3rem; }
    .report-card h3 { margin-bottom: 3px; font-size: 1.08rem; font-weight: 800; }
    .report-slug { color: #94a3b8; font-size: .78rem; font-weight: 600; }
    .report-description { flex: 1; color: #475569; font-size: .94rem; line-height: 1.55; }
    .report-actions { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 20px; }
    .status { display: inline-flex; align-items: center; gap: 8px; color: #64748b; font-size: .82rem; font-weight: 700; }
    .status-dot { width: 10px; height: 10px; border-radius: 50%; background: #10b981; }
    .report-link { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 999px; color: #fff; background: #0f766e; font-size: .9rem; font-weight: 700; text-decoration: none; }
    .report-link:hover { background: #115e59; }
    .selection-card { cursor: pointer; }
    .selection-card:focus-visible { outline: 3px solid rgba(15, 118, 110, .35); outline-offset: 3px; }
    .empty { display: none; grid-column: 1 / -1; padding: 38px 24px; border: 1px dashed #cbd5e1; border-radius: 18px; color: #64748b; text-align: center; }
    .empty i { display: block; margin-bottom: 10px; color: #cbd5e1; font-size: 2rem; }
    @media (min-width: 1800px) { .page { padding: 42px 52px; } .reports-grid { gap: 24px; } .report-card { min-height: 270px; padding: 28px; } .report-card h3 { font-size: 1.25rem; } .report-description { font-size: 1.02rem; } }
    @media (max-width: 720px) { .page { padding: 15px 12px 30px; } .hero { align-items: stretch; flex-direction: column-reverse; gap: 18px; margin-bottom: 18px; padding: 20px; border-radius: 20px; } .logo-shell { align-self: flex-start; min-width: 176px; min-height: 80px; padding: 8px 14px; border-radius: 15px; } .company-logo { width: 148px; max-height: 68px; } .hero h1 { font-size: 30px; } .hero p { font-size: .94rem; } .toolbar { align-items: stretch; flex-direction: column; } .count-badge { align-self: flex-start; } .groups-grid, .reports-grid { grid-template-columns: 1fr; } .group-card { min-height: 205px; padding: 20px; } .reports-panel { padding: 15px; border-radius: 18px; } .panel-header { align-items: flex-start; flex-direction: column; } .report-card { min-height: 205px; padding: 18px; } }
  </style>
</head>
<body>
<main class="page">
  <header class="hero">
    <div class="hero-copy">
      <span class="hero-kicker">Tableros de planta</span>
      <h1><i class="fa-solid fa-table-columns"></i>Centro de Reportes</h1>
      <p>Selecciona el reporte que deseas consultar. Los tableros muestran las lecturas actuales de operación.</p>
    </div>
    <div class="logo-shell"><img class="company-logo" src="../assets/images/logo_empresa_horizontal.svg" alt="Progel"></div>
  </header>
  <section class="toolbar" aria-label="Búsqueda de reportes">
    <label class="search-box"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input id="searchReports" type="search" placeholder="Buscar reporte..." autocomplete="off"></label>
    <div class="count-badge"><span id="reportCount"><?= $visibleCardCount ?></span> <?= $escape($countLabel) ?></div>
  </section>
  <?php if ($selectedSubgroup !== null): ?>
    <section class="reports-panel">
      <header class="panel-header">
        <div class="panel-title">
          <span class="panel-icon" style="background: <?= $escape($selectedSubgroup['color'] ?? '#0f766e') ?>"><i class="fa-solid <?= $escape($selectedSubgroup['icon'] ?? 'fa-folder-open') ?>"></i></span>
          <div><h2><?= $escape($selectedSubgroup['title'] ?? 'Reportes') ?></h2><p><?= $escape($selectedSubgroup['description'] ?? '') ?></p></div>
        </div>
        <a class="panel-back" href="?grupo=<?= urlencode($selectedGroupKey) ?>"><i class="fa-solid fa-arrow-left"></i> Regresar</a>
      </header>
      <div class="reports-grid" id="reportsGrid">
        <?php foreach ($selectedSubgroup['reports'] as $report): ?>
          <?php $search = mb_strtolower((string)($report['title'] ?? '') . ' ' . (string)($report['description'] ?? '') . ' ' . (string)($report['slug'] ?? '')); ?>
          <article class="report-card selection-card" data-search="<?= $escape($search) ?>" data-href="<?= $escape($report['url'] ?? '#') ?>" tabindex="0" role="link">
            <div class="report-card-top">
              <span class="report-icon" style="background: <?= $escape($report['color'] ?? '#0f766e') ?>"><i class="fa-solid <?= $escape($report['icon'] ?? 'fa-chart-line') ?>"></i></span>
              <div><h3><?= $escape($report['title'] ?? 'Reporte') ?></h3><div class="report-slug"><?= $escape($report['slug'] ?? '') ?></div></div>
            </div>
            <p class="report-description"><?= $escape($report['description'] ?? 'Sin descripción.') ?></p>
            <div class="report-actions">
              <span class="status"><span class="status-dot"></span>Disponible</span>
              <a class="report-link" href="<?= $escape($report['url'] ?? '#') ?>">Ver reporte <i class="fa-solid fa-arrow-right"></i></a>
            </div>
          </article>
        <?php endforeach; ?>
        <div class="empty" id="emptyState"><i class="fa-solid fa-folder-open"></i>No se encontraron reportes con ese criterio.</div>
      </div>
    </section>
  <?php elseif ($selectedGroup !== null): ?>
    <section class="reports-panel">
      <header class="panel-header">
        <div class="panel-title">
          <span class="panel-icon" style="background: <?= $escape($selectedGroup['color'] ?? '#7c3aed') ?>"><i class="fa-solid <?= $escape($selectedGroup['icon'] ?? 'fa-folder-open') ?>"></i></span>
          <div><h2><?= $escape($selectedGroup['title'] ?? 'Grupo') ?></h2><p><?= $escape($selectedGroup['description'] ?? '') ?></p></div>
        </div>
        <a class="panel-back" href="./"><i class="fa-solid fa-arrow-left"></i> Regresar</a>
      </header>
      <div id="reportsGrid">
        <?php if (!empty($selectedGroup['subgroups'])): ?>
          <h3 class="section-heading">Subgrupos</h3>
          <div class="groups-grid subgroup-grid">
            <?php foreach ($selectedGroup['subgroups'] as $subgroup): ?>
              <?php
              $subgroupSearch = mb_strtolower(
                  (string)($subgroup['title'] ?? '') . ' ' .
                  (string)($subgroup['description'] ?? '') . ' ' .
                  implode(' ', array_map(static fn(array $report): string => (string)($report['title'] ?? ''), $subgroup['reports']))
              );
              ?>
              <article class="group-card selection-card" data-search="<?= $escape($subgroupSearch) ?>" data-href="?grupo=<?= urlencode($selectedGroupKey) ?>&amp;subgrupo=<?= urlencode((string)$subgroup['key']) ?>" tabindex="0" role="link">
                <div class="group-card-top">
                  <span class="group-icon" style="background: <?= $escape($subgroup['color'] ?? '#0f766e') ?>"><i class="fa-solid <?= $escape($subgroup['icon'] ?? 'fa-folder-open') ?>"></i></span>
                  <div><h2><?= $escape($subgroup['title'] ?? 'Subgrupo') ?></h2><div class="group-meta"><?= count($subgroup['reports']) ?> reporte(s)</div></div>
                </div>
                <p class="group-description"><?= $escape($subgroup['description'] ?? '') ?></p>
                <div class="group-actions">
                  <span class="status"><span class="status-dot"></span>Disponible</span>
                  <a class="group-link" href="?grupo=<?= urlencode($selectedGroupKey) ?>&amp;subgrupo=<?= urlencode((string)$subgroup['key']) ?>" style="background: <?= $escape($subgroup['color'] ?? '#0f766e') ?>">Abrir <i class="fa-solid fa-arrow-right"></i></a>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($selectedGroup['direct_reports'])): ?>
          <h3 class="section-heading <?= !empty($selectedGroup['subgroups']) ? 'section-heading-spaced' : '' ?>">Reportes</h3>
          <div class="reports-grid">
            <?php foreach ($selectedGroup['direct_reports'] as $report): ?>
              <?php $search = mb_strtolower((string)($report['title'] ?? '') . ' ' . (string)($report['description'] ?? '') . ' ' . (string)($report['slug'] ?? '')); ?>
              <article class="report-card selection-card" data-search="<?= $escape($search) ?>" data-href="<?= $escape($report['url'] ?? '#') ?>" tabindex="0" role="link">
                <div class="report-card-top">
                  <span class="report-icon" style="background: <?= $escape($report['color'] ?? '#0f766e') ?>"><i class="fa-solid <?= $escape($report['icon'] ?? 'fa-chart-line') ?>"></i></span>
                  <div><h3><?= $escape($report['title'] ?? 'Reporte') ?></h3><div class="report-slug"><?= $escape($report['slug'] ?? '') ?></div></div>
                </div>
                <p class="report-description"><?= $escape($report['description'] ?? 'Sin descripción.') ?></p>
                <div class="report-actions">
                  <span class="status"><span class="status-dot"></span>Disponible</span>
                  <a class="report-link" href="<?= $escape($report['url'] ?? '#') ?>">Ver reporte <i class="fa-solid fa-arrow-right"></i></a>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <div class="empty" id="emptyState"><i class="fa-solid fa-folder-open"></i>No se encontraron elementos con ese criterio.</div>
      </div>
    </section>
  <?php else: ?>
    <section class="groups-grid" id="reportsGrid">
      <?php foreach ($groups as $group): ?>
        <?php
        $groupReportTitles = array_map(static fn(array $report): string => (string)($report['title'] ?? ''), $group['direct_reports']);
        foreach ($group['subgroups'] as $subgroup) {
            $groupReportTitles[] = (string)($subgroup['title'] ?? '');
            foreach ($subgroup['reports'] as $report) {
                $groupReportTitles[] = (string)($report['title'] ?? '');
            }
        }
        $groupSearch = mb_strtolower((string)($group['title'] ?? '') . ' ' . (string)($group['description'] ?? '') . ' ' . implode(' ', $groupReportTitles));
        $subgroupCount = count($group['subgroups']);
        ?>
        <article class="group-card selection-card" data-search="<?= $escape($groupSearch) ?>" data-href="?grupo=<?= urlencode((string)$group['key']) ?>" tabindex="0" role="link">
          <div class="group-card-top">
            <span class="group-icon" style="background: <?= $escape($group['color'] ?? '#7c3aed') ?>"><i class="fa-solid <?= $escape($group['icon'] ?? 'fa-folder-open') ?>"></i></span>
            <div><h2><?= $escape($group['title'] ?? 'Grupo') ?></h2><div class="group-meta"><?= $subgroupCount > 0 ? ($subgroupCount . ' subgrupo(s) · ') : '' ?><?= (int)$group['report_count'] ?> reporte(s)</div></div>
          </div>
          <p class="group-description"><?= $escape($group['description'] ?? '') ?></p>
          <div class="group-actions">
            <span class="status"><span class="status-dot"></span>Disponible</span>
            <a class="group-link" href="?grupo=<?= urlencode((string)$group['key']) ?>" style="background: <?= $escape($group['color'] ?? '#7c3aed') ?>">Abrir <i class="fa-solid fa-arrow-right"></i></a>
          </div>
        </article>
      <?php endforeach; ?>
      <div class="empty" id="emptyState"><i class="fa-solid fa-folder-open"></i>No se encontraron grupos con ese criterio.</div>
    </section>
  <?php endif; ?>
</main>
<script>
(() => {
  const input = document.getElementById('searchReports');
  const cards = Array.from(document.querySelectorAll('.selection-card'));
  const count = document.getElementById('reportCount');
  const empty = document.getElementById('emptyState');

  function openCard(card) {
    const href = card.dataset.href || '';
    if (href !== '' && href !== '#') window.location.href = href;
  }

  cards.forEach(card => {
    card.addEventListener('click', event => {
      if (event.target.closest('a, button, input, select, textarea')) return;
      openCard(card);
    });
    card.addEventListener('keydown', event => {
      if (event.key !== 'Enter' && event.key !== ' ') return;
      event.preventDefault();
      openCard(card);
    });
  });

  input.addEventListener('input', () => {
    const term = input.value.trim().toLocaleLowerCase('es');
    let visible = 0;
    cards.forEach(card => {
      const show = term === '' || (card.dataset.search || '').includes(term);
      card.hidden = !show;
      if (show) visible++;
    });
    count.textContent = String(visible);
    empty.style.display = visible === 0 ? 'block' : 'none';
  });
})();
</script>
</body>
</html>
