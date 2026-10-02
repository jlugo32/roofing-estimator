<?php
/**
 * Company dashboard shell. EVERY dashboard page must do, in order:
 *
 *   require __DIR__ . '/../includes/bootstrap.php';
 *   $auth = require_company_auth();               // tenant gate FIRST
 *   $active = 'clients';                          // nav key (see $nav below)
 *   $page_title = 'Clients';
 *   require PROJECT_ROOT . '/includes/dashboard-header.php';
 *
 * All queries on the page filter by $auth['company_id'].
 */

declare(strict_types=1);

header('X-Robots-Tag: noindex, nofollow'); // never in search results

$company = $auth['company'];
$user    = $auth['user'];
$active  = $active ?? '';

$nav = [
    'home'             => ['/dashboard/index.php', 'Dashboard'],
    'clients'          => ['/dashboard/clients.php', 'Clients & Leads'],
    'estimates'        => ['/dashboard/estimates.php', 'Estimates'],
    'pricing'          => ['/dashboard/pricing-settings.php', 'Pricing Settings'],
    'company-settings' => ['/dashboard/company-settings.php', 'Company Settings'],
    'account'          => ['/dashboard/account.php', 'Account & API'],
    'billing'          => ['/dashboard/billing.php', 'Billing'],
];

$trialDaysLeft = null;
if ($company['subscription_status'] === 'trial' && $company['trial_ends_at']) {
    $trialDaysLeft = max(0, (int) floor((strtotime($company['trial_ends_at']) - time()) / 86400));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= e($page_title ?? 'Dashboard') ?> | <?= e(APP_NAME) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="bg-slate-100 text-slate-800 antialiased" x-data="{sidebar:false}">

<!-- Mobile top bar -->
<div class="lg:hidden flex items-center justify-between bg-white border-b border-slate-200 px-4 h-14 sticky top-0 z-40">
  <button @click="sidebar = true" class="p-2 -ml-2 text-slate-600" aria-label="Open menu">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16" stroke-linecap="round"/></svg>
  </button>
  <span class="font-semibold truncate"><?= e($company['name']) ?></span>
  <a href="/logout.php" class="text-sm text-slate-500">Log out</a>
</div>

<div class="lg:flex min-h-screen">
  <!-- Sidebar -->
  <div x-show="sidebar" x-cloak @click="sidebar = false" class="fixed inset-0 bg-black/40 z-40 lg:hidden"></div>
  <aside class="fixed lg:static inset-y-0 left-0 w-64 bg-slate-900 text-slate-200 z-50 transform transition-transform lg:transform-none flex flex-col"
         :class="sidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
    <div class="px-5 py-4 border-b border-slate-800">
      <p class="font-bold text-white"><?= e(APP_NAME) ?></p>
      <p class="text-xs text-slate-400 mt-0.5 truncate"><?= e($company['name']) ?></p>
    </div>
    <nav class="flex-1 px-3 py-4 space-y-1 text-sm">
      <?php foreach ($nav as $key => [$href, $label]): ?>
      <a href="<?= e($href) ?>"
         class="block px-3 py-2 rounded-md <?= $key === $active ? 'bg-slate-800 text-white font-medium' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
        <?= e($label) ?>
      </a>
      <?php endforeach; ?>
      <a href="/estimate/<?= e($company['slug']) ?>" target="_blank" rel="noopener"
         class="block px-3 py-2 rounded-md text-slate-300 hover:bg-slate-800 hover:text-white">
        Public estimator ↗
      </a>
    </nav>
    <div class="px-5 py-4 border-t border-slate-800 text-sm">
      <p class="truncate text-slate-400"><?= e($user['name']) ?></p>
      <a href="/logout.php" class="text-slate-300 hover:text-white">Log out</a>
    </div>
  </aside>

  <!-- Main -->
  <div class="flex-1 min-w-0">
    <?php if (!empty($_SESSION['impersonated_by_sa'])): ?>
    <div class="bg-violet-700 text-white text-sm px-4 py-2 text-center">
      Support mode — you are impersonating <strong><?= e($company['name']) ?></strong> as super admin.
      <a href="/logout.php" class="underline font-medium">End session</a>
    </div>
    <?php endif; ?>
    <?php if ($trialDaysLeft !== null): ?>
    <div class="bg-amber-100 border-b border-amber-200 text-amber-900 text-sm px-4 py-2 text-center">
      Free trial — <strong><?= $trialDaysLeft ?> day<?= $trialDaysLeft === 1 ? '' : 's' ?> left</strong>.
      <a href="/dashboard/billing.php" class="underline font-medium">Choose a plan</a>
    </div>
    <?php elseif ($company['subscription_status'] === 'past_due'): ?>
    <div class="bg-red-100 border-b border-red-200 text-red-900 text-sm px-4 py-2 text-center">
      Payment past due — <a href="/dashboard/billing.php" class="underline font-medium">update billing</a> to avoid interruption.
    </div>
    <?php endif; ?>
    <div class="p-4 sm:p-6 lg:p-8 max-w-6xl">
