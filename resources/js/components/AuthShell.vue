<template>
  <div class="relative grid min-h-screen bg-surface text-ink lg:grid-cols-2">
    <!-- Brand panel -->
    <aside class="relative hidden overflow-hidden border-r-2 border-ink bg-brand-gradient p-12 text-white animate-gradient lg:flex lg:flex-col lg:justify-between">
      <Icon name="star" class="absolute -top-16 -right-16 h-72 w-72 animate-spin-slow opacity-10" />
      <div class="dot-bg absolute inset-0 opacity-20" aria-hidden="true"></div>

      <router-link to="/" class="group relative flex items-center gap-3">
        <span class="grid h-11 w-11 place-items-center rounded-xl border-2 border-ink bg-card font-display text-xl font-bold text-ink shadow-brutal-sm transition-transform duration-300 group-hover:-rotate-12">X</span>
        <span class="font-display text-2xl font-bold">XZY</span>
      </router-link>

      <div class="relative">
        <h2 v-reveal class="font-display text-5xl leading-[1.05] font-bold tracking-tight">
          Ngoding bareng AI,<br />langsung jadi.
        </h2>
        <ul class="mt-8 space-y-4">
          <li v-for="(point, i) in points" :key="point.text" v-reveal:left="100 + i * 90" class="flex items-center gap-3 text-lg font-semibold">
            <span class="grid h-10 w-10 place-items-center rounded-xl border-2 border-ink bg-card text-ink shadow-brutal-sm">
              <Icon :name="point.icon" class="h-5 w-5" />
            </span>
            {{ point.text }}
          </li>
        </ul>
      </div>

      <div class="relative flex flex-wrap gap-3">
        <span class="animate-float rounded-2xl border-2 border-ink bg-yellow-300 px-3 py-2 font-display text-sm font-bold text-zinc-900 shadow-brutal-sm">claude-opus-5-5</span>
        <span class="animate-float-slow rounded-2xl border-2 border-ink bg-lime-300 px-3 py-2 font-display text-sm font-bold text-zinc-900 shadow-brutal-sm">kimi-k3</span>
        <span class="animate-float rounded-2xl border-2 border-ink bg-card px-3 py-2 font-display text-sm font-bold text-ink shadow-brutal-sm">glm-5.1</span>
      </div>
    </aside>

    <!-- Form panel -->
    <main class="relative flex items-center justify-center overflow-hidden px-4 py-12 sm:px-8">
      <div class="aurora lg:hidden" aria-hidden="true"><span></span><span></span><span></span></div>

      <div class="absolute top-4 right-4 flex items-center gap-2 sm:top-6 sm:right-6">
        <router-link to="/" class="btn btn-ghost btn-sm">
          <Icon name="arrow-left" class="h-4 w-4" />
          Beranda
        </router-link>
        <button
          type="button"
          class="grid h-9 w-9 place-items-center rounded-xl border-2 border-line bg-card transition-all hover:-translate-y-0.5 hover:border-violet-400"
          :title="theme === 'dark' ? 'Tema terang' : 'Tema gelap'"
          @click="toggleTheme"
        >
          <Icon :name="theme === 'dark' ? 'sun' : 'moon'" class="h-4 w-4" :class="theme === 'dark' ? 'text-amber-400' : 'text-violet-600'" />
        </button>
      </div>

      <div class="relative z-10 w-full max-w-md">
        <router-link to="/" class="mb-8 flex items-center gap-2.5 lg:hidden">
          <span class="grid h-10 w-10 place-items-center rounded-xl border-2 border-ink bg-brand-gradient font-display text-xl font-bold text-white shadow-brutal-sm">X</span>
          <span class="font-display text-2xl font-bold">XZY</span>
        </router-link>

        <div v-reveal>
          <span class="eyebrow">{{ eyebrow }}</span>
          <h1 class="mt-4 font-display text-4xl font-bold tracking-tight">{{ title }}</h1>
          <p class="mt-2 text-muted">{{ subtitle }}</p>
        </div>

        <div v-reveal:zoom="100" class="card-brutal mt-8 p-6 sm:p-8">
          <slot />
        </div>

        <p class="mt-6 text-center text-sm text-muted">
          <slot name="footer" />
        </p>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { useTheme } from '@/composables/useTheme';

defineProps<{
  eyebrow: string;
  title: string;
  subtitle: string;
}>();

const { theme, toggleTheme } = useTheme();

const points = [
    { icon: 'bot', text: 'Agent yang baca repo dan push sendiri' },
    { icon: 'cpu', text: 'Bebas pilih model TokenomixAI' },
    { icon: 'shield-check', text: 'Aman: tanpa force push, ada 2FA' },
];
</script>
