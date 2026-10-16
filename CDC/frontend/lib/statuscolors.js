// The one place a status gets its colour (design brief §5). Every chip, badge and stepper takes its MUI colour from
// here, so "accepted" is the same green on every page. Add a status here; never write another getStatusColor.
// A colour is never the only signal: the status word is always shown next to it.

/** @typedef {"default" | "primary" | "secondary" | "error" | "info" | "success" | "warning"} StatusColor */

/** @type {Record<string, StatusColor>} */
const STATUS_COLORS = {
  // Company forms (JNF / INF)
  draft: "default",
  submitted: "warning",
  under_review: "info",
  accepted: "success",
  // Job profiles, placements, enrolments
  open: "success",
  active: "success",
  in_process: "info",
  ongoing: "info",
  completed: "default",
  closed: "default",
  cancelled: "error",
  suspended: "error",
  // Applications, stage results, resumes, requests
  applied: "primary",
  pending: "warning",
  approved: "success",
  selected: "success",
  waitlisted: "info",
  rejected: "error",
  withdrawn: "default",
  // Profile verification (per field)
  verified: "success",
  unverified: "warning",
};

/**
 * @param {string | null | undefined} status
 * @returns {StatusColor}
 */
export const statusColor = (status) => STATUS_COLORS[String(status ?? "")] ?? "default";
