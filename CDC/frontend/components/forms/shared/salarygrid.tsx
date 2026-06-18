"use client";

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
  Divider,
  alpha,
} from "@mui/material";
import CurrencySelector, { Currency, getCurrencySymbol } from "./currencyselector";
import type { ProgrammeEligibility } from "./eligibilitygrid";

export interface ProgrammeSalary {
  programme: string;
  ctcAnnual: string;
  baseSalary: string;
  takeHome: string;
  enabled: boolean;
}

export interface SalaryComponents {
  joiningBonus: string;
  retentionBonus: string;
  performanceBonus: string;
  esops: string;
  vestPeriod: string;
  relocationAllowance: string;
  medicalAllowance: string;
  deductions: string;
  bondAmount: string;
  bondDuration: string;
  stocks: string;
  ctcBreakup: string;
}

interface SalaryGridProps {
  currency: Currency;
  onCurrencyChange: (currency: Currency) => void;
  sameForAll: boolean;
  onSameForAllChange: (same: boolean) => void;
  programmeSalaries: ProgrammeSalary[];
  onProgrammeSalariesChange: (salaries: ProgrammeSalary[]) => void;
  salaryComponents: SalaryComponents;
  onSalaryComponentsChange: (components: SalaryComponents) => void;
  eligibleProgrammes?: ProgrammeEligibility[];
}

const defaultProgrammeSalaries: ProgrammeSalary[] = [
  { programme: "B.Tech / Dual / Int. M.Tech", ctcAnnual: "", baseSalary: "", takeHome: "", enabled: true },
  { programme: "M.Tech", ctcAnnual: "", baseSalary: "", takeHome: "", enabled: false },
  { programme: "MBA", ctcAnnual: "", baseSalary: "", takeHome: "", enabled: false },
  { programme: "M.Sc / M.Sc.Tech", ctcAnnual: "", baseSalary: "", takeHome: "", enabled: false },
  { programme: "Ph.D", ctcAnnual: "", baseSalary: "", takeHome: "", enabled: false },
];

const defaultSalaryComponents: SalaryComponents = {
  joiningBonus: "",
  retentionBonus: "",
  performanceBonus: "",
  esops: "",
  vestPeriod: "",
  relocationAllowance: "",
  medicalAllowance: "",
  deductions: "",
  bondAmount: "",
  bondDuration: "",
  stocks: "",
  ctcBreakup: "",
};

export { defaultProgrammeSalaries, defaultSalaryComponents };

export default function SalaryGrid({
  currency,
  onCurrencyChange,
  sameForAll,
  onSameForAllChange,
  programmeSalaries,
  onProgrammeSalariesChange,
  salaryComponents,
  onSalaryComponentsChange,
  eligibleProgrammes,
}: SalaryGridProps) {
  const salaries = programmeSalaries.length > 0 ? programmeSalaries : defaultProgrammeSalaries;
  const components = { ...defaultSalaryComponents, ...salaryComponents };
  const symbol = getCurrencySymbol(currency);

  const activeSalaryCategories = new Set<string>();
  if (eligibleProgrammes && eligibleProgrammes.length > 0) {
    eligibleProgrammes.forEach(ep => {
      const hasSelectedBranches = ep.branches.some(b => b.selected);
      if (hasSelectedBranches) {
        const p = ep.programme.toLowerCase();
        if (p.includes("b.tech") || p.includes("integrated m.tech")) activeSalaryCategories.add("B.Tech / Dual / Int. M.Tech");
        if (p.includes("m.tech") && !p.includes("integrated")) activeSalaryCategories.add("M.Tech");
        if (p.includes("mba")) activeSalaryCategories.add("MBA");
        if (p.includes("m.sc") || p.includes("m.a.")) activeSalaryCategories.add("M.Sc / M.Sc.Tech");
        if (p.includes("ph.d")) activeSalaryCategories.add("Ph.D");
      }
    });
  }

  let firstVisibleIndex = 0;
  for (let i = 0; i < salaries.length; i++) {
    const isVisible = eligibleProgrammes && eligibleProgrammes.length > 0 
      ? activeSalaryCategories.has(salaries[i].programme)
      : true;
    if (isVisible) {
      firstVisibleIndex = i;
      break;
    }
  }

  const updateSalary = (index: number, field: keyof ProgrammeSalary, value: string | boolean) => {
    const updated = [...salaries];
    updated[index] = { ...updated[index], [field]: value };
    
    // If sameForAll is true and we're updating the first visible row, propagate to all enabled & visible rows
    if (sameForAll && index === firstVisibleIndex && typeof value === "string") {
      updated.forEach((s, i) => {
        const isVisible = eligibleProgrammes && eligibleProgrammes.length > 0 
                 ? activeSalaryCategories.has(s.programme)
                 : true;
        if (i !== firstVisibleIndex && isVisible) {
          updated[i] = { ...updated[i], [field]: value };
        }
      });
    }
    
    onProgrammeSalariesChange(updated);
  };

  const updateComponent = (field: keyof SalaryComponents, value: string) => {
    onSalaryComponentsChange({ ...components, [field]: value });
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
                Same salary structure for all programmes
              </Typography>
            }
          />
        </Stack>
      </Paper>

      {/* Programme-wise Salary Grid */}
      <Typography variant="subtitle2" fontWeight={600} mb={2}>
        Programme-wise Compensation ({symbol})
      </Typography>
      <TableContainer component={Paper} sx={{ mb: 3 }}>
        <Table size="small">
          <TableHead>
            <TableRow sx={{ bgcolor: "grey.100" }}>
              <TableCell sx={{ fontWeight: 600, minWidth: 180 }}>Programme</TableCell>
              <TableCell sx={{ fontWeight: 600 }}>CTC (Annual)</TableCell>
              <TableCell sx={{ fontWeight: 600 }}>Base/Fixed</TableCell>
              <TableCell sx={{ fontWeight: 600 }}>Monthly Take-home</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {salaries.map((salary, index) => {
              const isVisible = eligibleProgrammes && eligibleProgrammes.length > 0 
                 ? activeSalaryCategories.has(salary.programme)
                 : true;
              
              if (!isVisible) return null;

              return (
              <TableRow
                key={salary.programme}
                sx={{
                  bgcolor: alpha("#1976d2", 0.04),
                }}
              >
                <TableCell>
                  <Typography variant="body2" fontWeight={500}>
                    {salary.programme}
                  </Typography>
                </TableCell>
                <TableCell>
                  <TextField
                    size="small"
                    type="number"
                    value={salary.ctcAnnual}
                    onChange={(e) => updateSalary(index, "ctcAnnual", e.target.value)}
                    disabled={sameForAll && index !== firstVisibleIndex}
                    InputProps={{
                      startAdornment: <InputAdornment position="start">{symbol}</InputAdornment>,
                    }}
                    sx={{ width: 150 }}
                    placeholder="e.g. 1200000"
                  />
                </TableCell>
                <TableCell>
                  <TextField
                    size="small"
                    type="number"
                    value={salary.baseSalary}
                    onChange={(e) => updateSalary(index, "baseSalary", e.target.value)}
                    disabled={sameForAll && index !== firstVisibleIndex}
                    InputProps={{
                      startAdornment: <InputAdornment position="start">{symbol}</InputAdornment>,
                    }}
                    sx={{ width: 150 }}
                    placeholder="e.g. 800000"
                  />
                </TableCell>
                <TableCell>
                  <TextField
                    size="small"
                    type="number"
                    value={salary.takeHome}
                    onChange={(e) => updateSalary(index, "takeHome", e.target.value)}
                    disabled={sameForAll && index !== firstVisibleIndex}
                    InputProps={{
                      startAdornment: <InputAdornment position="start">{symbol}</InputAdornment>,
                    }}
                    sx={{ width: 150 }}
                    placeholder="e.g. 65000"
                  />
                </TableCell>
              </TableRow>
              );
            })}
          </TableBody>
        </Table>
      </TableContainer>

      {/* Additional Salary Components */}
      <Typography variant="subtitle2" fontWeight={600} mb={2}>
        Additional Compensation Components
      </Typography>
      <Paper sx={{ p: 2 }}>
        <Stack spacing={3}>
          {/* Bonuses Row */}
          <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
            <TextField
              size="small"
              label="Joining Bonus"
              value={components.joiningBonus}
              onChange={(e) => {
                updateComponent("joiningBonus", e.target.value);
              }}
              InputProps={{
                startAdornment: <InputAdornment position="start">{symbol}</InputAdornment>,
              }}
              sx={{ flex: 1 }}
            />
            <TextField
              size="small"
              label="Retention Bonus"
              value={components.retentionBonus}
              onChange={(e) => {
                updateComponent("retentionBonus", e.target.value);
              }}
              InputProps={{
                startAdornment: <InputAdornment position="start">{symbol}</InputAdornment>,
              }}
              sx={{ flex: 1 }}
            />
            <TextField
              size="small"
              label="Performance/Variable Bonus"
              value={components.performanceBonus}
              onChange={(e) => {
                updateComponent("performanceBonus", e.target.value);
              }}
              InputProps={{
                startAdornment: <InputAdornment position="start">{symbol}</InputAdornment>,
              }}
              sx={{ flex: 1 }}
            />
          </Stack>

          <Divider />

          {/* ESOPs Row */}
          <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
            <TextField
              size="small"
              label="ESOPs / Stock Options"
              value={components.esops}
              onChange={(e) => {
                updateComponent("esops", e.target.value);
              }}
              helperText="Number of options or value"
              sx={{ flex: 1 }}
            />
            <TextField
              size="small"
              label="Vesting Period"
              value={components.vestPeriod}
              onChange={(e) => {
                updateComponent("vestPeriod", e.target.value);
              }}
              helperText="e.g., 4 years with 1-year cliff"
              sx={{ flex: 1 }}
            />
            <TextField
              size="small"
              label="Stocks/RSUs"
              value={components.stocks}
              onChange={(e) => {
                updateComponent("stocks", e.target.value);
              }}
              sx={{ flex: 1 }}
            />
          </Stack>

          <Divider />

          {/* Allowances Row */}
          <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
            <TextField
              size="small"
              label="Relocation Allowance"
              value={components.relocationAllowance}
              onChange={(e) => {
                updateComponent("relocationAllowance", e.target.value);
              }}
              InputProps={{
                startAdornment: <InputAdornment position="start">{symbol}</InputAdornment>,
              }}
              sx={{ flex: 1 }}
            />
            <TextField
              size="small"
              label="Medical Allowance / Insurance"
              value={components.medicalAllowance}
              onChange={(e) => updateComponent("medicalAllowance", e.target.value)}
              InputProps={{
                startAdornment: <InputAdornment position="start">{symbol}</InputAdornment>,
              }}
              sx={{ flex: 1 }}
            />
            <TextField
              size="small"
              label="Deductions (PF, Tax, etc.)"
              value={components.deductions}
              onChange={(e) => updateComponent("deductions", e.target.value)}
              sx={{ flex: 1 }}
            />
          </Stack>

          <Divider />

          {/* Bond Row */}
          <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
            <TextField
              size="small"
              label="Bond Amount (if any)"
              value={components.bondAmount}
              onChange={(e) => {
                updateComponent("bondAmount", e.target.value);
              }}
              InputProps={{
                startAdornment: <InputAdornment position="start">{symbol}</InputAdornment>,
              }}
              sx={{ flex: 1 }}
            />
            <TextField
              size="small"
              label="Bond Duration"
              value={components.bondDuration}
              onChange={(e) => {
                updateComponent("bondDuration", e.target.value);
              }}
              helperText="e.g., 2 years"
              sx={{ flex: 1 }}
            />
          </Stack>

          <Divider />

          {/* CTC Breakup */}
          <TextField
            label="Detailed CTC Breakup (Optional)"
            value={components.ctcBreakup}
            onChange={(e) => {
              updateComponent("ctcBreakup", e.target.value);
            }}
            multiline
            rows={3}
            helperText="Provide any additional salary breakup details"
            fullWidth
          />
        </Stack>
      </Paper>
    </Box>
  );
}
