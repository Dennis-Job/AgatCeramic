<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { ListFilter, Pencil, Plus, Trash2, X } from '@lucide/vue'
import BaseCheckbox from '../components/BaseCheckbox.vue'
import BaseDialog from '../components/BaseDialog.vue'
import BaseInput from '../components/BaseInput.vue'
import BaseSelect from '../components/BaseSelect.vue'
import PaginationControls from '../components/PaginationControls.vue'
import { usePaginatedCollection } from '../composables/usePaginatedCollection'
import { getAllAttributeGroups, type AttributeGroup } from '../services/attributeGroups'
import { deleteAttribute, getAttributes, saveAttribute, type Attribute, type AttributePayload, type AttributeType } from '../services/attributes'
import { useAuthStore } from '../stores/auth'

const types: { value: AttributeType; label: string }[] = [{ value: 'string', label: 'Строка' }, { value: 'text', label: 'Многострочный текст' }, { value: 'integer', label: 'Целое число' }, { value: 'decimal', label: 'Десятичное число' }, { value: 'boolean', label: 'Да / нет' }, { value: 'select', label: 'Список' }, { value: 'multiselect', label: 'Множественный список' }, { value: 'date', label: 'Дата' }]
const auth = useAuthStore()
const attributeList = usePaginatedCollection<Attribute>('Не удалось загрузить характеристики.')
const { items: attributes, pagination, error, loading } = attributeList
const groups = ref<AttributeGroup[]>([])
const dialogError = ref('')
const opened = ref(false)
const isSaving = ref(false)
const editing = ref<Attribute | null>(null)
const deleting = ref<Attribute | null>(null)
const isDeleting = ref(false)
const manuallyEditedSlug = ref(false)
const form = ref<AttributePayload>(emptyForm())
const canManage = computed(() => auth.hasPermission('catalog.manage'))
const hasOptions = computed(() => ['select', 'multiselect'].includes(form.value.type))
const title = computed(() => editing.value ? `Характеристика: ${editing.value.name}` : 'Новая характеристика')
const groupOptions = computed(() => [{ label: 'Без группы', value: '' }, ...groups.value.map(group => ({ label: group.name, value: String(group.id) }))])
function groupName(attribute: Attribute): string | null { return attribute.attribute_group_id === null ? null : groups.value.find(group => group.id === attribute.attribute_group_id)?.name ?? null }
const selectedGroupId = computed({ get: () => form.value.attribute_group_id === null ? '' : String(form.value.attribute_group_id), set: (value: string) => { form.value.attribute_group_id = value === '' ? null : Number(value) } })
const selectedType = computed({ get: () => form.value.type, set: (value: string) => { form.value.type = value as AttributeType; changeType() } })

function emptyForm(): AttributePayload { return { attribute_group_id: null, name: '', slug: '', type: 'string', unit: null, is_filterable: false, is_required: false, is_visible_on_product_page: true, sort_order: 0, options: [] } }
const transliterationMap: Record<string, string> = {
  а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'yo', ж: 'zh', з: 'z', и: 'i', й: 'y', к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r', с: 's', т: 't', у: 'u', ф: 'f', х: 'kh', ц: 'ts', ч: 'ch', ш: 'sh', щ: 'shch', ъ: '', ы: 'y', ь: '', э: 'e', ю: 'yu', я: 'ya',
}
function slug(value: string): string {
  return Array.from(value.toLowerCase(), (character) => transliterationMap[character] ?? character)
    .join('')
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}
function updateName(value: string): void { form.value.name = value; if (!manuallyEditedSlug.value) form.value.slug = slug(value) }
function addOption(): void { form.value.options.push({ value: '', label: '', sort_order: form.value.options.length }) }
function changeType(): void { if (hasOptions.value && form.value.options.length === 0) addOption() }
function open(attribute: Attribute | null = null): void {
  opened.value = false; dialogError.value = ''; editing.value = attribute; manuallyEditedSlug.value = attribute !== null
  form.value = attribute ? { attribute_group_id: attribute.attribute_group_id, name: attribute.name, slug: attribute.slug, type: attribute.type, unit: attribute.unit, is_filterable: attribute.is_filterable, is_required: attribute.is_required, is_visible_on_product_page: attribute.is_visible_on_product_page, sort_order: attribute.sort_order, options: attribute.options.map(option => ({ ...option })) } : emptyForm()
  queueMicrotask(() => { opened.value = true })
}
function closeDialog(): void { opened.value = false }
async function load(page = pagination.value?.current_page ?? 1): Promise<void> { const response = await attributeList.load(page, async (requestedPage) => { const [attributePage, attributeGroups] = await Promise.all([getAttributes({ page: requestedPage }), getAllAttributeGroups()]); return { ...attributePage, attributeGroups } }); if (response) groups.value = response.attributeGroups }
async function save(): Promise<void> { isSaving.value = true; dialogError.value = ''; try { await saveAttribute(editing.value?.id ?? null, form.value); closeDialog(); await load() } catch (reason) { dialogError.value = reason instanceof Error ? reason.message : 'Не удалось сохранить характеристику.' } finally { isSaving.value = false } }
async function remove(): Promise<void> { if (!deleting.value) return; isDeleting.value = true; try { await deleteAttribute(deleting.value.id); deleting.value = null; const response = await attributeList.reloadAfterDeletion(async (page) => { const [attributePage, attributeGroups] = await Promise.all([getAttributes({ page }), getAllAttributeGroups()]); return { ...attributePage, attributeGroups } }); if (response) groups.value = response.attributeGroups } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось удалить характеристику.' } finally { isDeleting.value = false } }
onMounted(load)
</script>

<template>
  <section class="mx-auto admin-page" :aria-busy="loading">
    <div class="mb-7 flex flex-col items-stretch gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div><p class="text-sm font-medium text-gray-500">Каталог</p><h1 class="mt-1 text-2xl font-bold text-gray-900">Характеристики</h1></div>
      <button v-if="canManage" class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-500 px-4 py-2.5 text-sm font-semibold text-white sm:justify-start" @click="open()"><Plus :size="18" />Добавить</button>
    </div>
    <p v-if="error" class="mb-4 rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-500" role="alert">{{ error }}</p>
    <p v-if="loading" class="sr-only" role="status">Загрузка характеристик…</p>
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card">
      <div v-if="attributes.length" class="divide-y divide-gray-100"><article v-for="attribute in attributes" :key="attribute.id" class="flex flex-wrap items-start gap-3 p-4 sm:flex-nowrap sm:items-center sm:gap-4"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-primary-50 text-primary-600"><ListFilter :size="20" /></span><div class="min-w-0 flex-[1_1_calc(100%-52px)] sm:flex-1"><div class="flex flex-wrap items-center gap-x-1.5 gap-y-1"><h2 class="font-semibold text-gray-800">{{ attribute.name }}<span v-if="attribute.unit" class="font-medium text-gray-500"> ({{ attribute.unit }})</span><span v-if="attribute.is_required" class="ml-1 text-error-500" title="Обязательная характеристика">*</span></h2><span v-if="groupName(attribute)" class="rounded-full bg-blue-light-50 px-2 py-0.5 text-xs font-medium text-blue-light-500">Группа: {{ groupName(attribute) }}</span><span v-if="attribute.is_filterable" class="rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-500">В фильтрах</span><span v-if="attribute.is_visible_on_product_page" class="rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-600">На странице товара</span></div><p class="mt-0.5 text-sm text-gray-500">{{ types.find(type => type.value === attribute.type)?.label }} · /{{ attribute.slug }} · значений: {{ attribute.options.length }}</p></div><div v-if="canManage" class="ml-[52px] flex gap-1 sm:ml-0"><button class="rounded-lg p-2 text-gray-500 hover:bg-primary-50 hover:text-primary-600" :aria-label="`Редактировать характеристику ${attribute.name}`" @click="open(attribute)"><Pencil :size="17" /></button><button class="rounded-lg p-2 text-gray-500 hover:bg-error-50 hover:text-error-500" :aria-label="`Удалить характеристику ${attribute.name}`" @click="deleting = attribute"><Trash2 :size="17" /></button></div></article></div>
      <div v-else-if="!loading" class="px-5 py-14 text-center text-sm text-gray-500">Характеристик пока нет.</div>
    </div>
    <PaginationControls v-if="pagination" :meta="pagination" :loading="loading" @change="load" />
    <BaseDialog :open="opened" labelledby="attribute-dialog-title" describedby="attribute-dialog-description" :close-disabled="isSaving" panel-class="w-full max-w-xl" @close="closeDialog">
      <form class="max-h-[90vh] w-full overflow-y-auto rounded-xl bg-white p-6 shadow-xl" @submit.prevent="save">
        <div class="flex items-start justify-between"><div><h2 id="attribute-dialog-title" class="text-lg font-bold text-gray-900">{{ title }}</h2><p id="attribute-dialog-description" class="mt-1 text-sm text-gray-500">Укажите тип значения и варианты выбора, если они нужны.</p></div><button type="button" class="rounded-lg p-1 text-gray-500 hover:bg-gray-50 disabled:opacity-60" aria-label="Закрыть окно характеристики" :disabled="isSaving" @click="closeDialog"><X :size="20" /></button></div>
        <p v-if="dialogError" class="mt-4 rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-500" role="alert">{{ dialogError }}</p>
        <div class="mt-6 grid gap-4">
          <label class="text-sm font-medium text-gray-700">Название<BaseInput :model-value="form.name" class="mt-1.5 w-full font-normal" data-autofocus required @update:model-value="updateName" /></label>
          <label class="text-sm font-medium text-gray-700">Технический код (slug)<BaseInput :model-value="form.slug" class="mt-1.5 w-full font-normal" required pattern="[a-z0-9]+(-[a-z0-9]+)*" @update:model-value="value => { form.slug = value; manuallyEditedSlug = true }" /></label>
          <div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-medium text-gray-700">Группа<BaseSelect v-model="selectedGroupId" class="mt-1.5 w-full font-normal" accessible-name="Группа характеристики" :options="groupOptions" /></label><label class="text-sm font-medium text-gray-700">Тип<BaseSelect v-model="selectedType" class="mt-1.5 w-full font-normal" accessible-name="Тип характеристики" :options="types" /></label></div>
          <label class="text-sm font-medium text-gray-700">Единица измерения<BaseInput :model-value="form.unit ?? ''" class="mt-1.5 w-full font-normal" placeholder="мм, м², кг" @update:model-value="value => form.unit = value || null" /></label>
          <div class="grid gap-2 sm:grid-cols-2"><BaseCheckbox mode="boolean" :checked="form.is_filterable" @update:checked="form.is_filterable = $event">Использовать в фильтре</BaseCheckbox><BaseCheckbox mode="boolean" :checked="form.is_required" @update:checked="form.is_required = $event">Обязательная характеристика</BaseCheckbox><BaseCheckbox mode="boolean" :checked="form.is_visible_on_product_page" @update:checked="form.is_visible_on_product_page = $event">Показывать на странице товара</BaseCheckbox></div>
          <section v-if="hasOptions" class="rounded-xl border border-gray-200 bg-gray-25 p-4"><div class="flex items-center justify-between"><div><h3 class="font-semibold text-gray-800">Варианты</h3><p class="mt-0.5 text-xs text-gray-500">Код должен быть уникален в пределах характеристики.</p></div><button type="button" class="rounded-lg px-3 py-2 text-sm font-semibold text-primary-600 hover:bg-primary-50" @click="addOption">Добавить</button></div><div v-for="(option, index) in form.options" :key="index" class="mt-3 grid gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]"><BaseInput v-model="option.label" required placeholder="Название" :aria-label="`Название варианта ${index + 1}`" /><BaseInput v-model="option.value" required placeholder="Код" :aria-label="`Код варианта ${index + 1}`" /><button type="button" class="justify-self-end rounded-lg p-2 text-error-500 hover:bg-error-50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="form.options.length === 1" :aria-label="`Удалить вариант ${index + 1}`" @click="form.options.splice(index, 1)"><Trash2 :size="17" /></button></div></section>
          <label class="text-sm font-medium text-gray-700">Порядок сортировки<input v-model.number="form.sort_order" class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm font-normal outline-none transition focus:border-primary-500 focus:ring-4 focus:ring-primary-50" min="0" required type="number" /></label>
        </div>
        <div class="mt-6 flex justify-end gap-3"><button type="button" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 disabled:opacity-60" :disabled="isSaving" @click="closeDialog">Отмена</button><button class="rounded-lg bg-primary-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-600 disabled:opacity-60" :disabled="isSaving">{{ isSaving ? 'Сохранение…' : 'Сохранить' }}</button></div>
      </form>
    </BaseDialog>
    <BaseDialog :open="Boolean(deleting)" labelledby="delete-attribute-title" describedby="delete-attribute-description" :close-disabled="isDeleting" overlay-class="z-[60] grid place-items-center p-4" panel-class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl" @close="deleting = null"><template v-if="deleting"><h2 id="delete-attribute-title" class="text-lg font-bold text-gray-900">Удалить характеристику?</h2><p id="delete-attribute-description" class="mt-3 text-sm leading-6 text-gray-500">Характеристика «{{ deleting.name }}» и её варианты будут удалены. Это действие нельзя отменить.</p><div class="mt-6 flex justify-end gap-3"><button type="button" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-gray-600 disabled:cursor-not-allowed disabled:opacity-60" :disabled="isDeleting" @click="deleting = null">Отмена</button><button type="button" class="rounded-lg bg-error-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-error-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="isDeleting" @click="remove">{{ isDeleting ? 'Удаление…' : 'Удалить' }}</button></div></template></BaseDialog>
  </section>
</template>
