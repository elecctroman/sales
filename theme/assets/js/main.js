document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;

    document.querySelectorAll('[data-hero-slider]').forEach((slider) => {
        const slides = Array.from(slider.querySelectorAll('[data-hero-slide]'));
        if (!slides.length) {
            return;
        }

        const root = slider.closest('.hero__main') || slider.parentElement;
        const dotsWrapper = root ? root.querySelector('[data-hero-dots]') : null;
        const prevButton = root ? root.querySelector('[data-hero-prev]') : null;
        const nextButton = root ? root.querySelector('[data-hero-next]') : null;
        const dots = dotsWrapper ? Array.from(dotsWrapper.querySelectorAll('[data-hero-dot]')) : [];

        let currentIndex = slides.findIndex((slide) => slide.classList.contains('is-active'));
        if (currentIndex < 0) {
            currentIndex = 0;
        }

        const activate = (target) => {
            slides.forEach((slide, index) => {
                const isActive = index === target;
                slide.classList.toggle('is-active', isActive);
                slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
            });

            dots.forEach((dot, index) => {
                const isActive = index === target;
                dot.classList.toggle('is-active', isActive);
                dot.setAttribute('aria-current', isActive ? 'true' : 'false');
            });

            currentIndex = target;
        };

        const goTo = (target) => {
            const total = slides.length;
            if (!total) {
                return;
            }
            const nextIndex = (target + total) % total;
            activate(nextIndex);
            restartAuto();
        };

        const step = (delta) => {
            goTo(currentIndex + delta);
        };

        if (prevButton) {
            prevButton.addEventListener('click', () => step(-1));
        }

        if (nextButton) {
            nextButton.addEventListener('click', () => step(1));
        }

        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => goTo(index));
        });

        let autoTimer = null;

        const stopAuto = () => {
            if (autoTimer) {
                clearInterval(autoTimer);
                autoTimer = null;
            }
        };

        const startAuto = () => {
            if (slides.length < 2) {
                return;
            }
            stopAuto();
            autoTimer = setInterval(() => step(1), 6000);
        };

        const restartAuto = () => {
            stopAuto();
            startAuto();
        };

        const hoverables = [slider, dotsWrapper, prevButton, nextButton].filter(Boolean);
        hoverables.forEach((element) => {
            element.addEventListener('mouseenter', stopAuto);
            element.addEventListener('mouseleave', startAuto);
        });

        activate(currentIndex);
        startAuto();
    });

    const notificationRoot = document.querySelector('[data-notification-root]');
    if (notificationRoot) {
        const toggle = notificationRoot.querySelector('[data-notification-toggle]');
        const panel = notificationRoot.querySelector('[data-notification-panel]');
        const list = panel ? panel.querySelector('[data-notification-list]') : null;
        const countBadge = notificationRoot.querySelector('[data-notification-count]');
        const markAllButton = panel ? panel.querySelector('[data-notification-mark-all]') : null;
        const payload = window.__APP_NOTIFICATIONS || {};
        let notifications = [];
        let hasFetchedOnce = false;
        let isFetching = false;
        let lastFetchAt = 0;
        const FETCH_COOLDOWN = 60000;

        const normaliseNotifications = (items) => {
            if (!Array.isArray(items)) {
                return [];
            }

            return items.map((entry) => ({
                id: Number(entry.id),
                title: entry.title || '',
                message: entry.message || '',
                link: entry.link || '',
                is_read: Boolean(entry.is_read),
                published_at_human: entry.published_at_human || '',
            }));
        };

        const updateBadge = () => {
            const unread = notifications.reduce((carry, item) => carry + (item.is_read ? 0 : 1), 0);
            if (!countBadge) {
                return;
            }

            if (unread > 0) {
                countBadge.textContent = unread > 99 ? '99+' : String(unread);
                countBadge.classList.remove('is-hidden');
                notificationRoot.classList.add('has-unread');
            } else {
                countBadge.classList.add('is-hidden');
                notificationRoot.classList.remove('has-unread');
            }
        };

        const renderList = () => {
            if (!list) {
                return;
            }

            if (!notifications.length) {
                list.innerHTML = '<div class="site-header__notification-empty">Yeni bildiriminiz bulunmuyor.</div>';
                return;
            }

            const fragment = document.createDocumentFragment();
            notifications.forEach((entry) => {
                const item = document.createElement('article');
                item.className = 'site-header__notification-item' + (entry.is_read ? '' : ' is-unread');
                item.dataset.notificationId = entry.id;

                const content = document.createElement('div');
                content.className = 'site-header__notification-content';

                const title = document.createElement('strong');
                title.textContent = entry.title;
                const message = document.createElement('p');
                message.textContent = entry.message;
                content.appendChild(title);
                content.appendChild(message);

                if (entry.published_at_human) {
                    const time = document.createElement('span');
                    time.className = 'site-header__notification-time';
                    time.textContent = entry.published_at_human;
                    content.appendChild(time);
                }

                item.appendChild(content);

                if (entry.link) {
                    const link = document.createElement('a');
                    link.className = 'site-header__notification-link';
                    link.href = entry.link;
                    link.textContent = 'Incele';
                    link.rel = 'nofollow';
                    item.appendChild(link);
                }

                fragment.appendChild(item);
            });

            list.innerHTML = '';
            list.appendChild(fragment);
        };

        const applyNotifications = (items) => {
            notifications = normaliseNotifications(items);
            updateBadge();
            renderList();
        };

        const fetchNotifications = (force = false) => {
            const now = Date.now();
            if (isFetching || (!force && hasFetchedOnce && now - lastFetchAt < FETCH_COOLDOWN)) {
                return Promise.resolve();
            }

            isFetching = true;

            return fetch('/notifications.php', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Failed to load notifications');
                    }
                    return response.json();
                })
                .then((data) => {
                    if (!data || typeof data !== 'object' || data.success === false) {
                        return;
                    }

                    if (Array.isArray(data.items)) {
                        applyNotifications(data.items);
                        if (typeof window !== 'undefined') {
                            window.__APP_NOTIFICATIONS = {
                                items: data.items,
                                unread: data.unread || 0,
                            };
                        }
                    }
                })
                .catch(() => {
                    // Ignore fetch errors silently to keep the UI responsive.
                })
                .finally(() => {
                    isFetching = false;
                    hasFetchedOnce = true;
                    lastFetchAt = Date.now();
                });
        };

        applyNotifications(payload.items);

        const closePanel = () => {
            notificationRoot.classList.remove('is-open');
        };

        const openPanel = () => {
            notificationRoot.classList.add('is-open');
            fetchNotifications(false);
        };

        if (toggle && panel) {
            toggle.addEventListener('click', (event) => {
                event.preventDefault();
                if (notificationRoot.classList.contains('is-open')) {
                    closePanel();
                } else {
                    openPanel();
                }
            });
        }

        document.addEventListener('click', (event) => {
            if (!notificationRoot.contains(event.target)) {
                closePanel();
            }
        });

        const markNotificationAsRead = (id) => {
            fetch('/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ action: 'mark-read', id }),
                credentials: 'same-origin',
            }).catch(() => {});
        };

        const markAllNotifications = () => {
            fetch('/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ action: 'mark-all' }),
                credentials: 'same-origin',
            }).catch(() => {});
        };

        if (list) {
            list.addEventListener('click', (event) => {
                const item = event.target.closest('[data-notification-id]');
                if (!item) {
                    return;
                }

                const id = Number(item.dataset.notificationId);
                const entry = notifications.find((candidate) => Number(candidate.id) === id);
                if (!entry) {
                    return;
                }

                if (!entry.is_read) {
                    entry.is_read = true;
                    item.classList.remove('is-unread');
                    updateBadge();
                    markNotificationAsRead(id);
                }

                const link = item.querySelector('.site-header__notification-link');
                if (link && (event.target === link || link.contains(event.target))) {
                    closePanel();
                    return;
                }

                if (link && link.href) {
                    closePanel();
                    window.location.href = link.href;
                }
            });
        }

        if (markAllButton) {
            markAllButton.addEventListener('click', (event) => {
                event.preventDefault();
                let hasUnread = false;
                notifications = notifications.map((entry) => {
                    if (!entry.is_read) {
                        hasUnread = true;
                    }
                    return Object.assign({}, entry, { is_read: true });
                });
                if (!hasUnread) {
                    return;
                }
                updateBadge();
                renderList();
                markAllNotifications();
                fetchNotifications(true);
            });
        }

        updateBadge();
        renderList();
    }

    document.querySelectorAll('[data-account-wrapper]').forEach((wrapper) => {
        const buttons = wrapper.querySelectorAll('[data-account-tab]');
        const panels = wrapper.querySelectorAll('[data-account-panel]');
        if (!buttons.length || !panels.length) {
            return;
        }

        const setActive = (target) => {
            if (!target) {
                return;
            }
            buttons.forEach((button) => {
                const isActive = button.dataset.accountTab === target;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });
            panels.forEach((panel) => {
                const isActive = panel.dataset.accountPanel === target;
                panel.classList.toggle('is-active', isActive);
                panel.setAttribute('aria-hidden', isActive ? 'false' : 'true');
            });
            wrapper.setAttribute('data-account-active', target);
        };

        const initial = wrapper.getAttribute('data-account-active') || (buttons[0] ? buttons[0].dataset.accountTab : null);
        if (initial) {
            setActive(initial);
        }

        buttons.forEach((button) => {
            button.addEventListener('click', (event) => {
                if (typeof event.preventDefault === 'function') {
                    event.preventDefault();
                }
                const targetTab = button.dataset.accountTab;
                if (!targetTab) {
                    return;
                }
                setActive(targetTab);
                const href = button.getAttribute('href');
                if (href && typeof window.history.replaceState === 'function') {
                    window.history.replaceState({}, '', href);
                }
            });
        });
    });

    const ensureToastContainer = () => {
        let container = document.querySelector('[data-toast-container]');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            container.dataset.toastContainer = 'true';
            document.body.appendChild(container);
        }
        return container;
    };

    const showToast = (title, message = '') => {
        const container = ensureToastContainer();
        const toast = document.createElement('div');
        toast.className = 'toast';

        const titleEl = document.createElement('span');
        titleEl.className = 'toast__title';
        titleEl.textContent = title;
        toast.appendChild(titleEl);

        if (message) {
            const messageEl = document.createElement('span');
            messageEl.className = 'toast__message';
            messageEl.textContent = message;
            toast.appendChild(messageEl);
        }

        container.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('is-leaving');
            toast.addEventListener('transitionend', () => {
                toast.remove();
            }, { once: true });
        }, 3500);
    };

    const updateCartBadge = (cart) => {
        const badge = document.querySelector('[data-cart-count]');
        if (!badge) {
            return;
        }
        const quantity = cart && cart.totals ? Number(cart.totals.total_quantity || 0) : 0;
        badge.textContent = quantity;
        if (quantity > 0) {
            badge.classList.remove('is-hidden');
        } else {
            badge.classList.add('is-hidden');
        }
    };

    const requestCart = async (payload) => {
        const response = await fetch('/cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new URLSearchParams(payload),
        });

        let data;
        try {
            data = await response.json();
        } catch (error) {
            throw new Error('Beklenmedik bir hata olustu.');
        }

        if (!response.ok || !data.success) {
            throw new Error(data && data.message ? data.message : 'Islem tamamlanamadi.');
        }

        updateCartBadge(data.cart);
        return data;
    };

    const addButtons = document.querySelectorAll('[data-add-to-cart]');
    addButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            if (button.disabled) {
                return;
            }
            const productId = button.dataset.productId;
            if (!productId) {
                console.warn('Add to cart button missing product id', button);
                return;
            }

            const productName = button.dataset.productName || 'Urun';
            const originalText = button.textContent;
            button.disabled = true;
            button.textContent = 'Ekleniyor...';
            console.log('Add to cart clicked', { productId, productName });

            try {
                const data = await requestCart({
                    action: 'add',
                    product_id: productId,
                    quantity: 1,
                });
                const message = data.message || `${productName} sepetinize eklendi.`;
                showToast('Sepete Eklendi', message);
            } catch (error) {
                showToast('Islem Basarisiz', error.message || 'Lutfen tekrar deneyin.');
            } finally {
                button.disabled = false;
                button.textContent = originalText;
            }
        });
    });

    const buyNowButtons = document.querySelectorAll('[data-buy-now]');
    buyNowButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            if (button.disabled) {
                return;
            }
            const productId = button.dataset.productId;
            if (!productId) {
                return;
            }

            const originalText = button.textContent;
            button.disabled = true;
            button.textContent = 'Yonlendiriliyor...';

            try {
                await requestCart({
                    action: 'add',
                    product_id: productId,
                    quantity: 1,
                });

                const params = new URLSearchParams();
                params.set('checkout', '1');
                params.set('method', 'card');
                window.location.href = '/cart.php?' + params.toString();
            } catch (error) {
                showToast('Islem Basarisiz', error.message || 'Lutfen tekrar deneyin.');
                button.disabled = false;
                button.textContent = originalText;
            }
        });
    });

    const cartPage = document.querySelector('[data-cart-page]');
    if (cartPage) {
        cartPage.addEventListener('submit', async (event) => {
            const couponForm = event.target.closest('[data-cart-coupon-form]');
            if (!couponForm) {
                return;
            }

            event.preventDefault();

            const input = couponForm.querySelector('input[name="coupon_code"]');
            const submitButton = couponForm.querySelector('button[type="submit"]');
            const code = input ? input.value.trim() : '';

            if (code === '') {
                showToast('Kupon', 'Lütfen kupon kodu giriniz.');
                return;
            }

            const originalText = submitButton ? submitButton.textContent : '';
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Uygulaniyor...';
            }

            try {
                const data = await requestCart({
                    action: 'apply_coupon',
                    coupon_code: code,
                });
                showToast('Kupon Uygulandi', data.message || 'Kupon başarıyla uygulandı.');
                setTimeout(() => window.location.reload(), 200);
            } catch (error) {
                showToast('Kupon Hatası', error.message || 'Kupon uygulanamadı.');
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalText || 'Kuponu Uygula';
                }
            }
        });

        cartPage.addEventListener('click', async (event) => {
            const stepButton = event.target.closest('[data-cart-step]');
            if (stepButton) {
                const productId = stepButton.dataset.productId;
                const delta = parseInt(stepButton.dataset.delta || '0', 10);
                const item = stepButton.closest('[data-cart-item]');
                const currentQuantity = item ? parseInt(item.dataset.quantity || '1', 10) : 1;
                const nextQuantity = currentQuantity + delta;

                try {
                    if (nextQuantity <= 0) {
                        const data = await requestCart({ action: 'remove', product_id: productId });
                        showToast('Sepet Guncellendi', data.message || 'Urun sepetten kaldirildi.');
                    } else {
                        const data = await requestCart({ action: 'update', product_id: productId, quantity: nextQuantity });
                        showToast('Sepet Guncellendi', data.message || 'Urun adedi guncellendi.');
                    }
                    setTimeout(() => window.location.reload(), 200);
                } catch (error) {
                    showToast('Islem Basarisiz', error.message || 'Lutfen tekrar deneyin.');
                }
                return;
            }

            const removeButton = event.target.closest('[data-cart-remove]');
            if (removeButton) {
                const productId = removeButton.dataset.productId;
                try {
                    const data = await requestCart({ action: 'remove', product_id: productId });
                    showToast('Urun Kaldirildi', data.message || 'Urun sepetten kaldirildi.');
                    setTimeout(() => window.location.reload(), 200);
                } catch (error) {
                    showToast('Islem Basarisiz', error.message || 'Lutfen tekrar deneyin.');
                }
                return;
            }

            const couponRemove = event.target.closest('[data-cart-coupon-remove]');
            if (couponRemove) {
                if (couponRemove.disabled) {
                    return;
                }

                couponRemove.disabled = true;
                try {
                    const data = await requestCart({ action: 'remove_coupon' });
                    showToast('Kupon Kaldırıldı', data.message || 'Kupon kaldırıldı.');
                    setTimeout(() => window.location.reload(), 200);
                } catch (error) {
                    showToast('Kupon Hatası', error.message || 'Kupon kaldırılamadı.');
                    couponRemove.disabled = false;
                }
                return;
            }

            const clearButton = event.target.closest('[data-cart-clear]');
            if (clearButton) {
                try {
                    const data = await requestCart({ action: 'clear' });
                    showToast('Sepet Temizlendi', data.message || 'Sepetiniz temizlendi.');
                    setTimeout(() => window.location.reload(), 200);
                } catch (error) {
                    showToast('Islem Basarisiz', error.message || 'Lutfen tekrar deneyin.');
                }
            }
        });
    }

    const checkoutModal = document.querySelector('[data-checkout-modal]');
    const checkoutMethodField = checkoutModal ? checkoutModal.querySelector('[data-checkout-method-field]') : null;
    const checkoutMethodSelect = checkoutModal ? checkoutModal.querySelector('select[name="payment_option"]') : null;
    const checkoutForm = checkoutModal ? checkoutModal.querySelector('[data-checkout-form]') : null;

    const openCheckoutModal = (method) => {
        if (!checkoutModal) {
            return;
        }
        checkoutModal.removeAttribute('hidden');
        checkoutModal.classList.add('is-open');
        body.classList.add('is-modal-open');
        if (checkoutMethodField) {
            checkoutMethodField.value = method;
        }
        if (checkoutMethodSelect && checkoutMethodSelect.value !== method) {
            checkoutMethodSelect.value = method;
        }
    };

    const closeCheckoutModal = () => {
        if (!checkoutModal) {
            return;
        }
        checkoutModal.setAttribute('hidden', 'true');
        checkoutModal.classList.remove('is-open');
        body.classList.remove('is-modal-open');
    };

    document.querySelectorAll('[data-checkout-trigger]').forEach((button) => {
        button.addEventListener('click', () => {
            if (button.disabled) {
                return;
            }
            const method = button.dataset.method || 'card';
            openCheckoutModal(method);
        });
    });

    if (checkoutModal) {
        const checkoutParams = new URLSearchParams(window.location.search);
        if (checkoutParams.get('checkout') === '1') {
            const presetMethod = checkoutParams.get('method') || 'card';
            openCheckoutModal(presetMethod);
            checkoutParams.delete('checkout');
            checkoutParams.delete('method');
            const cleaned = checkoutParams.toString();
            const nextUrl = window.location.pathname + (cleaned ? '?' + cleaned : '');
            window.history.replaceState({}, '', nextUrl);
        }
    }

    if (checkoutForm) {
        const checkoutSubmit = checkoutForm.querySelector('.cart-modal__submit');
        checkoutForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (checkoutSubmit) {
                checkoutSubmit.disabled = true;
                checkoutSubmit.textContent = 'Isleniyor...';
            }

            try {
                const formData = new FormData(checkoutForm);
                const response = await fetch('/checkout.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        Accept: 'application/json',
                    },
                });

                let payload;
                try {
                    payload = await response.json();
                } catch (parseError) {
                    payload = { success: false, error: 'Sunucudan beklenmeyen yanit alindi.' };
                }

                if (!response.ok || !payload || !payload.success) {
                    const errorMessage = payload && payload.error ? payload.error : 'Odeme islenemedi. Lutfen tekrar deneyin.';
                    showToast('Islem Basarisiz', errorMessage);
                    return;
                }

                closeCheckoutModal();

                if (payload.redirect) {
                    window.location.href = payload.redirect;
                    return;
                }

                showToast('Islem Basarili', payload.message || 'Siparisiniz alindi.');
            } catch (error) {
                showToast('Islem Basarisiz', 'Odeme islenemedi. Lutfen tekrar deneyin.');
            } finally {
                if (checkoutSubmit) {
                    checkoutSubmit.disabled = false;
                    checkoutSubmit.textContent = 'Odemeye Gec';
                }
            }
        });
    }

    document.querySelectorAll('[data-checkout-dismiss]').forEach((button) => {
        button.addEventListener('click', () => {
            closeCheckoutModal();
        });
    });

    const orderDetailToggles = document.querySelectorAll('[data-order-toggle]');

    if (orderDetailToggles.length) {
        const resetOrderToggle = (row) => {
            row.classList.remove('is-open');
            const relatedButton = document.querySelector('[data-order-toggle=\"' + row.id + '\"]');
            if (relatedButton) {
                relatedButton.setAttribute('aria-expanded', 'false');
                relatedButton.textContent = 'Goruntule';
            }
        };

        orderDetailToggles.forEach((toggle) => {
            toggle.addEventListener('click', () => {
                const targetId = toggle.getAttribute('data-order-toggle');
                if (!targetId) {
                    return;
                }

                const targetRow = document.getElementById(targetId);
                if (!targetRow) {
                    return;
                }

                const willOpen = !targetRow.classList.contains('is-open');

                document.querySelectorAll('[data-order-details].is-open').forEach((row) => {
                    if (row !== targetRow) {
                        resetOrderToggle(row);
                    }
                });

                if (willOpen) {
                    targetRow.classList.add('is-open');
                    toggle.setAttribute('aria-expanded', 'true');
                    toggle.textContent = 'Kapat';
                } else {
                    resetOrderToggle(targetRow);
                }
            });
        });
    }

    if (checkoutMethodSelect) {
        checkoutMethodSelect.addEventListener('change', (event) => {
            if (checkoutMethodField) {
                checkoutMethodField.value = event.target.value;
            }
        });
    }

    if (checkoutForm) {
        checkoutForm.addEventListener('submit', (event) => {
            event.preventDefault();
            closeCheckoutModal();
            showToast('Odeme Yonlendirmesi', 'Odeme islemi icin yonlendiriliyorsunuz.');
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeCheckoutModal();
        }
    });

    document.querySelectorAll('[data-copy-target]').forEach((button) => {
        const targetSelector = button.getAttribute('data-copy-target');
        if (!targetSelector) {
            return;
        }

        const originalLabel = button.textContent.trim();
        const successLabel = button.getAttribute('data-copy-success') || 'Kopyalandı!';

        const resetState = () => {
            button.classList.remove('is-copied');
            if (originalLabel !== '') {
                button.textContent = originalLabel;
            }
        };

        const showSuccess = () => {
            if (successLabel !== '') {
                button.textContent = successLabel;
            }
            button.classList.add('is-copied');
            setTimeout(() => {
                resetState();
            }, 2000);
        };

        const fallbackCopy = (value) => {
            const helper = document.createElement('textarea');
            helper.value = value;
            helper.setAttribute('readonly', 'readonly');
            helper.style.position = 'absolute';
            helper.style.left = '-9999px';
            document.body.appendChild(helper);
            helper.select();
            try {
                const copied = document.execCommand('copy');
                if (copied) {
                    showSuccess();
                }
            } catch (error) {
                console.warn('Copy command is not supported', error);
            }
            document.body.removeChild(helper);
        };

        const handleCopy = () => {
            const target = document.querySelector(targetSelector);
            if (!target) {
                return;
            }

            let value = '';
            if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) {
                value = target.value;
            } else {
                value = target.textContent || '';
            }

            if (value === '') {
                return;
            }

            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                navigator.clipboard.writeText(value).then(() => {
                    showSuccess();
                }).catch(() => {
                    fallbackCopy(value);
                });
            } else {
                fallbackCopy(value);
            }
        };

        button.addEventListener('click', (event) => {
            event.preventDefault();
            handleCopy();
        });
    });
});




