(function () {
    var body = document.body;
    var toggleButtons = document.querySelectorAll('[data-sidebar-toggle]');
    var closeTargets = document.querySelectorAll('[data-sidebar-close]');
    var sidebar = document.getElementById('appSidebar');

    var openSidebar = function () {
        if (!sidebar) {
            return;
        }
        body.classList.add('app-sidebar-open');
        sidebar.setAttribute('aria-hidden', 'false');
        toggleButtons.forEach(function (btn) {
            btn.setAttribute('aria-expanded', 'true');
        });
    };

    var closeSidebar = function () {
        if (!sidebar) {
            return;
        }
        body.classList.remove('app-sidebar-open');
        sidebar.setAttribute('aria-hidden', 'true');
        toggleButtons.forEach(function (btn) {
            btn.setAttribute('aria-expanded', 'false');
        });
    };

    if (toggleButtons.length && sidebar) {
        toggleButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (body.classList.contains('app-sidebar-open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });
        });
    }

    if (closeTargets.length) {
        closeTargets.forEach(function (target) {
            target.addEventListener('click', closeSidebar);
        });
    }

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
        if (!sidebar) {
            return;
        }
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

    if (sidebar) {
        syncStateForViewport();
        window.addEventListener('resize', syncStateForViewport);
    }

    var editModalEl = document.getElementById('editUserModal');
    if (editModalEl) {
        var editForm = document.getElementById('editUserForm');
        var idInput = document.getElementById('editUserId');
        var nameInput = document.getElementById('editUserName');
        var emailInput = document.getElementById('editUserEmail');
        var statusSelect = document.getElementById('editUserStatus');
        var roleSelect = document.getElementById('editUserRole');
        var passwordInput = document.getElementById('editUserPassword');
        var balanceDirection = document.getElementById('editUserBalanceDirection');
        var balanceAmount = document.getElementById('editUserBalanceAmount');
        var balanceNote = document.getElementById('editUserBalanceNote');
        var currentBalance = document.getElementById('editUserCurrentBalance');
        var createdAtInput = document.getElementById('editUserCreatedAt');
        var updatedAtInput = document.getElementById('editUserUpdatedAt');
        var metaOutput = document.getElementById('editUserMeta');

        var resetEditForm = function () {
            if (editForm) {
                editForm.reset();
            }
            if (roleSelect) {
                roleSelect.innerHTML = '<option value="">Rol yükleniyor...</option>';
                roleSelect.disabled = true;
            }
            if (currentBalance) {
                currentBalance.value = '';
            }
            if (createdAtInput) {
                createdAtInput.value = '';
            }
            if (updatedAtInput) {
                updatedAtInput.value = '';
            }
            if (metaOutput) {
                metaOutput.textContent = '';
            }
        };

        resetEditForm();

        editModalEl.addEventListener('show.bs.modal', function (event) {
            resetEditForm();
            var trigger = event.relatedTarget;
            if (!trigger) {
                return;
            }

            var payload = trigger.getAttribute('data-user');
            if (!payload) {
                return;
            }

            var data;
            try {
                data = JSON.parse(payload);
            } catch (error) {
                return;
            }

            if (idInput) {
                idInput.value = typeof data.id !== 'undefined' ? data.id : '';
            }
            if (nameInput) {
                nameInput.value = data.name || '';
                setTimeout(function () {
                    nameInput.focus();
                }, 100);
            }
            if (emailInput) {
                emailInput.value = data.email || '';
            }
            if (statusSelect) {
                statusSelect.value = data.status === 'inactive' ? 'inactive' : 'active';
            }
            if (roleSelect) {
                roleSelect.innerHTML = '';
                roleSelect.disabled = false;
                if (Array.isArray(data.roles) && data.roles.length) {
                    data.roles.forEach(function (roleItem) {
                        if (!roleItem || typeof roleItem.value === 'undefined') {
                            return;
                        }
                        var option = document.createElement('option');
                        option.value = roleItem.value;
                        option.textContent = roleItem.label || roleItem.value;
                        roleSelect.appendChild(option);
                    });
                }
                if (!roleSelect.options.length) {
                    var fallbackOption = document.createElement('option');
                    var roleValue = data.role || '';
                    fallbackOption.value = roleValue;
                    fallbackOption.textContent = data.role_label || roleValue || 'Rol yok';
                    roleSelect.appendChild(fallbackOption);
                    roleSelect.disabled = true;
                }
                roleSelect.value = data.role || '';
            }
            if (passwordInput) {
                passwordInput.value = '';
            }
            if (balanceDirection) {
                balanceDirection.value = 'credit';
            }
            if (balanceAmount) {
                balanceAmount.value = '0';
            }
            if (balanceNote) {
                balanceNote.value = '';
            }
            if (currentBalance) {
                currentBalance.value = data.balance_formatted || '';
            }
            if (createdAtInput) {
                createdAtInput.value = data.created_at || '';
            }
            if (updatedAtInput) {
                updatedAtInput.value = data.updated_at || '';
            }
            if (metaOutput) {
                var parts = [];
                if (typeof data.id !== 'undefined') {
                    parts.push('ID: ' + data.id);
                }
                if (data.email) {
                    parts.push(data.email);
                }
                if (data.role_label) {
                    parts.push(data.role_label);
                }
                if (data.status_label) {
                    parts.push(data.status_label);
                }
                metaOutput.textContent = parts.join(' · ');
            }
        });

        editModalEl.addEventListener('hidden.bs.modal', function () {
            resetEditForm();
        });
    }
})();
