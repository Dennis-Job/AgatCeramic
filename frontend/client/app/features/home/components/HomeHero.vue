<script setup lang="ts">
import { ArrowLeft, ArrowRight } from '@lucide/vue'
import UiButton from '~/components/ui/UiButton.vue'
import type { HomeSlide } from '~/types/homePage'

const SLIDE_DURATION = 6500

const props = defineProps<{
  slides: readonly HomeSlide[]
  emptyTitle: string
  emptyDescription: string
}>()
const activeIndex = ref(0)
const isReady = ref(false)
const isPointerInside = ref(false)
const isFocusInside = ref(false)
const isPageVisible = ref(true)
const isReducedMotion = ref(false)
const progressFill = ref<HTMLElement | null>(null)
const activeSlide = computed(() => props.slides[activeIndex.value])
const canPlay = computed(
  () =>
    isReady.value &&
    props.slides.length > 1 &&
    !isPointerInside.value &&
    !isFocusInside.value &&
    isPageVisible.value &&
    !isReducedMotion.value,
)
const titleWords = computed(
  () =>
    activeSlide.value?.title
      .trim()
      .split(/\s+/u)
      .map((word) => `${word}\u00a0`) ?? [],
)
let timer: ReturnType<typeof setTimeout> | undefined
let progressFrame: number | undefined
let timerStartedAt = 0
let remainingMs = SLIDE_DURATION
let motionQuery: MediaQueryList | undefined

watch(
  () => props.slides.length,
  (count) => {
    if (activeIndex.value >= count) activeIndex.value = 0
    resetCountdown()
  },
)

function selectSlide(index: number) {
  if (!props.slides.length) return
  activeIndex.value = (index + props.slides.length) % props.slides.length
  resetCountdown()
}

function renderProgress(timeLeft: number) {
  const progress = 1 - timeLeft / SLIDE_DURATION
  progressFill.value?.style.setProperty('--progress', String(progress))
}

function stopProgressFrame() {
  if (progressFrame !== undefined) cancelAnimationFrame(progressFrame)
  progressFrame = undefined
}

function updateProgress() {
  const timeLeft = Math.max(
    0,
    remainingMs - (performance.now() - timerStartedAt),
  )
  renderProgress(timeLeft)
  progressFrame = requestAnimationFrame(updateProgress)
}

function syncCountdown() {
  if (canPlay.value) {
    if (timer) return
    timerStartedAt = performance.now()
    timer = setTimeout(() => {
      timer = undefined
      selectSlide(activeIndex.value + 1)
    }, remainingMs)
    progressFrame = requestAnimationFrame(updateProgress)
    return
  }

  if (!timer) return
  clearTimeout(timer)
  timer = undefined
  remainingMs = Math.max(0, remainingMs - (performance.now() - timerStartedAt))
  stopProgressFrame()
  renderProgress(remainingMs)
}

function resetCountdown() {
  if (timer) clearTimeout(timer)
  timer = undefined
  stopProgressFrame()
  remainingMs = SLIDE_DURATION
  renderProgress(remainingMs)
  syncCountdown()
}

function onPointerEnter(event: PointerEvent) {
  if (event.pointerType !== 'touch') isPointerInside.value = true
}

function onPointerLeave(event: PointerEvent) {
  if (event.pointerType !== 'touch') isPointerInside.value = false
}

function onFocusOut(event: FocusEvent) {
  const next = event.relatedTarget
  if (!(next instanceof Node) || !(event.currentTarget as Node).contains(next))
    isFocusInside.value = false
}

function onVisibilityChange() {
  isPageVisible.value = document.visibilityState === 'visible'
}

function onMotionChange(event: MediaQueryListEvent) {
  isReducedMotion.value = event.matches
  resetCountdown()
}

watch(canPlay, syncCountdown, { flush: 'sync' })

function onSlideLeave(element: Element) {
  const slide = element as HTMLElement
  slide.setAttribute('aria-hidden', 'true')
  slide.inert = true
}

onMounted(() => {
  motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)')
  isReducedMotion.value = motionQuery.matches
  onVisibilityChange()
  motionQuery.addEventListener('change', onMotionChange)
  document.addEventListener('visibilitychange', onVisibilityChange)
  isReady.value = true
})

onUnmounted(() => {
  if (timer) clearTimeout(timer)
  stopProgressFrame()
  motionQuery?.removeEventListener('change', onMotionChange)
  document.removeEventListener('visibilitychange', onVisibilityChange)
})
</script>

<template>
  <section
    id="home"
    class="hero"
    :class="{ 'hero--empty': !activeSlide }"
    aria-label="Главный экран"
    @pointerenter="onPointerEnter"
    @pointerleave="onPointerLeave"
    @focusin="isFocusInside = true"
    @focusout="onFocusOut"
  >
    <Transition name="hero-slide" @leave="onSlideLeave">
      <div v-if="activeSlide" :key="activeSlide.id" class="hero__slide">
        <div class="hero__copy">
          <div class="hero__copy-inner">
            <span class="eyebrow hero__eyebrow">{{ activeSlide.eyebrow }}</span>
            <h1 :aria-label="activeSlide.title">
              <span
                v-for="(word, index) in titleWords"
                :key="index"
                class="hero__word"
                aria-hidden="true"
              >
                <span
                  class="hero__word-inner"
                  :style="{ animationDelay: `${300 + index * 100}ms` }"
                  >{{ word }}</span
                ></span
              >
            </h1>
            <p class="hero__description">{{ activeSlide.description }}</p>
            <div v-if="activeSlide.linkUrl" class="hero__cta">
              <UiButton :to="activeSlide.linkUrl">
                {{ activeSlide.linkLabel }}
                <ArrowRight
                  class="hero__button-arrow"
                  :size="16"
                  :stroke-width="1.6"
                  aria-hidden="true"
                />
              </UiButton>
            </div>
          </div>
        </div>
        <div class="hero__image">
          <img
            v-if="activeSlide.image"
            :src="activeSlide.image"
            :alt="activeSlide.imageAlt"
            width="1280"
            height="853"
            fetchpriority="high"
          />
        </div>
      </div>
    </Transition>

    <div v-if="!activeSlide" class="hero__empty container">
      <h1>{{ emptyTitle }}</h1>
      <p>{{ emptyDescription }}</p>
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
    <div v-if="slides.length > 1 && !isReducedMotion" class="hero__next-hint">
      <span class="visually-hidden"
        >Шкала времени до автоматической смены слайда</span
      >
      <span class="hero__next-track" aria-hidden="true">
        <span ref="progressFill" class="hero__next-fill" />
      </span>
    </div>
  </section>
</template>

<style scoped>
.hero {
  position: relative;
  min-height: 620px;
  height: calc(100svh - 120px);
  overflow: hidden;
}
.hero--empty {
  display: flex;
  min-height: 400px;
  height: auto;
  align-items: center;
  padding-block: 100px;
}
.hero__empty {
  width: 100%;
}
.hero__slide {
  display: grid;
  height: 100%;
  grid-template-columns: 1.05fr 1fr;
}
.hero-slide-enter-active,
.hero-slide-leave-active {
  transition: opacity 1s ease;
}
.hero-slide-enter-from,
.hero-slide-leave-to {
  opacity: 0;
}
.hero-slide-leave-active {
  position: absolute;
  inset: 0;
  width: 100%;
  pointer-events: none;
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
}
.hero__eyebrow,
.hero__description,
.hero__cta {
  display: block;
  opacity: 0;
  transform: translateY(28px);
  animation: hero-fade-up 0.9s var(--ease-out) both;
}
.hero__eyebrow {
  animation-delay: 0.15s;
}
.hero__description {
  animation-delay: 0.75s;
}
.hero__cta {
  animation-delay: 0.95s;
}
.hero h1 {
  margin: 28px 0;
  font-size: clamp(38px, 4.6vw, 64px);
  font-weight: 500;
  letter-spacing: -0.025em;
  line-height: 1.06;
}
.hero__word {
  display: inline-block;
  overflow: hidden;
  padding-bottom: 0.08em;
  margin-bottom: -0.08em;
  vertical-align: top;
}
.hero__word-inner {
  display: inline-block;
  transform: translateY(112%);
  animation: hero-word-up 1s var(--ease-out) both;
}
.hero p {
  max-width: 390px;
  margin: 0 0 40px;
  color: var(--color-muted);
  font-size: 15px;
  line-height: 1.7;
}
.hero__button-arrow {
  flex: none;
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
.hero__next-hint {
  position: absolute;
  z-index: 2;
  bottom: 44px;
  left: 50%;
  transform: translateX(-50%);
}
.hero__next-track {
  display: block;
  width: 1px;
  height: 44px;
  background: var(--color-line);
  overflow: hidden;
}
.hero__next-fill {
  display: block;
  width: 100%;
  height: 100%;
  background: var(--color-ink);
  transform: scaleY(var(--progress, 0));
  transform-origin: top;
}
@keyframes hero-fade-up {
  from {
    opacity: 0;
    transform: translateY(28px);
  }
  to {
    opacity: 1;
    transform: none;
  }
}
@keyframes hero-word-up {
  to {
    transform: translateY(0);
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
@media (prefers-reduced-motion: reduce) {
  .hero__next-hint {
    display: none;
  }
  .hero-slide-enter-active,
  .hero-slide-leave-active {
    transition: none;
  }
  .hero__eyebrow,
  .hero__word-inner,
  .hero__description,
  .hero__cta {
    opacity: 1;
    transform: none;
    animation: none !important;
  }
  .hero__image img {
    animation: none !important;
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
    padding: 46px var(--page-gutter) 168px;
  }
  .hero__controls {
    left: var(--page-gutter);
    bottom: 32px;
  }
  .hero__dots {
    right: var(--page-gutter);
    bottom: 45px;
  }
  .hero__next-hint {
    bottom: 118px;
  }
  .hero__next-track {
    width: 160px;
    height: 2px;
  }
  .hero__next-fill {
    transform: scaleX(var(--progress, 0));
    transform-origin: left;
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
@media (max-width: 380px) {
  .hero__next-hint {
    bottom: 130px;
  }
  .hero__dots {
    bottom: 89px;
  }
  .hero__controls {
    bottom: 28px;
  }
}
</style>
