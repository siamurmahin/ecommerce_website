<?php

namespace Database\Seeders;

use App\Domain\Content\Enums\MenuLocation;
use App\Domain\Content\Models\MenuItem;
use Illuminate\Database\Seeder;

/** Starter menus; admins edit them under কনটেন্ট → মেনু. A location is only seeded while it's empty. */
class MenuSeeder extends Seeder
{
    private const MENUS = [
        'header' => [
            ['হোম', 'Home', '/', false],
            ['শপ', 'Shop', '/shop', false],
            ['বেস্ট সেলার', 'Best Selling', '/shop?sort=popular', false],
            ['নতুন এসেছে', 'New Arrivals', '/shop?sort=newest', false],
            ['হট অফার', 'Hot Offer', '/shop?badge=hot', true],
        ],
        'footer_help' => [
            ['সাধারণ প্রশ্ন', 'FAQ', '/faq', false],
            ['শিপিং পলিসি', 'Shipping policy', '/shipping-policy', false],
            ['রিটার্ন পলিসি', 'Return policy', '/return-policy', false],
            ['অর্ডার ট্র্যাক', 'Track order', '/track-order', false],
        ],
        'footer_company' => [
            ['আমাদের সম্পর্কে', 'About us', '/about', false],
            ['যোগাযোগ', 'Contact', '/contact', false],
            ['প্রাইভেসি পলিসি', 'Privacy policy', '/privacy-policy', false],
            ['শর্তাবলি', 'Terms', '/terms', false],
        ],
    ];

    public function run(): void
    {
        foreach (self::MENUS as $location => $items) {
            if (MenuItem::where('location', $location)->exists()) {
                continue;
            }

            foreach ($items as $i => [$label, $labelEn, $url, $highlighted]) {
                MenuItem::create([
                    'location' => MenuLocation::from($location), 'label' => $label, 'label_en' => $labelEn,
                    'url' => $url, 'is_highlighted' => $highlighted, 'sort_order' => $i,
                ]);
            }
        }
    }
}
