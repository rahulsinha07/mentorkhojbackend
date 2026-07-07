<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AnalyticController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\System\AddonController;
use App\Http\Controllers\Admin\WalletBonusController;
use App\Http\Controllers\Admin\OfflinePaymentMethodController;
use App\Http\Controllers\Admin\AttributeController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\BusinessSettingsController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ConversationController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerWalletController;
use App\Http\Controllers\Admin\CustomRoleController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\DatabaseSettingsController;
use App\Http\Controllers\Admin\DeliveryManController;
use App\Http\Controllers\Admin\DiscountController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\LocationSettingsController;
use App\Http\Controllers\Admin\LoyaltyPointController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\OfferController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\POSController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReviewsController;
use App\Http\Controllers\Admin\SMSModuleController;
use App\Http\Controllers\Admin\TimeSlotController;

Route::group(['namespace' => 'Admin', 'as' => 'admin.'], function () {
    Route::get('lang/{locale}', [LanguageController::class, 'lang'])->name('lang');

    Route::group(['namespace' => 'Auth', 'prefix' => 'auth', 'as' => 'auth.'], function () {
        Route::get('/code/captcha/{tmp}', [LoginController::class, 'captcha'])->name('default-captcha');
        Route::get('login', [LoginController::class, 'login'])->name('login');
        Route::post('login', [LoginController::class, 'submit'])->middleware('actch');
        Route::get('logout', [LoginController::class, 'logout'])->name('logout');
    });

    Route::group(['middleware' => ['admin', 'employee_active_check']], function () {
            Route::get('/fcm/{id}', [DashboardController::class, 'fcm'])->name('dashboard');     //test route
            Route::get('/', [DashboardController::class, 'dashboard'])->name('dashboard');
            Route::post('order-stats', [DashboardController::class, 'orderStats'])->name('order-stats');
            Route::get('settings', [SystemController::class, 'settings'])->name('settings');
            Route::post('settings', [SystemController::class, 'settingsUpdate']);
            Route::post('settings-password', [SystemController::class, 'settingsPasswordUpdate'])->name('settings-password');
            Route::get('/get-restaurant-data', [SystemController::class, 'restaurantData'])->name('get-restaurant-data');
            Route::get('dashboard/order-statistics', [DashboardController::class, 'getOrderStatistics'])->name('dashboard.order-statistics');
            Route::get('dashboard/earning-statistics', [DashboardController::class, 'getEarningStatistics'])->name('dashboard.earning-statistics');

        Route::group(['prefix' => 'custom-role', 'as' => 'custom-role.', 'middleware'=>['module:user_management']], function () {
            Route::get('create', [CustomRoleController::class, 'create'])->name('create');
            Route::post('create', [CustomRoleController::class, 'store'])->name('store');
            Route::get('update/{id}', [CustomRoleController::class, 'edit'])->name('update');
            Route::post('update/{id}', [CustomRoleController::class, 'update']);
            Route::delete('delete/{id}', [CustomRoleController::class, 'delete'])->name('delete');
            Route::get('status/{id}/{status}', [CustomRoleController::class, 'status'])->name('status');
            Route::get('export', [CustomRoleController::class, 'export'])->name('export');
        });

        Route::group(['prefix' => 'employee', 'as' => 'employee.','middleware'=>['module:user_management']], function () {
            Route::get('add-new', [EmployeeController::class, 'index'])->name('add-new');
            Route::post('add-new', [EmployeeController::class, 'store']);
            Route::get('list', [EmployeeController::class, 'list'])->name('list');
            Route::get('update/{id}', [EmployeeController::class, 'edit'])->name('update');
            Route::post('update/{id}', [EmployeeController::class, 'update']);
            Route::get('status/{id}/{status}', [EmployeeController::class, 'status'])->name('status');
            Route::delete('delete/{id}', [EmployeeController::class, 'delete'])->name('delete');
            Route::get('export', [EmployeeController::class, 'export'])->name('export');
        });
        Route::group(['prefix' => 'pos', 'as' => 'pos.','middleware'=>['module:pos_management']], function () {
            Route::get('/', [POSController::class, 'index'])->name('index');
            Route::get('quick-view', [POSController::class, 'quickView'])->name('quick-view');
            Route::post('variant_price', [POSController::class, 'variantPrice'])->name('variant_price');
            Route::post('add-to-cart', [POSController::class, 'addToCart'])->name('add-to-cart');
            Route::post('remove-from-cart', [POSController::class, 'removeFromCart'])->name('remove-from-cart');
            Route::post('cart-items', [POSController::class, 'cartItems'])->name('cart_items');
            Route::post('update-quantity', [POSController::class, 'updateQuantity'])->name('updateQuantity');
            Route::post('empty-cart', [POSController::class, 'emptyCart'])->name('emptyCart');
            Route::post('tax', [POSController::class, 'updateTax'])->name('tax');
            Route::post('discount', [POSController::class, 'updateDiscount'])->name('discount');
            Route::get('customers', [POSController::class, 'getCustomers'])->name('customers');
            Route::post('order', [POSController::class, 'placeOrder'])->name('order');
            Route::get('orders', [POSController::class, 'orderList'])->name('orders');
            Route::get('order-details/{id}', [POSController::class, 'orderDetails'])->name('order-details');
            Route::get('invoice/{id}', [POSController::class, 'generateInvoice']);
            Route::any('store-keys', [POSController::class, 'storeKeys'])->name('store-keys');
            Route::get('orders/export', [POSController::class, 'exportOrders'])->name('orders.export');
            Route::post('customer/store', [POSController::class, 'newCustomerStore'])->name('customer.store');
            Route::post('add-delivery-address', [POSController::class, 'addDeliveryInfo'])->name('add-delivery-address');
            Route::get('get-distance', [POSController::class, 'getDistance'])->name('get-distance');
            Route::post('order_type/store', [POSController::class, 'orderTypeStore'])->name('order_type.store');

        });

        Route::group(['prefix' => 'banner', 'as' => 'banner.','middleware'=>['module:promotion_management']], function () {
            Route::get('add-new', [BannerController::class, 'index'])->name('add-new');
            Route::post('store', [BannerController::class, 'store'])->name('store');
            Route::get('edit/{id}', [BannerController::class, 'edit'])->name('edit');
            Route::put('update/{id}', [BannerController::class, 'update'])->name('update');
            Route::get('status/{id}/{status}', [BannerController::class, 'status'])->name('status');
            Route::delete('delete/{id}', [BannerController::class, 'delete'])->name('delete');
        });

        Route::group(['prefix' => 'discount', 'as' => 'discount.','middleware'=>['module:promotion_management']], function () {
            Route::get('add-new', [DiscountController::class, 'index'])->name('add-new');
            Route::post('store', [DiscountController::class, 'store'])->name('store');
            Route::get('edit/{id}', [DiscountController::class, 'edit'])->name('edit');
            Route::post('update/{id}', [DiscountController::class, 'update'])->name('update');
            Route::get('list', [DiscountController::class, 'list'])->name('list');
            Route::get('status/{id}/{status}', [DiscountController::class, 'status'])->name('status');
            Route::delete('delete/{id}', [DiscountController::class, 'delete'])->name('delete');
        });

        Route::group(['prefix' => 'attribute', 'as' => 'attribute.','middleware'=>['module:product_management']], function () {
            Route::get('add-new', [AttributeController::class, 'index'])->name('add-new');
            Route::post('store', [AttributeController::class, 'store'])->name('store');
            Route::get('edit/{id}', [AttributeController::class, 'edit'])->name('edit');
            Route::post('update/{id}', [AttributeController::class, 'update'])->name('update');
            Route::delete('delete/{id}', [AttributeController::class, 'delete'])->name('delete');
            Route::get('status/{id}/{status}', [AttributeController::class, 'status'])->name('status');
        });

        Route::group(['prefix' => 'branch', 'as' => 'branch.','middleware'=>['module:system_management']], function () {
            Route::get('add-new', [BranchController::class, 'index'])->name('add-new');
            Route::get('list', [BranchController::class, 'list'])->name('list');
            Route::post('store', [BranchController::class, 'store'])->name('store');
            Route::get('edit/{id}', [BranchController::class, 'edit'])->name('edit');
            Route::post('update/{id}', [BranchController::class, 'update'])->name('update');
            Route::delete('delete/{id}', [BranchController::class, 'delete'])->name('delete');
            Route::get('status/{id}/{status}', [BranchController::class, 'status'])->name('status');
        });

        Route::group(['prefix' => 'delivery-man', 'as' => 'delivery-man.','middleware'=>['module:user_management']], function () {
            Route::get('add', [DeliveryManController::class, 'index'])->name('add');
            Route::post('store', [DeliveryManController::class, 'store'])->name('store');
            Route::get('list', [DeliveryManController::class, 'list'])->name('list');
            Route::get('preview/{id}', [DeliveryManController::class, 'preview'])->name('preview');
            Route::get('edit/{id}', [DeliveryManController::class, 'edit'])->name('edit');
            Route::post('update/{id}', [DeliveryManController::class, 'update'])->name('update');
            Route::delete('delete/{id}', [DeliveryManController::class, 'delete'])->name('delete');
            Route::get('status/{id}/{status}', [DeliveryManController::class, 'status'])->name('status');
            Route::get('export', [DeliveryManController::class, 'export'])->name('export');
            Route::get('pending/list', [DeliveryManController::class, 'pendingList'])->name('pending');
            Route::get('denied/list', [DeliveryManController::class, 'deniedList'])->name('denied');
            Route::get('update-application/{id}/{status}', [DeliveryManController::class, 'updateApplicationStatus'])->name('application');

            Route::group(['prefix' => 'reviews', 'as' => 'reviews.'], function () {
                Route::get('list', [DeliveryManController::class, 'reviewsList'])->name('list');
            });
        });

        Route::group(['prefix' => 'notification', 'as' => 'notification.','middleware'=>['module:promotion_management']], function () {
            Route::get('add-new', [NotificationController::class, 'index'])->name('add-new');
            Route::post('store', [NotificationController::class, 'store'])->name('store');
            Route::get('edit/{id}', [NotificationController::class, 'edit'])->name('edit');
            Route::post('update/{id}', [NotificationController::class, 'update'])->name('update');
            Route::get('status/{id}/{status}', [NotificationController::class, 'status'])->name('status');
            Route::delete('delete/{id}', [NotificationController::class, 'delete'])->name('delete');
        });

        Route::group(['prefix' => 'product', 'as' => 'product.','middleware'=>['module:product_management']], function () {
            Route::get('add-new', [ProductController::class, 'index'])->name('add-new');
            Route::post('variant-combination', [ProductController::class, 'variantCombination'])->name('variant-combination');
            Route::post('store', [ProductController::class, 'store'])->name('store');
            Route::get('edit/{id}', [ProductController::class, 'edit'])->name('edit');
            Route::post('update/{id}', [ProductController::class, 'update'])->name('update');
            Route::get('list', [ProductController::class, 'list'])->name('list');
            Route::delete('delete/{id}', [ProductController::class, 'delete'])->name('delete');
            Route::get('status/{id}/{status}', [ProductController::class, 'status'])->name('status');
            Route::get('bulk-import', [ProductController::class, 'bulkImportIndex'])->name('bulk-import');
            Route::post('bulk-import', [ProductController::class, 'bulkImportProduct']);
            Route::get('bulk-export-index', [ProductController::class, 'bulkExportIndex'])->name('bulk-export-index');
            Route::get('bulk-export', [ProductController::class, 'bulkExportProduct'])->name('bulk-export');
            Route::get('view/{id}', [ProductController::class, 'view'])->name('view');
            Route::get('remove-image/{id}/{name}', [ProductController::class, 'removeImage'])->name('remove-image');
            Route::get('get-categories', [ProductController::class, 'getCategories'])->name('get-categories');
            Route::post('daily-needs', [ProductController::class, 'dailyNeeds'])->name('daily-needs');
            Route::get('limited-stock', [ProductController::class, 'limitedStock'])->name('limited-stock');
            Route::get('get-variations', [ProductController::class, 'getVariations'])->name('get-variations');
            Route::post('update-quantity', [ProductController::class, 'updateQuantity'])->name('update-quantity');
            Route::get('feature/{id}/{is_featured}', [ProductController::class, 'feature'])->name('feature');
        });

        Route::group(['prefix' => 'orders', 'as' => 'orders.','middleware'=>['module:order_management']], function () {
            Route::get('list/{status}', [OrderController::class, 'list'])->name('list');
            Route::get('details/{id}', [OrderController::class, 'details'])->name('details');
            Route::get('status', [OrderController::class, 'status'])->name('status');
            Route::get('add-delivery-man/{order_id}/{delivery_man_id}', [OrderController::class, 'addDeliveryman'])->name('add-delivery-man');
            Route::get('payment-status', [OrderController::class, 'paymentStatus'])->name('payment-status');
            Route::get('generate-invoice/{id}', [OrderController::class, 'generateInvoice'])->name('generate-invoice')->withoutMiddleware(['module:order_management']);
            Route::post('add-payment-ref-code/{id}', [OrderController::class, 'addPaymentReferenceCode'])->name('add-payment-ref-code');
            Route::get('branch-filter/{branch_id}', [OrderController::class, 'branchFilter'])->name('branch-filter');
            Route::post('search', [OrderController::class, 'search'])->name('search');
            Route::get('export/{status}', [OrderController::class, 'exportOrders'])->name('export');
            Route::get('verify-offline-payment/{order_id}/{status}', [OrderController::class, 'verifyOfflinePayment']);
        });

        Route::group(['prefix' => 'order', 'as' => 'order.','middleware'=>['module:order_management']], function () {
            Route::get('list/{status}', [OrderController::class, 'list'])->name('list');
            Route::put('status-update/{id}', [OrderController::class, 'status'])->name('status-update');
            Route::post('update-shipping/{id}', [OrderController::class, 'updateShipping'])->name('update-shipping');
            Route::post('update-timeSlot', [OrderController::class, 'updateTimeSlot'])->name('update-timeSlot');
            Route::post('update-deliveryDate', [OrderController::class, 'updateDeliveryDate'])->name('update-deliveryDate');
        });

        Route::group(['prefix' => 'category', 'as' => 'category.','middleware'=>['module:product_management']], function () {
            Route::get('add', [CategoryController::class, 'index'])->name('add');
            Route::get('add-sub-category', [CategoryController::class, 'subIndex'])->name('add-sub-category');
            Route::post('store', [CategoryController::class, 'store'])->name('store');
            Route::get('edit/{id}', [CategoryController::class, 'edit'])->name('edit');
            Route::post('update/{id}', [CategoryController::class, 'update'])->name('update');
            Route::post('store', [CategoryController::class, 'store'])->name('store');
            Route::get('status/{id}/{status}', [CategoryController::class, 'status'])->name('status');
            Route::delete('delete/{id}', [CategoryController::class, 'delete'])->name('delete');
            Route::post('search', [CategoryController::class, 'search'])->name('search');
            Route::get('priority', [CategoryController::class, 'priority'])->name('priority');
        });

        Route::group(['prefix' => 'message', 'as' => 'message.','middleware'=>['module:support_management']], function () {
            Route::get('list', [ConversationController::class, 'list'])->name('list');
            Route::post('update-fcm-token', [ConversationController::class, 'updateFcmToken'])->name('update_fcm_token');
            Route::get('get-conversations', [ConversationController::class, 'getConversations'])->name('get_conversations');
            Route::post('store/{user_id}', [ConversationController::class, 'store'])->name('store');
            Route::get('view/{user_id}', [ConversationController::class, 'view'])->name('view');
        });

        Route::group(['prefix' => 'reviews', 'as' => 'reviews.','middleware'=>['module:user_management']], function () {
            Route::get('list', [ReviewsController::class, 'list'])->name('list');
            Route::get('status/{id}/{status}', [ReviewsController::class, 'status'])->name('status');
        });

        Route::group(['prefix' => 'coupon', 'as' => 'coupon.','middleware'=>['module:promotion_management']], function () {
            Route::get('add-new', [CouponController::class, 'index'])->name('add-new');
            Route::post('store', [CouponController::class, 'store'])->name('store');
            Route::get('update/{id}', [CouponController::class, 'edit'])->name('update');
            Route::post('update/{id}', [CouponController::class, 'update']);
            Route::get('status/{id}/{status}', [CouponController::class, 'status'])->name('status');
            Route::delete('delete/{id}', [CouponController::class, 'delete'])->name('delete');
            Route::get('quick-view-details', [CouponController::class, 'details'])->name('quick-view-details');

        });

        Route::group(['prefix' => 'business-settings', 'as' => 'business-settings.','middleware'=>['module:system_management']], function () {

            Route::group(['prefix'=>'store','as'=>'store.'], function() {
                Route::get('ecom-setup', [BusinessSettingsController::class, 'businessSettingsIndex'])->name('ecom-setup')->middleware('actch');
                Route::get('delivery-setup', [BusinessSettingsController::class, 'deliveryIndex'])->name('delivery-setup')->middleware('actch');
                Route::post('delivery-setup-update', [BusinessSettingsController::class, 'deliverySetupUpdate'])->name('delivery-setup-update');
                Route::post('update-setup', [BusinessSettingsController::class, 'businessSetup'])->name('update-setup');
                Route::get('maintenance-mode', [BusinessSettingsController::class, 'maintenanceMode'])->name('maintenance-mode');
                Route::get('currency-position/{position}', [BusinessSettingsController::class, 'currencySymbolPosition'])->name('currency-position');
                Route::get('self-pickup/{status}', [BusinessSettingsController::class, 'selfPickupStatus'])->name('self-pickup');
                Route::get('phone-verification/{status}', [BusinessSettingsController::class, 'phoneVerificationStatus'])->name('phone-verification');
                Route::get('email-verification/{status}', [BusinessSettingsController::class, 'emailVerificationStatus'])->name('email-verification');
                Route::get('location-setup', [LocationSettingsController::class, 'locationIndex'])->name('location-setup')->middleware('actch');
                Route::post('update-location', [LocationSettingsController::class, 'locationSetup'])->name('update-location');
                Route::get('main-branch-setup', [BusinessSettingsController::class, 'mainBranchSetup'])->name('main-branch-setup')->middleware('actch');
                Route::get('product-setup', [BusinessSettingsController::class, 'productSetup'])->name('product-setup');
                Route::post('product-setup-update', [BusinessSettingsController::class, 'productSetupUpdate'])->name('product-setup-update');
                Route::get('cookies-setup', [BusinessSettingsController::class, 'cookiesSetup'])->name('cookies-setup');
                Route::post('cookies-setup-update', [BusinessSettingsController::class, 'cookiesSetupUpdate'])->name('cookies-setup-update');
                Route::get('max-amount-status/{status}', [BusinessSettingsController::class, 'maximumAmountStatus'])->name('max-amount-status');
                Route::get('free-delivery-status/{status}', [BusinessSettingsController::class, 'freeDeliveryStatus'])->name('free-delivery-status');
                Route::get('dm-self-registration/{status}', [BusinessSettingsController::class, 'deliverymanSelfRegistrationStatus'])->name('dm-self-registration');
                Route::get('otp-setup', [BusinessSettingsController::class, 'OTPSetup'])->name('otp-setup');
                Route::post('otp-setup-update', [BusinessSettingsController::class, 'OTPSetupUpdate'])->name('otp-setup-update');
                Route::get('guest-checkout/{status}', [BusinessSettingsController::class, 'guestCheckoutStatus'])->name('guest-checkout');
                Route::get('partial-payment/{status}', [BusinessSettingsController::class, 'partialPaymentStatus'])->name('partial-payment');
                Route::get('customer-setup', [BusinessSettingsController::class, 'customerSetup'])->name('customer-setup');
                Route::post('customer-setup-update', [BusinessSettingsController::class, 'customerSetupUpdate'])->name('customer-setup-update');
                Route::get('order-setup', [BusinessSettingsController::class, 'orderSetup'])->name('order-setup');
                Route::post('order-setup-update', [BusinessSettingsController::class, 'orderSetupUpdate'])->name('order-setup-update');

                Route::group(['prefix' => 'timeSlot', 'as' => 'timeSlot.'], function () {
                    Route::get('add-new', [TimeSlotController::class, 'index'])->name('add-new');
                    Route::post('store', [TimeSlotController::class, 'store'])->name('store');
                    Route::get('update/{id}', [TimeSlotController::class, 'edit'])->name('update');
                    Route::post('update/{id}', [TimeSlotController::class, 'update']);
                    Route::get('status/{id}/{status}', [TimeSlotController::class, 'status'])->name('status');
                    Route::delete('delete/{id}', [TimeSlotController::class, 'delete'])->name('delete');
                });
            });

            Route::group(['prefix'=>'web-app','as'=>'web-app.'], function() {
                Route::get('mail-config', [BusinessSettingsController::class, 'mailIndex'])->name('mail-config')->middleware('actch');
                Route::post('mail-config', [BusinessSettingsController::class, 'mailConfig']);
                Route::get('mail-config/status/{status}', [BusinessSettingsController::class, 'mailConfigStatus'])->name('mail-config.status');
                Route::post('mail-send', [BusinessSettingsController::class, 'mailSend'])->name('mail-send');

                Route::get('sms-module', [SMSModuleController::class, 'smsIndex'])->name('sms-module');
                Route::post('sms-module-update/{sms_module}', [SMSModuleController::class, 'smsUpdate'])->name('sms-module-update');

                Route::get('payment-method', [BusinessSettingsController::class, 'paymentIndex'])->name('payment-method')->middleware('actch');
                Route::post('payment-method-update/{payment_method}', [BusinessSettingsController::class, 'paymentUpdate'])->name('payment-method-update');
                Route::post('payment-config-update', [BusinessSettingsController::class, 'paymentConfigUpdate'])->name('payment-config-update')->middleware('actch');


                Route::group(['prefix'=>'system-setup','as'=>'system-setup.'], function() {
                    Route::get('app-setting', [BusinessSettingsController::class, 'appSettingIndex'])->name('app_setting');
                    Route::post('app-setting', [BusinessSettingsController::class, 'appSettingUpdate']);
                    Route::get('db-index', [DatabaseSettingsController::class, 'databaseIndex'])->name('db-index');
                    Route::post('db-clean', [DatabaseSettingsController::class, 'cleanDatabase'])->name('clean-db');
                    Route::get('firebase-message-config', [BusinessSettingsController::class, 'firebaseMessageConfigIndex'])->name('firebase_message_config_index');
                    Route::post('firebase-message-config', [BusinessSettingsController::class, 'firebaseMessageConfig'])->name('firebase_message_config');

                    Route::group(['prefix' => 'language', 'as' => 'language.'], function () {
                        Route::get('', [LanguageController::class, 'index'])->name('index');
                        Route::post('add-new', [LanguageController::class, 'store'])->name('add-new');
                        Route::get('update-status', [LanguageController::class, 'updateStatus'])->name('update-status');
                        Route::get('update-default-status', [LanguageController::class, 'updateDefaultStatus'])->name('update-default-status');
                        Route::post('update', [LanguageController::class, 'update'])->name('update');
                        Route::get('translate/{lang}', [LanguageController::class, 'translate'])->name('translate');
                        Route::post('translate-submit/{lang}', [LanguageController::class, 'translateSubmit'])->name('translate-submit');
                        Route::post('remove-key/{lang}', [LanguageController::class, 'translateKeyRemove'])->name('remove-key');
                        Route::get('delete/{lang}', [LanguageController::class, 'delete'])->name('delete');
                    });
                });

                Route::group(['prefix' => 'third-party', 'as' => 'third-party.'], function () {
                    Route::get('map-api-settings',[BusinessSettingsController::class, 'mapApiSetting'])->name('map-api-settings');
                    Route::post('map-api-store',[BusinessSettingsController::class, 'mapApiStore'])->name('map-api-store');
                    Route::get('social-media', [BusinessSettingsController::class, 'socialMedia'])->name('social-media');
                    Route::get('fetch', [BusinessSettingsController::class, 'fetch'])->name('fetch');
                    Route::post('social-media-store', [BusinessSettingsController::class, 'socialMediaStore'])->name('social-media-store');
                    Route::post('social-media-edit', [BusinessSettingsController::class, 'socialMediaEdit'])->name('social-media-edit');
                    Route::post('social-media-update', [BusinessSettingsController::class, 'socialMediaUpdate'])->name('social-media-update');
                    Route::post('social-media-delete', [BusinessSettingsController::class, 'socialMediaDelete'])->name('social-media-delete');
                    Route::post('social-media-status-update', [BusinessSettingsController::class, 'socialMediaStatusUpdate'])->name('social-media-status-update');
                    Route::get('social-media-login', [BusinessSettingsController::class, 'socialMediaLogin'])->name('social-media-login');
                    Route::get('google-social-login/{status}', [BusinessSettingsController::class, 'googleSocialLogin'])->name('google-social-login');
                    Route::get('facebook-social-login/{status}', [BusinessSettingsController::class, 'facebookSocialLogin'])->name('facebook-social-login');
                    Route::get('linkedin-social-login/{status}', [BusinessSettingsController::class, 'linkedinSocialLogin'])->name('linkedin-social-login');
                    Route::post('update-apple-login', [BusinessSettingsController::class, 'updateAppleLogin'])->name('update-apple-login');
                    Route::get('recaptcha', [BusinessSettingsController::class, 'recaptchaIndex'])->name('recaptcha_index');
                    Route::post('recaptcha-update', [BusinessSettingsController::class, 'recaptchaUpdate'])->name('recaptcha_update');
                    Route::get('fcm-index', [BusinessSettingsController::class, 'fcmIndex'])->name('fcm-index')->middleware('actch');
                    Route::get('fcm-config', [BusinessSettingsController::class, 'fcmConfig'])->name('fcm-config')->middleware('actch');
                    Route::post('update-fcm', [BusinessSettingsController::class, 'updateFcm'])->name('update-fcm')->middleware('actch');
                    Route::post('update-fcm-messages', [BusinessSettingsController::class, 'updateFcmMessages'])->name('update-fcm-messages')->middleware('actch');
                    Route::get('chat-index', [BusinessSettingsController::class, 'chatIndex'])->name('chat-index');
                    Route::post('update-chat', [BusinessSettingsController::class, 'updateChat'])->name('update-chat');
                    Route::get('firebase-otp-verification', [BusinessSettingsController::class, 'firebaseOTPVerification'])->name('firebase-otp-verification');
                    Route::post('firebase-otp-verification-update', [BusinessSettingsController::class, 'firebaseOTPVerificationUpdate'])->name('firebase-otp-verification-update');

                    Route::group(['prefix' => 'offline-payment', 'as' => 'offline-payment.'], function(){
                        Route::get('list', [OfflinePaymentMethodController::class, 'list'])->name('list');
                        Route::get('add', [OfflinePaymentMethodController::class, 'add'])->name('add');
                        Route::post('store', [OfflinePaymentMethodController::class, 'store'])->name('store');
                        Route::get('edit/{id}', [OfflinePaymentMethodController::class, 'edit'])->name('edit');
                        Route::post('update/{id}', [OfflinePaymentMethodController::class, 'update'])->name('update');
                        Route::get('status/{id}/{status}', [OfflinePaymentMethodController::class, 'status'])->name('status');
                        Route::post('delete', [OfflinePaymentMethodController::class, 'delete'])->name('delete');
                    });
                });

            });

            Route::group(['prefix' => 'page-setup', 'as' => 'page-setup.'], function () {
                Route::get('terms-and-conditions', [BusinessSettingsController::class, 'termsAndConditions'])->name('terms-and-conditions');
                Route::post('terms-and-conditions', [BusinessSettingsController::class, 'termsAndConditionsUpdate']);

                Route::get('privacy-policy', [BusinessSettingsController::class, 'privacyPolicy'])->name('privacy-policy');
                Route::post('privacy-policy', [BusinessSettingsController::class, 'privacyPolicyUpdate']);

                Route::get('about-us', [BusinessSettingsController::class, 'aboutUs'])->name('about-us');
                Route::post('about-us', [BusinessSettingsController::class, 'aboutUsUpdate']);

                Route::get('faq', [BusinessSettingsController::class, 'faq'])->name('faq');
                Route::post('faq', [BusinessSettingsController::class, 'faqUpdate']);

                Route::get('cancellation-policy', [BusinessSettingsController::class, 'cancellationPolicy'])->name('cancellation-policy');
                Route::post('cancellation-policy', [BusinessSettingsController::class, 'cancellationPolicyUpdate']);
                Route::get('cancellation-policy/status/{status}', [BusinessSettingsController::class, 'cancellationPolicyStatus'])->name('cancellation-policy.status');

                Route::get('refund-policy', [BusinessSettingsController::class, 'refundPolicy'])->name('refund-policy');
                Route::post('refund-policy', [BusinessSettingsController::class, 'refundPolicyUpdate']);
                Route::get('refund-policy/status/{status}', [BusinessSettingsController::class, 'refundPolicyStatus'])->name('refund-policy.status');

                Route::get('return-policy', [BusinessSettingsController::class, 'returnPolicy'])->name('return-policy');
                Route::post('return-policy', [BusinessSettingsController::class, 'returnPolicyUpdate']);
                Route::get('return-policy/status/{status}', [BusinessSettingsController::class, 'returnPolicyStatus'])->name('return-policy.status');

            });

            Route::get('currency-add', [BusinessSettingsController::class, 'currencyIndex'])->name('currency-add')->middleware('actch');
            Route::post('currency-add', [BusinessSettingsController::class, 'currencyStore']);
            Route::get('currency-update/{id}', [BusinessSettingsController::class, 'currencyEdit'])->name('currency-update')->middleware('actch');
            Route::put('currency-update/{id}', [BusinessSettingsController::class, 'currencyUpdate']);
            Route::delete('currency-delete/{id}', [BusinessSettingsController::class, 'currencyDelete'])->name('currency-delete');

        });

        Route::group(['prefix' => 'report', 'as' => 'report.','middleware'=>['module:report_management']], function () {
            Route::get('order', [ReportController::class, 'order_index'])->name('order');
            Route::get('earning', [ReportController::class, 'earning_index'])->name('earning');
            Route::post('set-date', [ReportController::class, 'setDate'])->name('set-date');
            Route::get('sale-report', [ReportController::class, 'saleReportIndex'])->name('sale-report');
            Route::get('export-sale-report', [ReportController::class, 'exportSaleReport'])->name('export-sale-report');
            Route::get('expense', [ReportController::class, 'expenseIndex'])->name('expense');
            Route::get('expense-export-excel', [ReportController::class, 'expenseExportExcel'])->name('expense.export.excel');
            Route::get('expense-export-pdf', [ReportController::class, 'expenseSummaryPdf'])->name('expense.export.pdf');
        });

        Route::group(['prefix' => 'analytics', 'as' => 'analytics.','middleware'=>['module:report_management']], function () {
            Route::get('keyword-search', [AnalyticController::class, 'getKeywordSearch'])->name('keyword-search');
            Route::get('customer-search', [AnalyticController::class, 'getCustomerSearch'])->name('customer-search');
            Route::get('keyword-export-excel', [AnalyticController::class, 'exportKeywordSearch'])->name('keyword.export.excel');
            Route::get('customer-export-excel', [AnalyticController::class, 'exportCustomerSearch'])->name('customer.export.excel');

        });

        Route::group(['prefix' => 'customer', 'as' => 'customer.','middleware'=>['module:user_management']], function () {
            Route::get('list', [CustomerController::class, 'list'])->name('list');
            Route::get('view/{user_id}', [CustomerController::class, 'view'])->name('view');
            Route::post('search', [CustomerController::class, 'search'])->name('search');
            Route::get('subscribed-emails', [CustomerController::class, 'subscribedEmails'])->name('subscribed_emails');
            Route::delete('delete/{id}', [CustomerController::class, 'delete'])->name('delete');
            Route::get('status/{id}/{status}', [CustomerController::class, 'status'])->name('status');
            Route::get('export', [CustomerController::class, 'exportCustomer'])->name('export');

            Route::get('select-list', [CustomerWalletController::class, 'getCustomers'])->name('select-list');
            Route::group(['prefix' => 'wallet', 'as' => 'wallet.'], function () {
                Route::get('add-fund', [CustomerWalletController::class, 'addFundView'])->name('add-fund');
                Route::post('add-fund', [CustomerWalletController::class, 'addFund'])->name('add-fund-store');
                Route::get('report', [CustomerWalletController::class, 'report'])->name('report');

                Route::group(['prefix' => 'bonus', 'as' => 'bonus.'], function () {
                    Route::get('index', [WalletBonusController::class, 'index'])->name('index');
                    Route::post('store',  [WalletBonusController::class, 'store'])->name('store');
                    Route::get('edit/{id}',  [WalletBonusController::class, 'edit'])->name('edit');
                    Route::post('update/{id}',  [WalletBonusController::class, 'update'])->name('update');
                    Route::get('status/{id}/{status}',  [WalletBonusController::class, 'status'])->name('status');
                    Route::delete('delete/{id}',  [WalletBonusController::class, 'delete'])->name('delete');
                });
            });

            Route::get('loyalty-point/report', [LoyaltyPointController::class, 'report'])->name('loyalty-point.report');
        });

        Route::group(['prefix' => 'offer', 'as' => 'offer.'], function () {
            Route::get('flash-index', [OfferController::class, 'flashIndex'])->name('flash.index');
            Route::post('flash-store', [OfferController::class, 'flashStore'])->name('flash.store');
            Route::get('flash/edit/{id}', [OfferController::class, 'flashEdit'])->name('flash.edit');
            Route::post('flash/update/{id}', [OfferController::class, 'flashUpdate'])->name('flash.update');
            Route::get('flash/status/{id}/{status}', [OfferController::class, 'status'])->name('flash.status');
            Route::delete('flash/delete/{id}', [OfferController::class, 'delete'])->name('flash.delete');
            Route::get('flash/add-product/{flash_deal_id}', [OfferController::class, 'addFlashSaleProduct'])->name('flash.add-product');
            Route::post('flash/add-product/{flash_deal_id}', [OfferController::class, 'flashProductStore'])->name('flash.add-product.store');
            Route::post('flash/delete-product', [OfferController::class, 'deleteFlashProduct'])->name('flash.delete.product');
        });

        Route::group(['namespace' => 'System','prefix' => 'system-addon', 'as' => 'system-addon.'], function () {
            Route::get('/', [AddonController::class, 'index'])->name('index');
            Route::post('publish', [AddonController::class, 'publish'])->name('publish');
            Route::post('activation', [AddonController::class, 'activation'])->name('activation');
            Route::post('upload', [AddonController::class, 'upload'])->name('upload');
            Route::post('delete', [AddonController::class, 'deleteAddon'])->name('delete');
        });


        Route::get('verify-offline-payment/quick-view-details', [OfflinePaymentMethodController::class, 'quickViewDetails'])->name('offline-modal-view');
        Route::get('verify-offline-payment/{status}', [OfflinePaymentMethodController::class, 'offlinePaymentList'])->name('verify-offline-payment');

    });
});
