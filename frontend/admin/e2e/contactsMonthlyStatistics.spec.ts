import AxeBuilder from './approvedAccessibility'
import { expect, test, type Page } from './fixtures'
import { mockAdminBaseline } from './adminBaselineApi'
import { contactStatistics } from './contactStatisticsFixtures'

const metricCodes = ['partner', 'callback', 'completed', 'rejected'] as const
const contact = {
  id: 1,
  type: 'partner',
  contact: { name: 'Партнёр Керамика', phone: '+79990000000', email: null },
  message: 'Вопрос о сотрудничестве',
  source: 'site',
  status: 'new',
  assignee: null,
  assigned_at: null,
  completed_at: null,
  created_at: '2026-10-22T08:00:00Z',
}

async function mockStatistics(page: Page, longList = false) {
  await page.clock.setFixedTime(new Date('2026-10-22T09:00:00Z'))
  await mockAdminBaseline(page)
  const state = {
    empty: false,
    fail: false,
    requests: [] as string[],
    listRequests: [] as string[],
    hold: new Map<string, Promise<void>>(),
  }
  await page.route('**/api/v1/admin/contact-statistics?*', async (route) => {
    const month = new URL(route.request().url()).searchParams.get('month')!
    state.requests.push(month)
    await state.hold.get(month)
    if (state.fail)
      return route.fulfill({
        status: 503,
        json: { error: { message: 'Статистика временно недоступна.' } },
      })
    const data = contactStatistics(month, state.empty)
    // Distinct periods reveal stale responses overwriting the latest selection.
    if (!state.empty && month === '2025-02')
      data.metrics.partner = {
        count: 27,
        previous_count: 10,
        change_percent: 170,
      }
    return route.fulfill({ json: { data } })
  })
  await page.route('**/api/v1/admin/contact-requests?*', (route) => {
    const url = new URL(route.request().url())
    state.listRequests.push(url.search)
    const data = Array.from({ length: longList ? 25 : 1 }, (_, index) => ({
      ...contact,
      id: index + 1,
      contact: {
        ...contact.contact,
        name: `${contact.contact.name} ${index + 1}`,
      },
    }))
    return route.fulfill({
      json: {
        data,
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: data.length,
        },
      },
    })
  })
  return state
}

async function selectMonth(page: Page, year: string, month: string) {
  const trigger = page.getByRole('button', {
    name: 'Месяц статистики',
    exact: true,
  })
  await trigger.click()
  const picker = page.getByRole('region', {
    name: 'Месяц статистики: выбор месяца',
  })
  await expect(picker).toBeVisible()
  await picker.getByLabel('Год', { exact: true }).fill(year)
  await picker.getByRole('button', { name: month, exact: true }).click()
  await expect(picker).toHaveCount(0)
  await expect(trigger).toBeFocused()
}

test('monthly statistics use the selected cohort, compare the previous month and stay independent of list filters', async ({
  page,
}, testInfo) => {
  const state = await mockStatistics(page)
  await page.goto('/contacts')
  const statistics = page.getByRole('region', {
    name: 'Статистика обращений',
    exact: true,
  })
  await expect(
    statistics.getByRole('heading', { name: 'Статистика за месяц' }),
  ).toBeVisible()
  await expect(statistics.locator('[data-contact-metric]')).toHaveCount(4)
  for (const label of [
    'Заявки от партнёров',
    'Заявки перезвонить',
    'Завершено',
    'Отклонено',
  ])
    await expect(statistics.getByText(label, { exact: true })).toBeVisible()
  await expect(
    statistics.locator('[data-contact-metric="partner"]'),
  ).toContainText('+50%')
  await expect(
    statistics.locator('[data-contact-metric="callback"]'),
  ).toContainText(/[-−]25%/)
  await expect(
    statistics.locator('[data-contact-metric="completed"]'),
  ).toContainText('Нет базы')
  await expect(
    statistics.locator('[data-contact-metric="rejected"]'),
  ).toContainText('0%')
  await expect(statistics).toContainText(/неполн|не заверш|текущ|по состоянию/i)
  expect(state.requests).toEqual(['2026-10'])

  for (const width of [320, 640, 768, 1024, 1280, 1920]) {
    await page.setViewportSize({ width, height: 1000 })
    const columns = width >= 1024 ? 4 : width >= 640 ? 2 : 1
    const boxes = await Promise.all(
      metricCodes.map((code) =>
        statistics.locator(`[data-contact-metric="${code}"]`).boundingBox(),
      ),
    )
    for (let index = 0; index < 4; index++) {
      expect(boxes[index]).not.toBeNull()
      expect(boxes[index]!.width).toBeCloseTo(boxes[0]!.width, 0)
      if (index % columns)
        expect(boxes[index]!.y).toBeCloseTo(boxes[index - 1]!.y, 0)
      else if (index)
        expect(boxes[index]!.y).toBeGreaterThanOrEqual(
          boxes[index - columns]!.y + boxes[index - columns]!.height,
        )
    }
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
    await page.screenshot({
      path: testInfo.outputPath(`contact-statistics-${width}.png`),
      fullPage: width < 640,
    })
  }

  await selectMonth(page, '2025', 'Январь')
  await expect.poll(() => state.requests.at(-1)).toBe('2025-01')
  await expect(
    statistics.getByRole('button', { name: 'Месяц статистики', exact: true }),
  ).toContainText(/январь.*2025/i)
  await expect(statistics).toContainText(/Предыдущий месяц: декабрь.*2024/i)
  expect(
    state.listRequests.every(
      (query) => !new URLSearchParams(query).has('month'),
    ),
  ).toBe(true)
  const requestCount = state.requests.length
  await page.getByRole('tab', { name: 'Завершено', exact: true }).click()
  await expect
    .poll(() =>
      state.listRequests.some(
        (query) => new URLSearchParams(query).get('status') === 'completed',
      ),
    )
    .toBe(true)
  expect(state.requests.length).toBe(requestCount)
  await expect(
    statistics.locator('[data-contact-metric="partner"]'),
  ).toContainText('+50%')

  await page
    .getByRole('button', { name: 'Месяц статистики', exact: true })
    .click()
  const picker = page.getByRole('region', {
    name: 'Месяц статистики: выбор месяца',
  })
  await expect(
    picker.getByRole('button').filter({
      hasText:
        /^(Январь|Февраль|Март|Апрель|Май|Июнь|Июль|Август|Сентябрь|Октябрь|Ноябрь|Декабрь)$/,
    }),
  ).toHaveCount(12)
  const year = picker.getByLabel('Год', { exact: true })
  await picker
    .getByRole('button', { name: 'Предыдущий год', exact: true })
    .click()
  await expect(year).toHaveValue('2024')
  await picker
    .getByRole('button', { name: 'Следующий год', exact: true })
    .click()
  await expect(year).toHaveValue('2025')
  await year.fill('1899')
  await expect(
    picker.getByRole('button', { name: 'Январь', exact: true }),
  ).toBeDisabled()
  await year.fill('2025')
  await picker.getByRole('button', { name: 'Январь', exact: true }).focus()
  await page.keyboard.press('ArrowRight')
  await expect(
    picker.getByRole('button', { name: 'Февраль', exact: true }),
  ).toBeFocused()
  await page.keyboard.press('PageDown')
  await expect(year).toHaveValue('2026')
  await expect(
    picker.getByRole('button', { name: 'Февраль', exact: true }),
  ).toBeFocused()
  await page.keyboard.press('PageUp')
  await expect(year).toHaveValue('2025')
  await page.keyboard.press('End')
  await expect(
    picker.getByRole('button', { name: 'Декабрь', exact: true }),
  ).toBeFocused()
  await page.keyboard.press('Home')
  await expect(
    picker.getByRole('button', { name: 'Январь', exact: true }),
  ).toBeFocused()
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  for (const width of [640, 320]) {
    await page.setViewportSize({ width, height: 1000 })
    await expect(picker).toBeInViewport()
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: testInfo.outputPath(`contact-month-picker-${width}.png`),
    })
    await expect(
      picker.getByRole('button', { name: 'Январь', exact: true }),
    ).toBeFocused()
  }
  await page.keyboard.press('Escape')
  await expect(picker).toHaveCount(0)
  await expect(
    page.getByRole('button', { name: 'Месяц статистики', exact: true }),
  ).toBeFocused()
})

test('statistics have loading, error, retry and zero states and discard stale month responses', async ({
  page,
  browserIssueGuard,
}, testInfo) => {
  const state = await mockStatistics(page)
  state.fail = true
  browserIssueGuard.allowApiError(503, '/api/v1/admin/contact-statistics')
  await page.goto('/contacts')
  const statistics = page.getByRole('region', {
    name: 'Статистика обращений',
    exact: true,
  })
  await expect(statistics.getByRole('alert')).toContainText(
    'Статистика временно недоступна.',
  )
  await expect(
    page.getByRole('region', { name: 'Список обращений', exact: true }),
  ).toBeVisible()
  await page.screenshot({
    path: testInfo.outputPath('contact-statistics-error.png'),
  })
  state.fail = false
  state.empty = true
  await statistics
    .getByRole('button', { name: 'Повторить загрузку статистики', exact: true })
    .click()
  await expect(statistics.getByRole('alert')).toHaveCount(0)
  for (const code of metricCodes)
    await expect(
      statistics.locator(`[data-contact-metric="${code}"]`),
    ).toContainText('0%')
  await page.screenshot({
    path: testInfo.outputPath('contact-statistics-zero.png'),
  })

  state.empty = false
  let release!: () => void
  state.hold.set(
    '2025-01',
    new Promise<void>((resolve) => {
      release = resolve
    }),
  )
  try {
    await selectMonth(page, '2025', 'Январь')
    await expect.poll(() => state.requests.at(-1)).toBe('2025-01')
    await expect(
      statistics.getByRole('status').filter({ hasText: /Загрузка/ }),
    ).toBeVisible()
    await selectMonth(page, '2025', 'Февраль')
    await expect(
      statistics.locator('[data-contact-metric="partner"]'),
    ).toContainText('27')
    // Flush the deliberately delayed HTTP response before asserting the current cohort.
    const delayedResponse = page.waitForResponse((response) =>
      response.url().includes('contact-statistics?month=2025-01'),
    )
    release()
    await delayedResponse
    await expect(
      statistics.getByRole('button', { name: 'Месяц статистики', exact: true }),
    ).toContainText(/февраль.*2025/i)
    await expect(
      statistics.locator('[data-contact-metric="partner"]'),
    ).toContainText('27')
    await expect(
      statistics.locator('[data-contact-metric="partner"]'),
    ).not.toContainText('15')
  } finally {
    release()
  }
})

test('contacts use page scrolling and the original table header pins under the shell then releases at the table end', async ({
  page,
}, testInfo) => {
  await mockStatistics(page, true)
  await page.goto('/contacts')
  const region = page.getByRole('region', {
    name: 'Список обращений',
    exact: true,
  })
  await expect(region).toBeVisible()
  await page.evaluate(() => document.fonts.ready)

  for (const width of [768, 1024, 1280, 1920]) {
    await page.setViewportSize({ width, height: 900 })
    await expect(
      page.getByRole(width < 1024 ? 'button' : 'tablist', {
        name: 'Статус обращения',
        exact: true,
      }),
    ).toBeVisible()
    await page.evaluate(() => window.scrollTo(0, 0))
    await expect(page.locator('.admin-header')).not.toHaveClass(/is-condensed/)
    await region.evaluate((element) => {
      element.scrollLeft = 0
    })
    const naturalTop = await region
      .locator('thead')
      .evaluate((element) => element.getBoundingClientRect().top + scrollY)
    await page.evaluate((y) => window.scrollTo(0, y), naturalTop + 200)
    const offset = () =>
      region
        .locator('thead')
        .evaluate(
          (element) =>
            element.getBoundingClientRect().top -
            document.querySelector('.admin-header')!.getBoundingClientRect()
              .bottom,
        )
    await expect.poll(offset).toBeCloseTo(0, 0)
    expect(await region.locator('thead').count()).toBe(1)
    expect(
      await region.evaluate(
        (element) => element.scrollHeight > element.clientHeight,
      ),
    ).toBe(false)
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.mouse.move(width / 2, 500)
    const before = await page.evaluate(() => scrollY)
    await page.mouse.wheel(0, 200)
    await expect
      .poll(() => page.evaluate(() => scrollY))
      .toBeGreaterThan(before)
    await expect.poll(offset).toBeCloseTo(0, 0)
    await region.evaluate((element) => {
      element.scrollLeft = element.scrollWidth
    })
    await expect.poll(offset).toBeCloseTo(0, 0)
    await expect(region.locator('thead th').last()).toBeInViewport()
    await page.screenshot({
      path: testInfo.outputPath(`contacts-sticky-header-${width}.png`),
    })
    const tableBottom = await region
      .locator('table')
      .evaluate((element) => element.getBoundingClientRect().bottom + scrollY)
    const headerHeight = await region
      .locator('thead')
      .evaluate((element) => element.getBoundingClientRect().height)
    const shellBottom = await page
      .locator('.admin-header')
      .evaluate((element) => element.getBoundingClientRect().bottom)
    await page.locator('#admin-main').evaluate((element) => {
      element.style.paddingBottom = '1000px'
    })
    await page.evaluate(
      (y) => window.scrollTo(0, y),
      tableBottom - shellBottom - headerHeight / 2,
    )
    await expect
      .poll(() =>
        region
          .locator('thead')
          .evaluate((element) => element.getBoundingClientRect().bottom),
      )
      .toBeCloseTo(shellBottom + headerHeight / 2, 0)
    await page.locator('#admin-main').evaluate((element) => {
      element.style.paddingBottom = ''
    })
    await page.evaluate(() => window.scrollTo(0, 0))
    await expect
      .poll(() =>
        region
          .locator('thead')
          .evaluate((element) => element.getBoundingClientRect().top),
      )
      .toBeCloseTo(naturalTop, 0)
  }
})
