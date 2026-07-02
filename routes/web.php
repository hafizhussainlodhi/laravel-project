<?php

use App\Http\Controllers\AuthController;
use App\Models\Transaction;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->to(config('nova.path') . '/login');
});
Route::get('/login', function () {
    return redirect()->to(config('nova.path') . '/login');
})
    ->name('login');

Route::post('/register/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/register/resend-otp', [AuthController::class, 'resendOtp']);
Route::post('/register/verify', [AuthController::class, 'verifyOtpAndRegister']);
Route::get('/dashboard/register', [AuthController::class, 'showRegister']);
