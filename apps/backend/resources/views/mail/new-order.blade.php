@php($money = fn (?int $poisha) => \App\Support\Money::format((int) $poisha))
<!doctype html>
<html lang="bn">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $shop }}</title></head>
<body style="margin:0;background:#F8F8F6;font-family:'Noto Sans Bengali',Manrope,Arial,sans-serif;color:#171717">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FFFFFF;border-radius:16px;padding:32px">
        <tr><td style="font-size:14px;color:#6B6B68;padding-bottom:4px">{{ $shop }} · নতুন অর্ডার</td></tr>
        <tr><td style="font-size:22px;font-weight:600;color:#0F4C3A;padding-bottom:20px">{{ $order->number }} — {{ $money($order->total) }}</td></tr>

        <tr><td style="font-size:15px;line-height:1.7;padding-bottom:16px">
          <strong>{{ $order->customer_name }}</strong><br>
          <a href="tel:{{ $order->customer_phone }}" style="color:#0F4C3A">{{ $order->customer_phone }}</a><br>
          {{ $address }}
          @if ($order->customer_note)<br><span style="color:#6B6B68">নোট: {{ $order->customer_note }}</span>@endif
        </td></tr>

        <tr><td style="border-top:1px solid #E6E6E2;padding:12px 0">
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px">
            @foreach ($order->items as $item)
              <tr>
                <td style="padding:6px 0">{{ $item->name }}@if ($item->variant_label) ({{ $item->variant_label }})@endif × {{ $item->quantity }}@if ($item->preorder) <span style="color:#B8975A">· প্রি-অর্ডার</span>@endif</td>
                <td align="right" style="padding:6px 0;white-space:nowrap">{{ $money($item->line_total) }}</td>
              </tr>
            @endforeach
          </table>
        </td></tr>

        <tr><td style="border-top:1px solid #E6E6E2;padding:12px 0">
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;color:#6B6B68">
            <tr><td style="padding:3px 0">পণ্যের দাম</td><td align="right">{{ $money($order->subtotal) }}</td></tr>
            <tr><td style="padding:3px 0">ডেলিভারি</td><td align="right">{{ $money($order->delivery_charge) }}</td></tr>
            @if ($order->discount)<tr><td style="padding:3px 0">ছাড়{{ $order->coupon_code ? " ({$order->coupon_code})" : '' }}</td><td align="right">− {{ $money($order->discount) }}</td></tr>@endif
            @if ($order->gift_wrap_charge)<tr><td style="padding:3px 0">গিফট র‍্যাপ</td><td align="right">{{ $money($order->gift_wrap_charge) }}</td></tr>@endif
            <tr><td style="padding:6px 0;color:#171717;font-weight:600">মোট</td><td align="right" style="color:#171717;font-weight:600">{{ $money($order->total) }}</td></tr>
            <tr><td style="padding:3px 0">পেমেন্ট</td><td align="right">{{ $order->payment_method?->getLabel() }}@if ($order->payment_trx_id) · TrxID {{ $order->payment_trx_id }}@endif</td></tr>
          </table>
        </td></tr>

        @if ($order->gift_wrap)
          <tr><td style="padding-bottom:12px;font-size:14px;color:#B8975A">গিফট র‍্যাপ করতে হবে@if ($order->gift_note) — নোট: {{ $order->gift_note }}@endif</td></tr>
        @endif

        <tr><td style="padding-top:8px">
          <a href="{{ $url }}" style="display:inline-block;background:#0F4C3A;color:#FFFFFF;text-decoration:none;font-weight:600;padding:12px 24px;border-radius:12px">অ্যাডমিনে অর্ডার দেখুন</a>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
