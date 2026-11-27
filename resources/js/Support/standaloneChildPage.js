/**
 * Presentation mapping for the independent About and Media Center pages.
 * It only arranges CMS-provided content; every rendered value comes from the
 * backend payload or from fixed interface copy.
 */
const COPY = {
    ar: {
        about: 'تعارف',
        media: 'المركز الإعلامي',
        vision: 'رؤيتنا',
        mission: 'رسالتنا',
        objectives: 'الأهداف الاستراتيجية',
        achievements: 'الإنجازات بالأرقام',
        focusAreas: 'محاور العمل',
        board: 'مجلس الإدارة',
        news: 'الأخبار',
        activities: 'الأنشطة',
        albums: 'ألبوم الصور',
        stories: 'قصص نجاح',
        empty: 'لا توجد محتويات منشورة بعد.',
        visionLead: 'رؤية الجمعية ورسالتها كما تنشرها المصادر المعتمدة.',
        objectivesLead: 'الأهداف ومحاور العمل المعتمدة في المحتوى المنشور.',
        achievementsLead: 'إنجازات الجمعية ومحاور تركيز عملها كما تنشرها المصادر المعتمدة.',
        boardLead: 'أعضاء مجلس الإدارة وفق المحتوى المنشور.',
        newsLead: 'الأخبار والأنشطة المنشورة.',
        albumsLead: 'ألبومات الصور المنشورة.',
        storiesLead: 'قصص النجاح المنشورة.',
    },
    en: {
        about: 'Who we are',
        media: 'Media centre',
        vision: 'Our vision',
        mission: 'Our mission',
        objectives: 'Strategic objectives',
        achievements: 'Achievements in numbers',
        focusAreas: 'Focus areas',
        board: 'Board of directors',
        news: 'News',
        activities: 'Activities',
        albums: 'Photo album',
        stories: 'Success stories',
        empty: 'No published content yet.',
        visionLead: 'The published vision and mission of the association.',
        objectivesLead: 'The published objectives and focus areas of the association.',
        achievementsLead: 'Published achievements and focus areas of the association.',
        boardLead: 'The published members of the board of directors.',
        newsLead: 'Published news and activities.',
        albumsLead: 'Published photo albums.',
        storiesLead: 'Published success stories.',
    },
}

function copyFor(locale) {
    return COPY[locale] ?? COPY.en
}

function array(value) {
    return Array.isArray(value) ? value : []
}

export function aboutVisionPage(content, locale) {
    const copy = copyFor(locale)
    const vision = content?.vision ?? {}
    const mission = content?.mission ?? {}

    return {
        eyebrow: copy.about,
        title: copy.vision,
        lead: copy.visionLead,
        blocks: [
            {
                id: 'vision',
                kicker: copy.vision,
                title: vision.title || copy.vision,
                type: 'statements',
                items: [
                    { key: 'vision', title: vision.title || copy.vision, body: vision.body },
                    { key: 'mission', title: mission.title || copy.mission, body: mission.body },
                ],
                empty: copy.empty,
            },
        ],
    }
}

export function aboutObjectivesPage(content, locale) {
    const copy = copyFor(locale)

    return {
        eyebrow: copy.about,
        title: copy.objectives,
        lead: copy.objectivesLead,
        blocks: [
            {
                id: 'objectives',
                kicker: copy.objectives,
                title: copy.objectives,
                type: 'items',
                items: array(content?.objectives),
                empty: copy.empty,
            },
            {
                id: 'focus-areas',
                kicker: copy.focusAreas,
                title: copy.focusAreas,
                type: 'pillars',
                items: array(content?.pillars),
                empty: copy.empty,
            },
        ],
    }
}

export function aboutAchievementsPage(content, locale) {
    const copy = copyFor(locale)

    return {
        eyebrow: copy.about,
        title: copy.achievements,
        lead: copy.achievementsLead,
        blocks: [
            {
                id: 'achievements',
                kicker: copy.achievements,
                title: copy.achievements,
                type: 'items',
                items: array(content?.achievements),
                empty: copy.empty,
            },
            {
                id: 'focus-areas',
                kicker: copy.focusAreas,
                title: copy.focusAreas,
                type: 'pillars',
                items: array(content?.pillars),
                empty: copy.empty,
            },
        ],
    }
}

export function aboutBoardPage(content, locale) {
    const copy = copyFor(locale)

    return {
        eyebrow: copy.about,
        title: copy.board,
        lead: copy.boardLead,
        blocks: [
            {
                id: 'board',
                kicker: copy.board,
                title: copy.board,
                type: 'members',
                items: array(content?.board),
                empty: copy.empty,
            },
        ],
    }
}

export function mediaNewsPage(content, locale) {
    const copy = copyFor(locale)

    return {
        eyebrow: copy.media,
        title: copy.news,
        lead: copy.newsLead,
        blocks: [
            {
                id: 'news',
                kicker: copy.news,
                title: copy.news,
                type: 'articles',
                items: array(content?.news),
                empty: copy.empty,
            },
            {
                id: 'activities',
                kicker: copy.activities,
                title: copy.activities,
                type: 'articles',
                items: array(content?.activities),
                empty: copy.empty,
            },
        ],
    }
}

export function mediaAlbumsPage(content, locale) {
    const copy = copyFor(locale)

    return {
        eyebrow: copy.media,
        title: copy.albums,
        lead: copy.albumsLead,
        blocks: [
            {
                id: 'albums',
                kicker: copy.albums,
                title: content?.albums?.title || copy.albums,
                intro: content?.albums?.eyebrow,
                type: 'gallery',
                items: array(content?.albums?.items),
                empty: copy.empty,
            },
        ],
    }
}

export function mediaStoriesPage(content, locale) {
    const copy = copyFor(locale)

    return {
        eyebrow: copy.media,
        title: copy.stories,
        lead: copy.storiesLead,
        blocks: [
            {
                id: 'stories',
                kicker: copy.stories,
                title: content?.stories?.title || copy.stories,
                intro: content?.stories?.eyebrow,
                type: 'stories',
                items: array(content?.stories?.items),
                empty: copy.empty,
            },
        ],
    }
}
