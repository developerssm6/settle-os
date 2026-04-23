<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Root → login
Route::get('/', fn () => redirect()->route('login'));

// Admin routes (role guard added in M3 once spatie/permission middleware is wired)
Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', fn () => Inertia::render('Admin/Dashboard', [
        'stats' => [
            'policies_this_month' => 0,
            'commission_calculated' => '₹0.00',
            'pending_payouts' => 0,
            'active_partners' => 0,
        ],
    ]))->name('dashboard');

    // Placeholder routes — controllers wired in M3
    Route::get('/partners',         fn () => Inertia::render('Admin/Partners/Index'))->name('partners.index');
    Route::get('/policies',         fn () => Inertia::render('Admin/Policies/Index'))->name('policies.index');
    Route::get('/payouts',          fn () => Inertia::render('Admin/Payouts/Index'))->name('payouts.index');
    Route::get('/commission-rates', fn () => Inertia::render('Admin/CommissionRates/Index'))->name('commission-rates.index');
    Route::get('/insurers',         fn () => Inertia::render('Admin/Insurers/Index'))->name('insurers.index');
    Route::get('/taxonomy',         fn () => Inertia::render('Admin/Taxonomy/Index'))->name('taxonomy.index');
    Route::get('/reports',          fn () => Inertia::render('Admin/Reports/Index'))->name('reports.index');
    Route::get('/settings',         fn () => Inertia::render('Admin/Settings/Index'))->name('settings.index');
});

// Partner routes
Route::middleware(['auth', 'verified'])->prefix('partner')->name('partner.')->group(function () {
    Route::get('/dashboard', fn () => Inertia::render('Partner/Dashboard', [
        'stats' => [
            'policies_this_month' => 0,
            'commission_this_month' => '₹0.00',
            'unpaid_commission' => '₹0.00',
            'tds_ytd' => '₹0.00',
        ],
    ]))->name('dashboard');

    Route::get('/policies', fn () => Inertia::render('Partner/Policies/Index'))->name('policies.index');
    Route::get('/payouts',  fn () => Inertia::render('Partner/Payouts/Index'))->name('payouts.index');
    Route::get('/reports',  fn () => Inertia::render('Partner/Reports/Index'))->name('reports.index');
    Route::get('/profile',  fn () => Inertia::render('Partner/Profile/Edit'))->name('profile.edit');
});

require __DIR__.'/auth.php';
