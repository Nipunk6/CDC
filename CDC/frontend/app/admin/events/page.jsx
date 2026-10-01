"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Alert,
  Autocomplete,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControl,
  FormControlLabel,
  InputLabel,
  LinearProgress,
  MenuItem,
  Radio,
  RadioGroup,
  Select,
  Stack,
  Tab,
  Tabs,
  TextField,
  Typography,
} from "@mui/material";
import EventIcon from "@mui/icons-material/Event";
import AddIcon from "@mui/icons-material/Add";

import PageHeader from "@/components/shared/pageheader";
import { RichTextEditor, stripHtml } from "@/components/forms/shared";
import { adminApi } from "@/lib/adminapi";
import useCatalogue from "@/lib/usecatalogue";
import { formatDateTime, fromLocalInput, toLocalInput } from "@/lib/format";

const TYPES = { ppt: "Pre-Placement Talk", workshop: "Workshop", webinar: "Webinar", other: "Other" };

const blank = () => ({
  title: "",
  event_type: "ppt",
  company_id: null,
  starts_at: "",
  venue: "",
  meeting_link: "",
  description: "",
  audience_type: "all",
  branches: [],
  job_posting_id: "",
});

export default function AdminEventsPage() {
  const catalogue = useCatalogue(adminApi);
  const [when, setWhen] = useState("upcoming");
  const [events, setEvents] = useState(null);
  const [companies, setCompanies] = useState([]);
  const [postings, setPostings] = useState([]);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [dialog, setDialog] = useState(null);
  const [dialogError, setDialogError] = useState(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      const response = await adminApi(`/admin/events?when=${when}`);
      setEvents(response.events ?? []);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load events.");
    }
  }, [when]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    adminApi("/admin/companies").then((r) => setCompanies(r.companies ?? [])).catch(() => setCompanies([]));
    adminApi("/admin/postings").then((r) => setPostings(r.postings ?? [])).catch(() => setPostings([]));
  }, []);

  // Every programme (whole programme) and every programme/branch pair, for the audience picker.
  const branchOptions = useMemo(
    () =>
      Object.entries(catalogue).flatMap(([programme, branches]) => [
        { programme, branch: null },
        ...branches.map((branch) => ({ programme, branch })),
      ]),
    [catalogue]
  );

  const openEdit = (event) => {
    setDialogError(null);
    setDialog(
      event
        ? {
            id: event.id,
            title: event.title,
            event_type: event.event_type,
            company_id: event.company_id,
            starts_at: toLocalInput(event.starts_at),
            venue: event.venue ?? "",
            meeting_link: event.meeting_link ?? "",
            description: event.description ?? "",
            audience_type: event.audience_type,
            branches: event.audience_filter?.branches ?? [],
            job_posting_id: event.audience_filter?.job_posting_id ? String(event.audience_filter.job_posting_id) : "",
            published: Boolean(event.published_at),
          }
        : blank()
    );
  };

  const save = async () => {
    if (!dialog.title.trim() || !dialog.starts_at) {
      setDialogError("Title and start time are required.");
      return;
    }
    setBusy(true);
    setDialogError(null);
    const body = {
      title: dialog.title.trim(),
      event_type: dialog.event_type,
      company_id: dialog.company_id || null,
      starts_at: fromLocalInput(dialog.starts_at),
      venue: dialog.venue.trim() || null,
      meeting_link: dialog.meeting_link.trim() || null,
      description: stripHtml(dialog.description).trim() ? dialog.description : null,
      audience_type: dialog.audience_type,
      audience_filter:
        dialog.audience_type === "branches"
          ? { branches: dialog.branches.map((b) => ({ programme: b.programme, branch: b.branch || null })) }
          : dialog.audience_type === "posting_applicants"
            ? { job_posting_id: Number(dialog.job_posting_id) }
            : null,
    };
    try {
      const response = dialog.id
        ? await adminApi(`/admin/events/${dialog.id}`, { method: "PUT", body: JSON.stringify(body) })
        : await adminApi("/admin/events", { method: "POST", body: JSON.stringify(body) });
      setSuccess(response.message);
      setDialog(null);
      await load();
    } catch (e) {
      setDialogError(e instanceof Error ? e.message : "Failed to save the event.");
    } finally {
      setBusy(false);
    }
  };

  const act = async (path, method, confirmText) => {
    if (confirmText && !window.confirm(confirmText)) return;
    try {
      const response = await adminApi(path, { method });
      setSuccess(response.message);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Request failed.");
    }
  };

  const audienceText = (event) => {
    if (event.audience_type === "branches") {
      return (event.audience_filter?.branches ?? []).map((b) => b.branch || b.programme).join(", ");
    }
    if (event.audience_type === "posting_applicants") {
      const posting = postings.find((p) => p.id === event.audience_filter?.job_posting_id);
      return `Applicants of ${posting ? `${posting.company?.name} — ${posting.title}` : "a posting"}`;
    }
    return "All students";
  };

  return (
    <>
      <PageHeader
        icon={<EventIcon />}
        title="Events"
        subtitle="Pre-placement talks, workshops and webinars. Publishing emails the chosen audience."
        backHref="/admin"
        backLabel="Back to Dashboard"
        actions={
          <Button variant="contained" color="secondary" startIcon={<AddIcon />} onClick={() => openEdit(null)}>
            New Event
          </Button>
        }
      />
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
      <Tabs value={when} onChange={(_e, v) => setWhen(v)} sx={{ mb: 2 }}>
        <Tab value="upcoming" label="Upcoming" />
        <Tab value="past" label="Past" />
      </Tabs>
      {!events && <LinearProgress />}
      {events && events.length === 0 && <Typography color="text.secondary">No {when} events.</Typography>}
      <Stack spacing={1.5}>
        {(events ?? []).map((event) => (
          <Card key={event.id}>
            <CardContent>
              <Stack direction={{ xs: "column", md: "row" }} justifyContent="space-between" spacing={2}>
                <Box sx={{ minWidth: 0 }}>
                  <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap>
                    <Typography fontWeight={700}>{event.title}</Typography>
                    <Chip size="small" variant="outlined" label={TYPES[event.event_type]} />
                    {event.published_at ? <Chip size="small" color="success" label="Published" /> : <Chip size="small" variant="outlined" label="Draft" />}
                  </Stack>
                  <Typography variant="body2" color="text.secondary">
                    {formatDateTime(event.starts_at)}
                    {event.company ? ` · ${event.company.name}` : ""}
                    {event.venue ? ` · ${event.venue}` : ""}
                  </Typography>
                  <Typography variant="caption" color="text.secondary">
                    Audience: {audienceText(event)} ({event.audience_count} student(s))
                  </Typography>
                </Box>
                <Stack direction="row" spacing={1} alignItems="flex-start">
                  {!event.published_at && (
                    <Button
                      variant="contained"
                      size="small"
                      onClick={() => act(`/admin/events/${event.id}/publish`, "POST", `Publish and email ${event.audience_count} student(s)?`)}
                    >
                      Publish
                    </Button>
                  )}
                  <Button size="small" onClick={() => openEdit(event)}>
                    Edit
                  </Button>
                  <Button size="small" color="error" onClick={() => act(`/admin/events/${event.id}`, "DELETE", `Delete "${event.title}"?`)}>
                    Delete
                  </Button>
                </Stack>
              </Stack>
            </CardContent>
          </Card>
        ))}
      </Stack>

      <Dialog open={Boolean(dialog)} onClose={() => !busy && setDialog(null)} maxWidth="md" fullWidth>
        <DialogTitle>{dialog?.id ? "Edit event" : "New event"}</DialogTitle>
        <DialogContent dividers>
          {dialog && (
            <Stack spacing={2}>
              {dialogError && <Alert severity="error">{dialogError}</Alert>}
              {dialog.published && <Alert severity="info">This event is already published; edits are not emailed again.</Alert>}
              <TextField label="Title" required value={dialog.title} onChange={(e) => setDialog((d) => ({ ...d, title: e.target.value }))} />
              <Stack direction={{ xs: "column", sm: "row" }} spacing={2}>
                <FormControl fullWidth>
                  <InputLabel id="ev-type">Type</InputLabel>
                  <Select labelId="ev-type" label="Type" value={dialog.event_type} onChange={(e) => setDialog((d) => ({ ...d, event_type: e.target.value }))}>
                    {Object.entries(TYPES).map(([k, v]) => (
                      <MenuItem key={k} value={k}>
                        {v}
                      </MenuItem>
                    ))}
                  </Select>
                </FormControl>
                <TextField
                  fullWidth
                  required
                  type="datetime-local"
                  label="Starts at (IST)"
                  value={dialog.starts_at}
                  onChange={(e) => setDialog((d) => ({ ...d, starts_at: e.target.value }))}
                  slotProps={{ inputLabel: { shrink: true } }}
                />
              </Stack>
              <Autocomplete
                options={companies}
                value={companies.find((c) => c.id === dialog.company_id) ?? null}
                onChange={(_e, value) => setDialog((d) => ({ ...d, company_id: value?.id ?? null }))}
                getOptionLabel={(c) => c.name}
                renderInput={(params) => <TextField {...params} label="Company (optional)" />}
              />
              <Stack direction={{ xs: "column", sm: "row" }} spacing={2}>
                <TextField fullWidth label="Venue (optional)" value={dialog.venue} onChange={(e) => setDialog((d) => ({ ...d, venue: e.target.value }))} />
                <TextField fullWidth label="Meeting link (optional)" value={dialog.meeting_link} onChange={(e) => setDialog((d) => ({ ...d, meeting_link: e.target.value }))} />
              </Stack>
              <Box>
                <Typography variant="subtitle2" gutterBottom>
                  Description (optional)
                </Typography>
                <RichTextEditor value={dialog.description} onChange={(value) => setDialog((d) => ({ ...d, description: value }))} />
              </Box>
              <FormControl>
                <Typography variant="subtitle2">Audience</Typography>
                <RadioGroup row value={dialog.audience_type} onChange={(e) => setDialog((d) => ({ ...d, audience_type: e.target.value }))}>
                  <FormControlLabel value="all" control={<Radio />} label="All students" />
                  <FormControlLabel value="branches" control={<Radio />} label="Programmes / branches" />
                  <FormControlLabel value="posting_applicants" control={<Radio />} label="Applicants of a posting" />
                </RadioGroup>
              </FormControl>
              {dialog.audience_type === "branches" && (
                <Autocomplete
                  multiple
                  options={branchOptions}
                  value={dialog.branches}
                  onChange={(_e, value) => setDialog((d) => ({ ...d, branches: value }))}
                  getOptionLabel={(o) => (o.branch ? `${o.branch} — ${o.programme}` : `All of ${o.programme}`)}
                  isOptionEqualToValue={(a, b) => a.programme === b.programme && (a.branch ?? null) === (b.branch ?? null)}
                  renderInput={(params) => <TextField {...params} label="Programmes / branches" />}
                />
              )}
              {dialog.audience_type === "posting_applicants" && (
                <FormControl fullWidth>
                  <InputLabel id="ev-posting">Posting</InputLabel>
                  <Select labelId="ev-posting" label="Posting" value={dialog.job_posting_id} onChange={(e) => setDialog((d) => ({ ...d, job_posting_id: e.target.value }))}>
                    {postings.map((p) => (
                      <MenuItem key={p.id} value={String(p.id)}>
                        {p.company?.name} — {p.title}
                      </MenuItem>
                    ))}
                  </Select>
                </FormControl>
              )}
            </Stack>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDialog(null)} disabled={busy}>
            Cancel
          </Button>
          <Button variant="contained" onClick={save} disabled={busy}>
            Save
          </Button>
        </DialogActions>
      </Dialog>
    </>
  );
}
