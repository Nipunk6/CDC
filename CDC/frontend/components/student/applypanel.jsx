"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import {
  Alert,
  AlertTitle,
  Box,
  Button,
  Checkbox,
  Divider,
  FormControl,
  FormControlLabel,
  FormGroup,
  FormLabel,
  InputLabel,
  List,
  ListItem,
  ListItemText,
  MenuItem,
  Radio,
  RadioGroup,
  Select,
  Stack,
  TextField,
  Typography,
} from "@mui/material";

import { studentApi } from "@/lib/studentapi";
import { formatDateTime } from "@/lib/format";
import { studentConsequence } from "@/lib/offerpolicy";
import { countdown } from "@/components/student/postingcard";

const resumeLabel = (resume) =>
  `${resume.label}${resume.status === "approved" ? " — ✓ verified" : resume.status === "rejected" ? " — ✗ rejected" : " — ⚠ pending verification"}`;

const answersFromApplication = (application) =>
  Object.fromEntries((application?.answers ?? []).map((a) => [a.question_id, a.answer]));

/**
 * Apply / change resume & answers / withdraw. The server re-checks everything (spec B2/B3).
 */
export default function ApplyPanel({ posting, onChanged }) {
  const [resumes, setResumes] = useState(null);
  const application = posting.application;
  const applied = application?.status === "applied";
  const [resumeId, setResumeId] = useState(application?.resume_id ? String(application.resume_id) : "");
  const [answers, setAnswers] = useState(answersFromApplication(application));
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const [reasons, setReasons] = useState([]);
  const [success, setSuccess] = useState(null);

  useEffect(() => {
    studentApi("/student/resumes")
      .then((response) => setResumes(response.resumes ?? []))
      .catch(() => setResumes([]));
  }, []);

  useEffect(() => {
    setResumeId(application?.resume_id ? String(application.resume_id) : "");
    setAnswers(answersFromApplication(application));
  }, [application]);

  const selectedResume = useMemo(() => (resumes ?? []).find((r) => String(r.id) === resumeId), [resumes, resumeId]);
  const eligible = posting.eligibility?.eligible;
  const open = posting.accepts_applications;

  const payload = () => ({
    resume_id: Number(resumeId),
    answers: (posting.questions ?? []).map((q) => ({ question_id: q.id, answer: answers[q.id] ?? (q.qtype === "mcq_multi" ? [] : "") })),
  });

  const run = async (path, init, message) => {
    setBusy(true);
    setError(null);
    setReasons([]);
    setSuccess(null);
    try {
      const response = await studentApi(path, init);
      setSuccess(message ?? response.message);
      await onChanged();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Something went wrong.");
      setReasons(e?.payload?.reasons ?? []);
    } finally {
      setBusy(false);
    }
  };

  const toggleMulti = (questionId, option, checked) =>
    setAnswers((prev) => {
      const current = Array.isArray(prev[questionId]) ? prev[questionId] : [];
      return { ...prev, [questionId]: checked ? [...current, option] : current.filter((o) => o !== option) };
    });

  return (
    <Stack spacing={2}>
      <Box>
        <Typography variant="overline" color="text.secondary">
          Application deadline
        </Typography>
        <Typography fontWeight={700}>{formatDateTime(posting.application_deadline)}</Typography>
        {open && (
          <Typography variant="body2" color="warning.main" fontWeight={600}>
            {countdown(posting.application_deadline)}
          </Typography>
        )}
      </Box>

      {!eligible && (
        <Alert severity="error">
          <AlertTitle>You are not eligible</AlertTitle>
          <List dense disablePadding>
            {(posting.eligibility?.reasons ?? []).map((reason) => (
              <ListItem key={reason} disableGutters sx={{ py: 0 }}>
                <ListItemText primary={`• ${reason}`} />
              </ListItem>
            ))}
          </List>
        </Alert>
      )}

      {applied && application.used_unverified_resume && (
        <Alert severity="warning">
          <AlertTitle>Get your resume verified ASAP</AlertTitle>
          The resume attached to this application is not verified by the CDC yet.
        </Alert>
      )}
      {applied && <Alert severity="success">You applied on {formatDateTime(application.applied_at)}.</Alert>}
      {application?.status === "withdrawn" && <Alert severity="info">You withdrew this application. You can apply again until the deadline.</Alert>}
      {!open && <Alert severity="info">Applications are closed for this posting.</Alert>}

      {error && (
        <Alert severity="error">
          {error}
          {reasons.length > 0 && (
            <Box component="ul" sx={{ m: 0, pl: 2 }}>
              {reasons.map((r) => (
                <li key={r}>{r}</li>
              ))}
            </Box>
          )}
        </Alert>
      )}
      {success && <Alert severity="success">{success}</Alert>}

      {open && (eligible || applied) && (
        <>
          <Divider />
          {resumes && resumes.length === 0 ? (
            <Alert severity="warning">
              Upload a resume first. <Link href="/student/resumes">Go to My Resumes</Link>
            </Alert>
          ) : (
            <FormControl fullWidth size="small">
              <InputLabel id="apply-resume">Resume</InputLabel>
              <Select labelId="apply-resume" label="Resume" value={resumeId} onChange={(e) => setResumeId(e.target.value)}>
                {(resumes ?? []).map((resume) => (
                  <MenuItem key={resume.id} value={String(resume.id)}>
                    Slot {resume.slot}: {resumeLabel(resume)}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
          )}
          {selectedResume && selectedResume.status !== "approved" && (
            <Alert severity="warning">
              This resume is not verified. You can still apply, but your application will be flagged until the CDC verifies it.
            </Alert>
          )}

          {(posting.questions ?? []).map((q) => (
            <Box key={q.id}>
              {q.qtype === "text" && (
                <TextField
                  fullWidth
                  multiline
                  minRows={2}
                  size="small"
                  label={`${q.question}${q.required ? " *" : ""}`}
                  value={answers[q.id] ?? ""}
                  onChange={(e) => setAnswers((prev) => ({ ...prev, [q.id]: e.target.value }))}
                  inputProps={{ maxLength: 2000 }}
                />
              )}
              {q.qtype === "mcq_single" && (
                <FormControl>
                  <FormLabel>{`${q.question}${q.required ? " *" : ""}`}</FormLabel>
                  <RadioGroup value={answers[q.id] ?? ""} onChange={(e) => setAnswers((prev) => ({ ...prev, [q.id]: e.target.value }))}>
                    {(q.options ?? []).map((option) => (
                      <FormControlLabel key={option} value={option} control={<Radio size="small" />} label={option} />
                    ))}
                  </RadioGroup>
                </FormControl>
              )}
              {q.qtype === "mcq_multi" && (
                <FormControl>
                  <FormLabel>{`${q.question}${q.required ? " *" : ""}`}</FormLabel>
                  <FormGroup>
                    {(q.options ?? []).map((option) => (
                      <FormControlLabel
                        key={option}
                        control={
                          <Checkbox
                            size="small"
                            checked={Array.isArray(answers[q.id]) && answers[q.id].includes(option)}
                            onChange={(e) => toggleMulti(q.id, option, e.target.checked)}
                          />
                        }
                        label={option}
                      />
                    ))}
                  </FormGroup>
                </FormControl>
              )}
            </Box>
          ))}

          {applied ? (
            <Stack direction={{ xs: "column", sm: "row", md: "column", lg: "row" }} spacing={1}>
              <Button
                variant="contained"
                fullWidth
                disabled={busy || !resumeId}
                onClick={() => run(`/student/applications/${application.id}`, { method: "PATCH", body: JSON.stringify(payload()) })}
              >
                Save changes
              </Button>
              <Button
                variant="outlined"
                color="error"
                fullWidth
                disabled={busy}
                onClick={() =>
                  window.confirm("Withdraw this application? You can apply again before the deadline.") &&
                  run(`/student/applications/${application.id}/withdraw`, { method: "POST" })
                }
              >
                Withdraw
              </Button>
            </Stack>
          ) : (
            <>
              <Alert severity="info" variant="outlined">
                {studentConsequence(posting.offer_type)}
              </Alert>
              <Button
                variant="contained"
                size="large"
                disabled={busy || !resumeId}
                onClick={() => run(`/student/postings/${posting.id}/apply`, { method: "POST", body: JSON.stringify(payload()) })}
              >
                {application?.status === "withdrawn" ? "Apply again" : "Apply"}
              </Button>
            </>
          )}
        </>
      )}

      {open && !eligible && !applied && (
        <Button variant="contained" size="large" disabled>
          Apply
        </Button>
      )}
    </Stack>
  );
}
