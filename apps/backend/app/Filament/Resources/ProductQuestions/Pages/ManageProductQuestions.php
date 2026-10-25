<?php

namespace App\Filament\Resources\ProductQuestions\Pages;

use App\Domain\Review\Enums\QuestionStatus;
use App\Filament\Resources\ProductQuestions\ProductQuestionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageProductQuestions extends ManageRecords
{
    protected static string $resource = ProductQuestionResource::class;

    protected function getHeaderActions(): array
    {
        // Staff can add common questions (FAQ) themselves.
        return [CreateAction::make()->label('প্রশ্ন যোগ করুন')->mutateDataUsing(fn (array $data) => [...$data, 'answered_by' => filled($data['answer'] ?? null) ? auth()->id() : null])];
    }

    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('উত্তর বাকি')->modifyQueryUsing(fn (Builder $query) => $query->where('status', QuestionStatus::Pending)),
            'published' => Tab::make('প্রকাশিত')->modifyQueryUsing(fn (Builder $query) => $query->where('status', QuestionStatus::Published)),
            'all' => Tab::make('সব'),
        ];
    }
}
