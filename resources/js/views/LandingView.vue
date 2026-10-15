<template>
  <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col selection:bg-violet-500 selection:text-white">
    <!-- Navbar -->
    <header class="border-b border-slate-800/80 bg-slate-950/70 backdrop-blur-md sticky top-0 z-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-violet-600 via-fuchsia-500 to-cyan-400 flex items-center justify-center font-black text-white text-lg shadow-lg shadow-violet-500/20">
            X
          </div>
          <span class="text-2xl font-black tracking-tight bg-gradient-to-r from-white via-slate-200 to-cyan-300 bg-clip-text text-transparent">
            XZY
          </span>
        </div>

        <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-slate-300">
          <a href="#fitur" class="hover:text-cyan-400 transition-colors">Fitur Utama</a>
          <a href="#arsitektur" class="hover:text-cyan-400 transition-colors">Arsitektur</a>
          <a href="#tokenomix" class="hover:text-cyan-400 transition-colors">TokenomixAI</a>
          <a href="#keamanan" class="hover:text-cyan-400 transition-colors">Keamanan Sandbox</a>
        </nav>

        <div class="flex items-center gap-3">
          <router-link
            to="/login"
            class="px-4 py-2 text-sm font-semibold text-slate-300 hover:text-white hover:bg-slate-900 rounded-xl transition-colors"
          >
            Masuk
          </router-link>
          <router-link
            to="/register"
            class="px-4 py-2 text-sm font-semibold rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 hover:from-violet-500 hover:to-cyan-400 text-white shadow-md shadow-violet-600/30 transition-all hover:scale-[1.02] active:scale-[0.98]"
          >
            Mulai Gratis
          </router-link>
        </div>
      </div>
    </header>

    <!-- Hero Section -->
    <main class="flex-1">
      <section class="relative pt-24 pb-20 overflow-hidden text-center px-4">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_80%_60%_at_50%_-20%,rgba(124,58,237,0.25),rgba(255,255,255,0))] pointer-events-none"></div>

        <div class="max-w-4xl mx-auto space-y-6 relative z-10">
          <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-violet-950/60 border border-violet-700/50 text-violet-300 text-xs font-semibold backdrop-blur-md animate-pulse">
            <span>✨</span>
            <span>Platform AI Chat & Vibecoding Generasi Berikutnya</span>
          </div>

          <h1 class="text-5xl sm:text-7xl font-extrabold tracking-tight text-white leading-tight">
            Ubah Percakapan Menjadi
            <span class="bg-gradient-to-r from-violet-400 via-fuchsia-400 to-cyan-400 bg-clip-text text-transparent">
              Aplikasi Nyata
            </span>
          </h1>

          <p class="text-lg sm:text-xl text-slate-400 max-w-2xl mx-auto leading-relaxed">
            XZY menghubungkan model AI tercanggih TokenomixAI dengan workspace proyek terisolasi, sandboxed build runner, pratinjau instan, manajemen checkpoint, dan sinkronisasi GitHub otomatis.
          </p>

          <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-4">
            <router-link
              to="/register"
              class="w-full sm:w-auto px-8 py-3.5 rounded-xl bg-gradient-to-r from-violet-600 via-purple-600 to-cyan-500 hover:from-violet-500 hover:to-cyan-400 text-white font-bold text-base shadow-xl shadow-violet-600/25 transition-all hover:scale-105"
            >
              🚀 Buka Workspace Vibecoding
            </router-link>
            <router-link
              to="/login"
              class="w-full sm:w-auto px-8 py-3.5 rounded-xl bg-slate-900 border border-slate-800 hover:bg-slate-800 text-slate-300 font-semibold text-base transition-colors"
            >
              Masuk ke Akun
            </router-link>
          </div>
        </div>

        <!-- Simulated Workspace Interactive Demo Preview -->
        <div class="max-w-5xl mx-auto mt-16 rounded-2xl border border-slate-800 bg-slate-900/90 shadow-2xl overflow-hidden backdrop-blur-xl text-left">
          <div class="h-10 px-4 bg-slate-950 border-b border-slate-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <div class="w-3 h-3 rounded-full bg-rose-500/80"></div>
              <div class="w-3 h-3 rounded-full bg-amber-500/80"></div>
              <div class="w-3 h-3 rounded-full bg-emerald-500/80"></div>
              <span class="ml-2 font-mono text-xs text-slate-400">workspace-demo / resources/js/App.vue</span>
            </div>
            <div class="text-[11px] font-mono text-cyan-400 px-2 py-0.5 rounded bg-cyan-950/50 border border-cyan-800/40">
              Vibecoding Cycle: Checkpoint Passed
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-12 min-h-[340px] text-xs font-mono">
            <!-- Files -->
            <div class="md:col-span-3 border-r border-slate-800 p-3 bg-slate-950/40 space-y-1.5 hidden md:block">
              <div class="text-[10px] uppercase tracking-wider text-slate-500 font-bold mb-2">Workspace Explorer</div>
              <div class="text-slate-300 flex items-center gap-1.5 py-1 px-2 rounded bg-slate-800/50">
                <span>📄</span> App.vue
              </div>
              <div class="text-slate-400 flex items-center gap-1.5 py-1 px-2">
                <span>📄</span> app.js
              </div>
              <div class="text-slate-400 flex items-center gap-1.5 py-1 px-2">
                <span>📄</span> package.json
              </div>
              <div class="text-slate-400 flex items-center gap-1.5 py-1 px-2">
                <span>📄</span> composer.json
              </div>
            </div>
            <!-- Code -->
            <div class="md:col-span-5 p-4 bg-slate-950/80 text-slate-300 space-y-1 overflow-x-auto">
              <p class="text-violet-400">&lt;template&gt;</p>
              <p class="pl-4 text-slate-200">&lt;div class="min-h-screen bg-slate-900 text-white"&gt;</p>
              <p class="pl-8 text-cyan-300">&lt;h1&gt;Dashboard Vibecoding XZY&lt;/h1&gt;</p>
              <p class="pl-8 text-emerald-300">&lt;button @click="count++"&gt;Total: @{{ count }}&lt;/button&gt;</p>
              <p class="pl-4 text-slate-200">&lt;/div&gt;</p>
              <p class="text-violet-400">&lt;/template&gt;</p>
              <p class="text-slate-500 pt-2">// Checkpoint dibuat secara atomik sebelum perubahan</p>
            </div>
            <!-- AI Output -->
            <div class="md:col-span-4 p-4 bg-slate-900/60 border-l border-slate-800 flex flex-col justify-between">
              <div class="space-y-3">
                <div class="flex items-center gap-2 text-cyan-400 font-semibold">
                  <span class="w-2 h-2 rounded-full bg-cyan-400 animate-ping"></span>
                  <span>Agent Vibecoding Aktif</span>
                </div>
                <p class="text-slate-300 font-sans text-xs leading-relaxed">
                  "Menyusun rencana... Menerapkan patch pada <span class="text-cyan-300 font-mono">App.vue</span>... Memverifikasi sintaks pada sandbox terisolasi... Status: Lolos tanpa error!"
                </p>
                <div class="p-2.5 rounded-lg bg-emerald-950/40 border border-emerald-500/30 text-emerald-300 text-[11px] font-sans">
                  ✓ Verifikasi Sandbox Berhasil • Pratinjau Tersedia
                </div>
              </div>
              <div class="pt-4 border-t border-slate-800 flex items-center justify-between text-[11px] text-slate-400">
                <span>Model: glm-5.1 (2.0x)</span>
                <span class="text-cyan-400 font-semibold">428 weighted tokens</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Features Grid -->
      <section id="fitur" class="py-20 max-w-7xl mx-auto px-4 sm:px-6">
        <div class="text-center space-y-3 mb-16">
          <h2 class="text-3xl font-extrabold text-white">Fitur Lengkap Platform XZY</h2>
          <p class="text-slate-400 max-w-2xl mx-auto">
            Dirancang secara modular dengan keandalan penuh, tanpa tombol kosong atau mockup statis.
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 hover:border-violet-500/40 transition-colors">
            <div class="w-12 h-12 rounded-xl bg-violet-600/20 border border-violet-500/30 flex items-center justify-center text-2xl mb-4">
              🤖
            </div>
            <h3 class="text-lg font-bold text-white mb-2">Vibecoding Mandiri</h3>
            <p class="text-sm text-slate-400 leading-relaxed">
              AI membaca konteks proyek, menyusun rencana, membuat checkpoint otomatis, menerapkan patch, dan menguji kode di runner sandbox.
            </p>
          </div>

          <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 hover:border-cyan-500/40 transition-colors">
            <div class="w-12 h-12 rounded-xl bg-cyan-600/20 border border-cyan-500/30 flex items-center justify-center text-2xl mb-4">
              🛡️
            </div>
            <h3 class="text-lg font-bold text-white mb-2">Sandbox & Keamanan Ketat</h3>
            <p class="text-sm text-slate-400 leading-relaxed">
              Validasi canonical path untuk mencegah directory traversal dan symlink escape. Argv terstruktur tanpa shell string injection.
            </p>
          </div>

          <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 hover:border-fuchsia-500/40 transition-colors">
            <div class="w-12 h-12 rounded-xl bg-fuchsia-600/20 border border-fuchsia-500/30 flex items-center justify-center text-2xl mb-4">
              📊
            </div>
            <h3 class="text-lg font-bold text-white mb-2">Ledger Token Presisi</h3>
            <p class="text-sm text-slate-400 leading-relaxed">
              Menghitung <code class="text-cyan-300">raw_tokens × multiplier</code> sesuai spesifikasi TokenomixAI dengan kuota harian/bulanan terisolasi.
            </p>
          </div>

          <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 hover:border-emerald-500/40 transition-colors">
            <div class="w-12 h-12 rounded-xl bg-emerald-600/20 border border-emerald-500/30 flex items-center justify-center text-2xl mb-4">
              📦
            </div>
            <h3 class="text-lg font-bold text-white mb-2">Ekspor ZIP & Asli RAR</h3>
            <p class="text-sm text-slate-400 leading-relaxed">
              Unduh proyek utuh dalam format ZIP standar atau arsip resmi RAR dengan indikator kesiapan binary yang transparan.
            </p>
          </div>

          <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 hover:border-blue-500/40 transition-colors">
            <div class="w-12 h-12 rounded-xl bg-blue-600/20 border border-blue-500/30 flex items-center justify-center text-2xl mb-4">
              🐙
            </div>
            <h3 class="text-lg font-bold text-white mb-2">Sinkronisasi GitHub Aman</h3>
            <p class="text-sm text-slate-400 leading-relaxed">
              Pemindaian secret sebelum commit, pencegahan konflik remote non-fast-forward, dan pengiriman atomik tanpa eksekusi hook lokal tak tepercaya.
            </p>
          </div>

          <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 hover:border-amber-500/40 transition-colors">
            <div class="w-12 h-12 rounded-xl bg-amber-600/20 border border-amber-500/30 flex items-center justify-center text-2xl mb-4">
              🔐
            </div>
            <h3 class="text-lg font-bold text-white mb-2">MFA TOTP & Audit Log</h3>
            <p class="text-sm text-slate-400 leading-relaxed">
              Autentikasi dua faktor standar RFC 6238 dengan recovery codes, rotasi sesi, serta pencatatan audit log tanpa membocorkan secret.
            </p>
          </div>
        </div>
      </section>
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-900 py-8 bg-slate-950 text-slate-500 text-xs">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
        <p>© 2026 XZY Platform. Hak cipta dilindungi undang-undang.</p>
        <p>Integrasi Gateway OpenAI-Compatible Resmi TokenomixAI.</p>
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
// Landing page view
</script>
