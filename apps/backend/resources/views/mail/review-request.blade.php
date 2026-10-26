<!doctype html>
<html lang="bn">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $shop }}</title></head>
<body style="margin:0;background:#F8F8F6;font-family:'Noto Sans Bengali',Manrope,Arial,sans-serif;color:#171717">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#FFFFFF;border-radius:16px;padding:32px">
        <tr><td style="font-size:20px;font-weight:600;color:#0F4C3A;padding-bottom:16px">{{ $shop }}</td></tr>
        <tr><td style="font-size:15px;line-height:1.7;white-space:pre-line">{{ $body }}</td></tr>
        @if ($items->isNotEmpty())
          <tr><td style="padding:16px 0;font-size:14px;color:#6B6B68">
            @foreach ($items as $item)
              <div style="padding:4px 0">• {{ $item->name }}@if ($item->variant_label) ({{ $item->variant_label }})@endif</div>
            @endforeach
          </td></tr>
        @endif
        <tr><td style="padding-top:8px">
          <a href="{{ $url }}" style="display:inline-block;background:#0F4C3A;color:#FFFFFF;text-decoration:none;font-weight:600;padding:12px 24px;border-radius:12px">রিভিউ লিখুন</a>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
