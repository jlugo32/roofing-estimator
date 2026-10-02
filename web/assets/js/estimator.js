/**
 * Public estimator wizard controller — vanilla JS, no dependencies.
 *
 * Server config arrives via window.ESTIMATOR (injected by estimate/index.php):
 *   { slug, honeypotField, apiUrl }
 *
 * Flow: steps 1-3 collect answers → step 4 fetches a blurred "preview"
 * teaser → step 5 captures the lead ("submit") and swaps in the success
 * panel. All option lists are server-rendered; this file only reads the
 * data-group/data-value attributes, so PHP stays the single source of truth.
 */
(function () {
  'use strict';

  var CFG = window.ESTIMATOR || {};
  var TOTAL_STEPS = 5;
  var currentStep = 1;

  // Answers collected across steps. Keys mirror the API contract exactly.
  var state = {
    zip_code: '',
    state: '',
    home_sqft: 0,
    stories: '',
    roof_material_current: 'unknown',
    roof_material_desired: '',
    roof_age_years: '',
    roof_condition: '',
    pitch: '',
    tear_off: ''
  };

  // Pragmatic email shape check — the server does the authoritative validation.
  var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

  // ────────────────────────────────────────────────────────────
  // DOM helpers
  // ────────────────────────────────────────────────────────────

  function qs(sel) { return document.querySelector(sel); }
  function qsa(sel) { return Array.prototype.slice.call(document.querySelectorAll(sel)); }

  /** Show (message) or clear (falsy) the inline error slot for a field key. */
  function fieldError(key, message) {
    var el = qs('[data-error-for="' + key + '"]');
    if (!el) { return; }
    el.textContent = message || '';
    el.classList.toggle('hidden', !message);
  }

  /** Show (message) or clear (falsy) the step-level error region. */
  function stepError(step, message) {
    var el = qs('#step-' + step + '-error');
    if (!el) { return; }
    el.textContent = message || '';
    el.classList.toggle('hidden', !message);
  }

  /** Clear every error currently visible inside a step panel. */
  function clearStepErrors(step) {
    var panel = qs('#step-' + step);
    if (!panel) { return; }
    Array.prototype.forEach.call(panel.querySelectorAll('.field-error'), function (el) {
      el.textContent = '';
      el.classList.add('hidden');
    });
  }

  // ────────────────────────────────────────────────────────────
  // Formatting
  // ────────────────────────────────────────────────────────────

  /** Integer cents → "$12,345" (whole dollars — estimate ranges, cents are noise). */
  function centsToDollars(cents) {
    return '$' + Math.round(cents / 100).toLocaleString('en-US');
  }

  function formatNumber(n) {
    return Number(n).toLocaleString('en-US');
  }

  // ────────────────────────────────────────────────────────────
  // Validation (per step, before advancing)
  // ────────────────────────────────────────────────────────────

  function validateStep(step) {
    var ok = true;

    if (step === 1) {
      state.zip_code = qs('#f-zip').value.trim();
      state.state = qs('#f-state').value;
      if (!/^\d{5}$/.test(state.zip_code)) {
        fieldError('zip_code', 'Please enter a 5-digit ZIP code.');
        ok = false;
      }
      if (!state.state) {
        fieldError('state', 'Please select your state.');
        ok = false;
      }
    }

    if (step === 2) {
      var sqft = parseInt(qs('#f-sqft').value, 10);
      if (isNaN(sqft) || sqft < 200 || sqft > 20000) {
        fieldError('home_sqft', 'Please enter a home size between 200 and 20,000 sq ft.');
        ok = false;
      } else {
        state.home_sqft = sqft;
      }
      if (!state.stories) {
        fieldError('stories', 'Please choose the number of stories.');
        ok = false;
      }
    }

    if (step === 3) {
      state.roof_material_current = qs('#f-material-current').value || 'unknown';
      var age = qs('#f-roof-age').value.trim();
      if (age !== '' && (!/^\d{1,3}$/.test(age) || parseInt(age, 10) > 120)) {
        fieldError('roof_age_years', 'Roof age should be between 0 and 120 years (or leave it blank).');
        ok = false;
      } else {
        state.roof_age_years = age;
      }
      var requiredTiles = [
        ['roof_material_desired', 'Please pick the material you want.'],
        ['roof_condition', 'Please pick the closest condition.'],
        ['pitch', 'Please pick a pitch (or "Not sure").'],
        ['tear_off', 'Please pick an option (or "Not sure").']
      ];
      requiredTiles.forEach(function (pair) {
        if (!state[pair[0]]) {
          fieldError(pair[0], pair[1]);
          ok = false;
        }
      });
    }

    if (step === 5) {
      var name = qs('#f-name').value.trim();
      var email = qs('#f-email').value.trim();
      var phoneDigits = qs('#f-phone').value.replace(/\D/g, '');
      if (!name) {
        fieldError('name', 'Please enter your name.');
        ok = false;
      }
      if (!EMAIL_RE.test(email)) {
        fieldError('email', 'Please enter a valid email address.');
        ok = false;
      }
      if (phoneDigits.length < 10) {
        fieldError('phone', 'Please enter a valid phone number (at least 10 digits).');
        ok = false;
      }
      if (!qs('#f-consent').checked) {
        fieldError('consent_tcpa', 'Please check the consent box so we can send your estimate.');
        ok = false;
      }
    }

    return ok;
  }

  // ────────────────────────────────────────────────────────────
  // Navigation
  // ────────────────────────────────────────────────────────────

  function goToStep(step) {
    currentStep = step;

    qsa('.wizard-panel').forEach(function (panel) {
      panel.classList.toggle('hidden', parseInt(panel.getAttribute('data-step'), 10) !== step);
    });

    qsa('[data-step-dot]').forEach(function (dot) {
      var n = parseInt(dot.getAttribute('data-step-dot'), 10);
      dot.classList.toggle('is-active', n === step);
      dot.classList.toggle('is-done', n < step);
    });

    // Entering the teaser always refetches — answers may have changed via Back.
    if (step === 4) { loadPreview(); }

    scrollToCard();
  }

  function scrollToCard() {
    var card = qs('#wizard-card');
    if (!card) { return; }
    var top = card.getBoundingClientRect().top + window.pageYOffset - 16;
    window.scrollTo({ top: Math.max(top, 0), behavior: 'smooth' });
  }

  // ────────────────────────────────────────────────────────────
  // API plumbing
  // ────────────────────────────────────────────────────────────

  /** POST JSON; resolves to { status, body } (body {} when JSON parse fails). */
  function apiPost(payload) {
    return fetch(CFG.apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function (res) {
      return res.json()
        .catch(function () { return {}; })
        .then(function (body) { return { status: res.status, body: body }; });
    });
  }

  function errorMessage(result) {
    if (result.status === 429) {
      return 'You’ve requested quite a few estimates — please wait a few minutes and try again.';
    }
    return (result.body && result.body.error) || 'Something went wrong. Please try again.';
  }

  /** Roof answers shared by both actions, shaped exactly like the API expects. */
  function roofPayload() {
    return {
      slug: CFG.slug,
      zip_code: state.zip_code,
      state: state.state,
      home_sqft: Number(state.home_sqft),
      stories: Number(state.stories),
      roof_material_current: state.roof_material_current,
      roof_material_desired: state.roof_material_desired,
      roof_age_years: state.roof_age_years === '' ? null : Number(state.roof_age_years),
      roof_condition: state.roof_condition,
      pitch: state.pitch,
      tear_off: state.tear_off
    };
  }

  // ────────────────────────────────────────────────────────────
  // Step 4: teaser preview
  // ────────────────────────────────────────────────────────────

  function teaserShow(which) {
    qs('#teaser-loading').classList.toggle('hidden', which !== 'loading');
    qs('#teaser-error').classList.toggle('hidden', which !== 'error');
    qs('#teaser-content').classList.toggle('hidden', which !== 'content');
  }

  function loadPreview() {
    teaserShow('loading');
    apiPost(Object.assign({ action: 'preview' }, roofPayload()))
      .then(function (result) {
        var d = result.body && result.body.data;
        if (result.status === 200 && result.body.success && d) {
          qs('#teaser-sqft').textContent = formatNumber(d.roof_sqft_estimate);
          qs('#teaser-range').textContent =
            centsToDollars(d.estimate_low_cents) + ' – ' + centsToDollars(d.estimate_high_cents);
          qs('#teaser-monthly').textContent = centsToDollars(d.financing_monthly_cents);
          teaserShow('content');
        } else {
          qs('#teaser-error-msg').textContent = errorMessage(result);
          teaserShow('error');
        }
      })
      .catch(function () {
        qs('#teaser-error-msg').textContent =
          'Could not reach the server. Check your connection and try again.';
        teaserShow('error');
      });
  }

  // ────────────────────────────────────────────────────────────
  // Step 5: lead submit → success panel
  // ────────────────────────────────────────────────────────────

  function handleSubmit(evt) {
    evt.preventDefault();
    clearStepErrors(5);
    if (!validateStep(5)) { return; }

    var btn = qs('#submit-btn');
    var originalLabel = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Calculating…';

    var payload = Object.assign({ action: 'submit' }, roofPayload(), {
      name: qs('#f-name').value.trim(),
      email: qs('#f-email').value.trim(),
      phone: qs('#f-phone').value.trim(),
      address: qs('#f-address').value.trim(),
      consent_tcpa: 1
    });
    // Honeypot: always sent, value read from the DOM (stays '' for humans).
    payload[CFG.honeypotField] = (qs('#f-hp') && qs('#f-hp').value) || '';

    apiPost(payload)
      .then(function (result) {
        var d = result.body && result.body.data;
        if (result.status === 200 && result.body.success && d) {
          renderSuccess(d);
        } else {
          stepError(5, errorMessage(result));
          btn.disabled = false;
          btn.textContent = originalLabel;
        }
      })
      .catch(function () {
        stepError(5, 'Could not reach the server. Check your connection and try again.');
        btn.disabled = false;
        btn.textContent = originalLabel;
      });
  }

  function renderSuccess(d) {
    if (d.roof_sqft_estimate) {
      qs('#success-sqft').textContent = formatNumber(d.roof_sqft_estimate);
    }
    if (d.estimate_low_cents && d.estimate_high_cents) {
      qs('#success-range').textContent =
        centsToDollars(d.estimate_low_cents) + ' – ' + centsToDollars(d.estimate_high_cents);
    }
    if (d.financing_monthly_cents > 0) {
      qs('#success-monthly').textContent = centsToDollars(d.financing_monthly_cents);
      qs('#success-financing').classList.remove('hidden');
    }
    var link = qs('#success-results-link');
    if (d.results_url) {
      link.href = d.results_url;
    } else {
      link.classList.add('hidden');
    }

    // Swap the wizard out for the success panel; all dots read as done.
    qsa('.wizard-panel').forEach(function (panel) { panel.classList.add('hidden'); });
    qsa('[data-step-dot]').forEach(function (dot) {
      dot.classList.remove('is-active');
      dot.classList.add('is-done');
    });
    qs('#panel-success').classList.remove('hidden');
    scrollToCard();
  }

  // ────────────────────────────────────────────────────────────
  // Wiring
  // ────────────────────────────────────────────────────────────

  function init() {
    // Option tiles: one selection per data-group, stored under the group key.
    qsa('.option-tile[data-group]').forEach(function (tile) {
      tile.addEventListener('click', function () {
        var group = tile.getAttribute('data-group');
        qsa('.option-tile[data-group="' + group + '"]').forEach(function (t) {
          t.classList.remove('is-selected');
        });
        tile.classList.add('is-selected');
        state[group] = tile.getAttribute('data-value');
        fieldError(group, '');
      });
    });

    // Forward navigation validates the CURRENT step before moving on.
    qsa('[data-next]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        clearStepErrors(currentStep);
        var target = parseInt(btn.getAttribute('data-next'), 10);
        if (target >= 1 && target <= TOTAL_STEPS && validateStep(currentStep)) {
          goToStep(target);
        }
      });
    });

    // Back navigation never validates.
    qsa('[data-back]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        goToStep(parseInt(btn.getAttribute('data-back'), 10));
      });
    });

    // Clear a field's inline error as soon as the user edits it.
    [['#f-zip', 'zip_code'], ['#f-state', 'state'], ['#f-sqft', 'home_sqft'],
     ['#f-roof-age', 'roof_age_years'], ['#f-name', 'name'], ['#f-email', 'email'],
     ['#f-phone', 'phone'], ['#f-consent', 'consent_tcpa']].forEach(function (pair) {
      var el = qs(pair[0]);
      if (el) {
        el.addEventListener('input', function () { fieldError(pair[1], ''); });
        el.addEventListener('change', function () { fieldError(pair[1], ''); });
      }
    });

    var retry = qs('#teaser-retry');
    if (retry) { retry.addEventListener('click', loadPreview); }

    var form = qs('#lead-form');
    if (form) { form.addEventListener('submit', handleSubmit); }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init(); // script is deferred, so the DOM is already parsed
  }
})();
