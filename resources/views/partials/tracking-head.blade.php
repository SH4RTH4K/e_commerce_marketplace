@php
  $gtmId   = tracking_gtm_id();
  $ga4Id   = tracking_ga4_id();
  $pixelId = tracking_meta_pixel_id();
  $hotjarId = tracking_hotjar_id();
  $ga4Mode = setting('tracking_ga4_mode', 'direct');
  $pixelConsentRequired = tracking_meta_pixel_consent_required();
  // Marketing tags belong on the storefront, never in the administrator UI.
  if (request()->is('admin', 'admin/*')) $gtmId = $ga4Id = $pixelId = $hotjarId = null;
@endphp

{{-- Inject tracking config for JS (tracking.js reads window.__tracking) --}}
@if($gtmId || $ga4Id || $pixelId || $hotjarId)
<script nonce="{{ csp_nonce() }}">
window.__tracking = {
  gtm:      @json($gtmId),
  ga4:      @json($ga4Id),
  ga4Mode:  @json($ga4Mode),
  pixel:    @json($pixelId),
  pixelConsentRequired: @json((bool) ($pixelId && $pixelConsentRequired)),
  pixelConsent: @json((bool) ($pixelId && ! $pixelConsentRequired)),
  hotjar:   @json($hotjarId),
  currency: @json(setting('currency_code', 'BDT')),
};
@if($pixelId)
window.__loadMetaPixel = function () {
  var config = window.__tracking || {};
  if (!config.pixel || (config.pixelConsentRequired && config.pixelConsent !== true) || window.__metaPixelInitialized) return;
  window.__metaPixelInitialized = true;
  if (typeof window.fbq !== 'function') {
    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
    n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
  }
  window.fbq('consent', 'grant');
  window.fbq('init', config.pixel);
  window.fbq('track', 'PageView');
};
window.__setMetaPixelConsent = function (granted) {
  var config = window.__tracking || {};
  var wasGranted = config.pixelConsent === true;
  config.pixelConsent = Boolean(granted);
  if (granted) {
    if (window.__metaPixelInitialized && typeof window.fbq === 'function') {
      if (!wasGranted) {
        window.fbq('consent', 'grant');
        window.fbq('track', 'PageView');
      }
    } else if (typeof window.__loadMetaPixel === 'function') {
      window.__loadMetaPixel();
    }
  } else if (typeof window.fbq === 'function') {
    window.fbq('consent', 'revoke');
  }
};
@if(! $pixelConsentRequired)
window.__loadMetaPixel();
@endif
@endif
</script>
@endif

@if($gtmId)
<!-- Google Tag Manager -->
<script nonce="{{ csp_nonce() }}">
(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});
var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';
j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','{{ $gtmId }}');
</script>
@endif

@if($ga4Id && ($ga4Mode !== 'gtm' || !$gtmId))
<!-- Google Analytics 4 -->
<script async nonce="{{ csp_nonce() }}" src="https://www.googletagmanager.com/gtag/js?id={{ $ga4Id }}"></script>
<script nonce="{{ csp_nonce() }}">
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '{{ $ga4Id }}', { send_page_view: false });
</script>
@endif

@stack('tracking-head')
