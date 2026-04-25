<?php

namespace App\Console\Commands;

use App\Actions\Invoicing\GenerateInvoice;
use App\Actions\Payout\CalculatePayout;
use App\Actions\Payout\ProcessPayouts;
use App\Enums\BusinessTypeSlug;
use App\Enums\PayoutStatus;
use App\Enums\PolicyStatus;
use App\Enums\TaxAppliesTo;
use App\Models\Payout;
use App\Models\Policy;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\SeedDefaultSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Stancl\Tenancy\Database\Models\Domain;

#[Signature('app:demo-data {--fresh : Drop and recreate the demo tenant before seeding}')]
#[Description('Populate central + a demo tenant database with realistic sample data')]
class PopulateDemoData extends Command
{
    private const TENANT_ID = 'acme';

    private const TENANT_DOMAIN = 'acme.localhost';

    public function handle(): int
    {
        $admin = $this->ensureCentralUser('admin@settle.test', 'Demo Admin');
        $partnerUsers = [
            $this->ensureCentralUser('partner1@settle.test', 'Anita Kapoor'),
            $this->ensureCentralUser('partner2@settle.test', 'Ravi Mehta'),
        ];

        $tenant = $this->ensureTenant();

        tenancy()->initialize($tenant);
        try {
            $this->seedTenant($partnerUsers);
        } finally {
            tenancy()->end();
        }

        $this->info('Demo data ready.');
        $this->line("  Central admin: {$admin->email} / password");
        foreach ($partnerUsers as $user) {
            $this->line("  Partner:       {$user->email} / password");
        }
        $this->line('  Tenant id:     '.self::TENANT_ID);
        $this->line('  Tenant domain: '.self::TENANT_DOMAIN.' (add to /etc/hosts to access)');

        return self::SUCCESS;
    }

    private function ensureCentralUser(string $email, string $name): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
    }

    private function ensureTenant(): Tenant
    {
        if ($this->option('fresh')) {
            $existing = Tenant::find(self::TENANT_ID);
            if ($existing) {
                $this->warn('Dropping existing tenant '.self::TENANT_ID.' (and its database)…');
                $existing->delete();
            }
        }

        $tenant = Tenant::firstOrCreate(
            ['id' => self::TENANT_ID],
            [
                'name' => 'ACME Insurance Brokers',
                'plan' => 'standard',
                'is_active' => true,
                'region' => 'IN',
                'timezone' => 'Asia/Kolkata',
                'default_currency' => 'INR',
                'default_locale' => 'en_IN',
                'tax_regime_slug' => 'IN_GST',
            ]
        );

        Domain::firstOrCreate(
            ['domain' => self::TENANT_DOMAIN],
            ['tenant_id' => $tenant->id]
        );

        return $tenant;
    }

    /**
     * @param  array<int, User>  $partnerUsers
     */
    private function seedTenant(array $partnerUsers): void
    {
        if (DB::table('taxonomy_terms')->exists()) {
            $this->warn('Tenant already populated. Re-run with --fresh to reset.');

            return;
        }

        $now = now();
        $today = Carbon::today();

        (new SeedDefaultSettings)->run();

        $taxonomy = $this->seedTaxonomy($now);
        $insurers = $this->seedInsurers($now);
        $issuerId = $this->seedInvoiceIssuerAndSequence($today, $now);
        $this->seedTaxRules($now);

        $partnerProfileIds = $this->seedPartnerProfiles($partnerUsers, $today, $now);
        $customerIds = $this->seedCustomers($now);

        $this->seedCommissionRates($insurers, $taxonomy, $partnerProfileIds);
        $policies = $this->seedPolicies($insurers, $taxonomy, $partnerProfileIds, $customerIds, $today, $now);

        $this->driveEngine($policies);

        $this->info('  Issuer prefix: '.config('mis.defaults.invoice.prefix.value', 'INV').' (FY '.($today->month >= 4 ? $today->year : $today->year - 1).')');
        $this->info('  Seeded '.count($policies).' policies, '.count($partnerProfileIds).' partners, '.count($customerIds).' customers.');
    }

    /**
     * Run the engine end-to-end across the seeded policies:
     * - calculate every policy's payout
     * - process the first two (mark as `processed`)
     * - generate an invoice for one processed payout
     *
     * @param  array<int, array<string, mixed>>  $policies
     */
    private function driveEngine(array $policies): void
    {
        foreach ($policies as $p) {
            $policy = Policy::query()->findOrFail($p['id']);
            CalculatePayout::run($policy);
        }

        $toProcess = Payout::query()
            ->where('status', PayoutStatus::Calculated->value)
            ->limit(2)
            ->get();
        ProcessPayouts::run($toProcess);

        $invoiced = Payout::query()
            ->where('status', PayoutStatus::Processed->value)
            ->first();
        if ($invoiced !== null) {
            GenerateInvoice::run($invoiced);
        }
    }

    /**
     * @return array<string, array<string, int>> [type => [slug => id]]
     */
    private function seedTaxonomy(Carbon $now): array
    {
        $defs = [
            'business_type' => ['motor', 'non_motor', 'health'],
            'vehicle_type' => ['two_wheeler', 'four_wheeler', 'commercial_vehicle'],
            'coverage_type' => ['od_only', 'tp_only', 'comprehensive'],
            'vehicle_age' => ['0_to_1', '1_to_3', '3_plus'],
            'fuel_type' => ['petrol', 'diesel', 'cng', 'electric'],
            'policy_type' => ['comprehensive', 'third_party_only', 'standalone_od'],
        ];

        $rows = [];
        foreach ($defs as $type => $slugs) {
            foreach ($slugs as $i => $slug) {
                $rows[] = [
                    'type' => $type,
                    'name' => str($slug)->replace('_', ' ')->title()->toString(),
                    'slug' => $slug,
                    'parent_id' => null,
                    'meta' => null,
                    'is_active' => true,
                    'sort_order' => $i,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        DB::table('taxonomy_terms')->insert($rows);

        $map = [];
        foreach (DB::table('taxonomy_terms')->get(['id', 'type', 'slug']) as $r) {
            $map[$r->type][$r->slug] = (int) $r->id;
        }

        return $map;
    }

    /**
     * @return array<string, int> [slug => id]
     */
    private function seedInsurers(Carbon $now): array
    {
        $defs = [
            ['Bajaj Allianz General Insurance', 'bajaj_allianz', 'BAJAJ'],
            ['HDFC Ergo General Insurance', 'hdfc_ergo', 'HDFC'],
            ['ICICI Lombard General Insurance', 'icici_lombard', 'ICICI'],
        ];

        $rows = array_map(fn ($d) => [
            'name' => $d[0],
            'slug' => $d[1],
            'code' => $d[2],
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ], $defs);

        DB::table('insurers')->insert($rows);

        return DB::table('insurers')->pluck('id', 'slug')->map(fn ($v) => (int) $v)->toArray();
    }

    private function seedInvoiceIssuerAndSequence(Carbon $today, Carbon $now): int
    {
        $issuerId = (int) DB::table('invoice_issuers')->insertGetId([
            'name' => 'SettleOS Demo Pvt Ltd',
            'address' => '12, MG Road, Bengaluru, Karnataka 560001, India',
            'state_code' => 'KA',
            'currency_code' => 'INR',
            'pan' => null,
            'gstin' => null,
            'tan' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('invoice_sequences')->insert([
            'issuer_id' => $issuerId,
            'prefix' => 'INV',
            'fiscal_year' => $today->month >= 4 ? $today->year : $today->year - 1,
            'next_value' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $issuerId;
    }

    private function seedTaxRules(Carbon $now): void
    {
        // Per-payout TDS threshold is set to 0 in the demo so deductions are
        // visible. The real Sec 194D threshold (₹15,000 YTD) is enforced by
        // the YTD-aggregating calculator scheduled for M5 reporting.
        $rules = [
            ['TDS_194D_IND', 'tds', 'IN', TaxAppliesTo::NetCommission->value, 0.05000, 0, ['business_type' => ['individual', 'huf', 'proprietor']]],
            ['TDS_194D_CO', 'tds', 'IN', TaxAppliesTo::NetCommission->value, 0.10000, 0, ['business_type' => ['private_ltd', 'public_ltd', 'llp', 'partnership']]],
            ['CGST_997161', 'gst', 'IN', TaxAppliesTo::NetCommission->value, 0.09000, 0, ['intra_state' => true]],
            ['SGST_997161', 'gst', 'IN', TaxAppliesTo::NetCommission->value, 0.09000, 0, ['intra_state' => true]],
            ['IGST_997161', 'gst', 'IN', TaxAppliesTo::NetCommission->value, 0.18000, 0, ['intra_state' => false]],
        ];

        // effective_range starts a year ago so backfilled demo policies match.
        $effectiveFrom = Carbon::today()->subYear()->toDateString();

        foreach ($rules as [$code, $taxType, $jurisdiction, $appliesTo, $rate, $threshold, $conditions]) {
            $id = (int) DB::table('tax_rules')->insertGetId([
                'code' => $code,
                'tax_type' => $taxType,
                'jurisdiction' => $jurisdiction,
                'applies_to' => $appliesTo,
                'rate' => $rate,
                'annual_threshold' => $threshold,
                'conditions' => json_encode($conditions),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::statement(
                'UPDATE tax_rules SET effective_range = daterange(?::date, \'infinity\') WHERE id = ?',
                [$effectiveFrom, $id],
            );
        }
    }

    /**
     * @param  array<int, User>  $users
     * @return array<int, int> partner_profile ids ordered by $users
     */
    private function seedPartnerProfiles(array $users, Carbon $today, Carbon $now): array
    {
        $defs = [
            [BusinessTypeSlug::Individual->value, 'KA', true],
            [BusinessTypeSlug::PrivateLtd->value, 'MH', false],
        ];

        $ids = [];
        foreach ($users as $i => $user) {
            $ids[] = (int) DB::table('partner_profiles')->insertGetId([
                'user_id' => $user->id,
                'code' => sprintf('PTR-%04d', $i + 1),
                'display_name' => $user->name,
                'business_type' => $defs[$i][0],
                'state_code' => $defs[$i][1],
                'pan' => null,
                'pan_hash' => null,
                'gstin' => null,
                'tan' => null,
                'is_gst_registered' => $defs[$i][2],
                'bank_account_number' => null,
                'bank_ifsc' => null,
                'bank_account_name' => null,
                'bank_name' => null,
                'is_active' => true,
                'onboarded_on' => $today->copy()->subMonths(3)->toDateString(),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
        }

        return $ids;
    }

    /**
     * @return array<int, int> customer ids
     */
    private function seedCustomers(Carbon $now): array
    {
        $names = [
            ['Priya Sharma', 'priya.sharma@example.com', '+91-9876500001'],
            ['Arjun Verma', 'arjun.verma@example.com', '+91-9876500002'],
            ['Neha Iyer', 'neha.iyer@example.com', '+91-9876500003'],
            ['Karan Singh', 'karan.singh@example.com', '+91-9876500004'],
            ['Meera Joshi', 'meera.joshi@example.com', '+91-9876500005'],
        ];

        $ids = [];
        foreach ($names as $n) {
            $ids[] = (int) DB::table('customers')->insertGetId([
                'name' => $n[0],
                'email' => $n[1],
                'phone' => $n[2],
                'dob' => null,
                'address' => null,
                'pan' => null,
                'pan_hash' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
        }

        return $ids;
    }

    /**
     * @param  array<string, int>  $insurers
     * @param  array<string, array<string, int>>  $taxonomy
     * @param  array<int, int>  $partnerProfileIds
     * @return array<string, int> policy_dim_key => commission_rate_id
     */
    private function seedCommissionRates(array $insurers, array $taxonomy, array $partnerProfileIds): array
    {
        $motorBT = $taxonomy['business_type']['motor'];
        $nonMotorBT = $taxonomy['business_type']['non_motor'];
        $healthBT = $taxonomy['business_type']['health'];
        $fourWheeler = $taxonomy['vehicle_type']['four_wheeler'];
        $twoWheeler = $taxonomy['vehicle_type']['two_wheeler'];

        $rateRows = [
            // Global motor — four wheeler comprehensive @ Bajaj
            ['insurer_id' => $insurers['bajaj_allianz'], 'business_type_id' => $motorBT, 'partner_id' => null, 'vehicle_type_id' => $fourWheeler, 'vehicle_attrs' => null, 'od_percent' => 12.500, 'tp_percent' => 2.500, 'net_percent' => 0, 'flat_amount' => 0, 'key' => 'motor_4w_bajaj'],
            // Global motor — two wheeler @ HDFC
            ['insurer_id' => $insurers['hdfc_ergo'], 'business_type_id' => $motorBT, 'partner_id' => null, 'vehicle_type_id' => $twoWheeler, 'vehicle_attrs' => null, 'od_percent' => 18.000, 'tp_percent' => 2.000, 'net_percent' => 0, 'flat_amount' => 0, 'key' => 'motor_2w_hdfc'],
            // Partner override — Anita gets a richer four-wheeler rate at Bajaj
            ['insurer_id' => $insurers['bajaj_allianz'], 'business_type_id' => $motorBT, 'partner_id' => $partnerProfileIds[0], 'vehicle_type_id' => $fourWheeler, 'vehicle_attrs' => null, 'od_percent' => 14.000, 'tp_percent' => 2.500, 'net_percent' => 0, 'flat_amount' => 0, 'key' => 'motor_4w_bajaj_anita'],
            // Global non-motor flat @ ICICI
            ['insurer_id' => $insurers['icici_lombard'], 'business_type_id' => $nonMotorBT, 'partner_id' => null, 'vehicle_type_id' => null, 'vehicle_attrs' => null, 'od_percent' => 0, 'tp_percent' => 0, 'net_percent' => 15.000, 'flat_amount' => 0, 'key' => 'nonmotor_icici'],
            // Global health flat @ HDFC
            ['insurer_id' => $insurers['hdfc_ergo'], 'business_type_id' => $healthBT, 'partner_id' => null, 'vehicle_type_id' => null, 'vehicle_attrs' => null, 'od_percent' => 0, 'tp_percent' => 0, 'net_percent' => 12.500, 'flat_amount' => 0, 'key' => 'health_hdfc'],
        ];

        // Effective range starts a year ago so backfilled demo policies match.
        $effectiveFrom = Carbon::today()->subYear()->toDateString();

        $map = [];
        foreach ($rateRows as $r) {
            $key = $r['key'];
            unset($r['key']);
            $r['currency_code'] = 'INR';
            $r['vehicle_attrs'] = $r['vehicle_attrs'] !== null ? json_encode($r['vehicle_attrs']) : null;
            $r['created_at'] = now();
            $r['updated_at'] = now();
            $id = (int) DB::table('commission_rates')->insertGetId($r);

            DB::statement(
                'UPDATE commission_rates SET effective_range = daterange(?::date, \'infinity\') WHERE id = ?',
                [$effectiveFrom, $id],
            );

            $map[$key] = $id;
        }

        return $map;
    }

    /**
     * @param  array<string, int>  $insurers
     * @param  array<string, array<string, int>>  $taxonomy
     * @param  array<int, int>  $partnerProfileIds
     * @param  array<int, int>  $customerIds
     * @return array<int, array<string, mixed>> policies metadata for payout step
     */
    private function seedPolicies(array $insurers, array $taxonomy, array $partnerProfileIds, array $customerIds, Carbon $today, Carbon $now): array
    {
        $motorBT = $taxonomy['business_type']['motor'];
        $nonMotorBT = $taxonomy['business_type']['non_motor'];
        $healthBT = $taxonomy['business_type']['health'];

        $defs = [
            // [partner_idx, insurer_slug, business_type_id, business_label, premium, sum_insured, status, days_offset_start, term_days, motor_attrs|null]
            [0, 'bajaj_allianz', $motorBT, 'motor', 18450.00, 700000, PolicyStatus::Active, -30, 365, ['vehicle_type' => 'four_wheeler', 'coverage' => 'comprehensive', 'age' => '1_to_3', 'fuel' => 'petrol', 'reg' => 'KA-01-AB-1234', 'rate_key' => 'motor_4w_bajaj_anita']],
            [0, 'hdfc_ergo', $motorBT, 'motor', 4200.00, 90000, PolicyStatus::Active, -10, 365, ['vehicle_type' => 'two_wheeler', 'coverage' => 'comprehensive', 'age' => '0_to_1', 'fuel' => 'petrol', 'reg' => 'KA-03-XY-5678', 'rate_key' => 'motor_2w_hdfc']],
            [1, 'bajaj_allianz', $motorBT, 'motor', 22650.00, 950000, PolicyStatus::Active, -45, 365, ['vehicle_type' => 'four_wheeler', 'coverage' => 'comprehensive', 'age' => '3_plus', 'fuel' => 'diesel', 'reg' => 'MH-12-PQ-9988', 'rate_key' => 'motor_4w_bajaj']],
            [1, 'hdfc_ergo', $motorBT, 'motor', 3800.00, 75000, PolicyStatus::Active, -5, 365, ['vehicle_type' => 'two_wheeler', 'coverage' => 'comprehensive', 'age' => '1_to_3', 'fuel' => 'electric', 'reg' => 'MH-14-EV-2244', 'rate_key' => 'motor_2w_hdfc']],
            [0, 'icici_lombard', $nonMotorBT, 'non_motor', 12500.00, 1500000, PolicyStatus::Active, -60, 365, null],
            [1, 'icici_lombard', $nonMotorBT, 'non_motor', 9800.00, 800000, PolicyStatus::Active, -20, 365, null],
            [0, 'hdfc_ergo', $healthBT, 'health', 16400.00, 500000, PolicyStatus::Active, -90, 365, null],
            [1, 'hdfc_ergo', $healthBT, 'health', 24300.00, 1000000, PolicyStatus::Upcoming, 5, 365, null],
        ];

        $records = [];
        foreach ($defs as $i => $d) {
            $partnerId = $partnerProfileIds[$d[0]];
            $insurerId = $insurers[$d[1]];
            $btId = $d[2];
            $btLabel = $d[3];
            $premium = $d[4];
            $sumInsured = $d[5];
            $status = $d[6];
            $startDate = $today->copy()->addDays($d[7]);
            $endDate = $startDate->copy()->addDays($d[8]);
            $motor = $d[9];

            $policyId = (int) DB::table('policies')->insertGetId([
                'policy_number' => sprintf('POL-%s-%05d', $today->format('Y'), $i + 1),
                'partner_profile_id' => $partnerId,
                'insurer_id' => $insurerId,
                'customer_id' => $customerIds[$i % count($customerIds)],
                'business_type_id' => $btId,
                'premium' => $premium,
                'currency_code' => 'INR',
                'sum_insured' => $sumInsured,
                'policy_date' => $startDate->toDateString(),
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'status' => $status->value,
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);

            if ($motor !== null) {
                $od = round($premium * 0.75, 4);
                $tp = round($premium - $od, 4);
                DB::table('motor_details')->insert([
                    'policy_id' => $policyId,
                    'vehicle_type_id' => $taxonomy['vehicle_type'][$motor['vehicle_type']],
                    'coverage_type_id' => $taxonomy['coverage_type'][$motor['coverage']],
                    'vehicle_age_id' => $taxonomy['vehicle_age'][$motor['age']],
                    'fuel_type_id' => $taxonomy['fuel_type'][$motor['fuel']],
                    'vehicle_subtype_id' => null,
                    'engine_capacity_id' => null,
                    'seat_capacity_id' => null,
                    'weight_type_id' => null,
                    'vehicle_make_id' => null,
                    'own_damage' => $od,
                    'third_party' => $tp,
                    'registration_number' => $motor['reg'],
                    'vehicle_model' => null,
                    'manufacture_year' => $today->year - (int) explode('_', $motor['age'])[0],
                    'engine_number' => null,
                    'chassis_number' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } elseif ($btLabel === 'non_motor') {
                DB::table('non_motor_details')->insert([
                    'policy_id' => $policyId,
                    'sum_insured' => $sumInsured,
                    'meta' => json_encode(['line' => 'commercial', 'risk_grade' => 'B']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('health_details')->insert([
                    'policy_id' => $policyId,
                    'coverage_type_id' => $taxonomy['coverage_type']['comprehensive'],
                    'sum_insured' => $sumInsured,
                    'member_count' => 2,
                    'members' => json_encode([
                        ['name' => 'Self', 'relationship' => 'self'],
                        ['name' => 'Spouse', 'relationship' => 'spouse'],
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $records[] = [
                'id' => $policyId,
                'partner_profile_id' => $partnerId,
                'premium' => $premium,
                'business_label' => $btLabel,
                'rate_key' => $motor['rate_key']
                    ?? ($btLabel === 'non_motor' ? 'nonmotor_icici' : 'health_hdfc'),
                'motor' => $motor,
            ];
        }

        return $records;
    }
}
