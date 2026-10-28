<?php

declare(strict_types=1);

function correctiveActionsRender(array $actions, string $warning = '', string $layout = 'default'): void
{
    $escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    ?>
    <section class="corrective-actions" data-corrective-actions data-layout="<?= $escape($layout) ?>">
      <header class="corrective-actions-header">
        <div><span class="corrective-actions-icon"><i class="fa-solid fa-screwdriver-wrench"></i></span><div><span>SEGUIMIENTO OPERATIVO</span><h2>Acciones correctivas</h2></div></div>
        <strong><i class="fa-solid fa-rotate"></i> Actualización automática</strong>
      </header>
      <p class="corrective-actions-warning<?= trim($warning) === '' ? ' is-hidden' : '' ?>" data-corrective-warning><?= $escape($warning) ?></p>
      <div class="corrective-actions-grid" data-corrective-grid data-count="<?= count($actions) ?>">
        <?php if ($actions === []): ?>
          <div class="corrective-actions-empty"><i class="fa-regular fa-circle-check"></i><strong>Sin acciones registradas</strong><span>No hay información correctiva disponible.</span></div>
        <?php endif; ?>
        <?php foreach ($actions as $action): ?>
          <?php $color = in_array($action['stateColor'] ?? '', ['danger', 'warning', 'info'], true) ? (string)$action['stateColor'] : 'action'; ?>
          <article class="corrective-action-card state-<?= $escape($color) ?>">
            <header><h3><?= $escape($action['equipment'] ?? 'Equipo') ?></h3><span><?= $escape($action['stateName'] ?? 'Acción correctiva') ?></span></header>
            <time><i class="fa-regular fa-clock"></i> <?= $escape($action['timestamp'] ?? '—') ?></time>
            <?php if (!empty($action['observations'])): ?>
              <ul><?php foreach ((array)$action['observations'] as $observation): ?><li><?= $escape($observation) ?></li><?php endforeach; ?></ul>
            <?php else: ?>
              <p><?= $escape($action['stateDescription'] ?? 'Sin observaciones adicionales.') ?></p>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php
}
