/**
 * Offer categories and the CDC blocking rules (owner decision 2026-10-01, QA F-004). Mirrors
 * JobPosting::OFFER_CATEGORIES and BlockingPolicy on the backend.
 */

export const OFFER_CATEGORIES = {
  jnf: [
    { value: "fulltime", label: "Full-Time" },
    { value: "intern_fulltime", label: "Intern + Full-Time" },
  ],
  inf: [
    { value: "intern", label: "Internship" },
    { value: "intern_performance_ppo", label: "Intern + performance-based PPO" },
  ],
};

const COMPLETE = ["fulltime", "intern_fulltime", "intern_ppo"];

export const blocksCompletely = (offerType) => COMPLETE.includes(offerType);

/** What a selection under this category means for the student. */
export const selectionConsequence = (offerType) =>
  blocksCompletely(offerType)
    ? "Selected students are blocked from all further placement and internship opportunities in every placement they are registered for."
    : "Selected students are blocked from further internship opportunities; full-time opportunities stay open.";

/** The same rule, addressed to a student about to apply. */
export const studentConsequence = (offerType) =>
  blocksCompletely(offerType)
    ? "If you are selected, CDC policy blocks you from all further placement and internship opportunities in the placements you are registered for."
    : "If you are selected, CDC policy blocks you from further internship opportunities; full-time opportunities stay open to you.";

export const BLOCK_SCOPE_LABEL = {
  all: "Completely — every placement",
  internships_only: "Internships only — full-time open",
};
