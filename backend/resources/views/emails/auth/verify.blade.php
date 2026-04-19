<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .header {
            background-color: #1a1a1a;
            color: #ffffff;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .content {
            padding: 40px 30px;
        }
        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
        }
        .message {
            margin-bottom: 30px;
            color: #555;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .button {
            display: inline-block;
            background-color: #1a1a1a;
            color: #ffffff;
            padding: 14px 32px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
            font-size: 16px;
        }
        .button:hover {
            background-color: #333;
        }
        .footer {
            background-color: #f9f9f9;
            padding: 20px 30px;
            text-align: center;
            font-size: 12px;
            color: #777;
            border-top: 1px solid #e5e5e5;
        }
        .link {
            color: #1a1a1a;
            text-decoration: none;
        }
        .link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $platformName }}</h1>
        </div>
        <div class="content">
            <p class="greeting">Hello {{ $userName }},</p>
            <p class="message">
                Thank you for signing up for {{ $platformName }}. Please verify your email address to complete your registration.
            </p>
            <div class="button-container">
                <a href="{{ $verificationUrl }}" class="button">Verify Email Address</a>
            </div>
            <p class="message">
                If you didn't create an account, you can safely ignore this email.
            </p>
        </div>
        <div class="footer">
            <p>This link will expire in 60 minutes.</p>
            <p>&copy; {{ date('Y') }} {{ $platformName }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>