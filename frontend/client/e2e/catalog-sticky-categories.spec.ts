import { expect, test } from '@playwright/test'

test.beforeEach(async ({ request }) => {
  await request.get(
    'http://127.0.0.1:8015/__scenario?name=catalog-card-standard',
  )
})

test('standalone catalog replaces sticky categories with sidebar and mobile drawer', async ({
  page,
}) => {
  await page.goto('/catalog')
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    await expect(page.locator('.categories--sticky')).toHaveCount(0)
    if (width >= 1024) {
      const categories = page.getByRole('navigation', {
        name: 'Категории каталога',
      })
      await expect(categories).toBeVisible()
      await expect(categories).toHaveCSS('position', 'static')
      await expect(
        page.getByRole('button', { name: 'Фильтры', exact: true }),
      ).toBeHidden()
    } else {
      const opener = page.getByRole('button', { name: 'Фильтры', exact: true })
      await opener.click()
      const dialog = page.getByRole('dialog', { name: 'Фильтры каталога' })
      await dialog
        .getByRole('link', { name: 'Керамогранит', exact: true })
        .click()
      await expect(dialog).toHaveCount(0)
      await expect(page).toHaveURL(/category=porcelain/)
      await expect(opener).toBeFocused()
    }
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
  }
})
