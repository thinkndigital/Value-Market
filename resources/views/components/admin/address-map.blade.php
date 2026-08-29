{{--
    Interactive Leaflet/OpenStreetMap picker for an address's latitude/longitude. No API key required (OSM
    tiles are free for reasonable, attributed use - this is a low-traffic admin tool, not a public map
    embedded on every storefront page). Vendored locally at public/assets/admin/js/leaflet (matching this
    app's existing convention of self-hosting vendor JS rather than depending on a CDN at runtime), so
    npm/CDN availability at deploy time never affects this page.

    Usage: place inside a modal (or any container) that starts hidden. Leaflet can't size itself correctly
    inside a display:none element, so call window.initAddressMap(lat, lng) yourself once the container is
    actually visible (e.g. on the modal's 'shown.bs.modal' event) rather than on page load. Reads/writes the
    two hidden inputs named below - point your form's submit at their values.
--}}
<link rel="stylesheet" href="{{ asset('assets/admin/js/leaflet/leaflet.css') }}">
<style>
    #address_map_container {
        height: 320px;
        width: 100%;
        border-radius: 6px;
        z-index: 1;
    }
</style>

<div class="mb-2 d-flex justify-content-between align-items-center">
    <label class="form-label mb-0">{{ labels('admin_labels.pin_location_on_map', 'Pin Location on Map') }}</label>
    <button type="button" id="use_my_location_btn" class="btn btn-sm btn-outline-primary">
        <i class='bx bx-current-location'></i> {{ labels('admin_labels.use_my_current_location', 'Use My Current Location') }}
    </button>
</div>
<div id="address_map_container"></div>
<p class="text-muted mt-1 mb-3" style="font-size: 0.8rem;">
    {{ labels('admin_labels.click_or_drag_marker_to_set_location', 'Click anywhere on the map, or drag the marker, to set the exact location.') }}
</p>
<input type="hidden" name="latitude" id="address_map_latitude">
<input type="hidden" name="longitude" id="address_map_longitude">

<script src="{{ asset('assets/admin/js/leaflet/leaflet.js') }}"></script>
<script>
    (function() {
        var DEFAULT_LAT = 20.5937;
        var DEFAULT_LNG = 78.9629;
        var DEFAULT_ZOOM = 5;
        var PIN_ZOOM = 15;

        var map = null;
        var marker = null;

        function setCoordinates(lat, lng) {
            document.getElementById('address_map_latitude').value = lat;
            document.getElementById('address_map_longitude').value = lng;
        }

        function placeMarker(lat, lng) {
            var latLng = [lat, lng];
            if (marker) {
                marker.setLatLng(latLng);
            } else {
                marker = L.marker(latLng, {
                    draggable: true
                }).addTo(map);
                marker.on('dragend', function() {
                    var pos = marker.getLatLng();
                    setCoordinates(pos.lat, pos.lng);
                });
            }
            setCoordinates(lat, lng);
        }

        // lat/lng: existing coordinates to pre-fill (both empty/undefined for a fresh pin, defaults to a
        // wide view with no marker until the admin clicks or uses "current location").
        window.initAddressMap = function(lat, lng) {
            var hasExisting = lat !== undefined && lat !== null && lat !== '' &&
                lng !== undefined && lng !== null && lng !== '';
            var startLat = hasExisting ? parseFloat(lat) : DEFAULT_LAT;
            var startLng = hasExisting ? parseFloat(lng) : DEFAULT_LNG;

            if (map) {
                map.remove();
                marker = null;
            }

            map = L.map('address_map_container').setView([startLat, startLng], hasExisting ? PIN_ZOOM : DEFAULT_ZOOM);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            if (hasExisting) {
                placeMarker(startLat, startLng);
            } else {
                setCoordinates('', '');
            }

            map.on('click', function(e) {
                placeMarker(e.latlng.lat, e.latlng.lng);
            });

            // Leaflet computes tile layout from the container's size at init time - if the modal wasn't
            // fully painted yet, the map can render at the wrong size until this runs.
            setTimeout(function() {
                map.invalidateSize();
            }, 200);
        };

        document.addEventListener('click', function(e) {
            if (!e.target.closest('#use_my_location_btn')) {
                return;
            }
            e.preventDefault();
            if (!navigator.geolocation) {
                iziToast.error({
                    title: 'Error',
                    message: 'Geolocation is not supported by this browser.',
                    position: 'topRight'
                });
                return;
            }
            navigator.geolocation.getCurrentPosition(function(position) {
                var lat = position.coords.latitude;
                var lng = position.coords.longitude;
                map.setView([lat, lng], PIN_ZOOM);
                placeMarker(lat, lng);
            }, function(error) {
                iziToast.error({
                    title: 'Error',
                    message: 'Could not get your location: ' + error.message,
                    position: 'topRight'
                });
            });
        });
    })();
</script>
