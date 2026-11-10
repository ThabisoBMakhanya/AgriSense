(() => {
  const root = document.getElementById('farm-journal-app');
  if (!root) return;

  const DB_NAME = 'agrisense-journal-v1';
  const STORE = 'entries';
  const API = 'farm_journal_api.php';
  const owner = (document.body.dataset.farmerKey || localStorage.getItem('agrisense-active-farmer') || '').trim().toLowerCase();
  let database;
  let records = [];
  let activeDomain = 'all';
  const defaultPreferences = { task:true, finance:true, yield:true, case:true, inventory:true, animal:true };
  let recordingPreferences = { ...defaultPreferences };
  let preferencesDirty = false;

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
  const today = () => new Date().toLocaleDateString('en-CA');
  const makeId = () => {
    if (crypto.randomUUID) return crypto.randomUUID();
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0,8)}-${hex.slice(8,12)}-${hex.slice(12,16)}-${hex.slice(16,20)}-${hex.slice(20)}`;
  };
  const keyFor = (clientId) => `${owner}:${clientId}`;
  const currency = (amount) => `E${Number(amount || 0).toFixed(2)}`;
  const setStatus = (message, error = false) => {
    const el = document.getElementById('journal-status');
    if (el) { el.textContent = message; el.style.color = error ? '#a34332' : ''; }
  };
  function setSyncState(heading, copy) {
    const title = document.getElementById('journal-sync-heading');
    const detail = document.getElementById('journal-sync-copy');
    if (title) title.textContent = heading;
    if (detail) detail.textContent = copy;
  }
  const syncLabel = (row) => row.syncStatus === 'synced' ? 'Saved to your account' : row.syncStatus === 'conflict' ? 'Needs your review' : 'Saved on this device';

  function openDatabase() {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open(DB_NAME, 1);
      request.onupgradeneeded = () => {
        const db = request.result;
        if (!db.objectStoreNames.contains(STORE)) db.createObjectStore(STORE, { keyPath: 'key' });
      };
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  function transaction(mode, operation) {
    return new Promise((resolve, reject) => {
      const tx = database.transaction(STORE, mode);
      const request = operation(tx.objectStore(STORE));
      tx.oncomplete = () => resolve(request?.result);
      tx.onerror = () => reject(tx.error);
      tx.onabort = () => reject(tx.error || new Error('Local journal storage failed.'));
    });
  }

  const allLocal = async () => (await transaction('readonly', (store) => store.getAll())).filter((row) => row.owner === owner);
  const saveLocal = async (row) => {
    row.owner = owner;
    row.key = keyFor(row.clientId);
    await transaction('readwrite', (store) => store.put(row));
  };
  const removeLocal = async (clientId) => transaction('readwrite', (store) => store.delete(keyFor(clientId)));

  async function requestApi(payload) {
    const response = await fetch(API, {
      method: payload ? 'POST' : 'GET',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: payload ? { 'Content-Type': 'application/json', 'Accept': 'application/json' } : { 'Accept': 'application/json' },
      body: payload ? JSON.stringify(payload) : undefined
    });
    const type = response.headers.get('content-type') || '';
    if (!type.includes('application/json')) throw new Error('Go online and sign in to save entries to your account.');
    const data = await response.json();
    if (!response.ok) {
      const error = new Error(data.error || 'Could not save your entries.');
      error.status = response.status;
      error.data = data;
      throw error;
    }
    return data;
  }

  function apiRecord(row) {
    if (row.type === 'case') {
      const { id, key, owner: localOwner, syncStatus, photoUrl, ...clean } = row;
      return clean;
    }
    const { id, key, owner: localOwner, syncStatus, ...clean } = row;
    return clean;
  }

  async function syncOne(row) {
    if (!navigator.onLine || row.type === 'deleted') return false;
    try {
      const result = await requestApi({ action: 'save', entity: row.type, record: apiRecord(row) });
      row.id = result.id;
      row.updatedAt = result.updatedAt;
      delete row.serverUpdatedAt;
      row.syncStatus = 'synced';
      await saveLocal(row);
      return true;
    } catch (error) {
      if (error.status === 409 && error.data?.conflict) {
        row.syncStatus = 'conflict';
        row.serverUpdatedAt = error.data.updatedAt || null;
      } else {
        row.syncStatus = 'pending';
      }
      await saveLocal(row);
      return false;
    }
  }

  async function syncAll() {
    if (!owner || !navigator.onLine) return;
    setSyncState('Syncing…', 'Checking your farm account for saved records.');
    setStatus('Syncing farm journal…');
    try {
      const remote = await requestApi(null);
      preferencesDirty = localStorage.getItem(`agrisense-journal-recording-dirty:${owner}`) === '1';
      if (preferencesDirty) {
        await requestApi({ action:'savePreferences', settings:recordingPreferences });
        preferencesDirty = false;
        localStorage.removeItem(`agrisense-journal-recording-dirty:${owner}`);
      } else if (remote.preferences && typeof remote.preferences === 'object') {
        recordingPreferences = { ...defaultPreferences, ...remote.preferences };
        localStorage.setItem(`agrisense-journal-recording:${owner}`, JSON.stringify(recordingPreferences));
        applyRecordingPreferences();
      }
      const local = await allLocal();
      const localById = new Map(local.map((row) => [row.clientId, row]));
      const remoteIds = new Set();
      for (const row of (remote.records || [])) {
        remoteIds.add(row.clientId);
        const current = localById.get(row.clientId);
        if (current && current.syncStatus !== 'synced') continue;
        if (row.type === 'case' && row.photoBase64) {
          row.photoDataUrl = `data:${row.photoMime || 'image/jpeg'};base64,${row.photoBase64}`;
          delete row.photoBase64;
        }
        await saveLocal(row);
      }
      const syncRank = (row) => row.type === 'inventory' ? 0 : row.type === 'stock' ? 2 : 1;
      for (const row of local.sort((a, b) => syncRank(a) - syncRank(b))) {
        if (row.type === 'deleted') {
          try {
            await requestApi({ action: 'delete', entity: row.entity, clientId: row.clientId });
            await removeLocal(row.clientId);
          } catch { /* Keep the tombstone and retry online later. */ }
        } else if (row.syncStatus === 'pending') {
          await syncOne(row);
        } else if (!remoteIds.has(row.clientId)) {
          await removeLocal(row.clientId);
        }
      }
      await loadRecords();
      const pendingRows = (await allLocal()).filter((row) => row.syncStatus !== 'synced');
      const conflicts = pendingRows.filter((row) => row.syncStatus === 'conflict').length;
      const pending = pendingRows.length;
      if (pending) {
        setSyncState(conflicts ? 'Review edits from another device' : 'Some entries still need backup', conflicts ? `${conflicts} entry conflict${conflicts === 1 ? '' : 's'} need your choice. Keep your version or use the account version.` : `${pending} change${pending === 1 ? '' : 's'} remain on this device. Use Retry sync when your connection is stable.`);
        setStatus(conflicts ? `${conflicts} journal conflict${conflicts === 1 ? '' : 's'} need review.` : `${pending} journal change${pending === 1 ? '' : 's'} could not be backed up.`, true);
      } else {
        setSyncState('Backed up to your farm account', 'Your saved records are available on this device and in your account.');
        setStatus('Farm journal is up to date.');
      }
    } catch {
      setSyncState('Saved on this device', 'Could not reach your farm account. Entries stay on this device and will sync when the connection returns.');
      setStatus('Could not sync right now. Your local entries are still saved.', true);
    }
  }

  async function loadRecords() {
    records = (await allLocal()).filter((row) => row.type !== 'deleted').sort((a, b) => String(b.entryDate || b.dueDate || b.createdAt || '').localeCompare(String(a.entryDate || a.dueDate || a.createdAt || '')));
    renderAll();
  }

  async function addRecord(row) {
    row.clientId = row.clientId || makeId();
    row.syncStatus = 'pending';
    row.createdAt = row.createdAt || new Date().toISOString();
    await saveLocal(row);
    await loadRecords();
    if (navigator.onLine) {
      const synced = await syncOne(row);
      await loadRecords();
      if (synced) {
        setSyncState('Backed up to your farm account', 'Your saved records are available on this device and in your account.');
        setStatus('Saved and backed up.');
      } else if (row.syncStatus === 'conflict') {
        setSyncState('Review edits from another device', 'This entry changed in your account while you were editing. Choose which version to keep below.');
        setStatus('This entry needs your choice before it can sync.', true);
      } else {
        setSyncState('Saved on this device', 'This entry still needs to sync to your farm account.');
        setStatus('Saved on this device. It will sync when your connection returns.', true);
      }
    } else {
      setSyncState('Saved on this device', 'You are offline. New entries will sync to your farm account when you reconnect.');
      setStatus('Saved on this device. It will sync when you are online.');
    }
  }

  async function deleteRecord(row) {
    if (!window.confirm('Delete this journal entry?')) return;
    if (navigator.onLine && row.syncStatus === 'synced') {
      try {
        await requestApi({ action: 'delete', entity: row.type, clientId: row.clientId });
        await removeLocal(row.clientId);
      } catch {
        await saveLocal({ clientId: row.clientId, type: 'deleted', entity: row.type, syncStatus: 'pending' });
      }
    } else if (row.syncStatus === 'pending') {
      await saveLocal({ clientId: row.clientId, type: 'deleted', entity: row.type, syncStatus: 'pending' });
    } else {
      await removeLocal(row.clientId);
    }
    await loadRecords();
    if (navigator.onLine) await syncAll();
  }

  function entryActions(row, extra = '') {
    const conflict = row.syncStatus === 'conflict' ? `<button class="button" type="button" data-conflict-keep="${escapeHtml(row.clientId)}">Keep mine</button><button class="button button-light" type="button" data-conflict-server="${escapeHtml(row.clientId)}">Use saved copy</button>` : '';
    return `<div class="journal-entry-actions">${conflict}${extra}<button class="button button-light" type="button" data-delete="${escapeHtml(row.clientId)}">Delete</button></div>`;
  }

  function emptyList(target, message) {
    target.innerHTML = `<p class="journal-empty">${escapeHtml(message)}</p>`;
  }

  function stockUsageSummary(entryClientId) {
    const moves = records.filter((row) => row.type === 'stock' && row.linkedEntryClientId === entryClientId && Number(row.quantityDelta) < 0);
    if (!moves.length) return '';
    const items = moves.map((move) => {
      const stock = records.find((row) => row.type === 'inventory' && row.clientId === move.stockClientId);
      return `${Math.abs(Number(move.quantityDelta))} ${stock?.unit || ''} ${stock?.name || 'supply'}`.trim();
    });
    return `<p>Supplies used: ${escapeHtml(items.join(', '))}</p>`;
  }

  function renderTasks() {
    const target = document.getElementById('journal-task-list');
    const tasks = records.filter((row) => row.type === 'task').sort((a, b) => String(a.dueDate).localeCompare(String(b.dueDate)));
    if (!tasks.length) return emptyList(target, 'No tasks yet. Add the next job you want to remember.');
    target.innerHTML = tasks.map((row) => {
      const complete = Boolean(row.completedAt);
      const overdue = !complete && row.dueDate < today();
      const badge = complete ? 'Done' : overdue ? 'Overdue' : row.dueDate === today() ? 'Due today' : `Due ${row.dueDate}`;
      const completeButton = complete ? '' : `<button class="button" type="button" data-complete="${escapeHtml(row.clientId)}">Done</button>`;
      return `<article class="journal-entry ${complete ? 'is-complete' : ''}"><div class="journal-entry-main"><h3>${escapeHtml(row.title)} <span class="journal-status-chip">${escapeHtml(badge)}</span></h3><p>${escapeHtml(row.category)}${row.repeatDays ? ` · repeats every ${escapeHtml(row.repeatDays)} days` : ''}</p>${row.note ? `<p>${escapeHtml(row.note)}</p>` : ''}${stockUsageSummary(row.clientId)}<small>${escapeHtml(syncLabel(row))}</small></div>${entryActions(row, completeButton)}</article>`;
    }).join('');
    remindDueTasks(tasks);
  }

  function renderFinance() {
    const target = document.getElementById('journal-finance-list');
    const rows = records.filter((row) => row.type === 'finance').sort((a, b) => String(b.entryDate).localeCompare(String(a.entryDate)));
    const domainLabel = 'Farm';
    document.getElementById('journal-cost-label').textContent = `${domainLabel} costs`;
    document.getElementById('journal-sales-label').textContent = `${domainLabel} sales`;
    document.getElementById('journal-profit-label').textContent = `${domainLabel} net balance`;
    document.getElementById('journal-harvest-label').textContent = `${domainLabel} harvest recorded`;
    const costs = rows.filter((row) => row.recordType === 'expense').reduce((sum, row) => sum + Number(row.amount || 0), 0);
    const sales = rows.filter((row) => row.recordType === 'sale').reduce((sum, row) => sum + Number(row.amount || 0), 0);
    const kg = rows.filter((row) => row.recordType === 'harvest' && String(row.unit).toLowerCase() === 'kg').reduce((sum, row) => sum + Number(row.quantity || 0), 0);
    document.getElementById('journal-cost-total').textContent = currency(costs);
    document.getElementById('journal-sales-total').textContent = currency(sales);
    const profit = sales - costs;
    const profitNode = document.getElementById('journal-profit-total');
    profitNode.textContent = `${profit < 0 ? '−' : ''}E${Math.abs(profit).toFixed(2)}`;
    profitNode.style.color = profit < 0 ? '#a34332' : '';
    document.getElementById('journal-harvest-total').textContent = `${kg.toLocaleString()} kg`;
    if (!rows.length) return emptyList(target, 'No farm costs, sales, or harvests recorded yet.');
    target.innerHTML = rows.slice(0, 30).map((row) => `<article class="journal-entry"><div class="journal-entry-main"><h3>${escapeHtml(row.item)} <span class="journal-status-chip">${escapeHtml(row.recordType)}</span></h3><p>${escapeHtml(row.entryDate)} · ${currency(row.amount)}${row.quantity ? ` · ${escapeHtml(row.quantity)} ${escapeHtml(row.unit)}` : ''}</p>${row.note ? `<p>${escapeHtml(row.note)}</p>` : ''}<small>${escapeHtml(syncLabel(row))}</small></div>${entryActions(row)}</article>`).join('');
  }

  function renderCases() {
    const target = document.getElementById('journal-case-list');
    const rows = records.filter((row) => row.type === 'case');
    if (!rows.length) return emptyList(target, 'No crop or animal cases recorded.');
    target.innerHTML = rows.slice(0, 25).map((row) => `<article class="journal-entry"><div class="journal-entry-main"><h3>${escapeHtml(row.subject)} <span class="journal-severity ${row.severity === 'Urgent' ? 'urgent' : ''}">${escapeHtml(row.severity)}</span></h3><p>${escapeHtml(row.entryDate)} · ${escapeHtml(row.symptoms)}</p>${row.photoDataUrl ? `<img class="journal-photo" src="${escapeHtml(row.photoDataUrl)}" alt="Photo for ${escapeHtml(row.subject)}">` : ''}<small>${escapeHtml(syncLabel(row))}</small></div>${entryActions(row, `<button class="button button-light" type="button" data-share-case="${escapeHtml(row.clientId)}">Share report</button>`)}</article>`).join('');
  }

  function renderAnimals() {
    const target = document.getElementById('journal-animal-list');
    const rows = records.filter((row) => row.type === 'animal');
    if (!rows.length) return emptyList(target, 'No animal health or production records yet.');
    target.innerHTML = rows.slice(0, 30).map((row) => `<article class="journal-entry"><div class="journal-entry-main"><h3>${escapeHtml(row.group)} <span class="journal-status-chip">${escapeHtml(row.activity)}</span></h3><p>${escapeHtml(row.entryDate)} · ${escapeHtml(row.quantity || 0)} ${escapeHtml(row.unit)}</p>${row.note ? `<p>${escapeHtml(row.note)}</p>` : ''}${stockUsageSummary(row.clientId)}<small>${escapeHtml(syncLabel(row))}</small></div>${entryActions(row)}</article>`).join('');
  }

  function renderInventory() {
    const target = document.getElementById('journal-inventory-list');
    const movements = records.filter((row) => row.type === 'stock');
    const rows = records.filter((row) => row.type === 'inventory').map((row) => ({ ...row, balance:Math.max(0, Number(row.quantity || 0) + movements.filter((move) => move.stockClientId === row.clientId).reduce((sum, move) => sum + Number(move.quantityDelta || 0), 0)) })).sort((a, b) => String(a.name).localeCompare(String(b.name)));
    const attention = rows.filter((row) => Number(row.balance) <= Number(row.minimumQuantity) || (row.expiryDate && Math.ceil((new Date(`${row.expiryDate}T00:00:00`) - new Date(`${today()}T00:00:00`)) / 86400000) <= 30)).length;
    document.getElementById('journal-low-stock-total').textContent = String(attention);
    if (!rows.length) return emptyList(target, 'No inputs in stock yet. Add seed, fertilizer, feed, or other supplies.');
    target.innerHTML = rows.map((row) => {
      const remaining = row.expiryDate ? Math.ceil((new Date(`${row.expiryDate}T00:00:00`) - new Date(`${today()}T00:00:00`)) / 86400000) : null;
      const low = Number(row.balance) <= Number(row.minimumQuantity);
      const expiring = remaining !== null && remaining <= 30;
      const badges = `${low ? '<span class="journal-severity urgent">Low stock</span>' : ''}${expiring ? `<span class="journal-severity urgent">${remaining < 0 ? 'Expired' : remaining === 0 ? 'Expires today' : `Expires in ${remaining} days`}</span>` : ''}`;
      const adjust = `<button class="button button-light" type="button" data-adjust-inventory="${escapeHtml(row.clientId)}">Add or count stock</button>`;
      const history = movements.filter((move) => move.stockClientId === row.clientId).reduce((sum, move) => sum + (Number(move.quantityDelta) < 0 ? -Number(move.quantityDelta) : 0), 0);
      return `<article class="journal-entry"><div class="journal-entry-main"><h3>${escapeHtml(row.name)} <span class="journal-status-chip">${escapeHtml(row.inputType)}</span> ${badges}</h3><p>${escapeHtml(row.balance)} ${escapeHtml(row.unit)} on hand · ${escapeHtml(history)} ${escapeHtml(row.unit)} used · alert at ${escapeHtml(row.minimumQuantity)} ${escapeHtml(row.unit)}${row.expiryDate ? ` · expiry ${escapeHtml(row.expiryDate)}` : ''}</p>${row.note ? `<p>${escapeHtml(row.note)}</p>` : ''}<small>${escapeHtml(syncLabel(row))}</small></div>${entryActions(row, adjust)}</article>`;
    }).join('');
  }

  function renderYields() {
    const target = document.getElementById('journal-yield-list');
    const rows = records.filter((row) => row.type === 'yield').sort((a, b) => String(b.harvestDate).localeCompare(String(a.harvestDate)));
    const fields = new Set(rows.map((row) => String(row.fieldName).trim().toLowerCase()).filter(Boolean));
    document.getElementById('journal-field-total').textContent = String(fields.size);
    document.getElementById('journal-yield-sales-total').textContent = currency(rows.reduce((sum, row) => sum + Number(row.saleAmount || 0), 0));
    document.getElementById('journal-yield-count-total').textContent = String(rows.length);
    const summary = document.getElementById('journal-yield-summary');
    if (!rows.length) { summary.textContent = ''; return emptyList(target, 'No field harvests recorded yet. Add a field or plot when it is harvested.'); }
    const groups = new Map();
    for (const row of rows) {
      const key = `${String(row.fieldName).trim().toLowerCase()}|${String(row.crop).trim().toLowerCase()}|${String(row.unit).trim().toLowerCase()}`;
      const group = groups.get(key) || { fieldName:row.fieldName, crop:row.crop, unit:row.unit, quantity:0, saleAmount:0, entries:0 };
      group.quantity += Number(row.quantity || 0); group.saleAmount += Number(row.saleAmount || 0); group.entries += 1; groups.set(key, group);
    }
    summary.innerHTML = `<h3>Totals by field and crop</h3><div class="journal-yield-groups">${Array.from(groups.values()).map((group) => `<article><strong>${escapeHtml(group.fieldName)} · ${escapeHtml(group.crop)}</strong><span>${escapeHtml(group.quantity.toLocaleString(undefined, { maximumFractionDigits:3 }))} ${escapeHtml(group.unit)} across ${group.entries} harvest${group.entries === 1 ? '' : 's'}</span><small>Recorded value: ${currency(group.saleAmount)}</small></article>`).join('')}</div>`;
    target.innerHTML = rows.slice(0, 40).map((row) => `<article class="journal-entry"><div class="journal-entry-main"><h3>${escapeHtml(row.crop)} <span class="journal-status-chip">${escapeHtml(row.fieldName)}</span></h3><p>${escapeHtml(row.harvestDate)} · ${escapeHtml(row.quantity)} ${escapeHtml(row.unit)}${row.grade ? ` · ${escapeHtml(row.grade)}` : ''} · value ${currency(row.saleAmount)}</p>${row.note ? `<p>${escapeHtml(row.note)}</p>` : ''}<small>${escapeHtml(syncLabel(row))}</small></div>${entryActions(row)}</article>`).join('');
    summary.querySelectorAll('.journal-yield-groups article').forEach((node) => node.classList.add('journal-yield-group'));
  }

  function renderSnapshot() {
    const target = document.getElementById('offline-farm-snapshot');
    const saved = localStorage.getItem(`agrisense-weather:${owner}`);
    if (!saved) return;
    try {
      const data = JSON.parse(saved);
      target.innerHTML = `<div class="offline-farm-card"><strong>${escapeHtml(data.location)}</strong><span>${escapeHtml(data.temperature)}° ${escapeHtml(data.condition)}</span><span>24h rain: ${escapeHtml(data.rain)}%</span><small>Saved ${escapeHtml(new Date(data.savedAt).toLocaleString())}</small><p>${escapeHtml(data.advice)}</p></div>`;
    } catch { /* Ignore a malformed local snapshot. */ }
  }

  function renderAll() {
    renderTasks(); renderFinance(); renderCases(); renderAnimals(); renderInventory(); renderYields(); renderSnapshot(); updateStockChoices();
  }

  function setDomain() { activeDomain = 'all'; renderAll(); }

  function updateStockChoices() {
    const options = records.filter((row) => row.type === 'inventory').map((row) => { const used = records.filter((move) => move.type === 'stock' && move.stockClientId === row.clientId).reduce((sum, move) => sum + Number(move.quantityDelta || 0), 0); const balance = Math.max(0, Number(row.quantity || 0) + used); return `<option value="${escapeHtml(row.clientId)}">${escapeHtml(row.name)} (${escapeHtml(balance)} ${escapeHtml(row.unit)} available)</option>`; }).join('');
    root.querySelectorAll('[name$="_stock_id"]').forEach((select) => { const value = select.value; select.innerHTML = `<option value="">None</option>${options}`; select.value = value; });
  }

  function showEntryType(type) {
    const form = document.getElementById('journal-unified-form');
    if (!form) return;
    form.elements.entry_type.value = type;
    form.querySelectorAll('[data-fields]').forEach((fields) => {
      const visible = fields.dataset.fields === type;
      fields.hidden = !visible;
      fields.querySelectorAll('input,select,textarea').forEach((control) => { control.disabled = !visible; control.required = visible && ['task_title','task_due','finance_item','finance_date','yield_field','yield_crop','yield_date','yield_quantity','yield_unit','case_subject','case_symptoms','inventory_name','inventory_quantity','inventory_unit','animal_group','animal_date'].includes(control.name); });
    });
    updateStockChoices();
  }

  function applyRecordingPreferences() {
    const anyEnabled = Object.values(recordingPreferences).some(Boolean);
    root.querySelectorAll('[data-record-toggle]').forEach((input) => { input.checked = recordingPreferences[input.dataset.recordToggle] !== false; });
    root.querySelectorAll('[data-entry-type]').forEach((button) => { button.hidden = recordingPreferences[button.dataset.entryType] === false; });
    const selector = document.getElementById('journal-entry-type');
    if (selector) {
      selector.querySelectorAll('option').forEach((option) => { option.disabled = recordingPreferences[option.value] === false; });
      if (selector.selectedOptions[0]?.disabled) selector.value = Array.from(selector.options).find((option) => !option.disabled)?.value || 'task';
      selector.disabled = !anyEnabled;
    }
    const submit = document.querySelector('#journal-unified-form button[type="submit"]');
    if (submit) submit.disabled = !anyEnabled;
  }

  async function saveRecordingPreferences() {
    localStorage.setItem(`agrisense-journal-recording:${owner}`, JSON.stringify(recordingPreferences));
    preferencesDirty = true;
    localStorage.setItem(`agrisense-journal-recording-dirty:${owner}`, '1');
    applyRecordingPreferences();
    if (!navigator.onLine) { setStatus('Recording choices saved on this device. They will sync when you are online.'); return; }
    try { await requestApi({ action:'savePreferences', settings:recordingPreferences }); preferencesDirty = false; localStorage.removeItem(`agrisense-journal-recording-dirty:${owner}`); setStatus('Recording choices saved to your farm account.'); }
    catch { setStatus('Recording choices are saved on this device and will sync when you are online.'); }
  }

  async function remindDueTasks(tasks) {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    const due = tasks.filter((row) => !row.completedAt && row.dueDate <= today());
    if (!due.length) return;
    const stamp = today();
    const key = `agrisense-reminder:${owner}:${stamp}`;
    if (localStorage.getItem(key)) return;
    localStorage.setItem(key, '1');
    const message = due.length === 1 ? due[0].title : `${due.length} farm tasks are due.`;
    if (navigator.serviceWorker?.ready) {
      try { (await navigator.serviceWorker.ready).showNotification('agriSense task reminder', { body: message, icon: '/assets/agrisense-logo.png' }); return; } catch { /* Use the page notification below. */ }
    }
    new Notification('agriSense task reminder', { body: message });
  }

  function setFormDefaults() {
    document.querySelectorAll('input[type="date"]:not([name="expiry_date"])').forEach((input) => { if (!input.value) input.value = today(); });
  }

  function bindForms() {
    const form = document.getElementById('journal-unified-form');
    form.elements.entry_type.addEventListener('change', () => showEntryType(form.elements.entry_type.value));
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const data = new FormData(event.currentTarget);
      const entryType = String(data.get('entry_type'));
      let row;
      if (entryType === 'task') {
        row = { type:'task', clientId:makeId(), scope:'crops', title:String(data.get('task_title')).trim(), category:String(data.get('task_category')), dueDate:String(data.get('task_due')), repeatDays:Number(data.get('task_repeat') || 0), note:String(data.get('task_note') || '').trim(), completedAt:null };
      } else if (entryType === 'finance') {
        row = { type:'finance', clientId:makeId(), recordType:String(data.get('finance_kind')), scope:String(data.get('finance_scope')), item:String(data.get('finance_item')).trim(), amount:Number(data.get('finance_amount') || 0), quantity:Number(data.get('finance_quantity') || 0), unit:String(data.get('finance_unit') || '').trim(), entryDate:String(data.get('finance_date')), note:String(data.get('finance_note') || '').trim() };
      } else if (entryType === 'inventory') {
        row = { type:'inventory', clientId:makeId(), name:String(data.get('inventory_name')).trim(), inputType:String(data.get('inventory_kind')), quantity:Number(data.get('inventory_quantity')), unit:String(data.get('inventory_unit')).trim(), minimumQuantity:Number(data.get('inventory_minimum') || 0), expiryDate:String(data.get('inventory_expiry') || ''), note:String(data.get('inventory_note') || '').trim() };
      } else if (entryType === 'yield') {
        row = { type:'yield', clientId:makeId(), fieldName:String(data.get('yield_field')).trim(), crop:String(data.get('yield_crop')).trim(), harvestDate:String(data.get('yield_date')), quantity:Number(data.get('yield_quantity')), unit:String(data.get('yield_unit')).trim(), grade:String(data.get('yield_grade') || '').trim(), saleAmount:Number(data.get('yield_sale') || 0), note:String(data.get('yield_note') || '').trim() };
      } else if (entryType === 'animal') {
        row = { type:'animal', clientId:makeId(), group:String(data.get('animal_group')).trim(), activity:String(data.get('animal_activity')), quantity:Number(data.get('animal_quantity') || 0), unit:String(data.get('animal_unit') || '').trim(), entryDate:String(data.get('animal_date')), note:String(data.get('animal_note') || '').trim() };
      } else if (entryType === 'case') {
        const file = data.get('case_photo'); let photoDataUrl = '';
        if (file instanceof File && file.size) { try { photoDataUrl = await shrinkPhoto(file); } catch (error) { setStatus(error.message, true); return; } }
        const photoMatch = photoDataUrl.match(/^data:(image\/(?:jpeg|png|webp));base64,(.+)$/);
        row = { type:'case', clientId:makeId(), subjectType:String(data.get('case_scope')), subject:String(data.get('case_subject')).trim(), severity:String(data.get('case_severity')), symptoms:String(data.get('case_symptoms')).trim(), photoDataUrl, photoMime:photoMatch?.[1] || '', photoName:file instanceof File ? file.name.slice(0,200) : '', photoBase64:photoMatch?.[2] || '', entryDate:today() };
      }
      if (!row) return;
      const usedStockId = entryType === 'task' ? String(data.get('task_stock_id') || '') : entryType === 'animal' ? String(data.get('animal_stock_id') || '') : '';
      const usedAmount = entryType === 'task' ? Number(data.get('task_stock_used') || 0) : Number(data.get('animal_stock_used') || 0);
      if (usedStockId && usedAmount > 0) {
        const stock = records.find((entry) => entry.type === 'inventory' && entry.clientId === usedStockId);
        const balance = stock ? Math.max(0, Number(stock.quantity || 0) + records.filter((move) => move.type === 'stock' && move.stockClientId === usedStockId).reduce((sum, move) => sum + Number(move.quantityDelta || 0), 0)) : 0;
        if (!stock || usedAmount > balance) return setStatus(`Only ${balance} ${stock?.unit || ''} is available. Update the amount used or add more stock first.`, true);
      }
      await addRecord(row);
      if (usedStockId && usedAmount > 0) await addStockMovement(row, usedStockId, usedAmount, -1);
      form.reset(); showEntryType('task'); setFormDefaults();
    });
    showEntryType('task');
  }

  async function addStockMovement(linkedEntry, stockClientId, quantity, direction) {
    const movement = { type:'stock', clientId:makeId(), stockClientId, linkedEntryClientId:linkedEntry.clientId, quantityDelta:Number(quantity) * Number(direction), entryDate:today(), note:`Used with ${linkedEntry.type} entry` };
    await addRecord(movement);
  }

  function shrinkPhoto(file) {
    return new Promise((resolve, reject) => {
      if (!file.type.startsWith('image/')) return reject(new Error('Choose an image file.'));
      const image = new Image();
      const url = URL.createObjectURL(file);
      image.onload = () => {
        const scale = Math.min(1, 1280 / Math.max(image.width, image.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(image.width * scale));
        canvas.height = Math.max(1, Math.round(image.height * scale));
        canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);
        URL.revokeObjectURL(url);
        canvas.toBlob((blob) => {
          if (!blob || blob.size > 680 * 1024) return reject(new Error('Photo is too large after compression. Choose a smaller image.'));
          const reader = new FileReader();
          reader.onload = () => resolve(String(reader.result));
          reader.onerror = () => reject(new Error('Could not read the selected photo.'));
          reader.readAsDataURL(blob);
        }, 'image/jpeg', 0.78);
      };
      image.onerror = () => { URL.revokeObjectURL(url); reject(new Error('Could not open that image.')); };
      image.src = url;
    });
  }

  async function onAction(event) {
    const completeId = event.target.closest('[data-complete]')?.dataset.complete;
    const deleteId = event.target.closest('[data-delete]')?.dataset.delete;
    const keepConflictId = event.target.closest('[data-conflict-keep]')?.dataset.conflictKeep;
    const useServerId = event.target.closest('[data-conflict-server]')?.dataset.conflictServer;
    const shareId = event.target.closest('[data-share-case]')?.dataset.shareCase;
    const adjustInventoryId = event.target.closest('[data-adjust-inventory]')?.dataset.adjustInventory;
    if (keepConflictId) {
      const row = records.find((entry) => entry.clientId === keepConflictId);
      if (!row) return;
      row.updatedAt = row.serverUpdatedAt || undefined;
      delete row.serverUpdatedAt;
      row.syncStatus = 'pending';
      await saveLocal(row);
      await loadRecords();
      await syncAll();
    } else if (useServerId) {
      await removeLocal(useServerId);
      await loadRecords();
      await syncAll();
    } else if (completeId) {
      const row = records.find((entry) => entry.clientId === completeId);
      if (!row) return;
      row.completedAt = new Date().toISOString();
      await addRecord(row);
      if (Number(row.repeatDays) > 0) {
        const next = new Date(`${row.dueDate}T12:00:00`);
        next.setDate(next.getDate() + Number(row.repeatDays));
        await addRecord({ ...row, id:undefined, key:undefined, clientId:makeId(), dueDate:next.toLocaleDateString('en-CA'), completedAt:null, createdAt:new Date().toISOString(), syncStatus:'pending' });
      }
    } else if (deleteId) {
      const row = records.find((entry) => entry.clientId === deleteId);
      if (row) await deleteRecord(row);
    } else if (shareId) {
      const row = records.find((entry) => entry.clientId === shareId);
      if (row) await shareCase(row);
    } else if (adjustInventoryId) {
      const row = records.find((entry) => entry.clientId === adjustInventoryId);
      if (!row) return;
      const movements = records.filter((entry) => entry.type === 'stock' && entry.stockClientId === row.clientId);
      const current = Math.max(0, Number(row.quantity || 0) + movements.reduce((sum, move) => sum + Number(move.quantityDelta || 0), 0));
      const value = window.prompt(`Enter current ${row.unit} on hand for ${row.name}:`, String(current));
      if (value === null) return;
      const quantity = Number(value);
      if (!Number.isFinite(quantity) || quantity < 0) return setStatus('Enter a valid stock amount of zero or more.', true);
      const thresholdValue = window.prompt(`Low-stock alert level in ${row.unit}:`, String(row.minimumQuantity));
      if (thresholdValue === null) return;
      const minimumQuantity = Number(thresholdValue);
      if (!Number.isFinite(minimumQuantity) || minimumQuantity < 0) return setStatus('Enter a valid low-stock level of zero or more.', true);
      await addRecord({ ...row, minimumQuantity });
      const delta = quantity - current;
      if (delta) await addStockMovement({ clientId:makeId(), type:'stock-count', entryDate:today() }, row.clientId, Math.abs(delta), delta > 0 ? 1 : -1);
    }
  }

  async function shareCase(row) {
    const report = `agriSense field case\nSubject: ${row.subject}\nDate: ${row.entryDate}\nSeverity: ${row.severity}\nSymptoms: ${row.symptoms}\n`;
    const file = new File([report], `agrisense-case-${row.entryDate}.txt`, { type:'text/plain' });
    if (navigator.canShare?.({ files:[file] }) && navigator.share) {
      try { await navigator.share({ title:'agriSense field case', text:report, files:[file] }); return; } catch (error) { if (error.name === 'AbortError') return; }
    }
    download(file, file.name);
    if (row.photoDataUrl) {
      const photo = await (await fetch(row.photoDataUrl)).blob();
      download(photo, row.photoName || `agrisense-case-photo-${row.entryDate}.jpg`);
    }
  }

  function download(blob, name) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a'); link.href = url; link.download = name; link.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  }

  async function exportBackup() {
    const all = await allLocal();
    const backup = { app:'agriSense farm journal', version:1, exportedAt:new Date().toISOString(), records:all.filter((row) => row.type !== 'deleted').map(({ owner:localOwner, key, ...row }) => row) };
    download(new Blob([JSON.stringify(backup, null, 2)], { type:'application/json' }), `agrisense-journal-${today()}.json`);
  }

  async function importBackup(file) {
    const backup = JSON.parse(await file.text());
    if (backup.app !== 'agriSense farm journal' || !Array.isArray(backup.records)) throw new Error('That file is not an agriSense journal backup.');
    for (const row of backup.records) {
      if (!['task','finance','case','animal','inventory','yield','stock'].includes(row.type)) continue;
      row.clientId = row.clientId || makeId();
      row.syncStatus = 'pending';
      await saveLocal(row);
    }
    await loadRecords();
    if (navigator.onLine) await syncAll();
    setStatus('Backup imported. Entries will be sent to your account when online.');
  }

  function exportCsv() {
    const rows = records.filter((row) => ['finance','inventory','yield','stock'].includes(row.type));
    const quote = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
    const lines = [['Record type','Scope','Date','Field','Crop or input','Category','Quantity','Unit','Minimum stock','Sale value SZL','Grade','Expiry date','Notes'], ...rows.map((row) => row.type === 'finance' ? ['finance',row.scope || 'crops',row.entryDate,'',row.item,row.recordType,row.quantity,row.unit,'',row.amount,'','',row.note] : row.type === 'inventory' ? ['inventory',row.inputType === 'feed' ? 'animals' : 'crops','', '',row.name,row.inputType,row.quantity,row.unit,row.minimumQuantity,'','',row.expiryDate,row.note] : row.type === 'stock' ? ['stock movement','',row.entryDate,'',row.stockClientId,'used/added',row.quantityDelta,'','','','','',row.linkedEntryClientId] : ['field harvest','crops',row.harvestDate,row.fieldName,row.crop,'',row.quantity,row.unit,'',row.saleAmount,row.grade,'',row.note])];
    download(new Blob([lines.map((line) => line.map(quote).join(',')).join('\r\n')], { type:'text/csv;charset=utf-8' }), `agrisense-farm-records-${today()}.csv`);
  }

  async function enableReminders() {
    if (!('Notification' in window)) return setStatus('This browser does not support notifications.', true);
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') return setStatus('Reminders are off. Allow notifications in browser settings to enable them.', true);
    setStatus('Reminders show when you open the farm journal on this device.');
    remindDueTasks(records.filter((row) => row.type === 'task'));
  }

  async function start() {
    if (!owner) {
      root.innerHTML = '<div class="journal-storage-note">Sign in on this device while online to open your private journal.</div>';
      return;
    }
    try {
      database = await openDatabase();
      const response = await fetch('assets/farm-journal-ui.html', { cache:'no-store' });
      if (!response.ok) throw new Error('Could not load the farm journal.');
      root.innerHTML = await response.text();
      setFormDefaults();
      bindForms();
      root.addEventListener('click', onAction);
      root.querySelectorAll('[data-open-editor]').forEach((button) => {
        button.addEventListener('click', () => {
          const editor = document.getElementById('journal-editor-unified');
          if (!editor) return;
          showEntryType(button.dataset.entryType || 'task');
          editor.scrollIntoView({ behavior:'smooth', block:'center' });
          document.getElementById('journal-entry-type').focus({ preventScroll:true });
        });
      });
      const savedPreferences = localStorage.getItem(`agrisense-journal-recording:${owner}`);
      if (savedPreferences) { try { recordingPreferences = { ...defaultPreferences, ...JSON.parse(savedPreferences) }; } catch {} }
      applyRecordingPreferences();
      root.querySelectorAll('[data-record-toggle]').forEach((input) => input.addEventListener('change', async () => { recordingPreferences[input.dataset.recordToggle] = input.checked; await saveRecordingPreferences(); }));
      setDomain();
      const params = new URLSearchParams(location.search);
      const requestedEditor = params.get('new');
      const editorType = requestedEditor === 'harvest' ? 'yield' : requestedEditor;
      if (['task','finance','inventory','yield','case','animal'].includes(editorType)) {
        const editor = document.getElementById('journal-editor-unified');
        if (editor) {
          showEntryType(editorType);
          if (editorType === 'task' && params.get('title')) document.querySelector('#journal-unified-form [name="task_title"]').value = params.get('title');
          if (editorType === 'task' && params.get('note')) document.querySelector('#journal-unified-form [name="task_note"]').value = params.get('note');
          editor.scrollIntoView({ behavior:'smooth', block:'center' });
        }
      }
      document.getElementById('journal-reminder-enable').addEventListener('click', enableReminders);
      document.getElementById('journal-export').addEventListener('click', exportBackup);
      document.getElementById('journal-export-csv').addEventListener('click', exportCsv);
      document.getElementById('journal-retry-sync').addEventListener('click', syncAll);
      document.getElementById('journal-import').addEventListener('change', async (event) => {
        const file = event.target.files?.[0];
        if (!file) return;
        try { await importBackup(file); } catch (error) { setStatus(error.message || 'Could not import this backup.', true); }
        event.target.value = '';
      });
      window.addEventListener('online', syncAll);
      await loadRecords();
      if (navigator.onLine) await syncAll();
      else {
        setSyncState('Saved on this device', 'You are offline. Changes sync to your farm account when you reconnect.');
        setStatus('Offline mode: saved entries are available on this device.');
      }
    } catch (error) {
      root.innerHTML = `<div class="journal-storage-note">Could not open local journal storage: ${escapeHtml(error.message)}</div>`;
    }
  }

  document.addEventListener('DOMContentLoaded', start);
})();
