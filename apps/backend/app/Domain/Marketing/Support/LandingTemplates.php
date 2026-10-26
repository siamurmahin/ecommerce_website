<?php

namespace App\Domain\Marketing\Support;

/**
 * Ready-made landing page designs. The key is stored on the page and picks its look on the storefront
 * (`components/landing/templates.ts`); `blocks()` is the starting layout a new page gets with that design.
 * Media and product-specific blocks (gallery, video, reviews, checklist) start empty — they stay hidden on the site until filled in.
 */
final class LandingTemplates
{
    public const TEMPLATES = [
        'classic' => [
            'label' => 'ক্লাসিক',
            'description' => 'পাশাপাশি ছবি আর লেখা, সাদা কার্ড। যেকোনো পণ্যে মানায়।',
        ],
        'offer' => [
            'label' => 'ফ্ল্যাশ অফার',
            'description' => 'রঙিন বড় হিরো, বড় করে দাম আর কাউন্টডাউন, বারবার অর্ডার বাটন। ছাড় বা সীমিত সময়ের অফারে।',
        ],
        'minimal' => [
            'label' => 'মিনিমাল প্রিমিয়াম',
            'description' => 'মাঝখানে বড় শিরোনাম, চওড়া ছবি, অনেক ফাঁকা জায়গা। দামি বা গিফট পণ্যে।',
        ],
        'story' => [
            'label' => 'স্টোরি',
            'description' => 'লম্বা পেজ: ভিডিও, পণ্যের গল্প, সুবিধা, রিভিউ — একেকটা অংশ আলাদা রঙের ব্যান্ডে। নতুন বা বোঝাতে হয় এমন পণ্যে।',
        ],
        'quick' => [
            'label' => 'কুইক অর্ডার',
            'description' => 'অর্ডার ফর্ম একদম উপরে, ডেস্কটপে হিরোর পাশেই। চেনা পণ্য আর রিটার্গেটিং অ্যাডে।',
        ],
        'bundle' => [
            'label' => 'প্যাকেজ অফার',
            'description' => 'উপরে বড় শিরোনাম আর চওড়া ব্যানার, কাটা দামের অফার বক্স, বিশেষ দ্রষ্টব্য আর "প্যাকেজে যা পাচ্ছেন" তালিকা। কম্বো বা বান্ডেল অফারে।',
        ],
        'showcase' => [
            'label' => 'ফিচার শোকেস',
            'description' => 'ঘোষণার বার, ভরসার স্ট্রিপ, কাউন্টডাউনসহ চওড়া অফার ব্যান্ড, আইকনসহ ফিচার গ্রিড আর ফোনে অর্ডারের ব্যান্ড। অনেক ফিচারওয়ালা পণ্যে।',
        ],
        'elegant' => [
            'label' => 'সফট এলিগেন্ট',
            'description' => 'নরম, গোছানো চেহারা: শিরোনামের উপরে ছোট লেবেল, নম্বর দেওয়া ফিচার কার্ড (ভেতরে পয়েন্ট)। পোশাক, হিজাব বা লাইফস্টাইল পণ্যে।',
        ],
    ];

    public const DEFAULT = 'classic';

    /** @return array<string, string> key => label, for selects */
    public static function options(): array
    {
        return array_map(fn (array $t) => $t['label'], self::TEMPLATES);
    }

    /** @return array<string, string> */
    public static function descriptions(): array
    {
        return array_map(fn (array $t) => $t['description'], self::TEMPLATES);
    }

    public static function exists(?string $key): bool
    {
        return $key !== null && isset(self::TEMPLATES[$key]);
    }

    /** Starting blocks for a new page with this design, in the Builder's stored shape. */
    public static function blocks(string $key): array
    {
        $hero = fn (array $data = []) => self::block('hero', [...['title' => '', 'show_price' => true, 'button_label' => 'এখনই অর্ডার করুন'], ...$data]);
        $form = fn (array $data = []) => self::block('order_form', [...['title' => 'অর্ডার করতে নিচের ফর্মটি পূরণ করুন'], ...$data]);

        return match ($key) {
            'offer' => [
                $hero(['badge' => 'সীমিত সময়ের অফার', 'subtitle' => 'অফার শেষ হওয়ার আগেই অর্ডার করুন।']),
                self::benefits('কেন এখনই নেবেন', [
                    ['banknote', 'পণ্য হাতে পেয়ে টাকা দিন'],
                    ['truck', 'সারা দেশে হোম ডেলিভারি'],
                    ['shield', '১০০% অরিজিনাল পণ্য'],
                    ['rotate', 'সমস্যা হলে সহজ রিটার্ন'],
                ]),
                self::block('gallery', ['title' => 'কাছ থেকে দেখুন', 'images' => []]),
                self::block('reviews', ['title' => 'যারা নিয়েছেন তারা যা বলছেন', 'items' => []]),
                self::block('cta', ['title' => 'অফার শেষ হওয়ার আগেই নিয়ে নিন', 'button_label' => 'এখনই অর্ডার করুন']),
                $form(),
                self::faq(),
            ],
            'minimal' => [
                $hero(),
                self::block('gallery', ['title' => null, 'images' => []]),
                self::block('text', ['title' => 'পণ্যের বিস্তারিত', 'content' => '<p>পণ্যটি কী দিয়ে তৈরি, কীভাবে ব্যবহার করবেন আর কেন এটা আলাদা — দুই-তিন লাইনে লিখুন।</p>']),
                self::benefits('আপনি যা পাচ্ছেন', [
                    ['badge', 'প্রিমিয়াম মান'],
                    ['gift', 'সুন্দর প্যাকেজিং'],
                    ['truck', 'দ্রুত হোম ডেলিভারি'],
                ]),
                $form(),
            ],
            'story' => [
                $hero(),
                self::block('video', ['title' => 'ভিডিওতে দেখুন', 'url' => null]),
                self::block('text', ['title' => 'কেন আমরা এটা এনেছি', 'content' => '<p>কাস্টমারের যে সমস্যা এই পণ্য সমাধান করে, সেটা দিয়ে শুরু করুন। তারপর লিখুন পণ্যটি কীভাবে কাজ করে আর অন্যগুলোর চেয়ে কোথায় আলাদা।</p>']),
                self::benefits('যা যা সুবিধা পাবেন', [
                    ['shield', '১০০% অরিজিনাল পণ্য'],
                    ['badge', 'মান যাচাই করা'],
                    ['banknote', 'পণ্য হাতে পেয়ে টাকা দিন'],
                    ['truck', 'সারা দেশে হোম ডেলিভারি'],
                ]),
                self::block('gallery', ['title' => 'ছবিতে দেখুন', 'images' => []]),
                self::block('reviews', ['title' => 'কাস্টমারদের অভিজ্ঞতা', 'items' => []]),
                self::faq(),
                self::block('cta', ['title' => 'আজই অর্ডার করুন', 'subtitle' => 'ক্যাশ অন ডেলিভারি — পণ্য দেখে টাকা দিন।', 'button_label' => 'অর্ডার করতে চাই']),
                $form(),
            ],
            'quick' => [
                $hero(),
                $form(),
                self::benefits(null, [
                    ['banknote', 'ক্যাশ অন ডেলিভারি'],
                    ['truck', 'সারা দেশে ডেলিভারি'],
                    ['shield', '১০০% অরিজিনাল'],
                ]),
                self::block('reviews', ['title' => 'কাস্টমার রিভিউ', 'items' => []]),
                self::faq(),
            ],
            'bundle' => [
                $hero(['show_price' => false, 'subtitle' => 'পণ্যটি কেন নেবেন, এক লাইনে লিখুন। আজই অর্ডার করুন।']),
                self::block('offer', ['badge' => 'সীমিত সময়ের বিশেষ অফার', 'button_label' => 'অর্ডার করতে চাই — ক্যাশ অন ডেলিভারি']),
                self::notice(),
                self::block('checklist', ['title' => 'প্যাকেজে যা পাচ্ছেন', 'items' => []]),
                self::block('cta', ['title' => null, 'button_label' => 'অর্ডার করতে ক্লিক করুন']),
                self::benefits('কেন এটা আপনার নেওয়া উচিত', [
                    ['badge', 'বাছাই করা মান', 'প্রতিটা পণ্য পাঠানোর আগে যাচাই করা হয়।'],
                    ['shield', '১০০% অরিজিনাল', 'নকল পেলে পুরো টাকা ফেরত।'],
                    ['package', 'যত্নে প্যাকিং', 'ভাঙা বা নষ্ট হওয়ার ঝুঁকি নেই।'],
                    ['banknote', 'ক্যাশ অন ডেলিভারি', 'সারা দেশে হোম ডেলিভারি — হাতে পেয়ে টাকা দিন।'],
                ]),
                self::block('reviews', ['title' => 'আমাদের কাস্টমারদের রিভিউ', 'items' => []]),
                $form(['subtitle' => 'ক্যাশ অন ডেলিভারি — পণ্য হাতে পেয়ে চেক করে মূল্য পরিশোধ করুন।']),
            ],
            'showcase' => [
                self::block('announcement', ['title' => 'ক্যাশ অন ডেলিভারি সারা দেশে · পণ্য দেখে টাকা দিন']),
                $hero(['badge' => 'সবার পছন্দের', 'button_label' => 'অর্ডার করতে চাই']),
                self::trust(),
                self::block('offer', ['badge' => 'সীমিত সময়ের বিশেষ অফার', 'title' => 'অফার শেষ হওয়ার আগে অর্ডার করুন', 'button_label' => 'অর্ডার করতে চাই', 'ends_at' => null]),
                self::benefits('একটি পণ্যেই সব কিছু', [
                    ['star', 'প্রথম ফিচার', 'ফিচারটা কাস্টমারের কী কাজে আসে, এক লাইনে।'],
                    ['badge', 'দ্বিতীয় ফিচার', 'ফিচারটা কাস্টমারের কী কাজে আসে, এক লাইনে।'],
                    ['shield', 'টেকসই', 'অনেক দিন ব্যবহার করা যায়।'],
                    ['gift', 'গিফট হিসেবেও দারুণ', 'সুন্দর প্যাকেটে পাঠানো হয়।'],
                    ['clock', 'সময় বাঁচায়', 'রোজকার কাজ সহজ করে।'],
                    ['package', 'সম্পূর্ণ প্যাকেজ', 'বাক্সে যা যা থাকছে, লিখুন।'],
                ], 'কী কী পাচ্ছেন'),
                self::block('gallery', ['title' => 'ছবিতে দেখুন', 'images' => []]),
                self::block('reviews', ['badge' => 'সন্তুষ্ট গ্রাহকদের কথা', 'title' => 'আমাদের কাস্টমারদের রিভিউ', 'items' => []]),
                self::block('call', ['badge' => 'সরাসরি যোগাযোগ', 'title' => 'ফোনে অর্ডার করতে চান? সরাসরি কল করুন']),
                $form(['badge' => 'সহজ চেকআউট', 'title' => 'অর্ডার করতে নিচের ফর্মটি পূরণ করুন']),
            ],
            'elegant' => [
                self::block('announcement', ['title' => 'নতুন কালেকশন · ক্যাশ অন ডেলিভারি সারা দেশে']),
                $hero(['badge' => 'নতুন', 'button_label' => 'এখনই অর্ডার করুন']),
                self::trust(),
                self::block('offer', ['badge' => 'সীমিত সময়ের অফার', 'title' => 'অফার শেষ হওয়ার আগেই অর্ডার করুন', 'button_label' => 'এখনই অর্ডার করুন', 'ends_at' => null]),
                self::block('features', ['badge' => 'কেন এই পণ্য', 'title' => 'যে কারণে আপনার ভালো লাগবে', 'items' => [
                    ['title' => 'কাপড় ও মান', 'text' => "নরম ও আরামদায়ক কাপড়\nগরমেও স্বস্তি\nঅনেক দিন টেকে"],
                    ['title' => 'ব্যবহার', 'text' => "রোজকার ব্যবহারে উপযোগী\nসহজে ধোয়া যায়\nভাঁজ করে রাখা যায়"],
                    ['title' => 'সাইজ ও মাপ', 'text' => "মাপ এখানে লিখুন\nপ্রতি লাইনে একটা পয়েন্ট"],
                ]]),
                self::block('gallery', ['badge' => 'কাছ থেকে দেখুন', 'title' => null, 'images' => []]),
                self::block('reviews', ['badge' => 'কাস্টমার রিভিউ', 'title' => 'যারা নিয়েছেন তারা যা বলছেন', 'items' => []]),
                self::faq(),
                $form(['badge' => 'চেকআউট', 'title' => 'অর্ডার সম্পন্ন করুন']),
            ],
            default => [
                $hero(),
                self::benefits('কেন এটা নেবেন', [
                    ['shield', '১০০% অরিজিনাল পণ্য'],
                    ['banknote', 'পণ্য হাতে পেয়ে টাকা দিন'],
                    ['truck', 'সারা দেশে হোম ডেলিভারি'],
                ]),
                $form(),
                self::faq(),
            ],
        };
    }

    private static function block(string $type, array $data): array
    {
        return ['type' => $type, 'data' => ['enabled' => true, ...$data]];
    }

    /** @param  list<array{0: string, 1: string, 2?: string}>  $items  [icon, text, note] */
    private static function benefits(?string $title, array $items, ?string $badge = null): array
    {
        return self::block('benefits', [
            'badge' => $badge,
            'title' => $title,
            'items' => array_map(fn (array $i) => ['icon' => $i[0], 'title' => $i[1], 'note' => $i[2] ?? null], $items),
        ]);
    }

    private static function trust(): array
    {
        return self::block('trust', ['items' => array_map(fn (array $i) => ['icon' => $i[0], 'title' => $i[1]], [
            ['banknote', 'ক্যাশ অন ডেলিভারি'],
            ['truck', 'দ্রুত ডেলিভারি'],
            ['rotate', 'সহজ রিটার্ন'],
            ['badge', 'মানের নিশ্চয়তা'],
        ])]);
    }

    private static function notice(): array
    {
        return self::block('notice', ['title' => 'বিশেষ দ্রষ্টব্য', 'items' => array_map(fn (string $line) => ['title' => $line], [
            'পণ্য হাতে পেয়ে চেক করে সম্পূর্ণ মূল্য পরিশোধ করবেন।',
            'অর্ডার করতে আগে কোনো টাকা দিতে হবে না।',
            'ডেলিভারি ম্যানের সামনে প্যাকেট খুলে দেখে নিতে পারবেন।',
        ])]);
    }

    private static function faq(): array
    {
        return self::block('faq', ['title' => 'সাধারণ প্রশ্ন', 'items' => [
            ['question' => 'ডেলিভারি পেতে কত দিন লাগে?', 'answer' => 'ঢাকার ভেতরে ১–২ দিন, ঢাকার বাইরে ২–৪ দিন।'],
            ['question' => 'পণ্য দেখে টাকা দেওয়া যাবে?', 'answer' => 'হ্যাঁ, ডেলিভারি ম্যানের সামনে পণ্য দেখে টাকা পরিশোধ করবেন।'],
        ]]);
    }
}
