<?php

namespace App\Filament\Pages;

use App\Domain\Finance\Models\Expense;
use App\Domain\Report\DateRange;
use App\Domain\Report\SalesReport;
use App\Filament\Widgets\SalesStatsWidget;
use App\Support\Money;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/** Profit and loss, and sales broken down by day, product, district, source, landing page, coupon and payment method; each exports to CSV. */
class Reports extends Page implements HasTable
{
    use HasPageShield, InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'রিপোর্ট';

    protected static ?string $navigationLabel = 'সেলস রিপোর্ট';

    protected static ?string $title = 'সেলস রিপোর্ট';

    protected static ?string $slug = 'reports';

    /**
     * Report key => label and columns (key => [label, kind]); kind is text, number, money or date.
     *
     * @var array<string, array{label: string, columns: array<string, array{0: string, 1: string}>}>
     */
    private const REPORTS = [
        'daily' => ['label' => 'দিনভিত্তিক', 'columns' => ['date' => ['তারিখ', 'date'], 'orders' => ['অর্ডার', 'number'], 'sales' => ['বিক্রি (অর্ডার)', 'number'], 'revenue' => ['বিক্রি', 'money']]],
        'profit' => ['label' => 'লাভ-ক্ষতি', 'columns' => ['item' => ['হিসাব', 'text'], 'amount' => ['টাকা', 'money']]],
        'products' => ['label' => 'পণ্য', 'columns' => ['name' => ['পণ্য', 'text'], 'quantity' => ['পিস', 'number'], 'orders' => ['অর্ডার', 'number'], 'revenue' => ['বিক্রি', 'money'], 'purchase' => ['ক্রয় মূল্য', 'money'], 'extra' => ['অন্যান্য খরচ', 'money'], 'delivery' => ['ডেলিভারি খরচ', 'money'], 'cost' => ['মোট খরচ', 'money'], 'profit' => ['লাভ', 'money']]],
        'districts' => ['label' => 'জেলা', 'columns' => ['district' => ['জেলা', 'text'], 'orders' => ['অর্ডার', 'number'], 'delivered' => ['ডেলিভারি', 'number'], 'returned' => ['রিটার্ন', 'number'], 'revenue' => ['বিক্রি', 'money']]],
        'sources' => ['label' => 'সোর্স ও ক্যাম্পেইন', 'columns' => ['source' => ['সোর্স', 'text'], 'campaign' => ['ক্যাম্পেইন', 'text'], 'orders' => ['অর্ডার', 'number'], 'sales' => ['বিক্রি (অর্ডার)', 'number'], 'revenue' => ['বিক্রি', 'money']]],
        'campaigns' => ['label' => 'ক্যাম্পেইন ROAS', 'columns' => ['campaign' => ['ক্যাম্পেইন', 'text'], 'orders' => ['অর্ডার', 'number'], 'sales' => ['বিক্রি (অর্ডার)', 'number'], 'revenue' => ['বিক্রি', 'money'], 'spend' => ['বিজ্ঞাপন খরচ', 'money'], 'roas' => ['ROAS', 'text'], 'profit' => ['বিজ্ঞাপনের পর লাভ', 'money']]],
        'returns' => ['label' => 'রিটার্নের কারণ', 'columns' => ['reason' => ['কারণ', 'text'], 'returns' => ['রিটার্ন', 'number'], 'exchanges' => ['এক্সচেঞ্জ', 'number'], 'pieces' => ['পিস', 'number'], 'refunded' => ['ফেরত টাকা', 'money']]],
        'staff' => ['label' => 'স্টাফের কাজ', 'columns' => ['staff' => ['স্টাফ', 'text'], 'confirmed' => ['কনফার্ম', 'number'], 'cancelled' => ['বাতিল', 'number'], 'shipped' => ['শিপ', 'number'], 'changes' => ['মোট স্ট্যাটাস বদল', 'number'], 'delivered' => ['কনফার্ম করা থেকে ডেলিভারড', 'number'], 'lost' => ['কনফার্ম করা থেকে বাতিল/রিটার্ন', 'number'], 'success' => ['সফলতা', 'text']]],
        'landing' => ['label' => 'ল্যান্ডিং পেজ', 'columns' => ['title' => ['পেজ', 'text'], 'orders' => ['অর্ডার', 'number'], 'sales' => ['বিক্রি (অর্ডার)', 'number'], 'revenue' => ['বিক্রি', 'money']]],
        'coupons' => ['label' => 'কুপন', 'columns' => ['code' => ['কুপন', 'text'], 'orders' => ['অর্ডার', 'number'], 'discount' => ['ছাড়', 'money'], 'revenue' => ['বিক্রি', 'money']]],
        'payments' => ['label' => 'পেমেন্ট মাধ্যম', 'columns' => ['method' => ['মাধ্যম', 'text'], 'orders' => ['অর্ডার', 'number'], 'revenue' => ['বিক্রি', 'money']]],
    ];

    /** @var array{report: string, period: string, from: ?string, until: ?string} */
    public array $filters = ['report' => 'daily', 'period' => '30d', 'from' => null, 'until' => null];

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->statePath('filters')->live()->components([
            ToggleButtons::make('report')->hiddenLabel()->inline()->grouped()
                ->options(array_map(fn ($r) => $r['label'], self::REPORTS))
                ->afterStateUpdated(fn () => $this->resetTable()),
            Grid::make(['default' => 1, 'md' => 3])->schema([
                Select::make('period')->label('সময়')->options(DateRange::PRESETS)->selectablePlaceholder(false)->native(false),
                DatePicker::make('from')->label('শুরু')->native(false)->visible(fn (Get $get) => $get('period') === 'custom'),
                DatePicker::make('until')->label('শেষ')->native(false)->visible(fn (Get $get) => $get('period') === 'custom'),
            ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedSchema::make('filtersForm'), EmbeddedTable::make()]);
    }

    protected function getHeaderWidgets(): array
    {
        return [SalesStatsWidget::class];
    }

    public function getWidgetData(): array
    {
        return ['pageFilters' => $this->filters];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')->label('CSV ডাউনলোড')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->action(fn () => $this->export()),
        ];
    }

    public function table(Table $table): Table
    {
        $columns = self::REPORTS[$this->key()]['columns'];

        return $table
            ->records(fn () => collect($this->rows())->mapWithKeys(fn ($row, $i) => ["r{$i}" => $row])->all())
            ->columns(collect($columns)->map(fn (array $c, string $key) => match ($c[1]) {
                'money' => TextColumn::make($key)->label($c[0])->formatStateUsing(fn ($state) => Money::format((int) $state))->alignEnd()->placeholder('—')
                    ->weight(fn (array $record) => ($record['strong'] ?? false) ? FontWeight::Bold : null)
                    ->color(fn (array $record, $state) => ($record['strong'] ?? false) && $state < 0 ? 'danger' : null),
                'number' => TextColumn::make($key)->label($c[0])->numeric()->alignEnd(),
                'date' => TextColumn::make($key)->label($c[0])->formatStateUsing(fn ($state) => CarbonImmutable::parse($state)->translatedFormat('d M Y, l')),
                default => TextColumn::make($key)->label($c[0])->wrap()
                    ->weight(fn (array $record) => ($record['strong'] ?? false) ? FontWeight::Bold : null)
                    ->color(fn (array $record) => ($record['muted'] ?? false) ? 'gray' : null),
            })->values()->all())
            ->paginated([25, 50, 100])
            ->emptyStateHeading('এই সময়ে কোনো ডেটা নেই')
            ->emptyStateIcon(Heroicon::OutlinedChartBar);
    }

    /** @return list<array<string, mixed>> */
    private function rows(): array
    {
        $report = SalesReport::for($this->range());

        return match ($this->key()) {
            'daily' => array_reverse($report->daily()),
            'profit' => $this->profitRows($report),
            'products' => $report->products(),
            'districts' => $report->districts(),
            'sources' => $report->sources(),
            'campaigns' => $report->campaigns(),
            'returns' => $report->returns(),
            'staff' => $report->staff(),
            'landing' => $report->landingPages(),
            'coupons' => $report->coupons(),
            'payments' => $report->paymentMethods(),
        };
    }

    /** লাভ-ক্ষতি as a statement: goods sold − their costs = gross profit − expenses = net profit. */
    private function profitRows(SalesReport $report): array
    {
        $p = $report->profit();
        $rows = [
            ['item' => 'পণ্য বিক্রি (ছাড়ের পর, ডেলিভারি চার্জ বাদে)', 'amount' => $p['goods']],
            ['item' => '(−) আংশিক রিটার্নে ফেরত দেওয়া টাকা', 'amount' => -$p['refunds']],
            ['item' => '(−) পণ্যের ক্রয় মূল্য', 'amount' => -$p['purchase']],
            ['item' => '(−) পণ্যের অন্যান্য খরচ (র‍্যাপিং, ক্যারিং, গিফট র‍্যাপ, সাধারণ খরচ)', 'amount' => -$p['extra']],
            ['item' => '(+) রিটার্নে স্টকে ফেরা পণ্যের খরচ', 'amount' => $p['restocked']],
            ['item' => '(−) ফ্রি ডেলিভারির খরচ (দোকান দিয়েছে)', 'amount' => -$p['delivery']],
            ['item' => 'গ্রস প্রফিট', 'amount' => $p['gross'], 'strong' => true],
        ];
        foreach ($p['expenses'] as $category => $amount) {
            $rows[] = ['item' => '(−) '.(Expense::CATEGORIES[$category] ?? $category), 'amount' => -$amount];
        }
        $rows[] = ['item' => '(−) মোট খরচ', 'amount' => -$p['expenses_total']];
        $rows[] = ['item' => 'নেট প্রফিট', 'amount' => $p['net'], 'strong' => true];
        if ($p['uncosted'] > 0) {
            $rows[] = ['item' => "⚠ {$p['uncosted']} পিস পণ্যের ক্রয় মূল্য দেওয়া নেই, তাই লাভ আসলের চেয়ে বেশি দেখাচ্ছে — পণ্যের পেজে ক্রয় মূল্য দিন।", 'amount' => null, 'muted' => true];
        }

        return $rows;
    }

    private function key(): string
    {
        return array_key_exists($this->filters['report'] ?? '', self::REPORTS) ? $this->filters['report'] : 'daily';
    }

    private function range(): DateRange
    {
        return DateRange::from($this->filters['period'] ?? '30d', $this->filters['from'] ?? null, $this->filters['until'] ?? null);
    }

    public function export(): StreamedResponse
    {
        $columns = self::REPORTS[$this->key()]['columns'];
        $rows = $this->rows();
        $range = $this->range();
        $name = sprintf('report-%s-%s-%s.csv', $this->key(), $range->start->format('Ymd'), $range->end->format('Ymd'));

        return response()->streamDownload(function () use ($columns, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads Bangla as UTF-8
            fputcsv($out, array_map(fn ($c) => $c[0], $columns));
            foreach ($rows as $row) {
                // Money as plain taka so spreadsheets can sum it.
                fputcsv($out, array_map(fn (string $key) => $columns[$key][1] === 'money' && $row[$key] !== null ? $row[$key] / 100 : $row[$key], array_keys($columns)));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
