<?php
/**
 * Billing — subscription status + trial countdown. Phase 1: no live
 * Stripe calls; renders a clearly marked placeholder until keys exist.
 */

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$auth = require_company_auth(); // FIRST — tenant gate
$cid  = $auth['company_id'];

$company = $auth['company'];
$status  = (string) $company['subscription_status'];

$trialDaysLeft = null;
if ($status === 'trial' && $company['trial_ends_at']) {
    $trialDaysLeft = max(0, (int) floor((strtotime($company['trial_ends_at']) - time()) / 86400));
}

$statusExplanations = [
    'trial'     => 'You are on a free trial with full access to every feature. Pick a plan before it ends to keep your estimator live.',
    'active'    => 'Your subscription is active — your estimator and dashboard are fully available.',
    'past_due'  => 'Your last payment failed. Update your payment method to avoid interruption to your estimator and dashboard.',
    'suspended' => 'This account is suspended. Contact support to restore access.',
    'cancelled' => 'This subscription has been cancelled.',
];

// Secrets live in config/config.php; guard in case the constant isn't defined yet.
$stripeConfigured = defined('STRIPE_SECRET_KEY') && STRIPE_SECRET_KEY !== '';

$active     = 'billing';
$page_title = 'Billing';
require PROJECT_ROOT . '/includes/dashboard-header.php';
?>

<h1 class="text-2xl font-bold mb-6">Billing</h1>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
  <!-- Subscription status -->
  <div class="card">
    <h2 class="font-semibold mb-3">Subscription</h2>
    <div class="flex items-center gap-3 mb-3">
      <span class="badge badge-<?= e($status) ?>"><?= e(str_replace('_', ' ', $status)) ?></span>
      <?php if ($trialDaysLeft !== null): ?>
      <span class="text-sm font-medium text-amber-700">
        <?= $trialDaysLeft ?> day<?= $trialDaysLeft === 1 ? '' : 's' ?> left
        (ends <?= e(date('M j, Y', strtotime($company['trial_ends_at']))) ?>)
      </span>
      <?php endif; ?>
    </div>
    <p class="text-sm text-slate-600">
      <?= e($statusExplanations[$status] ?? 'Contact support about your subscription.') ?>
    </p>
  </div>

  <!-- Stripe actions / placeholder -->
  <?php if (!$stripeConfigured): ?>
  <div class="card bg-slate-50 border border-dashed border-slate-300">
    <h2 class="font-semibold mb-2 text-slate-500">TODO: Stripe not configured</h2>
    <p class="text-sm text-slate-500">
      Checkout and the billing portal will appear here once Stripe API keys are added to
      <code class="text-xs bg-slate-200 rounded px-1 py-0.5">config/config.php</code>.
      Your access continues as normal in the meantime — nothing is interrupted.
    </p>
  </div>
  <?php else: ?>
  <div class="card">
    <h2 class="font-semibold mb-3">Manage your plan</h2>
    <p class="text-sm text-slate-500 mb-4">Subscribe to a plan or manage your existing payment details.</p>
    <div class="flex flex-wrap gap-2">
      <?php
        // TODO(Phase 2 billing): replace '#' with real endpoints that create a
        // Stripe Checkout Session (subscribe) and a Stripe Billing Portal
        // session (manage), using $company['stripe_customer_id'] scoped to $cid.
      ?>
      <a href="#" class="btn-primary">Subscribe</a>
      <a href="#" class="btn-outline">Manage billing</a>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php require PROJECT_ROOT . '/includes/dashboard-footer.php'; ?>
