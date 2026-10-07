"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
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
  Stack,
  Switch,
  TextField,
  Typography,
} from "@mui/material";

import { adminApi } from "@/lib/adminapi";
import { formatDate, formatDateTime, formatMoney, fromLocalInput, postingStatusLabel, toLocalInput } from "@/lib/format";
import LinkIcon from "@mui/icons-material/Link";
import ForwardToInboxIcon from "@mui/icons-material/ForwardToInbox";
import { OFFER_CATEGORIES, selectionConsequence } from "@/lib/offerpolicy";
import ProcessStatusCard from "@/components/admin/posting/processstatuscard";

const Stat = ({ label, value, color }) => (
  <Card variant="outlined" sx={{ height: "100%" }}>
    <CardContent>
      <Typography variant="h4" fontWeight={700} color={color ?? "text.primary"}>
        {value ?? 0}
      </Typography>
      <Typography variant="body2" color="text.secondary">
        {label}
      </Typography>
    </CardContent>
  </Card>
);

export default function OverviewTab({ posting, onChanged, onMessage }) {
  const [deadline, setDeadline] = useState(toLocalInput(posting.application_deadline));
  const [share, setShare] = useState(Boolean(posting.share_contact_details));
  const [offerType, setOfferType] = useState(posting.offer_type);
  const [visitDate, setVisitDate] = useState(posting.visit_date ?? "");
  const [openAt, setOpenAt] = useState(toLocalInput(posting.scheduled_open_at));
  const [sendOpen, setSendOpen] = useState(false);
  const [note, setNote] = useState("");
  const [copied, setCopied] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    setDeadline(toLocalInput(posting.application_deadline));
    setShare(Boolean(posting.share_contact_details));
    setOfferType(posting.offer_type);
    setVisitDate(posting.visit_date ?? "");
    setOpenAt(toLocalInput(posting.scheduled_open_at));
  }, [posting.application_deadline, posting.share_contact_details, posting.offer_type, posting.visit_date, posting.scheduled_open_at]);

  const call = async (path, init, success) => {
    setBusy(true);
    setError(null);
    try {
      const response = await adminApi(path, init);
      onMessage?.(success ?? response.message);
      await onChanged();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Request failed.");
    } finally {
      setBusy(false);
    }
  };

  const save = () => {
    const body = { share_contact_details: share };
    if (offerType !== posting.offer_type) body.offer_type = offerType;
    if (visitDate !== (posting.visit_date ?? "")) body.visit_date = visitDate || null;
    if (posting.is_scheduled && openAt && openAt !== toLocalInput(posting.scheduled_open_at)) body.scheduled_open_at = fromLocalInput(openAt);
    if (deadline !== toLocalInput(posting.application_deadline)) {
      if (!deadline || Number.isNaN(new Date(deadline).getTime())) {
        setError("Enter a valid application deadline.");
        return;
      }
      body.application_deadline = fromLocalInput(deadline);
    }
    void call(`/admin/postings/${posting.id}`, { method: "PATCH", body: JSON.stringify(body) });
  };

  const comp = posting.compensation ?? {};
  const editable = !["completed", "cancelled"].includes(posting.status);
  const s = posting.stats ?? {};

  return (
    <Stack spacing={3}>
      {error && <Alert severity="error">{error}</Alert>}
      <ProcessStatusCard posting={posting} />
      <Grid container spacing={2}>
        <Grid size={{ xs: 12, md: 3 }}>
          <Card variant="outlined" sx={{ height: "100%" }}>
            <CardContent>
              <Typography variant="body2" color="text.secondary">
                Application Progress
              </Typography>
              <Typography variant="h6" fontWeight={700} color="primary.main">
                {s.applied ?? 0} applied out of {s.eligible ?? 0} eligible
              </Typography>
            </CardContent>
          </Card>
        </Grid>
        <Grid size={{ xs: 6, md: 3 }}>
          <Stat label="Withdrawn" value={s.withdrawn} />
        </Grid>
        <Grid size={{ xs: 6, md: 3 }}>
          <Stat label="Unverified resume" value={s.unverified_resume} color="warning.main" />
        </Grid>
        <Grid size={{ xs: 12, md: 3 }}>
          <Stat label="Placed elsewhere" value={s.placed_elsewhere} color="error.main" />
        </Grid>
      </Grid>

      <Grid container spacing={2}>
        <Grid size={{ xs: 12, md: 6 }}>
          <Card variant="outlined">
            <CardContent>
              <Typography variant="subtitle1" fontWeight={700} gutterBottom>
                Details
              </Typography>
              <Stack spacing={1}>
                <Typography variant="body2">
                  <strong>Form:</strong>{" "}
                  <Link href={`/admin/${posting.form_type}s/${posting.form_id}`}>
                    {posting.form_type.toUpperCase()} #{posting.form_id}
                  </Link>
                </Typography>
                <Typography variant="body2">
                  <strong>Placement:</strong>{" "}
                  <Link href={`/admin/placement-cycles/${posting.placement_cycle?.id}`}>{posting.placement_cycle?.name}</Link>
                </Typography>
                <Typography variant="body2">
                  <strong>Type:</strong> {posting.type === "fulltime" ? "Full Time" : "Internship"}
                </Typography>
                <Typography variant="body2">
                  <strong>Offer category:</strong> {posting.offer_label}
                </Typography>
                <Typography variant="body2">
                  <strong>CTC Offered:</strong>{" "}
                  {comp.ctc_annual ? `${formatMoney(comp.ctc_annual, comp.currency)} p.a.` : ""}
                  {comp.stipend_monthly ? `${formatMoney(comp.stipend_monthly, comp.currency)} / month` : ""}
                  {!comp.ctc_annual && !comp.stipend_monthly ? "—" : ""}
                  {comp.duration_weeks ? ` · ${comp.duration_weeks} weeks` : ""}
                  {comp.ppo ? " · PPO possible" : ""}
                </Typography>
                <Typography variant="body2">
                  <strong>Opened for applications:</strong> {formatDateTime(posting.floated_at)} by {posting.floated_by?.name ?? "—"}
                </Typography>
                <Typography variant="body2">
                  <strong>Date of Visit / Process:</strong> {posting.visit_date ? formatDate(posting.visit_date) : "—"}
                </Typography>
                <Typography variant="body2">
                  <strong>Status:</strong> {postingStatusLabel(posting)}
                  {posting.is_scheduled ? ` · opens ${formatDateTime(posting.scheduled_open_at)}` : ""}
                </Typography>
              </Stack>
            </CardContent>
          </Card>
        </Grid>
        <Grid size={{ xs: 12, md: 6 }}>
          <Card variant="outlined">
            <CardContent>
              <Typography variant="subtitle1" fontWeight={700} gutterBottom>
                Settings
              </Typography>
              <Stack spacing={2}>
                <TextField
                  type="datetime-local"
                  label="Application deadline (IST)"
                  value={deadline}
                  disabled={!editable || busy}
                  onChange={(e) => setDeadline(e.target.value)}
                  slotProps={{ inputLabel: { shrink: true } }}
                />
                <FormControl fullWidth disabled={!editable || busy}>
                  <InputLabel id="overview-offer-type">Offer category</InputLabel>
                  <Select labelId="overview-offer-type" label="Offer category" value={offerType ?? ""} onChange={(e) => setOfferType(e.target.value)}>
                    {(OFFER_CATEGORIES[posting.form_type] ?? []).map((category) => (
                      <MenuItem key={category.value} value={category.value}>
                        {category.label}
                      </MenuItem>
                    ))}
                  </Select>
                  <Typography variant="caption" color="text.secondary" sx={{ mt: 0.75 }}>
                    {selectionConsequence(offerType)}
                  </Typography>
                </FormControl>
                {posting.is_scheduled && (
                  <Stack direction={{ xs: "column", sm: "row" }} spacing={1} alignItems={{ sm: "center" }}>
                    <TextField
                      type="datetime-local"
                      label="Open applications at (IST)"
                      value={openAt}
                      disabled={busy}
                      onChange={(e) => setOpenAt(e.target.value)}
                      slotProps={{ inputLabel: { shrink: true } }}
                      sx={{ flex: 1 }}
                    />
                    <Button
                      variant="outlined"
                      disabled={busy}
                      onClick={() =>
                        window.confirm("Open applications now? Eligible students are emailed straight away.") &&
                        call(`/admin/postings/${posting.id}/open-now`, { method: "POST" })
                      }
                    >
                      Open now
                    </Button>
                  </Stack>
                )}
                <TextField
                  type="date"
                  label="Date of Visit / Process"
                  value={visitDate}
                  disabled={!editable || busy}
                  onChange={(e) => setVisitDate(e.target.value)}
                  slotProps={{ inputLabel: { shrink: true } }}
                />
                <FormControlLabel
                  control={<Switch checked={share} disabled={!editable || busy} onChange={(e) => setShare(e.target.checked)} />}
                  label="Share applicants' phone and personal email with the company"
                />
                <Box>
                  <Button variant="contained" onClick={save} disabled={!editable || busy}>
                    Save
                  </Button>
                </Box>
              </Stack>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      <Card variant="outlined">
        <CardContent>
          <Typography variant="subtitle1" fontWeight={700} gutterBottom>
            Share
          </Typography>
          <Stack direction={{ xs: "column", sm: "row" }} spacing={1}>
            <Button
              variant="outlined"
              startIcon={<LinkIcon />}
              onClick={async () => {
                // The student link is login-gated and carries no personal data.
                try {
                  await navigator.clipboard.writeText(`${window.location.origin}/student/postings/${posting.id}`);
                  setCopied(true);
                  setTimeout(() => setCopied(false), 2000);
                } catch {
                  setError("Could not copy the link. Copy it from the address bar of the student job page instead.");
                }
              }}
            >
              {copied ? "Link copied" : "Copy Link"}
            </Button>
            <Button variant="outlined" startIcon={<ForwardToInboxIcon />} disabled={busy || posting.status === "cancelled"} onClick={() => setSendOpen(true)}>
              Send Applicant List to company
            </Button>
          </Stack>
          <Typography variant="caption" color="text.secondary" sx={{ display: "block", mt: 1 }}>
            Copy Link gives the student job page (students must sign in). Sending the list emails the company a 7-day download link to the same
            company-safe Excel they can download from their portal at any time.
          </Typography>
        </CardContent>
      </Card>

      <Dialog open={sendOpen} onClose={() => !busy && setSendOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Send Applicant List to company</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ pt: 1 }}>
            <Typography variant="body2">
              Every active portal user of {posting.company?.name ?? "the company"} gets an email with a download link (valid 7 days) to the
              applicant list: {s.applied ?? 0} live applicant(s), company-safe columns only
              {posting.share_contact_details ? ", with contact details" : ", without contact details"}.
            </Typography>
            <TextField label="Note to the company (optional)" value={note} onChange={(e) => setNote(e.target.value)} multiline minRows={2} inputProps={{ maxLength: 1000 }} />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setSendOpen(false)} disabled={busy}>
            Cancel
          </Button>
          <Button
            variant="contained"
            disabled={busy}
            onClick={async () => {
              await call(`/admin/postings/${posting.id}/send-applicant-list`, { method: "POST", body: JSON.stringify({ note: note || null }) });
              setSendOpen(false);
              setNote("");
            }}
          >
            Send
          </Button>
        </DialogActions>
      </Dialog>

      <Card variant="outlined">
        <CardContent>
          <Typography variant="subtitle1" fontWeight={700} gutterBottom>
            Lifecycle
          </Typography>
          <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
            Accepting Applications → Close applications (in process) → results published (completed). Cancelling hides the job profile from students.
          </Typography>
          <Stack direction={{ xs: "column", sm: "row" }} spacing={1}>
            {posting.status === "open" && (
              <Button
                variant="outlined"
                disabled={busy}
                onClick={() =>
                  window.confirm("Close applications now? Students can no longer apply, edit or withdraw.") &&
                  call(`/admin/postings/${posting.id}/close`, { method: "PATCH" })
                }
              >
                Close applications
              </Button>
            )}
            {posting.status === "in_process" && (
              <Button variant="outlined" disabled={busy} onClick={() => call(`/admin/postings/${posting.id}/reopen`, { method: "PATCH" })}>
                Reopen applications
              </Button>
            )}
            {["open", "in_process"].includes(posting.status) && (
              <Button
                variant="outlined"
                color="error"
                disabled={busy}
                onClick={() =>
                  window.confirm("Cancel this job profile? It disappears from the student job board.") &&
                  call(`/admin/postings/${posting.id}/cancel`, { method: "PATCH" })
                }
              >
                Cancel job profile
              </Button>
            )}
          </Stack>
        </CardContent>
      </Card>
    </Stack>
  );
}
