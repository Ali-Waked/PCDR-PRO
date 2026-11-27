import assert from 'node:assert/strict'
import test from 'node:test'

import { legacyPathForHash, legacyRedirectUrl } from '../resources/js/Support/legacyPageRedirect.js'

test('approved legacy fragments resolve to localized independent pages', () => {
    assert.equal(legacyPathForHash('#vision', 'ar'), '/ar/about/vision')
    assert.equal(legacyPathForHash('#objectives', 'en'), '/en/about/objectives')
    assert.equal(legacyPathForHash('#achievements', 'en'), '/en/about/achievements')
    assert.equal(legacyPathForHash('#board', 'ar'), '/ar/about/board')
    assert.equal(legacyPathForHash('#news', 'ar'), '/ar/media/news')
    assert.equal(legacyPathForHash('#albums', 'en'), '/en/media/albums')
    assert.equal(legacyPathForHash('#stories', 'en'), '/en/media/stories')
    assert.equal(legacyPathForHash('#about', 'ar'), '/ar/about/vision')
})

test('unknown fragments and unsupported locales do not redirect', () => {
    assert.equal(legacyPathForHash('#unknown', 'ar'), null)
    assert.equal(legacyPathForHash('#vision', 'fr'), null)
    assert.equal(legacyPathForHash('', 'ar'), null)
})

test('redirect preserves query parameters and drops the hash', () => {
    assert.equal(
        legacyRedirectUrl('https://pcdr.example/ar/about?ref=newsletter#vision', 'ar'),
        '/ar/about/vision?ref=newsletter',
    )
    assert.equal(
        legacyRedirectUrl('/en/media?utm_source=footer&lang=en#stories', 'en'),
        '/en/media/stories?utm_source=footer&lang=en',
    )
})
