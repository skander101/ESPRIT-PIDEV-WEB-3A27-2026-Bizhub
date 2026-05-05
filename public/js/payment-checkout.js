/**
 * Premium checkout: live card preview + validation + promo (AJAX).
 */
(function () {
  'use strict';

  function onlyDigits(s) {
    return (s || '').replace(/\D+/g, '');
  }

  function formatPan(d) {
    var g = d.match(/.{1,4}/g);
    return g ? g.join(' ').trim() : '';
  }

  function holderOk(s) {
    var t = (s || '').trim();
    if (!t) return false;
    return /^[a-zA-ZÀ-ÿ\u00C0-\u024F\s\-']+$/.test(t);
  }

  function setFieldState(el, valid) {
    if (!el) return;
    el.classList.toggle('is-invalid', !valid);
    el.classList.toggle('is-valid', valid);
  }

  function fmtTnd(n) {
    var x = typeof n === 'number' ? n : parseFloat(n, 10);
    if (isNaN(x)) x = 0;
    return x.toLocaleString('fr-FR', { minimumFractionDigits: 3, maximumFractionDigits: 3 }) + ' TND';
  }

  function updateSummaryFromPayload(form, data) {
    var htEl = document.getElementById('pay-summary-ht');
    var tvaEl = document.getElementById('pay-summary-tva');
    var totalEl = document.getElementById('pay-summary-total');
    var discWrap = document.getElementById('pay-line-discount-wrap');
    var discPct = document.getElementById('pay-summary-discount-pct');
    var btn = document.getElementById('pay-submit-btn');
    if (htEl) htEl.textContent = fmtTnd(data.ht);
    if (tvaEl) tvaEl.textContent = fmtTnd(data.tva);
    if (totalEl) totalEl.textContent = fmtTnd(data.amount_ttc);
    if (btn) {
      var base = btn.getAttribute('data-base-label') || 'Confirmer le paiement';
      btn.innerHTML = '<i class="fa-solid fa-lock me-2"></i>' + base + ' (' + fmtTnd(data.amount_ttc) + ')';
    }
    if (discWrap && discPct) {
      if (data.discount_percent > 0) {
        discWrap.style.display = 'flex';
        discPct.textContent = '−' + data.discount_percent + '%';
      } else {
        discWrap.style.display = 'none';
        discPct.textContent = '';
      }
    }
  }

  function resetSummaryToBase(form) {
    var base = parseFloat(form.dataset.baseTtc, 10) || 0;
    var vatRate = parseFloat(form.dataset.vatRate, 10) || 0;
    var ht = vatRate > 0 ? Math.round((base / (1 + vatRate)) * 1000) / 1000 : base;
    var tva = Math.round((base - ht) * 1000) / 1000;
    updateSummaryFromPayload(form, {
      ht: ht,
      tva: tva,
      amount_ttc: base,
      discount_percent: 0,
    });
  }

  function runPromoValidate(form, promoInput, feedbackEl) {
    var url = form.dataset.validatePromoUrl;
    var token = form.dataset.promoCsrf;
    if (!url || !token || !promoInput) return;

    var fd = new FormData();
    fd.append('_token', token);
    fd.append('promo_code', promoInput.value || '');

    feedbackEl.classList.remove('is-ok', 'is-err');
    feedbackEl.textContent = 'Vérification…';

    fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (!data || !data.ok) {
          feedbackEl.classList.add('is-err');
          feedbackEl.textContent = (data && data.message) ? data.message : 'Code invalide.';
          resetSummaryToBase(form);
          return;
        }
        feedbackEl.classList.add('is-ok');
        feedbackEl.textContent = data.message || 'Code accepté.';
        updateSummaryFromPayload(form, data);
      })
      .catch(function () {
        feedbackEl.classList.add('is-err');
        feedbackEl.textContent = 'Erreur réseau. Réessayez.';
      });
  }

  var promoDebounce;

  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('payment-checkout-form');
    if (!form) return;

    var pan = document.getElementById('card_number');
    var holder = document.getElementById('card_holder');
    var expM = document.getElementById('exp_month');
    var expY = document.getElementById('exp_year');
    var cvv = document.getElementById('cvv');
    var prevPan = document.getElementById('preview-pan');
    var prevHolder = document.getElementById('preview-holder');
    var prevExp = document.getElementById('preview-exp');
    var btn = document.getElementById('pay-submit-btn');
    var promoInput = document.getElementById('promo_code');
    var promoBtn = document.getElementById('promo-apply-btn');
    var promoFb = document.getElementById('promo-feedback');

    if (cvv) {
      cvv.addEventListener('input', function () {
        cvv.value = onlyDigits(cvv.value).slice(0, 4);
      });
    }

    function syncPreview() {
      var d = onlyDigits(pan.value);
      if (d.length === 0) {
        prevPan.textContent = '•••• •••• •••• ••••';
      } else {
        var shown = formatPan(d);
        var masked = shown + ' ••••'.repeat(Math.max(0, 4 - Math.ceil(d.length / 4))).trim();
        prevPan.textContent = masked;
      }
      var h = (holder.value || '').trim();
      prevHolder.textContent = h !== '' ? h.toUpperCase() : 'NOM PRÉNOM';
      var mm = expM && expM.value ? (parseInt(expM.value, 10) < 10 ? '0' : '') + parseInt(expM.value, 10) : 'MM';
      var yy = expY && expY.value ? String(expY.value).slice(-2) : 'AA';
      prevExp.textContent = mm + '/' + yy;
    }

    ['input', 'change'].forEach(function (ev) {
      [pan, holder, expM, expY].forEach(function (el) {
        if (el) el.addEventListener(ev, syncPreview);
      });
    });

    if (pan) {
      pan.addEventListener('input', function () {
        var d = onlyDigits(pan.value).slice(0, 19);
        pan.value = formatPan(d);
      });
    }

    syncPreview();

    if (promoInput && promoFb) {
      if (promoBtn) {
        promoBtn.addEventListener('click', function () {
          runPromoValidate(form, promoInput, promoFb);
        });
      }
      promoInput.addEventListener('input', function () {
        clearTimeout(promoDebounce);
        promoDebounce = setTimeout(function () {
          if ((promoInput.value || '').trim() === '') {
            promoFb.classList.remove('is-ok', 'is-err');
            promoFb.textContent = '';
            resetSummaryToBase(form);
            return;
          }
          runPromoValidate(form, promoInput, promoFb);
        }, 550);
      });
    }

    form.addEventListener('submit', function (e) {
      var ok = true;
      [pan, holder, expM, expY, cvv].forEach(function (el) {
        if (el) {
          el.classList.remove('is-invalid', 'is-valid');
        }
      });

      var panDigits = onlyDigits(pan.value);
      var panValid = panDigits.length >= 13 && panDigits.length <= 19;
      setFieldState(pan, panValid);
      if (!panValid) ok = false;

      var hValid = holderOk(holder ? holder.value : '');
      setFieldState(holder, hValid);
      if (!hValid) ok = false;

      var expValid = !!(expM && expM.value && expY && expY.value);
      setFieldState(expM, expValid);
      setFieldState(expY, expValid);
      if (!expValid) ok = false;

      var cvvDigits = onlyDigits(cvv ? cvv.value : '');
      var cvvValid = cvvDigits.length === 3 || cvvDigits.length === 4;
      setFieldState(cvv, cvvValid);
      if (!cvvValid) ok = false;

      if (cvvValid && cvv) {
        cvv.value = cvvDigits;
      }

      if (!ok) {
        e.preventDefault();
        e.stopPropagation();
        form.classList.add('was-validated');
        return;
      }

      form.classList.add('was-validated');
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Traitement…';
      }
    });
  });
})();
