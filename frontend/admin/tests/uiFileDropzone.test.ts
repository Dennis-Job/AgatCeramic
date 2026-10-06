import { mount } from '@vue/test-utils'
import { describe, expect, test } from 'vitest'
import UiFileDropzone from '../src/components/ui/UiFileDropzone.vue'

const props = {
  label: 'Выберите Excel',
  dropLabel: 'Отпустите Excel',
  description: 'XLSX, не больше 10 МБ.',
  formatLabel: 'XLSX',
}

describe('UiFileDropzone', () => {
  test('requests the picker on click and accepts file drops across nested drag targets', async () => {
    const wrapper = mount(UiFileDropzone, { props })
    const file = new File(['workbook'], 'products.xlsx')
    const dataTransfer = { types: ['Files'], files: [file], dropEffect: '' }
    const button = wrapper.get('button')
    await button.trigger('click')
    expect(wrapper.emitted('choose')).toHaveLength(1)
    await button.trigger('dragenter', { dataTransfer })
    await button.trigger('dragenter', { dataTransfer })
    await button.trigger('dragleave')
    expect(button.text()).toContain('Отпустите Excel')
    await button.trigger('dragover', { dataTransfer })
    expect(dataTransfer.dropEffect).toBe('copy')
    await button.trigger('drop', { dataTransfer })
    expect(wrapper.emitted('files')).toEqual([[[file]]])
    expect(button.text()).toContain('Выберите Excel')
    wrapper.unmount()
  })

  test('rejects drops and clears the active drag when it becomes disabled', async () => {
    const wrapper = mount(UiFileDropzone, { props })
    const dataTransfer = {
      types: ['Files'],
      files: [new File(['workbook'], 'products.xlsx')],
      dropEffect: '',
    }
    const button = wrapper.get('button')
    await button.trigger('dragenter', { dataTransfer })
    await wrapper.setProps({ disabled: true })
    expect(button.text()).toContain('Выберите Excel')
    for (const type of ['dragover', 'drop']) {
      const event = new Event(type, { bubbles: true, cancelable: true })
      Object.defineProperty(event, 'dataTransfer', { value: dataTransfer })
      button.element.dispatchEvent(event)
    }
    await button.trigger('click')
    expect(wrapper.emitted('files')).toBeUndefined()
    expect(wrapper.emitted('choose')).toBeUndefined()
    expect(dataTransfer.dropEffect).toBe('none')
    wrapper.unmount()
  })

  test('ignores text drags and empty file drops', async () => {
    const wrapper = mount(UiFileDropzone, { props })
    await wrapper.get('button').trigger('dragenter', {
      dataTransfer: { types: ['text/plain'], files: [] },
    })
    expect(wrapper.text()).not.toContain('Отпустите Excel')
    await wrapper.get('button').trigger('drop', {
      dataTransfer: { types: ['Files'], files: [] },
    })
    expect(wrapper.emitted('files')).toBeUndefined()
    wrapper.unmount()
  })
})
