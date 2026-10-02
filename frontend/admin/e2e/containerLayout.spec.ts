import { expect, test } from './fixtures'
import { mockAdminBaseline } from './adminBaselineApi'
import { mockContentWorkspace } from './contentWorkspaceApi'

const routes = [
  '/',
  '/profile',
  '/settings',
  '/products',
  '/categories',
  '/brands',
  '/attribute-groups',
  '/attributes',
  '/employees',
  '/roles',
  '/permissions',
  '/audit-log',
  '/orders',
  '/contacts',
  '/media',
  '/content',
  '/content?section=appearance',
  '/content?section=stores',
  '/ui-kit',
]

for (const path of routes) {
  test(`shared container aligns menu and page blocks: ${path}`, async ({
    page,
  }) => {
    if (path.startsWith('/content?')) await mockContentWorkspace(page)
    else await mockAdminBaseline(page)
    await page.goto(path)
    await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible()
    await page.waitForLoadState('networkidle')
    for (const width of [320, 602, 640, 768, 1024, 1280, 1440, 1920, 2560]) {
      await page.setViewportSize({ width, height: 900 })
      const compact = page.getByRole('button', { name: 'Открыть меню' })
      if (width < 1024) await expect(compact).toBeVisible()
      else await expect(compact).toHaveCount(0)
      await page.evaluate(
        () =>
          new Promise<void>((resolve) =>
            requestAnimationFrame(() => requestAnimationFrame(() => resolve())),
          ),
      )
      const expectedWidth = Math.min(1280, width - (width < 640 ? 32 : 48))
      const expectedLeft = (width - expectedWidth) / 2
      const menu = await page.locator('.admin-navigation').boundingBox()
      expect(menu!.x, `${path} menu left at ${width}`).toBe(expectedLeft)
      expect(menu!.width, `${path} menu width at ${width}`).toBe(expectedWidth)
      const header = await page
        .getByRole('heading', { level: 1 })
        .first()
        .locator('..')
        .locator('..')
        .boundingBox()
      expect(header!.x, `${path} title left at ${width}`).toBe(expectedLeft)
      expect(header!.width, `${path} title width at ${width}`).toBe(
        expectedWidth,
      )
      for (const panel of await page
        .locator('form[role="search"], .admin-workspace > .admin-container')
        .all()) {
        if (!(await panel.isVisible())) continue
        const box = await panel.boundingBox()
        expect(box!.x, `${path} panel left at ${width}`).toBe(expectedLeft)
        expect(box!.width, `${path} panel width at ${width}`).toBe(
          expectedWidth,
        )
      }
      for (const table of await page.locator('.ui-table-full-bleed').all()) {
        if (!(await table.isVisible())) continue
        const box = await table.boundingBox()
        const viewportWidth = await page.evaluate(
          () => document.documentElement.clientWidth,
        )
        expect(box!.x, `${path} table left at ${width}`).toBe(0)
        expect(box!.width, `${path} table width at ${width}`).toBe(
          viewportWidth,
        )
        expect(box!.x + box!.width, `${path} table right at ${width}`).toBe(
          viewportWidth,
        )
      }
      if (path === '/') {
        const recentOrders = page.getByRole('region', {
          name: 'Последние заказы',
          exact: true,
        })
        const embedded = await recentOrders.boundingBox()
        expect(embedded!.x).toBeGreaterThanOrEqual(expectedLeft)
        expect(embedded!.x + embedded!.width).toBeLessThanOrEqual(
          expectedLeft + expectedWidth,
        )
        await expect(recentOrders).not.toHaveClass(/ui-table-full-bleed/)
      }
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= innerWidth,
        ),
      ).toBe(true)
      if (width === 1920 || (path === '/products' && width === 602))
        await page.screenshot({
          path: `.tmp/container-layout/${path.replace(/\W+/g, '-')}-${width}.png`,
        })
    }
  })
}
