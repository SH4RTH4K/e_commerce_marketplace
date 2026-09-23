<?php

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\ContactFormFieldController as AdminContactFormFieldController;
use App\Http\Controllers\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\InventoryController as AdminInventoryController;
use App\Http\Controllers\Admin\MediaController as AdminMediaController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FeatureController as AdminFeatureController;
use App\Http\Controllers\Admin\FlashSaleController as AdminFlashSaleController;
use App\Http\Controllers\Admin\LandingPageController as AdminLandingPageController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ProductCtaController;
use App\Http\Controllers\Admin\AdminPopupController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\ApplicationUpdateController;
use App\Http\Controllers\Admin\BlockedDeviceController;
use App\Http\Controllers\Admin\BlockedPhoneController;
use App\Http\Controllers\Admin\CourierController;
use App\Http\Controllers\Admin\FakeOrderGuardController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AbandonedCheckoutController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TrackOrderController;
use App\Http\Controllers\QuickOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront
|--------------------------------------------------------------------------
*/
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/home/products/load-more', [HomeController::class, 'loadMore'])->name('home.products.load-more');
Route::get('/lp/{slug}', [LandingPageController::class, 'show'])->name('landing.show');
Route::get('/campaign/{slug}', [LandingPageController::class, 'show'])->name('campaign.show');
Route::post('/lp/{slug}/order', [LandingPageController::class, 'order'])->middleware('throttle:60,1')->name('landing.order');
Route::post('/campaign/{slug}/order', [LandingPageController::class, 'order'])->middleware('throttle:60,1');
Route::get('/shop', [ShopController::class, 'index'])->name('shop');
Route::get('/category/{category}', [ShopController::class, 'index'])->name('shop.category');
Route::get('/product/{product}', [ProductController::class, 'show'])->name('product.show');
Route::get('/home-02', [HomeController::class, 'index'])->name('home.two');
Route::get('/home-03', [HomeController::class, 'index'])->name('home.three');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/blog', fn () => \Inertia\Inertia::render('Storefront/Blog'))->name('blog');
Route::get('/blog/{slug}', fn (string $slug) => \Inertia\Inertia::render('Storefront/BlogDetail', compact('slug')))->name('blog.detail');
Route::post('/product/{product}/reviews', [\App\Http\Controllers\ReviewController::class, 'store'])
    ->middleware('auth')
    ->name('product.reviews.store');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/add', [CartController::class, 'add'])->middleware('throttle:120,1')->name('cart.add');
Route::post('/cart/buy-now', [CartController::class, 'buyNow'])->middleware('throttle:120,1')->name('cart.buy-now');
Route::post('/cart/update', [CartController::class, 'update'])->middleware('throttle:120,1')->name('cart.update');
Route::post('/cart/remove', [CartController::class, 'remove'])->middleware('throttle:120,1')->name('cart.remove');
Route::post('/cart/remove-many', [CartController::class, 'removeMany'])->middleware('throttle:120,1')->name('cart.remove-many');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:60,1')->name('checkout.store');
Route::post('/quick-order', [QuickOrderController::class, 'store'])->middleware('throttle:60,1')->name('quick-order.store');
Route::post('/checkout/coupon', [CheckoutController::class, 'applyCoupon'])->middleware('throttle:60,1')->name('checkout.coupon.apply');
Route::post('/checkout/coupon/remove', [CheckoutController::class, 'removeCoupon'])->middleware('throttle:60,1')->name('checkout.coupon.remove');
Route::get('/order/{order}', [CheckoutController::class, 'confirmation'])->name('order.confirmation');
Route::get('/order/{order}/invoice', [\App\Http\Controllers\Admin\AdminInvoiceController::class, 'customerInvoice'])->name('order.invoice');

// Order tracking (public)
Route::get('/track', [TrackOrderController::class, 'show'])->name('track');
Route::post('/track', [TrackOrderController::class, 'find'])->middleware('throttle:60,1')->name('track.find');

// Abandoned Checkout Tracking
Route::post('/checkout/ping', [AbandonedCheckoutController::class, 'ping'])->middleware('throttle:120,1');

// Wishlist
Route::get('/wishlist', function () {
    return \Inertia\Inertia::render('Storefront/Wishlist');
})->name('wishlist');

// Legal & pages
Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/refund-policy', [PageController::class, 'refund'])->name('refund');
Route::get('/return-policy', [PageController::class, 'refund'])->name('return-policy');
Route::get('/shipping', [PageController::class, 'shipping'])->name('shipping');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:30,1')->name('contact.store');
Route::get('/page/{slug}', [PageController::class, 'show'])->name('page');

// SEO
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

/*
|--------------------------------------------------------------------------
| Customer authentication & account
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:30,1')->name('login.store');
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:30,1')->name('register.store');
    Route::get('/forgot-password', [PasswordResetController::class, 'showRequest'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendCode'])->middleware('throttle:15,1')->name('password.email');
    Route::get('/reset-password', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:15,1')->name('password.update');
});

// Email OTP verification (accessible mid-flow)
Route::get('/verify', [OtpController::class, 'show'])->name('verify');
Route::post('/verify', [OtpController::class, 'verify'])->middleware('throttle:30,1')->name('verify.store');
Route::post('/verify/resend', [OtpController::class, 'resend'])->middleware('throttle:15,1')->name('verify.resend');

Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/account', [AccountController::class, 'index'])->name('account');
    Route::get('/account/orders/{order}', [AccountController::class, 'showOrder'])->name('account.orders.show');
    Route::put('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
});

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
    // Guest (login)
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:30,1')->name('login.attempt');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    // Protected (testing.readonly blocks save/delete/verify while TESTING_MODE=true)
    Route::middleware(['admin', 'testing.readonly'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('analytics', [\App\Http\Controllers\Admin\AnalyticsController::class, 'index'])->name('analytics.index');

        // Inventory
        Route::get('inventory', [AdminInventoryController::class, 'index'])->name('inventory.index');
        Route::patch('inventory/{product:id}/stock', [AdminInventoryController::class, 'updateStock'])->name('inventory.stock');

        // Dropshipping integration (all supplier work is queued)
        Route::middleware(['dropshipping.enabled', 'permission:dropshipping.manage'])
            ->prefix('dropshipping')->name('dropshipping.')
            ->group(function () {
                 Route::get('/', [\App\Http\Controllers\Admin\DropshippingController::class, 'index'])->name('index');
                 // Ordered legacy-style submenu destinations. Dedicated screens
                 // can replace these aliases without changing the navigation.
                 Route::get('products', [\App\Http\Controllers\Admin\DropshippingController::class, 'supplierProducts'])->name('products');
                 Route::get('imported', [\App\Http\Controllers\Admin\DropshippingController::class, 'importedProducts'])->name('imported');
                 Route::get('media', [\App\Http\Controllers\Admin\DropshippingController::class, 'mediaCleanup'])->name('media');
                 Route::get('storage', [\App\Http\Controllers\Admin\DropshippingController::class, 'storageUsage'])->name('storage');
                 Route::get('migrations', [\App\Http\Controllers\Admin\DropshippingController::class, 'databaseMigration'])->name('migrations');
                 Route::get('categories', [\App\Http\Controllers\Admin\DropshippingController::class, 'categoryMapping'])->name('categories');
                 Route::post('categories/{supplierCategory}/map', [\App\Http\Controllers\Admin\DropshippingController::class, 'mapCategory'])->name('categories.map');
                 Route::post('categories/{supplierCategory}/image', [\App\Http\Controllers\Admin\DropshippingController::class, 'uploadCategoryImage'])->name('categories.image');
                 Route::get('variations', [\App\Http\Controllers\Admin\DropshippingController::class, 'variationMapping'])->name('variations');
                 Route::post('variations/auto-map', [\App\Http\Controllers\Admin\DropshippingController::class, 'autoMapVariations'])->name('variations.auto-map');
                 Route::post('variations/{variant}/map', [\App\Http\Controllers\Admin\DropshippingController::class, 'mapVariation'])->name('variations.map');
                 Route::get('pricing', [\App\Http\Controllers\Admin\DropshippingController::class, 'pricingRules'])->name('pricing');
                 Route::patch('suppliers/{supplier}/pricing', [\App\Http\Controllers\Admin\DropshippingController::class, 'updatePricing'])->name('suppliers.pricing');
                 Route::patch('suppliers/{supplier}/price-mapping', [\App\Http\Controllers\Admin\DropshippingController::class, 'updatePriceMapping'])->name('suppliers.price-mapping');
                 Route::post('suppliers/{supplier}/pricing/preview', [\App\Http\Controllers\Admin\DropshippingController::class, 'previewPricing'])->name('suppliers.pricing.preview');
                 Route::get('settings', [\App\Http\Controllers\Admin\DropshippingController::class, 'apiSettings'])->name('settings');
                 Route::post('driver-profiles', [\App\Http\Controllers\Admin\DropshippingController::class, 'storeDriverProfile'])->name('driver-profiles.store');
                 Route::patch('driver-profiles/{profile}', [\App\Http\Controllers\Admin\DropshippingController::class, 'updateDriverProfile'])->name('driver-profiles.update');
                 Route::patch('driver-profiles/{profile}/toggle', [\App\Http\Controllers\Admin\DropshippingController::class, 'toggleDriverProfile'])->name('driver-profiles.toggle');
                 Route::post('suppliers', [\App\Http\Controllers\Admin\DropshippingController::class, 'store'])->name('suppliers.store');
                 Route::post('products/{supplierProduct}/import', [\App\Http\Controllers\Admin\DropshippingController::class, 'importSupplierProduct'])->name('products.import');
                 Route::post('products/bulk-import', [\App\Http\Controllers\Admin\DropshippingController::class, 'bulkImportSupplierProducts'])->name('products.bulk-import');
                 Route::patch('imported/{product}/publish', [\App\Http\Controllers\Admin\DropshippingController::class, 'publishImportedProduct'])->name('imported.publish');
                 Route::post('imported/bulk-publish', [\App\Http\Controllers\Admin\DropshippingController::class, 'bulkPublishImportedProducts'])->name('imported.bulk-publish');
                 Route::post('imported/bulk-unpublish', [\App\Http\Controllers\Admin\DropshippingController::class, 'bulkUnpublishImportedProducts'])->name('imported.bulk-unpublish');
                 Route::post('imported/bulk-sync', [\App\Http\Controllers\Admin\DropshippingController::class, 'bulkSyncImportedProducts'])->name('imported.bulk-sync');
                 Route::post('imported/bulk-sync-filtered', [\App\Http\Controllers\Admin\DropshippingController::class, 'bulkSyncFilteredImportedProducts'])->name('imported.bulk-sync-filtered');
                Route::post('suppliers/{supplier}/test', [\App\Http\Controllers\Admin\DropshippingController::class, 'testConnection'])->name('suppliers.test');
                Route::post('suppliers/{supplier}/catalog', [\App\Http\Controllers\Admin\DropshippingController::class, 'catalog'])->name('suppliers.catalog');
                Route::post('suppliers/{supplier}/catalog/work', [\App\Http\Controllers\Admin\DropshippingController::class, 'workCatalogQueue'])->name('suppliers.catalog.work');
                Route::get('suppliers/{supplier}/catalog/progress', [\App\Http\Controllers\Admin\DropshippingController::class, 'catalogProgress'])->name('suppliers.catalog.progress');
                Route::post('suppliers/{supplier}/price-stock', [\App\Http\Controllers\Admin\DropshippingController::class, 'priceStock'])->name('suppliers.price-stock');
                Route::patch('suppliers/{supplier}', [\App\Http\Controllers\Admin\DropshippingController::class, 'update'])->name('suppliers.update');
                Route::patch('suppliers/{supplier}/toggle', [\App\Http\Controllers\Admin\DropshippingController::class, 'toggle'])->name('suppliers.toggle');
                Route::get('suppliers/{supplier}/active-run', [\App\Http\Controllers\Admin\DropshippingController::class, 'activeRun'])->name('suppliers.active-run');
                Route::post('runs/{run}/cancel', [\App\Http\Controllers\Admin\DropshippingController::class, 'cancelRun'])->name('runs.cancel');
                Route::post('runs/{run}/work-imported', [\App\Http\Controllers\Admin\DropshippingController::class, 'workImportedProductQueue'])->name('runs.work-imported');
                Route::post('runs/{run}/retry-imported', [\App\Http\Controllers\Admin\DropshippingController::class, 'retryFailedImportedProductSync'])->name('runs.retry-imported');
                Route::post('runs/{run}/pause-catalog', [\App\Http\Controllers\Admin\DropshippingController::class, 'pauseCatalogRun'])->name('runs.pause-catalog');
                Route::post('runs/{run}/resume-catalog', [\App\Http\Controllers\Admin\DropshippingController::class, 'resumeCatalogRun'])->name('runs.resume-catalog');
            });

        // Media
        Route::get('media', [AdminMediaController::class, 'index'])->name('media.index');
        Route::post('media', [AdminMediaController::class, 'store'])->name('media.store');
        Route::post('media/banners', [AdminMediaController::class, 'createBanners'])->name('media.banners.store');
        Route::delete('media/{image}', [AdminMediaController::class, 'destroy'])->name('media.destroy');

        // Orders + verification workflow
        Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/create', [AdminOrderController::class, 'create'])->name('orders.create');
        Route::get('orders/invoices/bulk', [\App\Http\Controllers\Admin\AdminInvoiceController::class, 'bulk'])->name('orders.invoices.bulk');
        Route::get('orders/product-search', [AdminOrderController::class, 'productSearch'])->name('orders.productSearch');
        Route::post('orders', [AdminOrderController::class, 'store'])->name('orders.store');
        Route::post('orders/bulk', [AdminOrderController::class, 'bulk'])->name('orders.bulk');
        Route::post('orders/check-fraud', [AdminOrderController::class, 'checkFraud'])->name('orders.check-fraud');
        Route::get('orders/customer-history', [AdminOrderController::class, 'customerHistory'])->name('orders.customer-history');
        Route::get('orders/{order:id}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::get('orders/{order:id}/invoice', [\App\Http\Controllers\Admin\AdminInvoiceController::class, 'show'])->name('orders.invoice');
        Route::get('orders/{order:id}/invoice/pdf', [\App\Http\Controllers\Admin\AdminInvoiceController::class, 'pdf'])->name('orders.invoice.pdf');
        Route::patch('orders/{order:id}', [AdminOrderController::class, 'update'])->name('orders.update');

        // Courier integration
        Route::post('orders/{order:id}/send-to-courier', [CourierController::class, 'sendToCourier'])->name('orders.send-to-courier');

        // Abandoned Checkouts
        Route::get('/abandoned-checkouts', [\App\Http\Controllers\Admin\AbandonedCheckoutController::class, 'index'])->name('abandoned-checkouts.index');
        Route::delete('/abandoned-checkouts/{id}', [\App\Http\Controllers\Admin\AbandonedCheckoutController::class, 'destroy'])->name('abandoned-checkouts.destroy');
        Route::patch('/abandoned-checkouts/{id}/recover', [\App\Http\Controllers\Admin\AbandonedCheckoutController::class, 'recover'])->name('abandoned-checkouts.recover');
        Route::post('/abandoned-checkouts/{id}/create-order', [\App\Http\Controllers\Admin\AbandonedCheckoutController::class, 'createOrder'])->name('abandoned-checkouts.create-order');
        Route::patch('/abandoned-checkouts/{id}/note', [\App\Http\Controllers\Admin\AbandonedCheckoutController::class, 'updateNote'])->name('abandoned-checkouts.note');
        Route::post('orders/{order:id}/verify', [AdminOrderController::class, 'verify'])->name('orders.verify');
        Route::post('orders/{order:id}/reject', [AdminOrderController::class, 'reject'])->name('orders.reject');
        Route::delete('orders/{order:id}', [AdminOrderController::class, 'destroy'])->name('orders.destroy');

        // Catalog — explicit {model:id} binding so admin uses numeric id, not slug
        Route::delete('products/{product:id}/images/{image}', [AdminProductController::class, 'destroyImage'])->name('products.images.destroy');
        Route::patch('products/{product:id}/toggle', [AdminProductController::class, 'toggle'])->name('products.toggle');
        Route::post('products/bulk', [AdminProductController::class, 'bulk'])->name('products.bulk');
        Route::get('products', [AdminProductController::class, 'index'])->name('products.index');
        Route::get('products/create', [AdminProductController::class, 'create'])->name('products.create');
        Route::post('products', [AdminProductController::class, 'store'])->name('products.store');
        Route::get('products/{product:id}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
        Route::put('products/{product:id}', [AdminProductController::class, 'update'])->name('products.update');
        Route::patch('products/{product:id}', [AdminProductController::class, 'update']);
        Route::delete('products/{product:id}', [AdminProductController::class, 'destroy'])->name('products.destroy');
        Route::get('categories/ordering', [AdminCategoryController::class, 'ordering'])->name('categories.ordering');
        Route::post('categories/ordering', [AdminCategoryController::class, 'saveOrdering'])->name('categories.save-ordering');
        Route::post('categories/bulk', [AdminCategoryController::class, 'bulk'])->name('categories.bulk');
        Route::get('categories', [AdminCategoryController::class, 'index'])->name('categories.index');
        Route::get('categories/create', [AdminCategoryController::class, 'create'])->name('categories.create');
        Route::post('categories', [AdminCategoryController::class, 'store'])->name('categories.store');
        Route::get('categories/{category:id}/edit', [AdminCategoryController::class, 'edit'])->name('categories.edit');
        Route::put('categories/{category:id}', [AdminCategoryController::class, 'update'])->name('categories.update');
        Route::patch('categories/{category:id}', [AdminCategoryController::class, 'update']);
        Route::delete('categories/{category:id}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');
        Route::patch('banners/{banner}/toggle', [AdminBannerController::class, 'toggle'])->name('banners.toggle');
        Route::patch('banners/bulk-status', [AdminBannerController::class, 'bulkStatus'])->name('banners.bulk-status');
        Route::patch('banners/bulk-position', [AdminBannerController::class, 'bulkPosition'])->name('banners.bulk-position');
        Route::delete('banners/bulk-delete', [AdminBannerController::class, 'bulkDelete'])->name('banners.bulk-delete');
        Route::resource('banners', AdminBannerController::class)->except('show');
        Route::post('features/bulk', [AdminFeatureController::class, 'bulk'])->name('features.bulk');
        Route::resource('features', AdminFeatureController::class)->except('show');
        Route::post('coupons/bulk', [AdminCouponController::class, 'bulk'])->name('coupons.bulk');
        Route::resource('coupons', AdminCouponController::class)->except('show');

        // Popup Notification
        Route::get('popup-notification', [AdminPopupController::class, 'index'])->name('popup-notification.index');
        Route::post('popup-notification', [AdminPopupController::class, 'update'])->name('popup-notification.update');

        // Landing pages
        Route::get('landing-pages/designs/{design}/preview', [AdminLandingPageController::class, 'previewDesign'])->name('landing-pages.designs.preview');
        Route::patch('landing-pages/designs/{design}/toggle', [AdminLandingPageController::class, 'toggleDesign'])->name('landing-pages.designs.toggle');
        Route::patch('landing-pages/{landing_page}/toggle', [AdminLandingPageController::class, 'toggle'])->name('landing-pages.toggle');
        Route::match(['put', 'post'], 'landing-pages/{landing_page}/sections/{section}', [AdminLandingPageController::class, 'updateSection'])->name('landing-pages.sections.update');
        Route::resource('landing-pages', AdminLandingPageController::class)->except('show');

        // Flash sale
        Route::get('flash-sale', [AdminFlashSaleController::class, 'index'])->name('flash-sale.index');
        Route::put('flash-sale/ends-at', [AdminFlashSaleController::class, 'updateEndsAt'])->name('flash-sale.ends-at');
        Route::put('flash-sale/reorder', [AdminFlashSaleController::class, 'reorder'])->name('flash-sale.reorder');
        Route::post('flash-sale/bulk', [AdminFlashSaleController::class, 'bulk'])->name('flash-sale.bulk');
        Route::post('flash-sale/{product:id}', [AdminFlashSaleController::class, 'add'])->name('flash-sale.add');
        Route::delete('flash-sale/{product:id}', [AdminFlashSaleController::class, 'remove'])->name('flash-sale.remove');

        // Reviews
        Route::get('reviews', [AdminReviewController::class, 'index'])->name('reviews.index');
        Route::post('reviews/bulk', [AdminReviewController::class, 'bulk'])->name('reviews.bulk');
        Route::post('reviews/{review}/approve', [AdminReviewController::class, 'approve'])->name('reviews.approve');
        Route::post('reviews/{review}/reject', [AdminReviewController::class, 'reject'])->name('reviews.reject');
        Route::delete('reviews/{review}', [AdminReviewController::class, 'destroy'])->name('reviews.destroy');

        // Contact messages + form fields
        Route::get('messages', [AdminContactMessageController::class, 'index'])->name('contact-messages.index');
        Route::post('messages/bulk', [AdminContactMessageController::class, 'bulk'])->name('contact-messages.bulk');
        Route::get('messages/{contact_message}', [AdminContactMessageController::class, 'show'])->name('contact-messages.show');
        Route::post('messages/{contact_message}/read', [AdminContactMessageController::class, 'markRead'])->name('contact-messages.read');
        Route::post('messages/{contact_message}/archive', [AdminContactMessageController::class, 'archive'])->name('contact-messages.archive');
        Route::delete('messages/{contact_message}', [AdminContactMessageController::class, 'destroy'])->name('contact-messages.destroy');
        Route::patch('contact-fields/{contact_field}/toggle', [AdminContactFormFieldController::class, 'toggle'])->name('contact-fields.toggle');
        Route::resource('contact-fields', AdminContactFormFieldController::class)->except('show');


        // People
        Route::get('customers', [AdminCustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}/edit', [AdminCustomerController::class, 'edit'])->name('customers.edit');
        Route::put('customers/{customer}', [AdminCustomerController::class, 'update'])->name('customers.update');
        Route::patch('customers/{customer}/toggle', [AdminCustomerController::class, 'toggle'])->name('customers.toggle');
        Route::delete('customers/{customer}', [AdminCustomerController::class, 'destroy'])->name('customers.destroy');
        Route::get('customers/{phone}', [AdminCustomerController::class, 'show'])->name('customers.show');

        // Product card button (text + action)
        Route::get('product-button', [ProductCtaController::class, 'edit'])->name('product-cta.edit');
        Route::put('product-button', [ProductCtaController::class, 'update'])->name('product-cta.update');



        Route::get('admins/password', [AdminUserController::class, 'editPassword'])->name('admins.password.edit');
        Route::put('admins/password', [AdminUserController::class, 'updatePassword'])->name('admins.password.update');
        Route::resource('admins', AdminUserController::class)->except(['show']);

        // Security & Blocked IPs
        Route::resource('blocked-ips', \App\Http\Controllers\Admin\BlockedIpController::class)->only(['index', 'store', 'destroy']);

        // Fake Order Guard
        Route::get('fake-order-guard', [FakeOrderGuardController::class, 'index'])->name('fake-order-guard.index');
        Route::get('fake-order-guard/{section}', [FakeOrderGuardController::class, 'index'])
            ->whereIn('section', ['guards', 'limits', 'bd-courier', 'blocked-orders'])
            ->name('fake-order-guard.section');
        Route::post('fake-order-guard/check-phone', [FakeOrderGuardController::class, 'checkPhone'])->name('fake-order-guard.check-phone');
        Route::post('fake-order-guard/test-connection', [FakeOrderGuardController::class, 'testConnection'])->name('fake-order-guard.test-connection');
        Route::get('fake-order-guard/plan-info', [FakeOrderGuardController::class, 'planInfo'])->name('fake-order-guard.plan-info');
        Route::resource('blocked-devices', BlockedDeviceController::class)->only(['index', 'store', 'destroy']);
        Route::resource('blocked-phones', BlockedPhoneController::class)->only(['index', 'store', 'destroy']);

        // System
        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::match(['put', 'post'], 'settings/{section}', [SettingController::class, 'updateSection'])->name('settings.update-section');
        Route::post('settings/test-mail', [SettingController::class, 'testMail'])->name('settings.test-mail');

        // System health, private backups, and recovery tools.
        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('system-health', [SystemHealthController::class, 'index'])->name('system-health.index');
            Route::post('system-health/backup', [SystemHealthController::class, 'createBackup'])->name('system-health.backup');
            Route::post('system-health/media-backup', [SystemHealthController::class, 'createMediaBackup'])->name('system-health.media-backup');
            Route::post('system-health/full-backup', [SystemHealthController::class, 'createFullBackup'])->name('system-health.full-backup');
            Route::post('system-health/restore', [SystemHealthController::class, 'restoreDatabase'])->name('system-health.restore');
            Route::post('system-health/media-restore', [SystemHealthController::class, 'restoreMedia'])->name('system-health.media-restore');
            Route::post('system-health/full-restore', [SystemHealthController::class, 'restoreFull'])->name('system-health.full-restore');
            Route::get('system-health/backups/{backup}/download', [SystemHealthController::class, 'download'])->name('system-health.backups.download');
            Route::delete('system-health/backups/{backup}', [SystemHealthController::class, 'destroy'])->name('system-health.backups.destroy');
            Route::post('system-health/backups/{backup}/delete', [SystemHealthController::class, 'destroy'])->name('system-health.backups.delete');
            Route::post('system-health/clear-cache', [SystemHealthController::class, 'clearCache'])->name('system-health.clear-cache');
            Route::post('system-health/migrations/run', [SystemHealthController::class, 'runMigrations'])->name('system-health.migrations.run');

            Route::get('system/git-repository', [ApplicationUpdateController::class, 'index'])->name('git-repository.index');
            Route::post('system/git-repository', [ApplicationUpdateController::class, 'saveSettings'])->name('git-repository.settings');
            Route::post('system/git-repository/test', [ApplicationUpdateController::class, 'test'])->name('git-repository.test');
            Route::post('system/git-repository/check', [ApplicationUpdateController::class, 'check'])->name('git-repository.check');
            Route::post('system/git-repository/pull', [ApplicationUpdateController::class, 'pull'])->name('git-repository.pull');
            Route::post('system/git-repository/deploy', [ApplicationUpdateController::class, 'deploy'])->name('git-repository.deploy');
            Route::post('system/git-repository/discard-and-deploy', [ApplicationUpdateController::class, 'discardAndDeploy'])->name('git-repository.discard-and-deploy');
            Route::post('system/git-repository/rollback/{deployment}', [ApplicationUpdateController::class, 'rollback'])->name('git-repository.rollback');
        });
    });
});

// Keep the upstream /system-health URL available while the admin navigation
// uses the conventional /admin/system-health path.
Route::middleware(['admin', 'testing.readonly', 'permission:settings.manage'])->group(function () {
    Route::get('/system-health', [SystemHealthController::class, 'index']);
    Route::post('/system-health/backup', [SystemHealthController::class, 'createBackup']);
    Route::post('/system-health/media-backup', [SystemHealthController::class, 'createMediaBackup']);
    Route::post('/system-health/full-backup', [SystemHealthController::class, 'createFullBackup']);
    Route::post('/system-health/restore', [SystemHealthController::class, 'restoreDatabase']);
    Route::post('/system-health/media-restore', [SystemHealthController::class, 'restoreMedia']);
    Route::post('/system-health/full-restore', [SystemHealthController::class, 'restoreFull']);
    Route::get('/system-health/backups/{backup}/download', [SystemHealthController::class, 'download']);
    Route::delete('/system-health/backups/{backup}', [SystemHealthController::class, 'destroy']);
    Route::post('/system-health/backups/{backup}/delete', [SystemHealthController::class, 'destroy']);
    Route::post('/system-health/clear-cache', [SystemHealthController::class, 'clearCache']);
    Route::post('/system-health/migrations/run', [SystemHealthController::class, 'runMigrations']);
});

/*
|--------------------------------------------------------------------------
| Courier Webhooks (CSRF exempt — added to VerifyCsrfToken $except)
|--------------------------------------------------------------------------
*/
Route::post('/webhooks/courier-status', [CourierController::class, 'webhook'])
    ->middleware('throttle:120,1')   // 120 calls/minute per IP — prevents status-spam
    ->name('webhooks.courier-status');
