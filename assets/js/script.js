document.addEventListener('DOMContentLoaded', () => {
  document.body.classList.toggle('low-data-mode', localStorage.getItem('agrisense-low-data') === '1');
  const controls = document.createElement('div');
  controls.className = 'system-preferences';
  const lowData = document.createElement('button');
  lowData.id = 'agrisense-low-data';
  lowData.type = 'button';
  lowData.className = 'low-data-toggle';
  const updateLowDataLabel = (enabled) => {
    lowData.textContent = `Low data: ${enabled ? 'on' : 'off'}`;
    lowData.setAttribute('aria-pressed', String(enabled));
  };
  updateLowDataLabel(document.body.classList.contains('low-data-mode'));
  lowData.addEventListener('click', () => {
    const enabled = !document.body.classList.contains('low-data-mode');
    document.body.classList.toggle('low-data-mode', enabled);
    localStorage.setItem('agrisense-low-data', enabled ? '1' : '0');
    updateLowDataLabel(enabled);
  });
  controls.append(lowData);
  const host = document.querySelector('.farmer-topbar') || document.querySelector('.login-card, .auth-panel');
  if (host) host.append(controls);
  document.querySelectorAll('[data-dismiss]').forEach((button) => {
    button.addEventListener('click', () => button.closest('.alert')?.remove());
  });

  const phone = document.querySelector('input[name="phone"]');
  if (phone) phone.addEventListener('input', () => { phone.value = phone.value.replace(/\D/g, '').slice(0, 8); });

  document.querySelectorAll('.password-toggle').forEach((button) => {
    button.addEventListener('click', () => {
      const input = button.parentElement.querySelector('input');
      const reveal = input.type === 'password';
      input.type = reveal ? 'text' : 'password';
      button.textContent = reveal ? 'Hide' : 'Show';
      button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
      button.setAttribute('aria-pressed', String(reveal));
    });
  });

  const owner = document.body.dataset.farmerKey?.trim().toLowerCase();
  if (owner) {
    localStorage.setItem('agrisense-active-farmer', owner);
    const snapshot = {
      location: document.body.dataset.weatherLocation || '',
      temperature: document.body.dataset.weatherTemperature || '',
      condition: document.body.dataset.weatherCondition || '',
      rain: document.body.dataset.weatherRain || '',
      advice: document.body.dataset.weatherAdvice || '',
      savedAt: new Date().toISOString()
    };
    if (snapshot.location && snapshot.temperature !== '') localStorage.setItem(`agrisense-weather:${owner}`, JSON.stringify(snapshot));
  }

  document.querySelectorAll('a[href="logout.php"]').forEach((link) => {
    link.addEventListener('click', () => localStorage.removeItem('agrisense-active-farmer'));
  });

  if (document.body.dataset.weatherLocation) {
    const openedAt = Date.now();
    const refreshWhenDue = () => { if (!document.body.classList.contains('low-data-mode') && Date.now() - openedAt >= 15 * 60 * 1000) window.location.reload(); };
    window.setTimeout(refreshWhenDue, 15 * 60 * 1000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshWhenDue(); });
  }

  if ('serviceWorker' in navigator) navigator.serviceWorker.register('/service-worker.js').catch(() => {});
});
