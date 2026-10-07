"use client";

import { useState } from "react";
import {
  Box,
  Checkbox,
  FormControlLabel,
  FormControl,
  InputLabel,
  MenuItem,
  Paper,
  Stack,
  Select,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TextField,
  Typography,
  Collapse,
  IconButton,
  Chip,
  Switch,
  alpha,
  Button,
} from "@mui/material";
import ExpandMoreIcon from "@mui/icons-material/ExpandMore";
import ExpandLessIcon from "@mui/icons-material/ExpandLess";

export interface BranchEligibility {
  branch: string;
  selected: boolean;
  cgpa: string;
  backlogsAllowed: boolean;
  /** Numeric caps (Phase 2). Blank/absent = unlimited; only meaningful while backlogsAllowed is true. */
  maxOngoingBacklogs?: string;
  maxTotalBacklogs?: string;
}

export interface ProgrammeEligibility {
  programme: string;
  branches: BranchEligibility[];
  expanded: boolean;
  courseDurationYears?: number;
  graduatingBatch?: string;
  graduatingBatches?: string[];
}

export interface ProgrammeBranchGroup {
  programme: string;
  branches: string[];
}

export interface ProgrammeBranchStateGroup {
  programme: string;
  branches: Array<{
    branch_name: string;
    is_active: boolean;
  }>;
}

interface EligibilityGridProps {
  value: ProgrammeEligibility[];
  onChange: (data: ProgrammeEligibility[]) => void;
  globalCgpa: string;
  onGlobalCgpaChange: (cgpa: string) => void;
  globalBacklogs: boolean;
  onGlobalBacklogsChange: (allowed: boolean) => void;
  /** When set, displays this batch as a read-only chip instead of the multi-select dropdown */
  graduatingBatch?: string;
  /** When true, the graduating batch is shown as read-only (not editable) */
  batchReadOnly?: boolean;
}

const defaultProgrammes: ProgrammeEligibility[] = [
  {
    programme: "B.Tech (4 Year) / B.Tech Double Major (5 Year) / B.Tech-M.Tech Dual Degree (5 Year)",
    expanded: true,
    branches: [
      { branch: "Chemical Engineering", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Civil Engineering", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Computer Science & Engineering", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Electrical Engineering", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Electronics & Communication Engineering", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Engineering Physics", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Environmental Engineering", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Mathematics & Computing", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Mechanical Engineering", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Mechanical Engineering (Mining Machinery Engineering)", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Mineral & Metallurgical Engineering", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Mining Engineering", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Petroleum Engineering", selected: false, cgpa: "7.0", backlogsAllowed: false },
    ],
  },
  {
    programme: "Integrated M.Tech (5 Year) - JEE Advanced",
    expanded: false,
    branches: [
      { branch: "Mathematics & Computing", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Applied Geology", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Applied Geophysics", selected: false, cgpa: "7.0", backlogsAllowed: false },
    ],
  },
  {
    programme: "M.Tech (2 Year) - GATE",
    expanded: false,
    branches: [
      { branch: "Earthquake Science & Engineering (Applied Geophysics)", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Chemical Engineering", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Pharmaceutical Science and Engineering", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Civil Engineering", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Computer Science and Engineering", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Power System Engineering (Electrical Engineering)", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Power Electronics & Electrical Drives (Electrical Engineering)", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Communication & Signal Processing (Electronics and Communication Engineering)", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Optical Communication & Integrated Photonics (Electronics and Communication Engineering)", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "RF & Microwave Engineering (Electronics and Communication Engineering)", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "VLSI Design (Electronics and Communication Engineering)", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Environmental Science & Engineering", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Fuel and Energy Engineering", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Mineral Engineering", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Metallurgical Engineering", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Industrial Engineering & Management", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Data Analytics", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Machine Design (Mechanical Engineering)", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Manufacturing Engineering (Mechanical Engineering)", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Thermal Engineering (Mechanical Engineering)", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Mining Engineering", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Geomatics (Mining Engineering)", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Tunneling and Underground Space Technology (Mining Engineering)", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Petroleum Engineering", selected: false, cgpa: "7.0", backlogsAllowed: false },
    ],
  },
  {
    programme: "M.Sc. Tech (3 Year) - JAM",
    expanded: false,
    branches: [
      { branch: "Applied Geology", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Applied Geophysics", selected: false, cgpa: "7.0", backlogsAllowed: false },
    ],
  },
  {
    programme: "MBA (2 Year) - CAT",
    expanded: false,
    branches: [
      { branch: "MBA - Business Analytics", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "MBA - Finance", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "MBA - Marketing", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "MBA - HR", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "MBA - Operations", selected: false, cgpa: "7.0", backlogsAllowed: false },
    ],
  },
  {
    programme: "M.Sc (2 Year) - JAM",
    expanded: false,
    branches: [
      { branch: "Physics", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Chemistry", selected: false, cgpa: "7.0", backlogsAllowed: false },
      { branch: "Mathematics & Computing", selected: false, cgpa: "7.0", backlogsAllowed: false },
    ],
  },
  {
    programme: "M.A. (2 Year) - Digital Humanities & Social Sciences",
    expanded: false,
    branches: [
      { branch: "Digital Humanities & Social Sciences", selected: false, cgpa: "7.0", backlogsAllowed: false },
    ],
  },
  {
    programme: "Ph.D - GATE/NET",
    expanded: false,
    branches: [
      { branch: "All Departments (Specify in Job Description)", selected: false, cgpa: "7.0", backlogsAllowed: false },
    ],
  },
];

export { defaultProgrammes };

const extractCourseDurationYears = (programme: string) => {
  const match = programme.match(/(\d+)\s*Year/i);
  if (!match) {
    return 1;
  }
  return Math.max(parseInt(match[1], 10), 1);
};

const normalizeProgrammes = (programmes: ProgrammeEligibility[]) =>
  programmes.map((programme) => ({
    ...programme,
    courseDurationYears:
      programme.courseDurationYears ?? extractCourseDurationYears(programme.programme),
    graduatingBatch: programme.graduatingBatch ?? "",
    graduatingBatches:
      programme.graduatingBatches ??
      (programme.graduatingBatch ? [programme.graduatingBatch] : []),
  }));

const createDefaultBranch = (branch: string): BranchEligibility => ({
  branch,
  selected: false,
  cgpa: "7.0",
  backlogsAllowed: false,
});

export const mergeCustomBranchesIntoProgrammes = (
  programmes: ProgrammeEligibility[],
  customGroups: ProgrammeBranchGroup[],
  branchStateGroups: ProgrammeBranchStateGroup[] = []
): ProgrammeEligibility[] => {
  const mergedProgrammes = normalizeProgrammes(
    programmes.map((programme) => ({
      ...programme,
      branches: programme.branches.map((branch) => ({ ...branch })),
    }))
  );

  const branchStateMap = new Map<string, boolean>();

  branchStateGroups.forEach((group) => {
    group.branches.forEach((branch) => {
      branchStateMap.set(`${group.programme}::${branch.branch_name}`.toLowerCase(), branch.is_active);
    });
  });

  mergedProgrammes.forEach((programme) => {
    programme.branches = programme.branches.filter((branch) => {
      const key = `${programme.programme}::${branch.branch}`.toLowerCase();
      if (!branchStateMap.has(key)) {
        return true;
      }

      return branchStateMap.get(key) ?? true;
    });
  });

  customGroups.forEach((group) => {
    const programmeIndex = mergedProgrammes.findIndex(
      (programme) => programme.programme === group.programme
    );

    const additionalBranches = group.branches
      .filter((branch) => branch.trim().length > 0)
      .filter((branch, index, allBranches) => allBranches.indexOf(branch) === index);

    if (programmeIndex >= 0) {
      const existingBranchNames = new Set(
        mergedProgrammes[programmeIndex].branches.map((branch) =>
          branch.branch.toLowerCase()
        )
      );

      additionalBranches.forEach((branch) => {
        if (!existingBranchNames.has(branch.toLowerCase())) {
          mergedProgrammes[programmeIndex].branches.push(createDefaultBranch(branch));
        }
      });

      return;
    }

    if (additionalBranches.length > 0) {
      mergedProgrammes.push({
        programme: group.programme,
        expanded: false,
        courseDurationYears: extractCourseDurationYears(group.programme),
        graduatingBatch: "",
        graduatingBatches: [],
        branches: additionalBranches.map((branch) => createDefaultBranch(branch)),
      });
    }
  });

  return mergedProgrammes;
};

/** Blank = no cap; otherwise a whole number ≥ 0. */
export const isValidBacklogCap = (value?: string) => !value || /^\d{1,3}$/.test(value.trim());

/** Blank = no cutoff; otherwise 0–100 with at most two decimals. */
export const isValidPercent = (value?: string) =>
  !value || (/^\d{1,3}(\.\d{1,2})?$/.test(value.trim()) && Number(value) >= 0 && Number(value) <= 100);

/** True when every Phase 2 numeric eligibility input on a form is in range (M4). */
export const eligibilityNumbersValid = (
  eligibility: ProgrammeEligibility[],
  minTenthPercent?: string,
  minTwelfthPercent?: string
) =>
  isValidPercent(minTenthPercent) &&
  isValidPercent(minTwelfthPercent) &&
  eligibility.every((p) =>
    p.branches.every((b) => !b.selected || (isValidBacklogCap(b.maxOngoingBacklogs) && isValidBacklogCap(b.maxTotalBacklogs)))
  );

const requiresGraduatingBatch = (programmeName: string) => !/ph\.?d/i.test(programmeName);

export default function EligibilityGrid({
  value,
  onChange,
  globalCgpa,
  onGlobalCgpaChange,
  globalBacklogs,
  onGlobalBacklogsChange,
  graduatingBatch,
  batchReadOnly = false,
}: EligibilityGridProps) {
  const programmes = normalizeProgrammes(value.length > 0 ? value : defaultProgrammes);
  // Global numeric caps are a convenience for "Apply to All Selected"; the stored values live per branch.
  const [globalMaxOngoing, setGlobalMaxOngoing] = useState("");
  const [globalMaxTotal, setGlobalMaxTotal] = useState("");
  const globalCaps = globalBacklogs
    ? { maxOngoingBacklogs: globalMaxOngoing, maxTotalBacklogs: globalMaxTotal }
    : { maxOngoingBacklogs: "", maxTotalBacklogs: "" };

  const getGraduatingBatchOptions = (courseDurationYears: number) => {
    const currentYear = new Date().getFullYear();
    return Array.from(
      { length: Math.max(courseDurationYears, 1) },
      (_, offset) => (currentYear + offset).toString()
    );
  };

  const toggleProgramme = (index: number) => {
    const updated = [...programmes];
    updated[index] = { ...updated[index], expanded: !updated[index].expanded };
    onChange(updated);
  };

  const selectAllInProgramme = (progIndex: number, selected: boolean) => {
    const updated = [...programmes];
    updated[progIndex] = {
      ...updated[progIndex],
      branches: updated[progIndex].branches.map((b) => ({
        ...b,
        selected,
        cgpa: selected ? globalCgpa : b.cgpa,
        backlogsAllowed: selected ? globalBacklogs : b.backlogsAllowed,
        ...(selected ? globalCaps : {}),
      })),
    };
    onChange(updated);
  };

  const toggleBranch = (progIndex: number, branchIndex: number) => {
    const updated = [...programmes];
    const branch = updated[progIndex].branches[branchIndex];
    updated[progIndex].branches[branchIndex] = {
      ...branch,
      selected: !branch.selected,
      cgpa: !branch.selected ? globalCgpa : branch.cgpa,
      backlogsAllowed: !branch.selected ? globalBacklogs : branch.backlogsAllowed,
      ...(!branch.selected ? globalCaps : {}),
    };
    onChange(updated);
  };

  const updateBranchCgpa = (progIndex: number, branchIndex: number, cgpa: string) => {
    const updated = [...programmes];
    updated[progIndex].branches[branchIndex] = {
      ...updated[progIndex].branches[branchIndex],
      cgpa,
    };
    onChange(updated);
  };

  const updateBranchBacklogs = (progIndex: number, branchIndex: number, allowed: boolean) => {
    const updated = [...programmes];
    updated[progIndex].branches[branchIndex] = {
      ...updated[progIndex].branches[branchIndex],
      backlogsAllowed: allowed,
      // Turning backlogs off keeps the legacy boolean meaning (zero backlogs), so the caps no longer apply.
      ...(allowed ? {} : { maxOngoingBacklogs: "", maxTotalBacklogs: "" }),
    };
    onChange(updated);
  };

  const updateBranchCap = (
    progIndex: number,
    branchIndex: number,
    field: "maxOngoingBacklogs" | "maxTotalBacklogs",
    cap: string
  ) => {
    const updated = [...programmes];
    updated[progIndex].branches[branchIndex] = {
      ...updated[progIndex].branches[branchIndex],
      [field]: cap,
    };
    onChange(updated);
  };

  const applyGlobalToAll = () => {
    const updated = programmes.map((prog) => ({
      ...prog,
      branches: prog.branches.map((b) =>
        b.selected ? { ...b, cgpa: globalCgpa, backlogsAllowed: globalBacklogs, ...globalCaps } : b
      ),
    }));
    onChange(updated);
  };

  const updateProgrammeBatches = (progIndex: number, graduatingBatches: string[]) => {
    const updated = [...programmes];
    updated[progIndex] = {
      ...updated[progIndex],
      graduatingBatches,
      graduatingBatch: graduatingBatches[0] ?? "",
    };
    onChange(updated);
  };

  const selectedCount = programmes.reduce(
    (acc, prog) => acc + prog.branches.filter((b) => b.selected).length,
    0
  );

  return (
    <Box>
      {/* Global Controls */}
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
          <Typography variant="subtitle2" fontWeight={600} color="primary">
            ⚙️ GLOBAL CONTROLS
          </Typography>
          <Stack direction="row" spacing={2} alignItems="center" flexWrap="wrap" useFlexGap>
            <TextField
              size="small"
              label="Global Min CGPA"
              type="number"
              value={globalCgpa}
              onChange={(e) => {
                onGlobalCgpaChange(e.target.value);
              }}
              inputProps={{ min: 0, max: 10, step: 0.1 }}
              sx={{ width: 130 }}
            />
            <FormControlLabel
              control={
                <Switch
                  checked={globalBacklogs}
                  onChange={(e) => {
                    onGlobalBacklogsChange(e.target.checked);
                  }}
                  color="success"
                />
              }
              label="Backlogs Allowed"
            />
            <TextField
              size="small"
              label="Max ongoing"
              type="number"
              value={globalMaxOngoing}
              onChange={(e) => setGlobalMaxOngoing(e.target.value)}
              disabled={!globalBacklogs}
              placeholder="Any"
              inputProps={{ min: 0, step: 1 }}
              sx={{ width: 130 }}
            />
            <TextField
              size="small"
              label="Max total"
              type="number"
              value={globalMaxTotal}
              onChange={(e) => setGlobalMaxTotal(e.target.value)}
              disabled={!globalBacklogs}
              placeholder="Any"
              inputProps={{ min: 0, step: 1 }}
              sx={{ width: 130 }}
            />
          </Stack>
          <Button variant="outlined" size="small" onClick={applyGlobalToAll}>
            Apply to All Selected
          </Button>
          <Chip
            label={`${selectedCount} branches selected`}
            color={selectedCount > 0 ? "primary" : "default"}
            size="small"
          />
        </Stack>
      </Paper>

      {/* Programme Accordions */}
      <Stack spacing={2}>
        {programmes.map((prog, progIndex) => {
          const selectedInProg = prog.branches.filter((b) => b.selected).length;
          const allSelected = selectedInProg === prog.branches.length;
          const someSelected = selectedInProg > 0 && !allSelected;

          return (
            <Paper key={prog.programme} sx={{ overflow: "hidden" }}>
              {/* Programme Header */}
              <Box
                sx={{
                  px: 2,
                  py: 1.5,
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "space-between",
                  bgcolor: selectedInProg > 0 ? alpha("#1976d2", 0.08) : "grey.50",
                  cursor: "pointer",
                  "&:hover": { bgcolor: alpha("#1976d2", 0.12) },
                }}
                onClick={() => toggleProgramme(progIndex)}
              >
                <Stack direction="row" alignItems="center" spacing={2}>
                  <Checkbox
                    checked={allSelected}
                    indeterminate={someSelected}
                    onClick={(e) => {
                      e.stopPropagation();
                      selectAllInProgramme(progIndex, !allSelected);
                    }}
                    size="small"
                  />
                  <Typography variant="subtitle1" fontWeight={600}>
                    🎓 {prog.programme}
                  </Typography>
                  {selectedInProg > 0 && (
                    <Chip
                      label={`${selectedInProg} selected`}
                      size="small"
                      color="primary"
                      variant="outlined"
                    />
                  )}
                </Stack>
                <Stack direction="row" alignItems="center" spacing={1.5}>
                  {requiresGraduatingBatch(prog.programme) && (
                    batchReadOnly && graduatingBatch ? (
                      <Chip
                        label={`Batch: ${graduatingBatch}`}
                        color="info"
                        size="small"
                        sx={{ fontWeight: 600 }}
                        onClick={(e) => e.stopPropagation()}
                      />
                    ) : (
                      <FormControl
                        size="small"
                        sx={{ minWidth: { xs: 220, md: 320 } }}
                        onClick={(e) => e.stopPropagation()}
                      >
                        <InputLabel>Passout Batch</InputLabel>
                        <Select
                          multiple
                          value={prog.graduatingBatches ?? []}
                          label="Passout Batch"
                          onChange={(e) => {
                            const value = e.target.value;
                            updateProgrammeBatches(
                              progIndex,
                              Array.isArray(value) ? value.map(String) : [String(value)]
                            );
                          }}
                          renderValue={(selected) => (
                            <Stack direction="row" spacing={0.5} flexWrap="wrap" useFlexGap>
                              {(selected as string[]).map((year) => (
                                <Chip
                                  key={`${prog.programme}-chip-${year}`}
                                  label={year}
                                  size="small"
                                  onDelete={() => {
                                    updateProgrammeBatches(
                                      progIndex,
                                      (prog.graduatingBatches ?? []).filter((item) => item !== year)
                                    );
                                  }}
                                  onMouseDown={(event) => {
                                    event.stopPropagation();
                                    event.preventDefault();
                                  }}
                                />
                              ))}
                            </Stack>
                          )}
                        >
                          {getGraduatingBatchOptions(prog.courseDurationYears).map((year) => (
                            <MenuItem key={`${prog.programme}-batch-${year}`} value={year}>
                              {year}
                            </MenuItem>
                          ))}
                        </Select>
                      </FormControl>
                    )
                  )}
                  <IconButton size="small">
                    {prog.expanded ? <ExpandLessIcon /> : <ExpandMoreIcon />}
                  </IconButton>
                </Stack>
              </Box>

              {/* Branches Table */}
              <Collapse in={prog.expanded}>
                <TableContainer sx={{ overflowX: "auto" }}>
                  <Table size="small">
                    <TableHead>
                      <TableRow sx={{ bgcolor: "grey.100" }}>
                        <TableCell padding="checkbox" />
                        <TableCell sx={{ fontWeight: 600 }}>Branch / Specialization</TableCell>
                        <TableCell sx={{ fontWeight: 600, width: 120 }}>Min CGPA</TableCell>
                        <TableCell sx={{ fontWeight: 600, width: 150 }}>Backlogs</TableCell>
                        <TableCell sx={{ fontWeight: 600, width: 100 }}>Max ongoing</TableCell>
                        <TableCell sx={{ fontWeight: 600, width: 100 }}>Max total</TableCell>
                      </TableRow>
                    </TableHead>
                    <TableBody>
                      {prog.branches.map((branch, branchIndex) => (
                        <TableRow
                          key={branch.branch}
                          sx={{
                            bgcolor: branch.selected ? alpha("#1976d2", 0.04) : "transparent",
                            "&:hover": { bgcolor: alpha("#1976d2", 0.08) },
                          }}
                        >
                          <TableCell padding="checkbox">
                            <Checkbox
                              checked={branch.selected}
                              onChange={() => toggleBranch(progIndex, branchIndex)}
                              size="small"
                            />
                          </TableCell>
                          <TableCell>
                            <Typography variant="body2">{branch.branch}</Typography>
                          </TableCell>
                          <TableCell>
                            <TextField
                              size="small"
                              type="number"
                              value={branch.cgpa}
                              onChange={(e) =>
                                updateBranchCgpa(progIndex, branchIndex, e.target.value)
                              }
                              disabled={!branch.selected}
                              inputProps={{ min: 0, max: 10, step: 0.1 }}
                              sx={{ width: 80 }}
                            />
                          </TableCell>
                          <TableCell>
                            <Chip
                              label={branch.backlogsAllowed ? "✅ YES" : "❌ NO"}
                              size="small"
                              color={branch.backlogsAllowed ? "success" : "error"}
                              variant="outlined"
                              onClick={() =>
                                branch.selected &&
                                updateBranchBacklogs(progIndex, branchIndex, !branch.backlogsAllowed)
                              }
                              sx={{
                                cursor: branch.selected ? "pointer" : "default",
                                opacity: branch.selected ? 1 : 0.5,
                              }}
                            />
                          </TableCell>
                          {(["maxOngoingBacklogs", "maxTotalBacklogs"] as const).map((field) => (
                            <TableCell key={field}>
                              <TextField
                                size="small"
                                type="number"
                                placeholder="Any"
                                value={branch[field] ?? ""}
                                onChange={(e) => updateBranchCap(progIndex, branchIndex, field, e.target.value)}
                                disabled={!branch.selected || !branch.backlogsAllowed}
                                error={branch.selected && !isValidBacklogCap(branch[field])}
                                inputProps={{ min: 0, step: 1 }}
                                sx={{ width: 80 }}
                              />
                            </TableCell>
                          ))}
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </TableContainer>
              </Collapse>
            </Paper>
          );
        })}
      </Stack>
    </Box>
  );
}
