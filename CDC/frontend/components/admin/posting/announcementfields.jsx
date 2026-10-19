"use client";

import { useEffect, useMemo, useState } from "react";
import {
  Alert,
  Autocomplete,
  Box,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControl,
  FormControlLabel,
  InputLabel,
  MenuItem,
  Paper,
  Select,
  Stack,
  Switch,
  TextField,
  Typography,
} from "@mui/material";
import CampaignIcon from "@mui/icons-material/Campaign";

import { adminApi } from "@/lib/adminapi";
import useCatalogue from "@/lib/usecatalogue";

export const ANNOUNCE_AUDIENCES = [
  { value: "eligible", label: "Only eligible students" },
  { value: "all", label: "All students" },
  { value: "branches", label: "Specific branches" },
  { value: "programmes", label: "Specific courses" },
];

export const defaultTitle = (company, title) => `${company || "The company"}'s ${title || "job profile"} is open for applications`;

/** A fresh choice: both channels on, only eligible students (owner request 2026-10-19). */
export const defaultAnnouncement = () => ({
  notice: { enabled: true, audience: { type: "eligible", branches: [], programmes: [] }, title: "", message: "" },
  email: { enabled: true, audience: { type: "eligible", branches: [], programmes: [] }, subject: "", message: "" },
});

/** The choice stored on a job profile, ready for the form. */
export const announcementFrom = (stored) => {
  const base = defaultAnnouncement();
  if (!stored) return base;
  const channel = (key) => ({
    ...base[key],
    ...stored[key],
    audience: { ...base[key].audience, ...(stored[key]?.audience ?? {}) },
    title: stored[key]?.title ?? "",
    subject: stored[key]?.subject ?? "",
    message: stored[key]?.message ?? "",
  });
  return { notice: channel("notice"), email: channel("email") };
};

const cleanAudience = (a) =>
  a.type === "branches"
    ? { type: "branches", branches: a.branches.map((b) => ({ programme: b.programme, branch: b.branch })) }
    : a.type === "programmes"
      ? { type: "programmes", programmes: a.programmes }
      : { type: a.type };

/** The body for POST /admin/postings (announcement) and POST /admin/postings/{id}/announce. */
export const cleanAnnouncement = (value) => ({
  notice: { enabled: value.notice.enabled, audience: cleanAudience(value.notice.audience), title: value.notice.title.trim() || null, message: value.notice.message.trim() || null },
  email: { enabled: value.email.enabled, audience: cleanAudience(value.email.audience), subject: value.email.subject.trim() || null, message: value.email.message.trim() || null },
});

/** A message for the first incomplete audience, or null. */
export const announcementProblem = (value) => {
  for (const [key, label] of [["notice", "notice"], ["email", "email"]]) {
    const channel = value[key];
    if (!channel.enabled) continue;
    if (channel.audience.type === "branches" && channel.audience.branches.length === 0) return `Pick at least one branch for the ${label}.`;
    if (channel.audience.type === "programmes" && channel.audience.programmes.length === 0) return `Pick at least one course for the ${label}.`;
  }
  return null;
};

// Live size of an "all / branches / courses" audience; "eligible" uses the count the caller already has.
function useAudienceCount(audience, eligibleCount) {
  const [count, setCount] = useState(null);
  const groups = useMemo(() => {
    if (audience.type === "all") return [{ audience_type: "all", audience_filter: null }];
    if (audience.type === "branches") return audience.branches.length ? [{ audience_type: "branches", audience_filter: { branches: audience.branches } }] : null;
    if (audience.type === "programmes")
      return audience.programmes.length ? [{ audience_type: "branches", audience_filter: { branches: audience.programmes.map((programme) => ({ programme, branch: null })) } }] : null;
    return null;
  }, [audience]);
  const payload = groups ? JSON.stringify(groups) : null;

  useEffect(() => {
    if (!payload) return undefined;
    let cancelled = false;
    const timer = setTimeout(() => {
      adminApi("/admin/audiences/preview", { method: "POST", body: JSON.stringify({ kind: "notice", audiences: JSON.parse(payload) }) })
        .then((r) => !cancelled && setCount({ payload, count: r.count }))
        .catch(() => !cancelled && setCount(null));
    }, 300);
    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [payload]);

  if (audience.type === "eligible") return eligibleCount ?? null;
  return payload && count?.payload === payload ? count.count : null;
}

function AudienceChoice({ id, audience, onChange, disabled, eligibleCount }) {
  const catalogue = useCatalogue(adminApi);
  const branchOptions = useMemo(
    () => Object.entries(catalogue).flatMap(([programme, branches]) => branches.map((branch) => ({ programme, branch }))),
    [catalogue]
  );
  const programmeOptions = useMemo(() => Object.keys(catalogue), [catalogue]);
  const count = useAudienceCount(audience, eligibleCount);

  return (
    <Stack spacing={1.5}>
      <FormControl fullWidth size="small" disabled={disabled}>
        <InputLabel id={`${id}-audience`}>Target audience</InputLabel>
        <Select labelId={`${id}-audience`} label="Target audience" value={audience.type} onChange={(e) => onChange({ ...audience, type: e.target.value })}>
          {ANNOUNCE_AUDIENCES.map((option) => (
            <MenuItem key={option.value} value={option.value}>
              {option.label}
            </MenuItem>
          ))}
        </Select>
      </FormControl>
      {audience.type === "branches" && (
        <Autocomplete
          multiple
          size="small"
          disabled={disabled}
          options={branchOptions}
          groupBy={(o) => o.programme}
          value={audience.branches}
          onChange={(_e, v) => onChange({ ...audience, branches: v })}
          getOptionLabel={(o) => o.branch}
          isOptionEqualToValue={(a, b) => a.programme === b.programme && a.branch === b.branch}
          renderInput={(params) => <TextField {...params} label="Branches" />}
        />
      )}
      {audience.type === "programmes" && (
        <Autocomplete
          multiple
          size="small"
          disabled={disabled}
          options={programmeOptions}
          value={audience.programmes}
          onChange={(_e, v) => onChange({ ...audience, programmes: v })}
          renderInput={(params) => <TextField {...params} label="Courses" />}
        />
      )}
      {!disabled && count !== null && (
        <Typography variant="caption" color="text.secondary">
          {count} student(s) in this audience
        </Typography>
      )}
    </Stack>
  );
}

/**
 * "Notify students" when a job profile opens for applications: a notice on the student Notices page and an email,
 * each switched on or off with its own target audience and text. Used in the Open for Applications dialog and in the
 * job profile's Notify students dialog.
 */
export default function AnnouncementFields({ value, onChange, company, title, eligibleCount, disabled = false }) {
  const set = (key, patch) => onChange({ ...value, [key]: { ...value[key], ...patch } });
  const headline = defaultTitle(company, title);

  return (
    <Stack spacing={1.5}>
      <Paper variant="outlined" sx={{ p: 1.5 }}>
        <FormControlLabel
          control={<Switch checked={value.notice.enabled} disabled={disabled} onChange={(e) => set("notice", { enabled: e.target.checked })} />}
          label={<Typography fontWeight={600}>Post a notice on the Notices page</Typography>}
        />
        {value.notice.enabled && (
          <Stack spacing={1.5} sx={{ mt: 1 }}>
            <AudienceChoice id="notice" audience={value.notice.audience} onChange={(audience) => set("notice", { audience })} disabled={disabled} eligibleCount={eligibleCount} />
            <TextField
              size="small"
              label="Headline"
              placeholder={headline}
              value={value.notice.title}
              disabled={disabled}
              onChange={(e) => set("notice", { title: e.target.value })}
              helperText={`Leave empty to use "${headline}".`}
              inputProps={{ maxLength: 255 }}
            />
            <TextField
              size="small"
              label="Message (optional)"
              value={value.notice.message}
              disabled={disabled}
              onChange={(e) => set("notice", { message: e.target.value })}
              multiline
              minRows={2}
              inputProps={{ maxLength: 5000 }}
              helperText="Shown first when a student opens the notice, above the courses, eligibility, stages and deadline."
            />
          </Stack>
        )}
      </Paper>
      <Paper variant="outlined" sx={{ p: 1.5 }}>
        <FormControlLabel
          control={<Switch checked={value.email.enabled} disabled={disabled} onChange={(e) => set("email", { enabled: e.target.checked })} />}
          label={<Typography fontWeight={600}>Send an email</Typography>}
        />
        {value.email.enabled && (
          <Stack spacing={1.5} sx={{ mt: 1 }}>
            <AudienceChoice id="email" audience={value.email.audience} onChange={(audience) => set("email", { audience })} disabled={disabled} eligibleCount={eligibleCount} />
            <TextField
              size="small"
              label="Subject"
              placeholder={`New opening: ${company || "Company"} — ${title || "Job profile"}`}
              value={value.email.subject}
              disabled={disabled}
              onChange={(e) => set("email", { subject: e.target.value })}
              helperText="Leave empty for the standard subject."
              inputProps={{ maxLength: 255 }}
            />
            <TextField
              size="small"
              label="Message (optional)"
              value={value.email.message}
              disabled={disabled}
              onChange={(e) => set("email", { message: e.target.value })}
              multiline
              minRows={2}
              inputProps={{ maxLength: 5000 }}
              helperText="The email always lists the company, role, deadline and a View & Apply button. A student is emailed about a job profile only once."
            />
          </Stack>
        )}
      </Paper>
    </Stack>
  );
}

/** The job profile's "Notify students" button and dialog (Overview tab). */
export function NotifyStudentsButton({ posting, disabled, onDone, onError }) {
  const [open, setOpen] = useState(false);
  const [value, setValue] = useState(defaultAnnouncement);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);
  const info = posting.announcement ?? {};

  const start = () => {
    // Start from the choice made when the job profile opened, with the email on only if nobody was emailed yet.
    const current = announcementFrom(info);
    setValue(info.emailed_count ? { ...current, email: { ...current.email, enabled: false } } : current);
    setError(null);
    setOpen(true);
  };

  const submit = async () => {
    const problem = announcementProblem(value);
    if (problem) {
      setError(problem);
      return;
    }
    setSaving(true);
    setError(null);
    try {
      const response = await adminApi(`/admin/postings/${posting.id}/announce`, { method: "POST", body: JSON.stringify({ announcement: cleanAnnouncement(value) }) });
      setOpen(false);
      await onDone?.(response.message);
    } catch (e) {
      const message = e instanceof Error ? e.message : "Could not notify students.";
      setError(message);
      onError?.(message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <>
      <Button variant="outlined" startIcon={<CampaignIcon />} disabled={disabled} onClick={start}>
        Notify students
      </Button>
      <Dialog open={open} onClose={() => !saving && setOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Notify students</DialogTitle>
        <DialogContent dividers>
          <Stack spacing={2}>
            {error && <Alert severity="error">{error}</Alert>}
            <Box>
              <Typography variant="body2" color="text.secondary">
                {info.notice_posted ? "The notice is on the Notices page; saving updates its audience and text." : "No notice has been posted for this job profile yet."}{" "}
                {info.emailed_count ? `${info.emailed_count} student(s) were already emailed and will not be emailed again.` : "No student has been emailed yet."}
                {posting.is_scheduled ? " This job profile opens later, so your choice is applied when it opens." : ""}
              </Typography>
            </Box>
            <AnnouncementFields value={value} onChange={setValue} company={posting.company?.name} title={posting.title} eligibleCount={posting.stats?.eligible} disabled={saving} />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setOpen(false)} disabled={saving}>
            Cancel
          </Button>
          <Button variant="contained" onClick={submit} disabled={saving || (!value.notice.enabled && !value.email.enabled)}>
            {saving ? "Sending..." : posting.is_scheduled ? "Save" : "Notify"}
          </Button>
        </DialogActions>
      </Dialog>
    </>
  );
}
