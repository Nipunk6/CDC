"use client";

import {
  Box,
  Button,
  Checkbox,
  FormControl,
  FormControlLabel,
  IconButton,
  InputLabel,
  MenuItem,
  Paper,
  Select,
  Stack,
  TextField,
  Tooltip,
  Typography,
} from "@mui/material";
import AddIcon from "@mui/icons-material/Add";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import ArrowUpwardIcon from "@mui/icons-material/ArrowUpward";
import ArrowDownwardIcon from "@mui/icons-material/ArrowDownward";

const typeLabels = { text: "Text Answer", mcq_single: "Multiple options, single answer", mcq_multi: "Multiple options, multiple answers" };

const blankQuestion = () => ({ question: "", help_text: "", qtype: "text", options: ["", ""], required: false });

/**
 * Returns the questions in API shape, or an error string for the first invalid row.
 */
export const cleanQuestions = (questions) => {
  const cleaned = [];
  for (const [index, q] of questions.entries()) {
    const text = q.question.trim();
    if (!text) return `Question ${index + 1} is empty.`;
    const options = q.qtype === "text" ? [] : Array.from(new Set(q.options.map((o) => o.trim()).filter(Boolean)));
    if (q.qtype !== "text" && options.length < 2) return `Question ${index + 1} needs at least two different options.`;
    cleaned.push({ ...(q.id ? { id: q.id } : {}), question: text, help_text: (q.help_text ?? "").trim() || null, qtype: q.qtype, options: q.qtype === "text" ? null : options, required: Boolean(q.required) });
  }
  return cleaned;
};

// API rows → editable rows (options as an array of strings with at least two slots for MCQ).
export const toEditable = (questions = []) =>
  questions.map((q) => ({
    id: q.id,
    question: q.question ?? "",
    help_text: q.help_text ?? "",
    qtype: q.qtype ?? "text",
    options: q.qtype === "text" ? ["", ""] : [...(q.options ?? []), ...(q.options?.length >= 2 ? [] : ["", ""])].slice(0, Math.max(2, q.options?.length ?? 0)),
    required: Boolean(q.required),
  }));

export default function QuestionBuilder({ value, onChange, disabled = false }) {
  const update = (index, patch) => onChange(value.map((q, i) => (i === index ? { ...q, ...patch } : q)));
  const move = (index, delta) => {
    const next = [...value];
    const [item] = next.splice(index, 1);
    next.splice(index + delta, 0, item);
    onChange(next);
  };

  return (
    <Stack spacing={1.5}>
      {value.length === 0 && (
        <Typography variant="body2" color="text.secondary">
          No additional questions.
        </Typography>
      )}
      {value.map((q, index) => (
        <Paper key={q.id ?? `new-${index}`} variant="outlined" sx={{ p: 1.5 }}>
          <Stack spacing={1.5}>
            <Stack direction={{ xs: "column", sm: "row" }} spacing={1.5}>
              <TextField
                size="small"
                fullWidth
                label="Question Title"
                value={q.question}
                disabled={disabled}
                onChange={(e) => update(index, { question: e.target.value })}
              />
              <FormControl size="small" sx={{ minWidth: 280 }} disabled={disabled}>
                <InputLabel id={`qtype-${index}`}>Answer Type</InputLabel>
                <Select labelId={`qtype-${index}`} label="Answer Type" value={q.qtype} onChange={(e) => update(index, { qtype: e.target.value })}>
                  {Object.entries(typeLabels).map(([key, label]) => (
                    <MenuItem key={key} value={key}>
                      {label}
                    </MenuItem>
                  ))}
                </Select>
              </FormControl>
            </Stack>
            <TextField
              size="small"
              fullWidth
              label="Help Text"
              helperText="Anything that will help the users understand that what kind of information is required."
              value={q.help_text ?? ""}
              disabled={disabled}
              inputProps={{ maxLength: 500 }}
              onChange={(e) => update(index, { help_text: e.target.value })}
            />
            {q.qtype !== "text" && (
              <Stack spacing={1}>
                {q.options.map((option, optionIndex) => (
                  <Stack key={optionIndex} direction="row" spacing={1} alignItems="center">
                    <TextField
                      size="small"
                      fullWidth
                      label={`Option ${optionIndex + 1}`}
                      value={option}
                      disabled={disabled}
                      onChange={(e) => update(index, { options: q.options.map((o, i) => (i === optionIndex ? e.target.value : o)) })}
                    />
                    <IconButton
                      size="small"
                      disabled={disabled || q.options.length <= 2}
                      onClick={() => update(index, { options: q.options.filter((_, i) => i !== optionIndex) })}
                    >
                      <DeleteOutlineIcon fontSize="small" />
                    </IconButton>
                  </Stack>
                ))}
                <Box>
                  <Button size="small" startIcon={<AddIcon />} disabled={disabled} onClick={() => update(index, { options: [...q.options, ""] })}>
                    Add option
                  </Button>
                </Box>
              </Stack>
            )}
            <Stack direction="row" justifyContent="space-between" alignItems="center">
              <FormControlLabel
                control={<Checkbox size="small" checked={q.required} disabled={disabled} onChange={(e) => update(index, { required: e.target.checked })} />}
                label="This question is mandatory"
              />
              <Stack direction="row">
                <Tooltip title="Move up">
                  <span>
                    <IconButton size="small" disabled={disabled || index === 0} onClick={() => move(index, -1)}>
                      <ArrowUpwardIcon fontSize="small" />
                    </IconButton>
                  </span>
                </Tooltip>
                <Tooltip title="Move down">
                  <span>
                    <IconButton size="small" disabled={disabled || index === value.length - 1} onClick={() => move(index, 1)}>
                      <ArrowDownwardIcon fontSize="small" />
                    </IconButton>
                  </span>
                </Tooltip>
                <Tooltip title="Remove question">
                  <span>
                    <IconButton size="small" color="error" disabled={disabled} onClick={() => onChange(value.filter((_, i) => i !== index))}>
                      <DeleteOutlineIcon fontSize="small" />
                    </IconButton>
                  </span>
                </Tooltip>
              </Stack>
            </Stack>
          </Stack>
        </Paper>
      ))}
      <Box>
        <Button size="small" startIcon={<AddIcon />} disabled={disabled} onClick={() => onChange([...value, blankQuestion()])}>
          Add Additional Question
        </Button>
      </Box>
    </Stack>
  );
}
