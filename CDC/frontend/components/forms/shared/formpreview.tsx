"use client";

import {
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  Divider,
  Grid2,
  List,
  ListItem,
  ListItemText,
  Paper,
  Stack,
  Typography,
  alpha,
  Avatar,
} from "@mui/material";
import BusinessIcon from "@mui/icons-material/Business";
import CheckCircleIcon from "@mui/icons-material/CheckCircle";
import EditIcon from "@mui/icons-material/Edit";
import { Currency, getCurrencySymbol } from "./currencyselector";
import { ProgrammeEligibility } from "./eligibilitygrid";
import { ProgrammeSalary, SalaryComponents } from "./salarygrid";
import { ProgrammeStipend } from "./stipendgrid";
import { SelectionRound } from "./selectionprocessbuilder";

interface PreviewSectionProps {
  title: string;
  children: React.ReactNode;
  onEdit?: () => void;
  complete?: boolean;
}

function PreviewSection({ title, children, onEdit, complete = true }: PreviewSectionProps) {
  return (
    <Card sx={{ mb: 2 }}>
      <Box
        sx={{
          px: 2,
          py: 1.5,
          display: "flex",
          alignItems: "center",
          justifyContent: "space-between",
          bgcolor: complete ? alpha("#2e7d32", 0.08) : alpha("#ed6c02", 0.08),
          borderBottom: "1px solid",
          borderColor: "divider",
        }}
      >
        <Stack direction="row" alignItems="center" spacing={1}>
          {complete ? (
            <CheckCircleIcon color="success" fontSize="small" />
          ) : (
            <Chip label="Incomplete" size="small" color="warning" />
          )}
          <Typography variant="subtitle2" fontWeight={600}>
            {title}
          </Typography>
        </Stack>
        {onEdit && (
          <Button size="small" startIcon={<EditIcon />} onClick={onEdit}>
            Edit
          </Button>
        )}
      </Box>
      <CardContent sx={{ py: 2 }}>{children}</CardContent>
    </Card>
  );
}

interface JnfPreviewProps {
  jobDetails: {
    title: string;
    designation: string;
    location: string;
    workMode: string;
    expectedHires: string;
    minimumHires: string;
    joiningMonth: string;
    skills: string[];
    description: string;
    registrationLink: string;
  };
  eligibility: ProgrammeEligibility[];
  salary: {
    currency: Currency;
    programmeSalaries: ProgrammeSalary[];
    components: SalaryComponents;
  };
  selectionProcess: {
    rounds: SelectionRound[];
  };
  declarations: {
    aipc: boolean;
    shortlistCriteria: boolean;
    infoVerified: boolean;
    consentLogo: boolean;
    confirmAccuracy: boolean;
    resultsViaCdc: boolean;
  };
  signatory: {
    name: string;
    designation: string;
    date: string;
  };
  onNavigateToTab: (tab: number) => void;
  companyLogoUrl?: string | null;
}

export function JnfPreview({
  jobDetails,
  eligibility,
  salary,
  selectionProcess,
  declarations,
  signatory,
  onNavigateToTab,
  companyLogoUrl,
}: JnfPreviewProps) {
  const symbol = getCurrencySymbol(salary.currency);
  const selectedBranches = eligibility.flatMap((p) =>
    p.branches.filter((b) => b.selected).map((b) => `${b.branch} (${p.programme})`)
  );
  const enabledSalaries = salary.programmeSalaries.filter((s) => s.enabled);
  const enabledRounds = selectionProcess.rounds.filter((r) => r.enabled);
  const allDeclarationsAccepted = Object.values(declarations).every(Boolean);

  return (
    <Box>
      <Paper
        sx={{
          p: 2,
          mb: 3,
          bgcolor: alpha("#1976d2", 0.05),
          border: "1px solid",
          borderColor: "primary.main",
        }}
      >
        <Typography variant="h6" fontWeight={600} color="primary" gutterBottom>
          📋 Form Preview - Review Before Submission
        </Typography>
        <Typography variant="body2" color="text.secondary">
          Please review all sections carefully. Click &quot;Edit&quot; to make changes.
        </Typography>
      </Paper>

      {/* Job Details */}
      <PreviewSection
        title="Job Details"
        onEdit={() => onNavigateToTab(0)}
        complete={!!jobDetails.title && !!jobDetails.description}
      >
        <Grid2 container spacing={2}>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Job Title</Typography>
            <Typography variant="body2" fontWeight={500}>{jobDetails.title || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Designation</Typography>
            <Typography variant="body2">{jobDetails.designation || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Location</Typography>
            <Typography variant="body2">{jobDetails.location || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Work Mode</Typography>
            <Typography variant="body2">{jobDetails.workMode || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Expected Hires</Typography>
            <Typography variant="body2">{jobDetails.expectedHires || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Joining Month</Typography>
            <Typography variant="body2">{jobDetails.joiningMonth || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 12 }}>
            <Typography variant="caption" color="text.secondary">Skills</Typography>
            <Stack direction="row" flexWrap="wrap" gap={0.5} mt={0.5}>
              {jobDetails.skills.length > 0 ? (
                jobDetails.skills.map((skill) => (
                  <Chip key={skill} label={skill} size="small" variant="outlined" />
                ))
              ) : (
                <Typography variant="body2" color="text.secondary">No skills specified</Typography>
              )}
            </Stack>
          </Grid2>
        </Grid2>
      </PreviewSection>

      {/* Eligibility */}
      <PreviewSection
        title="Eligibility Criteria"
        onEdit={() => onNavigateToTab(1)}
        complete={selectedBranches.length > 0}
      >
        <Typography variant="body2" fontWeight={500} gutterBottom>
          {selectedBranches.length} branches selected
        </Typography>
        <Stack direction="row" flexWrap="wrap" gap={0.5}>
          {selectedBranches.slice(0, 10).map((branch) => (
            <Chip key={branch} label={branch} size="small" />
          ))}
          {selectedBranches.length > 10 && (
            <Chip label={`+${selectedBranches.length - 10} more`} size="small" color="primary" />
          )}
        </Stack>
      </PreviewSection>

      {/* Salary */}
      <PreviewSection
        title="Compensation Details"
        onEdit={() => onNavigateToTab(2)}
        complete={enabledSalaries.some((s) => s.ctcAnnual)}
      >
        <List dense disablePadding>
          {enabledSalaries.map((s) => (
            <ListItem key={s.programme} disablePadding sx={{ py: 0.5 }}>
              <ListItemText
                primary={s.programme}
                secondary={`CTC: ${symbol}${s.ctcAnnual ? parseInt(s.ctcAnnual).toLocaleString() : "-"} | Base: ${symbol}${s.baseSalary ? parseInt(s.baseSalary).toLocaleString() : "-"}`}
              />
            </ListItem>
          ))}
        </List>
        {salary.components.joiningBonus && (
          <Typography variant="body2" mt={1}>
            Joining Bonus: {symbol}{parseInt(salary.components.joiningBonus).toLocaleString()}
          </Typography>
        )}
      </PreviewSection>

      {/* Selection Process */}
      <PreviewSection
        title="Selection Process"
        onEdit={() => onNavigateToTab(3)}
        complete={enabledRounds.length > 0}
      >
        <Stack direction="column" gap={1}>
          {enabledRounds.map((round, idx) => (
            <Stack key={round.id} direction="row" alignItems="center" spacing={1} flexWrap="wrap">
              <Chip
                label={`${idx + 1}. ${round.type.replace("_", " ")}`}
                size="small"
                color="primary"
                variant="outlined"
              />
              {round.date && (
                <Typography variant="caption" color="text.secondary">
                  ({round.date})
                </Typography>
              )}
              {(round.mode === "offline" || round.mode === "hybrid") && round.infraRequirement && (
                <Typography variant="caption" color="text.secondary">
                  [Infra: {round.infraRequirement}]
                </Typography>
              )}
              {round.details && (
                <Typography variant="caption" color="text.secondary" sx={{ fontStyle: "italic", ml: 1 }}>
                  - {round.details}
                </Typography>
              )}
            </Stack>
          ))}
        </Stack>
      </PreviewSection>

      {/* Declaration */}
      <PreviewSection
        title="Declaration"
        onEdit={() => onNavigateToTab(4)}
        complete={allDeclarationsAccepted && !!signatory.name}
      >
        <Stack spacing={1}>
          <Typography variant="body2">
            ✅ All {Object.keys(declarations).length} declarations accepted: {allDeclarationsAccepted ? "Yes" : "No"}
          </Typography>
          <Divider />
          <Typography variant="body2">
            <strong>Signatory:</strong> {signatory.name || "-"} ({signatory.designation || "-"})
          </Typography>
          <Typography variant="body2">
            <strong>Date:</strong> {signatory.date || "-"}
          </Typography>
        </Stack>
      </PreviewSection>
    </Box>
  );
}

interface InfPreviewProps {
  internshipDetails: {
    title: string;
    designation: string;
    location: string;
    workMode: string;
    expectedHires: string;
    duration: string;
    joiningMonth: string;
    skills: string[];
    description: string;
    registrationLink: string;
  };
  eligibility: ProgrammeEligibility[];
  stipend: {
    currency: Currency;
    programmeStipends: ProgrammeStipend[];
    ppoProvision: boolean;
    ppoCtc: string;
  };
  selectionProcess: {
    rounds: SelectionRound[];
  };
  declarations: {
    aipc: boolean;
    shortlistCriteria: boolean;
    infoVerified: boolean;
    consentLogo: boolean;
    confirmAccuracy: boolean;
    resultsViaCdc: boolean;
  };
  signatory: {
    name: string;
    designation: string;
    date: string;
  };
  onNavigateToTab: (tab: number) => void;
  companyLogoUrl?: string | null;
}

export function InfPreview({
  internshipDetails,
  eligibility,
  stipend,
  selectionProcess,
  declarations,
  signatory,
  onNavigateToTab,
  companyLogoUrl,
}: InfPreviewProps) {
  const symbol = getCurrencySymbol(stipend.currency);
  const selectedBranches = eligibility.flatMap((p) =>
    p.branches.filter((b) => b.selected).map((b) => `${b.branch} (${p.programme})`)
  );
  const enabledStipends = stipend.programmeStipends.filter((s) => s.enabled);
  const enabledRounds = selectionProcess.rounds.filter((r) => r.enabled);
  const allDeclarationsAccepted = Object.values(declarations).every(Boolean);

  return (
    <Box>
      <Paper
        sx={{
          p: 2,
          mb: 3,
          bgcolor: alpha("#1976d2", 0.05),
          border: "1px solid",
          borderColor: "primary.main",
        }}
      >
        <Typography variant="h6" fontWeight={600} color="primary" gutterBottom>
          📋 Form Preview - Review Before Submission
        </Typography>
        <Typography variant="body2" color="text.secondary">
          Please review all sections carefully. Click &quot;Edit&quot; to make changes.
        </Typography>
      </Paper>

      {/* Internship Details */}
      <PreviewSection
        title="Internship Details"
        onEdit={() => onNavigateToTab(0)}
        complete={!!internshipDetails.title && !!internshipDetails.description}
      >
        <Grid2 container spacing={2}>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Internship Title</Typography>
            <Typography variant="body2" fontWeight={500}>{internshipDetails.title || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Location</Typography>
            <Typography variant="body2">{internshipDetails.location || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Duration</Typography>
            <Typography variant="body2">{internshipDetails.duration ? `${internshipDetails.duration} weeks` : "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Work Mode</Typography>
            <Typography variant="body2">{internshipDetails.workMode || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 12 }}>
            <Typography variant="caption" color="text.secondary">Skills</Typography>
            <Stack direction="row" flexWrap="wrap" gap={0.5} mt={0.5}>
              {internshipDetails.skills.length > 0 ? (
                internshipDetails.skills.map((skill) => (
                  <Chip key={skill} label={skill} size="small" variant="outlined" />
                ))
              ) : (
                <Typography variant="body2" color="text.secondary">No skills specified</Typography>
              )}
            </Stack>
          </Grid2>
        </Grid2>
      </PreviewSection>

      {/* Eligibility */}
      <PreviewSection
        title="Eligibility Criteria"
        onEdit={() => onNavigateToTab(1)}
        complete={selectedBranches.length > 0}
      >
        <Typography variant="body2" fontWeight={500} gutterBottom>
          {selectedBranches.length} branches selected
        </Typography>
        <Stack direction="row" flexWrap="wrap" gap={0.5}>
          {selectedBranches.slice(0, 10).map((branch) => (
            <Chip key={branch} label={branch} size="small" />
          ))}
          {selectedBranches.length > 10 && (
            <Chip label={`+${selectedBranches.length - 10} more`} size="small" color="primary" />
          )}
        </Stack>
      </PreviewSection>

      {/* Stipend */}
      <PreviewSection
        title="Stipend Details"
        onEdit={() => onNavigateToTab(2)}
        complete={enabledStipends.some((s) => s.baseStipend)}
      >
        <List dense disablePadding>
          {enabledStipends.map((s) => (
            <ListItem key={s.programme} disablePadding sx={{ py: 0.5 }}>
              <ListItemText
                primary={s.programme}
                secondary={`Monthly: ${symbol}${s.total ? parseInt(s.total).toLocaleString() : "-"}`}
              />
            </ListItem>
          ))}
        </List>
        {stipend.ppoProvision && (
          <Chip
            label={`PPO Available - CTC: ${symbol}${stipend.ppoCtc ? parseInt(stipend.ppoCtc).toLocaleString() : "TBD"}`}
            color="success"
            size="small"
            sx={{ mt: 1 }}
          />
        )}
      </PreviewSection>

      {/* Selection Process */}
      <PreviewSection
        title="Selection Process"
        onEdit={() => onNavigateToTab(3)}
        complete={enabledRounds.length > 0}
      >
        <Stack direction="column" gap={1}>
          {enabledRounds.map((round, idx) => (
            <Stack key={round.id} direction="row" alignItems="center" spacing={1} flexWrap="wrap">
              <Chip
                label={`${idx + 1}. ${round.type.replace("_", " ")}`}
                size="small"
                color="primary"
                variant="outlined"
              />
              {round.date && (
                <Typography variant="caption" color="text.secondary">
                  ({round.date})
                </Typography>
              )}
              {(round.mode === "offline" || round.mode === "hybrid") && round.infraRequirement && (
                <Typography variant="caption" color="text.secondary">
                  [Infra: {round.infraRequirement}]
                </Typography>
              )}
              {round.details && (
                <Typography variant="caption" color="text.secondary" sx={{ fontStyle: "italic", ml: 1 }}>
                  - {round.details}
                </Typography>
              )}
            </Stack>
          ))}
        </Stack>
      </PreviewSection>

      {/* Declaration */}
      <PreviewSection
        title="Declaration"
        onEdit={() => onNavigateToTab(4)}
        complete={allDeclarationsAccepted && !!signatory.name}
      >
        <Stack spacing={1}>
          <Typography variant="body2">
            ✅ All {Object.keys(declarations).length} declarations accepted: {allDeclarationsAccepted ? "Yes" : "No"}
          </Typography>
          <Divider />
          <Typography variant="body2">
            <strong>Signatory:</strong> {signatory.name || "-"} ({signatory.designation || "-"})
          </Typography>
        </Stack>
      </PreviewSection>
    </Box>
  );
}
