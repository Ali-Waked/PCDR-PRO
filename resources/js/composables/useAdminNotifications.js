import { computed, reactive } from 'vue'
import { router } from '@inertiajs/vue3'

const MAX_VISIBLE = 5

const state = reactive({
    items: [],
    sequence: 0,
})

const confirmation = reactive({
    open: false,
    title: '',
    message: '',
    confirmLabel: '',
    cancelLabel: 'Cancel',
    danger: false,
    busy: false,
    pending: null,
})

const labels = reactive({
    notificationsTitle: 'Notifications',
    successTitle: 'Success',
    errorTitle: 'Something went wrong',
    warningTitle: 'Please review',
    infoTitle: 'Information',
    processingTitle: 'Processing',
    dismiss: 'Dismiss notification',
    clearAll: 'Dismiss all notifications',
    validationTitle: 'Please review the highlighted fields',
    validationMessage: 'The form was not saved. Correct the highlighted fields and try again.',
    networkTitle: 'Connection problem',
    networkMessage: 'The request could not be completed. Check your connection and try again.',
    serverTitle: 'Request failed',
    serverMessage: 'The server could not complete the request. No changes were saved.',
    unauthorizedTitle: 'Action not allowed',
    unauthorizedMessage: 'You do not have permission to perform this action.',
    confirmAction: 'Yes, continue',
    confirmDeleteAction: 'Yes, delete',
    confirmLoading: 'Working…',
    actions: {
        created: 'Item created successfully',
        updated: 'Changes saved successfully',
        deleted: 'Item deleted successfully',
        uploaded: 'File uploaded successfully',
        published: 'Content published successfully',
        status_updated: 'Status updated successfully',
        saved: 'Changes saved successfully',
    },
})

const notificationDurations = {
    success: 6000,
    info: 7000,
    warning: 8000,
    error: 9000,
    loading: 0,
}

let eventsInstalled = false
const flashedKeys = new Set()
const visitLoading = new Map()
let confirmationTimer = null
let confirmationVisitId = null

function clearConfirmationTimer() {
    if (confirmationTimer !== null) {
        window.clearTimeout(confirmationTimer)
        confirmationTimer = null
    }
}

function configureTranslations(t) {
    labels.notificationsTitle = t('ui.notification.notifications_title') || labels.notificationsTitle
    labels.successTitle = t('ui.notification.success_title') || labels.successTitle
    labels.errorTitle = t('ui.notification.error_title') || labels.errorTitle
    labels.warningTitle = t('ui.notification.warning_title') || labels.warningTitle
    labels.infoTitle = t('ui.notification.info_title') || labels.infoTitle
    labels.processingTitle = t('ui.notification.processing_title') || labels.processingTitle
    labels.dismiss = t('ui.notification.dismiss') || labels.dismiss
    labels.clearAll = t('ui.notification.clear_all') || labels.clearAll
    labels.validationTitle = t('ui.notification.validation_title') || labels.validationTitle
    labels.validationMessage = t('ui.notification.validation_message') || labels.validationMessage
    labels.networkTitle = t('ui.notification.network_title') || labels.networkTitle
    labels.networkMessage = t('ui.notification.network_message') || labels.networkMessage
    labels.serverTitle = t('ui.notification.server_title') || labels.serverTitle
    labels.serverMessage = t('ui.notification.server_message') || labels.serverMessage
    labels.unauthorizedTitle = t('ui.notification.unauthorized_title') || labels.unauthorizedTitle
    labels.unauthorizedMessage = t('ui.notification.unauthorized_message') || labels.unauthorizedMessage
    labels.confirmAction = t('ui.notification.confirm_action') || labels.confirmAction
    labels.confirmDeleteAction = t('ui.notification.confirm_delete_action') || labels.confirmDeleteAction
    labels.confirmLoading = t('ui.notification.confirm_loading') || labels.confirmLoading
    labels.cancelLabel = t('ui.cancel') || labels.cancelLabel

    ;[
        'confirm_delete_title', 'confirm_delete_message',
        'confirm_archive_title', 'confirm_archive_message',
        'confirm_status_title', 'confirm_status_message',
        'confirm_reverse_title', 'confirm_reverse_message',
    ].forEach((key) => {
        labels[key] = t(`ui.notification.${key}`) || labels[key]
    })

    Object.keys(labels.actions).forEach((key) => {
        labels.actions[key] = t(`ui.notification.${key}`) || labels.actions[key]
    })
}

function titleFor(type) {
    if (type === 'success') return labels.successTitle
    if (type === 'error') return labels.errorTitle
    if (type === 'warning') return labels.warningTitle
    if (type === 'loading') return labels.processingTitle
    return labels.infoTitle
}

function dismiss(id) {
    const index = state.items.findIndex((item) => item.id === id)
    if (index !== -1) {
        state.items.splice(index, 1)
    }
}

function clear() {
    state.items.splice(0, state.items.length)
}

function push({ type = 'info', title = '', message = '', duration, sticky = false, meta = {} }) {
    const item = {
        id: `notification-${++state.sequence}`,
        type,
        title: title || titleFor(type),
        message: String(message ?? ''),
        sticky: sticky || type === 'loading',
        duration: duration ?? notificationDurations[type] ?? 6000,
        meta,
    }

    state.items.push(item)
    while (state.items.length > MAX_VISIBLE) {
        state.items.shift()
    }

    return item.id
}

function update(id, changes = {}) {
    const item = state.items.find((entry) => entry.id === id)
    if (! item) {
        return
    }

    Object.assign(item, changes)
    if (changes.type && ! changes.title) {
        item.title = titleFor(changes.type)
    }
    if (changes.type && changes.duration === undefined) {
        item.duration = notificationDurations[changes.type] ?? 6000
    }
    if (changes.type && changes.type !== 'loading') {
        item.sticky = false
    }
}

const notify = {
    push,
    dismiss,
    update,
    clear,
    success: (message, title) => push({ type: 'success', title, message }),
    error: (message, title) => push({ type: 'error', title, message }),
    warning: (message, title) => push({ type: 'warning', title, message }),
    info: (message, title) => push({ type: 'info', title, message }),
    loading: (message = labels.confirmLoading, title) => push({ type: 'loading', title, message, sticky: true }),
    action: (action, message) => push({ type: 'success', title: labels.successTitle, message: message || labels.actions[action] || labels.actions.updated }),
}

function closeConfirmation(result) {
    clearConfirmationTimer()
    confirmationVisitId = null
    const pending = confirmation.pending
    confirmation.open = false
    confirmation.busy = false
    confirmation.pending = null
    if (typeof pending === 'function') {
        pending(result)
    }
}

function confirm(options = {}) {
    clearConfirmationTimer()
    confirmationVisitId = null
    if (typeof confirmation.pending === 'function') {
        closeConfirmation(false)
    }

    confirmation.title = options.title || labels.warningTitle
    confirmation.message = options.message || ''
    confirmation.confirmLabel = options.confirmLabel || (options.danger ? labels.confirmDeleteAction : labels.confirmAction)
    confirmation.cancelLabel = options.cancelLabel || labels.cancelLabel
    confirmation.danger = Boolean(options.danger)
    confirmation.busy = false
    confirmation.open = true

    return new Promise((resolve) => {
        confirmation.pending = resolve
    })
}

function acceptConfirmation() {
    if (confirmation.busy || ! confirmation.open) {
        return
    }

    confirmation.busy = true
    const pending = confirmation.pending
    confirmation.pending = null
    if (typeof pending === 'function') {
        pending(true)
    }

    // Safety net: never leave the dialog blocking the UI if a caller forgets to finish.
    clearConfirmationTimer()
    confirmationTimer = window.setTimeout(() => {
        if (confirmation.open && confirmation.busy) {
            closeConfirmation(true)
        }
    }, 20000)
}

function finishConfirmation() {
    closeConfirmation(true)
}

function setConfirmationBusy(busy) {
    confirmation.busy = Boolean(busy)
}

function confirmDelete(subject = '', options = {}) {
    return confirm({
        title: options.title || tNotification('confirm_delete_title', labels.errorTitle),
        message: options.message || `${tNotification('confirm_delete_message', '')} ${subject}`.trim(),
        confirmLabel: options.confirmLabel || labels.confirmDeleteAction,
        danger: true,
    })
}

function confirmArchive(subject = '', options = {}) {
    return confirm({
        title: options.title || tNotification('confirm_archive_title', labels.warningTitle),
        message: options.message || `${tNotification('confirm_archive_message', '')} ${subject}`.trim(),
        danger: false,
    })
}

function confirmStatus(subject = '', options = {}) {
    return confirm({
        title: options.title || tNotification('confirm_status_title', labels.warningTitle),
        message: options.message || `${tNotification('confirm_status_message', '')} ${subject}`.trim(),
        danger: false,
    })
}

function confirmReverse(subject = '', options = {}) {
    return confirm({
        title: options.title || tNotification('confirm_reverse_title', labels.warningTitle),
        message: options.message || `${tNotification('confirm_reverse_message', '')} ${subject}`.trim(),
        danger: false,
    })
}

function tNotification(key, fallback) {
    return labels[key] || fallback
}

function readFlash(flash = {}, nonce = 'response') {
    const types = ['success', 'error', 'warning', 'info']
    types.forEach((type) => {
        const message = type === 'info' ? (flash.info || flash.workflow) : flash[type]
        if (! message || typeof message !== 'string') {
            return
        }

        const key = `${nonce}:${type}:${message}`
        if (flashedKeys.has(key)) {
            return
        }
        flashedKeys.add(key)
        if (flashedKeys.size > 100) {
            flashedKeys.delete(flashedKeys.values().next().value)
        }
        notify.push({ type, message })
    })
}

function handleInitialFlash(flash) {
    if (flash && Object.values(flash).some(Boolean)) {
        readFlash(flash, 'initial')
    }
}

function installEvents() {
    if (eventsInstalled) {
        return
    }
    eventsInstalled = true

    router.on('start', (event) => {
        const visit = event.detail?.visit
        if (! visit) {
            return
        }

        if (confirmation.busy && confirmationVisitId === null) {
            confirmationVisitId = visit.id
        }

        if (visit.method === 'get' || visit.prefetch || visitLoading.has(visit.id)) {
            return
        }

        if (confirmation.busy) {
            return
        }
        const id = notify.loading(labels.confirmLoading)
        visitLoading.set(visit.id, id)
    })

    router.on('success', (event) => {
        const visitId = event.detail?.visitId
        const flash = event.detail?.page?.props?.flash

        if (flash && Object.values(flash).some(Boolean)) {
            readFlash(flash, visitId || `visit-${++state.sequence}`)
        }
    })

    router.on('error', (event) => {
        const errors = event.detail?.errors || {}
        const count = Object.keys(errors).length
        if (count > 0 && ! state.items.some((item) => item.type === 'error' && item.meta?.validation)) {
            notify.push({
                type: 'error',
                title: labels.validationTitle,
                message: labels.validationMessage,
                meta: { validation: true },
            })
        }
    })

    router.on('httpException', (event) => {
        const status = event.detail?.response?.status
        if (status === 401 || status === 403) {
            notify.error(labels.unauthorizedMessage, labels.unauthorizedTitle)
        } else if (status >= 500) {
            notify.error(labels.serverMessage, labels.serverTitle)
        }
    })

    router.on('networkError', () => {
        notify.error(labels.networkMessage, labels.networkTitle)
    })

    router.on('finish', (event) => {
        const visitId = event.detail?.visit?.id
        if (! visitId) {
            return
        }
        const id = visitLoading.get(visitId)
        if (id) {
            dismiss(id)
            visitLoading.delete(visitId)
        }

        if (confirmationVisitId === visitId) {
            finishConfirmation()
        }
    })
}

export function useAdminNotifications() {
    const notifications = computed(() => state.items)
    const hasNotifications = computed(() => state.items.length > 0)

    return {
        notifications,
        hasNotifications,
        confirmation,
        labels,
        configureTranslations,
        handleInitialFlash,
        installEvents,
        notify,
        confirm,
        confirmDelete,
        confirmArchive,
        confirmStatus,
        confirmReverse,
        acceptConfirmation,
        finishConfirmation,
        setConfirmationBusy,
        closeConfirmation,
        dismiss,
        clear,
    }
}
