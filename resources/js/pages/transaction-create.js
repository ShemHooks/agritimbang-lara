import api from "../api";

console.log("[AgriTimbang] Transaction module loaded");

function initializeTransactionPage() {
    const root = document.getElementById("sale-page");

    if (!root) {
        console.warn("[Transactions] #sale-page not found");
        return;
    }

    const $ = (id) => document.getElementById(id);

    const form = $("sale-form");
    const itemsContainer = $("sale-items");
    const alertBox = $("sale-alert");
    const saveButton = $("save-draft");
    const submitButton = $("submit-sale");
    const addButton = $("add-item");
    const totalElement = $("sale-total");

    if (
        !form ||
        !itemsContainer ||
        !alertBox ||
        !saveButton ||
        !submitButton ||
        !addButton ||
        !totalElement
    ) {
        console.error("[Transactions] Missing required form elements");
        return;
    }

    const money = new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
    });

    const state = {
        livestock: [],
        rows: new Map(),
        draftId: null,
        busy: false,
        submitted: false,
        uncertain: false,
        nextRowId: 0,
    };

    // --------------------------------------------------
    // API helpers
    // --------------------------------------------------

    function unwrap(response) {
        return response.data?.data ?? response.data;
    }

    function collection(payload) {
        if (Array.isArray(payload)) return payload;
        if (Array.isArray(payload?.data)) return payload.data;
        return [];
    }

    function getErrorMessage(error) {
        const data = error?.response?.data;

        if (data?.errors) {
            const messages = Object.values(data.errors).flat();
            if (messages.length) return messages.join(" ");
        }

        return data?.message || error?.message || "Something went wrong.";
    }

    // --------------------------------------------------
    // UI helpers
    // --------------------------------------------------

    function showAlert(message, type = "error") {
        alertBox.textContent = message;
        alertBox.className = `sale-alert ${type}`;
        alertBox.hidden = false;
    }

    function clearAlert() {
        alertBox.hidden = true;
        alertBox.textContent = "";
    }

    function setBusy(value) {
        state.busy = value;

        saveButton.disabled =
            value || Boolean(state.draftId) || state.uncertain;

        submitButton.disabled = value || state.submitted || state.uncertain;

        addButton.disabled = value || Boolean(state.draftId) || state.uncertain;

        for (const row of state.rows.values()) {
            row.remove.disabled =
                value || Boolean(state.draftId) || state.uncertain;
        }
    }

    function lockForm() {
        form.querySelectorAll("input, select, textarea").forEach((el) => {
            el.disabled = true;
        });

        for (const row of state.rows.values()) {
            row.remove.disabled = true;
        }

        addButton.disabled = true;
        saveButton.disabled = true;
        saveButton.textContent = "Draft Saved";
    }

    function updateTotal() {
        const total = [...state.rows.values()].reduce((sum, row) => {
            const price = Number(row.price.value);

            return sum + (Number.isFinite(price) && price > 0 ? price : 0);
        }, 0);

        totalElement.textContent = money.format(total);
    }

    // --------------------------------------------------
    // Livestock
    // --------------------------------------------------

    function selectedLivestockIds(exceptId = null) {
        return new Set(
            [...state.rows.entries()]
                .filter(([id]) => id !== exceptId)
                .map(([, row]) => row.livestock.value)
                .filter(Boolean),
        );
    }

    function refreshLivestockOptions() {
        for (const [id, row] of state.rows.entries()) {
            const previous = row.livestock.value;
            const used = selectedLivestockIds(id);

            row.livestock.replaceChildren(new Option("Select livestock", ""));

            for (const animal of state.livestock) {
                if (used.has(String(animal.id))) continue;

                const species =
                    animal.species?.name || animal.species_name || "Livestock";

                const breed = animal.breed?.name || animal.breed_name || "";

                const label = [species, breed, String(animal.id).slice(0, 8)]
                    .filter(Boolean)
                    .join(" — ");

                row.livestock.add(new Option(label, String(animal.id)));
            }

            if (
                [...row.livestock.options].some(
                    (option) => option.value === previous,
                )
            ) {
                row.livestock.value = previous;
            }
        }
    }

    async function loadLivestock() {
        console.log("[Transactions] Requesting farmer livestock...");

        try {
            const response = await api.get("/farmer/livestock", {
                params: {
                    status: "available",
                },
            });

            console.log("[Transactions] Livestock response:", response.data);

            state.livestock = collection(unwrap(response));

            refreshLivestockOptions();

            if (state.livestock.length === 0) {
                showAlert(
                    "No available livestock found. Please register an available livestock record first.",
                );
            }

            console.log(
                `[Transactions] Loaded ${state.livestock.length} livestock records`,
            );
        } catch (error) {
            console.error("[Transactions] Livestock request failed:", error);

            showAlert(`Unable to load livestock: ${getErrorMessage(error)}`);
        }
    }

    // --------------------------------------------------
    // Valuations
    // --------------------------------------------------

    async function loadValuations(row) {
        const livestockId = row.livestock.value;

        row.requestVersion += 1;
        const version = row.requestVersion;

        row.valuation.disabled = true;
        row.valuation.replaceChildren(new Option("Select livestock first", ""));

        if (!livestockId) return;

        row.valuation.replaceChildren(new Option("Loading valuations...", ""));

        try {
            const response = await api.get(
                `/farmer/livestock/${encodeURIComponent(livestockId)}/valuations`,
                {
                    params: { page: 1 },
                },
            );

            if (
                version !== row.requestVersion ||
                row.livestock.value !== livestockId
            ) {
                return;
            }

            const valuations = collection(unwrap(response));

            row.valuation.replaceChildren(
                new Option("Select FPC valuation", ""),
            );

            for (const valuation of valuations) {
                const amount = money.format(Number(valuation.estimated_value));

                const date = valuation.created_at
                    ? new Date(valuation.created_at).toLocaleDateString("en-PH")
                    : "";

                row.valuation.add(
                    new Option(
                        `${amount}${date ? ` — ${date}` : ""}`,
                        valuation.id,
                    ),
                );
            }

            if (valuations.length === 0) {
                row.valuation.replaceChildren(
                    new Option("No saved valuation found", ""),
                );
            } else {
                row.valuation.disabled = false;
            }
        } catch (error) {
            if (version !== row.requestVersion) return;

            console.error("[Transactions] Valuation request failed:", error);

            row.valuation.replaceChildren(
                new Option("Unable to load valuations", ""),
            );

            showAlert(getErrorMessage(error));
        }
    }

    // --------------------------------------------------
    // Transaction item rows
    // --------------------------------------------------

    function addRow() {
        if (
            state.busy ||
            state.draftId ||
            state.uncertain ||
            state.rows.size >= 50
        ) {
            return;
        }

        const id = ++state.nextRowId;

        const element = document.createElement("div");
        element.className = "sale-item";

        element.innerHTML = `
            <div class="sale-item-header">
                <strong>Livestock Item</strong>
                <button type="button" class="sale-remove">
                    Remove
                </button>
            </div>

            <div class="sale-grid">
                <label>
                    Registered livestock
                    <select class="sale-livestock" required>
                        <option value="">Select livestock</option>
                    </select>
                </label>

                <label>
                    Saved FPC valuation
                    <select class="sale-valuation" required disabled>
                        <option value="">Select livestock first</option>
                    </select>
                </label>

                <label>
                    Actual selling price (PHP)
                    <input
                        class="sale-price"
                        type="number"
                        min="0.01"
                        max="999999999999.99"
                        step="0.01"
                        placeholder="0.00"
                        required
                    >
                </label>
            </div>
        `;

        const row = {
            element,
            livestock: element.querySelector(".sale-livestock"),
            valuation: element.querySelector(".sale-valuation"),
            price: element.querySelector(".sale-price"),
            remove: element.querySelector(".sale-remove"),
            requestVersion: 0,
        };

        state.rows.set(id, row);
        itemsContainer.appendChild(element);

        row.livestock.addEventListener("change", () => {
            refreshLivestockOptions();
            loadValuations(row);
        });

        row.price.addEventListener("input", updateTotal);

        row.remove.addEventListener("click", () => {
            if (state.busy || state.draftId || state.uncertain) return;

            state.rows.delete(id);
            element.remove();

            refreshLivestockOptions();
            updateTotal();
        });

        refreshLivestockOptions();
        updateTotal();
    }

    // --------------------------------------------------
    // Payload
    // --------------------------------------------------

    function buildPayload() {
        const data = new FormData(form);

        const items = [...state.rows.values()].map((row) => ({
            livestock_id: row.livestock.value,
            valuation_id: row.valuation.value,
            actual_selling_price: Number(row.price.value),
        }));

        if (items.length === 0) {
            throw new Error("Add at least one livestock item.");
        }

        if (
            items.some(
                (item) =>
                    !item.livestock_id ||
                    !item.valuation_id ||
                    !Number.isFinite(item.actual_selling_price) ||
                    item.actual_selling_price <= 0,
            )
        ) {
            throw new Error(
                "Complete the livestock, valuation, and selling price fields.",
            );
        }

        return {
            buyer_name: String(data.get("buyer_name") || "").trim(),
            buyer_phone: String(data.get("buyer_phone") || "").trim(),
            transaction_date: String(data.get("transaction_date") || ""),
            remarks: String(data.get("remarks") || "").trim() || null,
            items,
        };
    }

    // --------------------------------------------------
    // Save and submit
    // --------------------------------------------------

    async function createDraft() {
        if (state.draftId) return state.draftId;

        const payload = buildPayload();

        console.log("[Transactions] Creating draft...");

        const response = await api.post("/farmer/transactions", payload);

        const transaction = unwrap(response);

        if (!transaction?.id) {
            state.uncertain = true;

            throw new Error(
                "The server did not return a transaction ID. Check the database before retrying.",
            );
        }

        state.draftId = transaction.id;

        console.log("[Transactions] Draft created:", state.draftId);

        lockForm();

        return state.draftId;
    }

    async function submitDraft() {
        if (!state.draftId) {
            throw new Error("Save the transaction draft first.");
        }

        console.log("[Transactions] Submitting draft:", state.draftId);

        await api.post(
            `/farmer/transactions/${encodeURIComponent(state.draftId)}/submit`,
        );

        state.submitted = true;

        console.log("[Transactions] Submission successful");
    }

    async function runAction(shouldSubmit) {
        if (state.busy || state.submitted || state.uncertain) return;

        clearAlert();

        if (!state.draftId && !form.reportValidity()) return;

        setBusy(true);

        let operation = "draft";

        try {
            if (!state.draftId) {
                await createDraft();
            }

            if (shouldSubmit) {
                operation = "submit";

                await submitDraft();

                showAlert(
                    "Transaction submitted successfully. Buyer confirmation SMS has been queued.",
                    "success",
                );

                submitButton.textContent = "Submitted";
            } else {
                showAlert(
                    "Draft saved successfully. You can now submit it for buyer confirmation.",
                    "success",
                );
            }
        } catch (error) {
            console.error("[Transactions] Action failed:", error);

            // Network errors may occur after the server has
            // already committed the transaction.
            if (!error?.response) {
                state.uncertain = true;

                showAlert(
                    operation === "submit"
                        ? "Submission result is uncertain. Check the transaction status before retrying."
                        : "Draft creation result is uncertain. Check existing transactions before retrying.",
                );
            } else {
                showAlert(getErrorMessage(error));
            }
        } finally {
            setBusy(false);

            if (state.draftId) {
                saveButton.disabled = true;
                addButton.disabled = true;
            }

            if (state.submitted || state.uncertain) {
                submitButton.disabled = true;
            }
        }
    }

    // --------------------------------------------------
    // Events
    // --------------------------------------------------

    addButton.addEventListener("click", addRow);

    saveButton.addEventListener("click", () => {
        runAction(false);
    });

    form.addEventListener("submit", (event) => {
        event.preventDefault();
        runAction(true);
    });

    // --------------------------------------------------
    // Initialization
    // --------------------------------------------------

    async function initialize() {
        console.log("[Transactions] Initializing page...");

        const dateInput = form.querySelector('[name="transaction_date"]');

        if (dateInput && !dateInput.value) {
            const now = new Date();
            const localDate = new Date(
                now.getTime() - now.getTimezoneOffset() * 60000,
            );

            dateInput.value = localDate.toISOString().slice(0, 10);
        }

        // Render immediately, regardless of API success.
        addRow();

        // Fetch livestock independently.
        await loadLivestock();

        console.log("[Transactions] Initialization complete");
    }

    initialize();
}

// Wait until the HTML is available.
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initializeTransactionPage, {
        once: true,
    });
} else {
    initializeTransactionPage();
}
