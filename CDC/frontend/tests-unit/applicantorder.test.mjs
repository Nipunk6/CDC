// P3-D54: company applicant lists sorted by CGPA (same rule as the backend's App\Support\ApplicantOrder).
// Run: npm run test:unit
import { test } from "node:test";
import assert from "node:assert/strict";
import { cgpaQuery, nextCgpaDirection, sortByCgpa } from "../lib/applicantorder.js";

const rows = [
  { roll_no: "22JE0003", current_cgpa: "7.50" },
  { roll_no: "22JE0001", current_cgpa: null },
  { roll_no: "22JE0004", current_cgpa: 9.1 },
  { roll_no: "22JE0002", current_cgpa: "7.50" },
  { roll_no: "22JE0005", current_cgpa: "" },
];
const rolls = (list) => list.map((r) => r.roll_no);

test("without a direction the order of applying is kept", () => {
  assert.equal(sortByCgpa(rows, null), rows);
});

test("highest first: missing CGPA last, equal CGPAs by roll number", () => {
  assert.deepEqual(rolls(sortByCgpa(rows, "desc")), ["22JE0004", "22JE0002", "22JE0003", "22JE0001", "22JE0005"]);
});

test("lowest first: missing CGPA still last", () => {
  assert.deepEqual(rolls(sortByCgpa(rows, "asc")), ["22JE0002", "22JE0003", "22JE0004", "22JE0001", "22JE0005"]);
  assert.deepEqual(rolls(rows), ["22JE0003", "22JE0001", "22JE0004", "22JE0002", "22JE0005"], "the input is not changed");
});

test("header clicks cycle default → desc → asc → default, and the query matches", () => {
  assert.equal(nextCgpaDirection(null), "desc");
  assert.equal(nextCgpaDirection("desc"), "asc");
  assert.equal(nextCgpaDirection("asc"), null);
  assert.equal(cgpaQuery("desc"), "sort=cgpa&dir=desc");
  assert.equal(cgpaQuery(null), "");
});
