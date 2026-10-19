import { mount } from '@vue/test-utils'
import { describe, expect, test } from 'vitest'
import UiInput from '../src/components/ui/UiInput.vue'

function moneyField(value = '17926.00', attrs = {}) {
  const wrapper = mount(UiInput, {
    props: { money: true, modelValue: value },
    attrs,
  })
  return { wrapper, field: wrapper.get('input') }
}

describe('editable money', () => {
  test('shows grouped initial amounts without changing the API value', () => {
    const { wrapper, field } = moneyField()
    expect(field.element.value).toBe('17\u00a0926,00')
    expect(field.attributes('inputmode')).toBe('decimal')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  test.each(['1 234 567,89 ₽', '1\u202f234\u00a0567.89'])(
    'accepts pasted %s exactly',
    async (value) => {
      const { wrapper, field } = moneyField()
      await field.setValue(value)
      expect(field.element.value).toBe('1\u00a0234\u00a0567,89')
      expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([
        '1234567.89',
      ])
    },
  )

  test('pads cents on blur and Enter without floating point rounding', async () => {
    const { wrapper, field } = moneyField()
    await field.setValue('1000000,1')
    await field.trigger('blur')
    expect(field.element.value).toBe('1\u00a0000\u00a0000,10')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['1000000.10'])
    await field.setValue('0,29')
    await field.trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['0.29'])
  })

  test.each(['-1', '1,234', '12abc', '1e3'])(
    'rejects invalid amount %s instead of corrupting data',
    async (value) => {
      const { wrapper, field } = moneyField()
      await field.setValue(value)
      expect(field.element.value).toBe('17\u00a0926,00')
      expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    },
  )

  test('keeps required, range validation, and clearing an optional amount', async () => {
    const { wrapper, field } = moneyField('0.00', {
      min: '0.01',
      max: '1000',
      required: true,
    })
    expect(field.element.validity.customError).toBe(true)
    await field.setValue('1000,01')
    expect(field.element.validity.customError).toBe(true)
    await field.setValue('999,99')
    expect(field.element.checkValidity()).toBe(true)
    await wrapper.get('button').trigger('click')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([''])
    await wrapper.setProps({ modelValue: '' })
    expect(field.element.validity.customError).toBe(false)
    expect(field.element.validity.valueMissing).toBe(true)
    await wrapper.setProps({ required: false })
    expect(field.element.checkValidity()).toBe(true)
  })

  test.each([{ disabled: true }, { readonly: '' }])(
    'preserves protected amount for %j',
    async (attrs) => {
      const { wrapper, field } = moneyField('17926.00', attrs)
      await field.setValue('999')
      await field.trigger('blur')
      await wrapper.get('button').trigger('click')
      expect(wrapper.emitted('update:modelValue')).toBeUndefined()
      expect(wrapper.get('button').attributes()).toHaveProperty('disabled')
    },
  )

  test('keeps quantity inputs numeric and ungrouped', async () => {
    const wrapper = mount(UiInput, {
      props: { type: 'number', modelValue: '17926' },
    })
    const field = wrapper.get('input')
    expect(field.attributes('type')).toBe('number')
    expect(field.element.value).toBe('17926')
    await field.setValue('20000')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['20000'])
  })
})
