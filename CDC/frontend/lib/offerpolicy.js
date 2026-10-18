/**
 * Offer categories and the CDC blocking rules (owner decisions 2026-10-01 and 2026-10-18). Mirrors
 * JobPosting::OFFER_CATEGORIES and BlockingPolicy on the backend. Categories are per placement cycle type:
 * an internship cycle is always "Internship"; a full-time cycle asks which kind of role it is.
 */

export const OFFER_CATEGORIES = {
  internship: [{ value: "intern", label: "Internship" }],
  fulltime: [
    { value: "fulltime", label: "Full-Time" },
    { value: "intern_performance_ppo", label: "Intern + performance-based PPO" },
    { value: "intern_fulltime", label: "Intern + Full-Time" },
  ],
};

export const offerCategoriesFor = (cycleType) => OFFER_CATEGORIES[cycleType === "internship" ? "internship" : "fulltime"];

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
