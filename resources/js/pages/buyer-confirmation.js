import axios from "axios";

const root = document.getElementById("buyer-confirmation");

if (root) {
    const token = root.dataset.token;

    const endpoint = `/api/buyer-confirmations/${encodeURIComponent(token)}`;

    const api = axios.create({
        headers: {
            Accept: "application/json",
        },
        timeout: 15000,
    });

    const $ = (id) => document.getElementById(id);

    const states = [
        "loading-state",
        "error-state",
        "transaction-state",
        "success-state",
    ];

    function showState(id) {
        states.forEach((state) => {
            $(state).hidden = state !== id;
        });
    }

    function setText(id, value) {
        $(id).textContent = value ?? "—";
    }

    function formatMoney(value) {
        const number = Number(value);

        if (!Number.isFinite(number)) return "—";

        return new Intl.NumberFormat("en-PH", {
            style: "currency",
            currency: "PHP",
        }).format(number);
    }

    function formatDate(value) {
        if (!value) return "—";

        const date = new Date(value);

        if (Number.isNaN(date.getTime())) return "—";

        return date.toLocaleString("en-PH", {
            dateStyle: "medium",
            timeStyle: "short",
            timeZone: "Asia/Manila",
        });
    }

    function getError(error) {
        const data = error.response?.data;

        if (data?.errors) {
            const first = Object.values(data.errors)[0];

            if (Array.isArray(first) && first.length) {
                return first[0];
            }
        }

        return data?.message || "Something went wrong. Please try again.";
    }

    function setBusy(busy) {
        $("confirm-btn").disabled = busy;

        $("dispute-toggle").disabled = busy;

        $("dispute-form").querySelector("button[type='submit']").disabled =
            busy;
    }

    function showActionError(message) {
        setText("action-error", message);
        $("action-error").hidden = false;
    }

    function renderItems(items) {
        const container = $("livestock-items");

        container.replaceChildren();

        for (const item of items || []) {
            const element = document.createElement("div");
            element.className = "livestock-item";

            const species = document.createElement("strong");
            species.textContent = item.species || "Livestock";

            const price = document.createElement("span");
            price.textContent = formatMoney(item.selling_price);

            element.append(species, price);
            container.appendChild(element);
        }
    }

    async function loadTransaction() {
        showState("loading-state");

        try {
            const response = await api.get(endpoint);
            const data = response.data.data;

            setText("buyer-name", data.buyer_name);
            setText("seller-name", data.seller_name);

            setText("transaction-date", formatDate(data.transaction_date));

            setText("selling-price", formatMoney(data.total_selling_price));

            setText("expires-at", formatDate(data.expires_at));

            renderItems(data.items);

            showState("transaction-state");
        } catch (error) {
            setText("error-message", getError(error));
            showState("error-state");
        }
    }

    $("confirm-btn").addEventListener("click", async () => {
        const agreed = window.confirm(
            "Are you sure the transaction details are correct?",
        );

        if (!agreed) return;

        setBusy(true);
        $("action-error").hidden = true;

        try {
            const response = await api.post(`${endpoint}/confirm`);

            setText("success-title", "Transaction Confirmed");

            setText("success-message", response.data.message);

            showState("success-state");
        } catch (error) {
            showActionError(getError(error));
        } finally {
            setBusy(false);
        }
    });

    $("dispute-toggle").addEventListener("click", () => {
        $("dispute-form").hidden = !$("dispute-form").hidden;
    });

    $("dispute-form").addEventListener("submit", async (event) => {
        event.preventDefault();

        const reason = $("dispute-reason").value.trim();

        if (!reason) {
            showActionError("Please provide a reason for the dispute.");
            return;
        }

        setBusy(true);
        $("action-error").hidden = true;

        try {
            const response = await api.post(`${endpoint}/dispute`, { reason });

            setText("success-title", "Dispute Submitted");

            setText("success-message", response.data.message);

            showState("success-state");
        } catch (error) {
            showActionError(getError(error));
        } finally {
            setBusy(false);
        }
    });

    loadTransaction();
}
