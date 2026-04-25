<?php

use App\Services\Tax\Calculators\IndiaGstCalculator;
use App\Services\Tax\Calculators\IndiaTdsCalculator;

/*
|--------------------------------------------------------------------------
| MIS Application Defaults
|--------------------------------------------------------------------------
| These values are used as seeds when inserting into the `settings` table
| on first deploy. Live values are read from the DB via Setting::get().
| Only infrastructure/env-level keys that cannot be DB-driven live here.
*/

return [

    'reporting_currency' => env('FX_REPORTING_BASE', 'INR'),

    /*
     * Seed defaults — synced to `settings` table by SeedDefaultSettings seeder.
     * Do NOT read these directly in business logic; use Setting::get() instead.
     */
    'defaults' => [
        'tds.rate_individual' => ['value' => '0.05', 'type' => 'float',   'group' => 'tax',    'description' => 'TDS rate for individual/HUF partners (Section 194D)'],
        'tds.rate_company' => ['value' => '0.10', 'type' => 'float',   'group' => 'tax',    'description' => 'TDS rate for company/LLP partners (Section 194D)'],
        'tds.threshold' => ['value' => '15000', 'type' => 'integer', 'group' => 'tax',    'description' => 'Annual TDS deduction threshold (INR)'],
        'gst.rate' => ['value' => '0.18', 'type' => 'float',   'group' => 'tax',    'description' => 'GST rate on insurance intermediary services (SAC 997161)'],
        'gst.cgst_rate' => ['value' => '0.09', 'type' => 'float',   'group' => 'tax',    'description' => 'CGST rate (intra-state)'],
        'gst.sgst_rate' => ['value' => '0.09', 'type' => 'float',   'group' => 'tax',    'description' => 'SGST rate (intra-state)'],
        'gst.igst_rate' => ['value' => '0.18', 'type' => 'float',   'group' => 'tax',    'description' => 'IGST rate (inter-state)'],
        'payout.min_amount' => ['value' => '100',  'type' => 'integer', 'group' => 'payout', 'description' => 'Minimum payout amount (INR)'],
        'payout.cutoff_day' => ['value' => '25',   'type' => 'integer', 'group' => 'payout', 'description' => 'Day of month after which payouts are held to next cycle'],
        'invoice.prefix' => ['value' => 'INV',  'type' => 'string',  'group' => 'invoice', 'description' => 'Invoice number prefix'],
        'invoice.fy_start_month' => ['value' => '4', 'type' => 'integer', 'group' => 'invoice', 'description' => 'Financial year start month (4 = April)'],
    ],

    /*
     * Strategy registry for the tax engine. The TaxCalculatorRegistry walks
     * this list and asks each calculator's supports() whether it handles a
     * given TaxRule. Order matters when multiple calculators could match.
     */
    'tax' => [
        'strategies' => [
            'india_tds' => IndiaTdsCalculator::class,
            'india_gst' => IndiaGstCalculator::class,
        ],
    ],

];
