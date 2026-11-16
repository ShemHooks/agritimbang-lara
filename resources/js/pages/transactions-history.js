import api from "../api";

// The same Vite entry is shared by Farmer Transaction History and staff
// (Encoder, Authority, Admin) history pages
if (document.getElementById("lgu-history-page")) {
    const $ = (id) => document.getElementById(id);
    const root = $("lgu-history-page");

    if (root) {
        const endpoint =
            root.dataset.transactionHistoryEndpoint ||
            "/lgu/encoder/transactions/history";

        const isAuthorityHistory = endpoint === "/lgu/transactions/history";
        const isAdminHistory = endpoint === "/admin/transactions/history";
        const isEncoderHistory = endpoint === "/lgu/encoder/transactions/history";

        let page = 1;
        let lastPage = 1;
        let total = 0;
        let activeFilters = {};

        const peso = (value) =>
            new Intl.NumberFormat("en-PH", {
                style: "currency",
                currency: "PHP",
            }).format(Number(value || 0));

        const nameOf = (user) =>
            [
                user?.first_name,
                user?.middle_name,
                user?.last_name,
            ].filter(Boolean).join(" ") ||
            user?.name ||
            "—";

        const safe = (value, fallback = "—") =>
            value === null || value === undefined || value === ""
                ? fallback
                : String(value);

        const label = (value) =>
            safe(value, "Unknown")
                .replaceAll("_", " ")
                .replace(/\b\w/g, (c) => c.toUpperCase());

        const date = (value) =>
            value
                ? new Date(value).toLocaleDateString("en-PH", {
                    year: "numeric",
                    month: "short",
                    day: "numeric",
                })
                : "—";

        function cell(row, value) {
            const td = document.createElement("td");
            td.textContent = safe(value);
            row.append(td);
            return td;
        }

        function setMessage(message, show = true) {
            const node = $("history-message");
            node.textContent = message;
            node.hidden = !show;
        }

        async function loadSpecies() {
            try {
                const response = await api.get(endpoint, {
                    params: { page: 1 },
                });

                const species = response.data?.species || [];
                const select = $("history-species");

                species.forEach((item) => {
                    const option = document.createElement("option");
                    option.value = item.id;
                    option.textContent = item.name;
                    select.append(option);
                });
            } catch (_) {
                /* The history request will show the actionable error. */
            }
        }

        async function load() {
            setMessage("Loading transaction history…");

            const params = { ...activeFilters, page };

            if (isEncoderHistory) {
                if (params.date_from) params.from = params.date_from;
                if (params.date_to) params.to = params.date_to;

                delete params.date_from;
                delete params.date_to;
            }

            try {
                const response = await api.get(endpoint, { params });
                const payload = response.data?.data ?? response.data;
                const rows = payload.data || [];

                total = payload.total || 0;
                page = payload.current_page || 1;
                lastPage = payload.last_page || 1;

                const body = $("history-table-body");
                body.replaceChildren();

                rows.forEach((tx) => {
                    const tr = document.createElement("tr");
                    const shortId = String(tx.id || "")
                        .slice(0, 8)
                        .toUpperCase();

                    const farmer = nameOf(tx.farmer?.user);

                    const transactionCell = cell(tr, `#${shortId}`);
                    transactionCell.classList.add("lgu-history-id");

                    cell(tr, date(tx.transaction_date));
                    cell(tr, farmer);
                    cell(tr, tx.items?.length || 0);
                    cell(tr, peso(tx.total_selling_price));

                    const statusCell = cell(tr, label(tx.status));
                    statusCell.innerHTML = "";

                    const badge = document.createElement("span");
                    badge.className = `lgu-history-status status-${String(
                        tx.status || "unknown"
                    ).toLowerCase()}`;
                    badge.textContent = label(tx.status);

                    statusCell.append(badge);

                    const actionCell = document.createElement("td");
                    const view = document.createElement("button");

                    view.type = "button";
                    view.className = "tx-btn tx-btn-view";
                    view.textContent = "View details";
                    view.addEventListener("click", () => showDetail(tx));

                    actionCell.append(view);
                    tr.append(actionCell);
                    body.append(tr);
                });

                if (!rows.length) {
                    const tr = document.createElement("tr");
                    const td = document.createElement("td");

                    td.colSpan = 7;
                    td.textContent = "No transactions match your filters.";

                    tr.append(td);
                    body.append(tr);
                }

                $("history-count").textContent =
                    `${total.toLocaleString()} transaction${total === 1 ? "" : "s"}`;

                $("history-page-info").textContent =
                    `Page ${page} of ${lastPage}`;

                $("history-page-label").textContent =
                    `Page ${page} of ${lastPage}`;

                $("history-prev").disabled = page <= 1;
                $("history-next").disabled = page >= lastPage;

                setMessage("", false);
            } catch (error) {
                const message =
                    error.response?.data?.message ||
                    "Unable to load transaction history.";

                const body = $("history-table-body");
                body.replaceChildren();

                const row = document.createElement("tr");
                const cellNode = document.createElement("td");

                cellNode.colSpan = 7;
                cellNode.textContent = message;

                row.append(cellNode);
                body.append(row);

                $("history-count").textContent = "0 transactions";
                $("history-page-info").textContent = "Page 1";
                $("history-page-label").textContent = "Page 1";

                $("history-prev").disabled = true;
                $("history-next").disabled = true;

                setMessage(message);
            }
        }

        function detailField(container, title, value) {
            const item = document.createElement("div");
            item.className = "lgu-history-detail-field";

            const term = document.createElement("span");
            term.textContent = title;

            const valueNode = document.createElement("strong");
            valueNode.textContent = safe(value);

            item.append(term, valueNode);
            container.append(item);
        }

        function showDetail(tx) {
            const content = $("detail-content");
            content.replaceChildren();

            const summary = document.createElement("div");
            summary.className = "lgu-history-detail-grid";

            const farmer = tx.farmer?.user;

            [
                ["Transaction ID", tx.id],
                ["Date", date(tx.transaction_date)],
                ["Farmer", nameOf(farmer)],
                ["Farmer contact", farmer?.phone_number || farmer?.phone],
                ["Buyer", tx.buyer_name],
                ["Buyer phone", tx.buyer_phone],
                ["Municipality", tx.municipality?.name],
                ["Barangay", tx.barangay?.name],
                ["Status", label(tx.status)],
                ["Total selling price", peso(tx.total_selling_price)],
                ["Encoded by", nameOf(tx.encoder)],
                ["Verified by", nameOf(tx.verifier)],
            ].forEach(([title, value]) => {
                detailField(summary, title, value);
            });

            content.append(summary);

            const heading = document.createElement("h3");
            heading.textContent = "Livestock items";
            content.append(heading);

            const wrap = document.createElement("div");
            wrap.className = "lgu-history-detail-table-wrap";

            const table = document.createElement("table");
            table.className = "lgu-history-detail-table";

            const thead = document.createElement("thead");
            const headRow = document.createElement("tr");

            [
                "Species",
                "Breed",
                "Weight",
                "Reference value",
                "Selling price",
            ].forEach((v) => {
                const th = document.createElement("th");
                th.textContent = v;
                headRow.append(th);
            });

            thead.append(headRow);
            table.append(thead);

            const tbody = document.createElement("tbody");

            (tx.items || []).forEach((item) => {
                const livestock = item.livestock || {};
                const tr = document.createElement("tr");

                [
                    livestock.species?.name || livestock.species_name,
                    livestock.breed?.name || livestock.breed_name,
                    item.weight_used_kg != null
                        ? `${item.weight_used_kg} kg`
                        : null,
                    peso(item.reference_value ?? item.valuation?.estimated_value),
                    peso(item.actual_selling_price ?? item.selling_price),
                ].forEach((v) => {
                    const td = document.createElement("td");
                    td.textContent = safe(v);
                    tr.append(td);
                });

                tbody.append(tr);
            });

            if (!tx.items?.length) {
                const tr = document.createElement("tr");
                const td = document.createElement("td");

                td.colSpan = 5;
                td.textContent = "No livestock items found.";

                tr.append(td);
                tbody.append(tr);
            }

            table.append(tbody);
            wrap.append(table);
            content.append(wrap);

            $("detail-title").textContent =
                `Transaction #${String(tx.id || "").slice(0, 8).toUpperCase()}`;

            $("detail-subtitle").textContent =
                `${nameOf(farmer)} · ${date(tx.transaction_date)}`;

            const dialog = $("history-detail-dialog");

            if (!dialog.open) {
                dialog.showModal();
            }
        }

        $("lgu-history-filters").addEventListener("submit", (event) => {
            event.preventDefault();

            activeFilters = {
                search: $("history-search").value.trim(),
                status: $("history-status").value,
                species: $("history-species").value,
                purpose: $("history-purpose").value,
                date_from: $("history-date-from").value,
                date_to: $("history-date-to").value,
            };

            Object.keys(activeFilters).forEach((key) => {
                if (!activeFilters[key]) {
                    delete activeFilters[key];
                }
            });

            page = 1;
            load();
        });

        $("history-reset").addEventListener("click", () => {
            $("lgu-history-filters").reset();
            activeFilters = {};
            page = 1;
            load();
        });

        $("history-prev").addEventListener("click", () => {
            if (page > 1) {
                page--;
                load();
            }
        });

        $("history-next").addEventListener("click", () => {
            if (page < lastPage) {
                page++;
                load();
            }
        });

        $("detail-close").addEventListener("click", () => {
            $("history-detail-dialog").close();
        });

        $("history-detail-dialog").addEventListener("click", (event) => {
            if (event.target === $("history-detail-dialog")) {
                $("history-detail-dialog").close();
            }
        });

        if (isAuthorityHistory || isAdminHistory) {
            const heading = root.querySelector("h1");
            const description = root.querySelector(".transaction-header p");
            const assistLink = root.querySelector(".transaction-header a");

            if (heading) {
                heading.textContent = isAdminHistory
                    ? "All Transaction History"
                    : "Transaction History";
            }

            if (description) {
                description.textContent =
                    "View all farmer and LGU transaction records within your assigned municipality, including their verification history.";
            }

            if (assistLink) {
                assistLink.hidden = true;
            }
        }

        loadSpecies().then(load);
    }
} else {
    const $ = (id) => document.getElementById(id);

    const peso = (value) =>
        new Intl.NumberFormat("en-PH", {
            style: "currency",
            currency: "PHP",
        }).format(Number(value || 0));

    let page = 1;
    let records = [];

    function unwrap(response) {
        return response.data?.data ?? response.data;
    }

    function statusLabel(value) {
        return String(value || "unknown").replaceAll("_", " ");
    }

    async function load() {
        $("tx-history-feedback").textContent = "Loading transactions...";

        try {
            const response = await api.get("/farmer/transactions", {
                params: { page },
            });

            const data = unwrap(response);
            records = data.data || [];

            const body = $("tx-history-body");
            body.replaceChildren();

            records.forEach((tx) => {
                const row = document.createElement("tr");

                const values = [
                    tx.transaction_date
                        ? new Date(tx.transaction_date).toLocaleDateString("en-PH")
                        : "—",
                    tx.buyer_name || "—",
                    (tx.items || [])
                        .map(
                            (item) =>
                                item.livestock?.species?.name ||
                                item.species?.name ||
                                "Livestock"
                        )
                        .join(", ") || "—",
                    peso(tx.total_selling_price),
                    statusLabel(tx.status),
                ];

                values.forEach((value) => {
                    const cell = document.createElement("td");
                    cell.textContent = value;
                    row.append(cell);
                });

                const action = document.createElement("td");
                const actionGroup = document.createElement("div");
                actionGroup.className = "tx-action-group";

                const view = document.createElement("button");
                view.type = "button";
                view.textContent = "View";
                view.className = "tx-btn tx-btn-view";
                view.addEventListener("click", () => showDetail(tx));

                actionGroup.append(view);

                if (
                    String(tx.status).toLowerCase() === "draft" &&
                    String(tx.source || "farmer").toLowerCase() === "farmer"
                ) {
                    const edit = document.createElement("a");
                    edit.href = `/transactions/create?draft=${encodeURIComponent(tx.id)}`;
                    edit.textContent = "Edit";
                    edit.className = "tx-btn tx-btn-edit";

                    actionGroup.append(edit);
                }

                action.append(actionGroup);
                row.append(action);
                body.append(row);
            });

            $("tx-history-feedback").textContent = records.length
                ? ""
                : "Wala pay transaction record. Select “Record Transaction” to start.";

            page = data.current_page || 1;

            $("tx-history-page-label").textContent =
                `Page ${page} of ${data.last_page || 1}`;

            $("tx-history-prev").disabled = page <= 1;
            $("tx-history-next").disabled = page >= (data.last_page || 1);
        } catch (error) {
            $("tx-history-feedback").textContent =
                error.response?.data?.message ||
                "Unable to load transaction history.";
        }
    }

    async function showDetail(tx) {
        const panel = $("tx-history-detail");
        const summary = $("tx-detail-summary");
        const itemsBody = $("tx-detail-items");
        const total = $("tx-detail-total");

        if (!panel || !summary || !itemsBody || !total) {
            return;
        }

        panel.hidden = false;
        summary.textContent = "Loading transaction details…";
        itemsBody.replaceChildren();
        total.textContent = peso(tx.total_selling_price);

        try {
            const response = await api.get(
                `/farmer/transactions/${encodeURIComponent(tx.id)}`
            );

            const record = unwrap(response);

            summary.textContent = [
                `Buyer: ${record.buyer_name || "—"}`,
                `Date: ${
                    record.transaction_date
                        ? new Date(record.transaction_date).toLocaleDateString("en-PH")
                        : "—"
                }`,
                `Status: ${statusLabel(record.status)}`,
                `Buyer contact: ${record.buyer_phone || "—"}`,
            ].join(" · ");

            (record.items || []).forEach((item) => {
                const row = document.createElement("tr");
                const livestock = item.livestock || {};

                const values = [
                    livestock.species?.name ||
                        item.species?.name ||
                        "Livestock",
                    livestock.breed?.name || "—",
                    item.weight_used_kg ??
                        livestock.actual_weight_kg ??
                        "—",
                    peso(item.reference_value ?? item.valuation?.estimated_value),
                    peso(item.final_price_per_kg ?? item.base_price_per_kg),
                    peso(item.actual_selling_price),
                ];

                values.forEach((value) => {
                    const cell = document.createElement("td");
                    cell.textContent = String(value);
                    row.append(cell);
                });

                itemsBody.append(row);
            });

            if (!record.items?.length) {
                const row = document.createElement("tr");
                const cell = document.createElement("td");

                cell.colSpan = 6;
                cell.textContent = "No livestock items recorded.";

                row.append(cell);
                itemsBody.append(row);
            }

            total.textContent = peso(record.total_selling_price);
        } catch (error) {
            summary.textContent =
                error.response?.data?.message ||
                "Unable to load transaction details.";
        }
    }

    $("tx-history-refresh")?.addEventListener("click", () => {
        page = 1;
        load();
    });

    $("tx-history-prev")?.addEventListener("click", () => {
        if (page > 1) {
            page--;
            load();
        }
    });

    $("tx-history-next")?.addEventListener("click", () => {
        page++;
        load();
    });

    load();

    $("tx-history-detail")
        ?.querySelectorAll("[data-tx-detail-close]")
        .forEach((button) => {
            button.addEventListener("click", () => {
                $("tx-history-detail").hidden = true;
            });
        });
}