import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page } from '@playwright/test'
import { approvedAdminColors } from './contracts/approvedPalette'
import { partitionApprovedContrast } from './contracts/approvedContrast'

export async function assertApprovedAdminColors(page: Page): Promise<void> {
  const { actual, expected } = await page.evaluate((approved) => {
    const style = getComputedStyle(document.documentElement)
    const probe = document.createElement('span')
    const normalize = (value: string) => {
      if (!value.trim()) return ''
      probe.style.color = ''
      probe.style.color = value
      return probe.style.color
    }
    // CSSOM canonicalizes #fff, #ffffff and rgb() without mutating the page.
    return {
      actual: Object.fromEntries(
        Object.keys(approved).map((name) => [
          name,
          normalize(style.getPropertyValue(name)),
        ]),
      ),
      expected: Object.fromEntries(
        Object.entries(approved).map(([name, value]) => [
          name,
          normalize(value),
        ]),
      ),
    }
  }, approvedAdminColors)
  expect(
    actual,
    'Owner-approved macOS color tokens must match on every OS',
  ).toEqual(expected)
}

/** Full axe scan with narrowly recorded owner-approved color exceptions. */
export default class ApprovedAdminAxeBuilder extends AxeBuilder {
  private readonly adminPage: Page

  constructor(options: ConstructorParameters<typeof AxeBuilder>[0]) {
    super(options)
    this.adminPage = options.page
  }

  override async analyze() {
    await assertApprovedAdminColors(this.adminPage)
    const raw = await super.analyze()
    const result = partitionApprovedContrast(raw.violations)
    if (result.approved.length) {
      await test.info().attach('owner-approved-contrast-findings', {
        body: JSON.stringify(
          {
            url: this.adminPage.url(),
            approved: result.approved,
            rawViolations: raw.violations,
          },
          null,
          2,
        ),
        contentType: 'application/json',
      })
      test.info().annotations.push({
        type: 'owner-approved-contrast',
        description:
          'Approved palette preserved; attached WCAG findings remain known limitations.',
      })
    }
    return {
      ...raw,
      violations: result.violations,
      approvedContrastViolations: result.approved,
    }
  }
}
