<script setup lang="ts">
import { ArrowLeft, ArrowRight, Expand } from '@lucide/vue'
import UiDialog from '~/components/ui/UiDialog.vue'
import UiIconButton from '~/components/ui/UiIconButton.vue'
import type { ProductImage } from '../types/product'
const props = defineProps<{ name: string; images: ProductImage[] }>()
const index = ref(0)
const zoom = ref(false)
const failed = ref(new Set<number>())
watch(
  () => props.images,
  (images) => {
    index.value = Math.max(
      0,
      images.findIndex((image) => image.is_primary),
    )
    failed.value = new Set()
    zoom.value = false
  },
  { immediate: true },
)
const image = computed(() => props.images[index.value])
function step(delta: number) {
  if (props.images.length)
    index.value =
      (index.value + delta + props.images.length) % props.images.length
}
function keydown(event: KeyboardEvent) {
  if (!zoom.value) return
  if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
    event.preventDefault()
    step(event.key === 'ArrowRight' ? 1 : -1)
  }
}
function imageFailed(id: number) {
  failed.value = new Set([...failed.value, id])
}
onMounted(() => window.addEventListener('keydown', keydown))
onBeforeUnmount(() => window.removeEventListener('keydown', keydown))
</script>
<template>
  <div class="product-gallery">
    <div class="gallery-layout">
      <div
        v-if="images.length > 1"
        class="thumbnails"
        aria-label="Фотографии товара"
      >
        <button
          v-for="(photo, position) in images"
          :key="photo.id"
          type="button"
          :aria-label="'Фото ' + (position + 1) + ' из ' + images.length"
          :aria-current="index === position ? 'true' : undefined"
          @click="index = position"
        >
          <img
            v-if="!failed.has(photo.id)"
            :src="photo.url"
            alt=""
            width="72"
            height="72"
            loading="lazy"
            @error="imageFailed(photo.id)"
          />
          <span v-else>{{ position + 1 }}</span>
        </button>
      </div>
      <figure>
        <div class="stage">
          <button
            v-if="image && !failed.has(image.id)"
            type="button"
            aria-label="Увеличить фото товара"
            aria-haspopup="dialog"
            class="stage-trigger"
            @click="zoom = true"
          >
            <img
              :key="image.id"
              data-testid="product-main-image"
              :src="image.url"
              :alt="name + ' — ' + (image.alt || 'фото ' + (index + 1))"
              width="660"
              height="660"
              fetchpriority="high"
              @error="imageFailed(image.id)"
            />
            <span class="expand"
              ><Expand :size="17" :stroke-width="1.6" aria-hidden="true"
            /></span>
          </button>
          <span v-else class="image-empty">Изображение пока не добавлено</span>
        </div>
        <figcaption v-if="images.length">
          <span>Фото {{ index + 1 }}</span>
          <div v-if="images.length > 1" class="photo-controls">
            <span>{{ index + 1 }} / {{ images.length }}</span>
            <UiIconButton label="Предыдущее фото" @click="step(-1)"
              ><ArrowLeft :size="15" :stroke-width="1.6" aria-hidden="true"
            /></UiIconButton>
            <UiIconButton label="Следующее фото" @click="step(1)"
              ><ArrowRight :size="15" :stroke-width="1.6" aria-hidden="true"
            /></UiIconButton>
          </div>
        </figcaption>
      </figure>
    </div>
    <UiDialog
      v-if="zoom"
      title="Просмотр фото"
      size="wide"
      @close="zoom = false"
    >
      <div class="zoom-content">
        <img
          v-if="image && !failed.has(image.id)"
          :src="image.url"
          :alt="name + ' — фото ' + (index + 1)"
          width="660"
          height="660"
          @error="imageFailed(image.id)"
        />
        <p v-else>Не удалось загрузить изображение.</p>
        <div class="photo-controls">
          <UiIconButton
            v-if="images.length > 1"
            label="Предыдущее фото"
            @click="step(-1)"
            ><ArrowLeft :size="17" aria-hidden="true"
          /></UiIconButton>
          <span>{{ index + 1 }} / {{ images.length }}</span>
          <UiIconButton
            v-if="images.length > 1"
            label="Следующее фото"
            @click="step(1)"
            ><ArrowRight :size="17" aria-hidden="true"
          /></UiIconButton>
        </div>
      </div>
    </UiDialog>
  </div>
</template>
<style scoped>
.product-gallery {
  position: sticky;
  top: 110px;
  min-width: 0;
  align-self: start;
}
.gallery-layout {
  display: grid;
  grid-template-columns: 72px minmax(0, 1fr);
  gap: 14px;
  align-items: start;
}
.gallery-layout:not(:has(.thumbnails)) {
  grid-template-columns: minmax(0, 1fr);
}
.thumbnails {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.thumbnails button {
  display: grid;
  place-items: center;
  width: 72px;
  aspect-ratio: 1;
  padding: 3px;
  border: 1px solid transparent;
  background: var(--color-beige);
  opacity: 0.65;
  transition:
    opacity 0.3s,
    border-color 0.3s;
}
.thumbnails button:hover,
.thumbnails button[aria-current='true'] {
  opacity: 1;
  border-color: var(--color-ink);
}
.thumbnails img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}
figure {
  min-width: 0;
  margin: 0;
}
.stage {
  display: grid;
  place-items: center;
  position: relative;
  aspect-ratio: 1;
  overflow: hidden;
  background: var(--color-beige);
}
.stage-trigger {
  display: block;
  width: 100%;
  height: 100%;
  padding: 0;
  border: 0;
  background: transparent;
  cursor: zoom-in;
}
.stage-trigger:focus-visible {
  outline-offset: -5px;
}
.stage img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: contain;
  transition: transform 0.8s var(--ease-out);
}
.stage-trigger:hover img {
  transform: scale(1.025);
}
.expand {
  position: absolute;
  right: 16px;
  bottom: 16px;
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: var(--color-card-label-surface);
}
.image-empty {
  padding: 24px;
  color: var(--color-muted);
  font-size: 13px;
  text-align: center;
}
figcaption {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding-top: 12px;
  color: var(--color-muted);
  font-size: 11px;
}
.photo-controls {
  display: flex;
  align-items: center;
  gap: 3px;
  font-variant-numeric: tabular-nums;
}
.photo-controls > span {
  margin-right: 8px;
  white-space: nowrap;
  letter-spacing: 0.06em;
  font-size: 11px;
}
.zoom-content img {
  display: block;
  width: 100%;
  max-height: calc(100dvh - 240px);
  object-fit: contain;
}
.zoom-content .photo-controls {
  justify-content: center;
  margin-top: 18px;
}
@media (max-width: 1024px) {
  .gallery-layout {
    grid-template-columns: 1fr;
  }
  .thumbnails {
    order: 2;
    flex-direction: row;
    flex-wrap: wrap;
    gap: 9px;
  }
  .thumbnails button {
    width: 62px;
  }
}
@media (max-width: 760px) {
  .product-gallery {
    position: static;
    width: 100%;
  }
  .gallery-layout {
    grid-template-columns: 64px minmax(0, 1fr);
    gap: 12px;
  }
  .thumbnails {
    order: 0;
    flex-direction: column;
  }
  .thumbnails button {
    width: 64px;
  }
}
@media (max-width: 520px) {
  .gallery-layout {
    grid-template-columns: 1fr;
  }
  .thumbnails {
    order: 2;
    flex-direction: row;
  }
}
</style>
