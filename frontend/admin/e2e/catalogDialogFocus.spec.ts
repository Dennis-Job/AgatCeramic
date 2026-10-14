import { expect, test } from './fixtures'
import { mockAdminBaseline } from './adminBaselineApi'
import { mockCategoryOverview } from './categoryOverviewApi'

for (const feature of ['categories', 'brands'] as const) {
  for (const mode of ['create', 'edit'] as const) {
    test(`${feature} ${mode} keeps the full focus halo inside the dialog scroll area`, async ({
      page,
    }) => {
      if (feature === 'categories') await mockCategoryOverview(page)
      else await mockAdminBaseline(page)
      for (const width of [320, 640, 768, 1024, 1280]) {
        await page.setViewportSize({ width, height: 720 })
        await page.goto(`/${feature}`)
        await page
          .getByRole('button', {
            name:
              mode === 'create'
                ? feature === 'categories'
                  ? 'Добавить категорию'
                  : 'Добавить бренд'
                : feature === 'categories'
                  ? /^Редактировать категорию/
                  : /^Редактировать бренд/,
          })
          .first()
          .click()
        const dialog = page.getByRole('dialog')
        for (const control of [
          dialog.getByRole('textbox', { name: /^Название(?:\s|$)/ }).first(),
          dialog.getByRole('textbox', { name: /^Технический код/ }),
          dialog.getByRole('textbox', { name: 'Описание', exact: true }),
          dialog.getByRole('checkbox', {
            name:
              feature === 'categories' ? 'Категория активна' : 'Бренд активен',
            exact: true,
          }),
        ]) {
          await control.focus()
          await control.scrollIntoViewIfNeeded()
          const clearance = await control.evaluate((element) => {
            const owner =
              element.closest('.admin-control-focus, .admin-choice-focus') ??
              element
            const scroll = owner.closest('.overflow-y-auto')!
            const bounds = owner.getBoundingClientRect()
            const clip = scroll.getBoundingClientRect()
            const halo = parseFloat(
              getComputedStyle(owner).getPropertyValue(
                '--admin-focus-halo-width',
              ),
            )
            return {
              left: bounds.left - halo - clip.left,
              right: clip.right - bounds.right - halo,
            }
          })
          expect(
            clearance.left,
            `${feature} ${mode} focus clipped on left at ${width}px`,
          ).toBeGreaterThanOrEqual(-0.5)
          expect(
            clearance.right,
            `${feature} ${mode} focus clipped on right at ${width}px`,
          ).toBeGreaterThanOrEqual(-0.5)
        }
        await expect(
          dialog.getByRole('button', { name: 'Сохранить', exact: true }),
        ).toBeInViewport()
        expect(
          await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth,
          ),
        ).toBe(true)
        await dialog
          .getByRole('textbox', { name: /^Название(?:\s|$)/ })
          .first()
          .focus()
        await page.screenshot({
          path: `../../.tmp/catalog-dialog-focus/${feature}-${mode}-${width}.png`,
        })
        await page.keyboard.press('Escape')
        await expect(dialog).toBeHidden()
      }
    })
  }
}
