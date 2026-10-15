<template>
  <div class="min-h-screen bg-surface text-ink flex flex-col font-sans">
    <Toast />

    <!-- Guest layout (Landing, Login, Register) -->
    <template v-if="isGuestRoute">
      <router-view v-slot="{ Component }">
        <transition name="page" mode="out-in">
          <component :is="Component" />
        </transition>
      </router-view>
    </template>

    <!-- Authenticated App Layout -->
    <template v-else>
      <!-- Soft animated glow + dotted texture behind the app (decorative) -->
      <div class="pointer-events-none fixed inset-0 z-0" aria-hidden="true">
        <div class="aurora opacity-50"><span></span><span></span><span></span></div>
        <div class="dot-bg absolute inset-0 opacity-40"></div>
      </div>
      <div
        class="relative z-10 flex flex-1 flex-col transition-[padding] duration-300"
        :class="isSidebarCollapsed ? 'md:pl-20' : 'md:pl-64'"
      >
        <Sidebar :is-open="isSidebarOpen" @close-sidebar="isSidebarOpen = false" />
        <Navbar @toggle-sidebar="isSidebarOpen = !isSidebarOpen" />
        <main class="flex-1" :class="isFullBleed ? 'overflow-hidden' : 'overflow-y-auto p-4 sm:p-6 lg:p-8'">
          <router-view v-slot="{ Component }">
            <transition name="page" mode="out-in">
              <component :is="Component" />
            </transition>
          </router-view>
        </main>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRoute } from 'vue-router';
import Navbar from '@/components/Navbar.vue';
import Sidebar from '@/components/Sidebar.vue';
import Toast from '@/components/Toast.vue';
import { useLayout } from '@/composables/useLayout';

const route = useRoute();
const isSidebarOpen = ref(false);
const { isSidebarCollapsed } = useLayout();

// Chat and workspace fill the viewport (h-[calc(100vh-4rem)]), so they get no page padding
const isFullBleed = computed(() => ['chat', 'workspace'].includes((route.name as string) || ''));

const isGuestRoute = computed(() => {
    return ['landing', 'login', 'register'].includes((route.name as string) || '');
});
</script>
