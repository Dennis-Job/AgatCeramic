export default defineNuxtPlugin({
  name: 'preview-devtools',
  enforce: 'pre',
  setup() {
    if (
      import.meta.dev &&
      window.self !== window.top &&
      window.location.pathname.startsWith('/preview/')
    ) {
      // DevTools otherwise reads the cross-origin parent before mounting.
      // Its own-window flag short-circuits that access; standalone tools stay enabled.
      ;(
        window as Window & { __NUXT_DEVTOOLS_DISABLE__?: boolean }
      ).__NUXT_DEVTOOLS_DISABLE__ = true
    }
  },
})
