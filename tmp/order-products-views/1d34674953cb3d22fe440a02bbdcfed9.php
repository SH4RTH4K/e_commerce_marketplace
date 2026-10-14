<?php if($gtmId = tracking_gtm_id()): ?>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo e($gtmId); ?>" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<?php endif; ?>

<?php if($pixelId = tracking_meta_pixel_id()): ?>
<noscript><img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id=<?php echo e($pixelId); ?>&amp;ev=PageView&amp;noscript=1" /></noscript>
<?php endif; ?>
<?php /**PATH E:\Project\e_commerce_marketplace\resources\views/partials/tracking-body.blade.php ENDPATH**/ ?>