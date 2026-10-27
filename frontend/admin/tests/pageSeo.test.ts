import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import HomePageSeoEditor from '../src/features/homepage/components/HomePageSeoEditor.vue'
import type { HomePageContent } from '../src/features/homepage/types/homepage.types'

describe('page SEO nullable image reference', () => {
  it.each([null, 12])(
    'renders a null URL as an empty input and retains the media reference %s',
    async (mediaId) => {
      const seo: HomePageContent['seo'] = {
        title: 'О нас — AgatCeramic',
        description: '',
        og_title: 'О нас — AgatCeramic',
        og_description: '',
        og_image_url: null,
        og_image_media_id: mediaId,
      }
      const wrapper = mount(HomePageSeoEditor, {
        props: { modelValue: seo },
        global: {
          stubs: {
            MediaImageEditor: {
              template: '<div><slot name="details" /></div>',
            },
          },
        },
      })
      const input = wrapper.findAll('input').at(-1)!
      expect(input.element.value).toBe('')
      expect(seo.og_image_url).toBeNull()
      await input.setValue('/images/about.webp')
      expect(seo.og_image_url).toBe('/images/about.webp')
      expect(seo.og_image_media_id).toBe(mediaId)
      await input.setValue('')
      expect(seo.og_image_url).toBe('')
      await wrapper.setProps({ modelValue: { ...seo, og_image_url: null } })
      expect(input.element.value).toBe('')
      wrapper.unmount()
    },
  )
})
