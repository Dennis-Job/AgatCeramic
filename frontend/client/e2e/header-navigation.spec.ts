import { expect, test } from '@playwright/test'

test('five configured links remain visible in the desktop header and available in the narrow menu', async ({
  page,
  request,
}, testInfo) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=header-navigation')
  const issues: string[] = []
  page.on('pageerror', (error) => issues.push(error.message))
  page.on('console', (message) => {
    if (/hydration/i.test(message.text())) issues.push(message.text())
  })
  await page.setViewportSize({ width: 1280, height: 900 })
  await page.goto('/about')
  const navigation = page.getByRole('navigation', {
    name: 'Основная навигация',
  })
  await expect(navigation).toBeVisible()
  await expect(navigation.getByRole('link')).toHaveText([
    'Главная',
    'Каталог',
    'О нас',
    'Контакты',
    'Сотрудничество',
  ])
  await expect(
    navigation.getByRole('link', { name: 'О нас', exact: true }),
  ).toHaveAttribute('aria-current', 'page')
  await navigation.getByRole('link', { name: 'Контакты', exact: true }).focus()
  await page.keyboard.press('Enter')
  await expect(page).toHaveURL(/\/contacts$/)
  await expect(
    navigation.getByRole('link', { name: 'Контакты', exact: true }),
  ).toHaveAttribute('aria-current', 'page')

  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    if (width > 1024) await expect(navigation).toBeVisible()
    else await expect(navigation).not.toBeVisible()
    const geometry = await page.evaluate(() => ({
      width: innerWidth,
      scroll: document.documentElement.scrollWidth,
      overflowing: Array.from(document.querySelectorAll('body *'))
        .filter(
          (element) => element.getBoundingClientRect().right > innerWidth + 1,
        )
        .map((element) => ({
          className: element.className,
          right: element.getBoundingClientRect().right,
        })),
    }))
    expect(geometry.scroll, JSON.stringify(geometry)).toBeLessThanOrEqual(
      geometry.width,
    )
    await page.screenshot({
      path: testInfo.outputPath(`header-navigation-${width}.png`),
    })
    const trigger = page.getByRole('button', { name: 'Открыть меню' })
    if (width > 1024) {
      await expect(trigger).toHaveCount(0)
      continue
    }
    await trigger.click()
    const menu = page.getByRole('dialog', { name: 'Меню сайта' })
    await expect(
      menu
        .getByRole('navigation', { name: 'Мобильная навигация' })
        .getByRole('link'),
    ).toHaveCount(5)
    await page.keyboard.press('Escape')
    await expect(menu).toHaveCount(0)
    await expect(trigger).toBeFocused()
  }
  expect(issues).toEqual([])
})

test('oversized desktop navigation keeps page content reachable and scrolls to keyboard focused links', async ({
  page,
  request,
}, testInfo) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=max-navigation')
  await page.setViewportSize({ width: 1280, height: 900 })
  await page.goto('/about')
  const header = page.locator('.site-header')
  const navigation = page.getByRole('navigation', {
    name: 'Основная навигация',
  })
  for (const height of [900, 360]) {
    await page.setViewportSize({ width: 1280, height })
    await page.evaluate(
      () =>
        new Promise<void>((resolve) => {
          requestAnimationFrame(() => requestAnimationFrame(() => resolve()))
        }),
    )
    expect((await header.boundingBox())!.height).toBeLessThan(height / 2)
    await expect(navigation.getByRole('link')).toHaveCount(16)
    await navigation.getByRole('link').last().focus()
    expect(
      await navigation.evaluate((element) => element.scrollTop),
    ).toBeGreaterThan(0)
    await expect(navigation.getByRole('link').last()).toBeInViewport({
      ratio: 1,
    })
    await page.keyboard.press('Shift+Tab')
    await expect(navigation.getByRole('link').nth(14)).toBeFocused()
    await header.screenshot({
      path: testInfo.outputPath(`bounded-header-${height}.png`),
    })
    await page.getByRole('button', { name: 'Открыть поиск' }).click()
    const search = page.locator('#site-search')
    await expect(search).toBeVisible()
    await expect
      .poll(async () => {
        const headerBox = (await header.boundingBox())!
        return Math.abs(
          (await search.boundingBox())!.y - headerBox.y - headerBox.height,
        )
      })
      .toBeLessThan(1)
    await page.keyboard.press('Escape')
  }
})
