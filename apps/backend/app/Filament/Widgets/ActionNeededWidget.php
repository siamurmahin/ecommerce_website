<?php

namespace App\Filament\Widgets;

use App\Domain\Catalog\Models\Product;
use App\Domain\Order\Enums\IncompleteStatus;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\PaymentStatus;
use App\Domain\Order\Models\IncompleteOrder;
use App\Domain\Order\Models\Order;
use App\Domain\Review\Enums\QuestionStatus;
use App\Domain\Review\Enums\ReviewStatus;
use App\Domain\Review\Models\ProductQuestion;
use App\Domain\Review\Models\Review;
use App\Filament\Resources\IncompleteOrders\IncompleteOrderResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\ProductQuestions\ProductQuestionResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Reviews\ReviewResource;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Work waiting right now, whatever period the dashboard shows. */
class ActionNeededWidget extends StatsOverviewWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 1;

    protected ?string $heading = 'এখন করণীয়';

    protected ?string $pollingInterval = '60s';

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $stats = [];

        if ($user->can('viewAny', Order::class)) {
            $stats[] = Stat::make('কনফার্ম বাকি', Order::where('status', OrderStatus::Pending)->count())
                ->description('নতুন অর্ডার — কল করে কনফার্ম করুন')->icon(Heroicon::OutlinedPhoneArrowUpRight)
                ->color('warning')->url(OrderResource::getUrl('index', ['activeTab' => OrderStatus::Pending->value]));
            $stats[] = Stat::make('পেমেন্ট যাচাই', Order::where('payment_status', PaymentStatus::Pending)->count())
                ->description('বিকাশ/নগদের TrxID মেলানো বাকি')->icon(Heroicon::OutlinedBanknotes)
                ->color('info')->url(OrderResource::getUrl('index', ['activeTab' => 'payment']));
        }
        if ($user->can('viewAny', IncompleteOrder::class)) {
            $stats[] = Stat::make('ইনকমপ্লিট অর্ডার', IncompleteOrder::where('status', IncompleteStatus::Open)->count())
                ->description('ফোন দিয়েছে, অর্ডার করেনি')->icon(Heroicon::OutlinedShoppingCart)
                ->url(IncompleteOrderResource::getUrl('index'));
        }
        if ($user->can('viewAny', Review::class)) {
            $stats[] = Stat::make('রিভিউ অনুমোদন', Review::where('status', ReviewStatus::Pending)->count())
                ->description('দেখে অনুমোদন দিন')->icon(Heroicon::OutlinedStar)
                ->url(ReviewResource::getUrl('index'));
        }
        if ($user->can('viewAny', ProductQuestion::class)) {
            $stats[] = Stat::make('প্রশ্নের উত্তর', ProductQuestion::where('status', QuestionStatus::Pending)->count())
                ->description('পণ্য পেজে কাস্টমারের প্রশ্ন')->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->url(ProductQuestionResource::getUrl('index'));
        }
        if ($user->can('viewAny', Product::class)) {
            $stats[] = Stat::make('কম স্টক', LowStockWidget::query()->count())
                ->description('স্টক শেষের পথে')->icon(Heroicon::OutlinedArchiveBox)->color('danger')
                ->url(ProductResource::getUrl('index'));
        }

        return $stats;
    }
}
