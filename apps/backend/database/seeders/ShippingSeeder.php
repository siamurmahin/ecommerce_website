<?php

namespace Database\Seeders;

use App\Domain\Shipping\Models\DeliveryZone;
use App\Domain\Shipping\Models\District;
use Illuminate\Database\Seeder;

/** Real reference data: the 64 districts and three starter delivery zones (charges editable in admin). */
class ShippingSeeder extends Seeder
{
    private const DISTRICTS = [
        ['ঢাকা', 'Dhaka', [
            ['ঢাকা', 'Dhaka'], ['ফরিদপুর', 'Faridpur'], ['গাজীপুর', 'Gazipur'], ['গোপালগঞ্জ', 'Gopalganj'],
            ['কিশোরগঞ্জ', 'Kishoreganj'], ['মাদারীপুর', 'Madaripur'], ['মানিকগঞ্জ', 'Manikganj'], ['মুন্সিগঞ্জ', 'Munshiganj'],
            ['নারায়ণগঞ্জ', 'Narayanganj'], ['নরসিংদী', 'Narsingdi'], ['রাজবাড়ী', 'Rajbari'], ['শরীয়তপুর', 'Shariatpur'],
            ['টাঙ্গাইল', 'Tangail'],
        ]],
        ['চট্টগ্রাম', 'Chattogram', [
            ['বান্দরবান', 'Bandarban'], ['ব্রাহ্মণবাড়িয়া', 'Brahmanbaria'], ['চাঁদপুর', 'Chandpur'], ['চট্টগ্রাম', 'Chattogram'],
            ['কক্সবাজার', "Cox's Bazar"], ['কুমিল্লা', 'Cumilla'], ['ফেনী', 'Feni'], ['খাগড়াছড়ি', 'Khagrachhari'],
            ['লক্ষ্মীপুর', 'Lakshmipur'], ['নোয়াখালী', 'Noakhali'], ['রাঙ্গামাটি', 'Rangamati'],
        ]],
        ['রাজশাহী', 'Rajshahi', [
            ['বগুড়া', 'Bogura'], ['জয়পুরহাট', 'Joypurhat'], ['নওগাঁ', 'Naogaon'], ['নাটোর', 'Natore'],
            ['চাঁপাইনবাবগঞ্জ', 'Chapainawabganj'], ['পাবনা', 'Pabna'], ['রাজশাহী', 'Rajshahi'], ['সিরাজগঞ্জ', 'Sirajganj'],
        ]],
        ['খুলনা', 'Khulna', [
            ['বাগেরহাট', 'Bagerhat'], ['চুয়াডাঙ্গা', 'Chuadanga'], ['যশোর', 'Jashore'], ['ঝিনাইদহ', 'Jhenaidah'],
            ['খুলনা', 'Khulna'], ['কুষ্টিয়া', 'Kushtia'], ['মাগুরা', 'Magura'], ['মেহেরপুর', 'Meherpur'],
            ['নড়াইল', 'Narail'], ['সাতক্ষীরা', 'Satkhira'],
        ]],
        ['বরিশাল', 'Barishal', [
            ['বরগুনা', 'Barguna'], ['বরিশাল', 'Barishal'], ['ভোলা', 'Bhola'], ['ঝালকাঠি', 'Jhalokati'],
            ['পটুয়াখালী', 'Patuakhali'], ['পিরোজপুর', 'Pirojpur'],
        ]],
        ['সিলেট', 'Sylhet', [
            ['হবিগঞ্জ', 'Habiganj'], ['মৌলভীবাজার', 'Moulvibazar'], ['সুনামগঞ্জ', 'Sunamganj'], ['সিলেট', 'Sylhet'],
        ]],
        ['রংপুর', 'Rangpur', [
            ['দিনাজপুর', 'Dinajpur'], ['গাইবান্ধা', 'Gaibandha'], ['কুড়িগ্রাম', 'Kurigram'], ['লালমনিরহাট', 'Lalmonirhat'],
            ['নীলফামারী', 'Nilphamari'], ['পঞ্চগড়', 'Panchagarh'], ['রংপুর', 'Rangpur'], ['ঠাকুরগাঁও', 'Thakurgaon'],
        ]],
        ['ময়মনসিংহ', 'Mymensingh', [
            ['জামালপুর', 'Jamalpur'], ['ময়মনসিংহ', 'Mymensingh'], ['নেত্রকোনা', 'Netrokona'], ['শেরপুর', 'Sherpur'],
        ]],
    ];

    public function run(): void
    {
        $city = DeliveryZone::firstOrCreate(['name' => 'ঢাকা সিটি'], [
            'name_en' => 'Dhaka City', 'charge' => 6000, 'eta_min_days' => 1, 'eta_max_days' => 2, 'sort_order' => 1,
        ]);
        $suburbs = DeliveryZone::firstOrCreate(['name' => 'ঢাকা সাব-এরিয়া'], [
            'name_en' => 'Dhaka suburbs', 'charge' => 10000, 'eta_min_days' => 2, 'eta_max_days' => 3, 'sort_order' => 2,
        ]);
        DeliveryZone::firstOrCreate(['name' => 'ঢাকার বাইরে'], [
            'name_en' => 'Outside Dhaka', 'charge' => 12000, 'eta_min_days' => 3, 'eta_max_days' => 5, 'is_default' => true, 'sort_order' => 3,
        ]);

        $zoneFor = ['Dhaka' => $city->id, 'Gazipur' => $suburbs->id, 'Narayanganj' => $suburbs->id];

        foreach (self::DISTRICTS as [$division, $divisionEn, $districts]) {
            foreach ($districts as [$name, $nameEn]) {
                District::firstOrCreate(['name_en' => $nameEn], [
                    'name' => $name,
                    'division' => $division,
                    'division_en' => $divisionEn,
                    'delivery_zone_id' => $zoneFor[$nameEn] ?? null,
                ]);
            }
        }
    }
}
