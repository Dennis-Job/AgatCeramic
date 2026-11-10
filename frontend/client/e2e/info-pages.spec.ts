import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

test('delivery, payment and designer CMS pages render their own wide intro and readable content', async ({
  page,
  request,
}) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=default')
  for (const [slug, title] of [
    ['delivery', 'Доставка'],
    ['payment', 'Оплата'],
    ['designers', 'Дизайнерам'],
  ]) {
    const response = await request.get(`/${slug}`)
    expect(response.status()).toBe(200)
    expect(await response.text()).toContain(
      `/images/page-intro/${slug}-wide.webp`,
    )
    await page.goto(`/${slug}`)
    await expect(page.locator('main h1')).toHaveText(title!)
    await expect(page.locator('.text-block')).not.toHaveCount(0)
    await expect(page.getByText('Содержимое пока не добавлено')).toHaveCount(0)
    await expect(page.locator('.page-intro__image')).toHaveJSProperty(
      'naturalWidth',
      2400,
    )
    for (const width of [320, 768, 1280, 2560]) {
      await page.setViewportSize({ width, height: 900 })
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= innerWidth,
        ),
      ).toBe(true)
    }
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  }
})
