import L from "leaflet";
import "leaflet/dist/leaflet.css";

document.addEventListener("DOMContentLoaded", async () => {
    const mapElement = document.getElementById("map");
    if (!mapElement) return;

    const map = L.map(mapElement).setView([10.3157, 123.8854], 11);

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; OpenStreetMap contributors",
        maxZoom: 19,
    }).addTo(map);

    // Cebu City UUID from our previous API test
    const municipalityId = "479175dd-47a9-46d4-b23a-ca5ad8e62002";

    try {
        const response = await fetch(
            `/api/gis/geojson?municipality_id=${municipalityId}`,
            {
                headers: {
                    Accept: "application/json",
                },
                credentials: "same-origin",
            },
        );

        if (!response.ok) {
            throw new Error(`GIS API returned HTTP ${response.status}`);
        }

        const geojson = await response.json();

        // Support both direct GeoJSON and Laravel's { data: ... } wrapper
        const collection =
            geojson.type === "FeatureCollection" ? geojson : geojson.data;

        if (collection?.type !== "FeatureCollection") {
            throw new Error("Invalid GeoJSON FeatureCollection response");
        }

        const boundaries = L.geoJSON(collection, {
            style: {
                color: "#15803d",
                weight: 2,
                fillColor: "#22c55e",
                fillOpacity: 0.25,
            },

            onEachFeature(feature, layer) {
                const properties = feature.properties ?? {};

                // Escape untrusted text before inserting into HTML
                const escapeHtml = (value) =>
                    String(value ?? "")
                        .replaceAll("&", "&amp;")
                        .replaceAll("<", "&lt;")
                        .replaceAll(">", "&gt;")
                        .replaceAll('"', "&quot;")
                        .replaceAll("'", "&#039;");

                layer.bindPopup(`
                    <div style="min-width:180px">
                        <strong>${escapeHtml(properties.name ?? properties.barangay_name ?? "Barangay")}</strong>
                        <p>PSGC: ${escapeHtml(properties.psgc_code ?? "N/A")}</p>
                        <p>Livestock: ${escapeHtml(properties.total_livestock ?? 0)}</p>
                    </div>
                `);
            },
        }).addTo(map);

        if (boundaries.getLayers().length > 0) {
            map.fitBounds(boundaries.getBounds(), {
                padding: [20, 20],
            });
        }

        console.log("AgriTimbang GIS boundaries loaded successfully!");
    } catch (error) {
        console.error("GIS loading failed:", error);
    }
});
