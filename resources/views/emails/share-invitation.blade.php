<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
</head>
<body style="margin:0; padding:0; background:#f4f3ee; font-family: -apple-system, 'Segoe UI', Helvetica, Arial, sans-serif; color:#37352f;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f3ee; padding:32px 16px;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px; background:#ffffff; border-radius:12px; padding:32px;">
                <tr><td>
                    <div style="font-size:12px; letter-spacing:.06em; text-transform:uppercase; color:#9b9a97;">{{ $teamName }}</div>
                    <h1 style="margin:8px 0 16px; font-size:22px; line-height:1.3; color:#37352f;">{{ $title }}</h1>
                    <p style="margin:0 0 16px; font-size:15px; line-height:1.6;">
                        {{ $senderName ? $senderName . ' hat' : 'Wir haben' }} Ihnen einen persönlichen Zugang eingerichtet.
                        Dort sehen Sie jederzeit den aktuellen Stand – ohne Anmeldung.
                    </p>
                    @if ($note)
                        <p style="margin:0 0 20px; padding:12px 16px; background:#f7f6f3; border-radius:8px; font-size:14px; line-height:1.6; white-space:pre-line;">{{ $note }}</p>
                    @endif
                    <p style="margin:0 0 24px;">
                        <a href="{{ $url }}" style="display:inline-block; background:#37352f; color:#ffffff; padding:12px 20px; border-radius:8px; font-size:15px; font-weight:600; text-decoration:none;">Zum aktuellen Stand</a>
                    </p>
                    <p style="margin:0; font-size:12px; line-height:1.6; color:#9b9a97;">
                        Der Link ist persönlich – bitte nicht weitergeben.<br>
                        <a href="{{ $url }}" style="color:#787774; word-break:break-all;">{{ $url }}</a>
                    </p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
