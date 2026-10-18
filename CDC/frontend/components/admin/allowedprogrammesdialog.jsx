"use client";

import { useEffect, useState } from "react";
import {
  Alert,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControl,
  IconButton,
  InputLabel,
  MenuItem,
  OutlinedInput,
  Select,
  Stack,
  Tooltip,
  Typography,
} from "@mui/material";
import AddIcon from "@mui/icons-material/Add";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";

import { adminApi } from "@/lib/adminapi";
import { defaultProgrammes } from "@/components/forms/shared";

const programmeOptions = defaultProgrammes.map((programme) => programme.programme);

const currentYear = new Date().getFullYear();
const batchOptions = Array.from({ length: 8 }, (_, index) => currentYear - 1 + index);

const emptyProgrammeRow = () => ({ programme: "", batches: [] });

const rowsFrom = (cycle) =>
  (cycle?.allowed_programmes ?? []).length > 0
    ? cycle.allowed_programmes.map((row) => ({ programme: row.programme, batches: [...(row.batches ?? [])] }))
    : [emptyProgrammeRow()];

// Edit a placement's Allowed Programmes & Batches (draft or published). Sends only that list.
export default function AllowedProgrammesDialog({ open, cycle, onClose, onSaved }) {
  const [rows, setRows] = useState(() => rowsFrom(cycle));
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (open) {
      setRows(rowsFrom(cycle));
      setError(null);
    }
  }, [open, cycle]);

  const updateRow = (index, patch) =>
    setRows((prev) => prev.map((row, rowIndex) => (rowIndex === index ? { ...row, ...patch } : row)));

  const handleSave = async () => {
    const filled = rows.filter((row) => row.programme && row.batches.length > 0);
    const programmes = filled.map((row) => row.programme);

    if (filled.length === 0) {
      setError("Add at least one programme with a passout batch.");
      return;
    }
    if (new Set(programmes).size !== programmes.length) {
      setError("Each programme can be listed only once. Add all its batches to one row.");
      return;
    }

    setSaving(true);
    setError(null);
    try {
      const response = await adminApi(`/admin/placement-cycles/${cycle.id}`, {
        method: "PATCH",
        body: JSON.stringify({ allowed_programmes: filled }),
      });
      onSaved?.(response.placement_cycle, response.message);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to update the allowed programmes.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <Dialog open={open} onClose={() => !saving && onClose()} maxWidth="md" fullWidth>
      <DialogTitle>Edit allowed programmes &amp; batches</DialogTitle>
      <DialogContent dividers>
        <Stack spacing={1.5} sx={{ pt: 1 }}>
          {error && <Alert severity="error">{error}</Alert>}
          <Typography variant="caption" color="text.secondary">
            Which programmes and passout batches this placement covers.
          </Typography>

          {rows.map((row, index) => {
            const years = Array.from(new Set([...batchOptions, ...row.batches])).sort((a, b) => a - b);
            const programmes = row.programme && !programmeOptions.includes(row.programme)
              ? [row.programme, ...programmeOptions]
              : programmeOptions;

            return (
              <Stack key={index} direction={{ xs: "column", md: "row" }} spacing={1.5} alignItems={{ md: "center" }}>
                <FormControl fullWidth size="small">
                  <InputLabel id={`edit-programme-label-${index}`}>Programme</InputLabel>
                  <Select
                    labelId={`edit-programme-label-${index}`}
                    label="Programme"
                    value={row.programme}
                    onChange={(event) => updateRow(index, { programme: event.target.value })}
                  >
                    {programmes.map((programme) => (
                      <MenuItem key={programme} value={programme}>
                        {programme}
                      </MenuItem>
                    ))}
                  </Select>
                </FormControl>

                <FormControl sx={{ minWidth: { md: 220 }, width: { xs: "100%", md: "auto" } }} size="small">
                  <InputLabel id={`edit-batches-label-${index}`}>Batches</InputLabel>
                  <Select
                    labelId={`edit-batches-label-${index}`}
                    multiple
                    value={row.batches}
                    onChange={(event) => updateRow(index, { batches: event.target.value })}
                    input={<OutlinedInput label="Batches" />}
                    renderValue={(selected) => [...selected].sort((a, b) => a - b).join(", ")}
                  >
                    {years.map((year) => (
                      <MenuItem key={year} value={year}>
                        {year}
                      </MenuItem>
                    ))}
                  </Select>
                </FormControl>

                <Tooltip title="Remove this programme">
                  <span>
                    <IconButton
                      onClick={() => setRows((prev) => prev.filter((_, rowIndex) => rowIndex !== index))}
                      disabled={rows.length === 1}
                      color="error"
                    >
                      <DeleteOutlineIcon />
                    </IconButton>
                  </span>
                </Tooltip>
              </Stack>
            );
          })}

          <Button
            size="small"
            startIcon={<AddIcon />}
            onClick={() => setRows((prev) => [...prev, emptyProgrammeRow()])}
            sx={{ alignSelf: "flex-start" }}
          >
            Add programme
          </Button>
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={saving}>
          Cancel
        </Button>
        <Button variant="contained" onClick={handleSave} disabled={saving}>
          {saving ? "Saving..." : "Save changes"}
        </Button>
      </DialogActions>
    </Dialog>
  );
}
