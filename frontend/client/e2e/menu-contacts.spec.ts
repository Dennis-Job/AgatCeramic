import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=menu-contacts')
})

test('side-menu bottom follows reference order and uses real site contacts, independent note and shared socials', async ({
  page,
  context,
}, testInfo) => {
  const issues: string[] = []
  page.on('pageerror', (error) => issues.push(error.message))
  page.on('console', (message) => {
    if (/hydration/i.test(message.text())) issues.push(message.text())
  })
  await page.goto('/about')
  for (const width of [320, 640, 768, 1024]) {
    await page.setViewportSize({ width, height: 900 })
    const trigger = page.getByRole('button', { name: 'Открыть меню' })
    await trigger.click()
    const menu = page.getByRole('dialog', { name: 'Меню сайта' })
    const footer = menu.getByRole('group', { name: 'Контакты в меню' })
    await expect(footer.locator('a,p')).toHaveText([
      '+7 (900) 000-00-00',
      'shop@example.test',
      'Ежедневно 10:00–21:00\nПоможем с выбором',
      'Telegram',
      'ВКонтакте',
    ])
    await expect(
      footer.getByRole('link', { name: '+7 (900) 000-00-00' }),
    ).toHaveAttribute('href', 'tel:+79000000000')
    await expect(
      footer.getByRole('link', { name: 'shop@example.test' }),
    ).toHaveAttribute('href', 'mailto:shop@example.test')
    await expect(
      footer.getByRole('link', { name: 'Telegram' }),
    ).toHaveAttribute('href', 'https://t.me/agat_test')
    await expect(
      footer.getByText('Материалы для дома', { exact: true }),
    ).toHaveCount(0)
    await footer.scrollIntoViewIfNeeded()
    expect(
      await menu.evaluate((node) => node.scrollWidth <= node.clientWidth),
    ).toBe(true)
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: testInfo.outputPath(`menu-contacts-${width}.png`),
    })
    const last = footer.getByRole('link', { name: 'ВКонтакте' })
    await last.focus()
    await page.keyboard.press('Tab')
    await expect(
      menu.getByRole('button', { name: 'Закрыть меню' }),
    ).toBeFocused()
    await page.keyboard.press('Shift+Tab')
    await expect(last).toBeFocused()
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
    await page.keyboard.press('Escape')
    await expect(trigger).toBeFocused()
  }
  await page.setViewportSize({ width: 1280, height: 900 })
  await expect(page.getByRole('button', { name: 'Открыть меню' })).toHaveCount(
    0,
  )
  await context.addCookies([
    {
      name: 'preview_test_session',
      value: 'employee',
      domain: '127.0.0.1',
      path: '/',
    },
  ])
  await page.setViewportSize({ width: 640, height: 900 })
  await page.goto('/preview/about')
  await page.getByRole('button', { name: 'Открыть меню' }).click()
  await expect(
    page
      .getByRole('group', { name: 'Контакты в меню' })
      .getByText('Ежедневно 10:00–21:00', { exact: false }),
  ).toBeVisible()
  expect(issues).toEqual([])
})

test('empty contacts and explicit empty menu note produce no invented content; legacy note is retained', async ({
  page,
  request,
}) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=menu-empty')
  await page.setViewportSize({ width: 320, height: 600 })
  await page.goto('/about')
  await page.getByRole('button', { name: 'Открыть меню' }).click()
  await expect(
    page.getByRole('group', { name: 'Контакты в меню' }),
  ).toHaveCount(0)
  await page.keyboard.press('Escape')
  await request.get('http://127.0.0.1:8015/__scenario?name=default')
  await page.reload()
  await page.getByRole('button', { name: 'Открыть меню' }).click()
  await expect(
    page
      .getByRole('group', { name: 'Контакты в меню' })
      .getByText('Материалы для дома', { exact: true }),
  ).toBeVisible()
})

test('long notes and social labels wrap in a short menu and unsafe social addresses never render', async ({
  page,
  request,
}, testInfo) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=menu-long')
  await page.setViewportSize({ width: 320, height: 360 })
  await page.goto('/about')
  await page.getByRole('button', { name: 'Открыть меню' }).click()
  const menu = page.getByRole('dialog', { name: 'Меню сайта' })
  const footer = menu.getByRole('group', { name: 'Контакты в меню' })
  const links = footer
    .getByRole('navigation', { name: 'Соцсети в меню' })
    .getByRole('link')
  await expect(links).toHaveCount(12)
  await links.last().focus()
  await expect(links.last()).toBeFocused()
  expect(
    await menu.evaluate((node) => node.scrollWidth <= node.clientWidth),
  ).toBe(true)
  await expect(
    menu.locator('a[href^="javascript:"],a[href^="//"]'),
  ).toHaveCount(0)
  await page.screenshot({ path: testInfo.outputPath('menu-long-320x360.png') })
  await page.keyboard.press('Escape')
  await expect(page.locator('body')).not.toHaveClass(/has-overlay/)
})
