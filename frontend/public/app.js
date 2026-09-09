(() => {
  const API_BASE = '/api';

  const MONTH_NAMES = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
  ];
  const WEEKDAYS = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];

  const els = {
    grid: document.getElementById('calendarGrid'),
    yearLabel: document.getElementById('yearLabel'),
    prevYear: document.getElementById('prevYear'),
    nextYear: document.getElementById('nextYear'),
    panel: document.getElementById('sidePanel'),
    overlay: document.getElementById('panelOverlay'),
    closePanel: document.getElementById('closePanel'),
    panelDate: document.getElementById('panelDate'),
    panelDateLong: document.getElementById('panelDateLong'),
    existingList: document.getElementById('existingList'),
    emptyState: document.getElementById('emptyState'),
    newItemsList: document.getElementById('newItemsList'),
    addRowBtn: document.getElementById('addRowBtn'),
    saveAllBtn: document.getElementById('saveAllBtn'),
    panelStatus: document.getElementById('panelStatus'),
  };

  const state = {
    year: new Date().getFullYear(),
    counts: {},          // { 'YYYY-MM-DD': count }
    selectedDate: null,  // 'YYYY-MM-DD'
    newRowId: 0,
  };

  const todayStr = toDateStr(new Date());

  function toDateStr(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  }

  function formatLongDate(dateStr) {
    const [y, m, d] = dateStr.split('-').map(Number);
    const date = new Date(y, m - 1, d);
    return date.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  }

  // ---------------------------------------------------------------
  // API helpers
  // ---------------------------------------------------------------
  async function apiGet(action, params = {}) {
    const qs = new URLSearchParams({ action, ...params }).toString();
    const res = await fetch(`${API_BASE}/api.php?${qs}`);
    if (!res.ok) throw new Error(`Request failed (${res.status})`);
    return res.json();
  }

  async function apiSend(method, action, body) {
    const res = await fetch(`${API_BASE}/api.php?action=${action}`, {
      method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.error || `Request failed (${res.status})`);
    }
    return res.json();
  }

  // ---------------------------------------------------------------
  // Calendar rendering
  // ---------------------------------------------------------------
  function renderYear() {
    els.yearLabel.textContent = state.year;
    els.grid.innerHTML = '';
    for (let m = 0; m < 12; m++) {
      els.grid.appendChild(buildMonthCard(m));
    }
  }

  function buildMonthCard(monthIndex) {
    const card = document.createElement('section');
    card.className = 'month-card';

    const title = document.createElement('h3');
    title.className = 'month-card__title';
    title.innerHTML = `${MONTH_NAMES[monthIndex]} <span>${state.year}</span>`;
    card.appendChild(title);

    const weekdayRow = document.createElement('div');
    weekdayRow.className = 'weekday-row';
    WEEKDAYS.forEach(w => {
      const s = document.createElement('span');
      s.textContent = w;
      weekdayRow.appendChild(s);
    });
    card.appendChild(weekdayRow);

    const dayGrid = document.createElement('div');
    dayGrid.className = 'day-grid';

    const firstOfMonth = new Date(state.year, monthIndex, 1);
    const startOffset = firstOfMonth.getDay();
    const daysInMonth = new Date(state.year, monthIndex + 1, 0).getDate();

    for (let i = 0; i < startOffset; i++) {
      const blank = document.createElement('span');
      blank.className = 'day-cell is-blank';
      dayGrid.appendChild(blank);
    }

    for (let d = 1; d <= daysInMonth; d++) {
      const dateStr = `${state.year}-${String(monthIndex + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'day-cell';
      btn.textContent = d;
      btn.dataset.date = dateStr;
      if (dateStr === todayStr) btn.classList.add('is-today');
      if (dateStr === state.selectedDate) btn.classList.add('is-selected');

      if (state.counts[dateStr]) {
        const dot = document.createElement('span');
        dot.className = 'day-cell__dot';
        btn.appendChild(dot);
      }

      btn.addEventListener('click', () => openPanel(dateStr));
      dayGrid.appendChild(btn);
    }

    card.appendChild(dayGrid);
    return card;
  }

  async function loadYearCounts() {
    try {
      state.counts = await apiGet('year', { year: state.year });
    } catch (e) {
      state.counts = {};
      console.error('Could not load year counts', e);
    }
    renderYear();
  }

  // ---------------------------------------------------------------
  // Side panel
  // ---------------------------------------------------------------
  async function openPanel(dateStr) {
    state.selectedDate = dateStr;
    renderYear();

    els.panelDate.textContent = dateStr;
    els.panelDateLong.textContent = formatLongDate(dateStr);
    els.panel.classList.add('is-open');
    els.overlay.classList.add('is-open');
    setStatus('');
    resetNewRows();

    await loadDay(dateStr);
  }

  function closePanel() {
    els.panel.classList.remove('is-open');
    els.overlay.classList.remove('is-open');
  }

  async function loadDay(dateStr) {
    els.existingList.innerHTML = '';
    try {
      const items = await apiGet('day', { date: dateStr });
      if (!items.length) {
        els.existingList.appendChild(els.emptyState);
      } else {
        items.forEach(renderExistingItem);
      }
    } catch (e) {
      setStatus('Could not load reminders for this date.', true);
    }
  }

  function renderExistingItem(item) {
    const li = document.createElement('li');
    li.className = 'reminder-item';
    li.dataset.id = item.id;

    const titleSpan = document.createElement('span');
    titleSpan.className = 'reminder-item__title';
    titleSpan.textContent = item.title;

    const editBtn = document.createElement('button');
    editBtn.className = 'reminder-item__btn is-edit';
    editBtn.type = 'button';
    editBtn.textContent = 'Edit';

    const deleteBtn = document.createElement('button');
    deleteBtn.className = 'reminder-item__btn is-delete';
    deleteBtn.type = 'button';
    deleteBtn.textContent = 'Delete';

    editBtn.addEventListener('click', () => enterEditMode(li, item));
    deleteBtn.addEventListener('click', () => deleteItem(item.id));

    li.append(titleSpan, editBtn, deleteBtn);
    els.existingList.appendChild(li);
  }

  function enterEditMode(li, item) {
    li.innerHTML = '';

    const input = document.createElement('input');
    input.type = 'text';
    input.value = item.title;
    input.maxLength = 255;

    const saveBtn = document.createElement('button');
    saveBtn.className = 'reminder-item__btn is-save';
    saveBtn.type = 'button';
    saveBtn.textContent = 'Save';

    const cancelBtn = document.createElement('button');
    cancelBtn.className = 'reminder-item__btn';
    cancelBtn.type = 'button';
    cancelBtn.textContent = 'Cancel';

    saveBtn.addEventListener('click', async () => {
      const newTitle = input.value.trim();
      if (!newTitle) { input.focus(); return; }
      try {
        await apiSend('PUT', 'update', { id: item.id, title: newTitle });
        setStatus('Reminder updated.');
        await loadDay(state.selectedDate);
      } catch (e) {
        setStatus(e.message || 'Could not update reminder.', true);
      }
    });

    cancelBtn.addEventListener('click', () => loadDay(state.selectedDate));

    li.append(input, saveBtn, cancelBtn);
    input.focus();
  }

  async function deleteItem(id) {
    try {
      await apiSend('DELETE', 'delete', { id });
      setStatus('Reminder deleted.');
      await loadDay(state.selectedDate);
      await loadYearCounts();
    } catch (e) {
      setStatus(e.message || 'Could not delete reminder.', true);
    }
  }

  // ---- New item rows (multi add) ----
  function resetNewRows() {
    els.newItemsList.innerHTML = '';
    state.newRowId = 0;
    addNewRow();
  }

  function addNewRow(prefill = '') {
    const rowId = `row-${state.newRowId++}`;
    const row = document.createElement('div');
    row.className = 'new-item-row';
    row.dataset.rowId = rowId;

    const input = document.createElement('input');
    input.type = 'text';
    input.placeholder = 'e.g. Bike tax, Car tax, Electricity bill';
    input.value = prefill;
    input.maxLength = 255;

    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'new-item-row__remove';
    removeBtn.setAttribute('aria-label', 'Remove this row');
    removeBtn.textContent = '\u2715';
    removeBtn.addEventListener('click', () => {
      if (els.newItemsList.children.length > 1) {
        row.remove();
      } else {
        input.value = '';
      }
    });

    row.append(input, removeBtn);
    els.newItemsList.appendChild(row);
    input.focus();
  }

  async function saveAll() {
    const inputs = Array.from(els.newItemsList.querySelectorAll('input[type="text"]'));
    const items = inputs
      .map(i => i.value.trim())
      .filter(Boolean)
      .map(title => ({ title }));

    if (!items.length) {
      setStatus('Add at least one title before saving.', true);
      return;
    }

    try {
      await apiSend('POST', 'save', { date: state.selectedDate, items });
      setStatus('Saved.');
      resetNewRows();
      await loadDay(state.selectedDate);
      await loadYearCounts();
    } catch (e) {
      setStatus(e.message || 'Could not save reminders.', true);
    }
  }

  function setStatus(msg, isError = false) {
    els.panelStatus.textContent = msg;
    els.panelStatus.classList.toggle('is-error', isError);
  }

  // ---------------------------------------------------------------
  // Wiring
  // ---------------------------------------------------------------
  els.prevYear.addEventListener('click', () => { state.year--; state.selectedDate = null; loadYearCounts(); });
  els.nextYear.addEventListener('click', () => { state.year++; state.selectedDate = null; loadYearCounts(); });
  els.closePanel.addEventListener('click', closePanel);
  els.overlay.addEventListener('click', closePanel);
  els.addRowBtn.addEventListener('click', () => addNewRow());
  els.saveAllBtn.addEventListener('click', saveAll);
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closePanel();
  });

  loadYearCounts();
})();
