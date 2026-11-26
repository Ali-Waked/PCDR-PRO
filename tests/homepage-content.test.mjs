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
