(function () {
    var toggleButtons = document.querySelectorAll('[data-sidebar-toggle]');
    var closeTargets = document.querySelectorAll('[data-sidebar-close]');
    var body = document.body;
    var sidebar = document.getElementById('appSidebar');

    if (!toggleButtons.length || !sidebar) {
        return;
    }

    var openSidebar = function () {
        body.classList.add('app-sidebar-open');
        sidebar.setAttribute('aria-hidden', 'false');
        toggleButtons.forEach(function (btn) {
            btn.setAttribute('aria-expanded', 'true');
        });
    };

    var closeSidebar = function () {
        body.classList.remove('app-sidebar-open');
        sidebar.setAttribute('aria-hidden', 'true');
        toggleButtons.forEach(function (btn) {
            btn.setAttribute('aria-expanded', 'false');
        });
    };

    toggleButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (body.classList.contains('app-sidebar-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    });

    closeTargets.forEach(function (target) {
        target.addEventListener('click', closeSidebar);
    });

    var collapsibleTriggers = document.querySelectorAll('[data-menu-toggle]');

    collapsibleTriggers.forEach(function (trigger) {
        var item = trigger.closest('.sidebar-item');
        var submenu = item ? item.querySelector('.sidebar-submenu') : null;

        if (!item || !submenu) {
            return;
        }

        var setExpanded = function (state) {
            trigger.setAttribute('aria-expanded', state ? 'true' : 'false');
        };

        setExpanded(item.classList.contains('is-open'));

        trigger.addEventListener('click', function () {
            var isOpen = item.classList.toggle('is-open');
            setExpanded(isOpen);
        });
    });

    var syncStateForViewport = function () {
        if (window.innerWidth >= 992) {
            body.classList.remove('app-sidebar-open');
            sidebar.setAttribute('aria-hidden', 'false');
            toggleButtons.forEach(function (btn) {
                btn.setAttribute('aria-expanded', 'false');
            });
        } else {
            closeSidebar();
        }
    };

    syncStateForViewport();

    window.addEventListener('resize', syncStateForViewport);
})();
