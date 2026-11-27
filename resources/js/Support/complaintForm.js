/**
 * Client-side rules shared by the public complaints form and its tests.
 * The backend remains authoritative for validation; these helpers only keep
 * anonymous submissions free of identity data and enforce the advertised
 * attachment limits before an upload is attempted.
 *
 * Canonical payload keys mirror StoreComplaintRequest. The legacy `description`,
 * `preferred_contact_method` and `preferred_locale` keys are accepted as input
 * aliases so stale callers keep working, but they are never emitted.
 */

export const COMPLAINT_IDENTITY_FIELDS = [
    'name',
    'contact_method',
    'email',
    'phone',
]

function value(form, key, legacyKey = '') {
    const canonical = form?.[key]
    if (canonical !== undefined && canonical !== null && String(canonical) !== '') {
        return canonical
    }

    return legacyKey ? (form?.[legacyKey] ?? '') : (canonical ?? '')
}

/** Anonymous submissions never carry name or contact details. */
export function complaintPayload(form) {
    const anonymous = form?.is_anonymous === true

    return {
        category: value(form, 'category'),
        subject: value(form, 'subject'),
        body: value(form, 'body', 'description'),
        related_project: value(form, 'related_project'),
        location: value(form, 'location'),
        is_anonymous: anonymous,
        name: anonymous ? '' : value(form, 'name'),
        contact_method: anonymous ? '' : value(form, 'contact_method', 'preferred_contact_method'),
        email: anonymous ? '' : value(form, 'email'),
        phone: anonymous ? '' : value(form, 'phone'),
        locale: value(form, 'locale', 'preferred_locale'),
        attachments: Array.isArray(form?.attachments) ? [...form.attachments] : [],
        honeypot: value(form, 'honeypot'),
    }
}

/** Clear identity state when the visitor switches to an anonymous complaint. */
export function clearComplaintIdentity(form) {
    if (!form) {
        return
    }

    for (const field of COMPLAINT_IDENTITY_FIELDS) {
        form[field] = ''
    }

    if (typeof form.clearErrors === 'function') {
        form.clearErrors(COMPLAINT_IDENTITY_FIELDS)
    }
}

function extensionOf(name) {
    const text = String(name || '')
    const dot = text.lastIndexOf('.')
    return dot === -1 ? '' : text.slice(dot + 1).toLowerCase()
}

/**
 * @returns {{files: File[], error: string}} error is one of
 * `too_many`, `too_large`, `extension` or an empty string.
 */
export function pickComplaintAttachments(fileList, limits) {
    const maxFiles = Math.max(0, Number(limits?.maxFiles ?? 0))
    const maxBytes = Math.max(0, Number(limits?.maxFileSizeKb ?? 0) * 1024)
    const allowed = (Array.isArray(limits?.allowedExtensions) ? limits.allowedExtensions : [])
        .map((extension) => String(extension).replace(/^\./, '').toLowerCase())
    const files = Array.from(fileList || []).filter((file) => file && typeof file === 'object')

    if (files.length > maxFiles) {
        return { files: [], error: 'too_many' }
    }

    for (const file of files) {
        if (maxBytes > 0 && Number(file.size || 0) > maxBytes) {
            return { files: [], error: 'too_large' }
        }
        if (allowed.length > 0 && !allowed.includes(extensionOf(file.name))) {
            return { files: [], error: 'extension' }
        }
    }

    return { files, error: '' }
}

export function attachmentAccept(limits) {
    const allowed = (Array.isArray(limits?.allowedExtensions) ? limits.allowedExtensions : [])
        .map((extension) => String(extension).replace(/^\./, '').toLowerCase())
        .filter(Boolean)

    return allowed.length ? allowed.map((extension) => `.${extension}`).join(',') : undefined
}
