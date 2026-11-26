/**
 * Shared complaint status and priority labels for the CMS screens.
 * Values mirror the backend complaint workflow codes; missing codes fall back
 * to a readable form instead of showing a raw machine value.
 */

export const COMPLAINT_STATUSES = [
    'new',
    'received',
    'acknowledged',
    'under_triage',
    'assigned',
    'under_review',
    'waiting_information',
    'referred',
    'in_progress',
    'responded',
    'resolved',
    'closed',
    'reopened',
]

export const COMPLAINT_PRIORITIES = ['low', 'normal', 'high', 'urgent']

const STATUS_TONES = {
    new: 'teal',
    received: 'teal',
    acknowledged: 'teal',
    under_triage: 'amber',
    assigned: 'amber',
    under_review: 'amber',
    waiting_information: 'amber',
    referred: 'amber',
    in_progress: 'amber',
    responded: 'green',
    resolved: 'green',
    closed: 'slate',
    reopened: 'red',
}

const PRIORITY_TONES = {
    low: 'green',
    normal: 'teal',
    medium: 'teal',
    high: 'amber',
    urgent: 'red',
}

function fallbackLabel(value) {
    return String(value || '').replaceAll('_', ' ') || '—'
}

export function complaintStatusLabel(t, status) {
    const key = `ui.complaints.statuses.${status}`
    const label = t(key)
    return label === key || label === undefined || label === null || label === ''
        ? fallbackLabel(status)
        : String(label)
}

export function complaintPriorityLabel(t, priority) {
    const key = `ui.complaints.priorities.${priority}`
    const label = t(key)
    return label === key || label === undefined || label === null || label === ''
        ? fallbackLabel(priority)
        : String(label)
}

export function complaintStatusTone(status) {
    return STATUS_TONES[status] || 'slate'
}

export function complaintPriorityTone(priority) {
    return PRIORITY_TONES[priority] || 'slate'
}
