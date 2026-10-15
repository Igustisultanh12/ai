<template>
  <div class="relative min-h-screen bg-slate-950 flex flex-col justify-center items-center p-4 overflow-hidden selection:bg-violet-500 selection:text-white">
    <div class="aurora" aria-hidden="true"><span></span><span></span><span></span></div>
    <div class="absolute inset-0 grid-bg pointer-events-none" aria-hidden="true"></div>

    <div class="relative z-10 max-w-md w-full space-y-6">
      <div v-reveal class="text-center space-y-2">
        <router-link to="/" class="inline-flex items-center gap-2 group">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-violet-600 via-fuchsia-500 to-cyan-400 flex items-center justify-center font-black text-white text-xl shadow-lg shadow-violet-500/25 transition-transform duration-300 group-hover:rotate-12 group-hover:scale-110">
            X
          </div>
          <span class="text-3xl font-black tracking-tight text-white">XZY</span>
        </router-link>
        <p class="text-sm text-slate-400">Bikin akun gratis, lalu mulai ngoding bareng AI.</p>
      </div>

      <div v-reveal:zoom="100" class="glow-border bg-slate-900/80 rounded-2xl p-6 sm:p-8 shadow-2xl shadow-violet-950/50 backdrop-blur-xl">
        <form @submit.prevent="handleRegister" class="space-y-4">
          <div v-if="errorMessage" class="p-3.5 rounded-xl bg-rose-950/40 border border-rose-500/30 text-rose-300 text-xs">
            {{ errorMessage }}
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Nama Lengkap</label>
            <input
              v-model="form.name"
              type="text"
              required
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 text-slate-100 text-sm outline-none transition-colors"
              placeholder="Arul Juli"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Alamat Email</label>
            <input
              v-model="form.email"
              type="email"
              required
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 text-slate-100 text-sm outline-none transition-colors"
              placeholder="nama@domain.com"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Password</label>
            <input
              v-model="form.password"
              type="password"
              required
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 text-slate-100 text-sm outline-none transition-colors"
              placeholder="Minimal 8 karakter"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Konfirmasi Password</label>
            <input
              v-model="form.password_confirmation"
              type="password"
              required
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 text-slate-100 text-sm outline-none transition-colors"
              placeholder="Ulangi password"
            />
          </div>

          <button
            type="submit"
            :disabled="isLoading"
            class="w-full py-3 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 hover:from-violet-500 hover:to-cyan-400 text-white font-semibold text-sm shadow-lg shadow-violet-600/25 transition-all hover:scale-[1.01] active:scale-[0.99] disabled:opacity-50 disabled:pointer-events-none flex items-center justify-center gap-2"
          >
            <span v-if="isLoading" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            <span>{{ isLoading ? 'Mendaftarkan Akun...' : 'Daftar Akun Baru' }}</span>
          </button>
        </form>
      </div>

      <div class="text-center text-xs text-slate-400">
        Sudah memiliki akun?
        <router-link to="/login" class="text-cyan-400 hover:underline font-semibold ml-1">
          Masuk di sini
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '@/api/client';
import { useAuth } from '@/stores/auth';
import { useToast } from '@/composables/useToast';

const router = useRouter();
const { checkAuth } = useAuth();
const toast = useToast();

const form = reactive({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const isLoading = ref(false);
const errorMessage = ref('');

const handleRegister = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        await api.post('/api/auth/register', form);
        await checkAuth();
        toast.success('Pendaftaran berhasil. Selamat datang di XZY!');
        router.push('/dashboard');
    } catch (e: any) {
        errorMessage.value = e.message || 'Pendaftaran gagal. Periksa input data Anda.';
    } finally {
        isLoading.value = false;
    }
};
</script>
