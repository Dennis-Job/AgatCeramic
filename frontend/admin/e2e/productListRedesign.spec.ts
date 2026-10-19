import AxeBuilder from './approvedAccessibility'
import { expect, test } from './fixtures'
import { mockCatalogApi } from './catalogApi'
import { createDeferredApiRequests } from './deferredApi'

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

for (const width of [320, 640, 768, 1024, 1280, 1440]) {
  test(`product filters and sorting fit and remain accessible at ${width}px`, async ({
    page,
  }) => {
    await mockCatalogApi(page)
    await page.setViewportSize({ width, height: 1024 })
    await page.goto('/products')
    await expect(page.getByText('Монте Тиберио', { exact: true })).toBeVisible()
    const toggle = page.getByRole('button', { name: /^Фильтры/ })
    await toggle.click()
    await expect(
      page.getByRole('group', { name: 'Распродажа', exact: true }),
    ).toBeVisible()
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
