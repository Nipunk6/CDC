"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  Checkbox,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControl,
  FormControlLabel,
  InputLabel,
  MenuItem,
  Select,
  Stack,
  Switch,
  TextField,
  Typography,
} from "@mui/material";
import SendIcon from "@mui/icons-material/Send";
import OpenInNewIcon from "@mui/icons-material/OpenInNew";

import QuestionBuilder, { cleanQuestions } from "@/components/admin/questionbuilder";
import BlockingRules from "@/components/shared/blockingrules";
import { adminApi } from "@/lib/adminapi";
import { formatDateTime, fromLocalInput, statusColor, titleCase, toLocalInput } from "@/lib/format";
import { OFFER_CATEGORIES, selectionConsequence } from "@/lib/offerpolicy";

// 23:59 IST a week from now.
const defaultDeadline = () => `${toLocalInput(Date.now() + 7 * 24 * 60 * 60 * 1000).slice(0, 10)}T23:59`;

/**
 * "Float to students" for an accepted JNF/INF (spec M5.3). Renders a status card with either the
 * existing posting or a Float button + dialog. Dropped into the Phase 1 admin JNF/INF detail pages.
 */
export default function FloatDialog({ formType, formId, formStatus }) {
  const [posting, setPosting] = useState(undefined);
  const [cycles, setCycles] = useState([]);
  const [open, setOpen] = useState(false);
  const [cycleId, setCycleId] = useState("");
  const [offerType, setOfferType] = useState(OFFER_CATEGORIES[formType === "inf" ? "inf" : "jnf"][0].value);
  const [deadline, setDeadline] = useState(defaultDeadline);
  const [shareContacts, setShareContacts] = useState(false);
  const [questions, setQuestions] = useState([]);
  const [preview, setPreview] = useState(null);
  const [error, setError] = useState(null);
  const [saving, setSaving] = useState(false);
  const [confirmed, setConfirmed] = useState(false);

  const wantedCycleType = formType === "inf" ? "internship" : "fulltime";

  const loadPosting = useCallback(async () => {
    try {
      const response = await adminApi(`/admin/postings/for-form?form_type=${formType}&form_id=${formId}`);
      setPosting(response.posting);
    } catch {
      setPosting(null);
    }
  }, [formType, formId]);

  useEffect(() => {
    if (formStatus === "accepted") void loadPosting();
  }, [formStatus, loadPosting]);

  useEffect(() => {
    if (!open) return;
    adminApi("/admin/placement-cycles")
      .then((response) => {
        const usable = (response.placement_cycles ?? []).filter((c) => c.status === "open" && c.type === wantedCycleType);
        setCycles(usable);
        if (usable.length === 1) setCycleId(String(usable[0].id));
      })
      .catch((e) => setError(e.message));
  }, [open, wantedCycleType]);

  useEffect(() => {
    if (!open || !cycleId) {
      setPreview(null);
      return;
    }
    let cancelled = false;
    adminApi(`/admin/postings/preview-eligibility?form_type=${formType}&form_id=${formId}&cycle_id=${cycleId}`)
      .then((response) => !cancelled && setPreview(response))
      .catch(() => !cancelled && setPreview(null));
    return () => {
      cancelled = true;
    };
  }, [open, cycleId, formType, formId]);

  if (formStatus !== "accepted" || posting === undefined) return null;

  const submit = async () => {
    setError(null);
    if (!cycleId) {
      setError("Choose a placement cycle.");
      return;
    }
    const cleaned = cleanQuestions(questions);
    if (typeof cleaned === "string") {
      setError(cleaned);
      return;
    }
    setSaving(true);
    try {
      await adminApi("/admin/postings", {
        method: "POST",
        body: JSON.stringify({
          form_type: formType,
          form_id: formId,
          placement_cycle_id: Number(cycleId),
          offer_type: offerType,
          application_deadline: fromLocalInput(deadline),
          share_contact_details: shareContacts,
          questions: cleaned,
        }),
      });
      setOpen(false);
      await loadPosting();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not float this form.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <Card variant="outlined" sx={{ mb: 2, borderColor: posting ? "success.light" : "primary.light" }}>
      <CardContent>
        {posting ? (
          <Stack direction={{ xs: "column", sm: "row" }} spacing={1.5} justifyContent="space-between" alignItems={{ sm: "center" }}>
            <Box>
              <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap>
                <Typography fontWeight={700}>Floated to students</Typography>
                <Chip size="small" variant="outlined" color={statusColor(posting.status)} label={titleCase(posting.status)} />
                {posting.offer_label && <Chip size="small" variant="outlined" label={posting.offer_label} />}
              </Stack>
              <Typography variant="body2" color="text.secondary">
                {posting.placement_cycle?.name} · apply by {formatDateTime(posting.application_deadline)}
              </Typography>
            </Box>
            <Button component={Link} href={`/admin/postings/${posting.id}`} variant="contained" endIcon={<OpenInNewIcon />}>
              Open Posting
            </Button>
          </Stack>
        ) : (
          <Stack direction={{ xs: "column", sm: "row" }} spacing={1.5} justifyContent="space-between" alignItems={{ sm: "center" }}>
            <Box>
              <Typography fontWeight={700}>Not visible to students yet</Typography>
              <Typography variant="body2" color="text.secondary">
                Float this accepted {formType.toUpperCase()} into {wantedCycleType === "fulltime" ? "a full-time" : "an internship"} cycle to open applications.
              </Typography>
            </Box>
            <Button
              variant="contained"
              startIcon={<SendIcon />}
              onClick={() => {
                setError(null);
                setConfirmed(false);
                setOpen(true);
              }}
            >
              Float to Students
            </Button>
          </Stack>
        )}
      </CardContent>

      <Dialog open={open} onClose={() => !saving && setOpen(false)} maxWidth="md" fullWidth>
        <DialogTitle>Float to students</DialogTitle>
        <DialogContent dividers>
          <Stack spacing={2.5}>
            {error && <Alert severity="error">{error}</Alert>}
            {cycles.length === 0 && (
              <Alert severity="warning">
                There is no open {wantedCycleType === "fulltime" ? "full-time" : "internship"} placement cycle.{" "}
                <Link href="/admin/placement-cycles">Create one first.</Link>
              </Alert>
            )}
            <Stack direction={{ xs: "column", sm: "row" }} spacing={2}>
              <FormControl fullWidth>
                <InputLabel id="float-cycle">Placement cycle</InputLabel>
                <Select labelId="float-cycle" label="Placement cycle" value={cycleId} onChange={(e) => setCycleId(e.target.value)}>
                  {cycles.map((cycle) => (
                    <MenuItem key={cycle.id} value={String(cycle.id)}>
                      {cycle.name} ({cycle.enrolled_students_count} enrolled)
                    </MenuItem>
                  ))}
                </Select>
              </FormControl>
              <TextField
                fullWidth
                type="datetime-local"
                label="Application deadline (IST)"
                value={deadline}
                onChange={(e) => setDeadline(e.target.value)}
                slotProps={{ inputLabel: { shrink: true } }}
              />
            </Stack>
            <FormControl fullWidth>
              <InputLabel id="float-offer-type">Offer category</InputLabel>
              <Select labelId="float-offer-type" label="Offer category" value={offerType} onChange={(e) => setOfferType(e.target.value)}>
                {OFFER_CATEGORIES[formType === "inf" ? "inf" : "jnf"].map((category) => (
                  <MenuItem key={category.value} value={category.value}>
                    {category.label}
                  </MenuItem>
                ))}
              </Select>
              <Typography variant="caption" color="text.secondary" sx={{ mt: 0.75 }}>
                Shown to students. {selectionConsequence(offerType)}
              </Typography>
            </FormControl>
            <BlockingRules />
            {preview && (
              <Alert severity={preview.eligible_count > 0 ? "info" : "warning"}>
                <strong>{preview.eligible_count}</strong> of {preview.enrolled_count} enrolled students are eligible and will be emailed
                when you float this posting.
              </Alert>
            )}
            <FormControlLabel
              control={<Switch checked={shareContacts} onChange={(e) => setShareContacts(e.target.checked)} />}
              label="Share applicants' phone and personal email with the company in exports"
            />
            <Box>
              <Typography variant="subtitle1" fontWeight={700}>
                Application questions
              </Typography>
              <Typography variant="body2" color="text.secondary" sx={{ mb: 1.5 }}>
                Optional. Students answer these when they apply; you can edit them until the deadline.
              </Typography>
              <QuestionBuilder value={questions} onChange={setQuestions} />
            </Box>
            <FormControlLabel
              control={<Checkbox checked={confirmed} onChange={(e) => setConfirmed(e.target.checked)} />}
              label="I understand eligible students are notified immediately."
            />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setOpen(false)} disabled={saving}>
            Cancel
          </Button>
          <Button variant="contained" startIcon={<SendIcon />} onClick={submit} disabled={saving || !cycleId || !confirmed}>
            {saving ? "Floating..." : "Float Posting"}
          </Button>
        </DialogActions>
      </Dialog>
    </Card>
  );
}
