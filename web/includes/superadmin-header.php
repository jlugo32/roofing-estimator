<?php
/**
 * Super-admin shell. EVERY authed super-admin page must do, in order:
 *
 *   require __DIR__ . '/../includes/bootstrap.php';
 *   $admin = require_super_admin();               // SA gate FIRST (SA_SESSID cookie)
 *   // ... POST handling (csrf_verify + action) goes here, before any output ...
 *   $active = 'companies';                        // nav key (see $nav below)
 *   $page_title = 'Companies';
 *   require PROJECT_ROOT . '/includes/superadmin-header.php';
 *
 * Deliberately dark violet (vs the slate company dashboard shell) so support
 * staff always know they are operating in the PLATFORM OWNER context.
 * login.php is standalone and does NOT use this shell.
 */

declare(strict_types=1);

header('X-Robots-Tag: noindex, nofollow'); // never in search results

$active = $active ?? '';

$nav = [
    'home'      => ['/super-admin/index.php', 'Dashboard'],
    'companies' => ['/super-admin/companies.php', 'Companies'],
    'settings'  => ['/super-admin/platform-settings.php', 'Platform Settings'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= e($page_title ?? 'Super Admin') ?> | <?= e(APP_NAME) ?> Super Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="bg-slate-100 text-slate-800 antialiased" x-data="{sidebar:false}">

<!-- Mobile top bar -->
<div class="lg:hidden flex items-center justify-between bg-violet-950 text-white px-4 h-14 sticky top-0 z-40">
  <button @click="sidebar = true" class="p-2 -ml-2 text-violet-200" aria-label="Open menu">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16" stroke-linecap="round"/></svg>
  </button>
  <span class="font-semibold truncate"><?= e(APP_NAME) ?> &middot; Super Admin</span>
  <a href="/super-admin/login.php?logout=1" class="text-sm text-violet-200">Log out</a>
</div>

<div class="lg:flex min-h-screen">
  <!-- Sidebar -->
  <div x-show="sidebar" x-cloak @click="sidebar = false" class="fixed inset-0 bg-black/40 z-40 lg:hidden"></div>
  <aside class="fixed lg:static inset-y-0 left-0 w-64 bg-violet-950 text-violet-100 z-50 transform transition-transform lg:transform-none flex flex-col"
         :class="sidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
    <div class="px-5 py-4 border-b border-violet-900">
      <p class="font-bold text-white"><?= e(APP_NAME) ?> &middot; Super Admin</p>
      <p class="text-xs text-violet-300 mt-0.5">Platform owner console</p>
    </div>
    <nav class="flex-1 px-3 py-4 space-y-1 text-sm">
      <?php foreach ($nav as $key => [$href, $label]): ?>
      <a href="<?= e($href) ?>"
         class="block px-3 py-2 rounded-md <?= $key === $active ? 'bg-violet-900 text-white font-medium' : 'text-violet-200 hover:bg-violet-900 hover:text-white' ?>">
        <?= e($label) ?>
      </a>
      <?php endforeach; ?>
    </nav>
    <div class="px-5 py-4 border-t border-violet-900 text-sm">
      <p class="truncate text-violet-300"><?= e($admin['username'] ?? '') ?></p>
      <a href="/super-admin/login.php?logout=1" class="text-violet-200 hover:text-white">Log out</a>
    </div>
  </aside>

  <!-- Main -->
  <div class="flex-1 min-w-0">
    <div class="p-4 sm:p-6 lg:p-8 max-w-6xl">
