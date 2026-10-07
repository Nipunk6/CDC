"use client";

import {
  Box,
  Button,
  Checkbox,
  Chip,
  Divider,
  FormControl,
  FormControlLabel,
  FormGroup,
  FormHelperText,
  IconButton,
  MenuItem,
  Paper,
  Radio,
  RadioGroup,
  Rating,
  Select,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import ArrowUpwardIcon from "@mui/icons-material/ArrowUpward";
import ArrowDownwardIcon from "@mui/icons-material/ArrowDownward";
import UploadFileIcon from "@mui/icons-material/UploadFile";

import { RichTextEditor } from "@/components/forms/shared";
import { plainText } from "@/lib/plaintext";

export const FILE_MAX_BYTES = 5 * 1024 * 1024;

// Starting answers: a Sequence starts in the listed order, so the student only reorders what they want to move.
export const initialAnswers = (questions, previous = {}) => {
  const answers = { ...previous };
  questions.forEach((q) => {
    if (q.qtype === "sequence" && !Array.isArray(answers[q.id])) answers[q.id] = [...(q.options ?? [])];
  });
  return answers;
};

function Sequence({ value, onChange, disabled }) {
  const move = (index, delta) => {
    const next = [...value];
    const [item] = next.splice(index, 1);
    next.splice(index + delta, 0, item);
    onChange(next);
  };
  return (
    <Stack spacing={0.75}>
      {value.map((item, index) => (
        <Paper key={item} variant="outlined" sx={{ display: "flex", alignItems: "center", gap: 1, px: 1, py: 0.5 }}>
          <Typography variant="body2" fontWeight={700} sx={{ width: 24 }}>
            {index + 1}.
          </Typography>
          <Typography variant="body2" sx={{ flex: 1, minWidth: 0, wordBreak: "break-word" }}>
            {item}
          </Typography>
          <IconButton size="small" disabled={disabled || index === 0} onClick={() => move(index, -1)} aria-label={`Move ${item} up`}>
            <ArrowUpwardIcon fontSize="small" />
          </IconButton>
          <IconButton size="small" disabled={disabled || index === value.length - 1} onClick={() => move(index, 1)} aria-label={`Move ${item} down`}>
            <ArrowDownwardIcon fontSize="small" />
          </IconButton>
        </Paper>
      ))}
    </Stack>
  );
}

function Answer({ question, value, onChange, file, onFile, existingFile, onOpenFile, disabled, onError }) {
  const options = question.options ?? [];
  switch (question.qtype) {
    case "mcq_single":
      return (
        <RadioGroup value={value ?? ""} onChange={(e) => onChange(e.target.value)}>
          {options.map((o) => (
            <FormControlLabel key={o} value={o} control={<Radio />} label={o} disabled={disabled} />
          ))}
        </RadioGroup>
      );
    case "mcq_multi": {
      const picked = Array.isArray(value) ? value : [];
      return (
        <FormGroup>
          {options.map((o) => (
            <FormControlLabel
              key={o}
              control={<Checkbox checked={picked.includes(o)} onChange={() => onChange(picked.includes(o) ? picked.filter((p) => p !== o) : [...picked, o])} />}
              label={o}
              disabled={disabled}
            />
          ))}
        </FormGroup>
      );
    }
    case "text":
      return <TextField fullWidth multiline minRows={2} value={value ?? ""} onChange={(e) => onChange(e.target.value)} disabled={disabled} placeholder="Your answer" inputProps={{ maxLength: 5000 }} />;
    case "rich_text":
      return disabled ? <TextField fullWidth multiline minRows={3} disabled placeholder="Formatted answer" /> : <RichTextEditor value={value ?? ""} onChange={onChange} />;
    case "yes_no":
      return (
        <Stack direction="row" spacing={1}>
          <Button variant={value === "yes" ? "contained" : "outlined"} color="primary" onClick={() => onChange(value === "yes" ? null : "yes")} disabled={disabled}>
            Yes
          </Button>
          <Button variant={value === "no" ? "contained" : "outlined"} color="error" onClick={() => onChange(value === "no" ? null : "no")} disabled={disabled}>
            No
          </Button>
        </Stack>
      );
    case "dropdown":
      return (
        <FormControl fullWidth size="small" disabled={disabled}>
          <Select value={value ?? ""} displayEmpty onChange={(e) => onChange(e.target.value || null)}>
            <MenuItem value="">
              <em>Select</em>
            </MenuItem>
            {options.map((o) => (
              <MenuItem key={o} value={o}>
                {o}
              </MenuItem>
            ))}
          </Select>
        </FormControl>
      );
    case "date":
      return <TextField type="date" size="small" value={value ?? ""} onChange={(e) => onChange(e.target.value || null)} disabled={disabled} slotProps={{ inputLabel: { shrink: true } }} />;
    case "rating":
      return <Rating max={Number(question.settings?.max ?? 5)} value={value ? Number(value) : null} onChange={(_e, v) => onChange(v)} disabled={disabled} size="large" />;
    case "sequence":
      return <Sequence value={Array.isArray(value) ? value : options} onChange={onChange} disabled={disabled} />;
    case "file":
      return (
        <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap>
          <Button component="label" variant="outlined" size="small" startIcon={<UploadFileIcon />} disabled={disabled}>
            {file || existingFile ? "Replace file" : "Upload file"}
            <input
              hidden
              type="file"
              accept="application/pdf,.pdf,image/jpeg,image/png"
              onChange={(e) => {
                const picked = e.target.files?.[0] ?? null;
                e.target.value = "";
                if (!picked) return;
                if (!/\.(pdf|jpe?g|png)$/i.test(picked.name)) return onError("Upload a PDF or an image (JPG/PNG).");
                if (picked.size > FILE_MAX_BYTES) return onError("The file must be at most 5 MB.");
                onError(null);
                onFile(picked);
              }}
            />
          </Button>
          {file && <Chip label={file.name} onDelete={() => onFile(null)} />}
          {!file && existingFile && <Chip label={existingFile.name} onClick={onOpenFile} variant="outlined" />}
          <Typography variant="caption" color="text.secondary">
            PDF or image, at most 5 MB
          </Typography>
        </Stack>
      );
    default:
      return null;
  }
}

/**
 * A survey as the respondent sees it (admin Preview and the student answer page). Text set by the CDC is shown as
 * plain text (D76). `readOnly` disables the inputs (Preview).
 */
export default function SurveyForm({ survey, answers = {}, onChange = () => {}, files = {}, onFile = () => {}, existingFiles = {}, onOpenFile = () => {}, errors = {}, onError = () => {}, readOnly = false }) {
  const answerable = (survey.questions ?? []).filter((q) => q.qtype !== "static_text");
  const numbers = Object.fromEntries(answerable.map((q, i) => [q.id, i + 1]));
  return (
    <Box>
      <Typography variant="h5" fontWeight={800} textAlign="center" sx={{ wordBreak: "break-word" }}>
        {survey.title}
      </Typography>
      <Divider sx={{ my: 2 }} />
      {plainText(survey.welcome_text) && (
        <Typography variant="body1" sx={{ whiteSpace: "pre-line", mb: 2, wordBreak: "break-word" }}>
          {plainText(survey.welcome_text)}
        </Typography>
      )}
      <Typography variant="caption" color="text.secondary" sx={{ display: "block", mb: 2 }}>
        <Box component="span" sx={{ color: "error.main" }}>
          *
        </Box>{" "}
        - Mandatory questions
      </Typography>
      <Stack spacing={2.5}>
        {(survey.questions ?? []).map((question) => {
          if (question.qtype === "static_text") {
            return (
              <Typography key={question.id} variant="body1" sx={{ whiteSpace: "pre-line", fontWeight: 600, wordBreak: "break-word" }}>
                {plainText(question.question)}
              </Typography>
            );
          }
          const error = errors[question.id];
          return (
            <Box key={question.id} id={`survey-question-${question.id}`}>
              <Typography fontWeight={600} sx={{ mb: 0.5, wordBreak: "break-word" }}>
                {numbers[question.id]}. {question.question}
                {question.required && (
                  <Box component="span" sx={{ color: "error.main" }}>
                    {" "}
                    *
                  </Box>
                )}
              </Typography>
              {question.help_text && (
                <Typography variant="body2" color="text.secondary" sx={{ mb: 1, wordBreak: "break-word" }}>
                  {question.help_text}
                </Typography>
              )}
              <Answer
                question={question}
                value={answers[question.id]}
                onChange={(v) => onChange(question.id, v)}
                file={files[question.id]}
                onFile={(f) => onFile(question.id, f)}
                existingFile={existingFiles[question.id]}
                onOpenFile={() => onOpenFile(question.id)}
                onError={(message) => onError(question.id, message)}
                disabled={readOnly}
              />
              {error && (
                <FormHelperText error role="alert">
                  {error}
                </FormHelperText>
              )}
            </Box>
          );
        })}
      </Stack>
      {plainText(survey.concluding_text) && (
        <Typography variant="body2" color="text.secondary" sx={{ whiteSpace: "pre-line", mt: 3, wordBreak: "break-word" }}>
          {plainText(survey.concluding_text)}
        </Typography>
      )}
    </Box>
  );
}
