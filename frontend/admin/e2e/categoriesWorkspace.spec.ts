import AxeBuilder from './approvedAccessibility'
import { expect, test } from './fixtures'
import { mockCategoryOverview } from './categoryOverviewApi'

for (const width of [320, 640, 768, 1024, 1280, 1440]) {
  for (const selected of [false, true]) {
    test(`category workspace ${selected ? 'selected' : 'all'} fits ${width}px with long content`, async ({
      page,
    }) => {
      await mockCategoryOverview(page, true)
      await page.setViewportSize({ width, height: 1000 })
      await page.goto(selected ? '/categories?category=1' : '/categories')
      const article = page.locator('#category-1')
      await expect(article).toBeVisible()
      await expect(page.getByRole('article')).toHaveCount(selected ? 1 : 3)
      await expect(
        article.getByText(
          'ОченьДлинноеНазваниеХарактеристикиМатериалаБезПробеловДляПроверкиПереноса',
          { exact: true },
        ),
      ).toBeVisible()
      const navigation = page.getByRole('navigation', {
        name: 'Выбор категории',
        exact: true,
      })
      const mobileSelect = page.getByRole('button', {
        name: 'Категория',
        exact: true,
      })
      if (width >= 1024) {
        await expect(navigation).toBeVisible()
        await expect(mobileSelect).toBeHidden()
        const sidebarBounds = (await navigation.boundingBox())!
        const articleBounds = (await article.boundingBox())!
        expect(sidebarBounds.x + sidebarBounds.width).toBeLessThanOrEqual(
          articleBounds.x,
        )
        await expect(
          navigation.getByRole('button', {
            name: selected
              ? /^Керамогранит для общественных/
              : /^Все категории/,
          }),
        ).toHaveAttribute('aria-current', 'true')
      } else {
        await expect(navigation).toBeHidden()
        await expect(mobileSelect).toBeVisible()
        const selectBounds = (await mobileSelect.boundingBox())!
        const articleBounds = (await article.boundingBox())!
        expect(selectBounds.y + selectBounds.height).toBeLessThanOrEqual(
          articleBounds.y,
        )
      }
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= innerWidth,
        ),
      ).toBe(true)
      const accessibility = await new AxeBuilder({ page }).analyze()
      expect(
        accessibility.violations.filter((item) =>
          ['serious', 'critical'].includes(item.impact ?? ''),
        ),
      ).toEqual([])
      await page.screenshot({
        path: `../../.tmp/categories-workspace-${selected ? 'selected' : 'all'}-${width}.png`,
        fullPage: true,
        animations: 'disabled',
      })
    })
  }
}

for (const width of [320, 1440]) {
  test(`category selection persists in URL and keyboard navigation works at ${width}px`, async ({
    page,
  }) => {
    await mockCategoryOverview(page)
    await page.setViewportSize({ width, height: 1000 })
    await page.goto('/categories')
    if (width >= 1024) {
      const navigation = page.getByRole('navigation', {
        name: 'Выбор категории',
        exact: true,
      })
      const floor = navigation.getByRole('button', { name: /^Для пола/ })
      await floor.focus()
      await floor.press('Enter')
      await expect(floor).toBeFocused()
      await expect(floor).toHaveAttribute('aria-current', 'true')
    } else {
      const select = page.getByRole('button', {
        name: 'Категория',
        exact: true,
      })
      await select.focus()
      await select.press('Enter')
      await page
        .getByRole('group', { name: 'Категория: варианты', exact: true })
        .getByRole('button', { name: 'Керамогранит / Для пола', exact: true })
        .press('Enter')
      await expect(select).toBeFocused()
    }
    await expect(page).toHaveURL(/category=2/)
    await expect(page.getByRole('article')).toHaveCount(1)
    await expect(page.locator('#category-2')).toBeVisible()
    await page.reload()
    await expect(page.locator('#category-2')).toBeVisible()
    await expect(page.getByRole('article')).toHaveCount(1)
    await page.screenshot({
      path: `../../.tmp/categories-workspace-selected-example-${width}.png`,
      fullPage: true,
      animations: 'disabled',
    })
    await page
      .locator('#category-2')
      .getByRole('button', { name: 'Керамогранит', exact: true })
      .press('Enter')
    await expect(page).toHaveURL(/category=1/)
    await expect(page.locator('#category-1')).toBeFocused()
    await expect(page.getByRole('article')).toHaveCount(1)
    await page
      .locator('#category-1')
      .getByRole('button', { name: 'Для стен', exact: true })
      .press('Enter')
    await expect(page).toHaveURL(/category=3/)
    await expect(page.locator('#category-3')).toBeFocused()
    await expect(page.getByRole('article')).toHaveCount(1)
  })

  test(`category load error retains URL selection and retries at ${width}px`, async ({
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
    await page.setViewportSize({ width, height: 900 })
    await page.goto('/categories?category=2')
    await expect(page.getByRole('alert')).toContainText(
      'Не удалось загрузить категории.',
    )
    await expect(page.getByRole('article')).toHaveCount(0)
    await expect(page.getByText('Категорий пока нет.')).toHaveCount(0)
    await expect(page).toHaveURL(/category=2/)
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: `../../.tmp/categories-workspace-error-${width}.png`,
      fullPage: true,
      animations: 'disabled',
    })
    fail = false
    await page
      .getByRole('button', { name: 'Повторить загрузку', exact: true })
      .click()
    await expect(page.locator('#category-2')).toBeVisible()
    await expect(page.getByRole('article')).toHaveCount(1)
    await expect(page.getByRole('alert')).toHaveCount(0)
  })
}

test('category search preserves hierarchy and selection across reload', async ({
  page,
}) => {
  await mockCategoryOverview(page)
  await page.setViewportSize({ width: 1440, height: 1000 })
  await page.goto('/categories?category=3')
  const navigation = page.getByRole('navigation', {
    name: 'Выбор категории',
    exact: true,
  })
  const search = page.getByRole('searchbox', {
    name: 'Поиск категорий',
    exact: true,
  })
  await search.fill('пола')
  await expect(
    navigation.getByRole('button', { name: /^Керамогранит/ }),
  ).toBeVisible()
  await expect(
    navigation.getByRole('button', { name: /^Для пола/ }),
  ).toBeVisible()
  await expect(
    navigation.getByRole('button', { name: /^Для стен/ }),
  ).toHaveCount(0)
  await expect(page.locator('#category-3')).toBeVisible()
  await expect(page).toHaveURL(/search=/)
  await page.reload()
  await expect(search).toHaveValue('пола')
  await expect(page.locator('#category-3')).toBeVisible()
  await search.fill('dlya-sten')
  await expect(
    navigation.getByRole('button', { name: /^Для стен/ }),
  ).toBeVisible()
  await expect(
    navigation.getByRole('button', { name: /^Для пола/ }),
  ).toHaveCount(0)
  await search.fill('несуществующая категория')
  await expect(
    navigation.getByRole('button', { name: /^Для пола/ }),
  ).toHaveCount(0)
  await expect(
    page.getByRole('status').filter({ hasText: /не найден/ }),
  ).toBeVisible()
  await search.clear()
  await expect(
    navigation.getByRole('button', { name: /^Для стен/ }),
  ).toBeVisible()
  await expect(page).toHaveURL('/categories?category=3')
})

test('an unknown URL category resets to all available categories', async ({
  page,
}) => {
  await mockCategoryOverview(page)
  await page.goto('/categories?category=99999')
  await expect(page.getByRole('article')).toHaveCount(3)
  await expect(page).toHaveURL('/categories')
  await expect(
    page
      .getByRole('navigation', { name: 'Выбор категории', exact: true })
      .getByRole('button', { name: /^Все категории/ }),
  ).toHaveAttribute('aria-current', 'true')
})

for (const width of [320, 1440]) {
  test(`empty category workspace remains usable at ${width}px`, async ({
    page,
  }) => {
    await mockCategoryOverview(page)
    await page.route('**/api/v1/admin/categories/overview', (route) =>
      route.fulfill({ json: { data: [] } }),
    )
    await page.setViewportSize({ width, height: 900 })
    await page.goto('/categories?category=1')
    await expect(
      page.getByRole('status').filter({ hasText: 'Категорий пока нет.' }),
    ).toBeVisible()
    await expect(page.getByRole('article')).toHaveCount(0)
    await expect(
      page.getByRole('button', { name: 'Добавить категорию', exact: true }),
    ).toBeEnabled()
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: `../../.tmp/categories-workspace-empty-${width}.png`,
      fullPage: true,
      animations: 'disabled',
    })
  })
}

test('loading category workspace keeps selection until the category tree arrives', async ({
  page,
}) => {
  await mockCategoryOverview(page)
  let release!: () => void
  const pending = new Promise<void>((resolve) => {
    release = resolve
  })
  await page.route('**/api/v1/admin/categories/overview', async (route) => {
    await pending
    await route.fallback()
  })
  await page.goto('/categories?category=2')
  await expect(page.getByRole('status')).toContainText('Загрузка категорий')
  await expect(page.getByRole('article')).toHaveCount(0)
  await expect(page).toHaveURL(/category=2/)
  release()
  await expect(page.locator('#category-2')).toBeVisible()
  await expect(page.getByRole('article')).toHaveCount(1)
  await expect(page).toHaveURL(/category=2/)
})
