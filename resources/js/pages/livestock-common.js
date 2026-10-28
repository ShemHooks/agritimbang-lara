import api from "../api";

export { api };

export const $ = (id) => document.getElementById(id);

export const label = (value) =>
    String(value ?? "—")
        .replace(/_/g, " ")
        .replace(/\b\w/g, (c) => c.toUpperCase());

export const readonly = (record) => record.status !== "available";

export const unwrap = (response) => response.data?.data ?? response.data;

export function message(error) {
    const errors = error.response?.data?.errors;

    return errors
        ? Object.values(errors).flat().join(" ")
        : (error.response?.data?.message ??
              error.message ??
              "An unexpected error occurred.");
}

export function showError(error) {
    const el = $("l55-error");
    el.textContent = typeof error === "string" ? error : message(error);
    el.hidden = false;
}

export function list(response) {
    const data = unwrap(response);

    return Array.isArray(data)
        ? data
        : Array.isArray(data?.data)
          ? data.data
          : [];
}

export async function context() {
    const user = unwrap(await api.get("/auth/me"));

    const roles = (user.roles ?? []).map((r) =>
        String(typeof r === "string" ? r : r.slug)
            .toLowerCase()
            .replace(/[-\s]+/g, "_"),
    );

    const role = ["admin", "lgu_authority", "lgu_encoder", "farmer"].find((r) =>
        roles.includes(r),
    );

    if (!role) {
        throw new Error("Your account cannot access livestock records.");
    }

    return {
        role,
        endpoint: role === "farmer" ? "/farmer/livestock" : "/lgu/livestock",
    };
}

export function detail(el, name, value) {
    const item = document.createElement("div");
    item.className = "l55-detail";

    const title = document.createElement("span");
    title.textContent = name;

    const content = document.createElement("strong");
    content.textContent =
        value === null || value === undefined || value === ""
            ? "Not recorded"
            : String(value);

    item.append(title, content);
    el.append(item);
}
