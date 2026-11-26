<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Website email contract
|--------------------------------------------------------------------------
| Email templates are database-managed content. Only the variables listed
| below may be interpolated; template bodies are never evaluated as PHP.
|
*/

return [
    'variables' => [
        'sender_name' => 'Sender display name',
        'sender_email' => 'Sender email address',
        'inquiry_subject' => 'Inquiry subject or topic',
        'inquiry_reference' => 'Inquiry reference number',
        'reply_message' => 'Editor-approved reply message',
        'organization_name' => 'Public organization display name',
        'current_date' => 'Localized current date',
    ],

    'default_templates' => [
        'inquiry_received' => [
            'ar' => [
                'name' => 'إيصال استلام الاستفسار',
                'description' => 'رسالة تأكيد تلقائية تُرسل لصاحب الاستفسار بعد تسجيله.',
                'subject' => 'استلمنا استفسارك {{inquiry_reference}}',
                'body' => "مرحبًا {{sender_name}}،\n\nاستلمنا استفسارك حول: {{inquiry_subject}}\nرقم المرجع: {{inquiry_reference}}\n\nسيراجع فريقنا رسالتك ويعود إليك في أقرب وقت.\n\nمع خالص التحيات،\n{{organization_name}}\n{{current_date}}",
            ],
            'en' => [
                'name' => 'Inquiry received confirmation',
                'description' => 'Automatic acknowledgement sent to the sender when an inquiry is saved.',
                'subject' => 'We received your inquiry {{inquiry_reference}}',
                'body' => "Dear {{sender_name}},\n\nWe received your inquiry about: {{inquiry_subject}}\nReference: {{inquiry_reference}}\n\nOur team will review your message and respond as soon as possible.\n\nKind regards,\n{{organization_name}}\n{{current_date}}",
            ],
        ],
        'additional_information' => [
            'ar' => [
                'name' => 'طلب معلومات إضافية',
                'description' => 'طلب معلومات أو تفاصيل إضافية لاستكمال مراجعة الاستفسار.',
                'subject' => 'معلومات إضافية بخصوص {{inquiry_reference}}',
                'body' => "مرحبًا {{sender_name}}،\n\nلتمكيننا من مراجعة استفسارك ({{inquiry_reference}}) بشكل كامل، نرجو تزويدنا بالمعلومات الإضافية التالية: ...\n\nمع خالص التحيات،\n{{organization_name}}\n{{current_date}}",
            ],
            'en' => [
                'name' => 'Request for additional information',
                'description' => 'Ask the sender for details needed to complete the review.',
                'subject' => 'Additional information about {{inquiry_reference}}',
                'body' => "Dear {{sender_name}},\n\nTo complete our review of inquiry {{inquiry_reference}}, please provide the following information: ...\n\nKind regards,\n{{organization_name}}\n{{current_date}}",
            ],
        ],
        'general_response' => [
            'ar' => [
                'name' => 'رد عام',
                'description' => 'رد مهني عام على الاستفسارات.',
                'subject' => 'رد على استفسارك {{inquiry_reference}}',
                'body' => "مرحبًا {{sender_name}}،\n\nشكرًا لتواصلك معنا بخصوص: {{inquiry_subject}}.\n\nنود إبلاغك بما يلي: ...\n\nإذا كانت لديك أي أسئلة إضافية، فلا تتردد في الرد على هذه الرسالة.\n\nمع خالص التحيات،\n{{organization_name}}\n{{current_date}}",
            ],
            'en' => [
                'name' => 'General response',
                'description' => 'General professional reply to an inquiry.',
                'subject' => 'Re: {{inquiry_reference}}',
                'body' => "Dear {{sender_name}},\n\nThank you for contacting us about: {{inquiry_subject}}.\n\nWe would like to inform you that: ...\n\nIf you have further questions, please reply to this message.\n\nKind regards,\n{{organization_name}}\n{{current_date}}",
            ],
        ],
        'thank_you' => [
            'ar' => [
                'name' => 'رسالة شكر',
                'description' => 'شكر صاحب الاستفسار على تواصله.',
                'subject' => 'شكرًا لتواصلك معنا',
                'body' => "مرحبًا {{sender_name}}،\n\nشكرًا لتواصلك مع {{organization_name}} بخصوص {{inquiry_subject}}. نقدّر وقتك ورسالتك.\n\nمع خالص التحيات،\n{{organization_name}}\n{{current_date}}",
            ],
            'en' => [
                'name' => 'Thank-you response',
                'description' => 'Thank the sender for getting in touch.',
                'subject' => 'Thank you for contacting us',
                'body' => "Dear {{sender_name}},\n\nThank you for contacting {{organization_name}} about {{inquiry_subject}}. We appreciate your time and message.\n\nKind regards,\n{{organization_name}}\n{{current_date}}",
            ],
        ],
        'unable_to_assist' => [
            'ar' => [
                'name' => 'تعذّر تقديم المساعدة',
                'description' => 'إبلاغ صاحب الاستفسار بأن الطلب خارج نطاق العمل الحالي.',
                'subject' => 'بخصوص استفسارك {{inquiry_reference}}',
                'body' => "مرحبًا {{sender_name}}،\n\nشكرًا لرسالتك بخصوص {{inquiry_subject}}. نأسف لأننا غير قادرين على تقديم المساعدة المطلوبة في الوقت الحالي.\n\nمع خالص التحيات،\n{{organization_name}}\n{{current_date}}",
            ],
            'en' => [
                'name' => 'Unable to assist response',
                'description' => 'Explain that the request is outside current capacity.',
                'subject' => 'Regarding your inquiry {{inquiry_reference}}',
                'body' => "Dear {{sender_name}},\n\nThank you for your message about {{inquiry_subject}}. We are unfortunately unable to provide the requested assistance at this time.\n\nKind regards,\n{{organization_name}}\n{{current_date}}",
            ],
        ],
        'follow_up' => [
            'ar' => [
                'name' => 'متابعة',
                'description' => 'متابعة استفسار لم يتلقَّ ردًا بعد.',
                'subject' => 'متابعة الاستفسار {{inquiry_reference}}',
                'body' => "مرحبًا {{sender_name}}،\n\nنتابع استفسارك {{inquiry_reference}} بخصوص {{inquiry_subject}} ونود التأكد من عدم وجود معلومات إضافية تحتاجها منا.\n\nمع خالص التحيات،\n{{organization_name}}\n{{current_date}}",
            ],
            'en' => [
                'name' => 'Follow-up response',
                'description' => 'Follow up on an inquiry awaiting a reply.',
                'subject' => 'Following up on {{inquiry_reference}}',
                'body' => "Dear {{sender_name}},\n\nWe are following up on inquiry {{inquiry_reference}} about {{inquiry_subject}} and would like to confirm whether you need any further information.\n\nKind regards,\n{{organization_name}}\n{{current_date}}",
            ],
        ],
        'inquiry_reply' => [
            'ar' => [
                'name' => 'رد على استفسار',
                'description' => 'قالب يُستخدم عند إرسال رد معتمد من المحرر على استفسار وارد.',
                'subject' => 'رد على استفسارك {{inquiry_reference}}',
                'body' => "مرحبًا {{sender_name}}،\n\nردًا على استفسارك {{inquiry_reference}} بخصوص {{inquiry_subject}}، نود إبلاغك بما يلي:\n\n{{reply_message}}\n\nمع خالص التحيات،\n{{organization_name}}\n{{current_date}}",
            ],
            'en' => [
                'name' => 'Inquiry reply',
                'description' => 'Used when an editor-approved reply is sent for a received inquiry.',
                'subject' => 'Re: {{inquiry_reference}}',
                'body' => "Dear {{sender_name}},\n\nRegarding your inquiry {{inquiry_reference}} about {{inquiry_subject}}, we would like to inform you:\n\n{{reply_message}}\n\nKind regards,\n{{organization_name}}\n{{current_date}}",
            ],
        ],
        'inquiry_received_notification' => [
            'ar' => [
                'name' => 'إشعار داخلي باستلام استفسار',
                'description' => 'إشعار داخلي لا يتضمن اسم المرسل أو بريده أو نص رسالته.',
                'subject' => 'استفسار جديد {{inquiry_reference}}',
                'body' => "وصل استفسار جديد برقم المرجع {{inquiry_reference}}.\n\nيرجى مراجعته من لوحة إدارة المحتوى في {{organization_name}}.\n{{current_date}}",
            ],
            'en' => [
                'name' => 'Internal inquiry received notification',
                'description' => 'Internal notice that excludes sender name, email and message content.',
                'subject' => 'New inquiry {{inquiry_reference}}',
                'body' => "A new inquiry was received with reference {{inquiry_reference}}.\n\nPlease review it in the website CMS of {{organization_name}}.\n{{current_date}}",
            ],
        ],
    ],
    'internal_notification_recipient' => env('EMAIL_INTERNAL_NOTIFICATION_RECIPIENT'),

    /*
    |--------------------------------------------------------------------------
    | Institutional email brand
    |--------------------------------------------------------------------------
    | The dedicated PNG is embedded as an inline CID attachment by default so
    | recipients do not need to load remote images. MAIL_LOGO_MODE=url is for
    | explicit CDN use and requires a public HTTPS MAIL_ASSET_URL or APP_URL.
    | EMAIL_LOGO_URL remains as a legacy absolute-URL override.
    |
    */
    'brand' => [
        'logo_mode' => env('MAIL_LOGO_MODE', 'cid'),
        'asset_url' => env('MAIL_ASSET_URL'),
        'logo_path' => 'assets/pcdr-homepage/logo/pcdr-logo.png',
        'public_logo_path' => 'assets/email/email-logo.png',
        'embedded_logo_path' => 'mail/email-logo.png',
        'logo_cid' => 'pcdr-email-logo@pcdr.local',
        'logo_url' => env('EMAIL_LOGO_URL'),
        'website_url' => env('EMAIL_WEBSITE_URL'),
        'logo_width' => 120,
        'logo_height' => 120,
    ],

    'colors' => [
        'navy' => '#3E5172',
        'teal' => '#71BCB7',
        'sand' => '#DAC3A3',
        'wheat' => '#D7C5A1',
        'white' => '#FFFFFF',
        'navy_900' => '#25334B',
        'navy_800' => '#30415E',
        'navy_100' => '#E9EDF2',
        'teal_700' => '#397F7B',
        'teal_100' => '#EAF5F4',
        'sand_100' => '#F7F2EA',
    ],

    'preview' => [
        'palette' => [
            'navy' => '#3E5172',
            'teal' => '#71BCB7',
            'sand' => '#DAC3A3',
            'background' => '#F7F2EA',
            'surface' => '#FFFFFF',
            'muted' => '#5C6B82',
            'border' => '#E9EDF2',
        ],
    ],
];
