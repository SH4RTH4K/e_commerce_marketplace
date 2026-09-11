import { useState, useEffect } from 'react';
import { Link } from '@inertiajs/react';

export default function VisitorPopupModal({ popup }) {
  if (!popup || !popup.enabled) return null;

  const [isOpen, setIsOpen] = useState(false);
  const [animating, setAnimating] = useState(false);

  useEffect(() => {
    // Check frequency restriction
    const frequency = popup.frequency || 'once_per_session';
    const sessionSeen = sessionStorage.getItem('shopzy_popup_seen');
    const localSeenTime = localStorage.getItem('shopzy_popup_seen_time');

    if (frequency === 'once_per_session' && sessionSeen === '1') {
      return;
    }

    if (frequency === 'every_24h' && localSeenTime) {
      const hoursPassed = (Date.now() - parseInt(localSeenTime, 10)) / (1000 * 60 * 60);
      if (hoursPassed < 24) {
        return;
      }
    }

    // Set delay timer
    const delay = Math.max(0, parseInt(popup.delay_seconds ?? 3, 10)) * 1000;
    const timer = setTimeout(() => {
      setIsOpen(true);
      setAnimating(true);
    }, delay);

    return () => clearTimeout(timer);
  }, [popup]);

  const handleClose = () => {
    setAnimating(false);
    setTimeout(() => {
      setIsOpen(false);
    }, 200);

    // Record seen
    sessionStorage.setItem('shopzy_popup_seen', '1');
    localStorage.setItem('shopzy_popup_seen_time', String(Date.now()));
  };

  if (!isOpen) return null;

  const hasImage = !!popup.image;
  const hasTitle = !!popup.title;
  const hasText = !!popup.text;
  const hasButton = !!popup.btn_label;
  const destination = popup.link || '';
  const isExternal = destination.startsWith('http');

  return (
    <div
      role="dialog"
      aria-modal="true"
      className={`fixed inset-0 z-50 flex items-center justify-center p-4 transition-all duration-300 ${
        animating ? 'opacity-100' : 'opacity-0'
      }`}
    >
      {/* Backdrop */}
      <div
        onClick={handleClose}
        className="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity"
      />

      {/* Modal Box */}
      <div
        className={`relative z-10 w-full max-w-md bg-white rounded-3xl shadow-2xl overflow-hidden border border-gray-100 transform transition-all duration-300 ${
          animating ? 'scale-100 translate-y-0' : 'scale-95 translate-y-4'
        }`}
      >
        {/* Close Button (X) */}
        <button
          type="button"
          onClick={handleClose}
          className="absolute top-3 right-3 z-30 w-8 h-8 rounded-full bg-black/50 hover:bg-black/80 text-white flex items-center justify-center transition-all shadow-md backdrop-blur-xs focus:outline-none"
          aria-label="Close notification popup"
        >
          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>

        {/* Optional Image Banner */}
        {hasImage && (
          <div className="relative w-full bg-gray-50 max-h-56 overflow-hidden flex items-center justify-center border-b border-gray-100">
            {destination ? (
              isExternal ? (
                <a href={destination} target="_blank" rel="noopener noreferrer" onClick={handleClose} className="block w-full">
                  <img src={popup.image} alt={popup.title || 'Special Notice'} className="w-full h-auto object-cover max-h-56 hover:opacity-95 transition-opacity" />
                </a>
              ) : (
                <Link href={destination} onClick={handleClose} className="block w-full">
                  <img src={popup.image} alt={popup.title || 'Special Notice'} className="w-full h-auto object-cover max-h-56 hover:opacity-95 transition-opacity" />
                </Link>
              )
            ) : (
              <img src={popup.image} alt={popup.title || 'Special Notice'} className="w-full h-auto object-cover max-h-56" />
            )}
          </div>
        )}

        {/* Text Content */}
        <div className="p-6 text-center space-y-3">
          {hasTitle && (
            <h2 className="text-lg sm:text-xl font-black text-gray-900 leading-tight">
              {popup.title}
            </h2>
          )}

          {hasText && (
            <p className="text-xs sm:text-sm text-gray-600 leading-relaxed whitespace-pre-line">
              {popup.text}
            </p>
          )}

          {/* Action Button */}
          {hasButton && (
            <div className="pt-2">
              {destination ? (
                isExternal ? (
                  <a
                    href={destination}
                    target="_blank"
                    rel="noopener noreferrer"
                    onClick={handleClose}
                    className="inline-flex items-center justify-center w-full py-3 px-6 bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white font-bold text-sm rounded-2xl shadow-lg shadow-orange-500/25 transition-all transform active:scale-98"
                  >
                    {popup.btn_label}
                  </a>
                ) : (
                  <Link
                    href={destination}
                    onClick={handleClose}
                    className="inline-flex items-center justify-center w-full py-3 px-6 bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white font-bold text-sm rounded-2xl shadow-lg shadow-orange-500/25 transition-all transform active:scale-98"
                  >
                    {popup.btn_label}
                  </Link>
                )
              ) : (
                <button
                  type="button"
                  onClick={handleClose}
                  className="inline-flex items-center justify-center w-full py-3 px-6 bg-orange-500 hover:bg-orange-600 text-white font-bold text-sm rounded-2xl shadow-md transition-colors"
                >
                  {popup.btn_label}
                </button>
              )}
            </div>
          )}

          <button
            type="button"
            onClick={handleClose}
            className="text-[11px] font-medium text-gray-400 hover:text-gray-600 underline pt-1 transition-colors"
          >
            Don&apos;t show again
          </button>
        </div>

      </div>
    </div>
  );
}
