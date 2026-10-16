"use client";

import { Box, Button, Chip, Paper, Stack, ToggleButton, ToggleButtonGroup, Typography } from "@mui/material";

import { LEVEL_LABELS } from "@/lib/access";

const SHORT = { none: "None", read: "Read", verify: "Verify", write: "Write" };
const SOURCE_CHIPS = {
  added: { label: "Added for this person", color: "success" },
  removed: { label: "Removed for this person", color: "error" },
};

// The permission catalogue as a grid of level pickers, one panel per group (Overview, Placement, ...).
// Editable when `onChange` is given. `sources` (per permission: role / added / removed) marks a person's own changes;
// `baseLevels` (the role's levels) lets a changed-but-unsaved level show what it differs from.
export default function PermissionGrid({ catalogue, levels, onChange, sources, baseLevels, disabled = false }) {
  const editable = Boolean(onChange) && !disabled;

  const setGroup = (group, mode) => {
    const next = { ...levels };
    group.permissions.forEach((p) => {
      next[p.key] = mode === "all" ? p.levels[p.levels.length - 1] : "none";
    });
    onChange(next);
  };

  return (
    <Stack spacing={1.5}>
      {catalogue.map((group) => (
        <Paper key={group.group} variant="outlined" sx={{ p: 1.5 }}>
          <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mb: 1 }}>
            <Typography variant="subtitle2" fontWeight={700}>
              {group.group}
            </Typography>
            {editable && (
              <Stack direction="row" spacing={0.5}>
                <Button size="small" onClick={() => setGroup(group, "all")}>
                  All
                </Button>
                <Button size="small" onClick={() => setGroup(group, "none")}>
                  None
                </Button>
              </Stack>
            )}
          </Stack>
          <Stack spacing={1} divider={<Box sx={{ borderTop: 1, borderColor: "divider" }} />}>
            {group.permissions.map((p) => {
              const value = levels?.[p.key] ?? "none";
              const source = sources?.[p.key];
              const chip = SOURCE_CHIPS[source];
              const changedFromBase = baseLevels && !source && value !== (baseLevels[p.key] ?? "none");
              return (
                <Stack key={p.key} direction={{ xs: "column", sm: "row" }} spacing={1} justifyContent="space-between" alignItems={{ sm: "center" }}>
                  <Box sx={{ minWidth: 0 }}>
                    <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap>
                      <Typography variant="body2" fontWeight={600}>
                        {p.label}
                      </Typography>
                      {chip && <Chip size="small" variant="outlined" color={chip.color} label={chip.label} />}
                      {changedFromBase && <Chip size="small" variant="outlined" color="info" label="Unsaved" />}
                    </Stack>
                    <Typography variant="caption" color="text.secondary">
                      {p.description}
                    </Typography>
                  </Box>
                  <ToggleButtonGroup
                    size="small"
                    exclusive
                    value={value}
                    disabled={!editable}
                    onChange={(_e, next) => next && onChange({ ...levels, [p.key]: next })}
                    sx={{ flexShrink: 0 }}
                  >
                    {["none", ...p.levels].map((level) => (
                      <ToggleButton key={level} value={level} title={LEVEL_LABELS[level]} aria-label={`${p.label}: ${LEVEL_LABELS[level]}`} sx={{ px: 1.25, py: 0.25, textTransform: "none" }}>
                        {SHORT[level]}
                      </ToggleButton>
                    ))}
                  </ToggleButtonGroup>
                </Stack>
              );
            })}
          </Stack>
        </Paper>
      ))}
    </Stack>
  );
}
