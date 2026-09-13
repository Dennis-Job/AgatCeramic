export type DeferredApiRequest = {
  requested: Promise<void>
  release: () => void
}

export type DeferredApiRequests = {
  defer: (path: string) => DeferredApiRequest
  wait: (path: string) => Promise<void>
}

type PendingRequest = {
  markRequested: () => void
  released: Promise<void>
}

export function createDeferredApiRequests(): DeferredApiRequests {
  const queues = new Map<string, PendingRequest[]>()

  return {
    defer(path) {
      let markRequested = () => undefined
      let release = () => undefined
      const requested = new Promise<void>((resolve) => { markRequested = resolve })
      const released = new Promise<void>((resolve) => { release = resolve })
      const queue = queues.get(path) ?? []
      queue.push({ markRequested, released })
      queues.set(path, queue)

      return { requested, release }
    },
    async wait(path) {
      const queue = queues.get(path)
      const request = queue?.shift()
      if (!request) return
      if (!queue?.length) queues.delete(path)
      request.markRequested()
      await request.released
    },
  }
}
