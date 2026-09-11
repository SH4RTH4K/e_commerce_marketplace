@php
  $gtmId   = tracking_gtm_id();
  $ga4Id   = tracking_ga4_id();
  $pixelId = tracking_meta_pixel_id();
@endphp

{{-- Inject tracking config for JS (tracking.js reads window.__tracking) --}}
@if($gtmId || $ga4Id || $pixelId)
<script>
window.__tracking = {
  gtm:      @json($gtmId),
  ga4:      @json($ga4Id),
  pixel:    @json($pixelId),
  currency: @json(setting('currency_code', 'BDT')),
};
</script>
@endif

@if($gtmId)
<!-- Google Tag Manager -->
<script>
(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});
var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';
j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','{{ $gtmId }}');
</script>
@endif

@if($ga4Id)
<!-- Google Analytics 4 -->
{{-- Load gtag.js always so window.gtag is available; but only call gtag('config')
     when GTM is NOT present — when GTM is active, GA4 config fires via GTM tag. --}}
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga4Id }}"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
@unless($gtmId)
gtag('config', '{{ $ga4Id }}');
@endunless
</script>
@endif

@if($pixelId)
<!-- Meta (Facebook) Pixel -->
<script>
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
