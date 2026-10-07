"use client";

import { use, useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { Alert, Box, Button, Card, CardContent, LinearProgress, Stack, Typography } from "@mui/material";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";
import CheckCircleIcon from "@mui/icons-material/CheckCircle";

import SurveyForm, { initialAnswers } from "@/components/shared/surveyform";
import { stripHtml } from "@/components/forms/shared";
import { studentApi, studentBlobUrl, studentUpload } from "@/lib/studentapi";
import { formatDateTime } from "@/lib/format";
import { plainText } from "@/lib/plaintext";

const isEmpty = (q, value, file, existing) => {
  if (q.qtype === "file") return !file && !existing;
  if (q.qtype === "rich_text") return !stripHtml(value ?? "");
  if (Array.isArray(value)) return value.length === 0;
  return value === null || value === undefined || String(value).trim() === "";
};

// Keep only the entries whose key is still a question of the (reloaded) survey.
const pickQuestions = (values, questions) => {
  const ids = new Set(questions.map((q) => String(q.id)));
  return Object.fromEntries(Object.entries(values ?? {}).filter(([k]) => ids.has(String(k))));
};

// Bring the first question with an error into view.
const scrollToFirstError = (questions, errors) => {
  const first = questions.find((q) => errors[q.id]);
  if (!first || typeof document === "undefined") return;
  requestAnimationFrame(() => document.getElementById(`survey-question-${first.id}`)?.scrollIntoView({ behavior: "smooth", block: "center" }));
};

function Answering({ survey, response, carry, onDone, onCancel, onReload }) {
  const previous = response
    ? Object.fromEntries(Object.entries(response.answers).filter(([, v]) => !(v && typeof v === "object" && !Array.isArray(v))))
    : {};
  const existingFiles = response
    ? Object.fromEntries(Object.entries(response.answers).filter(([, v]) => v && typeof v === "object" && !Array.isArray(v)))
    : {};
  // After "This survey was updated — please reload it", answers to questions that still exist are kept.
  const [answers, setAnswers] = useState(() => initialAnswers(survey.questions, { ...previous, ...pickQuestions(carry?.answers, survey.questions) }));
  const [files, setFiles] = useState(() => pickQuestions(carry?.files, survey.questions));
  const [errors, setErrors] = useState({});
  const [error, setError] = useState(null);
  const [stale, setStale] = useState(null); // message when the survey changed after this page was opened (M1)
  const [busy, setBusy] = useState(false);

  const openFile = async (questionId) => {
    try {
      const url = await studentBlobUrl(`/student/surveys/${survey.id}/responses/${response.id}/files/${questionId}`);
      window.open(url, "_blank", "noopener");
      setTimeout(() => URL.revokeObjectURL(url), 60000);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not open the file.");
    }
  };

  const submit = async () => {
    const missing = {};
    survey.questions.forEach((q) => {
      if (q.required && q.qtype !== "static_text" && isEmpty(q, answers[q.id], files[q.id], existingFiles[q.id])) missing[q.id] = "This question is mandatory.";
    });
    setErrors(missing);
    if (Object.keys(missing).length) {
      setError("Please answer every mandatory question.");
      scrollToFirstError(survey.questions, missing);
      return;
    }
    setBusy(true);
    setError(null);
    const path = response ? `/student/surveys/${survey.id}/responses/${response.id}` : `/student/surveys/${survey.id}/responses`;
    const payload = Object.fromEntries(Object.entries(answers).filter(([, v]) => v !== null && v !== undefined));
    try {
      let result;
      const picked = Object.entries(files).filter(([, f]) => f);
      if (picked.length) {
        const form = new FormData();
        form.append("answers", JSON.stringify(payload));
        picked.forEach(([qid, f]) => form.append(`files[${qid}]`, f));
        result = await studentUpload(path, form);
      } else {
        result = await studentApi(path, { method: "POST", body: JSON.stringify({ answers: payload }) });
      }
      onDone(result.message);
    } catch (e) {
      // JSON and multipart (file) submits both carry `errors`, keyed "answers.<question id>" (L20).
      const serverErrors = e?.payload?.errors ?? {};
      if (serverErrors.survey) {
        setStale(Array.isArray(serverErrors.survey) ? serverErrors.survey[0] : String(serverErrors.survey));
        setError(null);
        return;
      }
      const mapped = Object.fromEntries(
        Object.entries(serverErrors)
          .filter(([k]) => k.startsWith("answers."))
          .map(([k, v]) => [k.replace(/^answers\./, ""), Array.isArray(v) ? v[0] : v])
      );
      setErrors(mapped);
      setError(e instanceof Error ? e.message : "Could not submit your response.");
      scrollToFirstError(survey.questions, mapped);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Card>
      <CardContent sx={{ p: { xs: 2, sm: 3 } }}>
        {stale && (
          <Alert
            severity="warning"
            sx={{ mb: 2 }}
            action={
              <Button color="inherit" size="small" onClick={() => onReload({ answers, files })}>
                Reload
              </Button>
            }
          >
            {stale}
          </Alert>
        )}
        {error && (
          <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>
            {error}
          </Alert>
        )}
        <SurveyForm
          survey={survey}
          answers={answers}
          onChange={(qid, v) => setAnswers((a) => ({ ...a, [qid]: v }))}
          files={files}
          onFile={(qid, f) => setFiles((fs) => ({ ...fs, [qid]: f }))}
          existingFiles={existingFiles}
          onOpenFile={openFile}
          errors={errors}
          onError={(qid, message) => setErrors((es) => ({ ...es, [qid]: message }))}
        />
        <Stack direction={{ xs: "column-reverse", sm: "row" }} spacing={1} justifyContent="flex-end" sx={{ mt: 3 }}>
          {onCancel && (
            <Button onClick={onCancel} disabled={busy}>
              Cancel
            </Button>
          )}
          <Button variant="contained" size="large" onClick={submit} disabled={busy || Boolean(stale)}>
            {response ? "Update response" : "Submit"}
          </Button>
        </Stack>
      </CardContent>
    </Card>
  );
}

export default function StudentSurveyPage({ params }) {
  const { id } = use(params);
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [mode, setMode] = useState(null); // null = decide from data | "new" | { edit: response } | "done"
  const [message, setMessage] = useState(null);
  const [version, setVersion] = useState(0); // remounts the form when the survey is reloaded
  const [carry, setCarry] = useState(null); // answers typed before a "survey was updated" reload

  const load = useCallback(() => {
    studentApi(`/student/surveys/${id}`)
      .then((d) => {
        setData(d);
        setVersion((v) => v + 1);
      })
      .catch((e) => setError(e.status === 404 ? "This survey is not available to you." : e instanceof Error ? e.message : "Failed to load the survey."));
  }, [id]);

  const reload = (typed) => {
    setCarry(typed);
    load();
  };

  useEffect(() => {
    load();
  }, [load]);

  if (!data) {
    return (
      <Box sx={{ maxWidth: 860 }}>
        {error ? (
          <Stack spacing={2} alignItems="flex-start">
            <Alert severity="warning">{error}</Alert>
            <Button component={Link} href="/student/surveys" startIcon={<ArrowBackIcon />}>
              Back to Surveys
            </Button>
          </Stack>
        ) : (
          <LinearProgress />
        )}
      </Box>
    );
  }

  const { survey, responses } = data;
  const latest = responses[responses.length - 1] ?? null;
  const showForm = mode === "new" || (mode === null && survey.can_submit && !latest);
  const editing = mode && typeof mode === "object" ? mode.edit : null;

  const done = (text) => {
    setMessage(text);
    setMode("done");
    setCarry(null);
    load();
  };

  return (
    <Box sx={{ maxWidth: 860 }}>
      <Button component={Link} href="/student/surveys" startIcon={<ArrowBackIcon />} sx={{ mb: 1 }}>
        Surveys
      </Button>
      {!survey.is_open && (
        <Alert severity="info" sx={{ mb: 2 }}>
          This survey is closed{survey.deadline_at ? ` (deadline was ${formatDateTime(survey.deadline_at)})` : ""}.
        </Alert>
      )}
      {survey.is_open && survey.deadline_at && (showForm || editing) && (
        <Alert severity="info" sx={{ mb: 2 }}>
          Respond by {formatDateTime(survey.deadline_at)}.
        </Alert>
      )}

      {showForm && <Answering key={`new-${version}`} survey={survey} carry={carry} onDone={done} onReload={reload} onCancel={latest ? () => setMode(null) : null} />}
      {editing && (
        <Answering
          key={`edit-${editing.id}-${version}`}
          survey={survey}
          response={responses.find((r) => r.id === editing.id) ?? editing}
          carry={carry}
          onDone={done}
          onReload={reload}
          onCancel={() => setMode(null)}
        />
      )}

      {!showForm && !editing && (
        <Card>
          <CardContent sx={{ p: { xs: 2, sm: 3 } }}>
            <Typography variant="h6" fontWeight={800} sx={{ wordBreak: "break-word" }}>
              {survey.title}
            </Typography>
            {latest ? (
              <Stack spacing={1.5} sx={{ mt: 2 }}>
                <Stack direction="row" spacing={1} alignItems="center">
                  <CheckCircleIcon color="success" />
                  <Typography fontWeight={600}>{mode === "done" && message ? message : "Your response has been submitted."}</Typography>
                </Stack>
                <Typography variant="body2" color="text.secondary">
                  Last submitted {formatDateTime(latest.submitted_at)}
                  {responses.length > 1 ? ` · ${responses.length} responses from you` : ""}
                </Typography>
                {plainText(survey.concluding_text) && (
                  <Typography variant="body2" sx={{ whiteSpace: "pre-line", wordBreak: "break-word" }}>
                    {plainText(survey.concluding_text)}
                  </Typography>
                )}
                <Stack direction={{ xs: "column", sm: "row" }} spacing={1}>
                  {survey.can_edit && (
                    <Button variant="outlined" onClick={() => setMode({ edit: latest })}>
                      Edit my response
                    </Button>
                  )}
                  {survey.can_submit && (
                    <Button variant="outlined" onClick={() => setMode("new")}>
                      Submit another response
                    </Button>
                  )}
                </Stack>
              </Stack>
            ) : (
              <Typography color="text.secondary" sx={{ mt: 2 }}>
                You did not respond to this survey.
              </Typography>
            )}
          </CardContent>
        </Card>
      )}
    </Box>
  );
}
