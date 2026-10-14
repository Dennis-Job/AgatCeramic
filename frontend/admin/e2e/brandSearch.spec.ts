import AxeBuilder from './approvedAccessibility'
import { expect, test, type Page } from './fixtures'
import { mockAdminBaseline } from './adminBaselineApi'
import { brand } from './catalogApi'
import { createDeferredApiRequests } from './deferredApi'

async function mockSearch(page: Page) {
  await mockAdminBaseline(page)
  const deferred = createDeferredApiRequests()
  const requests: { search: string; page: number }[] = []
  let failNext = false
  await page.route('**/api/v1/admin/brands**', async (route) => {
    const url = new URL(route.request().url())
    const search = url.searchParams.get('search') ?? ''
    const currentPage = Number(url.searchParams.get('page') ?? 1)
    requests.push({ search, page: currentPage })
    await deferred.wait(search)
    if (failNext) {
      failNext = false
      return route.fulfill({
        status: 500,
        json: { error: { message: 'Поиск временно недоступен' } },
      })
    }
    const item = search
      ? {
          ...brand,
          name:
            currentPage === 1 ? 'Marazzi: найденный бренд' : 'Marazzi Италия',
          slug: 'marazzi-found',
        }
      : { ...brand, name: currentPage === 1 ? brand.name : 'Italon' }
    return route.fulfill({
      json: {
        data: search === 'несуществующий' ? [] : [item],
        meta: {
          current_page: currentPage,
          last_page: search === 'несуществующий' ? 1 : 2,
          per_page: 25,
          total: search === 'несуществующий' ? 0 : 30,
        },
      },
    })
  })
  return {
    requests,
    deferred,
    fail: () => {
      failNext = true
    },
  }
}

test('brand search resets page, trims input, retains query on pagination and clears', async ({
  page,
}) => {
  const api = await mockSearch(page)
  await page.goto('/brands')
  await page.getByRole('button', { name: 'Следующая страница' }).click()
  await expect(
    page.getByRole('heading', { name: 'Italon', exact: true }),
  ).toBeVisible()
  await page.getByRole('textbox', { name: 'Поиск брендов' }).fill('  MARAZZI  ')
  await expect(
    page.getByRole('heading', {
      name: 'Marazzi: найденный бренд',
      exact: true,
    }),
  ).toBeVisible()
  expect(api.requests.at(-1)).toEqual({ search: 'MARAZZI', page: 1 })
  await page.getByRole('button', { name: 'Следующая страница' }).click()
  await expect(
    page.getByRole('heading', { name: 'Marazzi Италия', exact: true }),
  ).toBeVisible()
  expect(api.requests.at(-1)).toEqual({ search: 'MARAZZI', page: 2 })
  await page.getByRole('button', { name: 'Очистить поле', exact: true }).click()
  await expect(
    page.getByRole('heading', { name: brand.name, exact: true }),
  ).toBeVisible()
  expect(api.requests.at(-1)).toEqual({ search: '', page: 1 })
  await expect(
    page.getByRole('textbox', { name: 'Поиск брендов' }),
  ).toHaveValue('')
})

test('brand search separates no matches from an API failure and supports retry', async ({
  page,
  browserIssueGuard,
}) => {
  browserIssueGuard.allowApiError(500, '/api/v1/admin/brands')
  const api = await mockSearch(page)
  await page.goto('/brands')
  const search = page.getByRole('textbox', { name: 'Поиск брендов' })
  await search.fill('несуществующий')
  await expect(
    page.getByRole('status').filter({ hasText: 'Бренды не найдены' }),
  ).toBeVisible()
  api.fail()
  await search.fill('ошибка')
  await expect(page.getByRole('alert')).toContainText(
    'Поиск временно недоступен',
  )
  await expect(
    page.getByRole('status').filter({ hasText: 'Бренды не найдены' }),
  ).toHaveCount(0)
  await page
    .getByRole('button', { name: 'Повторить загрузку', exact: true })
    .click()
  await expect(
    page.getByRole('heading', {
      name: 'Marazzi: найденный бренд',
      exact: true,
    }),
  ).toBeVisible()
  expect(api.requests.at(-1)).toEqual({ search: 'ошибка', page: 1 })
})

test('a late brand search does not replace a newer result', async ({
  page,
}) => {
  const api = await mockSearch(page)
  await page.goto('/brands')
  const delayed = api.deferred.defer('старый')
  const search = page.getByRole('textbox', { name: 'Поиск брендов' })
  await search.fill('старый')
  await delayed.requested
  await search.fill('несуществующий')
  await expect(
    page.getByRole('status').filter({ hasText: 'Бренды не найдены' }),
  ).toBeVisible()
  const oldResponse = page.waitForResponse(
    (response) =>
      new URL(response.url()).searchParams.get('search') === 'старый',
  )
  delayed.release()
  await oldResponse
  await expect(
    page.getByRole('status').filter({ hasText: 'Бренды не найдены' }),
  ).toBeVisible()
  await expect(
    page.getByRole('list', { name: 'Бренды и подробная информация' }),
  ).toHaveCount(0)
})

test('brand search is accessible and fits supported widths', async ({
  page,
}) => {
  await mockSearch(page)
  await page.goto('/brands')
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 720 })
    const search = page.getByRole('textbox', { name: 'Поиск брендов' })
    await expect(search).toBeInViewport()
    await search.fill('Marazzi')
    await expect(
      page.getByRole('heading', {
        name: 'Marazzi: найденный бренд',
        exact: true,
      }),
    ).toBeVisible()
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
    await page.screenshot({
      path: `../../.tmp/brands-search/search-${width}.png`,
    })
  }
})
