<?php

declare(strict_types=1);

return [
    'layout' => [
        'eyebrow' => 'Institutional correspondence',
        'reference' => 'Reference',
        'website' => 'Website',
        'footer_note' => 'This message was sent through the institutional email system.',
        'logo_alt' => ':organization logo',
        'preview_notice' => 'Preview only · sample data · no email sent',
        'preview_from' => 'From',
        'preview_to' => 'To (sample)',
    ],
    'auth' => [
        'verify_subject' => 'Verify your email address',
        'verify_intro' => 'Please use the button below to verify your email address.',
        'verify_action' => 'Verify email address',
        'verify_outro' => 'If you did not create an account, no further action is required.',
        'reset_subject' => 'Reset your password',
        'reset_intro' => 'You are receiving this email because we received a password reset request for your account.',
        'reset_action' => 'Reset password',
        'reset_expiry' => 'This password reset link will expire in :count minutes.',
        'reset_outro' => 'If you did not request a password reset, no further action is required.',
    ],
    'account' => [
        'change_subject' => 'Confirm your new email address',
        'change_intro' => 'An email address change was requested for your account. Confirm the new address using the button below.',
        'change_action' => 'Confirm new email address',
        'change_outro' => 'This link expires in :count minutes. If you did not request this change, no further action is required.',
        'notice_subject' => 'Email address change requested',
        'notice_intro' => 'A request was made to change the email address associated with your account.',
        'notice_outro' => 'Your current email address remains active and verified until the change is confirmed. If you did not request this change, please contact an administrator.',
    ],
];
