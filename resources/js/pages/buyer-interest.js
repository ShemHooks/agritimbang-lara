const page = document.getElementById('buyer-interest-page');
const scheduleId = page?.dataset.scheduleId || window.BuyerInterestConfig?.scheduleId || '';
const csrfToken = window.BuyerInterestConfig?.csrfToken || '';
const selectedIds = new Set();
let currentBuyerCode = '';

const interestModal = document.getElementById('interestModal');
const loginModal = document.getElementById('loginBuyerModal');
const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
}[character]));

const openModal = (modal) => {
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    modal.classList.add('show');
    document.body.classList.add('modal-open');
};

const closeModal = (modal) => {
    modal.classList.remove('show');
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    if (!document.querySelector('.interest-modal.show')) {
        document.body.classList.remove('modal-open');
    }
};

const formatDate = (value) => {
    if (!value) return '—';

    const date = new Date(`${value}T00:00:00+08:00`);
    return date.toLocaleDateString('en-PH', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const formatMoney = (value) => {
    const number = Number(value ?? 0);
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
    }).format(number);
};

const setSelectedCount = () => {
    const count = selectedIds.size;
    document.getElementById('selected-count').textContent = `${count} livestock selected`;
    const button = document.getElementById('create-interest-trigger');
    button.disabled = count === 0;
};

const renderAuction = (payload) => {
    const schedule = payload?.schedule || {};
    const heading = document.getElementById('auction-heading');
    heading.textContent = `Auction Date — ${formatDate(schedule.auction_date)} | Municipality — ${schedule.municipality || '—'}`;

    const status = document.getElementById('auction-status');
    status.textContent = `${(payload.livestock || []).length} livestock currently listed for this auction event.`;
    status.className = 'alert alert-info';
};

const renderLivestockCards = (items) => {
    const list = document.getElementById('livestock-list');
    if (!items.length) {
        list.innerHTML = '<div class="col-12"><div class="alert alert-warning mb-0">No livestock are currently listed for this auction.</div></div>';
        return;
    }

    list.innerHTML = items.map((item) => `
        <article class="livestock-card">
            <div style="position: relative">
                <img src="${escapeHtml(item.photo || '/assets/logo.png')}" class="livestock-photo" alt="${escapeHtml(item.name)}">
                <label style="position:absolute;top:10px;right:10px;background:#fff;padding:6px;border-radius:6px">
                    <input class="livestock-checkbox" type="checkbox" value="${escapeHtml(item.id)}" aria-label="Select ${escapeHtml(item.name)}" ${selectedIds.has(item.id) ? 'checked' : ''}>
                </label>
            </div>
            <div class="livestock-card-body">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <h3>${escapeHtml(item.name)}</h3>
                    <span class="badge">${escapeHtml(item.breed || '—')}</span>
                </div>
                <ul class="livestock-details">
                    <li><strong>Age:</strong> ${escapeHtml(item.age)}</li>
                    <li><strong>Weight:</strong> ${escapeHtml(item.weight)}</li>
                    <li><strong>Starting Price:</strong> ${item.starting_price !== null && item.starting_price !== undefined ? formatMoney(item.starting_price) : 'Not set'}</li>
                    <li><strong>Municipality:</strong> ${escapeHtml(item.municipality || '—')}</li>
                    <li><strong>Auction Date:</strong> ${formatDate(item.auction_date)}</li>
                    <li><strong>Seller:</strong> ${escapeHtml(item.seller_name || 'Seller')}</li>
                </ul>
            </div>
        </article>
    `).join('');

    list.querySelectorAll('.livestock-checkbox').forEach((checkbox) => {
        checkbox.addEventListener('change', (event) => {
            const id = event.target.value;
            if (event.target.checked) {
                selectedIds.add(id);
            } else {
                selectedIds.delete(id);
            }
            setSelectedCount();
        });
    });
};

const getSelectedLivestock = () => Array.from(selectedIds);

const renderSelectedList = () => {
    const selected = getSelectedLivestock();
    const list = document.getElementById('selected-livestock-list');
    if (!list) return;

    if (!selected.length) {
        list.innerHTML = '<li class="list-group-item text-muted">No livestock selected.</li>';
        return;
    }

    list.innerHTML = selected.map((id) => `
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <span>Selected livestock #${escapeHtml(id.slice(0, 8))}</span>
            <input type="hidden" name="livestock_ids[]" value="${escapeHtml(id)}">
        </li>
    `).join('');
};

const renderInterestForm = ({ mode = 'new', defaultCode = '' } = {}) => {
    const body = document.getElementById('interest-modal-body');
    const selected = getSelectedLivestock();

    if (mode === 'existing') {
        body.innerHTML = `
            <div class="mb-3 form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="existing-account-toggle" checked>
                <label class="form-check-label" for="existing-account-toggle">I already have a temporary account for this auction</label>
            </div>
            <form id="update-buyer-form">
                <div class="mb-3">
                    <label class="form-label" for="existing-account-code">Account Code</label>
                    <input id="existing-account-code" name="account_code" class="form-control text-uppercase" value="${escapeHtml(defaultCode)}" placeholder="BUY-ABC123" required>
                </div>
                <div class="alert alert-light border mb-3">
                    <strong>Selected livestock for update</strong>
                    <span class="d-block text-muted mt-1">${selected.length} item(s) selected</span>
                </div>
                <div class="d-flex justify-content-between align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Buyer Interests</button>
                </div>
            </form>
        `;

        const form = document.getElementById('update-buyer-form');
        form.addEventListener('submit', handleExistingBuyerSubmit);
        document.getElementById('existing-account-toggle').addEventListener('change', (event) => {
            if (!event.target.checked) {
                renderInterestForm({ mode: 'new' });
            }
        });
        return;
    }

    body.innerHTML = `
        <div class="mb-3 form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="existing-account-toggle">
            <label class="form-check-label" for="existing-account-toggle">I already have a temporary account for this auction</label>
        </div>

        <form id="new-buyer-form">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="first_name">First Name</label>
                    <input id="first_name" name="first_name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="last_name">Last Name</label>
                    <input id="last_name" name="last_name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="mobile_number">Mobile Number</label>
                    <input id="mobile_number" name="mobile_number" class="form-control" placeholder="09XX-XXX-XXXX" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="buyer_type">Buyer Type</label>
                    <select id="buyer_type" name="buyer_type" class="form-select" required>
                        <option value="">Select buyer type</option>
                        <option>Individual</option>
                        <option>Business</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="interest_status">Interest Status</label>
                    <select id="interest_status" name="interest_status" class="form-select" required>
                        <option value="Active">Active</option>
                        <option value="Withdrawn">Withdrawn</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="attendance_intention">Attendance Intention</label>
                    <select id="attendance_intention" name="attendance_intention" class="form-select" required>
                        <option value="Yes — Will attend">Yes — Will attend</option>
                        <option value="No — Cannot attend">No — Cannot attend</option>
                        <option value="Tentative">Tentative</option>
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <label class="form-label">Livestock Interested</label>
                <ul class="list-group" id="selected-livestock-list"></ul>
            </div>

            <div class="mt-3 form-check">
                <input class="form-check-input" type="checkbox" id="contact_consent" name="contact_consent" value="1" required>
                <label class="form-check-label" for="contact_consent">I agree to be contacted regarding this interest</label>
            </div>

            <div class="d-flex justify-content-between align-items-center gap-2 mt-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Temporary Account</button>
            </div>
        </form>
    `;

    renderSelectedList();
    document.getElementById('mobile_number').addEventListener('input', (event) => {
        const digits = event.target.value.replace(/\D/g, '').slice(0, 11);
        let formatted = digits;
        if (digits.length > 2 && digits.length <= 6) {
            formatted = `${digits.slice(0, 2)}-${digits.slice(2)}`;
        } else if (digits.length > 6) {
            formatted = `${digits.slice(0, 2)}-${digits.slice(2, 5)}-${digits.slice(5, 9)}`;
        }
        event.target.value = formatted;
    });

    document.getElementById('new-buyer-form').addEventListener('submit', handleNewBuyerSubmit);
    document.getElementById('existing-account-toggle').addEventListener('change', (event) => {
        if (event.target.checked) {
            renderInterestForm({ mode: 'existing', defaultCode: currentBuyerCode });
        }
    });
};

const apiJson = (url, options = {}) => fetch(url, {
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        ...(options.headers || {}),
    },
    ...options,
});

const handleNewBuyerSubmit = async (event) => {
    event.preventDefault();
    const form = event.target;
    const data = Object.fromEntries(new FormData(form).entries());
    data.livestock_ids = getSelectedLivestock();
    data.contact_consent = data.contact_consent === '1';

    const response = await apiJson(`/api/buyer-interest/auction/${scheduleId}/create`, {
        method: 'POST',
        body: JSON.stringify(data),
    });

    const payload = await response.json();
    if (!response.ok) {
        const message = payload.errors ? Object.values(payload.errors).flat().join(' ') : (payload.message || 'Unable to create buyer interest.');
        alert(message);
        return;
    }

    const code = payload.data?.buyer?.account_code || 'BUY-XXXXXX';
    currentBuyerCode = code;

    document.getElementById('interest-modal-body').innerHTML = `
        <div class="text-center py-3">
            <h4 class="mb-3">Account created successfully</h4>
            <div class="alert alert-success">
                <p class="mb-1 text-uppercase small">Your Account Code</p>
                <h3 class="fw-bold mb-0">${escapeHtml(code)}</h3>
            </div>
            <p class="mb-3">This account expires after this auction day. You will need a new temporary account for future events.</p>
            <p class="fw-semibold text-success">Temporary account created successfully! Your code: ${escapeHtml(code)}. Please save this code to update your interests later. It expires after ${escapeHtml(formatDate(document.querySelector('#auction-heading')?.textContent?.split('|')[0]?.replace('Auction Date — ', '') || ''))}.</p>
            <div class="d-flex justify-content-center gap-2 flex-wrap mt-4">
                <a class="btn btn-primary" href="${escapeHtml(payload.data?.pdf_url || '#')}" target="_blank" rel="noreferrer">Download PDF</a>
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()">Print</button>
            </div>
        </div>
    `;
};

const handleExistingBuyerSubmit = async (event) => {
    event.preventDefault();
    const form = event.target;
    const data = {
        account_code: document.getElementById('existing-account-code')?.value || '',
        livestock_ids: getSelectedLivestock(),
    };

    const response = await apiJson(`/api/buyer-interest/auction/${scheduleId}/update`, {
        method: 'POST',
        body: JSON.stringify(data),
    });

    const payload = await response.json();
    if (!response.ok) {
        const message = payload.errors ? Object.values(payload.errors).flat().join(' ') : (payload.message || 'Unable to update buyer interests.');
        alert(message);
        return;
    }

    document.getElementById('interest-modal-body').innerHTML = `
        <div class="text-center py-3">
            <h4 class="mb-3">Your interests have been updated.</h4>
            <p class="mb-0">${escapeHtml(payload.message)}</p>
            <div class="d-flex justify-content-center gap-2 flex-wrap mt-4">
                <a class="btn btn-primary" href="${escapeHtml(payload.data?.pdf_url || '#')}" target="_blank" rel="noreferrer">Download PDF</a>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    `;
};

const renderLoginForm = () => {
    const body = document.getElementById('login-modal-body');
    body.innerHTML = `
        <form id="login-buyer-form">
            <div class="mb-3">
                <label class="form-label" for="login-account-code">Account Code</label>
                <input id="login-account-code" name="account_code" class="form-control text-uppercase" placeholder="BUY-ABC123" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Auction Event Date</label>
                <input class="form-control" value="${document.getElementById('auction-heading')?.textContent?.split('Auction Date — ')[1]?.split(' |')[0] || ''}" readonly>
            </div>
            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">Login</button>
            </div>
        </form>
    `;

    document.getElementById('login-buyer-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.target;
        const payload = Object.fromEntries(new FormData(form).entries());

        const response = await apiJson(`/api/buyer-interest/auction/${scheduleId}/login`, {
            method: 'POST',
            body: JSON.stringify(payload),
        });

        const result = await response.json();
        if (!response.ok) {
            const message = result.errors ? Object.values(result.errors).flat().join(' ') : (result.message || 'Unable to login.');
            alert(message);
            return;
        }

        const buyer = result.data?.buyer || {};
        const interests = result.data?.interests || [];
        currentBuyerCode = buyer.account_code || '';

        const badge = buyer.is_expired ? '<span class="badge bg-danger">EXPIRED</span>' : '<span class="badge bg-success">ACTIVE</span>';
        const updateButton = buyer.can_update
            ? '<button type="button" class="btn btn-primary me-2" id="login-update-interests">Update My Interests</button>'
            : '<button type="button" class="btn btn-secondary me-2" disabled>Update My Interests</button>';

        body.innerHTML = `
            <div class="mb-3">
                <h5 class="mb-1">${escapeHtml(buyer.name)}</h5>
                ${badge}
            </div>
            <p class="mb-3">${escapeHtml(result.message)}</p>
            <div class="table-responsive mb-3">
                <table class="table table-sm align-middle">
                    <tbody>
                        <tr><th scope="row">Name</th><td>${escapeHtml(buyer.name || '—')}</td></tr>
                        <tr><th scope="row">Account Code</th><td>${escapeHtml(buyer.account_code || '—')}</td></tr>
                        <tr><th scope="row">Expiry Date</th><td>${buyer.expires_at ? formatDate(buyer.expires_at.slice(0, 10)) : '—'}</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="mb-3">
                <h6>Registered Livestock</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr><th>Livestock</th><th>Breed</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            ${interests.length ? interests.map((item) => `
                                <tr>
                                    <td>${escapeHtml(item.name || 'Livestock')}</td>
                                    <td>${escapeHtml(item.breed || '—')}</td>
                                    <td><span class="badge ${item.status === 'active' ? 'bg-success' : 'bg-secondary'}">${escapeHtml(item.status)}</span></td>
                                </tr>
                            `).join('') : '<tr><td colspan="3">No livestock registered.</td></tr>'}
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                ${updateButton}
                <a class="btn btn-outline-primary" href="${escapeHtml(result.data?.pdf_url || '#')}" target="_blank" rel="noreferrer">Download My Interest Summary</a>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close / Logout</button>
            </div>
        `;

        const updateButtonElement = document.getElementById('login-update-interests');
        if (updateButtonElement) {
            updateButtonElement.addEventListener('click', () => {
                closeModal(loginModal);
                renderInterestForm({ mode: 'existing', defaultCode: buyer.account_code });
                openModal(interestModal);
            });
        }
    });
};

const loadAuction = async () => {
    if (!scheduleId) {
        document.getElementById('auction-status').textContent = 'Auction schedule was not supplied.';
        return;
    }

    try {
        const response = await apiJson(`/api/buyer-interest/auction/${scheduleId}`);
        let payload;
        try {
            payload = await response.json();
        } catch {
            throw new Error(`Auction listing endpoint returned an invalid response (HTTP ${response.status}).`);
        }

        if (!response.ok) {
            throw new Error(payload.message || `Unable to load auction details (HTTP ${response.status}).`);
        }

        const data = payload.data || payload;
        renderAuction(data);
        renderLivestockCards(data.livestock || []);
        setSelectedCount();
        renderInterestForm({ mode: 'new' });
        renderLoginForm();
    } catch (error) {
        document.getElementById('auction-status').textContent = error.message || 'Unable to load this auction.';
        document.getElementById('auction-status').className = 'alert alert-danger';
    }
};

document.getElementById('create-interest-trigger')?.addEventListener('click', () => {
    renderInterestForm({ mode: 'new' });
    openModal(interestModal);
});

document.getElementById('login-interest-trigger')?.addEventListener('click', () => {
    openModal(loginModal);
    const input = document.getElementById('login-account-code');
    if (input) {
        input.focus();
    }
});

document.querySelectorAll('.interest-modal').forEach((modal) => {
    modal.addEventListener('click', (event) => {
        if (event.target === modal || event.target.closest('[data-modal-close], [data-bs-dismiss="modal"]')) {
            closeModal(modal);
        }
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        document.querySelectorAll('.interest-modal.show').forEach(closeModal);
    }
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadAuction);
} else {
    loadAuction();
}
