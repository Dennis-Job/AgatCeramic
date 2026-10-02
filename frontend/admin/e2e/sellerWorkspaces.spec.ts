import AxeBuilder from '@axe-core/playwright'
import { expect, test } from './fixtures'
import { mockAdminBaseline } from './adminBaselineApi'
import { attribute, attributeGroup, brand, category } from './catalogApi'

const widths = [320, 602, 640, 768, 1024, 1280, 1440, 1920, 2560]
const longName =
  'Керамогранит коллекционный с декоративной фактурой белого мрамора и длинным русским названием'
const longCode =
  'catalog.permission.with.a.very.long.unbroken.identifier'.repeat(3)
const timestamp = '2026-10-01T10:00:00Z'
const collection = (data: unknown[]) => ({
  data,
  meta: { current_page: 1, last_page: 1, per_page: 25, total: data.length },
})
const repeated = <T extends { id: number }>(item: T) =>
  Array.from({ length: 25 }, (_, index) => ({ ...item, id: index + 1 }))
const role = {
  id: 1,
  name: longName,
  slug: longCode,
  description: longName.repeat(3),
  is_system: false,
  permissions: [],
}
const order = {
  id: 1,
  order_number: `AC-${longCode}`,
  customer: {
    name: longName,
    phone: '+79990000000',
    email: `${longCode}@example.test`,
  },
  delivery_address: longName,
  customer_comment: null,
  status: 'new',
  payment_status: 'not_paid',
  payment_amount: null,
  payment_method: null,
  payment_reference: null,
  total_amount: '9999999999.99',
  items: [],
  paid_at: null,
  completed_at: null,
  created_at: timestamp,
}
const contact = {
  id: 1,
  type: 'callback',
  contact: { name: longName, phone: null, email: `${longCode}@example.test` },
  message: longName.repeat(3),
  source: 'site',
  status: 'new',
  assignee: { id: 1, name: longName },
  assigned_at: timestamp,
  completed_at: null,
  created_at: timestamp,
}
const fixtures = {
  '/admin/categories/tree': {
    data: repeated({ ...category, name: longName, slug: longCode }),
  },
  '/admin/brands': collection(
    repeated({ ...brand, name: longName, slug: longCode }),
  ),
  '/admin/attribute-groups': collection(
    repeated({
      ...attributeGroup,
      name: longName,
      slug: longCode,
      description: longName.repeat(3),
    }),
  ),
  '/admin/attributes': collection(
    repeated({ ...attribute, name: longName, slug: longCode }),
  ),
  '/admin/users': collection(
    repeated({
      id: 1,
      name: longName,
      email: `${longCode}@example.test`,
      status: 'blocked',
      last_login_at: timestamp,
      roles: [role, { ...role, id: 2 }],
    }),
  ),
  '/admin/roles': { data: repeated(role) },
  '/admin/permissions': {
    data: repeated({
      id: 1,
      name: longName,
      code: longCode,
      description: longName.repeat(3),
      roles: [role],
    }),
  },
  '/admin/audit-logs': collection(
    repeated({
      id: 1,
      action: longCode,
      actor: { id: 1, name: longName },
      entity: {
        type: 'product',
        id: 1,
        name: longName,
        email: `${longCode}@example.test`,
      },
      metadata: null,
      details: [{ label: longName, value: longCode }],
      occurred_at: timestamp,
    }),
  ),
  '/admin/orders': collection(repeated(order)),
  '/admin/orders/1': { data: order },
  '/admin/contact-requests': collection(repeated(contact)),
  '/admin/contact-requests/1': { data: contact },
  '/admin/media': collection(
    repeated({
      id: 1,
      kind: 'document',
      title: longName,
      alt: longName.repeat(3),
      url: '/catalog.pdf',
      thumbnail_url: null,
      mime_type: 'application/pdf',
      size: 2048,
      width: null,
      height: null,
      created_at: timestamp,
      updated_at: timestamp,
    }),
  ),
}

for (const [path, label, cardsBelow] of [
  ['/categories', 'Список категорий', 0],
  ['/brands', 'Список брендов', 0],
  ['/attribute-groups', 'Список групп характеристик', 0],
  ['/attributes', 'Список характеристик', 0],
  ['/employees', 'Список сотрудников', 1280],
  ['/roles', 'Список ролей', 0],
  ['/permissions', 'Каталог прав', 0],
  ['/audit-log', 'Записи журнала аудита', 1280],
  ['/orders', 'Список заказов', 768],
  ['/contacts', 'Список обращений', 768],
  ['/media', 'Список файлов', 0],
] as const) {
  test(`Seller workspace ${path} keeps long data, scroll and actions usable`, async ({
    page,
  }) => {
    await mockAdminBaseline(page, false, 'default', undefined, fixtures)
    await page.goto(path)
    await expect(page.locator('.admin-workspace')).toBeVisible()
    await expect(
      page.getByRole('status').filter({ hasText: 'Загрузка' }),
    ).toHaveCount(0)
    await page.evaluate(() => document.fonts.ready)
    for (const width of widths) {
      await page.setViewportSize({ width, height: 900 })
      await page.evaluate(
        () =>
          new Promise<void>((resolve) =>
            requestAnimationFrame(() => requestAnimationFrame(() => resolve())),
          ),
      )
      const region = page.getByRole('region', { name: label, exact: true })
      if (width >= cardsBelow) {
        await expect(region).toBeVisible()
        const dimensions = await region.evaluate((element) => ({
          width: element.clientWidth,
          viewportWidth: document.documentElement.clientWidth,
          cellsOverflow: Array.from(
            element.querySelectorAll('tbody td'),
          ).filter((cell) => cell.scrollWidth > cell.clientWidth).length,
        }))
        expect(dimensions.cellsOverflow, `${path} cells at ${width}`).toBe(0)
        expect(dimensions.width).toBe(dimensions.viewportWidth)
        await region.evaluate((element) => {
          element.scrollTop = 500
          element.scrollLeft = 500
        })
        const top = await region.evaluate(
          (element) =>
            element.querySelector('th')!.getBoundingClientRect().top -
            element.getBoundingClientRect().top,
        )
        expect(top).toBeCloseTo(0, 0)
        if (
          width >= 1280 &&
          (await region.evaluate((element) =>
            element.classList.contains('ui-table-sticky-edges'),
          ))
        ) {
          const edges = await region.evaluate((element) => {
            const rect = element.getBoundingClientRect()
            const row = element.querySelector('tbody tr')!
            return {
              first:
                row.firstElementChild!.getBoundingClientRect().left - rect.left,
              last:
                rect.right -
                row.lastElementChild!.getBoundingClientRect().right,
            }
          })
          expect(edges.first).toBeCloseTo(0, 0)
          expect(edges.last).toBeCloseTo(0, 0)
        }
        await region.evaluate((element) => {
          element.scrollTop = 0
          element.scrollLeft = 0
        })
        if (width === 320) {
          await region.focus()
          await page.keyboard.press('ArrowRight')
          await expect
            .poll(() => region.evaluate((element) => element.scrollLeft))
            .toBeGreaterThan(0)
          await region.evaluate((element) => {
            element.scrollLeft = element.scrollWidth
          })
          await region.screenshot({
            path: `.tmp/a060-visual/${path.slice(1)}-actions-320.png`,
          })
          await region.evaluate((element) => {
            element.scrollLeft = 0
          })
        }
      } else {
        await expect(region).toBeHidden()
        await expect(page.locator('article').first()).toBeVisible()
      }
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= innerWidth,
        ),
        `${path} page overflow at ${width}: ${JSON.stringify(
          await page.evaluate(() =>
            Array.from(document.querySelectorAll('.admin-workspace *'))
              .filter((el) => el.getBoundingClientRect().right > innerWidth)
              .slice(0, 8)
              .map((el) => ({
                tag: el.tagName,
                cls: el.className,
                right: el.getBoundingClientRect().right,
                text: el.textContent?.slice(0, 80),
              })),
          ),
        )}`,
      ).toBe(true)
      await page.evaluate(() => window.scrollTo(0, 0))
      await page.screenshot({
        path: `.tmp/a060-visual/${path.slice(1)}-${width}.png`,
        fullPage: width >= cardsBelow,
      })
    }
    await page.setViewportSize({ width: 1280, height: 900 })
    const region = page.getByRole('region', { name: label, exact: true })
    await region.focus()
    await expect(region).toBeFocused()
    if (['/orders', '/contacts'].includes(path)) {
      const opener = region.getByRole('button').first()
      await opener.focus()
      await page.keyboard.press('Enter')
      const detailPanel = page.getByRole('region', {
        name: 'Детали выбранной записи',
      })
      await expect(detailPanel).toBeFocused()
      await expect(
        detailPanel.locator('aside').getByRole('heading').first(),
      ).toBeInViewport()
      await detailPanel
        .getByRole('button', { name: 'Вернуться к списку' })
        .click()
      await expect(opener).toBeFocused()
      await expect(opener).toBeInViewport()
      for (const width of widths) {
        await page.setViewportSize({ width, height: 900 })
        await page.evaluate(
          () =>
            new Promise<void>((resolve) =>
              requestAnimationFrame(() =>
                requestAnimationFrame(() => resolve()),
              ),
            ),
        )
        expect(
          await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth,
          ),
          `selected ${path} at ${width}: ${JSON.stringify(
            await page.evaluate(() =>
              Array.from(document.querySelectorAll('*'))
                .filter((el) => el.getBoundingClientRect().right > innerWidth)
                .slice(0, 8)
                .map((el) => ({
                  tag: el.tagName,
                  cls: el.className,
                  right: el.getBoundingClientRect().right,
                  text: el.textContent?.slice(0, 80),
                })),
            ),
          )}`,
        ).toBe(true)
        const zones = await page
          .locator('.admin-list-detail')
          .evaluate((element) => {
            const [list, detail] = Array.from(element.children).map((child) =>
              child.getBoundingClientRect(),
            )
            return {
              list: {
                top: list!.top,
                right: list!.right,
                bottom: list!.bottom,
              },
              detail: {
                top: detail!.top,
                left: detail!.left,
                width: detail!.width,
              },
            }
          })
        expect(zones.detail.top).toBeGreaterThanOrEqual(zones.list.bottom)
        expect(zones.detail.width).toBe(
          Math.min(1280, width - (width < 640 ? 32 : 48)),
        )
        expect(zones.detail.left).toBe((width - zones.detail.width) / 2)
        await page
          .getByRole('region', { name: 'Детали выбранной записи', exact: true })
          .locator('aside')
          .screenshot({
            path: `.tmp/a060-visual/${path.slice(1)}-selected-${width}.png`,
            // Exclude the global sticky shell from a tall detail-only crop.
            style: '.admin-header { visibility: hidden !important; }',
          })
      }
    }
    await page.setViewportSize({ width: 1280, height: 900 })
    const accessibility = await new AxeBuilder({ page }).analyze()
    expect(
      accessibility.violations.filter((item) =>
        ['serious', 'critical'].includes(item.impact ?? ''),
      ),
    ).toEqual([])
  })
}
