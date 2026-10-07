"use client";

import { use, useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { DragDropContext, Draggable, Droppable } from "@hello-pangea/dnd";
import {
  Alert,
  Autocomplete,
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
  IconButton,
  InputLabel,
  LinearProgress,
  Link as MuiLink,
  MenuItem,
  Paper,
  Select,
  Stack,
  Switch,
  Tab,
  Tabs,
  TextField,
  Tooltip,
  Typography,
} from "@mui/material";
import ArrowBackIosNewIcon from "@mui/icons-material/ArrowBackIosNew";
import ArrowUpwardIcon from "@mui/icons-material/ArrowUpward";
import ArrowDownwardIcon from "@mui/icons-material/ArrowDownward";
import DeleteIcon from "@mui/icons-material/DeleteOutline";
import DragIndicatorIcon from "@mui/icons-material/DragIndicator";
import EditIcon from "@mui/icons-material/Edit";
import VisibilityIcon from "@mui/icons-material/VisibilityOutlined";
import ScheduleIcon from "@mui/icons-material/Schedule";
import CheckCircleIcon from "@mui/icons-material/CheckCircle";
import ContentCopyIcon from "@mui/icons-material/ContentCopy";
import AddCircleOutlineIcon from "@mui/icons-material/AddCircleOutline";
import RadioButtonCheckedIcon from "@mui/icons-material/RadioButtonChecked";
import CheckBoxIcon from "@mui/icons-material/CheckBoxOutlined";
import ShortTextIcon from "@mui/icons-material/ShortText";
import ThumbsUpDownIcon from "@mui/icons-material/ThumbsUpDownOutlined";
import StarIcon from "@mui/icons-material/StarOutline";
import ArrowDropDownCircleIcon from "@mui/icons-material/ArrowDropDownCircleOutlined";
import TextFieldsIcon from "@mui/icons-material/TextFields";
import FormatColorTextIcon from "@mui/icons-material/FormatColorText";
import UploadFileIcon from "@mui/icons-material/UploadFile";
import FormatListNumberedIcon from "@mui/icons-material/FormatListNumbered";
import EventIcon from "@mui/icons-material/Event";

import SurveyForm, { initialAnswers } from "@/components/shared/surveyform";
import AudiencePicker, { SURVEY_AUDIENCES, cleanAudiences } from "@/components/admin/engagement/audiencepicker";
import ConfirmDialog from "@/components/admin/engagement/confirmdialog";
import { QUESTION_TYPES, SURVEY_STATUS, SURVEY_TYPES, WITH_OPTIONS, questionTypeLabel, surveyLink } from "@/components/admin/engagement/surveylabels";
import { RichTextEditor, stripHtml } from "@/components/forms/shared";
import { adminApi } from "@/lib/adminapi";
import { formatDateTime, fromLocalInput, toLocalInput } from "@/lib/format";

const TOOL_ICONS = {
  mcq_single: <RadioButtonCheckedIcon />,
  mcq_multi: <CheckBoxIcon />,
  text: <ShortTextIcon />,
  yes_no: <ThumbsUpDownIcon />,
  rating: <StarIcon />,
  dropdown: <ArrowDropDownCircleIcon />,
  static_text: <TextFieldsIcon />,
  rich_text: <FormatColorTextIcon />,
  file: <UploadFileIcon />,
  sequence: <FormatListNumberedIcon />,
  date: <EventIcon />,
};

const CREATED_FLAG = "cdc-survey-created"; // set by the Survey Forms list after "Create"

let keySeed = 0;
const newKey = () => `q${Date.now()}-${(keySeed += 1)}`;

// Questions keep their server id: saving sends it back so unchanged questions keep their ids (and students' open
// forms stay valid, M1). New questions have no id until saved.
const fromServer = (q) => ({
  key: `s${q.id}`,
  id: q.id,
  qtype: q.qtype,
  question: q.question ?? "",
  help_text: q.help_text ?? "",
  show_help: Boolean(q.help_text),
  options: q.options ?? [],
  settings: q.settings ?? null,
  required: Boolean(q.required),
});

const blankQuestion = (qtype) => ({
  key: newKey(),
  id: null,
  qtype,
  question: "",
  help_text: "",
  show_help: false,
  options: WITH_OPTIONS.includes(qtype) ? ["Option 1", "Option 2"] : [],
  settings: qtype === "rating" ? { max: 5 } : null,
  required: false,
});

function QuestionCard({ question, index, number, onChange, onRemove, onMove, isFirst, isLast, locked, expanded, onToggle, dragHandleProps }) {
  const set = (patch) => onChange({ ...question, ...patch });
  const hasOptions = WITH_OPTIONS.includes(question.qtype);
  const display = question.qtype === "static_text";

  return (
    <Paper variant="outlined" sx={{ display: "flex", overflow: "hidden" }}>
      <Stack sx={{ bgcolor: "grey.800", color: "common.white", justifyContent: "center", px: 0.25 }}>
        <IconButton size="small" sx={{ color: "inherit" }} disabled={locked || isFirst} onClick={() => onMove(index, -1)} aria-label="Move up">
          <ArrowUpwardIcon fontSize="small" />
        </IconButton>
        <Box {...dragHandleProps} sx={{ display: "flex", justifyContent: "center", py: 0.25 }} aria-label="Drag to reorder">
          <DragIndicatorIcon fontSize="small" />
        </Box>
        <IconButton size="small" sx={{ color: "inherit" }} disabled={locked || isLast} onClick={() => onMove(index, 1)} aria-label="Move down">
          <ArrowDownwardIcon fontSize="small" />
        </IconButton>
      </Stack>
      <Box sx={{ flex: 1, minWidth: 0, p: 1.5 }}>
        <Stack direction="row" alignItems="center" spacing={1} sx={{ mb: 1 }}>
          <Typography variant="caption" fontWeight={700} color="text.secondary" sx={{ flex: 1, cursor: "pointer" }} onClick={onToggle}>
            {display ? "Static Text" : `${number} Question | ${questionTypeLabel(question.qtype)}`}
          </Typography>
          <Button size="small" onClick={onToggle}>
            {expanded ? "Done" : "Edit"}
          </Button>
          <Button size="small" color="error" startIcon={<DeleteIcon />} onClick={() => onRemove(index)} disabled={locked}>
            Delete
          </Button>
        </Stack>
        {expanded && !locked ? (
          <Stack spacing={1.5}>
            <TextField
              multiline
              minRows={display ? 3 : 1}
              placeholder={display ? "Type the text to show..." : "Type question..."}
              value={question.question}
              onChange={(e) => set({ question: e.target.value })}
              inputProps={{ maxLength: 5000 }}
              fullWidth
            />
            {hasOptions && (
              <Stack spacing={1}>
                {question.options.map((option, oi) => (
                  <Stack key={oi} direction="row" spacing={1} alignItems="center">
                    <TextField
                      size="small"
                      fullWidth
                      value={option}
                      placeholder={`Option ${oi + 1}`}
                      onChange={(e) => set({ options: question.options.map((o, j) => (j === oi ? e.target.value : o)) })}
                      inputProps={{ maxLength: 255 }}
                    />
                    <IconButton size="small" onClick={() => set({ options: question.options.filter((_o, j) => j !== oi) })} disabled={question.options.length <= 2} aria-label="Remove option">
                      <DeleteIcon fontSize="small" />
                    </IconButton>
                  </Stack>
                ))}
                <Box>
                  <Button size="small" startIcon={<AddCircleOutlineIcon />} onClick={() => set({ options: [...question.options, `Option ${question.options.length + 1}`] })} disabled={question.options.length >= 50}>
                    Add option
                  </Button>
                </Box>
              </Stack>
            )}
            {question.qtype === "rating" && (
              <FormControl size="small" sx={{ maxWidth: 200 }}>
                <InputLabel id={`max-${question.key}`}>Rating scale</InputLabel>
                <Select labelId={`max-${question.key}`} label="Rating scale" value={question.settings?.max ?? 5} onChange={(e) => set({ settings: { max: Number(e.target.value) } })}>
                  {[3, 4, 5, 7, 10].map((n) => (
                    <MenuItem key={n} value={n}>
                      1 to {n}
                    </MenuItem>
                  ))}
                </Select>
              </FormControl>
            )}
            {!display && (
              <FormControlLabel control={<Checkbox checked={question.required} onChange={(e) => set({ required: e.target.checked })} />} label="This question is mandatory" />
            )}
            {!display &&
              (question.show_help ? (
                <TextField size="small" label="Help text" value={question.help_text} onChange={(e) => set({ help_text: e.target.value })} inputProps={{ maxLength: 1000 }} fullWidth />
              ) : (
                <Box>
                  <MuiLink component="button" type="button" underline="hover" onClick={() => set({ show_help: true })} sx={{ display: "inline-flex", alignItems: "center", gap: 0.5 }}>
                    <AddCircleOutlineIcon fontSize="small" /> Include a help text
                  </MuiLink>
                </Box>
              ))}
          </Stack>
        ) : (
          <Box onClick={locked ? undefined : onToggle} sx={{ cursor: locked ? "default" : "pointer" }}>
            <Typography fontWeight={600} sx={{ whiteSpace: display ? "pre-line" : "normal", wordBreak: "break-word" }}>
              {question.question || <em>Untitled</em>}
              {question.required && (
                <Box component="span" sx={{ color: "error.main" }}>
                  *
                </Box>
              )}
            </Typography>
            {question.help_text && (
              <Typography variant="body2" color="text.secondary">
                {question.help_text}
              </Typography>
            )}
            {question.qtype === "yes_no" && (
              <Stack direction="row" spacing={0.5} sx={{ mt: 0.5 }}>
                <Chip size="small" color="primary" label="Yes" />
                <Chip size="small" color="error" label="No" />
              </Stack>
            )}
            {hasOptions && (
              <Stack direction="row" spacing={0.5} flexWrap="wrap" useFlexGap sx={{ mt: 0.5 }}>
                {question.options.map((o, oi) => (
                  <Chip key={oi} size="small" variant="outlined" label={o} />
                ))}
              </Stack>
            )}
          </Box>
        )}
      </Box>
    </Paper>
  );
}

function Builder({ survey, onSaved }) {
  const router = useRouter();
  const archived = survey.status === "archived";
  const locked = archived || survey.has_responses; // questions frozen once there are responses
  const [tab, setTab] = useState("template");
  const [preview, setPreview] = useState(false);
  const [title, setTitle] = useState(survey.title);
  const [renaming, setRenaming] = useState(false);
  const [surveyType, setSurveyType] = useState(survey.survey_type);
  const [jobPostingId, setJobPostingId] = useState(survey.job_posting_id);
  const [welcome, setWelcome] = useState(survey.welcome_text ?? "");
  const [concluding, setConcluding] = useState(survey.concluding_text ?? "");
  const [questions, setQuestions] = useState(() => survey.questions.map(fromServer));
  const [expanded, setExpanded] = useState(null);
  const [isPublic, setIsPublic] = useState(survey.is_public);
  const [allowMultiple, setAllowMultiple] = useState(survey.allow_multiple);
  const [allowEdits, setAllowEdits] = useState(survey.allow_edits);
  const [deadline, setDeadline] = useState(toLocalInput(survey.deadline_at));
  const [audiences, setAudiences] = useState(survey.audiences.map((a) => ({ audience_type: a.audience_type, audience_filter: a.audience_filter })));
  const [postings, setPostings] = useState([]);
  const [dirty, setDirty] = useState(false); // name, texts, settings or audience changed
  const [questionsDirty, setQuestionsDirty] = useState(false); // questions changed (saved only from the Template tab)
  const [leaveTo, setLeaveTo] = useState(null); // in-app link clicked with unsaved changes
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [publishing, setPublishing] = useState(null); // { sendEmail }

  useEffect(() => {
    adminApi("/admin/postings").then((r) => setPostings(r.postings ?? [])).catch(() => setPostings([]));
  }, []);

  // Unsaved changes (L26): the browser asks before a reload / tab close, and in-app links ask first.
  const unsaved = dirty || questionsDirty;
  useEffect(() => {
    if (!unsaved) return undefined;
    const onBeforeUnload = (event) => {
      event.preventDefault();
      event.returnValue = "";
    };
    const onClick = (event) => {
      if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      const anchor = event.target instanceof Element ? event.target.closest("a[href]") : null;
      if (!anchor || anchor.target === "_blank" || anchor.hasAttribute("download")) return;
      const url = new URL(anchor.href, window.location.href);
      if (url.origin !== window.location.origin || (url.pathname === window.location.pathname && url.search === window.location.search)) return;
      // Captured on the document, before Next's <Link> handler: hold the navigation until the admin confirms.
      event.preventDefault();
      event.stopPropagation();
      setLeaveTo(`${url.pathname}${url.search}${url.hash}`);
    };
    window.addEventListener("beforeunload", onBeforeUnload);
    document.addEventListener("click", onClick, true);
    return () => {
      window.removeEventListener("beforeunload", onBeforeUnload);
      document.removeEventListener("click", onClick, true);
    };
  }, [unsaved]);

  const change = (setter) => (value) => {
    setter(value);
    setDirty(true);
  };
  // The rich text editor also reports its own normalisation of the loaded HTML (source "api"): only typing counts.
  const changeRich = (setter) => (value, _delta, source) => {
    setter(value);
    if (source === undefined || source === "user") setDirty(true);
  };

  const addQuestion = (qtype) => {
    const q = blankQuestion(qtype);
    setQuestions((qs) => [...qs, q]);
    setExpanded(q.key);
    setQuestionsDirty(true);
  };
  const updateQuestion = (q) => {
    setQuestions((qs) => qs.map((x) => (x.key === q.key ? q : x)));
    setQuestionsDirty(true);
  };
  const removeQuestion = (index) => {
    setQuestions((qs) => qs.filter((_q, i) => i !== index));
    setQuestionsDirty(true);
  };
  const moveQuestion = (from, delta) => {
    setQuestions((qs) => {
      const next = [...qs];
      const [item] = next.splice(from, 1);
      next.splice(from + delta, 0, item);
      return next;
    });
    setQuestionsDirty(true);
  };
  const onDragEnd = (result) => {
    if (!result.destination || locked) return;
    moveQuestion(result.source.index, result.destination.index - result.source.index);
  };

  const answerableCount = questions.filter((q) => q.qtype !== "static_text").length;
  const numbers = Object.fromEntries(questions.filter((q) => q.qtype !== "static_text").map((q, i) => [q.key, i + 1]));

  // `withQuestions` false = the Audience tab's Save: survey fields only, never the questions (M1).
  const save = async ({ withQuestions = true } = {}) => {
    if (!title.trim()) {
      setError("Name of the form: Required field");
      return false;
    }
    setBusy(true);
    setError(null);
    const body = {
      title: title.trim(),
      survey_type: surveyType,
      job_posting_id: jobPostingId || null,
      welcome_text: stripHtml(welcome) ? welcome : null,
      concluding_text: stripHtml(concluding) ? concluding : null,
      is_public: isPublic,
      allow_multiple: allowMultiple,
      allow_edits: allowEdits,
      deadline_at: deadline ? fromLocalInput(deadline) : null,
      audiences: cleanAudiences(audiences),
    };
    if (withQuestions && !locked) {
      body.questions = questions.map((q) => ({
        id: q.id ?? null,
        qtype: q.qtype,
        question: q.question,
        help_text: q.help_text?.trim() ? q.help_text.trim() : null,
        required: q.required,
        options: WITH_OPTIONS.includes(q.qtype) ? q.options : null,
        settings: q.settings,
      }));
    }
    try {
      const response = await adminApi(`/admin/surveys/${survey.id}`, { method: "PUT", body: JSON.stringify(body) });
      setDirty(false);
      if (body.questions) {
        // Saved questions come back in the order sent: new ones get their ids even if the page is not reloaded.
        const saved = response.survey?.questions ?? [];
        setQuestions((qs) => qs.map((q, i) => ({ ...q, id: saved[i]?.id ?? q.id })));
        setQuestionsDirty(false);
      }
      return response;
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not save the survey.");
      return false;
    } finally {
      setBusy(false);
    }
  };

  const saveForm = async () => {
    const response = await save();
    if (response) onSaved(response.message);
  };

  // Audience tab: saves the settings and audience only. Unsaved question edits stay on screen (not reloaded away).
  const saveSettings = async () => {
    const response = await save({ withQuestions: false });
    if (!response) return;
    if (questionsDirty) {
      setSuccess(`${response.message} Question changes on the Template tab are not saved yet.`);
    } else {
      onSaved(response.message);
    }
  };

  const publish = async () => {
    const saved = await save();
    if (!saved) {
      setPublishing(null);
      return;
    }
    setBusy(true);
    try {
      const response = await adminApi(`/admin/surveys/${survey.id}/publish`, { method: "POST", body: JSON.stringify({ send_email: publishing.sendEmail }) });
      setPublishing(null);
      onSaved(response.message);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not publish the survey.");
      setPublishing(null);
      setBusy(false);
    }
  };

  const copyLink = async () => {
    try {
      await navigator.clipboard.writeText(surveyLink(survey.id));
      setSuccess("Link copied. Students must sign in to open it.");
    } catch {
      setError(`Copy this link: ${surveyLink(survey.id)}`);
    }
  };

  const previewSurvey = {
    title,
    welcome_text: welcome,
    concluding_text: concluding,
    questions: questions.map((q) => ({ ...q, id: q.key })),
  };

  const statusLine =
    survey.status === "draft" ? (
      <Stack direction="row" spacing={0.75} alignItems="center">
        <ScheduleIcon sx={{ color: "warning.main" }} fontSize="small" />
        <Typography variant="body2">The draft is not published</Typography>
      </Stack>
    ) : (
      <Stack direction="row" spacing={0.75} alignItems="center">
        <CheckCircleIcon color={archived ? "disabled" : "success"} fontSize="small" />
        <Typography variant="body2">
          {SURVEY_STATUS[survey.status]}
          {survey.published_at ? ` ${formatDateTime(survey.published_at)}` : ""}
        </Typography>
      </Stack>
    );

  return (
    <Box>
      <Stack direction="row" alignItems="center" spacing={1} sx={{ mb: 1 }}>
        <IconButton component={Link} href="/admin/surveys" aria-label="Back to Survey Forms">
          <ArrowBackIosNewIcon fontSize="small" />
        </IconButton>
        <Typography variant="h6" fontWeight={700} sx={{ flex: 1, minWidth: 0, wordBreak: "break-word" }}>
          {title || "Untitled survey"}
        </Typography>
        {survey.status === "draft" && (
          <Button variant="contained" color="success" disabled={busy || answerableCount === 0} onClick={() => setPublishing({ sendEmail: false })}>
            Publish Survey
          </Button>
        )}
        {survey.status !== "draft" && (
          <Button component={Link} href={`/admin/surveys/${survey.id}/report`} variant="outlined">
            View Report
          </Button>
        )}
      </Stack>
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
      {archived && (
        <Alert severity="info" sx={{ mb: 2 }}>
          This survey is archived. Students no longer see it; the report is kept. Clone it to run it again.
        </Alert>
      )}
      {!archived && survey.has_responses && (
        <Alert severity="info" sx={{ mb: 2 }}>
          This survey already has responses, so its questions are frozen. You can still change the audience and settings. Clone it to change the questions.
        </Alert>
      )}
      <Tabs value={tab} onChange={(_e, v) => setTab(v)} sx={{ mb: 2, borderBottom: 1, borderColor: "divider" }}>
        <Tab value="template" label="Template" />
        <Tab value="audience" label="Audience" />
      </Tabs>

      {tab === "template" && preview && (
        <Card>
          <CardContent>
            <Stack direction="row" justifyContent="flex-end" sx={{ mb: 1 }}>
              <Button startIcon={<EditIcon />} onClick={() => setPreview(false)}>
                Edit form
              </Button>
            </Stack>
            <Box sx={{ maxWidth: 760, mx: "auto" }}>
              <SurveyForm survey={previewSurvey} answers={initialAnswers(previewSurvey.questions)} readOnly />
            </Box>
          </CardContent>
        </Card>
      )}

      {tab === "template" && !preview && (
        <Box sx={{ display: "grid", gap: 2, gridTemplateColumns: { xs: "1fr", md: "260px minmax(0, 1fr)" }, alignItems: "start" }}>
          <Card sx={{ position: { md: "sticky" }, top: { md: 88 } }}>
            <CardContent>
              <Typography fontWeight={700} sx={{ mb: 1 }}>
                Toolbar
              </Typography>
              <Box sx={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 1 }}>
                {QUESTION_TYPES.map((t) => (
                  <Button
                    key={t.value}
                    variant="outlined"
                    disabled={locked}
                    onClick={() => addQuestion(t.value)}
                    sx={{ flexDirection: "column", gap: 0.5, py: 1, textTransform: "none", fontSize: 12, lineHeight: 1.2, minHeight: 72 }}
                  >
                    {TOOL_ICONS[t.value]}
                    {t.label}
                  </Button>
                ))}
              </Box>
            </CardContent>
          </Card>

          <Stack spacing={2} sx={{ minWidth: 0 }}>
            <Card>
              <CardContent>
                <Stack direction="row" justifyContent="space-between" alignItems="center" spacing={1} sx={{ mb: 2 }}>
                  {statusLine}
                  <Button startIcon={<VisibilityIcon />} onClick={() => setPreview(true)}>
                    Preview
                  </Button>
                </Stack>
                {renaming && !archived ? (
                  <TextField
                    fullWidth
                    autoFocus
                    label="Name of the form"
                    value={title}
                    onChange={(e) => change(setTitle)(e.target.value)}
                    onBlur={() => setRenaming(false)}
                    onKeyDown={(e) => e.key === "Enter" && setRenaming(false)}
                    error={!title.trim()}
                    helperText={!title.trim() ? "Required field" : " "}
                    inputProps={{ maxLength: 255 }}
                  />
                ) : (
                  <Stack direction="row" spacing={1} alignItems="center" justifyContent="center">
                    <Typography variant="h6" fontWeight={700} textAlign="center" sx={{ wordBreak: "break-word" }}>
                      {title}
                    </Typography>
                    {!archived && (
                      <IconButton size="small" onClick={() => setRenaming(true)} aria-label="Rename">
                        <EditIcon fontSize="small" />
                      </IconButton>
                    )}
                  </Stack>
                )}
                <Stack direction={{ xs: "column", sm: "row" }} spacing={2} sx={{ mt: 2 }}>
                  <FormControl size="small" sx={{ minWidth: 180 }} disabled={archived}>
                    <InputLabel id="survey-type">Type</InputLabel>
                    <Select labelId="survey-type" label="Type" value={surveyType} onChange={(e) => change(setSurveyType)(e.target.value)}>
                      {Object.entries(SURVEY_TYPES).map(([k, v]) => (
                        <MenuItem key={k} value={k}>
                          {v}
                        </MenuItem>
                      ))}
                    </Select>
                  </FormControl>
                  <Autocomplete
                    size="small"
                    fullWidth
                    disabled={archived}
                    options={postings}
                    value={postings.find((p) => p.id === jobPostingId) ?? null}
                    onChange={(_e, v) => change(setJobPostingId)(v?.id ?? null)}
                    getOptionLabel={(p) => `${p.company?.name ?? "—"} — ${p.title}`}
                    isOptionEqualToValue={(a, b) => a.id === b.id}
                    renderInput={(params) => <TextField {...params} label="Linked job profile (optional, for context)" helperText="Answers are information only; they never change an offer or a block." />}
                  />
                </Stack>
                <Box sx={{ mt: 2 }}>
                  <Typography variant="subtitle2" gutterBottom>
                    Objective of the survey / Welcome text
                  </Typography>
                  {archived ? (
                    <Typography variant="body2" color="text.secondary">
                      {stripHtml(welcome) || "—"}
                    </Typography>
                  ) : (
                    <RichTextEditor value={welcome} onChange={changeRich(setWelcome)} />
                  )}
                </Box>
              </CardContent>
            </Card>

            {questions.length === 0 && (
              <Paper variant="outlined" sx={{ p: 4, textAlign: "center", color: "text.secondary" }}>
                There are no sections to show! Select a control from toolbox to add to the form.
              </Paper>
            )}

            <DragDropContext onDragEnd={onDragEnd}>
              <Droppable droppableId="survey-questions">
                {(drop) => (
                  <Stack spacing={1.5} ref={drop.innerRef} {...drop.droppableProps}>
                    {questions.map((question, index) => (
                      <Draggable key={question.key} draggableId={question.key} index={index} isDragDisabled={locked}>
                        {(drag) => (
                          <Box ref={drag.innerRef} {...drag.draggableProps}>
                            <QuestionCard
                              question={question}
                              index={index}
                              number={numbers[question.key]}
                              onChange={updateQuestion}
                              onRemove={removeQuestion}
                              onMove={moveQuestion}
                              isFirst={index === 0}
                              isLast={index === questions.length - 1}
                              locked={locked}
                              expanded={expanded === question.key}
                              onToggle={() => setExpanded((k) => (k === question.key ? null : question.key))}
                              dragHandleProps={drag.dragHandleProps}
                            />
                          </Box>
                        )}
                      </Draggable>
                    ))}
                    {drop.placeholder}
                  </Stack>
                )}
              </Droppable>
            </DragDropContext>

            {questions.length > 0 && (
              <Box>
                <Typography variant="subtitle2" gutterBottom>
                  Concluding text
                </Typography>
                {archived ? (
                  <Typography variant="body2" color="text.secondary">
                    {stripHtml(concluding) || "—"}
                  </Typography>
                ) : (
                  <RichTextEditor value={concluding} onChange={changeRich(setConcluding)} placeholder="Type some concluding contents here..." />
                )}
              </Box>
            )}
            {!archived && (
              <Button variant="contained" size="large" fullWidth onClick={saveForm} disabled={busy}>
                Save Form{unsaved ? " *" : ""}
              </Button>
            )}
          </Stack>
        </Box>
      )}

      {tab === "audience" && (
        <Stack spacing={2} sx={{ maxWidth: 860 }}>
          <Card>
            <CardContent>
              <Stack spacing={2}>
                <FormControlLabel
                  sx={{ alignItems: "flex-start" }}
                  control={<Switch checked={isPublic} onChange={(e) => change(setIsPublic)(e.target.checked)} disabled={archived} color="success" />}
                  label={
                    <Box>
                      <Typography fontWeight={600}>Make this survey public</Typography>
                      <Typography variant="body2" color="text.secondary">
                        Any signed-in student will be able to submit a response to this survey. Recruiters and visitors who are not signed in never can.
                      </Typography>
                    </Box>
                  }
                />
                <FormControlLabel
                  sx={{ alignItems: "flex-start" }}
                  control={<Switch checked={allowMultiple} onChange={(e) => change(setAllowMultiple)(e.target.checked)} disabled={archived} color="success" />}
                  label={
                    <Box>
                      <Typography fontWeight={600}>Allow multiple submission</Typography>
                      <Typography variant="body2" color="text.secondary">
                        If enabled, a survey audience member will be able to submit multiple responses for this survey.
                      </Typography>
                    </Box>
                  }
                />
                <FormControlLabel
                  sx={{ alignItems: "flex-start" }}
                  control={<Switch checked={allowEdits} onChange={(e) => change(setAllowEdits)(e.target.checked)} disabled={archived} color="success" />}
                  label={
                    <Box>
                      <Typography fontWeight={600}>Allow edits before deadline</Typography>
                      <Typography variant="body2" color="text.secondary">
                        If enabled, a survey audience member will be able to re-open and update their submitted response any time before the submission deadline.
                      </Typography>
                    </Box>
                  }
                />
                <TextField
                  type="datetime-local"
                  label="Survey deadline (IST)"
                  value={deadline}
                  onChange={(e) => change(setDeadline)(e.target.value)}
                  disabled={archived}
                  helperText="Optional. After it, the survey closes for responses and edits."
                  slotProps={{ inputLabel: { shrink: true } }}
                  sx={{ maxWidth: 320 }}
                />
              </Stack>
            </CardContent>
          </Card>

          {!isPublic && (
            <Card>
              <CardContent>
                <Typography fontWeight={700} sx={{ mb: 1 }}>
                  Target Audience for this survey
                </Typography>
                <AudiencePicker value={audiences} onChange={change(setAudiences)} types={SURVEY_AUDIENCES} kind="survey" disabled={archived} />
              </CardContent>
            </Card>
          )}

          <Paper variant="outlined" sx={{ p: 2, borderStyle: "dashed" }}>
            <Stack direction={{ xs: "column", sm: "row" }} spacing={2} alignItems={{ sm: "center" }} justifyContent="space-between">
              <Typography variant="body2" color="text.secondary">
                {isPublic
                  ? "This is a public survey and can be taken using a common URL. Use the button for copying this link."
                  : "Surveys can be taken for all the audience groups using a common link. Use the button for copying this link."}
              </Typography>
              <Tooltip title="Students must sign in to open it">
                <Button variant="outlined" startIcon={<ContentCopyIcon />} onClick={copyLink} sx={{ flexShrink: 0 }}>
                  Copy Link
                </Button>
              </Tooltip>
            </Stack>
          </Paper>

          {!archived && (
            <Button variant="contained" size="large" onClick={saveSettings} disabled={busy}>
              Save{dirty ? " *" : ""}
            </Button>
          )}
        </Stack>
      )}

      <Dialog open={Boolean(publishing)} onClose={() => !busy && setPublishing(null)} maxWidth="sm" fullWidth>
        <DialogTitle>Publish Survey</DialogTitle>
        <DialogContent>
          {publishing && (
            <Stack spacing={1.5}>
              <Typography>
                The form is saved, then opened to {isPublic ? "every signed-in student" : "its target audience"}
                {deadline ? ` until ${formatDateTime(fromLocalInput(deadline))}` : ""}. After publishing, questions freeze once the first response arrives.
              </Typography>
              <FormControlLabel
                control={<Checkbox checked={publishing.sendEmail} onChange={(e) => setPublishing((p) => ({ ...p, sendEmail: e.target.checked }))} />}
                label="Email an announcement to the audience (BCC batches). No reminder mails are sent."
              />
            </Stack>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setPublishing(null)} disabled={busy}>
            Cancel
          </Button>
          <Button variant="contained" color="success" onClick={publish} disabled={busy}>
            Publish Survey
          </Button>
        </DialogActions>
      </Dialog>

      <ConfirmDialog
        open={Boolean(leaveTo)}
        title="Leave without saving?"
        message="Your changes to this survey are not saved. They will be lost if you leave this page."
        confirmLabel="Leave"
        cancelLabel="Stay"
        color="error"
        onConfirm={() => {
          const target = leaveTo;
          setLeaveTo(null);
          router.push(target);
        }}
        onCancel={() => setLeaveTo(null)}
      />
    </Box>
  );
}

export default function SurveyBuilderPage({ params }) {
  const { id } = use(params);
  const [survey, setSurvey] = useState(null);
  const [version, setVersion] = useState(0);
  const [error, setError] = useState(null);
  const [flash, setFlash] = useState(null);

  const load = useCallback(() => {
    adminApi(`/admin/surveys/${id}`)
      .then((r) => {
        setSurvey(r.survey);
        setVersion((v) => v + 1);
        try {
          if (sessionStorage.getItem(CREATED_FLAG) === String(r.survey.id)) {
            sessionStorage.removeItem(CREATED_FLAG);
            setFlash("Success! New survey added.");
          }
        } catch {
          // storage unavailable
        }
      })
      .catch((e) => setError(e instanceof Error ? e.message : "Failed to load the survey."));
  }, [id]);

  useEffect(() => {
    load();
  }, [load]);

  if (!survey) return error ? <Alert severity="error">{error}</Alert> : <LinearProgress />;

  return (
    <>
      {flash && (
        <Alert severity="success" sx={{ mb: 2 }} onClose={() => setFlash(null)}>
          {flash}
        </Alert>
      )}
      <Builder
        key={version}
        survey={survey}
        onSaved={(message) => {
          setFlash(message);
          load();
        }}
      />
    </>
  );
}

