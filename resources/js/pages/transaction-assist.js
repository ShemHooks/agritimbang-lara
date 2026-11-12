import api from "../api";

const $ = (id) => document.getElementById(id);

const page = $("transaction-assist-page");

if (page) {
    const state = {
        farmer: null,
        livestock: [],
        selected: new Map(),
        draft: null,
        loading: false,
        searchRequest: 0,
        livestockRequest: 0,
    };

    const money = (value) =>
        new Intl.NumberFormat("en-PH", {
            style: "currency",
            currency: "PHP",
        }).format(Number(value) || 0);

    const unwrap = (response) => response.data?.data ?? {};

    const text = (value) => String(value ?? "");

    function el(tag, className, content) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (content !== undefined) node.textContent = text(content);
        return node;
    }

    function notice(message, error = false) {
        const target = $("tx-notice");
        target.hidden = false;
        target.textContent = message;
        target.className = `tx-notice ${error ? "tx-error" : "tx-success"}`;
    }

    function errorMessage(error) {
        const errors = error.response?.data?.errors;
        const first = errors && Object.values(errors).flat()[0];

        return (
            first ||
            error.response?.data?.message ||
            error.message ||
            "Something went wrong."
        );
    }

    function farmerName(farmer) {
        const user = farmer.user ?? {};

        return (
            [user.first_name, user.last_name].filter(Boolean).join(" ") ||
            farmer.farmer_code ||
            "Farmer"
        );
    }

    function latestValuation(livestock) {
        const valuations = livestock.valuations ?? [];

        return (
            valuations.find(
                (valuation) =>
                    Number(valuation.estimated_value) > 0 &&
                    Number(valuation.weight_used_kg) > 0,
            ) ?? null
        );
    }

    function setDraftLocked(locked) {
        $("tx-farmer-search").disabled = locked;
        $("tx-form")
            .querySelectorAll("input, textarea, button")
            .forEach((node) => {
                node.disabled = locked;
            });

        document.querySelectorAll(".tx-livestock-check").forEach((node) => {
            node.disabled = locked;
        });
    }

    function renderFarmers(farmers) {
        const container = $("tx-farmer-results");
        container.replaceChildren();

        if (!farmers.length) {
            container.append(el("p", "tx-muted", "No verified farmers found."));
            return;
        }

        farmers.forEach((farmer) => {
            const button = el("button", "tx-farmer-option");
            button.type = "button";

            button.append(
                el("strong", "", farmerName(farmer)),
                el("span", "", farmer.farmer_code || "No farmer code"),
            );

            button.addEventListener("click", () => selectFarmer(farmer));

            container.append(button);
        });
    }

    async function searchFarmers() {
        const requestId = ++state.searchRequest;
        const search = $("tx-farmer-search").value.trim();

        try {
            const response = await api.get(
                "/lgu/transactions/encoding/farmers",
                { params: { search, per_page: 20 } },
            );

            if (requestId !== state.searchRequest) return;

            const payload = unwrap(response);
            renderFarmers(payload.data ?? []);
        } catch (error) {
            if (requestId !== state.searchRequest) return;
            notice(errorMessage(error), true);
        }
    }

    async function selectFarmer(farmer) {
        state.farmer = farmer;
        state.selected.clear();
        state.livestock = [];

        $("tx-selected-farmer").hidden = false;
        $("tx-selected-farmer").textContent =
            `${farmerName(farmer)} — ${farmer.farmer_code || "Registered farmer"}`;

        $("tx-farmer-results").replaceChildren();
        $("tx-farmer-search").value = farmerName(farmer);

        renderSelected();
        updateSummary();

        const requestId = ++state.livestockRequest;
        const container = $("tx-livestock-list");

        container.replaceChildren(el("p", "tx-muted", "Loading livestock..."));

        try {
            const response = await api.get(
                `/lgu/transactions/encoding/farmers/${farmer.id}/livestock`,
            );

            if (requestId !== state.livestockRequest) return;

            state.livestock = unwrap(response);
            renderLivestock();
        } catch (error) {
            if (requestId !== state.livestockRequest) return;
            container.replaceChildren();
            notice(errorMessage(error), true);
        }
    }

    function renderLivestock() {
        const container = $("tx-livestock-list");
        container.replaceChildren();

        if (!state.livestock.length) {
            container.append(
                el("p", "tx-muted", "No available livestock found."),
            );
            return;
        }

        state.livestock.forEach((livestock) => {
            const valuation = latestValuation(livestock);

            const card = el("label", "tx-livestock-card");
            const checkbox = document.createElement("input");

            checkbox.type = "checkbox";
            checkbox.className = "tx-livestock-check";
            checkbox.disabled = !valuation;

            const details = el("div", "tx-livestock-details");

            details.append(
                el(
                    "strong",
                    "",
                    `${livestock.species?.name || "Livestock"} — ${
                        livestock.breed?.name || "Unspecified breed"
                    }`,
                ),
                el(
                    "span",
                    "",
                    valuation
                        ? `Reference: ${money(valuation.estimated_value)} · Weight: ${valuation.weight_used_kg} kg`
                        : "No valid valuation available",
                ),
            );

            checkbox.addEventListener("change", () => {
                if (checkbox.checked) {
                    state.selected.set(livestock.id, {
                        livestock,
                        valuation,
                        sellingPrice: Number(valuation.estimated_value),
                    });
                } else {
                    state.selected.delete(livestock.id);
                }

                renderSelected();
                updateSummary();
            });

            card.append(checkbox, details);
            container.append(card);
        });
    }

    function renderSelected() {
        const container = $("tx-selected-items");
        container.replaceChildren();

        if (!state.selected.size) {
            container.append(el("p", "tx-muted", "No livestock selected."));
            return;
        }

        state.selected.forEach((item, id) => {
            const row = el("div", "tx-selected-row");
            const label = el(
                "span",
                "",
                `${item.livestock.species?.name || "Livestock"} · Reference ${money(item.valuation.estimated_value)}`,
            );

            const input = document.createElement("input");
            input.type = "number";
            input.min = "0.01";
            input.step = "0.01";
            input.required = true;
            input.value = item.sellingPrice;

            input.addEventListener("input", () => {
                const selected = state.selected.get(id);
                if (!selected) return;

                selected.sellingPrice = input.value;
                updateSummary();
            });

            row.append(label, input);
            container.append(row);
        });
    }

    function updateSummary() {
        let reference = 0;
        let selling = 0;

        state.selected.forEach((item) => {
            reference += Number(item.valuation.estimated_value) || 0;
            selling += Number(item.sellingPrice) || 0;
        });

        $("tx-count").textContent = state.selected.size;
        $("tx-reference").textContent = money(reference);
        $("tx-total").textContent = money(selling);
    }

    function collectPayload() {
        if (!state.farmer) {
            throw new Error("Please select a farmer.");
        }

        if (!state.selected.size) {
            throw new Error("Please select at least one livestock.");
        }

        const items = Array.from(state.selected.values()).map((item) => {
            const price = Number(item.sellingPrice);

            if (
                !Number.isFinite(price) ||
                price <= 0 ||
                !/^\d+(\.\d{1,2})?$/.test(String(item.sellingPrice))
            ) {
                throw new Error(
                    "Enter a valid selling price for each livestock.",
                );
            }

            return {
                livestock_id: item.livestock.id,
                valuation_id: item.valuation.id,
                actual_selling_price: price,
            };
        });

        return {
            farmer_id: state.farmer.id,
            buyer_name: $("tx-buyer-name").value.trim(),
            buyer_phone: $("tx-buyer-phone").value.trim(),
            transaction_date: $("tx-date").value,
            remarks: $("tx-remarks").value.trim() || null,
            items,
        };
    }

    async function saveDraft(event) {
        event.preventDefault();

        if (state.loading || state.draft) return;

        try {
            const payload = collectPayload();

            state.loading = true;
            $("tx-save").disabled = true;

            const response = await api.post(
                "/lgu/transactions/encoding",
                payload,
            );

            state.draft = unwrap(response);

            setDraftLocked(true);
            $("tx-draft-actions").hidden = false;
            $("tx-draft-message").textContent =
                "Draft saved. Review the information before submitting.";

            notice("Transaction draft saved successfully.");
        } catch (error) {
            notice(errorMessage(error), true);
        } finally {
            state.loading = false;

            if (!state.draft) {
                $("tx-save").disabled = false;
            }
        }
    }

    async function submitDraft() {
        if (!state.draft || state.loading) return;

        const confirmed = window.confirm(
            "Submit this transaction directly to the LGU Authority for verification?",
        );

        if (!confirmed) return;

        state.loading = true;
        $("tx-submit").disabled = true;

        try {
            await api.post(
                `/lgu/transactions/encoding/${state.draft.id}/submit`,
            );

            $("tx-draft-message").textContent =
                "Submitted successfully. Awaiting LGU Authority verification.";

            $("tx-submit").hidden = true;

            notice("Transaction submitted for LGU verification.");
        } catch (error) {
            notice(errorMessage(error), true);
            $("tx-submit").disabled = false;
        } finally {
            state.loading = false;
        }
    }

    let searchTimer;

    $("tx-farmer-search").addEventListener("input", () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(searchFarmers, 300);
    });

    $("tx-form").addEventListener("submit", saveDraft);
    $("tx-submit").addEventListener("click", submitDraft);

    $("tx-date").value = [
        new Date().getFullYear(),
        String(new Date().getMonth() + 1).padStart(2, "0"),
        String(new Date().getDate()).padStart(2, "0"),
    ].join("-");

    searchFarmers();
}
