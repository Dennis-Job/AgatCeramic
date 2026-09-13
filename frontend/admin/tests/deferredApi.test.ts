import { describe, expect, it } from 'vitest'
import { createDeferredApiRequests } from '../e2e/deferredApi'

describe('deferred API requests', () => {
  it('holds an armed request until its explicit release and ignores unarmed paths', async () => {
    const deferredRequests = createDeferredApiRequests()
    const request = deferredRequests.defer('/admin/products')
    let completed = false

    const pending = deferredRequests.wait('/admin/products').then(() => { completed = true })
    await request.requested
    expect(completed).toBe(false)

    request.release()
    await pending
    expect(completed).toBe(true)
    await expect(deferredRequests.wait('/admin/brands')).resolves.toBeUndefined()
  })
})
