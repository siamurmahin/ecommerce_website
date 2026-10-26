<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('review_request.enabled', false);
        $this->migrator->add('review_request.delay_days', 3);
        $this->migrator->add('review_request.subject', '{name}, আপনার অর্ডার {order} কেমন লাগল?');
        $this->migrator->add('review_request.body', "প্রিয় {name},\n\nআশা করি অর্ডার {order}-এর পণ্যগুলো আপনার ভালো লেগেছে। দুই মিনিট সময় দিয়ে একটা রিভিউ লিখলে অন্য ক্রেতারা উপকৃত হবেন, আর আমরাও আরও ভালো করতে পারব।\n\nধন্যবাদ,\n{shop}");
    }
};
