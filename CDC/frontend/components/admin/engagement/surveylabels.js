// Survey display labels (Superset parity S7.3). Values stay as stored; these are UI words only.
export const SURVEY_TYPES = { general: "General", ppo_consent: "PPO Consent", feedback: "Feedback" };

export const SURVEY_STATUS = { draft: "Draft", published: "Published", archived: "Archived" };

// Toolbar controls, in Superset's order and wording.
export const QUESTION_TYPES = [
  { value: "mcq_single", label: "Multiple options, single answer" },
  { value: "mcq_multi", label: "Multiple options, multiple answers" },
  { value: "text", label: "Text Answer" },
  { value: "yes_no", label: "Yes/No" },
  { value: "rating", label: "Rating" },
  { value: "dropdown", label: "Dropdown" },
  { value: "static_text", label: "Static Text" },
  { value: "rich_text", label: "Rich Text" },
  { value: "file", label: "File Upload" },
  { value: "sequence", label: "Sequence" },
  { value: "date", label: "Date" },
];

export const questionTypeLabel = (value) => QUESTION_TYPES.find((t) => t.value === value)?.label ?? value;

export const WITH_OPTIONS = ["mcq_single", "mcq_multi", "dropdown", "sequence"];

// The login-gated student link ("Copy Link"); no personal data in the URL.
export const surveyLink = (id) => `${typeof window === "undefined" ? "" : window.location.origin}/student/surveys/${id}`;
