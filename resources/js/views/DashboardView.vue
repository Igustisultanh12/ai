<template>
  <div class="mx-auto max-w-7xl space-y-8">
    <!-- Welcome banner -->
    <section class="relative overflow-hidden rounded-[2rem] border-2 border-ink bg-brand-gradient p-6 text-white shadow-brutal animate-gradient sm:p-10">
      <Icon name="star" class="absolute -top-12 -right-12 h-56 w-56 animate-spin-slow opacity-15" />
      <div class="dot-bg absolute inset-0 opacity-15" aria-hidden="true"></div>
      <div class="relative max-w-2xl">
        <span class="inline-flex items-center gap-2 rounded-full border-2 border-ink bg-yellow-300 px-3 py-1 text-xs font-extrabold tracking-wider text-zinc-900 uppercase shadow-brutal-sm">
          <Icon name="zap" class="h-3.5 w-3.5" />
          Workspace siap
        </span>
        <h1 class="mt-4 font-display text-3xl font-bold tracking-tight sm:text-5xl">Halo, {{ firstName }}. Mau bikin apa hari ini?</h1>
        <p class="mt-3 max-w-xl text-white/85">
          Bangun, perbaiki, dan kembangkan aplikasi cukup lewat percakapan. Agent membaca kode, menulis perubahan, lalu push ke GitHub.
        </p>
        <div class="mt-6 flex flex-wrap gap-3">
          <button type="button" class="btn btn-lg border-2 border-ink bg-card text-ink shadow-brutal-sm hover:-translate-y-0.5 hover:shadow-brutal" @click="isCreateModalOpen = true">
            <Icon name="folder-plus" class="h-5 w-5" />
            Proyek baru
          </button>
          <router-link to="/chat" class="btn btn-lg border-2 border-ink bg-lime-300 text-zinc-900 shadow-brutal-sm hover:-translate-y-0.5 hover:shadow-brutal">
            <Icon name="message-square" class="h-5 w-5" />
            Mulai chat AI
          </router-link>
        </div>
      </div>
    </section>

    <!-- Quota cards -->
    <section class="grid gap-5 sm:grid-cols-3">
      <div v-for="(card, i) in quotaCards" :key="card.label" v-reveal="i * 80" class="card-brutal group p-6 hover:-translate-y-1 hover:shadow-brutal-lg">
        <div class="flex items-center justify-between">
          <p class="text-sm font-semibold text-muted">{{ card.label }}</p>
          <span class="grid h-10 w-10 place-items-center rounded-xl border-2 border-zinc-950 transition-transform duration-500 group-hover:rotate-12" :class="card.tile">
            <Icon :name="card.icon" class="h-5 w-5" />
          </span>
        </div>
        <p class="mt-3 font-display text-3xl font-bold text-ink tabular-nums">{{ formatNumber(card.value) }}</p>
        <p class="mt-1 text-xs text-muted">{{ card.hint }}</p>
        <div v-if="card.percent !== null" class="mt-4 h-2 overflow-hidden rounded-full bg-surface-2">
          <div class="h-full rounded-full transition-all duration-700" :class="card.bar" :style="{ width: `${card.percent}%` }"></div>
        </div>
        <router-link v-else to="/usage" class="mt-4 inline-flex items-center gap-1 text-sm font-bold text-violet-600 hover:gap-2 dark:text-violet-300">
          Lihat riwayat
          <Icon name="arrow-right" class="h-4 w-4 transition-all" />
        </router-link>
      </div>
    </section>

    <!-- Recent projects -->
    <section class="space-y-5">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 class="page-title">Proyek terbaru</h2>
          <p class="page-subtitle">Workspace aktif dengan template Laravel + Vue 3.</p>
        </div>
        <router-link to="/projects" class="btn btn-secondary btn-sm">
          Semua proyek ({{ totalProjects }})
          <Icon name="arrow-right" class="h-4 w-4" />
        </router-link>
      </div>

      <div v-if="isLoadingProjects" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="i in 3" :key="i" class="h-48 animate-pulse rounded-3xl border-2 border-line bg-card"></div>
      </div>

      <div v-else-if="projects.length === 0" class="rounded-[2rem] border-2 border-dashed border-line bg-card/60 p-12 text-center">
        <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl border-2 border-ink bg-yellow-300 text-zinc-900 shadow-brutal-sm animate-bounce-soft">
          <Icon name="folder-plus" class="h-8 w-8" />
        </span>
        <h3 class="mt-5 font-display text-xl font-bold text-ink">Belum ada proyek</h3>
        <p class="mx-auto mt-2 max-w-sm text-sm text-muted">Buat proyek pertamamu, lalu minta AI mengerjakannya.</p>
        <button type="button" class="btn btn-primary shine mt-6" @click="isCreateModalOpen = true">
          <Icon name="plus" class="h-4 w-4" />
          Buat proyek pertama
        </button>
      </div>

      <div v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <router-link
          v-for="(project, i) in projects.slice(0, 6)"
          :key="project.id"
          v-reveal="i * 70"
          v-tilt="3"
          :to="`/workspace/${project.id}`"
          class="card group flex flex-col justify-between border-2 p-6 transition-all hover:border-ink hover:shadow-brutal"
        >
          <div>
            <div class="flex items-start justify-between gap-3">
              <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl border-2 border-zinc-950 transition-transform duration-500 group-hover:-rotate-6" :class="tileColors[i % tileColors.length]">
                <Icon name="code" class="h-5 w-5" />
              </span>
              <span class="chip">{{ project.status }}</span>
            </div>
            <h3 class="mt-4 truncate font-display text-lg font-bold text-ink">{{ project.name }}</h3>
            <p class="mt-1 line-clamp-2 text-sm text-muted">{{ project.description || 'Proyek vibecoding di XZY.' }}</p>
          </div>
          <div class="mt-5 flex items-center justify-between border-t border-line pt-4 text-xs text-muted">
            <span class="inline-flex items-center gap-1.5"><Icon name="file-code" class="h-4 w-4" /> {{ project.files_count ?? 0 }} berkas</span>
            <span class="inline-flex items-center gap-1 font-bold text-violet-600 dark:text-violet-300">
              Buka
              <Icon name="arrow-up-right" class="h-4 w-4 transition-transform group-hover:rotate-45" />
            </span>
          </div>
        </router-link>
      </div>
    </section>

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
              <label class="label" for="dash-project-name">Nama proyek</label>
              <input id="dash-project-name" v-model="newProject.name" type="text" required class="input" placeholder="Misal: Toko Online Keren" />
            </div>
            <div>
              <label class="label" for="dash-project-desc">Deskripsi</label>
              <textarea id="dash-project-desc" v-model="newProject.description" rows="3" class="input resize-none" placeholder="Ceritakan tujuan atau fitur utama aplikasinya"></textarea>
            </div>
            <div>
              <label class="label" for="dash-project-template">Template</label>
              <select id="dash-project-template" v-model="newProject.template" class="input">
                <option value="laravel-vue3">Laravel + Vue 3 (direkomendasikan)</option>
              </select>
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
import { ref, reactive, computed, onMounted } from 'vue';
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
const totalProjects = ref(0);
const firstName = computed(() => (user.value?.name || '').split(' ')[0]);
const tileColors = ['bg-violet-500 text-white', 'bg-lime-300 text-zinc-900', 'bg-orange-400 text-zinc-900', 'bg-fuchsia-500 text-white', 'bg-yellow-300 text-zinc-900', 'bg-cyan-300 text-zinc-900'];

// Bars show what is LEFT, matching the "sisa" labels
const quotaCards = computed(() => [
    {
        label: 'Sisa kuota harian',
        icon: 'zap',
        tile: 'bg-yellow-300 text-zinc-900',
        value: usage.value?.remaining_daily ?? 0,
        hint: `dari ${formatNumber(usage.value?.daily_quota ?? 0)} token per hari`,
        percent: Math.max(0, 100 - (usage.value?.daily_percent ?? 0)),
        bar: (usage.value?.daily_percent ?? 0) > 85 ? 'bg-rose-500' : 'bg-brand-gradient',
    },
    {
        label: 'Sisa kuota bulanan',
        icon: 'coins',
        tile: 'bg-violet-500 text-white',
        value: usage.value?.remaining_monthly ?? 0,
        hint: `dari ${formatNumber(usage.value?.monthly_quota ?? 0)} token per bulan`,
        percent: Math.max(0, 100 - (usage.value?.monthly_percent ?? 0)),
        bar: (usage.value?.monthly_percent ?? 0) > 85 ? 'bg-rose-500' : 'bg-violet-500',
    },
    {
        label: 'Terpakai hari ini',
        icon: 'activity',
        tile: 'bg-lime-300 text-zinc-900',
        value: usage.value?.today_weighted ?? 0,
        hint: `total ${formatNumber(usage.value?.total_weighted ?? 0)} weighted token`,
        percent: null,
        bar: '',
    },
]);
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
        totalProjects.value = projRes.total ?? projects.value.length;
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
