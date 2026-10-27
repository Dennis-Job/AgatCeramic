import { expect, test } from '@playwright/test'

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=banners-menu-links')
})

test('menu banners have their own note and destination without a Смотреть badge', async ({
  page,
  request,
}, testInfo) => {
  const html = await (await request.get('/')).text()
  const menuHtml =
    html.match(/<section[^>]*id="materials"[\s\S]*?<\/section>/)?.[0] || ''
  expect(menuHtml).toContain('href="/catalog?from=menu-first"')
  expect(menuHtml).toContain('Примечание первого баннера')
  expect(menuHtml).not.toContain('Старое общее примечание')
  const issues: string[] = []
  page.on('pageerror', (error) => issues.push(error.message))
  page.on('console', (message) => {
    if (/hydration/i.test(message.text())) issues.push(message.text())
  })
  await page.goto('/')
  const menu = page.locator('#materials')
  const panel = menu.getByRole('tabpanel')
  await expect(panel.locator('.materials-section__note')).toHaveText(
    'Примечание первого баннера',
  )
  await expect(panel.getByRole('link')).toHaveAttribute(
    'href',
    '/catalog?from=menu-first',
  )
  await menu.getByRole('tab', { name: 'Второй баннер меню' }).click()
  await expect(page).toHaveURL('http://127.0.0.1:3015/')
  await expect(panel.locator('.materials-section__note')).toHaveText(
    'Примечание второго баннера',
  )
  await expect(panel.getByRole('link')).toHaveAttribute(
    'href',
    'https://example.test/menu-second',
  )
  for (const label of [
    'Баннер без примечания',
    'Небезопасная ссылка меню',
    'Баннер без новых полей',
  ]) {
    await menu.getByRole('tab', { name: label, exact: true }).click()
    await expect(panel.locator('.materials-section__note')).toHaveCount(0)
    await expect(panel.getByRole('link')).toHaveCount(0)
  }
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    await menu.getByRole('tab', { name: 'Первый баннер меню' }).click()
    const link = panel.getByRole('link', {
      name: 'Первый баннер меню',
      exact: true,
    })
    await link.hover()
    await expect(menu.getByText('Смотреть', { exact: true })).toHaveCount(0)
    await page.keyboard.press('Tab')
    await expect(link).toBeFocused()
    expect(
      await link.evaluate((element) => element.matches(':focus-visible')),
    ).toBe(true)
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: testInfo.outputPath(`menu-banner-${width}.png`),
    })
  }
  await panel.getByRole('link').press('Enter')
  await expect(page).toHaveURL('http://127.0.0.1:3015/catalog?from=menu-first')
  expect(issues).toEqual([])
})

test('saved Nuxt preview renders each menu note and supports image clicks', async ({
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
    page.getByRole('tabpanel').locator('.materials-section__note'),
  ).toHaveText('Примечание первого баннера')
  await page.getByRole('tab', { name: 'Второй баннер меню' }).click()
  await expect(
    page.getByRole('tabpanel').locator('.materials-section__note'),
  ).toHaveText('Примечание второго баннера')
  await page.getByRole('tab', { name: 'Первый баннер меню' }).click()
  await page.getByRole('tabpanel').getByRole('img').click()
  await expect(page).toHaveURL('http://127.0.0.1:3015/catalog?from=menu-first')
})
