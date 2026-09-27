"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Avatar,
  Box,
  Button,
  Card,
  CardActionArea,
  CardContent,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Divider,
  FormControl,
  IconButton,
  InputLabel,
  LinearProgress,
  MenuItem,
  OutlinedInput,
  Paper,
  Select,
  Stack,
  TextField,
  Tooltip,
  Typography,
  alpha,
} from "@mui/material";
import AddIcon from "@mui/icons-material/Add";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import EventRepeatIcon from "@mui/icons-material/EventRepeat";
import GroupsIcon from "@mui/icons-material/Groups";
import LockIcon from "@mui/icons-material/Lock";
import WorkOutlineIcon from "@mui/icons-material/WorkOutline";
import EmojiEventsIcon from "@mui/icons-material/EmojiEvents";

import { adminApi } from "@/lib/adminapi";
import { defaultProgrammes } from "@/components/forms/shared";

const programmeOptions = defaultProgrammes.map((programme) => programme.programme);

const currentYear = new Date().getFullYear();
const batchOptions = Array.from({ length: 8 }, (_, index) => currentYear - 1 + index);

const emptyProgrammeRow = () => ({ programme: "", batches: [] });

const blankForm = () => ({
  name: "",
  type: "fulltime",
  starts_on: "",
  ends_on: "",
  description: "",
  allowed_programmes: [emptyProgrammeRow()],
});

const formatDate = (value) => {
  if (!value) return "—";
  const date = new Date(value);
  return Number.isNaN(date.getTime())
    ? value
    : date.toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric" });
};

export default function AdminPlacementCyclesPage() {
  const [cycles, setCycles] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);

  const [dialogOpen, setDialogOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState(null);
  const [form, setForm] = useState(blankForm());

  const loadCycles = async () => {
    setLoading(true);
    try {
      const response = await adminApi("/admin/placement-cycles");
      setCycles(response.placement_cycles ?? []);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load placement cycles.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    void loadCycles();
  }, []);

  const totals = useMemo(
    () =>
      cycles.reduce(
        (acc, cycle) => ({
          open: acc.open + (cycle.status === "open" ? 1 : 0),
          students: acc.students + (cycle.enrolled_students_count ?? 0),
        }),
        { open: 0, students: 0 }
      ),
    [cycles]
  );

  const openDialog = () => {
    setForm(blankForm());
    setFormError(null);
    setDialogOpen(true);
  };

  const updateField = (field, value) => setForm((prev) => ({ ...prev, [field]: value }));

  const updateProgrammeRow = (index, patch) =>
    setForm((prev) => ({
      ...prev,
      allowed_programmes: prev.allowed_programmes.map((row, rowIndex) =>
        rowIndex === index ? { ...row, ...patch } : row
      ),
    }));

  const addProgrammeRow = () =>
    setForm((prev) => ({ ...prev, allowed_programmes: [...prev.allowed_programmes, emptyProgrammeRow()] }));

  const removeProgrammeRow = (index) =>
    setForm((prev) => ({
      ...prev,
      allowed_programmes: prev.allowed_programmes.filter((_, rowIndex) => rowIndex !== index),
    }));

  const handleCreate = async () => {
    const rows = form.allowed_programmes.filter((row) => row.programme && row.batches.length > 0);

    if (!form.name.trim()) {
      setFormError("Enter a name for this cycle.");
      return;
    }
    if (!form.starts_on || !form.ends_on) {
      setFormError("Choose both a start and an end date.");
      return;
    }
    if (form.ends_on < form.starts_on) {
      setFormError("The end date cannot be before the start date.");
      return;
    }
    if (rows.length === 0) {
      setFormError("Add at least one programme with a graduating batch.");
      return;
    }

    setSaving(true);
    setFormError(null);

    try {
      await adminApi("/admin/placement-cycles", {
        method: "POST",
        body: JSON.stringify({
          name: form.name.trim(),
          type: form.type,
          starts_on: form.starts_on,
          ends_on: form.ends_on,
          description: form.description.trim() || null,
          allowed_programmes: rows,
        }),
      });
      setDialogOpen(false);
      setSuccess("Placement cycle created.");
      await loadCycles();
    } catch (e) {
      setFormError(e instanceof Error ? e.message : "Failed to create the placement cycle.");
    } finally {
      setSaving(false);
    }
  };

  const handleClose = async (cycle) => {
    if (!window.confirm(`Close "${cycle.name}"? Students can no longer be enrolled into a closed cycle from the UI.`)) {
      return;
    }

    try {
      await adminApi(`/admin/placement-cycles/${cycle.id}/close`, { method: "PATCH" });
      setSuccess(`"${cycle.name}" is now closed.`);
      await loadCycles();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to close the cycle.");
    }
  };

  return (
    <Box>
      <Paper
        sx={{
          p: 2,
          mb: 3,
          background: (theme) =>
            `linear-gradient(135deg, ${theme.palette.primary.main} 0%, ${theme.palette.primary.dark} 100%)`,
          color: "white",
          borderRadius: 2,
        }}
      >
        <Stack
          direction={{ xs: "column", md: "row" }}
          justifyContent="space-between"
          alignItems={{ md: "center" }}
          spacing={2}
        >
          <Stack direction="row" spacing={2} alignItems="center">
            <Avatar sx={{ width: 48, height: 48, bgcolor: "white", color: "primary.main" }}>
              <EventRepeatIcon />
            </Avatar>
            <Box>
              <Typography variant="h5" fontWeight={700}>
                Placement Cycles
              </Typography>
              <Typography variant="body2" sx={{ opacity: 0.9 }}>
                {cycles.length} cycle(s) · {totals.open} open · {totals.students} enrolment(s)
              </Typography>
            </Box>
          </Stack>
          <Stack direction="row" spacing={1}>
            <Button variant="contained" color="secondary" startIcon={<AddIcon />} onClick={openDialog}>
              Add Placement Cycle
            </Button>
            <Button
              component={Link}
              href="/admin"
              variant="outlined"
              startIcon={<ArrowBackIcon />}
              sx={{
                color: "white",
                borderColor: "white",
                "&:hover": { borderColor: "white", bgcolor: alpha("#fff", 0.1) },
              }}
            >
              Back to Dashboard
            </Button>
          </Stack>
        </Stack>
      </Paper>

      {error && (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>
          {error}
        </Alert>
      )}
      {success && (
        <Alert severity="success" sx={{ mb: 2 }} onClose={() => setSuccess(null)}>
          {success}
        </Alert>
      )}

      {loading ? (
        <LinearProgress />
      ) : cycles.length === 0 ? (
        <Paper sx={{ p: 6, textAlign: "center" }}>
          <EventRepeatIcon sx={{ fontSize: 56, color: "text.disabled", mb: 1 }} />
          <Typography variant="h6" gutterBottom>
            No placement cycles yet
          </Typography>
          <Typography color="text.secondary" sx={{ mb: 3, textAlign: "center" }}>
            Every drive, enrolment and offer hangs off a placement cycle. Create the first one to get started.
          </Typography>
          <Button variant="contained" startIcon={<AddIcon />} onClick={openDialog}>
            Add Placement Cycle
          </Button>
        </Paper>
      ) : (
        <Stack spacing={2}>
          {cycles.map((cycle) => (
            <Card key={cycle.id}>
              <CardActionArea component={Link} href={`/admin/placement-cycles/${cycle.id}`}>
                <CardContent>
                  <Stack
                    direction={{ xs: "column", md: "row" }}
                    justifyContent="space-between"
                    alignItems={{ md: "center" }}
                    spacing={2}
                  >
                    <Box sx={{ minWidth: 0 }}>
                      <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap sx={{ mb: 0.5 }}>
                        <Typography variant="h6" fontWeight={700} sx={{ textAlign: "left" }}>
                          {cycle.name}
                        </Typography>
                        <Chip
                          size="small"
                          variant="outlined"
                          color={cycle.type === "fulltime" ? "primary" : "secondary"}
                          label={cycle.type === "fulltime" ? "Full Time" : "Internship"}
                        />
                        <Chip
                          size="small"
                          variant="outlined"
                          color={cycle.status === "open" ? "success" : "default"}
                          label={cycle.status === "open" ? "Open" : "Closed"}
                        />
                      </Stack>
                      <Typography variant="body2" color="text.secondary">
                        {formatDate(cycle.starts_on)} → {formatDate(cycle.ends_on)} ·{" "}
                        {(cycle.allowed_programmes ?? []).length} programme(s)
                      </Typography>
                    </Box>

                    <Stack direction="row" spacing={3}>
                      {[
                        { icon: <GroupsIcon fontSize="small" />, label: "Students", value: cycle.enrolled_students_count },
                        { icon: <WorkOutlineIcon fontSize="small" />, label: "Postings", value: cycle.postings_count },
                        { icon: <EmojiEventsIcon fontSize="small" />, label: "Offers", value: cycle.offers_count },
                      ].map((stat) => (
                        <Box key={stat.label} sx={{ textAlign: "center", minWidth: 68 }}>
                          <Stack direction="row" spacing={0.5} alignItems="center" justifyContent="center" color="text.secondary">
                            {stat.icon}
                            <Typography variant="h6" fontWeight={700} color="text.primary">
                              {stat.value ?? 0}
                            </Typography>
                          </Stack>
                          <Typography variant="caption" color="text.secondary">
                            {stat.label}
                          </Typography>
                        </Box>
                      ))}
                    </Stack>
                  </Stack>
                </CardContent>
              </CardActionArea>
              {cycle.status === "open" && (
                <>
                  <Divider />
                  <Stack direction="row" justifyContent="flex-end" sx={{ px: 2, py: 1 }}>
                    <Tooltip title="Close this cycle">
                      <Button size="small" color="inherit" startIcon={<LockIcon />} onClick={() => handleClose(cycle)}>
                        Close cycle
                      </Button>
                    </Tooltip>
                  </Stack>
                </>
              )}
            </Card>
          ))}
        </Stack>
      )}

      <Dialog open={dialogOpen} onClose={() => !saving && setDialogOpen(false)} maxWidth="md" fullWidth>
        <DialogTitle>Add Placement Cycle</DialogTitle>
        <DialogContent dividers>
          <Stack spacing={2.5} sx={{ pt: 1 }}>
            {formError && <Alert severity="error">{formError}</Alert>}

            <TextField
              label="Cycle name"
              fullWidth
              required
              placeholder="Full Time 2026-27"
              value={form.name}
              onChange={(event) => updateField("name", event.target.value)}
            />

            <Stack direction={{ xs: "column", sm: "row" }} spacing={2}>
              <FormControl fullWidth>
                <InputLabel id="cycle-type-label">Type</InputLabel>
                <Select
                  labelId="cycle-type-label"
                  label="Type"
                  value={form.type}
                  onChange={(event) => updateField("type", event.target.value)}
                >
                  <MenuItem value="fulltime">Full Time</MenuItem>
                  <MenuItem value="internship">Internship</MenuItem>
                </Select>
              </FormControl>
              <TextField
                label="Starts on"
                type="date"
                fullWidth
                required
                value={form.starts_on}
                onChange={(event) => updateField("starts_on", event.target.value)}
                slotProps={{ inputLabel: { shrink: true } }}
              />
              <TextField
                label="Ends on"
                type="date"
                fullWidth
                required
                value={form.ends_on}
                onChange={(event) => updateField("ends_on", event.target.value)}
                slotProps={{ inputLabel: { shrink: true } }}
              />
            </Stack>

            <TextField
              label="Description (optional)"
              fullWidth
              multiline
              minRows={2}
              value={form.description}
              onChange={(event) => updateField("description", event.target.value)}
            />

            <Box>
              <Typography variant="subtitle2" fontWeight={700} gutterBottom>
                Allowed programmes &amp; batches
              </Typography>
              <Typography variant="caption" color="text.secondary" sx={{ display: "block", mb: 1.5 }}>
                Which programmes and graduating batches this cycle covers.
              </Typography>

              <Stack spacing={1.5}>
                {form.allowed_programmes.map((row, index) => (
                  <Stack key={index} direction={{ xs: "column", md: "row" }} spacing={1.5} alignItems={{ md: "center" }}>
                    <FormControl fullWidth size="small">
                      <InputLabel id={`programme-label-${index}`}>Programme</InputLabel>
                      <Select
                        labelId={`programme-label-${index}`}
                        label="Programme"
                        value={row.programme}
                        onChange={(event) => updateProgrammeRow(index, { programme: event.target.value })}
                      >
                        {programmeOptions.map((programme) => (
                          <MenuItem key={programme} value={programme}>
                            {programme}
                          </MenuItem>
                        ))}
                      </Select>
                    </FormControl>

                    <FormControl sx={{ minWidth: { md: 220 }, width: { xs: "100%", md: "auto" } }} size="small">
                      <InputLabel id={`batches-label-${index}`}>Batches</InputLabel>
                      <Select
                        labelId={`batches-label-${index}`}
                        multiple
                        value={row.batches}
                        onChange={(event) => updateProgrammeRow(index, { batches: event.target.value })}
                        input={<OutlinedInput label="Batches" />}
                        renderValue={(selected) => selected.join(", ")}
                      >
                        {batchOptions.map((year) => (
                          <MenuItem key={year} value={year}>
                            {year}
                          </MenuItem>
                        ))}
                      </Select>
                    </FormControl>

                    <Tooltip title="Remove this programme">
                      <span>
                        <IconButton
                          onClick={() => removeProgrammeRow(index)}
                          disabled={form.allowed_programmes.length === 1}
                          color="error"
                        >
                          <DeleteOutlineIcon />
                        </IconButton>
                      </span>
                    </Tooltip>
                  </Stack>
                ))}
              </Stack>

              <Button size="small" startIcon={<AddIcon />} onClick={addProgrammeRow} sx={{ mt: 1.5 }}>
                Add programme
              </Button>
            </Box>
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDialogOpen(false)} disabled={saving}>
            Cancel
          </Button>
          <Button variant="contained" onClick={handleCreate} disabled={saving}>
            {saving ? "Creating..." : "Create Cycle"}
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
}
