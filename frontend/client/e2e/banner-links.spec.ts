import { expect, test } from '@playwright/test'

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=banners-grid-links')
})

for (const motion of ['no-preference', 'reduce'] as const) {
  test(`grid banner restores the Смотреть badge on hover and keyboard focus (${motion})`, async ({
    page,
  }, testInfo) => {
    await page.emulateMedia({ reducedMotion: motion })
    await page.goto('/')
    const banner = page
      .locator('#catalog')
      .getByRole('link', { name: 'Баннер каталога', exact: true })
    const badge = banner.locator('.category-card__image-label')
    await expect(badge).toHaveText('Смотреть')
    await expect(
      page
        .locator('#catalog article.category-card')
        .getByText('Смотреть', { exact: true }),
    ).toHaveCount(0)
    for (const width of [320, 640, 768, 1024, 1280]) {
      await page.setViewportSize({ width, height: 900 })
      await page.mouse.move(0, 0)
      await banner.evaluate((element) => (element as HTMLElement).blur())
      await expect(badge).toHaveCSS('opacity', '0')
      await banner.hover()
      await expect(badge).toHaveCSS('opacity', '1')
      await page.screenshot({
        path: testInfo.outputPath(`watch-badge-hover-${motion}-${width}.png`),
      })
      await page.mouse.move(0, 0)
      await page.keyboard.press('Tab')
      await banner.focus()
      await expect(banner).toBeFocused()
      await expect(badge).toHaveCSS('opacity', '1')
      await expect(banner).toHaveAttribute('href', '/catalog?from=banner')
      await expect(page).toHaveURL('http://127.0.0.1:3015/')
    }
    await page.screenshot({
      path: testInfo.outputPath(`watch-badge-focus-${motion}.png`),
    })
  })
}

test('grid banners link to their own configured destinations and preserve hover behavior', async ({
  page,
  request,
}, testInfo) => {
  const html = await (await request.get('/')).text()
  const gridHtml =
    html.match(/<section[^>]*id="catalog"[\s\S]*?<\/section>/)?.[0] || ''
  expect(gridHtml).toContain('href="/catalog?from=banner"')
  expect(gridHtml).toContain('href="https://example.test/banner"')
  const issues: string[] = []
  page.on('pageerror', (error) => issues.push(error.message))
  page.on('console', (message) => {
    if (/hydration/i.test(message.text())) issues.push(message.text())
  })
  await page.goto('/')
  const grid = page.locator('#catalog')
  const internal = grid.getByRole('link', {
    name: 'Баннер каталога',
    exact: true,
  })
  const external = grid.getByRole('link', {
    name: 'Внешний баннер',
    exact: true,
  })
  await expect(internal).toHaveAttribute('href', '/catalog?from=banner')
  await expect(external).toHaveAttribute('href', 'https://example.test/banner')
  await expect(grid.getByRole('link')).toHaveCount(2)
  await expect(grid.locator('article.category-card')).toHaveCount(7)
  await internal.hover()
  await expect(internal.getByRole('img')).toBeVisible()
  await expect
    .poll(() =>
      internal
        .getByRole('img')
        .evaluate(
          (image) => image.getBoundingClientRect().width > image.clientWidth,
        ),
    )
    .toBe(true)
  await expect(page).toHaveURL('http://127.0.0.1:3015/')
  await expect(page.getByRole('tab').first()).toHaveAttribute(
    'aria-selected',
    'true',
  )
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    await internal.focus()
    await expect(internal).toBeFocused()
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: testInfo.outputPath(`grid-link-${width}.png`),
    })
  }
  await internal.press('Enter')
  await expect(page).toHaveURL('http://127.0.0.1:3015/catalog?from=banner')
  expect(issues).toEqual([])
})

test('grid links render in the real saved preview without changing menu selection', async ({
  page,
  context,
}) => {
  await context.addCookies([
    {
      name: 'preview_test_session',
      value: 'employee',
      url: 'http://127.0.0.1:8015',
      sameSite: 'Lax',
    },
  ])
  await page.goto('/preview/home')
  await expect(
    page
      .locator('#catalog')
      .getByRole('link', { name: 'Баннер каталога', exact: true }),
  ).toHaveAttribute('href', '/catalog?from=banner')
  await page.getByRole('tab', { name: 'Мозаика только в меню' }).click()
  await page
    .locator('#catalog')
    .getByRole('link', { name: 'Баннер каталога', exact: true })
    .hover()
  await expect(
    page.getByRole('tab', { name: 'Мозаика только в меню' }),
  ).toHaveAttribute('aria-selected', 'true')
})
