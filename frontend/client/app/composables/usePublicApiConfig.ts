export function usePublicApiConfig() {
  const config = useRuntimeConfig()
  return {
    requestBase:
      import.meta.server && config.apiBaseInternal
        ? config.apiBaseInternal
        : config.public.apiBase,
    publicBase: config.public.apiBase,
  }
}
