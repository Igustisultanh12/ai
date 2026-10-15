<template>
  <div class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none">
    <transition-group
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="pointer-events-auto p-4 rounded-xl border shadow-xl flex items-start gap-3 backdrop-blur-md"
        :class="{
          'bg-slate-900/95 border-emerald-500/40 text-emerald-300': toast.type === 'success',
          'bg-slate-900/95 border-rose-500/40 text-rose-300': toast.type === 'error',
          'bg-slate-900/95 border-amber-500/40 text-amber-300': toast.type === 'warning',
          'bg-slate-900/95 border-cyan-500/40 text-cyan-300': toast.type === 'info',
        }"
      >
        <div class="shrink-0 mt-0.5">
          <span v-if="toast.type === 'success'" class="text-emerald-400">✓</span>
          <span v-else-if="toast.type === 'error'" class="text-rose-400">✕</span>
          <span v-else-if="toast.type === 'warning'" class="text-amber-400">⚠</span>
          <span v-else class="text-cyan-400">ℹ</span>
        </div>
        <div class="flex-1 text-sm">
          <h4 v-if="toast.title" class="font-semibold text-white mb-0.5">{{ toast.title }}</h4>
          <p class="text-slate-300 leading-relaxed">{{ toast.message }}</p>
        </div>
        <button
          @click="remove(toast.id)"
          class="shrink-0 text-slate-400 hover:text-white transition-colors text-xs"
        >
          ✕
        </button>
      </div>
    </transition-group>
  </div>
</template>

<script setup lang="ts">
import { useToast } from '@/composables/useToast';

const { toasts, remove } = useToast();
</script>
