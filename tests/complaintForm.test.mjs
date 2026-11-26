import assert from 'node:assert/strict'
import test from 'node:test'

import {
    attachmentAccept,
    clearComplaintIdentity,
    complaintPayload,
    pickComplaintAttachments,
} from '../resources/js/Support/complaintForm.js'

const limits = {
    maxFiles: 2,
    maxFileSizeKb: 10,
    allowedExtensions: ['pdf', 'jpg'],
}

function file(name, size) {
    return { name, size }
}

test('complaintPayload strips identity from anonymous submissions', () => {
    const payload = complaintPayload({
        category: 'other',
        subject: 'Subject',
        description: 'Description',
        related_project: '',
        location: 'Gaza',
        is_anonymous: true,
        name: 'A Person',
        preferred_contact_method: 'email',
        email: 'person@example.org',
        phone: '+970500000000',
        preferred_locale: 'ar',
        attachments: [file('proof.pdf', 100)],
    })

    assert.equal(payload.is_anonymous, true)
    assert.equal(payload.name, '')
    assert.equal(payload.email, '')
    assert.equal(payload.phone, '')
    assert.equal(payload.preferred_contact_method, '')
    assert.equal(payload.preferred_locale, 'ar')
    assert.deepEqual(payload.attachments.map((item) => item.name), ['proof.pdf'])
})

test('complaintPayload keeps contact details for identified submissions', () => {
    const payload = complaintPayload({
        category: 'service_delivery',
        subject: 'Subject',
        description: 'Description',
        related_project: '',
        location: '',
        is_anonymous: false,
        name: 'A Person',
        preferred_contact_method: 'email',
        email: 'person@example.org',
        phone: '',
        preferred_locale: 'en',
        attachments: [],
    })

    assert.equal(payload.name, 'A Person')
    assert.equal(payload.email, 'person@example.org')
    assert.equal(payload.preferred_contact_method, 'email')
})

test('clearComplaintIdentity clears identity fields and errors', () => {
    const clearedErrors = []
    const form = {
        name: 'A Person',
        preferred_contact_method: 'email',
        email: 'person@example.org',
        phone: '+970500000000',
        clearErrors: (fields) => clearedErrors.push(fields),
    }

    clearComplaintIdentity(form)

    assert.equal(form.name, '')
    assert.equal(form.email, '')
    assert.equal(form.phone, '')
    assert.equal(form.preferred_contact_method, '')
    assert.deepEqual(clearedErrors, [['name', 'preferred_contact_method', 'email', 'phone']])
})

test('pickComplaintAttachments enforces count, size and extension limits', () => {
    assert.deepEqual(
        pickComplaintAttachments([file('a.pdf', 1), file('b.jpg', 2), file('c.pdf', 3)], limits),
        { files: [], error: 'too_many' },
    )
    assert.deepEqual(
        pickComplaintAttachments([file('a.pdf', 10 * 1024 + 1)], limits),
        { files: [], error: 'too_large' },
    )
    assert.deepEqual(
        pickComplaintAttachments([file('a.exe', 1)], limits),
        { files: [], error: 'extension' },
    )

    const accepted = pickComplaintAttachments([file('a.PDF', 1024), file('b.jpg', 2048)], limits)
    assert.equal(accepted.error, '')
    assert.deepEqual(accepted.files.map((item) => item.name), ['a.PDF', 'b.jpg'])
})

test('attachmentAccept builds the accept attribute from allowed extensions', () => {
    assert.equal(attachmentAccept(limits), '.pdf,.jpg')
    assert.equal(attachmentAccept({ allowedExtensions: [] }), undefined)
})
