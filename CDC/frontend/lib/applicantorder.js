// Company applicant lists sorted by CGPA (P3-D54). Same rule as the backend's App\Support\ApplicantOrder, so a
// download matches the screen: no CGPA on record goes last in both directions; equal CGPAs are ordered by roll number;
// without a direction the list keeps the order of applying.

/** @typedef {"asc" | "desc" | null} CgpaDirection */

const cgpaOf = (row) => {
  const value = row?.current_cgpa;
  if (value === null || value === undefined || value === "") return null;
  const number = Number(value);
  return Number.isFinite(number) ? number : null;
};

/**
 * @template T
 * @param {T[]} rows
 * @param {CgpaDirection} direction
 * @returns {T[]}
 */
export function sortByCgpa(rows, direction) {
  if (!direction) return rows;
  return [...rows].sort((a, b) => {
    const x = cgpaOf(a);
    const y = cgpaOf(b);
    if (x === null || y === null) {
      if (x !== y) return x === null ? 1 : -1;
    } else if (x !== y) {
      return direction === "desc" ? y - x : x - y;
    }
    return String(a?.roll_no ?? "").localeCompare(String(b?.roll_no ?? ""));
  });
}

/** Next state when the CGPA header is clicked: default → high to low → low to high → default. */
export function nextCgpaDirection(direction) {
  return direction === null ? "desc" : direction === "desc" ? "asc" : null;
}

/** The `sort` / `dir` query for downloads and the page address ("" for the default order). */
export function cgpaQuery(direction) {
  return direction ? `sort=cgpa&dir=${direction}` : "";
}
