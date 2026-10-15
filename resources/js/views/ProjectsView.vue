<template>
  <div class="mx-auto max-w-7xl space-y-8">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div v-reveal>
        <span class="eyebrow">Workspace</span>
        <h1 class="page-title mt-3">Daftar proyek</h1>
        <p class="page-subtitle">Kelola workspace vibecoding dan source code aplikasimu.</p>
      </div>
      <button type="button" class="btn btn-primary shine" @click="isCreateModalOpen = true">
        <Icon name="plus" class="h-4 w-4" />
        Proyek baru
      </button>
    </header>

    <!-- Filters & search -->
    <div class="card-brutal flex flex-col items-stretch gap-3 p-3 sm:flex-row sm:items-center sm:justify-between">
      <label class="relative block w-full sm:w-96">
        <Icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-muted" />
        <input v-model="searchQuery" type="text" placeholder="Cari nama atau deskripsi proyek..." class="input pl-10" @input="handleSearch" />
      </label>

      <div class="flex gap-1 rounded-2xl border-2 border-line bg-surface-2 p-1">
        <button
          v-for="opt in statusOptions"
          :key="opt.value"
          type="button"
          class="rounded-xl px-3.5 py-1.5 text-sm font-bold transition-all"
          :class="statusFilter === opt.value ? 'border-2 border-ink bg-yellow-300 text-zinc-900 shadow-brutal-sm' : 'border-2 border-transparent text-muted hover:text-ink'"
          @click="setStatus(opt.value)"
        >
          {{ opt.label }}
        </button>
      </div>
    </div>

    <!-- Grid -->
    <div v-if="isLoading" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="i in 6" :key="i" class="h-52 animate-pulse rounded-3xl border-2 border-line bg-card"></div>
    </div>

    <div v-else-if="projects.length === 0" class="rounded-[2rem] border-2 border-dashed border-line bg-card/60 p-14 text-center">
      <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl border-2 border-ink bg-lime-300 text-zinc-900 shadow-brutal-sm animate-bounce-soft">
        <Icon :name="searchQuery || statusFilter ? 'search' : 'folder-plus'" class="h-8 w-8" />
      </span>
      <h3 class="mt-5 font-display text-xl font-bold text-ink">{{ searchQuery || statusFilter ? 'Tidak ada proyek yang cocok' : 'Belum ada proyek' }}</h3>
      <p class="mx-auto mt-2 max-w-sm text-sm text-muted">Coba ubah kata kunci atau filter, atau buat proyek baru.</p>
      <button type="button" class="btn btn-brutal mt-6" @click="isCreateModalOpen = true">
        <Icon name="plus" class="h-4 w-4" />
        Buat proyek
      </button>
    </div>

    <div v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
      <article
        v-for="(project, i) in projects"
        :key="project.id"
        v-reveal="(i % 3) * 70"
        v-tilt="3"
        class="card group flex flex-col justify-between border-2 p-6 transition-all hover:border-ink hover:shadow-brutal"
      >
        <div>
          <div class="flex items-start justify-between gap-3">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl border-2 border-zinc-950 transition-transform duration-500 group-hover:-rotate-6" :class="tileColors[i % tileColors.length]">
              <Icon name="code" class="h-5 w-5" />
            </span>
            <span class="chip" :class="project.status === 'archived' ? 'opacity-60' : ''">
              <span class="h-1.5 w-1.5 rounded-full" :class="project.status === 'archived' ? 'bg-zinc-400' : 'bg-lime-500'"></span>
              {{ project.status === 'archived' ? 'Diarsipkan' : 'Aktif' }}
            </span>
          </div>
          <h3 class="mt-4 truncate font-display text-lg font-bold text-ink">{{ project.name }}</h3>
          <p class="mt-1 line-clamp-2 text-sm text-muted">{{ project.description || 'Tidak ada deskripsi' }}</p>

          <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-muted">
            <span class="inline-flex items-center gap-1.5"><Icon name="file-code" class="h-3.5 w-3.5" /> {{ project.files_count ?? 0 }} berkas</span>
            <span class="inline-flex min-w-0 items-center gap-1.5">
              <Icon name="github" class="h-3.5 w-3.5 shrink-0" />
              <span class="truncate">{{ project.github_repo || 'Belum terhubung' }}</span>
            </span>
          </div>
        </div>

        <div class="mt-5 flex items-center justify-between border-t border-line pt-4">
          <span class="inline-flex items-center gap-1.5 text-xs text-muted"><Icon name="clock" class="h-3.5 w-3.5" /> {{ formatDate(project.updated_at) }}</span>
          <router-link :to="`/workspace/${project.id}`" class="btn btn-secondary btn-sm">
            Buka
            <Icon name="arrow-up-right" class="h-4 w-4 transition-transform group-hover:rotate-45" />
          </router-link>
        </div>
      </article>
    </div>

    <!-- Pagination -->
    <nav v-if="totalPages > 1" class="flex items-center justify-center gap-3 pt-2">
      <button type="button" class="btn btn-secondary btn-sm" :disabled="currentPage <= 1" @click="changePage(currentPage - 1)">
        <Icon name="chevron-left" class="h-4 w-4" />
        Sebelumnya
      </button>
      <span class="rounded-xl border-2 border-ink bg-card px-3 py-1.5 font-display text-sm font-bold text-ink shadow-brutal-sm tabular-nums">{{ currentPage }} / {{ totalPages }}</span>
      <button type="button" class="btn btn-secondary btn-sm" :disabled="currentPage >= totalPages" @click="changePage(currentPage + 1)">
        Berikutnya
        <Icon name="chevron-right" class="h-4 w-4" />
      </button>
    </nav>

    <!-- Create project modal -->
    <transition name="page">
      <div v-if="isCreateModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/60 p-4 backdrop-blur-sm" @click.self="isCreateModalOpen = false">
        <div class="w-full max-w-md rounded-[2rem] border-2 border-ink bg-card p-6 shadow-brutal-lg sm:p-8">
          <div class="flex items-center justify-between">
            <h3 class="font-display text-2xl font-bold text-ink">Proyek baru</h3>
            <button type="button" class="btn-ghost grid h-9 w-9 place-items-center rounded-xl" title="Tutup" @click="isCreateModalOpen = false">
              <Icon name="x" class="h-5 w-5" />
            </button>
          </div>

          <form class="mt-6 space-y-5" @submit.prevent="handleCreateProject">
            <div>
              <label class="label" for="proj-name">Nama proyek</label>
              <input id="proj-name" v-model="newProject.name" type="text" required class="input" placeholder="Aplikasi E-Commerce" />
            </div>
            <div>
              <label class="label" for="proj-desc">Deskripsi</label>
              <textarea id="proj-desc" v-model="newProject.description" rows="3" class="input resize-none" placeholder="Deskripsi singkat aplikasi yang ingin kamu kembangkan"></textarea>
            </div>
            <div class="flex justify-end gap-2 pt-2">
              <button type="button" class="btn btn-ghost" @click="isCreateModalOpen = false">Batal</button>
              <button type="submit" :disabled="isSubmitting" class="btn btn-primary shine">
                <Icon v-if="isSubmitting" name="loader" class="h-4 w-4 animate-spin" />
                {{ isSubmitting ? 'Menyiapkan...' : 'Buat proyek' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </transition>
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

const statusOptions = [
    { value: '', label: 'Semua' },
    { value: 'active', label: 'Aktif' },
    { value: 'archived', label: 'Arsip' },
];
const tileColors = ['bg-violet-500 text-white', 'bg-lime-300 text-zinc-900', 'bg-orange-400 text-zinc-900', 'bg-fuchsia-500 text-white', 'bg-yellow-300 text-zinc-900', 'bg-cyan-300 text-zinc-900'];

// Changing the filter must restart from page 1, otherwise a high page number can come back empty
const setStatus = (value: string) => {
    statusFilter.value = value;
    currentPage.value = 1;
    fetchProjects();
};

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
