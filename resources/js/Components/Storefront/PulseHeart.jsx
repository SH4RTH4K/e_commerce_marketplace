import { useEffect, useLayoutEffect, useRef, useState } from 'react';

import './PulseHeart.css';

const OUT = 0.4;
const PATHS = {
  heart: 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
  star: 'M11.48 3.499a.6.6 0 011.04 0l2.1 4.26 4.702.684a.6.6 0 01.333 1.024l-3.4 3.313.803 4.683a.6.6 0 01-.87.632L12 15.888l-4.204 2.207a.6.6 0 01-.87-.632l.803-4.683-3.4-3.313a.6.6 0 01.333-1.024l4.702-.684 2.116-4.26z',
  thumb: 'M7 10v10M3 10h4v10H3zM7 20h9.5a2 2 0 001.938-1.507l1.5-6A2 2 0 0016.998 10H13l.692-3.46A2.25 2.25 0 0011.486 4L7 10z',
};

const back = (k, c) => {
  const u = k - 1;
  return 1 + (c + 1) * u ** 3 + c * u ** 2;
};
const swellOf = (t, c) => (t <= 0 ? 0 : t < OUT ? 1 - (1 - t / OUT) ** 3 : 1 - back((t - OUT) / (1 - OUT), c));
const format = n => new Intl.NumberFormat().format(n);
const reducedMotion = () => typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

export default function PulseHeart({
  liked: likedProp,
  defaultLiked = false,
  count = 0,
  onChange,
  showCount = true,
  icon = 'heart',
  idleOutline = true,
  size = 40,
  corner = 32,
  likedColor = '#ff4d6d',
  idleColor = '#8b8b93',
  pillColor = '#232326',
  textColor = '#f5f5f5',
  duration = 560,
  dotSize = 0.3,
  overshoot = 1.7,
  beat = 3,
  rollDuration = 350,
  disabled = false,
  label = 'Like',
  className = '',
}) {
  const controlled = likedProp !== undefined;
  const [inner, setInner] = useState(defaultLiked);
  const [total, setTotal] = useState(count);
  const liked = controlled ? likedProp : inner;
  const [shown, setShown] = useState({ liked, count });
  const [roll, setRoll] = useState(null);

  const rootRef = useRef(null);
  const pillRef = useRef(null);
  const heartRef = useRef(null);
  const glyphRef = useRef(null);
  const rollRef = useRef(null);
  const raf = useRef(0);
  const rollTimer = useRef(0);
  const viaPointer = useRef(false);
  const shownRef = useRef(shown);
  const logical = useRef({ liked, count: total });
  const cfg = useRef({ duration, dotSize, overshoot, beat, rollDuration });

  logical.current = { liked, count: total };
  cfg.current = { duration, dotSize, overshoot, beat, rollDuration };

  useEffect(() => setTotal(count), [count]);

  useEffect(() => {
    if (raf.current || (shownRef.current.liked === liked && shownRef.current.count === total)) return;
    shownRef.current = { liked, count: total };
    setShown(shownRef.current);
  }, [liked, total]);

  useLayoutEffect(() => {
    const root = rootRef.current;
    if (!root || root.dataset.instant === undefined) return;
    root.getBoundingClientRect();
    delete root.dataset.instant;
  }, [shown]);

  useLayoutEffect(() => {
    const element = rollRef.current;
    if (!element || !roll) return;
    element.style.transition = 'none';
    element.style.transform = `translateY(${roll.up ? '0' : '-1em'})`;
    element.getBoundingClientRect();
    element.style.transition = '';
    element.style.transform = `translateY(${roll.up ? '-1em' : '0'})`;
  }, [roll]);

  useEffect(() => () => {
    cancelAnimationFrame(raf.current);
    clearTimeout(rollTimer.current);
  }, []);

  const startRoll = (from, to) => {
    if (from === to) return;
    const a = format(from);
    const b = format(to);
    const changed = a.length === b.length ? [...b].flatMap((character, index) => (character !== a[index] ? [index] : [])) : [];
    setRoll({ a, b, at: changed.length === 1 ? changed[0] : -1, up: to > from });
    clearTimeout(rollTimer.current);
    rollTimer.current = setTimeout(() => setRoll(null), cfg.current.rollDuration);
  };

  const run = (nextLiked, nextCount) => {
    const root = rootRef.current;
    const heart = heartRef.current;
    const pill = pillRef.current;
    if (!root || !heart || !pill) return;

    const glyph = glyphRef.current;
    root.dataset.running = '';
    let swapped = false;
    let previous = 0;
    const startedAt = performance.now();
    const tick = now => {
      const { duration: animationDuration, dotSize: dot, overshoot: easing, beat: pulse } = cfg.current;
      const progress = Math.min(1, (now - startedAt) / animationDuration);
      const step = previous ? now - previous : 1000 / 60;
      previous = now;
      const swell = swellOf(progress, easing);
      const iconScale = 1 - (1 - dot) * swell;

      if (glyph) glyph.setAttribute('transform', `translate(12 12) scale(${iconScale}) translate(-12 -12)`);
      else heart.style.transform = `scale(${iconScale})`;
      pill.style.transform = `scale(${1 - (pulse / 100) * swell})`;

      if (!swapped && progress + step / 2 / animationDuration >= OUT) {
        swapped = true;
        root.dataset.liked = String(nextLiked);
        startRoll(shownRef.current.count, nextCount);
        shownRef.current = { liked: nextLiked, count: nextCount };
        setShown(shownRef.current);
      }

      if (progress < 1) {
        raf.current = requestAnimationFrame(tick);
        return;
      }

      raf.current = 0;
      if (glyph) glyph.removeAttribute('transform');
      heart.style.transform = '';
      pill.style.transform = '';
      delete root.dataset.running;
      const latest = logical.current;
      if (latest.liked !== shownRef.current.liked || latest.count !== shownRef.current.count) {
        shownRef.current = latest;
        setShown(latest);
      }
    };
    raf.current = requestAnimationFrame(tick);
  };

  const handlePointerDown = event => {
    if (event.button !== 0 || disabled) return;
    viaPointer.current = true;
    if (!reducedMotion() && rootRef.current) rootRef.current.dataset.pressed = '';
  };
  const releasePointer = () => rootRef.current && delete rootRef.current.dataset.pressed;
  const handleClick = event => {
    if (disabled || raf.current) return;
    const pointer = viaPointer.current && event.detail !== 0;
    viaPointer.current = false;
    const nextLiked = !liked;
    const nextCount = Math.max(0, total + (nextLiked ? 1 : -1));
    if (!controlled) setInner(nextLiked);
    setTotal(nextCount);
    onChange?.(nextLiked, nextCount);
    if (pointer && !reducedMotion()) run(nextLiked, nextCount);
    else if (rootRef.current) rootRef.current.dataset.instant = '';
  };

  const path = typeof icon === 'string' ? PATHS[icon] || PATHS.heart : null;
  const text = format(shown.count);
  const cells = roll
    ? roll.at === -1
      ? [{ top: roll.up ? roll.a : roll.b, bottom: roll.up ? roll.b : roll.a }]
      : [...roll.b].map((character, index) => (index === roll.at
        ? { top: roll.up ? roll.a[index] : character, bottom: roll.up ? character : roll.a[index] }
        : { character }))
    : [...text].map(character => ({ character }));

  return (
    <button
      ref={rootRef}
      type="button"
      aria-label={label}
      aria-pressed={liked}
      disabled={disabled}
      data-liked={String(shown.liked)}
      data-solid={idleOutline ? undefined : ''}
      data-no-count={showCount ? undefined : ''}
      className={`pulse-heart${className ? ` ${className}` : ''}`}
      style={{
        '--ph-size': `${size}px`, '--ph-corner': `${corner}px`, '--ph-pill': pillColor,
        '--ph-idle': idleColor, '--ph-liked': likedColor, '--ph-text': textColor,
        '--ph-roll': `${rollDuration}ms`, '--ph-stroke': `${(1.5 * size) / 24}px`,
      }}
      onPointerDown={handlePointerDown}
      onPointerUp={releasePointer}
      onPointerLeave={releasePointer}
      onPointerCancel={() => { viaPointer.current = false; releasePointer(); }}
      onKeyDown={() => { viaPointer.current = false; }}
      onClick={handleClick}
    >
      <span ref={pillRef} className="pulse-heart__pill">
        <span ref={heartRef} className="pulse-heart__heart" aria-hidden="true">
          {path ? <svg viewBox="0 0 24 24"><g ref={glyphRef}><path d={path} vectorEffect="non-scaling-stroke" /></g></svg> : icon}
        </span>
        {showCount && (
          <span className="pulse-heart__count" aria-hidden="true">
            {cells.map((cell, index) => ('character' in cell ? <span key={`character-${index}`}>{cell.character}</span> : (
              <span key={`roll-${index}`} className="pulse-heart__slot">
                <span ref={rollRef} className="pulse-heart__roll"><span>{cell.top}</span><span>{cell.bottom}</span></span>
              </span>
            )))}
          </span>
        )}
        <span className="pulse-heart__sr">{showCount ? `${label}, ${format(total)}` : label}</span>
      </span>
    </button>
  );
}
