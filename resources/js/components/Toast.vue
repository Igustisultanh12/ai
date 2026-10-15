<template>
  <div class="pointer-events-none fixed right-4 bottom-4 z-[80] flex w-full max-w-sm flex-col gap-3 sm:right-6 sm:bottom-6">
    <transition-group
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="translate-y-3 scale-95 opacity-0"
      enter-to-class="translate-y-0 scale-100 opacity-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="translate-x-6 opacity-0"
    >
      <div
        v-for="toast in toasts"
        :key="toast.id"
        role="status"
        class="pointer-events-auto flex items-start gap-3 rounded-2xl border-2 border-ink bg-card p-4 shadow-brutal"
      >
        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl border-2 border-ink" :class="STYLES[toast.type].tile">
          <Icon :name="STYLES[toast.type].icon" class="h-4 w-4" />
        </span>
        <div class="min-w-0 flex-1 text-sm">
          <p v-if="toast.title" class="font-display font-bold text-ink">{{ toast.title }}</p>
          <p class="leading-relaxed break-words text-ink/80">{{ toast.message }}</p>
        </div>
        <button type="button" class="btn-ghost grid h-7 w-7 shrink-0 place-items-center rounded-lg" title="Tutup" @click="remove(toast.id)">
          <Icon name="x" class="h-4 w-4" />
        </button>
      </div>
    </transition-group>
  </div>
</template>

<script setup lang="ts">
import { useToast } from '@/composables/useToast';

const { toasts, remove } = useToast();

const STYLES: Record<string, { icon: string; tile: string }> = {
    success: { icon: 'check', tile: 'bg-lime-300 text-zinc-900' },
    error: { icon: 'x', tile: 'bg-rose-500 text-white' },
    warning: { icon: 'alert-triangle', tile: 'bg-yellow-300 text-zinc-900' },
    info: { icon: 'info', tile: 'bg-violet-500 text-white' },
};
</script>
