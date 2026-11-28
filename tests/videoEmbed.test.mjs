import assert from 'node:assert/strict'
import test from 'node:test'

import { videoSource } from '../resources/js/Support/videoEmbed.js'

test('accepts explicit YouTube, Facebook and Vimeo providers as canonical embeds', () => {
    assert.deepEqual(videoSource('https://www.youtube.com/watch?v=abcdefghijk'), {
        type: 'embed',
        provider: 'youtube',
        src: 'https://www.youtube-nocookie.com/embed/abcdefghijk',
    })
    assert.deepEqual(videoSource('https://youtu.be/abcDEF-123'), {
        type: 'embed',
        provider: 'youtube',
        src: 'https://www.youtube-nocookie.com/embed/abcDEF-123',
    })
    assert.deepEqual(videoSource('https://vimeo.com/123456789'), {
        type: 'embed',
        provider: 'vimeo',
        src: 'https://player.vimeo.com/video/123456789',
    })
})

test('accepts Facebook watch, reel and profile video URLs', () => {
    const expected = {
        type: 'embed',
        provider: 'facebook',
        src: 'https://www.facebook.com/plugins/video.php?href=https%3A%2F%2Fwww.facebook.com%2Fwatch%2F%3Fv%3D123456789012345&show_text=false&width=560',
    }

    assert.deepEqual(videoSource('https://www.facebook.com/watch?v=123456789012345'), expected)
    assert.deepEqual(videoSource('https://www.facebook.com/reel/123456789012345/'), expected)
    assert.deepEqual(videoSource('https://m.facebook.com/pcdr/videos/123456789012345/'), expected)
})

test('accepts only same-origin video files', () => {
    assert.deepEqual(videoSource('/storage/media/intro.mp4'), {
        type: 'file',
        src: '/storage/media/intro.mp4',
    })
    assert.deepEqual(videoSource('/storage/media/intro.webm?download=1'), {
        type: 'file',
        src: '/storage/media/intro.webm?download=1',
    })
    assert.equal(videoSource('//evil.test/video.mp4'), null)
    assert.equal(videoSource('https://cdn.test/video.mp4'), null)
    assert.equal(videoSource('/storage/media/intro.html'), null)
})

test('rejects arbitrary markup, schemes, hosts and malformed provider identifiers', () => {
    for (const value of [
        '',
        '   ',
        null,
        42,
        'javascript:alert(1)',
        'data:text/html,<script>alert(1)</script>',
        '<iframe src="https://youtube.com"></iframe>',
        'http://youtube.com/watch?v=abcdefghijk',
        'https://youtube.com.evil.test/watch?v=abcdefghijk',
        'https://youtube.com/watch?v=short',
        'https://www.facebook.com/watch?v=abc',
        'https://facebook.com.evil.test/watch?v=123456789012345',
        'https://vimeo.com/not-a-number',
    ]) {
        assert.equal(videoSource(value), null, String(value))
    }
})
