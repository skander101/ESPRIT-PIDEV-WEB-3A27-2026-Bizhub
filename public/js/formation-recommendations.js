(function () {
  'use strict';

  function init() {
    var aiPickBtn = document.getElementById('formation-ai-best-pick-open');
    if (!aiPickBtn) return;

    aiPickBtn.addEventListener('click', function () {
      // Get the first personalized formation from the page
      var personaliedSection = document.querySelector('[data-reco-section="personalized"]');
      if (personaliedSection) {
        var firstCard = personaliedSection.querySelector('.reco-card a');
        if (firstCard && firstCard.href) {
          window.location.href = firstCard.href;
          return;
        }
      }

      // Fallback: get the first formation from any reco section
      var anyCard = document.querySelector('.reco-card a');
      if (anyCard && anyCard.href) {
        window.location.href = anyCard.href;
        return;
      }

      alert('Aucune formation disponible.');
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
