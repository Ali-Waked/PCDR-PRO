const HTTPS_URL = /^https:\/\/[^\s]+$/i
const LOCAL_VIDEO = /^\/(?!\/)[^\s]*\.(mp4|webm|ogg)(\?[^\s]*)?$/i

const YOUTUBE_HOSTS = new Set([
    'youtube.com',
    'www.youtube.com',
    'm.youtube.com',
    'youtu.be',
    'www.youtu.be',
])

const VIMEO_HOSTS = new Set([
    'vimeo.com',
    'www.vimeo.com',
    'player.vimeo.com',
])

function youtubeId(url) {
    if (url.hostname === 'youtu.be' || url.hostname === 'www.youtu.be') {
        const id = url.pathname.split('/').filter(Boolean)[0] || ''
        return /^[A-Za-z0-9_-]{6,20}$/.test(id) ? id : null
    }

    if (url.pathname === '/watch') {
        const id = url.searchParams.get('v') || ''
        return /^[A-Za-z0-9_-]{6,20}$/.test(id) ? id : null
    }

    const match = url.pathname.match(/^\/(?:embed|shorts|live)\/([A-Za-z0-9_-]{6,20})/)
    return match ? match[1] : null
}

function vimeoId(url) {
    const match = url.pathname.match(/^\/(?:video\/)?(\d{4,12})(?:\/|$)/)
    return match ? match[1] : null
}

/**
 * Resolve a CMS video value to a safe playback source. Only explicit external
 * providers and same-origin video files are accepted; arbitrary embed HTML is
 * never returned and unsupported hosts are rejected.
 */
export function videoSource(value) {
    if (typeof value !== 'string') {
        return null
    }

    const url = value.trim()
    if (url === '') {
        return null
    }

    if (LOCAL_VIDEO.test(url)) {
        return { type: 'file', src: url }
    }

    if (!HTTPS_URL.test(url)) {
        return null
    }

    let parsed
    try {
        parsed = new URL(url)
    } catch {
        return null
    }

    const host = parsed.hostname.toLowerCase()
    if (YOUTUBE_HOSTS.has(host)) {
        const id = youtubeId(parsed)
        return id ? { type: 'embed', provider: 'youtube', src: `https://www.youtube-nocookie.com/embed/${id}` } : null
    }

    if (VIMEO_HOSTS.has(host)) {
        const id = vimeoId(parsed)
        return id ? { type: 'embed', provider: 'vimeo', src: `https://player.vimeo.com/video/${id}` } : null
    }

    return null
}
