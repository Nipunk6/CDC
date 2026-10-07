import { dash, formatDate } from "@/lib/format";

// Superset parity S4.6: CDC-entered academic extras, with Superset labels. Students see them read-only.
export const academicExtraFields = [
  ["Current Semester", "current_semester"],
  ["Course Start Date", "course_start_date"],
  ["Course End Date", "course_end_date"],
  ["Lateral Entry", "lateral_entry"],
  ["Xth Board", "tenth_board"],
  ["Year of passing 10th", "tenth_passing_year"],
  ["XIIth Board", "twelfth_board"],
  ["Year of passing 12th", "twelfth_passing_year"],
  ["Previous Degree", "previous_degree"],
  ["Previous Degree Score", "previous_degree_score"],
];

export const formatAcademicExtra = (student, key) => {
  const value = student?.[key];
  if (key === "course_start_date" || key === "course_end_date") return formatDate(value);
  if (key === "lateral_entry") return value ? "Yes" : "No";
  if (key === "previous_degree_score") {
    if (value === null || value === undefined || value === "") return "—";
    const number = Number(value);
    const type = student.previous_degree_score_type;
    return type === "percentage" ? `${number}%` : type === "cgpa" ? `${number} CGPA` : String(number);
  }
  return dash(value);
};
