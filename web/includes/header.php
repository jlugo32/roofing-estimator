<?php
/**
 * Public site shell (marketing pages, auth pages). Page sets before including:
 *   $page_title       — <title> (APP_NAME is appended automatically)
 *   $page_description — meta description
 *   $canonical_path   — path starting with "/" for the canonical URL
 *   $extra_head       — optional raw HTML (extra JSON-LD, og:image overrides…)
 *   $hide_nav_cta     — optional, true to hide the signup CTA (e.g. on signup itself)
 */

declare(strict_types=1);

$page_title       = $page_title ?? APP_NAME;
$page_description = $page_description ?? 'Branded roofing estimate software for roofing companies.';
$canonical_path   = $canonical_path ?? '/';
$canonical_url    = SITE_URL . $canonical_path;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($page_title) ?> | <?= e(APP_NAME) ?></title>
<meta name="description" content="<?= e($page_description) ?>">
<link rel="canonical" href="<?= e($canonical_url) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(APP_NAME) ?>">
<meta property="og:title" content="<?= e($page_title) ?>">
<meta property="og:description" content="<?= e($page_description) ?>">
<meta property="og:url" content="<?= e($canonical_url) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($page_title) ?>">
<meta name="twitter:description" content="<?= e($page_description) ?>">
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<link rel="stylesheet" href="/assets/css/main.css">
<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'Organization',
    'name'     => APP_NAME,
    'url'      => SITE_URL,
], JSON_UNESCAPED_SLASHES) ?>
</script>
<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'WebSite',
    'name'     => APP_NAME,
    'url'      => SITE_URL,
], JSON_UNESCAPED_SLASHES) ?>
</script>
<?= $extra_head ?? '' ?>
</head>
<body class="bg-white text-slate-800 antialiased">
<header class="border-b border-slate-200 bg-white sticky top-0 z-40" x-data="{open:false}">
  <div class="max-w-6xl mx-auto px-4 sm:px-6 flex items-center justify-between h-16">
    <a href="/" class="flex items-center gap-2 font-bold text-lg" style="color:var(--color-primary)">
      <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path d="M3 12 12 4l9 8" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M5 10.5V20h14v-9.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <?= e(APP_NAME) ?>
    </a>
    <nav class="hidden md:flex items-center gap-6 text-sm font-medium">
      <a href="/#features" class="hover:text-slate-900 text-slate-600">Features</a>
      <a href="/#how-it-works" class="hover:text-slate-900 text-slate-600">How it works</a>
      <a href="/#pricing" class="hover:text-slate-900 text-slate-600">Pricing</a>
      <a href="/#faq" class="hover:text-slate-900 text-slate-600">FAQ</a>
      <a href="/login.php" class="hover:text-slate-900 text-slate-600">Log in</a>
      <?php if (empty($hide_nav_cta)): ?>
      <a href="/signup.php" class="btn-primary text-sm">Start free trial</a>
      <?php endif; ?>
    </nav>
    <button class="md:hidden p-2 text-slate-600" @click="open = !open" aria-label="Toggle menu">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path x-show="!open" d="M4 6h16M4 12h16M4 18h16" stroke-linecap="round"/>
        <path x-show="open" x-cloak d="M6 6l12 12M6 18L18 6" stroke-linecap="round"/>
      </svg>
    </button>
  </div>
  <nav x-show="open" x-cloak class="md:hidden border-t border-slate-200 bg-white px-4 py-3 space-y-2 text-sm font-medium">
    <a href="/#features" class="block py-1.5 text-slate-600">Features</a>
    <a href="/#how-it-works" class="block py-1.5 text-slate-600">How it works</a>
    <a href="/#pricing" class="block py-1.5 text-slate-600">Pricing</a>
    <a href="/#faq" class="block py-1.5 text-slate-600">FAQ</a>
    <a href="/login.php" class="block py-1.5 text-slate-600">Log in</a>
    <a href="/signup.php" class="btn-primary inline-block text-sm">Start free trial</a>
  </nav>
</header>
<main>
