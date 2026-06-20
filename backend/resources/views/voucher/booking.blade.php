<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="UTF-8">
    <title>Voucher — {{ $booking->reference }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #0A2540; font-size: 13px; }
        .voucher { max-width: 580px; margin: 20px auto; border: 2px solid #0A2540; border-radius: 12px; overflow: hidden; }
        .header { background: #0A2540; padding: 20px 28px; display: flex; justify-content: space-between; align-items: center; }
        .header .brand { color: #FFB800; font-size: 22px; font-weight: 700; letter-spacing: -0.5px; }
        .header .doc-type { color: #ffffff; font-size: 11px; text-transform: uppercase; letter-spacing: 2px; }
        .reference-bar { background: #FFB800; padding: 14px 28px; text-align: center; }
        .reference-bar .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; color: #0A2540; opacity: 0.7; }
        .reference-bar .ref { font-size: 26px; font-weight: 700; color: #0A2540; letter-spacing: 3px; margin-top: 2px; }
        .body { padding: 24px 28px; }
        .tour-title { font-size: 18px; font-weight: 700; color: #0A2540; margin-bottom: 16px; }
        .detail-grid { width: 100%; border-collapse: collapse; }
        .detail-grid td { padding: 8px 0; font-size: 13px; border-bottom: 1px solid #e6e9ec; vertical-align: top; }
        .detail-grid td.label { color: #8792a2; width: 35%; }
        .detail-grid td.value { color: #0A2540; font-weight: 500; }
        .qr-section { text-align: center; padding: 20px 28px; border-top: 1px dashed #d1d5db; }
        .qr-section .qr-note { font-size: 11px; color: #8792a2; margin-top: 8px; }
        .footer { background: #f8f9fa; padding: 14px 28px; text-align: center; font-size: 10px; color: #8792a2; }
        .footer strong { color: #0A2540; }
    </style>
</head>
<body>
    <div class="voucher">
        <div class="header">
            <span class="brand">Bookly</span>
            <span class="doc-type">Tour Voucher</span>
        </div>

        <div class="reference-bar">
            <div class="label">Booking Reference</div>
            <div class="ref">{{ $booking->reference }}</div>
        </div>

        <div class="body">
            <div class="tour-title">{{ $tourTitle }}</div>

            <table class="detail-grid">
                <tr>
                    <td class="label">Date</td>
                    <td class="value">{{ $tourDate }}</td>
                </tr>
                <tr>
                    <td class="label">Participants</td>
                    <td class="value">{{ $booking->participant_count }}</td>
                </tr>
                <tr>
                    <td class="label">Total Paid</td>
                    <td class="value">{{ $formattedTotal }}</td>
                </tr>
                <tr>
                    <td class="label">Meeting Point</td>
                    <td class="value">{{ $meetingPoint }}</td>
                </tr>
                <tr>
                    <td class="label">Traveler</td>
                    <td class="value">{{ $booking->traveler?->full_name ?? $booking->traveler?->name ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Status</td>
                    <td class="value" style="color: #16a34a; font-weight: 700;">✓ Confirmed</td>
                </tr>
            </table>
        </div>

        <div class="qr-section">
            {{-- QR code placeholder — requires a QR package like simplesoftwareio/simple-qrcode --}}
            <div style="display: inline-block; width: 120px; height: 120px; border: 2px solid #0A2540; border-radius: 8px; line-height: 120px; font-size: 10px; color: #8792a2;">
                QR: {{ $booking->reference }}
            </div>
            <div class="qr-note">Present this voucher (printed or digital) at the meeting point</div>
        </div>

        <div class="footer">
            <p>Generated on {{ now()->format('F j, Y') }} • <strong>bookly.com</strong></p>
            <p>This voucher is valid only for the booking referenced above.</p>
        </div>
    </div>
</body>
</html>
