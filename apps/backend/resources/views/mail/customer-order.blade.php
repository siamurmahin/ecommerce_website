@php($money = fn (?int $poisha) => \App\Support\Money::format((int) $poisha))
<!doctype html>
<html lang="{{ $lang }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $copy['subject'] }}</title></head>
<body style="margin:0;background:#F8F8F6;font-family:Manrope,'Noto Sans Bengali',Arial,sans-serif;color:#171717">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FFFFFF;border-radius:16px;padding:32px">
        <tr><td style="font-size:18px;font-weight:600;color:#0F4C3A;padding-bottom:20px">{{ $shop }}</td></tr>
        <tr><td style="font-size:22px;font-weight:600;padding-bottom:8px">{{ $copy['heading'] }}</td></tr>
        <tr><td style="font-size:15px;line-height:1.7;color:#171717;padding-bottom:16px">{{ $copy['body'] }}</td></tr>
        <tr><td style="font-size:14px;color:#6B6B68;padding-bottom:16px">{{ $label['order'] }} <strong style="color:#171717">{{ $order->number }}</strong></td></tr>

        @if ($tracking)
          <tr><td style="background:#F8F8F6;border-radius:12px;padding:14px 16px;font-size:14px;line-height:1.7;margin-bottom:16px">
            @if ($courier){{ $label['courier'] }}: <strong>{{ $courier }}</strong><br>@endif
            {{ $label['tracking'] }}: <strong>{{ $tracking }}</strong>
          </td></tr>
          <tr><td style="height:16px"></td></tr>
        @endif

        <tr><td style="border-top:1px solid #E6E6E2;padding:12px 0">
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px">
            @foreach ($order->items as $item)
              <tr>
                <td style="padding:6px 0">{{ $item->name }}@if ($item->variant_label) ({{ $item->variant_label }})@endif × {{ $item->quantity }}</td>
                <td align="right" style="padding:6px 0;white-space:nowrap">{{ $money($item->line_total) }}</td>
              </tr>
            @endforeach
          </table>
        </td></tr>

        <tr><td style="border-top:1px solid #E6E6E2;padding:12px 0">
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;color:#6B6B68">
            <tr><td style="padding:3px 0">{{ $label['subtotal'] }}</td><td align="right">{{ $money($order->subtotal) }}</td></tr>
            <tr><td style="padding:3px 0">{{ $label['delivery'] }}</td><td align="right">{{ $money($order->delivery_charge) }}</td></tr>
            @if ($order->discount)<tr><td style="padding:3px 0">{{ $label['discount'] }}</td><td align="right">− {{ $money($order->discount) }}</td></tr>@endif
            @if ($order->gift_wrap_charge)<tr><td style="padding:3px 0">{{ $label['gift_wrap'] }}</td><td align="right">{{ $money($order->gift_wrap_charge) }}</td></tr>@endif
            <tr><td style="padding:6px 0;color:#171717;font-weight:600">{{ $label['total'] }}</td><td align="right" style="color:#171717;font-weight:600">{{ $money($order->total) }}</td></tr>
            @if ($payment)<tr><td style="padding:3px 0">{{ $label['payment'] }}</td><td align="right">{{ $payment }}</td></tr>@endif
          </table>
        </td></tr>

        <tr><td style="border-top:1px solid #E6E6E2;padding:12px 0 20px;font-size:14px;line-height:1.7">
          <span style="color:#6B6B68">{{ $label['address'] }}</span><br>
          {{ $order->customer_name }} · {{ $order->customer_phone }}<br>
          {{ $address }}
        </td></tr>

        <tr><td>
          <a href="{{ $url }}" style="display:inline-block;background:#0F4C3A;color:#FFFFFF;text-decoration:none;font-weight:600;padding:12px 24px;border-radius:12px">{{ $label['button'] }}</a>
        </td></tr>

        @if ($hotline)
          <tr><td style="padding-top:24px;font-size:13px;color:#6B6B68">{{ $label['help'] }} <a href="tel:{{ preg_replace('/[^0-9+]/', '', $hotline) }}" style="color:#0F4C3A">{{ $hotline }}</a></td></tr>
        @endif
      </table>
    </td></tr>
  </table>
</body>
</html>
