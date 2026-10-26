@php($money = fn (int $p) => \App\Support\Money::format($p))
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ইনভয়েস {{ $orders->pluck('number')->implode(', ') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600&family=Manrope:wght@400;600;700&family=Noto+Sans+Bengali:wght@400;600;700&family=Noto+Serif+Bengali:wght@600&display=swap" rel="stylesheet">
    <style>
        @page { size: A5; margin: 10mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #F8F8F6; color: #171717; font: 13px/1.5 "Manrope", "Noto Sans Bengali", sans-serif; }
        .toolbar { position: sticky; top: 0; display: flex; justify-content: flex-end; gap: 8px; padding: 12px 16px; background: #fff; border-bottom: 1px solid #E6E6E2; }
        .toolbar button { font: inherit; font-weight: 600; padding: 8px 16px; border-radius: 10px; border: 0; background: #0F4C3A; color: #fff; cursor: pointer; }
        .invoice { max-width: 148mm; margin: 16px auto; padding: 10mm; background: #fff; border-radius: 14px; page-break-after: always; }
        .invoice:last-child { page-break-after: auto; }
        header { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 12px; border-bottom: 1px solid #E6E6E2; }
        .brand { font: 600 22px/1.1 "Fraunces", "Noto Serif Bengali", serif; color: #0F4C3A; }
        .muted { color: #6B6B68; }
        .meta { text-align: right; }
        .meta strong { font-size: 16px; }
        .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; padding: 12px 0; }
        h4 { margin: 0 0 4px; font-size: 11px; letter-spacing: .04em; text-transform: uppercase; color: #6B6B68; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 11px; color: #6B6B68; font-weight: 600; padding: 6px 0; border-bottom: 1px solid #E6E6E2; }
        td { padding: 8px 0; border-bottom: 1px solid #F0F0EC; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .totals { margin-left: auto; width: 60%; margin-top: 8px; }
        .totals td { border: 0; padding: 3px 0; }
        .grand td { border-top: 1px solid #171717; padding-top: 8px; font-size: 15px; font-weight: 700; }
        .cod { margin-top: 12px; padding: 10px 12px; border-radius: 10px; background: #F8F8F6; display: flex; justify-content: space-between; font-weight: 600; }
        footer { margin-top: 16px; font-size: 11px; text-align: center; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .invoice { margin: 0; padding: 0; max-width: none; border-radius: 0; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <span class="muted" style="margin-right:auto;align-self:center">{{ $orders->count() }}টি ইনভয়েস</span>
    <button type="button" onclick="window.print()">প্রিন্ট করুন</button>
</div>

@foreach ($orders as $order)
    <article class="invoice">
        <header>
            <div>
                <div class="brand">{{ $shop['name'] }}</div>
                <div class="muted">{{ $shop['phone'] }} · {{ preg_replace('#^https?://#', '', $shop['website']) }}</div>
            </div>
            <div class="meta">
                <div class="muted">ইনভয়েস</div>
                <strong>{{ $order->number }}</strong>
                <div class="muted">{{ $order->created_at->format('d M Y') }}</div>
            </div>
        </header>

        <section class="parties">
            <div>
                <h4>প্রাপক</h4>
                <strong>{{ $order->customer_name }}</strong><br>
                {{ $order->customer_phone }}<br>
                {{ $order->address }}@if ($order->thana_name), {{ $order->thana_name }}@endif@if ($order->district_name), {{ $order->district_name }}@endif
            </div>
            <div>
                <h4>ডেলিভারি</h4>
                {{ $order->delivery_zone_name }}<br>
                <span class="muted">পেমেন্ট:</span> {{ $order->payment_method->getLabel() }}
                @if ($order->customer_note)
                    <br><span class="muted">নোট:</span> {{ $order->customer_note }}
                @endif
            </div>
        </section>

        <table>
            <thead><tr><th>পণ্য</th><th class="num">দাম</th><th class="num">পরিমাণ</th><th class="num">মোট</th></tr></thead>
            <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->name }}@if ($item->variant_label)<br><span class="muted">{{ $item->variant_label }}</span>@endif</td>
                    <td class="num">{{ $money($item->unit_price) }}</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ $money($item->line_total) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <table class="totals">
            <tr><td class="muted">সাবটোটাল</td><td class="num">{{ $money($order->subtotal) }}</td></tr>
            <tr><td class="muted">ডেলিভারি চার্জ</td><td class="num">{{ $order->delivery_charge ? $money($order->delivery_charge) : 'ফ্রি' }}</td></tr>
            @if ($order->gift_wrap_charge)
                <tr><td class="muted">গিফট র‍্যাপ</td><td class="num">{{ $money($order->gift_wrap_charge) }}</td></tr>
            @endif
            @if ($order->discount)
                <tr><td class="muted">ডিসকাউন্ট@if ($order->coupon_code) ({{ $order->coupon_code }})@endif</td><td class="num">−{{ $money($order->discount) }}</td></tr>
            @endif
            <tr class="grand"><td>মোট</td><td class="num">{{ $money($order->total) }}</td></tr>
        </table>

        @if ($order->dueOnDelivery() > 0)
            <div class="cod"><span>ডেলিভারির সময় পরিশোধ করুন</span><span>{{ $money($order->dueOnDelivery()) }}</span></div>
        @else
            <div class="cod"><span>পরিশোধিত</span><span>{{ $money($order->paid_amount) }}</span></div>
        @endif

        <footer class="muted">{{ $shop['name'] }} থেকে কেনাকাটার জন্য ধন্যবাদ।</footer>
    </article>
@endforeach
</body>
</html>
