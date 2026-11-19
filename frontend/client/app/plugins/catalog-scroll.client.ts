/** Automatic refinements keep the control in view; other routing keeps Nuxt's rules. */
export default defineNuxtPlugin((nuxtApp) => {
  const router = useRouter()
  // Nuxt finalizes scrollBehavior during app:created; wrap it after hydration.
  nuxtApp.hook('app:mounted', () => {
    const scroll = router.options.scrollBehavior
    router.options.scrollBehavior = (to, from, savedPosition) => {
      if (
        !savedPosition &&
        to.path === from.path &&
        window.history.state?.catalogAutomaticFilter
      )
        return false
      return scroll?.(to, from, savedPosition)
    }
  })
})
