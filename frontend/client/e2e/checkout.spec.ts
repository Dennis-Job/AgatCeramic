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
  items = [{ product_id: 1, quantity: 2 }],
) {
  await request.get(mock + '/__scenario?name=default')
  await request.get(mock + '/__cart/reset?mode=' + mode)
  const { token } = await (
    await request.post(mock + '/__cart/seed', {
      data: { items },
    })
  ).json()
  await page.addInitScript(
    ({ key, token }) => localStorage.setItem(key, token),
    { key: tokenKey, token },
  )
  return token
}

async function fillCheckout(page: Page, acceptConsent = true) {
  await expect(page.getByRole('heading', { name: 'Контакты' })).toBeVisible()
  await page.getByLabel('ФИО', { exact: true }).fill(details.customer_name)
  await page.getByLabel('Телефон', { exact: true }).fill(details.customer_phone)
  await page.getByLabel('Email', { exact: true }).fill(details.customer_email)
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
  if (acceptConsent)
    await page.getByRole('checkbox', { name: /даю согласие/i }).check()
}

async function orderWrites(request: APIRequestContext) {
  const { requests } = await (await request.get(mock + '/__cart/stats')).json()
  return requests.filter((entry) => entry.pathname === '/api/v1/orders')
}

test('checkout requires a valid customer email before advancing and fits all widths', async ({
  page,
  request,
}) => {
  await prepare(request, page)
  await page.goto('/checkout')
  await page.getByLabel('ФИО', { exact: true }).fill(details.customer_name)
  await page.getByLabel('Телефон', { exact: true }).fill(details.customer_phone)
  const email = page.getByLabel('Email', { exact: true })
  await expect(email).toHaveAttribute('required', '')
  await page.getByRole('button', { name: 'Продолжить', exact: true }).click()
  await expect(email).toBeFocused()
  await expect(page.getByLabel('Адрес доставки', { exact: true })).toHaveCount(
    0,
  )
  await email.fill('invalid-email')
  await page.getByRole('button', { name: 'Продолжить', exact: true }).click()
  await expect(page.getByLabel('Адрес доставки', { exact: true })).toHaveCount(
    0,
  )
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    expect(
      await page.evaluate(
        () =>
          document.documentElement.scrollWidth <=
          document.documentElement.clientWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: `../../.tmp/required-email-${width}.png`,
      fullPage: true,
    })
  }
  await email.fill(details.customer_email)
  await page.getByRole('button', { name: 'Продолжить', exact: true }).click()
  await expect(page.getByLabel('Адрес доставки', { exact: true })).toBeVisible()
  expect(await orderWrites(request)).toEqual([])
})

test('checkout requires explicit consent and displays both published documents', async ({
  page,
  request,
}) => {
  await prepare(request, page)
  await page.goto('/checkout')
  await fillCheckout(page, false)
  const consent = page.getByRole('checkbox', { name: /даю согласие/i })
  const submit = page.getByRole('button', { name: /Подтвердить заказ/ })
  await expect(consent).toBeEnabled()
  await expect(consent).not.toBeChecked()
  await expect(submit).toBeDisabled()
  await expect(page.locator('#checkout-consent-text')).toHaveText(
    'Я даю согласие на обработку персональных данных, в соответствии с политикой конфиденциальности',
  )
  for (const [name, path, body] of [
    ['согласие', '/personal-data/', 'Тестовый текст согласия.'],
    ['политикой', '/privacy-policy/', 'Тестовый текст политики.'],
  ]) {
    const link = page.getByRole('link', { name, exact: true })
    await expect(link).toHaveAttribute('href', path)
    const opened = page.waitForEvent('popup')
    await link.click()
    const document = await opened
    await expect(document.getByText(body, { exact: true })).toBeVisible()
    await document.close()
    await expect(consent).not.toBeChecked()
    await expect(submit).toBeDisabled()
  }
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    expect(
      await page.evaluate(
        () =>
          document.documentElement.scrollWidth <=
          document.documentElement.clientWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: `../../.tmp/checkout-consent-${width}.png`,
      fullPage: true,
    })
  }
  expect(await orderWrites(request)).toEqual([])

  await page
    .locator('#checkout-consent-text')
    .click({ position: { x: 5, y: 5 } })
  await expect(consent).toBeChecked()
  await consent.focus()
  await consent.press('Space')
  await expect(consent).not.toBeChecked()
  await consent.press('Space')
  await expect(submit).toBeEnabled()
  await page.getByRole('button', { name: 'Назад', exact: true }).click()
  await page.getByRole('button', { name: 'Продолжить', exact: true }).click()
  await expect(consent).toBeEnabled()
  await expect(consent).not.toBeChecked()
  await consent.check()
  await submit.click()
  await expect(
    page.getByRole('heading', { name: 'Заказ принят' }),
  ).toBeVisible()
  expect(await orderWrites(request)).toHaveLength(1)
})

test('checkout cannot confirm until unavailable legal documents can be loaded', async ({
  page,
  request,
}) => {
  await prepare(request, page)
  await page.route(
    '**/api/v1/legal-documents/consent',
    (route) => route.fulfill({ status: 404 }),
    { times: 1 },
  )
  await page.goto('/checkout')
  await fillCheckout(page, false)
  const consent = page.getByRole('checkbox', { name: /даю согласие/i })
  await expect(page.getByRole('alert')).toContainText('Документы недоступны')
  await expect(consent).toBeDisabled()
  await expect(
    page.getByRole('button', { name: /Подтвердить заказ/ }),
  ).toBeDisabled()
  expect(await orderWrites(request)).toEqual([])
  await page
    .getByRole('button', { name: 'Повторить', exact: true })
    .press('Enter')
  await expect(consent).toBeEnabled()
  await expect(consent).not.toBeChecked()
  await expect(consent).toBeFocused()
})

test('checkout fields have white surfaces and missing thumbnails show text', async ({
  page,
  request,
}) => {
  await prepare(request, page, 'default', [
    { product_id: 1, quantity: 1 },
    { product_id: 2, quantity: 1 },
  ])
  await page.route('**/images/home/category-porcelain.webp', (route) =>
    route.abort(),
  )
  await page.goto('/checkout')

  const white = 'rgb(255, 255, 255)'
  const name = page.getByLabel('ФИО', { exact: true })
  const phone = page.getByLabel('Телефон', { exact: true })
  const email = page.getByLabel('Email', { exact: true })
  for (const field of [name, phone, email])
    await expect(field).toHaveCSS('background-color', white)

  const summary = page.getByRole('complementary', { name: /Ваш заказ/ })
  for (const product of ['Тестовый керамогранит', 'Тестовая затирка'])
    await expect(
      summary
        .getByRole('listitem')
        .filter({ hasText: product })
        .getByText('Нет фото', { exact: true }),
    ).toBeVisible()

  await name.fill(details.customer_name)
  await phone.fill(details.customer_phone)
  await email.fill(details.customer_email)
  await page.getByRole('button', { name: 'Продолжить', exact: true }).click()
  const address = page.getByLabel('Адрес доставки', { exact: true })
  const comment = page.getByLabel('Комментарий (необязательно)', {
    exact: true,
  })
  await expect(address).toHaveCSS('background-color', white)
  await comment.focus()
  await expect(comment).toHaveCSS('background-color', white)
})

test('drawer opens the real cart page, then checkout creates a server order', async ({
  page,
  request,
}) => {
  const token = await prepare(request, page)
  await page.goto('/about')
  await page.getByRole('button', { name: 'Корзина', exact: true }).click()
  const drawer = page.getByRole('dialog', { name: 'Корзина', exact: true })
  const drawerQuantity = drawer.locator('.ui-quantity')
  await expect(drawerQuantity).toBeVisible()
  const drawerSize = (await drawerQuantity.boundingBox())!
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
  const cartQuantity = page.getByRole('main').locator('.cart-item__quantity')
  const cartSize = (await cartQuantity.boundingBox())!
  expect({ width: cartSize.width, height: cartSize.height }).toEqual({
    width: drawerSize.width,
    height: drawerSize.height,
  })
  await page.getByRole('button', { name: /Увеличить количество/ }).click()
  await expect(page.getByRole('spinbutton')).toHaveValue('3')

  await page.getByRole('link', { name: 'Оформить заказ', exact: true }).click()
  await expect(page).toHaveURL(/\/checkout$/)
  await expect(
    page.getByRole('heading', { name: 'Оформление заказа', level: 1 }),
  ).toBeVisible()
  await page.setViewportSize({ width: 320, height: 760 })
  expect(
    await page.evaluate(() => {
      const root = document.documentElement
      return root.scrollWidth <= root.clientWidth
    }),
  ).toBe(true)
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
  const token = await prepare(request, page)
  await page.route(
    '**/api/v1/orders',
    async (route) => {
      const response = await route.fetch({ maxRetries: 0 })
      expect(response.status()).toBe(201)
      await route.abort('failed')
    },
    { times: 1 },
  )
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

test('pending checkout keeps the buyer on the page until the order number is available', async ({
  page,
  request,
}) => {
  await prepare(request, page)
  await page.goto('/checkout')
  await fillCheckout(page)
  const { promise: resume, resolve: release } = Promise.withResolvers<void>()
  await page.route('**/api/v1/orders', async (route) => {
    const response = await route.fetch()
    await resume
    await route.fulfill({ response })
  })

  try {
    await page.getByRole('button', { name: /Подтвердить заказ/ }).click()
    await expect(
      page.getByRole('button', { name: 'Отправляем…' }),
    ).toBeDisabled()
    await expect(
      page.getByRole('link', { name: /Изменить корзину/ }),
    ).toHaveCount(0)
    await page
      .getByRole('banner')
      .getByRole('link', { name: 'Каталог', exact: true })
      .click()
  } finally {
    release()
  }

  await expect(
    page.getByRole('heading', { name: 'Заказ принят' }),
  ).toBeFocused()
  await expect(page).toHaveURL(/\/checkout$/)
  await expect(page.getByText(`Заказ № ${orderNumber}`)).toBeVisible()
  await page.getByRole('link', { name: 'Вернуться в каталог' }).click()
  await expect(page).toHaveURL(/\/catalog$/)
  expect(await orderWrites(request)).toHaveLength(1)
})

test('checkout follows the visual keyboard order and keeps mobile summary items inside their boxes', async ({
  page,
  request,
}) => {
  await prepare(request, page)
  await page.setViewportSize({ width: 1280, height: 900 })
  await page.goto('/checkout')
  const name = page.getByLabel('ФИО', { exact: true })
  const edit = page.getByRole('link', { name: /Изменить корзину/ })
  await edit.focus()
  await page.keyboard.press('Shift+Tab')
  await expect(page.getByRole('button', { name: 'Продолжить' })).toBeFocused()

  await name.fill(details.customer_name)
  await page.setViewportSize({ width: 320, height: 760 })
  const toggle = page.getByRole('button', { name: 'Показать' })
  await expect
    .poll(async () => {
      const [summary, field] = await Promise.all([
        toggle.boundingBox(),
        name.boundingBox(),
      ])
      return summary !== null && field !== null && summary.y < field.y
    })
    .toBe(true)
  await toggle.focus()
  await page.keyboard.press('Tab')
  await expect(name).toBeFocused()
  await expect(name).toHaveValue(details.customer_name)
  await toggle.click()
  const summary = page.getByRole('complementary', { name: /Ваш заказ/ })
  const photo = summary.locator('li img')
  await expect(photo).toBeVisible()
  await expect
    .poll(() => photo.evaluate((image) => image.clientHeight))
    .toBe(52)
  const total = summary.locator('.checkout-summary__total strong')
  await expect
    .poll(() =>
      total.evaluate((node) => {
        const text = node.firstChild!
        const range = document.createRange()
        range.selectNodeContents(text)
        return range.getClientRects().length
      }),
    )
    .toBe(1)
  expect(
    await page.evaluate(() => {
      const root = document.documentElement
      return root.scrollWidth <= root.clientWidth
    }),
  ).toBe(true)
  await name.focus()
  await page.setViewportSize({ width: 1280, height: 900 })
  await expect(page.locator('.checkout-layout > div').first()).toHaveClass(
    'checkout-layout__main',
  )
  await expect(name).toBeFocused()
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
