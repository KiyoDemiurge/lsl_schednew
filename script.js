/* =========================================================
   LSL FACULTY WORKLOAD & ACADEMIC SCHEDULING SYSTEM
   COMPLETE UPDATED script.js
   ========================================================= */

/* SETTINGS */
const LSL_TRANSITION_DURATION = 220;
const LSL_MODAL_OPEN_DURATION = 220;
const LSL_MODAL_CLOSE_DURATION = 160;

function prefersReducedMotion() {
    return window.matchMedia(
        '(prefers-reduced-motion: reduce)'
    ).matches;
}

/* =========================================================
   SIDEBAR
   ========================================================= */

function toggleSidebar() {
    document.getElementById('sidebar')?.classList.toggle('open');
}

/* =========================================================
   PASSWORD VISIBILITY
   ========================================================= */

function togglePassword() {
    const input = document.getElementById('password');
    const button = document.querySelector('.show-password');

    if (!input || !button) return;

    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    button.textContent = show ? 'Hide' : 'Show';
}

/* =========================================================
   MODALS
   ========================================================= */

function getModalDialog(modal) {
    if (!modal) return null;

    return modal.querySelector(
        '.modal-content, .modal-dialog, .modal'
    );
}

function getModalFocusableElement(modal) {
    if (!modal) return null;

    return modal.querySelector(
        'input:not([type="hidden"]):not([disabled]), ' +
        'select:not([disabled]), textarea:not([disabled]), ' +
        'button:not([disabled]), a[href], ' +
        '[tabindex]:not([tabindex="-1"])'
    );
}

function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;

    const dialog = getModalDialog(modal);

    modal.classList.remove('lsl-modal-closing');
    modal.classList.add('open');

    if (dialog && !prefersReducedMotion()) {
        dialog.animate(
            [
                {
                    opacity: 0,
                    transform: 'translateY(10px) scale(0.98)'
                },
                {
                    opacity: 1,
                    transform: 'translateY(0) scale(1)'
                }
            ],
            {
                duration: LSL_MODAL_OPEN_DURATION,
                easing: 'cubic-bezier(0.22, 1, 0.36, 1)'
            }
        );
    }

    const firstField = getModalFocusableElement(modal);

    if (firstField) {
        requestAnimationFrame(() => {
            if (modal.classList.contains('open')) {
                firstField.focus({ preventScroll: true });
            }
        });
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;

    const dialog = getModalDialog(modal);

    if (!dialog || prefersReducedMotion()) {
        modal.classList.remove('open', 'lsl-modal-closing');
        delete modal.dataset.lslClosing;
        return;
    }

    if (modal.dataset.lslClosing === 'true') return;

    modal.dataset.lslClosing = 'true';

    const animation = dialog.animate(
        [
            {
                opacity: 1,
                transform: 'translateY(0) scale(1)'
            },
            {
                opacity: 0,
                transform: 'translateY(6px) scale(0.99)'
            }
        ],
        {
            duration: LSL_MODAL_CLOSE_DURATION,
            easing: 'ease-in'
        }
    );

    animation.onfinish = () => {
        modal.classList.remove('open', 'lsl-modal-closing');
        delete modal.dataset.lslClosing;
    };

    animation.oncancel = () => {
        delete modal.dataset.lslClosing;
    };
}

/* =========================================================
   EDIT FACULTY
   ========================================================= */

function editFaculty(faculty) {
    if (!faculty || typeof faculty !== 'object') return;

    const fields = [
        'id',
        'faculty_id',
        'full_name',
        'email',
        'department_id',
        'employment_type',
        'specialization',
        'status'
    ];

    fields.forEach(key => {
        const input = document.getElementById('f-' + key);
        if (input) input.value = faculty[key] ?? '';
    });

    const title = document.getElementById('faculty-modal-title');

    if (title) {
        title.textContent = 'Edit Faculty';
    }

    openModal('faculty-modal');
}

/* =========================================================
   FORM CONFIRMATIONS
   ========================================================= */

function confirmAction(form) {
    if (!form) return true;

    const action = form.querySelector('[name="action"]')?.value;

    if (action === 'delete_faculty') {
        return confirm(
            'Delete this faculty record? This may be blocked if it is still assigned to schedules.'
        );
    }

    if (action === 'delete_schedule') {
        return confirm('Delete this class schedule?');
    }

    if (action === 'request_decision') {
        return true;
    }

    return true;
}

/* =========================================================
   PAGE TRANSITION STYLES
   ========================================================= */

(function setupPageTransitionStyles() {
    if (document.getElementById('lsl-page-transition-styles')) {
        return;
    }

    const style = document.createElement('style');
    style.id = 'lsl-page-transition-styles';

    style.textContent = `
        .lsl-page-transitioning {
            pointer-events: none;
        }

        .lsl-page-enter {
            animation: lslPageEnter
                ${LSL_TRANSITION_DURATION}ms
                cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        .lsl-page-exit {
            animation: lslPageExit 140ms ease-in both;
            pointer-events: none;
        }

        @keyframes lslPageEnter {
            from {
                opacity: 0;
                transform: translateY(7px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes lslPageExit {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(-3px);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .lsl-page-enter,
            .lsl-page-exit {
                animation: none !important;
            }
        }
    `;

    document.head.appendChild(style);
})();

function getPageContent() {
    return (
        document.querySelector('.main-area .content') ||
        document.querySelector('main .content') ||
        document.querySelector('.main-area main') ||
        document.querySelector('main') ||
        document.querySelector('.content')
    );
}

/* =========================================================
   PAGE TRANSITIONS
   ========================================================= */

(function setupPageTransitions() {
    const TRANSITION_KEY = 'lslPageTransitionPending';

    function isInternalPageLink(link) {
        if (!link || !link.href) return false;

        if (link.target && link.target !== '_self') return false;

        if (
            link.hasAttribute('download') ||
            link.hasAttribute('data-no-transition')
        ) {
            return false;
        }

        let targetUrl;

        try {
            targetUrl = new URL(link.href, window.location.href);
        } catch {
            return false;
        }

        if (targetUrl.origin !== window.location.origin) return false;
        if (targetUrl.pathname !== window.location.pathname) return false;
        if (!targetUrl.searchParams.has('page')) return false;

        const currentUrl = new URL(window.location.href);

        if (
            targetUrl.searchParams.get('page') ===
            currentUrl.searchParams.get('page')
        ) {
            return false;
        }

        return true;
    }

    function animateCurrentPageIn() {
        let shouldAnimate = false;

        try {
            shouldAnimate =
                sessionStorage.getItem(TRANSITION_KEY) === '1';

            sessionStorage.removeItem(TRANSITION_KEY);
        } catch {
            shouldAnimate = false;
        }

        if (!shouldAnimate || prefersReducedMotion()) return;

        const content = getPageContent();
        if (!content) return;

        content.classList.remove('lsl-page-exit');
        content.classList.add('lsl-page-enter');

        content.addEventListener(
            'animationend',
            () => content.classList.remove('lsl-page-enter'),
            { once: true }
        );
    }

    document.addEventListener('click', event => {
        if (
            event.defaultPrevented ||
            event.button !== 0 ||
            event.ctrlKey ||
            event.metaKey ||
            event.shiftKey ||
            event.altKey
        ) {
            return;
        }

        const link = event.target.closest('a[href]');

        if (!isInternalPageLink(link)) return;
        if (prefersReducedMotion()) return;

        const content = getPageContent();
        if (!content) return;

        event.preventDefault();

        content.classList.remove('lsl-page-enter');
        content.classList.add('lsl-page-exit');
        document.body.classList.add('lsl-page-transitioning');

        try {
            sessionStorage.setItem(TRANSITION_KEY, '1');
        } catch {
            // Navigation continues if storage is unavailable.
        }

        const destination = link.href;

        window.setTimeout(() => {
            window.location.href = destination;
        }, 140);
    });

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            animateCurrentPageIn,
            { once: true }
        );
    } else {
        animateCurrentPageIn();
    }
})();

/* =========================================================
   BUTTON INTERACTION FEEDBACK
   ========================================================= */

(function setupButtonFeedback() {
    document.addEventListener('pointerdown', event => {
        if (prefersReducedMotion()) return;

        const button = event.target.closest(
            'button, .btn, input[type="submit"], input[type="button"]'
        );

        if (!button || button.disabled) return;

        if (
            button.closest('.modal-backdrop') &&
            !button.closest('.modal-content, .modal-dialog, .modal')
        ) {
            return;
        }

        button.animate(
            [
                { transform: 'scale(1)' },
                { transform: 'scale(0.98)' }
            ],
            {
                duration: 80,
                easing: 'ease-out',
                fill: 'forwards'
            }
        );
    });

    document.addEventListener('pointerup', event => {
        const button = event.target.closest(
            'button, .btn, input[type="submit"], input[type="button"]'
        );

        if (!button || prefersReducedMotion()) return;

        button.animate(
            [
                { transform: 'scale(0.98)' },
                { transform: 'scale(1)' }
            ],
            {
                duration: 120,
                easing: 'ease-out'
            }
        );
    });

    document.addEventListener('pointercancel', () => {
        document.querySelectorAll(
            'button, .btn, input[type="submit"], input[type="button"]'
        ).forEach(button => {
            button.getAnimations().forEach(animation => animation.cancel());
        });
    });
})();

/* =========================================================
   MOBILE SIDEBAR AUTO-CLOSE
   ========================================================= */

document.addEventListener('click', event => {
    const link = event.target.closest('#sidebar a[href]');

    if (!link || window.innerWidth > 768) return;

    document.getElementById('sidebar')?.classList.remove('open');
});

/* =========================================================
   MODAL BACKDROP CLICK
   ========================================================= */

document.addEventListener('click', event => {
    const backdrop = event.target.closest('.modal-backdrop');

    if (!backdrop || event.target !== backdrop) return;

    if (!backdrop.id) {
        backdrop.classList.remove('open');
        return;
    }

    closeModal(backdrop.id);
});

/* =========================================================
   ESCAPE KEY
   ========================================================= */

document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;

    document.querySelectorAll('.modal-backdrop.open').forEach(modal => {
        if (modal.id) {
            closeModal(modal.id);
        } else {
            modal.classList.remove('open');
        }
    });

    document.getElementById('sidebar')?.classList.remove('open');
});

/* =========================================================
   LOGIN AND LOGOUT TRANSITIONS
   ========================================================= */

(function setupAuthenticationTransitions() {
    const TRANSITION_DURATION = 220;
    let transitionInProgress = false;

    if (!document.getElementById('lsl-auth-transition-styles')) {
        const style = document.createElement('style');
        style.id = 'lsl-auth-transition-styles';

        style.textContent = `
            #lsl-auth-transition-overlay {
                position: fixed;
                inset: 0;
                z-index: 999999;
                background: #071426;
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
                transition:
                    opacity ${TRANSITION_DURATION}ms ease,
                    visibility ${TRANSITION_DURATION}ms ease;
            }

            #lsl-auth-transition-overlay.active {
                opacity: 1;
                visibility: visible;
                pointer-events: all;
            }

            @media (prefers-reduced-motion: reduce) {
                #lsl-auth-transition-overlay {
                    transition: none !important;
                }
            }
        `;

        document.head.appendChild(style);
    }

    function getOverlay() {
        let overlay = document.getElementById(
            'lsl-auth-transition-overlay'
        );

        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'lsl-auth-transition-overlay';
            overlay.setAttribute('aria-hidden', 'true');
            document.body.appendChild(overlay);
        }

        return overlay;
    }

    document.addEventListener('submit', event => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)) return;

        const action = form.querySelector('[name="action"]')?.value;

        if (action !== 'login' && action !== 'logout') return;

        if (form.dataset.lslAuthTransitionSubmitted === 'true') return;

        if (transitionInProgress) {
            event.preventDefault();
            return;
        }

        if (prefersReducedMotion()) return;

        event.preventDefault();
        transitionInProgress = true;

        getOverlay().classList.add('active');

        const buttons = form.querySelectorAll(
            'button[type="submit"], button:not([type]), input[type="submit"]'
        );

        buttons.forEach(button => {
            button.dataset.lslWasDisabled = button.disabled ? 'true' : 'false';
            button.disabled = true;
        });

        window.setTimeout(() => {
            form.dataset.lslAuthTransitionSubmitted = 'true';

            buttons.forEach(button => {
                button.disabled = button.dataset.lslWasDisabled === 'true';
                delete button.dataset.lslWasDisabled;
            });

            HTMLFormElement.prototype.submit.call(form);
        }, TRANSITION_DURATION);
    });

    window.addEventListener('pageshow', () => {
        const overlay = document.getElementById(
            'lsl-auth-transition-overlay'
        );

        if (!overlay) return;

        overlay.classList.remove('active');
        transitionInProgress = false;
    });
})();

/* =========================================================
   TOAST NOTIFICATIONS
   Example: showToast('Faculty saved successfully', 'success')
   Types: success, error, warning, info
   ========================================================= */

(function setupToastNotifications() {
    const style = document.createElement('style');
    style.id = 'lsl-enhancement-styles';

    style.textContent = `
        #lsl-toast-container {
            position: fixed;
            right: 22px;
            bottom: 22px;
            z-index: 1000000;
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: min(360px, calc(100vw - 32px));
            pointer-events: none;
        }

        .lsl-toast {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 13px 15px;
            border: 1px solid #29415f;
            border-radius: 12px;
            background: #0b1b31;
            color: #eef5ff;
            box-shadow: 0 12px 32px #0004;
            font: inherit;
            font-size: 14px;
            line-height: 1.45;
            pointer-events: auto;
            opacity: 0;
            transform: translateY(8px);
            transition: opacity .18s, transform .18s;
        }

        .lsl-toast.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .lsl-toast-mark { font-weight: 700; }
        .lsl-toast-message { flex: 1; overflow-wrap: anywhere; }

        .lsl-toast-close {
            border: 0;
            background: transparent;
            color: #b8c9df;
            cursor: pointer;
            font-size: 18px;
        }

        #lsl-loading-overlay {
            position: fixed;
            inset: 0;
            z-index: 999990;
            display: grid;
            place-items: center;
            background: #040d1b70;
            opacity: 0;
            visibility: hidden;
            transition: opacity .16s;
        }

        #lsl-loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .lsl-loading-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            border: 1px solid #29415f;
            border-radius: 14px;
            background: #0b1b31;
            color: #eef5ff;
            box-shadow: 0 14px 38px #0004;
        }

        .lsl-spinner {
            width: 21px;
            height: 21px;
            border: 2px solid #96bef040;
            border-top-color: #5da7ff;
            border-radius: 50%;
            animation: lslSpin .7s linear infinite;
        }

        @keyframes lslSpin {
            to { transform: rotate(360deg); }
        }

        .lsl-loading-button {
            opacity: .75;
            cursor: wait !important;
        }

        @media (prefers-reduced-motion: reduce) {
            .lsl-toast,
            #lsl-loading-overlay {
                transition: none !important;
            }

            .lsl-spinner { animation: none !important; }
        }
    `;

    if (!document.getElementById(style.id)) {
        document.head.appendChild(style);
    }

    function getToastContainer() {
        let container = document.getElementById('lsl-toast-container');

        if (!container) {
            container = document.createElement('div');
            container.id = 'lsl-toast-container';
            container.setAttribute('aria-live', 'polite');
            document.body.appendChild(container);
        }

        return container;
    }

    window.showToast = function (
        message,
        type = 'info',
        duration = 3500
    ) {
        if (!message) return;

        if (!['success', 'error', 'warning', 'info'].includes(type)) {
            type = 'info';
        }

        const toast = document.createElement('div');
        toast.className = 'lsl-toast';
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');

        const mark = document.createElement('span');
        mark.className = 'lsl-toast-mark';
        mark.textContent = {
            success: '✓',
            error: '!',
            warning: '!',
            info: 'i'
        }[type];

        mark.style.color = {
            success: '#73e0b8',
            error: '#ff9aa8',
            warning: '#ffd17d',
            info: '#8cc8ff'
        }[type];

        const text = document.createElement('span');
        text.className = 'lsl-toast-message';
        text.textContent = String(message);

        const close = document.createElement('button');
        close.className = 'lsl-toast-close';
        close.type = 'button';
        close.setAttribute('aria-label', 'Dismiss notification');
        close.textContent = '×';

        const remove = () => {
            toast.classList.remove('visible');
            setTimeout(() => toast.remove(), prefersReducedMotion() ? 0 : 200);
        };

        close.addEventListener('click', remove);
        toast.append(mark, text, close);
        getToastContainer().appendChild(toast);

        requestAnimationFrame(() => toast.classList.add('visible'));

        if (Number(duration) > 0) {
            setTimeout(remove, Number(duration));
        }
    };
})();

/* =========================================================
   LOADING INDICATOR FOR ORDINARY FORMS
   Login/logout keep their dedicated transition.
   ========================================================= */

(function setupFormLoading() {
    function getOverlay() {
        let overlay = document.getElementById('lsl-loading-overlay');

        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'lsl-loading-overlay';
            overlay.setAttribute('aria-hidden', 'true');

            overlay.innerHTML = `
                <div class="lsl-loading-card">
                    <span class="lsl-spinner" aria-hidden="true"></span>
                    <span class="lsl-loading-label">Processing...</span>
                </div>
            `;

            document.body.appendChild(overlay);
        }

        return overlay;
    }

    document.addEventListener('submit', event => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || event.defaultPrevented) {
            return;
        }

        if (
            form.dataset.lslAuthTransitionSubmitted === 'true' ||
            form.hasAttribute('data-no-loading')
        ) {
            return;
        }

        const action = form.querySelector('[name="action"]')?.value || '';

        if (action === 'login' || action === 'logout') return;
        if (prefersReducedMotion()) return;

        const overlay = getOverlay();

        overlay.querySelector('.lsl-loading-label').textContent =
            form.getAttribute('data-loading-text') || 'Processing...';

        overlay.classList.add('active');
        overlay.setAttribute('aria-hidden', 'false');

        form.querySelectorAll(
            'button[type="submit"], button:not([type]), input[type="submit"]'
        ).forEach(button => {
            button.classList.add('lsl-loading-button');
            button.setAttribute('aria-busy', 'true');
        });
    });

    window.addEventListener('pageshow', () => {
        const overlay = document.getElementById('lsl-loading-overlay');

        if (overlay) {
            overlay.classList.remove('active');
            overlay.setAttribute('aria-hidden', 'true');
        }

        document.querySelectorAll('.lsl-loading-button').forEach(button => {
            button.classList.remove('lsl-loading-button');
            button.removeAttribute('aria-busy');
        });
    });
})();

/* =========================================================
   LIVE SEARCH AND FILTERING
   Works with existing searchable tables and marked lists.
   ========================================================= */

(function setupLiveSearch() {
    function getTarget(input) {
        const selector = input.getAttribute('data-search-target');

        if (selector) {
            try {
                return document.querySelector(selector);
            } catch {
                return null;
            }
        }

        return (
            input.closest('[data-search-container]') ||
            input.closest('.panel') ||
            input.closest('.table-container') ||
            input.closest('.content') ||
            input.closest('main') ||
            document.querySelector('main') ||
            document.querySelector('.content')
        );
    }

    function attach(input) {
        if (input.dataset.lslSearchReady === 'true') return;

        input.dataset.lslSearchReady = 'true';

        input.addEventListener('input', () => {
            const target = getTarget(input);
            if (!target) return;

            const rows = Array.from(target.querySelectorAll('tbody tr'));

            const items = rows.length
                ? rows
                : Array.from(target.querySelectorAll('[data-search-item]'));

            if (!items.length) return;

            const query = input.value.trim().toLocaleLowerCase();
            let visibleCount = 0;

            items.forEach(item => {
                const content = (
                    item.getAttribute('data-search-text') ||
                    item.textContent ||
                    ''
                ).toLocaleLowerCase();

                item.hidden = !!query && !content.includes(query);

                if (!item.hidden) visibleCount++;
            });

            const empty = target.querySelector('[data-search-empty]');

            if (empty) {
                empty.hidden = visibleCount > 0;
            }
        });
    }

    function scan() {
        document.querySelectorAll(
            '[data-live-search], input[type="search"], ' +
            'input[placeholder*="search" i], input[aria-label*="search" i]'
        ).forEach(attach);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scan, { once: true });
    } else {
        scan();
    }
})();

/* =========================================================
   UNSAVED-CHANGES WARNING
   ========================================================= */

(function setupUnsavedChangesWarning() {
    let dirtyForm = null;
    let allowLeave = false;

    function isTrackable(form) {
        if (!(form instanceof HTMLFormElement)) return false;

        if (form.hasAttribute('data-no-unsaved-warning')) return false;
        if (form.method.toLowerCase() === 'get') return false;

        const action = form.querySelector('[name="action"]')?.value || '';

        if (
            action === 'login' ||
            action === 'logout' ||
            action.startsWith('delete_')
        ) {
            return false;
        }

        return !!form.querySelector(
            'input:not([type="hidden"]), select, textarea'
        );
    }

    function markDirty(form) {
        if (!isTrackable(form)) return;

        form.dataset.lslDirty = 'true';
        dirtyForm = form;
    }

    document.addEventListener('input', event => {
        markDirty(event.target.closest('form'));
    });

    document.addEventListener('change', event => {
        markDirty(event.target.closest('form'));
    });

    document.addEventListener('submit', event => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || event.defaultPrevented) {
            return;
        }

        delete form.dataset.lslDirty;

        if (dirtyForm === form) {
            dirtyForm = null;
        }
    });

    window.addEventListener('beforeunload', event => {
        if (allowLeave) return;

        if (
            !dirtyForm?.isConnected ||
            dirtyForm.dataset.lslDirty !== 'true'
        ) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    });

    document.addEventListener('click', event => {
        const link = event.target.closest('a[href]');

        if (!link || !dirtyForm || dirtyForm.dataset.lslDirty !== 'true') {
            return;
        }

        if (
            (link.target && link.target !== '_self') ||
            link.hasAttribute('download')
        ) {
            return;
        }

        let url;

        try {
            url = new URL(link.href, location.href);
        } catch {
            return;
        }

        if (url.href === location.href) return;

        if (!confirm(
            'You may have unsaved changes. Leave this page and discard them?'
        )) {
            event.preventDefault();
            event.stopImmediatePropagation();
            return;
        }

        allowLeave = true;
        dirtyForm = null;
    }, true);

    /* Ctrl/Cmd+S only submits forms explicitly configured for the shortcut. */
    window.addEventListener('keydown', event => {
        if (
            !(event.ctrlKey || event.metaKey) ||
            event.key.toLowerCase() !== 's'
        ) {
            return;
        }

        const form = document.activeElement?.closest('form');

        if (!form || form.getAttribute('data-shortcut-save') !== 'true') {
            return;
        }

        const button = form.querySelector(
            'button[type="submit"], button:not([type]), input[type="submit"]'
        );

        if (!button || button.disabled) return;

        event.preventDefault();

        if (form.requestSubmit) {
            form.requestSubmit(button);
        } else {
            button.click();
        }
    });
})();

/* =========================================================
   ANIMATED DASHBOARD STATISTICS
   Uses the existing .stat-value elements.
   ========================================================= */

(function setupDashboardCountUp() {
    function animate(element) {
        if (
            element.dataset.lslCountAnimated === 'true' ||
            prefersReducedMotion()
        ) {
            return;
        }

        const original = element.textContent.trim();
        const match = original.match(/^(\d[\d,]*)(.*)$/);

        if (!match) return;

        const end = Number(match[1].replace(/,/g, ''));

        if (!Number.isFinite(end) || end < 0) return;

        element.dataset.lslCountAnimated = 'true';

        const suffix = match[2] || '';
        const start = performance.now();

        function frame(now) {
            const progress = Math.min((now - start) / 650, 1);
            const eased = 1 - Math.pow(1 - progress, 3);

            element.textContent =
                Math.round(end * eased).toLocaleString() + suffix;

            if (progress < 1) {
                requestAnimationFrame(frame);
            } else {
                element.textContent = original;
            }
        }

        element.textContent = '0' + suffix;
        requestAnimationFrame(frame);
    }

    function run() {
        document.querySelectorAll('.stat-value').forEach(animate);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run, { once: true });
    } else {
        run();
    }
})();

/* =========================================================
   KEYBOARD SHORTCUT
   Press "/" to focus a visible search field.
   ========================================================= */

(function setupSearchShortcut() {
    document.addEventListener('keydown', event => {
        if (
            event.defaultPrevented ||
            event.key !== '/' ||
            event.ctrlKey ||
            event.metaKey ||
            event.altKey ||
            event.shiftKey
        ) {
            return;
        }

        const active = document.activeElement;

        if (
            active &&
            (
                active.matches('input, textarea, select') ||
                active.isContentEditable
            )
        ) {
            return;
        }

        const search = Array.from(document.querySelectorAll(
            '[data-live-search], input[type="search"], ' +
            'input[placeholder*="search" i], input[aria-label*="search" i]'
        )).find(input => {
            return !input.disabled && input.getClientRects().length > 0;
        });

        if (!search) return;

        event.preventDefault();
        search.focus();

        if (search.select) search.select();
    });
})();

/* =========================================================
   OPTIONAL WEEKLY SCHEDULE NAVIGATION
   Activates only if the matching data attributes exist.
   ========================================================= */

(function setupWeeklyScheduleNavigation() {
    const previous = document.querySelector('[data-week-prev]');
    const next = document.querySelector('[data-week-next]');
    const label = document.querySelector('[data-week-label]');
    const items = Array.from(
        document.querySelectorAll('[data-week-start]')
    );

    if (!previous || !next || !label || !items.length) return;

    let offset = 0;

    function mondayOf(date) {
        const monday = new Date(date);
        monday.setHours(0, 0, 0, 0);

        monday.setDate(
            monday.getDate() +
            (monday.getDay() === 0 ? -6 : 1 - monday.getDay())
        );

        return monday;
    }

    function iso(date) {
        return [
            date.getFullYear(),
            String(date.getMonth() + 1).padStart(2, '0'),
            String(date.getDate()).padStart(2, '0')
        ].join('-');
    }

    function render() {
        const monday = mondayOf(new Date());
        monday.setDate(monday.getDate() + offset * 7);

        const sunday = new Date(monday);
        sunday.setDate(sunday.getDate() + 6);

        const options = {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        };

        label.textContent =
            `${monday.toLocaleDateString(undefined, options)} – ` +
            `${sunday.toLocaleDateString(undefined, options)}`;

        items.forEach(item => {
            const date = item.getAttribute('data-week-start');

            item.hidden = !date || date < iso(monday) || date > iso(sunday);
        });
    }

    previous.addEventListener('click', () => {
        offset--;
        render();
    });

    next.addEventListener('click', () => {
        offset++;
        render();
    });

    render();
})();

/* =========================================================
   OPTIONAL NOTIFICATION DROPDOWN
   Persistent notifications require backend support.
   ========================================================= */

(function setupNotificationDropdown() {
    const toggle = document.querySelector('[data-notification-toggle]');
    const panel = document.querySelector('[data-notification-panel]');

    if (!toggle || !panel) return;

    toggle.setAttribute('aria-expanded', 'false');
    panel.hidden = true;

    function setOpen(open) {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
    }

    toggle.addEventListener('click', event => {
        event.preventDefault();
        setOpen(panel.hidden);
    });

    document.addEventListener('click', event => {
        if (
            !event.target.closest('[data-notification-toggle]') &&
            !event.target.closest('[data-notification-panel]')
        ) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') setOpen(false);
    });
})();