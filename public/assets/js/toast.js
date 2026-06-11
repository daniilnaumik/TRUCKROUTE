(function () {
    'use strict';

    function getContainer() {
        var el = document.getElementById('toast-container');
        if (!el) {
            el = document.createElement('div');
            el.id = 'toast-container';
            el.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:10px;pointer-events:none;';
            document.body.appendChild(el);
        }
        return el;
    }

    function show(msg, color) {
        var colors = {
            green: { bg: '#1a3a2a', border: '#2ecc71', text: '#a8f0c6' },
            red:   { bg: '#3a1a1a', border: '#e74c3c', text: '#f0a8a8' },
            blue:  { bg: '#1a2a3a', border: '#3498db', text: '#a8d4f0' },
        };
        var c = colors[color] || colors.blue;
        var toast = document.createElement('div');
        toast.style.cssText = [
            'background:' + c.bg,
            'border:1px solid ' + c.border,
            'color:' + c.text,
            'padding:12px 20px',
            'border-radius:6px',
            'font-size:14px',
            'line-height:1.4',
            'max-width:320px',
            'pointer-events:auto',
            'opacity:0',
            'transform:translateY(8px)',
            'transition:opacity .25s,transform .25s',
            'cursor:pointer',
        ].join(';');
        toast.textContent = msg;
        toast.addEventListener('click', function () { remove(toast); });
        getContainer().appendChild(toast);
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                toast.style.opacity = '1';
                toast.style.transform = 'translateY(0)';
            });
        });
        setTimeout(function () { remove(toast); }, 4000);
    }

    function remove(toast) {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(8px)';
        setTimeout(function () { toast.parentNode && toast.parentNode.removeChild(toast); }, 280);
    }

    window.TruckToast = {
        success: function (msg) { show(msg, 'green'); },
        error:   function (msg) { show(msg, 'red'); },
        info:    function (msg) { show(msg, 'blue'); },
    };
})();
