import { expect, test } from '@playwright/test'

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=default')
})

const documents = [
  {
    path: '/personal-data/',
    title: 'Согласие на обработку персональных данных',
    body: 'Тестовый текст согласия.',
  },
  {
    path: '/privacy-policy/',
    title: 'Политика конфиденциальности',
    body: 'Тестовый текст политики.',
  },
]

for (const document of documents) {
  test(`${document.path} renders the published document on the server and fits all widths`, async ({
    page,
    request,
  }) => {
    const response = await request.get(document.path)
    expect(response.status()).toBe(200)
    expect(await response.text()).toContain(document.body)
    const errors: string[] = []
    page.on('console', (message) => {
      if (/hydration/i.test(message.text())) errors.push(message.text())
    })
    await page.goto(document.path)
    await expect(
      page.getByRole('heading', { name: document.title, level: 1 }),
    ).toBeVisible()
    await expect(page.getByText(document.body, { exact: true })).toBeVisible()
    await expect(page.locator('link[rel="canonical"]')).toHaveAttribute(
      'href',
      `https://agatceramic.example${document.path}`,
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
        path: `../../.tmp/legal-${document.path.split('/')[1]}-${width}.png`,
        fullPage: true,
      })
    }
    expect(errors).toEqual([])
  })
}

test('an unpublished document shows an honest error and can be retried', async ({
  page,
  request,
}) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=legal-unavailable')
  const response = await page.goto('/personal-data/')
  expect(response?.status()).toBe(404)
  await expect(page.getByRole('alert')).toContainText('Документ недоступен')
  await expect(
    page.getByText('Тестовый текст согласия.', { exact: true }),
  ).toHaveCount(0)
  await request.get('http://127.0.0.1:8015/__scenario?name=default')
  await page.getByRole('button', { name: 'Повторить загрузку' }).click()
  await expect(
    page.getByText('Тестовый текст согласия.', { exact: true }),
  ).toBeVisible()
})
