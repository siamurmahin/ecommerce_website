<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('site.hotline', '01700-000000');
        $this->migrator->add('site.whatsapp', '01700000000');
        $this->migrator->add('site.email', null);
        $this->migrator->add('site.address', null);
        $this->migrator->add('site.address_en', null);
        $this->migrator->add('site.announcement', 'সারা বাংলাদেশে ক্যাশ অন ডেলিভারি');
        $this->migrator->add('site.announcement_en', 'Cash on delivery across Bangladesh');
        $this->migrator->add('site.footer_about', 'Looks Jhorna — পাঞ্জাবি, বোরকা, আতর আর ইবাদত সামগ্রীর বিশ্বস্ত অনলাইন শপ।');
        $this->migrator->add('site.footer_about_en', 'Looks Jhorna — your trusted online shop for panjabi, borka, attar and prayer essentials.');
        $this->migrator->add('site.facebook_url', null);
        $this->migrator->add('site.instagram_url', null);
        $this->migrator->add('site.youtube_url', null);
        $this->migrator->add('site.tiktok_url', null);
    }
};
