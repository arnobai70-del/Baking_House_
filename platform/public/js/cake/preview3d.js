/**
 * Live 3D cake preview (Three.js, loaded only when needed). Everything is plain geometry, materials and light —
 * no photos and no AI:
 *  - each tier follows the shape outline with a softly rounded top edge, in a frosting material (gentle sheen,
 *    a fine spatula texture drawn in code) in the customer's cream colour;
 *  - side pattern, piped borders, drip and the hand-made top design wrapped onto the top tier;
 *  - a white ceramic cake stand with a gold board, warm soft studio lighting and soft shadows;
 *  - the camera always fits the whole cake (any shape, size, tier count, screen shape) with space around it.
 * Renders on demand only. Controls: drag to turn, wheel / pinch / buttons to zoom, top · front · 3D, arrow keys.
 */
import { baseDiameterCm, drawPatternTile, outline, perimeter, seeded, TIER_HEIGHT_CM } from './geometry.js';

const VIEWS = { '3d': 0.48, front: 0.08, top: 1.5 };
const EDGE_RADIUS = 0.9; // rounded top edge of each tier, cm

export async function createPreview3d(container, threeUrl, { geometry, reducedMotion, label }) {
  const THREE = await import(threeUrl);
  const small = Math.min(window.innerWidth, window.innerHeight) < 700;
  const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, preserveDrawingBuffer: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, small ? 1.5 : 2));
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.32;
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  const canvas = renderer.domElement;
  canvas.className = 'cake-3d-canvas';
  canvas.tabIndex = 0;
  canvas.setAttribute('role', 'img');
  canvas.setAttribute('aria-label', label);
  container.replaceChildren(canvas);

  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(30, 1, 1, 2000);
  scene.environment = studioEnvironment(THREE, renderer);

  // Warm, soft studio light: a big warm key with soft shadows, a gentle fill and a back rim light.
  scene.add(new THREE.HemisphereLight(0xfff4e6, 0xc9b39d, 0.4));
  const key = new THREE.DirectionalLight(0xffecd6, 2.6);
  key.castShadow = true;
  key.shadow.mapSize.set(small ? 1024 : 2048, small ? 1024 : 2048);
  key.shadow.bias = -0.0004;
  key.shadow.normalBias = 0.02;
  scene.add(key, key.target);
  const fill = new THREE.DirectionalLight(0xffe6d0, 0.55);
  scene.add(fill);
  const rim = new THREE.DirectionalLight(0xffffff, 0.9);
  scene.add(rim);

  // Shadow catcher: only the soft shadow shows, over the page's own background.
  const floor = new THREE.Mesh(new THREE.PlaneGeometry(400, 400), new THREE.ShadowMaterial({ opacity: 0.16 }));
  floor.rotation.x = -Math.PI / 2;
  floor.receiveShadow = true;
  scene.add(floor);

  const world = new THREE.Group(); // stand + cake: turned together
  scene.add(world);
  let parent = world; // group the helpers add to (the tier being built)
  const frosting = frostingTextures(THREE);

  const state = { yaw: -0.55, pitch: VIEWS['3d'], zoom: 1 };
  const fit = { center: new THREE.Vector3(0, 10, 0), radius: 30 };
  let spec = null;
  let topCanvas = null;
  let topTexture = null;
  let topDecal = null;
  let frame = 0;
  const disposables = [];

  /**
   * Camera distance that shows the whole cake + stand for the current view direction and screen shape, with about
   * 9 % space on every side: each corner of the bounding box is projected and the closest distance that keeps every
   * corner inside the frame is used.
   */
  const MARGIN = 0.82;
  let fitCache = { key: '', distance: 60 };
  let fitPoints = []; // points on the real outline of the stand and every tier (round cakes do not fill a box)
  const distanceFor = (pitch) => {
    const tanV = Math.tan(THREE.MathUtils.degToRad(camera.fov) / 2);
    const tanH = tanV * camera.aspect;
    const worldUp = new THREE.Vector3(0, 1, 0);
    let distance = 1;
    // Worst case over a full turn, so the framing stays steady while the customer rotates a heart or rectangle.
    for (let step = 0; step < 12; step++) {
      const yaw = (step / 12) * Math.PI * 2;
      const forward = new THREE.Vector3(Math.sin(yaw) * Math.cos(pitch), Math.sin(pitch), Math.cos(yaw) * Math.cos(pitch)).negate();
      const right = new THREE.Vector3().crossVectors(forward, worldUp).normalize();
      const up = new THREE.Vector3().crossVectors(right, forward).normalize();
      for (const c of fitPoints) {
        const v = c.clone().sub(fit.center);
        const depth = v.dot(forward); // positive = further from the camera than the centre
        distance = Math.max(distance, Math.abs(v.dot(right)) / (MARGIN * tanH) - depth, Math.abs(v.dot(up)) / (MARGIN * tanV) - depth);
      }
    }
    return distance;
  };

  const render = () => {
    frame = 0;
    const pitch = Math.min(1.53, Math.max(0.02, state.pitch));
    const fitKey = `${pitch.toFixed(3)}|${camera.aspect.toFixed(3)}|${fitPoints.length}`;
    if (fitKey !== fitCache.key) fitCache = { key: fitKey, distance: distanceFor(pitch) };
    const distance = fitCache.distance / state.zoom;
    camera.position.set(
      fit.center.x + Math.sin(state.yaw) * Math.cos(pitch) * distance,
      fit.center.y + Math.sin(pitch) * distance,
      fit.center.z + Math.cos(state.yaw) * Math.cos(pitch) * distance,
    );
    camera.near = Math.max(0.5, distance - fit.radius * 3);
    camera.far = distance + fit.radius * 4;
    camera.updateProjectionMatrix();
    camera.lookAt(fit.center);
    renderer.render(scene, camera);
  };
  const requestRender = () => { if (!frame) frame = requestAnimationFrame(render); };

  const resize = () => {
    const width = container.clientWidth || 320;
    const height = container.clientHeight || width;
    renderer.setSize(width, height, false);
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
    requestRender();
  };
  const observer = new ResizeObserver(resize);
  observer.observe(container);

  const track = (thing) => { disposables.push(thing); return thing; };
  const clearCake = () => {
    world.clear();
    disposables.splice(0).forEach((thing) => thing.dispose?.());
    topDecal = null;
  };

  /** Frosting: soft, slightly rough cream with a gentle sheen. Whites stay warm and creamy. */
  const creamMaterial = (colour, repeat = [1, 1]) => {
    const bump = frosting.bump.clone();
    bump.needsUpdate = true;
    bump.repeat.set(repeat[0], repeat[1]);
    track(bump);
    return track(new THREE.MeshPhysicalMaterial({
      color: creamColour(THREE, colour), roughness: 1, metalness: 0, // × roughnessMap (≈ 0.5) = soft satin frosting
      sheen: 0.55, sheenRoughness: 0.75, sheenColor: new THREE.Color(0xfff6ea),
      clearcoat: 0.12, clearcoatRoughness: 0.55, bumpMap: bump, bumpScale: 2.2, roughnessMap: bump, envMapIntensity: 0.65,
    }));
  };
  const pipingMaterial = (colour) => track(new THREE.MeshPhysicalMaterial({
    color: creamColour(THREE, colour), roughness: 0.45, sheen: 0.5, sheenRoughness: 0.6, sheenColor: new THREE.Color(0xffffff), clearcoat: 0.2, clearcoatRoughness: 0.4, envMapIntensity: 0.7,
  }));

  /** World outline of a tier and the outward normal at each point (counter-clockwise, world z = -y). */
  const worldOutline = (points, scale) => points.map((p, i) => {
    const n = points.length;
    const prev = points[(i - 1 + n) % n];
    const next = points[(i + 1) % n];
    let nx = next[1] - prev[1];
    let ny = -(next[0] - prev[0]);
    const len = Math.hypot(nx, ny) || 1;
    nx /= len; ny /= len;
    return { x: p[0] * scale, z: -p[1] * scale, nx, nz: -ny, angle: Math.atan2(next[0] - prev[0], -(next[1] - prev[1])) };
  });

  /**
   * Tier wall with a rounded top edge: the profile rises straight to (height - r) and then curves inwards to the top.
   * u runs around the cake (for the side pattern), v up the side.
   */
  const wallGeometry = (pts, height, r, offset = 0, from = 0) => {
    const profile = [];
    profile.push([offset, from]);
    profile.push([offset, height - r]);
    if (r > 0) {
      for (let k = 1; k <= 6; k++) {
        const a = (k / 6) * (Math.PI / 2);
        profile.push([offset - r + r * Math.cos(a), height - r + r * Math.sin(a), Math.cos(a), Math.sin(a)]);
      }
    }
    const per = pts.reduce((sum, p, i) => sum + Math.hypot(pts[(i + 1) % pts.length].x - p.x, pts[(i + 1) % pts.length].z - p.z), 0);
    const positions = [];
    const normals = [];
    const uvs = [];
    const index = [];
    let run = 0;
    const rows = profile.length;
    for (let i = 0; i <= pts.length; i++) {
      const p = pts[i % pts.length];
      if (i > 0) { const q = pts[i - 1]; run += Math.hypot(p.x - q.x, p.z - q.z); }
      for (const [off, y, cn = 1, sn = 0] of profile) {
        positions.push(p.x + p.nx * off, y, p.z + p.nz * off);
        normals.push(p.nx * cn, sn, p.nz * cn);
        uvs.push(run / per, y / height);
      }
    }
    for (let i = 0; i < pts.length; i++) {
      for (let j = 0; j < rows - 1; j++) {
        const a = i * rows + j;
        const b = (i + 1) * rows + j;
        index.push(a, b, a + 1, a + 1, b, b + 1);
      }
    }
    const geo = new THREE.BufferGeometry();
    geo.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
    geo.setAttribute('normal', new THREE.Float32BufferAttribute(normals, 3));
    geo.setAttribute('uv', new THREE.Float32BufferAttribute(uvs, 2));
    geo.setIndex(index);
    return { geo: track(geo), per };
  };

  /** Flat top inside the rounded edge; uv maps the design square (width = tier width) onto it. */
  const capGeometry = (pts, inset, y, designWidth) => {
    const shape = new THREE.Shape(pts.map((p) => new THREE.Vector2(p.x - p.nx * inset, -(p.z - p.nz * inset))));
    const geo = new THREE.ShapeGeometry(shape, 24);
    geo.rotateX(-Math.PI / 2);
    geo.translate(0, y, 0);
    const pos = geo.attributes.position;
    const uv = [];
    for (let i = 0; i < pos.count; i++) uv.push(pos.getX(i) / designWidth + 0.5, -pos.getZ(i) / designWidth + 0.5);
    geo.setAttribute('uv', new THREE.Float32BufferAttribute(uv, 2));
    return track(geo);
  };

  const patternTexture = (style, colour, per, height) => {
    const tile = document.createElement('canvas');
    tile.width = tile.height = 128;
    drawPatternTile(tile.getContext('2d'), style, colour, 128);
    const texture = track(new THREE.CanvasTexture(tile));
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.wrapS = texture.wrapT = THREE.RepeatWrapping;
    texture.repeat.set(Math.max(1, Math.round(per / 6)), Math.max(1, Math.round(height / 6)));
    texture.anisotropy = 4;
    return texture;
  };

  /** Piping along an edge. Top borders sit on the rounded edge; bottom borders sit on the board against the wall. */
  const border = (pts, y, style, colour, isTop) => {
    const per = pts.reduce((sum, p, i) => sum + Math.hypot(pts[(i + 1) % pts.length].x - p.x, pts[(i + 1) % pts.length].z - p.z), 0);
    const mat = pipingMaterial(colour);
    const place = (p, out, up) => new THREE.Vector3(p.x + p.nx * out, y + up, p.z + p.nz * out);
    if (style === 'rope' || style === 'ruffle') {
      const r = style === 'rope' ? 0.55 : 0.42;
      const waves = Math.round(per / 2.2);
      const path = pts.map((p, i) => {
        const wave = style === 'ruffle' ? Math.sin((i / pts.length) * Math.PI * 2 * waves) * 0.32 : 0;
        return place(p, isTop ? -EDGE_RADIUS * 0.55 : r * 0.45, (isTop ? r * 0.15 : r * 0.9) + wave);
      });
      const tube = new THREE.Mesh(track(new THREE.TubeGeometry(new THREE.CatmullRomCurve3(path, true), pts.length * 2, r, 12, true)), mat);
      tube.castShadow = true;
      parent.add(tube);
      if (style === 'rope') {
        // A second, twisting strand makes the rope read as twisted piping.
        const twist = pts.map((p, i) => {
          const a = (i / pts.length) * Math.PI * 2 * Math.round(per / 1.4);
          return place(p, (isTop ? -EDGE_RADIUS * 0.55 : r * 0.45) + Math.cos(a) * r * 0.55, (isTop ? r * 0.15 : r * 0.9) + Math.sin(a) * r * 0.55);
        });
        parent.add(new THREE.Mesh(track(new THREE.TubeGeometry(new THREE.CatmullRomCurve3(twist, true), pts.length * 4, r * 0.5, 8, true)), mat));
      }
      return;
    }
    const spacing = { shell: 1.45, bead: 1.15, dots: 1.7 }[style] || 1.4;
    const radius = { shell: 0.62, bead: 0.55, dots: 0.33 }[style] || 0.5;
    const count = Math.max(8, Math.round(per / spacing));
    const mesh = new THREE.InstancedMesh(track(new THREE.SphereGeometry(radius, 16, 12)), mat, count);
    const m = new THREE.Matrix4();
    const q = new THREE.Quaternion();
    const yAxis = new THREE.Vector3(0, 1, 0);
    for (let k = 0; k < count; k++) {
      const p = pts[Math.floor((k / count) * pts.length)];
      q.setFromAxisAngle(yAxis, p.angle);
      const shell = style === 'shell';
      const pos = isTop ? place(p, -EDGE_RADIUS * 0.5, radius * 0.25) : place(p, radius * 0.5, radius * (shell ? 0.6 : 0.85));
      m.compose(pos, q, new THREE.Vector3(1, shell ? 0.78 : 1, shell ? 1.75 : 1));
      mesh.setMatrixAt(k, m);
    }
    mesh.castShadow = true;
    parent.add(mesh);
  };

  /** Ganache drip: a glossy glaze over the top and rounded edge, with drops of different lengths. */
  const drip = (pts, y, colour, designWidth) => {
    const mat = track(new THREE.MeshPhysicalMaterial({ color: creamColour(THREE, colour), roughness: 0.22, clearcoat: 0.8, clearcoatRoughness: 0.15, envMapIntensity: 1 }));
    parent.add(new THREE.Mesh(capGeometry(pts, EDGE_RADIUS * 0.5, y + 0.12, designWidth), mat));
    const { geo } = wallGeometry(pts, y + 0.1, EDGE_RADIUS + 0.12, 0.12, y - EDGE_RADIUS);
    parent.add(new THREE.Mesh(geo, mat));
    const per = pts.reduce((sum, p, i) => sum + Math.hypot(pts[(i + 1) % pts.length].x - p.x, pts[(i + 1) % pts.length].z - p.z), 0);
    const count = Math.round(per / 1.9);
    const random = seeded(Math.round(per * 10));
    const drops = new THREE.InstancedMesh(track(new THREE.CapsuleGeometry(0.45, 1, 4, 12)), mat, count);
    const m = new THREE.Matrix4();
    for (let k = 0; k < count; k++) {
      const p = pts[Math.floor(((k + random() * 0.5) / count) * pts.length) % pts.length];
      const length = 1 + random() * 3.4;
      m.compose(new THREE.Vector3(p.x + p.nx * 0.14, y - EDGE_RADIUS - length / 2 + 0.3, p.z + p.nz * 0.14), new THREE.Quaternion(), new THREE.Vector3(1, length, 1));
      drops.setMatrixAt(k, m);
    }
    drops.castShadow = true;
    parent.add(drops);
  };

  /** White ceramic cake stand with a gold cake board, sized to the cake. */
  const stand = (radius) => {
    const plateR = radius + 3.2;
    const height = Math.min(9, 4 + radius * 0.22);
    const profile = [[0, 0], [plateR * 0.45, 0], [plateR * 0.47, 0.5], [plateR * 0.3, 1.1], [plateR * 0.13, 1.8], [plateR * 0.11, height - 1.4],
      [plateR * 0.2, height - 0.9], [plateR * 0.96, height - 0.75], [plateR, height - 0.45], [plateR * 0.99, height], [0, height]].map(([x, y]) => new THREE.Vector2(x, y));
    const ceramic = track(new THREE.MeshPhysicalMaterial({ color: 0xfdfaf6, roughness: 0.18, clearcoat: 1, clearcoatRoughness: 0.12, envMapIntensity: 0.9 }));
    for (let k = 0; k < 48; k++) {
      const a = (k / 48) * Math.PI * 2;
      fitPoints.push(new THREE.Vector3(Math.cos(a) * plateR, 0, Math.sin(a) * plateR), new THREE.Vector3(Math.cos(a) * plateR, height, Math.sin(a) * plateR));
    }
    const standMesh = new THREE.Mesh(track(new THREE.LatheGeometry(profile, 96)), ceramic);
    standMesh.castShadow = true;
    standMesh.receiveShadow = true;
    world.add(standMesh);
    const gold = track(new THREE.MeshStandardMaterial({ color: 0xe2bf72, metalness: 0.85, roughness: 0.3, envMapIntensity: 1.1 }));
    const board = new THREE.Mesh(track(new THREE.CylinderGeometry(radius + 1.1, radius + 1.1, 0.35, 96)), gold);
    board.position.y = height + 0.175;
    board.receiveShadow = true;
    world.add(board);
    return height + 0.35;
  };

  const applyTop = () => {
    if (!topDecal) return;
    if (topTexture) { topTexture.dispose(); topTexture = null; }
    if (topCanvas) {
      topTexture = new THREE.CanvasTexture(topCanvas);
      topTexture.colorSpace = THREE.SRGBColorSpace;
      topTexture.anisotropy = 8;
      topDecal.material.map = topTexture;
      topDecal.material.visible = true;
    } else {
      topDecal.material.map = null;
      topDecal.material.visible = false;
    }
    topDecal.material.needsUpdate = true;
  };

  const build = () => {
    clearCake();
    if (!spec) return;
    const tiers = Math.max(1, Math.min(3, spec.tiers || 1));
    const base = baseDiameterCm(spec.grams, tiers, geometry);
    const points = outline(spec.shape, 180);
    const halfExtent = Math.max(...points.map(([x, y]) => Math.hypot(x, y))) * base;
    parent = world;
    fitPoints = [];
    fitCache = { key: '', distance: 60 };
    const standTop = stand(halfExtent);
    let y = standTop;
    for (let t = 0; t < tiers; t++) {
      const scale = base * geometry.tierScale[t];
      const height = TIER_HEIGHT_CM - t * 0.6;
      const pts = worldOutline(points, scale);
      parent = new THREE.Group();
      parent.position.y = y;
      world.add(parent);
      // Fit points around the tier, a little outside it to leave room for piping and drips.
      pts.forEach((p, i) => { if (i % 6 === 0) fitPoints.push(new THREE.Vector3(p.x + p.nx * 1.2, y, p.z + p.nz * 1.2), new THREE.Vector3(p.x + p.nx * 1.2, y + height + 1.2, p.z + p.nz * 1.2)); });
      const { geo: wall, per } = wallGeometry(pts, height, EDGE_RADIUS);
      const wallMesh = new THREE.Mesh(wall, creamMaterial(spec.base, [Math.max(1, Math.round(per / 14)), 1]));
      wallMesh.castShadow = true;
      wallMesh.receiveShadow = true;
      parent.add(wallMesh);
      const top = new THREE.Mesh(capGeometry(pts, EDGE_RADIUS, height, scale), creamMaterial(spec.base, [2, 2]));
      top.receiveShadow = true;
      parent.add(top);
      if (spec.pattern) {
        const { geo } = wallGeometry(pts, height - EDGE_RADIUS, 0, 0.05, height * 0.08);
        parent.add(new THREE.Mesh(geo, track(new THREE.MeshStandardMaterial({
          map: patternTexture(spec.pattern.style, spec.pattern.colour, per, height), transparent: true, roughness: 0.6, depthWrite: false, polygonOffset: true, polygonOffsetFactor: -1,
        }))));
      }
      const isTopTier = t === tiers - 1;
      if (spec.drip && isTopTier) drip(pts, height, spec.drip, scale);
      if (isTopTier) {
        topDecal = new THREE.Mesh(capGeometry(pts, EDGE_RADIUS + 0.2, height + (spec.drip ? 0.2 : 0.05), scale), track(new THREE.MeshStandardMaterial({
          transparent: true, roughness: 0.5, polygonOffset: true, polygonOffsetFactor: -2,
        })));
        parent.add(topDecal);
      }
      if (spec.borderTop) border(pts, height, spec.borderTop.style, spec.borderTop.colour, true);
      if (spec.borderBottom) border(pts, 0, spec.borderBottom.style, spec.borderBottom.colour, false);
      y += height;
    }
    parent = world;

    // Fit the camera and the shadow to everything that is drawn.
    const box = new THREE.Box3().setFromObject(world);
    const sphere = box.getBoundingSphere(new THREE.Sphere());
    fit.center.copy(box.getCenter(new THREE.Vector3()));
    fit.radius = Math.max(12, sphere.radius);
    const r = fit.radius;
    key.position.set(fit.center.x - r * 1.1, fit.center.y + r * 2.2, fit.center.z + r * 1.4);
    key.target.position.copy(fit.center);
    Object.assign(key.shadow.camera, { left: -r * 1.6, right: r * 1.6, top: r * 1.6, bottom: -r * 1.6, near: 1, far: r * 8 });
    key.shadow.camera.updateProjectionMatrix();
    fill.position.set(fit.center.x + r * 2, fit.center.y + r * 0.6, fit.center.z + r * 0.8);
    rim.position.set(fit.center.x + r * 0.4, fit.center.y + r * 1.5, fit.center.z - r * 2);
    applyTop();
    requestRender();
  };

  // Turn / tilt / zoom (zoom never moves the camera closer than the full-cake fit allows at 1×, nor too far out).
  let drag = null;
  const pointers = new Map();
  const clampZoom = (z) => Math.min(2.6, Math.max(0.75, z));
  canvas.addEventListener('pointerdown', (e) => {
    canvas.setPointerCapture(e.pointerId);
    pointers.set(e.pointerId, [e.clientX, e.clientY]);
    drag = { x: e.clientX, y: e.clientY, yaw: state.yaw, pitch: state.pitch, dist: null, zoom: state.zoom };
  });
  canvas.addEventListener('pointermove', (e) => {
    if (!drag || !pointers.has(e.pointerId)) return;
    pointers.set(e.pointerId, [e.clientX, e.clientY]);
    if (pointers.size === 2) {
      const [a, b] = [...pointers.values()];
      const dist = Math.hypot(a[0] - b[0], a[1] - b[1]);
      drag.dist ??= dist;
      state.zoom = clampZoom(drag.zoom * (dist / drag.dist));
    } else {
      state.yaw = drag.yaw - (e.clientX - drag.x) * 0.01;
      state.pitch = Math.min(1.53, Math.max(0.02, drag.pitch + (e.clientY - drag.y) * 0.008));
    }
    requestRender();
  });
  const end = (e) => { pointers.delete(e.pointerId); if (!pointers.size) drag = null; };
  canvas.addEventListener('pointerup', end);
  canvas.addEventListener('pointercancel', end);
  canvas.addEventListener('wheel', (e) => {
    e.preventDefault();
    state.zoom = clampZoom(state.zoom * (e.deltaY < 0 ? 1.08 : 0.93));
    requestRender();
  }, { passive: false });
  canvas.addEventListener('keydown', (e) => {
    const step = { ArrowLeft: [-0.15, 0], ArrowRight: [0.15, 0], ArrowUp: [0, 0.1], ArrowDown: [0, -0.1] }[e.key];
    if (step) { e.preventDefault(); state.yaw += step[0]; state.pitch = Math.min(1.53, Math.max(0.02, state.pitch + step[1])); requestRender(); }
    if (e.key === '+' || e.key === '=') api.zoom(1);
    if (e.key === '-') api.zoom(-1);
  });

  let tween = 0;
  let pendingBuild = 0;
  const api = {
    update(next) {
      spec = next;
      if (pendingBuild) return;
      pendingBuild = requestAnimationFrame(() => { pendingBuild = 0; build(); });
    },
    setTop(canvasEl) { topCanvas = canvasEl; applyTop(); requestRender(); },
    refreshTop() { if (topTexture) { topTexture.needsUpdate = true; requestRender(); } },
    setView(name) {
      const target = { yaw: name === 'front' || name === 'top' ? 0 : -0.55, pitch: VIEWS[name] ?? VIEWS['3d'], zoom: 1 };
      cancelAnimationFrame(tween);
      if (reducedMotion) { Object.assign(state, target); requestRender(); return; }
      const from = { ...state };
      const start = performance.now();
      const stepTween = (now) => {
        const t = Math.min(1, (now - start) / 420);
        const k = 1 - (1 - t) ** 3;
        for (const key2 of ['yaw', 'pitch', 'zoom']) state[key2] = from[key2] + (target[key2] - from[key2]) * k;
        render();
        if (t < 1) tween = requestAnimationFrame(stepTween);
      };
      tween = requestAnimationFrame(stepTween);
    },
    zoom(direction) { state.zoom = clampZoom(state.zoom * (direction > 0 ? 1.15 : 0.87)); requestRender(); },
    /** PNG of the cake from the standard 3D angle (for the order, job sheet and "Download preview"). */
    snapshot(type = 'image/png') {
      if (pendingBuild) { cancelAnimationFrame(pendingBuild); pendingBuild = 0; build(); }
      const saved = { ...state };
      Object.assign(state, { yaw: -0.55, pitch: VIEWS['3d'], zoom: 1 });
      render();
      const url = canvas.toDataURL(type, 0.9);
      Object.assign(state, saved);
      render();
      return url;
    },
    current() { render(); return canvas.toDataURL('image/png'); },
    dispose() { observer.disconnect(); clearCake(); renderer.dispose(); },
  };
  resize();
  return api;
}

/**
 * Cream colours as they look in real frosting: the chosen colour is kept, but very light colours are nudged a
 * touch warmer so white reads as cream, not grey plastic.
 */
function creamColour(THREE, hex) {
  const colour = new THREE.Color(hex);
  const hsl = colour.getHSL({});
  if (hsl.l > 0.9 && hsl.s < 0.5) colour.setHSL(0.09, Math.max(hsl.s, 0.45), Math.min(hsl.l, 0.965), THREE.SRGBColorSpace);
  return colour;
}

/** A fine spatula-stroke texture for frosting, drawn in code (used as a bump map). */
function frostingTextures(THREE) {
  const size = 256;
  const c = document.createElement('canvas');
  c.width = c.height = size;
  const ctx = c.getContext('2d');
  ctx.fillStyle = '#808080';
  ctx.fillRect(0, 0, size, size);
  const random = seeded(7);
  // Fine grain (the slightly rough surface of buttercream) …
  const grain = ctx.getImageData(0, 0, size, size);
  for (let i = 0; i < grain.data.length; i += 4) {
    const v = 128 + Math.round((random() - 0.5) * 26);
    grain.data[i] = grain.data[i + 1] = grain.data[i + 2] = v;
  }
  ctx.putImageData(grain, 0, 0);
  // … and soft horizontal spatula strokes, wrapped so the tile repeats without seams.
  for (let i = 0; i < 520; i++) {
    const x = random() * size;
    const y = random() * size;
    const w = 14 + random() * 60;
    const h = 1.5 + random() * 3.5;
    const shade = random() < 0.5 ? 90 + Math.round(random() * 25) : 150 + Math.round(random() * 30);
    ctx.fillStyle = `rgba(${shade},${shade},${shade},0.32)`;
    for (const dx of [0, -size, size]) {
      ctx.beginPath();
      ctx.ellipse(x + dx, y, w, h, (random() - 0.5) * 0.2, 0, Math.PI * 2);
      ctx.fill();
    }
  }
  const bump = new THREE.CanvasTexture(c);
  bump.wrapS = bump.wrapT = THREE.RepeatWrapping;
  return { bump };
}

/** A warm photo-studio environment (soft boxes on a warm room) for gentle reflections, built from simple meshes. */
function studioEnvironment(THREE, renderer) {
  const env = new THREE.Scene();
  const room = new THREE.Mesh(new THREE.SphereGeometry(50, 32, 16), new THREE.MeshBasicMaterial({ color: 0x8a7868, side: THREE.BackSide }));
  env.add(room);
  const panel = (w, h, colour, intensity, position, look) => {
    const mesh = new THREE.Mesh(new THREE.PlaneGeometry(w, h), new THREE.MeshBasicMaterial({ color: new THREE.Color(colour).multiplyScalar(intensity), side: THREE.DoubleSide }));
    mesh.position.set(...position);
    mesh.lookAt(...look);
    env.add(mesh);
  };
  panel(40, 30, 0xfff1e0, 2.2, [-20, 30, 25], [0, 0, 0]); // big warm key soft box
  panel(30, 30, 0xffe9d8, 1.1, [35, 10, 10], [0, 0, 0]);  // fill
  panel(60, 10, 0xffffff, 1.4, [0, 45, -20], [0, 0, 0]);  // overhead strip
  panel(80, 20, 0xe8d6c4, 0.6, [0, -10, 40], [0, 0, 0]);  // warm bounce from the table
  const pmrem = new THREE.PMREMGenerator(renderer);
  const texture = pmrem.fromScene(env, 0.04).texture;
  pmrem.dispose();
  return texture;
}

/** True when this device can draw WebGL; `strict` refuses slow software-only rendering. */
export function webglAvailable(strict = false) {
  try {
    const c = document.createElement('canvas');
    const options = strict ? { failIfMajorPerformanceCaveat: true } : {};
    return !!(window.WebGLRenderingContext && (c.getContext('webgl2', options) || c.getContext('webgl', options)));
  } catch {
    return false;
  }
}
