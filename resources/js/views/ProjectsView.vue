<template>
  <div class="space-y-6 animate-in fade-in duration-200">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black text-white">Daftar Proyek</h1>
        <p class="text-xs text-slate-400">Kelola workspace vibecoding dan source code aplikasi Anda</p>
      </div>

      <button
        @click="isCreateModalOpen = true"
        class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 hover:from-violet-500 hover:to-cyan-400 text-white font-semibold text-xs shadow-lg transition-all flex items-center justify-center gap-2"
      >
        <span>+</span>
        <span>Buat Proyek Baru</span>
      </button>
    </div>

    <!-- Filters & Search -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 p-4 rounded-2xl bg-slate-900 border border-slate-800">
      <div class="relative w-full sm:w-80">
        <input
          v-model="searchQuery"
          @input="handleSearch"
          type="text"
          placeholder="Cari nama proyek..."
          class="w-full pl-9 pr-3.5 py-2 rounded-xl bg-slate-950 border border-slate-800 focus:border-cyan-400 text-xs text-white outline-none"
        />
        <span class="absolute left-3 top-2.5 text-slate-500 text-xs">🔍</span>
      </div>

      <div class="flex items-center gap-2 w-full sm:w-auto">
        <select
          v-model="statusFilter"
          @change="fetchProjects"
          class="w-full sm:w-auto px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-300 outline-none"
        >
          <option value="">Semua Status</option>
          <option value="active">Aktif</option>
          <option value="archived">Diarsipkan</option>
        </select>
      </div>
    </div>

    <!-- Projects Grid -->
    <div v-if="isLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
      <div v-for="i in 6" :key="i" class="h-48 rounded-2xl bg-slate-900/60 border border-slate-800/80 animate-pulse"></div>
    </div>

    <div v-else-if="projects.length === 0" class="p-16 text-center rounded-2xl border border-dashed border-slate-800 bg-slate-900/30 space-y-3">
      <div class="text-4xl">📁</div>
      <h3 class="font-bold text-white text-base">Tidak ada proyek yang cocok</h3>
      <p class="text-xs text-slate-400">Coba ubah kata kunci pencarian atau buat proyek baru.</p>
    </div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
      <div
        v-for="project in projects"
        :key="project.id"
        class="rounded-2xl border border-slate-800 bg-slate-900/80 p-5 flex flex-col justify-between hover:border-violet-500/50 transition-all hover:shadow-xl"
      >
        <div class="space-y-3">
          <div class="flex items-start justify-between gap-2">
            <h3 class="font-bold text-base text-white truncate">{{ project.name }}</h3>
            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/10 border border-emerald-500/20 text-emerald-400">
              {{ project.status }}
            </span>
          </div>

          <p class="text-xs text-slate-400 line-clamp-2 leading-relaxed">
            {{ project.description || 'Tidak ada deskripsi' }}
          </p>

          <div class="flex flex-wrap items-center gap-3 text-[11px] text-slate-500">
            <span>📄 {{ project.files_count ?? 0 }} berkas</span>
            <span>•</span>
            <span class="truncate">Git: {{ project.github_repo || 'Belum terhubung' }}</span>
          </div>
        </div>

        <div class="pt-5 border-t border-slate-800/80 flex items-center justify-between">
          <span class="text-[10px] text-slate-500">{{ formatDate(project.updated_at) }}</span>
          <router-link
            :to="`/workspace/${project.id}`"
            class="px-3.5 py-1.5 rounded-lg bg-cyan-500/10 border border-cyan-500/30 hover:bg-cyan-500/20 text-cyan-300 text-xs font-semibold transition-colors flex items-center gap-1.5"
          >
            <span>💻</span>
            <span>Buka Workspace</span>
          </router-link>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="totalPages > 1" class="flex justify-center items-center gap-2 pt-4">
      <button
        @click="changePage(currentPage - 1)"
        :disabled="currentPage <= 1"
        class="px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-800 text-xs text-slate-300 hover:text-white disabled:opacity-40"
      >
        ← Sebelumnya
      </button>
      <span class="text-xs text-slate-500">
        Halaman {{ currentPage }} dari {{ totalPages }}
      </span>
      <button
        @click="changePage(currentPage + 1)"
        :disabled="currentPage >= totalPages"
        class="px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-800 text-xs text-slate-300 hover:text-white disabled:opacity-40"
      >
        Berikutnya →
      </button>
    </div>

    <!-- Modal Create Project -->
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
              placeholder="Aplikasi E-Commerce"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Deskripsi Proyek</label>
            <textarea
              v-model="newProject.description"
              rows="3"
              class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 focus:border-cyan-400 text-sm text-white outline-none resize-none"
              placeholder="Deskripsi singkat aplikasi yang ingin Anda kembangkan..."
            ></textarea>
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
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';

const router = useRouter();
const toast = useToast();

const projects = ref<any[]>([]);
const isLoading = ref(true);
const searchQuery = ref('');
const statusFilter = ref('');
const currentPage = ref(1);
const totalPages = ref(1);

const isCreateModalOpen = ref(false);
const isSubmitting = ref(false);

const newProject = reactive({
    name: '',
    description: '',
    template: 'laravel-vue3',
});

let searchDebounce: any = null;

const formatDate = (dateStr: string) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
};

const fetchProjects = async () => {
    isLoading.value = true;
    try {
        const queryParams = new URLSearchParams({
            page: currentPage.value.toString(),
            search: searchQuery.value,
            status: statusFilter.value,
        });

        const res = await api.get(`/api/projects?${queryParams.toString()}`);
        projects.value = res.data || [];
        currentPage.value = res.current_page || 1;
        totalPages.value = res.last_page || 1;
    } catch (e: any) {
        toast.error('Gagal memuat daftar proyek: ' + e.message);
    } finally {
        isLoading.value = false;
    }
};

const handleSearch = () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => {
        currentPage.value = 1;
        fetchProjects();
    }, 300);
};

const changePage = (page: number) => {
    if (page < 1 || page > totalPages.value) return;
    currentPage.value = page;
    fetchProjects();
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
    fetchProjects();
});
</script>
