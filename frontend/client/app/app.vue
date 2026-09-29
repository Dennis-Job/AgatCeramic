<script setup lang="ts">
const { data: homePage } = await useHomePageContent()
</script>

<template>
  <NuxtRouteAnnouncer />
  <NuxtLayout :home-page="homePage">
    <NuxtPage />
  </NuxtLayout>
</template>
