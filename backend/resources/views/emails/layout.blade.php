<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Bookly')</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 0; background-color: #f4f5f7; color: #0A2540; }
        .wrapper { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; margin-top: 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .header { background: #0A2540; padding: 24px 32px; text-align: center; }
        .header h1 { color: #FFB800; font-size: 24px; margin: 0; font-weight: 700; letter-spacing: -0.5px; }
        .content { padding: 32px; }
        .content h2 { font-size: 20px; margin: 0 0 16px; color: #0A2540; }
        .content p { font-size: 15px; line-height: 1.6; color: #425466; margin: 0 0 16px; }
        .detail-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        .detail-table td { padding: 10px 0; font-size: 14px; border-bottom: 1px solid #e6e9ec; }
        .detail-table td:first-child { color: #8792a2; width: 40%; }
        .detail-table td:last-child { color: #0A2540; font-weight: 500; text-align: right; }
        .highlight-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 16px 20px; margin: 20px 0; text-align: center; }
        .highlight-box .ref { font-size: 28px; font-weight: 700; color: #0A2540; letter-spacing: 2px; }
        .cta-button { display: inline-block; background: #FFB800; color: #0A2540; font-weight: 600; padding: 12px 28px; border-radius: 6px; text-decoration: none; font-size: 15px; margin-top: 16px; }
        .footer { background: #f8f9fa; padding: 20px 32px; text-align: center; font-size: 12px; color: #8792a2; }
        .footer a { color: #0A2540; text-decoration: none; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>Bookly</h1>
        </div>
        <div class="content">
            @yield('content')
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Bookly Tours Marketplace. All rights reserved.</p>
            <p>Questions? <a href="mailto:support@bookly.com">Contact support</a></p>
        </div>
    </div>
</body>
</html>
