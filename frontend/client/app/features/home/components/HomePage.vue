<script setup lang="ts">
import type { HomePageContent } from '~/types/homePage'
import HomePageBody from './HomePageBody.vue'

defineProps<{ content: HomePageContent }>()
</script>

<template>
  <HomePageBody :content="content" />
</template>
