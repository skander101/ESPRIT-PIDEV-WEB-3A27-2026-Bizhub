/**
 * Leaflet map for formation présentielle: pick location, reverse-geocode (Nominatim), fill lieu/lat/lng.
 */
(function () {
  'use strict';

  var REVERSE_URL = 'https://nominatim.openstreetmap.org/reverse';

  function q(root, sel) {
    return root.querySelector(sel);
  }

  function parseOnline(select) {
    if (!select) {
      return false;
    }
    return select.value === '1' || select.value === 'true';
  }

  function parseFloatOrNaN(v) {
    var n = parseFloat(String(v).replace(',', '.').trim(), 10);
    return isNaN(n) ? NaN : n;
  }

  function debounce(fn, ms) {
    var t = null;
    return function () {
      var ctx = this;
      var args = arguments;
      clearTimeout(t);
      t = setTimeout(function () {
        fn.apply(ctx, args);
      }, ms);
    };
  }

  function FormationLocationMap(root) {
    this.root = root;
    this.form = root.closest('form');
    this.typeSelect = this.form ? this.form.querySelector('.js-formation-type-select') : null;
    this.wrap = q(root, '.js-formation-presential-wrap');
    this.mapEl = q(root, '.js-formation-leaflet-map');
    this.lieuInput = this.form ? this.form.querySelector('.js-formation-lieu') : null;
    this.latInput = this.form ? this.form.querySelector('.js-formation-latitude') : null;
    this.lngInput = this.form ? this.form.querySelector('.js-formation-longitude') : null;
    this.map = null;
    this.marker = null;
    this._reverseDebounced = debounce(this._reverseImmediate.bind(this), 950);
  }

  FormationLocationMap.prototype.init = function () {
    var self = this;
    if (!this.typeSelect || !this.wrap || !this.mapEl) {
      return;
    }
    this.typeSelect.addEventListener('change', function () {
      self.sync();
    });
    this.sync();
  };

  FormationLocationMap.prototype._destroyMap = function () {
    if (this.map) {
      this.map.remove();
      this.map = null;
      this.marker = null;
    }
  };

  FormationLocationMap.prototype._readInitialCoords = function () {
    var ds = this.root.dataset || {};
    var lat = this.latInput && this.latInput.value ? parseFloatOrNaN(this.latInput.value) : NaN;
    var lng = this.lngInput && this.lngInput.value ? parseFloatOrNaN(this.lngInput.value) : NaN;
    if (isNaN(lat) || isNaN(lng)) {
      lat = parseFloatOrNaN(ds.initialLat || '');
      lng = parseFloatOrNaN(ds.initialLng || '');
    }
    if (!isNaN(lat) && !isNaN(lng)) {
      return { lat: lat, lng: lng };
    }
    return null;
  };

  FormationLocationMap.prototype._reverseImmediate = function (lat, lng) {
    var self = this;
    var url = REVERSE_URL + '?format=jsonv2&lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lng);
    fetch(url, {
      method: 'GET',
      headers: {
        'Accept-Language': 'fr,en',
      },
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        var label = (data && data.display_name) ? data.display_name : lat + ', ' + lng;
        if (self.lieuInput) {
          self.lieuInput.value = label;
        }
      })
      .catch(function () {
        if (self.lieuInput) {
          self.lieuInput.value = lat + ', ' + lng;
        }
      });
  };

  FormationLocationMap.prototype._onPick = function (lat, lng) {
    if (this.latInput) {
      this.latInput.value = String(lat);
    }
    if (this.lngInput) {
      this.lngInput.value = String(lng);
    }
    this._reverseDebounced(lat, lng);
    if (this.marker) {
      this.marker.setPopupContent(
        '<div style="min-width:180px;max-width:260px;font-size:12px;line-height:1.35;">' +
          '<strong>Position</strong><br>' +
          lat.toFixed(6) +
          ', ' +
          lng.toFixed(6) +
          '<br><span style="opacity:.75">Adresse chargée dans le champ Lieu.</span></div>'
      );
      this.marker.openPopup();
    }
  };

  FormationLocationMap.prototype._ensureMap = function () {
    if (typeof L === 'undefined' || !this.mapEl) {
      return;
    }
    var coords = this._readInitialCoords();
    var centerLat = coords ? coords.lat : 36.8065;
    var centerLng = coords ? coords.lng : 10.1815;
    var zoom = coords ? 15 : 6;

    if (this.map) {
      this._destroyMap();
    }

    this.map = L.map(this.mapEl, { scrollWheelZoom: true }).setView([centerLat, centerLng], zoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(this.map);

    var self = this;

    function addOrMoveMarker(latlng) {
      if (self.marker) {
        self.marker.setLatLng(latlng);
      } else {
        self.marker = L.marker(latlng, { draggable: true }).addTo(self.map);
        self.marker.bindPopup('<div style="font-size:12px;">Marqueur déplaçable — relâchez pour mettre à jour l’adresse.</div>');
        self.marker.on('dragend', function () {
          var p = self.marker.getLatLng();
          self._onPick(p.lat, p.lng);
        });
      }
      self._onPick(latlng.lat, latlng.lng);
    }

    this.map.on('click', function (e) {
      addOrMoveMarker(e.latlng);
    });

    if (coords) {
      addOrMoveMarker(L.latLng(coords.lat, coords.lng));
    }

    setTimeout(function () {
      if (self.map) {
        self.map.invalidateSize();
      }
    }, 200);
  };

  FormationLocationMap.prototype.sync = function () {
    var online = parseOnline(this.typeSelect);
    if (online) {
      this.wrap.setAttribute('hidden', 'hidden');
      this.wrap.setAttribute('aria-hidden', 'true');
      if (this.lieuInput) {
        this.lieuInput.value = '';
      }
      if (this.latInput) {
        this.latInput.value = '';
      }
      if (this.lngInput) {
        this.lngInput.value = '';
      }
      this._destroyMap();
      return;
    }

    this.wrap.removeAttribute('hidden');
    this.wrap.setAttribute('aria-hidden', 'false');

    var self = this;
    window.requestAnimationFrame(function () {
      self._ensureMap();
    });
  };

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-formation-map-root]').forEach(function (root) {
      var m = new FormationLocationMap(root);
      m.init();
    });
  });
})();
