<?php

declare(strict_types=1);

/**
 * Central configuration for the public complaints channel and CMS workflow.
 *
 * Statuses, categories, priorities and transitions are kept in configuration
 * so the intake and workflow rules can be reviewed in one place. Labels live
 * in lang/{locale}/website.php and are passed to the Vue pages by controllers.
 */
return [
    'reference' => [
        'prefix' => 'PCDR-CMP',
        'padding' => 6,
    ],

    'statuses' => [
        'new',
        'under_review',
        'in_progress',
        'waiting_information',
        'resolved',
        'closed',
        'rejected',
    ],

    'status_transitions' => [
        'new' => ['under_review', 'rejected'],
        'under_review' => ['in_progress', 'waiting_information', 'resolved', 'rejected'],
        'in_progress' => ['waiting_information', 'resolved', 'rejected'],
        'waiting_information' => ['in_progress', 'resolved', 'closed', 'rejected'],
        'resolved' => ['closed', 'in_progress'],
        'closed' => [],
        'rejected' => [],
    ],

    'priorities' => ['low', 'normal', 'high', 'urgent'],

    'categories' => [
        'service_delivery',
        'staff_conduct',
        'assistance',
        'accessibility',
        'data_privacy',
        'other',
    ],

    'contact_methods' => ['email', 'phone'],

    'rate_limit' => [
        'ip_submission' => 20,
        'contact_submission' => 5,
        'decay_minutes' => 10,
    ],

    'attachments' => [
        'enabled' => true,
        'max_files' => 3,
        'max_file_size_kb' => 5120,
        'allowed_mimes' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png',
        ],
        'allowed_extensions' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
    ],

    'notifications' => [
        // Opt-in only. Complaint intake never depends on email delivery.
        'status_email_enabled' => false,
    ],
];
