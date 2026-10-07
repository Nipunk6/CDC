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
import CheckCircleIcon from "@mui/icons-material/CheckCircle";
import RadioButtonUncheckedIcon from "@mui/icons-material/RadioButtonUnchecked";

import QuestionBuilder, { cleanQuestions } from "@/components/admin/questionbuilder";
import BlockingRules from "@/components/shared/blockingrules";
import StudentCategoryPicker from "@/components/admin/studentcategorypicker";
import { adminApi } from "@/lib/adminapi";
import { formatDateTime, fromLocalInput, statusColor, postingStatusLabel, toLocalInput } from "@/lib/format";
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
  const [scheduleLater, setScheduleLater] = useState(false);
  const [openAt, setOpenAt] = useState("");
  const [visitDate, setVisitDate] = useState("");
  const [categories, setCategories] = useState([]);

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
        const usable = (response.placement_cycles ?? []).filter((c) => c.status === "open" && !c.is_draft && c.type === wantedCycleType); // drafts take no job profiles (S8.3)
        setCycles(usable);
        // "Add New Job" (S6.1) lands here with ?cycle=<id>: pre-select that placement.
        const remembered = new URLSearchParams(window.location.search).get("cycle");
        if (remembered && usable.some((c) => String(c.id) === remembered)) setCycleId(remembered);
        else if (usable.length === 1) setCycleId(String(usable[0].id));
      })
      .catch((e) => setError(e.message));
  }, [open, wantedCycleType]);

  useEffect(() => {
    if (!open || !cycleId) {
      setPreview(null);
      return;
    }
    let cancelled = false;
    const categoryQuery = categories.map((c) => `&allowed_student_categories[]=${c}`).join("");
    adminApi(`/admin/postings/preview-eligibility?form_type=${formType}&form_id=${formId}&cycle_id=${cycleId}${categoryQuery}`)
      .then((response) => !cancelled && setPreview(response))
      .catch(() => !cancelled && setPreview(null));
    return () => {
      cancelled = true;
    };
  }, [open, cycleId, formType, formId, categories]);

  if (formStatus !== "accepted" || posting === undefined) return null;

  const submit = async () => {
    setError(null);
    if (!cycleId) {
      setError("Choose a placement.");
      return;
    }
    const cleaned = cleanQuestions(questions);
    if (typeof cleaned === "string") {
      setError(cleaned);
      return;
    }
    if (scheduleLater && (!openAt || Number.isNaN(new Date(openAt).getTime()))) {
      setError("Choose when applications should open.");
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
          ...(scheduleLater && openAt ? { scheduled_open_at: fromLocalInput(openAt) } : {}),
          ...(visitDate ? { visit_date: visitDate } : {}),
          ...(categories.length ? { allowed_student_categories: categories } : {}),
        }),
      });
      setOpen(false);
      await loadPosting();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not open this form for applications.");
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
                <Typography fontWeight={700}>Open For Applications</Typography>
                <Chip size="small" variant="outlined" color={statusColor(posting.status)} label={postingStatusLabel(posting.status)} />
                {posting.offer_label && <Chip size="small" variant="outlined" label={posting.offer_label} />}
              </Stack>
              <Typography variant="body2" color="text.secondary">
                {posting.placement_cycle?.name} · apply by {formatDateTime(posting.application_deadline)}
              </Typography>
              {posting.is_scheduled && (
                <Typography variant="body2" color="warning.main">
                  Scheduled to open on {formatDateTime(posting.scheduled_open_at)}
                </Typography>
              )}
            </Box>
            <Button component={Link} href={`/admin/postings/${posting.id}`} variant="contained" endIcon={<OpenInNewIcon />}>
              Open Job Profile
            </Button>
          </Stack>
        ) : (
          <Stack direction={{ xs: "column", sm: "row" }} spacing={1.5} justifyContent="space-between" alignItems={{ sm: "center" }}>
            <Box>
              <Typography fontWeight={700}>Not visible to students yet</Typography>
              <Typography variant="body2" color="text.secondary">
                Open this accepted {formType.toUpperCase()} for applications in {wantedCycleType === "fulltime" ? "a full-time" : "an internship"} placement.
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
              Open Profile for Applications
            </Button>
          </Stack>
        )}
      </CardContent>

      <Dialog open={open} onClose={() => !saving && setOpen(false)} maxWidth="md" fullWidth>
        <DialogTitle>Open for Applications</DialogTitle>
        <DialogContent dividers>
          <Stack spacing={2.5}>
            {error && <Alert severity="error">{error}</Alert>}
            {cycles.length === 0 && (
              <Alert severity="warning">
                There is no open {wantedCycleType === "fulltime" ? "full-time" : "internship"} placement.{" "}
                <Link href="/admin/placement-cycles">Create one first.</Link>
              </Alert>
            )}
            <Stack direction={{ xs: "column", sm: "row" }} spacing={2}>
              <FormControl fullWidth>
                <InputLabel id="float-cycle">Placement</InputLabel>
                <Select labelId="float-cycle" label="Placement" value={cycleId} onChange={(e) => setCycleId(e.target.value)}>
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
            <StudentCategoryPicker value={categories} onChange={setCategories} />
            <BlockingRules />
            {preview && (
              <Alert severity={preview.eligible_count > 0 ? "info" : "warning"}>
                <strong>{preview.eligible_count}</strong> of {preview.enrolled_count} enrolled students are eligible and will be emailed
                when you open this job profile for applications.
              </Alert>
            )}
            <FormControlLabel
              control={<Switch checked={shareContacts} onChange={(e) => setShareContacts(e.target.checked)} />}
              label="Share applicants' phone and personal email with the company in exports"
            />
            <Box>
              <Typography variant="subtitle1" fontWeight={700}>
                Additional Questions
              </Typography>
              <Typography variant="body2" color="text.secondary" sx={{ mb: 1.5 }}>
                Optional. Students answer these when they apply; you can edit them until the deadline.
              </Typography>
              <QuestionBuilder value={questions} onChange={setQuestions} />
            </Box>
            <Box>
              <FormControlLabel
                control={<Switch checked={scheduleLater} onChange={(e) => setScheduleLater(e.target.checked)} />}
                label="Schedule For Later"
              />
              {scheduleLater && (
                <TextField
                  fullWidth
                  type="datetime-local"
                  label="Open applications at (IST)"
                  helperText="The job profile stays hidden from students until then; eligible students are emailed when it opens."
                  value={openAt}
                  onChange={(e) => setOpenAt(e.target.value)}
                  slotProps={{ inputLabel: { shrink: true } }}
                  sx={{ mt: 1 }}
                />
              )}
            </Box>
            <TextField
              type="date"
              label="Date of Visit / Process (optional)"
              value={visitDate}
              onChange={(e) => setVisitDate(e.target.value)}
              slotProps={{ inputLabel: { shrink: true } }}
              sx={{ maxWidth: { sm: 320 } }}
            />
            <Box sx={{ p: 1.5, borderRadius: 1, bgcolor: "action.hover" }}>
              <Typography variant="subtitle2" fontWeight={700} gutterBottom>
                Steps to publish
              </Typography>
              {[
                { done: true, label: `${formType.toUpperCase()} accepted` },
                { done: Boolean(cycleId), label: "Open placement chosen" },
                { done: Boolean(deadline) && new Date(fromLocalInput(deadline)).getTime() > Date.now(), label: "Application deadline set (in the future)" },
                {
                  done: (preview?.stages ?? 0) > 0,
                  label: preview
                    ? preview.stages > 0
                      ? `${preview.stages} stage(s) from the form`
                      : "No stages on the form: one \"Selection\" stage will be created (add more on the Stages tab)"
                    : "Stages present",
                },
                { done: confirmed, label: `Additional Questions reviewed (${questions.length} added)` },
              ].map((step) => (
                <Stack key={step.label} direction="row" spacing={1} alignItems="center">
                  {step.done ? <CheckCircleIcon fontSize="small" color="success" /> : <RadioButtonUncheckedIcon fontSize="small" color="disabled" />}
                  <Typography variant="body2">{step.label}</Typography>
                </Stack>
              ))}
            </Box>
            <FormControlLabel
              control={<Checkbox checked={confirmed} onChange={(e) => setConfirmed(e.target.checked)} />}
              label={
                scheduleLater
                  ? "I have reviewed the questions. Eligible students are notified when the job profile opens."
                  : "I have reviewed the questions and understand eligible students are notified immediately."
              }
            />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setOpen(false)} disabled={saving}>
            Cancel
          </Button>
          <Button variant="contained" startIcon={<SendIcon />} onClick={submit} disabled={saving || !cycleId || !confirmed}>
            {saving ? "Opening..." : scheduleLater ? "Schedule" : "Open for Applications"}
          </Button>
        </DialogActions>
      </Dialog>
    </Card>
  );
}
