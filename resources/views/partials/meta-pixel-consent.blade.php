@if(! request()->is('admin', 'admin/*') && tracking_meta_pixel_id() && tracking_meta_pixel_consent_required())
<style nonce="{{ csp_nonce() }}">
  #taqi-pixel-consent,
  #taqi-pixel-consent-preferences { font-family: inherit; }
  #taqi-pixel-consent[hidden],
  #taqi-pixel-consent-preferences[hidden] { display: none !important; }
  #taqi-pixel-consent {
    position: fixed; z-index: 9999; inset: auto 16px 16px; width: min(720px, calc(100% - 32px));
    margin-inline: auto; padding: 20px; border: 1px solid #e5e7eb; border-radius: 18px;
    background: #fff; color: #172033; box-shadow: 0 18px 55px rgba(15, 23, 42, .22);
  }
  #taqi-pixel-consent h2 { margin: 0; font-size: 18px; line-height: 1.35; font-weight: 750; }
  #taqi-pixel-consent p { margin: 8px 0 0; color: #5b6472; font-size: 14px; line-height: 1.55; }
  #taqi-pixel-consent a { color: #c34212; text-decoration: underline; text-underline-offset: 2px; }
  #taqi-pixel-consent .taqi-consent-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px; }
  #taqi-pixel-consent button,
  #taqi-pixel-consent-preferences {
    min-height: 42px; border: 1px solid #d8dde5; border-radius: 10px; padding: 9px 15px;
    background: #fff; color: #172033; font-size: 14px; font-weight: 650; cursor: pointer;
  }
  #taqi-pixel-consent button:hover,
  #taqi-pixel-consent-preferences:hover { background: #f8fafc; }
  #taqi-pixel-consent button[data-consent-accept] { border-color: #f15a24; background: #f15a24; color: #fff; }
  #taqi-pixel-consent button[data-consent-accept]:hover { background: #d94c1a; }
  #taqi-pixel-consent-preferences { position: fixed; z-index: 9998; left: 12px; bottom: 12px; min-height: 36px; padding: 6px 11px; font-size: 12px; box-shadow: 0 4px 16px rgba(15, 23, 42, .12); }
  @media (max-width: 600px) {
    #taqi-pixel-consent { inset: auto 0 0; width: 100%; padding: 18px 16px calc(18px + env(safe-area-inset-bottom)); border-radius: 18px 18px 0 0; }
    #taqi-pixel-consent .taqi-consent-actions { flex-direction: column; }
    #taqi-pixel-consent .taqi-consent-actions button { width: 100%; }
  }
</style>

<section id="taqi-pixel-consent" role="region" aria-labelledby="taqi-pixel-consent-title">
  <h2 id="taqi-pixel-consent-title">Choose your marketing tracking preference</h2>
  <p>TAQI LIFE uses the Meta Pixel to measure visits and improve advertising. It will load only if you allow marketing tracking. You can change your choice any time in Cookie settings. Read our <a href="{{ route('privacy') }}">Privacy Policy</a>.</p>
  <div class="taqi-consent-actions">
    <button type="button" data-consent-accept>Allow marketing</button>
    <button type="button" data-consent-reject>Reject optional tracking</button>
  </div>
</section>
<button type="button" id="taqi-pixel-consent-preferences" aria-controls="taqi-pixel-consent" hidden>Cookie settings</button>

<script nonce="{{ csp_nonce() }}">
(() => {
  const banner = document.getElementById('taqi-pixel-consent');
  const preferences = document.getElementById('taqi-pixel-consent-preferences');
  if (!banner || !preferences) return;

  const cookieName = 'taqi_marketing_consent';
  const readChoice = () => document.cookie.split(';').map(value => value.trim()).find(value => value.startsWith(cookieName + '='))?.slice(cookieName.length + 1) || '';
  const saveChoice = choice => {
    const secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${cookieName}=${choice}; Max-Age=15552000; Path=/; SameSite=Lax${secure}`;
    banner.hidden = true;
    preferences.hidden = false;
    window.__setMetaPixelConsent?.(choice === 'granted');
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
    window.__setMetaPixelConsent?.(savedChoice === 'granted');
  } else {
    banner.hidden = false;
    preferences.hidden = true;
    window.__setMetaPixelConsent?.(false);
  }

  banner.querySelector('[data-consent-accept]')?.addEventListener('click', () => saveChoice('granted'));
  banner.querySelector('[data-consent-reject]')?.addEventListener('click', () => saveChoice('denied'));
  preferences.addEventListener('click', openPreferences);
})();
</script>
@endif
