import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

test.beforeEach(async ({ request, page }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=standalone-default')
  await page.setViewportSize({ width: 1280, height: 900 })
})

test('compact select supports keyboard exploration, cancel and commit', async ({
  page,
}) => {
  await page.goto('/catalog')
  const sort = page.getByRole('combobox', { name: 'Сортировка', exact: true })
  await expect(sort).toHaveText('Название А–Я')
  await expect(sort).toHaveCSS('min-height', '44px')
  await sort.focus()
  await sort.press('ArrowDown')
  const list = page.getByRole('listbox', { name: 'Сортировка', exact: true })
  await expect(list).toBeVisible()
  await expect(sort).toBeFocused()
  await sort.press('End')
  await expect(sort).toHaveText('Название А–Я')
  await sort.press('Escape')
  await expect(list).toBeHidden()
  await expect(page).not.toHaveURL(/sort=/)
  await sort.press('ArrowDown')
  // Playwright inserts non-Latin text without keydown; use the real event shape.
  await sort.dispatchEvent('keydown', { key: 'ц' })
  await sort.press('ArrowDown')
  await sort.press('Enter')
  await expect(page).toHaveURL(/sort=price_desc/)
  await expect(sort).toHaveText('Цена по убыванию')
  await sort.press('ArrowDown')
  await sort.dispatchEvent('keydown', { key: 'н' })
  await sort.press('Space')
  await expect(sort).toHaveText('Название А–Я')
  await expect(page).not.toHaveURL(/sort=/)
  await sort.press('End')
  await sort.press('Tab')
  await expect(page).toHaveURL(/sort=newest/)
})

test('select panels work in the mobile dialog and Escape closes only the list', async ({
  page,
}, testInfo) => {
  await page.goto('/catalog')
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    if (width < 1024)
      await page.getByRole('button', { name: 'Фильтры', exact: true }).click()
    const scope =
      width < 1024
        ? page.getByRole('dialog', { name: 'Фильтры каталога' })
        : page.getByRole('complementary', { name: 'Фильтры каталога' })
    const brand = scope.getByRole('combobox', { name: 'Бренд', exact: true })
    await expect(
      scope.getByRole('combobox', { name: 'Коллекция', exact: true }),
    ).toBeDisabled()
    await brand.click()
    const list = scope.getByRole('listbox', { name: 'Бренд', exact: true })
    await expect(list).toBeVisible()
    await expect(brand).toHaveCSS('outline-style', 'none')
    await expect(list).toHaveCSS('background-color', 'rgb(250, 250, 248)')
    const bounds = await list.boundingBox()
    expect(bounds!.x).toBeGreaterThanOrEqual(0)
    expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(width)
    await page.screenshot({ path: testInfo.outputPath(`select-${width}.png`) })
    if (width === 1280) {
      const violations = (
        await new AxeBuilder({ page }).include('.ui-select').analyze()
      ).violations
      expect(violations).toEqual([])
    }
    await brand.press('Escape')
    await expect(list).toBeHidden()
    await expect(brand).toBeFocused()
    if (width < 1024) {
      await expect(scope).toBeVisible()
      await brand.press('Escape')
      await expect(scope).toHaveCount(0)
    }
  }
})

test('normal motion animates both directions and reduced motion removes delays', async ({
  page,
}) => {
  await page.emulateMedia({ reducedMotion: 'no-preference' })
  await page.goto('/catalog')
  const sort = page.getByRole('combobox', { name: 'Сортировка', exact: true })
  await sort.click()
  const list = page.locator('.catalog-toolbar .ui-select__panel')
  await expect(list).toHaveCSS(
    'transition-duration',
    '0.35s, 0.35s, 0.35s, 0.35s',
  )
  await expect(list).toHaveCSS('opacity', '1')
  await sort.click()
  await expect(list).toHaveAttribute('inert', '')
  expect(
    await list.evaluate((el) => el.getAnimations().length),
  ).toBeGreaterThan(0)
  await expect(list).toBeHidden()
  await sort.click()
  await expect(list).toBeVisible()
  await page.getByRole('heading', { name: 'Фильтры', exact: true }).click()
  await expect(list).toBeHidden()
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await sort.click()
  await expect(list).toHaveCSS('transform', 'none')
  await expect(list).toHaveCSS('transition-duration', '1e-05s')
  await page.mouse.move(700, 850)
  await page.mouse.wheel(0, 1000)
  await expect(list).toBeHidden()
})
