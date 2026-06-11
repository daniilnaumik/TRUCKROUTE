(function () {
    'use strict';

    function GeocodeAutocomplete(inputEl, opts) {
        opts = opts || {};
        var onSelect = opts.onSelect || function () {};
        var dropdown = null;
        var timer = null;

        function createDropdown() {
            if (dropdown) return;
            dropdown = document.createElement('ul');
            dropdown.style.cssText = [
                'position:absolute',
                'z-index:1000',
                'background:#1a1a2e',
                'border:1px solid #333',
                'border-radius:4px',
                'list-style:none',
                'margin:2px 0 0',
                'padding:0',
                'width:100%',
                'max-height:220px',
                'overflow-y:auto',
                'box-shadow:0 4px 16px rgba(0,0,0,.4)',
            ].join(';');
            var parent = inputEl.parentElement;
            parent.style.position = 'relative';
            parent.appendChild(dropdown);
        }

        function clearDropdown() {
            if (dropdown) {
                dropdown.innerHTML = '';
                dropdown.style.display = 'none';
            }
        }

        function renderResults(results) {
            createDropdown();
            dropdown.innerHTML = '';
            if (!results.length) { clearDropdown(); return; }
            results.forEach(function (item) {
                var li = document.createElement('li');
                li.style.cssText = 'padding:8px 12px;cursor:pointer;font-size:13px;color:#ccc;border-bottom:1px solid #222;';
                li.textContent = item.display_name;
                li.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    inputEl.value = item.display_name;
                    clearDropdown();
                    onSelect(item);
                });
                li.addEventListener('mouseenter', function () { li.style.background = '#2a2a3e'; });
                li.addEventListener('mouseleave', function () { li.style.background = ''; });
                dropdown.appendChild(li);
            });
            dropdown.style.display = 'block';
        }

        inputEl.addEventListener('input', function () {
            clearTimeout(timer);
            var q = inputEl.value.trim();
            if (q.length < 3) { clearDropdown(); return; }
            timer = setTimeout(function () {
                fetch('/api/v1/geo/geocode?q=' + encodeURIComponent(q))
                    .then(function (r) { return r.json(); })
                    .then(function (data) { renderResults(data.data || data || []); })
                    .catch(function () { clearDropdown(); });
            }, 400);
        });

        inputEl.addEventListener('blur', function () {
            setTimeout(clearDropdown, 200);
        });
    }

    window.GeocodeAutocomplete = GeocodeAutocomplete;
})();
