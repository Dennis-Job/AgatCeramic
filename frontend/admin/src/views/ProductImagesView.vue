<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { ImagePlus, Save, Star, Trash2 } from '@lucide/vue'
import BaseCheckbox from '../components/BaseCheckbox.vue'
import BaseInput from '../components/BaseInput.vue'
import BaseSelect from '../components/BaseSelect.vue'
import { getAllProducts, type Product } from '../services/products'
import { deleteProductImage, getAllProductImages, updateProductImage, uploadProductImage, type ProductImage } from '../services/productImages'
import { useAuthStore } from '../stores/auth'

type ImageForm = { alt: string; sort_order: string; is_primary: boolean }
const auth = useAuthStore(); const products = ref<Product[]>([]); const images = ref<ProductImage[]>([]); const selectedProductId = ref(''); const selectedFile = ref<File | null>(null); const form = ref<ImageForm>({ alt: '', sort_order: '0', is_primary: false }); const drafts = ref<Record<number, ImageForm>>({}); const error = ref(''); const saving = ref(false)
const canManage = computed(() => auth.hasPermission('catalog.manage')); const productOptions = computed(() => products.value.map((product) => ({ value: String(product.id), label: product.name })))
function draft(image: ProductImage): ImageForm { return drafts.value[image.id] ?? { alt: image.alt ?? '', sort_order: String(image.sort_order), is_primary: image.is_primary } }
function resetUpload(): void { selectedFile.value = null; form.value = { alt: '', sort_order: '0', is_primary: false } }
async function loadProducts(): Promise<void> { try { products.value = await getAllProducts() } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось загрузить товары.' } }
async function loadImages(): Promise<void> { if (!selectedProductId.value) { images.value = []; return }; try { images.value = await getAllProductImages(Number(selectedProductId.value)); drafts.value = Object.fromEntries(images.value.map((image) => [image.id, { alt: image.alt ?? '', sort_order: String(image.sort_order), is_primary: image.is_primary }])) } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось загрузить изображения.' } }
function selectFile(event: Event): void { selectedFile.value = (event.target as HTMLInputElement).files?.[0] ?? null }
async function upload(): Promise<void> { if (!selectedProductId.value || !selectedFile.value) return; saving.value = true; try { await uploadProductImage(Number(selectedProductId.value), selectedFile.value, { alt: form.value.alt || null, is_primary: form.value.is_primary, sort_order: Number(form.value.sort_order) }); resetUpload(); await loadImages() } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось загрузить изображение.' } finally { saving.value = false } }
async function save(image: ProductImage): Promise<void> { if (!selectedProductId.value) return; saving.value = true; try { const item = draft(image); await updateProductImage(Number(selectedProductId.value), image.id, { alt: item.alt || null, sort_order: Number(item.sort_order), is_primary: item.is_primary }); await loadImages() } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось сохранить изображение.' } finally { saving.value = false } }
async function remove(image: ProductImage): Promise<void> { if (!selectedProductId.value || !confirm(`Удалить изображение «${image.alt || image.id}»?`)) return; saving.value = true; try { await deleteProductImage(Number(selectedProductId.value), image.id); await loadImages() } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось удалить изображение.' } finally { saving.value = false } }
watch(selectedProductId, () => { resetUpload(); void loadImages() }); onMounted(loadProducts)
</script>
<template>
  <section class="mx-auto admin-page"><div class="mb-7"><p class="text-sm font-medium text-gray-500">Каталог</p><h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">Изображения товаров</h1><p class="mt-2 text-sm text-gray-500">JPEG, PNG или WebP до 10 МБ. Первое изображение станет обложкой товара.</p></div><p v-if="error" class="mb-4 rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-500">{{ error }}</p><label class="mb-5 block max-w-xl text-sm font-medium text-gray-700">Товар<BaseSelect v-model="selectedProductId" class="mt-1.5" :options="productOptions" placeholder="Выберите товар" accessible-name="Товар" searchable /></label><form v-if="selectedProductId && canManage" class="mb-6 rounded-xl border border-gray-200 bg-white p-5 shadow-card" @submit.prevent="upload"><div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-medium text-gray-700">Файл<input class="mt-1.5 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:font-semibold file:text-primary-600" type="file" accept="image/jpeg,image/png,image/webp" required @change="selectFile" /></label><label class="text-sm font-medium text-gray-700">Alt-текст<BaseInput v-model="form.alt" class="mt-1.5" /></label><label class="text-sm font-medium text-gray-700">Порядок<BaseInput v-model="form.sort_order" class="mt-1.5" type="number" min="0" required /></label><BaseCheckbox :checked="form.is_primary" class="self-end" mode="boolean" @update:checked="form.is_primary = $event">Сделать обложкой</BaseCheckbox></div><button class="mt-5 inline-flex items-center gap-2 rounded-lg bg-primary-500 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60" :disabled="saving"><ImagePlus :size="18" />{{ saving ? 'Загрузка…' : 'Загрузить изображение' }}</button></form><div v-if="selectedProductId" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3"><article v-for="image in images" :key="image.id" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card"><img :src="image.url" :alt="image.alt ?? ''" class="aspect-[4/3] w-full bg-gray-50 object-cover" /><div class="p-4"><p v-if="image.is_primary" class="mb-3 inline-flex items-center gap-1 rounded-full bg-warning-50 px-2 py-1 text-xs font-semibold text-warning-600"><Star :size="13" />Обложка</p><label class="block text-sm font-medium text-gray-700">Alt-текст<BaseInput v-model="drafts[image.id].alt" class="mt-1.5" /></label><div class="mt-3 grid grid-cols-2 gap-3"><label class="text-sm font-medium text-gray-700">Порядок<BaseInput v-model="drafts[image.id].sort_order" class="mt-1.5" type="number" min="0" /></label><BaseCheckbox :checked="drafts[image.id].is_primary" class="self-end" mode="boolean" @update:checked="drafts[image.id].is_primary = $event">Обложка</BaseCheckbox></div><div v-if="canManage" class="mt-4 flex justify-between"><button class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-semibold text-primary-600 hover:bg-primary-50" :disabled="saving" @click="save(image)"><Save :size="16" />Сохранить</button><button class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-semibold text-error-500 hover:bg-error-50" :disabled="saving" @click="remove(image)"><Trash2 :size="16" />Удалить</button></div></div></article><div v-if="!images.length" class="col-span-full rounded-xl border border-dashed border-gray-300 px-5 py-14 text-center text-sm text-gray-500">У выбранного товара пока нет изображений.</div></div><div v-else class="rounded-xl border border-dashed border-gray-300 px-5 py-14 text-center text-sm text-gray-500">Выберите товар, чтобы загрузить его изображения.</div></section>
</template>

<style scoped>
article {
  position: relative;
}

article > div > p:first-child {
  position: absolute;
  top: 0.75rem;
  left: 0.75rem;
  z-index: 1;
  margin: 0;
  box-shadow: 0 1px 3px rgb(0 0 0 / 15%);
}
</style>
