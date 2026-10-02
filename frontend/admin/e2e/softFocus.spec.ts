import AxeBuilder from '@axe-core/playwright'
import { expect, test } from './fixtures'
import { mockAdminBaseline } from './adminBaselineApi'

const widths = [320, 602, 640, 768, 1024, 1280, 1440, 1920, 2560]
const edge = 'rgb(100, 125, 209)'

test('product radio selection stays subtle with a pointer and has one soft keyboard halo at every width', async ({
  page,
}) => {
  await mockAdminBaseline(page)
  await page.goto('/products')
  const group = page.getByRole('group', { name: 'Активность', exact: true })
  const radio = group.getByRole('radio', { name: 'Все', exact: true })
  const label = radio.locator('..')
  for (const width of widths) {
    await page.setViewportSize({ width, height: 910 })
    await label.click()
    await expect(radio).toBeChecked()
    await expect(label).toHaveCSS('border-width', '1px')
    await expect(label).toHaveCSS('border-color', 'rgb(186, 213, 255)')
    await expect(label).toHaveCSS('box-shadow', 'none')
    await page.screenshot({
      path: `.tmp/soft-focus/products-pointer-${width}.png`,
    })
    await page.keyboard.press('Tab')
    await radio.focus()
    await expect(label).toHaveCSS('border-color', edge)
    await expect(label).toHaveCSS('outline-style', 'none')
    await expect(label).toHaveCSS(
      'box-shadow',
      /rgba\(100, 125, 209, 0.14\).*4px/,
    )
    await page.screenshot({
      path: `.tmp/soft-focus/products-keyboard-${width}.png`,
    })
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  }
  await page.keyboard.press('ArrowDown')
  await expect(
    group.getByRole('radio', { name: 'Активные', exact: true }),
  ).toBeChecked()
})

test('fields use one thin focus edge, composite inputs have no duplicate outline, and error/disabled states stay visible', async ({
  page,
}) => {
  await mockAdminBaseline(page)
  await page.goto('/ui-kit')
  const form = page.locator('[data-ui-kit-section="form-controls"]')
  const input = form.getByRole('textbox', { name: /^Название/ })
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 910 })
    await input.scrollIntoViewIfNeeded()
    await input.click()
    await expect(input.locator('..')).toHaveCSS('border-color', edge)
    await expect(input.locator('..')).toHaveCSS('border-width', '1px')
    await expect(input).toHaveCSS('outline-style', 'none')
    await expect(input).toHaveCSS('box-shadow', 'none')
    await page.screenshot({ path: `.tmp/soft-focus/input-${width}.png` })
  }
  const textarea = form.getByPlaceholder('Текст комментария')
  await textarea.focus()
  await expect(textarea).toHaveCSS('border-color', edge)
  await expect(textarea).toHaveCSS('outline-style', 'none')
  await page.screenshot({ path: '.tmp/soft-focus/textarea-focus.png' })
  const error = form.getByRole('textbox', { name: /^Поле с ошибкой/ })
  await error.focus()
  await expect(error).toHaveAttribute('aria-invalid', 'true')
  await expect(error.locator('..')).toHaveCSS(
    'border-color',
    'rgb(180, 35, 24)',
  )
  await expect(form.getByRole('alert').first()).toBeVisible()
  await page.screenshot({ path: '.tmp/soft-focus/error-focus.png' })
  const disabled = form.getByRole('textbox', {
    name: /^Недоступный комментарий/,
  })
  await disabled.focus()
  await expect(disabled).not.toBeFocused()
  await expect(disabled).not.toHaveCSS('border-color', edge)
  const select = form
    .getByRole('button', { name: 'Демонстрационная категория', exact: true })
    .and(form.locator('button:not(:disabled)'))
  await select.focus()
  await expect(select).toHaveCSS('border-color', edge)
  await expect(select).toHaveCSS('outline-style', 'none')
  await page.screenshot({ path: '.tmp/soft-focus/select-focus.png' })
  const date = form
    .getByRole('textbox', {
      name: 'Дата публикации',
      exact: true,
    })
    .and(form.locator('input:not(:disabled)'))
  await date.focus()
  await expect(date.locator('..')).toHaveCSS('border-color', edge)
  await expect(date).toHaveCSS('outline-style', 'none')
  await page.screenshot({ path: '.tmp/soft-focus/date-focus.png' })
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
})

test('filled and secondary buttons and selected checkbox keep a thin visible keyboard focus', async ({
  page,
}) => {
  await mockAdminBaseline(page)
  await page.setViewportSize({ width: 1280, height: 910 })
  await page.goto('/ui-kit')
  const buttons = page.locator('[data-ui-kit-section="buttons"]')
  await page.keyboard.press('Tab')
  for (const name of [
    'Основная',
    'Вторичная',
    'Опасное действие',
    'Прозрачная',
  ]) {
    const button = buttons.getByRole('button', { name, exact: true })
    await button.focus()
    await expect(button).toHaveCSS('outline-width', '1px')
    await expect(button).toHaveCSS('outline-color', edge)
    await expect(button).toHaveCSS('outline-offset', '0px')
    await page.screenshot({ path: `.tmp/soft-focus/button-${name}.png` })
  }
  const checkbox = page.getByRole('checkbox', {
    name: 'Видимый в каталоге',
    exact: true,
  })
  await checkbox.focus()
  await expect(checkbox).toBeChecked()
  await expect(checkbox.locator('..')).toHaveCSS('border-color', edge)
  await page.screenshot({ path: '.tmp/soft-focus/checkbox-keyboard.png' })
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
})

test('paint-contained tables keep the soft keyboard focus inside the scroll region', async ({
  page,
}) => {
  await mockAdminBaseline(page)
  await page.goto('/products')
  const table = page.getByRole('region', {
    name: 'Таблица товаров',
    exact: true,
  })
  await page.keyboard.press('Tab')
  await table.focus()
  await expect(table).toHaveCSS('contain', 'paint')
  await expect(table).toHaveCSS('outline-style', 'none')
  await expect(table).toHaveCSS('box-shadow', /inset/)
  await page.screenshot({ path: '.tmp/soft-focus/table-keyboard.png' })
})

test('forced colors retain a system keyboard outline for buttons, fields and radio wrappers', async ({
  page,
}) => {
  await mockAdminBaseline(page)
  await page.emulateMedia({ forcedColors: 'active' })
  await page.goto('/products')
  const radio = page
    .getByRole('group', { name: 'Активность', exact: true })
    .getByRole('radio', { name: 'Все', exact: true })
  await page.keyboard.press('Tab')
  await radio.focus()
  await expect(radio.locator('..')).toHaveCSS('outline-width', '2px')
  await expect(radio.locator('..')).toHaveCSS('outline-style', 'solid')
  await expect(radio.locator('..')).toHaveCSS('box-shadow', 'none')
  await expect(radio.locator('..').locator('svg')).toHaveCSS(
    'visibility',
    'visible',
  )
  const unselected = page
    .getByRole('group', { name: 'Активность', exact: true })
    .getByRole('radio', { name: 'Активные', exact: true })
  await expect(unselected.locator('..').locator('svg')).toHaveCSS(
    'visibility',
    'hidden',
  )
  const button = page.getByRole('button', {
    name: 'Добавить товар',
    exact: true,
  })
  await button.focus()
  await expect(button).toHaveCSS('outline-width', '2px')
  await expect(button).toHaveCSS('outline-style', 'solid')
  const input = page.getByRole('textbox', { name: 'Поиск', exact: true })
  await input.focus()
  await expect(input.locator('..')).toHaveCSS('outline-width', '2px')
  await page.screenshot({ path: '.tmp/soft-focus/forced-colors.png' })
  await page.goto('/ui-kit')
  const checked = page.getByRole('checkbox', {
    name: 'Видимый в каталоге',
    exact: true,
  })
  const unchecked = page.getByRole('checkbox', {
    name: 'Публиковать товар',
    exact: true,
  })
  await expect(checked.locator('..').locator('svg')).toHaveCSS(
    'visibility',
    'visible',
  )
  await expect(unchecked.locator('..').locator('svg')).toHaveCSS(
    'visibility',
    'hidden',
  )
  await checked.focus()
  await page.screenshot({ path: '.tmp/soft-focus/forced-colors-checkbox.png' })
})
