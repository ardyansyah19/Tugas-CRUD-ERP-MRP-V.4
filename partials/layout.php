<?php
/**
 * Layout bersama semua halaman aplikasi (wajib login) — V4, tampilan Tailwind + sidebar.
 * Pemakaian:  page_start('Judul', 'menu-aktif');  ... isi ...  page_end(['js/erp-core.js', 'js/halaman.js']);
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/assets.php';

/** Ikon garis tipis (24x24) dipakai di sidebar & tempat lain. Set kecil, ditulis tangan agar ringan (tanpa dependency). */
function icon(string $name, string $class = 'h-5 w-5'): string
{
    $paths = [
        'dashboard' => '<path d="M3 13h8V3H3v10Zm10 8h8V11h-8v10ZM3 21h8v-6H3v6ZM13 3v6h8V3h-8Z"/>',
        'users'     => '<path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3Zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3Zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5Zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5Z"/>',
        'layers'    => '<path d="m12 2 10 6-10 6L2 8Zm0 8.5L2 14.5 12 20.5l10-6-10-6.5Z"/>',
        'truck'     => '<path d="M3 6h11v8H3zM14 10h4l3 3v1h-7zM6 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm12 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/>',
        'clipboard' => '<path d="M9 3h6a1 1 0 0 1 1 1v1h2a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h2V4a1 1 0 0 1 1-1Zm0 4H7v13h10V7h-2v1H9V7Zm0-2v1h6V5H9Z"/>',
        'calc'      => '<path d="M6 2h12a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1Zm1 2v4h10V4H7Zm0 6v2h2v-2H7Zm4 0v2h2v-2h-2Zm4 0v2h2v-2h-2ZM7 14v2h2v-2H7Zm4 0v2h2v-2h-2Zm4 0v4h2v-4h-2ZM7 18v2h2v-2H7Zm4 0v2h2v-2h-2Z"/>',
        'box'       => '<path d="m12 2 9 5v10l-9 5-9-5V7l9-5Zm0 2.3L5.2 8 12 11.7 18.8 8 12 4.3ZM5 9.8v7l6 3.3v-7L5 9.8Zm14 0-6 3.3v7l6-3.3v-7Z"/>',
        'chart'     => '<path d="M4 20V10h3v10H4Zm6.5 0V4h3v16h-3ZM17 20v-7h3v7h-3Z"/>',
        'moon'      => '<path d="M20.7 15.3A8.5 8.5 0 0 1 8.7 3.3a.6.6 0 0 0-.7-.8A10 10 0 1 0 21.5 16a.6.6 0 0 0-.8-.7Z"/>',
        'logout'    => '<path d="M10 17v-2H3v-2h7V9l4 4-4 4Zm9 3H12v-2h7V6h-7V4h7a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2Z"/>',
        'menu'      => '<path d="M3 6h18v2H3V6Zm0 5h18v2H3v-2Zm0 5h18v2H3v-2Z"/>',
    ];
    $d = $paths[$name] ?? '';
    return "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\" fill=\"currentColor\" class=\"$class shrink-0\">$d</svg>";
}

function nav_menu(): array
{
    return [
        'Akademik' => [
            'dashboard' => ['Dashboard', 'dashboard.php', 'dashboard'],
            'mahasiswa' => ['Mahasiswa', 'index.php', 'users'],
            'kelas'     => ['Kelas', 'kelas.php', 'layers'],
        ],
        'Master' => [
            'item'     => ['Item & BOM', 'item.php', 'box'],
            'supplier' => ['Supplier', 'supplier.php', 'truck'],
        ],
        'Perencanaan' => [
            'kebutuhan' => ['Kebutuhan (MPS)', 'kebutuhan.php', 'clipboard'],
            'mrp'       => ['MRP', 'mrp.php', 'calc'],
        ],
        'Operasional' => [
            'po'   => ['Purchase Order', 'po.php', 'clipboard'],
            'wo'   => ['Work Order', 'wo.php', 'layers'],
            'stok' => ['Stok', 'stok.php', 'chart'],
        ],
    ];
}

function page_start(string $title, string $active): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
    $token = csrf_token();
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title) ?> - <?= htmlspecialchars(APP_NAME) ?></title>
  <?php tailwind_assets(); ?>
</head>
<body class="min-h-screen">
  <input type="hidden" id="csrf_token" value="<?= htmlspecialchars($token) ?>">

  <div class="flex min-h-screen">
    <!-- ============ Sidebar ============ -->
    <aside id="sidebar"
      class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full transform border-r border-slate-200 bg-white transition-transform
             lg:static lg:translate-x-0 dark:border-slate-800 dark:bg-slate-900">
      <div class="flex h-16 items-center gap-2 px-5 border-b border-slate-100 dark:border-slate-800">
        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-violet-600 text-white font-display font-extrabold text-sm shadow-sm">M</div>
        <div>
          <div class="font-display font-extrabold text-slate-800 dark:text-white leading-tight">MRP-ERP</div>
          <div class="text-[11px] text-slate-400 dark:text-slate-500 leading-tight">Sistem Akademik</div>
        </div>
      </div>
      <nav class="px-3 py-4 space-y-5 overflow-y-auto h-[calc(100vh-4rem)]">
        <?php foreach (nav_menu() as $grup => $items): ?>
        <div>
          <p class="px-3 mb-1.5 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500"><?= htmlspecialchars($grup) ?></p>
          <div class="space-y-0.5">
            <?php foreach ($items as $key => [$label, $href, $icon]): $isActive = $key === $active; ?>
            <a href="<?= $href ?>"
               class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition-colors
                      <?= $isActive
                        ? 'bg-brand-50 text-brand-700 dark:bg-brand-950 dark:text-brand-300'
                        : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100' ?>">
              <?= icon($icon, 'h-[18px] w-[18px] ' . ($isActive ? 'text-brand-600 dark:text-brand-400' : 'text-slate-400 dark:text-slate-500')) ?>
              <?= htmlspecialchars($label) ?>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </nav>
    </aside>
    <div id="sidebarOverlay" class="fixed inset-0 z-30 hidden bg-slate-900/40 lg:hidden"></div>

    <!-- ============ Konten ============ -->
    <div class="flex-1 min-w-0">
      <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/80 px-4 backdrop-blur sm:px-6 dark:border-slate-800 dark:bg-slate-950/80">
        <button id="btnMenu" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden dark:hover:bg-slate-800"><?= icon('menu') ?></button>
        <div class="flex-1"></div>
        <span class="hidden sm:inline text-sm text-slate-500 dark:text-slate-400">Halo, <strong class="text-slate-700 dark:text-slate-200"><?= htmlspecialchars($_SESSION['username']) ?></strong></span>
        <button id="btnDarkMode" title="Ganti tema" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 transition dark:hover:bg-slate-800"><?= icon('moon') ?></button>
        <button id="btnLogout" title="Keluar" class="flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold text-slate-500 hover:bg-slate-100 transition dark:hover:bg-slate-800">
          <?= icon('logout', 'h-4 w-4') ?><span class="hidden sm:inline">Keluar</span>
        </button>
      </header>
      <main class="container py-6 sm:py-8">
<?php
}

function page_end(array $scripts = []): void
{
    ?>
      </main>
    </div>
  </div>
  <div id="toastContainer" class="toast-container"></div>
  <script>
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("sidebarOverlay");
    const openSidebar = () => { sidebar.classList.remove("-translate-x-full"); overlay.classList.remove("hidden"); };
    const closeSidebar = () => { sidebar.classList.add("-translate-x-full"); overlay.classList.add("hidden"); };
    document.getElementById("btnMenu").addEventListener("click", openSidebar);
    overlay.addEventListener("click", closeSidebar);
  </script>
  <?php foreach ($scripts as $src): ?>
  <script src="<?= htmlspecialchars($src) ?>"></script>
  <?php endforeach; ?>
</body>
</html>
<?php
}
