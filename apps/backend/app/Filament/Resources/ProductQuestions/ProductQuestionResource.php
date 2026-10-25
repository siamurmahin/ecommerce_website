<?php

namespace App\Filament\Resources\ProductQuestions;

use App\Domain\Review\Enums\QuestionStatus;
use App\Domain\Review\Models\ProductQuestion;
use App\Filament\Resources\ProductQuestions\Pages\ManageProductQuestions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ProductQuestionResource extends Resource
{
    protected static ?string $model = ProductQuestion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'কাস্টমার';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'প্রশ্ন';

    protected static ?string $pluralModelLabel = 'পণ্যের প্রশ্নোত্তর';

    public static function getNavigationBadge(): ?string
    {
        $pending = ProductQuestion::where('status', QuestionStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'উত্তরের অপেক্ষায়';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                Select::make('product_id')->label('পণ্য')->relationship('product', 'name')->searchable()->preload()->required(),
                TextInput::make('name')->label('নাম')->required()->maxLength(100),
                TextInput::make('phone')->label('মোবাইল (সাইটে দেখায় না)')->tel()->maxLength(20),
                Select::make('status')->label('স্ট্যাটাস')->options(QuestionStatus::class)->default(QuestionStatus::Published)->required()->native(false),
                Textarea::make('question')->label('প্রশ্ন')->required()->rows(2)->maxLength(500)->columnSpanFull(),
                Textarea::make('answer')->label('উত্তর')->rows(3)->maxLength(2000)->columnSpanFull()
                    ->helperText('উত্তর দিলে প্রশ্নটি নিজে থেকেই সাইটে প্রকাশ হয়'),
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
                    ->description(fn (ProductQuestion $record) => $record->name.($record->phone ? ' · '.$record->phone : '')),
                TextColumn::make('question')->label('প্রশ্ন')->limit(70)->wrap()->searchable(),
                TextColumn::make('answer')->label('উত্তর')->limit(60)->wrap()->placeholder('—')->toggleable(),
                TextColumn::make('status')->label('স্ট্যাটাস')->badge()->sortable(),
                TextColumn::make('created_at')->label('তারিখ')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('স্ট্যাটাস')->options(QuestionStatus::class),
                SelectFilter::make('product_id')->label('পণ্য')->relationship('product', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                Action::make('answer')->label('উত্তর দিন')->icon(Heroicon::OutlinedChatBubbleLeft)->color('primary')
                    ->authorize('update')
                    ->modalDescription(fn (ProductQuestion $record) => $record->question)
                    ->fillForm(fn (ProductQuestion $record) => ['answer' => $record->answer])
                    ->schema([Textarea::make('answer')->label('উত্তর')->required()->rows(4)->maxLength(2000)])
                    ->action(function (ProductQuestion $record, array $data) {
                        $record->answered_by = auth()->id();
                        $record->update(['answer' => $data['answer']]);
                    }),
                Action::make('hide')->label('লুকান')->icon(Heroicon::OutlinedEyeSlash)->color('gray')
                    ->visible(fn (ProductQuestion $record) => $record->status !== QuestionStatus::Hidden)
                    ->authorize('update')
                    ->action(fn (ProductQuestion $record) => $record->update(['status' => QuestionStatus::Hidden])),
                Action::make('publish')->label('প্রকাশ করুন')->icon(Heroicon::OutlinedEye)->color('success')
                    ->visible(fn (ProductQuestion $record) => $record->status === QuestionStatus::Hidden && filled($record->answer))
                    ->authorize('update')
                    ->action(fn (ProductQuestion $record) => $record->update(['status' => QuestionStatus::Published])),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageProductQuestions::route('/')];
    }
}
