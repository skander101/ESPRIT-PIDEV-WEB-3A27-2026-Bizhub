/**
 * Formation admin map: Leaflet + Nominatim reverse-geocoding.
 * Handles presential formation location selection.
 */
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var mapEls = document.querySelectorAll('.js-formation-leaflet-map');
    if (!mapEls.length) return;

    mapEls.forEach(function (mapEl) {
      var root = mapEl.closest('.formation-location-root');
      if (!root) return;

      var initialLat = root.getAttribute('data-initial-lat') || '';
      var initialLng = root.getAttribute('data-initial-lng') || '';
      var hasInitial = initialLat && initialLng && !isNaN(parseFloat(initialLat)) && !isNaN(parseFloat(initialLng));

      var defaultLat = 36.8065;
      var defaultLng = 10.1815;
      var defaultZoom = 12;

      var lat = hasInitial ? parseFloat(initialLat) : defaultLat;
      var lng = hasInitial ? parseFloat(initialLng) : defaultLng;
      var zoom = hasInitial ? 14 : defaultZoom;

      var map = L.map(mapEl, { center: [lat, lng], zoom: zoom });

      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19
      }).addTo(map);

      var marker = null;

      function updateFields(latVal, lngVal, address) {
        var latInput = root.querySelector('[data-validate-field="latitude"] input, input[data-validate-field="latitude"]');
        var lngInput = root.querySelector('[data-validate-field="longitude"] input, input[data-validate-field="longitude"]');
        var lieuInput = root.querySelector('[data-validate-field="lieu"] input, input[data-validate-field="lieu"]');

        if (!latInput) latInput = root.querySelector('[name$="[latitude]"]');
        if (!lngInput) lngInput = root.querySelector('[name$="[longitude]"]');
        if (!lieuInput) lieuInput = root.querySelector('[name$="[lieu]"]');

        if (latInput) latInput.value = latVal;
        if (lngInput) lngInput.value = lngVal;
        if (lieuInput && address) lieuInput.value = address;
      }

      function reverseGeocode(latVal, lngVal) {
        var url = 'https://nominatim.openstreetmap.org/reverse?format=json&lat=' + encodeURIComponent(latVal) + '&lon=' + encodeURIComponent(lngVal) + '&zoom=18&addressdetails=1';
        fetch(url, { headers: { 'Accept-Language': 'fr' } })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            var addr = (data && data.display_name) ? data.display_name : (latVal + ', ' + lngVal);
            updateFields(latVal, lngVal, addr);
          })
          .catch(function () {
            updateFields(latVal, lngVal, latVal + ', ' + lngVal);
          });
      }

      if (hasInitial) {
        marker = L.marker([lat, lng], { draggable: true }).addTo(map);
        reverseGeocode(lat, lng);
        marker.on('dragend', function (e) {
          var ll = e.target.getLatLng();
          reverseGeocode(ll.lat.toFixed(6), ll.lng.toFixed(6));
        });
      }

      map.on('click', function (e) {
        var latVal = e.latlng.lat.toFixed(6);
        var lngVal = e.latlng.lng.toFixed(6);
        if (marker) {
          marker.setLatLng(e.latlng);
        } else {
          marker = L.marker(e.latlng, { draggable: true }).addTo(map);
          marker.on('dragend', function (ev) {
            var ll = ev.target.getLatLng();
            reverseGeocode(ll.lat.toFixed(6), ll.lng.toFixed(6));
          });
        }
        reverseGeocode(latVal, lngVal);
      });

      var presentialWrap = root.querySelector('.js-formation-presential-wrap');
      if (presentialWrap) {
        var observer = new MutationObserver(function () {
          if (presentialWrap.hasAttribute('hidden') || presentialWrap.getAttribute('aria-hidden') === 'true') {
            map.invalidateSize();
          } else {
            setTimeout(function () { map.invalidateSize(); }, 100);
          }
        });
        observer.observe(presentialWrap, { attributes: true, attributeFilter: ['hidden', 'aria-hidden'] });
      }

      setTimeout(function () { map.invalidateSize(); }, 200);
    });
  });
})();
