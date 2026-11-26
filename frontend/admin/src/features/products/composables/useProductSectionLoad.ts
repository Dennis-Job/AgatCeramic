import { reactive } from 'vue'
import type { Product } from '../types/product.types'

export function useProductSectionLoad<T>(
  load: (product: Product) => Promise<T>,
  apply: (data: T) => void,
  failureMessage: string,
) {
  const state = reactive({ ready: false, error: '' })
  let pending: Promise<void> | undefined
  let session = 0

  function reset() {
    session++
    pending = undefined
    state.ready = false
    state.error = ''
  }

  function ensure(product: Product, retry: boolean) {
    if (state.ready || (state.error && !retry)) return Promise.resolve()
    if (pending) return pending
    const request = session
    state.error = ''
    pending = Promise.resolve()
      .then(() => load(product))
      .then((data) => {
        if (request !== session) return
        apply(data)
        state.ready = true
      })
      .catch((reason: unknown) => {
        if (request === session)
          state.error =
            reason instanceof Error ? reason.message : failureMessage
      })
      .finally(() => {
        if (request === session) pending = undefined
      })
    return pending
  }

  return { state, ensure, reset }
}
