import { computed, ref, toValue, watch, type MaybeRefOrGetter } from 'vue'
import { savePage, publishPage } from '../services/pages'
import type { ContentPage, PagePayload } from '../types/page.types'
import type { BlockType } from '../types/block.types'
import { createBlock, moveBlock } from '../validation/blocks'

export function usePageBlocks(
  source: MaybeRefOrGetter<ContentPage>,
  onSaved: (page: ContentPage) => void,
) {
  const form = ref<PagePayload>({ title: '', slug: '', body: '', blocks: [] })
  const baseline = ref('')
  const busy = ref(false)
  const error = ref('')
  const success = ref('')
  const resourceDirty = ref(false)
  const resourceBusy = ref(false)
  let currentId: number | null = null
  watch(
    () => toValue(source),
    () => {
      const page = toValue(source)
      if (
        currentId === page.id &&
        JSON.stringify(form.value) !== baseline.value
      )
        return
      const changingPage = currentId !== page.id
      currentId = page.id
      form.value = JSON.parse(
        JSON.stringify({
          title: page.title,
          slug: page.slug,
          body: page.body,
          blocks: page.blocks ?? [],
          ...(page.seo ? { seo: page.seo } : {}),
        }),
      ) as PagePayload
      baseline.value = JSON.stringify(form.value)
      if (changingPage) {
        error.value = ''
        success.value = ''
        resourceDirty.value = false
        resourceBusy.value = false
      }
    },
    { immediate: true },
  )
  const dirty = computed(() => JSON.stringify(form.value) !== baseline.value)
  const pending = computed(
    () => dirty.value || resourceDirty.value || busy.value,
  )

  function add(type: BlockType): string | null {
    const blocks = form.value.blocks!
    if (
      busy.value ||
      blocks.length >= 40 ||
      (type !== 'text' && blocks.some((block) => block.type === type))
    )
      return null
    const block = createBlock(type)
    blocks.push(block)
    return block.id
  }

  async function save(): Promise<void> {
    if (busy.value || resourceDirty.value) return
    busy.value = true
    error.value = ''
    success.value = ''
    const submitted = JSON.parse(JSON.stringify(form.value)) as PagePayload
    try {
      const page = await savePage(toValue(source).id, submitted)
      baseline.value = JSON.stringify(submitted)
      onSaved(page)
      success.value =
        'Черновик блоков сохранён. Для обновления сайта опубликуйте страницу.'
    } catch (reason) {
      error.value =
        reason instanceof Error ? reason.message : 'Не удалось сохранить блоки.'
    } finally {
      busy.value = false
    }
  }

  async function publish(): Promise<void> {
    if (pending.value) return
    busy.value = true
    error.value = ''
    success.value = ''
    try {
      onSaved(await publishPage(toValue(source).id))
      success.value = 'Сохранённый черновик опубликован.'
    } catch (reason) {
      error.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось опубликовать страницу.'
    } finally {
      busy.value = false
    }
  }
  return {
    form,
    dirty,
    pending,
    resourceDirty,
    resourceBusy,
    busy,
    error,
    success,
    add,
    save,
    publish,
    move: (id: string, offset: -1 | 1) =>
      moveBlock(form.value.blocks!, id, offset),
  }
}
