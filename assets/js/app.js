(() => {
    'use strict';

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
    const csrf = $('meta[name="csrf-token"]')?.content;
    const rtl = document.documentElement.dir === 'rtl';

    /* ---------- theme ---------- */

    $$('.js-theme').forEach((btn) => btn.addEventListener('click', () => {
        const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = next;
        try { localStorage.setItem('theme', next); } catch (e) { /* private mode */ }
    }));

    /* ---------- toasts ---------- */

    const hideToast = (el) => {
        el.classList.add('is-leaving');
        el.addEventListener('animationend', () => el.remove(), { once: true });
    };

    const toast = (message, type = 'error') => {
        const el = document.createElement('div');
        el.className = `toast toast-${type}`;
        el.textContent = message;
        $('.toasts')?.appendChild(el);
        setTimeout(() => hideToast(el), 3500);
    };

    $$('.toast').forEach((el) => setTimeout(() => hideToast(el), 4000));

    /* ---------- confirm before destructive actions ---------- */

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });

    /* ---------- close open menus on outside click / Escape ---------- */

    const closeMenus = (except) => $$('details.menu[open]').forEach((menu) => {
        if (menu !== except) menu.removeAttribute('open');
    });
    document.addEventListener('click', (event) => closeMenus(event.target.closest('details.menu')));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeMenus(null);
    });

    /* ---------- keyboard shortcuts ----------
     * Each one is a faster way to something that has a visible control too.
     * Ignored while typing, and when a modifier key is held.
     */

    const shortcuts = $('#shortcuts');
    $$('.js-shortcuts').forEach((btn) => btn.addEventListener('click', () => shortcuts?.showModal()));

    const focusField = (field) => {
        if (!field) return false;
        field.closest('details')?.setAttribute('open', '');
        field.focus();
        if (field.select) field.select();
        return true;
    };

    document.addEventListener('keydown', (event) => {
        const target = event.target;
        const typing = target.closest && target.closest('input, textarea, select, [contenteditable="true"]');

        if (event.key === 'Escape' && typing) {
            target.blur();
            return;
        }
        if (typing || event.ctrlKey || event.metaKey || event.altKey || (shortcuts && shortcuts.open)) return;

        const actions = {
            '/': () => focusField($('.js-filter-search')) || focusField($('.js-global-search')),
            f: () => {
                const filters = $('details.filters');
                if (!filters) return false;
                filters.open = !filters.open;
                if (filters.open) $('.js-filter-search')?.focus();
                return true;
            },
            n: () => focusField($('.js-add-card')),
            e: () => focusField($('.js-edit-card input[name="title"]')),
            c: () => focusField($('.js-comment')),
            '?': () => {
                if (!shortcuts) return false;
                shortcuts.showModal();
                return true;
            },
        };

        const action = actions[event.key.length === 1 ? event.key.toLowerCase() : event.key];
        if (action && action()) event.preventDefault();
    });

    /* ---------- fetch helper ---------- */

    async function post(url, fields) {
        const body = new FormData();
        Object.entries(fields).forEach(([key, value]) => body.append(key, value));
        body.append('_token', csrf);

        const response = await fetch(url, {
            method: 'POST',
            body,
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok || !json.ok) throw new Error(json.message || 'Something went wrong.');
        return json;
    }

    /** If a save fails, reload to show the true order rather than a guess. */
    const failed = (error) => {
        toast(error.message);
        setTimeout(() => window.location.reload(), 900);
    };

    /** The element the dragged item should go before, from the pointer position. */
    function insertBefore(container, selector, pointer, axis) {
        return $$(selector, container).filter((el) => !el.classList.contains('is-dragging')).find((el) => {
            const box = el.getBoundingClientRect();
            if (axis === 'y') return pointer < box.top + box.height / 2;
            const middle = box.left + box.width / 2;
            return rtl ? pointer > middle : pointer < middle;
        }) || null;
    }

    /* ---------- drag and drop ----------
     * The server only needs the item's new neighbour: "put it after X".
     * It computes one ordering key and writes one row, so a drop is cheap
     * however long the list is.
     */

    /**
     * Vertical sorting of `item` elements across `zone` containers inside root.
     * onDrop(element, zone, previousItem) saves the new place and returns a promise.
     */
    function sortable(root, { item, zone, onDrop }) {
        let dragged = null;

        root.addEventListener('dragstart', (event) => {
            const el = event.target.closest(item + '[draggable="true"]');
            if (!el || !root.contains(el)) return;
            dragged = el;
            el.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', '');
        });

        root.addEventListener('dragend', () => {
            if (dragged) dragged.classList.remove('is-dragging');
            $$(zone + '.is-over', root).forEach((el) => el.classList.remove('is-over'));
            dragged = null;
        });

        root.addEventListener('dragover', (event) => {
            if (!dragged) return;
            const target = event.target.closest(zone);
            if (!target || !root.contains(target)) return;
            event.preventDefault();
            $$(zone + '.is-over', root).forEach((el) => { if (el !== target) el.classList.remove('is-over'); });
            target.classList.add('is-over');
            target.insertBefore(dragged, insertBefore(target, item, event.clientY, 'y'));
        });

        root.addEventListener('drop', (event) => {
            if (!dragged) return;
            event.preventDefault();
            const el = dragged;
            const before = el.previousElementSibling;
            onDrop(el, el.closest(zone), before && before.matches(item) ? before : null).catch(failed);
        });
    }

    /* Checklist items, within and between the checklists of a card. */
    const checklists = $('#checklists');
    if (checklists) {
        sortable(checklists, {
            item: '.checklist-item',
            zone: '.js-items',
            onDrop: (el, zone, previous) => post(el.dataset.moveUrl, {
                checklist_id: zone.dataset.checklistId,
                after_id: previous ? previous.dataset.itemId : '',
            }),
        });
    }

    /* Board: cards between lists, and the lists themselves. */
    const lists = $('.js-lists');
    if (!lists) return;

    const refreshCounts = () => $$('.list[data-list-id]', lists).forEach((list) => {
        const count = $('.count', list);
        if (count) count.textContent = $$('.card-tile', list).length;
    });

    sortable(lists, {
        item: '.card-tile',
        zone: '.js-cards',
        onDrop: async (el, zone, previous) => {
            await post(el.dataset.moveUrl, {
                list_id: zone.dataset.listId,
                after_id: previous ? previous.dataset.cardId : '',
            });
            refreshCounts();
        },
    });

    let draggedList = null;

    lists.addEventListener('dragstart', (event) => {
        if (event.target.closest('.card-tile')) return;
        draggedList = event.target.closest('.list[draggable="true"]');
        if (!draggedList) return;
        draggedList.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', '');
    });

    lists.addEventListener('dragend', () => {
        if (draggedList) draggedList.classList.remove('is-dragging');
        draggedList = null;
    });

    lists.addEventListener('dragover', (event) => {
        if (!draggedList) return;
        event.preventDefault();
        const before = insertBefore(lists, '.list[data-list-id]', event.clientX, 'x') || $('.list-new', lists);
        lists.insertBefore(draggedList, before);
    });

    lists.addEventListener('drop', (event) => {
        if (!draggedList) return;
        event.preventDefault();
        const previous = draggedList.previousElementSibling;
        post(draggedList.dataset.moveUrl, {
            after_id: previous && previous.dataset.listId ? previous.dataset.listId : '',
        }).catch(failed);
    });
})();
