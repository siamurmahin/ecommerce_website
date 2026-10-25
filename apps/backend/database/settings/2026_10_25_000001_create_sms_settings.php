<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('sms.enabled', false);
        $this->migrator->add('sms.gateway', 'log');
        $this->migrator->addEncrypted('sms.api_key', null);
        $this->migrator->add('sms.sender_id', null);
        $this->migrator->add('sms.admin_phones', null);
        $this->migrator->add('sms.templates', [
            'order_placed' => ['enabled' => true, 'body' => 'প্রিয় {name}, আপনার অর্ডার {order} পেয়েছি। মোট {total}। শিগগিরই কল করে কনফার্ম করব। - {shop}'],
            'confirmed' => ['enabled' => true, 'body' => 'আপনার অর্ডার {order} কনফার্ম হয়েছে। ট্র্যাক করুন: {track_url} - {shop}'],
            'shipped' => ['enabled' => true, 'body' => 'আপনার অর্ডার {order} কুরিয়ারে পাঠানো হয়েছে। ডেলিভারির সময় {total} প্রস্তুত রাখুন। - {shop}'],
            'delivered' => ['enabled' => false, 'body' => 'অর্ডার {order} ডেলিভারি হয়েছে। {shop}-এর সাথে থাকার জন্য ধন্যবাদ!'],
            'cancelled' => ['enabled' => false, 'body' => 'আপনার অর্ডার {order} বাতিল হয়েছে। প্রশ্ন থাকলে কল করুন {hotline}। - {shop}'],
            'admin_new_order' => ['enabled' => true, 'body' => 'নতুন অর্ডার {order}: {name}, {phone}, মোট {total}'],
        ]);
    }
};
