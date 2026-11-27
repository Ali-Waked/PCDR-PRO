import { createApp, h } from 'vue'
import { createInertiaApp, router } from '@inertiajs/vue3'
import { applyThemePreference, readStoredPreference } from './composables/useTheme'
import { legacyRedirectUrl } from './Support/legacyPageRedirect'
import '../css/app.css'
import '../css/dashboard.css'
import '../css/selection.css'
import '../css/phase5.css'

const reducedMotion = typeof window !== 'undefined'
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches

if (typeof document !== 'undefined') {
    document.documentElement.classList.add('js-motion')
}

function syncDocument(props) {
    const locale = props?.locale ?? document.documentElement.lang ?? 'ar'
    const direction = props?.direction ?? (locale === 'ar' ? 'rtl' : 'ltr')
    const isAuthenticated = Boolean(props?.auth?.user)

    document.documentElement.lang = locale
    document.documentElement.dir = direction
    document.documentElement.dataset.locale = locale

    // Authenticated preferences come from the server; guests keep the local
    // choice already applied by the synchronous boot script.
    const preference = isAuthenticated ? props?.theme : readStoredPreference()
    applyThemePreference(preference, { persist: false, transition: false })
}

let revealObserver = null

function observer() {
    if (revealObserver || reducedMotion || ! ('IntersectionObserver' in window)) {
        return revealObserver
    }

    revealObserver = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (! entry.isIntersecting) {
                continue
            }

            entry.target.classList.add('is-visible')
            revealObserver.unobserve(entry.target)
        }
    }, { rootMargin: '0px 0px -6% 0px', threshold: 0.04 })

    return revealObserver
}

const revealDirective = {
    mounted(el) {
        el.classList.add('reveal')

        if (reducedMotion || ! ('IntersectionObserver' in window)) {
            el.classList.add('is-visible')
            return
        }

        observer()?.observe(el)
    },
    unmounted(el) {
        revealObserver?.unobserve(el)
    },
}

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })
        return pages[`./Pages/${name}.vue`]
    },
    setup({ el, App, props, plugin }) {
        syncDocument(props.initialProps)

        router.on('navigate', (event) => {
            syncDocument(event.detail.page.props)
        })

        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .directive('reveal', revealDirective)
            .mount(el)

        // A direct visit to an old aggregate-page hash lands on its own page.
        // The query string is preserved and the replacement has no fragment.
        const legacyTarget = legacyRedirectUrl(
            window.location.href,
            props.initialProps?.locale,
        )

        if (legacyTarget) {
            router.visit(legacyTarget, { replace: true })
        }

        return app
    },
})
