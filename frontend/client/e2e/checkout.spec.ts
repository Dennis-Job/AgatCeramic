import {
  expect,
  test,
  type APIRequestContext,
  type Page,
} from '@playwright/test'

const mock = 'http://127.0.0.1:8015'
const tokenKey = 'agatceramic.cart.token'
const orderNumber = 'AC-20261103-A1B2C3D4E5'
const details = {
  customer_name: 'Анна Тестова',
  customer_phone: '+79991234567',
  customer_email: 'checkout@example.test',
  delivery_address: 'Тестовый город, улица Макета, 42',
  customer_comment: 'Тестовый комментарий к заказу',
}

async function prepare(
  request: APIRequestContext,
  page: Page,
  mode = 'default',
) {
  await request.get(mock + '/__scenario?name=default')
  await request.get(mock + '/__cart/reset?mode=' + mode)
  const { token } = await (
    await request.post(mock + '/__cart/seed', {
      data: { items: [{ product_id: 1, quantity: 2 }] },
    })
  ).json()
  await page.addInitScript(
    ({ key, token }) => localStorage.setItem(key, token),
    { key: tokenKey, token },
  )
  return token
}

async function fillCheckout(page: Page) {
  await expect(page.getByRole('heading', { name: 'Контакты' })).toBeVisible()
  await page.getByLabel('ФИО', { exact: true }).fill(details.customer_name)
  await page.getByLabel('Телефон', { exact: true }).fill(details.customer_phone)
  await page
    .getByLabel('Email (необязательно)', { exact: true })
    .fill(details.customer_email)
  await page.getByRole('button', { name: 'Продолжить', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Доставка' })).toBeVisible()
  await page
    .getByLabel('Адрес доставки', { exact: true })
    .fill(details.delivery_address)
  await page
    .getByLabel('Комментарий (необязательно)', { exact: true })
    .fill(details.customer_comment)
  await page.getByRole('button', { name: 'Продолжить', exact: true }).click()
  await expect(
    page.getByRole('heading', { name: 'Подтверждение' }),
  ).toBeVisible()
}

async function orderWrites(request: APIRequestContext) {
  const { requests } = await (await request.get(mock + '/__cart/stats')).json()
  return requests.filter((entry) => entry.pathname === '/api/v1/orders')
}

test('drawer opens the real cart page, then checkout creates a server order', async ({
  page,
  request,
}) => {
  const token = await prepare(request, page)
  await page.goto('/about')
  await page.getByRole('button', { name: 'Корзина', exact: true }).click()
  const drawer = page.getByRole('dialog', { name: 'Корзина', exact: true })
  const drawerCheckout = drawer.getByRole('button', { name: 'Оформить заказ' })
  const drawerArrow = drawerCheckout.locator('.ui-button__arrow')
  await page.emulateMedia({ reducedMotion: 'no-preference' })
  await drawerCheckout.hover()
  await expect(drawerArrow).toHaveCSS('transform', 'matrix(1, 0, 0, 1, 4, 0)')
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await expect(drawerArrow).toHaveCSS('transform', 'none')
  await drawerCheckout.click()
  await expect(page).toHaveURL(/\/cart$/)
  await expect(drawer).toHaveCount(0)
  await expect(
    page.getByRole('heading', { name: 'Корзина', level: 1 }),
  ).toBeVisible()
  await expect(
    page.getByText('Тестовый керамогранит', { exact: true }),
  ).toBeVisible()
  await expect(page.getByRole('spinbutton')).toHaveValue('2')
  await page.getByRole('button', { name: /Увеличить количество/ }).click()
  await expect(page.getByRole('spinbutton')).toHaveValue('3')

  await page.getByRole('link', { name: 'Оформить заказ', exact: true }).click()
  await expect(page).toHaveURL(/\/checkout$/)
  await expect(
    page.getByRole('heading', { name: 'Оформление заказа', level: 1 }),
  ).toBeVisible()
  await page.setViewportSize({ width: 320, height: 760 })
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBe(
    320,
  )
  await fillCheckout(page)
  expect(await orderWrites(request)).toEqual([])
  await page.getByRole('button', { name: /Подтвердить заказ/ }).click()
  await expect(
    page.getByRole('heading', { name: 'Заказ принят', level: 1 }),
  ).toBeVisible()
  await expect(
    page.getByText(orderNumber, { exact: false }).first(),
  ).toBeVisible()
  await expect(
    page
      .getByRole('main')
      .getByText(/3\s703,50/)
      .last(),
  ).toBeVisible()

  const writes = await orderWrites(request)
  expect(writes).toHaveLength(1)
  expect(writes[0]).toMatchObject({
    method: 'POST',
    token,
    key: expect.stringMatching(/^.{16,255}$/),
    payload: details,
  })
  expect(Object.keys(writes[0].payload).sort()).toEqual(
    Object.keys(details).sort(),
  )
  const clientState = await page.evaluate(
    () =>
      location.href +
      JSON.stringify(localStorage) +
      JSON.stringify(sessionStorage),
  )
  for (const value of Object.values(details))
    expect(clientState).not.toContain(value)
  expect(clientState).not.toContain(token)
  await expect(
    page
      .getByRole('main')
      .getByRole('link', { name: /каталог/i })
      .last(),
  ).toHaveAttribute('href', '/catalog')
})

test('an ambiguous order response keeps the form and reuses its idempotency key', async ({
  page,
  request,
}) => {
  const token = await prepare(request, page, 'order-disconnect')
  await page.goto('/checkout')
  await fillCheckout(page)
  const submit = page.getByRole('button', { name: /Подтвердить заказ/ })
  await submit.click()
  await expect(page.getByRole('alert')).toBeVisible()
  const first = await orderWrites(request)
  expect(first).toHaveLength(1)
  expect(first[0]).toMatchObject({ token, payload: details })
  await expect(submit).toBeEnabled()
  await submit.click()
  await expect(
    page.getByRole('heading', { name: 'Заказ принят', level: 1 }),
  ).toBeVisible()
  const replayed = await orderWrites(request)
  expect(replayed).toHaveLength(2)
  expect(replayed[1]).toEqual(first[0])
})

test('checkout rejects unavailable items without claiming an order was placed', async ({
  page,
  request,
}) => {
  await prepare(request, page, 'order-unavailable')
  await page.goto('/checkout')
  await fillCheckout(page)
  await page.getByRole('button', { name: /Подтвердить заказ/ }).click()
  await expect(page.getByRole('alert')).toBeVisible()
  await expect(
    page.getByRole('heading', { name: 'Заказ принят', level: 1 }),
  ).toHaveCount(0)
  const stats = await (await request.get(mock + '/__cart/stats')).json()
  expect(stats.items).toMatchObject([{ product_id: 1, quantity: 2 }])
  expect(await orderWrites(request)).toHaveLength(1)
})

test('empty cart and direct checkout show a path back without an order action', async ({
  page,
  request,
}) => {
  await request.get(mock + '/__scenario?name=default')
  await request.get(mock + '/__cart/reset')
  await page.goto('/cart')
  await expect(
    page.getByRole('heading', { name: 'Корзина пуста' }),
  ).toBeVisible()
  await expect(
    page.getByRole('link', { name: 'Перейти в каталог' }),
  ).toHaveAttribute('href', '/catalog')
  await page.goto('/checkout')
  await expect(
    page
      .getByRole('main')
      .getByRole('link', { name: /корзин/i })
      .last(),
  ).toHaveAttribute('href', '/cart')
  await expect(
    page.getByRole('button', { name: /Подтвердить заказ/ }),
  ).toHaveCount(0)
  expect(await orderWrites(request)).toEqual([])
})
