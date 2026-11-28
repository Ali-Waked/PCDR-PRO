import assert from 'node:assert/strict'
import test from 'node:test'

import {
    acceptForFormats,
    detectKind,
    displayExtension,
    previewMode,
} from '../resources/js/Support/mediaKind.js'

test('detects type-aware kinds from stored MIME metadata', () => {
    assert.equal(detectKind({ mime: 'image/webp', extension: 'webp' }), 'image')
    assert.equal(detectKind({ mime: 'application/pdf', extension: 'pdf' }), 'pdf')
    assert.equal(detectKind({
        mime: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        extension: 'docx',
    }), 'word')
    assert.equal(detectKind({
        mime: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        extension: 'xlsx',
    }), 'excel')
    assert.equal(detectKind({
        mime: 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        extension: 'pptx',
    }), 'powerpoint')
    assert.equal(detectKind({ mime: 'video/mp4', extension: 'mp4' }), 'video')
    assert.equal(detectKind({ mime: 'audio/mpeg', extension: 'mp3' }), 'audio')
    assert.equal(detectKind({ mime: 'text/plain', extension: 'txt' }), 'text')
})

test('does not trust filenames, generic MIME fallbacks, or SVG for previews', () => {
    assert.equal(detectKind({ mime: 'application/pdf', extension: 'png' }), 'pdf')
    assert.equal(detectKind({ mime: 'image/svg+xml', extension: 'svg' }), 'other')
    assert.equal(detectKind({ mime: 'application/octet-stream', extension: 'exe' }), 'other')
    assert.equal(detectKind({ mime: 'application/octet-stream', extension: 'pdf' }), 'pdf')
    assert.equal(previewMode({ mime: 'image/svg+xml', extension: 'svg' }), '')
    assert.equal(previewMode({ mime: 'application/msword', extension: 'doc' }), '')
})

test('only exposes safe preview modes', () => {
    assert.equal(previewMode({ mime: 'image/gif', extension: 'gif' }), 'image')
    assert.equal(previewMode({ mime: 'application/pdf', extension: 'pdf' }), 'pdf')
    assert.equal(previewMode({ mime: 'video/mp4', extension: 'mp4' }), 'video')
    assert.equal(previewMode({ mime: 'audio/wav', extension: 'wav' }), 'audio')
    assert.equal(previewMode({ mime: 'application/x-msdownload', extension: 'exe' }), '')
})

test('formats display metadata without inventing an extension', () => {
    assert.equal(displayExtension({ mime: 'image/jpeg', extension: '' }), 'jpg')
    assert.equal(displayExtension({ mime: 'application/octet-stream', extension: 'pdf' }), 'pdf')
    assert.equal(displayExtension({ mime: 'application/x-msdownload', extension: 'exe' }), 'exe')
    assert.equal(acceptForFormats(['jpg', 'png', 'gif']), '.jpg,.png,.gif,.jpeg')
    assert.equal(acceptForFormats([]), '')
})
