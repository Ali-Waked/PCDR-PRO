<?php

declare(strict_types=1);

/**
 * Phase 3 public website configuration.
 *
 * Content in this file is limited to wording supplied by the approved
 * strategic plan or by the project brief. Nothing here is operational data.
 */
return [
    'name' => [
        'ar' => 'جمعية العمل التشاركي في الأزمات والكوارث الخيرية',
        'en' => 'Participatory Community for Disaster Response',
    ],

    'short_name' => [
        'ar' => 'PCDR',
        'en' => 'PCDR',
    ],

    'region' => [
        'ar' => 'قطاع غزة',
        'en' => 'Gaza Strip',
    ],

    'slogan' => [
        'ar' => 'من الاستجابة الإنسانية إلى التعافي والتنمية المستدامة',
        'en' => 'From humanitarian response to recovery and sustainable development',
    ],

    'locales' => ['ar', 'en'],
    'default_locale' => 'ar',
    'fallback_locale' => 'en',

    /*
     * Static navigation suggestions for the localized 404 page. Labels are
     * resolved from lang/{locale}/website.php so the error page never needs a
     * database-backed navigation menu.
     */
    'not_found_suggestions' => [
        ['label' => 'home', 'route' => 'public.home'],
    ],

    'description' => [
        'ar' => 'منصة مؤسسية تقدم الرؤية والرسالة والمحاور الاستراتيجية والبرامج المخططة وتقارير جمعية العمل التشاركي في الأزمات والكوارث الخيرية.',
        'en' => 'The institutional platform of the Participatory Community for Disaster Response (PCDR): vision, mission, strategic pillars, planned programmes and publications.',
    ],

    /*
     * The eleven pillars are transcribed from the strategic plan. The English
     * labels are working translations supplied by the project brief; they are
     * not represented as formally approved translations.
     */
    'pillars' => [
        ['code' => '01', 'ar' => 'الاستجابة الإنسانية وإدارة الأزمات والكوارث', 'en' => 'Humanitarian Response and Crisis & Disaster Management'],
        ['code' => '02', 'ar' => 'الأمن الغذائي وتحسين الظروف المعيشية', 'en' => 'Food Security and Improved Living Conditions'],
        ['code' => '03', 'ar' => 'التنمية الاقتصادية وسبل العيش ومكافحة الفقر', 'en' => 'Economic Development, Livelihoods and Poverty Reduction'],
        ['code' => '04', 'ar' => 'الحماية الاجتماعية والرعاية النفسية والاجتماعية', 'en' => 'Social Protection and Psychosocial Care'],
        ['code' => '05', 'ar' => 'الصحة العامة والمياه والإصحاح البيئي', 'en' => 'Public Health, Water and Environmental Sanitation'],
        ['code' => '06', 'ar' => 'التعليم وتنمية قدرات الأطفال والشباب', 'en' => 'Education and Child & Youth Capacity Development'],
        ['code' => '07', 'ar' => 'الإسكان والتعافي المبكر وإعادة تأهيل المرافق', 'en' => 'Housing, Early Recovery and Facility Rehabilitation'],
        ['code' => '08', 'ar' => 'المشاركة المجتمعية والتطوع والعمل التشاركي', 'en' => 'Community Participation, Volunteering and Participatory Action'],
        ['code' => '09', 'ar' => 'الشراكات المحلية والدولية واستقطاب التمويل', 'en' => 'Local and International Partnerships and Resource Mobilisation'],
        ['code' => '10', 'ar' => 'الإعلام والاتصال والمناصرة', 'en' => 'Media, Communication and Advocacy'],
        ['code' => '11', 'ar' => 'البحث والدراسات وتقييم الاحتياجات وقياس الأثر', 'en' => 'Research, Studies, Needs Assessment and Impact Measurement'],
    ],

    'impact_framework' => [
        ['key' => 'response', 'ar' => 'الاستجابة الإنسانية', 'en' => 'Emergency Response'],
        ['key' => 'recovery', 'ar' => 'التعافي', 'en' => 'Recovery'],
        ['key' => 'empowerment', 'ar' => 'التمكين', 'en' => 'Empowerment'],
        ['key' => 'development', 'ar' => 'التنمية المستدامة', 'en' => 'Sustainable Development'],
    ],

    'values' => [
        ['ar' => 'الإنسانية وصون الكرامة', 'en' => 'Humanity and the preservation of dignity'],
        ['ar' => 'العدالة وعدم التمييز وتكافؤ الفرص', 'en' => 'Justice, non-discrimination and equal opportunity'],
        ['ar' => 'النزاهة والشفافية والمساءلة', 'en' => 'Integrity, transparency and accountability'],
        ['ar' => 'المشاركة المجتمعية والعمل التطوعي', 'en' => 'Community participation and voluntary action'],
        ['ar' => 'المهنية والكفاءة والاستدامة', 'en' => 'Professionalism, efficiency and sustainability'],
        ['ar' => 'الشراكة والتكامل وعدم الازدواجية', 'en' => 'Partnership, integration and avoidance of duplication'],
        ['ar' => 'الاستجابة السريعة والمرونة في إدارة الأزمات', 'en' => 'Rapid response and flexibility in crisis management'],
        ['ar' => 'حماية الفئات الأكثر هشاشة', 'en' => 'Protection of the most vulnerable groups'],
        ['ar' => 'احترام الخصوصية وحماية البيانات', 'en' => 'Respect for privacy and data protection'],
        ['ar' => 'التعلم والتطوير والتحسين المستمر', 'en' => 'Learning, development and continuous improvement'],
    ],

    'vision' => [
        'ar' => 'الريادة في العمل الإنساني والتنموي التشاركي، والمساهمة في بناء مجتمع فلسطيني أكثر قدرة على الصمود والتعافي، يتمتع فيه الأفراد والأسر بالكرامة والعدالة وتكافؤ الفرص.',
        'en' => 'To lead participatory humanitarian and development work and to contribute to building a Palestinian society more capable of resilience and recovery, in which individuals and families enjoy dignity, justice and equal opportunity.',
    ],

    'mission' => [
        'ar' => 'تنسيق الجهود الإنسانية وتعزيز الشراكات المحلية والدولية، وتقديم الاستجابات الإغاثية والتنموية المتكاملة، وتمكين الفئات الأكثر هشاشة، وتحسين الظروف المعيشية للأسر المتضررة والفقيرة، من خلال برامج مهنية ومستدامة تقوم على المشاركة المجتمعية والشفافية والمساءلة.',
        'en' => 'To coordinate humanitarian efforts, strengthen local and international partnerships, deliver integrated relief and development responses, empower the most vulnerable groups and improve the living conditions of affected and poor families through professional and sustainable programmes founded on community participation, transparency and accountability.',
    ],

    'about' => [
        'target_groups' => [
            ['ar' => 'الأسر النازحة والفقيرة والمتضررة من الأزمات', 'en' => 'Displaced, poor and crisis-affected families'],
            ['ar' => 'الأسر التي فقدت مصدر دخلها أو معيلها', 'en' => 'Families that lost their income or breadwinner'],
            ['ar' => 'الأطفال والأيتام والنساء المعيلات', 'en' => 'Children, orphans and female-headed households'],
            ['ar' => 'الأشخاص ذوو الإعاقة وكبار السن والمرضى', 'en' => 'Persons with disabilities, older persons and people with illness'],
            ['ar' => 'الشباب والخريجون والباحثون عن عمل', 'en' => 'Youth, graduates and job seekers'],
            ['ar' => 'الأسر في مراكز الإيواء والمخيمات', 'en' => 'Families in shelters and camps'],
            ['ar' => 'المبادرات واللجان المجتمعية والتعاونيات', 'en' => 'Community initiatives, committees and cooperatives'],
        ],
        'approach' => [
            ['ar' => 'تقييم الاحتياجات وتحديد الأولويات وفق معايير معلنة وغير تمييزية', 'en' => 'Needs assessment and prioritisation against published, non-discriminatory criteria'],
            ['ar' => 'تنسيق الجهود مع الفاعلين المحليين والدوليين لتجنب الازدواجية', 'en' => 'Coordination with local and international actors to avoid duplication'],
            ['ar' => 'تصميم برامج مهنية قابلة للاستدامة وقابلة للقياس', 'en' => 'Designing professional, sustainable and measurable programmes'],
            ['ar' => 'إشراك المجتمع في التخطيط والتنفيذ والتقييم', 'en' => 'Engaging communities in planning, implementation and evaluation'],
            ['ar' => 'الشفافية والمساءلة وحماية البيانات الشخصية', 'en' => 'Transparency, accountability and protection of personal data'],
        ],
    ],

    'pagination' => 12,

    'uploads' => [
        'max_file_size_kb' => 10240,
        'allowed_image_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        'allowed_document_mimes' => ['application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
        ],
        'mime_extensions' => [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/plain' => 'txt',
        ],
        'private_disk' => 'private',
        'public_disk' => 'public',
    ],

    'inquiry' => [
        'rate_limit' => 5,
        'ip_rate_limit' => 20,
        'rate_decay_minutes' => 10,
    ],
];
