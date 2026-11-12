import { expect, test } from '@playwright/test'

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=standalone-default')
})

test('desktop categories stay above products and pin below the header', async ({
  page,
}, testInfo) => {
  await page.goto('/catalog')
  const categories = page.getByRole('navigation', {
    name: 'Категории каталога',
  })
  for (const width of [1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    await page.evaluate(() => window.scrollTo(0, 0))
    await expect(categories).toBeVisible()
    await expect(categories).toHaveCount(1)
    await expect(page.locator('.catalog-sidebar nav')).toHaveCount(0)
    const navigation = categories.locator('..')
    await expect(navigation).toHaveCSS('position', 'sticky')
    const before = await categories.boundingBox()
    const products = await page.locator('.product-card-list').boundingBox()
    expect(before!.y + before!.height).toBeLessThan(products!.y)
    await page.evaluate(() => window.scrollTo(0, 1000))
    await expect
      .poll(async () => {
        const header = await page.locator('.site-header').boundingBox()
        const pinned = await navigation.boundingBox()
        return Math.abs(pinned!.y - (header!.y + header!.height))
      })
      .toBeLessThan(2)
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: testInfo.outputPath(`catalog-pinned-${width}.png`),
    })
  }
})

test('narrow screens show vertical categories inside filters and preserve URLs', async ({
  page,
}, testInfo) => {
  for (const width of [320, 640, 768, 1023]) {
    await page.setViewportSize({ width, height: 900 })
    await page.goto('/catalog?brand=atlas&price_from=100&page=2')
    await expect(page.locator('.catalog-category-navigation')).toBeHidden()
    const opener = page.getByRole('button', { name: 'Фильтры', exact: true })
    await opener.click()
    const drawer = page.getByRole('dialog', { name: 'Фильтры каталога' })
    await expect(drawer).toBeVisible()
    await expect(
      drawer.getByRole('navigation', { name: 'Категории каталога' }),
    ).toBeVisible()
    await expect(drawer.getByLabel('Цена от')).toBeVisible()
    await page.screenshot({
      path: testInfo.outputPath(`catalog-categories-drawer-${width}.png`),
    })
    await drawer.getByRole('button', { name: 'Материалы', exact: true }).click()
    await drawer.getByRole('button', { name: 'Плитка', exact: true }).click()
    await expect(drawer).toBeVisible()
    expect(page.url()).not.toContain('category=tile')
    await expect(drawer.getByText('Цвет', { exact: true })).toBeVisible()
    await drawer.getByRole('button', { name: 'Применить', exact: true }).click()
    await expect(page).toHaveURL(/category=tile/)
    expect(page.url()).toContain('brand=atlas')
    expect(page.url()).toContain('price_from=100')
    expect(page.url()).not.toContain('page=2')
    await expect(drawer).toHaveCount(0)
    await expect(opener).toBeFocused()
    await opener.click()
    await page.keyboard.press('Escape')
    await expect(drawer).toHaveCount(0)
    await expect(opener).toBeFocused()
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
  }
})

test('keyboard focus is revealed below both sticky layers', async ({
  page,
}) => {
  await page.goto('/catalog')
  const categories = page.getByRole('navigation', {
    name: 'Категории каталога',
  })
  await page.evaluate(() => window.scrollTo(0, 1000))
  await categories.getByRole('link').last().focus()
  await page.keyboard.press('Tab')
  await expect
    .poll(() =>
      page.evaluate(() => {
        const active = document.activeElement!.getBoundingClientRect()
        const navigation = document
          .querySelector('.catalog-category-navigation')!
          .getBoundingClientRect()
        return active.top - navigation.bottom
      }),
    )
    .toBeGreaterThanOrEqual(7)
})

test('drawer categories share the site menu row style with smaller text', async ({
  page,
}, testInfo) => {
  for (const width of [320, 640, 768]) {
    await page.setViewportSize({ width, height: 900 })
    await page.goto('/catalog')
    await page.getByRole('button', { name: 'Открыть меню' }).click()
    const menu = page.getByRole('dialog', { name: 'Меню сайта' })
    const menuLink = menu.getByRole('navigation').getByRole('link').first()
    const reference = await menuLink.evaluate((element) => {
      const style = getComputedStyle(element)
      const row = getComputedStyle(element.parentElement!)
      return {
        fontSize: parseFloat(style.fontSize),
        fontWeight: style.fontWeight,
        paddingTop: style.paddingTop,
        paddingBottom: style.paddingBottom,
        color: style.color,
        border: row.borderBottom,
      }
    })
    await menuLink.hover()
    const hoverColor = await menuLink.evaluate(
      (element) => getComputedStyle(element).color,
    )
    await page.screenshot({
      path: testInfo.outputPath(`site-menu-${width}.png`),
    })
    await page.keyboard.press('Escape')
    await expect(menu).toHaveCount(0)
    await page.getByRole('button', { name: 'Фильтры', exact: true }).click()
    const drawer = page.getByRole('dialog', { name: 'Фильтры каталога' })
    const categories = drawer.getByRole('navigation', {
      name: 'Категории каталога',
    })
    const link = categories.getByRole('button', {
      name: 'Все товары',
      exact: true,
    })
    const style = await link.evaluate((element) => {
      const style = getComputedStyle(element)
      const row = getComputedStyle(element.parentElement!)
      return {
        fontSize: parseFloat(style.fontSize),
        fontWeight: style.fontWeight,
        paddingTop: style.paddingTop,
        paddingBottom: style.paddingBottom,
        color: style.color,
        border: row.borderBottom,
      }
    })
    expect(style.fontSize).toBe(13)
    expect(
      await drawer
        .locator('.ui-checkbox')
        .first()
        .evaluate((element) => parseFloat(getComputedStyle(element).fontSize)),
    ).toBe(style.fontSize)
    expect(style.fontSize).toBeLessThan(reference.fontSize)
    const linkBounds = (await link.boundingBox())!
    const navigationBounds = (await categories.boundingBox())!
    expect(Math.abs(linkBounds.x - navigationBounds.x)).toBeLessThan(1)
    expect(Math.abs(linkBounds.width - navigationBounds.width)).toBeLessThan(1)
    for (const key of [
      'fontWeight',
      'paddingTop',
      'paddingBottom',
      'color',
      'border',
    ] as const)
      expect(style[key]).toBe(reference[key])
    await expect(link.locator('[aria-hidden="true"]')).toHaveText('01')
    await expect(
      categories
        .getByRole('button', { name: 'Мозаика', exact: true })
        .locator('[aria-hidden="true"]'),
    ).toHaveText('03')
    await link.hover()
    await expect(link).toHaveCSS('color', hoverColor)
    await page.screenshot({
      path: testInfo.outputPath(`category-menu-${width}.png`),
    })
    expect(
      await drawer.evaluate(
        (element) => element.scrollWidth <= element.clientWidth,
      ),
    ).toBe(true)
    await page.keyboard.press('Escape')
    await expect(drawer).toHaveCount(0)
  }
})
