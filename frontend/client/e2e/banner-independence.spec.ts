import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=banners-independent')
})

test('banner grid and menu render distinct SSR content without hydration errors', async ({
  page,
  request,
}, testInfo) => {
  const html = await (await request.get('/')).text()
  const main = html.match(/<main[\s\S]*?<\/main>/)?.[0] || ''
  const grid =
    main.match(/<section[^>]*id="catalog"[\s\S]*?<\/section>/)?.[0] || ''
  const menu =
    main.match(/<section[^>]*id="materials"[\s\S]*?<\/section>/)?.[0] || ''
  expect(grid).toContain('Плитка только в сетке')
  expect(grid).not.toContain('Камень только в меню')
  expect(menu).toContain('Камень только в меню')
  expect(menu).not.toContain('Плитка только в сетке')
  const issues: string[] = []
  page.on('console', (message) => {
    if (/hydration/i.test(message.text())) issues.push(message.text())
  })
  page.on('pageerror', (error) => issues.push(error.message))
  await page.goto('/')
  await expect(page.locator('#catalog article.category-card')).toHaveCount(1)
  await expect(page.locator('#catalog button, #catalog a')).toHaveCount(0)
  await expect(page.getByRole('tab')).toHaveText([
    'Камень только в меню',
    'Мозаика только в меню',
  ])
  await expect(page.getByRole('tabpanel')).toContainText(
    'Подробно: Камень только в меню',
  )
  await expect(page.getByRole('tabpanel').getByRole('img')).toHaveAttribute(
    'src',
    '/images/home/category-porcelain.webp',
  )
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    const overflow = await page.evaluate(() =>
      Array.from(document.querySelectorAll('body *'))
        .filter((node) => node.getBoundingClientRect().right > innerWidth)
        .map(
          (node) =>
            `${node.tagName}.${node.className}: ${Math.round(node.getBoundingClientRect().width)}`,
        )
        .slice(0, 15),
    )
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
      JSON.stringify({ width, overflow }),
    ).toBe(true)
    await page.screenshot({
      path: testInfo.outputPath(`banners-${width}.png`),
      fullPage: true,
    })
  }
  const violations = (await new AxeBuilder({ page }).analyze()).violations
  // Owner-approved existing colors stay fixed; track the known eyebrow contrast
  // explicitly while still failing for any additional accessibility regression.
  expect(violations).toHaveLength(1)
  expect(violations[0]?.id).toBe('color-contrast')
  expect(violations[0]?.nodes).toHaveLength(1)
  expect(violations[0]?.nodes[0]?.html).toContain('class="eyebrow"')
  expect(violations[0]?.nodes[0]?.any[0]?.data).toMatchObject({
    fgColor: '#6e6b66',
    bgColor: '#e9e6e0',
    contrastRatio: 4.25,
  })
  expect(issues).toEqual([])
})

test('menu tabs select and wrap with keyboard using only their own items', async ({
  page,
}, testInfo) => {
  await page.goto('/')
  const stone = page.getByRole('tab', { name: 'Камень только в меню' })
  const mosaic = page.getByRole('tab', { name: 'Мозаика только в меню' })
  await mosaic.click()
  await expect(mosaic).toHaveAttribute('aria-selected', 'true')
  await expect(page.getByRole('tabpanel')).toContainText(
    'Подробно: Мозаика только в меню',
  )
  await expect(page.getByRole('tabpanel').getByRole('img')).toHaveCount(0)
  await page.keyboard.press('ArrowRight')
  await expect(stone).toBeFocused()
  await expect(stone).toHaveAttribute('tabindex', '0')
  await page.keyboard.press('ArrowLeft')
  await expect(mosaic).toBeFocused()
  await expect(mosaic).toHaveAttribute('aria-selected', 'true')
  await expect(page.locator('#catalog')).toContainText('Плитка только в сетке')
  await page.setViewportSize({ width: 320, height: 900 })
  await page.screenshot({
    path: testInfo.outputPath('banner-menu-keyboard-empty-image.png'),
    fullPage: true,
  })
})

for (const scenario of [
  'banners-standalone-menu',
  'banners-grid-empty',
  'banners-grid-disabled',
  'banners-grid-changed',
]) {
  test(`menu is independent in ${scenario}`, async ({ page, request }) => {
    await request.get(`http://127.0.0.1:8015/__scenario?name=${scenario}`)
    await page.goto('/')
    await expect(page.getByRole('tab')).toHaveText([
      'Камень только в меню',
      'Мозаика только в меню',
    ])
    await expect(page.getByRole('tabpanel')).toContainText(
      'Подробно: Камень только в меню',
    )
    if (scenario !== 'banners-grid-changed')
      await expect(page.locator('#catalog')).toHaveCount(0)
    else
      await expect(page.locator('#catalog')).toContainText(
        'Изменённый баннер сетки',
      )
  })
}

test('empty menu stays hidden without borrowing grid items', async ({
  page,
  request,
}) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=banners-menu-empty')
  await page.goto('/')
  await expect(page.locator('#catalog article.category-card')).toHaveCount(1)
  await expect(page.locator('#materials')).toHaveCount(0)
})

test('disabled menu does not change grid content or make cards interactive', async ({
  page,
  request,
}) => {
  await request.get(
    'http://127.0.0.1:8015/__scenario?name=banners-menu-disabled',
  )
  await page.goto('/')
  await expect(page.locator('#catalog article.category-card')).toHaveCount(1)
  await expect(page.locator('#catalog')).toContainText('Плитка только в сетке')
  await expect(
    page.locator('#catalog button, #catalog a, #materials'),
  ).toHaveCount(0)
})

test('twelve long menu labels stay reachable without page overflow', async ({
  page,
  request,
}, testInfo) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=banners-menu-long')
  await page.goto('/')
  await expect(page.getByRole('tab')).toHaveCount(12)
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    const overflow = await page.evaluate(() =>
      Array.from(document.querySelectorAll('body *'))
        .filter((node) => node.getBoundingClientRect().right > innerWidth)
        .map(
          (node) =>
            `${node.tagName}.${node.className}: ${Math.round(node.getBoundingClientRect().width)}`,
        )
        .slice(0, 15),
    )
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
      JSON.stringify({ width, overflow }),
    ).toBe(true)
    await page.getByRole('tab').first().focus()
    await page.keyboard.press('ArrowLeft')
    await expect(page.getByRole('tab').last()).toBeFocused()
    await expect(page.getByRole('tab').last()).toBeInViewport()
    await expect(page.getByRole('tabpanel')).toContainText(
      'Подробно: Очень длинное русское название баннера 12',
    )
    expect(
      await page
        .getByRole('tab')
        .last()
        .evaluate((tab) => {
          const list = tab.parentElement!.getBoundingClientRect()
          const bounds = tab.getBoundingClientRect()
          const style = getComputedStyle(tab)
          const outline =
            parseFloat(style.outlineWidth) + parseFloat(style.outlineOffset)
          return (
            bounds.top - outline >= list.top &&
            bounds.bottom + outline <= list.bottom
          )
        }),
      'Keyboard focus outline must fit inside the scrolling tab list',
    ).toBe(true)
    if (width === 320 || width === 1280) {
      await page
        .getByRole('tablist')
        .evaluate((list) => list.scrollIntoView({ block: 'center' }))
      await page.screenshot({
        path: testInfo.outputPath(`focused-menu-last-${width}.png`),
      })
      await page.keyboard.press('ArrowRight')
      await expect(page.getByRole('tab').first()).toBeFocused()
      await page.screenshot({
        path: testInfo.outputPath(`focused-menu-first-${width}.png`),
      })
    }
  }
})

test('real saved preview keeps independent lists and resets selection when its own items change', async ({
  page,
  context,
  request,
}, testInfo) => {
  await context.addCookies([
    {
      name: 'preview_test_session',
      value: 'employee',
      url: 'http://127.0.0.1:8015',
      sameSite: 'Lax',
    },
  ])
  await page.clock.install()
  await page.goto('/preview/home')
  await expect(page.locator('#catalog article.category-card')).toHaveCount(1)
  await page.getByRole('tab', { name: 'Мозаика только в меню' }).click()
  await expect(page.getByRole('tabpanel')).toContainText(
    'Подробно: Мозаика только в меню',
  )
  await request.get(
    'http://127.0.0.1:8015/__scenario?name=banners-grid-changed',
  )
  await page.clock.fastForward(15_001)
  await expect(page.locator('#catalog')).toContainText(
    'Изменённый баннер сетки',
  )
  await expect(
    page.getByRole('tab', { name: 'Мозаика только в меню' }),
  ).toHaveAttribute('aria-selected', 'true')
  await request.get(
    'http://127.0.0.1:8015/__scenario?name=banners-menu-changed',
  )
  await page.clock.fastForward(15_001)
  await expect(
    page.getByRole('tab', { name: 'Новый баннер меню' }),
  ).toHaveAttribute('aria-selected', 'true')
  await expect(page.getByRole('tab')).toHaveCount(1)
  await expect(page.getByRole('tabpanel')).toContainText(
    'Подробно: Новый баннер меню',
  )
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    const overflow = await page.evaluate(() =>
      Array.from(document.querySelectorAll('body *'))
        .filter((node) => node.getBoundingClientRect().right > innerWidth)
        .map(
          (node) =>
            `${node.tagName}.${node.className}: ${Math.round(node.getBoundingClientRect().width)}`,
        )
        .slice(0, 15),
    )
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
      JSON.stringify({ width, overflow }),
    ).toBe(true)
    await page.screenshot({
      path: testInfo.outputPath(`preview-banner-menu-${width}.png`),
      fullPage: true,
    })
  }
})
