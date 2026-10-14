import AxeBuilder from './approvedAccessibility'
import { expect, test } from './fixtures'
import { attribute, mockCatalogApi } from './catalogApi'

for (const viewport of [
  { width: 320, height: 900 },
  { width: 640, height: 900 },
  { width: 768, height: 900 },
  { width: 1024, height: 900 },
  { width: 1280, height: 900 },
  { width: 1280, height: 600 },
]) {
  test(`category assignments use available space at ${viewport.width}x${viewport.height}`, async ({
    page,
  }) => {
    await mockCatalogApi(page)
    const attributes = Array.from({ length: 24 }, (_, index) => ({
      ...attribute,
      id: index + 1,
      name: `Характеристика ${index + 1}: размер керамической плитки`,
      slug: `tile-dimension-${index + 1}`,
    }))
    await page.route(/\/api\/v1\/admin\/attributes\?/, (route) =>
      route.fulfill({
        json: {
          data: attributes,
          meta: {
            current_page: 1,
            last_page: 1,
            per_page: 100,
            total: attributes.length,
          },
        },
      }),
    )
    await page.setViewportSize(viewport)
    await page.goto('/categories')
    const opener = page.getByRole('button', {
      name: 'Настроить характеристики категории Керамогранит',
    })
    await opener.click()
    const dialog = page.getByRole('dialog', {
      name: 'Характеристики: Керамогранит',
    })
    await expect(
      dialog.getByText(attributes[23]!.name, { exact: false }),
    ).toHaveCount(1)
    const bounds = (await dialog.boundingBox())!
    expect(bounds.height).toBeGreaterThanOrEqual(viewport.height - 40)
    expect(bounds.y).toBeGreaterThanOrEqual(16)
    expect(bounds.y + bounds.height).toBeLessThanOrEqual(viewport.height - 15)
    if (viewport.width >= 1024) expect(bounds.width).toBeGreaterThanOrEqual(850)
    expect(
      await dialog.evaluate(
        (element) => element.scrollWidth <= element.clientWidth,
      ),
    ).toBe(true)
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    const cancel = dialog.getByRole('button', { name: 'Отмена', exact: true })
    const save = dialog.getByRole('button', { name: 'Сохранить', exact: true })
    const cancelBefore = (await cancel.boundingBox())!
    const saveBefore = (await save.boundingBox())!
    expect(Math.abs(cancelBefore.y - saveBefore.y)).toBeLessThan(2)
    await dialog
      .getByText(attributes[23]!.name, { exact: false })
      .scrollIntoViewIfNeeded()
    expect((await save.boundingBox())!.y).toBe(saveBefore.y)
    await expect(
      dialog.getByRole('heading', { name: 'Характеристики: Керамогранит' }),
    ).toBeInViewport()
    await expect(save).toBeInViewport()
    expect(
      (await new AxeBuilder({ page }).include('[role="dialog"]').analyze())
        .violations,
    ).toEqual([])
    await page.screenshot({
      path: `.tmp/category-assignments-${viewport.width}x${viewport.height}.png`,
    })
    await page.keyboard.press('Escape')
    await expect(dialog).toHaveCount(0)
    await expect(opener).toBeFocused()
  })
}
