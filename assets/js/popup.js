(function () {
    'use strict';

    var overlay = document.getElementById('wpata-overlay');
    var button  = document.getElementById('wpata-accept');

    if (!overlay || !button) {
        return;
    }

    // Prevent scrolling while the overlay is visible.
    document.documentElement.style.overflow = 'hidden';

    button.addEventListener('click', function () {
        // Set the cookie client-side immediately.
        var days    = (wpata && wpata.cookieDays) ? wpata.cookieDays : 30;
        var expires = new Date(Date.now() + days * 86400000).toUTCString();
        document.cookie = 'wpata_accepted=1;expires=' + expires + ';path=/;SameSite=Lax' + (location.protocol === 'https:' ? ';Secure' : '');

        // Fade out overlay.
        overlay.classList.add('wpata-hidden');
        document.documentElement.style.overflow = '';

        // Also notify the server (fire-and-forget).
        if (wpata && wpata.ajaxUrl && wpata.nonce) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', wpata.ajaxUrl);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send('action=wpata_accept&nonce=' + encodeURIComponent(wpata.nonce));
        }

        // Remove overlay from DOM after transition.
        setTimeout(function () {
            if (overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
        }, 350);
    });
})();
