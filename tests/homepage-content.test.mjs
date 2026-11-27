import assert from 'node:assert/strict'
import test from 'node:test'
import { ref } from 'vue'

import { useHomepageContent } from '../resources/js/composables/useHomepageContent.js'

const sections = [
    {
        key: 'hero',
        items: [
            { key: 'slide-2', published: true, sortOrder: 2, title: 'Second' },
            { key: 'slide-1', published: true, sortOrder: 1, title: 'First' },
            { key: 'slide-hidden', published: false, sortOrder: 0, title: 'Hidden' },
        ],
    },
]

test('useHomepageContent accepts a getter source without calling filter on the getter', () => {
    const content = useHomepageContent(() => sections)

    assert.deepEqual(content.items('hero', 'slide-').map((item) => item.key), ['slide-1', 'slide-2'])
    assert.equal(content.section('hero')?.key, 'hero')
})

test('useHomepageContent accepts a ref source and tracks updates', () => {
    const source = ref(sections)
    const content = useHomepageContent(source)

    assert.equal(content.items('hero').length, 2)

    source.value = [{ key: 'hero', items: [] }]
    assert.equal(content.items('hero').length, 0)
})

test('useHomepageContent accepts a plain array source', () => {
    const content = useHomepageContent(sections)
    assert.equal(content.items('hero', 'slide-').length, 2)
})

test('useHomepageContent degrades safely when the source is not an array', () => {
    for (const source of [undefined, null, {}, 'sections']) {
        const content = useHomepageContent(source)
        assert.deepEqual(content.sectionsByKey.value, {})
        assert.deepEqual(content.items('hero'), [])
    }
})

test('useHomepageContent groups prototype items by tab', () => {
    const content = useHomepageContent([{
        key: 'news',
        items: [
            { key: 'news-1', tab: 'news', published: true, sortOrder: 2 },
            { key: 'pubs-1', tab: 'publications', published: true, sortOrder: 1 },
            { key: 'news-2', tab: 'news', published: true, sortOrder: 1 },
            { key: 'news-draft', tab: 'news', published: false, sortOrder: 0 },
        ],
    }])

    assert.deepEqual(content.itemsByTab('news', 'news').map((item) => item.key), ['news-2', 'news-1'])
    assert.deepEqual(content.itemsByTab('news', 'publications').map((item) => item.key), ['pubs-1'])
    assert.deepEqual(content.itemsByTab('missing', 'news'), [])
})

test('useHomepageContent keeps removed public paths inert', () => {
    const content = useHomepageContent([])

    assert.equal(content.safeUrl('/complaints'), '/complaints')
    assert.equal(content.safeUrl('/ar/complaints'), '/ar/complaints')
    assert.equal(content.safeUrl('/en/complaints'), '/en/complaints')
    assert.equal(content.safeUrl('/partners'), '/partners')
    assert.equal(content.safeUrl('/ar/partners'), '/ar/partners')
    assert.equal(content.safeUrl('/en/partners'), '/en/partners')
    assert.equal(content.safeUrl('#news'), '#news')
    assert.equal(content.safeUrl('/ar'), '/ar')
    assert.equal(content.safeUrl('/ar/programs'), '/ar/programs')
    assert.equal(content.safeUrl('/en/programs'), '/en/programs')
    assert.equal(content.safeUrl('/en/news/article'), '')
    assert.equal(content.safeUrl('https://example.org/page'), 'https://example.org/page')
    assert.equal(content.safeUrl('#'), '')
    assert.equal(content.safeUrl('javascript:alert(1)'), '')
    assert.equal(content.safeUrl('data:text/html,unsafe'), '')
    assert.equal(content.safeUrl('http://example.org'), '')
    assert.equal(content.safeUrl('//evil.example'), '')
    assert.equal(content.safeUrl('/\\evil.example'), '')
    assert.equal(content.safeUrl('/%5Cevil.example'), '')
    assert.equal(content.safeUrl('/ar/page\nX-Evil: 1'), '')
})

test('useHomepageContent exposes visibility-aware ordered sections', () => {
    const content = useHomepageContent([
        { key: 'late', sort_order: 2, enabled: true },
        { key: 'hidden', sortOrder: 1, enabled: false },
        { key: 'draft', sortOrder: 3, status: 'draft' },
        { key: 'early', sortOrder: 1, enabled: true },
    ])

    assert.deepEqual(content.visibleSections().map((section) => section.key), ['late', 'early'])
    assert.deepEqual(content.orderedSections().map((section) => section.key), ['early', 'late'])
    assert.equal(content.visibleSection('early')?.key, 'early')
    assert.equal(content.visibleSection('hidden'), null)
    assert.equal(content.isVisibleSection({ key: 'draft', status: 'draft' }), false)
})
