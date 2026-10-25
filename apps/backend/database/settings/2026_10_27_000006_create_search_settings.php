<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('search.synonyms', [
            'আতর, attar, ator, itr, ittar, সুগন্ধি, perfume',
            'পাঞ্জাবি, panjabi, punjabi, panjabee',
            'বোরকা, borka, burka, burqa, বোরখা',
            'আবায়া, abaya',
            'হিজাব, hijab, hizab',
            'জায়নামাজ, jaynamaz, janamaz, jainamaz, prayer mat, মুসাল্লা',
            'তাসবিহ, tasbih, tasbeeh, tasbi',
            'টুপি, tupi, topi, cap',
            'জুব্বা, jubba, thobe, থোব',
            'খেজুর, khejur, dates, আজওয়া, ajwa',
            'মধু, modhu, honey',
            'কালোজিরা, kalojira, black seed',
            'বই, boi, book',
            'গিফট, gift, উপহার',
        ]);
        $this->migrator->add('search.popular', ['আতর', 'পাঞ্জাবি', 'জায়নামাজ', 'খেজুর', 'হিজাব']);
    }
};
