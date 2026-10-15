<template>
  <div class="space-y-6 animate-in fade-in duration-200">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="page-title">Manajemen Pengguna</h1>
        <p class="page-subtitle">Atur hak akses peran (role), status akun, dan kuota token pengguna</p>
      </div>

      <button @click="fetchUsers" class="btn btn-secondary btn-sm">
        <Icon name="refresh" class="h-4 w-4" /> Refresh Pengguna
      </button>
    </div>

    <!-- Filters -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 p-4 rounded-3xl bg-card border-2 border-line">
      <input
        v-model="search"
        @input="handleSearch"
        type="text"
        placeholder="Cari nama atau email pengguna..."
        class="input sm:w-80 text-xs"
      />

      <div class="flex items-center gap-2 w-full sm:w-auto">
        <select
          v-model="roleFilter"
          @change="fetchUsers"
          class="px-3 py-2 rounded-xl bg-surface border border-line text-xs text-ink/80 outline-none"
        >
          <option value="">Semua Peran</option>
          <option value="admin">Administrator</option>
          <option value="user">Pengguna</option>
        </select>
        <select
          v-model="statusFilter"
          @change="fetchUsers"
          class="px-3 py-2 rounded-xl bg-surface border border-line text-xs text-ink/80 outline-none"
        >
          <option value="">Semua Status</option>
          <option value="active">Aktif</option>
          <option value="suspended">Ditangguhkan</option>
        </select>
      </div>
    </div>

    <!-- Users Table -->
    <div class="rounded-3xl border-2 border-ink bg-card overflow-hidden shadow-brutal">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-ink/80">
          <thead class="bg-surface/60 border-b border-line text-[11px] text-muted uppercase font-mono">
            <tr>
              <th class="p-3.5">Nama & Email</th>
              <th class="p-3.5">Peran (Role)</th>
              <th class="p-3.5">Status</th>
              <th class="p-3.5">Kuota Harian</th>
              <th class="p-3.5">Kuota Bulanan</th>
              <th class="p-3.5">Batas Konkurensi</th>
              <th class="p-3.5 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line">
            <tr v-for="u in users" :key="u.id" class="hover:bg-surface-2/40 transition-colors">
              <td class="p-3.5">
                <p class="font-bold text-ink">{{ u.name }}</p>
                <p class="text-[11px] text-muted font-mono">{{ u.email }}</p>
              </td>
              <td class="p-3.5">
                <span
                  class="px-2 py-0.5 rounded text-[10px] font-bold"
                  :class="u.role === 'admin' ? 'bg-violet-600/20 text-violet-600 dark:text-violet-300 border border-violet-500/30' : 'bg-surface-2 text-muted'"
                >
                  {{ u.role === 'admin' ? 'Administrator' : 'Pengguna' }}
                </span>
              </td>
              <td class="p-3.5">
                <span
                  class="px-2 py-0.5 rounded text-[10px] font-bold"
                  :class="u.status === 'active' ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'bg-rose-500/20 text-rose-600 dark:text-rose-400'"
                >
                  {{ u.status === 'active' ? 'Aktif' : 'Ditangguhkan' }}
                </span>
              </td>
              <td class="p-3.5 font-mono text-cyan-600 dark:text-cyan-400 font-semibold">{{ formatNumber(u.daily_token_quota) }}</td>
              <td class="p-3.5 font-mono text-violet-600 dark:text-violet-400 font-semibold">{{ formatNumber(u.monthly_token_quota) }}</td>
              <td class="p-3.5 font-mono text-ink/80">{{ u.concurrency_limit }} stream</td>
              <td class="p-3.5 text-right">
                <button
                  @click="openEditModal(u)"
                  class="btn btn-secondary btn-sm"
                >
                  Edit Kuota / Status
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Edit User Modal -->
    <div
      v-if="isEditModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/60 backdrop-blur-sm"
    >
      <div class="max-w-md w-full rounded-[2rem] bg-card border-2 border-ink p-6 space-y-4 shadow-brutal-lg">
        <div class="flex items-center justify-between border-b border-line pb-3">
          <h3 class="font-bold text-base text-ink">Edit Pengguna: {{ editingUser?.name }}</h3>
          <button @click="isEditModalOpen = false" class="btn-ghost grid h-9 w-9 place-items-center rounded-xl" title="Tutup"><Icon name="x" class="h-5 w-5" /></button>
        </div>

        <form @submit.prevent="saveUserChanges" class="space-y-4 text-xs">
          <div>
            <label class="label">Peran (Role)</label>
            <select
              v-model="editForm.role"
              class="input"
            >
              <option value="user">Pengguna Biasa</option>
              <option value="admin">Administrator</option>
            </select>
          </div>

          <div>
            <label class="label">Status Akun</label>
            <select
              v-model="editForm.status"
              class="input"
            >
              <option value="active">Aktif</option>
              <option value="suspended">Ditangguhkan (Suspended)</option>
            </select>
          </div>

          <div>
            <label class="label">Kuota Harian (Daily Tokens)</label>
            <input
              v-model.number="editForm.daily_token_quota"
              type="number"
              min="0"
              class="input font-mono"
            />
          </div>

          <div>
            <label class="label">Kuota Bulanan (Monthly Tokens)</label>
            <input
              v-model.number="editForm.monthly_token_quota"
              type="number"
              min="0"
              class="input font-mono"
            />
          </div>

          <div>
            <label class="label">Batas Permintaan Simultan (Concurrency)</label>
            <input
              v-model.number="editForm.concurrency_limit"
              type="number"
              min="1"
              max="20"
              class="input font-mono"
            />
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="isEditModalOpen = false" class="px-3 py-1.5 text-muted">Batal</button>
            <button
              type="submit"
              :disabled="isSaving"
              class="btn btn-primary shine btn-sm disabled:opacity-50"
            >
              {{ isSaving ? 'Menyimpan...' : 'Simpan Perubahan' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';

const toast = useToast();

const users = ref<any[]>([]);
const search = ref('');
const roleFilter = ref('');
const statusFilter = ref('');

const isEditModalOpen = ref(false);
const editingUser = ref<any>(null);
const isSaving = ref(false);

const editForm = reactive({
    role: 'user',
    status: 'active',
    daily_token_quota: 100000,
    monthly_token_quota: 2000000,
    concurrency_limit: 2,
});

let searchDebounce: any = null;

const formatNumber = (num: number) => {
    return new Intl.NumberFormat('id-ID').format(Math.round(num || 0));
};

const fetchUsers = async () => {
    try {
        const queryParams = new URLSearchParams({
            search: search.value,
            role: roleFilter.value,
            status: statusFilter.value,
        });

        const res = await api.get(`/api/admin/users?${queryParams.toString()}`);
        users.value = res.data || [];
    } catch (e: any) {
        toast.error('Gagal memuat pengguna: ' + e.message);
    }
};

const handleSearch = () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => {
        fetchUsers();
    }, 300);
};

const openEditModal = (u: any) => {
    editingUser.value = u;
    editForm.role = u.role;
    editForm.status = u.status;
    editForm.daily_token_quota = u.daily_token_quota;
    editForm.monthly_token_quota = u.monthly_token_quota;
    editForm.concurrency_limit = u.concurrency_limit;
    isEditModalOpen.value = true;
};

const saveUserChanges = async () => {
    if (!editingUser.value) return;
    isSaving.value = true;

    try {
        await api.put(`/api/admin/users/${editingUser.value.id}`, editForm);
        toast.success(`Pengguna ${editingUser.value.name} berhasil diperbarui.`);
        isEditModalOpen.value = false;
        fetchUsers();
    } catch (e: any) {
        toast.error('Gagal memperbarui pengguna: ' + e.message);
    } finally {
        isSaving.value = false;
    }
};

onMounted(() => {
    fetchUsers();
});
</script>
