<?php

namespace Database\Seeders;

use App\Domain\Shipping\Models\District;
use App\Domain\Shipping\Models\Thana;
use Illuminate\Database\Seeder;

/**
 * Upazilas from the open bangladesh-geocode dataset plus Dhaka metro thanas, keyed by district name_en.
 * Additive: admin edits and additions are kept on re-seed.
 */
class ThanaSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path('data/thanas.json')), true);
        $districts = District::pluck('id', 'name_en');

        foreach ($data as $district => $thanas) {
            if (! $districtId = $districts[$district] ?? null) {
                continue;
            }

            foreach ($thanas as [$name, $nameEn]) {
                Thana::withoutEvents(fn () => Thana::firstOrCreate(
                    ['district_id' => $districtId, 'name_en' => $nameEn],
                    ['name' => $name],
                ));
            }
        }
    }
}
