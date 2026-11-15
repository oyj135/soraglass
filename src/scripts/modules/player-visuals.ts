/** Playback decoration is independent of the audible, persistent player.
 * Only a silent CORS-capable probe enters Web Audio; incompatible streams keep
 * playing normally and use an explicitly decorative waveform instead. */
export function createPlayerVisuals(audio: HTMLAudioElement): {
  mount: () => void;
  unmount: () => void;
  track: () => void;
  resume: () => void;
  sync: () => void;
} {
  type Surface = { canvas: HTMLCanvasElement; context: CanvasRenderingContext2D; a: string; b: string };
  let artwork: HTMLElement[] = [], surfaces: Surface[] = [];
  let phase = 0, tick = performance.now(), running = false, frame = 0;
  let context: AudioContext | null = null, analyser: AnalyserNode | null = null;
  let samples: Uint8Array<ArrayBuffer> | null = null, probe: HTMLAudioElement | null = null;
  let controller: AbortController | null = null, deadline = 0, generation = 0, probeUrl = '';
  const cors = new Map<string, boolean>();
  const reduce = matchMedia('(prefers-reduced-motion: reduce)');

  const advance = (): void => {
    const now = performance.now();
    if (running) phase = (phase + (now - tick) * .0225) % 360;
    tick = now;
  };
  const clearProbe = (): void => {
    generation++; controller?.abort(); controller = null;
    window.clearTimeout(deadline); deadline = 0; probeUrl = '';
    if (probe) { probe.onloadedmetadata = null; probe.onerror = null; probe.pause(); probe.removeAttribute('src'); probe.load(); }
  };
  const hasSurfaces = (): boolean => surfaces.length > 0 && !document.hidden && !reduce.matches;
  const draw = (): void => {
    advance();
    artwork.forEach(el => el.style.setProperty('--sg-record-angle', `${phase.toFixed(2)}deg`));
    const active = running && !audio.muted && audio.volume > 0 && !audio.ended && hasSurfaces();
    const spectrum = active && probe && !probe.paused && probe.readyState >= 2 && analyser && samples;
    if (spectrum && analyser && samples) analyser.getByteFrequencyData(samples);
    surfaces.forEach(({ canvas, context: ctx, a, b }) => {
      if (!canvas.isConnected) return;
      const { width, height } = canvas.getBoundingClientRect();
      if (!width || !height) return;
      const ratio = Math.min(devicePixelRatio || 1, 2);
      if (canvas.width !== Math.round(width * ratio) || canvas.height !== Math.round(height * ratio)) {
        canvas.width = Math.round(width * ratio); canvas.height = Math.round(height * ratio);
      }
      ctx.setTransform(ratio, 0, 0, ratio, 0, 0); ctx.clearRect(0, 0, width, height);
      const gradient = ctx.createLinearGradient(0, 0, width, 0);
      gradient.addColorStop(0, a); gradient.addColorStop(1, b); ctx.fillStyle = gradient;
      const count = Math.max(24, Math.floor(width / 6)), gap = 2.5;
      const bar = (width - gap * (count - 1)) / count;
      const time = audio.currentTime || 0;
      canvas.dataset.sgWaveMode = spectrum ? 'spectrum' : 'decorative';
      for (let i = 0; i < count; i++) {
        const frequency = Math.min((samples?.length ?? 1) - 1, Math.round(2 * Math.pow(45, i / (count - 1))));
        const decorative = .18 + .48 * Math.abs(Math.sin(time * 2.1 + i * .42) * Math.cos(time * 1.3 - i * .17));
        const level = spectrum && samples ? Math.pow(samples[frequency] / 255, 1.5) : decorative;
        const h = active ? 2 + level * (height - 6) : 2;
        ctx.globalAlpha = active ? .88 : .35;
        ctx.fillRect(i * (bar + gap), height - h - 2, Math.max(1, bar), h);
      }
      ctx.globalAlpha = 1;
    });
  };
  const render = (): void => {
    cancelAnimationFrame(frame); frame = 0; draw();
    if (running && !document.hidden && !reduce.matches && (artwork.length || surfaces.length)) frame = requestAnimationFrame(render);
  };
  const readyContext = (): void => {
    if (!hasSurfaces() || !window.AudioContext) return;
    try {
      if (!context) {
        context = new AudioContext(); analyser = context.createAnalyser();
        analyser.fftSize = 512; analyser.smoothingTimeConstant = .8;
        samples = new Uint8Array(analyser.frequencyBinCount);
        probe = new Audio(); probe.crossOrigin = 'anonymous'; probe.preload = 'metadata';
        const source = context.createMediaElementSource(probe), silence = context.createGain();
        silence.gain.value = 0; source.connect(analyser); analyser.connect(silence); silence.connect(context.destination);
      }
      if (context.state === 'suspended') void context.resume().catch(() => { /* decoration remains available */ });
    } catch { /* Web Audio is optional; never change the audible audio element. */ }
  };
  const prepareProbe = (): void => {
    const src = audio.currentSrc || audio.src;
    if (!running || !hasSurfaces() || !probe || !context || !src || probeUrl === src || cors.get(src) === false) return;
    clearProbe(); probeUrl = src;
    const seq = generation, target = probe, abort = new AbortController(); controller = abort;
    deadline = window.setTimeout(() => {
      if (seq === generation) { cors.set(src, false); clearProbe(); }
    }, 8000);
    void (async () => {
      let allowed = cors.get(src);
      if (allowed === undefined) {
        const url = new URL(src, location.href);
        allowed = url.origin === location.origin || url.protocol === 'blob:';
        if (!allowed) {
          // A CORS HEAD probe downloads no music body. Failed/unsupported HEAD
          // deliberately falls back; it cannot make the audible stream silent.
          const response = await fetch(src, { method:'HEAD', mode:'cors', credentials:'omit', signal:abort.signal });
          allowed = response.ok;
        }
        cors.set(src, allowed);
      }
      if (!allowed || seq !== generation || !running || !hasSurfaces()) return;
      target.onloadedmetadata = () => {
        if (seq !== generation || !running || !hasSurfaces()) return;
        try { target.currentTime = Math.min(audio.currentTime, Number.isFinite(target.duration) ? Math.max(0,target.duration-.01) : audio.currentTime); } catch { /* unseekable live stream uses decoration */ }
        void target.play().then(() => {
          if (seq !== generation) return;
          if (!running || !hasSurfaces()) { target.pause(); return; }
          window.clearTimeout(deadline); deadline = 0;
        }).catch(() => { if (seq === generation) { cors.set(src,false); clearProbe(); } });
      };
      target.onerror = () => { if (seq === generation) { cors.set(src,false); clearProbe(); } };
      target.src = src; target.load();
    })().catch(() => { if (seq === generation) { cors.set(src,false); clearProbe(); } });
  };
  const sync = (): void => {
    advance(); running = !audio.paused && !audio.ended; tick = performance.now();
    if (!running || !hasSurfaces()) clearProbe();
    else prepareProbe();
    render();
  };
  const resume = (): void => { readyContext(); prepareProbe(); };
  const colors = (): void => {
    surfaces.forEach(surface => {
      const style = getComputedStyle(surface.canvas);
      surface.a = style.getPropertyValue('--sg-wave-a').trim() || '#53b8c8';
      surface.b = style.getPropertyValue('--sg-wave-b').trim() || '#be78ae';
    });
    render();
  };
  const mount = (): void => {
    artwork = Array.from(document.querySelectorAll<HTMLElement>('[data-sg-album-art]'));
    surfaces = Array.from(document.querySelectorAll<HTMLCanvasElement>('[data-sg-wave]')).flatMap(canvas => {
      const ctx = canvas.getContext('2d');
      return ctx ? [{ canvas, context:ctx, a:'#53b8c8', b:'#be78ae' }] : [];
    });
    colors(); sync(); if (running) resume();
  };
  const unmount = (): void => { advance(); artwork = []; surfaces = []; cancelAnimationFrame(frame); frame = 0; clearProbe(); };
  const track = (): void => { phase = 0; tick = performance.now(); clearProbe(); render(); };
  audio.addEventListener('timeupdate', () => {
    if (probe && !probe.paused && probe.readyState >= 1 && Math.abs(probe.currentTime-audio.currentTime) > .5) {
      try { probe.currentTime = audio.currentTime; } catch { /* decoration until seek is available */ }
    }
  });
  audio.addEventListener('volumechange', render);
  document.addEventListener('visibilitychange', () => { sync(); if (running) resume(); });
  reduce.addEventListener('change', () => { sync(); if (running) resume(); });
  window.addEventListener('resize', render, { passive:true });
  new MutationObserver(colors).observe(document.documentElement, { attributes:true, attributeFilter:['class'] });
  window.addEventListener('pagehide', () => { unmount(); void context?.close().catch(() => {}); });
  mount();
  return { mount, unmount, track, resume, sync };
}
