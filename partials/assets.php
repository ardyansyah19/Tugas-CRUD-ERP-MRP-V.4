<?php
/**
 * Aset Tailwind bersama untuk seluruh halaman (V4).
 *
 * Memakai Tailwind Play CDN (tanpa build step) + blok <style type="text/tailwindcss">
 * berisi @layer components. Pendekatan ini sengaja dipilih supaya seluruh markup
 * yang sudah dihasilkan oleh JavaScript (erp-core.js, script.js, dashboard.js, dst.)
 * tetap memakai nama class yang sama seperti V3 (mis. "btn btn-primary", "card",
 * "badge-blue") — hanya definisi visualnya yang sekarang 100% Tailwind (@apply),
 * bukan CSS manual. Jadi seluruh logika yang sudah diuji di V3 tidak perlu disentuh.
 */
function tailwind_assets(): void
{
    ?>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
  <!-- Set tema sebelum render agar tidak "kedip" saat mode gelap aktif -->
  <script>
    (function () {
      var saved = localStorage.getItem('dark_mode');
      if (saved === '1') document.documentElement.classList.add('dark');
    })();
  </script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          fontFamily: {
            sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            display: ['"Plus Jakarta Sans"', 'Inter', 'ui-sans-serif', 'sans-serif'],
          },
          colors: {
            brand: {
              50: '#eef2ff', 100: '#e0e7ff', 200: '#c7d2fe', 300: '#a5b4fc', 400: '#818cf8',
              500: '#6366f1', 600: '#4f46e5', 700: '#4338ca', 800: '#3730a3', 900: '#312e81', 950: '#1e1b4b',
            },
          },
          boxShadow: {
            soft: '0 2px 10px -2px rgb(15 23 42 / 0.06), 0 8px 24px -8px rgb(15 23 42 / 0.08)',
            softer: '0 1px 2px rgb(15 23 42 / 0.04), 0 4px 12px -4px rgb(15 23 42 / 0.06)',
          },
          animation: {
            'fade-in': 'fadeIn .15s ease-out',
            'pop-in': 'popIn .18s cubic-bezier(.2,0,0,1.2)',
            'slide-up': 'slideUp .25s ease-out',
          },
          keyframes: {
            fadeIn: { from: { opacity: 0 }, to: { opacity: 1 } },
            popIn: { from: { opacity: 0, transform: 'scale(.96) translateY(6px)' }, to: { opacity: 1, transform: 'scale(1) translateY(0)' } },
            slideUp: { from: { opacity: 0, transform: 'translateY(10px)' }, to: { opacity: 1, transform: 'translateY(0)' } },
          },
        },
      },
    };
  </script>
  <style type="text/tailwindcss">
    @layer base {
      html { @apply scroll-smooth; }
      body {
        @apply font-sans bg-slate-50 text-slate-800 antialiased;
        @apply dark:bg-slate-950 dark:text-slate-200;
      }
      h1, h2, h3 { @apply font-display; }
      ::selection { @apply bg-brand-200 text-brand-900; }
      /* Scrollbar tipis, tema-aware */
      ::-webkit-scrollbar { @apply h-2 w-2; }
      ::-webkit-scrollbar-track { @apply bg-transparent; }
      ::-webkit-scrollbar-thumb { @apply bg-slate-300 rounded-full dark:bg-slate-700; }
    }

    @layer components {
      /* ---------- Struktur & kartu ---------- */
      .container { @apply max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8; }
      .card {
        @apply bg-white rounded-2xl shadow-soft border border-slate-200/70 p-5 sm:p-6;
        @apply dark:bg-slate-900 dark:border-slate-800;
      }
      .stack > * + * { @apply mt-5; }
      .mt { @apply mt-5; }
      .grid-2 { @apply grid grid-cols-1 xl:grid-cols-2 gap-5; }
      .muted { @apply text-slate-500 dark:text-slate-400; }
      .small { @apply text-xs; }
      .right { @apply text-right; }
      .num { @apply text-right tabular-nums whitespace-nowrap; }
      .nowrap { @apply whitespace-nowrap; }
      .hidden-init { @apply hidden; }
      .section-title { @apply text-base font-bold text-slate-800 mb-3 dark:text-slate-100; }

      .page-head { @apply flex flex-wrap items-start justify-between gap-4 mb-6; }
      .page-head h1 { @apply text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white; }
      .page-head p { @apply text-sm text-slate-500 max-w-2xl mt-1 dark:text-slate-400; }

      /* ---------- Tombol ---------- */
      .btn {
        @apply inline-flex items-center justify-center gap-1.5 rounded-xl px-4 py-2.5 text-sm font-semibold
               transition-all duration-150 select-none active:scale-[.97] disabled:opacity-50 disabled:pointer-events-none
               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900;
      }
      .btn-sm { @apply px-3 py-1.5 text-xs rounded-lg; }
      .btn-block { @apply w-full; }
      .btn-primary { @apply bg-brand-600 text-white shadow-sm shadow-brand-600/30 hover:bg-brand-700 focus-visible:ring-brand-500; }
      .btn-secondary { @apply bg-slate-100 text-slate-700 hover:bg-slate-200 focus-visible:ring-slate-400 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700; }
      .btn-danger { @apply bg-red-50 text-red-700 hover:bg-red-100 focus-visible:ring-red-400 dark:bg-red-950 dark:text-red-300 dark:hover:bg-red-900; }
      .btn-success { @apply bg-emerald-600 text-white hover:bg-emerald-700 focus-visible:ring-emerald-500; }
      .btn-warning { @apply bg-amber-100 text-amber-800 hover:bg-amber-200 focus-visible:ring-amber-400 dark:bg-amber-950 dark:text-amber-300; }
      .btn-info { @apply bg-sky-100 text-sky-700 hover:bg-sky-200 focus-visible:ring-sky-400 dark:bg-sky-950 dark:text-sky-300; }

      /* ---------- Badge ---------- */
      .badge { @apply inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold whitespace-nowrap bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300; }
      .badge-blue   { @apply bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300; }
      .badge-green  { @apply bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300; }
      .badge-amber  { @apply bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300; }
      .badge-red    { @apply bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300; }
      .badge-gray   { @apply bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300; }
      .badge-purple { @apply bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-300; }

      /* ---------- Statistik dashboard ---------- */
      .stats-grid { @apply grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 mb-6; }
      .stat-card {
        @apply flex flex-col gap-1 bg-white rounded-2xl p-5 shadow-soft border border-slate-200/70 cursor-pointer
               transition-all hover:-translate-y-0.5 hover:shadow-lg
               dark:bg-slate-900 dark:border-slate-800;
      }
      .stat-value { @apply text-2xl font-extrabold text-brand-600 dark:text-brand-400; }
      .stat-label { @apply text-xs font-medium text-slate-500 dark:text-slate-400; }

      /* ---------- Toolbar & form ---------- */
      .toolbar { @apply flex flex-wrap gap-3 items-center mb-5; }
      .toolbar input[type="search"] {
        @apply flex-1 min-w-[220px] rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm outline-none
               transition focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-100
               dark:border-slate-700 dark:bg-slate-800 dark:focus:bg-slate-900 dark:focus:ring-brand-900;
      }
      .toolbar select, form select {
        @apply rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm outline-none
               transition focus:border-brand-500 focus:ring-2 focus:ring-brand-100
               dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:focus:ring-brand-900;
      }
      .form-grid { @apply grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-4; }
      .inline-form { @apply grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end; }
      form label { @apply block text-sm font-semibold text-slate-700 dark:text-slate-300; }
      form input:not([type="checkbox"]), form textarea {
        @apply mt-1.5 block w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-800 outline-none
               transition focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-100
               dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:focus:bg-slate-900 dark:focus:ring-brand-900;
      }
      form input[type="file"] { @apply mt-1.5 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100 dark:file:bg-brand-950 dark:file:text-brand-300; }
      .field-hint { @apply block font-normal text-xs text-slate-400 mt-1 dark:text-slate-500; }
      .req::after { content: " *"; @apply text-red-500; }
      .checkbox-label { @apply inline-flex items-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-300; }
      .checkbox-label input { @apply h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500; }
      .form-actions { @apply flex justify-end gap-2 mt-6 pt-4 border-t border-slate-100 dark:border-slate-800; }
      .hint-box { @apply bg-brand-50 border-l-4 border-brand-500 text-brand-800 text-sm rounded-r-lg px-4 py-2.5 mb-4 dark:bg-brand-950 dark:text-brand-200; }
      .warn-box { @apply bg-amber-50 border-l-4 border-amber-500 text-amber-800 text-sm rounded-r-lg px-4 py-2.5 mb-4 dark:bg-amber-950 dark:text-amber-200; }
      .alert { @apply rounded-xl px-4 py-3 text-sm font-medium mb-4; }
      .alert-error { @apply bg-red-50 text-red-700 border border-red-100 dark:bg-red-950 dark:text-red-300 dark:border-red-900; }
      .alert-success { @apply bg-emerald-50 text-emerald-700 border border-emerald-100 dark:bg-emerald-950 dark:text-emerald-300; }

      /* ---------- Tabel ---------- */
      .table-wrapper { @apply overflow-x-auto rounded-xl border border-slate-100 dark:border-slate-800; }
      .table-wrapper table, table.table-plain { @apply w-full border-collapse text-sm min-w-[850px]; }
      table.table-plain { @apply min-w-0; }
      th, td { @apply px-4 py-3 text-left border-b border-slate-100 dark:border-slate-800; }
      thead th { @apply bg-slate-50 text-slate-500 text-xs font-bold uppercase tracking-wide select-none dark:bg-slate-800/60 dark:text-slate-400; }
      th[data-sort] { @apply cursor-pointer hover:text-brand-600 transition; }
      tbody tr { @apply transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40; }
      td.loading, td.empty { @apply text-center text-slate-400 py-10 dark:text-slate-500; }
      .loading { @apply text-center text-slate-400 py-6 text-sm dark:text-slate-500; }
      .empty-state { @apply text-center text-slate-400 py-10 dark:text-slate-500; }
      .mini { @apply w-20 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 dark:border-slate-700 dark:bg-slate-800 dark:focus:ring-brand-900; }
      input.chk, input#chkAll { @apply h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800; }
      td.actions, th ~ th:last-child { @apply whitespace-nowrap; }

      .avatar { @apply h-9 w-9 rounded-full object-cover block ring-2 ring-white dark:ring-slate-900; }
      .avatar-placeholder { @apply flex items-center justify-center bg-gradient-to-br from-brand-500 to-violet-600 text-white font-bold uppercase; }

      /* ---------- Pagination ---------- */
      .pagination { @apply flex flex-wrap items-center justify-between gap-3 mt-4 text-sm; }
      .pagination-info { @apply text-slate-500 dark:text-slate-400; }
      .pagination-buttons { @apply flex items-center gap-1; }
      .pagination-buttons .btn { @apply px-3 py-1.5 min-w-[36px]; }

      /* ---------- Modal ---------- */
      .modal {
        @apply fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fade-in;
      }
      .modal-content {
        @apply w-full max-w-lg max-h-[88vh] overflow-y-auto rounded-2xl bg-white shadow-2xl animate-pop-in
               dark:bg-slate-900 dark:ring-1 dark:ring-slate-800;
      }
      .modal-content.modal-small { @apply max-w-sm; }
      .modal-content.modal-wide { @apply max-w-3xl; }
      .modal-header { @apply flex items-center justify-between px-6 py-4 border-b border-slate-100 sticky top-0 bg-white/90 backdrop-blur dark:bg-slate-900/90 dark:border-slate-800; }
      .modal-header h2 { @apply text-lg font-bold text-slate-800 dark:text-white; }
      .modal-body, .modal form { @apply px-6 py-5; }
      .modal .form-actions { @apply px-0; }
      .close {
        @apply flex h-8 w-8 items-center justify-center rounded-full text-xl leading-none text-slate-400 border-0 bg-transparent cursor-pointer
               hover:bg-slate-100 hover:text-slate-600 transition dark:hover:bg-slate-800 dark:hover:text-slate-200;
      }

      /* ---------- Toast ---------- */
      .toast-container { @apply fixed bottom-5 right-5 z-[60] flex flex-col gap-2 items-end; }
      .toast {
        @apply pointer-events-auto max-w-sm rounded-xl border-l-4 bg-white px-4 py-3 text-sm font-medium shadow-soft
               opacity-0 translate-y-2 transition-all duration-300
               dark:bg-slate-800 dark:text-slate-100;
      }
      .toast.show { @apply opacity-100 translate-y-0; }
      .toast-success { @apply border-emerald-500; }
      .toast-error { @apply border-red-500; }

      /* ---------- Kelas & progress ---------- */
      .kelas-grid { @apply grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6; }
      .kelas-card { @apply card !p-5; }
      .kelas-card h3 { @apply text-lg font-bold text-slate-800 dark:text-white; }
      .kelas-card .big { @apply text-3xl font-extrabold text-brand-600 dark:text-brand-400; }
      .progress { @apply h-2 rounded-full bg-slate-100 overflow-hidden my-2.5 dark:bg-slate-800; }
      .progress > span { @apply block h-full rounded-full bg-gradient-to-r from-brand-500 to-violet-500; }

      /* ---------- Tab ---------- */
      .tabs { @apply flex gap-1 border-b border-slate-200 mb-4 flex-wrap dark:border-slate-800; }
      .tab {
        @apply bg-transparent border-0 border-b-2 border-transparent px-4 py-2.5 -mb-px text-sm font-semibold
               text-slate-500 cursor-pointer transition hover:text-brand-600 dark:text-slate-400;
      }
      .tab.active { @apply text-brand-600 border-brand-600; }

      /* ---------- Tabel MRP time-phased ---------- */
      .mrp-item { @apply rounded-2xl border border-slate-200 overflow-hidden mb-4 dark:border-slate-800; }
      .mrp-item-head { @apply flex flex-wrap items-center justify-between gap-2 px-4 py-3 bg-brand-50/60 dark:bg-brand-950/40; }
      .mrp-item-head strong { @apply text-sm font-bold text-slate-800 dark:text-slate-100; }
      .mrp-scroll { @apply overflow-x-auto; }
      table.mrp { @apply w-full text-xs min-w-0; }
      table.mrp th, table.mrp td { @apply px-2.5 py-1.5 text-right whitespace-nowrap border-b border-slate-100 dark:border-slate-800; }
      table.mrp th:first-child, table.mrp td:first-child { @apply text-left sticky left-0 bg-white font-semibold z-[1] dark:bg-slate-900; }
      table.mrp thead th { @apply bg-brand-50/60 text-[11px] dark:bg-brand-950/30; }
      table.mrp tr.row-net td { @apply text-amber-600 dark:text-amber-400; }
      table.mrp tr.row-plan td { @apply text-brand-700 font-bold dark:text-brand-400; }
      table.mrp td.zero { @apply text-slate-300 dark:text-slate-700; }
      .legend { @apply text-xs text-slate-400 mb-3 dark:text-slate-500; }
      .check-col { @apply w-8; }
      .tree-l1 td:first-child { @apply pl-4; } .tree-l2 td:first-child { @apply pl-8; }
      .tree-l3 td:first-child { @apply pl-12; } .tree-l4 td:first-child { @apply pl-16; }

      /* ---------- Ringkasan info (dl) ---------- */
      .kv { @apply grid grid-cols-[130px_1fr] gap-x-3 gap-y-2 text-sm mb-4; }
      .kv dt { @apply text-slate-400 dark:text-slate-500; }
      .kv dd { @apply font-semibold m-0 text-slate-700 dark:text-slate-200; }

      /* ---------- Foto upload preview ---------- */
      .foto-preview-wrap { @apply flex items-center gap-3 mt-2; }
      .foto-preview-wrap img { @apply h-14 w-14 rounded-full object-cover ring-2 ring-slate-100 dark:ring-slate-800; }
    }
  </style>
<?php
}
