@php($money = fn (int $p) => \App\Support\Money::format($p))
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>প্যাকিং স্লিপ {{ $orders->pluck('number')->implode(', ') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600&family=Manrope:wght@400;600;700&family=Noto+Sans+Bengali:wght@400;600;700&family=Noto+Serif+Bengali:wght@600&display=swap" rel="stylesheet">
    <style>
        @page { size: A5; margin: 10mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #F8F8F6; color: #171717; font: 13px/1.5 "Manrope", "Noto Sans Bengali", sans-serif; }
        .toolbar { position: sticky; top: 0; display: flex; justify-content: flex-end; gap: 8px; padding: 12px 16px; background: #fff; border-bottom: 1px solid #E6E6E2; }
        .toolbar button { font: inherit; font-weight: 600; padding: 8px 16px; border-radius: 10px; border: 0; background: #0F4C3A; color: #fff; cursor: pointer; }
        .sheet { max-width: 148mm; margin: 16px auto; padding: 10mm; background: #fff; border-radius: 14px; page-break-after: always; }
        .sheet:last-child { page-break-after: auto; }
        header { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; padding-bottom: 10px; border-bottom: 1px solid #E6E6E2; }
        .brand { font: 600 18px/1.1 "Fraunces", "Noto Serif Bengali", serif; color: #0F4C3A; }
        .number { font-size: 22px; font-weight: 700; letter-spacing: .03em; }
        .muted { color: #6B6B68; }
        h2 { margin: 0 0 8px; font: 600 18px/1.2 "Fraunces", "Noto Serif Bengali", serif; }
        h4 { margin: 0 0 4px; font-size: 11px; letter-spacing: .04em; text-transform: uppercase; color: #6B6B68; }
        .to { padding: 10px 0; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 11px; color: #6B6B68; font-weight: 600; padding: 6px 0; border-bottom: 1px solid #E6E6E2; }
        td { padding: 9px 0; border-bottom: 1px solid #F0F0EC; vertical-align: top; }
        .qty { text-align: center; width: 56px; font-size: 18px; font-weight: 700; }
        .check { width: 28px; }
        .box { display: inline-block; width: 16px; height: 16px; border: 1.5px solid #171717; border-radius: 4px; }
        .note { margin-top: 10px; padding: 10px 12px; border-radius: 10px; background: #F8F8F6; }
        .cod { margin-top: 10px; display: flex; justify-content: space-between; font-weight: 700; font-size: 15px; }
        .tag { display: inline-block; padding: 1px 8px; border-radius: 999px; background: #B8975A22; color: #7a6235; font-size: 11px; font-weight: 600; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; padding: 0; max-width: none; border-radius: 0; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <span class="muted" style="margin-right:auto;align-self:center">{{ $orders->count() }}টি অর্ডারের প্যাকিং স্লিপ</span>
    <button type="button" onclick="window.print()">প্রিন্ট করুন</button>
</div>

@if ($orders->count() > 1)
    <article class="sheet">
        <h2>পিক লিস্ট — {{ $orders->count() }}টি অর্ডার</h2>
        <p class="muted" style="margin:0 0 8px">স্টোর থেকে একবারে যা তুলতে হবে।</p>
        <table>
            <thead><tr><th class="check"></th><th>পণ্য</th><th>SKU</th><th class="qty">পিস</th></tr></thead>
            <tbody>
            @foreach ($picks as $pick)
                <tr>
                    <td class="check"><span class="box"></span></td>
                    <td>{{ $pick['name'] }}@if ($pick['variant'])<br><span class="muted">{{ $pick['variant'] }}</span>@endif</td>
                    <td class="muted">{{ $pick['sku'] ?? '—' }}</td>
                    <td class="qty">{{ $pick['quantity'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </article>
@endif

@foreach ($orders as $order)
    <article class="sheet">
        <header>
            <div>
                <div class="brand">{{ $shop['name'] }}</div>
                <div class="muted">প্যাকিং স্লিপ · {{ $order->created_at->format('d M Y') }}</div>
            </div>
            <div style="text-align:right">
                <div class="number">{{ $order->number }}</div>
                @if ($order->latestShipment)
                    <div class="muted">{{ $order->latestShipment->courierLabel() }} · {{ $order->latestShipment->consignment_id }}</div>
                @endif
            </div>
        </header>

        <section class="to">
            <h4>প্রাপক</h4>
            <strong>{{ $order->customer_name }}</strong> · {{ $order->customer_phone }}<br>
            {{ $order->address }}@if ($order->thana_name), {{ $order->thana_name }}@endif@if ($order->district_name), {{ $order->district_name }}@endif
        </section>

        <table>
            <thead><tr><th class="check"></th><th>পণ্য</th><th>SKU</th><th class="qty">পিস</th></tr></thead>
            <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td class="check"><span class="box"></span></td>
                    <td>
                        {{ $item->name }}@if ($item->variant_label)<br><span class="muted">{{ $item->variant_label }}</span>@endif
                        @if ($item->preorder) <span class="tag">প্রি-অর্ডার</span>@endif
                    </td>
                    <td class="muted">{{ $item->sku ?? '—' }}</td>
                    <td class="qty">{{ $item->quantity }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        @if ($order->gift_wrap || $order->gift_note)
            <div class="note"><strong>{{ $order->gift_wrap ? '🎁 গিফট র‍্যাপ করুন' : '🎁 গিফট নোট' }}</strong>@if ($order->gift_note)<br>কার্ডে লিখুন: “{{ $order->gift_note }}”@endif</div>
        @endif
        @if ($order->customer_note)
            <div class="note"><span class="muted">কাস্টমারের নোট:</span> {{ $order->customer_note }}</div>
        @endif
        @if ($order->internal_note)
            <div class="note"><span class="muted">স্টাফ নোট:</span> {{ $order->internal_note }}</div>
        @endif

        <div class="cod">
            <span>{{ $order->dueOnDelivery() > 0 ? 'COD — কুরিয়ার নেবে' : 'পরিশোধিত — টাকা নেবে না' }}</span>
            <span>{{ $money($order->dueOnDelivery()) }}</span>
        </div>
    </article>
@endforeach
</body>
</html>
