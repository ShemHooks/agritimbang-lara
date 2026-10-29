import api from "../api";
import Chart from "chart.js/auto";

const $ = (id) => document.getElementById(id);
const form = $("forecast-form");
if (form) {
    const message = $("forecast-message");
    const results = $("forecast-results");
    const runButton = $("forecast-run");
    let chart = null;
    const peso = (value) =>
        new Intl.NumberFormat("en-PH", {
            style: "currency",
            currency: "PHP",
            minimumFractionDigits: 2,
        }).format(value);
    const dateLabel = (value) =>
        new Date(`${value}T00:00:00`).toLocaleDateString("en-PH", {
            month: "short",
            day: "numeric",
            year: "numeric",
        });
    const showMessage = (text, type = "info") => {
        message.textContent = text;
        message.dataset.type = type;
        message.hidden = false;
    };
    const apiError = (error) => {
        const data = error.response?.data;
        if (error.response?.status === 422) {
            const errors = data?.errors;
            if (errors && typeof errors === "object")
                return Object.values(errors).flat().join(" ");
        }
        if (error.response?.status === 403)
            return "You do not have permission to generate forecasts.";
        if (error.response?.status === 401)
            return "Your session has expired. Please sign in again.";
        return (
            data?.message ||
            "Unable to generate the forecast. Please try again."
        );
    };

    const unwrapCollection = (payload) => {
        if (Array.isArray(payload)) return payload;

        if (Array.isArray(payload?.data)) {
            return payload.data;
        }

        if (Array.isArray(payload?.data?.data)) {
            return payload.data.data;
        }

        if (Array.isArray(payload?.species)) {
            return payload.species;
        }

        if (Array.isArray(payload?.items)) {
            return payload.items;
        }

        console.warn("Unexpected reference response:", payload);

        return [];
    };

    const populateSelect = (id, rows, labelKey = "name") => {
        const select = $(id);
        for (const row of rows) {
            if (!row?.id || !row?.[labelKey]) continue;
            const option = document.createElement("option");
            option.value = row.id;
            option.textContent = row[labelKey];
            select.append(option);
        }
    };

    async function loadReferences() {
        const speciesSelect = $("forecast-species");
        const municipalitySelect = $("forecast-municipality");

        try {
            const response = await api.get("/reference/species");

            console.log("Species response:", response.data);

            const species = unwrapCollection(response.data);

            speciesSelect.innerHTML =
                '<option value="">Select species</option>';

            populateSelect("forecast-species", species);

            if (species.length === 0) {
                showMessage(
                    "No species were returned by the reference API.",
                    "error",
                );
            }
        } catch (error) {
            console.error("Species loading failed:", error);

            showMessage(
                "Unable to load species. Check the browser console.",
                "error",
            );
        }

        try {
            const response = await api.get("/reference/municipalities");

            console.log("Municipalities response:", response.data);

            const municipalities = unwrapCollection(response.data);

            populateSelect("forecast-municipality", municipalities);
        } catch (error) {
            console.error("Municipality loading failed:", error);
        }
    }

    function renderChart(historical, forecast) {
        const labels = [
            ...historical.map((p) => p.week),
            ...forecast.map((p) => p.week),
        ];
        const lastObserved = historical[historical.length - 1]?.price ?? null;
        const historicalSeries = [
            ...historical.map((p) => p.price),
            ...forecast.map(() => null),
        ];
        const forecastSeries = [
            ...historical.slice(0, -1).map(() => null),
            lastObserved,
            ...forecast.map((p) => p.predicted_price),
        ];
        const lowerSeries = [
            ...historical.map(() => null),
            ...forecast.map((p) => p.lower_95),
        ];
        const upperSeries = [
            ...historical.map(() => null),
            ...forecast.map((p) => p.upper_95),
        ];
        chart?.destroy();
        chart = new Chart($("forecast-chart"), {
            type: "line",
            data: {
                labels,
                datasets: [
                    {
                        label: "Historical",
                        data: historicalSeries,
                        borderColor: "#176b4d",
                        backgroundColor: "#176b4d",
                        borderWidth: 3,
                        pointRadius: 2,
                        tension: 0.2,
                        spanGaps: false,
                    },
                    {
                        label: "Forecast",
                        data: forecastSeries,
                        borderColor: "#e49b37",
                        backgroundColor: "#e49b37",
                        borderWidth: 3,
                        borderDash: [7, 5],
                        pointRadius: 3,
                        tension: 0.2,
                        spanGaps: false,
                    },
                    {
                        label: "Lower 95%",
                        data: lowerSeries,
                        borderColor: "rgba(228,155,55,0.35)",
                        pointRadius: 0,
                        borderWidth: 1,
                        tension: 0.2,
                    },
                    {
                        label: "Upper 95%",
                        data: upperSeries,
                        borderColor: "rgba(228,155,55,0.35)",
                        backgroundColor: "rgba(228,155,55,0.13)",
                        fill: "-1",
                        pointRadius: 0,
                        borderWidth: 1,
                        tension: 0.2,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: "index", intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: (items) => dateLabel(items[0].label),
                            label: (item) =>
                                `${item.dataset.label}: ${peso(item.parsed.y)}/kg`,
                        },
                    },
                },
                scales: {
                    x: {
                        ticks: {
                            maxTicksLimit: 9,
                            callback(value) {
                                return dateLabel(this.getLabelForValue(value));
                            },
                        },
                        grid: { display: false },
                    },
                    y: {
                        title: { display: true, text: "PHP / kg" },
                        ticks: { callback: (value) => `₱${value}` },
                    },
                },
            },
        });
    }
    function render(data) {
        const historical = data.historical || [];
        const forecast = data.forecast || [];
        if (!historical.length || !forecast.length)
            throw new Error(
                "The forecast response did not contain enough chart data.",
            );
        const latest = historical.at(-1);
        const final = forecast.at(-1);
        $("forecast-latest").textContent = peso(latest.price);
        $("forecast-latest-date").textContent =
            `Week of ${dateLabel(latest.week)}`;
        $("forecast-final").textContent = peso(final.predicted_price);
        $("forecast-final-date").textContent =
            `Week of ${dateLabel(final.week)}`;
        const change =
            ((final.predicted_price - latest.price) / latest.price) * 100;
        $("forecast-change").textContent =
            `${change > 0 ? "+" : ""}${change.toFixed(2)}%`;
        $("forecast-change").dataset.trend =
            change > 0 ? "up" : change < 0 ? "down" : "flat";
        $("forecast-count").textContent = `${historical.length} weeks`;
        const tbody = $("forecast-table-body");
        tbody.replaceChildren();
        for (const point of forecast) {
            const tr = document.createElement("tr");
            for (const value of [
                dateLabel(point.week),
                peso(point.predicted_price),
                peso(point.lower_95),
                peso(point.upper_95),
            ]) {
                const td = document.createElement("td");
                td.textContent = value;
                tr.append(td);
            }
            tbody.append(tr);
        }
        $("forecast-disclaimer").textContent =
            data.disclaimer ||
            "Statistical estimates only; not guaranteed market prices.";
        renderChart(historical, forecast);
        results.hidden = false;
        message.hidden = true;
    }
    form.addEventListener("submit", async (event) => {
        event.preventDefault();
        const speciesId = $("forecast-species").value;
        if (!speciesId) return showMessage("Please select a species.", "error");
        const params = {
            species_id: speciesId,
            weeks: Number($("forecast-weeks").value),
        };
        const municipalityId = $("forecast-municipality").value;
        if (municipalityId) params.municipality_id = municipalityId;
        const purpose = $("forecast-purpose").value;
        if (purpose) params.sale_purpose = purpose;
        runButton.disabled = true;
        runButton.textContent = "Generating…";
        results.hidden = true;
        showMessage(
            "Generating forecast from approved official weekly prices…",
        );
        try {
            const response = await api.get("/analytics/forecast", { params });
            render(response.data.data);
        } catch (error) {
            showMessage(
                error.message?.startsWith("The forecast response")
                    ? error.message
                    : apiError(error),
                "error",
            );
        } finally {
            runButton.disabled = false;
            runButton.textContent = "Generate forecast";
        }
    });
    loadReferences();
}
