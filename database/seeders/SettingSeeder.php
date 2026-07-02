<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = collect([
            [
                'name' => \App\Models\Setting::NUMBER_OF_DAYS_TO_EXPIRE,
                'value' => 5,
                'type' => \App\Models\Setting::TEXT,
            ],
            [
                'name' => \App\Models\Setting::ORDER_REFUND_TIME,
                'value' => 5,
                'type' => \App\Models\Setting::TEXT,
            ],  
            [
                'name' => \App\Models\Setting::ORDER_REFUNDED_BY_HOURS,
                'value' => 'false',
                'type' => \App\Models\Setting::TEXT,
            ],
        ]);
        $settings->each(function ($setting) {
            Setting::create($setting);
        }); 
    }
}
