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
          <Grid size={{ xs: 12, sm: 4 }}>{field("graduating_batch", "Graduating batch", { required: true, type: "number" })}</Grid>
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
          <Grid size={{ xs: 6, sm: 3 }}>{field("tenth_percent", "10th %", { type: "number", inputProps: { step: 0.01 } })}</Grid>
          <Grid size={{ xs: 6, sm: 3 }}>{field("twelfth_percent", "12th %", { type: "number", inputProps: { step: 0.01 } })}</Grid>
          <Grid size={{ xs: 12, sm: 6 }}>{field("date_of_birth", "Date of birth", { type: "date" })}</Grid>
          <Grid size={{ xs: 12, sm: 4 }}>{field("phone", "Phone")}</Grid>
          <Grid size={{ xs: 6, sm: 4 }}>{field("category", "Category")}</Grid>
          <Grid size={{ xs: 6, sm: 4 }}>{field("home_state", "Home state")}</Grid>
          <Grid size={{ xs: 12, sm: 6 }}>{field("linkedin_url", "LinkedIn URL")}</Grid>
          <Grid size={{ xs: 12, sm: 6 }}>{field("github_url", "GitHub URL")}</Grid>
          <Grid size={{ xs: 12 }}>
            <FormControlLabel control={<Checkbox checked={Boolean(form.pwd)} onChange={set("pwd")} />} label="Person with disability (PwD)" />
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
