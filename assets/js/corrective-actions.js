(() => {
  const safeColor = color => ['danger', 'warning', 'info'].includes(color) ? color : 'action';

  const createCard = action => {
    const card = document.createElement('article');
    card.className = `corrective-action-card state-${safeColor(action.stateColor)}`;

    const header = document.createElement('header');
    const title = document.createElement('h3');
    title.textContent = action.equipment || 'Equipo';
    const state = document.createElement('span');
    state.textContent = action.stateName || 'Acción correctiva';
    header.append(title, state);

    const time = document.createElement('time');
    const clock = document.createElement('i');
    clock.className = 'fa-regular fa-clock';
    time.append(clock, document.createTextNode(` ${action.timestamp || '—'}`));
    card.append(header, time);

    const observations = Array.isArray(action.observations) ? action.observations.filter(Boolean) : [];
    if (observations.length) {
      const list = document.createElement('ul');
      observations.forEach(observation => {
        const item = document.createElement('li');
        item.textContent = observation;
        list.append(item);
      });
      card.append(list);
    } else {
      const description = document.createElement('p');
      description.textContent = action.stateDescription || 'Sin observaciones adicionales.';
      card.append(description);
    }
    return card;
  };

  const update = (root, actions, warning = '') => {
    if (!root) return;
    const warningElement = root.querySelector('[data-corrective-warning]');
    if (warningElement) {
      warningElement.textContent = warning;
      warningElement.classList.toggle('is-hidden', !warning);
    }

    const grid = root.querySelector('[data-corrective-grid]');
    if (!grid) return;
    grid.replaceChildren();
    grid.dataset.count = Array.isArray(actions) ? String(actions.length) : '0';
    if (!Array.isArray(actions) || actions.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'corrective-actions-empty';
      const icon = document.createElement('i');
      icon.className = 'fa-regular fa-circle-check';
      const title = document.createElement('strong');
      title.textContent = 'Sin acciones registradas';
      const copy = document.createElement('span');
      copy.textContent = 'No hay información correctiva disponible.';
      empty.append(icon, title, copy);
      grid.append(empty);
      return;
    }
    actions.forEach(action => grid.append(createCard(action)));
  };

  window.CorrectiveActions = { update };
})();
