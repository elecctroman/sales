(function (window, document) {
    'use strict';

    const DATA_URL = '/assets/admin/data/category-icons.json';
    const ICON_INPUT_SELECTOR = '[data-icon-picker]';
    const ICON_VALUE_PATTERN = /^(iconify:[A-Za-z0-9:_-]+|[A-Za-z0-9:_-]+(?:\s+[A-Za-z0-9:_-]+)*)$/i;

    const STATE = {
        icons: null,
        listItems: [],
        overlay: null,
        overlayPanel: null,
        overlayList: null,
        overlaySearch: null,
        activePicker: null,
        loading: false
    };

    const translations = {
        choose: 'Ikon Sec',
        clear: 'Temizle',
        manualToggle: 'Ozel ikon gir',
        manualPlaceholder: 'Ornek: iconify:simple-icons:valorant veya ri-store-2-line',
        noneSelected: 'Secilmedi',
        searchPlaceholder: 'Ara (isim veya anahtar)...'
    };

    function fetchIcons() {
        if (STATE.icons) {
            return Promise.resolve(STATE.icons);
        }

        if (STATE.loading) {
            return STATE.loading;
        }

        STATE.loading = fetch(DATA_URL, {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Icon data yuklenemedi (' + response.status + ')');
            }
            return response.json();
        }).then(function (data) {
            if (!Array.isArray(data)) {
                throw new Error('Icon veri formati gecersiz.');
            }

            STATE.icons = data.map(function (item) {
                const value = typeof item.value === 'string' ? item.value.trim() : '';
                const label = typeof item.label === 'string' ? item.label.trim() : value;
                const tags = Array.isArray(item.tags) ? item.tags : [];
                return {
                    value: value,
                    label: label,
                    tags: tags.filter(Boolean),
                    searchText: [label, value]
                        .concat(tags)
                        .join(' ')
                        .toLowerCase()
                };
            }).filter(function (item) {
                return item.value !== '';
            });

            STATE.loading = null;
            return STATE.icons;
        }).catch(function (error) {
            console.error(error);
            STATE.loading = null;
            STATE.icons = [];
            return STATE.icons;
        });

        return STATE.loading;
    }

    function createOverlay() {
        if (STATE.overlay) {
            return;
        }

        const overlay = document.createElement('div');
        overlay.className = 'icon-picker__overlay';
        overlay.setAttribute('hidden', 'hidden');

        overlay.innerHTML = '' +
            '<div class="icon-picker__panel">' +
            '   <div class="icon-picker__panel-header">' +
            '       <input type="search" class="form-control icon-picker__search" placeholder="' + translations.searchPlaceholder + '" aria-label="' + translations.searchPlaceholder + '">' +
            '       <button type="button" class="btn-close icon-picker__close" aria-label="Kapat"></button>' +
            '   </div>' +
            '   <div class="icon-picker__list" role="listbox"></div>' +
            '</div>';

        document.body.appendChild(overlay);

        const panel = overlay.querySelector('.icon-picker__panel');
        const searchInput = overlay.querySelector('.icon-picker__search');
        const closeBtn = overlay.querySelector('.icon-picker__close');
        const list = overlay.querySelector('.icon-picker__list');

        overlay.addEventListener('click', function (evt) {
            if (evt.target === overlay) {
                closeOverlay();
            }
        });

        closeBtn.addEventListener('click', function () {
            closeOverlay();
        });

        searchInput.addEventListener('input', function (evt) {
            filterIcons(evt.target.value || '');
        });

        list.addEventListener('click', function (evt) {
            const item = evt.target.closest('[data-icon-value]');
            if (!item) {
                return;
            }
            evt.preventDefault();
            selectIconValue(item.getAttribute('data-icon-value'), item.getAttribute('data-icon-label'));
        });

        document.addEventListener('keydown', function (evt) {
            if (evt.key === 'Escape' && !overlay.hasAttribute('hidden')) {
                closeOverlay();
            }
        });

        STATE.overlay = overlay;
        STATE.overlayPanel = panel;
        STATE.overlayList = list;
        STATE.overlaySearch = searchInput;
    }

    function openOverlay(pickerInstance) {
        createOverlay();
        STATE.activePicker = pickerInstance;
        STATE.overlaySearch.value = '';
        STATE.overlay.classList.add('is-visible');
        STATE.overlay.removeAttribute('hidden');
        document.body.classList.add('icon-picker-open');

        populateList().then(function () {
            highlightActiveValue();
        });
    }

    function closeOverlay() {
        if (STATE.overlay) {
            STATE.overlay.classList.remove('is-visible');
            STATE.overlay.setAttribute('hidden', 'hidden');
        }
        document.body.classList.remove('icon-picker-open');
        STATE.activePicker = null;
    }

    function populateList() {
        return fetchIcons().then(function (icons) {
            if (!STATE.overlayList) {
                return;
            }

            if (STATE.overlayList.childElementCount === icons.length) {
                STATE.overlaySearch.focus();
                return;
            }

            const fragment = document.createDocumentFragment();
            icons.forEach(function (icon) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'icon-picker__item';
                button.setAttribute('data-icon-value', icon.value);
                button.setAttribute('data-icon-label', icon.label);
                button.setAttribute('data-search-text', icon.searchText);
                button.innerHTML = '' +
                    '<span class="icon-picker__item-icon">' + renderIconMarkup(icon.value) + '</span>' +
                    '<span class="icon-picker__item-label">' + escapeHtml(icon.label) + '</span>';
                fragment.appendChild(button);
            });

            STATE.overlayList.innerHTML = '';
            STATE.overlayList.appendChild(fragment);
            STATE.listItems = Array.prototype.slice.call(STATE.overlayList.querySelectorAll('[data-icon-value]'));

            STATE.overlaySearch.focus();
        });
    }

    function filterIcons(query) {
        const term = (query || '').toLowerCase();
        if (!STATE.listItems || !STATE.listItems.length) {
            return;
        }

        STATE.listItems.forEach(function (item) {
            const haystack = item.getAttribute('data-search-text') || '';
            if (!term || haystack.indexOf(term) !== -1) {
                item.classList.remove('is-hidden');
            } else {
                item.classList.add('is-hidden');
            }
        });
    }

    function highlightActiveValue() {
        if (!STATE.activePicker || !STATE.listItems) {
            return;
        }

        const value = STATE.activePicker.value || '';
        STATE.listItems.forEach(function (item) {
            if (item.getAttribute('data-icon-value') === value) {
                item.classList.add('is-selected');
            } else {
                item.classList.remove('is-selected');
            }
        });
    }

    function selectIconValue(value, label) {
        if (!STATE.activePicker) {
            return;
        }
        STATE.activePicker.setValue(value || '', label || '');
        closeOverlay();
    }

    function renderIconMarkup(value, extraClass) {
        if (!value) {
            return '';
        }

        var classes = extraClass || '';

        if (value.indexOf('iconify:') === 0) {
            var iconName = value.slice(8);
            if (!iconName) {
                return '';
            }
            var className = ('iconify ' + (classes || '')).trim();
            return '<span class="' + escapeHtml(className) + '" data-icon="' + escapeHtml(iconName) + '"></span>';
        }

        var classAttr = (value + ' ' + (classes || '')).trim();
        return '<i class="' + escapeHtml(classAttr) + '"></i>';
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function IconPicker(input) {
        this.input = input;
        this.value = input.value ? input.value.trim() : '';
        this.manualMode = false;
        this.wrapper = document.createElement('div');
        this.wrapper.className = 'icon-picker';
        input.type = 'hidden';
        input.setAttribute('data-icon-picker-source', 'hidden');
        input.parentNode.insertBefore(this.wrapper, input);
        this.wrapper.appendChild(input);
        this.build();
        if (this.manualInput) {
            this.manualInput.value = this.value;
        }
        this.updatePreview();
    }

    IconPicker.prototype.build = function () {
        var preview = document.createElement('div');
        preview.className = 'icon-picker__preview';
        preview.innerHTML = '<span class="icon-picker__preview-icon"></span><span class="icon-picker__preview-label"></span>';

        var actions = document.createElement('div');
        actions.className = 'icon-picker__actions';

        var chooseBtn = document.createElement('button');
        chooseBtn.type = 'button';
        chooseBtn.className = 'btn btn-outline-secondary btn-sm icon-picker__choose';
        chooseBtn.textContent = translations.choose;

        var clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'btn btn-link btn-sm icon-picker__clear';
        clearBtn.textContent = translations.clear;

        var manualToggle = document.createElement('button');
        manualToggle.type = 'button';
        manualToggle.className = 'btn btn-link btn-sm icon-picker__manual-toggle';
        manualToggle.textContent = translations.manualToggle;

        var manualWrapper = document.createElement('div');
        manualWrapper.className = 'icon-picker__manual d-none';
        var manualInput = document.createElement('input');
        manualInput.type = 'text';
        manualInput.className = 'form-control icon-picker__manual-input';
        manualInput.placeholder = translations.manualPlaceholder;
        manualWrapper.appendChild(manualInput);

        actions.appendChild(chooseBtn);
        actions.appendChild(clearBtn);
        actions.appendChild(manualToggle);

        this.wrapper.appendChild(preview);
        this.wrapper.appendChild(actions);
        this.wrapper.appendChild(manualWrapper);

        this.previewIcon = preview.querySelector('.icon-picker__preview-icon');
        this.previewLabel = preview.querySelector('.icon-picker__preview-label');
        this.chooseBtn = chooseBtn;
        this.clearBtn = clearBtn;
        this.manualToggle = manualToggle;
        this.manualWrapper = manualWrapper;
        this.manualInput = manualInput;

        var self = this;

        chooseBtn.addEventListener('click', function () {
            openOverlay(self);
        });

        clearBtn.addEventListener('click', function () {
            self.setValue('');
            self.manualInput.value = '';
        });

        manualToggle.addEventListener('click', function () {
            self.manualWrapper.classList.toggle('d-none');
            if (!self.manualWrapper.classList.contains('d-none')) {
                self.manualInput.focus();
                self.manualInput.select();
            }
        });

        manualInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                self.setValue(event.target.value || '');
            }
        });

        manualInput.addEventListener('blur', function (event) {
            if (event.target.value.trim() === '') {
                return;
            }
            self.setValue(event.target.value || '');
        });
    };

    IconPicker.prototype.setValue = function (newValue, label) {
        const normalized = (newValue || '').trim().replace(/\s+/g, ' ');

        if (normalized && !ICON_VALUE_PATTERN.test(normalized)) {
            this.wrapper.classList.add('icon-picker--invalid');
            if (this.manualInput) {
                this.manualInput.classList.add('is-invalid');
                this.manualInput.setCustomValidity('Geçersiz ikon değeri');
            }
            return;
        }

        this.wrapper.classList.remove('icon-picker--invalid');
        this.value = normalized;
        this.input.value = this.value;

        if (this.manualInput) {
            this.manualInput.classList.remove('is-invalid');
            this.manualInput.setCustomValidity('');
            this.manualInput.value = this.value;
        }

        this.updatePreview(typeof label === 'string' ? label : null);
    };

    IconPicker.prototype.updatePreview = function (label) {
        var value = this.value;
        var iconLabel = label || '';

        if (!iconLabel && value && STATE.icons) {
            var match = STATE.icons.find(function (icon) {
                return icon.value === value;
            });
            if (match) {
                iconLabel = match.label;
            }
        }

        this.previewIcon.innerHTML = renderIconMarkup(value, 'icon-picker__preview-icon-inner');
        this.previewLabel.textContent = iconLabel || (value || translations.noneSelected);
    };

    function initAll() {
        createOverlay();
        var inputs = document.querySelectorAll(ICON_INPUT_SELECTOR);

        if (!inputs.length) {
            return;
        }

        Array.prototype.forEach.call(inputs, function (input) {
            if (input.getAttribute('data-icon-picker-initialized') === 'true') {
                return;
            }
            input.setAttribute('data-icon-picker-initialized', 'true');
            new IconPicker(input);
        });
    }

    window.AdminIconPicker = {
        init: initAll
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})(window, document);
