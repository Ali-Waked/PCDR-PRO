export const AUTH_INTRO_MIN_VISIBLE_MS = 3000
export const AUTH_INTRO_LEAVE_MS = 550
export const AUTH_INTRO_READY_FALLBACK_MS = 6000
export const ANIMATED_LOGO_SRC = '/assets/pcdr-homepage/logo/pcdr-logo-animated.svg'
export const STATIC_LOGO_SRC = '/assets/pcdr-homepage/logo/pcdr-logo.png'

const defaultNow = () => (typeof performance !== 'undefined' ? performance.now() : Date.now())
const defaultSchedule = (callback, delay) => setTimeout(callback, delay)
const defaultCancel = (timer) => clearTimeout(timer)

/**
 * Gate the intro fade on two independent conditions: a minimum visible time
 * measured from the first painted frame, and an explicit login-surface ready
 * signal. The overlay stays fully opaque until both conditions hold.
 */
export function createAuthIntroGate({
    now = defaultNow,
    schedule = defaultSchedule,
    cancel = defaultCancel,
    minVisibleMs = AUTH_INTRO_MIN_VISIBLE_MS,
    leaveMs = AUTH_INTRO_LEAVE_MS,
    onLeave = () => {},
    onFinish = () => {},
} = {}) {
    let state = 'idle'
    let shownAt = 0
    let ready = false
    let timer = null

    function clearTimer() {
        if (timer !== null) {
            cancel(timer)
            timer = null
        }
    }

    function finish() {
        if (state === 'done') {
            return
        }

        state = 'done'
        clearTimer()
        onFinish()
    }

    function beginLeave() {
        if (state !== 'visible' || !ready) {
            return
        }

        const remaining = Math.max(0, minVisibleMs - (now() - shownAt))
        if (remaining > 0) {
            timer = schedule(beginLeave, remaining)

            return
        }

        state = 'leaving'
        onLeave()
        clearTimer()
        timer = schedule(finish, leaveMs)
    }

    return {
        markShown() {
            if (state !== 'idle') {
                return
            }

            state = 'visible'
            shownAt = now()
            beginLeave()
        },
        markReady() {
            if (state === 'done' || state === 'leaving' || ready) {
                return
            }

            ready = true
            beginLeave()
        },
        finish,
        destroy() {
            clearTimer()
            state = 'done'
        },
        get state() {
            return state
        },
    }
}

/**
 * Resolve when the login surface has painted with its logo and fonts settled.
 * Font and asset failures still resolve; an upper bound prevents a permanently
 * blocked intro. Only login-critical resources are awaited.
 */
export function waitForAuthSurfaceReady({
    win = typeof window === 'undefined' ? null : window,
    doc = typeof document === 'undefined' ? null : document,
    schedule = defaultSchedule,
    cancel = defaultCancel,
    fallbackMs = AUTH_INTRO_READY_FALLBACK_MS,
    logoSource = ANIMATED_LOGO_SRC,
    signal = null,
} = {}) {
    if (!win || !doc) {
        return Promise.resolve({ reason: 'ssr', assetFailed: false })
    }

    return new Promise((resolve) => {
        let settled = false
        let painted = false
        let fontsSettled = false
        let logoFailed = false
        let logoSettled = false
        const timers = []
        const frames = []

        function done(reason) {
            if (settled) {
                return
            }

            settled = true
            timers.forEach(cancel)
            frames.forEach((id) => (win.cancelAnimationFrame ?? cancel)(id))
            signal?.removeEventListener('abort', onAbort)
            resolve({ reason, assetFailed: logoFailed })
        }

        function onAbort() {
            done('aborted')
        }

        function maybeDone() {
            if (!painted || !fontsSettled || !logoSettled) {
                return
            }

            if (!doc.querySelector('#pc-auth-main form')) {
                return
            }

            done(logoFailed ? 'asset-fallback' : 'ready')
        }

        if (signal?.aborted) {
            done('aborted')

            return
        }

        signal?.addEventListener('abort', onAbort, { once: true })
        timers.push(schedule(() => done('timeout'), fallbackMs))

        const image = new win.Image()
        function settleImage(failed) {
            if (logoSettled) {
                return
            }

            logoFailed = failed
            logoSettled = true
            maybeDone()
        }
        image.onload = () => settleImage(false)
        image.onerror = () => settleImage(true)
        image.src = logoSource
        if (image.complete) {
            settleImage(Boolean(image.naturalWidth === 0))
        }

        const fonts = doc.fonts?.ready
        if (fonts && typeof fonts.then === 'function') {
            fonts.then(
                () => { fontsSettled = true; maybeDone() },
                () => { fontsSettled = true; maybeDone() },
            )
        } else {
            fontsSettled = true
        }

        const requestFrame = win.requestAnimationFrame
        if (typeof requestFrame === 'function') {
            frames.push(requestFrame.call(win, () => {
                frames.push(requestFrame.call(win, () => {
                    painted = true
                    maybeDone()
                }))
            }))
        } else {
            painted = true
        }

        maybeDone()
    })
}
