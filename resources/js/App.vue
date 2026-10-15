<template>
  <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col font-sans">
    <Toast />

    <!-- Guest layout (Landing, Login, Register) -->
    <template v-if="isGuestRoute">
      <router-view />
    </template>

    <!-- Authenticated App Layout -->
    <template v-else>
      <div class="flex-1 flex flex-col md:pl-64 transition-all">
        <Sidebar :is-open="isSidebarOpen" @close-sidebar="isSidebarOpen = false" />
        <Navbar @toggle-sidebar="isSidebarOpen = !isSidebarOpen" />
        <main class="flex-1 p-4 sm:p-6 lg:p-8 overflow-y-auto">
          <router-view />
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

const route = useRoute();
const isSidebarOpen = ref(false);

const isGuestRoute = computed(() => {
    return ['landing', 'login', 'register'].includes((route.name as string) || '');
});
</script>
