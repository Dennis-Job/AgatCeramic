import AxeBuilder from './approvedAccessibility'
import { mockAdminBaseline } from './adminBaselineApi'
import { createDeferredApiRequests } from './deferredApi'
import { expect, test, type Page } from './fixtures'
import type { AuditLog } from '../src/features/audit-log/types/auditLog.types'
import type { DeferredApiRequests } from './deferredApi'

const endpoint = '/admin/audit-logs'
const widths = [320, 640, 768, 1024, 1280, 1440, 2560]
const actor = { id: 1, name: 'Марина Тестовая' }
const productName = 'Керамогранит Монте Тиберио'
const sku = 'MONTE-001'
const productSummary = `Изменён товар: SKU ${sku} · ${productName}`
// These are API Resource responses: human-readable details are produced by the
// backend presenter, including historical values and object snapshots.
const events: AuditLog[] = [
  {
    id: 101,
    action: 'product.updated',
    actor,
    entity: { type: 'Product', id: 10, name: productName },
    metadata: {
      changes: {
        price: { before: '1990.00', after: '2190.00' },
        is_active: { before: false, after: true },
      },
    },
    details: [
      { label: 'Цена', value: '1990.00 → 2190.00' },
      { label: 'Активность', value: 'Нет → Да' },
      { label: 'SKU', value: sku },
      { label: 'Действие', value: productSummary },
    ],
    occurred_at: '2026-10-16T07:45:00Z',
  },
  {
    id: 102,
    action: 'product.image-uploaded',
    actor,
    entity: { type: 'ProductImage', id: 21, name: 'Фотография Монте Тиберио' },
    metadata: { values: { path: 'monte-front.webp', is_primary: true } },
    details: [
      { label: 'Файл изображения', value: 'monte-front.webp' },
      { label: 'Главное фото', value: 'Да' },
      {
        label: 'Действие',
        value: 'Загружено фото товара: Фотография Монте Тиберио',
      },
    ],
    // UTC October 15 belongs to October 16 in Moscow.
    occurred_at: '2026-10-15T21:15:00Z',
  },
  {
    id: 103,
    action: 'product-group.updated',
    actor,
    entity: { type: 'ProductGroup', id: 31, name: 'Объединение Монте' },
    metadata: {
      changes: { product_skus: { before: ['A-1'], after: ['A-1', 'A-2'] } },
    },
    details: [
      { label: 'Товары в объединении (SKU)', value: 'A-1 → A-1; A-2' },
      {
        label: 'Действие',
        value: 'Изменено объединение товаров: Объединение Монте',
      },
    ],
    occurred_at: '2026-10-15T20:45:00Z',
  },
  {
    id: 104,
    action: 'order.status-changed',
    actor,
    entity: { type: 'Order', id: 41, name: 'Заказ №AC-20261015-001' },
    metadata: { from_status: 'new', to_status: 'processing' },
    details: [
      { label: 'Статус заказа', value: 'Новый → В обработке' },
      { label: 'Номер заказа', value: 'AC-20261015-001' },
      {
        label: 'Действие',
        value: 'Изменён статус заказа: Заказ №AC-20261015-001',
      },
    ],
    occurred_at: '2026-10-15T09:00:00Z',
  },
  {
    id: 105,
    action: 'import.completed',
    actor: null,
    entity: null,
    metadata: { operation: 'product', processed: 120, failed: 0 },
    details: [
      { label: 'Операция импорта', value: 'Товары' },
      { label: 'Обработано строк', value: '120' },
      { label: 'Ошибок', value: '0' },
      { label: 'Действие', value: 'Завершён импорт' },
    ],
    occurred_at: '2026-10-14T08:00:00Z',
  },
  {
    id: 106,
    action: 'created',
    actor,
    entity: { type: 'Product', id: 61, name: 'Исторический товар' },
    metadata: null,
    details: [],
    occurred_at: '2026-10-14T07:00:00Z',
  },
  {
    id: 107,
    action: 'custom.unknown-event',
    actor,
    entity: null,
    metadata: { affected_records: 3 },
    details: [{ label: 'Затронуто записей', value: '3' }],
    occurred_at: '2026-10-14T06:00:00Z',
  },
]

async function mockTimeline(
  page: Page,
  options: {
    longData?: boolean
    state?: 'empty' | 'error'
    deferred?: DeferredApiRequests
    paginate?: boolean
  } = {},
) {
  const records = structuredClone(events)
  if (options.longData) {
    const longName =
      'Керамогранит коллекционный с декоративной фактурой белого мрамора и длинным русским названием '
        .repeat(3)
        .trim()
    const longSku = 'АРТИКУЛБЕЗПРОБЕЛОВ'.repeat(12)
    records[0]!.entity!.name = longName
    records[0]!.actor!.name =
      'Ответственный сотрудник по работе с ассортиментом и фотографиями магазина '
        .repeat(3)
        .trim()
    records[0]!.metadata = {
      changes: {
        description: { before: longName, after: `${longName} обновлённый` },
      },
    }
    records[0]!.details = [
      { label: 'Описание', value: `${longName} → ${longName} обновлённый` },
      { label: 'SKU', value: longSku },
      {
        label: 'Действие',
        value: `Изменён товар: SKU ${longSku} · ${longName}`,
      },
      {
        label: 'Технический код',
        value: 'catalog.product.identifier.'.repeat(12),
      },
    ]
  }
  const reads: URL[] = []
  await mockAdminBaseline(page)
  await page.route('**/api/v1/admin/audit-logs?**', async (route) => {
    const url = new URL(route.request().url())
    reads.push(url)
    await options.deferred?.wait(endpoint)
    if (options.state === 'error') {
      return route.fulfill({
        status: 500,
        json: { error: { message: 'Audit API error' } },
      })
    }
    const currentPage = Number(url.searchParams.get('page') ?? 1)
    const action = url.searchParams.get('action')
    const filtered = action
      ? records.filter((record) => record.action === action)
      : records
    const data =
      options.state === 'empty'
        ? []
        : currentPage === 2
          ? [{ ...records[0]!, id: 201 }]
          : filtered
    return route.fulfill({
      json: {
        data,
        meta: {
          current_page: currentPage,
          last_page: options.paginate ? 2 : 1,
          total: options.paginate ? 26 : data.length,
          per_page: 25,
        },
      },
    })
  })
  return reads
}

const timeline = (page: Page) =>
  page.getByRole('region', { name: 'Записи журнала аудита', exact: true })
const event = (page: Page, id: number) =>
  timeline(page)
    .getByRole('article')
    .filter({ has: page.locator(`#audit-event-${id}-title`) })

test.beforeEach(async ({ page }) => {
  await page.clock.setFixedTime(new Date('2026-10-16T09:00:00Z'))
})

test('audit timeline groups events by Moscow day and keeps summary concise', async ({
  page,
}) => {
  await mockTimeline(page)
  await page.goto('/audit-log')
  const region = timeline(page)
  await expect(region.getByRole('article')).toHaveCount(7)
  await expect(region.getByRole('table')).toHaveCount(0)
  await expect(
    region.getByRole('heading', { name: /16 октября/ }),
  ).toBeVisible()
  await expect(
    region.getByRole('heading', { name: /15 октября/ }),
  ).toBeVisible()
  await expect(
    region.getByRole('heading', { name: /14 октября/ }),
  ).toBeVisible()
  const lists = region.locator('ol')
  await expect(lists).toHaveCount(3)
  await expect(lists.nth(0).getByRole('article')).toHaveCount(2)
  await expect(lists.nth(0).locator('#audit-event-102-title')).toBeVisible()
  await expect(lists.nth(1).getByRole('article')).toHaveCount(2)
  await expect(lists.nth(2).getByRole('article')).toHaveCount(3)
  const product = event(page, 101)
  await expect(
    product.getByRole('heading', { name: 'Изменён товар', exact: true }),
  ).toBeVisible()
  await expect(product.getByText(actor.name, { exact: true })).toBeVisible()
  await expect(product.getByText(productName, { exact: true })).toBeVisible()
  expect((await product.innerText()).split(sku)).toHaveLength(2)
  await expect(product.getByText(productSummary, { exact: true })).toHaveCount(
    0,
  )
  for (const record of events) {
    await expect(
      event(page, record.id).getByText(record.action, { exact: true }),
    ).toHaveCount(0)
    await expect(
      event(page, record.id).getByRole('button').first(),
    ).toHaveAttribute('aria-expanded', 'false')
  }
  await expect(
    event(page, 102).getByRole('heading', {
      name: 'Загружено фото товара',
      exact: true,
    }),
  ).toBeVisible()
  await expect(
    event(page, 103).getByRole('heading', {
      name: 'Изменено объединение товаров',
      exact: true,
    }),
  ).toBeVisible()
  await expect(
    event(page, 104).getByRole('heading', {
      name: 'Изменён статус заказа',
      exact: true,
    }),
  ).toBeVisible()
  await expect(
    event(page, 106).getByText('Исторический товар', { exact: true }),
  ).toBeVisible()
  await expect(event(page, 102)).toContainText('Главное фото: Да')
  await expect(event(page, 105)).toContainText(
    'Обработано строк: 120 · Ошибок: 0',
  )
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  await page.screenshot({
    path: '.tmp/audit-timeline/default-1280.png',
    fullPage: true,
  })
})

test('audit timeline disclosures work with keyboard and reveal formatted historical changes', async ({
  page,
}) => {
  await mockTimeline(page)
  await page.goto('/audit-log')
  const product = event(page, 101)
  const disclosure = product.getByRole('button', {
    name: 'Показать изменения',
    exact: true,
  })
  await expect(disclosure).toHaveAttribute('aria-expanded', 'false')
  const panelId = await disclosure.getAttribute('aria-controls')
  expect(panelId).toBeTruthy()
  const panel = page.locator(`[id="${panelId}"]`)
  await expect(panel).toBeHidden()
  await disclosure.focus()
  await page.keyboard.press('Enter')
  await expect(disclosure).toHaveAttribute('aria-expanded', 'true')
  await expect(disclosure).toBeFocused()
  await expect(panel).toBeVisible()
  await expect(panel.getByText('Было', { exact: true }).first()).toBeVisible()
  await expect(panel.getByText('Стало', { exact: true }).first()).toBeVisible()
  await expect(panel.getByText('1990.00', { exact: true })).toBeVisible()
  await expect(panel.getByText('2190.00', { exact: true })).toBeVisible()
  await expect(panel.getByText('Нет', { exact: true })).toBeVisible()
  await expect(panel.getByText('Да', { exact: true })).toBeVisible()
  await expect(
    panel.getByText('product.updated', { exact: true }),
  ).toBeVisible()
  expect((await product.innerText()).split(sku)).toHaveLength(2)
  await expect(product.getByText(productSummary, { exact: true })).toHaveCount(
    0,
  )
  await expect(
    event(page, 102).getByText('monte-front.webp', { exact: true }),
  ).toHaveCount(0)
  await event(page, 102)
    .getByRole('button', { name: 'Подробности', exact: true })
    .click()
  await expect(
    event(page, 102).getByText('monte-front.webp', { exact: true }),
  ).toBeVisible()
  await event(page, 107)
    .getByRole('button', { name: 'Подробности', exact: true })
    .click()
  await expect(
    event(page, 107).getByText('custom.unknown-event', { exact: true }),
  ).toBeVisible()
  await expect(
    event(page, 107).getByText('Затронуто записей', { exact: true }),
  ).toBeVisible()
  await event(page, 106)
    .getByRole('button', { name: 'Подробности', exact: true })
    .click()
  await expect(
    event(page, 106).getByText('created', { exact: true }),
  ).toBeVisible()
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  await page.evaluate(() => window.scrollTo(0, 0))
  await page.screenshot({
    path: '.tmp/audit-timeline/expanded-1280.png',
    fullPage: true,
  })
  await disclosure.focus()
  await page.keyboard.press('Space')
  await expect(disclosure).toHaveAttribute('aria-expanded', 'false')
  await expect(panel).toBeHidden()
  await expect(disclosure).toBeFocused()
})

test('audit timeline filters preserve API query and pagination resets to first page', async ({
  page,
}) => {
  const reads = await mockTimeline(page, { paginate: true })
  await page.goto('/audit-log')
  await expect(event(page, 101)).toBeVisible()
  await expect(
    page.getByRole('button', { name: 'Предыдущая страница', exact: true }),
  ).toBeDisabled()
  await page
    .getByRole('button', { name: 'Следующая страница', exact: true })
    .click()
  await expect(event(page, 201)).toBeVisible()
  expect(reads.at(-1)!.searchParams.get('page')).toBe('2')
  await expect(
    page.getByRole('button', { name: 'Следующая страница', exact: true }),
  ).toBeDisabled()
  await page
    .getByRole('button', { name: 'Предыдущая страница', exact: true })
    .click()
  await expect(event(page, 101)).toBeVisible()
  await page
    .getByRole('button', { name: 'Следующая страница', exact: true })
    .click()
  await expect(event(page, 201)).toBeVisible()
  await page
    .getByRole('textbox', { name: 'Поиск в журнале', exact: true })
    .fill('Монте')
  await page.getByRole('button', { name: 'Найти', exact: true }).click()
  await expect
    .poll(() => reads.at(-1)!.searchParams.get('search'))
    .toBe('Монте')
  await expect(event(page, 101)).toBeVisible()
  expect(reads.at(-1)!.searchParams.get('page')).toBe('1')
  const action = page.getByRole('button', { name: 'Тип действия', exact: true })
  await action.click()
  // Catalog and import actions are selectable without changing endpoint semantics.
  const options = page.getByRole('group', {
    name: 'Тип действия: варианты',
    exact: true,
  })
  await expect(
    options.getByRole('button', { name: /импорт/i }).first(),
  ).toBeVisible()
  await options
    .getByRole('button', { name: /^Измен(?:ён товар|ение товара)$/ })
    .click()
  await expect
    .poll(() => reads.at(-1)!.searchParams.get('action'))
    .toBe('product.updated')
  await expect(timeline(page).getByRole('article')).toHaveCount(1)
  await page
    .getByRole('textbox', { name: 'Дата с', exact: true })
    .fill('15.10.2026')
  await page.keyboard.press('Escape')
  await page
    .getByRole('textbox', { name: 'Дата по', exact: true })
    .fill('16.10.2026')
  await page.keyboard.press('Escape')
  await page.getByRole('button', { name: 'Найти', exact: true }).click()
  await expect
    .poll(() => reads.at(-1)!.searchParams.get('date_to'))
    .toBe('2026-10-16')
  expect(reads.at(-1)!.searchParams.get('date_from')).toBe('2026-10-15')
  expect(reads.at(-1)!.searchParams.get('per_page')).toBe('25')
  expect(reads.at(-1)!.searchParams.get('page')).toBe('1')
  await page.getByRole('button', { name: 'Сбросить', exact: true }).click()
  await expect(timeline(page).getByRole('article')).toHaveCount(7)
  for (const key of ['search', 'action', 'date_from', 'date_to']) {
    expect(reads.at(-1)!.searchParams.has(key)).toBe(false)
  }
  expect(reads.at(-1)!.searchParams.get('page')).toBe('1')
})

test('audit timeline loading state announces pending response', async ({
  page,
}) => {
  const deferred = createDeferredApiRequests()
  const pending = deferred.defer(endpoint)
  await mockTimeline(page, { deferred })
  await page.goto('/audit-log', { waitUntil: 'commit' })
  await pending.requested
  try {
    await expect(
      page.getByRole('status').filter({ hasText: 'Загрузка журнала аудита' }),
    ).toBeVisible()
    await expect(page.getByRole('article')).toHaveCount(0)
    await page.screenshot({
      path: '.tmp/audit-timeline/loading-1280.png',
      fullPage: true,
    })
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  } finally {
    pending.release()
  }
  await expect(timeline(page).getByRole('article')).toHaveCount(7)
})

for (const state of ['empty', 'error'] as const) {
  test(`audit timeline ${state} state keeps filters usable`, async ({
    page,
    browserIssueGuard,
  }) => {
    if (state === 'error')
      browserIssueGuard.allowApiError(500, `/api/v1${endpoint}`)
    await mockTimeline(page, { state })
    await page.goto('/audit-log')
    await expect(
      page.getByRole(state === 'error' ? 'alert' : 'status').filter({
        hasText:
          state === 'error'
            ? 'Не удалось загрузить журнал аудита.'
            : 'Записи не найдены.',
      }),
    ).toBeVisible()
    await expect(page.getByRole('article')).toHaveCount(0)
    await expect(
      page.getByRole('textbox', { name: 'Поиск в журнале', exact: true }),
    ).toBeEnabled()
    await expect(
      page.getByRole('button', { name: 'Найти', exact: true }),
    ).toBeEnabled()
    await expect(
      page.getByRole('button', { name: 'Следующая страница', exact: true }),
    ).toHaveCount(0)
    await page.screenshot({
      path: `.tmp/audit-timeline/${state}-1280.png`,
      fullPage: true,
    })
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  })
}

test('audit timeline keeps long Cyrillic events usable at all required widths', async ({
  page,
}) => {
  test.setTimeout(90_000)
  await mockTimeline(page, { longData: true })
  await page.goto('/audit-log')
  const product = event(page, 101)
  await expect(product).toBeVisible()
  for (const width of widths) {
    await page.setViewportSize({ width, height: 900 })
    const disclosure = product.getByRole('button').first()
    await page.getByRole('button', { name: 'Сбросить', exact: true }).focus()
    await page.keyboard.press('Tab')
    await expect(disclosure).toBeInViewport()
    await expect(disclosure).toBeFocused()
    expect(
      await disclosure.evaluate(
        (element) => getComputedStyle(element).outlineStyle,
      ),
    ).not.toBe('none')
    await page.keyboard.press('Enter')
    await expect(disclosure).toHaveAttribute('aria-expanded', 'true')
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
      `page overflow at ${width}px`,
    ).toBe(true)
    expect(
      await timeline(page).evaluate((element) =>
        Array.from(element.querySelectorAll('article')).every(
          (article) => article.scrollWidth <= article.clientWidth,
        ),
      ),
      `event overflow at ${width}px`,
    ).toBe(true)
    await page.evaluate(() => window.scrollTo(0, 0))
    await page.screenshot({
      path: `.tmp/audit-timeline/long-expanded-${width}.png`,
      fullPage: true,
    })
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
    await disclosure.focus()
    await page.keyboard.press('Space')
    await expect(disclosure).toHaveAttribute('aria-expanded', 'false')
  }
})

test('audit timeline captures baseline candidates for independent UI review', async ({
  page,
}) => {
  await mockAdminBaseline(page)
  await page.goto('/audit-log')
  await expect(timeline(page).getByRole('article')).toHaveCount(1)
  await page.evaluate(() => document.fonts.ready)
  for (const width of [1280, 1440]) {
    await page.setViewportSize({ width, height: 900 })
    await page.screenshot({
      path: `.tmp/audit-timeline/baseline-candidate-${width}.png`,
      fullPage: true,
    })
  }
})
