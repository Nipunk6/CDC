"use client";

import { useEffect, useMemo } from "react";
import {
  Box,
  InputAdornment,
  Paper,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TextField,
  Typography,
  Checkbox,
  FormControlLabel,
  Switch,
  alpha,
} from "@mui/material";
import CurrencySelector, { Currency, getCurrencySymbol } from "./currencyselector";
import type { ProgrammeEligibility } from "./eligibilitygrid";
import { getDisplayName } from "./salarygrid";

export interface ProgrammeStipend {
  programme: string;
  baseStipend: string;
  hra: string;
  otherPerks: string;
  total: string;
  enabled: boolean;
}

interface StipendGridProps {
  currency: Currency;
  onCurrencyChange: (currency: Currency) => void;
  sameForAll: boolean;
  onSameForAllChange: (same: boolean) => void;
  programmeStipends: ProgrammeStipend[];
  onProgrammeStipendsChange: (stipends: ProgrammeStipend[]) => void;
  ppoProvision: boolean;
  onPpoProvisionChange: (provision: boolean) => void;
  ppoCtc: string;
  onPpoCtcChange: (ctc: string) => void;
  eligibleProgrammes?: ProgrammeEligibility[];
}

const defaultProgrammeStipends: ProgrammeStipend[] = [
  { programme: "B.Tech (4 Year) / B.Tech Double Major (5 Year) / B.Tech-M.Tech Dual Degree (5 Year)", baseStipend: "", hra: "", otherPerks: "", total: "", enabled: true },
  { programme: "Integrated M.Tech (5 Year) - JEE Advanced", baseStipend: "", hra: "", otherPerks: "", total: "", enabled: false },
  { programme: "M.Tech (2 Year) - GATE", baseStipend: "", hra: "", otherPerks: "", total: "", enabled: false },
  { programme: "M.Sc. Tech (3 Year) - JAM", baseStipend: "", hra: "", otherPerks: "", total: "", enabled: false },
  { programme: "MBA (2 Year) - CAT", baseStipend: "", hra: "", otherPerks: "", total: "", enabled: false },
  { programme: "M.Sc (2 Year) - JAM", baseStipend: "", hra: "", otherPerks: "", total: "", enabled: false },
  { programme: "M.A. (2 Year) - Digital Humanities & Social Sciences", baseStipend: "", hra: "", otherPerks: "", total: "", enabled: false },
  { programme: "Ph.D - GATE/NET", baseStipend: "", hra: "", otherPerks: "", total: "", enabled: false },
];

export { defaultProgrammeStipends };

const normalizeProgrammeStipends = (loaded: ProgrammeStipend[]): ProgrammeStipend[] => {
  if (!loaded || loaded.length === 0) return defaultProgrammeStipends;

  const defaultNames = defaultProgrammeStipends.map(d => d.programme);
  const loadedNames = loaded.map(l => l.programme);
  const isUpToDate = defaultNames.every(name => loadedNames.includes(name));
  if (isUpToDate) {
    return defaultNames.map(name => loaded.find(l => l.programme === name)!);
  }

  return defaultProgrammeStipends.map(def => {
    const exact = loaded.find(l => l.programme === def.programme);
    if (exact) return exact;

    let oldMatch: ProgrammeStipend | undefined;
    const dp = def.programme.toLowerCase();
    
    if (dp.includes("b.tech") || dp.includes("integrated m.tech")) {
      oldMatch = loaded.find(l => l.programme.includes("B.Tech / Dual / Int. M.Tech") || l.programme.includes("B.Tech"));
    } else if (dp.includes("m.tech")) {
      oldMatch = loaded.find(l => l.programme === "M.Tech" || l.programme.includes("M.Tech (2 Year)"));
    } else if (dp.includes("mba")) {
      oldMatch = loaded.find(l => l.programme === "MBA" || l.programme.includes("MBA (2 Year)"));
    } else if (dp.includes("m.sc. tech") || dp.includes("m.sc.tech")) {
      oldMatch = loaded.find(l => l.programme === "M.Sc. Tech" || l.programme.includes("M.Sc / M.Sc.Tech") || l.programme === "M.Sc.Tech");
    } else if (dp.includes("m.sc") || dp.includes("m.a.")) {
      oldMatch = loaded.find(l => l.programme === "M.Sc" || l.programme.includes("M.Sc / M.Sc.Tech"));
    } else if (dp.includes("ph.d")) {
      oldMatch = loaded.find(l => l.programme === "Ph.D" || l.programme.includes("Ph.D"));
    }

    if (oldMatch) {
      return {
        ...def,
        baseStipend: oldMatch.baseStipend,
        hra: oldMatch.hra,
        otherPerks: oldMatch.otherPerks,
        total: oldMatch.total,
        enabled: def.enabled,
      };
    }

    return def;
  });
};

export default function StipendGrid({
  currency,
  onCurrencyChange,
  sameForAll,
  onSameForAllChange,
  programmeStipends,
  onProgrammeStipendsChange,
  ppoProvision,
  onPpoProvisionChange,
  ppoCtc,
  onPpoCtcChange,
  eligibleProgrammes,
}: StipendGridProps) {
  const stipends = normalizeProgrammeStipends(programmeStipends);
  const symbol = getCurrencySymbol(currency);

  const activeStipendCategories = useMemo(() => {
    const categories = new Set<string>();
    if (eligibleProgrammes && eligibleProgrammes.length > 0) {
      eligibleProgrammes.forEach(ep => {
        const hasSelectedBranches = ep.branches.some(b => b.selected);
        if (hasSelectedBranches) {
          categories.add(ep.programme);
        }
      });
    }
    return categories;
  }, [eligibleProgrammes]);

  // Sync active eligibility with the enabled property of stipends to reflect in preview
  useEffect(() => {
    let changed = false;
    const updated = stipends.map(s => {
      const isVisible = eligibleProgrammes && eligibleProgrammes.length > 0
        ? activeStipendCategories.has(s.programme)
        : true;
      if (s.enabled !== isVisible) {
        changed = true;
        return { ...s, enabled: isVisible };
      }
      return s;
    });

    if (changed) {
      onProgrammeStipendsChange(updated);
    }
  }, [activeStipendCategories, eligibleProgrammes, stipends, onProgrammeStipendsChange]);

  let firstVisibleIndex = 0;
  for (let i = 0; i < stipends.length; i++) {
    const isVisible = eligibleProgrammes && eligibleProgrammes.length > 0 
      ? activeStipendCategories.has(stipends[i].programme)
      : true;
    if (isVisible) {
      firstVisibleIndex = i;
      break;
    }
  }

  const updateStipend = (index: number, field: keyof ProgrammeStipend, value: string | boolean) => {
    const updated = [...stipends];
    updated[index] = { ...updated[index], [field]: value };

    // Auto-calculate total
    if (typeof value === "string" && (field === "baseStipend" || field === "hra" || field === "otherPerks")) {
      const base = parseFloat(updated[index].baseStipend) || 0;
      const hra = parseFloat(updated[index].hra) || 0;
      const perks = parseFloat(updated[index].otherPerks) || 0;
      updated[index].total = (base + hra + perks).toString();
    }

    // If sameForAll is true and we're updating the first visible row, propagate to all enabled & visible rows
    if (sameForAll && index === firstVisibleIndex && typeof value === "string") {
      updated.forEach((s, i) => {
        const isVisible = eligibleProgrammes && eligibleProgrammes.length > 0 
                 ? activeStipendCategories.has(s.programme)
                 : true;
        if (i !== firstVisibleIndex && isVisible) {
          updated[i] = { ...updated[i], [field]: value };
          // Recalculate total for propagated rows
          const base = parseFloat(updated[i].baseStipend) || 0;
          const hra = parseFloat(updated[i].hra) || 0;
          const perks = parseFloat(updated[i].otherPerks) || 0;
          updated[i].total = (base + hra + perks).toString();
        }
      });
    }

    onProgrammeStipendsChange(updated);
  };

  return (
    <Box>
      {/* Currency and Global Toggle */}
      <Paper
        sx={{
          p: 2,
          mb: 3,
          background: (theme) => alpha(theme.palette.primary.main, 0.05),
          border: "1px solid",
          borderColor: "primary.light",
        }}
      >
        <Stack direction={{ xs: "column", md: "row" }} spacing={3} alignItems={{ md: "center" }}>
          <CurrencySelector value={currency} onChange={onCurrencyChange} />
          <FormControlLabel
            control={
              <Switch
                checked={sameForAll}
                onChange={(e) => onSameForAllChange(e.target.checked)}
                color="primary"
              />
            }
            label={
              <Typography variant="body2" fontWeight={500}>
                Same stipend structure for all programmes
              </Typography>
            }
          />
        </Stack>
      </Paper>

      {/* Programme-wise Stipend Grid */}
      <Typography variant="subtitle2" fontWeight={600} mb={2}>
        Programme-wise Stipend (Monthly - {symbol})
      </Typography>
      <TableContainer component={Paper} sx={{ mb: 3 }}>
        <Table size="small">
          <TableHead>
            <TableRow sx={{ bgcolor: "grey.100" }}>
              <TableCell sx={{ fontWeight: 600, minWidth: 180 }}>Programme</TableCell>
              <TableCell sx={{ fontWeight: 600 }}>Base Stipend</TableCell>
              <TableCell sx={{ fontWeight: 600 }}>HRA/Housing</TableCell>
              <TableCell sx={{ fontWeight: 600 }}>Other Perks</TableCell>
              <TableCell sx={{ fontWeight: 600 }}>Total</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {stipends.map((stipend, index) => {
              const isVisible = eligibleProgrammes && eligibleProgrammes.length > 0 
                 ? activeStipendCategories.has(stipend.programme)
                 : true;
              
              if (!isVisible) return null;

              return (
              <TableRow
                key={stipend.programme}
                sx={{
                  bgcolor: alpha("#1976d2", 0.04),
                }}
              >
                <TableCell>
                  <Typography variant="body2" fontWeight={500}>
                    {getDisplayName(stipend.programme)}
                  </Typography>
                </TableCell>
                <TableCell>
                  <TextField
                    size="small"
                    type="number"
                    value={stipend.baseStipend}
                    onChange={(e) => {
                      updateStipend(index, "baseStipend", e.target.value);
                    }}
                    disabled={sameForAll && index !== firstVisibleIndex}
                    InputProps={{
                      startAdornment: <InputAdornment position="start">{symbol}</InputAdornment>,
                    }}
                    sx={{ width: 130 }}
                    placeholder="50000"
                    label="Base Stipend"
                  />
                </TableCell>
                <TableCell>
                  <TextField
                    size="small"
                    type="number"
                    value={stipend.hra}
                    onChange={(e) => {
                      updateStipend(index, "hra", e.target.value);
                    }}
                    disabled={sameForAll && index !== firstVisibleIndex}
                    InputProps={{
                      startAdornment: <InputAdornment position="start">{symbol}</InputAdornment>,
                    }}
                    sx={{ width: 130 }}
                    placeholder="10000"
                    label="HRA/Housing"
                  />
                </TableCell>
                <TableCell>
                  <TextField
                    size="small"
                    type="number"
                    value={stipend.otherPerks}
                    onChange={(e) => {
                      updateStipend(index, "otherPerks", e.target.value);
                    }}
                    disabled={sameForAll && index !== firstVisibleIndex}
                    InputProps={{
                      startAdornment: <InputAdornment position="start">{symbol}</InputAdornment>,
                    }}
                    sx={{ width: 130 }}
                    placeholder="5000"
                    label="Other Perks"
                  />
                </TableCell>
                <TableCell>
                  <Typography variant="body2" fontWeight={600} color="primary">
                    {stipend.total ? `${symbol}${parseInt(stipend.total).toLocaleString()}` : "-"}
                  </Typography>
                </TableCell>
              </TableRow>
              );
            })}
          </TableBody>
        </Table>
      </TableContainer>

      {/* PPO Section */}
      <Paper
        sx={{
          p: 2,
          background: (theme) => alpha(theme.palette.success.main, 0.05),
          border: "1px solid",
          borderColor: "success.light",
        }}
      >
        <Typography variant="subtitle2" fontWeight={600} mb={2} color="success.dark">
          🎯 Pre-Placement Offer (PPO) Provision
        </Typography>
        <Stack direction={{ xs: "column", md: "row" }} spacing={3} alignItems={{ md: "center" }}>
          <FormControlLabel
            control={
              <Switch
                checked={ppoProvision}
                onChange={(e) => onPpoProvisionChange(e.target.checked)}
                color="success"
              />
            }
            label={
              <Typography variant="body2" fontWeight={500}>
                PPO available based on performance
              </Typography>
            }
          />
          {ppoProvision && (
            <TextField
              size="small"
              label="Expected PPO CTC (Annual)"
              value={ppoCtc}
              onChange={(e) => {
                onPpoCtcChange(e.target.value);
              }}
              type="number"
              InputProps={{
                startAdornment: <InputAdornment position="start">{symbol}</InputAdornment>,
              }}
              sx={{ width: 200 }}
              placeholder="1200000"
            />
          )}
        </Stack>
      </Paper>
    </Box>
  );
}
