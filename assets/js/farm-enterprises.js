(() => {
  const list = document.querySelector('[data-enterprise-list]');
  const add = document.querySelector('[data-add-enterprise]');
  const template = document.getElementById('farm-enterprise-template');
  if (!list || !add || !template) return;

  function refreshRows() {
    for (const row of list.querySelectorAll('.farm-enterprise-row')) {
      const type = row.querySelector('[name="enterprise_type[]"]').value;
      const stage = row.querySelector('[data-crop-stage]');
      if (stage) {
        stage.hidden = type !== 'crop';
        if (type !== 'crop') stage.querySelector('select').value = '';
      }
    }
  }

  add.addEventListener('click', () => {
    if (list.querySelectorAll('.farm-enterprise-row').length >= 30) return;
    list.append(template.content.cloneNode(true));
    refreshRows();
  });
  list.addEventListener('change', (event) => {
    if (event.target.matches('[name="enterprise_type[]"]')) refreshRows();
  });
  list.addEventListener('click', (event) => {
    const remove = event.target.closest('[data-remove-enterprise]');
    if (!remove) return;
    const row = remove.closest('.farm-enterprise-row');
    if (list.querySelectorAll('.farm-enterprise-row').length === 1) {
      row.querySelector('[name="enterprise_type[]"]').value = '';
      row.querySelectorAll('input').forEach((input) => { input.value = ''; });
      const stage = row.querySelector('[name="enterprise_stage[]"]');
      if (stage) stage.value = '';
    } else row.remove();
    refreshRows();
  });
  refreshRows();
})();
