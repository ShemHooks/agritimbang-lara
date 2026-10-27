import api from "../api";

const $ = (selector) => document.querySelector(selector);

const sections = [
    "#profile-section",
    "#document-section",
    "#pending-section",
    "#rejected-section",
    "#approved-section",
];

const loading = $("#verification-loading");
const errorBox = $("#verification-error");

const documentLabels = {
    national_id: "National ID",
    drivers_license: "Driver's License",
    passport: "Passport",
    voters_id: "Voter's ID",
    barangay_id: "Barangay ID",
    other: "Other",
};

function showSection(selector) {
    sections.forEach((id) => {
        $(id).hidden = id !== selector;
    });

    loading.hidden = true;
    errorBox.hidden = true;
}

function showError(message) {
    loading.hidden = true;
    errorBox.textContent = message;
    errorBox.hidden = false;
}

function getError(error) {
    const errors = error.response?.data?.errors;

    return (
        Object.values(errors ?? {}).flat()[0] ??
        error.response?.data?.message ??
        "Something went wrong. Please try again."
    );
}

function setBusy(form, busy) {
    const button = form.querySelector('button[type="submit"]');
    button.disabled = busy;

    if (!button.dataset.originalText) {
        button.dataset.originalText = button.textContent.trim();
    }

    button.textContent = busy ? "Please wait..." : button.dataset.originalText;
}

function formatDate(value) {
    if (!value) return "—";

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? "—"
        : date.toLocaleDateString("en-PH", {
              year: "numeric",
              month: "long",
              day: "numeric",
          });
}

function markSteps(profileComplete, documentSubmitted) {
    const email = $("#step-email");
    const profile = $("#step-profile");
    const document = $("#step-document");

    email.classList.add("completed");

    profile.classList.toggle("completed", profileComplete);
    document.classList.toggle("completed", documentSubmitted);

    [email, profile, document].forEach((step) => {
        step.classList.remove("current");
    });

    if (!profileComplete) {
        profile.classList.add("current");
    } else if (!documentSubmitted) {
        document.classList.add("current");
    }
}

async function loadVerificationStatus() {
    loading.hidden = false;
    errorBox.hidden = true;
    sections.forEach((id) => ($(id).hidden = true));

    if (!sessionStorage.getItem("agritimbang_token")) {
        window.location.replace("/login");
        return;
    }

    try {
        const meResponse = await api.get("/auth/me");
        const user = meResponse.data.data;

        if (!user.email_verified_at) {
            window.location.replace("/verify-email");
            return;
        }

        const roles = user.roles ?? [];
        const isFarmer = roles.some((role) => role.slug === "farmer");

        if (!isFarmer) {
            window.location.replace("/dashboard");
            return;
        }

        if (user.verification_status === "verified") {
            markSteps(true, true);
            showSection("#approved-section");
            return;
        }

        let profile = null;

        try {
            const profileResponse = await api.get("/farmer/profile");

            profile = profileResponse.data.data;
        } catch (error) {
            if (error.response?.status !== 404) {
                throw error;
            }
        }

        if (!profile?.municipality_id || !profile?.barangay_id) {
            await loadMunicipalities();

            markSteps(false, false);
            showSection("#profile-section");
            return;
        }

        const docsResponse = await api.get("/farmer/verification-documents");

        const documents = docsResponse.data.data ?? [];

        const latest = [...documents].sort(
            (a, b) => new Date(b.created_at) - new Date(a.created_at),
        )[0];

        markSteps(true, latest?.status === "approved");

        if (latest?.status === "pending") {
            $("#pending-document-type").textContent =
                documentLabels[latest.document_type] ??
                latest.document_type ??
                "—";

            $("#pending-submitted-at").textContent = formatDate(
                latest.created_at,
            );

            showSection("#pending-section");
            return;
        }

        // Document rejected by LGU
        if (latest?.status === "rejected") {
            $("#rejection-message").textContent =
                latest.rejection_reason ??
                "Please submit a clearer or valid document.";

            showSection("#rejected-section");
            return;
        }

        if (latest?.status === "approved") {
            showSection("#approved-section");
            return;
        }

        showSection("#document-section");
    } catch (error) {
        if (error.response?.status === 401) {
            sessionStorage.removeItem("agritimbang_token");
            window.location.replace("/login");
            return;
        }

        showError(getError(error));
    }
}

async function loadMunicipalities() {
    const response = await api.get("/reference/municipalities");

    const municipalities = response.data.data ?? [];
    const select = $("#municipality-select");

    select.replaceChildren(new Option("Select municipality", ""));

    municipalities.forEach((municipality) => {
        select.add(new Option(municipality.name, municipality.id));
    });
}

$("#municipality-select").addEventListener("change", async (event) => {
    const municipalityId = event.target.value;
    const select = $("#barangay-select");

    select.replaceChildren(new Option("Select barangay", ""));
    select.disabled = true;

    if (!municipalityId) return;

    try {
        const response = await api.get(
            `/reference/municipalities/${municipalityId}/barangays`,
        );

        const barangays = response.data.data ?? [];

        barangays.forEach((barangay) => {
            select.add(new Option(barangay.name, barangay.id));
        });

        select.disabled = false;
    } catch (error) {
        showError(getError(error));
    }
});

$("#profile-form").addEventListener("submit", async (event) => {
    event.preventDefault();

    const form = event.currentTarget;
    setBusy(form, true);
    errorBox.hidden = true;

    try {
        await api.put("/farmer/profile", {
            municipality_id: form.elements.municipality_id.value,
            barangay_id: form.elements.barangay_id.value,
            address: form.elements.address.value.trim() || null,
        });

        markSteps(true, false);
        showSection("#document-section");
    } catch (error) {
        showError(getError(error));
    } finally {
        setBusy(form, false);
    }
});

$("#document-file").addEventListener("change", (event) => {
    const file = event.target.files?.[0];

    $("#selected-file-name").textContent = file?.name ?? "No file selected";
});

$("#document-form").addEventListener("submit", async (event) => {
    event.preventDefault();

    const form = event.currentTarget;
    const file = form.elements.document.files?.[0];

    if (!file) {
        showError("Please select an identification document.");
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        showError("The document must not exceed 5 MB.");
        return;
    }

    const allowedExtensions = /\.(jpg|jpeg|png|pdf)$/i;

    if (!allowedExtensions.test(file.name)) {
        showError("Only JPG, PNG, and PDF files are allowed.");
        return;
    }

    const data = new FormData(form);

    if (!data.get("document_number")) {
        data.delete("document_number");
    }

    setBusy(form, true);
    errorBox.hidden = true;

    try {
        await api.post("/farmer/verification-documents", data);

        await loadVerificationStatus();
    } catch (error) {
        showError(getError(error));
    } finally {
        setBusy(form, false);
    }
});

$("#refresh-status").addEventListener("click", loadVerificationStatus);

$("#resubmit-button").addEventListener("click", () => {
    showSection("#document-section");
});

$("#logout-button").addEventListener("click", async () => {
    try {
        await api.post("/auth/logout");
    } finally {
        sessionStorage.removeItem("agritimbang_token");
        window.location.replace("/login");
    }
});

loadVerificationStatus();
