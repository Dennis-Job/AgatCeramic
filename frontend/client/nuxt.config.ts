// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2026-08-12',
  ssr: true,
  devtools: { enabled: true },
  runtimeConfig: {
    public: {
      siteUrl: '',
    },
  },
  css: [
    '@fontsource/inter/cyrillic-400.css',
    '@fontsource/inter/cyrillic-500.css',
    '@fontsource/inter/cyrillic-600.css',
    '@fontsource/inter/latin-400.css',
    '@fontsource/inter/latin-500.css',
    '@fontsource/inter/latin-600.css',
    '~/assets/css/tokens.css',
    '~/assets/css/base.css',
  ],
  app: {
    head: {
      htmlAttrs: { lang: 'ru' },
      meta: [{ name: 'theme-color', content: '#F2F1EE' }],
    },
  },
})
