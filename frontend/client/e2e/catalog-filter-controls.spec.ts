import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=standalone-default')
})

test('light controls, stock switches and exact price slider stay synchronized', async ({
  page,
}) => {
  await page.goto('/catalog?category=tile')
  const sidebar = page.getByRole('complementary', { name: 'Фильтры каталога' })
  await expect(page.getByRole('combobox', { name: 'Сортировка' })).toHaveCSS(
    'background-color',
    'rgb(250, 250, 248)',
  )
  await expect(
    sidebar
      .getByRole('group', { name: 'Цвет', exact: true })
      .getByLabel('Серый', { exact: true }),
  ).toHaveCSS('background-color', 'rgb(250, 250, 248)')
  const stock = sidebar.getByRole('switch', { name: 'В наличии' })
  await stock.focus()
  await stock.press('Space')
  await expect(stock).toBeChecked()
  await expect(stock).toHaveCSS('background-color', 'rgb(20, 20, 20)')
  await expect(
    sidebar
      .locator('.ui-checkbox--switch')
      .first()
      .locator('.ui-checkbox__thumb'),
  ).toHaveCSS('background-color', 'rgb(255, 255, 255)')
  await expect(page).toHaveURL(/in_stock=1/)
  const lower = sidebar.getByRole('slider', { name: 'Минимальная цена' })
  const upper = sidebar.getByRole('slider', { name: 'Максимальная цена' })
  await expect(lower).toHaveAttribute('min', '10000')
  await expect(upper).toHaveAttribute('max', '35000')
  await lower.focus()
  await lower.press('ArrowRight')
  await expect(page).toHaveURL(/price_from=100.01/)
  await expect(sidebar.getByLabel('Цена от', { exact: true })).toHaveValue(
    '100,01',
  )
  await sidebar.getByLabel('Цена до', { exact: true }).fill('250,25')
  await expect(page).toHaveURL(/price_to=250.25/)
  await expect(upper).toHaveValue('25025')
  await expect(sidebar.locator('.ui-range__selection')).toHaveCSS(
    'background-color',
    'rgb(20, 20, 20)',
  )
  expect(
    await sidebar
      .locator('.ui-range__selection')
      .evaluate((el) => el.getBoundingClientRect().width),
  ).toBeLessThan(
    await sidebar
      .locator('.ui-range__track')
      .evaluate((el) => el.getBoundingClientRect().width),
  )
  await lower.press('End')
  await expect(page).toHaveURL(/price_from=250.25/)
  await expect(lower).toHaveValue('25025')
  await sidebar.getByRole('button', { name: 'Сбросить фильтры' }).click()
  await expect(page).not.toHaveURL(/price_from=|price_to=|in_stock=/)
  await expect(lower).toHaveValue('10000')
  await expect(upper).toHaveValue('35000')
  expect(
    (await new AxeBuilder({ page }).include('.catalog-sidebar').analyze())
      .violations,
  ).toEqual([])
})

test('mobile price slider and switches keep a draft until Apply and cancel on Escape', async ({
  page,
}) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/catalog?category=tile')
  await page.getByRole('button', { name: 'Фильтры', exact: true }).click()
  const dialog = page.getByRole('dialog', { name: 'Фильтры каталога' })
  const lower = dialog.getByRole('slider', { name: 'Минимальная цена' })
  await lower.press('ArrowRight')
  await dialog.getByRole('switch', { name: 'В наличии' }).check()
  await expect(page).not.toHaveURL(/price_from=|in_stock=/)
  await lower.press('Escape')
  await expect(dialog).toBeHidden()
  await page.getByRole('button', { name: 'Фильтры', exact: true }).click()
  await expect(lower).toHaveValue('10000')
  await lower.press('ArrowRight')
  await dialog.getByRole('button', { name: 'Применить', exact: true }).click()
  await expect(page).toHaveURL(/price_from=100.01/)
})

test('range handles support pointer dragging and absence of priced products', async ({
  page,
  request,
}) => {
  await page.goto('/catalog?category=tile')
  const lower = page.getByRole('slider', { name: 'Минимальная цена' })
  await lower.scrollIntoViewIfNeeded()
  const box = (await lower.boundingBox())!
  await page.mouse.move(box.x + 9, box.y + box.height / 2)
  await page.mouse.down()
  await page.mouse.move(box.x + box.width / 3, box.y + box.height / 2)
  await page.mouse.up()
  await expect(page).toHaveURL(/price_from=/)
  await lower.press('End')
  await expect(lower).toHaveValue('35000')
  const collapsed = (await lower.boundingBox())!
  await page.mouse.move(
    collapsed.x + collapsed.width - 9,
    collapsed.y + collapsed.height / 2,
  )
  await page.mouse.down()
  await page.mouse.move(
    collapsed.x + collapsed.width / 2,
    collapsed.y + collapsed.height / 2,
  )
  await page.mouse.up()
  await expect(lower).not.toHaveValue('35000')
  await expect(
    page.getByRole('slider', { name: 'Максимальная цена' }),
  ).toHaveValue('35000')
  await request.get('http://127.0.0.1:8015/__scenario?name=standalone-empty')
  await page.goto('/catalog')
  await expect(page.getByRole('slider')).toHaveCount(0)
  await expect(page.getByLabel('Цена от', { exact: true })).toBeVisible()
})
