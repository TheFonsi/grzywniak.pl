(() => {
  const optional = new Set(['clientTaxId', 'paymentDetails']);
  const unresolved = value => /\[DO (UZUPEŁNIENIA|UZGODNIENIA):/iu.test(value);
  const read = form => { try { return JSON.parse(form.elements.reviewState.value) || {}; } catch { return {}; } };
  const write = (form, state) => { form.elements.reviewState.value = JSON.stringify(state); };
  function refresh(form) {
    const state = read(form);
    form.querySelectorAll('[data-review-field]').forEach(card => {
      const name = card.dataset.reviewField;
      const input = form.elements.namedItem(name);
      const label = card.querySelector('label');
      card.hidden = label.hidden;
      const entry = state[name];
      const accepted = entry?.accepted && entry.value.trim() === input.value.trim();
      const missing = (!input.value.trim() && !optional.has(name)) || unresolved(input.value);
      card.dataset.accepted = accepted ? '1' : '0';
      card.querySelector('[data-review-status]').textContent = accepted ? 'Zaakceptowano' : missing ? 'Uzupełnij dane' : entry ? 'Propozycja do akceptacji' : 'Dane do sprawdzenia';
      const accept = card.querySelector('[data-review-accept]');
      accept.disabled = !!accepted || missing || !!form.dataset.saving;
      accept.textContent = accepted ? 'Zaakceptowano' : 'Akceptuj';
      const edit = card.querySelector('[data-review-edit]');
      edit.disabled = !!form.dataset.saving;
      input.hidden = !!accepted;
      const preview = card.querySelector('[data-review-preview]');
      preview.hidden = !accepted;
      preview.textContent = input.tagName === 'SELECT' ? input.selectedOptions[0]?.textContent || input.value : input.value || 'Nie dotyczy';
    });
    const pending = Object.entries(state).filter(([name,item]) => {
      const input=form.elements.namedItem(name);
      const card=input?.closest('[data-review-field]');
      return card && !card.hidden && (!item.accepted || item.value.trim()!==input.value.trim());
    }).length;
    form.querySelector('[data-review-summary]').textContent = pending ? `Do akceptacji: ${pending} pól. Możesz zapisać projekt i wrócić do niego później.` : 'Akceptacja pól zatwierdza projekt wewnętrznie — nie oznacza podpisania umowy przez klienta.';
  }
  function init(form) {
    if(!form?.elements.reviewState || form.dataset.reviewReady) return;
    form.dataset.reviewReady='1';
    const summary=document.createElement('p'); summary.dataset.reviewSummary=''; summary.setAttribute('role','status'); form.prepend(summary);
    [...form.querySelectorAll('textarea,input:not([type="hidden"]),select')].forEach(input => {
      const label=input.closest('label'); if(!label) return;
      const card=document.createElement('section'); card.dataset.reviewField=input.name;
      label.before(card); card.append(label);
      const preview=document.createElement('p'); preview.dataset.reviewPreview=''; preview.style.whiteSpace='pre-wrap'; card.append(preview);
      const status=document.createElement('span'); status.dataset.reviewStatus=''; status.setAttribute('role','status'); card.append(status);
      const accept=document.createElement('button'); accept.type='button'; accept.className='button'; accept.dataset.reviewAccept='';
      accept.onclick=() => { const state=read(form); state[input.name]={value:input.value,accepted:true}; write(form,state); refresh(form); };
      const edit=document.createElement('button'); edit.type='button'; edit.className='button'; edit.dataset.reviewEdit=''; edit.textContent='Zmień';
      edit.onclick=() => { const state=read(form); state[input.name]={value:input.value,accepted:false}; write(form,state); refresh(form); input.focus(); };
      card.append(accept,edit);
      const change=() => {
        const state=read(form); state[input.name]={value:input.value,accepted:false};
        if(input.name==='ipMode') {
          const license=form.querySelector('[data-license-terms]'); if(license) license.hidden=!['exclusive','nonexclusive'].includes(input.value);
          for(const key of ['ip','terms']) if(state[key]) state[key].accepted=false;
        }
        write(form,state); refresh(form);
      };
      input.addEventListener('input',change); input.addEventListener('change',change);
    });
    refresh(form);
  }
  function apply(form, result) {
    init(form); const state=read(form);
    for(const [name,value] of Object.entries({...result.fields,...result.facts})) {
      const input=form.elements.namedItem(name); if(!input || typeof value!=='string') continue;
      // Re-running AI never overwrites an accepted value.
      if(state[name]?.accepted && state[name].value.trim()===input.value.trim()) continue;
      input.value=value; state[name]={value,accepted:false};
    }
    const license=form.querySelector('[data-license-terms]'); if(license) license.hidden=!['exclusive','nonexclusive'].includes(form.elements.ipMode.value);
    write(form,state); refresh(form);
  }
  window.contractReview={init,apply,refresh};
})();
