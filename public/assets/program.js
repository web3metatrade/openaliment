'use strict';
(() => {
  const csrf = () => document.querySelector('input[name="_csrf"]')?.value || '';
  const api = async (path, method = 'GET', data = undefined, key = crypto.randomUUID()) => {
    const response = await fetch(path, {method, credentials: 'same-origin', cache: 'no-store', headers: {'Accept': 'application/json', ...(data === undefined ? {} : {'Content-Type': 'application/json', 'X-CSRF-Token': csrf(), 'Idempotency-Key': key})}, ...(data === undefined ? {} : {body: JSON.stringify(data)})});
    if (!response.headers.get('Content-Type')?.includes('application/json')) throw new Error('Sesiunea nu mai este disponibilă. Conectează-te din nou.');
    const result = await response.json();
    if (!response.ok) throw new Error(result.message || 'Operația nu a reușit. Reîncearcă.');
    return result;
  };
  document.querySelectorAll('[data-program-print]').forEach(button => button.addEventListener('click', () => window.print()));
  document.querySelector('[data-program-cui]')?.addEventListener('input', () => {
    const result = document.querySelector('[data-company-result]'); if (!result) return;
    result.hidden = true; result.querySelector('input[name="company_check_id"]').value = '';
    result.querySelectorAll('input,button').forEach(input => { input.disabled = true; });
  });
  document.querySelectorAll('[data-program-upload]').forEach(root => {
    const button = root.querySelector('[data-upload-button]'); const status = root.querySelector('[data-upload-status]');
    button.addEventListener('click', async () => {
      const file = root.querySelector('input[type="file"]').files[0];
      if (!file || !root.querySelector('[data-upload-rights]').checked) { status.textContent = 'Alege imaginea și confirmă dreptul de utilizare.'; return; }
      if (file.size > 2097152 || !['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) { status.textContent = 'Folosește PNG, JPEG sau WebP de cel mult 2 MB.'; return; }
      button.disabled = true; status.textContent = 'Se încarcă și se verifică imaginea…';
      try {
        const encoded = await new Promise((resolve, reject) => { const reader = new FileReader(); reader.onload = () => resolve(String(reader.result).split(',')[1]); reader.onerror = reject; reader.readAsDataURL(file); });
        const result = await api('/api/v2/program/assets', 'POST', {purpose: root.dataset.programUpload, content_base64: encoded, rights_confirmed: true});
        const field = root.dataset.programUpload === 'lab_evidence' ? 'evidence_asset_id' : 'asset_id';
        document.querySelectorAll(`input[name="${field}"]`).forEach(input => { input.value = result.id; });
        let image = root.querySelector('img'); if (!image) { image = document.createElement('img'); image.className = 'program-logo'; image.width = 160; image.height = 100; image.alt = 'Imaginea încărcată'; root.append(image); }
        image.src = result.url; status.textContent = 'Imagine încărcată. Salvează formularul pentru a o asocia.';
      } catch (error) { status.textContent = error.message; }
      finally { button.disabled = false; }
    });
  });
  const confirm = document.querySelector('[data-program-confirm]');
  if (confirm) {
    const params = new URLSearchParams(location.hash.slice(1)); const fromLink = params.get('token');
    if (fromLink && /^[a-f0-9]{64}$/.test(fromLink)) { sessionStorage.setItem('oa-program-confirm', fromLink); history.replaceState(null, '', location.pathname); }
    const token = sessionStorage.getItem('oa-program-confirm'); const details = confirm.querySelector('[data-confirm-details]');
    const controls = confirm.querySelector('[data-confirm-controls]'); const status = confirm.querySelector('[data-confirm-status]');
    if (!token) details.textContent = 'Deschide legătura completă din email.';
    else api('/api/v2/program/verifications/preview', 'POST', {token}).then(result => {
      const labels = {personal_email: 'Confirmarea adresei tale de email', partner_contact: 'Confirmarea emailului public al companiei', contributor_consent: 'Acord pentru publicarea profilului de contribuitor', partner_invite: 'Invitație în programul companiei'};
      details.textContent = labels[result.purpose] || 'Solicitare de confirmare';
      if (result.details) {
        const p = document.createElement('p'); p.textContent = result.details.company || `${result.details.name} · ${result.details.summary}`; details.append(p);
        if (result.details.links) result.details.links.forEach(url => { const p = document.createElement('p'); p.textContent = url; details.append(p); });
      }
      controls.hidden = false;
    }).catch(error => { details.textContent = error.message; });
    confirm.querySelector('[data-confirm-button]').addEventListener('click', async event => {
      if (!confirm.querySelector('[data-confirm-accepted]').checked) { status.textContent = 'Confirmă acordul înainte de a continua.'; return; }
      event.target.disabled = true;
      try { const result = await api('/api/v2/program/verifications/confirm', 'POST', {token, accepted: true}); sessionStorage.removeItem('oa-program-confirm'); controls.hidden = true; status.textContent = 'Confirmarea a fost înregistrată.'; const link = document.createElement('a'); link.href = /^\/(?!\/)/.test(result.next) ? result.next : '/invatare/cont'; link.textContent = 'Continuă în cont'; status.append(' ', link); }
      catch (error) { status.textContent = error.message; event.target.disabled = false; }
    });
  }
  const exam = document.querySelector('[data-program-exam]');
  if (exam) {
    let queue = Promise.resolve(); let failed = false; const status = document.getElementById('exam-save-status');
    const extract = fieldset => {
      const type = fieldset.dataset.questionType;
      if (type === 'single') return fieldset.querySelector('input[type="radio"]:checked')?.value ?? null;
      if (type === 'multiple') return [...fieldset.querySelectorAll('input[type="checkbox"]:checked')].map(input => input.value);
      return Object.fromEntries([...fieldset.querySelectorAll('input.form-control')].map(input => [input.name.match(/\[([^\]]+)\]$/)[1], input.value]));
    };
    exam.querySelectorAll('[data-question]').forEach(fieldset => {
      fieldset.addEventListener('change', () => {
        const answer = extract(fieldset); if (answer === null) return;
        queue = queue.then(async () => {
          status.textContent = 'Se salvează răspunsul…';
          try {
            const saved = await api(`/api/v2/program/me/attempts/${exam.dataset.programExam}/answers/${fieldset.dataset.question}`, 'PUT', {expected_revision: Number(fieldset.dataset.revision), answer});
            fieldset.dataset.revision = String(saved.revision); fieldset.querySelector('input[name^="revisions["]').value = String(saved.revision);
            if (!failed) status.textContent = 'Răspuns salvat.';
          } catch (error) { failed = true; status.textContent = error.message + ' Păstrează pagina deschisă; verifică răspunsurile înainte de predare.'; }
        });
      });
    });
    exam.addEventListener('submit', async event => {
      event.preventDefault(); const button = exam.querySelector('button[type="submit"]'); button.disabled = true;
      await queue;
      if (failed) {
        status.textContent = 'Unele răspunsuri nu au fost confirmate. Verifică legătura și reîncarcă pagina pentru a vedea ce este salvat. Termenul examenului rămâne același.'; button.disabled = false; button.removeAttribute('aria-busy'); status.focus(); return;
      }
      try { const result = await api(`/api/v2/program/me/attempts/${exam.dataset.programExam}/submit`, 'POST', {}); location.assign(`/invatare/rezultate/${result.id}`); }
      catch (error) { status.textContent = error.message; button.disabled = false; button.removeAttribute('aria-busy'); }
    });
    const clock = document.querySelector('[data-exam-clock]');
    if (clock) {
      const start = performance.now(); const remaining = Date.parse(clock.dataset.due.replace(' ', 'T') + 'Z') - Date.parse(clock.dataset.server.replace(' ', 'T') + 'Z');
      const tick = () => { const seconds = Math.max(0, Math.floor((remaining - (performance.now() - start)) / 1000)); clock.textContent = `Timp rămas: ${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`; if (seconds === 0) status.textContent = 'Timpul a expirat. Poți vedea rezultatul prin predarea tentativei; sunt luate în calcul răspunsurile salvate înainte de termen.'; };
      tick(); setInterval(tick, 1000);
    }
  }
  document.querySelectorAll('[data-program-editor]').forEach(root => {
    const textarea = root.querySelector('textarea'); const status = root.querySelector('[data-editor-status]'); const type = root.dataset.programEditor;
    const toolbar = document.createElement('div'); toolbar.className = 'button-row my-3'; root.prepend(toolbar);
    const editor = document.createElement('div'); editor.className = 'program-editor-fields'; root.append(editor);
    let doc;
    const read = () => { doc = JSON.parse(textarea.value); if (!doc || typeof doc !== 'object' || Array.isArray(doc)) throw new Error('Documentul trebuie să fie un obiect JSON.'); };
    const save = () => { textarea.value = JSON.stringify(doc, null, 2); };
    const field = (label, value, change, multiline = false) => {
      const wrap = document.createElement('label'); wrap.textContent = label; const input = document.createElement(multiline ? 'textarea' : 'input'); input.className = 'form-control'; input.value = value ?? ''; if (multiline) input.rows = 6;
      input.addEventListener('change', () => { change(input.value); save(); }); wrap.append(input); editor.append(wrap); return input;
    };
    const select = (label, values, current, change) => {
      const wrap = document.createElement('label'); wrap.textContent = label; const input = document.createElement('select'); input.className = 'form-select';
      values.forEach(([value, text]) => { const option = document.createElement('option'); option.value = value; option.textContent = text; option.selected = value === current; input.append(option); });
      input.addEventListener('change', () => change(input.value)); wrap.append(input); editor.append(wrap); return input;
    };
    const button = (label, parent, action) => { const b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-outline-primary btn-sm'; b.textContent = label; b.addEventListener('click', action); parent.append(b); return b; };
    let moduleIndex = 0; let itemIndex = 0;
    const render = () => {
      editor.replaceChildren();
      field('Titlul documentului', doc.title, v => { doc.title = v; }); field('Versiunea ediției', doc.version, v => { doc.version = v; });
      if (type === 'course') {
        field('Rezumat', doc.summary, v => { doc.summary = v; }, true);
        select('Licența materialelor proprii', ['CC-BY-4.0','CC-BY-SA-4.0','CC0-1.0'].map(v => [v,v]), doc.license, v => { doc.license = v; save(); });
        doc.modules ||= [];
        select('Modul', doc.modules.map((m,i) => [String(i),m.title || `Modul ${i+1}`]), String(moduleIndex), v => { moduleIndex = Number(v); itemIndex = 0; render(); });
        button('Adaugă modul', editor, () => { doc.modules.push({key:`M${String(doc.modules.length+1).padStart(2,'0')}`,title:'Modul nou',specialization:null,lessons:[]}); moduleIndex = doc.modules.length-1; itemIndex = 0; save(); render(); });
        const module = doc.modules[moduleIndex]; if (!module) return;
        field('Titlul modulului', module.title, v => { module.title = v; });
        select('Specializarea modulului', [['','Comun'],['erp-integration','ERP'],['printing-integration','Tipărire'],['platform-operations','Operare'],['product-data','Date produs']], module.specialization || '', v => { module.specialization = v || null; save(); });
        select('Lecție', module.lessons.map((l,i) => [String(i),l.title || `Lecția ${i+1}`]), String(itemIndex), v => { itemIndex = Number(v); render(); });
        button('Adaugă lecție', editor, () => { module.lessons.push({id:crypto.randomUUID().replaceAll('-',''),title:'Lecție nouă',objective:'',body:'',resources:[],practice:{question:'',options:['',''],answer:0,explanation:''}}); itemIndex = module.lessons.length-1; save(); render(); });
        const lesson = module.lessons[itemIndex]; if (!lesson) return;
        field('Titlul lecției', lesson.title, v => { lesson.title = v; }); field('Obiectiv', lesson.objective, v => { lesson.objective = v; }); field('Conținut Markdown sigur', lesson.body, v => { lesson.body = v; }, true);
        field('Întrebarea exercițiului', lesson.practice.question, v => { lesson.practice.question = v; }); field('Variante, câte una pe rând', lesson.practice.options.join('\n'), v => { lesson.practice.options = v.split('\n'); }, true);
        field('Indicele răspunsului corect, de la 0', lesson.practice.answer, v => { lesson.practice.answer = Number(v); }); field('Explicația exercițiului', lesson.practice.explanation, v => { lesson.practice.explanation = v; }, true);
        field('Referințe, fiecare rând: titlu | URL HTTPS', lesson.resources.map(r => `${r.title} | ${r.url}`).join('\n'), v => { lesson.resources = v.split('\n').filter(Boolean).map(line => { const [title,...url] = line.split('|'); return {title:title.trim(),url:url.join('|').trim()}; }); }, true);
      } else {
        doc.questions ||= [];
        select('Întrebare privată', doc.questions.map((q,i) => [String(i),`${i+1}. ${q.stem || q.key}`]), String(itemIndex), v => { itemIndex = Number(v); render(); });
        button('Adaugă întrebare', editor, () => { doc.questions.push({key:crypto.randomUUID(),domain:'identity',type:'single',critical:false,stem:'',options:[{key:'a',text:''},{key:'b',text:''}],answer:'a',explanation:'',reference:'https://ref.gs1.org/standards/digital-link/uri-syntax/1.7.0/'}); itemIndex = doc.questions.length-1; save(); render(); });
        const question = doc.questions[itemIndex]; if (!question) return;
        select('Domeniu', ['identity','uri','resolver','data','api','operations'].map(v => [v,v]), question.domain, v => { question.domain = v; save(); });
        select('Tip', [['single','O variantă'],['multiple','Mai multe variante'],['matching','Asociere'],['uri','Componente URI']], question.type, v => { question.type = v; save(); });
        select('Întrebare critică', [['false','Nu'],['true','Da']], String(question.critical), v => { question.critical = v === 'true'; save(); });
        field('Enunț', question.stem, v => { question.stem = v; }, true);
        field('Variante/componente, fiecare rând: cheie | text', question.options.map(o => `${o.key} | ${o.text}`).join('\n'), v => { question.options = v.split('\n').filter(Boolean).map(line => { const [key,...text] = line.split('|'); return {key:key.trim(),text:text.join('|').trim()}; }); }, true);
        const answerField = field('Răspuns corect ca JSON: "a", ["a","b"] sau {"componentă":"valoare"}', JSON.stringify(question.answer), v => { try { question.answer = JSON.parse(v); answerField.setCustomValidity(''); status.textContent = ''; } catch { status.textContent = 'Răspunsul nu este JSON valid. Corectează-l înainte de salvare.'; answerField.setCustomValidity(status.textContent); } });
        field('Explicație pentru evaluatori', question.explanation, v => { question.explanation = v; }, true); field('Referință HTTPS', question.reference, v => { question.reference = v; });
      }
    };
    button('Editor vizual', toolbar, () => { try { read(); textarea.parentElement.hidden = true; editor.hidden = false; render(); status.textContent = ''; } catch { status.textContent = 'JSON incorect. Corectează documentul sau importă un fișier valid.'; } });
    button('Editează JSON', toolbar, () => { editor.hidden = true; textarea.parentElement.hidden = false; });
    button('Exportă documentul', toolbar, () => { try { read(); const blob = new Blob([JSON.stringify(doc,null,2)],{type:'application/json'}); const url = URL.createObjectURL(blob); const link = document.createElement('a'); link.href = url; link.download = type === 'bank' ? 'banca-intrebari.private.json' : 'curs.json'; link.click(); setTimeout(() => URL.revokeObjectURL(url),1000); } catch { status.textContent = 'Corectează JSON-ul înainte de export.'; } });
    root.querySelector('[data-editor-import]').addEventListener('change', async event => {
      const file = event.target.files[0]; if (!file) return;
      if (file.size > 1200000) { status.textContent = 'Documentul depășește 1,2 MB.'; return; }
      try { const input = JSON.parse(await file.text()); const wrapped = input.manifest || input.bank || input; textarea.value = JSON.stringify(wrapped,null,2); read(); moduleIndex = 0; itemIndex = 0; render(); status.textContent = 'Document importat în editor. Salvează ciorna pentru validarea pe server.'; }
      catch { status.textContent = 'Fișier JSON incorect.'; }
    });
    try { read(); textarea.parentElement.hidden = true; render(); } catch { editor.hidden = true; }
  });
})();
