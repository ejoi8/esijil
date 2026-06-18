<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <title>Sijil Anda Telah Tersedia</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.6;">
    <p>Assalamualaikum dan salam sejahtera {{ $participant->full_name }},</p>

    <p>
        Sijil anda untuk program berikut telah tersedia untuk dimuat turun.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 20px 0; width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 8px 0; width: 150px; color: #4b5563;">Program</td>
            <td style="padding: 8px 0; font-weight: 600;">{{ $event->title }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #4b5563;">Tarikh</td>
            <td style="padding: 8px 0;">{{ $event->starts_at?->format('d/m/Y') ?? '-' }}</td>
        </tr>
        @if ($registration->cert_serial_number)
            <tr>
                <td style="padding: 8px 0; color: #4b5563;">No. Rujukan</td>
                <td style="padding: 8px 0;">{{ $registration->cert_serial_number }}</td>
            </tr>
        @endif
    </table>

    <p>
        Muat turun sijil anda di
        <a href="{{ $lookupUrl }}">{{ $lookupUrl }}</a>
        dengan memasukkan No. Kad Pengenalan anda.
    </p>

    <p>Terima kasih.</p>
</body>
</html>
