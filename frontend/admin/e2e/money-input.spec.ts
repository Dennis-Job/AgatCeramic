import AxeBuilder from '@axe-core/playwright'
import { expect, test } from './fixtures'
import { mockCatalogApi } from './catalogApi'

for (const width of [320, 640, 768, 1024, 1280]) {
  test(`money inputs display grouping and submit exact decimal strings at ${width}px`, async ({
    page,
  }, testInfo) => {
    await page.setViewportSize({ width, height: 900 })
    await mockCatalogApi(page, {
      sourceProduct: { price: '17926.00', old_price: '24459.00' },
    })
    await page.goto('/products')
    await page
      .getByRole('button', { name: 'Редактировать товар Монте Тиберио' })
      .click()
    const dialog = page.getByRole('dialog')
    const price = dialog.getByRole('textbox', { name: /^Цена(?: |$)/ })
    const oldPrice = dialog.getByRole('textbox', { name: 'Старая цена' })
    await expect(price).toHaveValue('17\u00a0926,00')
    await expect(oldPrice).toHaveValue('24\u00a0459,00')
    await price.click()
    await expect(price).toBeFocused()
    await price.press('ControlOrMeta+A')
    await price.press('ArrowLeft')
    await expect
      .poll(() =>
        price.evaluate((el) => (el as HTMLInputElement).selectionStart),
      )
      .toBe(0)
    await price.press('ArrowRight')
    await price.press('ArrowRight')
    await price.press('ArrowRight')
    await price.press('Backspace')
    await expect(price).toHaveValue('1\u00a0926,00')
    await price.fill('')
    await price.press(',')
    await price.press('5')
    await expect(price).toHaveValue(',5')
    await price.press('Tab')
    await expect(price).toHaveValue('0,50')
    await price.fill('1,23')
    await price.press('ControlOrMeta+A')
    await price.press('ArrowLeft')
    await price.press('ArrowRight')
    await price.press('Backspace')
    await expect(price).toHaveValue(',23')
    await price.press('5')
    await expect(price).toHaveValue('5,23')
    await price.fill('1234567,89')
    await expect(price).toHaveValue('1\u00a0234\u00a0567,89')
    await oldPrice.fill('2\u202f000\u00a0000.10 ₽')
    await oldPrice.press('Tab')
    await expect(oldPrice).toHaveValue('2\u00a0000\u00a0000,10')
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= window.innerWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: testInfo.outputPath(`money-modal-${width}.png`),
      fullPage: true,
    })
    if (width === 1280) {
      const results = await new AxeBuilder({ page }).analyze()
      expect(
        results.violations.filter((v) =>
          ['serious', 'critical'].includes(v.impact ?? ''),
        ),
      ).toEqual([])
    }
    const request = page.waitForRequest(
      (req) =>
        req.method() === 'PATCH' && req.url().endsWith('/admin/products/1'),
    )
    await dialog.getByRole('button', { name: 'Сохранить и продолжить' }).click()
    expect((await request).postDataJSON()).toMatchObject({
      price: '1234567.89',
      old_price: '2000000.10',
    })
    await dialog.getByRole('button', { name: /Основное и продажа/ }).click()
    await expect(price).toHaveValue('1\u00a0234\u00a0567,89')
    await expect(oldPrice).toHaveValue('2\u00a0000\u00a0000,10')
    await oldPrice
      .locator('..')
      .getByRole('button', { name: 'Очистить поле' })
      .click()
    await expect(oldPrice).toHaveValue('')
    const clearRequest = page.waitForRequest(
      (req) =>
        req.method() === 'PATCH' && req.url().endsWith('/admin/products/1'),
    )
    await dialog.getByRole('button', { name: 'Сохранить и продолжить' }).click()
    expect((await clearRequest).postDataJSON()).toMatchObject({
      price: '1234567.89',
      old_price: null,
    })
  })
}
