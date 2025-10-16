(function () {
    'use strict';

    var menuManagerEl = document.querySelector('[data-menu-manager]');
    if (!menuManagerEl) {
        return;
    }

    var parseJSON = function (value) {
        if (typeof value === 'object' && value !== null) {
            return JSON.parse(JSON.stringify(value));
        }
        if (typeof value !== 'string') {
            return {};
        }
        try {
            return JSON.parse(value);
        } catch (error) {
            return {};
        }
    };

    var rawConfig = menuManagerEl.getAttribute('data-menu-config') || '{}';
    var config = parseJSON(rawConfig);

    var locations = config.locations || {};
    var structures = config.structures || {};
    var sources = config.sources || {};

    var findTree = function (location) {
        return document.querySelector('[data-menu-tree="' + location + '"]');
    };

    var findEmpty = function (location) {
        return document.querySelector('[data-menu-empty="' + location + '"]');
    };

    var getItemData = function (element) {
        var raw = element ? element.getAttribute('data-menu-item') : null;
        var data = parseJSON(raw);
        if (!data.settings) {
            data.settings = {};
        }
        if (!Array.isArray(data.children)) {
            data.children = [];
        }
        return data;
    };

    var setItemData = function (element, data) {
        if (!element) {
            return;
        }
        element.setAttribute('data-menu-item', JSON.stringify(data));
        updateSummary(element, data);
    };

    var typeLabel = function (type) {
        switch (type) {
            case 'category':
                return 'Kategori';
            case 'page':
                return 'Sayfa';
            case 'blog':
                return 'Blog';
            case 'route':
                return 'Yönetim';
            case 'group':
                return 'Grup';
            default:
                return 'Özel';
        }
    };

    var updateSummary = function (itemEl, data) {
        var labelEl = itemEl.querySelector('[data-menu-label]');
        if (labelEl) {
            labelEl.textContent = data.title && data.title !== '' ? data.title : 'Yeni Öğe';
        }
        var typeEl = itemEl.querySelector('[data-menu-type]');
        if (typeEl) {
            typeEl.textContent = typeLabel(data.type);
        }
        if (data.is_visible === false) {
            itemEl.classList.add('is-disabled');
        } else {
            itemEl.classList.remove('is-disabled');
        }
    };

    var syncEmptyState = function (location) {
        var tree = findTree(location);
        var empty = findEmpty(location);
        if (!tree || !empty) {
            return;
        }
        if (tree.querySelector('li.menu-item')) {
            empty.classList.add('is-hidden');
        } else {
            empty.classList.remove('is-hidden');
        }
    };

    var normaliseItem = function (data, defaults) {
        var base = {
            id: null,
            type: 'custom',
            reference_key: null,
            title: 'Yeni Öğe',
            url: '#',
            target: '_self',
            is_visible: true,
            settings: {},
            children: []
        };
        var result = Object.assign({}, base, defaults || {}, data || {});
        if (!result.settings) {
            result.settings = {};
        }
        if (!Array.isArray(result.children)) {
            result.children = [];
        }
        return result;
    };

    var createFieldGroup = function (label, control) {
        var wrapper = document.createElement('div');
        wrapper.className = 'mb-3';
        var labelEl = document.createElement('label');
        labelEl.className = 'form-label';
        labelEl.textContent = label;
        wrapper.appendChild(labelEl);
        wrapper.appendChild(control);
        return wrapper;
    };

    var createTextInput = function (name, value) {
        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control';
        input.setAttribute('data-menu-field', name);
        input.value = value || '';
        return input;
    };

    var createSettingsInput = function (key, value, tag) {
        var input = document.createElement(tag === 'textarea' ? 'textarea' : 'input');
        if (tag === 'textarea') {
            input.className = 'form-control';
            input.rows = 2;
        } else {
            input.type = 'text';
            input.className = 'form-control';
        }
        input.setAttribute('data-menu-setting', key);
        input.value = value || '';
        return input;
    };

    var toggleDetails = function (itemEl, force) {
        if (typeof force === 'boolean') {
            itemEl.classList.toggle('is-open', force);
        } else {
            itemEl.classList.toggle('is-open');
        }
    };

    var buildStructure = function (listEl) {
        var result = [];
        if (!listEl) {
            return result;
        }
        Array.prototype.forEach.call(listEl.children, function (child) {
            if (!child.classList.contains('menu-item')) {
                return;
            }
            var data = getItemData(child);
            var childList = child.querySelector(':scope > ol');
            data.children = buildStructure(childList);
            result.push(data);
        });
        return result;
    };

    var removeItem = function (itemEl, location) {
        if (itemEl && itemEl.parentNode) {
            itemEl.parentNode.removeChild(itemEl);
            syncEmptyState(location);
        }
    };

    var computeDepth = function (listEl) {
        var depth = 1;
        var parent = listEl;
        while (parent && parent !== document.body) {
            if (parent.classList && parent.classList.contains('menu-item')) {
                depth++;
            }
            parent = parent.parentElement;
        }
        return depth;
    };

    var initSortable = function (listEl, location) {
        if (!listEl || typeof Sortable === 'undefined') {
            return;
        }
        if (listEl._menuSortable) {
            listEl._menuSortable.destroy();
        }
        listEl._menuSortable = new Sortable(listEl, {
            group: location + '-menu',
            handle: '[data-menu-handle]',
            animation: 150,
            fallbackOnBody: true,
            swapThreshold: 0.65,
            onEnd: function () {
                syncEmptyState(location);
            },
            onMove: function (evt) {
                var depth = computeDepth(evt.to);
                var definition = locations[location] || {};
                var maxDepth = definition.max_depth || 2;
                if (depth > maxDepth) {
                    return false;
                }
                return true;
            }
        });
    };

    var createItemElement = function (location, data) {
        var itemData = normaliseItem(data);
        var li = document.createElement('li');
        li.className = 'menu-item';
        li.setAttribute('data-menu-item', JSON.stringify(itemData));

        var header = document.createElement('div');
        header.className = 'menu-item__header';

        var handle = document.createElement('span');
        handle.className = 'menu-item__drag';
        handle.setAttribute('data-menu-handle', '');
        handle.innerHTML = '<i class="bi bi-grip-vertical"></i>';
        header.appendChild(handle);

        var summary = document.createElement('div');
        summary.className = 'menu-item__summary';
        var labelEl = document.createElement('strong');
        labelEl.setAttribute('data-menu-label', '');
        summary.appendChild(labelEl);
        var typeEl = document.createElement('span');
        typeEl.className = 'badge bg-light text-dark menu-item__type';
        typeEl.setAttribute('data-menu-type', '');
        summary.appendChild(typeEl);
        header.appendChild(summary);

        var actions = document.createElement('div');
        actions.className = 'menu-item__actions';
        var visibleWrap = document.createElement('div');
        visibleWrap.className = 'form-check form-switch menu-item__visibility';
        var visibleInput = document.createElement('input');
        visibleInput.type = 'checkbox';
        visibleInput.className = 'form-check-input';
        visibleInput.setAttribute('data-menu-visible', '');
        visibleInput.checked = itemData.is_visible !== false;
        visibleWrap.appendChild(visibleInput);
        var visibleLabel = document.createElement('label');
        visibleLabel.className = 'form-check-label';
        visibleLabel.textContent = 'Aktif';
        visibleWrap.appendChild(visibleLabel);
        actions.appendChild(visibleWrap);

        var toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.className = 'btn btn-sm btn-outline-secondary';
        toggleBtn.setAttribute('data-menu-toggle', '');
        toggleBtn.textContent = 'Detay';
        actions.appendChild(toggleBtn);

        if (itemData.type !== 'category') {
            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-sm btn-outline-danger';
            removeBtn.setAttribute('data-menu-remove', '');
            removeBtn.textContent = 'Sil';
            actions.appendChild(removeBtn);
        }

        header.appendChild(actions);
        li.appendChild(header);

        var details = document.createElement('div');
        details.className = 'menu-item__details';

        var titleInput = createTextInput('title', itemData.title);
        details.appendChild(createFieldGroup('Bağlantı Başlığı', titleInput));

        if (itemData.type === 'category' && data && data.path) {
            var pathNote = document.createElement('p');
            pathNote.className = 'menu-item__meta text-muted';
            pathNote.textContent = 'Kategori yolu: ' + data.path;
            details.appendChild(pathNote);
        }

        var urlInput = createTextInput('url', itemData.url || '');
        if (itemData.type === 'category') {
            urlInput.readOnly = true;
            urlInput.classList.add('is-readonly');
        }
        details.appendChild(createFieldGroup('Bağlantı Adresi', urlInput));

        var targetSelect = document.createElement('select');
        targetSelect.className = 'form-select';
        targetSelect.setAttribute('data-menu-field', 'target');
        [{ value: '_self', label: 'Aynı sekmede aç' }, { value: '_blank', label: 'Yeni sekmede aç' }].forEach(function (option) {
            var opt = document.createElement('option');
            opt.value = option.value;
            opt.textContent = option.label;
            if (itemData.target === option.value) {
                opt.selected = true;
            }
            targetSelect.appendChild(opt);
        });
        details.appendChild(createFieldGroup('Açılma Biçimi', targetSelect));

        var iconInput = createSettingsInput('icon', itemData.settings.icon || '');
        details.appendChild(createFieldGroup('İkon Sınıfı', iconInput));

        if (location === 'admin') {
            var patternInput = createSettingsInput('pattern', itemData.settings.pattern || '');
            details.appendChild(createFieldGroup('Aktiflik Deseni', patternInput));
            var rolesInput = createSettingsInput('roles', Array.isArray(itemData.settings.roles) ? itemData.settings.roles.join(', ') : '');
            rolesInput.placeholder = 'super_admin, admin, content';
            details.appendChild(createFieldGroup('Roller (virgül ile ayırın)', rolesInput));
        }

        if (itemData.type === 'category') {
            var autoWrap = document.createElement('div');
            autoWrap.className = 'form-check form-switch mb-3';
            var autoInput = document.createElement('input');
            autoInput.type = 'checkbox';
            autoInput.className = 'form-check-input';
            autoInput.setAttribute('data-menu-setting', 'title_locked');
            autoInput.setAttribute('data-menu-setting-invert', '1');
            autoInput.checked = !(itemData.settings && itemData.settings.title_locked === true);
            var autoLabel = document.createElement('label');
            autoLabel.className = 'form-check-label';
            autoLabel.textContent = 'Kategori adını otomatik güncelle';
            autoWrap.appendChild(autoInput);
            autoWrap.appendChild(autoLabel);
            details.appendChild(autoWrap);
        }

        li.appendChild(details);

        var childrenList = document.createElement('ol');
        childrenList.className = 'menu-item__children';
        li.appendChild(childrenList);

        updateSummary(li, itemData);

        visibleInput.addEventListener('change', function () {
            var current = getItemData(li);
            current.is_visible = !!visibleInput.checked;
            setItemData(li, current);
        });

        toggleBtn.addEventListener('click', function () {
            toggleDetails(li);
        });

        if (itemData.type !== 'category') {
            var removeBtnEl = li.querySelector('[data-menu-remove]');
            removeBtnEl.addEventListener('click', function () {
                removeItem(li, location);
            });
        }

        titleInput.addEventListener('input', function () {
            var current = getItemData(li);
            var newTitle = titleInput.value.trim();
            current.title = newTitle;
            if (current.type === 'category') {
                var autoInput = li.querySelector('[data-menu-setting="title_locked"]');
                if (autoInput && autoInput.checked) {
                    autoInput.checked = false;
                    autoInput.dispatchEvent(new Event('change'));
                }
            }
            setItemData(li, current);
        });

        urlInput.addEventListener('input', function () {
            var current = getItemData(li);
            current.url = urlInput.value.trim();
            setItemData(li, current);
        });

        targetSelect.addEventListener('change', function () {
            var current = getItemData(li);
            current.target = targetSelect.value === '_blank' ? '_blank' : '_self';
            setItemData(li, current);
        });

        iconInput.addEventListener('input', function () {
            var current = getItemData(li);
            current.settings = current.settings || {};
            current.settings.icon = iconInput.value.trim();
            setItemData(li, current);
        });

        var settingInputs = details.querySelectorAll('[data-menu-setting]');
        Array.prototype.forEach.call(settingInputs, function (input) {
            if (input === iconInput) {
                return;
            }
            var invert = input.getAttribute('data-menu-setting-invert');
            input.addEventListener('change', function () {
                var current = getItemData(li);
                current.settings = current.settings || {};
                var key = input.getAttribute('data-menu-setting');
                if (input.type === 'checkbox') {
                    var value = !!input.checked;
                    if (invert) {
                        value = !value;
                    }
                    current.settings[key] = value;
                } else {
                    var listKeys = key === 'roles';
                    var valueText = input.value.trim();
                    if (listKeys) {
                        var roles = [];
                        valueText.split(',').forEach(function (part) {
                            var trimmed = part.trim();
                            if (trimmed) {
                                roles.push(trimmed);
                            }
                        });
                        current.settings[key] = roles;
                    } else {
                        current.settings[key] = valueText;
                    }
                }
                setItemData(li, current);
            });
        });

        return li;
    };

    var renderTree = function (location, items, target) {
        var list = target || findTree(location);
        if (!list) {
            return;
        }
        list.innerHTML = '';
        (items || []).forEach(function (item) {
            var li = createItemElement(location, item);
            list.appendChild(li);
            var childList = li.querySelector('ol');
            if (item.children && item.children.length) {
                renderTree(location, item.children, childList);
            }
            initSortable(childList, location);
        });
        initSortable(list, location);
        syncEmptyState(location);
    };

    var eachItem = function (location, callback) {
        var tree = findTree(location);
        if (!tree) {
            return;
        }
        Array.prototype.forEach.call(tree.querySelectorAll('li.menu-item'), function (li) {
            callback(li, getItemData(li));
        });
    };

    var hasCategory = function (location, reference) {
        var found = false;
        eachItem(location, function (_, data) {
            if (data.type === 'category' && data.reference_key === reference) {
                found = true;
            }
        });
        return found;
    };

    var buildCategoryUrl = function (path) {
        if (!path) {
            return '#';
        }
        var parts = path.split('/').map(function (segment) {
            return encodeURIComponent(segment.trim());
        });
        return '/kategori/' + parts.join('/');
    };

    var addItemToTree = function (location, item) {
        var tree = findTree(location);
        if (!tree) {
            return;
        }
        var li = createItemElement(location, item);
        tree.appendChild(li);
        initSortable(li.querySelector('ol'), location);
        initSortable(tree, location);
        syncEmptyState(location);
        toggleDetails(li, true);
    };

    var renderSources = function (location) {
        var wrapper = document.querySelector('[data-menu-sources="' + location + '"] [data-menu-source-list]');
        if (!wrapper) {
            return;
        }
        wrapper.innerHTML = '';
        var sourceSet = sources[location] || {};
        var createButton = function (label, handler) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-outline-secondary w-100 mb-2';
            button.textContent = label;
            button.addEventListener('click', handler);
            return button;
        };
        if (sourceSet.categories && sourceSet.categories.length) {
            var catHeader = document.createElement('h4');
            catHeader.textContent = 'Kategoriler';
            wrapper.appendChild(catHeader);
            sourceSet.categories.forEach(function (cat) {
                var button = createButton(cat.title, function () {
                    if (hasCategory(location, cat.reference)) {
                        return;
                    }
                    addItemToTree(location, {
                        type: 'category',
                        reference_key: cat.reference,
                        title: cat.title,
                        url: buildCategoryUrl(cat.path),
                        target: '_self',
                        is_visible: true,
                        settings: { title_locked: false },
                        children: []
                    });
                });
                wrapper.appendChild(button);
            });
        }
        if (sourceSet.pages && sourceSet.pages.length) {
            var pageHeader = document.createElement('h4');
            pageHeader.textContent = 'Sayfalar';
            wrapper.appendChild(pageHeader);
            sourceSet.pages.forEach(function (page) {
                var button = createButton(page.title, function () {
                    addItemToTree(location, {
                        type: 'page',
                        reference_key: page.reference,
                        title: page.title,
                        url: '/page/' + encodeURIComponent(page.reference || ''),
                        target: '_self',
                        is_visible: true,
                        settings: {},
                        children: []
                    });
                });
                wrapper.appendChild(button);
            });
        }
        if (sourceSet.blogs && sourceSet.blogs.length) {
            var blogHeader = document.createElement('h4');
            blogHeader.textContent = 'Blog Yazıları';
            wrapper.appendChild(blogHeader);
            sourceSet.blogs.forEach(function (post) {
                var button = createButton(post.title, function () {
                    addItemToTree(location, {
                        type: 'blog',
                        reference_key: post.reference,
                        title: post.title,
                        url: '/blog/' + encodeURIComponent(post.reference || ''),
                        target: '_self',
                        is_visible: true,
                        settings: {},
                        children: []
                    });
                });
                wrapper.appendChild(button);
            });
        }
        if (sourceSet.routes && sourceSet.routes.length) {
            var routeHeader = document.createElement('h4');
            routeHeader.textContent = 'Yönetim Bağlantıları';
            wrapper.appendChild(routeHeader);
            sourceSet.routes.forEach(function (route) {
                var button = createButton(route.title, function () {
                    addItemToTree(location, {
                        type: 'route',
                        reference_key: route.url,
                        title: route.title,
                        url: route.url,
                        target: '_self',
                        is_visible: true,
                        settings: { pattern: route.pattern || '' },
                        children: []
                    });
                });
                wrapper.appendChild(button);
            });
        }
    };

    Object.keys(structures).forEach(function (location) {
        renderTree(location, structures[location] || []);
        renderSources(location);
        syncEmptyState(location);
    });

    var forms = document.querySelectorAll('[data-menu-form]');
    Array.prototype.forEach.call(forms, function (form) {
        var location = form.getAttribute('data-menu-form');
        var tree = findTree(location);
        var structureInput = form.querySelector('[data-menu-structure-input]');
        form.addEventListener('submit', function () {
            var structure = buildStructure(tree);
            structureInput.value = JSON.stringify(structure);
        });
        var customBtn = form.querySelector('[data-menu-add-custom]');
        if (customBtn) {
            customBtn.addEventListener('click', function () {
                addItemToTree(location, {
                    type: 'custom',
                    title: 'Yeni Öğe',
                    url: '#',
                    target: '_self',
                    is_visible: true,
                    settings: {},
                    children: []
                });
            });
        }
    });

    var tabs = document.querySelectorAll('[data-menu-tab]');
    Array.prototype.forEach.call(tabs, function (tab) {
        tab.addEventListener('click', function () {
            var target = tab.getAttribute('data-menu-tab');
            Array.prototype.forEach.call(tabs, function (btn) {
                btn.classList.toggle('active', btn === tab);
            });
            var sections = document.querySelectorAll('[data-menu-section]');
            Array.prototype.forEach.call(sections, function (section) {
                section.classList.toggle('is-active', section.getAttribute('data-menu-section') === target);
            });
        });
    });

    var expandButtons = document.querySelectorAll('[data-menu-expand]');
    Array.prototype.forEach.call(expandButtons, function (button) {
        button.addEventListener('click', function () {
            var location = button.getAttribute('data-menu-expand');
            var items = document.querySelectorAll('[data-menu-section="' + location + '"] li.menu-item');
            Array.prototype.forEach.call(items, function (item) {
                item.classList.add('is-open');
            });
        });
    });

    var collapseButtons = document.querySelectorAll('[data-menu-collapse]');
    Array.prototype.forEach.call(collapseButtons, function (button) {
        button.addEventListener('click', function () {
            var location = button.getAttribute('data-menu-collapse');
            var items = document.querySelectorAll('[data-menu-section="' + location + '"] li.menu-item');
            Array.prototype.forEach.call(items, function (item) {
                item.classList.remove('is-open');
            });
        });
    });
})();
