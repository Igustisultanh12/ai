<template>
  <svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    :stroke-width="strokeWidth"
    stroke-linecap="round"
    stroke-linejoin="round"
    class="icon"
    aria-hidden="true"
  >
    <template v-for="(shape, i) in shapes" :key="i">
      <path v-if="typeof shape === 'string'" :d="shape" />
      <circle v-else-if="shape.c" :cx="shape.c[0]" :cy="shape.c[1]" :r="shape.c[2]" />
      <rect v-else-if="shape.r" :x="shape.r[0]" :y="shape.r[1]" :width="shape.r[2]" :height="shape.r[3]" :rx="shape.r[4] ?? 0" />
    </template>
  </svg>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { ICONS, type IconShape } from './icons';

const props = withDefaults(defineProps<{ name: string; strokeWidth?: number | string }>(), {
    strokeWidth: 2,
});

const shapes = computed<IconShape[]>(() => ICONS[props.name] ?? ICONS['circle']);
</script>
