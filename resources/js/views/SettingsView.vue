<template>
  <div class="space-y-6 max-w-4xl mx-auto animate-in fade-in duration-200">
    <div>
      <h1 class="page-title">Pengaturan Akun & Keamanan</h1>
      <p class="page-subtitle">Kelola profil, keamanan autentikasi dua faktor (MFA), sesi aktif, dan integrasi GitHub</p>
    </div>

    <!-- Settings Navigation Tabs -->
    <div class="flex border-b border-line text-xs font-semibold">
      <button
        @click="activeTab = 'profile'"
        class="px-4 py-2.5 border-b-2 transition-colors"
        :class="activeTab === 'profile' ? 'border-violet-400 text-violet-600 dark:text-violet-400' : 'border-transparent text-muted hover:text-ink'"
      >
        Profil & Password
      </button>
      <button
        @click="activeTab = 'mfa'"
        class="px-4 py-2.5 border-b-2 transition-colors"
        :class="activeTab === 'mfa' ? 'border-violet-400 text-violet-600 dark:text-violet-400' : 'border-transparent text-muted hover:text-ink'"
      >
        Autentikasi 2FA (MFA)
      </button>
      <button
        @click="activeTab = 'sessions'"
        class="px-4 py-2.5 border-b-2 transition-colors"
        :class="activeTab === 'sessions' ? 'border-violet-400 text-violet-600 dark:text-violet-400' : 'border-transparent text-muted hover:text-ink'"
      >
        Sesi Aktif
      </button>
      <button
        @click="activeTab = 'github'"
        class="px-4 py-2.5 border-b-2 transition-colors"
        :class="activeTab === 'github' ? 'border-violet-400 text-violet-600 dark:text-violet-400' : 'border-transparent text-muted hover:text-ink'"
      >
        Integrasi GitHub
      </button>
    </div>

    <!-- TAB 1: Profile & Password -->
    <div v-show="activeTab === 'profile'" class="space-y-6">
      <div class="p-6 rounded-3xl bg-card border-2 border-line space-y-4">
        <h3 class="font-bold text-base text-ink">Informasi Profil</h3>
        <form @submit.prevent="updateProfile" class="space-y-4 max-w-md text-xs">
          <div>
            <label class="label">Nama</label>
            <input
              v-model="profileForm.name"
              type="text"
              required
              class="input"
            />
          </div>
          <div>
            <label class="label">Email</label>
            <input
              v-model="profileForm.email"
              type="email"
              required
              class="input"
            />
          </div>
          <button
            type="submit"
            :disabled="isUpdatingProfile"
            class="btn btn-primary shine disabled:opacity-50"
          >
            {{ isUpdatingProfile ? 'Menyimpan...' : 'Simpan Profil' }}
          </button>
        </form>
      </div>

      <div class="p-6 rounded-3xl bg-card border-2 border-line space-y-4">
        <h3 class="font-bold text-base text-ink">Ubah Password</h3>
        <form @submit.prevent="updatePassword" class="space-y-4 max-w-md text-xs">
          <div>
            <label class="label">Password Saat Ini</label>
            <input
              v-model="passwordForm.current_password"
              type="password"
              required
              class="input"
            />
          </div>
          <div>
            <label class="label">Password Baru (min 8 karakter)</label>
            <input
              v-model="passwordForm.password"
              type="password"
              required
              class="input"
            />
          </div>
          <div>
            <label class="label">Konfirmasi Password Baru</label>
            <input
              v-model="passwordForm.password_confirmation"
              type="password"
              required
              class="input"
            />
          </div>
          <button
            type="submit"
            :disabled="isUpdatingPassword"
            class="btn btn-primary shine disabled:opacity-50"
          >
            {{ isUpdatingPassword ? 'Memproses...' : 'Perbarui Password' }}
          </button>
        </form>
      </div>
    </div>

    <!-- TAB 2: MFA TOTP -->
    <div v-show="activeTab === 'mfa'" class="p-6 rounded-3xl bg-card border-2 border-line space-y-5">
      <div>
        <h3 class="font-bold text-base text-ink">Autentikasi Dua Faktor (TOTP)</h3>
        <p class="text-xs text-muted">Wajib untuk administrator di lingkungan production. Kompatibel dengan Google Authenticator, Microsoft Authenticator, dan 1Password.</p>
      </div>

      <!-- State: MFA Active -->
      <div v-if="hasTwoFactor" class="p-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <Icon name="shield-check" class="h-6 w-6" />
          <div>
            <h4 class="font-bold text-emerald-600 dark:text-emerald-300 text-sm">2FA Aktif</h4>
            <p class="text-[11px] text-emerald-400/80">Akun Anda terlindungi dengan kode OTP dan recovery codes.</p>
          </div>
        </div>
        <button
          @click="disableMfaPrompt"
          class="px-3 py-1.5 rounded-lg bg-rose-600/20 border-2 border-rose-500/30 text-rose-600 dark:text-rose-300 text-xs font-semibold hover:bg-rose-600/30 transition-all hover:-translate-y-0.5"
        >
          Nonaktifkan 2FA
        </button>
      </div>

      <!-- State: Setup Flow -->
      <div v-else-if="mfaSetupData" class="p-5 rounded-xl bg-surface border border-line space-y-4">
        <h4 class="font-bold text-ink text-sm">Langkah 1: Pindai Kode atau Masukkan Kunci Rahasia</h4>
        <div class="p-3 bg-card rounded-lg text-xs font-mono text-violet-600 dark:text-violet-300 break-all select-all">
          Kunci Rahasia Manual: {{ mfaSetupData.secret }}
        </div>
        <div class="text-[11px] text-muted">
          Atau gunakan tautan URI: <code class="text-violet-600 dark:text-violet-300 break-all">{{ mfaSetupData.otpauth_url }}</code>
        </div>

        <div v-if="mfaSetupData.recovery_codes" class="p-4 rounded-xl bg-card border border-line space-y-2">
          <h5 class="font-bold text-amber-700 dark:text-amber-300 text-xs">Simpan Kode Pemulihan Cadangan (Recovery Codes)</h5>
          <p class="text-[11px] text-muted">Kode ini hanya ditampilkan sekali. Simpan di tempat yang aman:</p>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 font-mono text-xs text-ink">
            <span v-for="c in mfaSetupData.recovery_codes" :key="c" class="p-1.5 rounded bg-surface border border-line text-center">
              {{ c }}
            </span>
          </div>
        </div>

        <h4 class="font-bold text-ink text-sm pt-2">Langkah 2: Masukkan Kode Konfirmasi</h4>
        <div class="flex items-center gap-3">
          <input
            v-model="confirmCode"
            type="text"
            placeholder="123456"
            class="w-36 px-3 py-2 rounded-xl bg-card border border-line text-center font-mono text-violet-600 dark:text-violet-300 outline-none text-sm"
          />
          <button
            @click="confirmMfa"
            class="btn btn-primary shine btn-sm"
          >
            Aktifkan Sekarang
          </button>
        </div>
      </div>

      <!-- State: Not configured -->
      <div v-else class="space-y-3">
        <p class="text-xs text-muted leading-relaxed">
          Autentikasi dua faktor menambahkan lapisan keamanan ekstra pada akun Anda dengan mewajibkan kode 6 digit setiap kali login.
        </p>
        <button
          @click="startMfaSetup"
          :disabled="isSettingUpMfa"
          class="btn btn-primary shine btn-sm"
        >
          {{ isSettingUpMfa ? 'Menyiapkan...' : 'Siapkan Autentikasi 2FA' }}
        </button>
      </div>
    </div>

    <!-- TAB 3: Active Sessions -->
    <div v-show="activeTab === 'sessions'" class="p-6 rounded-3xl bg-card border-2 border-line space-y-4">
      <div class="flex items-center justify-between">
        <div>
          <h3 class="font-bold text-base text-ink">Sesi Login Aktif</h3>
          <p class="text-xs text-muted">Daftar perangkat yang saat ini memiliki akses ke akun Anda</p>
        </div>
        <button
          @click="revokeOtherSessions"
          class="px-3.5 py-1.5 rounded-lg bg-rose-600/20 border-2 border-rose-500/30 text-rose-600 dark:text-rose-300 hover:bg-rose-600/30 text-xs font-semibold transition-all hover:-translate-y-0.5"
        >
          Keluarkan Sesi Lainnya
        </button>
      </div>

      <div class="divide-y divide-line border border-line rounded-xl overflow-hidden bg-surface text-xs">
        <div
          v-for="s in sessions"
          :key="s.id"
          class="p-4 flex items-center justify-between"
        >
          <div class="space-y-0.5">
            <div class="flex items-center gap-2">
              <span class="font-semibold text-ink">{{ s.ip_address }}</span>
              <span v-if="s.is_current" class="px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-[10px]">
                Perangkat Ini
              </span>
            </div>
            <p class="text-muted text-[11px] truncate max-w-sm">{{ s.user_agent }}</p>
            <p class="text-muted text-[10px]">Terakhir aktif: {{ s.last_active }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 4: GitHub Integration -->
    <div v-show="activeTab === 'github'" class="p-6 rounded-3xl bg-card border-2 border-line space-y-4">
      <div>
        <h3 class="font-bold text-base text-ink">Integrasi GitHub</h3>
        <p class="text-xs text-muted">Hubungkan akun GitHub Anda untuk mengaktifkan sinkronisasi kode dan auto-push.</p>
      </div>

      <div v-if="github?.is_connected" class="p-4 rounded-xl bg-surface border border-line flex items-center justify-between">
        <div class="flex items-center gap-3">
          <Icon name="github" class="h-6 w-6" />
          <div>
            <h4 class="font-bold text-ink text-sm">Terhubung sebagai @{{ github.username }}</h4>
            <p class="text-[11px] text-muted">Izin (Scopes): {{ github.scopes?.join(', ') || 'repo' }}</p>
          </div>
        </div>
        <button
          @click="disconnectGithub"
          class="px-3 py-1.5 rounded-lg bg-rose-600/20 border-2 border-rose-500/30 text-rose-600 dark:text-rose-300 hover:bg-rose-600/30 text-xs font-semibold transition-all hover:-translate-y-0.5"
        >
          Putuskan Koneksi
        </button>
      </div>

      <form v-else @submit.prevent="connectGithub" class="space-y-4 max-w-md text-xs">
        <div>
          <label class="label">GitHub Personal Access Token (PAT)</label>
          <input
            v-model="githubToken"
            type="password"
            required
            placeholder="ghp_xxxxxxxxxxxxxxxxxxxx"
            class="input font-mono"
          />
          <p class="text-[10px] text-muted mt-1">Token disimpan terenkripsi di server dan digunakan hanya untuk mengakses repositori Anda.</p>
        </div>
        <button
          type="submit"
          :disabled="isConnectingGithub"
          class="btn btn-primary shine disabled:opacity-50"
        >
          {{ isConnectingGithub ? 'Menghubungkan...' : 'Hubungkan GitHub' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { useAuth } from '@/stores/auth';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';

const { user, hasTwoFactor, checkAuth } = useAuth();
const toast = useToast();

const activeTab = ref<'profile' | 'mfa' | 'sessions' | 'github'>('profile');

const profileForm = reactive({
    name: user.value?.name || '',
    email: user.value?.email || '',
});
const isUpdatingProfile = ref(false);

const passwordForm = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
});
const isUpdatingPassword = ref(false);

const mfaSetupData = ref<any>(null);
const confirmCode = ref('');
const isSettingUpMfa = ref(false);

const sessions = ref<any[]>([]);
const github = ref<any>(null);
const githubToken = ref('');
const isConnectingGithub = ref(false);

const updateProfile = async () => {
    isUpdatingProfile.value = true;
    try {
        await api.put('/api/auth/profile', profileForm);
        await checkAuth();
        toast.success('Profil berhasil diperbarui.');
    } catch (e: any) {
        toast.error('Gagal memperbarui profil: ' + e.message);
    } finally {
        isUpdatingProfile.value = false;
    }
};

const updatePassword = async () => {
    isUpdatingPassword.value = true;
    try {
        await api.put('/api/auth/password', passwordForm);
        toast.success('Password berhasil diperbarui.');
        passwordForm.current_password = '';
        passwordForm.password = '';
        passwordForm.password_confirmation = '';
    } catch (e: any) {
        toast.error('Gagal memperbarui password: ' + e.message);
    } finally {
        isUpdatingPassword.value = false;
    }
};

const startMfaSetup = async () => {
    isSettingUpMfa.value = true;
    try {
        mfaSetupData.value = await api.post('/api/auth/mfa/setup');
    } catch (e: any) {
        toast.error('Gagal menyiapkan MFA: ' + e.message);
    } finally {
        isSettingUpMfa.value = false;
    }
};

const confirmMfa = async () => {
    if (!confirmCode.value.trim()) return;
    try {
        await api.post('/api/auth/mfa/confirm', { code: confirmCode.value });
        await checkAuth();
        toast.success('Autentikasi dua faktor berhasil diaktifkan.');
        mfaSetupData.value = null;
        confirmCode.value = '';
    } catch (e: any) {
        toast.error('Kode konfirmasi tidak valid: ' + e.message);
    }
};

const disableMfaPrompt = async () => {
    const password = prompt('Masukkan password akun Anda untuk mengonfirmasi penonaktifan 2FA:');
    if (!password) return;

    try {
        await api.post('/api/auth/mfa/disable', { password });
        await checkAuth();
        toast.success('Autentikasi 2FA dinonaktifkan.');
    } catch (e: any) {
        toast.error('Gagal menonaktifkan 2FA: ' + e.message);
    }
};

const fetchSessions = async () => {
    try {
        const res = await api.get('/api/auth/sessions');
        sessions.value = res.sessions || [];
    } catch (e) {
        // Ignore
    }
};

const revokeOtherSessions = async () => {
    const password = prompt('Masukkan password untuk mengeluarkan semua sesi di perangkat lain:');
    if (!password) return;

    try {
        await api.post('/api/auth/sessions/revoke-others', { password });
        toast.success('Sesi perangkat lain berhasil dikeluarkan.');
        fetchSessions();
    } catch (e: any) {
        toast.error('Gagal mencabut sesi: ' + e.message);
    }
};

const fetchGithub = async () => {
    try {
        github.value = await api.get('/api/github/status');
    } catch (e) {
        // Ignore
    }
};

const connectGithub = async () => {
    if (!githubToken.value.trim()) return;
    isConnectingGithub.value = true;
    try {
        await api.post('/api/github/connect', { token: githubToken.value });
        toast.success('Akun GitHub berhasil terhubung.');
        githubToken.value = '';
        fetchGithub();
    } catch (e: any) {
        toast.error('Gagal menghubungkan GitHub: ' + e.message);
    } finally {
        isConnectingGithub.value = false;
    }
};

const disconnectGithub = async () => {
    if (!confirm('Putuskan koneksi GitHub?')) return;
    try {
        await api.delete('/api/github/disconnect');
        toast.success('Koneksi GitHub diputuskan.');
        fetchGithub();
    } catch (e: any) {
        toast.error('Gagal memutuskan GitHub: ' + e.message);
    }
};

onMounted(() => {
    fetchSessions();
    fetchGithub();
});
</script>
