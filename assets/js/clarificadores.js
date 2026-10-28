(() => {
  const dashboard = document.querySelector('.clarificador-report');
  if (!dashboard || dashboard.dataset.capture === '1') return;
  const statusClasses = ['status-verde','status-amarillo','status-rojo','status-gris'];
  const refreshMs = Math.max(1000, Number(dashboard.dataset.refreshMs) || 60000);
  let updating = false;

  const setField = (card, field) => {
    if (!card || !field) return;
    const status = ['verde','amarillo','rojo'].includes(field.statusKey) ? field.statusKey : 'gris';
    card.classList.remove(...statusClasses);
    card.classList.add(`status-${status}`);
    const value = card.querySelector('.cl-value');
    if (value) {
      value.replaceChildren(document.createTextNode(field.formatted || '—'));
      if (field.unit && field.formatted !== '—' && field.formatted !== 'F.O.') {
        const unit = document.createElement('small');
        unit.textContent = ` ${field.unit}`;
        value.append(unit);
      }
    }
    const cue = card.querySelector('.cl-direction');
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
    Object.entries(data.fields || {}).forEach(([key, field]) => setField(document.querySelector(`[data-field-key="${CSS.escape(key)}"]`), field));
    const state = document.querySelector('.cl-state');
    if (state) state.textContent = data.statusLabel || 'Sin datos';
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
      const response = await fetch(`${dashboard.dataset.liveUrl || 'live.php'}?_=${Date.now()}`, { cache:'no-store' });
      const data = await response.json();
      if (data && typeof data === 'object') requestAnimationFrame(() => applyData(data));
    } catch (error) {
      // Conserva la última lectura visible si una actualización falla.
    } finally {
      updating = false;
    }
  };
  refresh();
  window.setInterval(refresh, refreshMs);
})();
