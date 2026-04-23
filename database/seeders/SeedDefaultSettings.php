<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SeedDefaultSettings extends Seeder
{
    public function run(): void
    {
        foreach (config('mis.defaults', []) as $key => $meta) {
            Setting::firstOrCreate(
                ['key' => $key],
                [
                    'value' => $meta['value'],
                    'type' => $meta['type'],
                    'group' => $meta['group'],
                    'description' => $meta['description'],
                ]
            );
        }
    }
}
