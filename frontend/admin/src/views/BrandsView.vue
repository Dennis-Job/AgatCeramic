<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { BadgeCheck, Pencil, Plus, Trash2, X } from '@lucide/vue'
import BaseCheckbox from '../components/BaseCheckbox.vue'
import BaseInput from '../components/BaseInput.vue'
import BaseSelect from '../components/BaseSelect.vue'
import PaginationControls from '../components/PaginationControls.vue'
import { usePaginatedCollection } from '../composables/usePaginatedCollection'
import { COUNTRY_OPTIONS, countryName } from '../constants/countries'
import { deleteBrand, getBrands, saveBrand, type Brand, type BrandPayload } from '../services/brands'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const brandList = usePaginatedCollection<Brand>('Не удалось загрузить бренды.')
const { items: brands, pagination, error, loading } = brandList
const opened = ref(false)
const editing = ref<Brand | null>(null)
const deleting = ref<Brand | null>(null)
const isDeleting = ref(false)
const manuallyEditedSlug = ref(false)
const form = ref<BrandPayload>({
  name: '', slug: '', description: '', country_code: null, is_active: true,
})

const canManage = computed(() => auth.hasPermission('catalog.manage'))
const selectedCountryCode = computed({
  get: () => form.value.country_code ?? '',
  set: (value: string) => { form.value.country_code = value || null },
})
const title = computed(() => editing.value ? `Бренд: ${editing.value.name}` : 'Новый бренд')
const transliterationMap: Record<string, string> = {
  а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'yo', ж: 'zh', з: 'z', и: 'i', й: 'y', к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r', с: 's', т: 't', у: 'u', ф: 'f', х: 'kh', ц: 'ts', ч: 'ch', ш: 'sh', щ: 'shch', ъ: '', ы: 'y', ь: '', э: 'e', ю: 'yu', я: 'ya',
}

function toSlug(value: string): string {
  return Array.from(value.toLowerCase(), (character) => transliterationMap[character] ?? character)
    .join('').normalize('NFKD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')
}
function updateName(value: string): void {
  form.value.name = value
  if (!manuallyEditedSlug.value) form.value.slug = toSlug(value)
}
function open(brand: Brand | null = null): void {
  opened.value = false
  editing.value = brand
  manuallyEditedSlug.value = brand !== null
  form.value = brand
    ? { name: brand.name, slug: brand.slug, description: brand.description ?? '', country_code: brand.country_code, is_active: brand.is_active }
    : { name: '', slug: '', description: '', country_code: null, is_active: true }
  queueMicrotask(() => { opened.value = true })
}
async function load(page = pagination.value?.current_page ?? 1): Promise<void> {
  await brandList.load(page, (requestedPage) => getBrands({ page: requestedPage }))
}
async function save(): Promise<void> {
  try {
    await saveBrand(editing.value?.id ?? null, form.value)
    opened.value = false
    await load()
  } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось сохранить бренд.' }
}
async function remove(): Promise<void> {
  if (!deleting.value) return
  isDeleting.value = true
  try {
    await deleteBrand(deleting.value.id)
    deleting.value = null
    await brandList.reloadAfterDeletion((page) => getBrands({ page }))
  } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось удалить бренд.' } finally { isDeleting.value = false }
}
onMounted(load)
</script>

<template>
  <section class="mx-auto admin-page" :aria-busy="loading">
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
      <div><p class="text-sm font-medium text-gray-500">Каталог</p><h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">Бренды</h1></div>
      <button v-if="canManage" class="inline-flex items-center gap-2 rounded-lg bg-primary-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-600" @click="open()"><Plus :size="18" />Добавить бренд</button>
    </div>
    <p v-if="error" class="mb-4 rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-500">{{ error }}</p>
    <p v-if="loading" class="sr-only" role="status">Загрузка брендов…</p>
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card">
      <div v-if="brands.length" class="divide-y divide-gray-100">
        <article v-for="brand in brands" :key="brand.id" class="flex items-center gap-4 p-4 sm:p-5">
          <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-primary-50 text-primary-600"><BadgeCheck :size="20" /></span>
          <div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><h2 class="truncate font-semibold text-gray-800">{{ brand.name }}</h2><span class="admin-badge rounded-full px-2 py-1 text-xs font-medium" :class="brand.is_active ? 'bg-success-50 text-success-500' : 'bg-gray-100 text-gray-500'">{{ brand.is_active ? 'Активен' : 'Скрыт' }}</span></div><p class="mt-1 truncate text-sm text-gray-500">/{{ brand.slug }}<template v-if="brand.country_code"> · {{ countryName(brand.country_code) }}</template></p><p v-if="brand.description" class="mt-1 truncate text-sm text-gray-500">{{ brand.description }}</p></div>
          <div v-if="canManage" class="flex gap-1"><button class="rounded-lg p-2 text-gray-500 hover:bg-primary-50 hover:text-primary-600" :aria-label="`Изменить бренд ${brand.name}`" @click="open(brand)"><Pencil :size="17" /></button><button class="rounded-lg p-2 text-gray-500 hover:bg-error-50 hover:text-error-500" :aria-label="`Удалить бренд ${brand.name}`" @click="deleting = brand"><Trash2 :size="17" /></button></div>
        </article>
      </div>
      <div v-else class="px-5 py-14 text-center text-sm text-gray-500">Брендов пока нет.</div>
    </div>
    <PaginationControls v-if="pagination" :meta="pagination" :loading="loading" @change="load" />
    <div v-if="opened" class="fixed inset-0 z-50 grid place-items-center bg-gray-900/50 p-4" @click.self="opened = false">
      <form class="admin-dialog-content w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl" @submit.prevent="save">
        <div class="flex items-start justify-between"><div><h2 class="text-lg font-bold text-gray-900">{{ title }}</h2><p class="mt-1 text-sm text-gray-500">Укажите сведения о бренде для каталога.</p></div><button type="button" class="rounded-lg p-1 text-gray-500 hover:bg-gray-50" aria-label="Закрыть" @click="opened = false"><X :size="20" /></button></div>
        <div class="mt-6 grid gap-4 sm:grid-cols-2">
          <label class="text-sm font-medium text-gray-700">Название<BaseInput :model-value="form.name" class="mt-1.5" required @update:model-value="updateName" /></label>
          <label class="text-sm font-medium text-gray-700">Технический код (slug)<BaseInput :model-value="form.slug" class="mt-1.5" pattern="[a-z0-9]+(-[a-z0-9]+)*" required @update:model-value="(value) => { form.slug = value; manuallyEditedSlug = true }" /></label>
          <label class="text-sm font-medium text-gray-700">Страна происхождения<BaseSelect v-model="selectedCountryCode" class="mt-1.5" :options="COUNTRY_OPTIONS" placeholder="Не выбрана" accessible-name="Страна происхождения" searchable /></label>
          <label class="text-sm font-medium text-gray-700 sm:col-span-2">Описание<textarea v-model="form.description" class="mt-1.5 min-h-24 w-full rounded-lg border border-gray-300 p-3 font-normal outline-none focus:border-primary-500" /></label>
          <BaseCheckbox :checked="form.is_active" mode="boolean" class="sm:col-span-2" @update:checked="form.is_active = $event">Бренд активен и доступен на витрине</BaseCheckbox>
        </div>
        <div class="mt-6 flex justify-end gap-3"><button type="button" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50" @click="opened = false">Отмена</button><button class="rounded-lg bg-primary-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-600">Сохранить</button></div>
      </form>
    </div>
    <div v-if="deleting" class="fixed inset-0 z-[60] grid place-items-center bg-gray-900/50 p-4">
      <section class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl"><h2 class="text-lg font-bold text-gray-900">Удалить бренд?</h2><p class="mt-3 text-sm text-gray-500">Бренд «{{ deleting.name }}» будет удалён. Это действие нельзя отменить.</p><div class="mt-6 flex justify-end gap-3"><button class="rounded-lg px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50" type="button" :disabled="isDeleting" @click="deleting = null">Отмена</button><button class="rounded-lg bg-error-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-error-700 disabled:cursor-not-allowed disabled:opacity-60" type="button" :disabled="isDeleting" @click="remove">{{ isDeleting ? 'Удаление…' : 'Удалить' }}</button></div></section>
    </div>
  </section>
</template>
