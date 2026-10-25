<?php

namespace App\Filament\Resources\Reviews\Pages;

use App\Domain\Review\Enums\ReviewStatus;
use App\Filament\Resources\Reviews\ReviewResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageReviews extends ManageRecords
{
    protected static string $resource = ReviewResource::class;

    protected function getHeaderActions(): array
    {
        // Staff-entered reviews (from Messenger, Facebook…) are marked as such.
        return [CreateAction::make()->label('রিভিউ যোগ করুন')->mutateDataUsing(fn (array $data) => [...$data, 'source' => 'admin'])];
    }

    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('অপেক্ষমাণ')->modifyQueryUsing(fn (Builder $query) => $query->where('status', ReviewStatus::Pending)),
            'approved' => Tab::make('প্রকাশিত')->modifyQueryUsing(fn (Builder $query) => $query->where('status', ReviewStatus::Approved)),
            'all' => Tab::make('সব'),
        ];
    }
}
