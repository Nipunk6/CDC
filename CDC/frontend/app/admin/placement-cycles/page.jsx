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
  InputAdornment,
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
import SearchIcon from "@mui/icons-material/Search";
import PublishIcon from "@mui/icons-material/Publish";
import HistoryIcon from "@mui/icons-material/History";

import { adminApi } from "@/lib/adminapi";
import { useRecentPlacementIds } from "@/lib/recentplacements";
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

// Previous Placements (S8.3): closed, or its end date has passed (IST calendar day).
const todayIst = () => new Date().toLocaleDateString("en-CA", { timeZone: "Asia/Kolkata" });
const isPrevious = (cycle) => cycle.status !== "open" || (cycle.ends_on && cycle.ends_on < todayIst());

export default function AdminPlacementCyclesPage() {
  const [cycles, setCycles] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);

  const [dialogOpen, setDialogOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState(null);
  const [form, setForm] = useState(blankForm());
  const [search, setSearch] = useState("");
  const recentIds = useRecentPlacementIds();

  const loadCycles = async () => {
    setLoading(true);
    try {
      const response = await adminApi("/admin/placement-cycles");
      setCycles(response.placement_cycles ?? []);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load placements.");
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

  const [current, previous] = useMemo(() => {
    const term = search.trim().toLowerCase();
    const matching = cycles.filter((cycle) => !term || cycle.name.toLowerCase().includes(term));
    return [matching.filter((cycle) => !isPrevious(cycle)), matching.filter(isPrevious)];
  }, [cycles, search]);

  const recent = useMemo(
    () => recentIds.map((id) => cycles.find((cycle) => cycle.id === id)).filter(Boolean),
    [recentIds, cycles]
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

  const handleCreate = async (asDraft = false) => {
    const rows = form.allowed_programmes.filter((row) => row.programme && row.batches.length > 0);

    if (!form.name.trim()) {
      setFormError("Enter a name for this placement.");
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
      setFormError("Add at least one programme with a passout batch.");
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
          is_draft: asDraft,
        }),
      });
      setDialogOpen(false);
      setSuccess(asDraft ? "Placement saved as a draft. Students cannot see it until you publish it." : "Placement created.");
      await loadCycles();
    } catch (e) {
      setFormError(e instanceof Error ? e.message : "Failed to create the placement.");
    } finally {
      setSaving(false);
    }
  };

  const handleClose = async (cycle) => {
    if (!window.confirm(`Close "${cycle.name}"? Students can no longer be enrolled into a closed placement from the UI.`)) {
      return;
    }

    try {
      await adminApi(`/admin/placement-cycles/${cycle.id}/close`, { method: "PATCH" });
      setSuccess(`"${cycle.name}" is now closed.`);
      await loadCycles();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to close the placement.");
    }
  };

  const handlePublish = async (cycle) => {
    if (!window.confirm(`Publish "${cycle.name}"? Its enrolled students will be able to see it, and job profiles can be opened for applications in it.`)) {
      return;
    }

    try {
      const response = await adminApi(`/admin/placement-cycles/${cycle.id}/publish`, { method: "PATCH" });
      setSuccess(response.message ?? `"${cycle.name}" is now published.`);
      await loadCycles();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to publish the placement.");
    }
  };

  const renderCycle = (cycle) => (
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
                        <Typography variant="h6" fontWeight={700} sx={{ textAlign: "left", overflowWrap: "anywhere" }}>
                          {cycle.name}
                        </Typography>
                        {cycle.is_draft && <Chip size="small" color="warning" label="Draft" />}
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
                        { icon: <WorkOutlineIcon fontSize="small" />, label: "Job Profiles", value: cycle.postings_count },
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
              {(cycle.status === "open" || cycle.is_draft) && (
                <>
                  <Divider />
                  <Stack direction="row" justifyContent="flex-end" spacing={1} flexWrap="wrap" useFlexGap sx={{ px: 2, py: 1 }}>
                    {cycle.is_draft && (
                      <Tooltip title="Make this placement visible to its enrolled students">
                        <Button size="small" color="primary" startIcon={<PublishIcon />} onClick={() => handlePublish(cycle)}>
                          Publish placement
                        </Button>
                      </Tooltip>
                    )}
                    {cycle.status === "open" && (
                      <Tooltip title="Close this placement">
                        <Button size="small" color="inherit" startIcon={<LockIcon />} onClick={() => handleClose(cycle)}>
                          Close placement
                        </Button>
                      </Tooltip>
                    )}
                  </Stack>
                </>
              )}
            </Card>
  );

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
                Placements
              </Typography>
              <Typography variant="body2" sx={{ opacity: 0.9 }}>
                {loading ? "Loading placements…" : `${cycles.length} placement(s) · ${totals.open} open · ${totals.students} enrolment(s)`}
              </Typography>
            </Box>
          </Stack>
          <Stack direction="row" spacing={1}>
            <Button variant="contained" color="secondary" startIcon={<AddIcon />} onClick={openDialog}>
              Add placement process
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
            No placements yet
          </Typography>
          <Typography color="text.secondary" sx={{ mb: 3, textAlign: "center" }}>
            Every job profile, enrolment and offer hangs off a placement. Create the first one to get started.
          </Typography>
          <Button variant="contained" startIcon={<AddIcon />} onClick={openDialog}>
            Add placement process
          </Button>
        </Paper>
      ) : (
        <Box sx={{ display: "grid", gap: 3, gridTemplateColumns: { xs: "minmax(0, 1fr)", lg: "minmax(0, 1fr) 300px" }, alignItems: "start" }}>
          <Box sx={{ minWidth: 0 }}>
            <TextField
              size="small"
              fullWidth
              placeholder="Search Placements"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              sx={{ mb: 2, bgcolor: "background.paper" }}
              slotProps={{ input: { startAdornment: <InputAdornment position="start"><SearchIcon fontSize="small" /></InputAdornment> } }}
            />
            {current.length === 0 && previous.length === 0 && (
              <Typography color="text.secondary" sx={{ py: 2 }}>
                No placements match &quot;{search.trim()}&quot;.
              </Typography>
            )}
            <Stack spacing={2}>{current.map(renderCycle)}</Stack>
            {previous.length > 0 && (
              <>
                <Typography variant="subtitle1" fontWeight={700} color="text.secondary" sx={{ mt: current.length > 0 ? 4 : 0, mb: 1.5 }}>
                  Previous Placements
                </Typography>
                <Stack spacing={2}>{previous.map(renderCycle)}</Stack>
              </>
            )}
          </Box>

          <Paper variant="outlined" sx={{ p: 2, minWidth: 0 }}>
            <Stack direction="row" spacing={1} alignItems="center" sx={{ mb: 1 }}>
              <HistoryIcon fontSize="small" color="action" />
              <Typography variant="subtitle1" fontWeight={700}>
                Recently Visited
              </Typography>
            </Stack>
            {recent.length === 0 ? (
              <Typography variant="body2" color="text.secondary">
                Placements you open appear here.
              </Typography>
            ) : (
              <Stack divider={<Divider flexItem />}>
                {recent.map((cycle) => (
                  <Box
                    key={cycle.id}
                    component={Link}
                    href={`/admin/placement-cycles/${cycle.id}`}
                    sx={{ py: 1, color: "text.primary", textDecoration: "none", "&:hover": { color: "primary.main" } }}
                  >
                    <Typography variant="body2" fontWeight={600} sx={{ overflowWrap: "anywhere" }}>
                      {cycle.name}
                      {cycle.is_draft ? " [DRAFT]" : ""}
                    </Typography>
                    <Typography variant="caption" color="text.secondary">
                      {formatDate(cycle.starts_on)} → {formatDate(cycle.ends_on)}
                    </Typography>
                  </Box>
                ))}
              </Stack>
            )}
          </Paper>
        </Box>
      )}

      <Dialog open={dialogOpen} onClose={() => !saving && setDialogOpen(false)} maxWidth="md" fullWidth>
        <DialogTitle>Add placement process</DialogTitle>
        <DialogContent dividers>
          <Stack spacing={2.5} sx={{ pt: 1 }}>
            {formError && <Alert severity="error">{formError}</Alert>}

            <TextField
              label="Placement name"
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
                Which programmes and passout batches this placement covers.
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
          <Button variant="outlined" onClick={() => handleCreate(true)} disabled={saving}>
            Save as draft
          </Button>
          <Button variant="contained" onClick={() => handleCreate(false)} disabled={saving}>
            {saving ? "Creating..." : "Create Placement"}
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
}
