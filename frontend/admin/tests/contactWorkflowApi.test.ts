import { expect, test, vi } from 'vitest'
import {
  getContactStatuses,
  getContactStatusCounts,
} from '../src/features/contacts/services/contacts'

const apiFetch = vi.hoisted(() => vi.fn())
vi.mock('../src/services/auth', () => ({
  apiFetch,
  requestCsrfCookie: vi.fn(),
}))

test('the contacts workspace loads allowed transitions through the additive workflow endpoint', async () => {
  const statuses = [
    {
      code: 'new',
      name: 'Новое',
      is_terminal: false,
      allowed_transitions: ['processing', 'rejected'],
    },
  ]
  apiFetch.mockResolvedValueOnce(
    new Response(JSON.stringify({ data: statuses })),
  )
  await expect(getContactStatuses()).resolves.toEqual(statuses)
  expect(apiFetch).toHaveBeenCalledWith('/admin/contact-workflow')
})

test('loads global status counts through a separate unfiltered endpoint', async () => {
  const counts = { new: 9, processing: 4, awaiting_client: 2 }
  apiFetch.mockResolvedValueOnce(new Response(JSON.stringify({ data: counts })))
  await expect(getContactStatusCounts()).resolves.toEqual(counts)
  expect(apiFetch).toHaveBeenLastCalledWith('/admin/contact-request-counts')
})
