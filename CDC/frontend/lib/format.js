export const dash = (value) => (value === null || value === undefined || value === "" ? "—" : value);

// The CDC works in India Standard Time whatever the viewer's device is set to (owner decision, QA F-005).
export const IST = "Asia/Kolkata";
const IST_OFFSET_MS = 330 * 60 * 1000; // IST has no daylight saving

export const formatDate = (value) => {
  if (!value) return "—";
  const date = new Date(value);
  return Number.isNaN(date.getTime())
    ? value
    : date.toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric", timeZone: IST });
};

export const formatDateTime = (value) => {
  if (!value) return "—";
  const date = new Date(value);
  return Number.isNaN(date.getTime())
    ? value
    : `${date.toLocaleString("en-IN", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
        timeZone: IST,
      })} IST`;
};

export const formatMoney = (value, currency = "INR") => {
  if (value === null || value === undefined || value === "") return "—";
  const number = Number(value);
  if (Number.isNaN(number)) return String(value);
  try {
    return new Intl.NumberFormat("en-IN", { style: "currency", currency, maximumFractionDigits: 0 }).format(number);
  } catch {
    return `${currency} ${number.toLocaleString("en-IN")}`;
  }
};

// "2026-10-05T18:00" (IST wall clock) for <input type="datetime-local"> from an ISO string or Date.
export const toLocalInput = (value) => {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  return new Date(date.getTime() + IST_OFFSET_MS).toISOString().slice(0, 16);
};

// The value of an IST datetime-local input as an ISO string with the IST offset, for the API.
export const fromLocalInput = (value) => (value ? `${value.length === 16 ? `${value}:00` : value}+05:30` : null);

export const statusColor = (status) =>
  ({
    open: "success",
    active: "success",
    approved: "success",
    selected: "success",
    completed: "default",
    closed: "default",
    pending: "warning",
    in_process: "info",
    waitlisted: "info",
    ongoing: "info",
    applied: "primary",
    rejected: "error",
    suspended: "error",
    cancelled: "error",
    withdrawn: "default",
  })[status] ?? "default";

export const titleCase = (value) =>
  String(value ?? "")
    .replace(/_/g, " ")
    .replace(/\b\w/g, (c) => c.toUpperCase());

/**
 * Display label for a job profile (posting) status (Superset parity S6.12); enum values stay unchanged.
 * Pass the posting itself (or `{ status, deadline_passed, any_stage_published, is_scheduled }`) for the finer labels:
 * open + deadline ahead → Accepting Applications; open past the deadline or in_process before any stage is published
 * → Closed For Applications; in_process after a publish → In Process; scheduled → Scheduled to Open.
 */
export const postingStatusLabel = (statusOrPosting) => {
  const p = typeof statusOrPosting === "object" && statusOrPosting !== null ? statusOrPosting : { status: statusOrPosting };
  // Only an open job profile can be waiting to open; a cancelled/closed one shows its real status (fix M4).
  if (p.is_scheduled && p.status === "open") return "Scheduled to Open";
  if (p.status === "open" && (p.deadline_passed || p.accepts_applications === false)) return "Closed For Applications";
  if (p.status === "in_process" && p.any_stage_published === false) return "Closed For Applications";
  return (
    {
      open: "Accepting Applications",
      in_process: "In Process",
      completed: "Completed",
      cancelled: "Cancelled",
    }[p.status] ?? titleCase(p.status)
  );
};
