import AxeBuilder from '@axe-core/playwright'
import { expect, test } from './fixtures'
import { mockAdminBaseline } from './adminBaselineApi'
import { product } from './catalogApi'

const widths = [320, 640, 768, 1024, 1280, 1440, 1920, 2560]

test('scrolling moves navigation into the brand row without moving document content', async ({
  page,
}) => {
  await mockAdminBaseline(page, false, 'default', undefined, {
    '/admin/products': {
      data: Array.from({ length: 25 }, (_, index) => ({
        ...product,
        id: index + 1,
        name: `${product.name} ${index + 1}`,
      })),
      meta: { current_page: 1, last_page: 1, per_page: 25, total: 25 },
    },
  })
  await page.goto('/products')
  const header = page.getByRole('banner')
  const table = page.getByRole('region', { name: 'Таблица товаров' })
  const brand = header.getByRole('link', { name: 'AgatCeramic — главная' })
  const nav = header.locator('.admin-navigation')
  const actions = header.locator('.admin-header-actions')
  await expect(table).toBeVisible()
  await page.evaluate(() => document.fonts.ready)

  for (const width of widths) {
    await page.setViewportSize({ width, height: 900 })
    await page.evaluate(() => window.scrollTo(0, 0))
    await expect(header).not.toHaveClass(/is-condensed/)
    await expect(header).toHaveCSS('height', width < 1024 ? '65px' : '109px')
    const naturalTop = await table.evaluate(
      (element) => element.getBoundingClientRect().top + scrollY,
    )
    const naturalDocumentHeight = await page.evaluate(
      () => document.documentElement.scrollHeight,
    )
    await page.evaluate(() => window.scrollTo(0, 45))
    await expect(header).toHaveClass(/is-condensed/)
    await expect(header).toHaveCSS('height', '65px')
    await expect.poll(() => page.evaluate(() => scrollY)).toBe(45)
    expect(
      await table.evaluate(
        (element) => element.getBoundingClientRect().top + scrollY,
      ),
    ).toBeCloseTo(naturalTop, 0)
    expect(
      await page.evaluate(() => document.documentElement.scrollHeight),
    ).toBe(naturalDocumentHeight)
    const brandRect = (await brand.boundingBox())!
    const navRect = (await nav.boundingBox())!
    const actionsRect = (await actions.boundingBox())!
    expect(navRect.x).toBeGreaterThanOrEqual(brandRect.x + brandRect.width)
    expect(navRect.x + navRect.width).toBeLessThanOrEqual(actionsRect.x)
    expect(navRect.y).toBeGreaterThanOrEqual(brandRect.y)
    expect(navRect.y + navRect.height).toBeLessThanOrEqual(
      brandRect.y + brandRect.height,
    )
    if (width >= 1024) {
      const links = nav.locator('.admin-nav-trigger')
      const first = (await links.first().boundingBox())!
      const last = (await links.last().boundingBox())!
      expect((first.x + last.x + last.width) / 2).toBeCloseTo(
        navRect.x + navRect.width / 2,
        0,
      )
    } else {
      const menu = header.getByRole('button', { name: 'Открыть меню' })
      await expect(menu).toHaveText('')
      await expect(menu.locator('svg')).toBeVisible()
      const menuRect = (await menu.boundingBox())!
      const bellRect = (await header
        .getByRole('button', { name: 'Уведомления', exact: true })
        .boundingBox())!
      expect(menuRect.width).toBe(bellRect.width)
      expect(menuRect.height).toBe(bellRect.height)
      expect(menuRect.y).toBe(bellRect.y)
      expect(bellRect.x - menuRect.x - menuRect.width).toBe(
        width < 640 ? 8 : 12,
      )
    }
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.evaluate((y) => window.scrollTo(0, y), naturalTop + 200)
    await expect
      .poll(() =>
        table
          .locator('thead')
          .evaluate((element) => element.getBoundingClientRect().top),
      )
      .toBeCloseTo(65, 0)
    await page.screenshot({ path: `.tmp/scroll-header/condensed-${width}.png` })

    if (width < 1024) {
      const menu = header.getByRole('button', { name: 'Открыть меню' })
      await menu.click()
      const dialog = page.getByRole('dialog', { name: 'Разделы панели' })
      await expect(dialog).toBeVisible()
      expect((await dialog.boundingBox())!.y).toBeGreaterThanOrEqual(65)
      await expect(
        dialog.getByRole('link', { name: 'Список товаров' }),
      ).toBeVisible()
      await expect(page.getByRole('tooltip')).toHaveText('Закрыть меню')
      await page.keyboard.press('Escape')
      await expect(page.getByRole('tooltip')).toHaveCount(0)
      await expect(dialog).toBeVisible()
      await page.keyboard.press('Escape')
      await expect(dialog).toHaveCount(0)
      await expect(menu).toBeFocused()
    } else {
      const products = header.getByRole('button', {
        name: 'Товары',
        exact: true,
      })
      await products.focus()
      await page.keyboard.press('ArrowDown')
      const panel = page.getByRole('region', { name: 'Товары — подразделы' })
      await expect(panel).toBeVisible()
      await expect(
        panel.getByRole('link', { name: 'Список товаров' }),
      ).toBeFocused()
      expect((await panel.boundingBox())!.y).toBeGreaterThanOrEqual(65)
      await page.keyboard.press('Escape')
      await expect(products).toBeFocused()
    }
    const user = header.getByRole('button', {
      name: 'Меню пользователя',
      exact: true,
    })
    await user.focus()
    await page.keyboard.press('ArrowDown')
    const panel = page.getByRole('region', { name: 'Меню пользователя' })
    await expect(panel.getByRole('link', { name: 'Мой профиль' })).toBeFocused()
    expect((await panel.boundingBox())!.y).toBeGreaterThanOrEqual(
      (await user.boundingBox())!.y + (await user.boundingBox())!.height + 8,
    )
    await page.keyboard.press('Escape')
    await expect(user).toBeFocused()
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
    await page.evaluate(() => window.scrollTo(0, 44))
    await expect(header).not.toHaveClass(/is-condensed/)
    await expect(header).toHaveCSS('height', width < 1024 ? '65px' : '109px')
    await expect.poll(() => page.evaluate(() => scrollY)).toBe(44)
  }
})

test('open navigation keeps focus and follows the header when crossing the scroll threshold', async ({
  page,
}) => {
  await page.setViewportSize({ width: 1024, height: 900 })
  await mockAdminBaseline(page)
  await page.goto('/products')
  await page.locator('#admin-main').evaluate((element) => {
    element.style.paddingBottom = '1000px'
  })
  const header = page.getByRole('banner')
  const products = header.getByRole('button', { name: 'Товары', exact: true })
  await products.focus()
  await page.keyboard.press('ArrowDown')
  const panel = page.getByRole('region', { name: 'Товары — подразделы' })
  const firstLink = panel.getByRole('link', { name: 'Список товаров' })
  await expect(firstLink).toBeFocused()
  await page.evaluate(() => window.scrollTo(0, 45))
  await expect(header).toHaveClass(/is-condensed/)
  await expect(firstLink).toBeFocused()
  await expect.poll(async () => (await panel.boundingBox())!.y).toBe(72)
  await page.evaluate(() => window.scrollTo(0, 44))
  await expect(header).not.toHaveClass(/is-condensed/)
  await expect(firstLink).toBeFocused()
  await expect.poll(async () => (await panel.boundingBox())!.y).toBe(116)
  await page.keyboard.press('Escape')
  await expect(products).toBeFocused()

  await page.evaluate(() => window.scrollTo(0, 120))
  await expect(header).toHaveClass(/is-condensed/)
  await page.setViewportSize({ width: 320, height: 900 })
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= innerWidth,
    ),
  ).toBe(true)
  await header.getByRole('button', { name: 'Открыть меню' }).click()
  await expect(
    page.getByRole('dialog', { name: 'Разделы панели' }),
  ).toBeVisible()
  await expect(page.getByRole('tooltip')).toHaveText('Закрыть меню')
  await page.keyboard.press('Escape')
  await expect(page.getByRole('tooltip')).toHaveCount(0)
  await expect(
    page.getByRole('dialog', { name: 'Разделы панели' }),
  ).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(
    header.getByRole('button', { name: 'Открыть меню' }),
  ).toBeFocused()
})
