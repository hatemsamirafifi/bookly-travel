<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('emails.account_locked_out.title', [], $locale) }}</title>
</head>
<body style="font-family: system-ui, -apple-system, sans-serif; background: #f4f4f5; padding: 24px; margin: 0;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden;">
        <tr>
            <td style="padding: 32px 32px 16px; text-align: center;">
                <h1 style="margin: 0; font-size: 20px; color: #18181b;">{{ $platformName }}</h1>
            </td>
        </tr>
        <tr>
            <td style="padding: 16px 32px 24px;">
                <h2 style="margin: 0 0 12px; font-size: 18px; color: #dc2626;">{{ __('emails.account_locked_out.title', [], $locale) }}</h2>
                <p style="margin: 0 0 12px; color: #3f3f46; line-height: 1.6;">
                    {{ __('emails.account_locked_out.greeting', ['name' => $userName], $locale) }}
                </p>
                <p style="margin: 0 0 12px; color: #3f3f46; line-height: 1.6;">
                    {{ __('emails.account_locked_out.body', ['platform' => $platformName], $locale) }}
                </p>
                <p style="margin: 0; padding: 16px; background: #fef2f2; border-left: 4px solid #dc2626; border-radius: 8px; color: #991b1b; line-height: 1.6;">
                    {{ __('emails.account_locked_out.security_note', [], $locale) }}
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding: 16px 32px; background: #fafafa; text-align: center;">
                <p style="margin: 0; font-size: 12px; color: #a1a1aa;">
                    &copy; {{ date('Y') }} {{ $platformName }}. {{ __('emails.account_locked_out.rights', [], $locale) }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
