<?php

namespace Database\Seeders;

use App\Domain\Catalog\Enums\ProductBadge;
use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Attribute;
use App\Domain\Catalog\Models\AttributeValue;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Demo catalog for local development. Product photos are plain neutral
 * placeholders until real photography is uploaded from the admin.
 */
class DemoCatalogSeeder extends Seeder
{
    private const TONES = ['#E3E3DF', '#DCDDD8', '#E6E2DA', '#D9DEDA', '#E2E0E4'];

    private array $values = [];

    public function run(): void
    {
        $this->attributes();
        $categories = $this->categories();

        $brands = collect(['Looks Jhorna', 'Al Noor', 'Ajmal', 'Darussalam', 'Madinah Dates'])
            ->mapWithKeys(fn ($name) => [$name => Brand::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])]);

        foreach ($this->products() as $i => $data) {
            $variants = $data['variants'] ?? null;
            unset($data['variants']);

            $product = Product::updateOrCreate(['slug' => $data['slug']], [
                ...$data,
                'category_id' => $categories[$data['category_id']]->id,
                'brand_id' => isset($data['brand_id']) ? $brands[$data['brand_id']]->id : null,
                'type' => $variants ? ProductType::Variable : ProductType::Simple,
                'published_at' => now()->subDays(count($this->products()) - $i),
                'is_active' => true,
            ]);

            $product->images()->delete();
            foreach ([0, 1] as $n) {
                $product->images()->create([
                    'path' => $this->placeholder("{$product->slug}-{$n}", $product->name_en, self::TONES[($i + $n) % count(self::TONES)]),
                    'sort_order' => $n,
                ]);
            }

            $product->variants()->delete();
            foreach ($variants ?? [] as $v => [$options, $price, $stock, $compare]) {
                $variant = $product->variants()->create([
                    'sku' => strtoupper(Str::slug($product->sku.'-'.implode('-', $options))),
                    'price' => $price,
                    'compare_price' => $compare,
                    'stock' => $stock,
                    'sort_order' => $v,
                ]);
                $variant->attributeValues()->sync(collect($options)->map(fn ($o) => $this->values[$o]));
            }
        }
    }

    private function attributes(): void
    {
        $sets = [
            ['সাইজ', 'Size', 'size', 'button', [['S'], ['M'], ['L'], ['XL'], ['৩৮', '38'], ['৪০', '40'], ['৪২', '42'], ['৪৪', '44'], ['৫২', '52'], ['৫৪', '54'], ['৫৬', '56']]],
            ['রং', 'Color', 'color', 'color', [['সাদা', 'White', '#F5F5F2'], ['অফ হোয়াইট', 'Off White', '#ECE9E1'], ['কালো', 'Black', '#1A1A1A'], ['নেভি', 'Navy', '#1F2A44'], ['মেরুন', 'Maroon', '#6B1E2A'], ['অলিভ', 'Olive', '#5B6145'], ['ধূসর', 'Grey', '#8A8A86']]],
            ['পরিমাণ', 'Volume', 'volume', 'button', [['৩ ml', '3 ml'], ['৬ ml', '6 ml'], ['১২ ml', '12 ml']]],
        ];

        foreach ($sets as $order => [$name, $nameEn, $slug, $type, $values]) {
            $attribute = Attribute::updateOrCreate(['slug' => $slug], ['name' => $name, 'name_en' => $nameEn, 'type' => $type, 'sort_order' => $order]);

            foreach ($values as $vOrder => $value) {
                $record = AttributeValue::updateOrCreate(
                    ['attribute_id' => $attribute->id, 'value' => $value[0]],
                    ['value_en' => $value[1] ?? $value[0], 'color_hex' => $value[2] ?? null, 'sort_order' => $vOrder],
                );
                $this->values[$value[1] ?? $value[0]] = $record->id;
            }
        }
    }

    /** @return array<string, Category> keyed by slug */
    private function categories(): array
    {
        $tree = [
            ['পুরুষ', 'Men', 'men', true, [
                ['পাঞ্জাবি', 'Panjabi', 'panjabi'],
                ['জুব্বা ও থোব', 'Jubba & Thobe', 'jubba-thobe'],
                ['টুপি', 'Caps', 'tupi'],
            ]],
            ['নারী', 'Women', 'women', true, [
                ['বোরকা ও আবায়া', 'Borka & Abaya', 'borka-abaya'],
                ['হিজাব', 'Hijab', 'hijab'],
            ]],
            ['আতর ও সুগন্ধি', 'Attar & Fragrance', 'attar', true, []],
            ['ইবাদত সামগ্রী', 'Prayer Essentials', 'prayer-essentials', true, []],
            ['বই', 'Books', 'books', false, []],
            ['সুন্নাহ ফুড', 'Sunnah Foods', 'sunnah-foods', true, []],
            ['গিফট বক্স', 'Gift Boxes', 'gift-boxes', false, []],
        ];

        $out = [];
        foreach ($tree as $order => [$name, $nameEn, $slug, $featured, $children]) {
            $parent = Category::updateOrCreate(['slug' => $slug], [
                'name' => $name, 'name_en' => $nameEn, 'sort_order' => $order, 'is_featured' => $featured,
                'image' => $this->placeholder("category-{$slug}", $nameEn, self::TONES[$order % count(self::TONES)]),
            ]);
            $out[$slug] = $parent;

            foreach ($children as $cOrder => [$cName, $cNameEn, $cSlug]) {
                $out[$cSlug] = Category::updateOrCreate(['slug' => $cSlug], [
                    'name' => $cName, 'name_en' => $cNameEn, 'parent_id' => $parent->id, 'sort_order' => $cOrder,
                ]);
            }
        }

        return $out;
    }

    /** Prices in poisha. Variants: [[option keys], price, stock, compare]. */
    private function products(): array
    {
        $tk = fn (int $taka) => $taka * 100;
        $sizes = fn (int $price, ?int $compare = null, array $colors = ['White']) => collect($colors)
            ->flatMap(fn ($c) => collect(['40', '42', '44'])->map(fn ($s, $i) => [[$s, $c], $price, [8, 14, 6][$i], $compare]))
            ->all();

        return [
            ['name' => 'সুতি কাবলি পাঞ্জাবি', 'name_en' => 'Cotton Kabli Panjabi', 'slug' => 'cotton-kabli-panjabi', 'sku' => 'LJ-PJ-001', 'category_id' => 'panjabi', 'brand_id' => 'Looks Jhorna',
                'price' => $tk(2450), 'compare_price' => $tk(2950), 'badge' => ProductBadge::New, 'is_featured' => true, 'sold_count' => 42,
                'short_description' => "নরম ১০০% সুতি কাপড়\nকাবলি কাট, আরামদায়ক ফিটিং\nগরমে হালকা ও বাতাস চলাচল করে",
                'short_description_en' => "Soft 100% cotton\nKabli cut, relaxed fit\nLight and breathable in the heat",
                'description' => '<p>প্রতিদিনের নামাজ আর ঈদ, দুই জায়গাতেই মানানসই একটা পাঞ্জাবি। ধোয়ার পরও রং আর আকার ঠিক থাকে।</p>',
                'specifications' => [['label' => 'ফ্যাব্রিক', 'value' => '১০০% সুতি'], ['label' => 'ফিট', 'value' => 'রিলাক্সড']],
                'variants' => $sizes($tk(2450), $tk(2950), ['White', 'Off White'])],
            ['name' => 'লিনেন সেমি-লং পাঞ্জাবি', 'name_en' => 'Linen Semi-Long Panjabi', 'slug' => 'linen-semi-long-panjabi', 'sku' => 'LJ-PJ-002', 'category_id' => 'panjabi', 'brand_id' => 'Looks Jhorna',
                'price' => $tk(3200), 'badge' => ProductBadge::BestSeller, 'sold_count' => 120,
                'short_description' => "প্রিমিয়াম লিনেন\nসেমি-লং কাট\nহাতে করা বোতামের কাজ",
                'variants' => $sizes($tk(3200), null, ['Navy', 'Olive'])],
            ['name' => 'এমব্রয়ডারি ঈদ পাঞ্জাবি', 'name_en' => 'Embroidered Eid Panjabi', 'slug' => 'embroidered-eid-panjabi', 'sku' => 'LJ-PJ-003', 'category_id' => 'panjabi', 'brand_id' => 'Looks Jhorna',
                'price' => $tk(4500), 'compare_price' => $tk(5200), 'badge' => ProductBadge::Hot, 'is_featured' => true, 'sold_count' => 64,
                'short_description' => "গলা ও হাতায় সূক্ষ্ম এমব্রয়ডারি\nজর্জেট-সুতি মিক্স",
                'variants' => $sizes($tk(4500), $tk(5200), ['Off White', 'Maroon'])],
            ['name' => 'আরবি জুব্বা — সাদা', 'name_en' => 'Arabic Jubba — White', 'slug' => 'arabic-jubba-white', 'sku' => 'LJ-JB-001', 'category_id' => 'jubba-thobe', 'brand_id' => 'Al Noor',
                'price' => $tk(3800), 'sold_count' => 18,
                'short_description' => "লম্বা জুব্বা, পূর্ণ হাতা\nইস্ত্রি ছাড়াই পরিপাটি",
                'variants' => [[['52', 'White'], $tk(3800), 5, null], [['54', 'White'], $tk(3800), 9, null], [['56', 'White'], $tk(3800), 4, null]]],
            ['name' => 'হাতে বোনা কটন টুপি', 'name_en' => 'Hand-Knit Cotton Cap', 'slug' => 'hand-knit-cotton-cap', 'sku' => 'LJ-TP-001', 'category_id' => 'tupi',
                'price' => $tk(350), 'stock' => 80, 'sold_count' => 210, 'badge' => ProductBadge::BestSeller,
                'short_description' => "নরম সুতি সুতা\nসব মাপে ফিট হয়"],
            ['name' => 'তুর্কি ভেলভেট টুপি', 'name_en' => 'Turkish Velvet Cap', 'slug' => 'turkish-velvet-cap', 'sku' => 'LJ-TP-002', 'category_id' => 'tupi',
                'price' => $tk(550), 'stock' => 3, 'badge' => ProductBadge::Limited],
            ['name' => 'নিদা ফেব্রিক আবায়া সেট', 'name_en' => 'Nida Fabric Abaya Set', 'slug' => 'nida-abaya-set', 'sku' => 'LJ-AB-001', 'category_id' => 'borka-abaya', 'brand_id' => 'Looks Jhorna',
                'price' => $tk(3200), 'compare_price' => $tk(3750), 'badge' => ProductBadge::New, 'is_featured' => true, 'sold_count' => 55,
                'short_description' => "প্রিমিয়াম নিদা ফেব্রিক\nহিজাবসহ সেট\nকুঁচকায় না",
                'variants' => [[['52', 'Black'], $tk(3200), 10, $tk(3750)], [['54', 'Black'], $tk(3200), 12, $tk(3750)], [['52', 'Navy'], $tk(3200), 6, $tk(3750)], [['54', 'Navy'], $tk(3200), 0, $tk(3750)]]],
            ['name' => 'বাটারফ্লাই বোরকা', 'name_en' => 'Butterfly Borka', 'slug' => 'butterfly-borka', 'sku' => 'LJ-AB-002', 'category_id' => 'borka-abaya',
                'price' => $tk(2800), 'sold_count' => 30,
                'variants' => [[['52', 'Black'], $tk(2800), 7, null], [['54', 'Black'], $tk(2800), 9, null], [['56', 'Black'], $tk(2800), 5, null]]],
            ['name' => 'জর্জেট হিজাব', 'name_en' => 'Georgette Hijab', 'slug' => 'georgette-hijab', 'sku' => 'LJ-HJ-001', 'category_id' => 'hijab',
                'price' => $tk(450), 'sold_count' => 140, 'badge' => ProductBadge::BestSeller,
                'variants' => [[['Black'], $tk(450), 30, null], [['Grey'], $tk(450), 22, null], [['Off White'], $tk(450), 18, null], [['Maroon'], $tk(450), 9, null]]],
            ['name' => 'জার্সি হিজাব', 'name_en' => 'Jersey Hijab', 'slug' => 'jersey-hijab', 'sku' => 'LJ-HJ-002', 'category_id' => 'hijab',
                'price' => $tk(390), 'stock' => 60],
            ['name' => 'উদ আতর', 'name_en' => 'Oud Attar', 'slug' => 'oud-attar', 'sku' => 'LJ-AT-001', 'category_id' => 'attar', 'brand_id' => 'Ajmal',
                'price' => $tk(450), 'badge' => ProductBadge::BestSeller, 'is_featured' => true, 'sold_count' => 310,
                'short_description' => "অ্যালকোহল-মুক্ত\nদীর্ঘস্থায়ী উষ্ণ সুগন্ধ\nরোল-অন বোতল",
                'variants' => [[['3 ml'], $tk(450), 40, null], [['6 ml'], $tk(850), 25, null], [['12 ml'], $tk(1550), 12, $tk(1700)]]],
            ['name' => 'হোয়াইট মাস্ক আতর', 'name_en' => 'White Musk Attar', 'slug' => 'white-musk-attar', 'sku' => 'LJ-AT-002', 'category_id' => 'attar', 'brand_id' => 'Al Noor',
                'price' => $tk(350), 'sold_count' => 95,
                'variants' => [[['3 ml'], $tk(350), 50, null], [['6 ml'], $tk(650), 30, null], [['12 ml'], $tk(1200), 10, null]]],
            ['name' => 'জান্নাতুল ফেরদৌস আতর', 'name_en' => 'Jannatul Firdous Attar', 'slug' => 'jannatul-firdous-attar', 'sku' => 'LJ-AT-003', 'category_id' => 'attar',
                'price' => $tk(300), 'stock' => 70, 'badge' => ProductBadge::New],
            ['name' => 'তুর্কি ভেলভেট জায়নামাজ', 'name_en' => 'Turkish Velvet Prayer Mat', 'slug' => 'turkish-velvet-prayer-mat', 'sku' => 'LJ-PR-001', 'category_id' => 'prayer-essentials',
                'price' => $tk(1990), 'compare_price' => $tk(2400), 'is_featured' => true, 'sold_count' => 77, 'badge' => ProductBadge::Hot,
                'short_description' => "মোটা ফোম প্যাডিং\nপিছলে যায় না\nভাঁজ করে সহজে রাখা যায়",
                'variants' => [[['Navy'], $tk(1990), 14, $tk(2400)], [['Olive'], $tk(1990), 10, $tk(2400)], [['Maroon'], $tk(1990), 6, $tk(2400)]]],
            ['name' => 'ক্রিস্টাল তাসবিহ (৯৯ দানা)', 'name_en' => 'Crystal Tasbih (99 beads)', 'slug' => 'crystal-tasbih-99', 'sku' => 'LJ-PR-002', 'category_id' => 'prayer-essentials',
                'price' => $tk(650), 'stock' => 35],
            ['name' => 'ডিজিটাল তাসবিহ কাউন্টার', 'name_en' => 'Digital Tasbih Counter', 'slug' => 'digital-tasbih-counter', 'sku' => 'LJ-PR-003', 'category_id' => 'prayer-essentials',
                'price' => $tk(280), 'stock' => 0],
            ['name' => 'রিয়াদুস সালেহীন (সম্পূর্ণ সেট)', 'name_en' => 'Riyad us Saliheen (Full Set)', 'slug' => 'riyad-us-saliheen-set', 'sku' => 'LJ-BK-001', 'category_id' => 'books', 'brand_id' => 'Darussalam',
                'price' => $tk(1800), 'compare_price' => $tk(2100), 'stock' => 20, 'sold_count' => 40],
            ['name' => 'শিশুদের নবী-রাসূলের গল্প', 'name_en' => 'Stories of the Prophets for Kids', 'slug' => 'prophet-stories-kids', 'sku' => 'LJ-BK-002', 'category_id' => 'books',
                'price' => $tk(450), 'stock' => 45, 'badge' => ProductBadge::New],
            ['name' => 'আজওয়া খেজুর ৫০০ গ্রাম', 'name_en' => 'Ajwa Dates 500g', 'slug' => 'ajwa-dates-500g', 'sku' => 'LJ-FD-001', 'category_id' => 'sunnah-foods', 'brand_id' => 'Madinah Dates',
                'price' => $tk(1650), 'compare_price' => $tk(1900), 'stock' => 25, 'sold_count' => 88, 'is_featured' => true, 'badge' => ProductBadge::BestSeller,
                'short_description' => "মদিনার আসল আজওয়া\nসিল করা প্যাকেট"],
            ['name' => 'সুন্দরবনের খলিশা ফুলের মধু ১ কেজি', 'name_en' => 'Sundarbans Honey 1kg', 'slug' => 'sundarbans-honey-1kg', 'sku' => 'LJ-FD-002', 'category_id' => 'sunnah-foods',
                'price' => $tk(1200), 'stock' => 30, 'sold_count' => 60],
            ['name' => 'কালোজিরা তেল ২০০ ml', 'name_en' => 'Black Seed Oil 200ml', 'slug' => 'black-seed-oil-200ml', 'sku' => 'LJ-FD-003', 'category_id' => 'sunnah-foods',
                'price' => $tk(550), 'stock' => 40],
            ['name' => 'ঈদ গিফট বক্স — আতর ও তাসবিহ', 'name_en' => 'Eid Gift Box — Attar & Tasbih', 'slug' => 'eid-gift-box-attar-tasbih', 'sku' => 'LJ-GB-001', 'category_id' => 'gift-boxes',
                'price' => $tk(1490), 'compare_price' => $tk(1750), 'stock' => 15, 'badge' => ProductBadge::Limited, 'free_delivery' => true],
        ];
    }

    /** A flat neutral JPEG with the product name, stored on the public disk. */
    private function placeholder(string $key, string $label, string $hex): string
    {
        $path = "demo/{$key}.jpg";
        if (Storage::disk('public')->exists($path)) {
            return $path;
        }

        [$w, $h] = [800, 1000];
        $img = imagecreatetruecolor($w, $h);
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        imagefill($img, 0, 0, imagecolorallocate($img, $r, $g, $b));

        $ink = imagecolorallocate($img, 107, 107, 104);
        $lines = explode("\n", wordwrap($label, 22));
        foreach ($lines as $n => $line) {
            $x = (int) (($w - imagefontwidth(5) * strlen($line)) / 2);
            imagestring($img, 5, $x, (int) ($h / 2 - count($lines) * 10 + $n * 20), $line, $ink);
        }

        ob_start();
        imagejpeg($img, null, 82);
        Storage::disk('public')->put($path, ob_get_clean());
        imagedestroy($img);

        return $path;
    }
}
