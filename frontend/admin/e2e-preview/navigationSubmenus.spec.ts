import { expect, test } from '../e2e/fixtures'
import { mockContentWorkspace } from '../e2e/contentWorkspaceApi'

test('appearance embeds real saved Nuxt submenus in desktop and phone preview', async ({
  page,
  context,
  request,
}, testInfo) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=submenu')
  await context.addCookies([
    {
      name: 'preview_test_session',
      value: 'employee',
      url: 'http://127.0.0.1:8015',
      sameSite: 'Lax',
    },
  ])
  await mockContentWorkspace(page)
  await page.route('http://127.0.0.1:8015/**', (route) => route.continue())
  await page.setViewportSize({ width: 1920, height: 1080 })
  await page.goto('/content?section=appearance')
  const iframe = page.locator('iframe')
  const frame = page.frameLocator('iframe')
  await expect(iframe).toHaveAttribute(
    'src',
    'http://127.0.0.1:3015/preview/home',
  )
  await expect(frame.getByText('Черновая шапка')).toBeVisible()
  const navigation = frame.getByRole('navigation', {
    name: 'Навигация предпросмотра',
  })
  const parent = navigation.getByRole('button', {
    name: 'Каталог',
    exact: true,
  })
  await parent.focus()
  await page.keyboard.press('Enter')
  await expect(parent).toHaveAttribute('aria-expanded', 'true')
  await expect(
    navigation.getByRole('link', { name: 'Весь каталог' }),
  ).toHaveAttribute('href', '/catalog')
  await expect(
    navigation.getByRole('link', { name: 'Весь каталог' }),
  ).toBeVisible()
  await expect(iframe).toHaveAttribute(
    'src',
    'http://127.0.0.1:3015/preview/home',
  )
  await page.screenshot({
    path: testInfo.outputPath('real-admin-nuxt-desktop.png'),
  })
  await page.keyboard.press('Escape')
  await expect(parent).toBeFocused()
  await page.getByRole('button', { name: 'Телефон', exact: true }).click()
  await expect(iframe).toHaveCSS('width', '375px')
  // Wait for the scaled canvas and iframe viewport to finish the same layout cycle.
  await iframe
    .contentFrame()
    .locator('body')
    .evaluate(async () => {
      await document.fonts.ready
      await new Promise<void>((resolve) =>
        requestAnimationFrame(() => requestAnimationFrame(() => resolve())),
      )
    })
  await frame.getByRole('button', { name: 'Открыть меню' }).click()
  const menu = frame.getByRole('dialog', { name: 'Меню сайта' })
  await expect(menu).toBeFocused()
  const mobileParent = menu.getByRole('button', {
    name: 'Каталог',
    exact: true,
  })
  await mobileParent.click()
  await expect(mobileParent).toHaveAttribute('aria-expanded', 'true')
  await expect(menu.getByRole('link', { name: 'Магазины' })).toBeVisible()
  await page.screenshot({
    path: testInfo.outputPath('real-admin-nuxt-phone.png'),
  })
  await page.keyboard.press('Escape')
  await expect(mobileParent).toBeFocused()
  await expect(menu).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(menu).toHaveCount(0)
  expect(
    await iframe
      .contentFrame()
      .locator('body')
      .evaluate(() => document.documentElement.scrollWidth <= innerWidth),
  ).toBe(true)
})
