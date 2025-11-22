<?php
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\API\ReportController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/featured', [ProductController::class, 'featured']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);
Route::post('/midtrans/notification', [OrderController::class, 'midtransCallback']);
Route::get('/laporan/stok/export/excel', [ReportController::class, 'exportExcel']);
Route::get('/laporan/stok/export/pdf', [ReportController::class, 'exportPDF']);
Route::get('/laporan/outgoing/pdf/online', [ReportController::class, 'exportOutgoingPDF']);
// --- Export Semua Transaksi Keluar ---
Route::get('outgoing/all/export/excel', [ReportController::class, 'exportAllOutgoingExcel']);
Route::get('outgoing/all/export/pdf', [ReportController::class, 'exportAllOutgoingPDF']);

// --- Export Transaksi Keluar Offline ---
Route::get('outgoing/offline/export/excel', [ReportController::class, 'exportOfflineOutgoingExcel']);
Route::get('outgoing/offline/export/pdf', [ReportController::class, 'exportOfflineOutgoingPDF']);

// --- Export Transaksi Keluar Online ---
Route::get('outgoing/online/export/excel', [ReportController::class, 'exportOnlineOutgoingExcel']);
Route::get('outgoing/online/export/pdf', [ReportController::class, 'exportOnlineOutgoingPDF']);

// Route User
Route::middleware('auth:sanctum')->group(function () {
    // Profile
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Order
    Route::post('/checkout', [OrderController::class, 'checkout']);
    Route::get('/orders', [OrderController::class, 'myOrders']);
    Route::get('/orders/{id}', [OrderController::class, 'showOrder']);
    Route::post('/transactions/outgoing/online/{orderId}', [TransactionController::class, 'confirmDelivery']);

});


// Route Admin
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // Dashboard
    Route::get('/dashboard/metrics', [DashboardController::class, 'getSalesMetrics']);
    Route::get('/dashboard/latest-sales', [DashboardController::class, 'getLatestSales']);
    Route::get('/dashboard/revenue-trend', [DashboardController::class, 'getRevenueTrend']);

    // Kategori
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
    
    // Produk
    Route::get('/allProducts', [ProductController::class, 'adminIndex']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    Route::patch('/products/{id}/toggle-active', [ProductController::class, 'toggleIsActive']);
    Route::patch('/products/{id}/toggle-featured', [ProductController::class, 'toggleIsFeatured']);

    // Transaksi Masuk
    Route::get('/transactions/incoming', [TransactionController::class, 'indexIncoming']);
    Route::post('/transactions/incoming', [TransactionController::class, 'storeIncoming']);
    Route::put('/transactions/incoming/{id}', [TransactionController::class, 'updateIncomingStock']);
    Route::delete('/transactions/incoming/{id}', [TransactionController::class, 'deleteIncoming']);

    // Transaksi Keluar Offline
    Route::get('/transactions/outgoing/offline', [TransactionController::class, 'indexOutgoingOffline']);
    Route::post('/transactions/outgoing/offline', [TransactionController::class, 'storeOutgoingOffline']);
    Route::put('/transactions/outgoing/offline/{id}', [TransactionController::class, 'updateOutgoingOffline']);
    Route::delete('/transactions/outgoing/offline/{id}', [TransactionController::class, 'deleteOutgoingOffline']);

    // Transaksi Keluar Online
    Route::get('/transactions/outgoing/online', [TransactionController::class, 'indexOutgoing']);
    Route::get('/transactions/outgoing/online/pending', [OrderController::class, 'allOrders']);
    Route::put('/transactions/outgoing/online/{id}', [TransactionController::class, 'updateShippingStatus']);

    // Laporan
    Route::get('/laporan/stok', [ReportController::class, 'stockReport']);
    
   
});