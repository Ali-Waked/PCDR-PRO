import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'
import { inflateSync } from 'node:zlib'

const FOOTER = 'footer.foot'
const MIN_TEXT_SIZE = 13
const LARGE_TEXT_SIZE = 24
const LARGE_BOLD_TEXT_SIZE = 18.66
const UI_MIN_RATIO = 3

const viewports = [
    { width: 390, height: 844 },
    { width: 1440, height: 900 },
]
const locales = ['ar', 'en']

function parseColor(value) {
    if (!value || value === 'transparent') return [0, 0, 0, 0]

    const hex = value.trim().match(/^#([0-9a-f]+)$/i)
    if (hex) {
        const digits = hex[1].length === 3
            ? hex[1].split('').map((character) => character + character).join('')
            : hex[1]
        return [
            Number.parseInt(digits.slice(0, 2), 16),
            Number.parseInt(digits.slice(2, 4), 16),
            Number.parseInt(digits.slice(4, 6), 16),
            digits.length >= 8 ? Number.parseInt(digits.slice(6, 8), 16) / 255 : 1,
        ]
    }

    const channels = value.match(/[\d.]+/g)?.map(Number) ?? []
    if (channels.length < 3) return [0, 0, 0, 0]

    return [channels[0], channels[1], channels[2], channels.length > 3 ? channels[3] : 1]
}

function blend(foreground, background) {
    const alpha = foreground[3]

    return [0, 1, 2].map((index) => Math.round(foreground[index] * alpha + background[index] * (1 - alpha)))
}

function luminance(color) {
    const channels = color.slice(0, 3).map((channel) => {
        const normalized = channel / 255
        return normalized <= 0.03928 ? normalized / 12.92 : ((normalized + 0.055) / 1.055) ** 2.4
    })

    return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2]
}

function contrastRatio(foreground, background) {
    const first = luminance(foreground)
    const second = luminance(background)

    return (Math.max(first, second) + 0.05) / (Math.min(first, second) + 0.05)
}

function decodePng(buffer) {
    const signature = Buffer.from([137, 80, 78, 71, 13, 10, 26, 10])
    if (!buffer.subarray(0, 8).equals(signature)) throw new Error('Expected a PNG screenshot')

    let width = 0
    let height = 0
    let bitDepth = 0
    let colorType = 0
    const idat = []

    for (let offset = 8; offset + 12 <= buffer.length;) {
        const length = buffer.readUInt32BE(offset)
        const type = buffer.toString('ascii', offset + 4, offset + 8)
        const data = buffer.subarray(offset + 8, offset + 8 + length)

        if (type === 'IHDR') {
            width = data.readUInt32BE(0)
            height = data.readUInt32BE(4)
            bitDepth = data[8]
            colorType = data[9]
        } else if (type === 'IDAT') {
            idat.push(data)
        } else if (type === 'IEND') {
            break
        }

        offset += length + 12
    }

    const channels = colorType === 6 ? 4 : colorType === 2 ? 3 : 0
    if (bitDepth !== 8 || channels === 0) throw new Error(`Unsupported PNG format: ${colorType}/${bitDepth}`)

    const raw = inflateSync(Buffer.concat(idat))
    const stride = width * channels
    const pixels = Buffer.alloc(height * stride)
    let cursor = 0

    for (let y = 0; y < height; y += 1) {
        const filter = raw[cursor]
        cursor += 1
        const line = raw.subarray(cursor, cursor + stride)
        cursor += stride

        for (let x = 0; x < stride; x += 1) {
            const left = x >= channels ? pixels[y * stride + x - channels] : 0
            const above = y > 0 ? pixels[(y - 1) * stride + x] : 0
            const upperLeft = x >= channels && y > 0 ? pixels[(y - 1) * stride + x - channels] : 0
            let value = line[x]

            if (filter === 1) {
                value += left
            } else if (filter === 2) {
                value += above
            } else if (filter === 3) {
                value += Math.floor((left + above) / 2)
            } else if (filter === 4) {
                const prediction = left + above - upperLeft
                const leftDistance = Math.abs(prediction - left)
                const aboveDistance = Math.abs(prediction - above)
                const upperLeftDistance = Math.abs(prediction - upperLeft)
                const predictor = leftDistance <= aboveDistance && leftDistance <= upperLeftDistance
                    ? left
                    : aboveDistance <= upperLeftDistance ? above : upperLeft
                value += predictor
            }

            pixels[y * stride + x] = value & 0xff
        }
    }

    return {
        width,
        height,
        at(x, y) {
            const safeX = Math.min(Math.max(Math.round(x), 0), width - 1)
            const safeY = Math.min(Math.max(Math.round(y), 0), height - 1)
            const index = (safeY * width + safeX) * channels

            return [pixels[index], pixels[index + 1], pixels[index + 2]]
        },
    }
}

async function footerTextAudit(page) {
    return page.locator(FOOTER).evaluate((footer) => {
        const parse = (value) => {
            const hex = value.trim().match(/^#([0-9a-f]+)$/i)
            if (hex) {
                const digits = hex[1].length === 3
                    ? hex[1].split('').map((character) => character + character).join('')
                    : hex[1]
                return [
                    Number.parseInt(digits.slice(0, 2), 16),
                    Number.parseInt(digits.slice(2, 4), 16),
                    Number.parseInt(digits.slice(4, 6), 16),
                    digits.length >= 8 ? Number.parseInt(digits.slice(6, 8), 16) / 255 : 1,
                ]
            }

            const channels = value.match(/[\d.]+/g)?.map(Number) ?? []
            if (channels.length < 3) return [0, 0, 0, 0]

            return [channels[0], channels[1], channels[2], channels.length > 3 ? channels[3] : 1]
        }
        const composite = (foreground, background) => foreground.slice(0, 3).map(
            (channel, index) => channel * foreground[3] + background[index] * (1 - foreground[3]),
        )
        const rgb = (color) => `rgb(${color.map((channel) => Math.round(channel)).join(', ')})`

        const rows = []
        const walker = document.createTreeWalker(footer, NodeFilter.SHOW_TEXT)
        let node = walker.nextNode()

        while (node) {
            const text = node.textContent.replace(/\s+/g, ' ').trim()
            const element = node.parentElement
            if (!text || !element || element.closest('script, style')) {
                node = walker.nextNode()
                continue
            }

            const style = getComputedStyle(element)
            const rect = element.getBoundingClientRect()
            if (style.visibility === 'hidden' || style.display === 'none' || rect.width === 0 || rect.height === 0) {
                node = walker.nextNode()
                continue
            }

            const layers = []
            let hasImage = false
            for (let current = element; current; current = current.parentElement) {
                const currentStyle = getComputedStyle(current)
                if (currentStyle.backgroundImage !== 'none') hasImage = true

                const color = parse(currentStyle.backgroundColor)
                if (color[3] > 0) layers.push(color)
                if (current === footer) break
            }

            let background = [255, 255, 255]
            for (let index = layers.length - 1; index >= 0; index -= 1) {
                background = composite(layers[index], background)
            }

            const foreground = composite(parse(style.color), background)
            const fontSize = Number.parseFloat(style.fontSize)
            const fontWeight = Number.parseInt(style.fontWeight, 10)
                || (style.fontWeight === 'bold' ? 700 : 400)

            rows.push({
                text: text.slice(0, 80),
                element: `${element.tagName.toLowerCase()}${element.className ? `.${String(element.className).trim().split(/\s+/).join('.')}` : ''}`,
                fontSize,
                fontWeight,
                foreground: foreground.map((channel) => Math.round(channel)),
                background: rgb(background),
                backgroundIsImage: hasImage,
                samplePoints: [
                    { x: rect.left + 3, y: rect.top + 3 },
                    { x: rect.right - 3, y: rect.top + 3 },
                    { x: rect.left + rect.width / 2, y: rect.top + 3 },
                    { x: rect.left + 3, y: rect.bottom - 3 },
                    { x: rect.right - 3, y: rect.bottom - 3 },
                ],
            })

            node = walker.nextNode()
        }

        return rows
    })
}

async function checkFocusRing(page, selector) {
    const target = page.locator(`${FOOTER} ${selector}`).first()
    await expect(target).toHaveCount(1)
    await target.focus()
    await page.keyboard.press('Tab')
    await page.keyboard.press('Shift+Tab')
    await expect.poll(() => target.evaluate((element) => element.matches(':focus-visible'))).toBe(true)

    const outline = await target.evaluate((element) => {
        const style = getComputedStyle(element)

        return {
            color: style.outlineColor,
            style: style.outlineStyle,
            width: style.outlineWidth,
            offset: style.outlineOffset,
        }
    })

    expect(outline.width).toBe('3px')
    expect(outline.offset).toBe('3px')
    expect(outline.style).not.toBe('none')
    expect(outline.color).toBe('rgb(113, 188, 183)')
}

for (const viewport of viewports) {
    for (const locale of locales) {
        test.describe(`footer colors ${locale} ${viewport.width}x${viewport.height}`, () => {
            test.use({ colorScheme: 'light', viewport })

            test('text, icons, focus rings and the floating button clearance meet the on-dark spec', async ({ page }) => {
                await page.goto(`/${locale}`, { waitUntil: 'domcontentloaded', timeout: 60_000 })
                await expect(page.locator(FOOTER)).toBeAttached()
                await page.evaluate(() => document.fonts.ready)
                await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight))

                const screenshot = decodePng(await page.screenshot({ animations: 'disabled' }))
                const scaleX = screenshot.width / await page.evaluate(() => window.innerWidth)

                const rows = await footerTextAudit(page)
                expect(rows.length).toBeGreaterThan(0)

                const failures = []
                for (const row of rows) {
                    const label = `${locale} ${viewport.width} ${row.element} "${row.text}"`
                    expect(row.fontSize, `${label} font size`).toBeGreaterThanOrEqual(MIN_TEXT_SIZE)

                    let background = parseColor(row.background)
                    if (row.backgroundIsImage) {
                        const samples = row.samplePoints.map((point) => screenshot.at(point.x * scaleX, point.y * scaleX))
                        samples.sort((first, second) => luminance(first) - luminance(second))
                        background = samples[0]
                    }

                    const rgb = contrastRatio(row.foreground, background)
                    const minimum = row.fontSize >= LARGE_TEXT_SIZE
                        || (row.fontSize >= LARGE_BOLD_TEXT_SIZE && row.fontWeight >= 700)
                        ? 3
                        : 4.5

                    if (rgb < minimum) {
                        failures.push(`${label}: ${rgb.toFixed(2)}:1 (need ${minimum}:1)`)
                    }
                }
                expect(failures, `footer text contrast failures:\n${failures.join('\n')}`).toEqual([])

                const social = await page.locator(`${FOOTER} .social a, ${FOOTER} .social span`).evaluateAll((elements) => {
                    const footerStyle = getComputedStyle(document.querySelector('footer.foot'))

                    return elements.map((element) => {
                        const style = getComputedStyle(element)

                        return {
                            color: style.color,
                            borderColor: style.borderTopColor,
                            footerBackground: footerStyle.backgroundColor,
                        }
                    })
                })
                expect(social.length).toBeGreaterThan(0)

                for (const icon of social) {
                    const background = parseColor(icon.footerBackground)
                    const iconColor = blend(parseColor(icon.color), background)
                    const borderColor = blend(parseColor(icon.borderColor), background)

                    expect(contrastRatio(iconColor, background), `${locale} social icon color`).toBeGreaterThanOrEqual(UI_MIN_RATIO)
                    expect(contrastRatio(borderColor, background), `${locale} social icon border`).toBeGreaterThanOrEqual(UI_MIN_RATIO)
                }

                await checkFocusRing(page, '.foot .link')
                await checkFocusRing(page, '.social a')

                const overlap = await page.evaluate(() => {
                    const base = document.querySelector('footer.foot .foot-base')?.getBoundingClientRect()
                    const button = document.querySelector('.prototype-footer-wrap .fab')?.getBoundingClientRect()
                    if (!base || !button) return true

                    return !(base.right <= button.left || button.right <= base.left
                        || base.bottom <= button.top || button.bottom <= base.top)
                })
                expect(overlap, `${locale} ${viewport.width} floating complaint button covers the footer last row`).toBe(false)

                const axe = await new AxeBuilder({ page })
                    .include(FOOTER)
                    .withRules(['color-contrast'])
                    .analyze()
                expect(axe.violations, `axe color-contrast violations: ${JSON.stringify(axe.violations, null, 2)}`).toEqual([])
            })
        })
    }
}
