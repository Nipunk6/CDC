"use client";

import { useMemo, useState } from "react";
import {
  Autocomplete,
  Badge,
  Box,
  Button,
  Chip,
  Collapse,
  Divider,
  FormControl,
  FormControlLabel,
  InputLabel,
  MenuItem,
  Paper,
  Radio,
  RadioGroup,
  Select,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import FilterListIcon from "@mui/icons-material/FilterList";
import SearchIcon from "@mui/icons-material/Search";

import { EMPTY_FILTERS, countFilters } from "@/lib/studentfilters";
import { shortProgramme } from "@/lib/usecatalogue";

const GENDERS = [
  { value: "male", label: "Male" },
  { value: "female", label: "Female" },
  { value: "other", label: "Other" },
];

const thisYear = new Date().getFullYear();
const BATCHES = Array.from({ length: 9 }, (_v, i) => String(thisYear - 3 + i));

const Section = ({ title, count, children }) => (
  <Box sx={{ minWidth: 0 }}>
    <Stack direction="row" spacing={1} alignItems="center" sx={{ mb: 1 }}>
      <Typography variant="subtitle2" fontWeight={700}>
        {title}
      </Typography>
      {count ? <Chip size="small" color="success" label={count} sx={{ height: 20 }} /> : null}
    </Stack>
    {children}
  </Box>
);

/**
 * "Apply Filters" panel (Superset parity S4.1) for the Students list and the placement's enrolled list.
 * The parent owns the applied filters; this panel edits a draft and hands it back on Apply. Key the panel by the
 * applied query so the draft resets when the applied filters change.
 *
 * Props: initial (applied filters), onApply(filters), catalogue (programme → branches), statusLabel + statusOptions
 * (account status on Students, enrolment status on a placement), cycles (optional: Placement Status per placement),
 * searchPlaceholder, actions (extra buttons beside Filters, e.g. Download as Excel).
 */
export default function StudentFilterPanel({
  initial,
  onApply,
  catalogue,
  statusLabel = "Status",
  statusOptions = [],
  cycles = null,
  searchPlaceholder = "Search student with name, roll number, email, or mobile number...",
  actions = null,
}) {
  const [draft, setDraft] = useState({ ...EMPTY_FILTERS, ...initial });
  const [open, setOpen] = useState(false);
  const activeCount = countFilters(initial);

  const set = (key, value) => setDraft((prev) => ({ ...prev, [key]: value }));
  const field = (key) => ({
    value: draft[key] ?? "",
    onChange: (event) => set(key, event.target.value),
  });

  const programmes = Object.keys(catalogue ?? {});
  const branches = useMemo(() => {
    const source = draft.programmes.length > 0 ? draft.programmes : Object.keys(catalogue ?? {});
    return Array.from(new Set(source.flatMap((p) => catalogue?.[p] ?? []))).sort();
  }, [catalogue, draft.programmes]);

  const apply = () => onApply({ ...draft });
  const clear = () => {
    setDraft({ ...EMPTY_FILTERS });
    onApply({ ...EMPTY_FILTERS });
  };

  const range = (key, title, unit) => (
    <Section title={title} count={draft[`${key}_min`] !== "" || draft[`${key}_max`] !== "" ? 1 : 0}>
      <Stack direction="row" spacing={1}>
        <TextField size="small" type="number" label={`Greater Than${unit}`} fullWidth {...field(`${key}_min`)} />
        <TextField size="small" type="number" label={`Less Than${unit}`} fullWidth {...field(`${key}_max`)} />
      </Stack>
    </Section>
  );

  const multi = (key, title, options, placeholder, getLabel = (o) => o) => (
    <Section title={title} count={draft[key].length}>
      <Autocomplete
        multiple
        size="small"
        limitTags={2}
        options={options}
        value={draft[key]}
        getOptionLabel={getLabel}
        onChange={(_e, value) => set(key, value)}
        renderInput={(params) => <TextField {...params} placeholder={draft[key].length ? "" : placeholder} />}
      />
    </Section>
  );

  const radios = (key, title, options) => (
    <Section title={title} count={draft[key] ? 1 : 0}>
      <RadioGroup value={draft[key]} onChange={(event) => set(key, event.target.value)}>
        {options.map(([value, label]) => (
          <FormControlLabel
            key={value}
            value={value}
            control={<Radio size="small" />}
            label={<Typography variant="body2">{label}</Typography>}
            sx={{ my: -0.5 }}
          />
        ))}
      </RadioGroup>
    </Section>
  );

  return (
    <Paper sx={{ p: 2, mb: 2 }}>
      <Stack direction={{ xs: "column", md: "row" }} spacing={1.5} alignItems={{ md: "center" }}>
        <TextField
          size="small"
          fullWidth
          placeholder={searchPlaceholder}
          value={draft.search}
          onChange={(event) => set("search", event.target.value)}
          onKeyDown={(event) => event.key === "Enter" && apply()}
          slotProps={{ input: { startAdornment: <SearchIcon fontSize="small" sx={{ mr: 1, color: "text.secondary" }} /> } }}
        />
        <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap sx={{ flexShrink: 0 }}>
          <Badge color="success" badgeContent={activeCount} invisible={activeCount === 0}>
            <Button variant={open ? "contained" : "outlined"} startIcon={<FilterListIcon />} onClick={() => setOpen((v) => !v)}>
              Filters
            </Button>
          </Badge>
          <Button variant="contained" onClick={apply}>
            Search
          </Button>
          {activeCount > 0 && (
            <Button color="inherit" onClick={clear}>
              Clear All Filters
            </Button>
          )}
          {actions}
        </Stack>
      </Stack>

      <Collapse in={open} unmountOnExit>
        <Divider sx={{ my: 2 }} />
        <Box
          sx={{
            display: "grid",
            gap: 2.5,
            gridTemplateColumns: { xs: "1fr", sm: "repeat(2, minmax(0, 1fr))", lg: "repeat(3, minmax(0, 1fr))" },
          }}
        >
          {multi("programmes", "Programme", programmes, "Select Programmes", shortProgramme)}
          {multi("branches", "Branch", branches, "Select Branches")}
          {multi("batches", "Batch", BATCHES, "Select Batches", (b) => `${b} Passout Batch`)}
          {multi("genders", "Gender", GENDERS.map((g) => g.value), "Select Genders", (g) => GENDERS.find((x) => x.value === g)?.label ?? g)}
          {range("tenth", "Class X Percentage", " (%)")}
          {range("twelfth", "Class XII Percentage", " (%)")}
          {range("cgpa", "CGPA", "")}
          <Section
            title="Backlogs"
            count={(draft.ongoing_backlogs_max !== "" ? 1 : 0) + (draft.total_backlogs_max !== "" ? 1 : 0)}
          >
            <Stack direction="row" spacing={1}>
              <TextField size="small" type="number" label="Ongoing (at most)" fullWidth {...field("ongoing_backlogs_max")} />
              <TextField size="small" type="number" label="Total (at most)" fullWidth {...field("total_backlogs_max")} />
            </Stack>
          </Section>
          {statusOptions.length > 0 && (
            <Section title={statusLabel} count={draft.status ? 1 : 0}>
              <FormControl size="small" fullWidth>
                <InputLabel id="sfp-status">{statusLabel}</InputLabel>
                <Select labelId="sfp-status" label={statusLabel} {...field("status")}>
                  <MenuItem value="">All</MenuItem>
                  {statusOptions.map(([value, label]) => (
                    <MenuItem key={value} value={value}>
                      {label}
                    </MenuItem>
                  ))}
                </Select>
              </FormControl>
            </Section>
          )}
          <Box>
            {radios("placement_status", "Placement Status", [
              ["", "All Students"],
              ["placed", "Students who are Placed"],
              ["not_placed", "Students who are not Placed yet"],
            ])}
            {cycles && (
              <FormControl size="small" fullWidth sx={{ mt: 1 }}>
                <InputLabel id="sfp-cycle">In placement</InputLabel>
                <Select labelId="sfp-cycle" label="In placement" {...field("cycle_id")}>
                  <MenuItem value="">All placements</MenuItem>
                  {cycles.map((c) => (
                    <MenuItem key={c.id} value={String(c.id)}>
                      {c.name}
                    </MenuItem>
                  ))}
                </Select>
              </FormControl>
            )}
          </Box>
          {radios("blocked_status", "Blocked Status", [
            ["", "All Students"],
            ["blocked", "Students who are Blocked"],
            ["not_blocked", "Students who are not Blocked"],
          ])}
          {radios("invitation_status", "Invitation Status", [
            ["", "All Students"],
            ["invited", "Invited"],
            ["accepted", "Registered"],
          ])}
        </Box>
        <Stack direction="row" spacing={1} justifyContent="flex-end" sx={{ mt: 2 }}>
          <Button color="inherit" onClick={clear}>
            Clear All Filters
          </Button>
          <Button variant="contained" color="success" onClick={apply}>
            Apply Filters
          </Button>
        </Stack>
      </Collapse>
    </Paper>
  );
}
