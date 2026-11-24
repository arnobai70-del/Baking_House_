/**
 * Top-design editor (Konva, loaded only when needed). The design is a list of layers on a square that stands
 * for the cake top; x / y / w are fractions of the top's width, exactly as the server stores them.
 *
 * Text (English / বাংলা, library fonts, colour, outline, curve), the customer's own photos (crop, rotate,
 * resize, circle mask, brightness), library toppers / decorations / stickers and templates; layers, snap guides,
 * undo / redo, keyboard control, and a print-quality check in plain arithmetic (pixels per printed inch).
 */
import { outline, printQuality, topDiameterCm } from './geometry.js';

const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const uid = () => Math.random().toString(36).slice(2, 10);
const clamp = (v, min, max) => Math.min(max, Math.max(min, v));
const SNAP = 7;

export async function createEditor(opts) {
  const { root, konvaUrl, library, i18n, geometry, csrf, uploadUrl, maxImages, maxLayers, onChange, formatTaka } = opts;
  if (!window.Konva) await import(konvaUrl);
  const Konva = window.Konva;
  const $ = (s) => root.querySelector(s);
  const host = $('#ed-stage');
  const live = $('#ed-live');
  const uploads = new Map((opts.uploads || []).map((u) => [u.uuid, u]));
  const images = new Map();
  const fonts = new Map((library.font || []).map((f) => [f.code, f]));
  const items = new Map();
  for (const kind of ['topper', 'decoration', 'sticker_pack', 'template']) for (const it of library[kind] || []) items.set(`${kind}.${it.code}`, it);

  let spec = opts.getSpec();
  let layers = (opts.layers || []).map((l) => ({ ...l, id: l.id || uid() }));
  let selected = null;
  let history = [JSON.stringify(layers)];
  let cursor = 0;

  let S = 400;
  const stage = new Konva.Stage({ container: host, width: S, height: S });
  const bg = new Konva.Layer({ listening: false });
  const content = new Konva.Layer();
  const ui = new Konva.Layer();
  stage.add(bg, content, ui);
  const transformer = new Konva.Transformer({
    keepRatio: true, rotateEnabled: true, enabledAnchors: ['top-left', 'top-right', 'bottom-left', 'bottom-right'],
    anchorSize: 12, anchorCornerRadius: 6, borderStroke: '#0f7c80', anchorStroke: '#0f7c80', rotateAnchorOffset: 26,
    boundBoxFunc: (oldBox, box) => (box.width < 16 ? oldBox : box),
  });
  ui.add(transformer);
  if (opts.readOnly) { content.listening(false); transformer.hide(); }
  const guides = new Konva.Group();
  ui.add(guides);
  host.tabIndex = 0;
  host.setAttribute('role', 'application');
  host.setAttribute('aria-label', i18n.editorLabel);

  const announce = (text) => { if (live) live.textContent = text; };
  const outlinePoints = () => outline(spec.shape || 'round', 160);
  const colourOf = (fill) => (fill === 'accent' ? spec.accent : fill === 'base' ? spec.base : fill);
  const topCm = () => topDiameterCm(spec.grams, spec.tiers, geometry);
  const outlineFunc = (ctx) => {
    outlinePoints().forEach(([x, y], i) => (i ? ctx.lineTo : ctx.moveTo).call(ctx, (x + 0.5) * S, (0.5 - y) * S));
    ctx.closePath();
  };
  content.clipFunc(outlineFunc);

  const drawBackground = () => {
    bg.destroyChildren();
    bg.add(new Konva.Rect({ width: S, height: S, fill: '#eef2f4' }));
    bg.add(new Konva.Shape({ sceneFunc: (ctx, shape) => { ctx.beginPath(); outlineFunc(ctx); ctx.fillStrokeShape(shape); }, fill: spec.dripColour || spec.base, stroke: 'rgba(16,59,86,.35)', strokeWidth: 1.5 }));
    bg.add(new Konva.Shape({
      sceneFunc: (ctx, shape) => { ctx.save(); ctx.translate(S * 0.05, S * 0.05); ctx.scale(0.9, 0.9); ctx.beginPath(); outlineFunc(ctx); ctx.restore(); ctx.strokeShape(shape); },
      stroke: 'rgba(15,124,128,.55)', strokeWidth: 1, dash: [6, 6], name: 'safe-guide',
    }));
    bg.draw();
  };

  const loadImage = (uuid) => {
    if (images.has(uuid)) return images.get(uuid);
    const upload = uploads.get(uuid);
    const img = new Image();
    const ready = new Promise((resolve) => { img.onload = () => resolve(img); img.onerror = () => resolve(null); });
    img.src = upload ? upload.url : '';
    images.set(uuid, { img, ready });
    ready.then(() => { render(); emit(); }); // redraw and update the 3D texture once the photo has loaded
    return images.get(uuid);
  };

  const ensureFonts = async () => {
    const used = new Set(layers.filter((l) => l.type === 'text').map((l) => l.font));
    await Promise.all([...used].map((code) => {
      const f = fonts.get(code);
      return f && document.fonts ? document.fonts.load(`${f.payload.weight || 400} 40px ${f.payload.stack}`, 'Aa অআ').catch(() => null) : null;
    }));
  };

  /** Builds the Konva node for a layer: an outer group (position / rotation) around centred content. */
  const nodeFor = (layer) => {
    const group = new Konva.Group({ id: layer.id, x: layer.x * S, y: layer.y * S, rotation: layer.rotation || 0, opacity: layer.opacity ?? 1, draggable: true });
    if (layer.type === 'text') {
      const f = fonts.get(layer.font);
      const style = { fontFamily: f ? f.payload.stack : 'Noto Sans, Noto Sans Bengali, sans-serif', fontStyle: String(f ? f.payload.weight || 400 : 700), fontSize: layer.size * S,
        fill: layer.fill, stroke: layer.stroke || undefined, strokeWidth: layer.stroke ? layer.stroke_width * S : 0, fillAfterStrokeEnabled: true, lineJoin: 'round' };
      const probe = new Konva.Text({ text: layer.text, ...style });
      const width = probe.width();
      if (Math.abs(layer.curve || 0) < 0.03) {
        probe.offset({ x: width / 2, y: probe.height() / 2 });
        group.add(probe);
      } else {
        probe.destroy();
        const theta = Math.abs(layer.curve) * Math.PI;
        const r = Math.max(width / theta, layer.size * S);
        const half = Math.min(theta, Math.PI * 1.9) / 2;
        const up = layer.curve > 0;
        const cy = up ? r : -r;
        const x1 = -r * Math.sin(half);
        const y1 = cy - (up ? r : -r) * Math.cos(half);
        const data = `M${x1} ${y1} A${r} ${r} 0 ${half > Math.PI / 2 ? 1 : 0} ${up ? 1 : 0} ${-x1} ${y1}`;
        group.add(new Konva.TextPath({ data, text: layer.text, align: 'center', ...style, y: up ? layer.size * S * 0.3 : -layer.size * S * 0.3 }));
      }
    } else if (layer.type === 'item') {
      const it = items.get(`${layer.kind}.${layer.item}`);
      const shape = layer.kind === 'sticker_pack' ? it?.payload.stickers?.[layer.sticker || 0] : it?.payload;
      if (!shape) return null;
      const [, , vbW, vbH] = shape.viewBox.split(' ').map(Number);
      const scale = (layer.w * S) / vbW;
      const inner = new Konva.Group({ scaleX: scale, scaleY: scale, offsetX: vbW / 2, offsetY: vbH / 2 });
      for (const p of shape.paths) inner.add(new Konva.Path({ data: p.d, fill: colourOf(p.fill) }));
      group.add(inner);
    } else if (layer.type === 'image') {
      const entry = loadImage(layer.upload);
      const img = entry.img;
      if (!img.complete || !img.naturalWidth) return group; // drawn once loaded
      const crop = { x: layer.crop.x * img.naturalWidth, y: layer.crop.y * img.naturalHeight, width: layer.crop.w * img.naturalWidth, height: layer.crop.h * img.naturalHeight };
      const w = layer.w * S;
      const h = w * (crop.height / crop.width);
      const inner = new Konva.Group(layer.mask === 'circle' ? { clipFunc: (ctx) => { ctx.arc(0, 0, Math.min(w, h) / 2, 0, Math.PI * 2); } } : {});
      const node = new Konva.Image({ image: img, crop, width: w, height: h, offsetX: w / 2, offsetY: h / 2 });
      if (layer.brightness) {
        node.cache({ pixelRatio: Math.min(2, Math.max(1, crop.width / w)) });
        node.filters([Konva.Filters.Brighten]);
        node.brightness(layer.brightness);
      }
      inner.add(node);
      group.add(inner);
    }
    group.on('mousedown touchstart', () => select(layer.id));
    group.on('dragmove', () => snap(group));
    group.on('dragend', () => { guides.destroyChildren(); update(layer.id, { x: group.x() / S, y: group.y() / S }, true); });
    group.on('transformend', () => {
      const factor = group.scaleX();
      group.scale({ x: 1, y: 1 });
      const patch = { rotation: Math.round(group.rotation() * 10) / 10, x: group.x() / S, y: group.y() / S };
      if (layer.type === 'text') patch.size = clamp(layer.size * factor, 0.02, 0.3);
      else patch.w = clamp(layer.w * factor, 0.03, 1.5);
      update(layer.id, patch, true);
    });
    group.on('dragmove transform', () => emit(true));
    return group;
  };

  /** Snap a dragged layer's centre to the middle of the top and to other layers' centres. */
  const snap = (group) => {
    guides.destroyChildren();
    const lines = [S / 2, ...content.getChildren((n) => n !== group).map((n) => n.x())];
    const rows = [S / 2, ...content.getChildren((n) => n !== group).map((n) => n.y())];
    const x = lines.find((v) => Math.abs(v - group.x()) < SNAP);
    const y = rows.find((v) => Math.abs(v - group.y()) < SNAP);
    if (x !== undefined) { group.x(x); guides.add(new Konva.Line({ points: [x, 0, x, S], stroke: '#e8833a', strokeWidth: 1, dash: [4, 4] })); }
    if (y !== undefined) { group.y(y); guides.add(new Konva.Line({ points: [0, y, S, y], stroke: '#e8833a', strokeWidth: 1, dash: [4, 4] })); }
  };

  let emitFrame = 0;
  /** Sends the layers and a picture of the design to the designer (3D texture, price, hidden input). */
  const emit = (liveOnly = false) => {
    cancelAnimationFrame(emitFrame);
    emitFrame = requestAnimationFrame(() => {
      const canvas = content.toCanvas({ pixelRatio: (liveOnly ? 512 : 1024) / S });
      onChange(layers, canvas, liveOnly);
    });
  };

  const render = () => {
    content.destroyChildren();
    for (const layer of layers) {
      const node = nodeFor(layer);
      if (node) content.add(node);
    }
    const node = selected && content.findOne(`#${selected}`);
    transformer.nodes(node ? [node] : []);
    content.draw();
    ui.draw();
    renderLayerList();
    renderProps();
    renderQuality();
  };

  const commit = () => {
    const snapshot = JSON.stringify(layers);
    if (snapshot === history[cursor]) return;
    history = history.slice(0, cursor + 1);
    history.push(snapshot);
    if (history.length > 60) history.shift();
    cursor = history.length - 1;
    syncHistoryButtons();
    emit();
  };

  const update = (id, patch, save = false) => {
    layers = layers.map((l) => (l.id === id ? { ...l, ...patch } : l));
    render();
    if (save) commit(); else emit(true);
  };

  const select = (id) => {
    selected = id;
    render();
    const layer = layers.find((l) => l.id === id);
    if (layer) announce(`${i18n.selected}: ${labelOf(layer)}`);
  };

  const labelOf = (layer) => {
    if (layer.type === 'text') return `${i18n.text}: ${layer.text}`;
    if (layer.type === 'image') return `${i18n.photo} ${layers.filter((l) => l.type === 'image').indexOf(layer) + 1}`;
    const it = items.get(`${layer.kind}.${layer.item}`);
    const sticker = layer.kind === 'sticker_pack' ? it?.payload.stickers?.[layer.sticker || 0]?.name : '';
    return `${it?.name || layer.item}${sticker ? ` · ${sticker}` : ''}`;
  };

  const add = (layer) => {
    if (layers.length >= maxLayers) { announce(i18n.tooManyLayers); return; }
    const full = { id: uid(), x: 0.5, y: 0.5, w: 0.3, rotation: 0, opacity: 1, ...layer };
    layers = [...layers, full];
    selected = full.id;
    render();
    commit();
    announce(`${i18n.added}: ${labelOf(full)}`);
  };

  const move = (id, delta) => {
    const i = layers.findIndex((l) => l.id === id);
    const j = i + delta;
    if (i < 0 || j < 0 || j >= layers.length) return;
    const copy = [...layers];
    [copy[i], copy[j]] = [copy[j], copy[i]];
    layers = copy;
    render();
    commit();
  };

  const remove = (id) => {
    const layer = layers.find((l) => l.id === id);
    layers = layers.filter((l) => l.id !== id);
    if (selected === id) selected = null;
    render();
    commit();
    if (layer) announce(`${i18n.removed}: ${labelOf(layer)}`);
  };

  const restore = (index) => {
    cursor = index;
    layers = JSON.parse(history[cursor]);
    if (!layers.some((l) => l.id === selected)) selected = null;
    render();
    syncHistoryButtons();
    emit();
  };
  const syncHistoryButtons = () => {
    const undo = root.querySelector('[data-ed="undo"]');
    const redo = root.querySelector('[data-ed="redo"]');
    if (undo) undo.disabled = cursor === 0;
    if (redo) redo.disabled = cursor >= history.length - 1;
  };

  const renderLayerList = () => {
    const list = $('#ed-layers');
    if (!list) return;
    list.innerHTML = layers.length ? [...layers].reverse().map((l) => `
      <li class="ed-layer${l.id === selected ? ' is-selected' : ''}">
        <button type="button" class="ed-layer-name" data-layer="${l.id}" aria-pressed="${l.id === selected}">${esc(labelOf(l))}</button>
        <button type="button" class="icon-btn" data-layer-up="${l.id}" aria-label="${esc(i18n.moveUp)}">▲</button>
        <button type="button" class="icon-btn" data-layer-down="${l.id}" aria-label="${esc(i18n.moveDown)}">▼</button>
        <button type="button" class="icon-btn" data-layer-copy="${l.id}" aria-label="${esc(i18n.duplicate)}">⧉</button>
        <button type="button" class="icon-btn" data-layer-del="${l.id}" aria-label="${esc(i18n.delete)}">✕</button>
      </li>`).join('') : `<li class="muted small">${esc(i18n.noLayers)}</li>`;
  };

  const quality = (layer) => {
    const up = uploads.get(layer.upload);
    if (!up) return null;
    return printQuality(up.width * layer.crop.w, layer.w * topCm(), geometry);
  };
  const qualityText = (q) => (q ? `${i18n.quality[q.level]} (${q.dpi} dpi)` : '');

  const renderQuality = () => {
    const box = $('#ed-quality');
    if (!box) return;
    const photos = layers.filter((l) => l.type === 'image');
    box.hidden = !photos.length;
    box.innerHTML = photos.map((l, i) => {
      const q = quality(l);
      return q ? `<li class="q-${q.level}"><strong>${esc(i18n.photo)} ${i + 1}</strong> · ${esc(qualityText(q))} · ≈ ${(l.w * topCm()).toFixed(1)} cm</li>` : '';
    }).join('');
  };

  const field = (label, html) => `<div class="field"><label>${esc(label)}${html}</label></div>`;
  const renderProps = () => {
    const panel = $('#ed-props');
    if (!panel) return;
    const layer = layers.find((l) => l.id === selected);
    if (!layer) { panel.innerHTML = `<p class="muted small">${esc(i18n.selectHint)}</p>`; return; }
    const common = field(i18n.rotate, `<input type="range" min="-180" max="180" step="1" data-prop="rotation" value="${layer.rotation || 0}">`);
    let html = `<h4>${esc(labelOf(layer))}</h4>`;
    if (layer.type === 'text') {
      html += field(i18n.textLabel, `<input class="input" maxlength="60" data-prop="text" value="${esc(layer.text)}" lang="${/[ঀ-৿]/.test(layer.text) ? 'bn' : 'en'}">`)
        + field(i18n.font, `<select class="input" data-prop="font">${[...fonts.values()].map((f) => `<option value="${esc(f.code)}"${f.code === layer.font ? ' selected' : ''}>${esc(f.name)}${f.price_paisa ? ` (+${esc(formatTaka(f.price_paisa))})` : ''}</option>`).join('')}</select>`)
        + `<div class="ed-row">${field(i18n.colour, `<input type="color" data-prop="fill" value="${esc(layer.fill)}">`)}${field(i18n.outline, `<input type="color" data-prop="stroke" value="${esc(layer.stroke || '#ffffff')}">`)}</div>`
        + field(i18n.outlineWidth, `<input type="range" min="0" max="0.02" step="0.001" data-prop="stroke_width" value="${layer.stroke ? layer.stroke_width : 0}">`)
        + field(i18n.size, `<input type="range" min="0.02" max="0.3" step="0.005" data-prop="size" value="${layer.size}">`)
        + field(i18n.curve, `<input type="range" min="-1" max="1" step="0.05" data-prop="curve" value="${layer.curve || 0}">`);
    } else if (layer.type === 'image') {
      const zoom = 1 / Math.max(layer.crop.w, layer.crop.h);
      html += field(i18n.size, `<input type="range" min="0.05" max="1.2" step="0.01" data-prop="w" value="${layer.w}">`)
        + field(i18n.cropZoom, `<input type="range" min="1" max="4" step="0.05" data-crop="zoom" value="${zoom.toFixed(2)}">`)
        + `<div class="ed-row">${field(i18n.cropX, `<input type="range" min="0" max="1" step="0.01" data-crop="x" value="${layer.crop.w < 1 ? (layer.crop.x / (1 - layer.crop.w)).toFixed(2) : 0.5}">`)}${field(i18n.cropY, `<input type="range" min="0" max="1" step="0.01" data-crop="y" value="${layer.crop.h < 1 ? (layer.crop.y / (1 - layer.crop.h)).toFixed(2) : 0.5}">`)}</div>`
        + field(i18n.brightness, `<input type="range" min="-0.5" max="0.5" step="0.02" data-prop="brightness" value="${layer.brightness || 0}">`)
        + `<label class="check"><input type="checkbox" data-prop="mask" ${layer.mask === 'circle' ? 'checked' : ''}> ${esc(i18n.circleMask)}</label>`
        + `<div class="row" style="gap:6px;margin:8px 0"><button type="button" class="btn btn-outline btn-sm" data-rotate="-90">⟲ 90°</button><button type="button" class="btn btn-outline btn-sm" data-rotate="90">⟳ 90°</button></div>`
        + `<p class="q-badge q-${quality(layer)?.level || 'fair'}" role="status">${esc(qualityText(quality(layer)))}</p>`;
    } else {
      html += field(i18n.size, `<input type="range" min="0.05" max="1" step="0.01" data-prop="w" value="${layer.w}">`);
    }
    panel.innerHTML = html + common;
  };

  // Property panel input → layer.
  const propValue = (input) => {
    const key = input.dataset.prop;
    if (key === 'mask') return input.checked ? 'circle' : 'none';
    if (['text', 'font', 'fill', 'stroke'].includes(key)) return input.value;
    return parseFloat(input.value);
  };
  const onProp = (input, save) => {
    const layer = layers.find((l) => l.id === selected);
    if (!layer) return;
    if (input.dataset.crop) {
      const zoom = parseFloat(root.querySelector('[data-crop="zoom"]').value);
      const px = parseFloat(root.querySelector('[data-crop="x"]').value);
      const py = parseFloat(root.querySelector('[data-crop="y"]').value);
      // Same fraction of width and height keeps the photo's proportions; x / y pan inside the photo.
      const cw = 1 / zoom;
      const ch = 1 / zoom;
      layers = layers.map((l) => (l.id === layer.id ? { ...l, crop: { x: (1 - cw) * px, y: (1 - ch) * py, w: cw, h: ch } } : l));
    } else {
      const patch = { [input.dataset.prop]: propValue(input) };
      if (input.dataset.prop === 'stroke_width' && patch.stroke_width > 0 && !layer.stroke) patch.stroke = '#ffffff';
      if (input.dataset.prop === 'stroke_width' && patch.stroke_width === 0) patch.stroke = '';
      if (input.dataset.prop === 'text' && !patch.text.trim()) return;
      layers = layers.map((l) => (l.id === layer.id ? { ...l, ...patch } : l));
    }
    // Re-render the stage only (keep focus in the panel while dragging a slider).
    content.destroyChildren();
    for (const l of layers) { const n = nodeFor(l); if (n) content.add(n); }
    transformer.nodes([content.findOne(`#${selected}`)].filter(Boolean));
    content.draw(); ui.draw();
    renderQuality();
    const badge = root.querySelector('#ed-props .q-badge');
    const current = layers.find((l) => l.id === selected);
    if (badge && current?.type === 'image') { const q = quality(current); badge.className = `q-badge q-${q?.level || 'fair'}`; badge.textContent = qualityText(q); }
    if (save) { renderLayerList(); commit(); } else emit(true);
  };
  root.addEventListener('input', (e) => { if (e.target.closest('#ed-props') && (e.target.dataset.prop || e.target.dataset.crop)) onProp(e.target, false); });
  root.addEventListener('change', (e) => {
    if (e.target.closest('#ed-props') && (e.target.dataset.prop || e.target.dataset.crop)) { if (e.target.dataset.prop === 'font') ensureFonts().then(render); onProp(e.target, true); }
  });

  root.addEventListener('click', (e) => {
    const t = e.target.closest('button');
    if (!t || !root.contains(t)) return;
    const d = t.dataset;
    if (d.layer) select(d.layer);
    else if (d.layerUp) move(d.layerUp, 1);
    else if (d.layerDown) move(d.layerDown, -1);
    else if (d.layerDel) remove(d.layerDel);
    else if (d.layerCopy) { const l = layers.find((x) => x.id === d.layerCopy); if (l && (l.type !== 'image' || layers.filter((x) => x.type === 'image').length < maxImages)) add({ ...structuredClone(l), id: uid(), x: clamp(l.x + 0.04, 0, 1), y: clamp(l.y + 0.04, 0, 1) }); }
    else if (d.rotate) { const l = layers.find((x) => x.id === selected); if (l) update(l.id, { rotation: (((l.rotation || 0) + parseFloat(d.rotate) + 540) % 360) - 180 }, true); }
    else if (d.ed === 'add-text') addText();
    else if (d.ed === 'undo' && cursor > 0) restore(cursor - 1);
    else if (d.ed === 'redo' && cursor < history.length - 1) restore(cursor + 1);
    else if (d.ed === 'delete' && selected) remove(selected);
    else if (d.ed === 'center' && selected) update(selected, { x: 0.5, y: 0.5 }, true);
    else if (d.lib) useLibrary(d.lib, d.sticker);
    else if (d.libKind !== undefined) { kindFilter = d.libKind; renderLibrary(); }
  });

  const addText = () => {
    const input = $('#ed-new-text');
    const text = (input?.value || '').trim() || i18n.sampleText;
    const font = [...fonts.values()].find((f) => (/[ঀ-৿]/.test(text) ? f.payload.scripts.includes('bn') : true));
    add({ type: 'text', text: text.slice(0, 60), font: font?.code || '', size: 0.08, fill: spec.accent && spec.accent !== spec.base ? spec.accent : '#103b56', stroke: '', stroke_width: 0, curve: 0, w: 0.5 });
    if (input) input.value = '';
    ensureFonts().then(render);
  };

  // Library: searchable by name (English or বাংলা) and tags; shows the price add-on.
  let kindFilter = '';
  const thumb = (shape) => {
    if (!shape) return '';
    return `<svg viewBox="${esc(shape.viewBox)}" aria-hidden="true" focusable="false">${shape.paths.map((p) => `<path d="${esc(p.d)}" fill="${esc(colourOf(p.fill))}"/>`).join('')}</svg>`;
  };
  const renderLibrary = () => {
    const box = $('#ed-library-results');
    if (!box) return;
    const q = ($('#ed-library-search')?.value || '').trim().toLowerCase();
    const kinds = kindFilter ? [kindFilter] : ['template', 'topper', 'decoration', 'sticker_pack'];
    const matches = kinds.flatMap((kind) => (library[kind] || []).map((it) => ({ ...it, kind })))
      .filter((it) => !it.shapes || it.shapes.includes(spec.shape))
      .filter((it) => !q || [it.name, it.name_en, it.name_bn, ...(it.tags || [])].some((s) => String(s || '').toLowerCase().includes(q)));
    root.querySelectorAll('[data-lib-kind]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.libKind === kindFilter)));
    box.innerHTML = matches.length ? matches.map((it) => {
      const price = it.price_paisa ? `+${formatTaka(it.price_paisa)}` : i18n.free;
      const pic = it.kind === 'template' ? `<span class="lib-tpl">Aa</span>` : thumb(it.kind === 'sticker_pack' ? it.payload.stickers[0] : it.payload);
      const stickers = it.kind === 'sticker_pack' ? `<span class="lib-stickers">${it.payload.stickers.map((s, i) => `<button type="button" class="lib-sticker" data-lib="${esc(`${it.kind}.${it.code}`)}" data-sticker="${i}" aria-label="${esc(`${it.name}: ${s.name}`)}">${thumb(s)}</button>`).join('')}</span>` : '';
      return `<li class="lib-item">${it.kind === 'sticker_pack' ? `<span class="lib-main">${pic}<span><strong>${esc(it.name)}</strong><small>${esc(price)}${it.test ? ' · TEST' : ''}</small></span></span>${stickers}`
        : `<button type="button" class="lib-main" data-lib="${esc(`${it.kind}.${it.code}`)}">${pic}<span><strong>${esc(it.name)}</strong><small>${esc(price)}${it.test ? ' · TEST' : ''}</small></span></button>`}</li>`;
    }).join('') : `<li class="muted small">${esc(i18n.noResults)}</li>`;
  };
  $('#ed-library-search')?.addEventListener('input', renderLibrary);

  const useLibrary = (key, sticker) => {
    const it = items.get(key);
    if (!it) return;
    if (it.kind === 'template') {
      if (layers.length && !window.confirm(i18n.replaceWithTemplate)) return;
      const photos = layers.filter((l) => l.type === 'image');
      layers = [...it.payload.layers.map((l) => ({ ...l, id: uid() })), ...photos];
      selected = null;
      opts.onTemplate?.(it.code);
      ensureFonts().then(render);
      render();
      commit();
      announce(`${i18n.added}: ${it.name}`);
      return;
    }
    add({ type: 'item', kind: it.kind, item: it.code, sticker: sticker ? parseInt(sticker, 10) : 0, w: it.kind === 'topper' ? 0.28 : 0.2 });
  };

  // Photo upload (the rights checkbox must be ticked first; the photo stays private).
  const photoInput = $('#ed-photo');
  photoInput?.addEventListener('change', async () => {
    const file = photoInput.files[0];
    const error = $('#ed-photo-error');
    if (error) error.hidden = true;
    if (!file) return;
    const rights = $('#ed-photo-rights');
    const fail = (message) => { if (error) { error.textContent = message; error.hidden = false; } announce(message); photoInput.value = ''; };
    if (rights && !rights.checked) { rights.focus(); fail(i18n.rightsFirst); return; }
    if (layers.filter((l) => l.type === 'image').length >= maxImages) { fail(i18n.tooManyPhotos); return; }
    const body = new FormData();
    body.append('photo', file);
    body.append('rights', '1');
    root.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch(uploadUrl, { method: 'POST', body, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' } });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) { fail(Object.values(data.errors || {}).flat()[0] || i18n.uploadFailed); return; }
      uploads.set(data.uuid, data);
      opts.onUpload?.(data);
      add({ type: 'image', upload: data.uuid, crop: { x: 0, y: 0, w: 1, h: 1 }, mask: 'none', brightness: 0, w: 0.6 });
    } catch {
      fail(i18n.uploadFailed);
    } finally {
      root.removeAttribute('aria-busy');
      photoInput.value = '';
    }
  });

  // Keyboard: arrows move (Shift = more), Delete removes, Ctrl+Z / Ctrl+Y undo / redo.
  host.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') { e.preventDefault(); if (cursor > 0) restore(cursor - 1); return; }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'y') { e.preventDefault(); if (cursor < history.length - 1) restore(cursor + 1); return; }
    const layer = layers.find((l) => l.id === selected);
    if (!layer) return;
    const step = e.shiftKey ? 0.05 : 0.01;
    const delta = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }[e.key];
    if (delta) { e.preventDefault(); update(layer.id, { x: clamp(layer.x + delta[0], 0, 1), y: clamp(layer.y + delta[1], 0, 1) }, true); }
    if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); remove(layer.id); }
  });
  stage.on('mousedown touchstart', (e) => { if (e.target === stage || e.target.getLayer() === bg) { selected = null; render(); } });

  const resize = () => {
    const width = Math.max(240, Math.min(560, host.clientWidth || 400));
    if (width === S) return;
    S = width;
    stage.size({ width: S, height: S });
    drawBackground();
    render();
    emit();
  };
  new ResizeObserver(resize).observe(host);

  drawBackground();
  await ensureFonts();
  render();
  renderLibrary();
  syncHistoryButtons();
  resize();
  emit();

  return {
    /** Shape / colours / size changed in other steps. */
    setSpec(next) { spec = next; drawBackground(); render(); renderLibrary(); emit(); },
    layers: () => layers,
    /** Top design on the cream colour (for the order's job sheet). */
    topImage() {
      const guide = bg.findOne('.safe-guide');
      ui.hide();
      guide?.hide();
      const url = stage.toDataURL({ pixelRatio: 1024 / S, mimeType: 'image/png' });
      guide?.show();
      ui.show();
      return url;
    },
  };
}
