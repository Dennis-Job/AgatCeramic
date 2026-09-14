import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { mount } from '@vue/test-utils'
import { describe, expect, test } from 'vitest'
import AuthCard from '../src/components/shared/AuthCard.vue'

describe('AuthCard', () => {
  test('owns the shared auth branding, heading, description, card and submit event', async () => {
    const wrapper = mount(AuthCard, {
      props: { title: 'Вход', description: 'Описание формы.' },
      slots: { default: '<button type="submit">Продолжить</button>' },
    })

    expect(wrapper.get('form').classes()).toContain('max-w-md')
    expect(wrapper.get('h1').text()).toBe('Вход')
    expect(wrapper.text()).toContain('AgatCeramic')
    expect(wrapper.text()).toContain('Описание формы.')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.emitted('submit')).toEqual([[]])
  })

  test('can use an h2 when embedded in the UI-kit without changing the auth default', () => {
    const embedded = mount(AuthCard, {
      props: {
        title: 'Пример формы',
        description: 'Встроенная демонстрация.',
        headingTag: 'h2',
      },
    })
    const standalone = mount(AuthCard, {
      props: { title: 'Вход', description: 'Отдельная страница.' },
    })

    expect(embedded.get('h2').text()).toBe('Пример формы')
    expect(embedded.find('h1').exists()).toBe(false)
    expect(standalone.get('h1').text()).toBe('Вход')
  })

  test('is the only owner of the auth card shell used by all guest views', () => {
    const authCard = readFileSync(
      resolve(process.cwd(), 'src/components/shared/AuthCard.vue'),
      'utf8',
    )
    expect(authCard).toContain('shadow-dialog')

    for (const viewName of [
      'LoginView.vue',
      'ForgotPasswordView.vue',
      'ResetPasswordView.vue',
    ]) {
      const source = readFileSync(
        resolve(process.cwd(), `src/views/${viewName}`),
        'utf8',
      )
      expect(source, viewName).toContain('components/shared/AuthCard.vue')
      expect(source, viewName).not.toContain('shadow-dialog')
      expect(source, viewName).not.toContain('AgatCeramic</p>')
    }
  })
})
