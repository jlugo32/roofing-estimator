/**
 * Dashboard behaviors — vanilla JS, no dependencies.
 *
 * (a) [data-copy="selector"]  → copies target input/element text to clipboard
 * (b) [data-confirm="msg"]    → confirm() gate on form submit / button click
 * (c) .flash                  → auto-dismiss after 4s with a fade
 * (d) tr[data-href]           → whole-row navigation (ignores inner links/buttons)
 */
(function () {
  'use strict';

  // ── (a) Copy-to-clipboard buttons ─────────────────────────────
  document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var target = document.querySelector(btn.getAttribute('data-copy'));
      if (!target) return;

      var text = (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA')
        ? target.value
        : target.textContent.trim();

      var done = function () {
        var original = btn.textContent;
        btn.textContent = 'Copied ✓';
        btn.disabled = true;
        setTimeout(function () {
          btn.textContent = original;
          btn.disabled = false;
        }, 1500);
      };

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text, done); });
      } else {
        fallbackCopy(text, done);
      }
    });
  });

  /** execCommand fallback for older/locked-down browsers (non-HTTPS dev). */
  function fallbackCopy(text, done) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch (e) { /* best effort */ }
    document.body.removeChild(ta);
    done();
  }

  // ── (b) Confirmation gates ────────────────────────────────────
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    var message = el.getAttribute('data-confirm') || 'Are you sure?';
    if (el.tagName === 'FORM') {
      el.addEventListener('submit', function (e) {
        if (!window.confirm(message)) e.preventDefault();
      });
    } else {
      el.addEventListener('click', function (e) {
        if (!window.confirm(message)) {
          e.preventDefault();
          e.stopImmediatePropagation();
        }
      });
    }
  });

  // ── (c) Auto-dismiss flash messages ───────────────────────────
  document.querySelectorAll('.flash').forEach(function (flash) {
    setTimeout(function () {
      flash.style.transition = 'opacity .5s ease';
      flash.style.opacity = '0';
      setTimeout(function () { flash.remove(); }, 500);
    }, 4000);
  });

  // ── (d) Clickable table rows ──────────────────────────────────
  document.querySelectorAll('tr[data-href]').forEach(function (row) {
    row.style.cursor = 'pointer';
    row.addEventListener('click', function (e) {
      // Don't hijack clicks on interactive elements inside the row.
      if (e.target.closest('a, button, input, select, textarea, label, form')) return;
      window.location.href = row.getAttribute('data-href');
    });
  });
})();
