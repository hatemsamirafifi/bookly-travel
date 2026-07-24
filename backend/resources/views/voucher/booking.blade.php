@php
// Spec 014 (FR-015, R5): in-view locale label map with EN fallback. EN is the
// source; es/it fall back to EN for any missing entry. Keeping the map local to
// the one file this spec already edits avoids standing up a full Blade lang
// system for ~8 strings.
$labels = [
    'en' => [
        'docType' => 'Tour Voucher',
        'bookingReference' => 'Booking Reference',
        'date' => 'Date',
        'participants' => 'Participants',
        'totalPaid' => 'Total Paid',
        'meetingPoint' => 'Meeting Point',
        'traveler' => 'Traveler',
        'status' => 'Status',
        'qrNote' => 'Present this voucher (printed or digital) at the meeting point.',
        'footerValid' => 'This voucher is valid only for the booking referenced above.',
        'generatedOn' => 'Generated on',
    ],
    'es' => [
        'docType' => 'Comprobante de tour',
        'bookingReference' => 'Referencia de reserva',
        'date' => 'Fecha',
        'participants' => 'Participantes',
        'totalPaid' => 'Total pagado',
        'meetingPoint' => 'Punto de encuentro',
        'traveler' => 'Viajero',
        'status' => 'Estado',
        'qrNote' => 'Presenta este comprobante (impreso o digital) en el punto de encuentro.',
        'footerValid' => 'Este comprobante solo es válido para la reserva referenciada arriba.',
        'generatedOn' => 'Generado el',
    ],
    'it' => [
        'docType' => 'Voucher del tour',
        'bookingReference' => 'Riferimento prenotazione',
        'date' => 'Data',
        'participants' => 'Partecipanti',
        'totalPaid' => 'Totale pagato',
        'meetingPoint' => 'Punto di incontro',
        'traveler' => 'Viaggiatore',
        'status' => 'Stato',
        'qrNote' => 'Presenta questo voucher (stampato o digitale) al punto di incontro.',
        'footerValid' => 'Questo voucher è valido solo per la prenotazione sopra indicata.',
        'generatedOn' => 'Generato il',
    ],
];

$statusWords = [
    'en' => [
        'confirmed' => 'Confirmed',
        'completed' => 'Completed',
        'cancellation_requested' => 'Cancellation requested',
        'cancelled' => 'Cancelled',
        'no_show' => 'No-show',
        'pending_payment' => 'Pending',
        'expired' => 'Expired',
    ],
    'es' => [
        'confirmed' => 'Confirmado',
        'completed' => 'Completado',
        'cancellation_requested' => 'Cancelación solicitada',
        'cancelled' => 'Cancelado',
        'no_show' => 'No presentado',
        'pending_payment' => 'Pendiente',
        'expired' => 'Expirado',
    ],
    'it' => [
        'confirmed' => 'Confermato',
        'completed' => 'Completato',
        'cancellation_requested' => 'Cancellazione richiesta',
        'cancelled' => 'Cancellato',
        'no_show' => 'Non presentato',
        'pending_payment' => 'In attesa',
        'expired' => 'Scaduto',
    ],
];

$activeLocale = $locale ?? 'en';
if (! isset($labels[$activeLocale])) {
    $activeLocale = 'en';
}
$L = fn (string $key): string => $labels[$activeLocale][$key] ?? $labels['en'][$key];
$statusWord = $statusWords[$activeLocale][$booking->status] ?? $statusWords['en'][$booking->status] ?? ucfirst($booking->status);
$baseUrl = rtrim((string) config('services.voucher.public_base_url', 'https://bookly.travel'), '/');
$host = parse_url($baseUrl, PHP_URL_HOST) ?: 'bookly.travel';
@endphp
<!DOCTYPE html>
<html lang="{{ $activeLocale }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $L('docType') }} — {{ $booking->reference }}</title>
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
        .qr-section img { width: 120px; height: 120px; }
        .qr-section .qr-note { font-size: 11px; color: #8792a2; margin-top: 8px; }
        .footer { background: #f8f9fa; padding: 14px 28px; text-align: center; font-size: 10px; color: #8792a2; }
        .footer strong { color: #0A2540; }
    </style>
</head>
<body>
    <div class="voucher">
        <div class="header">
            <span class="brand">Bookly</span>
            <span class="doc-type">{{ $L('docType') }}</span>
        </div>

        <div class="reference-bar">
            <div class="label">{{ $L('bookingReference') }}</div>
            <div class="ref">{{ $booking->reference }}</div>
        </div>

        <div class="body">
            <div class="tour-title">{{ $tourTitle }}</div>

            <table class="detail-grid">
                <tr>
                    <td class="label">{{ $L('date') }}</td>
                    <td class="value">{{ $tourDate }}</td>
                </tr>
                <tr>
                    <td class="label">{{ $L('participants') }}</td>
                    <td class="value">{{ $booking->participant_count }}</td>
                </tr>
                <tr>
                    <td class="label">{{ $L('totalPaid') }}</td>
                    <td class="value">{{ $formattedTotal }}</td>
                </tr>
                <tr>
                    <td class="label">{{ $L('meetingPoint') }}</td>
                    <td class="value">{{ $meetingPoint }}</td>
                </tr>
                <tr>
                    <td class="label">{{ $L('traveler') }}</td>
                    <td class="value">{{ $booking->traveler?->full_name ?? $booking->traveler?->name ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label">{{ $L('status') }}</td>
                    <td class="value" style="color: #16a34a; font-weight: 700;">✓ {{ $statusWord }}</td>
                </tr>
            </table>
        </div>

        <div class="qr-section">
            {{-- Spec 014: real QR encoding the public verification URL (FR-002, SC-009).
                Rendered as a PNG data URI (GD) because dompdf silently drops inline SVG. --}}
            <img src="{{ $qrDataUri }}" alt="QR code — {{ $booking->reference }}" width="120" height="120">
            <div class="qr-note">{{ $L('qrNote') }}</div>
        </div>

        <div class="footer">
            <p>{{ $L('generatedOn') }} {{ now()->format('F j, Y') }} • <strong>{{ $host }}</strong></p>
            <p>{{ $L('footerValid') }}</p>
        </div>
    </div>
</body>
</html>