import { expect as baseExpect, test as base, type ConsoleMessage, type Page } from '@playwright/test'

type BrowserIssue = {
  kind: 'console.error' | 'pageerror' | 'vue-component-warning'
  text: string
  url: string
}

type ExpectedApiError = {
  path: string | RegExp
  status: number
}

export type BrowserIssueGuard = {
  allowApiError: (status: number, path: string | RegExp) => void
}

function observeBrowserIssues(page: Page, issues: BrowserIssue[]): void {
  page.on('console', (message: ConsoleMessage) => {
    const locationUrl = message.location().url
    const isUnresolvedVueComponent = message.type() === 'warning'
      && message.text().includes('Failed to resolve component')

    if (message.type() !== 'error' && !isUnresolvedVueComponent) return

    issues.push({
      kind: isUnresolvedVueComponent ? 'vue-component-warning' : 'console.error',
      text: message.text(),
      url: locationUrl || page.url(),
    })
  })

  page.on('pageerror', (error: Error) => {
    issues.push({ kind: 'pageerror', text: error.stack ?? error.message, url: page.url() })
  })
}

function isExpectedApiError(issue: BrowserIssue, expected: ExpectedApiError[]): boolean {
  if (issue.kind !== 'console.error') return false
  const status = Number(issue.text.match(/status of (\d{3})/)?.[1])
  if (!status) return false

  let path: string
  try {
    path = new URL(issue.url).pathname
  } catch {
    return false
  }

  return expected.some(item => item.status === status && (typeof item.path === 'string'
    ? item.path === path
    : item.path.test(path)))
}

export const test = base.extend<{ browserIssueGuard: BrowserIssueGuard }>({
  browserIssueGuard: [async ({ context }, use) => {
    const issues: BrowserIssue[] = []
    const expectedApiErrors: ExpectedApiError[] = []
    const observedPages = new WeakSet<Page>()
    const observe = (page: Page) => {
      if (observedPages.has(page)) return
      observedPages.add(page)
      observeBrowserIssues(page, issues)
    }

    context.pages().forEach(observe)
    context.on('page', observe)

    await use({
      allowApiError(status, path) {
        expectedApiErrors.push({ status, path })
      },
    })

    const unexpectedIssues = issues.filter(issue => !isExpectedApiError(issue, expectedApiErrors))
    baseExpect(unexpectedIssues, 'Browser console/runtime errors must be resolved or explicitly expected').toEqual([])
  }, { auto: true }],
})

export const expect = baseExpect
export type { Page } from '@playwright/test'
