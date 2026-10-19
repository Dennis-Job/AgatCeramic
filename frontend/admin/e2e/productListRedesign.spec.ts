import AxeBuilder from './approvedAccessibility'
import { expect, test } from './fixtures'
import { mockCatalogApi, product } from './catalogApi'
import { createDeferredApiRequests } from './deferredApi'

test('products paginate by 50 and keep activity inside collapsible filters', async ({
  page,
}) => {
  await mockCatalogApi(page)
  const requests: URLSearchParams[] = []
  await page.route('**/api/v1/admin/products?*', (route) => {
    const query = new URL(route.request().url()).searchParams
    requests.push(query)
    const currentPage = Number(query.get('page') ?? 1)
    const perPage = Number(query.get('per_page') ?? 15)
    const total = query.has('is_active') ? 50 : 51
    const from = (currentPage - 1) * perPage
    const data = Array.from(
      { length: Math.min(perPage, total - from) },
      (_, index) => ({
        ...product,
        id: from + index + 1,
        name: `Товар ${from + index + 1}`,
      }),
    )
    return route.fulfill({
      json: {
        data,
        meta: {
          current_page: currentPage,
          last_page: Math.ceil(total / perPage),
          per_page: perPage,
          total,
          from: from + 1,
          to: from + data.length,
        },
      },
    })
  })
  await page.goto('/products')
  const rows = page
    .getByRole('table', { name: 'Таблица товаров' })
    .locator('tbody tr')
  await expect(rows).toHaveCount(50)
  await expect(
    page.getByText('Показано 1–50 из 51', { exact: true }),
  ).toBeVisible()
  await page
    .getByRole('button', { name: 'Следующая страница', exact: true })
    .click()
  await expect(rows).toHaveCount(1)
  await expect(
    page.getByText('Показано 51–51 из 51', { exact: true }),
  ).toBeVisible()
  const toggle = page.getByRole('button', { name: /^Фильтры/ })
  const activity = page.getByRole('group', {
    name: 'Активность',
    includeHidden: true,
  })
  await expect(activity).toBeHidden()
  await toggle.click()
  await activity
    .getByRole('radio', { name: 'Активные', exact: true })
    .locator('..')
    .click()
  await expect(rows).toHaveCount(50)
  await expect(
    page.getByText('Показано 1–50 из 50', { exact: true }),
  ).toBeVisible()
  expect(requests.at(-1)?.get('is_active')).toBe('1')
  expect(requests.at(-1)?.get('page') ?? '1').toBe('1')
  expect(requests.every((query) => query.get('per_page') === '50')).toBe(true)
  await toggle.click()
  await expect(activity).toBeHidden()
  await expect(
    page.getByRole('button', { name: 'Убрать фильтр: Активные', exact: true }),
  ).toBeVisible()
  await page
    .getByRole('button', { name: 'Убрать фильтр: Активные', exact: true })
    .click()
  await expect(
    page.getByText('Показано 1–50 из 51', { exact: true }),
  ).toBeVisible()
  await toggle.click()
  await expect(
    activity.getByRole('radio', { name: 'Все', exact: true }),
  ).toBeChecked()
})

test('a superseded sorting response cannot roll back the sort used by a newer filter request', async ({
  page,
}) => {
  const deferredRequests = createDeferredApiRequests()
  await mockCatalogApi(page, { deferredRequests })
  await page.goto('/products')
  await expect(page.getByText('Монте Тиберио', { exact: true })).toBeVisible()
  const sorting = page.getByRole('button', { name: /^Сортировка:/ })
  await sorting.click()
  const pending = deferredRequests.defer('/admin/products')
  await page
    .getByRole('menuitemradio', { name: 'Наименованию', exact: true })
    .click()
  await pending.requested
  const filtered = page.waitForResponse((response) => {
    const url = new URL(response.url())
    return (
      url.pathname.endsWith('/admin/products') &&
      url.searchParams.get('search') === 'монте' &&
      url.searchParams.get('sort') === 'name'
    )
  })
  await page.getByRole('textbox', { name: 'Поиск', exact: true }).fill('монте')
  await filtered
  await expect(sorting).toHaveAttribute('aria-busy', 'false')
  const late = page.waitForResponse((response) => {
    const url = new URL(response.url())
    return (
      url.pathname.endsWith('/admin/products') &&
      !url.searchParams.has('search') &&
      url.searchParams.get('sort') === 'name'
    )
  })
  pending.release()
  await late
  await expect(sorting).toContainText('Название: А–Я')
})

test('keyboard sorting keeps focus through a pending request and filter reset returns to search', async ({
  page,
}) => {
  const deferredRequests = createDeferredApiRequests()
  await mockCatalogApi(page, { deferredRequests })
  await page.goto('/products')
  await expect(page.getByText('Монте Тиберио', { exact: true })).toBeVisible()
  const sorting = page.getByRole('button', { name: /^Сортировка:/ })
  await sorting.focus()
  await page.keyboard.press('ArrowDown')
  await page.keyboard.press('ArrowDown')
  const field = page.getByRole('menuitemradio', {
    name: 'Наименованию',
    exact: true,
  })
  await expect(field).toBeFocused()
  const pending = deferredRequests.defer('/admin/products')
  await page.keyboard.press('Enter')
  await pending.requested
  await expect(field).toBeFocused()
  await expect(field).toHaveAttribute('aria-disabled', 'true')
  await page.keyboard.press('Escape')
  await expect(sorting).toBeFocused()
  await expect(sorting).toHaveAttribute('aria-busy', 'true')
  await page.keyboard.press('ArrowDown')
  await page.keyboard.press('ArrowDown')
  await expect(field).toBeFocused()
  pending.release()
  await expect(sorting).toHaveAttribute('aria-busy', 'false')
  await expect(field).toBeFocused()
  await page.keyboard.press('End')
  await expect(
    page.getByRole('menuitemradio', { name: 'А–Я', exact: true }),
  ).toBeFocused()
  await page.keyboard.press('Escape')
  await page.getByRole('textbox', { name: 'Поиск', exact: true }).fill('монте')
  const reset = page.getByRole('button', { name: 'Сбросить фильтры' })
  await reset.focus()
  await page.keyboard.press('Enter')
  await expect(
    page.getByRole('textbox', { name: 'Поиск', exact: true }),
  ).toBeFocused()
})

test('portalled product actions leave the menu without losing the position in the table', async ({
  page,
}) => {
  await mockCatalogApi(page)
  await page.goto('/products')
  const trigger = page.getByRole('button', {
    name: 'Действия товара Монте Тиберио',
    exact: true,
  })
  await trigger.focus()
  await page.keyboard.press('ArrowDown')
  await expect(
    page.getByRole('button', {
      name: 'Создать похожий товар Монте Тиберио',
      exact: true,
    }),
  ).toBeFocused()
  await page.keyboard.press('Shift+Tab')
  await expect(trigger).toBeFocused()
  await expect(trigger).toHaveAttribute('aria-expanded', 'false')
  await page.keyboard.press('ArrowDown')
  await page.keyboard.press('Tab')
  await expect(
    page.getByRole('button', {
      name: 'Удалить товар Монте Тиберио',
      exact: true,
    }),
  ).toBeFocused()
  await page.keyboard.press('Tab')
  await expect(trigger).toHaveAttribute('aria-expanded', 'false')
  expect(await page.evaluate(() => document.activeElement?.tagName)).not.toBe(
    'BODY',
  )
})

test('collapsed product filters keep removable conditions and preserve sorting on reset', async ({
  page,
}) => {
  await mockCatalogApi(page)
  await page.goto('/products')
  const filters = page.getByRole('search')
  const toggle = filters.getByRole('button', { name: /^Фильтры/ })
  await expect(toggle).toHaveAttribute('aria-expanded', 'false')
  await toggle.click()
  await filters.getByLabel('Категория', { exact: true }).click()
  await page.getByRole('button', { name: 'Керамогранит', exact: true }).click()
  await filters
    .getByRole('radio', { name: 'Не распродажа', exact: true })
    .locator('..')
    .click()
  await expect(toggle).toContainText('2')
  await toggle.click()
  await expect(filters.getByLabel('Категория', { exact: true })).toBeHidden()
  await expect(
    filters.getByRole('button', { name: 'Убрать фильтр: Керамогранит' }),
  ).toBeVisible()
  const sorting = page.getByRole('button', { name: /^Сортировка:/ })
  await sorting.click()
  await page
    .getByRole('menuitemradio', { name: 'Наименованию', exact: true })
    .click()
  await expect(sorting).toContainText('Название: А–Я')
  await page.keyboard.press('Escape')
  await filters
    .getByRole('button', { name: 'Убрать фильтр: Керамогранит' })
    .click()
  await expect(toggle).toContainText('1')
  const reset = page.waitForRequest((request) => {
    const url = new URL(request.url())
    return (
      url.pathname.endsWith('/admin/products') &&
      !url.searchParams.has('is_on_sale') &&
      url.searchParams.get('sort') === 'name' &&
      url.searchParams.get('direction') === 'asc'
    )
  })
  await filters.getByRole('button', { name: 'Сбросить фильтры' }).click()
  await reset
  await expect(sorting).toContainText('Название: А–Я')
  await expect(toggle).not.toContainText('1')
})

test('sort menu keeps all supported fields and restores focus after Escape', async ({
  page,
}) => {
  await mockCatalogApi(page)
  await page.goto('/products')
  await expect(page.getByText('Монте Тиберио', { exact: true })).toBeVisible()
  const sorting = page.getByRole('button', { name: /^Сортировка:/ })
  await sorting.focus()
  await page.keyboard.press('ArrowDown')
  const menu = page.getByRole('menu', { name: 'Параметры сортировки' })
  await expect(menu.getByRole('menuitemradio')).toHaveCount(6)
  await expect(
    menu.getByRole('menuitemradio', { name: 'Дате создания', exact: true }),
  ).toHaveAttribute('aria-checked', 'true')
  await expect(
    menu.getByRole('menuitemradio', { name: 'Сначала новые', exact: true }),
  ).toHaveAttribute('aria-checked', 'true')
  await page.keyboard.press('Escape')
  await expect(menu).toBeHidden()
  await expect(sorting).toBeFocused()
  for (const name of ['Цена', 'Остаток', 'Статус'])
    await expect(
      page
        .getByRole('columnheader', { name: new RegExp(name) })
        .getByRole('button'),
    ).toHaveCount(0)
})

test('four filters retain useful select widths with large counts at 1024px', async ({
  page,
}) => {
  await mockCatalogApi(page)
  await page.route('**/api/v1/admin/products/filter-counts*', (route) =>
    route.fulfill({
      json: {
        data: { active: 999999, hidden: 999999, sale: 999999, regular: 999999 },
      },
    }),
  )
  await page.setViewportSize({ width: 1024, height: 900 })
  await page.goto('/products')
  await page.getByRole('button', { name: /^Фильтры/ }).click()
  const filters = page.getByRole('search')
  await expect(
    filters.getByRole('radio', { name: 'Активные', exact: true }).locator('..'),
  ).toContainText(/999\s999/)
  for (const name of ['Категория', 'Бренд']) {
    const select = filters.getByLabel(name, { exact: true })
    expect((await select.boundingBox())!.width).toBeGreaterThanOrEqual(128)
    const textWidth = await select
      .locator('span')
      .first()
      .evaluate((element) => element.getBoundingClientRect().width)
    expect(textWidth).toBeGreaterThan(60)
  }
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= innerWidth,
    ),
  ).toBe(true)
  expect(
    await filters.evaluate((element) =>
      [...element.querySelectorAll('label')].every(
        (label) => label.scrollWidth <= label.clientWidth,
      ),
    ),
  ).toBe(true)
  await filters.screenshot({
    path: '.tmp/products-redesign/four-filters-large-1024.png',
  })
})

for (const width of [320, 640, 768, 1024, 1035, 1280, 1349, 1440]) {
  test(`product filters and sorting fit and remain accessible at ${width}px`, async ({
    page,
  }) => {
    await mockCatalogApi(page)
    await page.setViewportSize({ width, height: 1024 })
    await page.goto('/products')
    await expect(page.getByText('Монте Тиберио', { exact: true })).toBeVisible()
    const table = page.getByRole('table', { name: 'Таблица товаров' })
    const row = table.locator('tbody tr').first()
    const category = row.locator('td').nth(2)
    await expect(category.locator('span')).toHaveClass(/bg-blue-light-50/)
    for (const index of [2, 5]) {
      await expect(table.locator('th').nth(index)).toHaveCSS(
        'text-align',
        'center',
      )
      const cell = row.locator('td').nth(index)
      const alignment = await cell.evaluate((element) => {
        const bounds = element.getBoundingClientRect()
        return [...element.querySelectorAll('span')].every((badge) => {
          const rect = badge.getBoundingClientRect()
          return (
            Math.abs(
              rect.left + rect.width / 2 - bounds.left - bounds.width / 2,
            ) <= 1
          )
        })
      })
      expect(alignment).toBe(true)
    }
    const actionSpace = await row
      .locator('td')
      .last()
      .evaluate((cell) => {
        const buttons = [...cell.querySelectorAll('button')]
        const contentWidth = buttons.reduce(
          (sum, button) => sum + button.getBoundingClientRect().width,
          0,
        )
        const style = getComputedStyle(cell)
        return (
          cell.getBoundingClientRect().width -
          contentWidth -
          parseFloat(style.paddingLeft) -
          parseFloat(style.paddingRight)
        )
      })
    expect(actionSpace).toBeLessThanOrEqual(1)
    const toggle = page.getByRole('button', { name: /^Фильтры/ })
    await toggle.click()
    await expect(
      page.getByRole('group', { name: 'Распродажа', exact: true }),
    ).toBeVisible()
    const filters = page.getByRole('search')
    const sale = filters.getByRole('group', { name: 'Распродажа', exact: true })
    await expect(
      sale
        .getByRole('radio', { name: 'Распродажа', exact: true })
        .locator('..'),
    ).toContainText('16')
    await page.evaluate(() => document.fonts.ready)
    for (const group of [
      sale,
      filters.getByRole('group', { name: 'Активность', exact: true }),
    ]) {
      const all = group
        .getByRole('radio', { name: 'Все', exact: true })
        .locator('..')
      expect(
        await all.evaluate((label) => {
          const text = label.querySelector('span > span')!
          return (
            text.getBoundingClientRect().height <=
            parseFloat(getComputedStyle(text).lineHeight) + 1
          )
        }),
      ).toBe(true)
    }
    await expect(sale.locator('legend')).toHaveClass('sr-only')
    await expect(
      filters.locator('p').filter({ hasText: /^(Категория|Бренд)$/ }),
    ).toHaveCount(0)
    if (width >= 1024) {
      const bounds = await Promise.all(
        [
          filters.getByLabel('Категория', { exact: true }),
          filters.getByLabel('Бренд', { exact: true }),
          sale,
          filters.getByRole('group', { name: 'Активность', exact: true }),
        ].map((control) =>
          control.evaluate((element) => {
            const rect = element.getBoundingClientRect()
            return {
              center: rect.top + rect.height / 2,
              left: rect.left,
              right: rect.right,
            }
          }),
        ),
      )
      const centers = bounds.map((rect) => rect.center)
      expect(Math.max(...centers) - Math.min(...centers)).toBeLessThanOrEqual(1)
      for (let index = 1; index < bounds.length; index++) {
        expect(bounds[index]!.left - bounds[index - 1]!.right).toBeCloseTo(
          16,
          0,
        )
      }
    }
    await page.getByRole('button', { name: /^Сортировка:/ }).click()
    await expect(
      page.getByRole('menu', { name: 'Параметры сортировки' }),
    ).toBeVisible()
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
    await page.screenshot({
      path: `.tmp/products-redesign/open-${width}.png`,
      fullPage: true,
    })
  })
}
