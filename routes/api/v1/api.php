<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\GuestUserController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerWalletController;
use App\Http\Controllers\Api\V1\OfflinePaymentMethodController;
use App\Http\Controllers\Api\V1\DeliverymanController;
use App\Http\Controllers\Api\V1\BannerController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ConfigController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\CouponController;
use App\Http\Controllers\Api\V1\DeliveryManReviewController;
use App\Http\Controllers\Api\V1\LoyaltyPointController;
use App\Http\Controllers\Api\V1\MapApiController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OfferController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\WishlistController;
use App\Http\Controllers\Api\V1\TimeSlotController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\Auth\CustomerAuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\DeliveryManLoginController;

Route::group(['namespace' => 'Api\V1', 'middleware'=>'localization'], function () {

    Route::group(['prefix' => 'auth', 'namespace' => 'Auth'], function () {
        Route::post('register', [CustomerAuthController::class, 'registration']);
        Route::post('login', [CustomerAuthController::class, 'login']);
        Route::post('social-customer-login', [CustomerAuthController::class, 'customerSocialLogin']);

        Route::post('check-phone', [CustomerAuthController::class, 'checkPhone']);
        Route::post('verify-phone', [CustomerAuthController::class, 'verifyPhone']);
        Route::post('send-login-otp', [CustomerAuthController::class, 'sendLoginOtp']);
        Route::post('verify-login-otp', [CustomerAuthController::class, 'verifyLoginOtp']);
        Route::post('check-email', [CustomerAuthController::class, 'checkEmail']);
        Route::post('verify-email', [CustomerAuthController::class, 'verifyEmail']);
        Route::post('firebase-auth-verify', [CustomerAuthController::class, 'firebaseAuthVerify']);
        Route::post('forgot-password', [PasswordResetController::class, 'resetPasswordRequest']);
        Route::post('verify-token', [PasswordResetController::class, 'verifyToken']);
        Route::put('reset-password', [PasswordResetController::class, 'resetPasswordSubmit']);

        Route::group(['prefix' => 'delivery-man'], function () {
            Route::post('register', [DeliveryManLoginController::class, 'registration']);
            Route::post('login', [DeliveryManLoginController::class, 'login']);
        });
    });

    Route::group(['prefix' => 'config'], function () {
        Route::get('/', [ConfigController::class, 'configuration']);
    });

    Route::match(['get', 'post'], 'whatsapp/webhook', [\App\Http\Controllers\Api\V1\WhatsAppWebhookController::class, 'handle']);

    Route::group(['prefix' => 'products'], function () {
        Route::get('all', [ProductController::class, 'getAllProducts']);
        Route::get('latest', [ProductController::class, 'getLatestProducts']);
        Route::get('popular', [ProductController::class, 'getPopularProducts']);
        Route::get('discounted', [ProductController::class, 'getDiscountedProducts']);
        Route::get('search', [ProductController::class, 'getSearchedProducts']);
        Route::get('details/{id}', [ProductController::class, 'getProduct']);
        Route::get('related-products/{product_id}', [ProductController::class, 'getRelatedProducts']);
        Route::get('reviews/{product_id}', [ProductController::class, 'getProductReviews']);
        Route::get('rating/{product_id}', [ProductController::class, 'getProductRating']);
        Route::get('daily-needs', [ProductController::class, 'getDailyNeedProducts']);
        Route::post('reviews/submit', [ProductController::class, 'submitProductReview'])->middleware('auth:api');

        Route::group(['prefix' => 'favorite', 'middleware' => ['auth:api', 'customer_is_block']], function () {
            Route::get('/', [ProductController::class, 'getFavoriteProducts']);
            Route::post('/', [ProductController::class, 'addFavoriteProducts']);
            Route::delete('/', [ProductController::class, 'removeFavoriteProducts']);
        });

        Route::get('featured', [ProductController::class, 'featuredProducts']);
        Route::get('most-viewed', [ProductController::class, 'getMostViewedProducts']);
        Route::get('trending', [ProductController::class, 'getTrendingProducts']);
        Route::get('recommended', [ProductController::class, 'getRecommendedProducts']);
        Route::get('most-reviewed', [ProductController::class, 'getMostReviewedProducts']);
        Route::post('create', [ProductController::class, 'createProduct']);
    });

    Route::group(['prefix' => 'banners'], function () {
        Route::get('/', [BannerController::class, 'getBanners']);
    });

    Route::group(['prefix' => 'notifications'], function () {
        Route::get('/', [NotificationController::class, 'getNotifications']);
    });

    Route::group(['prefix' => 'categories'], function () {
        Route::get('/', [CategoryController::class, 'getCategories']);
        Route::get('childes/{category_id}', [CategoryController::class, 'getChildes']);
        Route::get('products/{category_id}', [CategoryController::class, 'getProducts']);
        Route::get('products/{category_id}/all', [CategoryController::class, 'getAllProducts']);
    });

    Route::group(['prefix' => 'customer', 'middleware' => ['auth:api', 'customer_is_block']], function () {
        Route::get('info', [CustomerController::class, 'info']);
        Route::put('update-profile', [CustomerController::class, 'updateProfile']);
        Route::put('cm-firebase-token', [CustomerController::class, 'updateFirebaseToken']);
        Route::delete('remove-account', [CustomerController::class, 'removeAccount']);

        Route::group(['prefix' => 'address', 'middleware' => 'guest_user'], function () {
            Route::get('list', [CustomerController::class, 'addressList'])->withoutMiddleware(['auth:api', 'customer_is_block']);
            Route::post('add', [CustomerController::class, 'addNewAddress'])->withoutMiddleware(['auth:api', 'customer_is_block']);
            Route::put('update/{id}', [CustomerController::class, 'updateAddress'])->withoutMiddleware(['auth:api', 'customer_is_block']);
            Route::delete('delete', [CustomerController::class, 'deleteAddress'])->withoutMiddleware(['auth:api', 'customer_is_block']);
        });
        Route::get('last-ordered-address', [CustomerController::class, 'lastOrderedAddress']);

        Route::group(['prefix' => 'order', 'middleware' => 'guest_user'], function () {
            Route::get('list', [OrderController::class, 'getOrderList'])->withoutMiddleware(['auth:api', 'customer_is_block']);
            Route::post('details', [OrderController::class, 'getOrderDetails'])->withoutMiddleware(['auth:api', 'customer_is_block']);
            Route::post('place', [OrderController::class, 'placeOrder'])->withoutMiddleware(['auth:api', 'customer_is_block']);
            Route::put('cancel', [OrderController::class, 'cancelOrder'])->withoutMiddleware(['auth:api', 'customer_is_block']);
            Route::post('track', [OrderController::class, 'trackOrder'])->withoutMiddleware(['auth:api', 'customer_is_block']);
            Route::put('payment-method', [OrderController::class, 'updatePaymentMethod'])->withoutMiddleware(['auth:api', 'customer_is_block']);
        });
        Route::group(['prefix' => 'message'], function () {
            //customer-admin
            Route::get('get-admin-message', [ConversationController::class, 'getAdminMessage']);
            Route::post('send-admin-message', [ConversationController::class, 'storeAdminMessage']);
            //customer-deliveryman
            Route::get('get-order-message', [ConversationController::class, 'getMessageByOrder']);
            Route::post('send/{sender_type}', [ConversationController::class, 'storeMessageByOrder']);

        });

        Route::group(['prefix' => 'wish-list'], function () {
            Route::get('/', [WishlistController::class, 'getWishlist']);
            Route::post('add', [WishlistController::class, 'addToWishlist']);
            Route::delete('remove', [WishlistController::class, 'removeFromWishlist']);
        });

        Route::post('transfer-point-to-wallet', [CustomerWalletController::class, 'transferLoyaltyPointToWallet']);
        Route::get('wallet-transactions', [CustomerWalletController::class, 'walletTransactions']);
        Route::get('bonus/list', [CustomerWalletController::class, 'walletBonusList']);

        Route::get('loyalty-point-transactions', [LoyaltyPointController::class, 'pointTransactions']);

    });

    Route::group(['prefix' => 'coupon', 'middleware' => ['auth:api', 'customer_is_block']], function () {
        Route::get('list', [CouponController::class, 'list'])->withoutMiddleware(['auth:api', 'customer_is_block']);
        Route::get('apply', [CouponController::class, 'apply'])->withoutMiddleware(['auth:api', 'customer_is_block']);
    });

    Route::group(['prefix' => 'timeSlot'], function () {
        Route::get('/', [TimeSlotController::class, 'getTimeSlot']);
    });

    Route::group(['prefix' => 'mapapi'], function () {
        Route::get('place-api-autocomplete', [MapApiController::class, 'placeApiAutocomplete']);
        Route::get('distance-api', [MapApiController::class, 'distanceApi']);
        Route::get('place-api-details', [MapApiController::class, 'placeApiDetails']);
        Route::get('geocode-api', [MapApiController::class, 'geocodeApi']);
    });

    Route::group(['prefix' => 'flash-deals'], function () {
        Route::get('/', [OfferController::class, 'getFlashDeal']);
        Route::get('products/{flash_deal_id}', [OfferController::class, 'getFlashDealProducts']);
    });

    Route::post('subscribe-newsletter', [CustomerController::class, 'subscribeNewsletter']);

    Route::group(['prefix' => 'delivery-man'], function () {
        Route::group(['middleware' => 'deliveryman_is_active'], function () {
            Route::get('profile', [DeliverymanController::class, 'getProfile']);
            Route::get('current-orders', [DeliverymanController::class, 'getCurrentOrders']);
            Route::get('all-orders', [DeliverymanController::class, 'getAllOrders']);
            Route::post('record-location-data', [DeliverymanController::class, 'recordLocationData']);
            Route::get('order-delivery-history', [DeliverymanController::class, 'getOrderHistory']);
            Route::put('update-order-status', [DeliverymanController::class, 'updateOrderStatus']);
            Route::put('update-payment-status', [DeliverymanController::class, 'orderPaymentStatusUpdate']);
            Route::post('order-details', [DeliverymanController::class, 'getOrderDetails']);
            Route::get('last-location', [DeliverymanController::class, 'getLastLocation']);
            Route::put('update-fcm-token', [DeliverymanController::class, 'updateFcmToken']);
            Route::get('order-model', [DeliverymanController::class, 'orderModel']);
        });

        //delivery-man message
        Route::group(['prefix' => 'message'], function () {
            Route::post('get-message', [ConversationController::class, 'getOrderMessageForDeliveryman']);
            Route::post('send/{sender_type}', [ConversationController::class, 'storeMessageByOrder']);
        });

        Route::group(['prefix' => 'reviews', 'middleware' => ['auth:api', 'customer_is_block']], function () {
            Route::get('/{delivery_man_id}', [DeliveryManReviewController::class, 'getReviews']);
            Route::get('rating/{delivery_man_id}', [DeliveryManReviewController::class, 'getRating']);
            Route::post('/submit', [DeliveryManReviewController::class, 'submitReview']);
        });
    });

    Route::group(['prefix' => 'guest'], function () {
        Route::post('/add', [GuestUserController::class, 'guestStore']);
    });

    Route::group(['prefix' => 'offline-payment-method'], function () {
        Route::get('/list', [OfflinePaymentMethodController::class, 'list']);
    });

    Route::post('customer/change-language', [CustomerController::class, 'changeLanguage']);
    Route::post('delivery-man/change-language', [DeliverymanController::class, 'changeLanguage']);

    require __DIR__.'/mentors.php';
    require __DIR__.'/seminars.php';
    require __DIR__.'/internships.php';

});
