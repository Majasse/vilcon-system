(function () {
    var loader = null;
    var submitGuardAttr = 'data-loader-submitting';

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
        if (destination.hash && destination.pathname === window.location.pathname && destination.search === window.location.search) {
            return false;
        }
        return destination.href !== window.location.href;
    }

    function shouldHandleFormSubmit(form, event) {
        if (!form || event.defaultPrevented) {
            return false;
        }
        if (form.getAttribute('data-loader-skip') === '1') {
            return false;
        }
        if (form.getAttribute(submitGuardAttr) === '1') {
            return false;
        }
        var target = (form.getAttribute('target') || '').toLowerCase();
        if (target === '_blank') {
            return false;
        }
        return true;
    }

    window.VilconLoader = {
        show: showLoader,
        hide: hideLoader
    };

    document.addEventListener('DOMContentLoaded', function () {
        hideLoader();

        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!shouldHandleFormSubmit(form, event)) {
                return;
            }
            event.preventDefault();
            showLoader();
            form.setAttribute(submitGuardAttr, '1');
            window.requestAnimationFrame(function () {
                window.setTimeout(function () {
                    if (typeof form.submit === 'function') {
                        form.submit();
                    }
                }, 20);
            });
        });

        document.addEventListener('click', function (event) {
            var anchor = event.target.closest('a');
            if (shouldHandleLink(anchor, event)) {
                event.preventDefault();
                showLoader();
                var href = anchor.href;
                window.requestAnimationFrame(function () {
                    window.setTimeout(function () {
                        window.location.href = href;
                    }, 20);
                });
            }
        });
    });

    window.addEventListener('beforeunload', function () {
        showLoader();
    });

    window.addEventListener('pageshow', function () {
        hideLoader();
    });
})();
