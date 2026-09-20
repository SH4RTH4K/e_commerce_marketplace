import { useEffect, useRef, useState } from 'react';
import { motion } from 'motion/react';

import './TrueFocus.css';

export default function TrueFocus({
  sentence = 'True Focus',
  separator = ' ',
  manualMode = false,
  blurAmount = 1,
  borderColor = '#f15a24',
  glowColor = 'rgb(241 90 36 / 0.45)',
  animationDuration = 0.7,
  pauseBetweenAnimations = 1.5,
  fontSize = '1rem',
  fontWeight = 900,
  fontFamily = 'inherit',
  textColor = '#0b1c21',
  className = '',
  style,
}) {
  const words = String(sentence).split(separator).filter(Boolean);
  const [currentIndex, setCurrentIndex] = useState(0);
  const [lastActiveIndex, setLastActiveIndex] = useState(null);
  const containerRef = useRef(null);
  const wordRefs = useRef([]);
  const [focusRect, setFocusRect] = useState({ x: 0, y: 0, width: 0, height: 0 });

  useEffect(() => {
    if (manualMode || words.length < 2) return undefined;
    const interval = setInterval(() => setCurrentIndex(index => (index + 1) % words.length), (animationDuration + pauseBetweenAnimations) * 1000);
    return () => clearInterval(interval);
  }, [manualMode, animationDuration, pauseBetweenAnimations, words.length]);

  useEffect(() => {
    const updateFocusRect = () => {
      const activeWord = wordRefs.current[currentIndex];
      const container = containerRef.current;
      if (!activeWord || !container || currentIndex < 0) return;
      const parent = container.getBoundingClientRect();
      const active = activeWord.getBoundingClientRect();
      setFocusRect({ x: active.left - parent.left, y: active.top - parent.top, width: active.width, height: active.height });
    };

    updateFocusRect();
    const observer = new ResizeObserver(updateFocusRect);
    if (containerRef.current) observer.observe(containerRef.current);
    return () => observer.disconnect();
  }, [currentIndex, words.length, sentence]);

  const focusWord = index => {
    if (!manualMode) return;
    setLastActiveIndex(index);
    setCurrentIndex(index);
  };

  return (
    <div
      ref={containerRef}
      className={`true-focus ${className}`}
      style={{ ...style, '--tf-border': borderColor, '--tf-glow': glowColor }}
      aria-label={sentence}
    >
      {words.map((word, index) => {
        const active = index === currentIndex;
        return (
          <span
            key={`${word}-${index}`}
            ref={element => { wordRefs.current[index] = element; }}
            className="true-focus__word"
            style={{
              filter: active ? 'blur(0px)' : `blur(${blurAmount}px)`,
              transitionDuration: `${animationDuration}s`,
              fontSize,
              fontWeight,
              fontFamily,
              color: textColor,
            }}
            onMouseEnter={() => focusWord(index)}
            onMouseLeave={() => manualMode && setCurrentIndex(lastActiveIndex)}
          >
            {word}
          </span>
        );
      })}
      <motion.div
        className="true-focus__frame"
        animate={{ x: focusRect.x, y: focusRect.y, width: focusRect.width, height: focusRect.height, opacity: currentIndex >= 0 ? 1 : 0 }}
        transition={{ duration: animationDuration, ease: 'easeInOut' }}
        aria-hidden="true"
      >
        <span className="true-focus__corner true-focus__corner--top-left" />
        <span className="true-focus__corner true-focus__corner--top-right" />
        <span className="true-focus__corner true-focus__corner--bottom-left" />
        <span className="true-focus__corner true-focus__corner--bottom-right" />
      </motion.div>
    </div>
  );
}
