/**
 * Custom cake designer: six steps, a live server price, the main 3D preview (Three.js, lazy-loaded; a 2D SVG
 * drawing on low-end devices, without WebGL or with reduced motion) and the Konva top-design editor.
 * The browser never calculates the price: every change is sent to /custom-cake/price and the server's lines,
 * total and bookable dates are shown. Without JavaScript the form still works as one long page.
 */
import { baseDiameterCm, drawPatternTile, outlinePath, topDiameterCm } from './cake/geometry.js';

const form = document.getElementById('cake-form');

if (form) {
  const data = JSON.parse(form.dataset.config || '{}');
  const i18n = data.i18n || {};
  const geometry = data.geometry;
  const readOnly = !!data.readOnly;
  const library = data.library || {};
  const $ = (s) => form.querySelector(s);
  const $$ = (s) => [...form.querySelectorAll(s)];
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const formatTaka = (paisa) => `৳${(paisa / 100).toLocaleString('en-IN', { maximumFractionDigits: 2 })}`;
  const csrf = $('input[name="_token"]')?.value || '';
  const canvasInput = $('#canvas-input');
  const dateSelect = $('#date');
  const slotSelect = $('#slot');

  let preview = null;
  let editor = null;
  let topCanvas = null;
  let layers = JSON.parse(canvasInput?.value || '{"layers":[]}').layers || [];
  let lastPrice = null;

  // ---------- current choices ----------
  const value = (name) => form.elements[name] instanceof RadioNodeList ? form.elements[name].value : form.elements[name]?.value ?? '';
  const findItem = (kind, code) => (library[kind] || []).find((it) => it.code === code);
  const resolveColour = (colour) => (colour === 'accent' ? value('accent_colour') : colour);
  const spec = () => {
    const drip = value('drip');
    const pattern = findItem('pattern', value('pattern'));
    const top = findItem('border', value('border_top'));
    const bottom = findItem('border', value('border_bottom'));
    return {
      shape: value('shape') || 'round', grams: parseInt(value('grams'), 10) || 1000, tiers: parseInt(value('tiers'), 10) || 1,
      base: value('base_colour') || '#fffaf3', accent: value('accent_colour') || '#fffaf3',
      drip: drip ? value('drip_colour') || '#4a2a1a' : null,
      dripColour: drip ? value('drip_colour') || '#4a2a1a' : null,
      pattern: pattern ? { style: pattern.payload.style, colour: resolveColour(pattern.payload.colour) } : null,
      borderTop: top ? { style: top.payload.style, colour: resolveColour(top.payload.colour) } : null,
      borderBottom: bottom ? { style: bottom.payload.style, colour: resolveColour(bottom.payload.colour) } : null,
    };
  };

  // ---------- steps ----------
  const sections = $$('.cake-step');
  const stepNumbers = [...new Set(sections.map((s) => parseInt(s.dataset.step, 10)))].sort((a, b) => a - b);
  let current = stepNumbers[0];
  const showStep = (n, focus = true) => {
    current = n;
    sections.forEach((s) => { s.hidden = parseInt(s.dataset.step, 10) !== n; });
    $$('[data-goto]').forEach((b) => {
      const isCurrent = parseInt(b.dataset.goto, 10) === n;
      if (isCurrent) b.setAttribute('aria-current', 'step'); else b.removeAttribute('aria-current');
      b.classList.toggle('is-done', parseInt(b.dataset.goto, 10) < n);
      b.closest('li').hidden = !stepNumbers.includes(parseInt(b.dataset.goto, 10));
    });
    const last = n === stepNumbers[stepNumbers.length - 1];
    $$('[data-step-next]').forEach((b) => { b.hidden = last; });
    $$('[data-bar-submit]').forEach((b) => { b.hidden = !last; });
    $$('[data-step-prev]').forEach((b) => { b.disabled = n === stepNumbers[0]; });
    if (n === 4) loadEditor();
    if (n === 6) renderReview();
    if (focus) {
      const section = sections.find((s) => parseInt(s.dataset.step, 10) === n && s.querySelector('h2'));
      section?.focus({ preventScroll: true });
      if (window.innerWidth < 960) $('.cake-flow')?.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });
    }
    try { sessionStorage.setItem('cake-step', String(n)); } catch { /* storage may be blocked */ }
  };
  form.classList.add('is-stepped');
  $('.cake-stepper').hidden = false;
  $$('.step-nav, .cake-mobile-bar').forEach((el) => { el.hidden = false; });
  form.addEventListener('click', (e) => {
    const go = e.target.closest('[data-goto]');
    if (go) showStep(parseInt(go.dataset.goto, 10));
    if (e.target.closest('[data-step-next]')) showStep(stepNumbers[Math.min(stepNumbers.length - 1, stepNumbers.indexOf(current) + 1)]);
    if (e.target.closest('[data-step-prev]')) showStep(stepNumbers[Math.max(0, stepNumbers.indexOf(current) - 1)]);
  });

  // ---------- small pictures for shapes, patterns and borders ----------
  $$('[data-shape-icon]').forEach((svg) => {
    svg.innerHTML = `<path d="${outlinePath(svg.dataset.shapeIcon, 30, 20, 20)}" fill="currentColor" opacity=".18" stroke="currentColor" stroke-width="1.5"/>`;
  });
  const drawStyleIcons = () => $$('[data-style-icon]').forEach((c) => {
    const [kind, style] = c.dataset.styleIcon.split(':');
    const ctx = c.getContext('2d');
    ctx.clearRect(0, 0, c.width, c.height);
    ctx.fillStyle = value('base_colour') || '#fffaf3';
    ctx.fillRect(0, 0, c.width, c.height);
    const accent = value('accent_colour');
    const colour = accent && accent !== '#fffaf3' && accent !== '#ffffff' ? accent : '#0f7c80';
    if (kind === 'pattern') {
      const tile = document.createElement('canvas');
      tile.width = tile.height = 32;
      drawPatternTile(tile.getContext('2d'), style, colour, 32);
      for (let x = 0; x < c.width; x += 32) ctx.drawImage(tile, x, 0);
    } else {
      ctx.fillStyle = colour;
      ctx.strokeStyle = colour;
      const n = style === 'dots' ? 8 : 6;
      if (style === 'rope' || style === 'ruffle') {
        ctx.lineWidth = 6;
        ctx.beginPath();
        for (let x = 0; x <= c.width; x += 2) ctx.lineTo(x, 16 + Math.sin(x / (style === 'rope' ? 3 : 5)) * (style === 'rope' ? 2 : 4));
        ctx.stroke();
      } else {
        for (let i = 0; i < n; i++) {
          ctx.beginPath();
          const x = 6 + (i * (c.width - 12)) / (n - 1);
          if (style === 'shell') ctx.ellipse(x, 16, 7, 5, 0.6, 0, Math.PI * 2);
          else ctx.arc(x, 16, style === 'dots' ? 3 : 5, 0, Math.PI * 2);
          ctx.fill();
        }
      }
    }
  });

  // ---------- colours ----------
  const paletteColours = () => findItem('palette', value('palette'))?.payload.colours || [];
  const renderSwatches = () => {
    $$('[data-swatches-for]').forEach((row) => {
      const input = form.elements[row.dataset.swatchesFor];
      const colours = [...new Set([...(data.freeColours || []).slice(0, 1), ...paletteColours()])];
      row.innerHTML = colours.map((c) => `<button type="button" class="swatch${input.value.toLowerCase() === c ? ' is-on' : ''}" style="background:${esc(c)}" data-swatch="${esc(c)}" aria-label="${esc(c)}" aria-pressed="${input.value.toLowerCase() === c}"></button>`).join('');
    });
  };
  form.addEventListener('click', (e) => {
    const sw = e.target.closest('[data-swatch]');
    if (!sw) return;
    const input = form.elements[sw.closest('[data-swatches-for]').dataset.swatchesFor];
    input.value = sw.dataset.swatch;
    input.dispatchEvent(new Event('change', { bubbles: true }));
  });

  // ---------- preview ----------
  const viewer = $('#cake-viewer');
  const sizeLine = $('#viewer-size');
  const tools = $('.viewer-tools');
  // 3D is the default. The 2D drawing is used only with reduced motion, or when this device has no usable WebGL
  // (none at all, or only slow software rendering), unless ?preview=3d / ?preview=2d asks for one explicitly.
  const forcedPreview = new URLSearchParams(location.search).get('preview');
  let mode = null;

  const describe = () => {
    const s = spec();
    const across = baseDiameterCm(s.grams, s.tiers, geometry);
    const slices = Math.max(4, Math.round(s.grams / 100));
    const text = `${(i18n.across || '').replace(':cm', across)} · ${(i18n.tierCount || '').replace(':n', s.tiers)} · ${(i18n.serves || '').replace(':n', slices)}`;
    if (sizeLine) sizeLine.textContent = text;
    const hint = $('#size-hint');
    if (hint) hint.textContent = text;
  };

  let previewReady = Promise.resolve();
  const startPreview = (wanted) => { previewReady = loadPreview(wanted).catch(() => null); return previewReady; };
  const loadPreview = async (wanted) => {
    preview?.dispose?.();
    preview = null;
    tools.hidden = false;
    if (wanted === '3d') {
      try {
        const { createPreview3d, webglAvailable } = await import(form.dataset.preview3d);
        if (!webglAvailable(forcedPreview !== '3d' && !chose3dByHand)) throw new Error('no usable webgl');
        preview = await createPreview3d(viewer, form.dataset.three, { geometry, reducedMotion, label: i18n.previewLabel });
        mode = '3d';
      } catch {
        viewer.insertAdjacentHTML('afterend', `<p class="small muted" role="status">${esc(i18n.previewFailed)}</p>`);
        wanted = '2d';
      }
    }
    if (wanted === '2d') {
      const { createPreview2d } = await import(form.dataset.preview2d);
      preview = createPreview2d(viewer, { geometry, label: i18n.previewLabel, topLabel: i18n.topView });
      mode = '2d';
    }
    $$('[data-view], [data-zoom]').forEach((b) => { b.hidden = mode !== '3d'; });
    const toggle = $('[data-mode-toggle]');
    if (toggle) toggle.textContent = mode === '3d' ? i18n.view2d : i18n.view3d;
    preview.update(spec());
    if (topCanvas) preview.setTop(topCanvas);
  };
  let chose3dByHand = false;
  $('[data-mode-toggle]')?.addEventListener('click', () => { chose3dByHand = mode !== '3d'; startPreview(mode === '3d' ? '2d' : '3d'); });
  tools?.addEventListener('click', (e) => {
    const view = e.target.closest('[data-view]');
    if (view) { preview?.setView(view.dataset.view); $$('[data-view]').forEach((b) => b.setAttribute('aria-pressed', String(b === view))); }
    const zoom = e.target.closest('[data-zoom]');
    if (zoom) preview?.zoom(parseInt(zoom.dataset.zoom, 10));
  });

  // ---------- editor (lazy) ----------
  let editorLoading = null;
  const loadEditor = () => {
    if (editorLoading || !$('#ed-stage')) return editorLoading;
    $('#editor-root')?.setAttribute('aria-busy', 'true');
    editorLoading = import(form.dataset.editor).then(({ createEditor }) => createEditor({
      root: $('#editor-root'), konvaUrl: form.dataset.konva, library, i18n, geometry, csrf, layers, uploads: data.uploads || [], readOnly,
      uploadUrl: data.urls?.upload, maxImages: data.maxImages, maxLayers: data.maxLayers, formatTaka, getSpec: spec,
      onTemplate: (code) => { $('#template-input').value = code; },
      onUpload: (upload) => { (data.uploads ||= []).push(upload); },
      onChange: (next, canvas, liveOnly) => {
        layers = next;
        topCanvas = canvas;
        if (canvasInput) canvasInput.value = JSON.stringify({ layers });
        if (!$('#ed-photo-rights')?.checked && layers.some((l) => l.type === 'image')) { const r = $('#ed-photo-rights'); if (r) r.checked = true; }
        preview?.setTop(canvas);
        if (!liveOnly) schedule(250);
        syncRights();
      },
    })).then((ed) => { editor = ed; return ed; }).catch(() => null).finally(() => $('#editor-root')?.removeAttribute('aria-busy'));
    return editorLoading;
  };
  const syncRights = () => {
    const box = $('#rights-final');
    if (box) box.hidden = !layers.some((l) => l.type === 'image');
  };

  // ---------- server price ----------
  let timer = 0;
  let controller = null;
  const fillSlots = () => {
    if (!dateSelect || !slotSelect) return;
    const option = dateSelect.selectedOptions[0];
    const slots = option && option.dataset.slots ? JSON.parse(option.dataset.slots) : [];
    const keep = slotSelect.value || slotSelect.dataset.selected || '';
    slotSelect.innerHTML = slots.length
      ? slots.map((slot) => `<option value="${esc(slot)}"${slot === keep ? ' selected' : ''}>${esc(slot)}</option>`).join('')
      : `<option value="">${esc(slotSelect.dataset.emptyLabel || '—')}</option>`;
  };
  const fillDates = (dates) => {
    if (!dateSelect) return;
    const keep = dateSelect.value || dateSelect.dataset.selected || '';
    const first = dateSelect.options[0] ? dateSelect.options[0].outerHTML : '';
    const label = (date) => new Date(`${date}T00:00:00`).toLocaleDateString(data.locale === 'bn' ? 'bn-BD' : 'en-GB', { weekday: 'short', day: 'numeric', month: 'short' });
    dateSelect.innerHTML = first + dates.map((day) => `<option value="${esc(day.date)}" data-slots='${esc(JSON.stringify(day.slots))}'${day.date === keep ? ' selected' : ''}>${esc(label(day.date))}</option>`).join('');
    fillSlots();
  };
  const render = (result) => {
    lastPrice = result;
    $('#cake-breakdown').innerHTML = result.breakdown.map((part) => `<li><span>${esc(part.label)}</span><span class="tnum">${esc(part.amount)}</span></li>`).join('');
    for (const el of [$('#cake-total'), $('#cake-total-mobile')]) {
      if (!el) continue;
      el.textContent = result.total;
      el.classList.remove('tick');
      void el.offsetWidth;
      el.classList.add('tick');
    }
    const error = $('#cake-error');
    error.hidden = result.ok || !result.error;
    error.textContent = result.error || '';
    const note = $('#test-note');
    if (note) note.hidden = !result.test;
    if (!readOnly) fillDates(result.dates || []);
    if (current === 6) renderReview();
  };
  const refresh = async () => {
    controller?.abort();
    controller = new AbortController();
    form.setAttribute('aria-busy', 'true');
    try {
      const body = new FormData(form);
      body.delete('snapshot');
      body.delete('top_image');
      const response = await fetch(form.dataset.priceUrl, { method: 'POST', body, signal: controller.signal, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
      if (response.ok) render(await response.json());
      else priceFailed();
    } catch (e) {
      if (e.name !== 'AbortError') priceFailed();
    } finally {
      form.removeAttribute('aria-busy');
    }
  };
  const priceFailed = () => {
    const error = $('#cake-error');
    error.textContent = i18n.priceFailed;
    error.hidden = false;
  };
  const schedule = (ms = 150) => { clearTimeout(timer); timer = setTimeout(refresh, ms); };

  const styleChanged = () => {
    describe();
    preview?.update(spec());
    editor?.setSpec(spec());
    drawStyleIcons();
    const dripField = $('#drip-colour-field');
    if (dripField) dripField.hidden = !value('drip');
    $$('[data-shapes]').forEach((label) => {
      const allowed = label.dataset.shapes.split(' ').includes(value('shape'));
      label.hidden = !allowed;
      const input = label.querySelector('input');
      if (!allowed && input.checked) form.querySelector(`input[name="${input.name}"][value=""]`).checked = true;
    });
  };

  form.addEventListener('change', (event) => {
    const t = event.target;
    if (t.closest('#editor-root') && t.id !== 'ed-photo-rights') return; // editor handles its own controls
    if (t.dataset?.reload) { window.location.assign(t.dataset.reload); return; }
    if (t.name === 'drip' && t.dataset.colour) form.elements.drip_colour.value = t.dataset.colour;
    if (t.name === 'palette') {
      const colours = paletteColours();
      if (colours.length) {
        if (!colours.includes(value('base_colour').toLowerCase())) form.elements.base_colour.value = colours[0];
        if (!colours.includes(value('accent_colour').toLowerCase())) form.elements.accent_colour.value = colours[Math.min(3, colours.length - 1)];
      }
    }
    if (['palette', 'base_colour', 'accent_colour'].includes(t.name)) renderSwatches();
    if (t === slotSelect) { schedule(0); return; }
    if (t === dateSelect) fillSlots();
    styleChanged();
    schedule();
  });
  form.addEventListener('input', (event) => {
    const t = event.target;
    if (['base_colour', 'accent_colour', 'drip_colour'].includes(t.name)) { preview?.update(spec()); editor?.setSpec(spec()); schedule(400); }
    if (t.name === 'note' || t.name === 'name') schedule(600);
  });

  // ---------- review ----------
  const checkedLabel = (name) => {
    const input = form.querySelector(`input[name="${name}"]:checked`);
    return input ? input.closest('label')?.querySelector('span')?.textContent.trim() : '';
  };
  const selectLabel = (name) => form.elements[name]?.selectedOptions?.[0]?.textContent.trim() || '';
  /** "Filling: Chocolate ganache" — the field's own label, so two "Standard" choices are not ambiguous. */
  const labelled = (name) => (form.elements[name] ? `${document.querySelector(`label[for="${name}"]`)?.textContent.trim() || name}: ${selectLabel(name)}` : '');
  const renderReview = () => {
    const sheet = $('#spec-sheet');
    if (!sheet) return;
    const s = spec();
    const photos = layers.filter((l) => l.type === 'image').length;
    const texts = layers.filter((l) => l.type === 'text').map((l) => `“${l.text}”`);
    const extras = $$('input[name="extras[]"]:checked').map((i) => i.closest('label').textContent.trim().split('\n')[0]);
    const rows = [
      [document.getElementById('s-shape')?.textContent.replace(/^\s*\d\s*/, ''), `${checkedLabel('shape')} · ${selectLabel('grams')} · ${selectLabel('tiers')} · ${(i18n.across || '').replace(':cm', baseDiameterCm(s.grams, s.tiers, geometry))}`],
      [document.querySelector('#s-flavour')?.textContent.replace(/^\s*\d\s*/, ''), [checkedLabel('flavour'), labelled('filling'), labelled('cream')].filter(Boolean).join(' · ')],
      [i18n.colour, `<i class="dot" style="background:${esc(s.base)}"></i> ${esc(s.base)} · <i class="dot" style="background:${esc(s.accent)}"></i> ${esc(s.accent)}`, true],
      [document.querySelector('#s-decorate')?.textContent.replace(/^\s*\d\s*/, ''), [checkedLabel('drip'), checkedLabel('pattern'), checkedLabel('border_top'), checkedLabel('border_bottom'), ...extras].filter(Boolean).join(' · ') || i18n.none],
      [document.querySelector('#s-top')?.textContent.replace(/^\s*\d\s*/, ''), [texts.length ? `${i18n.textItems}: ${texts.join(', ')}` : '', photos ? `${i18n.photos}: ${photos}` : '',
        layers.filter((l) => l.type === 'item').length ? `${i18n.libraryItems}: ${layers.filter((l) => l.type === 'item').length}` : ''].filter(Boolean).join(' · ') || i18n.none],
      [document.querySelector('#s-date')?.textContent.replace(/^\s*\d\s*/, ''), dateSelect?.value ? `${selectLabel('date')} · ${slotSelect.value}` : i18n.dateMissing],
    ].filter((r) => r[0]);
    sheet.innerHTML = rows.map(([k, v, html]) => `<div><dt>${esc(k)}</dt><dd>${html ? v : esc(v)}</dd></div>`).join('')
      + (lastPrice ? `<div class="spec-total"><dt>${esc($('#sum-title')?.textContent)}</dt><dd class="tnum">${esc(lastPrice.total)}</dd></div>` : '');
    syncRights();
  };

  // ---------- snapshot, download, save, share ----------
  const snapshot = async () => { await previewReady; return preview ? await preview.snapshot() : ''; };
  const download = async () => {
    const url = await snapshot();
    if (!url) return;
    const a = document.createElement('a');
    a.href = url;
    a.download = 'cake-design.png';
    document.body.append(a);
    a.click();
    a.remove();
  };
  form.addEventListener('click', (e) => { if (e.target.closest('[data-download]')) download(); });

  form.addEventListener('submit', async (e) => {
    if (form.dataset.ready === '1' || readOnly) return;
    e.preventDefault();
    const submitter = e.submitter;
    if (editorLoading) await editorLoading;
    try {
      $('#snapshot-input').value = await snapshot();
      $('#top-input').value = editor ? editor.topImage() : '';
    } catch { /* pictures help staff but never block the order */ }
    if (canvasInput) canvasInput.value = JSON.stringify({ layers });
    form.dataset.ready = '1';
    form.requestSubmit(submitter);
  });

  $('[data-share]')?.addEventListener('click', async (e) => {
    const out = $('#share-result');
    const response = await fetch(e.currentTarget.dataset.share, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' } });
    if (!response.ok) return;
    const { url } = await response.json();
    let copied = false;
    try { await navigator.clipboard.writeText(url); copied = true; } catch { /* clipboard may be blocked */ }
    out.hidden = false;
    out.innerHTML = `${esc(copied ? i18n.shareCopied : i18n.shareReady)} <a href="${esc(url)}">${esc(url)}</a>`;
  });

  // ---------- start ----------
  if (slotSelect) slotSelect.dataset.emptyLabel = slotSelect.options[0]?.textContent || '—';
  fillSlots();
  renderSwatches();
  styleChanged();
  syncRights();
  let startStep = stepNumbers[0];
  try { const saved = parseInt(sessionStorage.getItem('cake-step'), 10); if (stepNumbers.includes(saved) && new URLSearchParams(location.search).has('design')) startStep = saved; } catch { /* ignore */ }
  if (document.querySelector('.alert-error')) startStep = stepNumbers[stepNumbers.length - 1];
  showStep(startStep, false);
  startPreview(forcedPreview === '3d' || forcedPreview === '2d' ? forcedPreview : reducedMotion ? '2d' : '3d');
  // The top design is drawn by the editor; load it now when the design already has a top (also for shared views).
  if (layers.length) loadEditor();
  if (layers.length && !readOnly) schedule(0);
  if (readOnly) {
    // A shared design is for looking only: choices are locked; "Start my own copy" makes an editable copy.
    form.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach((el) => { el.disabled = true; });
  }
}
