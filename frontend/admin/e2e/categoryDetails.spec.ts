import AxeBuilder from '@axe-core/playwright'
import type { Page, Route } from '@playwright/test'
import { expect, test } from './fixtures'
import { mockCategoryOverview } from './categoryOverviewApi'
import { attribute, attributeGroup } from './catalogApi'

async function holdDetails(page: Page) {
  const requests: Route[] = []
  await page.route(
    /\/api\/v1\/admin\/categories\/\d+\/(attributes|attribute-groups)$/,
    (route) => {
      requests.push(route)
    },
  )
  return requests
}

async function releaseDetails(
  page: Page,
  requests: Route[],
  name: string,
  fail = false,
) {
  await Promise.all(
    requests.map(async (route) => {
      const isAttributes = route.request().url().endsWith('/attributes')
      const finished = page
        .waitForResponse((response) => response.request() === route.request())
        .then((response) => response.finished())
      await route.fulfill({
        status: fail && isAttributes ? 500 : 200,
        json:
          fail && isAttributes
            ? { error: { message: 'Устаревшая ошибка' } }
            : {
                data: [
                  isAttributes
                    ? { ...attribute, name, is_filterable: false }
                    : { ...attributeGroup, name: `Группа: ${name}` },
                ],
              },
      })
      await finished
    }),
  )
  // Let fetch JSON processing and Vue rendering finish after the response bodies.
  await page.evaluate(
    () =>
      new Promise<void>((resolve) => {
        requestAnimationFrame(() => requestAnimationFrame(() => resolve()))
      }),
  )
}

for (const width of [320, 640, 768, 1024, 1280]) {
  test(`category details keep current loading and assignments at ${width}px`, async ({
    page,
  }) => {
    await mockCategoryOverview(page)
    const requests = await holdDetails(page)
    await page.setViewportSize({ width, height: 900 })
    await page.goto('/categories')
    await page
      .locator('#category-title-1')
      .getByRole('button', { name: 'Керамогранит', exact: true })
      .click()
    await expect.poll(() => requests.length).toBe(2)
    await page.keyboard.press('Escape')
    await expect(page.getByRole('dialog')).toHaveCount(0)
    const opener = page
      .locator('#category-title-2')
      .getByRole('button', { name: 'Для пола', exact: true })
    await opener.click()
    await expect.poll(() => requests.length).toBe(4)
    await releaseDetails(
      page,
      requests.slice(0, 2),
      'Устаревшая характеристика',
    )
    const dialog = page.getByRole('dialog', { name: 'Для пола', exact: true })
    await expect(dialog.getByRole('status')).toContainText('Загрузка сведений')
    await expect(dialog.getByText('Группы не назначены.')).toHaveCount(0)
    await page.screenshot({
      path: `.tmp/category-details-loading-${width}.png`,
    })
    await releaseDetails(page, requests.slice(2), 'Актуальная характеристика')
    await expect(dialog.getByRole('status')).toHaveCount(0)
    await expect(dialog.getByText(/^Актуальная характеристика/)).toHaveCount(1)
    await expect(
      dialog.getByText('Группа: Актуальная характеристика'),
    ).toBeVisible()
    await expect(dialog.getByText('Устаревшая характеристика')).toHaveCount(0)
    const bounds = (await dialog.boundingBox())!
    expect(bounds.x).toBeGreaterThanOrEqual(0)
    expect(bounds.x + bounds.width).toBeLessThanOrEqual(width)
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= window.innerWidth,
      ),
    ).toBe(true)
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
      path: `.tmp/category-details-success-${width}.png`,
    })
    await dialog.getByRole('button', { name: 'Закрыть', exact: true }).click()
    await expect(dialog).toHaveCount(0)
    await expect(opener).toBeFocused()
  })
}

for (const outcome of ['success', 'error'] as const) {
  test(`reopened category ignores the previous request ${outcome}`, async ({
    page,
    browserIssueGuard,
  }) => {
    await mockCategoryOverview(page)
    if (outcome === 'error')
      browserIssueGuard.allowApiError(
        500,
        '/api/v1/admin/categories/1/attributes',
      )
    const requests = await holdDetails(page)
    await page.goto('/categories')
    const opener = page.locator('#category-title-1').getByRole('button')
    await opener.click()
    await expect.poll(() => requests.length).toBe(2)
    await page
      .getByRole('dialog')
      .getByRole('button', { name: 'Закрыть' })
      .click()
    await opener.click()
    await expect.poll(() => requests.length).toBe(4)
    await releaseDetails(page, requests.slice(2), 'Новая характеристика')
    await releaseDetails(
      page,
      requests.slice(0, 2),
      'Устаревшая характеристика',
      outcome === 'error',
    )
    const dialog = page.getByRole('dialog', {
      name: 'Керамогранит',
      exact: true,
    })
    await expect(dialog).toBeVisible()
    await expect(dialog.getByText(/^Новая характеристика/)).toHaveCount(1)
    await expect(dialog.getByText('Группа: Новая характеристика')).toBeVisible()
    await expect(dialog.getByText('Устаревшая характеристика')).toHaveCount(0)
    await expect(page.getByRole('alert')).toHaveCount(0)
    await page.keyboard.press('Escape')
    await expect(dialog).toHaveCount(0)
    await expect(opener).toBeFocused()
  })
}
