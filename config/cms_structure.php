<?php

declare(strict_types=1);
use App\Models\Article;
use App\Models\DirectoryPartner;
use App\Models\Program;
use App\Models\Project;
use App\Models\StrategicPillar;

/*
|--------------------------------------------------------------------------
| Centralized website structure registry
|--------------------------------------------------------------------------
|
| Pages and sections are predefined by the application. The CMS manages only
| their content and visibility; it can never create a page or a section. This
| registry is the single source of truth used by the seeder, the CMS, public
| route guards and navigation filtering.
|
*/

return [
    'pages' => [
        'home' => [
            'path' => '/',
            'route' => 'public.home',
            'label' => ['ar' => 'الرئيسية', 'en' => 'Home'],
            'order' => 0,
        ],
        'about' => [
            'path' => '/about',
            'route' => 'public.about',
            'label' => ['ar' => 'من نحن', 'en' => 'About us'],
            'order' => 1,
        ],
        'about_vision' => [
            'path' => '/about/vision',
            'route' => 'public.about.vision',
            'label' => ['ar' => 'رؤيتنا', 'en' => 'Our vision'],
            'order' => 2,
        ],
        'about_objectives' => [
            'path' => '/about/objectives',
            'route' => 'public.about.objectives',
            'label' => ['ar' => 'أهدافنا الاستراتيجية', 'en' => 'Our strategic objectives'],
            'order' => 3,
        ],
        'about_achievements' => [
            'path' => '/about/achievements',
            'route' => 'public.about.achievements',
            'label' => ['ar' => 'إنجازاتنا بالأرقام', 'en' => 'Our achievements in numbers'],
            'order' => 4,
        ],
        'about_board' => [
            'path' => '/about/board',
            'route' => 'public.about.board',
            'label' => ['ar' => 'مجلس الإدارة', 'en' => 'Board of directors'],
            'order' => 5,
        ],
        'areas_of_work' => [
            'path' => '/programs',
            'route' => 'public.programs',
            'label' => ['ar' => 'مجالات العمل', 'en' => 'Areas of work'],
            'order' => 6,
        ],
        'projects' => [
            'path' => '/programs',
            'route' => null,
            'label' => ['ar' => 'المشاريع', 'en' => 'Projects'],
            'order' => 7,
        ],
        'partners' => [
            'path' => '/partners',
            'route' => 'public.partners',
            'label' => ['ar' => 'الشركاء', 'en' => 'Partners'],
            'order' => 8,
        ],
        'media_center' => [
            'path' => '/media',
            'route' => 'public.media.center',
            'label' => ['ar' => 'المركز الإعلامي', 'en' => 'Media center'],
            'order' => 9,
        ],
        'media_news' => [
            'path' => '/media/news',
            'route' => 'public.media.news',
            'label' => ['ar' => 'الأخبار والأنشطة', 'en' => 'News and activities'],
            'order' => 10,
        ],
        'media_albums' => [
            'path' => '/media/albums',
            'route' => 'public.media.albums',
            'label' => ['ar' => 'ألبوم الصور', 'en' => 'Photo album'],
            'order' => 11,
        ],
        'media_stories' => [
            'path' => '/media/stories',
            'route' => 'public.media.stories',
            'label' => ['ar' => 'قصص نجاح', 'en' => 'Success stories'],
            'order' => 12,
        ],
        'contact' => [
            'path' => '/contact',
            'route' => 'public.contact',
            'label' => ['ar' => 'اتصل بنا', 'en' => 'Contact us'],
            'order' => 13,
        ],
        'complaints' => [
            'path' => '/complaints',
            'route' => 'public.complaints',
            'label' => ['ar' => 'صندوق الشكاوى', 'en' => 'Complaints'],
            'order' => 14,
        ],
    ],

    'sections' => [
        'hero' => [
            'page' => 'home',
            'order' => 0,
            'label' => ['ar' => 'شريط الصور الرئيسي', 'en' => 'Hero slider'],
            'content' => 'slides',
        ],
        'ticker' => [
            'page' => 'home',
            'order' => 1,
            'label' => ['ar' => 'شريط الإعلانات', 'en' => 'Announcement ticker'],
            // The public ticker renders standalone announcements (title + URL)
            // and may also reference a published article. It has no tabs,
            // icons, images or statistics of its own.
            'content' => 'announcements',
            'source' => 'article',
        ],
        'stories' => [
            'page' => 'home',
            'order' => 2,
            'label' => ['ar' => 'قصص النجاح', 'en' => 'Success stories'],
            'content' => 'stories',
        ],
        'about' => [
            'page' => 'home',
            'order' => 3,
            'label' => ['ar' => 'من نحن', 'en' => 'About us'],
            'content' => 'organization',
        ],
        'vision_mission' => [
            'page' => 'home',
            'order' => 4,
            'label' => ['ar' => 'الرؤية والرسالة', 'en' => 'Vision and mission'],
            'content' => 'organization',
            'presentation' => 'about',
        ],
        'objectives' => [
            'page' => 'home',
            'order' => 5,
            'label' => ['ar' => 'الأهداف', 'en' => 'Objectives'],
            'content' => 'objectives',
            'presentation' => 'about',
        ],
        'albums' => [
            'page' => 'home',
            'order' => 6,
            'label' => ['ar' => 'ألبوم الصور', 'en' => 'Photo album'],
            'content' => 'albums',
        ],
        'news' => [
            'page' => 'home',
            'order' => 7,
            'label' => ['ar' => 'آخر الأخبار', 'en' => 'Latest news'],
            'content' => 'links',
            'source' => 'article',
        ],
        'latest_projects' => [
            'page' => 'home',
            'order' => 8,
            'label' => ['ar' => 'آخر المشاريع', 'en' => 'Latest projects'],
            'content' => 'links',
            'source' => 'project',
            'tab' => 'projects',
            'presentation' => 'news',
        ],
        'program_areas' => [
            'page' => 'home',
            'order' => 9,
            'label' => ['ar' => 'مجالات البرامج المقترحة', 'en' => 'Suggested program areas'],
            'content' => 'links',
            'source' => 'pillar',
            'presentation' => 'programs',
        ],
        'interventions' => [
            'page' => 'home',
            'order' => 10,
            'label' => ['ar' => 'البرامج والتدخلات', 'en' => 'Programs and interventions'],
            'content' => 'links',
            'source' => 'program',
            'presentation' => 'programs',
        ],

        'about_intro' => [
            'page' => 'about',
            'order' => 11,
            'label' => ['ar' => 'مقدمة من نحن', 'en' => 'About introduction'],
            'content' => 'organization',
        ],
        'about_identity' => [
            'page' => 'about',
            'order' => 12,
            'label' => ['ar' => 'الرؤية والرسالة والأهداف', 'en' => 'Vision, mission and objectives'],
            'content' => 'organization',
        ],
        'areas_intro' => [
            'page' => 'areas_of_work',
            'order' => 13,
            'label' => ['ar' => 'مقدمة مجالات العمل', 'en' => 'Areas of work introduction'],
            'content' => 'organization',
        ],
        'areas_list' => [
            'page' => 'areas_of_work',
            'order' => 14,
            'label' => ['ar' => 'قائمة البرامج والمشاريع', 'en' => 'Programs and projects'],
            'content' => 'links',
            'source' => 'pillar',
        ],
        'projects_list' => [
            'page' => 'projects',
            'order' => 15,
            'label' => ['ar' => 'قائمة المشاريع', 'en' => 'Projects listing'],
            'content' => 'links',
            'source' => 'project',
        ],
        'partners_list' => [
            'page' => 'partners',
            'order' => 16,
            'label' => ['ar' => 'قائمة الشركاء', 'en' => 'Partners listing'],
            'content' => 'links',
            'source' => 'partner',
        ],
        'media_news' => [
            'page' => 'media_center',
            'order' => 17,
            'label' => ['ar' => 'الأخبار', 'en' => 'News'],
            'content' => 'links',
            'source' => 'article',
        ],
        'media_albums' => [
            'page' => 'media_center',
            'order' => 18,
            'label' => ['ar' => 'ألبومات الصور', 'en' => 'Photo albums'],
            'content' => 'albums',
        ],
        'contact_form' => [
            'page' => 'contact',
            'order' => 19,
            'label' => ['ar' => 'نموذج التواصل', 'en' => 'Contact form'],
            'content' => 'organization',
        ],
        'footer' => [
            'page' => 'home',
            'order' => 20,
            'label' => ['ar' => 'تذييل الموقع', 'en' => 'Site footer'],
            'content' => 'footer',
        ],
    ],

    /**
     * Hero keys that are presentation controls (CTA buttons), not slides.
     * Slide membership is decided by this registry and by the ticker prefixes
     * below, never by the outdated `slide-` naming convention.
     */
    'hero_non_slide_keys' => ['primary', 'secondary'],

    /**
     * Item prefixes owned by each typed section. Legacy rows keep their keys
     * so historical content remains identifiable after a section move.
     */
    'item_prefixes' => [
        'hero' => ['slide-', 'hero-'],
        'ticker' => ['ticker-'],
        'stories' => ['story-'],
        'objectives' => ['objective-', 'value-'],
        'vision_mission' => ['mission', 'vision'],
        'albums' => ['album-'],
        'footer' => ['footer-', 'social-', 'value-'],
    ],

    'link_sources' => [
        'article' => [Article::class],
        'project' => [Project::class],
        'pillar' => [StrategicPillar::class],
        'program' => [Program::class],
        'partner' => [DirectoryPartner::class],
    ],

    'presentation_merges' => [
        'about' => ['vision_mission', 'objectives'],
        'news' => ['latest_projects'],
        'programs' => ['program_areas', 'interventions'],
    ],
];
