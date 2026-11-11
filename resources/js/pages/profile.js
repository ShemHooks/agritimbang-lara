import api from "../api";

const root = document.getElementById("profile-settings");

if (root) {
    const $ = (selector) => root.querySelector(selector);

    let user = null;
    let busy = false;

    function escapeHtml(value) {
        return String(value ?? "").replace(
            /[&<>"']/g,
            (char) =>
                ({
                    "&": "&amp;",
                    "<": "&lt;",
                    ">": "&gt;",
                    '"': "&quot;",
                    "'": "&#39;",
                })[char],
        );
    }

    function unwrap(response) {
        return response.data?.data ?? response.data;
    }

    function errorMessage(error) {
        const data = error.response?.data;
        const first = Object.values(data?.errors ?? {})[0];

        return (
            (Array.isArray(first) ? first[0] : first) ||
            data?.message ||
            error.message ||
            "Something went wrong."
        );
    }

    function alertMessage(message, type = "success") {
        const element = $("#profile-alert");
        element.textContent = message;
        element.dataset.type = type;
        element.hidden = false;
    }

    function fullName(value) {
        return (
            [value?.first_name, value?.middle_name, value?.last_name]
                .filter(Boolean)
                .join(" ") || "User"
        );
    }

    function formatRole(slug) {
        return (
            {
                admin: "System Administrator",
                lgu_authority: "LGU Authority",
                lgu_encoder: "LGU Encoder",
                farmer: "Farmer",
            }[slug] ??
            slug ??
            "Unassigned"
        );
    }

    function detail(label, value) {
        return `
            <div class="profile-detail">
                <span>${escapeHtml(label)}</span>
                <strong>${escapeHtml(value ?? "—")}</strong>
            </div>
        `;
    }

    function renderAccount() {
        const farmer = user.farmer_profile;
        const officer = user.lgu_officer_profile;

        const roles = (user.roles ?? []).map((role) => formatRole(role.slug));

        let html = [
            detail("Assigned Role", roles.join(", ") || "Unassigned"),
            detail("Account Status", user.status),
            detail("Verification Status", user.verification_status),
            detail(
                "Email Verification",
                user.email_verified_at ? "Verified" : "Not verified",
            ),
        ].join("");

        if (farmer) {
            html += [
                detail("Farmer Code", farmer.farmer_code),
                detail("Municipality", farmer.municipality?.name),
                detail("Barangay", farmer.barangay?.name),
                detail("Address", farmer.address),
            ].join("");
        }

        if (officer) {
            html += [
                detail("Assigned Municipality", officer.municipality?.name),
                detail("Station", officer.station),
                detail("Designation", officer.designation),
            ].join("");
        }

        $("#profile-account-details").innerHTML = html;
    }

    function renderProfile() {
        const form = $("#profile-personal-form");

        for (const name of [
            "first_name",
            "middle_name",
            "last_name",
            "email",
            "phone_number",
        ]) {
            form.elements[name].value = user[name] ?? "";
        }

        const name = fullName(user);

        $("#profile-display-name").textContent = name;
        $("#profile-display-email").textContent = user.email ?? "—";

        $("#profile-avatar").textContent =
            [user.first_name?.[0], user.last_name?.[0]]
                .filter(Boolean)
                .join("")
                .toUpperCase() || "?";

        $("#profile-role").textContent =
            (user.roles ?? [])
                .map((role) => formatRole(role.slug))
                .join(", ") || "Unassigned";

        $("#profile-account-status").textContent = user.status ?? "Unknown";

        renderAccount();
    }

    async function loadProfile() {
        try {
            const response = await api.get("/profile");
            user = unwrap(response);
            renderProfile();
        } catch (error) {
            alertMessage(errorMessage(error), "error");
        }
    }

    function setBusy(value) {
        busy = value;

        $("#profile-save").disabled = value;
        $("#profile-change-password").disabled = value;
    }

    async function saveProfile(event) {
        event.preventDefault();
        if (busy) return;

        const form = event.currentTarget;

        const payload = {
            first_name: form.elements.first_name.value.trim(),
            middle_name: form.elements.middle_name.value.trim() || null,
            last_name: form.elements.last_name.value.trim(),
            email: form.elements.email.value.trim(),
            phone_number: form.elements.phone_number.value.trim() || null,
        };

        setBusy(true);

        try {
            const response = await api.patch("/profile", payload);
            user = unwrap(response);
            renderProfile();
            alertMessage("Profile updated successfully.");
        } catch (error) {
            alertMessage(errorMessage(error), "error");
        } finally {
            setBusy(false);
        }
    }

    async function changePassword(event) {
        event.preventDefault();
        if (busy) return;

        const form = event.currentTarget;

        const password = form.elements.password.value;
        const confirmation = form.elements.password_confirmation.value;

        if (password !== confirmation) {
            alertMessage("New passwords do not match.", "error");
            return;
        }

        const payload = {
            current_password: form.elements.current_password.value,
            password,
            password_confirmation: confirmation,
        };

        setBusy(true);

        try {
            await api.put("/profile/password", payload);

            form.reset();
            alertMessage("Password updated successfully.");
        } catch (error) {
            alertMessage(errorMessage(error), "error");
        } finally {
            setBusy(false);
        }
    }

    root.querySelectorAll("[data-profile-tab]").forEach((button) => {
        button.addEventListener("click", () => {
            const selected = button.dataset.profileTab;

            root.querySelectorAll("[data-profile-tab]").forEach((tab) => {
                tab.classList.toggle(
                    "active",
                    tab.dataset.profileTab === selected,
                );
            });

            root.querySelectorAll("[data-profile-panel]").forEach((panel) => {
                panel.hidden = panel.dataset.profilePanel !== selected;
            });

            $("#profile-alert").hidden = true;
        });
    });

    $("#profile-personal-form").addEventListener("submit", saveProfile);
    $("#profile-password-form").addEventListener("submit", changePassword);

    loadProfile();
}
