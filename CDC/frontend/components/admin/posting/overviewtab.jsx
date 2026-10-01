"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
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
import { formatDateTime, formatMoney, fromLocalInput, titleCase, toLocalInput } from "@/lib/format";
import { OFFER_CATEGORIES, selectionConsequence } from "@/lib/offerpolicy";

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
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    setDeadline(toLocalInput(posting.application_deadline));
    setShare(Boolean(posting.share_contact_details));
    setOfferType(posting.offer_type);
  }, [posting.application_deadline, posting.share_contact_details, posting.offer_type]);

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
      <Grid container spacing={2}>
        <Grid size={{ xs: 6, md: 2.4 }}>
          <Stat label="Eligible students" value={s.eligible} />
        </Grid>
        <Grid size={{ xs: 6, md: 2.4 }}>
          <Stat label="Applied" value={s.applied} color="primary.main" />
        </Grid>
        <Grid size={{ xs: 6, md: 2.4 }}>
          <Stat label="Withdrawn" value={s.withdrawn} />
        </Grid>
        <Grid size={{ xs: 6, md: 2.4 }}>
          <Stat label="Unverified resume" value={s.unverified_resume} color="warning.main" />
        </Grid>
        <Grid size={{ xs: 12, md: 2.4 }}>
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
                  <strong>Cycle:</strong>{" "}
                  <Link href={`/admin/placement-cycles/${posting.placement_cycle?.id}`}>{posting.placement_cycle?.name}</Link>
                </Typography>
                <Typography variant="body2">
                  <strong>Type:</strong> {posting.type === "fulltime" ? "Full Time" : "Internship"}
                </Typography>
                <Typography variant="body2">
                  <strong>Offer category:</strong> {posting.offer_label}
                </Typography>
                <Typography variant="body2">
                  <strong>Compensation:</strong>{" "}
                  {comp.ctc_annual ? `${formatMoney(comp.ctc_annual, comp.currency)} p.a.` : ""}
                  {comp.stipend_monthly ? `${formatMoney(comp.stipend_monthly, comp.currency)} / month` : ""}
                  {!comp.ctc_annual && !comp.stipend_monthly ? "—" : ""}
                  {comp.duration_weeks ? ` · ${comp.duration_weeks} weeks` : ""}
                  {comp.ppo ? " · PPO possible" : ""}
                </Typography>
                <Typography variant="body2">
                  <strong>Floated:</strong> {formatDateTime(posting.floated_at)} by {posting.floated_by?.name ?? "—"}
                </Typography>
                <Typography variant="body2">
                  <strong>Status:</strong> {titleCase(posting.status)}
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
            Lifecycle
          </Typography>
          <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
            Open → Close applications (in process) → results published (completed). Cancelling hides the posting from students.
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
                  window.confirm("Cancel this posting? It disappears from the student job board.") &&
                  call(`/admin/postings/${posting.id}/cancel`, { method: "PATCH" })
                }
              >
                Cancel posting
              </Button>
            )}
          </Stack>
        </CardContent>
      </Card>
    </Stack>
  );
}
