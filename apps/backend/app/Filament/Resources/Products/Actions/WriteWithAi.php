<?php

namespace App\Filament\Resources\Products\Actions;

use App\Domain\Ai\AiUnavailable;
use App\Domain\Ai\Contracts\Copywriter;
use App\Domain\Catalog\Models\Category;
use App\Settings\AiSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\RateLimiter;

/** Drafts descriptions, highlights and SEO from what's already on the form; staff review before saving. */
class WriteWithAi
{
    private const PARTS = [
        'description' => 'বিস্তারিত বিবরণ (বাংলা ও English)',
        'highlights' => 'সংক্ষিপ্ত হাইলাইট (বাংলা ও English)',
        'seo' => 'মেটা টাইটেল ও মেটা বিবরণ',
    ];

    public static function make(): Action
    {
        return Action::make('writeWithAi')
            ->label('AI দিয়ে লিখুন')
            ->icon(Heroicon::OutlinedSparkles)
            ->link()
            ->visible(fn () => app(AiSettings::class)->ready())
            ->modalHeading('AI দিয়ে বিবরণ লিখুন')
            ->modalDescription('নাম, ক্যাটাগরি, দাম আর স্পেসিফিকেশন দেখে লিখবে — যা দেওয়া নেই তা বানিয়ে লিখবে না। ফর্মে বসিয়ে দেবে, সেভ করার আগে পড়ে ঠিক করে নিন।')
            ->modalSubmitActionLabel('লিখুন')
            ->schema([
                Textarea::make('notes')->label('আর কী বলতে চান? (ঐচ্ছিক)')->rows(3)->maxLength(1000)
                    ->placeholder('যেমন: তুরস্ক থেকে আনা, ঈদের জন্য উপযুক্ত, মেশিনে ধোয়া যায়'),
                CheckboxList::make('parts')->label('কোনগুলো লিখবে')->options(self::PARTS)->default(array_keys(self::PARTS))->required(),
            ])
            ->action(function (array $data, Get $get, Set $set, Action $action) {
                if (blank($get('name'))) {
                    Notification::make()->warning()->title('আগে পণ্যের নাম দিন')->send();
                    $action->halt();
                }

                $key = 'ai-copy:'.auth()->id();
                if (RateLimiter::tooManyAttempts($key, 10)) {
                    Notification::make()->warning()->title('একটু থামুন')->body('এক মিনিটে ১০ বারের বেশি লেখানো যায় না।')->send();
                    $action->halt();
                }
                RateLimiter::hit($key);

                try {
                    $copy = app(Copywriter::class)->productCopy([
                        'name' => (string) $get('name'),
                        'name_en' => $get('name_en'),
                        'category' => Category::find($get('category_id'))?->name,
                        'price' => filled($get('price')) ? (int) round((float) $get('price') * 100) : null,
                        'specifications' => collect($get('specifications') ?? [])
                            ->filter(fn ($row) => filled($row['label'] ?? null) && filled($row['value'] ?? null))
                            ->map(fn ($row) => ['label' => (string) $row['label'], 'value' => (string) $row['value']])->values()->all(),
                        'notes' => $data['notes'] ?? null,
                        'current' => $get('description'),
                    ]);
                } catch (AiUnavailable $e) {
                    Notification::make()->danger()->title('লেখা যায়নি')->body($e->getMessage())->send();
                    $action->halt();

                    return;
                }

                $parts = $data['parts'];
                if (in_array('description', $parts, true)) {
                    $set('description', $copy->description);
                    $set('description_en', $copy->descriptionEn);
                }
                if (in_array('highlights', $parts, true)) {
                    $set('short_description', $copy->shortDescription);
                    $set('short_description_en', $copy->shortDescriptionEn);
                }
                if (in_array('seo', $parts, true)) {
                    $set('meta_title', $copy->metaTitle);
                    $set('meta_description', $copy->metaDescription);
                }

                Notification::make()->success()->title('লেখা হয়েছে')->body('ফর্মে বসানো হয়েছে — পড়ে দেখে সেভ করুন।')->send();
            });
    }
}
