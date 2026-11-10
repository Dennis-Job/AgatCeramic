import { readFileSync } from 'node:fs'
import { expect, test, type Page } from '@playwright/test'

type Credentials = { email: string; password: string }
type SmokeState = {
  full_access: Credentials
  view_only: Credentials
  brand: { id: number; name: string }
  order: { id: number; number: string }
  contact: { id: number }
}

function loadState(): SmokeState {
  const stateFile = process.env.ADMIN_SMOKE_STATE_FILE
  if (!stateFile) throw new Error('ADMIN_SMOKE_STATE_FILE is required.')

  return JSON.parse(readFileSync(stateFile, 'utf8')) as SmokeState
}

async function login(page: Page, credentials: Credentials): Promise<void> {
  await page.goto('/login')
  await page.getByLabel('Email').fill(credentials.email)
  await page.getByLabel('Пароль').fill(credentials.password)
  await page.getByRole('button', { name: 'Войти' }).click()
  await expect(page).toHaveURL('/')
}

async function logout(page: Page): Promise<void> {
  const responsePromise = page.waitForResponse(
    (response) =>
      response.request().method() === 'POST' &&
      new URL(response.url()).pathname === '/api/v1/admin/auth/logout',
  )
  await page.getByRole('button', { name: 'Меню пользователя' }).click()
  await page.getByRole('button', { name: 'Выйти' }).click()
  expect((await responsePromise).status()).toBe(204)
  await expect(page).toHaveURL('/login')
}

test('production Admin crosses the real Sanctum and Laravel boundary', async ({
  page,
}) => {
  const state = loadState()
  await login(page, state.full_access)

  await page.goto('/brands')
  await expect(
    page.getByRole('heading', { level: 1, name: 'Бренды' }),
  ).toBeVisible()
  await expect(page.getByText(state.brand.name, { exact: true })).toBeVisible()

  const createdBrandName = `browser-brand-${Date.now()}`
  await page.getByRole('button', { name: 'Добавить бренд' }).click()
  const brandDialog = page.getByRole('dialog', { name: 'Новый бренд' })
  await brandDialog.getByLabel('Название').fill(createdBrandName)
  const brandMutation = page.waitForResponse(
    (response) =>
      response.request().method() === 'POST' &&
      new URL(response.url()).pathname === '/api/v1/admin/brands',
  )
  await brandDialog.getByRole('button', { name: 'Сохранить' }).click()
  expect((await brandMutation).status()).toBe(201)
  await expect(page.getByText(createdBrandName, { exact: true })).toBeVisible()

  await page.goto('/orders')
  const orderOpener = page.getByRole('button', {
    name: `Открыть заказ ${state.order.number}`,
  })
  await expect(orderOpener).toBeVisible()
  await orderOpener.click()
  await page.getByLabel('Новый статус заказа').click()
  await page.getByRole('button', { name: 'В обработке', exact: true }).click()
  const orderMutation = page.waitForResponse(
    (response) =>
      response.request().method() === 'PATCH' &&
      new URL(response.url()).pathname ===
        `/api/v1/admin/orders/${state.order.id}/status`,
  )
  await page.getByRole('button', { name: 'Сохранить', exact: true }).click()
  expect((await orderMutation).status()).toBe(200)
  await expect(page.getByLabel('Новый статус заказа')).toContainText(
    'В обработке',
  )

  await page.goto('/contacts')
  await page
    .getByRole('button', { name: `Открыть обращение ${state.contact.id}` })
    .click()
  const contactDialog = page.getByRole('dialog', {
    name: `Обращение #${state.contact.id}`,
    exact: true,
  })
  const contactMutation = page.waitForResponse(
    (response) =>
      response.request().method() === 'PATCH' &&
      new URL(response.url()).pathname ===
        `/api/v1/admin/contact-requests/${state.contact.id}/status`,
  )
  await contactDialog
    .getByRole('button', { name: 'Начать обработку', exact: true })
    .click()
  const contactResponse = await contactMutation
  expect(contactResponse.status()).toBe(200)
  expect(await contactResponse.json()).toMatchObject({
    data: { id: state.contact.id, status: 'processing' },
  })
  await expect(
    contactDialog.getByRole('button', { name: 'Ожидать клиента', exact: true }),
  ).toBeEnabled()
  await expect(
    contactDialog.getByRole('list').filter({ hasText: 'Новое →' }),
  ).toContainText('В обработке')
  await contactDialog
    .getByRole('button', { name: 'Закрыть обращение', exact: true })
    .click()
  await expect(contactDialog).toHaveCount(0)

  await logout(page)
  const unauthenticated = await page.evaluate(async () => {
    const response = await fetch('/api/v1/admin/auth/me', {
      headers: { Accept: 'application/json' },
      credentials: 'include',
    })
    return { status: response.status, body: await response.json() }
  })
  expect(unauthenticated).toMatchObject({
    status: 401,
    body: { error: { code: 'unauthenticated' } },
  })

  await login(page, state.view_only)
  await page.goto('/brands')
  await expect(page).toHaveURL('/')

  const forbidden = await page.evaluate(async () => {
    const response = await fetch('/api/v1/admin/brands', {
      headers: { Accept: 'application/json' },
      credentials: 'include',
    })
    return { status: response.status, body: await response.json() }
  })
  expect(forbidden).toMatchObject({
    status: 403,
    body: { error: { code: 'forbidden' } },
  })

  await logout(page)
})
