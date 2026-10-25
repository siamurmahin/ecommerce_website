<?php

namespace App\Filament\Resources\Reviews;

use App\Domain\Review\Enums\ReviewStatus;
use App\Domain\Review\Models\Review;
use App\Filament\Resources\Reviews\Pages\ManageReviews;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'কাস্টমার';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'রিভিউ';

    protected static ?string $pluralModelLabel = 'রিভিউ';

    public static function getNavigationBadge(): ?string
    {
        $pending = Review::where('status', ReviewStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'অনুমোদনের অপেক্ষায়';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                Select::make('product_id')->label('পণ্য')->relationship('product', 'name')->searchable()->preload()->required()
                    ->disabled(fn (?Review $record) => $record?->customer_id !== null),
                TextInput::make('name')->label('কাস্টমারের নাম')->required()->maxLength(100),
                Select::make('rating')->label('রেটিং')->options([5 => '★★★★★ (৫)', 4 => '★★★★ (৪)', 3 => '★★★ (৩)', 2 => '★★ (২)', 1 => '★ (১)'])
                    ->default(5)->required()->native(false),
                Select::make('status')->label('স্ট্যাটাস')->options(ReviewStatus::class)->default(ReviewStatus::Approved)->required()->native(false),
                Textarea::make('body')->label('রিভিউ')->rows(3)->maxLength(1000)->columnSpanFull(),
                FileUpload::make('images')->label('ছবি / স্ক্রিনশট')->image()->multiple()->reorderable()->maxFiles(6)
                    ->disk('public')->directory('reviews')->maxSize(4096)->panelLayout('grid')->columnSpanFull()
                    ->helperText('Messenger বা Facebook কমেন্টের স্ক্রিনশটও দেওয়া যায়'),
                Toggle::make('is_verified')->label('কেনা নিশ্চিত (ভেরিফায়েড)')->inline(false)
                    ->disabled(fn (?Review $record) => $record?->customer_id !== null),
                Textarea::make('reply')->label('দোকানের উত্তর (সাইটে দেখাবে)')->rows(2)->maxLength(1000)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('product'))
            ->columns([
                TextColumn::make('product.name')->label('পণ্য')->limit(30)->searchable()
                    ->description(fn (Review $record) => $record->name),
                TextColumn::make('rating')->label('রেটিং')->sortable()
                    ->formatStateUsing(fn (int $state) => str_repeat('★', $state).str_repeat('☆', 5 - $state))->color('warning'),
                TextColumn::make('body')->label('রিভিউ')->limit(60)->wrap()->placeholder('—')->searchable(),
                ImageColumn::make('images')->label('ছবি')->disk('public')->circular()->stacked()->limit(3)->toggleable(),
                IconColumn::make('is_verified')->label('ভেরিফায়েড')->boolean()->toggleable(),
                TextColumn::make('status')->label('স্ট্যাটাস')->badge()->sortable(),
                TextColumn::make('created_at')->label('তারিখ')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('স্ট্যাটাস')->options(ReviewStatus::class),
                SelectFilter::make('rating')->label('রেটিং')->options([5 => '৫', 4 => '৪', 3 => '৩', 2 => '২', 1 => '১']),
                SelectFilter::make('product_id')->label('পণ্য')->relationship('product', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                Action::make('approve')->label('প্রকাশ করুন')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->visible(fn (Review $record) => $record->status !== ReviewStatus::Approved)
                    ->authorize('update')
                    ->action(fn (Review $record) => $record->update(['status' => ReviewStatus::Approved])),
                Action::make('reject')->label('বাতিল')->icon(Heroicon::OutlinedXMark)->color('gray')
                    ->visible(fn (Review $record) => $record->status !== ReviewStatus::Rejected)
                    ->authorize('update')
                    ->action(fn (Review $record) => $record->update(['status' => ReviewStatus::Rejected])),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')->label('প্রকাশ করুন')->icon(Heroicon::OutlinedCheck)
                        ->authorize('updateAny')
                        ->action(fn (Collection $records) => $records->each->update(['status' => ReviewStatus::Approved])),
                    BulkAction::make('reject')->label('বাতিল করুন')->icon(Heroicon::OutlinedXMark)
                        ->authorize('updateAny')
                        ->action(fn (Collection $records) => $records->each->update(['status' => ReviewStatus::Rejected])),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageReviews::route('/')];
    }
}
