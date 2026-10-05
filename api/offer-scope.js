(() => {
  const endpoint=document.currentScript?.src ? new URL('offer.php',document.currentScript.src).pathname : location.pathname.startsWith('/api/')?'/api/offer.php':'/offer.php';
  function readItem(item) {const copy=item.cloneNode(true);copy.querySelectorAll('[data-optional-control]').forEach(control=>control.remove());return copy.textContent.trim();}
  function mount(root,offer,sessionId,onSave) {
    const optional=title=>/poza.*zakres|możliwe później|opcjonal|optional|out of scope/i.test(title||'');
    const parts=[...root.querySelectorAll('[data-offer-section]')];
    const visible=(offer.sections||[]).filter(s=>!/zatwierdzone ustalenia|zmiany do potwierdzenia/i.test(s.title||''));
    let busy=false;
    async function change(action,extra,button) {
      if(busy) return;
      if(root.querySelector('[contenteditable="true"]')) {alert('Najpierw zapisz edytowaną treść oferty.');return;}
      if(action==='promoteOptional'&&!confirm('Przenieść ten pomysł do zakresu realizacji? Powstanie nowa wersja oferty. Sprawdź cenę, termin i warunki; klient musi zaakceptować nowy zakres.')) return;
      busy=true;button.disabled=true;
      try {
        const metaResponse=await fetch(endpoint+'?session='+encodeURIComponent(sessionId)+'&format=agreement');const meta=await metaResponse.json();
        if(!metaResponse.ok) throw new Error(meta.message||'Nie udało się pobrać formularza.');
        const response=await fetch(endpoint+'?session='+encodeURIComponent(sessionId),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action,csrf:meta.csrf,expectedVersion:offer.version,...extra})});
        const data=await response.json();if(!response.ok) throw new Error(data.message||'Nie udało się zmienić zakresu.');
        onSave(data.offer);
      } catch(error) {alert(error.message||'Nie udało się zapisać zmiany.');button.disabled=false;}
      finally {busy=false;}
    }
    visible.forEach((section,index)=>{
      if(!optional(section.title)) return;
      const part=parts[index];if(!part) return;
      const note=document.createElement('p');note.className='muted';note.dataset.optionalControl='';note.contentEditable='false';note.textContent='Pomysły rozbudowy — poza obecną ceną. Po ustaleniu z klientem przenieś wybrany punkt do realizacji i sprawdź wycenę.';part.insertBefore(note,part.querySelector('ul'));
      [...(part.querySelector('ul')?.children||[])].filter(li=>li.tagName==='LI'&&!li.hasAttribute('data-change-control')).forEach((li,item)=>{
        if(!section.items?.[item]) return;
        const button=document.createElement('button');button.type='button';button.className='button';button.dataset.optionalControl='';button.contentEditable='false';button.style.cssText='display:block;margin-top:8px;border-color:#3a9477;color:#b9f4dc';button.textContent='Przenieś do zakresu realizacji';
        button.disabled=offer.status==='OUTDATED';button.onclick=()=>change('promoteOptional',{section:offer.sections.indexOf(section),item,text:section.items[item]},button);li.append(button);
      });
      if((section.items||[]).length<3) {
        const button=document.createElement('button');button.type='button';button.className='button';button.dataset.optionalControl='';button.textContent='Uzupełnij pomysły rozbudowy';button.disabled=offer.status==='OUTDATED';button.onclick=()=>change('expandOptional',{},button);part.append(button);
      }
    });
  }
  window.offerScope={mount,readItem};
})();
