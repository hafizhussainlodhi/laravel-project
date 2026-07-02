<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{

    public function run()
    {

        City::truncate();
        $file = fopen(database_path('data/uscities.csv'), 'r');

        fgetcsv($file); // skip header

        $batch = [];
        $uniqueCities = []; // 👈 yeh important hai

        while (($row = fgetcsv($file)) !== false) {

            $cityName = trim($row[0]);

            // skip agar already aa chuka hai
            if (isset($uniqueCities[strtolower($cityName)])) {
                continue;
            }

            $uniqueCities[strtolower($cityName)] = true;

            $batch[] = [
                'country_id' => 234,
                'name' => $cityName,
                'is_active' => true,
            ];

            if (count($batch) === 500) {
                City::insert($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            City::insert($batch);
        }

        fclose($file);
    }
}



// namespace Database\Seeders;

// use App\Models\City;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
// use Illuminate\Database\Seeder;

// class CitySeeder extends Seeder
// {
//     /**
//      * Run the database seeds.
//      */
//     public function run()
//     {
//         $cities = collect([
//             ['country_id' => 234, 'name' => 'New York', 'is_active' => true],
//             ['country_id' => 234, 'name' => 'Los Angeles', 'is_active' => true],
//             ['country_id' => 234, 'name' => 'Chicago', 'is_active' => true],
//             ['country_id' => 234, 'name' => 'Houston', 'is_active' => true],
//             ['country_id' => 234, 'name' => 'Phoenix', 'is_active' => true],
//             ['country_id' => 234, 'name' => 'Philadelphia', 'is_active' => true],
//             ['country_id' => 234, 'name' => 'San Antonio', 'is_active' => true],
//             ['country_id' => 234, 'name' => 'San Diego', 'is_active' => true],
//             ['country_id' => 234, 'name' => 'Dallas', 'is_active' => true],
//             ['country_id' => 234, 'name' => 'San Jose', 'is_active' => true],
//             // Add more cities as needed
//         ]);

//         $cities->each(fn($city) => City::create($city));
//     }

// }
