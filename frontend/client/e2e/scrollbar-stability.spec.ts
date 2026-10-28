import { expect, test } from '@playwright/test'

test.use({
  launchOptions: {
    ignoreDefaultArgs: ['--hide-scrollbars'],
    args: ['--disable-features=OverlayScrollbar'],
  },
})

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=default')
})

test('classic scrollbar removal preserves page geometry for menu, search and native modal', async ({
  page,
}, testInfo) => {
  await page.goto('/about')
  await page.evaluate(() => document.fonts.ready)
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 707 })
    await page.evaluate(
      () =>
        new Promise<void>((resolve) =>
          requestAnimationFrame(() => requestAnimationFrame(() => resolve())),
        ),
    )
    expect(
      await page.evaluate(
        () => innerWidth - document.documentElement.clientWidth,
      ),
    ).toBeGreaterThan(0)
    const geometry = () =>
      page.evaluate(() => {
        const header = document
          .querySelector('.site-header__inner')!
          .getBoundingClientRect()
        const logo = document
          .querySelector('.wordmark')!
          .getBoundingClientRect()
        const main = document.querySelector('main')!.getBoundingClientRect()
        return {
          headerX: header.x,
          headerWidth: header.width,
          logoX: logo.x,
          mainX: main.x,
          mainWidth: main.width,
        }
      })
    const before = await geometry()
    expect(
      await page.evaluate(
        () =>
          document.documentElement.scrollWidth <=
          document.documentElement.clientWidth,
      ),
    ).toBe(true)
    await page.screenshot({ path: testInfo.outputPath(`page-${width}.png`) })
    if (width <= 1024) {
      await page.getByRole('button', { name: 'Открыть меню' }).click()
      await expect(
        page.getByRole('dialog', { name: 'Меню сайта' }),
      ).toBeFocused()
      expect(await geometry()).toEqual(before)
      await page.screenshot({ path: testInfo.outputPath(`menu-${width}.png`) })
      await page
        .getByRole('button', { name: 'Поиск по разделам страницы' })
        .click()
    } else {
      await expect(
        page.getByRole('button', { name: 'Открыть меню' }),
      ).toHaveCount(0)
      await page.getByRole('button', { name: 'Открыть поиск' }).click()
    }
    await expect(
      page.getByRole('dialog', { name: 'Поиск по разделам страницы' }),
    ).toBeFocused()
    expect(await geometry()).toEqual(before)
    await page.keyboard.press('Escape')
    await expect(page.locator('#site-search')).toHaveCount(0)
    expect(await geometry()).toEqual(before)
    await page
      .getByRole('button', { name: 'заказать звонок', exact: true })
      .click()
    await expect(page.getByRole('dialog')).toBeFocused()
    await expect(page.getByRole('dialog')).toHaveCSS('opacity', '1')
    expect(await geometry()).toEqual(before)
    await page.screenshot({ path: testInfo.outputPath(`modal-${width}.png`) })
    await page.keyboard.press('Escape')
    await expect(page.locator('dialog')).toHaveCount(0)
    expect(await geometry()).toEqual(before)
  }
})

test('classic scrollbar gutter fits a narrow and short page without horizontal overflow', async ({
  page,
}) => {
  await page.setViewportSize({ width: 320, height: 5000 })
  await page.goto('/about')
  expect(
    await page.evaluate(
      () =>
        document.documentElement.scrollWidth <=
        document.documentElement.clientWidth,
    ),
  ).toBe(true)
  expect(
    await page.evaluate(
      () =>
        document.body.getBoundingClientRect().width <=
        document.documentElement.getBoundingClientRect().width,
    ),
  ).toBe(true)
})
