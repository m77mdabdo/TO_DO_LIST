(() => {
    'use strict';

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
    const csrf = $('meta[name="csrf-token"]')?.content;

    /* ---------- theme ---------- */

    $$('.js-theme').forEach((btn) => btn.addEventListener('click', () => {
        const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = next;
        try { localStorage.setItem('theme', next); } catch (e) { /* private mode */ }
    }));

    /* ---------- toasts ---------- */

    const hideToast = (toast) => {
        toast.classList.add('is-leaving');
        toast.addEventListener('animationend', () => toast.remove(), { once: true });
    };

    const toast = (message, type = 'success') => {
        let box = $('.toasts');
        const el = document.createElement('div');
        el.className = `toast toast-${type}`;
        el.textContent = message;
        box.appendChild(el);
        setTimeout(() => hideToast(el), 3500);
    };

    $$('.toast').forEach((el) => setTimeout(() => hideToast(el), 4000));

    /* ---------- confirm before deleting ---------- */

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (event.defaultPrevented) return; // already handled by the board script
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });

    /* Everything below only matters on the board page */
    const board = $('.board');
    if (!board) return;

    /* ---------- keyboard shortcuts ---------- */

    const quickTitle = $('#quick-title');
    const search = $('#search');

    document.addEventListener('keydown', (event) => {
        const typing = event.target.matches('input, textarea, select');

        if (event.key === 'Escape') {
            if (search.value) {
                search.value = '';
                applyFilters();
            }
            event.target.blur?.();
            return;
        }
        if (typing || event.metaKey || event.ctrlKey || event.altKey) return;

        if (event.key === 'n' || event.key === 'N') {
            event.preventDefault();
            quickTitle.focus();
        } else if (event.key === '/') {
            event.preventDefault();
            search.focus();
        }
    });

    /* ---------- search + filter chips ---------- */

    let activeFilter = 'all';

    const matchesFilter = (card) => {
        const { priority, due, category } = card.dataset;
        switch (activeFilter) {
            case 'today': return due === 'today';
            case 'overdue': return due === 'overdue';
            case 'high': return priority === 'high';
            case 'all': return true;
            default: return activeFilter.startsWith('cat:') && category === activeFilter.slice(4);
        }
    };

    function refreshColumns() {
        $$('.column').forEach((column) => {
            const body = $('.column-body', column);
            const all = $$('.task-card', body);
            const visible = all.filter((card) => !card.classList.contains('is-hidden'));
            $('.js-count', column).textContent = visible.length;
            body.classList.toggle('is-empty', visible.length === 0);
        });
    }

    function applyFilters() {
        const term = search.value.trim().toLowerCase();
        $$('.task-card').forEach((card) => {
            const show = matchesFilter(card) && (!term || card.dataset.search.includes(term));
            card.classList.toggle('is-hidden', !show);
        });
        refreshColumns();
    }

    search.addEventListener('input', applyFilters);

    $$('.chip').forEach((chip) => chip.addEventListener('click', () => {
        $$('.chip').forEach((c) => c.classList.remove('is-active'));
        chip.classList.add('is-active');
        activeFilter = chip.dataset.filter;
        applyFilters();
    }));

    /* ---------- stats ---------- */

    function updateStats(stats) {
        if (!stats) return;
        $('.js-progress').value = stats.percent;
        $('.js-percent').textContent = `${stats.percent}%`;
        $('.js-done-count').textContent = stats.done;
        $('.js-total-count').textContent = stats.total;
        $('.js-stat-open').textContent = stats.open;
        $('.js-stat-done-today').textContent = stats.done_today;
    }

    /* ---------- little celebration when something gets done ---------- */

    function celebrate(card) {
        card.classList.add('just-done');
        const rect = card.getBoundingClientRect();
        const colors = ['#7c5cff', '#ff6fb5', '#ffb020', '#12c29b', '#2f9bff'];

        for (let i = 0; i < 18; i++) {
            const bit = document.createElement('span');
            bit.className = 'confetti';
            bit.style.left = `${rect.left + rect.width / 2}px`;
            bit.style.top = `${rect.top + 20}px`;
            bit.style.background = colors[i % colors.length];
            bit.style.setProperty('--x', `${(Math.random() - 0.5) * 260}px`);
            bit.style.setProperty('--y', `${-60 - Math.random() * 120}px`);
            bit.style.setProperty('--r', `${Math.random() * 540}deg`);
            document.body.appendChild(bit);
            bit.addEventListener('animationend', () => bit.remove());
        }
    }

    /* ---------- moving cards (buttons + drag & drop) ---------- */

    async function post(url, data) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-Token': csrf },
            body: data,
            credentials: 'same-origin',
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok || !json.ok) throw new Error(json.message || 'Something went wrong, try again.');
        return json;
    }

    async function moveCard(card, status, url) {
        const data = new FormData();
        data.append('_token', csrf);
        data.append('status', status);

        const json = await post(url, data);
        const holder = document.createElement('div');
        holder.innerHTML = json.html.trim();
        const fresh = holder.firstElementChild;

        $(`.column[data-status="${status}"] .column-body`).prepend(fresh);
        card.remove();
        bindCard(fresh);
        updateStats(json.stats);
        applyFilters();

        if (status === 'done') celebrate(fresh);
    }

    function bindCard(card) {
        $$('form.js-move', card).forEach((form) => form.addEventListener('submit', async (event) => {
            event.preventDefault();
            try {
                await moveCard(card, form.elements.status.value, form.action);
            } catch (error) {
                toast(error.message, 'error');
            }
        }));

        $$('form.js-delete', card).forEach((form) => form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!window.confirm(form.dataset.confirm)) return;
            try {
                const json = await post(form.action, new FormData(form));
                card.style.transition = 'opacity .2s, transform .2s';
                card.style.opacity = '0';
                card.style.transform = 'scale(.95)';
                setTimeout(() => { card.remove(); applyFilters(); }, 200);
                updateStats(json.stats);
                toast('Task deleted.');
            } catch (error) {
                toast(error.message, 'error');
            }
        }));

        card.addEventListener('dragstart', (event) => {
            card.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', card.dataset.id);
        });
        card.addEventListener('dragend', () => card.classList.remove('is-dragging'));
    }

    $$('.task-card').forEach(bindCard);

    $$('.js-dropzone').forEach((zone) => {
        const status = zone.closest('.column').dataset.status;

        zone.addEventListener('dragover', (event) => {
            event.preventDefault();
            zone.classList.add('is-over');
        });
        zone.addEventListener('dragleave', (event) => {
            if (!zone.contains(event.relatedTarget)) zone.classList.remove('is-over');
        });
        zone.addEventListener('drop', async (event) => {
            event.preventDefault();
            zone.classList.remove('is-over');
            const card = $(`.task-card[data-id="${event.dataTransfer.getData('text/plain')}"]`);
            if (!card || card.dataset.status === status) return;

            try {
                await moveCard(card, status, card.dataset.moveUrl);
            } catch (error) {
                toast(error.message, 'error');
            }
        });
    });

    applyFilters();
})();
