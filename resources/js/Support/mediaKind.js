const RASTER_IMAGE_MIMES = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif',
]

const MIME_KINDS = {
    'image/jpeg': 'image',
    'image/png': 'image',
    'image/webp': 'image',
    'image/gif': 'image',
    'application/pdf': 'pdf',
    'application/msword': 'word',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'word',
    'application/vnd.ms-excel': 'excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'excel',
    'text/csv': 'excel',
    'application/vnd.ms-powerpoint': 'powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.slideshow': 'powerpoint',
    'text/plain': 'text',
}

const GENERIC_MIMES = ['', 'application/octet-stream', 'binary/octet-stream', 'application/unknown']

const EXTENSION_KINDS = {
    jpg: 'image', jpeg: 'image', png: 'image', webp: 'image', gif: 'image',
    pdf: 'pdf',
    doc: 'word', docx: 'word',
    xls: 'excel', xlsx: 'excel', csv: 'excel',
    ppt: 'powerpoint', pptx: 'powerpoint',
    mp4: 'video', webm: 'video', ogv: 'video', mov: 'video',
    mp3: 'audio', wav: 'audio', ogg: 'audio', aac: 'audio', m4a: 'audio',
    txt: 'text',
}

const KIND_EXTENSIONS = {
    image: ['jpg', 'png', 'webp', 'gif'],
    pdf: ['pdf'],
    word: ['doc', 'docx'],
    excel: ['xls', 'xlsx', 'csv'],
    powerpoint: ['ppt', 'pptx'],
    video: ['mp4', 'webm', 'ogv', 'mov'],
    audio: ['mp3', 'wav', 'ogg', 'aac', 'm4a'],
    text: ['txt'],
    other: [],
}

const KIND_META = {
    image: { icon: 'image', tone: 'teal' },
    pdf: { icon: 'pdf', tone: 'red' },
    word: { icon: 'word', tone: 'blue' },
    excel: { icon: 'excel', tone: 'green' },
    powerpoint: { icon: 'slides', tone: 'sand' },
    video: { icon: 'video', tone: 'navy' },
    audio: { icon: 'audio', tone: 'violet' },
    text: { icon: 'file', tone: 'slate' },
    other: { icon: 'file', tone: 'slate' },
}

const SAFE_VIDEO_MIMES = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime']

const SAFE_AUDIO_MIMES = [
    'audio/mpeg',
    'audio/mp3',
    'audio/ogg',
    'audio/wav',
    'audio/x-wav',
    'audio/aac',
    'audio/mp4',
]

export const MEDIA_KINDS = Object.keys(KIND_EXTENSIONS)

function normalize(value) {
    return String(value ?? '').trim().toLowerCase()
}

function safeExtension(value) {
    const extension = normalize(value).replace(/^\./, '')
    return /^[a-z0-9]{1,8}$/.test(extension) ? extension : ''
}

/**
 * Derive a presentation kind from server-stored metadata. Untrusted SVG and
 * unknown formats deliberately resolve to `other` instead of being previewed.
 */
export function detectKind(value, extension = '') {
    const mime = typeof value === 'string'
        ? normalize(value)
        : normalize(value?.mime)

    const storedExtension = safeExtension(
        typeof value === 'string' ? extension : (value?.extension || extension),
    )

    if (Object.hasOwn(MIME_KINDS, mime)) {
        return MIME_KINDS[mime]
    }

    if (mime.startsWith('video/')) {
        return 'video'
    }

    if (mime.startsWith('audio/')) {
        return 'audio'
    }

    if (GENERIC_MIMES.includes(mime) && Object.hasOwn(EXTENSION_KINDS, storedExtension)) {
        return EXTENSION_KINDS[storedExtension]
    }

    return 'other'
}

export function kindMeta(kind) {
    return KIND_META[detectKind(kind)] || KIND_META.other
}

export function displayExtension(value) {
    const extension = safeExtension(typeof value === 'string' ? '' : value?.extension)
    if (extension !== '') {
        return extension
    }

    return KIND_EXTENSIONS[detectKind(value)][0] || ''
}

/** Return the safe preview mode for an item, or an empty string. */
export function previewMode(value) {
    const mime = normalize(typeof value === 'string' ? value : value?.mime)
    const kind = detectKind(value)

    if (kind === 'image' && RASTER_IMAGE_MIMES.includes(mime)) {
        return 'image'
    }

    if (kind === 'pdf' && mime === 'application/pdf') {
        return 'pdf'
    }

    if (kind === 'video' && SAFE_VIDEO_MIMES.includes(mime)) {
        return 'video'
    }

    if (kind === 'audio' && SAFE_AUDIO_MIMES.includes(mime)) {
        return 'audio'
    }

    return ''
}

export function acceptForFormats(formats = []) {
    const extensions = (Array.isArray(formats) ? formats : [])
        .map(safeExtension)
        .filter(Boolean)

    const extra = []
    if (extensions.includes('jpg')) {
        extra.push('jpeg')
    }

    return [...new Set([...extensions, ...extra])]
        .map((extension) => `.${extension}`)
        .join(',')
}
