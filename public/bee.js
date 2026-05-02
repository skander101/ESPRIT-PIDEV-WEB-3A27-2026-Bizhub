// Animated Bee Cursor Follower for BizHub
(function() {
  // --- SVG BEE CREATION ---
  const beeSVG = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  beeSVG.setAttribute('viewBox', '0 0 54 54');
  beeSVG.setAttribute('width', '48');
  beeSVG.setAttribute('height', '48');
  beeSVG.style.position = 'fixed';
  beeSVG.style.zIndex = '9999';
  beeSVG.style.pointerEvents = 'none';
  beeSVG.style.left = '0px';
  beeSVG.style.top = '0px';
  beeSVG.style.transition = 'none';
  beeSVG.style.willChange = 'transform';

  // Wings
  function createWing(id, cx, cy, rx, ry, rot) {
    const wing = document.createElementNS('http://www.w3.org/2000/svg', 'ellipse');
    wing.setAttribute('id', id);
    wing.setAttribute('cx', cx);
    wing.setAttribute('cy', cy);
    wing.setAttribute('rx', rx);
    wing.setAttribute('ry', ry);
    wing.setAttribute('fill', 'rgba(200,230,255,0.8)');
    wing.setAttribute('stroke', 'rgba(100,160,220,0.5)');
    wing.setAttribute('stroke-width', '0.8');
    wing.setAttribute('transform', `rotate(${rot},${cx},${cy})`);
    return wing;
  }
  const wingL = createWing('bee-wing-l', 18, 24, 9, 5, -20);
  const wingR = createWing('bee-wing-r', 36, 24, 9, 5, 20);
  beeSVG.appendChild(wingL);
  beeSVG.appendChild(wingR);
  // Body
  const body = document.createElementNS('http://www.w3.org/2000/svg', 'ellipse');
  body.setAttribute('cx', '27');
  body.setAttribute('cy', '30');
  body.setAttribute('rx', '11');
  body.setAttribute('ry', '8');
  body.setAttribute('fill', '#FDD835');
  beeSVG.appendChild(body);
  // Stripes
  function stripe(x, y, w, h, r, op) {
    const s = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
    s.setAttribute('x', x); s.setAttribute('y', y);
    s.setAttribute('width', w); s.setAttribute('height', h);
    s.setAttribute('rx', r); s.setAttribute('fill', '#5D3A00');
    s.setAttribute('opacity', op);
    return s;
  }
  beeSVG.appendChild(stripe(19,27,16,3,1.5,0.65));
  beeSVG.appendChild(stripe(20,31,14,2.5,1.2,0.55));
  beeSVG.appendChild(stripe(21,34,12,2,1,0.45));
  // Head
  const head = document.createElementNS('http://www.w3.org/2000/svg', 'ellipse');
  head.setAttribute('cx', '27');
  head.setAttribute('cy', '22');
  head.setAttribute('rx', '6');
  head.setAttribute('ry', '5.5');
  head.setAttribute('fill', '#FDD835');
  head.setAttribute('stroke', '#5D3A00');
  head.setAttribute('stroke-width', '0.8');
  beeSVG.appendChild(head);
  // Eye
  const eye = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
  eye.setAttribute('cx', '25');
  eye.setAttribute('cy', '21');
  eye.setAttribute('r', '1.5');
  eye.setAttribute('fill', '#1a1a1a');
  beeSVG.appendChild(eye);
  const eyeShine = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
  eyeShine.setAttribute('cx', '25.4');
  eyeShine.setAttribute('cy', '20.5');
  eyeShine.setAttribute('r', '0.5');
  eyeShine.setAttribute('fill', '#fff');
  eyeShine.setAttribute('opacity', '0.7');
  beeSVG.appendChild(eyeShine);
  // Antennae
  function antenna(x1,y1,x2,y2) {
    const l = document.createElementNS('http://www.w3.org/2000/svg', 'line');
    l.setAttribute('x1',x1); l.setAttribute('y1',y1);
    l.setAttribute('x2',x2); l.setAttribute('y2',y2);
    l.setAttribute('stroke','#5D3A00');
    l.setAttribute('stroke-width','0.9');
    return l;
  }
  beeSVG.appendChild(antenna(24,17.5,21,14));
  beeSVG.appendChild(antenna(26,17,24.5,13.5));
  function antDot(cx,cy) {
    const c = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
    c.setAttribute('cx',cx); c.setAttribute('cy',cy);
    c.setAttribute('r','1'); c.setAttribute('fill','#5D3A00');
    return c;
  }
  beeSVG.appendChild(antDot(21,13.5));
  beeSVG.appendChild(antDot(24,13));
  // Legs
  function leg(x1,y1,x2,y2) {
    const l = document.createElementNS('http://www.w3.org/2000/svg', 'line');
    l.setAttribute('x1',x1); l.setAttribute('y1',y1);
    l.setAttribute('x2',x2); l.setAttribute('y2',y2);
    l.setAttribute('stroke','#5D3A00');
    l.setAttribute('stroke-width','0.8');
    return l;
  }
  beeSVG.appendChild(leg(22,37,20,41));
  beeSVG.appendChild(leg(27,38,27,42));
  beeSVG.appendChild(leg(32,37,34,41));

  document.body.appendChild(beeSVG);

  // --- STATE ---
  let mouseX = window.innerWidth/2, mouseY = window.innerHeight/2;
  let beeX = mouseX, beeY = mouseY;
  // Initial target aligns stinger to center
  let targetX = mouseX - 24, targetY = mouseY - 37;
  let prevX = beeX, prevY = beeY;
  let focusEl = null, focusRect = null, focusMode = false, landing = false, landingStart = 0;
  let lastInputRect = null;
  let lastFocusTime = 0;
  let blurTimeout = null;

  // --- EVENT LISTENERS ---
  document.addEventListener('mousemove', e => {
    mouseX = e.clientX;
    mouseY = e.clientY;
  });

  function updateFocusRect() {
    if (focusEl) {
      focusRect = focusEl.getBoundingClientRect();
      lastInputRect = focusRect;
    }
  }
  document.addEventListener('focusin', e => {
    if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT')) {
      focusEl = e.target;
      updateFocusRect();
      focusMode = true;
      landing = true;
      landingStart = performance.now();
      lastFocusTime = Date.now();
      if (blurTimeout) { clearTimeout(blurTimeout); blurTimeout = null; }
    }
  });
  document.addEventListener('focusout', e => {
    if (e.target === focusEl) {
      blurTimeout = setTimeout(() => {
        focusEl = null;
        focusRect = null;
        focusMode = false;
      }, 300);
    }
  });
  window.addEventListener('scroll', () => {
    if (focusEl) updateFocusRect();
  }, true);

  // --- ANIMATION LOOP ---
  function lerp(a, b, f) { return a + (b - a) * f; }
  function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }

  function animate() {
    prevX = beeX; prevY = beeY;
    let now = performance.now();
    let dt = 1/60;
    // Target position
    if (focusMode && focusRect) {
      // Sit to the left of the focused field, vertically centered
      targetX = focusRect.left - 36;
      targetY = focusRect.top + focusRect.height/2 - 24;
      // Lerp slow for floaty landing
      beeX = lerp(beeX, targetX, 0.08);
      beeY = lerp(beeY, targetY, 0.08);
    } else {
      // Cursor mode: stinger tip at cursor, no wobble, snappy
      let wobble = 0;
      targetX = mouseX - 24;
      targetY = mouseY - 25 + wobble;
      beeX = lerp(beeX, targetX, 0.25);
      beeY = lerp(beeY, targetY, 0.25);
    }
    // Landing bounce
    let bounce = 0;
    if (landing) {
      let t = (now - landingStart)/340;
      if (t < 1) {
        bounce = Math.abs(Math.sin(Math.PI * t)) * 10 * (1-t);
      } else {
        landing = false;
      }
    }
    // Compute velocity for tilt
    let vx = beeX - prevX, vy = beeY - prevY;
    let tilt = clamp(Math.atan2(vy, vx)*0.4, -0.44, 0.44); // ±25deg
    // Apply transform
    beeSVG.style.transform = `translate3d(${beeX|0}px,${(beeY-bounce)|0}px,0) rotate(${tilt}rad)`;
    // Animate wings
    let flap = Math.sin(Date.now()*0.015);
    let wingLangle = -20 + flap*16;
    let wingRangle = 20 - flap*16;
    wingL.setAttribute('transform', `rotate(${wingLangle},18,24)`);
    wingR.setAttribute('transform', `rotate(${wingRangle},36,24)`);
    requestAnimationFrame(animate);
  }
  animate();
})();
