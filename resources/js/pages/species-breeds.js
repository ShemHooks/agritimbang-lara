import api from '../api';

const root = document.getElementById('species-breeds-page');
if (root) {
  const el = (id) => document.getElementById(id);
  const state = { tab: 'species', page: 1, search: '', speciesId: '', pagination: null, species: [], editing: null, pending: null, requestId: 0, timer: null };
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch]);
  const unwrap = (response) => response.data?.data ?? response.data;
  const resource = () => `/admin/${state.tab}`;
  const errorMessage = (error) => error.response?.data?.message || 'Something went wrong. Please try again.';
  const busy = (button, value, label) => { button.disabled = value; if (label) button.textContent = value ? 'Saving...' : label; };

  function alertMessage(message = '') {
    el('sb-alert').hidden = !message;
    el('sb-alert').textContent = message;
  }

  function setTab(tab) {
    if (tab === state.tab) return;
    state.tab = tab;
    state.page = 1;
    state.search = '';
    state.speciesId = '';
    el('sb-search').value = '';
    el('sb-species-filter').value = '';
    el('sb-filter-wrap').hidden = tab !== 'breeds';
    el('sb-add').textContent = `+ Add ${tab === 'species' ? 'Species' : 'Breed'}`;
    document.querySelectorAll('.sb-tab').forEach((button) => {
      const selected = button.dataset.tab === tab;
      button.classList.toggle('is-selected', selected);
      button.setAttribute('aria-selected', String(selected));
    });
    load();
  }

  async function fetchAllSpecies() {
    // Admin index is paginated; load all pages for breed parent selectors/filtering.
    let page = 1;
    let lastPage = 1;
    const records = [];
    do {
      const response = await api.get('/admin/species', { params: { page } });
      const payload = unwrap(response);
      records.push(...(payload.data ?? []));
      lastPage = Number(payload.last_page ?? 1);
      page += 1;
    } while (page <= lastPage);
    state.species = records;
    const filter = el('sb-species-filter');
    const previous = state.speciesId;
    filter.innerHTML = '<option value="">All species</option>' + records.map((item) => `<option value="${escapeHtml(item.id)}">${escapeHtml(item.name)}${item.is_active ? '' : ' (inactive)'}</option>`).join('');
    filter.value = previous;
  }

  function render(payload) {
    state.pagination = payload;
    const speciesTab = state.tab === 'species';
    el('sb-table-head').innerHTML = speciesTab
      ? '<tr><th>Name</th><th>Code</th><th>Breeds</th><th>Status</th><th>Actions</th></tr>'
      : '<tr><th>Breed</th><th>Species</th><th>Code</th><th>Status</th><th>Actions</th></tr>';
    const rows = payload.data ?? [];
    el('sb-table-body').innerHTML = rows.length ? rows.map((item) => {
      const active = Boolean(item.is_active);
      const status = `<span class="sb-pill ${active ? '' : 'inactive'}">${active ? 'Active' : 'Inactive'}</span>`;
      const actions = `<div class="sb-actions"><button type="button" class="sb-action" data-action="edit" data-id="${escapeHtml(item.id)}">Edit</button><button type="button" class="sb-action" data-action="status" data-id="${escapeHtml(item.id)}">${active ? 'Deactivate' : 'Activate'}</button></div>`;
      return speciesTab
        ? `<tr><td class="sb-name">${escapeHtml(item.name)}</td><td>${escapeHtml(item.code)}</td><td>${Number(item.breeds_count ?? 0)}</td><td>${status}</td><td>${actions}</td></tr>`
        : `<tr><td class="sb-name">${escapeHtml(item.name)}</td><td>${escapeHtml(item.species?.name ?? '—')}</td><td>${escapeHtml(item.code || '—')}</td><td>${status}</td><td>${actions}</td></tr>`;
    }).join('') : '<tr><td colspan="5" class="sb-muted" style="text-align:center;padding:38px">No records found.</td></tr>';
    const current = Number(payload.current_page ?? 1);
    const last = Number(payload.last_page ?? 1);
    const total = Number(payload.total ?? rows.length);
    const start = total ? Number(payload.from ?? (current - 1) * 20 + 1) : 0;
    const end = Number(payload.to ?? Math.min(current * 20, total));
    el('sb-count').textContent = `Showing ${start}–${end} of ${total} records`;
    el('sb-page-label').textContent = `Page ${current} of ${last}`;
    el('sb-prev').disabled = current <= 1;
    el('sb-next').disabled = current >= last;
  }

  async function load() {
    const requestId = ++state.requestId;
    alertMessage();
    el('sb-table-body').innerHTML = '<tr><td colspan="5" class="sb-muted" style="padding:30px;text-align:center">Loading records...</td></tr>';
    try {
      const params = { page: state.page };
      if (state.search) params.search = state.search;
      if (state.tab === 'breeds' && state.speciesId) params.species_id = state.speciesId;
      const response = await api.get(resource(), { params });
      if (requestId !== state.requestId) return;
      render(unwrap(response));
    } catch (error) {
      if (requestId !== state.requestId) return;
      el('sb-table-body').innerHTML = '';
      alertMessage(errorMessage(error));
    }
  }

  function clearFormErrors() {
    el('sb-form-errors').hidden = true;
    el('sb-form-errors').textContent = '';
    document.querySelectorAll('#sb-form .sb-error').forEach((node) => { node.textContent = ''; });
  }

  function populateParentOptions(item = null) {
    const parent = el('sb-parent');
    const active = state.species.filter((species) => species.is_active || String(species.id) === String(item?.species_id));
    parent.innerHTML = '<option value="">Select species</option>' + active.map((species) => `<option value="${escapeHtml(species.id)}">${escapeHtml(species.name)}${species.is_active ? '' : ' (inactive)'}</option>`).join('');
    parent.value = item?.species_id ?? '';
  }

  async function openForm(item = null) {
    state.editing = item;
    clearFormErrors();
    el('sb-form').reset();
    const breedTab = state.tab === 'breeds';
    el('sb-modal-title').textContent = `${item ? 'Edit' : 'Add'} ${breedTab ? 'Breed' : 'Species'}`;
    el('sb-parent-field').hidden = !breedTab;
    el('sb-code-required').hidden = breedTab;
    el('sb-code').required = !breedTab;
    if (breedTab) {
      try { await fetchAllSpecies(); } catch (error) { alertMessage(errorMessage(error)); return; }
      populateParentOptions(item);
    }
    el('sb-name').value = item?.name ?? '';
    el('sb-code').value = item?.code ?? '';
    el('sb-description').value = item?.description ?? '';
    el('sb-modal').hidden = false;
    el(breedTab ? 'sb-parent' : 'sb-name').focus();
  }

  function closeForm() { el('sb-modal').hidden = true; state.editing = null; }

  async function save(event) {
    event.preventDefault();
    clearFormErrors();
    const breedTab = state.tab === 'breeds';
    const payload = {
      name: el('sb-name').value.trim(),
      code: el('sb-code').value.trim().toUpperCase() || (breedTab ? null : ''),
      description: el('sb-description').value.trim() || null,
    };
    if (breedTab) {
      // When editing an inactive breed whose parent is inactive, preserve parent
      // without resubmitting species_id unless user actually changes it.
      const parentId = el('sb-parent').value;
      if (!state.editing || String(parentId) !== String(state.editing.species_id)) payload.species_id = parentId;
    }
    const button = el('sb-save');
    busy(button, true, 'Save');
    try {
      if (state.editing) await api.patch(`${resource()}/${state.editing.id}`, payload);
      else await api.post(resource(), payload);
      closeForm();
      if (breedTab) await fetchAllSpecies();
      await load();
    } catch (error) {
      const errors = error.response?.data?.errors;
      if (errors && typeof errors === 'object') {
        Object.entries(errors).forEach(([field, messages]) => {
          const target = document.querySelector(`#sb-form [data-error="${field}"]`);
          if (target) target.textContent = Array.isArray(messages) ? messages.join(' ') : String(messages);
        });
      }
      el('sb-form-errors').hidden = false;
      el('sb-form-errors').textContent = errorMessage(error);
    } finally { busy(button, false, 'Save'); }
  }

  function confirmStatus(item) {
    const activate = !Boolean(item.is_active);
    state.pending = { id: item.id, activate };
    el('sb-confirm-title').textContent = `${activate ? 'Activate' : 'Deactivate'} ${state.tab === 'species' ? 'species' : 'breed'}?`;
    el('sb-confirm-message').textContent = `Are you sure you want to ${activate ? 'activate' : 'deactivate'} “${item.name}”? ${!activate && state.tab === 'species' ? 'Its breeds will no longer appear in active reference lists until this species is reactivated.' : ''}`;
    el('sb-confirm').hidden = false;
    el('sb-confirm-yes').focus();
  }

  async function updateStatus() {
    if (!state.pending) return;
    const { id, activate } = state.pending;
    const button = el('sb-confirm-yes');
    button.disabled = true;
    try {
      await api.patch(`${resource()}/${id}/status`, { is_active: activate });
      el('sb-confirm').hidden = true;
      state.pending = null;
      await load();
    } catch (error) {
      el('sb-confirm').hidden = true;
      state.pending = null;
      alertMessage(errorMessage(error));
    } finally { button.disabled = false; }
  }

  document.querySelectorAll('.sb-tab').forEach((button) => button.addEventListener('click', () => setTab(button.dataset.tab)));
  el('sb-add').addEventListener('click', () => openForm());
  el('sb-search').addEventListener('input', (event) => {
    clearTimeout(state.timer);
    state.timer = setTimeout(() => { state.search = event.target.value.trim(); state.page = 1; load(); }, 300);
  });
  el('sb-species-filter').addEventListener('change', (event) => { state.speciesId = event.target.value; state.page = 1; load(); });
  el('sb-prev').addEventListener('click', () => { if (state.page > 1) { state.page -= 1; load(); } });
  el('sb-next').addEventListener('click', () => { if (state.page < Number(state.pagination?.last_page ?? 1)) { state.page += 1; load(); } });
  el('sb-table-body').addEventListener('click', (event) => {
    const button = event.target.closest('button[data-action]');
    if (!button) return;
    const item = (state.pagination?.data ?? []).find((record) => String(record.id) === button.dataset.id);
    if (!item) return;
    if (button.dataset.action === 'edit') openForm(item);
    if (button.dataset.action === 'status') confirmStatus(item);
  });
  el('sb-form').addEventListener('submit', save);
  el('sb-modal-close').addEventListener('click', closeForm);
  el('sb-cancel').addEventListener('click', closeForm);
  el('sb-confirm-cancel').addEventListener('click', () => { el('sb-confirm').hidden = true; state.pending = null; });
  el('sb-confirm-yes').addEventListener('click', updateStatus);
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      if (!el('sb-confirm').hidden) { el('sb-confirm').hidden = true; state.pending = null; }
      else if (!el('sb-modal').hidden) closeForm();
    }
  });
  load();
  fetchAllSpecies().catch(() => {});
}
