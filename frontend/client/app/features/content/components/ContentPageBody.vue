<script setup lang="ts">
import type { ContentPage } from '~/types/contentPage'
import PageBlocks from './PageBlocks.vue'
import PageState from '~/components/shared/PageState.vue'
import PartnershipSection from '~/features/partnership/components/PartnershipSection.vue'
import PageIntro from '~/components/shared/PageIntro.vue'

const props = defineProps<{ content: ContentPage }>()
const hasLeadingHero = computed(
  () => props.content.blocks.find((block) => block.enabled)?.type === 'hero',
)
</script>

<template>
  <PageIntro
    v-if="!hasLeadingHero"
    :title="content.title"
    :slug="content.slug"
    :description="content.seo.description"
    :image="content.intro"
  />
  <PageBlocks
    :blocks="content.blocks"
    :title="content.title"
    :description="content.seo.description"
    :hero-heading-tag="hasLeadingHero ? 'h1' : 'h2'"
  />
  <PartnershipSection v-if="content.slug === 'cooperation'" />
  <div
    v-if="
      content.slug !== 'cooperation' &&
      !content.blocks.some((block) => block.enabled)
    "
    class="container"
  >
    <PageState
      title="Содержимое пока не добавлено"
      message="Информация появится после публикации."
    />
  </div>
</template>
