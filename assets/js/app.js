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

    /* ---------- board: drag and drop ----------
     * The server only needs the item's new neighbour: "put it after X".
     * It computes one ordering key and writes one row, so a drop is cheap
     * however long the list is. If the request fails, reload to show the
     * true order rather than a guess.
     */

    const lists = $('.js-lists');
    if (!lists) return;

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

    const refreshCounts = () => $$('.list[data-list-id]', lists).forEach((list) => {
        const count = $('.count', list);
        if (count) count.textContent = $$('.card-tile', list).length;
    });

    /** The element the dragged item should go before, from the pointer position. */
    function insertBefore(container, selector, pointer, axis) {
        return $$(selector, container).filter((el) => !el.classList.contains('is-dragging')).find((el) => {
            const box = el.getBoundingClientRect();
            if (axis === 'y') return pointer < box.top + box.height / 2;
            const middle = box.left + box.width / 2;
            return rtl ? pointer > middle : pointer < middle;
        }) || null;
    }

    let dragged = null;

    lists.addEventListener('dragstart', (event) => {
        const card = event.target.closest('.card-tile[draggable="true"]');
        const list = card ? null : event.target.closest('.list[draggable="true"]');
        dragged = card || list;
        if (!dragged) return;

        dragged.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', '');
    });

    lists.addEventListener('dragend', () => {
        if (dragged) dragged.classList.remove('is-dragging');
        $$('.is-over', lists).forEach((el) => el.classList.remove('is-over'));
        dragged = null;
    });

    lists.addEventListener('dragover', (event) => {
        if (!dragged) return;

        if (dragged.matches('.card-tile')) {
            const zone = event.target.closest('.js-cards');
            if (!zone) return;
            event.preventDefault();
            $$('.js-cards.is-over', lists).forEach((el) => {
                if (el !== zone) el.classList.remove('is-over');
            });
            zone.classList.add('is-over');
            zone.insertBefore(dragged, insertBefore(zone, '.card-tile', event.clientY, 'y'));
        } else {
            event.preventDefault();
            const before = insertBefore(lists, '.list[data-list-id]', event.clientX, 'x') || $('.list-new', lists);
            lists.insertBefore(dragged, before);
        }
    });

    lists.addEventListener('drop', async (event) => {
        if (!dragged) return;
        event.preventDefault();
        const item = dragged;
        const previous = item.previousElementSibling;

        try {
            if (item.matches('.card-tile')) {
                await post(item.dataset.moveUrl, {
                    list_id: item.closest('.js-cards').dataset.listId,
                    after_id: previous ? previous.dataset.cardId : '',
                });
                refreshCounts();
            } else {
                await post(item.dataset.moveUrl, {
                    after_id: previous && previous.dataset.listId ? previous.dataset.listId : '',
                });
            }
        } catch (error) {
            toast(error.message);
            setTimeout(() => window.location.reload(), 900);
        }
    });
})();
