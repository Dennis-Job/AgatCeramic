import AxeBuilder from './approvedAccessibility'
import { expect, test, type Page } from './fixtures'
import { mockAdminBaseline } from './adminBaselineApi'
import { brand } from './catalogApi'
import { createDeferredApiRequests } from './deferredApi'

const switchName = 'Показывать в алфавитном меню каталога'

async function mockBrands(page: Page, selected = false) {
  await mockAdminBaseline(page)
  const deferred = createDeferredApiRequests()
  const saves: Record<string, unknown>[] = []
  let item = { ...brand, show_in_catalog_menu: selected, is_active: false }
  let failNext = false
  await page.route('**/api/v1/admin/brands**', async (route) => {
    if (['POST', 'PATCH'].includes(route.request().method())) {
      const payload = route.request().postDataJSON()
      saves.push(payload)
      await deferred.wait('save')
      if (failNext) {
        failNext = false
        return route.fulfill({
          status: 422,
          json: { error: { message: 'Не удалось сохранить бренд.' } },
        })
      }
      item = { ...item, ...payload }
      return route.fulfill({ json: { data: item } })
    }
    return route.fulfill({
      json: {
        data: [item],
        meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
      },
    })
  })
  return {
    saves,
    deferred,
    failSave: () => {
      failNext = true
    },
  }
}

for (const edit of [false, true]) {
  test(`brand ${edit ? 'edit' : 'create'} persists catalog menu choice through reload and can turn it off`, async ({
    page,
  }) => {
    const api = await mockBrands(page, edit)
    await page.goto('/brands')
    await page
      .getByRole('button', {
        name: edit ? `Редактировать бренд ${brand.name}` : 'Добавить бренд',
        exact: true,
      })
      .click()
    const dialog = page.getByRole('dialog')
    const toggle = dialog.getByRole('switch', { name: switchName })
    await expect(toggle).toHaveAttribute('aria-checked', String(edit))
    if (!edit) {
      await dialog.getByRole('textbox', { name: /^Название/ }).fill(brand.name)
      await toggle.focus()
      await page.keyboard.press('Space')
    }
    await expect(toggle).toHaveAttribute('aria-checked', 'true')
    const active = dialog.getByRole('checkbox', { name: 'Бренд активен' })
    if (!edit) {
      await active.focus()
      await page.keyboard.press('Space')
    }
    await expect(active).not.toBeChecked()
    const request = api.deferred.defer('save')
    await dialog.getByRole('button', { name: 'Сохранить', exact: true }).click()
    await request.requested
    await expect(toggle).toBeDisabled()
    request.release()
    await expect(dialog).toBeHidden()
    expect(api.saves[0]).toMatchObject({
      show_in_catalog_menu: true,
      is_active: false,
    })
    await page.reload()
    await page
      .getByRole('button', {
        name: `Редактировать бренд ${brand.name}`,
        exact: true,
      })
      .click()
    await expect(toggle).toHaveAttribute('aria-checked', 'true')
    await toggle.focus()
    await page.keyboard.press('Enter')
    await expect(toggle).toHaveAttribute('aria-checked', 'false')
    await dialog.getByRole('button', { name: 'Сохранить', exact: true }).click()
    await expect(dialog).toBeHidden()
    expect(api.saves[1]).toMatchObject({
      show_in_catalog_menu: false,
      is_active: false,
    })
    await page
      .getByRole('button', {
        name: `Редактировать бренд ${brand.name}`,
        exact: true,
      })
      .click()
    await expect(toggle).toHaveAttribute('aria-checked', 'false')
  })
}

test('failed save retains catalog menu choice for retry and cancelling create resets it', async ({
  page,
  browserIssueGuard,
}) => {
  browserIssueGuard.allowApiError(422, '/api/v1/admin/brands')
  const api = await mockBrands(page)
  await page.goto('/brands')
  const add = page.getByRole('button', { name: 'Добавить бренд', exact: true })
  await add.click()
  const dialog = page.getByRole('dialog')
  const toggle = dialog.getByRole('switch', { name: switchName })
  await dialog.getByRole('textbox', { name: /^Название/ }).fill(brand.name)
  await toggle.click()
  api.failSave()
  await dialog.getByRole('button', { name: 'Сохранить', exact: true }).click()
  await expect(dialog.getByRole('alert')).toContainText(
    'Не удалось сохранить бренд.',
  )
  await expect(toggle).toHaveAttribute('aria-checked', 'true')
  await expect(toggle).toBeEnabled()
  await dialog.getByRole('button', { name: 'Сохранить', exact: true }).click()
  await expect(dialog).toBeHidden()
  expect(api.saves[1]).toMatchObject({ show_in_catalog_menu: true })
  await add.click()
  await toggle.click()
  await page.keyboard.press('Escape')
  await expect(dialog).toBeHidden()
  await add.click()
  await expect(toggle).toHaveAttribute('aria-checked', 'false')
})

test('catalog menu switch fits required widths with visible focus and accessible description', async ({
  page,
}) => {
  await mockBrands(page)
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 720 })
    await page.goto('/brands')
    await page
      .getByRole('button', { name: 'Добавить бренд', exact: true })
      .click()
    const dialog = page.getByRole('dialog')
    const toggle = dialog.getByRole('switch', { name: switchName })
    await toggle.focus()
    await page.keyboard.press('Space')
    await expect(toggle).toBeFocused()
    await expect(toggle).toBeInViewport()
    await expect(toggle).toHaveAccessibleDescription(
      'Включите для брендов, которые нужно показывать под категориями',
    )
    expect(
      await dialog.evaluate((el) => el.scrollWidth <= el.clientWidth),
    ).toBe(true)
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: `../../.tmp/brand-catalog-menu/form-${width}.png`,
      animations: 'disabled',
    })
    if (width === 320 || width === 1280) {
      expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
    }
    await page.keyboard.press('Escape')
    await expect(dialog).toBeHidden()
  }
})

test('brand list saves only the menu flag without opening an editor or resetting search', async ({
  page,
}) => {
  const api = await mockBrands(page)
  await page.goto('/brands')
  const search = page.getByRole('textbox', {
    name: 'Поиск брендов',
    exact: true,
  })
  await search.fill('Kerama')
  const row = page.getByRole('article', { name: brand.name, exact: true })
  const toggle = row.getByRole('switch', {
    name: `В алфавитном меню: ${brand.name}`,
    exact: true,
  })
  await expect(toggle).toBeVisible()
  const pending = api.deferred.defer('save')
  await toggle.click()
  await pending.requested
  await expect(toggle).toBeDisabled()
  await expect(toggle).toHaveAttribute('aria-busy', 'true')
  await expect(toggle).toHaveAttribute('aria-checked', 'false')
  await expect(
    row.getByRole('button', { name: `Редактировать бренд ${brand.name}` }),
  ).toBeDisabled()
  await expect(
    row.getByRole('button', { name: `Удалить бренд ${brand.name}` }),
  ).toBeDisabled()
  await expect(page.getByRole('dialog')).toHaveCount(0)
  pending.release()
  await expect(toggle).toHaveAttribute('aria-checked', 'true')
  expect(api.saves).toEqual([{ show_in_catalog_menu: true }])
  await expect(search).toHaveValue('Kerama')
  await expect(toggle).toBeFocused()
  await row.getByText('В алфавитном меню', { exact: true }).click()
  await expect(toggle).toHaveAttribute('aria-checked', 'false')
  expect(api.saves[1]).toEqual({ show_in_catalog_menu: false })
  await page.reload()
  await expect(toggle).toHaveAttribute('aria-checked', 'false')
})

test('inline menu save failure keeps the row and previous value available for retry', async ({
  page,
  browserIssueGuard,
}) => {
  browserIssueGuard.allowApiError(422, `/api/v1/admin/brands/${brand.id}`)
  const api = await mockBrands(page, true)
  await page.goto('/brands')
  const row = page.getByRole('article', { name: brand.name, exact: true })
  const toggle = row.getByRole('switch')
  api.failSave()
  await toggle.click()
  await expect(page.getByRole('alert')).toContainText(brand.name)
  await expect(page.getByRole('alert')).toContainText(
    'Не удалось сохранить бренд.',
  )
  await expect(row).toBeVisible()
  await expect(toggle).toHaveAttribute('aria-checked', 'true')
  await expect(toggle).toBeEnabled()
  await toggle.click()
  await expect(toggle).toHaveAttribute('aria-checked', 'false')
  await expect(page.getByRole('alert')).toHaveCount(0)
})

test('a pending inline save never takes focus back after the user moves to search', async ({
  page,
}) => {
  const api = await mockBrands(page)
  await page.goto('/brands')
  const toggle = page
    .getByRole('article', { name: brand.name, exact: true })
    .getByRole('switch')
  const pending = api.deferred.defer('save')
  await toggle.click()
  await pending.requested
  const search = page.getByRole('textbox', {
    name: 'Поиск брендов',
    exact: true,
  })
  await search.click()
  pending.release()
  await expect(toggle).toHaveAttribute('aria-checked', 'true')
  await expect(search).toBeFocused()
})

test('inline menu switch is usable with keyboard and fits all required widths', async ({
  page,
}) => {
  await mockBrands(page)
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 882 })
    await page.goto('/brands')
    const row = page.getByRole('article', { name: brand.name, exact: true })
    const toggle = row.getByRole('switch')
    await toggle.focus()
    await page.keyboard.press('Space')
    await expect(toggle).toBeEnabled()
    await expect(toggle).toBeFocused()
    await expect(toggle).toBeInViewport()
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    expect(await row.evaluate((el) => el.scrollWidth <= el.clientWidth)).toBe(
      true,
    )
    if (width === 320 || width === 1280)
      expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
    await page.screenshot({
      path: `../../.tmp/brand-inline-menu/row-${width}.png`,
      animations: 'disabled',
      fullPage: true,
    })
  }
})
