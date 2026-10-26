<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('returns.enabled', true);
        $this->migrator->add('returns.window_days', 7);
        $this->migrator->add('returns.policy', 'ডেলিভারির ৭ দিনের মধ্যে অব্যবহৃত পণ্য মূল প্যাকেটে ফেরত দিলে রিটার্ন বা এক্সচেঞ্জ করা যাবে। ভাঙা বা ভুল পণ্য হলে ফেরতের ডেলিভারি খরচ আমরা দেব।');
        $this->migrator->add('returns.policy_en', 'Unused items in their original packaging can be returned or exchanged within 7 days of delivery. If an item arrived damaged or wrong, we cover the return delivery.');
    }
};
