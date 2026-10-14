<?php
  $gtmId   = tracking_gtm_id();
  $ga4Id   = tracking_ga4_id();
  $pixelId = tracking_meta_pixel_id();
?>


<?php if($gtmId || $ga4Id || $pixelId): ?>
<script>
window.__tracking = {
  gtm:      <?php echo json_encode($gtmId, 15, 512) ?>,
  ga4:      <?php echo json_encode($ga4Id, 15, 512) ?>,
  pixel:    <?php echo json_encode($pixelId, 15, 512) ?>,
  currency: <?php echo json_encode(setting('currency_code', 'BDT'), 512) ?>,
};
</script>
<?php endif; ?>

<?php if($gtmId): ?>
<!-- Google Tag Manager -->
<script>
(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});
var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';
j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','<?php echo e($gtmId); ?>');
</script>
<?php endif; ?>

<?php if($ga4Id): ?>
<!-- Google Analytics 4 -->

<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo e($ga4Id); ?>"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
<?php if (! ($gtmId)): ?>
gtag('config', '<?php echo e($ga4Id); ?>');
<?php endif; ?>
</script>
<?php endif; ?>

<?php if($pixelId): ?>
<!-- Meta (Facebook) Pixel -->
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '<?php echo e($pixelId); ?>');
fbq('track', 'PageView'); 
</script>
<?php endif; ?>

<?php echo $__env->yieldPushContent('tracking-head'); ?>
<?php /**PATH E:\Project\e_commerce_marketplace\resources\views/partials/tracking-head.blade.php ENDPATH**/ ?>