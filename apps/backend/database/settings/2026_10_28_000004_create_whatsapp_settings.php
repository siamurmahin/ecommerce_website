<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('whatsapp.enabled', false);
        $this->migrator->add('whatsapp.gateway', 'log');
        $this->migrator->add('whatsapp.phone_number_id', null);
        $this->migrator->addEncrypted('whatsapp.access_token', null);
        $this->migrator->add('whatsapp.admin_phones', null);
        $this->migrator->add('whatsapp.templates', [
            'order_placed' => ['enabled' => true, 'template' => 'order_placed', 'language' => 'bn', 'body' => 'প্রিয় {name}, আপনার অর্ডার {order} পেয়েছি। মোট {total}। শিগগিরই কল করে কনফার্ম করব।'],
            'confirmed' => ['enabled' => true, 'template' => 'order_confirmed', 'language' => 'bn', 'body' => 'আপনার অর্ডার {order} কনফার্ম হয়েছে। ট্র্যাক করুন: {track_url}'],
            'shipped' => ['enabled' => true, 'template' => 'order_shipped', 'language' => 'bn', 'body' => 'আপনার অর্ডার {order} কুরিয়ারে পাঠানো হয়েছে। ডেলিভারির সময় {total} প্রস্তুত রাখুন।'],
            'delivered' => ['enabled' => true, 'template' => 'order_delivered', 'language' => 'bn', 'body' => 'অর্ডার {order} ডেলিভারি হয়েছে। {shop}-এর সাথে থাকার জন্য ধন্যবাদ!'],
            'cancelled' => ['enabled' => false, 'template' => 'order_cancelled', 'language' => 'bn', 'body' => 'আপনার অর্ডার {order} বাতিল হয়েছে। প্রশ্ন থাকলে কল করুন {hotline}।'],
            'admin_new_order' => ['enabled' => false, 'template' => 'admin_new_order', 'language' => 'bn', 'body' => 'নতুন অর্ডার {order}: {name}, {phone}, মোট {total}'],
        ]);
    }
};
