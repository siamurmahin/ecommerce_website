<!doctype html>
<html lang="{{ $lang }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $shop }}</title></head>
<body style="margin:0;background:#F8F8F6;font-family:Manrope,'Noto Sans Bengali',Arial,sans-serif;color:#171717">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#FFFFFF;border-radius:16px;padding:32px">
        <tr><td style="font-size:20px;font-weight:600;color:#0F4C3A;padding-bottom:16px">{{ $shop }}</td></tr>
        <tr><td style="font-size:15px;line-height:1.7">{{ $body }}</td></tr>
        <tr><td align="center" style="padding:24px 0">
          <div style="display:inline-block;background:#F8F8F6;border:1px solid #E6E6E2;border-radius:14px;padding:14px 28px;font-size:30px;font-weight:700;letter-spacing:8px;font-family:'Courier New',monospace">{{ $code }}</div>
        </td></tr>
        <tr><td style="font-size:13px;line-height:1.6;color:#6B6B68">{{ $ignore }}</td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
