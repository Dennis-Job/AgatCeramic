import { expect, test } from './fixtures'

const settings = {
  operator_type: 'individual_entrepreneur',
  seller_name: 'ИП Тест',
  entrepreneur_name: 'Тестовый Продавец',
  inn: '123456789012',
  ogrnip: '123456789012345',
  address: 'Москва',
  phones: ['+79990000000'],
  email: 'seller@example.test',
  bank_name: null,
  bank_bik: null,
  bank_account: null,
  bank_correspondent_account: null,
  bank_details: null,
  publish_bank_details: false,
  updated_at: '2026-09-25T10:00:00Z',
}
const document = {
  id: 4,
  type: 'privacy_policy',
  version: 'v1',
  body: 'Проверяемый текст политики ПДн.',
  published_at: null as string | null,
  created_at: '2026-09-25T10:00:00Z',
}

test('settings preserve edits after a failed save and require legal text review before publication', async ({
  page,
  browserIssueGuard,
}) => {
  browserIssueGuard.allowApiError(422, '/api/v1/admin/site-settings')
  let saveAttempts = 0
  let published = false
  let savedName = ''
  let savedPhones: string[] = []
  await page.route('**/sanctum/csrf-cookie', (route) =>
    route.fulfill({ status: 204 }),
  )
  await page.route('**/api/v1/**', (route) => {
    const path = new URL(route.request().url()).pathname.replace('/api/v1', '')
    if (path === '/admin/auth/me') {
      return route.fulfill({
        json: {
          data: {
            id: 1,
            name: 'Тестовый администратор',
            email: 'admin@example.test',
            status: 'active',
            permissions: ['settings.manage', 'settings.approve'],
          },
        },
      })
    }
    if (
      path === '/admin/site-settings' &&
      route.request().method() === 'PATCH'
    ) {
      saveAttempts += 1
      savedName = route.request().postDataJSON().seller_name
      savedPhones = route.request().postDataJSON().phones
      return saveAttempts === 1
        ? route.fulfill({
            status: 422,
            json: { error: { message: 'Проверьте реквизиты.' } },
          })
        : route.fulfill({
            json: { data: { ...settings, seller_name: savedName } },
          })
    }
    if (path === '/admin/site-settings')
      return route.fulfill({ json: { data: settings } })
    if (path === '/admin/legal-documents')
      return route.fulfill({
        json: {
          data: [
            {
              ...document,
              published_at: published ? '2026-09-25T11:00:00Z' : null,
            },
          ],
        },
      })
    if (path === '/admin/legal-documents/4/publish') {
      published = true
      return route.fulfill({
        json: { data: { ...document, published_at: '2026-09-25T11:00:00Z' } },
      })
    }
    if (path === '/admin/compliance-approvals')
      return route.fulfill({ json: { data: [] } })
    return route.fulfill({
      status: 404,
      json: { error: { message: 'Unknown endpoint' } },
    })
  })

  await page.goto('/settings')
  await expect(
    page.getByRole('heading', { name: 'Настройки сайта' }),
  ).toBeVisible()
  const sellerName = page.getByLabel('Наименование продавца')
  const phone = page.getByRole('textbox', { name: /^Телефон/ })
  await expect(phone).toHaveValue('+7 (999) 000-00-00')
  await phone.fill('')
  await expect(phone).toHaveValue('+7')
  await phone.pressSequentially('495')
  await page.getByRole('button', { name: 'Сохранить реквизиты' }).click()
  expect(saveAttempts).toBe(0)
  await expect(phone).toHaveValue('+7 (495)')
  await phone.pressSequentially('1204567')
  await expect(phone).toHaveValue('+7 (495) 120-45-67')
  await sellerName.fill('ИП Исправленный')
  await page.getByRole('button', { name: 'Сохранить реквизиты' }).click()
  await expect(page.getByRole('alert')).toContainText('Проверьте реквизиты.')
  await expect(sellerName).toHaveValue('ИП Исправленный')
  await page.getByRole('button', { name: 'Сохранить реквизиты' }).click()
  await expect(
    page.getByText('Реквизиты сохранены.', { exact: true }),
  ).toBeVisible()
  expect(saveAttempts).toBe(2)
  expect(savedName).toBe('ИП Исправленный')
  expect(savedPhones).toEqual(['+74951204567'])

  await page
    .getByRole('button', { name: 'Просмотреть Политика ПДн версии v1' })
    .click()
  await expect(page.getByRole('dialog')).toContainText(document.body)
  await page
    .getByRole('dialog')
    .getByRole('button', { name: 'Опубликовать эту версию' })
    .click()
  await expect(page.getByRole('dialog')).toHaveCount(0)
  await expect(page.getByText('Опубликован', { exact: true })).toBeVisible()
  expect(published).toBe(true)
})

test('legacy phones require explicit selection and a single masked phone remains usable at every width', async ({
  page,
}, testInfo) => {
  let savedPhones: string[] | null = null
  await page.route('**/sanctum/csrf-cookie', (route) =>
    route.fulfill({ status: 204 }),
  )
  await page.route('**/api/v1/**', (route) => {
    const path = new URL(route.request().url()).pathname.replace('/api/v1', '')
    if (path === '/admin/auth/me')
      return route.fulfill({
        json: {
          data: {
            id: 1,
            name: 'Редактор',
            email: 'editor@example.test',
            status: 'active',
            permissions: ['settings.manage'],
          },
        },
      })
    if (path === '/admin/site-settings') {
      if (route.request().method() === 'PATCH') {
        savedPhones = route.request().postDataJSON().phones
        return route.fulfill({
          json: { data: { ...settings, phones: savedPhones } },
        })
      }
      return route.fulfill({
        json: {
          data: { ...settings, phones: ['+74951204567', '+79991112233'] },
        },
      })
    }
    if (
      ['/admin/legal-documents', '/admin/compliance-approvals'].includes(path)
    )
      return route.fulfill({ json: { data: [] } })
    return route.fulfill({
      status: 404,
      json: { error: { message: 'Unknown endpoint' } },
    })
  })
  await page.goto('/settings')
  await expect(
    page.getByText('Ранее сохранено несколько телефонов.', { exact: false }),
  ).toBeVisible()
  const phone = page.getByRole('textbox', { name: /^Телефон/ })
  await expect(phone).toHaveValue('+7')
  await phone.focus()
  await phone.evaluate((input: HTMLInputElement) =>
    input.setSelectionRange(0, 2),
  )
  await phone.press('Backspace')
  await expect(phone).toHaveValue('+7')
  await phone.pressSequentially('495')
  await phone.press('ControlOrMeta+A')
  await phone.press('Backspace')
  await expect(phone).toHaveValue('+7')
  await page.getByRole('button', { name: 'Сохранить реквизиты' }).click()
  await expect(page.getByRole('alert')).toContainText('Выберите один телефон')
  expect(savedPhones).toBeNull()
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    await expect(phone).toBeVisible()
    expect(
      await page.evaluate(() => document.documentElement.scrollWidth),
    ).toBeLessThanOrEqual(width)
    await page.screenshot({
      path: testInfo.outputPath(`settings-legacy-phone-${width}.png`),
      fullPage: true,
    })
  }
  await page
    .getByRole('button', { name: '+7 (999) 111-22-33', exact: true })
    .click()
  await expect(phone).toHaveValue('+7 (999) 111-22-33')
  await phone.focus()
  await phone.press('End')
  await phone.press('Backspace')
  await expect(phone).toHaveValue('+7 (999) 111-22-3')
  await phone.pressSequentially('3')
  await page.getByRole('button', { name: 'Сохранить реквизиты' }).click()
  await expect(
    page.getByText('Реквизиты сохранены.', { exact: true }),
  ).toBeVisible()
  expect(savedPhones).toEqual(['+79991112233'])
  await page.reload()
  await page
    .getByRole('button', { name: 'Убрать телефон', exact: true })
    .click()
  await page.getByRole('button', { name: 'Сохранить реквизиты' }).click()
  await expect(
    page.getByText('Реквизиты сохранены.', { exact: true }),
  ).toBeVisible()
  expect(savedPhones).toEqual([])
})
