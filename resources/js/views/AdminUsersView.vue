<template>
  <div class="space-y-6 animate-in fade-in duration-200">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black text-white">Manajemen Pengguna</h1>
        <p class="text-xs text-slate-400">Atur hak akses peran (role), status akun, dan kuota token pengguna</p>
      </div>

      <button @click="fetchUsers" class="px-3.5 py-2 rounded-xl bg-slate-900 border border-slate-800 text-xs text-slate-300 hover:text-white">
        ↻ Refresh Pengguna
      </button>
    </div>

    <!-- Filters -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 p-4 rounded-2xl bg-slate-900 border border-slate-800">
      <input
        v-model="search"
        @input="handleSearch"
        type="text"
        placeholder="Cari nama atau email pengguna..."
        class="w-full sm:w-80 px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-xs text-white outline-none focus:border-cyan-400"
      />

      <div class="flex items-center gap-2 w-full sm:w-auto">
        <select
          v-model="roleFilter"
          @change="fetchUsers"
          class="px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-300 outline-none"
        >
          <option value="">Semua Peran</option>
          <option value="admin">Administrator</option>
          <option value="user">Pengguna</option>
        </select>
        <select
          v-model="statusFilter"
          @change="fetchUsers"
          class="px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-300 outline-none"
        >
          <option value="">Semua Status</option>
          <option value="active">Aktif</option>
          <option value="suspended">Ditangguhkan</option>
        </select>
      </div>
    </div>

    <!-- Users Table -->
    <div class="rounded-2xl border border-slate-800 bg-slate-900 overflow-hidden shadow-xl">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-300">
          <thead class="bg-slate-950/60 border-b border-slate-800 text-[11px] text-slate-400 uppercase font-mono">
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
          <tbody class="divide-y divide-slate-800/80">
            <tr v-for="u in users" :key="u.id" class="hover:bg-slate-800/40 transition-colors">
              <td class="p-3.5">
                <p class="font-bold text-white">{{ u.name }}</p>
                <p class="text-[11px] text-slate-400 font-mono">{{ u.email }}</p>
              </td>
              <td class="p-3.5">
                <span
                  class="px-2 py-0.5 rounded text-[10px] font-bold"
                  :class="u.role === 'admin' ? 'bg-violet-600/20 text-violet-300 border border-violet-500/30' : 'bg-slate-800 text-slate-400'"
                >
                  {{ u.role === 'admin' ? 'Administrator' : 'Pengguna' }}
                </span>
              </td>
              <td class="p-3.5">
                <span
                  class="px-2 py-0.5 rounded text-[10px] font-bold"
                  :class="u.status === 'active' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-rose-500/20 text-rose-400'"
                >
                  {{ u.status === 'active' ? 'Aktif' : 'Ditangguhkan' }}
                </span>
              </td>
              <td class="p-3.5 font-mono text-cyan-400 font-semibold">{{ formatNumber(u.daily_token_quota) }}</td>
              <td class="p-3.5 font-mono text-violet-400 font-semibold">{{ formatNumber(u.monthly_token_quota) }}</td>
              <td class="p-3.5 font-mono text-slate-300">{{ u.concurrency_limit }} stream</td>
              <td class="p-3.5 text-right">
                <button
                  @click="openEditModal(u)"
                  class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-cyan-300 font-semibold text-xs"
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
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm"
    >
      <div class="max-w-md w-full rounded-2xl bg-slate-900 border border-slate-800 p-6 space-y-4 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <h3 class="font-bold text-base text-white">Edit Pengguna: {{ editingUser?.name }}</h3>
          <button @click="isEditModalOpen = false" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <form @submit.prevent="saveUserChanges" class="space-y-4 text-xs">
          <div>
            <label class="block font-semibold text-slate-300 mb-1">Peran (Role)</label>
            <select
              v-model="editForm.role"
              class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white outline-none"
            >
              <option value="user">Pengguna Biasa</option>
              <option value="admin">Administrator</option>
            </select>
          </div>

          <div>
            <label class="block font-semibold text-slate-300 mb-1">Status Akun</label>
            <select
              v-model="editForm.status"
              class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white outline-none"
            >
              <option value="active">Aktif</option>
              <option value="suspended">Ditangguhkan (Suspended)</option>
            </select>
          </div>

          <div>
            <label class="block font-semibold text-slate-300 mb-1">Kuota Harian (Daily Tokens)</label>
            <input
              v-model.number="editForm.daily_token_quota"
              type="number"
              min="0"
              class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white font-mono outline-none"
            />
          </div>

          <div>
            <label class="block font-semibold text-slate-300 mb-1">Kuota Bulanan (Monthly Tokens)</label>
            <input
              v-model.number="editForm.monthly_token_quota"
              type="number"
              min="0"
              class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white font-mono outline-none"
            />
          </div>

          <div>
            <label class="block font-semibold text-slate-300 mb-1">Batas Permintaan Simultan (Concurrency)</label>
            <input
              v-model.number="editForm.concurrency_limit"
              type="number"
              min="1"
              max="20"
              class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white font-mono outline-none"
            />
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="isEditModalOpen = false" class="px-3 py-1.5 text-slate-400">Batal</button>
            <button
              type="submit"
              :disabled="isSaving"
              class="px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold text-xs disabled:opacity-50"
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
