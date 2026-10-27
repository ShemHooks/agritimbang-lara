import api from "../api";

const dashboard = document.querySelector("#dashboard-content");
const loading = document.querySelector("#loading-message");
const userName = document.querySelector("#user-name");
const logoutButton = document.querySelector("#logout-button");

async function initializeDashboard() {
    const token = sessionStorage.getItem("agritimbang_token");

    if (!token) {
        window.location.replace("/login");
        return;
    }

    try {
        const response = await api.get("/auth/me");

        if (!response.data.success) {
            throw new Error("Authentication failed.");
        }

        const user = response.data.data;

        // Your users table stores first_name and last_name,
        // not a single name column.
        userName.textContent = [user.first_name, user.last_name]
            .filter(Boolean)
            .join(" ");

        loading.hidden = true;
        dashboard.hidden = false;
    } catch (error) {
        console.error("Authentication failed:", error);

        sessionStorage.removeItem("agritimbang_token");
        window.location.replace("/login");
    }
}

logoutButton.addEventListener("click", async () => {
    logoutButton.disabled = true;

    try {
        await api.post("/auth/logout");
    } catch (error) {
        console.error("Logout request failed:", error);
    } finally {
        sessionStorage.removeItem("agritimbang_token");
        window.location.replace("/login");
    }
});

initializeDashboard();
