import AxeBuilder from '@axe-core/playwright'
import { expect, test } from './fixtures'
import { mockCatalogApi } from './catalogApi'
import { mockAdminBaseline } from './adminBaselineApi'

test('product actions show distinct hover backgrounds and descriptive tooltips', async ({
  page,
}) => {
  await mockCatalogApi(page)
  await page.goto('/products')
  const actions = [
    [
      'Создать похожий товар Монте Тиберио',
      'Копировать товар — создать похожий',
    ],
    ['Редактировать товар Монте Тиберио', 'Редактировать товар'],
    ['Удалить товар Монте Тиберио', 'Удалить товар'],
  ]
  for (const [name, description] of actions) {
    const button = page.getByRole('button', { name, exact: true })
    await button.hover()
    await expect(button).toHaveCSS(
      'background-color',
      name.startsWith('Удалить') ? 'rgb(254, 228, 226)' : 'rgb(217, 234, 255)',
    )
    await expect(page.getByRole('tooltip')).toHaveText(description!)
    await expect(page.getByRole('tooltip')).toHaveCSS(
      'background-color',
      'rgb(255, 255, 255)',
    )
    await expect(page.getByRole('tooltip')).toHaveCSS(
      'color',
      'rgb(17, 24, 32)',
    )
    await expect(page.getByRole('tooltip')).toHaveCSS(
      'border-top-color',
      'rgb(227, 233, 241)',
    )
    if (name.startsWith('Создать'))
      await page.screenshot({
        path: test.info().outputPath('product-copy-tooltip.png'),
      })
    await page.keyboard.press('Escape')
    await expect(page.getByRole('tooltip')).toHaveCount(0)
  }
  const clear = page.getByRole('button', { name: 'Очистить поле' })
  await page.getByRole('textbox', { name: 'Поиск' }).fill('Монте')
  await clear.hover()
  await expect(page.getByRole('tooltip')).toHaveCount(0)
})

test('tooltips remain within the viewport and outside table clipping at all supported widths', async ({
  page,
}) => {
  await mockCatalogApi(page)
  for (const width of [320, 640, 768, 1024, 1280, 1440, 1920, 2560]) {
    await page.setViewportSize({ width, height: 800 })
    await page.goto('/products')
    const edit = page.getByRole('button', {
      name: 'Редактировать товар Монте Тиберио',
      exact: true,
    })
    await edit.scrollIntoViewIfNeeded()
    await page.mouse.move(2, 2)
    await edit.hover()
    const cell = edit.locator('..').locator('..')
    const cellBox = (await cell.boundingBox())!
    for (const action of await cell.getByRole('button').all()) {
      const actionBox = (await action.boundingBox())!
      expect(actionBox.x).toBeGreaterThanOrEqual(cellBox.x + 8)
      expect(actionBox.x + actionBox.width).toBeLessThanOrEqual(
        cellBox.x + cellBox.width - 8,
      )
    }
    await expect(page.getByRole('tooltip')).toHaveText('Редактировать товар')
    const box = await page.getByRole('tooltip').boundingBox()
    expect(box!.x).toBeGreaterThanOrEqual(0)
    expect(box!.x + box!.width).toBeLessThanOrEqual(width)
    expect(box!.y).toBeGreaterThanOrEqual(0)
    expect(box!.y + box!.height).toBeLessThanOrEqual(800)
    await page.screenshot({
      path: test.info().outputPath(`product-edit-tooltip-${width}.png`),
    })
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.keyboard.press('Escape')
    await expect(page.getByRole('tooltip')).toHaveCount(0)
    await edit.focus()
    await expect(page.getByRole('tooltip')).toHaveCount(0)
  }
})

test('modal close controls have no tooltip and Escape closes the dialog immediately', async ({
  page,
}) => {
  await mockCatalogApi(page)
  await page.goto('/products')
  await page
    .getByRole('button', {
      name: 'Редактировать товар Монте Тиберио',
      exact: true,
    })
    .click()
  const close = page.getByRole('button', { name: 'Закрыть карточку товара' })
  await close.focus()
  await expect(page.getByRole('tooltip')).toHaveCount(0)
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  await page.keyboard.press('Escape')
  await expect(page.getByRole('tooltip')).toHaveCount(0)
  await expect(page.getByRole('dialog')).toHaveCount(0)
})

test('UI-kit demonstrates the reusable action tooltip', async ({ page }) => {
  await mockCatalogApi(page)
  await page.goto('/ui-kit')
  const copy = page.getByRole('button', { name: 'Копировать', exact: true })
  await copy.scrollIntoViewIfNeeded()
  await copy.hover()
  await expect(page.getByRole('tooltip')).toHaveText(
    'Копировать товар — создать похожий',
  )
  await page.screenshot({
    path: test.info().outputPath('ui-kit-action-tooltip.png'),
  })
})

test('category action icons have focused tooltips only while hovered', async ({
  page,
}) => {
  await mockCatalogApi(page)
  await page.goto('/categories')
  for (const [name, tooltip] of [
    [
      'Настроить характеристики категории Керамогранит',
      'Настроить характеристики',
    ],
    ['Редактировать категорию Керамогранит', 'Редактировать категорию'],
    ['Удалить категорию Керамогранит', 'Удалить категорию'],
  ]) {
    const action = page.getByRole('button', { name, exact: true })
    await action.hover()
    if (name.startsWith('Удалить'))
      await expect(action).toHaveCSS('background-color', 'rgb(254, 228, 226)')
    await expect(page.getByRole('tooltip')).toHaveText(tooltip!)
    await page.getByRole('heading', { name: 'Категории', exact: true }).hover()
    await expect(page.getByRole('tooltip')).toHaveCount(0)
  }
})

test('mobile navigation close control does not show a tooltip', async ({
  page,
}) => {
  await mockCatalogApi(page)
  for (const width of [320, 640]) {
    await page.setViewportSize({ width, height: 800 })
    await page.goto('/products')
    await page.getByRole('button', { name: 'Открыть меню' }).click()
    const close = page.getByRole('button', { name: 'Закрыть меню' })
    await close.focus()
    await expect(page.getByRole('tooltip')).toHaveCount(0)
    await page.keyboard.press('Escape')
    await expect(
      page.getByRole('dialog', { name: 'Разделы панели' }),
    ).toBeHidden()
  }
})

test('header popovers remain readable without a tooltip over the open panel', async ({
  page,
}) => {
  await mockCatalogApi(page)
  await page.goto('/products')
  for (const name of ['Уведомления', 'Меню пользователя']) {
    const trigger = page.getByRole('button', { name, exact: true })
    await trigger.hover()
    await expect(trigger).toHaveAttribute('aria-expanded', 'true')
    // Wait beyond tooltip's hover delay to detect the popup/tooltip conflict.
    await page.waitForTimeout(350)
    await expect(page.getByRole('tooltip')).toHaveCount(0)
    await expect(trigger).toHaveAttribute('aria-expanded', 'true')
    await page.screenshot({
      path: test
        .info()
        .outputPath(
          `header-${name === 'Уведомления' ? 'notifications' : 'user'}.png`,
        ),
    })
    await page.keyboard.press('Escape')
    await expect(trigger).toHaveAttribute('aria-expanded', 'false')
    await page.getByRole('heading', { name: 'Товары', exact: true }).hover()
  }
})

test('edit actions share the same blue style across admin workspaces', async ({
  page,
}) => {
  await mockAdminBaseline(page)
  for (const [route, name] of [
    ['/products', 'Редактировать товар Монте Тиберио'],
    ['/categories', 'Редактировать категорию Керамогранит'],
    ['/brands', 'Редактировать бренд Kerama Marazzi'],
    ['/attribute-groups', 'Редактировать группу Размеры'],
    ['/attributes', 'Редактировать характеристику Ширина'],
    ['/employees', 'Редактировать сотрудника Тестовый администратор'],
    ['/roles', 'Редактировать роль Администратор'],
    ['/media', 'Изменить файл Каталог коллекций'],
  ]) {
    await page.goto(route!)
    const edit = page.getByRole('button', { name, exact: true })
    await expect(edit).toHaveCSS('color', 'rgb(0, 80, 224)')
    await edit.hover()
    await expect(edit).toHaveCSS('color', 'rgb(0, 80, 224)')
    await expect(edit).toHaveCSS('background-color', 'rgb(217, 234, 255)')
    await page.screenshot({
      path: test.info().outputPath(`edit-${route!.slice(1)}.png`),
    })
  }
})
