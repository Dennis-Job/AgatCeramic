import AxeBuilder from '@axe-core/playwright'
import { expect, test } from './fixtures'
import { mockCategoryOverview, overviewCategories } from './categoryOverviewApi'

for (const width of [320, 640, 768, 1024, 1280]) {
  for (const mode of ['create', 'edit'] as const) {
    test(`category ${mode} form scrolls every field above its actions at ${width}px`, async ({
      page,
    }) => {
      await mockCategoryOverview(page, mode === 'edit')
      await page.setViewportSize({ width, height: 600 })
      await page.goto('/categories')
      const opener =
        mode === 'create'
          ? page.getByRole('button', {
              name: 'Добавить категорию',
              exact: true,
            })
          : page
              .locator('#category-1')
              .getByRole('button', { name: /^Редактировать категорию/ })
      await opener.click()
      const dialog = page.getByRole('dialog')
      await expect(
        dialog.getByText('Документы категории', { exact: true }),
      ).toHaveCount(0)
      const cancelBounds = (await dialog
        .getByRole('button', { name: 'Отмена', exact: true })
        .boundingBox())!
      const saveBounds = (await dialog
        .getByRole('button', { name: 'Сохранить', exact: true })
        .boundingBox())!
      expect(Math.abs(cancelBounds.y - saveBounds.y)).toBeLessThanOrEqual(1)
      if (width >= 640) {
        const nameControl = await dialog
          .getByRole('textbox', { name: /^Название(?:\s|$)/ })
          .evaluate((el) => {
            const rect = el.parentElement!.getBoundingClientRect()
            return { width: rect.width, height: rect.height }
          })
        const sortControl = await dialog
          .getByRole('spinbutton', { name: /^Порядок сортировки(?:\s|$)/ })
          .evaluate((el) => {
            const rect = el.parentElement!.getBoundingClientRect()
            return { width: rect.width, height: rect.height }
          })
        const parentBounds = (await dialog
          .getByRole('button', { name: 'Родительская категория', exact: true })
          .boundingBox())!
        expect(
          Math.abs(sortControl.width - nameControl.width),
        ).toBeLessThanOrEqual(1)
        expect(
          Math.abs(parentBounds.width - nameControl.width),
        ).toBeLessThanOrEqual(1)
        expect(
          Math.abs(parentBounds.height - nameControl.height),
        ).toBeLessThanOrEqual(1)
      }
      const scroller = dialog.locator('.overflow-y-auto').first()
      expect(
        await scroller.evaluate((el) => el.clientHeight),
      ).toBeGreaterThanOrEqual(36)
      await scroller.hover()
      await page.mouse.wheel(0, 2000)
      await expect
        .poll(() => scroller.evaluate((el) => el.scrollTop))
        .toBeGreaterThan(0)
      await expect
        .poll(async () => {
          const field = (await dialog
            .getByRole('spinbutton', {
              name: /^Порядок сортировки(?:\s|$)/,
            })
            .boundingBox())!
          const footer = (await dialog.locator('footer').boundingBox())!
          const lastControlBottom = await dialog
            .getByRole('checkbox', { name: 'Категория активна', exact: true })
            .evaluate(
              (el) => el.closest('label')!.getBoundingClientRect().bottom,
            )
          return Math.max(field.y + field.height, lastControlBottom) - footer.y
        })
        .toBeLessThanOrEqual(0)
      const bounds = (await dialog.boundingBox())!
      expect(bounds.x).toBeGreaterThanOrEqual(0)
      expect(bounds.x + bounds.width).toBeLessThanOrEqual(width)
      expect(bounds.y + bounds.height).toBeLessThanOrEqual(600)
      await expect(
        dialog.getByRole('button', { name: 'Сохранить', exact: true }),
      ).toBeInViewport()
      const accessibility = await new AxeBuilder({ page })
        .include('[role="dialog"]')
        .disableRules(['color-contrast'])
        .analyze()
      expect(
        accessibility.violations.filter((item) =>
          ['serious', 'critical'].includes(item.impact ?? ''),
        ),
      ).toEqual([])
      await page.screenshot({
        path: `.tmp/category-${mode}-scroll-${width}.png`,
      })
      const sort = dialog.getByRole('spinbutton', {
        name: /^Порядок сортировки(?:\s|$)/,
      })
      await sort.scrollIntoViewIfNeeded()
      await expect(sort).toBeInViewport({ ratio: 1 })
      const parent = dialog.getByRole('button', {
        name: 'Родительская категория',
        exact: true,
      })
      await parent.click()
      const parentMenu = page.getByRole('group', {
        name: 'Родительская категория: варианты',
        exact: true,
      })
      await expect
        .poll(() =>
          parentMenu
            .getByRole('button')
            .last()
            .evaluate((el) => {
              const rect = el.getBoundingClientRect()
              return el.contains(
                document.elementFromPoint(
                  rect.x + rect.width / 2,
                  rect.y + rect.height / 2,
                ),
              )
            }),
        )
        .toBe(true)
      await page.screenshot({
        path: `.tmp/category-${mode}-parent-menu-${width}.png`,
      })
      await page.keyboard.press('Escape')
      await expect(parentMenu).toHaveCount(0)
      await expect(parent).toBeFocused()
      await page.keyboard.press('Escape')
      await expect(dialog).toHaveCount(0)
      await expect(opener).toBeFocused()
    })
  }
}

test('categories show the complete hierarchy, groups and per-attribute flags on arrival', async ({
  page,
}) => {
  await mockCategoryOverview(page)
  await page.setViewportSize({ width: 1440, height: 1024 })
  await page.goto('/categories')
  const root = page.locator('#category-1')
  await expect(root.getByRole('heading', { name: 'Основные' })).toBeVisible()
  await expect(root.getByText('Обязательная', { exact: true })).toHaveCount(3)
  await expect(root.getByText('В фильтрах', { exact: true })).toHaveCount(3)
  await expect(
    root.getByRole('button', { name: 'Для пола', exact: true }),
  ).toBeVisible()
  const wall = page.locator('#category-3')
  await expect(wall.getByText('Скрыта', { exact: true })).toBeVisible()
  await expect(wall.getByText('Нет назначенных характеристик.')).toBeVisible()
  await expect(page.getByRole('dialog')).toHaveCount(0)
  await page.screenshot({
    path: '.tmp/category-overview-desktop.png',
    fullPage: true,
  })
  const select = page.getByRole('button', { name: 'Категория', exact: true })
  await select.click()
  await page
    .getByRole('group', { name: 'Категория: варианты' })
    .getByRole('button', { name: 'Керамогранит / Для пола', exact: true })
    .click()
  await expect(page.locator('article')).toHaveCount(2)
  await expect(root).toBeVisible()
  await page
    .locator('#category-2')
    .getByRole('button', { name: 'Керамогранит', exact: true })
    .click()
  await expect(select).toContainText('Все категории')
  await expect(root).toBeFocused()
  await select.click()
  await page
    .getByRole('searchbox', { name: 'Поиск: Категория', exact: true })
    .fill('Не существует')
  await expect(
    page.getByRole('group', { name: 'Категория: варианты' }),
  ).toContainText('Ничего не найдено')
  await page.keyboard.press('Escape')
  await expect(select).toBeFocused()
})

test('category creation still opens the existing form and returns focus on Escape', async ({
  page,
}) => {
  await mockCategoryOverview(page)
  await page.goto('/categories')
  const create = page.getByRole('button', {
    name: 'Добавить категорию',
    exact: true,
  })
  await create.click()
  await expect(page.getByRole('dialog')).toBeVisible()
  await expect(
    page.getByRole('textbox', { name: 'Название', exact: true }),
  ).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(page.getByRole('dialog')).toHaveCount(0)
  await expect(create).toBeFocused()
})

test('saved required assignments refresh the visible overview without a page reload', async ({
  page,
}) => {
  await mockCategoryOverview(page)
  await page.goto('/categories')
  await page
    .getByRole('button', {
      name: 'Настроить характеристики категории Керамогранит',
      exact: true,
    })
    .click()
  const dialog = page.getByRole('dialog', {
    name: 'Характеристики: Керамогранит',
  })
  const required = dialog.getByRole('checkbox', {
    name: 'Обязательная характеристика: Бренд',
    exact: true,
  })
  await expect(required).toBeChecked()
  await required.press('Space')
  await expect(required).not.toBeChecked()
  await dialog.getByRole('button', { name: 'Сохранить', exact: true }).click()
  await expect(dialog).toHaveCount(0)
  await expect(
    page.locator('#category-1').getByText('Обязательная', { exact: true }),
  ).toHaveCount(2)
})

for (const width of [320, 640, 768, 1024, 1280]) {
  test(`category overview fits ${width}px with long text and keyboard controls`, async ({
    page,
  }) => {
    await mockCategoryOverview(page, true)
    await page.setViewportSize({ width, height: 900 })
    await page.goto('/categories')
    await expect(
      page.locator('#category-3').getByText('Цвет', { exact: true }),
    ).toBeVisible()
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= window.innerWidth,
      ),
    ).toBe(true)
    const accessibility = await new AxeBuilder({ page })
      .disableRules(['color-contrast'])
      .analyze()
    expect(
      accessibility.violations.filter((item) =>
        ['serious', 'critical'].includes(item.impact ?? ''),
      ),
    ).toEqual([])
    await page.screenshot({
      path: `.tmp/category-overview-${width}.png`,
      fullPage: true,
    })
    const select = page.getByRole('button', { name: 'Категория', exact: true })
    await select.click()
    const menu = page.getByRole('group', { name: 'Категория: варианты' })
    await expect(menu.getByRole('button')).toHaveCount(4)
    const bounds = (await menu.boundingBox())!
    expect(bounds.x).toBeGreaterThanOrEqual(0)
    expect(bounds.x + bounds.width).toBeLessThanOrEqual(width)
    const menuAccessibility = await new AxeBuilder({ page })
      .disableRules(['color-contrast'])
      .analyze()
    expect(
      menuAccessibility.violations.filter((item) =>
        ['serious', 'critical'].includes(item.impact ?? ''),
      ),
    ).toEqual([])
    await page.screenshot({ path: `.tmp/category-select-menu-${width}.png` })
    await page.keyboard.press('Escape')
    await expect(menu).toHaveCount(0)
    await expect(select).toBeFocused()
  })
}

test('a failed overview refresh shows retry, never an empty assignment claim', async ({
  page,
  browserIssueGuard,
}) => {
  await mockCategoryOverview(page)
  let fail = true
  let requested = 0
  browserIssueGuard.allowApiError(500, '/api/v1/admin/categories/1/attributes')
  await page.route('**/api/v1/admin/categories/1/attributes', async (route) => {
    if (route.request().method() !== 'GET' || ++requested === 1 || !fail)
      return route.fallback()
    await route.fulfill({
      status: 500,
      json: { error: { message: 'Ошибка загрузки характеристик' } },
    })
  })
  await page.goto('/categories')
  await page
    .getByRole('button', {
      name: 'Настроить характеристики категории Керамогранит',
      exact: true,
    })
    .click()
  const dialog = page.getByRole('dialog', {
    name: 'Характеристики: Керамогранит',
  })
  await expect(
    dialog.getByRole('checkbox', {
      name: 'Обязательная характеристика: Бренд',
      exact: true,
    }),
  ).toBeVisible()
  await dialog.getByRole('button', { name: 'Сохранить', exact: true }).click()
  await expect(page.locator('#category-1').getByRole('alert')).toContainText(
    'Ошибка загрузки характеристик',
  )
  await expect(
    page.locator('#category-2').getByText('Толщина, мм'),
  ).toBeVisible()
  fail = false
  await page
    .getByRole('button', {
      name: 'Повторить загрузку характеристик категории Керамогранит',
    })
    .click()
  await expect(
    page.locator('#category-1').getByRole('heading', { name: 'Размеры' }),
  ).toBeVisible()
})

test('a failed category tree can be retried without claiming the catalog is empty', async ({
  page,
  browserIssueGuard,
}) => {
  await mockCategoryOverview(page)
  let fail = true
  browserIssueGuard.allowApiError(500, '/api/v1/admin/categories/overview')
  await page.route('**/api/v1/admin/categories/overview', async (route) => {
    if (!fail) return route.fallback()
    await route.fulfill({
      status: 500,
      json: { error: { message: 'Не удалось загрузить категории.' } },
    })
  })
  await page.goto('/categories')
  await expect(page.getByRole('alert')).toContainText(
    'Не удалось загрузить категории.',
  )
  await expect(page.getByText('Категорий пока нет.')).toHaveCount(0)
  fail = false
  await page
    .getByRole('button', { name: 'Повторить загрузку', exact: true })
    .click()
  await expect(page.locator('#category-1')).toBeVisible()
  await expect(page.getByRole('alert')).toHaveCount(0)
})

for (const status of [422, 500]) {
  test(`failed category deletion ${status} stays visible inside confirmation and preserves the overview`, async ({
    page,
    browserIssueGuard,
  }) => {
    await mockCategoryOverview(page)
    browserIssueGuard.allowApiError(status, '/api/v1/admin/categories/1')
    await page.route('**/api/v1/admin/categories/1', (route) =>
      route.fulfill({
        status,
        json: { error: { message: 'Не удалось удалить категорию.' } },
      }),
    )
    await page.goto('/categories')
    await page
      .getByRole('button', {
        name: 'Удалить категорию Керамогранит',
        exact: true,
      })
      .click()
    const dialog = page.getByRole('dialog', { name: 'Удалить категорию?' })
    await dialog.getByRole('button', { name: 'Удалить', exact: true }).click()
    await expect(dialog.getByRole('alert')).toContainText(
      'Не удалось удалить категорию.',
    )
    await dialog.getByRole('button', { name: 'Отмена', exact: true }).click()
    await expect(dialog).toHaveCount(0)
    await expect(page.locator('#category-1')).toBeVisible()
    await expect(
      page.getByRole('button', { name: 'Повторить загрузку', exact: true }),
    ).toHaveCount(0)
  })
}

test('deleting the selected category resets the dropdown to all remaining categories', async ({
  page,
}) => {
  await mockCategoryOverview(page)
  let deleted = false
  await page.route('**/api/v1/admin/categories/overview', (route) => {
    if (!deleted) return route.fallback()
    return route.fulfill({
      json: {
        data: overviewCategories.map((root) => ({
          ...root,
          attributes: [],
          attribute_groups: [],
          children: root.children
            .filter((child) => child.id !== 2)
            .map((child) => ({
              ...child,
              attributes: [],
              attribute_groups: [],
            })),
        })),
      },
    })
  })
  await page.route('**/api/v1/admin/categories/2', (route) => {
    deleted = true
    return route.fulfill({ status: 204 })
  })
  await page.goto('/categories')
  const select = page.getByRole('button', { name: 'Категория', exact: true })
  await select.click()
  await page
    .getByRole('group', { name: 'Категория: варианты' })
    .getByRole('button', { name: 'Керамогранит / Для пола', exact: true })
    .click()
  await page
    .getByRole('button', { name: 'Удалить категорию Для пола', exact: true })
    .click()
  await page
    .getByRole('dialog', { name: 'Удалить категорию?' })
    .getByRole('button', { name: 'Удалить', exact: true })
    .click()
  await expect(select).toContainText('Все категории')
  await expect(page.locator('#category-2')).toHaveCount(0)
  await expect(page.locator('#category-3')).toBeVisible()
  await expect(page.locator('article')).toHaveCount(2)
})
