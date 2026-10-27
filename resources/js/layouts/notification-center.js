import api from '../api';

const $ = (id) => document.getElementById(id);
const button = $('notification-button');
const panel = $('notification-panel');
const badge = $('notification-count');
const list = $('notification-list');
const status = $('notification-status');
const allRead = $('notification-read-all');
const more = $('notification-load-more');

if (button && panel && badge && list && status && allRead && more) {
  let nextPage = null;
  let loading = false;
  let unreadCount = 0;

  const formatDate = (date) => {
    if (!date) return '';
    const d = new Date(date);
    return Number.isNaN(d.getTime()) ? '' : new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium', timeStyle: 'short' }).format(d);
  };

  function getDisplay(notification) {
    const data = notification.data && typeof notification.data === 'object' ? notification.data : {};
    return {
      title: String(data.title ?? data.subject ?? data.heading ?? 'Account notification'),
      message: String(data.message ?? data.body ?? data.description ?? 'You have a new update.'),
    };
  }

  function setStatus(message = '') {
    status.textContent = message;
    status.hidden = !message;
  }

  function renderBadge() {
    badge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
    badge.hidden = unreadCount === 0;
    button.setAttribute('aria-label', unreadCount ? `Notifications, ${unreadCount} unread` : 'Notifications');
    allRead.disabled = unreadCount === 0;
  }

  async function refreshCount() {
    try {
      const response = await api.get('/notifications/unread-count');
      unreadCount = Math.max(0, Number(response.data?.count) || 0);
      renderBadge();
    } catch (error) {
      if (error.response?.status === 401) return;
      console.warn('Unable to refresh notification count', error);
    }
  }

  function createItem(notification) {
    const unread = !notification.read_at;
    const { title, message } = getDisplay(notification);
    const item = document.createElement('button');
    item.type = 'button';
    item.className = `notification-item${unread ? ' is-unread' : ''}`;
    item.dataset.id = String(notification.id);
    item.disabled = !unread;

    const dot = document.createElement('span');
    dot.className = 'notification-item-dot';
    dot.setAttribute('aria-hidden', 'true');

    const body = document.createElement('span');
    body.className = 'notification-item-body';
    const heading = document.createElement('strong');
    heading.textContent = title;
    const description = document.createElement('span');
    description.textContent = message;
    const time = document.createElement('small');
    time.textContent = formatDate(notification.created_at);
    body.append(heading, description, time);
    item.append(dot, body);

    if (unread) {
      item.title = 'Mark as read';
      item.addEventListener('click', async () => {
        item.disabled = true;
        try {
          await api.patch(`/notifications/${encodeURIComponent(notification.id)}/read`);
          item.classList.remove('is-unread');
          item.title = 'Read';
          unreadCount = Math.max(0, unreadCount - 1);
          renderBadge();
        } catch (error) {
          item.disabled = false;
          setStatus(error.response?.data?.message || 'Unable to mark notification as read.');
        }
      });
    }
    return item;
  }

  async function loadNotifications(append = false) {
    if (loading) return;
    loading = true;
    more.disabled = true;
    if (!append) {
      list.replaceChildren();
      setStatus('Loading notifications…');
    } else {
      setStatus('Loading more…');
    }
    try {
      // Only follow pagination paths returned by our own API, using the same Axios base URL.
      const endpoint = append && nextPage
        ? (() => { const url = new URL(nextPage, window.location.origin); return url.pathname.replace(/^\/api(?=\/)/, '') + url.search; })()
        : '/notifications';
      const response = await api.get(endpoint);
      const payload = response.data;
      const notifications = Array.isArray(payload?.data) ? payload.data : [];
      for (const notification of notifications) list.appendChild(createItem(notification));
      nextPage = payload?.next_page_url ?? null;
      more.hidden = !nextPage;
      setStatus(list.children.length ? '' : 'You’re all caught up. No notifications yet.');
      await refreshCount();
    } catch (error) {
      setStatus(error.response?.data?.message || 'Unable to load notifications. Please try again.');
    } finally {
      loading = false;
      more.disabled = false;
    }
  }

  function closePanel() {
    panel.hidden = true;
    button.setAttribute('aria-expanded', 'false');
  }

  button.addEventListener('click', () => {
    const open = panel.hidden;
    panel.hidden = !open;
    button.setAttribute('aria-expanded', String(open));
    if (open) loadNotifications();
  });

  document.addEventListener('click', (event) => {
    if (!panel.hidden && !event.target.closest('.notification-wrap')) closePanel();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closePanel();
  });

  allRead.addEventListener('click', async () => {
    allRead.disabled = true;
    try {
      await api.patch('/notifications/read-all');
      list.querySelectorAll('.notification-item.is-unread').forEach(item => {
        item.classList.remove('is-unread');
        item.disabled = true;
        item.title = 'Read';
      });
      unreadCount = 0;
      renderBadge();
    } catch (error) {
      setStatus(error.response?.data?.message || 'Unable to mark all as read.');
      await refreshCount();
    }
  });

  more.addEventListener('click', () => {
    if (nextPage) loadNotifications(true);
  });

  // Only authenticated pages mount this UI. The server still enforces Sanctum.
  if (sessionStorage.getItem('agritimbang_token')) refreshCount();
}
