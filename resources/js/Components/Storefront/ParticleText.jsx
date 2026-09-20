import { useEffect, useRef } from 'react';

import './ParticleText.css';

const clamp = (value, min, max) => Math.min(Math.max(value, min), max);
const easeOutCubic = value => 1 - (1 - value) ** 3;

const hexToRgb = value => {
  const hex = String(value).replace('#', '').trim();
  if (!/^[0-9a-f]{6}$/i.test(hex)) return null;
  return { r: parseInt(hex.slice(0, 2), 16), g: parseInt(hex.slice(2, 4), 16), b: parseInt(hex.slice(4, 6), 16) };
};

const mixColor = (from, to, amount) => `rgb(${Math.round(from.r + (to.r - from.r) * amount)}, ${Math.round(from.g + (to.g - from.g) * amount)}, ${Math.round(from.b + (to.b - from.b) * amount)})`;

export default function ParticleText({
  text = 'React Bits', particleSize = 2, density = 4, color = '#ffffff', highlightColor = '#8b5cf6',
  scatter = 180, gatherDuration = 1600, stagger = 420, pointerRepel = 40, repelRadius = 120,
  idleDrift = 0.7, trigger = 'mount', fontSize = 'clamp(3rem, 12vw, 8rem)', fontWeight = 800,
  fontFamily = 'inherit', glow = true, align = 'center', showText = false, textColor = color, particleOpacity = 1, className = '', style,
}) {
  const containerRef = useRef(null);
  const canvasRef = useRef(null);

  useEffect(() => {
    const container = containerRef.current;
    const canvas = canvasRef.current;
    const context = canvas?.getContext('2d');
    if (!container || !canvas || !context) return undefined;

    let particles = [];
    let frame = null;
    let resizeFrame = null;
    let buildId = 0;
    let gathering = false;
    let gatherStartedAt = 0;
    let width = 0;
    let height = 0;
    let reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
    const pointer = { active: false, x: 0, y: 0, smoothX: 0, smoothY: 0 };

    const startGather = (fromScatter = true) => {
      if (!particles.length) return;
      const spread = reducedMotion ? 0 : scatter;
      particles.forEach(particle => {
        if (fromScatter) {
          const angle = particle.seed * Math.PI * 2;
          const distance = spread * (0.35 + particle.depth * 0.75);
          particle.x = particle.targetX + Math.cos(angle) * distance + (particle.depth - 0.5) * spread * 0.55;
          particle.y = particle.targetY + Math.sin(angle) * distance + (particle.seed - 0.5) * spread * 0.55;
        }
        particle.startX = particle.x;
        particle.startY = particle.y;
        particle.delay = reducedMotion ? 0 : particle.seed * stagger;
      });
      gatherStartedAt = performance.now();
      gathering = true;
    };

    const draw = now => {
      context.clearRect(0, 0, width, height);
      context.shadowBlur = glow && !reducedMotion ? particleSize * 3 : 0;
      context.shadowColor = highlightColor;
      pointer.smoothX += (pointer.x - pointer.smoothX) * 0.18;
      pointer.smoothY += (pointer.y - pointer.smoothY) * 0.18;

      let complete = true;
      particles.forEach(particle => {
        let x = particle.targetX;
        let y = particle.targetY;
        let progress = 1;

        if (gathering) {
          progress = clamp((now - gatherStartedAt - particle.delay) / Math.max(1, reducedMotion ? 1 : gatherDuration), 0, 1);
          const eased = easeOutCubic(progress);
          x = particle.startX + (particle.targetX - particle.startX) * eased;
          y = particle.startY + (particle.targetY - particle.startY) * eased;
          if (progress < 1) complete = false;
        } else if (!reducedMotion && idleDrift > 0) {
          const driftTime = now * 0.001;
          x += Math.sin(driftTime * 0.9 + particle.seed * 10) * idleDrift * particle.depth;
          y += Math.cos(driftTime * 0.75 + particle.depth * 10) * idleDrift * particle.depth;
        }

        if (pointer.active && !reducedMotion && pointerRepel > 0 && repelRadius > 0) {
          const deltaX = x - pointer.smoothX;
          const deltaY = y - pointer.smoothY;
          const distance = Math.hypot(deltaX, deltaY);
          if (distance > 0 && distance < repelRadius) {
            const force = (1 - distance / repelRadius) ** 2 * pointerRepel;
            x += (deltaX / distance) * force;
            y += (deltaY / distance) * force;
          }
        }

        particle.x += (x - particle.x) * (reducedMotion ? 1 : 0.22);
        particle.y += (y - particle.y) * (reducedMotion ? 1 : 0.22);
        context.globalAlpha = clamp(0.35 + progress * 0.65, 0, 1);
        context.fillStyle = particle.color;
        if (particle.size <= 2.1) context.fillRect(particle.x - particle.size / 2, particle.y - particle.size / 2, particle.size, particle.size);
        else {
          context.beginPath();
          context.arc(particle.x, particle.y, particle.size / 2, 0, Math.PI * 2);
          context.fill();
        }
      });

      context.globalAlpha = 1;
      context.shadowBlur = 0;
      if (gathering && complete) gathering = false;
      frame = requestAnimationFrame(draw);
    };

    const sampleText = async () => {
      const currentBuild = ++buildId;
      const rect = container.getBoundingClientRect();
      width = Math.floor(rect.width);
      height = Math.floor(rect.height);
      if (!width || !height) return;

      const pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
      canvas.width = Math.max(1, Math.floor(width * pixelRatio));
      canvas.height = Math.max(1, Math.floor(height * pixelRatio));
      context.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);

      const computed = window.getComputedStyle(container);
      const resolvedFamily = fontFamily === 'inherit' ? computed.fontFamily || 'sans-serif' : fontFamily;
      let resolvedSize = typeof fontSize === 'number' ? fontSize : parseFloat(computed.fontSize) || 18;
      let font = `${fontWeight} ${resolvedSize}px ${resolvedFamily}`;
      if (document.fonts) {
        try { await document.fonts.load(font); } catch {}
        await document.fonts.ready;
      }
      if (currentBuild !== buildId) return;

      const offscreen = document.createElement('canvas');
      const offContext = offscreen.getContext('2d', { willReadFrequently: true });
      if (!offContext) return;
      const content = String(text || ' ');
      offContext.font = font;
      let metrics = offContext.measureText(content);
      if (metrics.width > width * 0.92) {
        resolvedSize = Math.max(10, resolvedSize * (width * 0.92 / metrics.width));
        font = `${fontWeight} ${resolvedSize}px ${resolvedFamily}`;
        offContext.font = font;
        metrics = offContext.measureText(content);
      }

      const left = Math.ceil(metrics.actualBoundingBoxLeft || 0);
      const right = Math.ceil(metrics.actualBoundingBoxRight || metrics.width);
      const ascent = Math.ceil(metrics.actualBoundingBoxAscent || resolvedSize * 0.78);
      const descent = Math.ceil(metrics.actualBoundingBoxDescent || resolvedSize * 0.22);
      const padding = Math.max(4, Math.ceil(resolvedSize * 0.08));
      offscreen.width = Math.max(1, left + right + padding * 2);
      offscreen.height = Math.max(1, ascent + descent + padding * 2);
      offContext.font = font;
      offContext.textBaseline = 'alphabetic';
      offContext.fillStyle = '#fff';
      offContext.fillText(content, padding - left, padding + ascent);

      const pixels = offContext.getImageData(0, 0, offscreen.width, offscreen.height).data;
      const targets = [];
      const step = Math.max(1, Math.floor(density));
      const targetOffsetX = align === 'left' ? 1 - padding : align === 'right' ? width - offscreen.width - padding : width / 2 - offscreen.width / 2;
      for (let y = 0; y < offscreen.height; y += step) {
        for (let x = 0; x < offscreen.width; x += step) {
          const alpha = pixels[(y * offscreen.width + x) * 4 + 3];
          if (alpha > 40) targets.push({ x: targetOffsetX + x, y: height / 2 - offscreen.height / 2 + y, alpha: alpha / 255 });
        }
      }

      const maxParticles = Math.max(180, Math.min(5200, Math.floor((width * height) / 3)));
      const stride = Math.max(1, Math.ceil(targets.length / maxParticles));
      const base = hexToRgb(color);
      const highlight = hexToRgb(highlightColor);
      particles = targets.filter((_, index) => index % stride === 0).map((target, index) => {
        const seed = ((index * 9301 + 49297) % 233280) / 233280;
        const depth = 0.45 + ((index * 233 + 97) % 1000) / 1000 * 0.9;
        const angle = seed * Math.PI * 2;
        const distance = (reducedMotion ? 0 : scatter) * (0.35 + depth * 0.75);
        const blend = base && highlight ? clamp(target.x / Math.max(1, width) + (seed - 0.5) * 0.35, 0, 1) : 0;
        return {
          targetX: target.x, targetY: target.y,
          x: reducedMotion ? target.x : target.x + Math.cos(angle) * distance,
          y: reducedMotion ? target.y : target.y + Math.sin(angle) * distance,
          startX: target.x, startY: target.y,
          size: Math.max(0.6, particleSize * (0.75 + target.alpha * 0.45)),
          color: base && highlight ? mixColor(base, highlight, blend) : color,
          seed, depth, delay: seed * stagger,
        };
      });

      pointer.x = pointer.smoothX = width / 2;
      pointer.y = pointer.smoothY = height / 2;
      if (reducedMotion) {
        particles.forEach(particle => { particle.x = particle.targetX; particle.y = particle.targetY; });
        gathering = false;
      } else startGather(false);
      if (frame === null) frame = requestAnimationFrame(draw);
    };

    const queueSample = () => {
      if (resizeFrame) cancelAnimationFrame(resizeFrame);
      resizeFrame = requestAnimationFrame(sampleText);
    };
    const movePointer = event => {
      const rect = canvas.getBoundingClientRect();
      pointer.x = event.clientX - rect.left;
      pointer.y = event.clientY - rect.top;
      pointer.active = true;
    };
    const mediaQuery = window.matchMedia?.('(prefers-reduced-motion: reduce)');
    const onMotionChange = event => { reducedMotion = event.matches; sampleText(); };
    const observer = new ResizeObserver(queueSample);
    observer.observe(container);
    mediaQuery?.addEventListener('change', onMotionChange);
    canvas.addEventListener('pointerenter', event => { movePointer(event); if (trigger === 'hover') startGather(true); });
    canvas.addEventListener('pointermove', movePointer);
    canvas.addEventListener('pointerleave', () => { pointer.active = false; });
    canvas.addEventListener('click', () => { if (trigger === 'click') startGather(true); });
    sampleText();

    return () => {
      buildId += 1;
      observer.disconnect();
      mediaQuery?.removeEventListener('change', onMotionChange);
      if (frame) cancelAnimationFrame(frame);
      if (resizeFrame) cancelAnimationFrame(resizeFrame);
    };
  }, [text, particleSize, density, color, highlightColor, scatter, gatherDuration, stagger, pointerRepel, repelRadius, idleDrift, trigger, fontSize, fontWeight, fontFamily, glow, align]);

  return (
    <div ref={containerRef} className={`particle-text ${className}`} style={style} aria-label={text}>
      <canvas ref={canvasRef} className="particle-text__canvas" aria-hidden="true" style={{ opacity: particleOpacity }} />
      {showText && (
        <span
          className="particle-text__overlay"
          aria-hidden="true"
          style={{
            justifyContent: align === 'left' ? 'flex-start' : align === 'right' ? 'flex-end' : 'center',
            fontSize: typeof fontSize === 'number' ? `${fontSize}px` : fontSize,
            fontWeight,
            fontFamily,
            color: textColor,
          }}
        >
          {text}
        </span>
      )}
      <span className="particle-text__sr">{text}</span>
    </div>
  );
}
