<?php

namespace Database\Seeders;

use App\Domain\Content\Models\Page;
use App\Settings\SiteSettings;
use Illuminate\Database\Seeder;

/**
 * Starter copy for the footer pages so links work on day one. Admins rewrite it under কনটেন্ট → স্ট্যাটিক পেজ;
 * existing pages are never overwritten.
 */
class PageSeeder extends Seeder
{
    public function run(): void
    {
        $hotline = app(SiteSettings::class)->hotline;

        $pages = [
            'about' => ['আমাদের সম্পর্কে', 'About us',
                '<p>Looks Jhorna একটি বাংলাদেশি অনলাইন শপ — পাঞ্জাবি, বোরকা, হিজাব, আতর আর ইবাদত সামগ্রী এক জায়গায়। প্রতিটি পণ্য আমরা নিজেরা বাছাই করি, যাতে মান আর দামের ভারসাম্য ঠিক থাকে।</p><p>সারা বাংলাদেশে ক্যাশ অন ডেলিভারিতে পণ্য পৌঁছে দিই।</p>',
                '<p>Looks Jhorna is a Bangladeshi online shop for panjabi, borka, hijab, attar and prayer essentials. We hand-pick every product so quality and price stay in balance.</p><p>We deliver across Bangladesh with cash on delivery.</p>'],
            'contact' => ['যোগাযোগ', 'Contact',
                "<p>অর্ডার, সাইজ বা ডেলিভারি নিয়ে যেকোনো প্রশ্নে আমাদের সাথে যোগাযোগ করুন।</p><ul><li>হটলাইন: {$hotline}</li><li>সময়: প্রতিদিন সকাল ১০টা – রাত ১০টা</li></ul>",
                "<p>Reach us with any question about orders, sizes or delivery.</p><ul><li>Hotline: {$hotline}</li><li>Hours: every day, 10am – 10pm</li></ul>"],
            'faq' => ['সাধারণ প্রশ্ন', 'FAQ',
                '<h3>কীভাবে অর্ডার করব?</h3><p>পণ্য বেছে "অর্ডার করুন" চাপুন, নাম-ফোন-ঠিকানা দিন। আমরা ফোন করে অর্ডার কনফার্ম করব।</p><h3>ডেলিভারিতে কত দিন লাগে?</h3><p>ঢাকার ভেতরে সাধারণত ১–২ দিন, ঢাকার বাইরে ২–৫ দিন।</p><h3>টাকা কখন দেব?</h3><p>পণ্য হাতে পেয়ে ডেলিভারিম্যানকে টাকা দেবেন।</p>',
                '<h3>How do I order?</h3><p>Pick a product, tap "Order now" and enter your name, phone and address. We call to confirm.</p><h3>How long does delivery take?</h3><p>Usually 1–2 days inside Dhaka and 2–5 days elsewhere.</p><h3>When do I pay?</h3><p>Pay the delivery person when the parcel arrives.</p>'],
            'shipping-policy' => ['শিপিং পলিসি', 'Shipping policy',
                '<p>আমরা সারা বাংলাদেশে ডেলিভারি দিই। ডেলিভারি চার্জ আপনার জেলা অনুযায়ী চেকআউটে দেখানো হয়।</p><p>অর্ডার কনফার্ম হওয়ার পর সাধারণত ঢাকায় ১–২ দিন এবং ঢাকার বাইরে ২–৫ কর্মদিবসে পণ্য পৌঁছায়।</p>',
                '<p>We deliver across Bangladesh. The delivery charge for your district is shown at checkout.</p><p>Once confirmed, orders usually arrive in 1–2 days inside Dhaka and 2–5 working days elsewhere.</p>'],
            'return-policy' => ['রিটার্ন পলিসি', 'Return policy',
                '<p>পণ্য হাতে পাওয়ার ৭ দিনের মধ্যে রিটার্ন বা এক্সচেঞ্জের অনুরোধ করতে পারবেন, যদি পণ্যটি অব্যবহৃত ও ট্যাগসহ থাকে।</p><p>ভুল বা ত্রুটিপূর্ণ পণ্য পেলে ডেলিভারির সময়ই জানান — আমরা খরচ ছাড়াই বদলে দেব।</p>',
                '<p>You can ask for a return or exchange within 7 days of delivery, as long as the item is unused and tagged.</p><p>If you receive a wrong or faulty item, tell us at delivery and we will replace it free of charge.</p>'],
            'privacy-policy' => ['প্রাইভেসি পলিসি', 'Privacy policy',
                '<p>অর্ডার পৌঁছে দেওয়ার জন্য আমরা শুধু আপনার নাম, ফোন নম্বর আর ঠিকানা সংগ্রহ করি। এই তথ্য কুরিয়ার ছাড়া কারও সাথে শেয়ার করা হয় না।</p>',
                '<p>We only collect your name, phone number and address to deliver your order. This is shared with no one except the courier.</p>'],
            'terms' => ['শর্তাবলি', 'Terms',
                '<p>এই সাইট থেকে অর্ডার করলে আপনি আমাদের শিপিং ও রিটার্ন পলিসিতে সম্মত হচ্ছেন। স্টক ও দাম পরিবর্তন হতে পারে; চূড়ান্ত দাম অর্ডার কনফার্মেশনের সময় নিশ্চিত করা হয়।</p>',
                '<p>By ordering from this site you agree to our shipping and return policies. Stock and prices may change; the final price is confirmed when your order is confirmed.</p>'],
        ];

        foreach ($pages as $slug => [$title, $titleEn, $content, $contentEn]) {
            Page::firstOrCreate(['slug' => $slug], [
                'title' => $title, 'title_en' => $titleEn, 'content' => $content, 'content_en' => $contentEn,
            ]);
        }
    }
}
