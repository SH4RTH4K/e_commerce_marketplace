@php
  $gtmId   = tracking_gtm_id();
  $ga4Id   = tracking_ga4_id();
  $pixelId = tracking_meta_pixel_id();
  $ga4Mode = setting('tracking_ga4_mode', 'direct');
  // Marketing tags belong on the storefront, never in the administrator UI.
  if (request()->is('admin', 'admin/*')) $gtmId = $ga4Id = $pixelId = null;
@endphp

{{-- Inject tracking config for JS (tracking.js reads window.__tracking) --}}
@if($gtmId || $ga4Id || $pixelId)
<script nonce="{{ csp_nonce() }}">
window.__tracking = {
  gtm:      @json($gtmId),
  ga4:      @json($ga4Id),
  ga4Mode:  @json($ga4Mode),
  pixel:    @json($pixelId),
  currency: @json(setting('currency_code', 'BDT')),
};
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

@if($pixelId)
<!-- Meta (Facebook) Pixel -->
<script nonce="{{ csp_nonce() }}">
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '{{ $pixelId }}');
fbq('track', 'PageView'); {{-- Initial page load; SPA navigations handled by tracking.js --}}
</script>
@endif

@stack('tracking-head')
