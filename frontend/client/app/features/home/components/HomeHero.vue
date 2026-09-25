<script setup lang="ts">
import { ArrowLeft, ArrowRight } from '@lucide/vue'
import UiButton from '~/components/ui/UiButton.vue'
import type { HomeSlide } from '../data/homeContent'

const props = defineProps<{ slides: readonly HomeSlide[] }>()
const activeIndex = ref(0)
const isPaused = ref(false)
const activeSlide = computed(() => props.slides[activeIndex.value])
let timer: ReturnType<typeof setInterval> | undefined

function selectSlide(index: number) {
  activeIndex.value = (index + props.slides.length) % props.slides.length
}

onMounted(() => {
  if (
    window.matchMedia('(prefers-reduced-motion: reduce)').matches ||
    props.slides.length < 2
  )
    return
  timer = setInterval(() => {
    if (!isPaused.value && document.visibilityState === 'visible')
      selectSlide(activeIndex.value + 1)
  }, 6500)
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})
</script>

<template>
  <section
    v-if="activeSlide"
    id="home"
    class="hero"
    aria-label="Главный экран"
    @mouseenter="isPaused = true"
    @mouseleave="isPaused = false"
    @focusin="isPaused = true"
    @focusout="isPaused = false"
  >
    <div :key="activeSlide.id" class="hero__slide">
      <div class="hero__copy">
        <div class="hero__copy-inner">
          <span class="eyebrow">{{ activeSlide.eyebrow }}</span>
          <h1>{{ activeSlide.title }}</h1>
          <p>{{ activeSlide.description }}</p>
          <UiButton to="/#catalog">
            Смотреть направления
            <span class="hero__button-arrow" aria-hidden="true">→</span>
          </UiButton>
        </div>
      </div>
      <div class="hero__image">
        <img
          :src="activeSlide.image"
          :alt="activeSlide.imageAlt"
          width="1280"
          height="853"
          fetchpriority="high"
        />
      </div>
    </div>

    <div
      v-if="slides.length > 1"
      class="hero__controls"
      aria-label="Управление слайдером"
    >
      <button
        type="button"
        aria-label="Предыдущий слайд"
        @click="selectSlide(activeIndex - 1)"
      >
        <ArrowLeft :size="18" :stroke-width="1.6" aria-hidden="true" />
      </button>
      <button
        type="button"
        aria-label="Следующий слайд"
        @click="selectSlide(activeIndex + 1)"
      >
        <ArrowRight :size="18" :stroke-width="1.6" aria-hidden="true" />
      </button>
      <span class="hero__count" aria-live="polite">
        <strong>{{ String(activeIndex + 1).padStart(2, '0') }}</strong> /
        {{ String(slides.length).padStart(2, '0') }}
      </span>
    </div>

    <div v-if="slides.length > 1" class="hero__dots" aria-label="Выбрать слайд">
      <button
        v-for="(slide, index) in slides"
        :key="slide.id"
        type="button"
        :class="{ 'is-active': index === activeIndex }"
        :aria-label="`Слайд ${index + 1}: ${slide.eyebrow}`"
        :aria-current="index === activeIndex ? 'true' : undefined"
        @click="selectSlide(index)"
      />
    </div>
    <div class="hero__scroll-hint" aria-hidden="true">Листайте <span /></div>
  </section>
</template>

<style scoped>
.hero {
  position: relative;
  min-height: 620px;
  height: min(790px, calc(100svh - 118px));
  overflow: hidden;
}
.hero__slide {
  display: grid;
  height: 100%;
  grid-template-columns: 1.05fr 1fr;
}
.hero__copy {
  display: flex;
  align-items: center;
  min-width: 0;
  padding: 40px 40px 110px
    max(40px, calc((100vw - var(--content-width)) / 2 + 40px));
}
.hero__copy-inner {
  max-width: 540px;
  animation: copy-in 0.8s var(--ease-out) both;
}
.hero h1 {
  margin: 28px 0;
  font-size: clamp(38px, 4.6vw, 64px);
  font-weight: 500;
  letter-spacing: -0.025em;
  line-height: 1.06;
}
.hero p {
  max-width: 390px;
  margin: 0 0 40px;
  color: var(--color-muted);
  font-size: 15px;
  line-height: 1.7;
}
.hero__button-arrow {
  font-size: 16px;
  line-height: 1;
  transition: transform 0.3s;
}
:deep(.ui-button:hover) .hero__button-arrow {
  transform: translateX(4px);
}
.hero__image {
  position: relative;
  min-width: 0;
  overflow: hidden;
  background: var(--color-beige);
}
.hero__image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  animation: image-in 7.5s var(--ease-out) both;
}
.hero__image::after {
  position: absolute;
  inset: 0;
  background: linear-gradient(90deg, var(--color-bg), transparent 18%);
  content: '';
  pointer-events: none;
}
.hero__controls {
  position: absolute;
  z-index: 2;
  bottom: 44px;
  left: max(40px, calc((100vw - var(--content-width)) / 2 + 40px));
  display: flex;
  align-items: center;
  gap: 18px;
}
.hero__controls button {
  display: grid;
  width: 52px;
  height: 52px;
  place-items: center;
  border: 1px solid var(--color-line);
  border-radius: 50%;
  background: var(--color-hero-control-surface);
  transition:
    background 0.3s,
    color 0.3s;
}
.hero__controls button:hover {
  background: var(--color-ink);
  color: var(--color-white);
}
.hero__count {
  margin-left: 4px;
  color: var(--color-muted);
  font-size: 12px;
  letter-spacing: 0.2em;
  white-space: nowrap;
}
.hero__count strong {
  color: var(--color-ink);
  font-weight: 500;
}
.hero__dots {
  position: absolute;
  z-index: 2;
  right: 40px;
  bottom: 50px;
  display: flex;
  gap: 8px;
}
.hero__dots button {
  width: 28px;
  height: 16px;
  border: 0;
  border-top: 2px solid transparent;
  border-bottom: 2px solid transparent;
  background: linear-gradient(var(--color-line), var(--color-line)) center /
    100% 2px no-repeat;
}
.hero__dots button.is-active {
  background-image: linear-gradient(var(--color-ink), var(--color-ink));
}
.hero__scroll-hint {
  position: absolute;
  z-index: 2;
  bottom: 44px;
  left: 50%;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  color: var(--color-muted);
  font-size: 10px;
  letter-spacing: 0.3em;
  text-transform: uppercase;
  transform: translateX(-50%);
}
.hero__scroll-hint span {
  display: block;
  width: 1px;
  height: 44px;
  background: var(--color-line);
}
@keyframes copy-in {
  from {
    opacity: 0;
    transform: translateY(24px);
  }
  to {
    opacity: 1;
    transform: none;
  }
}
@keyframes image-in {
  from {
    transform: scale(1.07);
  }
  to {
    transform: scale(1);
  }
}
@media (max-width: 900px) {
  .hero {
    height: auto;
    min-height: 0;
  }
  .hero__slide {
    grid-template-columns: 1fr;
  }
  .hero__image {
    grid-row: 1;
    height: clamp(300px, 46vh, 520px);
  }
  .hero__image::after {
    background: linear-gradient(0deg, var(--color-bg), transparent 22%);
  }
  .hero__copy {
    padding: 46px var(--page-gutter) 112px;
  }
  .hero__controls {
    left: var(--page-gutter);
    bottom: 32px;
  }
  .hero__dots {
    right: var(--page-gutter);
    bottom: 45px;
  }
  .hero__scroll-hint {
    display: none;
  }
}
@media (max-width: 520px) {
  .hero__image {
    height: 40vh;
    min-height: 270px;
  }
  .hero__copy {
    padding-top: 34px;
  }
  .hero h1 {
    margin: 18px 0;
  }
  .hero p {
    margin-bottom: 28px;
  }
  .hero__controls {
    gap: 8px;
  }
  .hero__controls button {
    width: 44px;
    height: 44px;
  }
  .hero__count {
    margin-left: 6px;
  }
  .hero__dots {
    bottom: 42px;
  }
}
</style>
