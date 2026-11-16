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

    /* ---------- copy a link (invitations) ---------- */

    /* data-copy="<link>" copies that link (made absolute); otherwise the
     * .js-copy-source field next to the button. */
    $$('.js-copy').forEach((btn) => btn.addEventListener('click', async () => {
        const source = btn.dataset.copy ? null : $('.js-copy-source', btn.closest('.copy-row') || document);
        const text = btn.dataset.copy ? new URL(btn.dataset.copy, window.location.href).href : (source && source.value);
        if (!text) return;
        try {
            await navigator.clipboard.writeText(text);
        } catch (e) {
            if (!source) { window.prompt('', text); return; }
            source.select();
            document.execCommand('copy');
        }
        toast(btn.dataset.copied || 'Copied', 'success');
    }));

    /* ---------- bulk actions on a board ----------
     * Shows only the field the chosen action needs, keeps the count of
     * chosen cards, and asks again before deleting.
     */

    $$('.js-bulk').forEach((form) => {
        const action = $('.js-bulk-action', form);
        const boxes = () => $$('.js-bulk-card');
        const count = $('.js-bulk-count', form);
        const all = $('.js-bulk-all', form);
        const refresh = () => {
            $$('[data-bulk-for]', form).forEach((field) => {
                field.hidden = field.dataset.bulkFor !== action.value;
            });
            count.textContent = String(boxes().filter((box) => box.checked).length);
        };
        action.addEventListener('change', refresh);
        document.addEventListener('change', (event) => {
            if (event.target.classList && event.target.classList.contains('js-bulk-card')) refresh();
        });
        if (all) {
            all.hidden = false;
            all.addEventListener('click', () => {
                const on = boxes().some((box) => !box.checked);
                boxes().forEach((box) => { box.checked = on; });
                refresh();
            });
        }
        form.addEventListener('submit', (event) => {
            if (action.value === 'delete' && !window.confirm(form.dataset.confirmDelete)) event.preventDefault();
        });
        refresh();
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

    /* ---------- polling ----------
     * One request at a time, never while the tab is hidden, slower after
     * errors (doubling up to 2 minutes), and back to normal once it works.
     */

    function poll(task, every) {
        let delay = every;
        let timer = null;
        let running = false;
        const schedule = () => {
            clearTimeout(timer);
            timer = setTimeout(tick, delay);
        };
        async function tick() {
            if (running || document.hidden) return schedule();
            running = true;
            try {
                await task();
                delay = every;
            } catch (e) {
                delay = Math.min(delay * 2, 120000);
            } finally {
                running = false;
                schedule();
            }
        }
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) { delay = every; clearTimeout(timer); tick(); }
        });
        schedule();
        return { now: tick };
    }

    async function getJson(url) {
        const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
    }

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

    /* ---------- comments: reactions, file name, "is writing" ----------
     * Reactions are plain forms; with JavaScript they are sent in the
     * background and only that comment's reactions are redrawn.
     */

    document.addEventListener('submit', async (event) => {
        const form = event.target.closest && event.target.closest('.js-react');
        if (!form || event.defaultPrevented) return;
        event.preventDefault();
        const comment = form.closest('.comment');
        try {
            await post(form.action, { emoji: form.elements.emoji.value });
            const response = await fetch(window.location.pathname + window.location.search, { credentials: 'same-origin', cache: 'no-store' });
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const fresh = comment && $('#' + comment.id + ' .reactions', page);
            const mine = comment && $('.reactions', comment);
            if (fresh && mine) {
                mine.replaceWith(document.importNode(fresh, true));
                const add = $('.reaction-add', comment);
                if (add) add.focus();
            }
        } catch (error) {
            toast(error.message);
        }
    });

    $$('.js-comment-file').forEach((input) => input.addEventListener('change', () => {
        const label = $('.js-comment-file-name', input.closest('form'));
        if (label) label.textContent = input.files[0] ? input.files[0].name : '';
    }));

    const store = {
        get: (key) => { try { return localStorage.getItem(key); } catch (e) { return null; } },
        set: (key, value) => { try { localStorage.setItem(key, value); } catch (e) { /* private mode */ } },
    };

    const commentBox = $('.js-comment[data-typing-url]');
    if (commentBox) {
        const share = $('.js-typing-share');
        const shareRow = $('.js-typing-share-row');
        if (share && shareRow) {
            shareRow.hidden = false;
            share.checked = store.get('typing-share') !== 'off';
            share.addEventListener('change', () => store.set('typing-share', share.checked ? 'on' : 'off'));
        }
        let lastSent = 0;
        commentBox.addEventListener('input', () => {
            if ((share && !share.checked) || Date.now() - lastSent < 4000 || commentBox.value.trim() === '') return;
            lastSent = Date.now();
            post(commentBox.dataset.typingUrl, {}).catch(() => { /* only a hint; never in the way */ });
        });
    }

    const typing = $('.js-typing');
    if (typing) {
        poll(async () => {
            const { names = [] } = await getJson(typing.dataset.url);
            typing.hidden = names.length === 0;
            typing.textContent = names.length === 1
                ? typing.dataset.one.replace(':name', names[0])
                : typing.dataset.many.replace(':names', names.join('، '));
        }, 5000);
    }

    /* ---------- browser notifications (optional, per browser) ----------
     * Off until the person turns them on and the browser allows them. The
     * last shown id is shared by the tabs, so each notification shows once.
     */

    const bell = $('.js-bell');
    const pushSupported = 'Notification' in window;
    const pushOn = () => pushSupported && Notification.permission === 'granted' && store.get('push') === 'on';
    let pushStarted = false;

    function startPush() {
        if (pushStarted || !bell || !pushOn()) return;
        pushStarted = true;
        poll(async () => {
            if (!pushOn()) return;
            const last = Number(store.get('push-last') || 0);
            const data = await getJson(bell.dataset.latestUrl + '?after=' + last);
            if (!last) { store.set('push-last', String(data.latest)); return; }
            data.items.slice().reverse().forEach((item) => {
                if (item.id <= Number(store.get('push-last') || 0)) return;
                const shown = new Notification(item.text, { body: item.body, tag: 'todo-' + item.id });
                shown.onclick = () => { window.focus(); window.location.href = item.url; };
            });
            store.set('push-last', String(Math.max(last, data.latest)));
        }, 45000);
    }
    startPush();

    const pushPanel = $('.js-push');
    if (pushPanel) {
        pushPanel.hidden = false;
        const state = $('.js-push-state', pushPanel);
        const on = $('.js-push-on', pushPanel);
        const off = $('.js-push-off', pushPanel);
        const show = () => {
            if (!pushSupported) {
                state.textContent = pushPanel.dataset.unsupported;
            } else if (Notification.permission === 'denied') {
                state.textContent = pushPanel.dataset.blocked;
            } else {
                state.textContent = pushOn() ? pushPanel.dataset.on : pushPanel.dataset.off;
            }
            on.hidden = !pushSupported || Notification.permission === 'denied' || pushOn();
            off.hidden = !pushOn();
        };
        on.addEventListener('click', async () => {
            const permission = await Notification.requestPermission();
            if (permission === 'granted') {
                store.set('push', 'on');
                store.set('push-last', '');
                startPush();
            }
            show();
            (pushOn() ? off : on).focus();
        });
        off.addEventListener('click', () => {
            store.set('push', 'off');
            show();
            on.focus();
        });
        show();
    }

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
    /* ---------- live board ----------
     * Asks the server for the board's change counter; when it moved, fetches
     * the board page again and swaps only the lists that differ. Never while
     * the person is dragging, typing or has a menu open: then a banner offers
     * to show the changes instead.
     */

    const stale = $('.js-board-stale');
    let dragging = false;
    lists.addEventListener('dragstart', () => { dragging = true; });
    lists.addEventListener('dragend', () => { setTimeout(() => { dragging = false; }, 300); });

    const busy = () => dragging
        || $$('details[open]', lists).length > 0
        || (document.activeElement && lists.contains(document.activeElement) && /INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName));

    async function applyBoard() {
        const response = await fetch(location.href, { credentials: 'same-origin', cache: 'no-store' });
        if (!response.ok) throw new Error('HTTP ' + response.status);
        const page = new DOMParser().parseFromString(await response.text(), 'text/html');
        const fresh = $('.js-lists', page);
        if (!fresh) return;
        const old = new Map($$('.list[data-list-id]', lists).map((el) => [el.dataset.listId, el]));
        const keep = new Set();
        $$('.list[data-list-id]', fresh).forEach((el) => {
            const id = el.dataset.listId;
            keep.add(id);
            const current = old.get(id);
            const copy = document.importNode(el, true);
            if (!current) {
                lists.insertBefore(copy, $('.list-new', lists));
            } else if (current.outerHTML !== el.outerHTML) {
                current.replaceWith(copy);
            }
        });
        old.forEach((el, id) => { if (!keep.has(id)) el.remove(); });
        // Keep the lists in the server's order.
        $$('.list[data-list-id]', fresh).forEach((el) => {
            const mine = $(`.list[data-list-id="${el.dataset.listId}"]`, lists);
            if (mine) lists.insertBefore(mine, $('.list-new', lists));
        });
        const activity = $('.js-board-activity');
        const freshActivity = $('.js-board-activity', page);
        if (activity && freshActivity) activity.replaceWith(document.importNode(freshActivity, true));
        lists.dataset.version = fresh.dataset.version;
        if (stale) stale.hidden = true;
    }

    if (lists.dataset.changesUrl) {
        const checker = poll(async () => {
            const url = lists.dataset.changesUrl + '?since=' + encodeURIComponent(lists.dataset.version || '0');
            const state = await getJson(url);
            if (!state.changed) return;
            if (busy()) {
                if (stale) {
                    $('.js-board-stale-text', stale).textContent = state.by ? lists.dataset.updatedBy.replace(':name', state.by) : lists.dataset.updated;
                    stale.hidden = false;
                }
                return;
            }
            await applyBoard();
            if (state.by) toast(lists.dataset.updatedBy.replace(':name', state.by), 'success');
        }, 8000);
        $('.js-board-refresh')?.addEventListener('click', () => applyBoard().catch(() => window.location.reload()));
        window.addEventListener('focus', () => checker.now());
    }
})();

/* ---------- batch 4: show only the inputs that apply ---------- */
(() => {
    'use strict';
    const pairs = [['.js-field-type', '.js-field-options', (v) => v === 'select'], ['.js-card-kind', '.js-link-board', (v) => v === 'board']];
    pairs.forEach(([selectSel, targetSel, wants]) => {
        document.querySelectorAll(selectSel).forEach((select) => {
            const form = select.form;
            const target = form ? form.querySelector(targetSel) : null;
            if (!target) return;
            const sync = () => { target.hidden = !wants(select.value); };
            select.addEventListener('change', sync);
            sync();
        });
    });
})();

/* ---------- batch 5: checklist paste, repeat options ---------- */
(() => {
    'use strict';
    // Enter adds the item(s); Shift+Enter (or pasting) gives more lines, one item each.
    document.querySelectorAll('textarea.js-add-item').forEach((area) => {
        area.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
                e.preventDefault();
                if (area.value.trim() !== '') area.form.requestSubmit();
            }
        });
        const grow = () => { area.rows = Math.min(8, Math.max(1, area.value.split('\n').length)); };
        area.addEventListener('input', grow);
    });
    document.querySelectorAll('.js-repeat-frequency').forEach((select) => {
        const form = select.form;
        const weekly = form.querySelector('.js-repeat-weekly');
        const monthly = form.querySelector('.js-repeat-monthly');
        const sync = () => {
            if (weekly) weekly.hidden = select.value !== 'weekly';
            if (monthly) monthly.hidden = select.value !== 'monthly';
        };
        select.addEventListener('change', sync);
        sync();
    });
})();
