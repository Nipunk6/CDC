"use client";

import { useEffect, useState } from "react";
import {
  Alert,
  Button,
  Checkbox,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControl,
  FormControlLabel,
  Grid2 as Grid,
  InputLabel,
  MenuItem,
  Select,
  TextField,
  Typography,
} from "@mui/material";

import { adminApi } from "@/lib/adminapi";
import useCatalogue from "@/lib/usecatalogue";

const blank = {
  roll_no: "",
  full_name: "",
  institute_email: "",
  personal_email: "",
  phone: "",
  programme: "",
  branch: "",
  graduating_batch: new Date().getFullYear() + 1,
  gender: "male",
  current_cgpa: "",
  ongoing_backlogs: 0,
  total_backlogs: 0,
  tenth_percent: "",
  twelfth_percent: "",
  date_of_birth: "",
  category: "",
  pwd: false,
  home_state: "",
  linkedin_url: "",
  github_url: "",
  // S4.6 academic extras (CDC-entered only)
  current_semester: "",
  course_start_date: "",
  course_end_date: "",
  lateral_entry: false,
  tenth_board: "",
  tenth_passing_year: "",
  twelfth_board: "",
  twelfth_passing_year: "",
  previous_degree: "",
  previous_degree_score: "",
  previous_degree_score_type: "",
};

const fromStudent = (student) =>
  Object.fromEntries(Object.keys(blank).map((key) => [key, student?.[key] ?? blank[key]]));

// Create (student = null) or edit an existing student. Every field is admin-controlled.
export default function StudentFormDialog({ open, student, onClose, onSaved }) {
  const catalogue = useCatalogue(adminApi);
  const [form, setForm] = useState(blank);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (open) {
      setForm(student ? fromStudent(student) : blank);
      setError(null);
    }
  }, [open, student]);

  const set = (field) => (event) => {
    const value = event.target.type === "checkbox" ? event.target.checked : event.target.value;
    setForm((prev) => ({ ...prev, [field]: value, ...(field === "programme" ? { branch: "" } : {}) }));
  };

  const handleSave = async () => {
    setSaving(true);
    setError(null);
    const body = Object.fromEntries(
      Object.entries(form).map(([key, value]) => [key, value === "" ? null : value])
    );
    try {
      const response = student
        ? await adminApi(`/admin/students/${student.id}`, { method: "PATCH", body: JSON.stringify(body) })
        : await adminApi("/admin/students", { method: "POST", body: JSON.stringify(body) });
      onSaved?.(response);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to save the student.");
    } finally {
      setSaving(false);
    }
  };

  const field = (name, label, props = {}) => (
    <TextField
      label={label}
      fullWidth
      size="small"
      value={form[name] ?? ""}
      onChange={set(name)}
      slotProps={props.type === "date" ? { inputLabel: { shrink: true } } : undefined}
      {...props}
    />
  );

  const branches = catalogue[form.programme] ?? [];

  return (
    <Dialog open={open} onClose={() => !saving && onClose()} maxWidth="md" fullWidth>
      <DialogTitle>{student ? `Edit ${student.roll_no}` : "Add Student"}</DialogTitle>
      <DialogContent dividers>
        {error && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {error}
          </Alert>
        )}
        {!student && (
          <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
            The student receives an invitation at their institute email with their roll number and a link to set a password.
          </Typography>
        )}
        <Grid container spacing={2}>
          <Grid size={{ xs: 12, sm: 4 }}>{field("roll_no", "Roll number", { required: true })}</Grid>
          <Grid size={{ xs: 12, sm: 8 }}>{field("full_name", "Full name", { required: true })}</Grid>
          <Grid size={{ xs: 12, sm: 6 }}>{field("institute_email", "Institute email", { required: true, type: "email" })}</Grid>
          <Grid size={{ xs: 12, sm: 6 }}>{field("personal_email", "Personal email", { type: "email" })}</Grid>
          <Grid size={{ xs: 12 }}>
            <FormControl fullWidth size="small" required>
              <InputLabel id="student-programme">Programme</InputLabel>
              <Select labelId="student-programme" label="Programme" value={form.programme} onChange={set("programme")}>
                {Object.keys(catalogue).map((programme) => (
                  <MenuItem key={programme} value={programme}>
                    {programme}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
          </Grid>
          <Grid size={{ xs: 12, sm: 8 }}>
            <FormControl fullWidth size="small" required disabled={!form.programme}>
              <InputLabel id="student-branch">Branch</InputLabel>
              <Select labelId="student-branch" label="Branch" value={branches.includes(form.branch) ? form.branch : ""} onChange={set("branch")}>
                {branches.map((branch) => (
                  <MenuItem key={branch} value={branch}>
                    {branch}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
          </Grid>
          <Grid size={{ xs: 12, sm: 4 }}>{field("graduating_batch", "Passout Batch", { required: true, type: "number" })}</Grid>
          <Grid size={{ xs: 6, sm: 3 }}>
            <FormControl fullWidth size="small" required>
              <InputLabel id="student-gender">Gender</InputLabel>
              <Select labelId="student-gender" label="Gender" value={form.gender} onChange={set("gender")}>
                <MenuItem value="male">Male</MenuItem>
                <MenuItem value="female">Female</MenuItem>
                <MenuItem value="other">Other</MenuItem>
              </Select>
            </FormControl>
          </Grid>
          <Grid size={{ xs: 6, sm: 3 }}>{field("current_cgpa", "CGPA", { type: "number", inputProps: { step: 0.01, min: 0, max: 10 } })}</Grid>
          <Grid size={{ xs: 6, sm: 3 }}>{field("ongoing_backlogs", "Ongoing backlogs", { type: "number", inputProps: { min: 0 } })}</Grid>
          <Grid size={{ xs: 6, sm: 3 }}>{field("total_backlogs", "Total backlogs", { type: "number", inputProps: { min: 0 } })}</Grid>
          <Grid size={{ xs: 6, sm: 3 }}>{field("tenth_percent", "Class X Percentage", { type: "number", inputProps: { step: 0.01 } })}</Grid>
          <Grid size={{ xs: 6, sm: 3 }}>{field("twelfth_percent", "Class XII Percentage", { type: "number", inputProps: { step: 0.01 } })}</Grid>
          <Grid size={{ xs: 12, sm: 6 }}>{field("date_of_birth", "Date of birth", { type: "date" })}</Grid>
          <Grid size={{ xs: 12, sm: 4 }}>{field("phone", "Contact No.")}</Grid>
          <Grid size={{ xs: 6, sm: 4 }}>{field("category", "Social Category")}</Grid>
          <Grid size={{ xs: 6, sm: 4 }}>{field("home_state", "Home state")}</Grid>
          <Grid size={{ xs: 12, sm: 6 }}>{field("linkedin_url", "LinkedIn URL")}</Grid>
          <Grid size={{ xs: 12, sm: 6 }}>{field("github_url", "GitHub URL")}</Grid>
          <Grid size={{ xs: 12 }}>
            <FormControlLabel control={<Checkbox checked={Boolean(form.pwd)} onChange={set("pwd")} />} label="Person with disability (PwD)" />
          </Grid>

          <Grid size={{ xs: 12 }}>
            <Typography variant="subtitle2" fontWeight={700}>
              Academic Details
            </Typography>
            <Typography variant="caption" color="text.secondary">
              Optional. Students see these read-only.
            </Typography>
          </Grid>
          <Grid size={{ xs: 6, sm: 4 }}>{field("current_semester", "Current Semester", { type: "number", inputProps: { min: 1, max: 12 } })}</Grid>
          <Grid size={{ xs: 6, sm: 4 }}>{field("course_start_date", "Course Start Date", { type: "date" })}</Grid>
          <Grid size={{ xs: 12, sm: 4 }}>{field("course_end_date", "Course End Date", { type: "date" })}</Grid>
          <Grid size={{ xs: 12, sm: 8 }}>{field("tenth_board", "Xth Board")}</Grid>
          <Grid size={{ xs: 12, sm: 4 }}>{field("tenth_passing_year", "Year of passing 10th", { type: "number" })}</Grid>
          <Grid size={{ xs: 12, sm: 8 }}>{field("twelfth_board", "XIIth Board")}</Grid>
          <Grid size={{ xs: 12, sm: 4 }}>{field("twelfth_passing_year", "Year of passing 12th", { type: "number" })}</Grid>
          <Grid size={{ xs: 12, sm: 6 }}>{field("previous_degree", "Previous Degree")}</Grid>
          <Grid size={{ xs: 6, sm: 3 }}>{field("previous_degree_score", "Previous Degree Score", { type: "number", inputProps: { step: 0.01, min: 0 } })}</Grid>
          <Grid size={{ xs: 6, sm: 3 }}>
            <FormControl fullWidth size="small">
              <InputLabel id="student-previous-score-type">Score type</InputLabel>
              <Select
                labelId="student-previous-score-type"
                label="Score type"
                value={form.previous_degree_score_type ?? ""}
                onChange={set("previous_degree_score_type")}
              >
                <MenuItem value="">—</MenuItem>
                <MenuItem value="cgpa">CGPA</MenuItem>
                <MenuItem value="percentage">Percentage</MenuItem>
              </Select>
            </FormControl>
          </Grid>
          <Grid size={{ xs: 12 }}>
            <FormControlLabel
              control={<Checkbox checked={Boolean(form.lateral_entry)} onChange={set("lateral_entry")} />}
              label="Lateral Entry"
            />
          </Grid>
        </Grid>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={saving}>
          Cancel
        </Button>
        <Button variant="contained" onClick={handleSave} disabled={saving}>
          {saving ? "Saving..." : student ? "Save Changes" : "Create Student"}
        </Button>
      </DialogActions>
    </Dialog>
  );
}
