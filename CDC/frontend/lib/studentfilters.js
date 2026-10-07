// Apply Filters for the admin student lists (Superset parity S4.1). One shape is shared by the Students page and the
// placement's enrolled list (both kept in the URL, and passed on to their "Download as Excel"); the backend rules live
// in App\Support\StudentDirectoryFilters.

export const ARRAY_KEYS = ["programmes", "branches", "batches", "genders"];
export const VALUE_KEYS = [
  "tenth_min",
  "tenth_max",
  "twelfth_min",
  "twelfth_max",
  "cgpa_min",
  "cgpa_max",
  "ongoing_backlogs_max",
  "total_backlogs_max",
  "placement_status",
  "blocked_status",
  "cycle_id",
  "status",
  "invitation_status", // S5: sent / accepted / revoked, or "invited" (not registered yet)
];

export const EMPTY_FILTERS = {
  search: "",
  ...Object.fromEntries(ARRAY_KEYS.map((key) => [key, []])),
  ...Object.fromEntries(VALUE_KEYS.map((key) => [key, ""])),
};

const filled = (value) => String(value ?? "").trim() !== "";

/** Read filters from URLSearchParams (arrays as repeated keys: `batches=2026&batches=2027`). */
export function filtersFromParams(params) {
  const filters = { ...EMPTY_FILTERS, search: params.get("search") ?? "" };
  ARRAY_KEYS.forEach((key) => {
    filters[key] = params.getAll(key).filter(filled);
  });
  VALUE_KEYS.forEach((key) => {
    filters[key] = params.get(key) ?? "";
  });
  return filters;
}

/**
 * Serialise filters. `api: true` writes arrays the way Laravel reads them (`batches[]=2026`); otherwise as repeated
 * keys for a readable page URL.
 */
export function filtersToQuery(filters, { api = false, extra = {} } = {}) {
  const query = new URLSearchParams();
  if (filled(filters.search)) query.set("search", filters.search.trim());
  ARRAY_KEYS.forEach((key) => {
    (filters[key] ?? []).filter(filled).forEach((value) => query.append(api ? `${key}[]` : key, String(value)));
  });
  VALUE_KEYS.forEach((key) => {
    if (filled(filters[key])) query.set(key, String(filters[key]).trim());
  });
  Object.entries(extra).forEach(([key, value]) => {
    if (filled(value)) query.set(key, String(value));
  });
  return query.toString();
}

/** Number of filter groups in use (search not counted), for the badge on the Filters button. */
export function countFilters(filters) {
  const ranges = [
    ["tenth_min", "tenth_max"],
    ["twelfth_min", "twelfth_max"],
    ["cgpa_min", "cgpa_max"],
  ];
  let count = ARRAY_KEYS.filter((key) => (filters[key] ?? []).length > 0).length;
  count += ranges.filter(([a, b]) => filled(filters[a]) || filled(filters[b])).length;
  count += ["ongoing_backlogs_max", "total_backlogs_max", "placement_status", "blocked_status", "status", "invitation_status"].filter((key) =>
    filled(filters[key])
  ).length;
  return count;
}

export const hasFilters = (filters) => countFilters(filters) > 0 || filled(filters.search);
