<?php

return [
    'verification' => [
        'subject' => 'Verify your email address',
        'title' => 'Verify Your Email',
        'greeting' => 'Hello :name,',
        'body' => 'Thank you for signing up for :platform. Please verify your email address to complete your registration.',
        'button' => 'Verify Email Address',
        'ignore' => "If you didn't create an account, you can safely ignore this email.",
        'expiration' => 'This link will expire in :minutes minutes.',
        'rights' => 'All rights reserved.',
    ],
    'account_locked_out' => [
        'subject' => 'Security Alert: Account Temporarily Locked',
        'title' => 'Account Temporarily Locked',
        'greeting' => 'Hello :name,',
        'body' => 'We detected too many failed sign-in attempts on your :platform account. For your security, your account has been temporarily locked. You can try signing in again after the lockout period expires.',
        'security_note' => 'If this wasn\'t you, we recommend changing your password once the lockout expires to secure your account.',
        'rights' => 'All rights reserved.',
    ],
];
