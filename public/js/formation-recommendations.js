/**
 * Recommendation rails: horizontal scroll, nav buttons, impression + click tracking.
 */
(function () {
  'use strict';

  function postTrack(url, csrf, body) {
    if (!url || !csrf) return;
    fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(Object.assign({ _token: csrf }, body)),
    }).catch(function () {});
  }

  document.addEventListener('DOMContentLoaded', function () {
    var root = document.getElementById('formation-reco-root');
    if (!root) return;
    var url = root.dataset.trackUrl;
    var csrf = root.dataset.trackCsrf;

    root.querySelectorAll('.reco-section').forEach(function (section) {
      var sec = section.getAttribute('data-reco-section') || '';
      var rail = section.querySelector('.reco-rail');
      var prev = section.querySelector('.reco-prev');
      var next = section.querySelector('.reco-next');
      if (!rail) return;

      var step = function () {
        return Math.min(320, Math.floor(rail.clientWidth * 0.85));
      };

      if (prev) {
        prev.addEventListener('click', function () {
          rail.scrollBy({ left: -step(), behavior: 'smooth' });
        });
      }
      if (next) {
        next.addEventListener('click', function () {
          rail.scrollBy({ left: step(), behavior: 'smooth' });
        });
      }

      section.querySelectorAll('.reco-card').forEach(function (card) {
        var fid = parseInt(card.getAttribute('data-formation-id'), 10);
        if (!fid) return;

        var obs = new IntersectionObserver(function (entries) {
          entries.forEach(function (e) {
            if (e.isIntersecting) {
              postTrack(url, csrf, { formation_id: fid, section: sec, event: 'impression' });
              obs.disconnect();
            }
          });
        }, { threshold: 0.45 });
        obs.observe(card);

        var link = card.querySelector('a');
        if (link) {
          link.addEventListener('click', function () {
            postTrack(url, csrf, { formation_id: fid, section: sec, event: 'click' });
          });
        }
      });
    });

    /* Modale « meilleure formation » (Groq + historique) */
    var modal = document.getElementById('formation-ai-best-pick-modal');
    var openBtn = document.getElementById('formation-ai-best-pick-open');
    if (!modal || !openBtn) return;

    var runBtn = document.getElementById('formation-ai-best-pick-run');
    var notesEl = document.getElementById('formation-ai-best-pick-notes');
    var statusEl = document.getElementById('formation-ai-best-pick-status');
    var resultEl = document.getElementById('formation-ai-best-pick-result');
    var aiUrl = modal.getAttribute('data-ai-url') || '';
    var aiCsrf = modal.getAttribute('data-ai-csrf') || '';

    function setModalOpen(on) {
      if (on) {
        modal.removeAttribute('hidden');
        document.body.style.overflow = 'hidden';
        if (notesEl) notesEl.focus();
      } else {
        modal.setAttribute('hidden', 'hidden');
        document.body.style.overflow = '';
      }
    }

    function showStatus(msg, kind) {
      if (!statusEl) return;
      statusEl.hidden = false;
      statusEl.textContent = msg;
      statusEl.className = 'reco-ai-status' + (kind === 'error' ? ' is-error' : kind === 'ok' ? ' is-ok' : '');
    }

    function hideStatus() {
      if (!statusEl) return;
      statusEl.hidden = true;
      statusEl.textContent = '';
      statusEl.className = 'reco-ai-status';
    }

    function esc(s) {
      return String(s == null ? '' : s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    function renderResult(data) {
      if (!resultEl) return;
      resultEl.hidden = false;
      var title = esc(data.title || '');
      var reason = esc(data.reason || '');
      var url = esc(data.url || '#');
      var fid = parseInt(data.formation_id, 10) || 0;
      var src = esc(data.source || '');
      var online = !!data.en_ligne;
      var accent = online ? 'reco-card-accent--online' : 'reco-card-accent--present';
      var tagClass = online ? 'reco-card-tag online' : 'reco-card-tag';
      var tagText = online ? 'En ligne' : 'Présentielle';
      resultEl.innerHTML =
        '<article class="reco-card" data-formation-id="' + fid + '">' +
        '<span class="reco-badge">IA</span>' +
        '<div class="reco-card-accent ' + accent + '"></div>' +
        '<div class="reco-card-body">' +
        '<div class="' + tagClass + '">' + tagText + '</div>' +
        '<h3 class="reco-card-title"><a href="' + url + '">' + title + '</a></h3>' +
        '<p class="reco-card-desc">' + reason + '</p>' +
        '</div>' +
        '<div class="reco-card-meta"><span></span><a class="reco-price" style="text-decoration:none;color:#0369a1;font-weight:800;" href="' + url + '">Voir la fiche →</a></div>' +
        '</article>' +
        (src ? '<p class="reco-ai-source">Source : ' + src + '</p>' : '');
    }

    openBtn.addEventListener('click', function () {
      hideStatus();
      if (resultEl) {
        resultEl.hidden = true;
        resultEl.innerHTML = '';
      }
      setModalOpen(true);
    });

    modal.querySelectorAll('[data-ai-close]').forEach(function (el) {
      el.addEventListener('click', function () {
        setModalOpen(false);
      });
    });

    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && !modal.hasAttribute('hidden')) {
        setModalOpen(false);
      }
    });

    if (runBtn) {
      runBtn.addEventListener('click', function () {
        if (!aiUrl || !aiCsrf) {
          showStatus('Configuration manquante.', 'error');
          return;
        }
        runBtn.disabled = true;
        hideStatus();
        if (resultEl) {
          resultEl.hidden = true;
          resultEl.innerHTML = '';
        }
        showStatus('Analyse en cours…', '');

        fetch(aiUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: JSON.stringify({
            _token: aiCsrf,
            notes: notesEl ? notesEl.value : '',
          }),
        })
          .then(function (r) {
            var st = r.status;
            return r
              .json()
              .then(function (j) {
                return { ok: r.ok, status: st, body: j };
              })
              .catch(function () {
                return { ok: r.ok, status: st, body: {} };
              });
          })
          .then(function (res) {
            if (!res.body || !res.body.ok) {
              var m =
                (res.body && res.body.message) ||
                (!res.ok ? 'Erreur serveur (' + (res.status || '') + ').' : '') ||
                'Impossible d’obtenir une suggestion pour le moment.';
              showStatus(m, 'error');
              return;
            }
            hideStatus();
            showStatus('Voici la formation recommandée.', 'ok');
            renderResult(res.body);
          })
          .catch(function () {
            showStatus('Erreur réseau. Réessayez plus tard.', 'error');
          })
          .finally(function () {
            runBtn.disabled = false;
          });
      });
    }
  });
})();
