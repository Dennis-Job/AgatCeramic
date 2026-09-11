<script setup lang="ts">withDefaults(defineProps<{ padded?: boolean }>(), { padded: true })</script>
<template><section class="rounded-xl border border-gray-200 bg-white shadow-card" :class="{ 'p-5': padded }"><slot/></section></template>
