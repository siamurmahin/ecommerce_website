<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /** Starts as the home page that was hard-coded before the builder. */
    public function up(): void
    {
        $products = fn (array $data) => ['type' => 'products', 'data' => [
            'enabled' => true, 'note' => null, 'note_en' => null, 'category_id' => null, 'limit' => 8,
            'layout' => 'grid', 'columns' => '4', 'style' => 'plain', 'badge' => null, 'link_url' => null, ...$data,
        ]];

        $this->migrator->add('home.sections', [
            ['type' => 'hero', 'data' => [
                'enabled' => true,
                'eyebrow' => 'নতুন কালেকশন', 'eyebrow_en' => 'New collection',
                'title' => 'ঈদ কালেকশন ২০২৬', 'title_en' => 'Eid Collection 2026',
                'subtitle' => 'হাতে বাছাই করা পাঞ্জাবি, অ্যালকোহল-মুক্ত আতর আর প্রিমিয়াম জায়নামাজ। সারা বাংলাদেশে ক্যাশ অন ডেলিভারি।',
                'subtitle_en' => 'Hand-picked panjabi, alcohol-free attar and premium prayer mats. Cash on delivery across Bangladesh.',
                'button_label' => 'এখনই কিনুন', 'button_label_en' => 'Shop now', 'button_url' => '/shop',
            ]],
            ['type' => 'trust', 'data' => ['enabled' => true, 'items' => [
                ['icon' => 'truck', 'title' => 'সারা দেশে ডেলিভারি', 'title_en' => 'Nationwide delivery', 'note' => '২–৫ কর্মদিবসে', 'note_en' => 'In 2–5 working days'],
                ['icon' => 'banknote', 'title' => 'ক্যাশ অন ডেলিভারি', 'title_en' => 'Cash on delivery', 'note' => 'পণ্য হাতে পেয়ে টাকা', 'note_en' => 'Pay when it arrives'],
                ['icon' => 'rotate', 'title' => 'সহজ রিটার্ন', 'title_en' => 'Easy returns', 'note' => '৭ দিনের মধ্যে', 'note_en' => 'Within 7 days'],
                ['icon' => 'shield', 'title' => '১০০% অরিজিনাল', 'title_en' => '100% authentic', 'note' => 'বাছাই করা পণ্য', 'note_en' => 'Hand-picked products'],
            ]]],
            ['type' => 'categories', 'data' => ['enabled' => true, 'title' => 'ক্যাটাগরি অনুযায়ী কিনুন', 'title_en' => 'Shop by category', 'limit' => 7]],
            $products(['title' => 'বাছাই করা পণ্য', 'title_en' => 'Featured', 'note' => 'আমাদের বাছাই করা সেরা পণ্য', 'note_en' => 'Our favourite picks', 'source' => 'featured', 'limit' => 4, 'layout' => 'carousel']),
            ['type' => 'promo_banners', 'data' => ['enabled' => true]],
            $products(['title' => 'আজকের অফার', 'title_en' => "Today's deals", 'note' => 'সীমিত সময়ের বিশেষ দাম — স্টক শেষ হওয়ার আগেই নিন', 'note_en' => 'Special prices for a limited time', 'source' => 'deals', 'limit' => 10, 'layout' => 'carousel', 'style' => 'highlight', 'link_url' => '/shop?sort=popular']),
            $products(['title' => 'নতুন এসেছে', 'title_en' => 'New arrivals', 'note' => 'সদ্য যোগ হওয়া কালেকশন', 'note_en' => 'Just added', 'source' => 'new', 'style' => 'panel', 'link_url' => '/shop?sort=newest']),
            $products(['title' => 'বেস্ট সেলার', 'title_en' => 'Best sellers', 'note' => 'সবচেয়ে বেশি পছন্দের পণ্য', 'note_en' => 'Most loved', 'source' => 'best_sellers', 'limit' => 10, 'columns' => '5', 'badge' => 'best_seller', 'link_url' => '/shop?sort=popular']),
            ['type' => 'help', 'data' => [
                'enabled' => true,
                'title' => 'অর্ডার করতে সাহায্য লাগবে?', 'title_en' => 'Need help ordering?',
                'note' => 'সাইজ, দাম বা ডেলিভারি নিয়ে যেকোনো প্রশ্নে আমাদের কল বা মেসেজ করুন।', 'note_en' => 'Call or message us about sizes, prices or delivery.',
            ]],
        ]);
    }
};
