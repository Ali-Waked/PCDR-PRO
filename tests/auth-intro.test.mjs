import assert from 'node:assert/strict'
import test from 'node:test'

import {
    AUTH_INTRO_MIN_VISIBLE_MS,
    ANIMATED_LOGO_SRC,
    STATIC_LOGO_SRC,
    createAuthIntroGate,
    waitForAuthSurfaceReady,
} from '../resources/js/Support/authIntro.js'

class FakeClock {
    constructor() {
        this.current = 0
        this.nextId = 1
        this.tasks = new Map()
    }

    schedule = (callback, delay) => {
        const id = this.nextId
        this.nextId += 1
        this.tasks.set(id, { at: this.current + delay, callback })

        return id
    }

    cancel = (id) => {
        this.tasks.delete(id)
    }

    advance(ms) {
        const target = this.current + ms

        for (;;) {
            const due = [...this.tasks.entries()]
                .filter(([, task]) => task.at <= target)
                .sort((first, second) => first[1].at - second[1].at)[0]

            if (!due) {
                break
            }

            const [id, task] = due
            this.tasks.delete(id)
            this.current = task.at
            task.callback()
        }

        this.current = target
    }
}

function setupEnvironment(clock, { formPresent = true, fontsFail = false } = {}) {
    const images = []

    class FakeImage {
        constructor() {
            this.complete = false
            this.naturalWidth = 0
            images.push(this)
        }
    }

    const win = {
        Image: FakeImage,
        requestAnimationFrame: (callback) => clock.schedule(callback, 16),
    }
    const doc = {
        fonts: {
            ready: fontsFail ? Promise.reject(new Error('font unavailable')) : Promise.resolve(),
        },
        querySelector: (selector) => selector === '#pc-auth-main form' && formPresent ? {} : null,
    }

    return { doc, images, win }
}

test('the intro stays fully visible until both the 3 second minimum and readiness are met', () => {
    const clock = new FakeClock()
    const events = []
    const gate = createAuthIntroGate({
        now: () => clock.current,
        schedule: clock.schedule,
        cancel: clock.cancel,
        onLeave: () => events.push(['leave', clock.current]),
        onFinish: () => events.push(['finish', clock.current]),
    })

    gate.markShown()
    gate.markReady()
    clock.advance(AUTH_INTRO_MIN_VISIBLE_MS - 1)
    assert.deepEqual(events, [])

    clock.advance(1)
    assert.deepEqual(events, [['leave', AUTH_INTRO_MIN_VISIBLE_MS]])

    clock.advance(549)
    assert.deepEqual(events, [['leave', AUTH_INTRO_MIN_VISIBLE_MS]])

    clock.advance(1)
    assert.deepEqual(events, [
        ['leave', AUTH_INTRO_MIN_VISIBLE_MS],
        ['finish', AUTH_INTRO_MIN_VISIBLE_MS + 550],
    ])
})

test('slow readiness never triggers an early fade', () => {
    const clock = new FakeClock()
    const events = []
    const gate = createAuthIntroGate({
        now: () => clock.current,
        schedule: clock.schedule,
        cancel: clock.cancel,
        onLeave: () => events.push(['leave', clock.current]),
        onFinish: () => events.push(['finish', clock.current]),
    })

    gate.markShown()
    clock.advance(4000)
    assert.deepEqual(events, [], 'the intro must remain opaque while login is not ready')

    gate.markReady()
    assert.deepEqual(events, [['leave', 4000]])
    clock.advance(550)
    assert.deepEqual(events, [['leave', 4000], ['finish', 4550]])
})

test('readiness before the first painted frame still honours the full minimum', () => {
    const clock = new FakeClock()
    const events = []
    const gate = createAuthIntroGate({
        now: () => clock.current,
        schedule: clock.schedule,
        cancel: clock.cancel,
        onLeave: () => events.push(['leave', clock.current]),
        onFinish: () => events.push(['finish', clock.current]),
    })

    gate.markReady()
    gate.markShown()
    clock.advance(2999)
    assert.deepEqual(events, [])
    clock.advance(1)
    assert.deepEqual(events, [['leave', 3000]])
})

test('duplicate lifecycle calls cannot create competing timers', () => {
    const clock = new FakeClock()
    let leaves = 0
    let finishes = 0
    const gate = createAuthIntroGate({
        now: () => clock.current,
        schedule: clock.schedule,
        cancel: clock.cancel,
        onLeave: () => { leaves += 1 },
        onFinish: () => { finishes += 1 },
    })

    gate.markShown()
    gate.markShown()
    gate.markReady()
    gate.markReady()
    gate.finish()
    clock.advance(5000)

    assert.equal(leaves, 0)
    assert.equal(finishes, 1)
    assert.equal(gate.state, 'done')
})

test('destroy cancels pending work when the component unmounts mid-intro', () => {
    const clock = new FakeClock()
    let finishes = 0
    const gate = createAuthIntroGate({
        now: () => clock.current,
        schedule: clock.schedule,
        cancel: clock.cancel,
        onFinish: () => { finishes += 1 },
    })

    gate.markShown()
    gate.markReady()
    gate.destroy()
    clock.advance(10_000)

    assert.equal(finishes, 0)
    assert.equal(gate.state, 'done')
})

test('readiness waits for paint, fonts, logo and the rendered login form', async () => {
    const clock = new FakeClock()
    const { doc, images, win } = setupEnvironment(clock)
    let result
    const pending = waitForAuthSurfaceReady({
        doc,
        win,
        schedule: clock.schedule,
        cancel: clock.cancel,
    }).then((value) => { result = value })

    await Promise.resolve()
    clock.advance(32)
    await Promise.resolve()
    assert.equal(result, undefined, 'an unsettled logo must not mark the page ready')

    assert.equal(images.length, 1)
    assert.equal(images[0].src, ANIMATED_LOGO_SRC)
    images[0].onload()
    await pending

    assert.deepEqual(result, { reason: 'ready', assetFailed: false })
})

test('a failed animated logo resolves readiness as a graceful static fallback', async () => {
    const clock = new FakeClock()
    const { doc, images, win } = setupEnvironment(clock, { fontsFail: true })
    const pending = waitForAuthSurfaceReady({
        doc,
        win,
        schedule: clock.schedule,
        cancel: clock.cancel,
    })

    images[0].onerror()
    clock.advance(32)

    assert.deepEqual(await pending, { reason: 'asset-fallback', assetFailed: true })

    /* The static logo is the fallback used by the template and the Vue image. */
    assert.notEqual(ANIMATED_LOGO_SRC, STATIC_LOGO_SRC)
})

test('a bounded timeout prevents a permanent intro when readiness stalls', async () => {
    const clock = new FakeClock()
    const { doc, win } = setupEnvironment(clock)
    const pending = waitForAuthSurfaceReady({
        doc,
        win,
        schedule: clock.schedule,
        cancel: clock.cancel,
        fallbackMs: 1000,
    })

    clock.advance(1000)

    assert.deepEqual(await pending, { reason: 'timeout', assetFailed: false })
})

test('aborting on navigation settles readiness without leaking work', async () => {
    const clock = new FakeClock()
    const { doc, win } = setupEnvironment(clock)
    const controller = new AbortController()
    const pending = waitForAuthSurfaceReady({
        doc,
        win,
        schedule: clock.schedule,
        cancel: clock.cancel,
        signal: controller.signal,
    })

    controller.abort()

    assert.deepEqual(await pending, { reason: 'aborted', assetFailed: false })
    assert.equal(clock.tasks.size, 0)
})
