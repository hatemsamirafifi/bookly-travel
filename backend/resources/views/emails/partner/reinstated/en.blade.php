<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Your Bookly Partner Account Has Been Reinstated</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #16a34a;">Your Partner Account Has Been Reinstated</h2>
    <p>Dear {{ $businessName }},</p>
    <p>We are pleased to inform you that your Bookly partner account has been reinstated by our administration team.</p>
    <div style="background: #f0fdf4; border-left: 4px solid #16a34a; padding: 15px; margin: 20px 0;">
        <strong style="color: #166534;">Important Notice Regarding Tours:</strong>
        <p style="margin: 8px 0 0 0; color: #14532d;">Your tours remain in draft/unpublished status for safety. Please review your tour details and resubmit them for review or publish them through your partner dashboard.</p>
    </div>
    <p style="margin: 25px 0;">
        <a href="{{ $dashboardUrl }}" style="background-color: #2563eb; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: bold;">Go to Partner Dashboard</a>
    </p>
    <p>If you have any questions, feel free to contact us at <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.</p>
    <p>Best regards,<br>The Bookly Team</p>
</body>
</html>
