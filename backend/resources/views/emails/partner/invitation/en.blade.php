<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Partner Invitation</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px;">
        <h2 style="color: #2b6cb0;">Welcome to Bookly, {{ $companyName }}!</h2>
        <p>You have been invited by {{ $adminName }} to join Bookly as an approved partner.</p>
        <p>To accept this invitation and complete your partner account setup, please click the button below:</p>
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $inviteUrl }}" style="background-color: #3182ce; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block;">Complete Partner Setup</a>
        </div>
        <p style="font-size: 0.9em; color: #666;">This invitation is valid until {{ $expiresAt }}. If you were not expecting this invitation, you can safely ignore this email.</p>
    </div>
</body>
</html>
