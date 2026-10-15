<template>
  <div class="space-y-8 animate-in fade-in duration-200">
    <!-- Welcome Header Banner -->
    <div class="rounded-3xl border border-slate-800 bg-gradient-to-r from-violet-950/40 via-slate-900/80 to-cyan-950/30 p-6 sm:p-8 relative overflow-hidden backdrop-blur-xl">
      <div class="max-w-2xl space-y-3 relative z-10">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-cyan-500/10 border border-cyan-500/20 text-cyan-300 text-xs font-semibold">
          <span>⚡</span>
          <span>Ruang Kerja Vibecoding Siap Digunakan</span>
        </div>
        <h1 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">
          Halo, {{ user?.name }} 👋
        </h1>
        <p class="text-slate-300 text-sm leading-relaxed">
          Bangun, perbaiki, dan kembangkan aplikasi web modern cukup lewat percakapan AI. Kode dieksekusi dan diverifikasi pada sandbox terisolasi.
        </p>
        <div class="pt-2 flex flex-wrap gap-3">
          <button
            @click="isCreateModalOpen = true"
            class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 hover:from-violet-500 hover:to-cyan-400 text-white font-semibold text-sm shadow-lg shadow-violet-600/25 transition-all hover:scale-[1.02] flex items-center gap-2"
          >
            <span>+</span>
            <span>Buat Proyek Baru</span>
          </button>
          <router-link
            to="/chat"
            class="px-5 py-2.5 rounded-xl bg-slate-800/80 hover:bg-slate-800 text-slate-200 border border-slate-700 font-semibold text-sm transition-colors flex items-center gap-2"
          >
            <span>💬</span>
            <span>Mulai Chat AI</span>
          </router-link>
        </div>
      </div>
    </div>

    <!-- Token Quota Overview -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
      <div class="p-5 rounded-2xl bg-slate-900/80 border border-slate-800 space-y-2">
        <div class="text-xs text-slate-400 font-medium">Sisa Kuota Harian</div>
        <div class="text-2xl font-black text-cyan-400">
          {{ formatNumber(usage?.remaining_daily ?? 0) }}
        </div>
        <div class="text-[11px] text-slate-500">
          Dari total {{ formatNumber(usage?.daily_quota ?? 0) }} token/hari
        </div>
        <div class="w-full h-1.5 bg-slate-800 rounded-full overflow-hidden mt-3">
          <div
            class="h-full bg-cyan-400 rounded-full transition-all duration-500"
            :style="{ width: `${usage?.daily_percent ?? 0}%` }"
          ></div>
        </div>
      </div>

      <div class="p-5 rounded-2xl bg-slate-900/80 border border-slate-800 space-y-2">
        <div class="text-xs text-slate-400 font-medium">Sisa Kuota Bulanan</div>
        <div class="text-2xl font-black text-violet-400">
          {{ formatNumber(usage?.remaining_monthly ?? 0) }}
        </div>
        <div class="text-[11px] text-slate-500">
          Dari total {{ formatNumber(usage?.monthly_quota ?? 0) }} token/bulan
        </div>
        <div class="w-full h-1.5 bg-slate-800 rounded-full overflow-hidden mt-3">
          <div
            class="h-full bg-violet-400 rounded-full transition-all duration-500"
            :style="{ width: `${usage?.monthly_percent ?? 0}%` }"
          ></div>
        </div>
      </div>

      <div class="p-5 rounded-2xl bg-slate-900/80 border border-slate-800 space-y-2">
        <div class="text-xs text-slate-400 font-medium">Pemakaian Hari Ini</div>
        <div class="text-2xl font-black text-slate-100">
          {{ formatNumber(usage?.today_weighted ?? 0) }}
        </div>
        <div class="text-[11px] text-slate-500">
          Total pemakaian: {{ formatNumber(usage?.total_weighted ?? 0) }} weighted tokens
        </div>
        <router-link to="/usage" class="inline-block text-xs text-cyan-400 hover:underline pt-1">
          Lihat riwayat ledger →
        </router-link>
      </div>
    </div>

    <!-- Recent Projects Section -->
    <div class="space-y-4">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-xl font-bold text-white">Proyek Terbaru</h2>
          <p class="text-xs text-slate-400">Ruang kerja aktif dengan template Laravel + Vue 3</p>
        </div>
        <router-link to="/projects" class="text-xs text-cyan-400 hover:underline font-semibold">
          Lihat Semua ({{ projects.length }}) →
        </router-link>
      </div>

      <div v-if="isLoadingProjects" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        <div v-for="i in 3" :key="i" class="h-44 rounded-2xl bg-slate-900/60 border border-slate-800/80 animate-pulse"></div>
      </div>

      <div v-else-if="projects.length === 0" class="p-12 text-center rounded-2xl border border-dashed border-slate-800 bg-slate-900/30 space-y-3">
        <div class="text-3xl">📁</div>
        <h3 class="font-bold text-white text-base">Belum ada proyek</h3>
        <p class="text-xs text-slate-400 max-w-sm mx-auto">
          Mulai dengan membuat proyek baru berbasis Laravel + Vue 3 untuk mulai melakukan vibecoding.
        </p>
        <button
          @click="isCreateModalOpen = true"
          class="px-4 py-2 rounded-xl bg-violet-600 hover:bg-violet-500 text-white text-xs font-semibold"
        >
          + Buat Proyek Pertama
        </button>
      </div>

      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        <div
          v-for="project in projects"
          :key="project.id"
          class="rounded-2xl border border-slate-800 bg-slate-900/80 p-5 flex flex-col justify-between hover:border-violet-500/50 transition-all hover:shadow-xl hover:shadow-violet-950/20"
        >
          <div class="space-y-3">
            <div class="flex items-start justify-between gap-2">
              <h3 class="font-bold text-base text-white truncate">{{ project.name }}</h3>
              <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/10 border border-emerald-500/20 text-emerald-400">
                {{ project.status }}
              </span>
            </div>
            <p class="text-xs text-slate-400 line-clamp-2 leading-relaxed">
              {{ project.description || 'Proyek vibecoding mandiri pada platform XZY.' }}
            </p>
            <div class="flex items-center gap-3 text-[11px] text-slate-500">
              <span>📄 {{ project.files_count ?? 0 }} berkas</span>
              <span>•</span>
              <span class="truncate">Template: {{ project.template }}</span>
            </div>
          </div>

          <div class="pt-5 border-t border-slate-800/80 flex items-center justify-between">
            <span class="text-[10px] text-slate-500">{{ formatDate(project.updated_at) }}</span>
            <router-link
              :to="`/workspace/${project.id}`"
              class="px-3.5 py-1.5 rounded-lg bg-cyan-500/10 border border-cyan-500/30 hover:bg-cyan-500/20 text-cyan-300 text-xs font-semibold transition-colors flex items-center gap-1.5"
            >
              <span>💻</span>
              <span>Buka IDE</span>
            </router-link>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Project Modal -->
    <div
      v-if="isCreateModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm"
    >
      <div class="max-w-md w-full rounded-2xl bg-slate-900 border border-slate-800 p-6 space-y-4 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <h3 class="font-bold text-lg text-white">Buat Proyek Baru</h3>
          <button @click="isCreateModalOpen = false" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <form @submit.prevent="handleCreateProject" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Proyek</label>
            <input
              v-model="newProject.name"
              type="text"
              required
              class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 focus:border-cyan-400 text-sm text-white outline-none"
              placeholder="Aplikasi E-Commerce Keren"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Deskripsi Proyek</label>
            <textarea
              v-model="newProject.description"
              rows="3"
              class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 focus:border-cyan-400 text-sm text-white outline-none resize-none"
              placeholder="Jelaskan tujuan atau fitur utama aplikasi..."
            ></textarea>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Template Dasar</label>
            <select
              v-model="newProject.template"
              class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white outline-none"
            >
              <option value="laravel-vue3">Laravel + Vue 3 (Direkomendasikan)</option>
            </select>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button
              type="button"
              @click="isCreateModalOpen = false"
              class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-white"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-5 py-2 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 hover:from-violet-500 hover:to-cyan-400 text-white font-semibold text-xs transition-all disabled:opacity-50"
            >
              {{ isSubmitting ? 'Menginisialisasi...' : 'Inisialisasi Proyek' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuth } from '@/stores/auth';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';

const router = useRouter();
const { user } = useAuth();
const toast = useToast();

const projects = ref<any[]>([]);
const usage = ref<any>(null);
const isLoadingProjects = ref(true);
const isCreateModalOpen = ref(false);
const isSubmitting = ref(false);

const newProject = reactive({
    name: '',
    description: '',
    template: 'laravel-vue3',
});

const formatNumber = (num: number) => {
    return new Intl.NumberFormat('id-ID').format(Math.round(num || 0));
};

const formatDate = (dateStr: string) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
};

const fetchDashboardData = async () => {
    try {
        const [projRes, usageRes] = await Promise.all([
            api.get('/api/projects'),
            api.get('/api/usage/summary'),
        ]);
        projects.value = projRes.data || [];
        usage.value = usageRes;
    } catch (e: any) {
        toast.error('Gagal memuat data dasbor: ' + e.message);
    } finally {
        isLoadingProjects.value = false;
    }
};

const handleCreateProject = async () => {
    isSubmitting.value = true;
    try {
        const res = await api.post('/api/projects', newProject);
        toast.success('Proyek berhasil diinisialisasi.');
        isCreateModalOpen.value = false;
        router.push(`/workspace/${res.project.id}`);
    } catch (e: any) {
        toast.error('Gagal membuat proyek: ' + e.message);
    } finally {
        isSubmitting.value = false;
    }
};

onMounted(() => {
    fetchDashboardData();
});
</script>
