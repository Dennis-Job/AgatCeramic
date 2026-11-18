import type { Locator } from '@playwright/test'

export async function selectChoice(control: Locator, label: string) {
  await control.click()
  await control
    .locator('..')
    .getByRole('option', { name: label, exact: true })
    .click()
}
