<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/partials/assets.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}
$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - <?= htmlspecialchars(APP_NAME) ?></title>
  <?php tailwind_assets(); ?>
</head>
<body class="min-h-screen bg-slate-50 dark:bg-slate-950">
  <div class="grid min-h-screen lg:grid-cols-2">
    <!-- Panel kiri: brand / hero -->
    <div class="relative hidden lg:flex flex-col justify-between overflow-hidden bg-gradient-to-br from-brand-700 via-brand-600 to-violet-700 p-12 text-white">
      <div class="absolute -top-24 -right-24 h-72 w-72 rounded-full bg-white/10 blur-3xl"></div>
      <div class="absolute -bottom-32 -left-10 h-80 w-80 rounded-full bg-violet-400/20 blur-3xl"></div>

      <div class="relative">
        <div class="flex items-center gap-3">
          <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 font-display font-extrabold text-lg backdrop-blur">M</div>
          <span class="font-display text-xl font-extrabold tracking-tight">MRP-ERP Kampus</span>
        </div>
      </div>

      <div class="relative max-w-md">
        <h1 class="font-display text-4xl font-extrabold leading-tight">Kelola akademik &amp; produksi dalam satu tempat.</h1>
        <p class="mt-4 text-white/80">Data mahasiswa, pembagian kelas otomatis berdasarkan NBI, hingga perencanaan
          kebutuhan material (MRP), purchase order, dan work order — semua terhubung.</p>
        <div class="mt-8 grid grid-cols-3 gap-4">
          <div class="rounded-2xl bg-white/10 p-4 backdrop-blur">
            <div class="text-2xl font-extrabold">4</div>
            <div class="text-xs text-white/70 mt-1">Kelas (A–D)</div>
          </div>
          <div class="rounded-2xl bg-white/10 p-4 backdrop-blur">
            <div class="text-2xl font-extrabold">120</div>
            <div class="text-xs text-white/70 mt-1">Mahasiswa</div>
          </div>
          <div class="rounded-2xl bg-white/10 p-4 backdrop-blur">
            <div class="text-2xl font-extrabold">MRP</div>
            <div class="text-xs text-white/70 mt-1">Gross-to-net</div>
          </div>
        </div>
      </div>

      <p class="relative text-xs text-white/60">&copy; <?= date('Y') ?> Sistem Akademik &amp; MRP-ERP</p>
    </div>

    <!-- Panel kanan: form login -->
    <div class="flex items-center justify-center p-6 sm:p-10">
      <div class="w-full max-w-sm">
        <div class="mb-8 text-center lg:hidden">
          <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-violet-600 font-display text-lg font-extrabold text-white">M</div>
          <h1 class="font-display text-xl font-extrabold text-slate-800 dark:text-white">MRP-ERP Kampus</h1>
        </div>

        <h2 class="font-display text-2xl font-extrabold text-slate-800 dark:text-white">Selamat datang kembali</h2>
        <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">Masuk untuk mengelola data akademik dan operasional.</p>

        <div id="alert" class="mt-5"></div>

        <form id="loginForm" class="mt-6 space-y-4">
          <input type="hidden" id="csrf_token" value="<?= htmlspecialchars($token) ?>">
          <label>Username
            <input id="username" name="username" required autofocus autocomplete="username" placeholder="admin">
          </label>
          <label>Password
            <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="••••••••">
          </label>
          <button type="submit" class="btn btn-primary btn-block !py-3 mt-2">Masuk</button>
        </form>

        <p class="mt-6 text-center text-xs text-slate-400 dark:text-slate-500">
          Akun default: <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono dark:bg-slate-800">admin</code> /
          <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono dark:bg-slate-800">admin123</code>
        </p>
      </div>
    </div>
  </div>
  <script src="js/login.js"></script>
</body>
</html>
