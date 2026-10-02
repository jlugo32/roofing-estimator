<?php
/**
 * Platform marketing landing page — the front door for roofing company
 * owners deciding whether to sign up. Hero → how-it-works → features →
 * pricing → FAQ → final CTA.
 *
 * $faqs below is the single source of truth for BOTH the rendered FAQ
 * section and the FAQPage JSON-LD, so the two can never drift apart.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

// ── FAQ content: 'answer' renders inside the .answer-box (direct answer
//    first, AEO style); 'detail' adds 1–2 supporting sentences below it. ──
$faqs = [
    [
        'question' => 'How much does roofing estimate software cost?',
        'answer'   => APP_NAME . ' runs on one straightforward monthly plan, and every account starts with a '
            . TRIAL_LENGTH_DAYS . '-day free trial with no credit card required.',
        'detail'   => 'There are no per-lead fees or setup charges, and you can cancel anytime. '
            . 'Create your free account to see current plan pricing before you commit to anything.',
    ],
    [
        'question' => 'Can I customize pricing for my roofing company?',
        'answer'   => 'Yes. Every estimate is calculated with your own numbers, not generic national averages.',
        'detail'   => 'From your dashboard you control per-square material costs, tear-off and permit fees, '
            . 'steep-pitch and multi-story surcharges, and state-by-state regional multipliers. '
            . 'Change a number once and every future estimate uses it instantly.',
    ],
    [
        'question' => 'Does this work with my existing CRM?',
        'answer'   => 'Yes. Every lead is emailed to you the moment it arrives, so ' . APP_NAME
            . ' works alongside any CRM your roofing company already uses.',
        'detail'   => 'Leads also land in the built-in pipeline dashboard where you can track them from new to won, '
            . 'and a REST API is ready for deeper integrations and the upcoming mobile app.',
    ],
    [
        'question' => 'How fast can I get set up?',
        'answer'   => 'Most roofing companies are live in under ten minutes: sign up, adjust your pricing, '
            . 'and share your branded estimator link.',
        'detail'   => 'There is nothing to install and no code is required. Your estimator link works on its own '
            . 'or embedded on the website you already have.',
    ],
    [
        'question' => 'Do homeowners see my roofing company brand or yours?',
        'answer'   => 'Homeowners see your roofing company from start to finish: your estimator page, '
            . 'your estimate emails, your follow-up.',
        'detail'   => APP_NAME . ' stays behind the scenes, so the trust you have built in your local market '
            . 'carries through every estimate.',
    ],
    [
        'question' => 'How do I receive the leads my estimator captures?',
        'answer'   => 'Every completed estimate lands in your lead dashboard and hits your inbox instantly, '
            . 'with the homeowner\'s contact details and the range they saw.',
        'detail'   => 'Each lead includes TCPA contact consent captured at submission, so your team can '
            . 'follow up by phone or email right away.',
    ],
];

// ── How-it-works steps ──
$steps = [
    [
        'title' => 'Sign up and get your branded estimator link',
        'body'  => 'Create your account, fine-tune your pricing, and grab your company\'s unique estimator '
            . 'link — share it anywhere or embed it on your website.',
    ],
    [
        'title' => 'Homeowners complete a 5-step wizard',
        'body'  => 'Visitors answer quick questions about their roof and see an instant range priced with '
            . 'YOUR numbers — materials, surcharges, and state multipliers.',
    ],
    [
        'title' => 'The lead lands in your dashboard and inbox',
        'body'  => 'You get the name, phone, email, and the estimate they saw the second they submit — '
            . 'follow up while the roof is still on their mind.',
    ],
];

// ── Feature grid (icon = 24×24 stroke path, matches the header logo style) ──
$features = [
    [
        'icon'  => 'M10 14a3.5 3.5 0 0 0 5 0l4-4a3.5 3.5 0 0 0-5-5l-1 1M14 10a3.5 3.5 0 0 0-5 0l-4 4a3.5 3.5 0 0 0 5 5l1-1',
        'title' => 'Branded estimator link and embed',
        'body'  => 'Every company gets its own public estimator page to share or embed — homeowners see your name, not ours.',
    ],
    [
        'icon'  => 'M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6',
        'title' => 'You control the pricing',
        'body'  => 'Set per-square material costs, tear-off and permit fees, surcharges, and state multipliers — estimates always use your numbers.',
    ],
    [
        'icon'  => 'M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M8.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M17 11l2 2 4-4',
        'title' => 'Instant lead capture with TCPA consent',
        'body'  => 'Contact details are captured before results are shown, with documented consent to contact — compliant follow-up from day one.',
    ],
    [
        'icon'  => 'M23 6l-9.5 9.5-5-5L1 18M17 6h6v6',
        'title' => 'Lead pipeline dashboard',
        'body'  => 'Track every lead from new to contacted, quoted, and won or lost — with the full estimate attached to each one.',
    ],
    [
        'icon'  => 'M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zM22 6l-10 7L2 6',
        'title' => 'Email notifications',
        'body'  => 'You are notified the moment a lead comes in, and the homeowner receives a branded copy of their estimate.',
    ],
    [
        'icon'  => 'M7 2h10a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zM12 18h.01',
        'title' => 'API-ready for the mobile app',
        'body'  => 'A secure REST API is built in, ready for the upcoming mobile app and custom integrations when you need them.',
    ],
];

// ── JSON-LD: SoftwareApplication + FAQPage (FAQ built from $faqs above) ──
$softwareLd = [
    '@context'            => 'https://schema.org',
    '@type'               => 'SoftwareApplication',
    'name'                => APP_NAME,
    'url'                 => SITE_URL,
    'applicationCategory' => 'BusinessApplication',
    'operatingSystem'     => 'Web',
    'description'         => 'Branded instant roof estimate software that turns roofing company website visitors into qualified leads.',
    'offers'              => [
        '@type'         => 'Offer',
        'price'         => '0',
        'priceCurrency' => 'USD',
        'description'   => TRIAL_LENGTH_DAYS . '-day free trial, then one simple monthly plan. Cancel anytime.',
    ],
];

$faqLd = [
    '@context'   => 'https://schema.org',
    '@type'      => 'FAQPage',
    'mainEntity' => array_map(static fn (array $f): array => [
        '@type'          => 'Question',
        'name'           => $f['question'],
        'acceptedAnswer' => [
            '@type' => 'Answer',
            'text'  => $f['answer'] . ' ' . $f['detail'],
        ],
    ], $faqs),
];

$extra_head = '<script type="application/ld+json">' . "\n"
    . json_encode($softwareLd, JSON_UNESCAPED_SLASHES) . "\n"
    . '</script>' . "\n"
    . '<script type="application/ld+json">' . "\n"
    . json_encode($faqLd, JSON_UNESCAPED_SLASHES) . "\n"
    . '</script>';

$page_title       = 'Instant Roof Estimate Software for Roofing Companies';
$page_description = APP_NAME . ' turns your website visitors into qualified roofing leads with branded instant '
    . 'estimates priced with your own numbers. Free ' . TRIAL_LENGTH_DAYS . '-day trial, no credit card.';
$canonical_path   = '/';

include PROJECT_ROOT . '/includes/header.php';
?>

<!-- ── Hero ── -->
<section class="bg-slate-50 border-b border-slate-200">
  <div class="max-w-6xl mx-auto px-4 sm:px-6 py-16 sm:py-24 text-center">
    <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight max-w-3xl mx-auto" style="color:var(--color-primary)">
      Branded instant roof estimates that turn website visitors into qualified leads
    </h1>
    <p class="mt-5 max-w-2xl mx-auto text-lg text-slate-600">
      <?= e(APP_NAME) ?> gives your roofing company its own online estimator. Homeowners answer five quick
      questions and get a ballpark range priced with your numbers — and you get their contact info the moment they do.
    </p>
    <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
      <a href="/signup.php" class="btn-primary text-base px-6 py-3">Start free trial — no credit card</a>
      <a href="#how-it-works" class="btn-outline text-base px-6 py-3">See how it works</a>
    </div>
    <ul class="mt-8 flex flex-col sm:flex-row gap-2 sm:gap-8 justify-center text-sm font-medium text-slate-600">
      <?php foreach (['Set up in minutes', 'Your own pricing', 'Leads emailed instantly'] as $bullet): ?>
      <li class="flex items-center justify-center gap-2">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="var(--color-success)" stroke-width="3" aria-hidden="true">
          <path d="M20 6L9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <?= e($bullet) ?>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<!-- ── How it works ── -->
<section id="how-it-works" class="max-w-6xl mx-auto px-4 sm:px-6 py-16">
  <h2 class="text-2xl sm:text-3xl font-bold text-center" style="color:var(--color-primary)">How it works</h2>
  <p class="mt-3 text-center text-slate-600 max-w-2xl mx-auto">
    From signup to your first lead in three steps — no installs, no code.
  </p>
  <div class="mt-10 grid gap-8 md:grid-cols-3">
    <?php foreach ($steps as $i => $step): ?>
    <div class="text-center md:text-left">
      <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold mx-auto md:mx-0"
           style="background:var(--color-accent)"><?= $i + 1 ?></div>
      <h3 class="mt-4 text-lg font-semibold"><?= e($step['title']) ?></h3>
      <p class="mt-2 text-sm text-slate-600"><?= e($step['body']) ?></p>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ── Features ── -->
<section id="features" class="bg-slate-50 border-y border-slate-200">
  <div class="max-w-6xl mx-auto px-4 sm:px-6 py-16">
    <h2 class="text-2xl sm:text-3xl font-bold text-center" style="color:var(--color-primary)">
      Everything you need to capture roofing leads
    </h2>
    <p class="mt-3 text-center text-slate-600 max-w-2xl mx-auto">
      One platform: a branded estimator for your visitors, a lead pipeline for your team.
    </p>
    <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($features as $feature): ?>
      <div class="card">
        <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="var(--color-accent)" stroke-width="2" aria-hidden="true">
          <path d="<?= e($feature['icon']) ?>" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <h3 class="mt-3 font-semibold"><?= e($feature['title']) ?></h3>
        <p class="mt-2 text-sm text-slate-600"><?= e($feature['body']) ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── Pricing ── -->
<section id="pricing" class="max-w-6xl mx-auto px-4 sm:px-6 py-16">
  <h2 class="text-2xl sm:text-3xl font-bold text-center" style="color:var(--color-primary)">Simple pricing, no surprises</h2>
  <div class="mt-10 max-w-lg mx-auto card p-8 text-center">
    <h3 class="text-xl font-bold">One plan. Everything included.</h3>
    <p class="mt-3 text-slate-600">
      Try <?= e(APP_NAME) ?> free for <?= TRIAL_LENGTH_DAYS ?> days — no credit card to start. After your
      trial, it&rsquo;s one straightforward monthly plan. Cancel anytime.
    </p>
    <ul class="mt-6 space-y-2 text-sm text-left text-slate-700 max-w-xs mx-auto">
      <?php foreach ([
          'Branded estimator link and website embed',
          'Full control of your estimate pricing',
          'Lead pipeline dashboard',
          'Instant email notifications',
          'Cancel anytime — no contracts',
      ] as $item): ?>
      <li class="flex items-start gap-2">
        <svg class="w-4 h-4 mt-0.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="var(--color-success)" stroke-width="3" aria-hidden="true">
          <path d="M20 6L9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <?= e($item) ?>
      </li>
      <?php endforeach; ?>
    </ul>
    <a href="/signup.php" class="btn-primary mt-8 inline-block text-base px-6 py-3">
      Start your <?= TRIAL_LENGTH_DAYS ?>-day free trial
    </a>
    <p class="mt-3 text-xs text-slate-500">No credit card required. Set up in minutes.</p>
  </div>
</section>

<!-- ── FAQ (rendered from the same $faqs array as the JSON-LD) ── -->
<section id="faq" class="bg-slate-50 border-y border-slate-200">
  <div class="max-w-3xl mx-auto px-4 sm:px-6 py-16">
    <h2 class="text-2xl sm:text-3xl font-bold text-center" style="color:var(--color-primary)">Frequently asked questions</h2>
    <div class="mt-10 space-y-10">
      <?php foreach ($faqs as $faq): ?>
      <div>
        <h3 class="text-lg font-semibold"><?= e($faq['question']) ?></h3>
        <div class="answer-box mt-3"><?= e($faq['answer']) ?></div>
        <p class="mt-3 text-sm text-slate-600"><?= e($faq['detail']) ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── Final CTA band ── -->
<section class="text-white" style="background:var(--color-primary)">
  <div class="max-w-6xl mx-auto px-4 sm:px-6 py-16 text-center">
    <h2 class="text-2xl sm:text-3xl font-bold">Ready to turn your website traffic into roofing jobs?</h2>
    <p class="mt-3 text-slate-200 max-w-xl mx-auto">
      Join the roofing companies capturing leads around the clock with <?= e(APP_NAME) ?>.
    </p>
    <a href="/signup.php" class="btn-primary mt-8 inline-block text-base px-6 py-3">Start free trial — no credit card</a>
  </div>
</section>

<?php include PROJECT_ROOT . '/includes/footer.php'; ?>
