<template>
  <div v-if="open" class="fixed inset-0 z-40 flex items-center justify-center p-3 sm:p-6 bg-black/70 backdrop-blur-sm" @click.self="close">
    <div class="w-full max-w-2xl max-h-[92vh] flex flex-col rounded-2xl bg-slate-900 border border-slate-800 shadow-2xl overflow-hidden">
      <!-- Header -->
      <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
        <div>
          <h3 class="font-bold text-white text-sm sm:text-base">Push ke GitHub</h3>
          <p class="text-[11px] text-slate-400">Commit berkas dari chat langsung ke repositori &amp; branch pilihan Anda.</p>
        </div>
        <button @click="close" class="text-slate-400 hover:text-white text-sm p-1" title="Tutup">✕</button>
      </div>

      <div class="flex-1 overflow-y-auto p-5 space-y-4 text-xs">
        <div v-if="loadingStatus" class="text-slate-400">Memeriksa koneksi GitHub...</div>

        <!-- Not connected: connect inline -->
        <div v-else-if="!status.is_connected" class="space-y-3">
          <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300">
            Akun GitHub belum terhubung. Masukkan Personal Access Token dengan izin <span class="font-mono">repo</span>
            (classic) atau <span class="font-mono">Contents: Read and write</span> (fine-grained).
          </div>
          <input
            v-model="token"
            type="password"
            autocomplete="off"
            placeholder="ghp_... atau github_pat_..."
            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 focus:border-cyan-400 text-white font-mono outline-none"
          />
          <button
            @click="connect"
            :disabled="!token.trim() || connecting"
            class="px-4 py-2 rounded-xl bg-violet-600 hover:bg-violet-500 text-white font-semibold disabled:opacity-40"
          >
            {{ connecting ? 'Menghubungkan...' : 'Hubungkan GitHub' }}
          </button>
        </div>

        <!-- Connected -->
        <template v-else>
          <div class="flex items-center justify-between text-slate-400">
            <span>Terhubung sebagai <span class="text-cyan-300 font-semibold">@{{ status.username }}</span></span>
            <button @click="loadRepos" class="hover:text-white" :disabled="loadingRepos">{{ loadingRepos ? 'Memuat...' : '↻ Muat ulang repo' }}</button>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label class="space-y-1">
              <span class="text-slate-400">Repositori</span>
              <input
                v-model="repo"
                list="gh-repo-list"
                placeholder="pemilik/nama-repo"
                @change="onRepoChange"
                class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 focus:border-cyan-400 text-white font-mono outline-none"
              />
              <datalist id="gh-repo-list">
                <option v-for="r in repos" :key="r.id" :value="r.full_name">{{ r.private ? '🔒' : '' }}</option>
              </datalist>
            </label>
            <label class="space-y-1">
              <span class="text-slate-400">Branch <span class="text-slate-500">(dibuat otomatis jika belum ada)</span></span>
              <input
                v-model="branch"
                list="gh-branch-list"
                placeholder="main"
                class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 focus:border-cyan-400 text-white font-mono outline-none"
              />
              <datalist id="gh-branch-list">
                <option v-for="b in branches" :key="b" :value="b" />
              </datalist>
            </label>
          </div>

          <label class="block space-y-1">
            <span class="text-slate-400">Pesan commit</span>
            <input
              v-model="commitMessage"
              class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 focus:border-cyan-400 text-white outline-none"
            />
          </label>

          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-slate-400">Berkas ({{ selectedCount }} dipilih)</span>
              <button @click="addEmptyFile" class="text-cyan-400 hover:text-cyan-300">+ Tambah berkas</button>
            </div>
            <div v-if="rows.length === 0" class="p-3 rounded-xl bg-slate-950 border border-slate-800 text-slate-400">
              Tidak ada blok kode di pesan ini. Minta AI menulis berkas dengan format <span class="font-mono">```php:path/berkas.php</span>.
            </div>
            <div
              v-for="(row, i) in rows"
              :key="row.key"
              class="rounded-xl bg-slate-950 border"
              :class="row.selected && !isSafePath(row.path) ? 'border-rose-500/50' : 'border-slate-800'"
            >
              <div class="flex items-center gap-2 p-2">
                <input type="checkbox" v-model="row.selected" class="accent-violet-500" />
                <input
                  v-model="row.path"
                  placeholder="path/ke/berkas.ext"
                  class="flex-1 min-w-0 px-2 py-1 rounded-lg bg-slate-900 border border-slate-800 focus:border-cyan-400 text-white font-mono outline-none"
                />
                <span class="text-[10px] text-slate-500 shrink-0">{{ formatSize(row.content) }}</span>
                <button @click="row.expanded = !row.expanded" class="text-slate-400 hover:text-white shrink-0 px-1">{{ row.expanded ? '▲' : '▼' }}</button>
                <button @click="rows.splice(i, 1)" class="text-slate-500 hover:text-rose-400 shrink-0 px-1" title="Hapus">✕</button>
              </div>
              <textarea
                v-if="row.expanded"
                v-model="row.content"
                rows="10"
                spellcheck="false"
                class="w-full p-2 bg-slate-950 border-t border-slate-800 text-[11px] text-slate-200 font-mono outline-none resize-y"
              ></textarea>
            </div>
          </div>

          <div v-if="result" class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 space-y-1">
            <p>{{ result.message }}<span v-if="result.branch_created"> (branch baru dibuat)</span></p>
            <a :href="result.commit_url" target="_blank" rel="noopener" class="underline font-mono break-all">{{ result.commit_url }}</a>
          </div>
          <div v-if="error" class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 whitespace-pre-wrap">{{ error }}</div>
        </template>
      </div>

      <!-- Footer -->
      <div v-if="status.is_connected" class="px-5 py-3 border-t border-slate-800 flex items-center justify-end gap-2">
        <button @click="close" class="px-4 py-2 rounded-xl text-slate-300 hover:bg-slate-800 text-xs">Tutup</button>
        <button
          @click="push"
          :disabled="!canPush || pushing"
          class="px-5 py-2 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 hover:from-violet-500 hover:to-cyan-400 text-white font-bold text-xs disabled:opacity-40 disabled:pointer-events-none"
        >
          {{ pushing ? 'Mendorong...' : `Push ${selectedCount} berkas` }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';
import { isSafePath, normalisePath, suggestPath, type CodeFile } from '@/utils/codeBlocks';

const props = defineProps<{
  open: boolean;
  conversationId: string;
  conversationTitle?: string;
  files: CodeFile[];
}>();

const emit = defineEmits<{ (e: 'close'): void }>();

const toast = useToast();
const STORAGE_KEY = 'xzy_chat_github_target';

interface Row { key: number; path: string; content: string; selected: boolean; expanded: boolean }

const status = ref<{ is_connected: boolean; username?: string }>({ is_connected: false });
const loadingStatus = ref(false);
const token = ref('');
const connecting = ref(false);
const repos = ref<any[]>([]);
const loadingRepos = ref(false);
const branches = ref<string[]>([]);
const repo = ref('');
const branch = ref('main');
const commitMessage = ref('');
const rows = ref<Row[]>([]);
const pushing = ref(false);
const result = ref<any>(null);
const error = ref('');
let keySeq = 0;

const selectedRows = computed(() => rows.value.filter(r => r.selected));
const selectedCount = computed(() => selectedRows.value.length);
const canPush = computed(() =>
  /^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/.test(repo.value.trim()) &&
  branch.value.trim() !== '' &&
  selectedCount.value > 0 &&
  selectedRows.value.every(r => isSafePath(normalisePath(r.path)))
);

const formatSize = (s: string) => {
  const bytes = new Blob([s]).size;
  return bytes < 1024 ? `${bytes} B` : `${(bytes / 1024).toFixed(1)} KB`;
};

const loadSaved = () => {
  try {
    const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
    if (saved.repo) repo.value = saved.repo;
    if (saved.branch) branch.value = saved.branch;
  } catch { /* storage unavailable */ }
};

const saveTarget = () => {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({ repo: repo.value.trim(), branch: branch.value.trim() }));
  } catch { /* storage unavailable */ }
};

const loadStatus = async () => {
  loadingStatus.value = true;
  try {
    status.value = await api.get('/api/github/status');
    if (status.value.is_connected) {
      loadRepos();
      if (repo.value) loadBranches();
    }
  } catch (e: any) {
    error.value = 'Gagal memeriksa status GitHub: ' + e.message;
  } finally {
    loadingStatus.value = false;
  }
};

const connect = async () => {
  connecting.value = true;
  try {
    const res = await api.post('/api/github/connect', { token: token.value.trim() });
    token.value = '';
    toast.success(res.message || 'GitHub terhubung.');
    await loadStatus();
  } catch (e: any) {
    toast.error(e.message);
  } finally {
    connecting.value = false;
  }
};

const loadRepos = async () => {
  loadingRepos.value = true;
  try {
    const res = await api.get('/api/github/repos');
    repos.value = res.repos || [];
  } catch (e: any) {
    toast.error('Gagal memuat repositori: ' + e.message);
  } finally {
    loadingRepos.value = false;
  }
};

const loadBranches = async () => {
  branches.value = [];
  const r = repo.value.trim();
  if (!/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/.test(r)) return;
  try {
    const res = await api.get(`/api/github/branches?repo=${encodeURIComponent(r)}`);
    branches.value = res.branches || [];
  } catch {
    // repo may be typed partially or inaccessible; push will report a clear error
  }
};

const onRepoChange = () => {
  const found = repos.value.find(r => r.full_name === repo.value.trim());
  if (found?.default_branch && (!branch.value || branch.value === 'main')) branch.value = found.default_branch;
  loadBranches();
};

const addEmptyFile = () => {
  rows.value.push({ key: ++keySeq, path: '', content: '', selected: true, expanded: true });
};

const push = async () => {
  pushing.value = true;
  error.value = '';
  result.value = null;
  try {
    const res = await api.post(`/api/conversations/${props.conversationId}/github/push`, {
      repo: repo.value.trim(),
      branch: branch.value.trim(),
      commit_message: commitMessage.value.trim(),
      files: selectedRows.value.map(r => ({ path: normalisePath(r.path), content: r.content })),
    });
    result.value = res;
    saveTarget();
    toast.success(res.message || 'Push berhasil.');
    loadBranches();
  } catch (e: any) {
    const details = e.data?.errors ? '\n' + Object.values(e.data.errors).flat().join('\n') : '';
    error.value = e.message + details;
  } finally {
    pushing.value = false;
  }
};

const close = () => emit('close');

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return;
    result.value = null;
    error.value = '';
    loadSaved();
    rows.value = props.files.map((f, i) => ({
      key: ++keySeq,
      path: f.path || suggestPath(f, i),
      content: f.content,
      selected: !!f.path,
      expanded: false,
    }));
    if (rows.value.length > 0 && !rows.value.some(r => r.selected)) {
      rows.value.forEach(r => (r.selected = true));
    }
    const title = (props.conversationTitle || '').replace(/\.\.\.$/, '').trim();
    commitMessage.value = title ? `Update dari chat XZY: ${title}` : 'Update dari chat XZY';
    loadStatus();
  },
  { immediate: true }
);
</script>
