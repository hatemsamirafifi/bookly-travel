<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Your Bookly Partner Account Has Been Suspended</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #dc2626;">Your Partner Account Has Been Suspended</h2>
    <p>Dear {{ $businessName }},</p>
    <p>We are writing to inform you that your Bookly partner account has been suspended by our administration team.</p>
    <div style="background: #fef2f2; border-left: 4px solid #dc2626; padding: 15px; margin: 20px 0;">
        <strong style="color: #991b1b;">Reason for Suspension:</strong>
        <p style="margin: 8px 0 0 0; color: #7f1d1d;">{{ $reason }}</p>
    </div>
    <p>As a result, your published tours have been unpublished and are currently hidden from search and booking. If you have active confirmed bookings, our team or guests may contact you regarding them.</p>
    <p>If you believe this decision was made in error or wish to appeal, please reach out to our support team at <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.</p>
    <p>Best regards,<br>The Bookly Team</p>
</body>
</html>
