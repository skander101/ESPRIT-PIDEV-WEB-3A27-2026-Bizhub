// Global functions for AI recommendation modal
(function() {
  'use strict';
  
  var modal = document.getElementById('formation-ai-best-pick-modal');
  var backdrop = document.getElementById('formation-ai-best-pick-backdrop');
  var runBtn = document.getElementById('formation-ai-best-pick-run');
  var statusEl = document.getElementById('formation-ai-best-pick-status');
  var resultEl = document.getElementById('formation-ai-best-pick-result');
  var notesEl = document.getElementById('formation-ai-best-pick-notes');
  
  var aiUrl = '';
  var aiCsrf = '';
  
  if (modal) {
    aiUrl = modal.getAttribute('data-ai-url') || '';
    aiCsrf = modal.getAttribute('data-ai-csrf') || '';
  }
  
  window.openAIModal = function() {
    console.log('openAIModal called');
    if (modal) {
      modal.style.display = 'block';
    }
    if (backdrop) {
      backdrop.style.display = 'block';
    }
    if (statusEl) statusEl.style.display = 'none';
    if (resultEl) resultEl.style.display = 'none';
    if (notesEl) notesEl.value = '';
  };
  
  window.closeAIModal = function() {
    console.log('closeAIModal called');
    if (modal) {
      modal.style.display = 'none';
    }
    if (backdrop) {
      backdrop.style.display = 'none';
    }
  };
  
  window.runAIRecommendation = function() {
    console.log('runAIRecommendation called');
    if (!aiUrl) {
      console.log('No AI URL configured');
      return;
    }
    
    if (statusEl) statusEl.style.display = 'none';
    if (resultEl) resultEl.style.display = 'none';
    if (runBtn) runBtn.disabled = true;
    
    var payload = { _token: aiCsrf };
    if (notesEl && notesEl.value.trim() !== '') {
      payload.notes = notesEl.value.trim();
    }
    
    console.log('Sending request to:', aiUrl);
    
    fetch(aiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': aiCsrf },
      body: JSON.stringify(payload)
    })
    .then(function(resp) { 
      console.log('Response status:', resp.status);
      return resp.json(); 
    })
    .then(function(data) {
      console.log('Data received:', data);
      if (runBtn) runBtn.disabled = false;
      if (data.ok && data.url) {
        if (resultEl) {
          resultEl.innerHTML = '<p><strong>' + (data.title || 'Formation recommandée') + '</strong><br/>' +
            '<a href="' + data.url + '" class="btn-main" style="margin-top:10px;display:inline-block;">Voir la formation</a></p>';
          resultEl.style.display = 'block';
        }
      } else {
        if (statusEl) {
          statusEl.textContent = data.message || 'Erreur lors de la recommandation.';
          statusEl.style.display = 'block';
        }
      }
    })
    .catch(function(err) {
      console.log('Error:', err);
      if (runBtn) runBtn.disabled = false;
      if (statusEl) {
        statusEl.textContent = 'Erreur de connexion au serveur.';
        statusEl.style.display = 'block';
      }
    });
  };
  
  console.log('AI Recommendation functions loaded');
})();
