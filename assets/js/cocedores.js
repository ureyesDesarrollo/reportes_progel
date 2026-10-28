(() => {
  const dashboard = document.querySelector('.cocedores-report');
  if (!dashboard || dashboard.dataset.capture === '1') return;

  const statusClasses = ['status-verde', 'status-amarillo', 'status-rojo', 'status-gris'];
  const refreshMs = Math.max(1000, Number(dashboard.dataset.refreshMs) || 60000);
  const liveUrl = dashboard.dataset.liveUrl || 'live.php';
  let updating = false;

  const setStatus = (element, status) => {
    if (!element) return;
    const valid = ['verde', 'amarillo', 'rojo'].includes(status) ? status : 'gris';
    element.classList.remove(...statusClasses);
    element.classList.add(`status-${valid}`);
  };

  const setField = (container, field) => {
    if (!container || !field) return;
    setStatus(container, field.statusKey);
    const value = container.querySelector('.cc-value');
    if (value) {
      value.replaceChildren(document.createTextNode(field.formatted || '—'));
      if (field.unit && field.formatted !== '—' && field.formatted !== 'F.O.') {
        const unit = document.createElement('small');
        unit.textContent = ` ${field.unit}`;
        value.append(unit);
      }
    }
    const cue = container.querySelector('.cc-direction');
    if (!cue) return;
    const direction = field.direction === 'up' || field.direction === 'down' ? field.direction : '';
    cue.classList.toggle('is-visible', direction !== '');
    const icon = cue.querySelector('i');
    const action = cue.querySelector('em');
    const amount = cue.querySelector('b');
    if (icon) icon.className = `fa-solid ${direction === 'up' ? 'fa-arrow-up-long' : 'fa-arrow-down-long'}`;
    if (action) action.textContent = direction === 'up' ? 'Subir' : (direction === 'down' ? 'Bajar' : '');
    if (amount) amount.textContent = direction ? (field.adjustment || '') : '';
  };

  const applyData = (data) => {
    Object.entries(data.headerMetrics || {}).forEach(([key, field]) => {
      setField(document.querySelector(`[data-header-key="${CSS.escape(key)}"] .cc-field`), field);
    });
    Object.entries(data.equipment || {}).forEach(([key, equipment]) => {
      const card = document.querySelector(`[data-equipment-key="${CSS.escape(key)}"]`);
      if (!card) return;
      const state = card.querySelector('.cc-state');
      if (state) state.textContent = equipment.statusLabel || 'Sin datos';
      Object.entries(equipment.fields || {}).forEach(([fieldKey, field]) => {
        setField(card.querySelector(`[data-field-key="${CSS.escape(fieldKey)}"]`), field);
      });
    });
    const avevaTime = document.querySelector('[data-timestamp="aveva"]');
    const mysqlTime = document.querySelector('[data-timestamp="mysql"]');
    if (avevaTime) avevaTime.textContent = data.avevaTimestamp || '—';
    if (mysqlTime) mysqlTime.textContent = data.mysqlTimestamp || '—';
    const warning = document.querySelector('[data-warning]');
    if (warning) {
      warning.textContent = data.warning || '';
      warning.classList.toggle('is-hidden', !data.warning);
    }
  };

  const refresh = async () => {
    if (updating || document.hidden) return;
    updating = true;
    try {
      const response = await fetch(`${liveUrl}?_=${Date.now()}`, { cache: 'no-store' });
      const data = await response.json();
      if (data && typeof data === 'object') requestAnimationFrame(() => applyData(data));
    } catch (error) {
      // El tablero conserva la última lectura visible si una actualización falla.
    } finally {
      updating = false;
    }
  };

  refresh();
  window.setInterval(refresh, refreshMs);
})();
