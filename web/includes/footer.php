</main>
<footer class="bg-slate-900 text-slate-300 mt-16">
  <div class="max-w-6xl mx-auto px-4 sm:px-6 py-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
    <div>
      <p class="font-bold text-white text-lg"><?= e(APP_NAME) ?></p>
      <p class="mt-2 text-sm text-slate-400">Branded instant-estimate software that turns your roofing company's website traffic into qualified leads.</p>
    </div>
    <div>
      <p class="font-semibold text-white text-sm uppercase tracking-wide">Product</p>
      <ul class="mt-3 space-y-2 text-sm">
        <li><a href="/#features" class="hover:text-white">Features</a></li>
        <li><a href="/#pricing" class="hover:text-white">Pricing</a></li>
        <li><a href="/#faq" class="hover:text-white">FAQ</a></li>
      </ul>
    </div>
    <div>
      <p class="font-semibold text-white text-sm uppercase tracking-wide">Account</p>
      <ul class="mt-3 space-y-2 text-sm">
        <li><a href="/signup.php" class="hover:text-white">Start free trial</a></li>
        <li><a href="/login.php" class="hover:text-white">Log in</a></li>
      </ul>
    </div>
  </div>
  <div class="border-t border-slate-800">
    <p class="max-w-6xl mx-auto px-4 sm:px-6 py-4 text-xs text-slate-500">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.</p>
  </div>
</footer>
</body>
</html>
