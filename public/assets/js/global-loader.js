(function () {
    var loader = null;

    function getLoader() {
        if (loader && document.body.contains(loader)) {
            return loader;
        }
        loader = document.getElementById('vilcon-global-loader');
        return loader;
    }

    function showLoader() {
        var el = getLoader();
        if (!el) {
            return;
        }
        el.classList.add('is-visible');
    }

    function hideLoader() {
        var el = getLoader();
        if (!el) {
            return;
        }
        el.classList.remove('is-visible');
    }

    function shouldHandleLink(anchor, event) {
        if (!anchor || !anchor.href || event.defaultPrevented) {
            return false;
        }
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return false;
        }
        if (anchor.hasAttribute('download') || anchor.target === '_blank') {
            return false;
        }

        var href = anchor.getAttribute('href') || '';
        if (!href || href === '#' || href.indexOf('javascript:') === 0 || href.indexOf('mailto:') === 0 || href.indexOf('tel:') === 0) {
            return false;
        }

        var destination = new URL(anchor.href, window.location.href);
        return destination.href !== window.location.href;
    }

    window.VilconLoader = {
        show: showLoader,
        hide: hideLoader
    };

    document.addEventListener('DOMContentLoaded', function () {
        hideLoader();

        document.addEventListener('submit', function () {
            showLoader();
        }, true);

        document.addEventListener('click', function (event) {
            var anchor = event.target.closest('a');
            if (shouldHandleLink(anchor, event)) {
                showLoader();
            }
        }, true);
    });

    window.addEventListener('beforeunload', function () {
        showLoader();
    });

    window.addEventListener('pageshow', function () {
        hideLoader();
    });
})();
