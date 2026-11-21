(() => {
  const init = () => {
    const banner = document.getElementById('taqi-pixel-consent');
    const preferences = document.getElementById('taqi-pixel-consent-preferences');

    if (!banner || !preferences || banner.dataset.consentInitialized === 'true') return;
    banner.dataset.consentInitialized = 'true';

    const cookieName = 'taqi_marketing_consent';
    const readChoice = () => document.cookie
      .split(';')
      .map(value => value.trim())
      .find(value => value.startsWith(`${cookieName}=`))
      ?.slice(cookieName.length + 1) || '';

    const applyChoice = choice => {
      const secure = location.protocol === 'https:' ? '; Secure' : '';
      document.cookie = `${cookieName}=${choice}; Max-Age=15552000; Path=/; SameSite=Lax${secure}`;

      // Hide the sheet before notifying the tracker. A tracker error must never
      // leave the customer stuck behind the consent prompt.
      banner.hidden = true;
      preferences.hidden = false;

      try {
        window.__setMetaPixelConsent?.(choice === 'granted', choice);
      } catch (error) {
        // Consent is still saved even if an optional analytics integration fails.
        console.warn('TAQI LIFE marketing consent update failed', error);
      }
    };

    const openPreferences = () => {
      banner.hidden = false;
      preferences.hidden = true;
      banner.querySelector('[data-consent-accept]')?.focus();
    };

    const savedChoice = readChoice();
    if (savedChoice === 'granted' || savedChoice === 'denied') {
      banner.hidden = true;
      preferences.hidden = false;
      try {
        window.__setMetaPixelConsent?.(savedChoice === 'granted', savedChoice);
      } catch (error) {
        console.warn('TAQI LIFE marketing consent restore failed', error);
      }
    } else {
      banner.hidden = false;
      preferences.hidden = true;
      try {
        window.__setMetaPixelConsent?.(false, 'pending');
      } catch (error) {
        console.warn('TAQI LIFE marketing consent initialization failed', error);
      }
    }

    banner.querySelector('[data-consent-accept]')?.addEventListener('click', () => applyChoice('granted'));
    banner.querySelector('[data-consent-reject]')?.addEventListener('click', () => applyChoice('denied'));
    preferences.addEventListener('click', openPreferences);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
