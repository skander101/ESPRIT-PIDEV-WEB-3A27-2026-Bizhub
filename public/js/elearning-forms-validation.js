/**
 * Validation côté client (sans attributs HTML5 required/pattern).
 * Formulaires : formation (new/edit), participation admin (new/edit), participation front.
 */
(function () {
  function clearTopErrors(form) {
    var top = form.querySelector('.js-form-errors-top');
    if (top) {
      top.innerHTML = '';
      top.style.display = 'none';
    }
  }

  function showTopErrors(form, messages) {
    var top = form.querySelector('.js-form-errors-top');
    if (!top) {
      top = document.createElement('div');
      top.className = 'js-form-errors-top alert alert-danger';
      top.setAttribute('role', 'alert');
      form.insertBefore(top, form.firstChild);
    }
    top.style.display = 'block';
    top.innerHTML = '<ul class="mb-0 ps-3">' + messages.map(function (m) {
      return '<li>' + m + '</li>';
    }).join('') + '</ul>';
  }

  function parseNum(v) {
    return parseFloat(String(v).replace(',', '.').trim());
  }

  function validateFormation(form) {
    var errors = [];
    var title = form.querySelector('[data-validate-field="title"]');
    var trainer = form.querySelector('[data-validate-field="trainer"]');
    var start = form.querySelector('[data-validate-field="start_date"]');
    var end = form.querySelector('[data-validate-field="end_date"]');
    var cost = form.querySelector('[data-validate-field="cost"]');
    var lieu = form.querySelector('[data-validate-field="lieu"]');
    var typeSelect = form.querySelector('[data-validate-field="formation_type"]');
    var latH = form.querySelector('[data-validate-field="latitude"]');
    var lngH = form.querySelector('[data-validate-field="longitude"]');
    var isOnline = typeSelect && (typeSelect.value === '1' || typeSelect.value === 'true');

    if (title) {
      var tv = (title.value || '').trim();
      if (!tv) errors.push('Le titre est obligatoire.');
      else if (tv.length > 200) errors.push('Le titre ne doit pas dépasser 200 caractères.');
    }
    if (trainer && (!trainer.value || trainer.value === '')) {
      errors.push('Le formateur est obligatoire.');
    }
    if (start && !(start.value || '').trim()) errors.push('La date de début est obligatoire.');
    if (end && !(end.value || '').trim()) errors.push('La date de fin est obligatoire.');
    if (start && end && (start.value || '').trim() && (end.value || '').trim()) {
      var ds = new Date(start.value);
      var de = new Date(end.value);
      if (!isNaN(ds.getTime()) && !isNaN(de.getTime()) && de < ds) {
        errors.push('La date de fin doit être postérieure ou égale à la date de début.');
      }
    }
    if (!isOnline) {
      if (lieu) {
        var lv = (lieu.value || '').trim();
        if (!lv) errors.push('Pour une formation présentielle, sélectionnez un lieu sur la carte (adresse).');
        else if (lv.length > 500) errors.push('Le lieu ne doit pas dépasser 500 caractères.');
      }
      var latv = latH ? (latH.value || '').trim() : '';
      var lngv = lngH ? (lngH.value || '').trim() : '';
      if (!latv || !lngv) {
        errors.push('Pour une formation présentielle, cliquez sur la carte pour enregistrer la position.');
      } else {
        var la = parseNum(latv);
        var lo = parseNum(lngv);
        if (isNaN(la) || la < -90 || la > 90) errors.push('Latitude invalide.');
        if (isNaN(lo) || lo < -180 || lo > 180) errors.push('Longitude invalide.');
      }
    }
    if (cost && (cost.value || '').trim() !== '') {
      var n = parseNum(cost.value);
      if (isNaN(n)) errors.push('Le coût doit être un nombre valide.');
      else if (n < 0) errors.push('Le coût ne peut pas être négatif.');
    }
    return errors;
  }

  function validateParticipationAdmin(form) {
    var errors = [];
    var user = form.querySelector('[data-validate-field="user"]');
    var formation = form.querySelector('[data-validate-field="formation"]');
    var ps = form.querySelector('[data-validate-field="payment_status"]');
    var amount = form.querySelector('[data-validate-field="amount"]');
    var rem = form.querySelector('[data-validate-field="remarques"]');
    var pprov = form.querySelector('[data-validate-field="payment_provider"]');
    var pref = form.querySelector('[data-validate-field="payment_ref"]');
    var da = form.querySelector('[data-validate-field="date_affectation"]');
    var paidAt = form.querySelector('[data-validate-field="paid_at"]');

    if (user && (!user.value || user.value === '')) errors.push('Le participant est obligatoire.');
    if (formation && (!formation.value || formation.value === '')) errors.push('La formation est obligatoire.');
    if (ps && (!ps.value || ps.value === '')) errors.push('Le statut de paiement est obligatoire.');
    if (amount) {
      if (!(amount.value || '').trim()) errors.push('Le montant est obligatoire.');
      else {
        var n = parseNum(amount.value);
        if (isNaN(n) || n < 0) errors.push('Le montant doit être un nombre positif ou zéro.');
      }
    }
    if (rem && (rem.value || '').length > 10000) errors.push('Les remarques sont trop longues.');
    if (pprov && (pprov.value || '').length > 30) errors.push('Fournisseur de paiement : 30 caractères maximum.');
    if (pref && (pref.value || '').length > 255) errors.push('Référence paiement : 255 caractères maximum.');
    if (da && (da.value || '').trim() && isNaN(Date.parse(da.value))) {
      errors.push("La date d'affectation est invalide.");
    }
    if (paidAt && (paidAt.value || '').trim() && isNaN(Date.parse(paidAt.value))) {
      errors.push('La date « Payé le » est invalide.');
    }
    return errors;
  }

  function validateParticipationFront(form) {
    var errors = [];
    var rem = form.querySelector('[data-validate-field="remarques"]');
    if (rem && (rem.value || '').length > 10000) {
      errors.push('Le message est trop long (10 000 caractères maximum).');
    }
    return errors;
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form.elearning-validated-form').forEach(function (form) {
      form.setAttribute('novalidate', 'novalidate');
      form.addEventListener('submit', function (e) {
        var mode = form.getAttribute('data-validate-mode') || '';
        clearTopErrors(form);
        var errors = [];
        if (mode === 'formation-new' || mode === 'formation-edit') {
          errors = validateFormation(form);
        } else if (mode === 'participation-new' || mode === 'participation-edit') {
          errors = validateParticipationAdmin(form);
        } else if (mode === 'participation-front') {
          errors = validateParticipationFront(form);
        }
        if (errors.length) {
          e.preventDefault();
          e.stopPropagation();
          showTopErrors(form, errors);
        }
      });
    });
  });
})();
