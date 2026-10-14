import AxeBuilder from './approvedAccessibility'
import { expect, test } from './fixtures'
import { attribute, attributeGroup, mockCatalogApi } from './catalogApi'

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
    const groups = [
      attributeGroup,
      {
        ...attributeGroup,
        id: 2,
        name: 'Внешний вид и поверхность',
        slug: 'appearance',
      },
      {
        ...attributeGroup,
        id: 3,
        name: 'Дополнительные технические характеристики керамической плитки',
        slug: 'technical',
      },
    ]
    const attributes = Array.from({ length: 24 }, (_, index) => ({
      ...attribute,
      id: index + 1,
      attribute_group_id: index < 6 ? 1 : index < 12 ? 2 : null,
      name: `Характеристика ${index + 1}: размер керамической плитки`,
      slug: `tile-dimension-${index + 1}`,
    }))
    await page.route(/\/api\/v1\/admin\/attribute-groups\?/, (route) =>
      route.fulfill({
        json: {
          data: groups,
          meta: {
            current_page: 1,
            last_page: 1,
            per_page: 100,
            total: groups.length,
          },
        },
      }),
    )
    await page.route(
      /\/api\/v1\/admin\/categories\/1\/attribute-groups$/,
      (route) => route.fulfill({ json: { data: groups } }),
    )
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
    const groupPicker = dialog.getByRole('region', {
      name: 'Группы характеристик',
      exact: true,
    })
    const emptyGroup = groupPicker.getByRole('checkbox', {
      name: groups[2]!.name,
      exact: true,
    })
    await expect(emptyGroup).toBeChecked()
    await emptyGroup.press('Space')
    await expect(emptyGroup).not.toBeChecked()
    await expect(
      dialog.getByRole('region', { name: groups[2]!.name, exact: true }),
    ).toHaveCount(0)
    await emptyGroup.press('Space')
    await expect(emptyGroup).toBeChecked()
    await expect(
      dialog
        .getByRole('region', { name: groups[2]!.name, exact: true })
        .getByText('В группе пока нет характеристик.'),
    ).toHaveCount(1)
    await expect(
      dialog.getByRole('region', { name: 'Без группы', exact: true }),
    ).toHaveCount(1)
    await dialog
      .getByRole('heading', { name: 'Группы характеристик', exact: true })
      .scrollIntoViewIfNeeded()
    await page.screenshot({
      path: `.tmp/category-assignment-groups-${viewport.width}x${viewport.height}.png`,
    })
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
