"use client";

import { useEffect, useMemo, useState } from "react";
import {
  Autocomplete,
  Box,
  Button,
  Checkbox,
  FormControl,
  FormControlLabel,
  FormGroup,
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
import DeleteIcon from "@mui/icons-material/DeleteOutline";

import { adminApi } from "@/lib/adminapi";
import useCatalogue from "@/lib/usecatalogue";

export const AUDIENCE_LABELS = {
  all: "All students",
  branches: "Programmes / branches",
  cycle: "Students enrolled in a placement",
  batch: "Passout Batch",
  offer_holders: "Offer holders of a job profile",
  posting_applicants: "Applicants of a job profile",
  round_results: "Shortlisted / On Hold in a stage (published only)",
};

export const NOTICE_AUDIENCES = ["all", "branches", "cycle", "posting_applicants", "round_results"];
export const SURVEY_AUDIENCES = ["all", "branches", "cycle", "batch", "offer_holders", "posting_applicants"];

const defaultFilter = (type) =>
  ({
    branches: { branches: [] },
    cycle: { placement_cycle_id: "" },
    batch: { batches: [] },
    offer_holders: { job_posting_id: "" },
    posting_applicants: { job_posting_id: "" },
    round_results: { job_posting_id: "", posting_round_id: "", results: ["selected", "waitlisted"] },
  })[type] ?? null;

// Groups ready for the API ({audience_type, audience_filter}); ids become numbers.
export const cleanAudiences = (groups) =>
  groups.map((g) => {
    const f = g.audience_filter ?? {};
    switch (g.audience_type) {
      case "branches":
        return { audience_type: "branches", audience_filter: { branches: (f.branches ?? []).map((b) => ({ programme: b.programme, branch: b.branch || null })) } };
      case "cycle":
        return { audience_type: "cycle", audience_filter: { placement_cycle_id: Number(f.placement_cycle_id) || null } };
      case "batch":
        return { audience_type: "batch", audience_filter: { batches: (f.batches ?? []).map(Number) } };
      case "offer_holders":
      case "posting_applicants":
        return { audience_type: g.audience_type, audience_filter: { job_posting_id: Number(f.job_posting_id) || null } };
      case "round_results":
        return { audience_type: "round_results", audience_filter: { posting_round_id: Number(f.posting_round_id) || null, results: f.results ?? [] } };
      default:
        return { audience_type: "all", audience_filter: null };
    }
  });

function RoundPicker({ filter, onChange, postings }) {
  const [rounds, setRounds] = useState([]);
  const postingId = filter.job_posting_id;

  useEffect(() => {
    if (!postingId) return undefined;
    let cancelled = false;
    adminApi(`/admin/postings/${postingId}`)
      .then((r) => {
        if (!cancelled) setRounds(r.posting?.rounds ?? []);
      })
      .catch(() => {
        if (!cancelled) setRounds([]);
      });
    return () => {
      cancelled = true;
    };
  }, [postingId]);

  const toggle = (value) => {
    const results = filter.results ?? [];
    onChange({ ...filter, results: results.includes(value) ? results.filter((r) => r !== value) : [...results, value] });
  };

  return (
    <Stack spacing={1.5}>
      <PostingSelect postings={postings} value={postingId} onChange={(v) => onChange({ ...filter, job_posting_id: v, posting_round_id: "" })} />
      <FormControl fullWidth size="small" disabled={!postingId}>
        <InputLabel id="aud-round">Stage</InputLabel>
        <Select labelId="aud-round" label="Stage" value={postingId ? String(filter.posting_round_id ?? "") : ""} onChange={(e) => onChange({ ...filter, posting_round_id: e.target.value })}>
          {rounds.map((r) => (
            <MenuItem key={r.id} value={String(r.id)}>
              {r.name}
            </MenuItem>
          ))}
        </Select>
      </FormControl>
      <FormGroup row>
        <FormControlLabel control={<Checkbox checked={(filter.results ?? []).includes("selected")} onChange={() => toggle("selected")} />} label="Shortlisted" />
        <FormControlLabel control={<Checkbox checked={(filter.results ?? []).includes("waitlisted")} onChange={() => toggle("waitlisted")} />} label="On Hold" />
      </FormGroup>
    </Stack>
  );
}

function PostingSelect({ postings, value, onChange }) {
  return (
    <Autocomplete
      size="small"
      options={postings}
      value={postings.find((p) => String(p.id) === String(value)) ?? null}
      onChange={(_e, v) => onChange(v ? String(v.id) : "")}
      getOptionLabel={(p) => `${p.company?.name ?? "—"} — ${p.title}`}
      isOptionEqualToValue={(a, b) => a.id === b.id}
      renderInput={(params) => <TextField {...params} label="Job Profile" />}
    />
  );
}

/**
 * "Target Audience" groups editor shared by notices and surveys. A student in ANY group is in the audience.
 * Shows a live count from the server (POST /admin/audiences/preview).
 */
export default function AudiencePicker({ value, onChange, types = NOTICE_AUDIENCES, kind = "notice", disabled = false }) {
  const catalogue = useCatalogue(adminApi);
  const [postings, setPostings] = useState([]);
  const [cycles, setCycles] = useState([]);
  const [preview, setPreview] = useState(null);

  useEffect(() => {
    adminApi("/admin/postings").then((r) => setPostings(r.postings ?? [])).catch(() => setPostings([]));
    adminApi("/admin/placement-cycles").then((r) => setCycles(r.placement_cycles ?? [])).catch(() => setCycles([]));
  }, []);

  const branchOptions = useMemo(
    () => Object.entries(catalogue).flatMap(([programme, branches]) => [{ programme, branch: null }, ...branches.map((branch) => ({ programme, branch }))]),
    [catalogue]
  );

  const payload = JSON.stringify(cleanAudiences(value));
  useEffect(() => {
    let cancelled = false;
    const timer = setTimeout(() => {
      adminApi("/admin/audiences/preview", { method: "POST", body: JSON.stringify({ kind, audiences: JSON.parse(payload) }) })
        .then((r) => !cancelled && setPreview({ count: r.count }))
        .catch((e) => !cancelled && setPreview({ error: e instanceof Error ? e.message : "Incomplete audience." }));
    }, 400);
    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [payload, kind]);

  const update = (index, group) => onChange(value.map((g, i) => (i === index ? group : g)));
  const remove = (index) => onChange(value.filter((_g, i) => i !== index));
  const add = () => onChange([...value, { audience_type: types.includes("cycle") ? "cycle" : types[0], audience_filter: defaultFilter(types.includes("cycle") ? "cycle" : types[0]) }]);

  return (
    <Box>
      <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mb: 1 }}>
        <Typography variant="subtitle2">Target Audience</Typography>
        <Button size="small" startIcon={<AddIcon />} onClick={add} disabled={disabled}>
          Add Audience
        </Button>
      </Stack>
      <Stack spacing={1.5}>
        {value.length === 0 && (
          <Typography variant="body2" color="text.secondary">
            No audience yet. Use “Add Audience”.
          </Typography>
        )}
        {value.map((group, index) => {
          const filter = group.audience_filter ?? {};
          const setFilter = (f) => update(index, { ...group, audience_filter: f });
          return (
            <Paper key={index} variant="outlined" sx={{ p: 1.5 }}>
              <Stack spacing={1.5}>
                <Stack direction="row" spacing={1} alignItems="center">
                  <FormControl fullWidth size="small" disabled={disabled}>
                    <InputLabel id={`aud-type-${index}`}>Audience</InputLabel>
                    <Select
                      labelId={`aud-type-${index}`}
                      label="Audience"
                      value={group.audience_type}
                      onChange={(e) => update(index, { audience_type: e.target.value, audience_filter: defaultFilter(e.target.value) })}
                    >
                      {types.map((t) => (
                        <MenuItem key={t} value={t}>
                          {AUDIENCE_LABELS[t]}
                        </MenuItem>
                      ))}
                    </Select>
                  </FormControl>
                  <Tooltip title="Remove this audience">
                    <span>
                      <IconButton onClick={() => remove(index)} disabled={disabled} aria-label="Remove audience">
                        <DeleteIcon />
                      </IconButton>
                    </span>
                  </Tooltip>
                </Stack>
                {group.audience_type === "branches" && (
                  <Autocomplete
                    multiple
                    size="small"
                    disabled={disabled}
                    options={branchOptions}
                    value={filter.branches ?? []}
                    onChange={(_e, v) => setFilter({ branches: v })}
                    getOptionLabel={(o) => (o.branch ? `${o.branch} — ${o.programme}` : `All of ${o.programme}`)}
                    isOptionEqualToValue={(a, b) => a.programme === b.programme && (a.branch ?? null) === (b.branch ?? null)}
                    renderInput={(params) => <TextField {...params} label="Programmes / branches" />}
                  />
                )}
                {group.audience_type === "cycle" && (
                  <FormControl fullWidth size="small" disabled={disabled}>
                    <InputLabel id={`aud-cycle-${index}`}>Placement</InputLabel>
                    <Select labelId={`aud-cycle-${index}`} label="Placement" value={String(filter.placement_cycle_id ?? "")} onChange={(e) => setFilter({ placement_cycle_id: e.target.value })}>
                      {cycles.map((c) => (
                        <MenuItem key={c.id} value={String(c.id)}>
                          {c.name}
                        </MenuItem>
                      ))}
                    </Select>
                  </FormControl>
                )}
                {group.audience_type === "batch" && (
                  <Autocomplete
                    multiple
                    freeSolo
                    size="small"
                    disabled={disabled}
                    options={["2026", "2027", "2028", "2029", "2030"]}
                    value={(filter.batches ?? []).map(String)}
                    onChange={(_e, v) => setFilter({ batches: v.map((b) => String(b).trim()).filter((b) => /^\d{4}$/.test(b)) })}
                    renderInput={(params) => <TextField {...params} label="Passout Batch" helperText="Type a year and press Enter" />}
                  />
                )}
                {(group.audience_type === "posting_applicants" || group.audience_type === "offer_holders") && (
                  <PostingSelect postings={postings} value={filter.job_posting_id} onChange={(v) => setFilter({ job_posting_id: v })} />
                )}
                {group.audience_type === "round_results" && <RoundPicker filter={filter} onChange={setFilter} postings={postings} />}
              </Stack>
            </Paper>
          );
        })}
      </Stack>
      <Typography variant="caption" color={preview?.error ? "error" : "text.secondary"} sx={{ display: "block", mt: 1 }}>
        {preview?.error ? preview.error : preview ? `${preview.count} student(s) in this audience` : "Counting…"}
      </Typography>
    </Box>
  );
}
