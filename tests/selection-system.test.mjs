import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'
import { fileURLToPath } from 'node:url'

import { STATUS_TONES, statusTone, withStatusTone } from '../resources/js/Support/selectionStatuses.js'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const read = (relativePath) => fs.readFileSync(path.join(root, relativePath), 'utf8')

function walk(directory, extension, files = []) {
    for (const entry of fs.readdirSync(path.join(root, directory), { withFileTypes: true })) {
        const relativePath = path.join(directory, entry.name)
        if (entry.isDirectory()) {
            walk(relativePath, extension, files)
        } else if (entry.name.endsWith(extension)) {
            files.push(relativePath)
        }
    }

    return files
}

test('statusTone maps real backend values without changing stored values', () => {
    const expectations = {
        draft: 'slate',
        pending_review: 'amber',
        approved: 'teal',
        published: 'green',
        new: 'teal',
        in_progress: 'amber',
        resolved: 'green',
        closed: 'slate',
        submitted: 'teal',
        under_verification: 'amber',
        awarded: 'green',
        rejected: 'red',
        failed: 'red',
        pending: 'amber',
        processing: 'teal',
        verified: 'green',
        pruned: 'slate',
        critical: 'red',
        warning: 'amber',
        info: 'teal',
        some_unmapped_value: 'slate',
    }

    for (const [value, tone] of Object.entries(expectations)) {
        assert.equal(statusTone(value), tone, `${value} should use the ${tone} tone`)
    }

    assert.equal(statusTone(' Pending-Review '), 'amber')
    assert.equal(statusTone(null), 'slate')
    assert.equal(statusTone('some_unmapped_value', 'red'), 'red')
})

test('central status vocabulary has no duplicate values across tones', () => {
    const values = Object.values(STATUS_TONES).flat()
    assert.equal(new Set(values).size, values.length)

    for (const tone of Object.keys(STATUS_TONES)) {
        assert.ok(['green', 'teal', 'amber', 'red', 'slate'].includes(tone))
    }
})

test('withStatusTone preserves options and only adds semantic tone metadata', () => {
    const options = [
        { value: 'published', label: 'Published' },
        { value: 'draft', label: 'Draft', tone: 'slate' },
        'archived',
    ]
    const original = structuredClone(options)
    const result = withStatusTone(options)

    assert.deepEqual(options, original, 'input options must not be mutated')
    assert.equal(result[0].value, 'published')
    assert.equal(result[0].label, 'Published')
    assert.equal(result[0].tone, 'green')
    assert.equal(result[1].tone, 'slate')
    assert.equal(result[2].value, 'archived')
    assert.equal(result[2].label, 'archived')
    assert.equal(result[2].tone, 'slate')
})

test('one selection primitive implements the complete interaction contract', () => {
    const source = read('resources/js/Components/Selection/SelectionControl.vue')

    for (const marker of [
        "from '@floating-ui/dom'",
        'Teleport',
        'role="combobox"',
        'role="listbox"',
        'role="option"',
        'aria-expanded',
        'aria-controls',
        'aria-activedescendant',
        'aria-multiselectable',
        'aria-disabled',
        'aria-invalid',
        'addEventListener(\'pointerdown\'',
        "key === 'Escape'",
        "key === 'ArrowDown'",
        "key === 'ArrowUp'",
        "key === 'Home'",
        "key === 'End'",
        "key === 'Enter'",
        "key === 'Tab'",
        'nextTick(focusTrigger)',
        "direction.value === 'rtl' ? 'bottom-end' : 'bottom-start'",
        'loading && !filteredOptions.length',
        '!filteredOptions.length',
        'props.error ? errorId : undefined',
        'props.multiple ? [] : props.emptyValue',
    ]) {
        assert.ok(source.includes(marker), `SelectionControl is missing: ${marker}`)
    }
})

test('all public selection components delegate to the shared primitive', () => {
    for (const component of [
        'resources/js/Components/Dashboard/PcSelect.vue',
        'resources/js/Components/Selection/StatusSelect.vue',
        'resources/js/Components/Selection/MultiSelect.vue',
        'resources/js/Components/Ui/SelectField.vue',
    ]) {
        const source = read(component)
        assert.match(source, /<SelectionControl\b/, `${component} must wrap SelectionControl`)
        assert.doesNotMatch(source, /role="listbox"/, `${component} must not duplicate listbox markup`)
    }

    const listboxes = walk('resources/js', '.vue')
        .filter((file) => read(file).includes('role="listbox"'))
    assert.deepEqual(listboxes, ['resources/js/Components/Selection/SelectionControl.vue'])
})

test('no Vue template renders a native select element', () => {
    const offenders = walk('resources', '.vue').filter((file) => /<select\b/.test(read(file)))
    assert.deepEqual(offenders, [])
})

test('array-valued selections use MultiSelect while boolean toggles stay native', () => {
    const multiSelectPages = [
        'resources/js/Pages/Phase5/Webhooks/Index.vue',
        'resources/js/Pages/Admin/Access/Index.vue',
        'resources/js/Pages/Admin/Roles/Edit.vue',
        'resources/js/Pages/Admin/Users/Create.vue',
        'resources/js/Pages/Admin/Users/Edit.vue',
        'resources/js/Pages/Cms/Content/Edit.vue',
    ]

    for (const page of multiSelectPages) {
        assert.match(read(page), /<MultiSelect\b/, `${page} should use MultiSelect`)
    }

    for (const page of multiSelectPages.filter((item) => !item.endsWith('Cms/Content/Edit.vue'))) {
        assert.doesNotMatch(read(page), /type="checkbox"/, `${page} still contains an array checkbox group`)
    }

    assert.doesNotMatch(
        read('resources/js/Pages/Cms/Content/Edit.vue'),
        /v-model="form\.tags" type="checkbox"/,
        'News tags must use MultiSelect instead of a checkbox array',
    )

    const access = read('resources/js/Pages/Admin/Access/Index.vue')
    for (const marker of [
        'testId: \'permission-option\'',
        'disabled: !item.assignable',
        'selectAllPermissions',
        'clearPermissions',
        'pc-access-summary',
    ]) {
        assert.ok(access.includes(marker), `Access page is missing: ${marker}`)
    }
})

test('status controls and badges share the centralized map', () => {
    const statusSelect = read('resources/js/Components/Selection/StatusSelect.vue')
    const badge = read('resources/js/Components/Dashboard/CmsStatusBadge.vue')
    const auditArchive = read('resources/js/Pages/Admin/AuditArchives/Index.vue')
    const auditLogs = read('resources/js/Pages/Admin/AuditLogs/Index.vue')

    assert.match(statusSelect, /withStatusTone/)
    assert.match(badge, /statusTone/)
    assert.match(auditArchive, /statusTone/)
    assert.match(auditLogs, /statusTone/)
    assert.doesNotMatch(badge, /const toneMap/)
})

test('unified stylesheet exposes palette, RTL, responsive, reduced-motion and dark hooks', () => {
    const css = read('resources/css/selection.css').toLocaleLowerCase()

    for (const marker of [
        '#71bcb7',
        '#397f7b',
        '#eaf5f4',
        '#dac3a3',
        '#d7c5a1',
        '#3e5172',
        '#25334b',
        '#30415e',
        '#e9edf2',
        '#f7f2ea',
        '#ffffff',
        "[dir='rtl']",
        "@media (max-width: 640px)",
        '@media (prefers-reduced-motion: reduce)',
        "[data-theme='dark']",
        'html.dark',
        'prefers-color-scheme: dark',
    ]) {
        assert.ok(css.includes(marker), `selection.css is missing: ${marker}`)
    }

    assert.match(read('resources/js/app.js'), /import '\.\.\/css\/selection\.css'/)
})

test('browser specs target the unified selection markup', () => {
    for (const spec of [
        'tests/e2e/admin-redesign.spec.mjs',
    ]) {
        const source = read(spec)
        assert.doesNotMatch(source, /pc-select-pro/, `${spec} still uses the retired selector classes`)
        assert.doesNotMatch(source, /selectOption\(/, `${spec} still depends on a native <select>`)
    }

    const admin = read('tests/e2e/admin-redesign.spec.mjs')
    assert.match(admin, /pc-selection__trigger\[role="combobox"\]/)
    assert.match(admin, /\[role="listbox"\]/)
    assert.match(admin, /aria-activedescendant/)
})

test('CMS content list and editor reuse the shared design-system primitives', () => {
    const index = read('resources/js/Pages/Cms/Content/Index.vue')
    const edit = read('resources/js/Pages/Cms/Content/Edit.vue')
    const filterBar = read('resources/js/Components/Cms/ContentFilterBar.vue')
    const mediaField = read('resources/js/Components/Cms/ContentMediaField.vue')

    for (const marker of [
        'ContentFilterBar',
        'StatusSelect',
        'DataTable',
        'ContentCollectionCard',
        'pc-content-desktop',
        'pc-content-mobile',
        'confirmDelete',
        'previewPath',
    ]) {
        assert.ok(index.includes(marker), `CMS content index is missing: ${marker}`)
    }

    for (const marker of [
        'ContentFormSection',
        'RichTextEditor',
        'ContentMediaField',
        'MultiSelect',
        'SelectionControl',
        'beforeunload',
        'pc-content-editor__errors',
    ]) {
        assert.ok(edit.includes(marker), `CMS content editor is missing: ${marker}`)
    }

    assert.match(filterBar, /<StatusSelect\b/)
    assert.match(filterBar, /<SelectionControl\b/)
    assert.match(mediaField, /pc-media-cover/)
    assert.doesNotMatch(mediaField, /<img\s+class="pc-media-cover"/)
})

test('CMS content redesign ships RTL-aware responsive styles in both locales', () => {
    const css = read('resources/css/dashboard.css')
    for (const marker of [
        '.pc-content-filters',
        '.pc-content-card',
        '.pc-content-media',
        '.pc-content-desktop',
        '.pc-content-mobile',
        "[dir='rtl'] .pc-content-actions",
        '@media (max-width: 900px)',
        '@media (max-width: 620px)',
    ]) {
        assert.ok(css.includes(marker), `dashboard.css is missing: ${marker}`)
    }

    for (const locale of ['en', 'ar']) {
        const language = read(`lang/${locale}/website.php`)
        for (const key of [
            'cms_content_page',
            'cms_programs_page',
            'cms_projects_page',
            'cms_news_page',
            'cms_unknown_actor',
        ]) {
            assert.ok(language.includes(`'${key}'`), `lang/${locale}/website.php is missing: ${key}`)
        }
    }
})
