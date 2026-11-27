/**
 * Shared complaint status and priority labels for the CMS screens.
 * Values mirror config/complaints.php; missing codes fall back to a readable
 * form instead of showing a raw machine value.
 */

export const COMPLAINT_STATUSES = [
    'new',
    'under_review',
    'in_progress',
    'waiting_information',
    'resolved',
    'closed',
    'rejected',
]

export const COMPLAINT_PRIORITIES = ['low', 'normal', 'high', 'urgent']

const STATUS_TONES = {
    new: 'teal',
    under_review: 'amber',
    in_progress: 'amber',
    waiting_information: 'amber',
    resolved: 'green',
    closed: 'slate',
    rejected: 'red',
}

const PRIORITY_TONES = {
    low: 'green',
    normal: 'teal',
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
