import AxeBuilder from './approvedAccessibility'
import { expect, test, type Page } from './fixtures'
import { mockAdminBaseline } from './adminBaselineApi'
import { contactStatuses } from './contactStatusFixtures'

async function mockContacts(page: Page, manage = true) {
  await mockAdminBaseline(page)
  const records = [
    { id: 184, name: 'Иван Петров', type: 'callback', status: 'new' },
    { id: 183, name: 'Анна Смирнова', type: 'email', status: 'processing' },
    {
      id: 182,
      name: 'Компания Керамика',
      type: 'partner',
      status: 'awaiting_client',
    },
  ].map((item) => ({
    id: item.id,
    type: item.type,
    status: item.status,
    contact: {
      name: item.name,
      phone: '+79990000001',
      email: 'customer@example.test',
    },
    message:
      'Нужна помощь с выбором плитки для ванной. Перезвоните после 14:00.',
    source: 'site',
    assignee: null,
    created_at: '2026-10-22T07:42:00Z',
    assigned_at: null,
    completed_at: null,
  }))
  const state = {
    records,
    failStatus: false,
    holdStatus: null as Promise<void> | null,
    metadata: true,
    failCounts: false,
    countRequests: 0,
    patches: [] as string[],
    comments: [] as {
      id: number
      body: string
      author: null
      created_at: string
    }[],
  }
  await page.route('**/api/v1/admin/auth/me', (route) =>
    route.fulfill({
      json: {
        data: {
          id: 1,
          name: 'Сотрудник',
          email: 'admin@example.test',
          status: 'active',
          last_login_at: null,
          permissions: [
            'contacts.view',
            ...(manage ? ['contacts.manage'] : []),
          ],
        },
      },
    }),
  )
  await page.route('**/api/v1/admin/contact-workflow', (route) =>
    route.fulfill({
      json: {
        data: state.metadata
          ? contactStatuses
          : contactStatuses.map(({ code, name, is_terminal }) => ({
              code,
              name,
              is_terminal,
            })),
      },
    }),
  )
  await page.route('**/api/v1/admin/contact-assignees', (route) =>
    route.fulfill({ json: { data: [{ id: 1, name: 'Сотрудник' }] } }),
  )
  await page.route('**/api/v1/admin/contact-request-counts', (route) => {
    state.countRequests++
    if (state.failCounts)
      return route.fulfill({
        status: 503,
        json: { error: { message: 'Счётчики временно недоступны.' } },
      })
    return route.fulfill({
      json: {
        data: Object.fromEntries(
          ['new', 'processing', 'awaiting_client'].map((status) => [
            status,
            records.filter((record) => record.status === status).length,
          ]),
        ),
      },
    })
  })
  await page.route('**/api/v1/admin/contact-requests**', async (route) => {
    const url = new URL(route.request().url())
    const parts = url.pathname
      .split('/contact-requests')[1]!
      .split('/')
      .filter(Boolean)
    const record = records.find((item) => item.id === Number(parts[0]))
    if (!parts.length) {
      const data = records.filter(
        (item) =>
          (!url.searchParams.get('status') ||
            item.status === url.searchParams.get('status')) &&
          (!url.searchParams.get('type') ||
            item.type === url.searchParams.get('type')),
      )
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
    }
    if (parts[1] === 'status') {
      const target = route.request().postDataJSON().status as string
      state.patches.push(target)
      if (state.holdStatus) await state.holdStatus
      if (state.failStatus)
        return route.fulfill({
          status: 503,
          json: {
            error: { message: 'Статус не сохранён. Повторите попытку.' },
          },
        })
      expect(
        contactStatuses.find((item) => item.code === record!.status)!
          .allowed_transitions,
      ).toContain(target)
      record!.status = target
      return route.fulfill({ json: { data: record } })
    }
    if (parts[1] === 'status-history')
      return route.fulfill({ json: { data: [] } })
    if (parts[1] === 'comments') {
      if (route.request().method() === 'POST')
        state.comments.push({
          id: 1,
          body: route.request().postDataJSON().body,
          author: null,
          created_at: '2026-10-22T08:00:00Z',
        })
      return route.fulfill({
        status: route.request().method() === 'POST' ? 201 : 200,
        json: { data: state.comments },
      })
    }
    return route.fulfill({ json: { data: record } })
  })
  return state
}

test('guided actions respect the server graph, confirm terminal changes and update counters immediately', async ({
  page,
  browserIssueGuard,
}) => {
  const state = await mockContacts(page)
  await page.goto('/contacts?contact=184')
  const newCount = page
    .getByRole('tab', { name: /^Новое/ })
    .locator('.bg-success-50')
  const processingCount = page
    .getByRole('tab', { name: /^В обработке/ })
    .locator('.bg-blue-light-50')
  const awaitingCount = page
    .getByRole('tab', { name: /^Ожидает клиента/ })
    .locator('.bg-warning-50')
  await expect(newCount).toHaveText('1')
  await expect(processingCount).toHaveText('1')
  await expect(awaitingCount).toHaveText('1')
  const countRequestsBeforeSave = state.countRequests
  const panel = page.getByRole('dialog', {
    name: 'Обращение #184',
    exact: true,
  })
  await expect(
    panel.getByRole('heading', { name: 'Обращение #184', exact: true }),
  ).toBeFocused()
  await expect(
    panel.getByRole('button', { name: 'Ожидает клиента', exact: true }),
  ).toBeDisabled()
  await expect(
    panel.getByRole('button', { name: 'Завершить', exact: true }),
  ).toBeDisabled()
  await expect(panel).toContainText('Сначала начните обработку')
  state.failStatus = true
  browserIssueGuard.allowApiError(
    503,
    '/api/v1/admin/contact-requests/184/status',
  )
  await panel
    .getByRole('button', { name: 'Начать обработку', exact: true })
    .click()
  await expect(page.getByRole('alert')).toContainText('Статус не сохранён')
  await expect(
    panel.getByRole('button', { name: 'Начать обработку', exact: true }),
  ).toBeEnabled()
  expect(state.countRequests).toBe(countRequestsBeforeSave)
  await expect(newCount).toHaveText('1')
  state.failStatus = false
  await panel
    .getByRole('button', { name: 'Начать обработку', exact: true })
    .click()
  await expect(
    panel.getByRole('button', { name: 'Ожидать клиента', exact: true }),
  ).toBeEnabled()
  await expect(
    panel.getByRole('button', { name: 'Завершить', exact: true }),
  ).toBeEnabled()
  expect(state.records[0]!.status).toBe('processing')
  await expect(newCount).toHaveText('0')
  await expect(processingCount).toHaveText('2')
  await expect(awaitingCount).toHaveText('1')
  await expect(
    panel.getByRole('heading', { name: 'Обращение #184', exact: true }),
  ).toBeFocused()
  await panel.getByRole('button', { name: 'Завершить', exact: true }).focus()
  await page.keyboard.press('Enter')
  await expect(
    panel.getByRole('button', { name: 'Подтвердить завершение', exact: true }),
  ).toBeFocused()
  await expect(panel).toContainText(
    'После подтверждения статус нельзя изменить',
  )
  await panel.getByRole('button', { name: 'Отмена', exact: true }).click()
  await expect(
    panel.getByRole('button', { name: 'Завершить', exact: true }),
  ).toBeFocused()
  expect(state.records[0]!.status).toBe('processing')
  await panel.getByRole('button', { name: 'Завершить', exact: true }).click()
  let releaseStatus!: () => void
  state.holdStatus = new Promise<void>((resolve) => {
    releaseStatus = resolve
  })
  await panel
    .getByRole('button', { name: 'Подтвердить завершение', exact: true })
    .click()
  await expect(
    panel.getByRole('heading', { name: 'Обращение #184', exact: true }),
  ).toBeFocused()
  await expect(
    panel.getByRole('button', { name: 'Подтвердить завершение', exact: true }),
  ).toBeDisabled()
  releaseStatus()
  await expect(panel).toContainText('Статус больше нельзя изменить')
  await expect(processingCount).toHaveText('1')
  expect(state.patches).toEqual(['processing', 'processing', 'completed'])
  await panel
    .getByRole('button', { name: 'Закрыть обращение', exact: true })
    .click()
  await expect(
    page
      .getByRole('button', { name: 'Уведомления', exact: true })
      .locator('.admin-notification-count'),
  ).toHaveCount(0)
})

test('right inspector fits all widths, keeps the table, returns focus and protects comment drafts', async ({
  page,
}, testInfo) => {
  await mockContacts(page)
  await page.goto('/contacts')
  for (const width of [320, 640, 768, 1024, 1280, 1440]) {
    await page.setViewportSize({ width, height: 1024 })
    const opener = page
      .getByRole('button', { name: 'Открыть обращение 184', exact: true })
      .filter({ visible: true })
    await opener.click()
    const panel = page.getByRole('dialog', {
      name: 'Обращение #184',
      exact: true,
    })
    await expect(
      panel.getByRole('heading', { name: 'Обращение #184', exact: true }),
    ).toBeFocused()
    await expect(
      panel.getByRole('button', { name: 'Начать обработку', exact: true }),
    ).toBeEnabled()
    await expect(panel).toHaveCSS('transform', 'none')
    const box = (await panel.boundingBox())!
    expect(box.width).toBeLessThanOrEqual(Math.min(544, width))
    expect(box.x + box.width).toBeLessThanOrEqual(width)
    await expect(page.locator('.contacts-panel-overlay')).toHaveCSS(
      'backdrop-filter',
      'none',
    )
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
    await page.screenshot({
      path: testInfo.outputPath(`contacts-drawer-${width}.png`),
    })
    await page.keyboard.press('Escape')
    await expect(panel).toHaveCount(0)
    await expect(opener).toBeFocused()
  }
  await page
    .getByRole('button', { name: 'Открыть обращение 184', exact: true })
    .filter({ visible: true })
    .click()
  await page
    .getByLabel('Новый внутренний комментарий', { exact: true })
    .fill('Нужно уточнить размер плитки')
  await page.keyboard.press('Escape')
  const discard = page.getByRole('dialog', {
    name: 'Закрыть без сохранения комментария?',
    exact: true,
  })
  await expect(discard).toBeVisible()
  await discard.getByRole('button', { name: 'Отмена', exact: true }).click()
  await expect(
    page.getByLabel('Новый внутренний комментарий', { exact: true }),
  ).toHaveValue('Нужно уточнить размер плитки')
  await page
    .getByRole('button', { name: 'Закрыть обращение', exact: true })
    .click()
  await discard
    .getByRole('button', { name: 'Закрыть без сохранения', exact: true })
    .click()
  await expect(page.getByRole('dialog')).toHaveCount(0)
})

test('viewer retains filters but cannot mutate; missing transition metadata never enables guessed actions', async ({
  page,
}) => {
  await mockContacts(page, false)
  await page.goto('/contacts')
  await expect(
    page.getByRole('button', { name: 'Ответственный в списке', exact: true }),
  ).toBeEnabled()
  await page.getByRole('tab', { name: /^Ожидает клиента/ }).click()
  await expect(
    page
      .getByRole('button', { name: 'Открыть обращение 182', exact: true })
      .filter({ visible: true }),
  ).toBeVisible()
  await page.getByRole('tab', { name: 'Все', exact: true }).click()
  await page
    .getByRole('button', { name: 'Открыть обращение 184', exact: true })
    .filter({ visible: true })
    .click()
  await expect(page.getByRole('dialog')).toContainText(
    'требуется право управления',
  )
  await expect(
    page.getByRole('button', { name: 'Начать обработку', exact: true }),
  ).toHaveCount(0)
  const state = await mockContacts(page)
  state.metadata = false
  await page.goto('/contacts?contact=184')
  await expect(page.getByRole('dialog')).toContainText(
    'Действия со статусом временно недоступны',
  )
  await expect(
    page.getByRole('button', { name: 'Начать обработку', exact: true }),
  ).toHaveCount(0)
})

test('contact table stays in its container under the sliding inspector and uses the requested status colors', async ({
  page,
}, testInfo) => {
  const state = await mockContacts(page)
  state.records.push(
    { ...state.records[0]!, id: 181, status: 'completed' },
    { ...state.records[0]!, id: 180, status: 'rejected' },
  )
  await page.setViewportSize({ width: 1920, height: 1024 })
  await page.goto('/contacts')
  const table = page.getByRole('table', { name: 'Список обращений' })
  await expect(table).toBeVisible()
  const tableBox = (await table.boundingBox())!
  expect(tableBox.width).toBeLessThanOrEqual(1280)
  expect(tableBox.x).toBeGreaterThan(0)
  const pagination = page.getByRole('navigation', { name: /^Пагинация:/ })
  await expect(pagination).toHaveCSS('border-top-width', '0px')
  for (const [id, tone] of [
    [184, 'success'],
    [183, 'blue-light'],
    [182, 'warning'],
    [181, 'gray'],
    [180, 'error'],
  ] as const) {
    const row = table.getByRole('row').filter({
      has: page.getByRole('button', {
        name: `Открыть обращение ${id}`,
        exact: true,
      }),
    })
    const badge = row.locator(`.bg-${tone}-${tone === 'gray' ? '100' : '50'}`)
    await expect(badge).toBeVisible()
    await expect(badge).toHaveCSS('white-space', 'nowrap')
  }
  await page.evaluate(() => {
    document.body.style.minHeight = '1800px'
  })
  const before = await table.boundingBox()
  const headerBefore = await page.getByRole('banner').boundingBox()
  await page
    .getByRole('button', { name: 'Открыть обращение 184', exact: true })
    .filter({ visible: true })
    .click()
  const panel = page.getByRole('dialog', {
    name: 'Обращение #184',
    exact: true,
  })
  await expect(panel).toHaveCSS('transform', 'none')
  expect(await table.boundingBox()).toEqual(before)
  expect(await page.getByRole('banner').boundingBox()).toEqual(headerBefore)
  await expect(panel.locator('svg.lucide-user-round')).toBeVisible()
  await expect(panel.locator('svg.lucide-phone')).toBeVisible()
  await expect(panel.locator('svg.lucide-mail')).toBeVisible()
  await expect(panel.locator('svg.lucide-message-square')).toBeVisible()
  const message = panel.getByText(state.records[0]!.message, { exact: true })
  const messageBackground = await message.evaluate((element) => {
    let node: Element | null = element
    while (node && !node.matches('[role="dialog"]')) {
      const color = getComputedStyle(node).backgroundColor
      if (color !== 'rgba(0, 0, 0, 0)') return color
      node = node.parentElement
    }
    return null
  })
  expect(messageBackground).toBe('rgb(249, 250, 251)')
  await page.screenshot({
    path: testInfo.outputPath('contacts-refinements-desktop.png'),
  })
  await page.keyboard.press('Escape')
  await expect(panel).toHaveCount(0)
  await page.setViewportSize({ width: 320, height: 800 })
  const awaitingTab = page.getByRole('tab', { name: /^Ожидает клиента/ })
  await awaitingTab.click()
  await expect(
    page.getByRole('tab', { name: /^Новое/ }).locator('.bg-success-50'),
  ).toHaveText('1')
  await expect(
    page
      .getByRole('tab', { name: /^В обработке/ })
      .locator('.bg-blue-light-50'),
  ).toHaveText('1')
  const awaitingOpener = page
    .getByRole('button', { name: 'Открыть обращение 182', exact: true })
    .filter({ visible: true })
  const awaitingBadge = awaitingOpener.locator('.bg-warning-50')
  await expect(awaitingBadge).toHaveText('Ожидает клиента')
  await expect(awaitingBadge).toHaveCSS('white-space', 'nowrap')
  expect(
    await awaitingBadge.evaluate((element) => {
      const range = document.createRange()
      range.selectNodeContents(element)
      return range.getClientRects().length
    }),
  ).toBe(1)
  await awaitingOpener.click()
  const awaitingPanel = page.getByRole('dialog', {
    name: 'Обращение #182',
    exact: true,
  })
  await expect(awaitingPanel).toHaveCSS('transform', 'none')
  await expect(awaitingPanel.locator('.bg-warning-50').first()).toHaveCSS(
    'white-space',
    'nowrap',
  )
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= innerWidth,
    ),
  ).toBe(true)
  await page.screenshot({
    path: testInfo.outputPath('contacts-refinements-mobile.png'),
  })
})

test('unavailable status counts can be retried and render real zeroes without blocking the queue', async ({
  page,
  browserIssueGuard,
}) => {
  const state = await mockContacts(page)
  state.records.length = 0
  state.failCounts = true
  browserIssueGuard.allowApiError(503, '/api/v1/admin/contact-request-counts')
  await page.goto('/contacts')
  await expect(page.getByRole('alert')).toContainText(
    'Счётчики временно недоступны.',
  )
  await expect(
    page.getByRole('status').filter({ hasText: 'Обращения не найдены' }),
  ).toBeVisible()
  state.failCounts = false
  await page
    .getByRole('button', { name: 'Повторить загрузку счётчиков', exact: true })
    .click()
  await expect(page.getByRole('alert')).toHaveCount(0)
  for (const label of [/^Новое/, /^В обработке/, /^Ожидает клиента/])
    await expect(
      page.getByRole('tab', { name: label }).locator('[data-contact-count]'),
    ).toHaveText('0')
})

test('inspector enters and leaves smoothly and respects reduced motion', async ({
  page,
}) => {
  await mockContacts(page)
  await page.goto('/contacts')
  for (const reducedMotion of ['no-preference', 'reduce'] as const) {
    await page.emulateMedia({ reducedMotion })
    await page.evaluate(() => {
      const store = window as unknown as {
        contactMotion: { phase: string; duration: string }[]
        contactMotionEnds: string[]
        contactMotionStarts: string[]
        contactLeaveStart: number | null
        contactLeaveElapsed: number | null
        contactMotionObserver?: MutationObserver
      }
      store.contactMotion = []
      store.contactMotionEnds = []
      store.contactMotionStarts = []
      store.contactLeaveStart = null
      store.contactLeaveElapsed = null
      document.addEventListener(
        'transitionrun',
        (event) => {
          if (
            event.target instanceof Element &&
            event.target.matches('.contacts-panel-overlay') &&
            event.propertyName === 'opacity'
          )
            store.contactMotionStarts.push(event.propertyName)
        },
        true,
      )
      document.addEventListener(
        'transitionend',
        (event) => {
          if (
            event.target instanceof Element &&
            ((event.target.matches('.contacts-panel') &&
              event.propertyName === 'transform') ||
              (event.target.matches('.contacts-panel-overlay') &&
                event.propertyName === 'opacity'))
          )
            store.contactMotionEnds.push(event.propertyName)
        },
        true,
      )
      store.contactMotionObserver?.disconnect()
      store.contactMotionObserver = new MutationObserver(() => {
        const overlay = document.querySelector('.contacts-panel-overlay')
        const panel = overlay?.querySelector('.contacts-panel')
        if (!overlay || !panel) {
          if (
            store.contactLeaveStart !== null &&
            store.contactLeaveElapsed === null
          )
            store.contactLeaveElapsed =
              performance.now() - store.contactLeaveStart
          return
        }
        for (const phase of ['enter', 'leave'])
          if (overlay.classList.contains(`contacts-panel-${phase}-active`)) {
            if (phase === 'leave' && store.contactLeaveStart === null)
              store.contactLeaveStart = performance.now()
            store.contactMotion.push({
              phase,
              duration: getComputedStyle(panel).transitionDuration,
            })
          }
      })
      store.contactMotionObserver.observe(document.body, {
        subtree: true,
        childList: true,
        attributes: true,
        attributeFilter: ['class'],
      })
    })
    const opener = page
      .getByRole('button', { name: 'Открыть обращение 184', exact: true })
      .filter({ visible: true })
    await opener.click()
    const panel = page.getByRole('dialog', {
      name: 'Обращение #184',
      exact: true,
    })
    await expect(panel).toHaveCSS('transform', 'none')
    await expect(page.locator('.contacts-panel-enter-active')).toHaveCount(0)
    await page.keyboard.press('Escape')
    await expect(panel).toHaveCount(0)
    await expect(page.locator('.contacts-panel-overlay')).toHaveCount(0)
    await expect(opener).toBeFocused()
    const snapshots = await page.evaluate(
      () =>
        (
          window as unknown as {
            contactMotion: { phase: string; duration: string }[]
          }
        ).contactMotion,
    )
    for (const phase of ['enter', 'leave']) {
      const durations = snapshots
        .filter((snapshot) => snapshot.phase === phase)
        .flatMap((snapshot) =>
          snapshot.duration.split(',').map((duration) => parseFloat(duration)),
        )
      expect(durations.length).toBeGreaterThan(0)
      if (reducedMotion === 'reduce')
        expect(durations.every((duration) => duration === 0)).toBe(true)
      else expect(durations.some((duration) => duration > 0)).toBe(true)
    }
    expect(await page.evaluate(() => document.body.style.overflow)).not.toBe(
      'hidden',
    )
    const completedTransitions = await page.evaluate(
      () =>
        (window as unknown as { contactMotionEnds: string[] })
          .contactMotionEnds,
    )
    if (reducedMotion === 'reduce') expect(completedTransitions).toEqual([])
    else {
      expect(
        completedTransitions.filter((property) => property === 'transform')
          .length,
      ).toBeGreaterThanOrEqual(1)
      const motion = await page.evaluate(() => ({
        starts: (window as unknown as { contactMotionStarts: string[] })
          .contactMotionStarts,
        elapsed: (window as unknown as { contactLeaveElapsed: number | null })
          .contactLeaveElapsed,
      }))
      expect(motion.starts.length).toBeGreaterThanOrEqual(2)
      // Vue's timeout fallback may remove the leaving node before transitionend.
      // Check the actual time the leaving layer stays mounted as well as motion start.
      expect(motion.elapsed).toBeGreaterThanOrEqual(120)
    }
  }
})
