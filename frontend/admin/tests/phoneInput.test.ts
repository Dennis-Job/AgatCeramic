import { mount } from '@vue/test-utils'
import { describe, expect, test } from 'vitest'
import UiInput from '../src/components/ui/UiInput.vue'

function phoneField(value = '', attrs = {}) {
  const wrapper = mount(UiInput, {
    props: { type: 'tel', modelValue: value },
    attrs,
  })
  return { wrapper, field: wrapper.get('input') }
}

describe('telephone input', () => {
  test('shows the fixed country code and formats initial API values', () => {
    const empty = phoneField()
    expect(empty.field.element.value).toBe('+7')
    expect(empty.wrapper.emitted('update:modelValue')).toBeUndefined()
    expect(phoneField('+74951204567').field.element.value).toBe(
      '+7 (495) 120-45-67',
    )
  })

  test.each(['4951204567', '+7 (495) 120-45-67', '8 (495) 120-45-67'])(
    'accepts %s and emits the canonical API value',
    async (value) => {
      const { wrapper, field } = phoneField()
      await field.setValue(value)
      expect(field.element.value).toBe('+7 (495) 120-45-67')
      expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([
        '+74951204567',
      ])
    },
  )

  test('blocks incomplete numbers and lets optional fields be cleared', async () => {
    const { wrapper, field } = phoneField()
    expect(field.element.checkValidity()).toBe(true)
    await field.setValue('495')
    expect(field.element.value).toBe('+7 (495)')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['+7495'])
    expect(field.element.validity.customError).toBe(true)
    await wrapper.setProps({ modelValue: '+7495' })
    await wrapper.get('button').trigger('click')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([''])
    expect(field.element.value).toBe('+7')
    expect(field.element.checkValidity()).toBe(true)
    const required = phoneField('', { required: true })
    expect(required.field.element.checkValidity()).toBe(false)
  })

  test('deletes digits across separators and replaces a selected range', async () => {
    const { wrapper, field } = phoneField('+74951204567')
    field.element.setSelectionRange(13, 13)
    await field.trigger('beforeinput', { inputType: 'deleteContentBackward' })
    expect(field.element.value).toBe('+7 (495) 124-56-7')
    expect(field.element.selectionStart).toBe(11)
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([
      '+7495124567',
    ])
    field.element.setSelectionRange(4, 7)
    await field.trigger('beforeinput', { inputType: 'insertText', data: '999' })
    expect(field.element.value).toBe('+7 (999) 124-56-7')
    expect(field.element.selectionStart).toBe(7)
  })

  test('protects the prefix, handles select-all paste, and clears select-all deletion', async () => {
    const { wrapper, field } = phoneField('+74951204567')
    field.element.setSelectionRange(0, 0)
    await field.trigger('beforeinput', { inputType: 'deleteContentBackward' })
    expect(field.element.value).toBe('+7 (495) 120-45-67')
    field.element.select()
    await field.trigger('paste', {
      clipboardData: { getData: () => '8 (999) 111-22-33' },
    })
    expect(field.element.value).toBe('+7 (999) 111-22-33')
    field.element.select()
    await field.trigger('beforeinput', { inputType: 'deleteContentBackward' })
    expect(field.element.value).toBe('+7')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([''])
  })

  test('does not report a change for prefix deletion or rejected paste into an empty field', async () => {
    const { wrapper, field } = phoneField()
    field.element.setSelectionRange(0, 2)
    await field.trigger('beforeinput', { inputType: 'deleteContentBackward' })
    await field.trigger('paste', {
      clipboardData: { getData: () => '+49 1234567890' },
    })
    expect(field.element.value).toBe('+7')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  test.each(['+49 151 23456789', '+74951204567, +79991112233', '495120456799'])(
    'rejects invalid pasted or filled data without truncating it: %s',
    async (value) => {
      const { wrapper, field } = phoneField('+74951204567')
      await field.setValue(value)
      expect(field.element.value).toBe('+7 (495) 120-45-67')
      expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    },
  )

  test.each([{ disabled: true }, { readonly: '' }])(
    'does not edit protected values: %j',
    async (attrs) => {
      const { wrapper, field } = phoneField('+74951204567', attrs)
      field.element.select()
      await field.trigger('beforeinput', {
        inputType: 'insertText',
        data: '999',
      })
      await field.setValue('999')
      await wrapper.get('button').trigger('click')
      expect(wrapper.emitted('update:modelValue')).toBeUndefined()
      expect(wrapper.get('button').attributes()).toHaveProperty('disabled')
    },
  )
})
