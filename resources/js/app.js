import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('layout', () => ({
    sidebarCollapsed: localStorage.getItem('brix:sidebar-collapsed') === '1',
    mobileNavOpen: false,

    toggleSidebar() {
        this.sidebarCollapsed = !this.sidebarCollapsed;
        localStorage.setItem('brix:sidebar-collapsed', this.sidebarCollapsed ? '1' : '0');
    },
}));

Alpine.data('notificationPanel', (groups) => ({
    open: false,
    groups: groups,

    get unreadCount() {
        return this.groups.reduce(
            (sum, group) => sum + group.items.filter((item) => !item.read).length,
            0
        );
    },

    markRead(item) {
        if (item.read) return;

        item.read = true;

        window.axios.post(`/notifications/${item.id}/read`).catch(() => {
            item.read = false;
        });
    },

    markAllRead() {
        const previous = this.groups.map((g) => g.items.map((i) => i.read));

        this.groups.forEach((group) => group.items.forEach((item) => (item.read = true)));

        window.axios.post('/notifications/read-all').catch(() => {
            this.groups.forEach((group, gi) => {
                group.items.forEach((item, ii) => (item.read = previous[gi][ii]));
            });
        });
    },
}));

Alpine.data('profileMenu', () => ({
    open: false,
    view: 'menu',

    close() {
        this.open = false;
        this.view = 'menu';
    },
}));

Alpine.data('requestPayoutModal', (availableBalance, currencySymbol = '₹') => ({
    show: false,
    amount: availableBalance,
    notes: '',
    submitting: false,
    error: null,
    success: null,

    open() {
        this.show = true;
        this.error = null;
        this.success = null;
        this.amount = availableBalance;
        this.notes = '';
    },

    close() {
        if (this.submitting) return; // don't let a mid-flight request get closed out from under it
        this.show = false;
    },

    submit() {
        // Duplicate-click guard — the server independently re-validates too.
        if (this.submitting) return;

        this.submitting = true;
        this.error = null;

        window.axios
            .post('/payouts/request', { amount: this.amount, notes: this.notes })
            .then((response) => {
                this.success = response.data.payout;
            })
            .catch((err) => {
                this.error = err.response?.data?.message || 'Something went wrong. Please try again.';
            })
            .finally(() => {
                this.submitting = false;
            });
    },

    finish() {
        // Reload so the balance cards and payout history reflect the new request.
        window.location.reload();
    },
}));

Alpine.data('toastNotice', (message, type) => ({
    show: true,
    message,
    type,

    init() {
        setTimeout(() => {
            this.show = false;
        }, 4500);
    },
}));

Alpine.start();
