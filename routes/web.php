<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Api\V1Controller;
use App\Http\Controllers\MercadoPagoController;
use App\Http\Controllers\Website;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Site público
|--------------------------------------------------------------------------
*/
Route::get('/', [Website\HomeController::class, 'index']);
Route::get('/terms-of-service', [Website\HomeController::class, 'terms']);
Route::get('/privacy-policy', [Website\HomeController::class, 'privacy']);
Route::get('/sortitionListAll', [Website\HomeController::class, 'listAll']);
Route::get('/sortitionList/{categorie}', fn () => '<h1>EM CONSTRUÇÃO</h1>');

Route::get('/share/{shareCode}', [Website\SharedController::class, 'show']);
Route::get('/checkout/{shareCode}/{idCustomer}', [Website\SharedController::class, 'numbersOfCustomer'])->whereNumber('idCustomer');
Route::post('/customer/fastRegister', [Website\CustomerController::class, 'fastRegister']);
Route::get('/website/logout', [Website\CustomerController::class, 'logout']);

Route::get('/checkoutFinish/{shareCode}', [Website\CheckoutController::class, 'finish']);
Route::get('/getMercadoPagoCheckOut/{shareCode}', [Website\CheckoutController::class, 'mercadoPago'])->middleware('auth:website');

/*
|--------------------------------------------------------------------------
| Painel do dono da rifa (/admin)
|--------------------------------------------------------------------------
*/
Route::middleware('guest:admin')->group(function () {
    Route::get('/admin/login', [Admin\LoginController::class, 'show']);
    Route::post('/admin/login', [Admin\LoginController::class, 'login']);
    Route::post('/newCustomer', [Admin\LoginController::class, 'register']);
});
Route::post('/admin/login-google', [Admin\LoginController::class, 'loginGoogle']);
Route::get('/admin/account-recovery', [Admin\LoginController::class, 'accountRecovery']);
Route::get('/admin/logout', [Admin\LoginController::class, 'logout']);

// Retorno e webhook do Mercado Pago (liberação de rifas)
Route::get('/admin/payments/notificationMp', [MercadoPagoController::class, 'back']);
Route::post('/admin/payments/notificationMp', [MercadoPagoController::class, 'webhook']);

Route::middleware('auth:admin')->prefix('admin')->group(function () {
    Route::get('/', [Admin\HomeController::class, 'index']);

    // Configurações da conta
    Route::controller(Admin\CustomerController::class)->group(function () {
        Route::get('/customers', 'index');
        Route::get('/customer/profile', 'profile');
        Route::post('/customer/editProfile', 'updateProfile');
        Route::get('/customer/payment', 'payment');
        Route::post('/customer/setPayments', 'updatePayments');
        Route::get('/customer/social', 'social');
        Route::post('/customer/social', 'updateSocial');
        Route::get('/customer/notification', 'notification');
        Route::post('/customer/notification', 'updateNotification');
        Route::get('/customer/getPassword', 'password');
        Route::post('/customer/setPassword', 'updatePassword');
        Route::get('/customer/payments-history', 'paymentsHistory');
    });

    // Rifas
    Route::controller(Admin\RaffleController::class)->prefix('sortition')->group(function () {
        Route::get('/', 'index');
        Route::get('/create', 'create');
        Route::post('/create', 'store');
        Route::post('/delete', 'destroy');
        Route::post('/fastRegisterCustomersNumbers', 'assignNumbers');
        Route::get('/viewSortition/{shareCode}', 'preview');
        Route::get('/{raffleId}/edit', 'edit')->whereNumber('raffleId');
        Route::post('/{raffleId}/edit', 'update')->whereNumber('raffleId');
        Route::post('/{raffleId}/deleteImageSortition', 'deleteImage')->whereNumber('raffleId');
        Route::get('/{raffleId}/list', 'marked')->whereNumber('raffleId');
        Route::post('/{raffleId}/confirmNumbersCustomers', 'confirmNumbers')->whereNumber('raffleId');
        Route::post('/{raffleId}/cancelNumbersCustomers', 'cancelNumbers')->whereNumber('raffleId');
        Route::get('/{raffleId}/listSortition', 'manage')->whereNumber('raffleId');
        Route::get('/{raffleId}/winnerLottery', 'winners')->whereNumber('raffleId');
        Route::post('/{raffleId}/winnerLottery', 'addWinner')->whereNumber('raffleId');
        Route::get('/{raffleId}/winnerPreview', 'previewWinner')->whereNumber('raffleId');
        Route::post('/{raffleId}/deleteWinnerLottery/{index}', 'deleteWinner')->whereNumber(['raffleId', 'index']);
        Route::post('/{raffleId}/executeFinishLottery', 'finish')->whereNumber('raffleId');
    });

    // Pagamento da liberação da rifa
    Route::get('/{raffleId}/checkout', [Admin\CheckoutController::class, 'index'])->whereNumber('raffleId');
    Route::get('/{raffleId}/checkout/status', [Admin\CheckoutController::class, 'status'])->whereNumber('raffleId');
});

/*
|--------------------------------------------------------------------------
| API v1 (chamada pelo JavaScript das páginas, usa a sessão)
|--------------------------------------------------------------------------
*/
Route::prefix('api/v1')->group(function () {
    Route::get('/', [V1Controller::class, 'details']);
    Route::post('/auth', [V1Controller::class, 'token']);
    Route::get('/customers/getDataCustomers', [V1Controller::class, 'customerByPhone'])->middleware('auth:admin');
    Route::match(['get', 'post'], '/setNumberCustomer/{shareCode}/{idCustomer}', [V1Controller::class, 'toggleNumber'])->whereNumber('idCustomer');
    Route::post('/reserveNumbers/{shareCode}', [V1Controller::class, 'reserveNumbers']);
    Route::get('/getNumbersCustomer/{shareCode}/{idCustomer}', [V1Controller::class, 'numbersOfCustomer'])->whereNumber('idCustomer');
    Route::post('/sortition/newCodeShared', [Admin\RaffleController::class, 'updateCodeShare'])->middleware('auth:admin');
});
