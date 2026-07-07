<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Branch\Auth\LoginController;
use App\Http\Controllers\Branch\DashboardController;
use App\Http\Controllers\Branch\OrderController;
use App\Http\Controllers\Branch\POSController;
use App\Http\Controllers\Branch\SystemController;


Route::group(['namespace' => 'Branch', 'as' => 'branch.'], function () {
    Route::group(['namespace' => 'Auth', 'prefix' => 'auth', 'as' => 'auth.'], function () {
        Route::get('/code/captcha/{tmp}', [LoginController::class, 'captcha'])->name('default-captcha');
        Route::get('login', [LoginController::class, 'login'])->name('login');
        Route::post('login', [LoginController::class, 'submit']);
        Route::get('logout', [LoginController::class, 'logout'])->name('logout');
    });

    Route::group(['middleware' => ['branch', 'active_branch_check']], function () {
        Route::get('/', [DashboardController::class, 'dashboard'])->name('dashboard');
        Route::post('order-stats', [DashboardController::class, 'orderStats'])->name('order-stats');
        Route::get('dashboard/order-statistics', [DashboardController::class, 'getOrderStatistics'])->name('dashboard.order-statistics');
        Route::get('dashboard/earning-statistics', [DashboardController::class, 'getEarningStatistics'])->name('dashboard.earning-statistics');
        Route::get('settings', [SystemController::class, 'settings'])->name('settings');
        Route::post('settings', [SystemController::class, 'settingsUpdate']);
        Route::post('settings-password', [SystemController::class, 'settingsPasswordUpdate'])->name('settings-password');
        Route::get('/get-restaurant-data', [SystemController::class, 'businessData'])->name('get-restaurant-data');

        Route::group(['prefix' => 'pos', 'as' => 'pos.'], function () {
            Route::get('/', [POSController::class, 'index'])->name('index');
            Route::get('quick-view', [POSController::class, 'quickView'])->name('quick-view');
            Route::post('variant_price', [POSController::class, 'variantPrice'])->name('variant_price');
            Route::post('add-to-cart', [POSController::class, 'addToCart'])->name('add-to-cart');
            Route::post('remove-from-cart', [POSController::class, 'removeFromCart'])->name('remove-from-cart');
            Route::post('cart-items', [POSController::class, 'cartItems'])->name('cart_items');
            Route::post('update-quantity', [POSController::class, 'updateQuantity'])->name('updateQuantity');
            Route::post('empty-cart', [POSController::class, 'emptyCart'])->name('emptyCart');
            Route::post('tax', [POSController::class, 'updateTax'])->name('tax');
            Route::post('discount', [POSController::class, 'update_discount'])->name('discount');
            Route::get('customers', [POSController::class, 'getCustomers'])->name('customers');
            Route::post('order', [POSController::class, 'place_order'])->name('order');
            Route::get('orders', [POSController::class, 'order_list'])->name('orders');
            Route::get('order-details/{id}', [POSController::class, 'order_details'])->name('order-details');
            Route::get('invoice/{id}', [POSController::class, 'generateInvoice']);
            Route::any('store-keys', [POSController::class, 'storeKeys'])->name('store-keys');
            Route::any('customer/store', [POSController::class, 'newCustomerStore'])->name('customer.store');
            Route::get('orders/export', [POSController::class, 'exportOrders'])->name('orders.export');
            Route::post('add-delivery-address', [POSController::class, 'addDeliveryInfo'])->name('add-delivery-address');
            Route::get('get-distance', [POSController::class, 'getDistance'])->name('get-distance');
            Route::post('order_type/store', [POSController::class, 'orderTypeStore'])->name('order_type.store');
        });

        Route::group(['prefix' => 'orders', 'as' => 'orders.'], function () {
            Route::get('list/{status}', [OrderController::class, 'list'])->name('list');
            Route::get('details/{id}', [OrderController::class, 'details'])->name('details');
            Route::get('status', [OrderController::class, 'status'])->name('status');
            Route::get('add-delivery-man/{order_id}/{delivery_man_id}', [OrderController::class, 'addDeliveryman'])->name('add-delivery-man');
            Route::get('payment-status', [OrderController::class, 'paymentStatus'])->name('payment-status');
            Route::get('generate-invoice/{id}', [OrderController::class, 'generateInvoice'])->name('generate-invoice');
            Route::post('add-payment-ref-code/{id}', [OrderController::class, 'addPaymentReferenceCode'])->name('add-payment-ref-code');
            Route::get('export/{status}', [OrderController::class, 'exportOrders'])->name('export');
            Route::get('verify-offline-payment/{order_id}/{status}', [OrderController::class, 'VerifyOfflinePayment']);
        });

        Route::group(['prefix' => 'order', 'as' => 'order.'], function () {
            Route::get('list/{status}', [OrderController::class, 'list'])->name('list');
            Route::put('status-update/{id}', [OrderController::class, 'status'])->name('status-update');
            Route::post('update-shipping/{id}', [OrderController::class, 'updateShipping'])->name('update-shipping');
            Route::post('update-timeSlot', [OrderController::class, 'updateTimeSlot'])->name('update-timeSlot');
            Route::post('update-deliveryDate', [OrderController::class, 'updateDeliveryDate'])->name('update-deliveryDate');
        });

        Route::get('verify-offline-payment/quick-view-details', [OrderController::class, 'offlineQuickViewDetails'])->name('offline-modal-view');
        Route::get('verify-offline-payment/{status}', [OrderController::class, 'offlinePaymentList'])->name('verify-offline-payment');

    });
});
