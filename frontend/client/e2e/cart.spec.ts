import { expect, test } from '@playwright/test'

const mock = 'http://127.0.0.1:8015'
const tokenKey = 'agatceramic.cart.token'

test.beforeEach(async ({ request }) => {
  await request.get(mock + '/__scenario?name=default')
  await request.get(mock + '/__cart/reset')
})

test('a new visitor has no cart until interaction and tokens stay out of SSR and URLs', async ({
  page,
  request,
}) => {
  const html = await (await request.get('/about')).text()
  expect(html).not.toContain('c'.repeat(64))
  await page.goto('/about')
  expect(
    (await (await request.get(mock + '/__cart/stats')).json()).requests,
  ).toHaveLength(0)
  const trigger = page.getByRole('button', { name: 'Корзина', exact: true })
  await expect(trigger).toBeEnabled()
  await trigger.click()
  const dialog = page.getByRole('dialog', { name: 'Корзина', exact: true })
  await expect(dialog).toBeVisible()
  await expect(dialog.getByText('Корзина пока пуста')).toBeVisible()
  const stats = await (await request.get(mock + '/__cart/stats')).json()
  expect(stats.requests).toHaveLength(1)
  expect(stats.requests[0]).toMatchObject({
    method: 'GET',
    pathname: '/api/v1/cart',
    token: null,
  })
  expect(page.url()).not.toContain('c'.repeat(64))
  await page.keyboard.press('Escape')
  await expect(dialog).toHaveCount(0)
  await expect(trigger).toBeFocused()
})

test('saved cart badge restores after reload before opening and cart edits/deletes through the API', async ({
  page,
  request,
}) => {
  const { token } = await (
    await request.post(mock + '/__cart/seed', {
      data: { items: [{ product_id: 1, quantity: 2 }] },
    })
  ).json()
  await page.addInitScript(
    ({ key, token }) => localStorage.setItem(key, token),
    { key: tokenKey, token },
  )
  await page.goto('/about')
  const trigger = page.getByRole('button', { name: 'Корзина', exact: true })
  const badge = trigger.locator('.cart-badge')
  const dialog = page.getByRole('dialog', { name: 'Корзина', exact: true })
  await expect(badge).toHaveText('1')
  await page.reload()
  await expect(badge).toHaveText('1')
  await expect(dialog).toHaveCount(0)
  const restored = await (await request.get(mock + '/__cart/stats')).json()
  expect(restored.requests.map((entry) => [entry.method, entry.token])).toEqual(
    [
      ['GET', token],
      ['GET', token],
    ],
  )
  await trigger.click()
  await expect(
    dialog.getByText('Тестовый керамогранит', { exact: true }),
  ).toBeVisible()
  await expect(
    dialog.getByText('2 469,00 ₽', { exact: true }).first(),
  ).toBeVisible()
  await dialog.getByRole('button', { name: /Увеличить количество/ }).click()
  await expect(
    dialog.getByText('3 703,50 ₽', { exact: true }).first(),
  ).toBeVisible()
  const stats = await (await request.get(mock + '/__cart/stats')).json()
  expect(
    stats.requests.some(
      (entry) =>
        entry.method === 'PATCH' &&
        entry.payload.quantity === 3 &&
        entry.token === token,
    ),
  ).toBe(true)
  await dialog.getByRole('button', { name: /Удалить/ }).click()
  await expect(dialog.getByText('Корзина пока пуста')).toBeVisible()
  await expect(badge).toHaveCount(0)
  const after = await (await request.get(mock + '/__cart/stats')).json()
  expect(
    after.requests.some(
      (entry) => entry.method === 'DELETE' && entry.token === token,
    ),
  ).toBe(true)
})

test('the cart opens against the right edge with a scrolling list and pinned total', async ({
  page,
  request,
}) => {
  const { token } = await (
    await request.post(mock + '/__cart/seed', {
      data: {
        items: Array.from({ length: 8 }, (_, index) => ({
          product_id: 1,
          quantity: index + 1,
        })),
      },
    })
  ).json()
  await page.addInitScript(
    ({ key, token }) => localStorage.setItem(key, token),
    { key: tokenKey, token },
  )
  await page.setViewportSize({ width: 1024, height: 440 })
  await page.goto('/about')
  const trigger = page.getByRole('button', { name: 'Корзина', exact: true })
  await trigger.click()
  const dialog = page.getByRole('dialog', { name: 'Корзина', exact: true })
  await expect(dialog.getByRole('heading', { level: 2 })).toHaveText(
    'Корзина (8)',
  )
  expect(await dialog.boundingBox()).toEqual({
    x: 564,
    y: 0,
    width: 460,
    height: 440,
  })
  const footer = dialog.getByRole('button', { name: 'Оформить заказ' })
  await expect(footer).toBeInViewport()
  const lastItem = dialog.getByRole('article').last()
  await expect(lastItem).not.toBeInViewport()
  await lastItem.scrollIntoViewIfNeeded()
  await expect(lastItem).toBeInViewport()
  await expect(footer).toBeInViewport()
  await expect(dialog.getByRole('heading', { level: 2 })).toBeInViewport()
  await page.mouse.click(100, 200)
  await expect(dialog).toHaveCount(0)
  await expect(trigger).toBeFocused()
})

test('expired saved cart recovers without replaying a mutation and errors remain retryable', async ({
  page,
  request,
}) => {
  await request.get(mock + '/__cart/reset?mode=expired')
  await page.addInitScript(
    ({ key }) => localStorage.setItem(key, 'c'.repeat(64)),
    { key: tokenKey },
  )
  await page.goto('/about')
  await expect
    .poll(() => page.evaluate((key) => localStorage.getItem(key), tokenKey))
    .toBeNull()
  const expired = await (await request.get(mock + '/__cart/stats')).json()
  expect(expired.requests.map((entry) => [entry.method, entry.token])).toEqual([
    ['GET', 'c'.repeat(64)],
  ])
  await page.getByRole('button', { name: 'Корзина', exact: true }).click()
  const dialog = page.getByRole('dialog', { name: 'Корзина', exact: true })
  await expect(dialog.getByText('Корзина пока пуста')).toBeVisible()
  const stats = await (await request.get(mock + '/__cart/stats')).json()
  expect(stats.requests.map((entry) => entry.method)).toEqual(['GET', 'GET'])
  await page.keyboard.press('Escape')
  await request.get(mock + '/__cart/reset?mode=error')
  await page.getByRole('button', { name: 'Корзина', exact: true }).click()
  await expect(dialog.getByRole('alert')).toContainText('Не удалось')
  await expect(dialog.getByRole('button', { name: 'Повторить' })).toBeEnabled()
  await request.get(mock + '/__cart/reset')
  await dialog.getByRole('button', { name: 'Повторить' }).click()
  await expect(dialog.getByText('Корзина пока пуста')).toBeVisible()
})

test('cart rejects fractional quantities, restores server values after rejection and never retries failed writes', async ({
  page,
  request,
}) => {
  const { token } = await (
    await request.post(mock + '/__cart/seed', {
      data: { items: [{ product_id: 1, quantity: 2 }] },
    })
  ).json()
  await page.addInitScript(
    ({ key, token }) => localStorage.setItem(key, token),
    { key: tokenKey, token },
  )
  await page.goto('/about')
  await page.getByRole('button', { name: 'Корзина', exact: true }).click()
  const dialog = page.getByRole('dialog', { name: 'Корзина', exact: true })
  const quantity = dialog.getByRole('spinbutton')
  await expect(quantity).toHaveValue('2')
  await quantity.fill('2.5')
  await quantity.press('Enter')
  await quantity.blur()
  const fractional = await (await request.get(mock + '/__cart/stats')).json()
  expect(fractional.requests.filter((entry) => entry.method !== 'GET')).toEqual(
    [],
  )
  await expect(quantity).toHaveValue('2')
  await expect(dialog.getByRole('alert')).toContainText(
    'Укажите целое количество',
  )

  await quantity.fill('11')
  await quantity.press('Enter')
  await expect(dialog.getByRole('alert')).toHaveText(
    'В наличии 10 м² товара «Тестовый керамогранит».',
  )
  await expect(dialog.getByRole('button', { name: 'Повторить' })).toHaveCount(0)
  await expect(quantity).toHaveValue('2')
  const rejected = await (await request.get(mock + '/__cart/stats')).json()
  expect(rejected.items[0].quantity).toBe(2)

  let attemptedWrites = 0
  await page.route('**/api/v1/cart/items/*', async (route) => {
    if (route.request().method() === 'PATCH') {
      attemptedWrites++
      await route.abort('connectionfailed')
    } else await route.continue()
  })
  await dialog.getByRole('button', { name: /Увеличить количество/ }).click()
  await expect(dialog.getByRole('alert')).toContainText('Не удалось')
  await expect(quantity).toHaveValue('2')
  expect(attemptedWrites).toBe(1)
  await dialog.getByRole('button', { name: 'Повторить' }).click()
  await expect(dialog.getByRole('alert')).toHaveCount(0)
  await expect(quantity).toHaveValue('2')
  expect(attemptedWrites).toBe(1)
})
