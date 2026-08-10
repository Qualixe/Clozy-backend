<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $appName }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; padding:32px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px; background-color:#ffffff; border-radius:12px; overflow:hidden;">
          <tr>
            <td align="center" style="padding:32px 32px 16px;">
              @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $appName }}" style="max-height:40px; max-width:200px; display:block;">
              @else
                <span style="font-size:18px; font-weight:600; color:#111827;">{{ $appName }}</span>
              @endif
            </td>
          </tr>
          <tr>
            <td style="padding:16px 32px 8px;">
              <h1 style="margin:0 0 16px; font-size:20px; color:#111827;">Verify your email address</h1>
              <p style="margin:0 0 24px; font-size:14px; line-height:1.6; color:#4b5563;">
                Please click the button below to verify your email address.
              </p>
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:0 32px 24px;">
              <a href="{{ $url }}" style="display:inline-block; padding:12px 24px; background-color:{{ $accentColor }}; color:#ffffff; text-decoration:none; font-size:14px; font-weight:600; border-radius:8px;">
                Verify Email Address
              </a>
            </td>
          </tr>
          <tr>
            <td style="padding:0 32px 32px;">
              <p style="margin:0; font-size:12px; line-height:1.6; color:#9ca3af;">
                If you did not create an account, no further action is required. If the button above doesn't work, copy and paste this link into your browser:
                <br>
                <a href="{{ $url }}" style="color:{{ $accentColor }}; word-break:break-all;">{{ $url }}</a>
              </p>
            </td>
          </tr>
        </table>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;">
          <tr>
            <td align="center" style="padding:24px 16px;">
              <p style="margin:0; font-size:12px; color:#9ca3af;">
                {{ $footerText ?: '© ' . date('Y') . ' ' . $appName . '. All rights reserved.' }}
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
