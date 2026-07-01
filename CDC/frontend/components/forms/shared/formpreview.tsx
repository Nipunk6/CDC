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
  TextField,
} from "@mui/material";
import BusinessIcon from "@mui/icons-material/Business";
import CheckCircleIcon from "@mui/icons-material/CheckCircle";
import EditIcon from "@mui/icons-material/Edit";
import { Currency, getCurrencySymbol } from "./currencyselector";
import { ProgrammeEligibility } from "./eligibilitygrid";
import { ProgrammeSalary, SalaryComponents, getDisplayName } from "./salarygrid";
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
  companyProfile: {
    name: string;
    website: string;
    about?: string;
    industry?: string;
    sector?: string;
    employeeCount?: string;
    postalAddress?: string;
    categoryOrgType?: string;
    dateOfEstablishment?: string;
    annualTurnover?: string;
    linkedinUrl?: string;
    industrySectorTags?: string;
    mncHqCountryCity?: string;
    natureOfBusiness?: string;
    companyDescription?: string;
  };
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
    additionalInfo?: string;
  };
  eligibility: ProgrammeEligibility[];
  globalCgpa: string;
  globalBacklogs: boolean;
  genderFilter: string;
  slpRequirement: string;
  graduatingBatch: string;
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
  onSignatoryChange?: (signatory: JnfPreviewProps["signatory"]) => void;
  onNavigateToTab?: (tab: number) => void;
  companyLogoUrl?: string | null;
  readOnly?: boolean;
}

export const stripHtml = (html: string | undefined | null): string => {
  if (!html) return "";
  let cleaned = html.replace(/<[^>]*>/g, "");
  cleaned = cleaned
    .replace(/&nbsp;/g, " ")
    .replace(/&amp;/g, "&")
    .replace(/&lt;/g, "<")
    .replace(/&gt;/g, ">")
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'")
    .replace(/&apos;/g, "'");
  return cleaned.trim();
};

export function JnfPreview({
  companyProfile,
  jobDetails,
  eligibility,
  globalCgpa,
  globalBacklogs,
  genderFilter,
  slpRequirement,
  graduatingBatch,
  salary,
  selectionProcess,
  declarations,
  signatory,
  onSignatoryChange,
  onNavigateToTab,
  companyLogoUrl,
  readOnly = false,
}: JnfPreviewProps) {
  const symbol = getCurrencySymbol(salary.currency);
  const formatCurrency = (val: string | undefined | null) => {
    if (!val) return "-";
    if (/^\d+$/.test(val.trim())) {
      return `${symbol}${parseInt(val.trim(), 10).toLocaleString()}`;
    }
    return val;
  };
  const formatCompensationField = (value: string | undefined | null, showSymbol: boolean = true) => {
    if (!value) return null;
    if (/^\d+$/.test(value.trim())) {
      const num = parseInt(value.trim(), 10);
      return showSymbol ? `${symbol}${num.toLocaleString()}` : num.toLocaleString();
    }
    return value;
  };
  const selectedBranches = eligibility.flatMap((p) =>
    p.branches.filter((b) => b.selected).map((b) => `${b.branch} (${getDisplayName(p.programme)})`)
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

      {/* Company Profile */}
      <PreviewSection
        title="Company Profile"
        onEdit={onNavigateToTab ? () => onNavigateToTab(0) : undefined}
        complete={!!companyProfile.name && !!companyProfile.website}
      >
        <Stack direction="row" spacing={2} alignItems="center" mb={2} flexWrap="wrap">
          {companyLogoUrl && (
            <Avatar src={companyLogoUrl} alt={companyProfile.name} sx={{ width: 56, height: 56 }} />
          )}
          <Box>
            <Typography variant="caption" color="text.secondary">Company Name</Typography>
            <Typography variant="body2" fontWeight={600} fontSize="1.1rem">{companyProfile.name || "-"}</Typography>
          </Box>
        </Stack>
        <Grid2 container spacing={2}>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Website</Typography>
            <Typography variant="body2">{companyProfile.website || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Sector</Typography>
            <Typography variant="body2">{companyProfile.sector || companyProfile.industry || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Category / Org Type</Typography>
            <Typography variant="body2">{companyProfile.categoryOrgType || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Establishment Date</Typography>
            <Typography variant="body2">{companyProfile.dateOfEstablishment || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Employee Count</Typography>
            <Typography variant="body2">{companyProfile.employeeCount || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Annual Turnover</Typography>
            <Typography variant="body2">{companyProfile.annualTurnover || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">LinkedIn URL</Typography>
            <Typography variant="body2">{companyProfile.linkedinUrl || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">MNC HQ (Country/City)</Typography>
            <Typography variant="body2">{companyProfile.mncHqCountryCity || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 12, md: 6 }}>
            <Typography variant="caption" color="text.secondary">Industry Sector Tags</Typography>
            <Typography variant="body2">{companyProfile.industrySectorTags || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 12, md: 6 }}>
            <Typography variant="caption" color="text.secondary">Postal Address</Typography>
            <Typography variant="body2">{companyProfile.postalAddress || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 12 }}>
            <Typography variant="caption" color="text.secondary">Nature of Business</Typography>
            <Typography variant="body2">{companyProfile.natureOfBusiness || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 12 }}>
            <Typography variant="caption" color="text.secondary">Company Description</Typography>
            <Paper 
              variant="outlined" 
              sx={{ 
                p: 2, 
                mt: 0.5, 
                bgcolor: "grey.50",
                whiteSpace: "pre-line"
              }}
            >
              <Typography variant="body2">{stripHtml(companyProfile.companyDescription || companyProfile.about || "No description provided.")}</Typography>
            </Paper>
          </Grid2>
        </Grid2>
      </PreviewSection>

      {/* Job Details */}
      <PreviewSection
        title="Job Details"
        onEdit={onNavigateToTab ? () => onNavigateToTab(1) : undefined}
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
            <Typography variant="body2" sx={{ textTransform: "capitalize" }}>{jobDetails.workMode || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Expected Hires</Typography>
            <Typography variant="body2">{jobDetails.expectedHires || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Minimum Hires</Typography>
            <Typography variant="body2">{jobDetails.minimumHires || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Joining Month</Typography>
            <Typography variant="body2">{jobDetails.joiningMonth || "-"}</Typography>
          </Grid2>
          {jobDetails.registrationLink && (
            <Grid2 size={{ xs: 12, md: 6 }}>
              <Typography variant="caption" color="text.secondary">Registration Link</Typography>
              <Typography variant="body2">{jobDetails.registrationLink}</Typography>
            </Grid2>
          )}
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
          {jobDetails.additionalInfo && (
            <Grid2 size={{ xs: 12 }}>
              <Typography variant="caption" color="text.secondary">Additional Info / Special Requirements</Typography>
              <Typography variant="body2">{jobDetails.additionalInfo}</Typography>
            </Grid2>
          )}
          <Grid2 size={{ xs: 12 }}>
            <Typography variant="caption" color="text.secondary">Job Description</Typography>
            <Paper 
              variant="outlined" 
              sx={{ 
                p: 2, 
                mt: 0.5, 
                bgcolor: "grey.50",
                whiteSpace: "pre-line"
              }}
            >
              <Typography variant="body2">{stripHtml(jobDetails.description || "No description provided.")}</Typography>
            </Paper>
          </Grid2>
        </Grid2>
      </PreviewSection>

      {/* Eligibility */}
      <PreviewSection
        title="Eligibility Criteria"
        onEdit={onNavigateToTab ? () => onNavigateToTab(2) : undefined}
        complete={selectedBranches.length > 0}
      >
        <Grid2 container spacing={2}>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Graduating Batch</Typography>
            <Typography variant="body2" fontWeight={600}>{graduatingBatch || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Cutoff CGPA</Typography>
            <Typography variant="body2" fontWeight={500}>{globalCgpa || "No Cutoff"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Active Backlogs Allowed</Typography>
            <Typography variant="body2">{globalBacklogs ? "Yes" : "No"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Gender Preference</Typography>
            <Typography variant="body2" sx={{ textTransform: "capitalize" }}>{genderFilter || "All"}</Typography>
          </Grid2>
          {slpRequirement && (
            <Grid2 size={{ xs: 12 }}>
              <Typography variant="caption" color="text.secondary">SLP (Skill-based Learning Program) Requirements</Typography>
              <Typography variant="body2">{slpRequirement}</Typography>
            </Grid2>
          )}
          <Grid2 size={{ xs: 12 }}>
            <Typography variant="caption" color="text.secondary" gutterBottom sx={{ display: "block" }}>Selected Branches</Typography>
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
          </Grid2>
        </Grid2>
      </PreviewSection>

      {/* Salary */}
      <PreviewSection
        title="Compensation Details"
        onEdit={onNavigateToTab ? () => onNavigateToTab(3) : undefined}
        complete={enabledSalaries.some((s) => s.ctcAnnual)}
      >
        <List dense disablePadding>
          {enabledSalaries.map((s) => (
             <ListItem key={s.programme} disablePadding sx={{ py: 0.5 }}>
              <ListItemText
                primary={getDisplayName(s.programme)}
                secondary={`CTC: ${formatCurrency(s.ctcAnnual)} | Base: ${formatCurrency(s.baseSalary)} | Take Home: ${formatCurrency(s.takeHome)}`}
              />
            </ListItem>
          ))}
        </List>
        <Grid2 container spacing={2} mt={2}>
          {formatCompensationField(salary.components.joiningBonus) && (
            <Grid2 size={{ xs: 6, md: 3 }}>
              <Typography variant="caption" color="text.secondary">Joining Bonus</Typography>
              <Typography variant="body2">{formatCompensationField(salary.components.joiningBonus)}</Typography>
            </Grid2>
          )}
          {formatCompensationField(salary.components.retentionBonus) && (
            <Grid2 size={{ xs: 6, md: 3 }}>
              <Typography variant="caption" color="text.secondary">Retention Bonus</Typography>
              <Typography variant="body2">{formatCompensationField(salary.components.retentionBonus)}</Typography>
            </Grid2>
          )}
          {formatCompensationField(salary.components.performanceBonus) && (
            <Grid2 size={{ xs: 6, md: 3 }}>
              <Typography variant="caption" color="text.secondary">Performance Bonus</Typography>
              <Typography variant="body2">{formatCompensationField(salary.components.performanceBonus)}</Typography>
            </Grid2>
          )}
          {formatCompensationField(salary.components.esops, false) && (
            <Grid2 size={{ xs: 6, md: 3 }}>
              <Typography variant="caption" color="text.secondary">ESOPs / Stocks</Typography>
              <Typography variant="body2">{formatCompensationField(salary.components.esops, false)} {salary.components.vestPeriod ? `(Vest: ${salary.components.vestPeriod})` : ""}</Typography>
            </Grid2>
          )}
          {formatCompensationField(salary.components.stocks) && (
            <Grid2 size={{ xs: 6, md: 3 }}>
              <Typography variant="caption" color="text.secondary">Stocks / RSUs</Typography>
              <Typography variant="body2">{formatCompensationField(salary.components.stocks)}</Typography>
            </Grid2>
          )}
          {formatCompensationField(salary.components.relocationAllowance) && (
            <Grid2 size={{ xs: 6, md: 3 }}>
              <Typography variant="caption" color="text.secondary">Relocation Allowance</Typography>
              <Typography variant="body2">{formatCompensationField(salary.components.relocationAllowance)}</Typography>
            </Grid2>
          )}
          {formatCompensationField(salary.components.medicalAllowance) && (
            <Grid2 size={{ xs: 6, md: 3 }}>
              <Typography variant="caption" color="text.secondary">Medical Allowance</Typography>
              <Typography variant="body2">{formatCompensationField(salary.components.medicalAllowance)}</Typography>
            </Grid2>
          )}
          {formatCompensationField(salary.components.deductions, false) && (
            <Grid2 size={{ xs: 6, md: 3 }}>
              <Typography variant="caption" color="text.secondary">Deductions</Typography>
              <Typography variant="body2">{formatCompensationField(salary.components.deductions, false)}</Typography>
            </Grid2>
          )}
          {formatCompensationField(salary.components.bondAmount) && (
            <Grid2 size={{ xs: 6, md: 3 }}>
              <Typography variant="caption" color="text.secondary">Service Bond</Typography>
              <Typography variant="body2">{formatCompensationField(salary.components.bondAmount)} {salary.components.bondDuration ? `(${salary.components.bondDuration})` : ""}</Typography>
            </Grid2>
          )}
          {salary.components.ctcBreakup && (
            <Grid2 size={{ xs: 12 }}>
              <Typography variant="caption" color="text.secondary">Detailed CTC Breakup / Remarks</Typography>
              <Typography variant="body2" sx={{ whiteSpace: "pre-line" }}>{salary.components.ctcBreakup}</Typography>
            </Grid2>
          )}
        </Grid2>
      </PreviewSection>

      {/* Selection Process */}
      <PreviewSection
        title="Selection Process"
        onEdit={onNavigateToTab ? () => onNavigateToTab(4) : undefined}
        complete={enabledRounds.length > 0}
      >
        <Stack direction="column" gap={2}>
          {enabledRounds.map((round, idx) => (
            <Stack key={round.id} direction="column" spacing={0.5} sx={{ pl: 2, borderLeft: "2px solid", borderColor: "divider", py: 0.5 }}>
              <Stack direction="row" alignItems="center" spacing={1} flexWrap="wrap">
                <Chip
                  label={`${idx + 1}. ${round.type.replace("_", " ")}`}
                  size="small"
                  color="primary"
                  variant="outlined"
                  sx={{ textTransform: "capitalize" }}
                />
                <Chip
                  label={round.mode}
                  size="small"
                  variant="outlined"
                  sx={{ textTransform: "capitalize" }}
                />
                {round.duration && (
                  <Chip
                    label={`${round.duration} mins`}
                    size="small"
                    variant="outlined"
                  />
                )}
                {round.date && (
                  <Typography variant="caption" color="text.secondary">
                    Date: {round.date}
                  </Typography>
                )}
              </Stack>
              {round.infraRequirement && (
                <Typography variant="body2" color="text.secondary">
                  <strong>Infra Requirement:</strong> {round.infraRequirement}
                </Typography>
              )}
              {round.details && (
                <Typography variant="body2" color="text.secondary" sx={{ fontStyle: "italic" }}>
                  <strong>Details:</strong> {round.details}
                </Typography>
              )}
            </Stack>
          ))}
        </Stack>
      </PreviewSection>

      {/* Declaration & Signatory */}
      <PreviewSection
        title="Declaration & Signatory"
        onEdit={onNavigateToTab ? () => onNavigateToTab(5) : undefined}
        complete={allDeclarationsAccepted && !!signatory.name && !!signatory.designation && !!signatory.date}
      >
        <Stack spacing={3}>
          {allDeclarationsAccepted ? (
            <Typography variant="body2" color="success.main" fontWeight={500}>
              ✅ All {Object.keys(declarations).length} declarations have been accepted and agreed to.
            </Typography>
          ) : (
            <Typography variant="body2" color="error.main" fontWeight={500}>
              ❌ Declarations have not been accepted yet. Please go to the Declaration tab to review and accept them.
            </Typography>
          )}
          <Divider />
          
          {readOnly ? (
            <Stack spacing={1}>
              <Typography variant="body2">
                <strong>Signatory:</strong> {signatory.name || "-"} ({signatory.designation || "-"})
              </Typography>
              <Typography variant="body2">
                <strong>Date:</strong> {signatory.date || "-"}
              </Typography>
            </Stack>
          ) : (
            <Paper
              variant="outlined"
              sx={{
                p: 3,
                background: (theme) => alpha(theme.palette.secondary.main, 0.03),
                borderColor: "secondary.light",
              }}
            >
              <Typography variant="subtitle1" fontWeight={600} mb={2} color="secondary.dark">
                ✍️ Authorised Signatory
              </Typography>
              <Typography variant="body2" color="text.secondary" mb={3}>
                Please verify or fill in the details of the authorised representative submitting this form.
              </Typography>

              <Stack spacing={2}>
                <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
                  <TextField
                    fullWidth
                    label="Full Name of Signatory"
                    value={signatory.name}
                    onChange={(e) => onSignatoryChange?.({ ...signatory, name: e.target.value })}
                    required
                    placeholder="Enter full name"
                  />
                  <TextField
                    fullWidth
                    label="Designation"
                    value={signatory.designation}
                    onChange={(e) => onSignatoryChange?.({ ...signatory, designation: e.target.value })}
                    required
                    placeholder="e.g., HR Manager, Campus Recruiter"
                  />
                </Stack>
                <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
                  <TextField
                    type="date"
                    label="Date"
                    value={signatory.date}
                    InputProps={{ readOnly: true }}
                    InputLabelProps={{ shrink: true }}
                    required
                    sx={{ width: { xs: "100%", md: 200 } }}
                  />
                </Stack>
              </Stack>

              <Typography variant="caption" color="text.secondary" mt={2} display="block">
                By submitting this form, you confirm that you are authorised to represent your organisation
                and that all information provided is accurate to the best of your knowledge.
              </Typography>
            </Paper>
          )}
        </Stack>
      </PreviewSection>
    </Box>
  );
}

interface InfPreviewProps {
  companyProfile: {
    name: string;
    website: string;
    about?: string;
    industry?: string;
    sector?: string;
    employeeCount?: string;
    postalAddress?: string;
    categoryOrgType?: string;
    dateOfEstablishment?: string;
    annualTurnover?: string;
    linkedinUrl?: string;
    industrySectorTags?: string;
    mncHqCountryCity?: string;
    natureOfBusiness?: string;
    companyDescription?: string;
  };
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
    additionalInfo?: string;
  };
  eligibility: ProgrammeEligibility[];
  globalCgpa: string;
  globalBacklogs: boolean;
  genderFilter: string;
  slpRequirement: string;
  graduatingBatch: string;
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
  onSignatoryChange?: (signatory: InfPreviewProps["signatory"]) => void;
  onNavigateToTab?: (tab: number) => void;
  companyLogoUrl?: string | null;
  readOnly?: boolean;
}

export function InfPreview({
  companyProfile,
  internshipDetails,
  eligibility,
  globalCgpa,
  globalBacklogs,
  genderFilter,
  slpRequirement,
  graduatingBatch,
  stipend,
  selectionProcess,
  declarations,
  signatory,
  onSignatoryChange,
  onNavigateToTab,
  companyLogoUrl,
  readOnly = false,
}: InfPreviewProps) {
  const symbol = getCurrencySymbol(stipend.currency);
  const formatCurrency = (val: string | undefined | null) => {
    if (!val) return "-";
    if (/^\d+$/.test(val.trim())) {
      return `${symbol}${parseInt(val.trim(), 10).toLocaleString()}`;
    }
    return val;
  };
  const selectedBranches = eligibility.flatMap((p) =>
    p.branches.filter((b) => b.selected).map((b) => `${b.branch} (${getDisplayName(p.programme)})`)
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

      {/* Company Profile */}
      <PreviewSection
        title="Company Profile"
        onEdit={onNavigateToTab ? () => onNavigateToTab(0) : undefined}
        complete={!!companyProfile.name && !!companyProfile.website}
      >
        <Stack direction="row" spacing={2} alignItems="center" mb={2} flexWrap="wrap">
          {companyLogoUrl && (
            <Avatar src={companyLogoUrl} alt={companyProfile.name} sx={{ width: 56, height: 56 }} />
          )}
          <Box>
            <Typography variant="caption" color="text.secondary">Company Name</Typography>
            <Typography variant="body2" fontWeight={600} fontSize="1.1rem">{companyProfile.name || "-"}</Typography>
          </Box>
        </Stack>
        <Grid2 container spacing={2}>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Website</Typography>
            <Typography variant="body2">{companyProfile.website || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Sector</Typography>
            <Typography variant="body2">{companyProfile.sector || companyProfile.industry || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Category / Org Type</Typography>
            <Typography variant="body2">{companyProfile.categoryOrgType || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Establishment Date</Typography>
            <Typography variant="body2">{companyProfile.dateOfEstablishment || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Employee Count</Typography>
            <Typography variant="body2">{companyProfile.employeeCount || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Annual Turnover</Typography>
            <Typography variant="body2">{companyProfile.annualTurnover || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">LinkedIn URL</Typography>
            <Typography variant="body2">{companyProfile.linkedinUrl || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">MNC HQ (Country/City)</Typography>
            <Typography variant="body2">{companyProfile.mncHqCountryCity || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 12, md: 6 }}>
            <Typography variant="caption" color="text.secondary">Industry Sector Tags</Typography>
            <Typography variant="body2">{companyProfile.industrySectorTags || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 12, md: 6 }}>
            <Typography variant="caption" color="text.secondary">Postal Address</Typography>
            <Typography variant="body2">{companyProfile.postalAddress || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 12 }}>
            <Typography variant="caption" color="text.secondary">Nature of Business</Typography>
            <Typography variant="body2">{companyProfile.natureOfBusiness || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 12 }}>
            <Typography variant="caption" color="text.secondary">Company Description</Typography>
            <Paper 
              variant="outlined" 
              sx={{ 
                p: 2, 
                mt: 0.5, 
                bgcolor: "grey.50",
                whiteSpace: "pre-line"
              }}
            >
              <Typography variant="body2">{stripHtml(companyProfile.companyDescription || companyProfile.about || "No description provided.")}</Typography>
            </Paper>
          </Grid2>
        </Grid2>
      </PreviewSection>

      {/* Internship Details */}
      <PreviewSection
        title="Internship Details"
        onEdit={onNavigateToTab ? () => onNavigateToTab(1) : undefined}
        complete={!!internshipDetails.title && !!internshipDetails.description}
      >
        <Grid2 container spacing={2}>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Internship Title</Typography>
            <Typography variant="body2" fontWeight={500}>{internshipDetails.title || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Designation</Typography>
            <Typography variant="body2">{internshipDetails.designation || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Location</Typography>
            <Typography variant="body2">{internshipDetails.location || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Work Mode</Typography>
            <Typography variant="body2" sx={{ textTransform: "capitalize" }}>{internshipDetails.workMode || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Expected Hires</Typography>
            <Typography variant="body2">{internshipDetails.expectedHires || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Duration</Typography>
            <Typography variant="body2">{internshipDetails.duration ? `${internshipDetails.duration} weeks` : "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Joining Month</Typography>
            <Typography variant="body2">{internshipDetails.joiningMonth || "-"}</Typography>
          </Grid2>
          {internshipDetails.registrationLink && (
            <Grid2 size={{ xs: 12, md: 6 }}>
              <Typography variant="caption" color="text.secondary">Registration Link</Typography>
              <Typography variant="body2">{internshipDetails.registrationLink}</Typography>
            </Grid2>
          )}
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
          {internshipDetails.additionalInfo && (
            <Grid2 size={{ xs: 12 }}>
              <Typography variant="caption" color="text.secondary">Additional Info / Special Requirements</Typography>
              <Typography variant="body2">{internshipDetails.additionalInfo}</Typography>
            </Grid2>
          )}
          <Grid2 size={{ xs: 12 }}>
            <Typography variant="caption" color="text.secondary">Internship Description</Typography>
            <Paper 
              variant="outlined" 
              sx={{ 
                p: 2, 
                mt: 0.5, 
                bgcolor: "grey.50",
                whiteSpace: "pre-line"
              }}
            >
              <Typography variant="body2">{stripHtml(internshipDetails.description || "No description provided.")}</Typography>
            </Paper>
          </Grid2>
        </Grid2>
      </PreviewSection>

      {/* Eligibility */}
      <PreviewSection
        title="Eligibility Criteria"
        onEdit={onNavigateToTab ? () => onNavigateToTab(2) : undefined}
        complete={selectedBranches.length > 0}
      >
        <Grid2 container spacing={2}>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Graduating Batch</Typography>
            <Typography variant="body2" fontWeight={600}>{graduatingBatch || "-"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Cutoff CGPA</Typography>
            <Typography variant="body2" fontWeight={500}>{globalCgpa || "No Cutoff"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Active Backlogs Allowed</Typography>
            <Typography variant="body2">{globalBacklogs ? "Yes" : "No"}</Typography>
          </Grid2>
          <Grid2 size={{ xs: 6, md: 3 }}>
            <Typography variant="caption" color="text.secondary">Gender Preference</Typography>
            <Typography variant="body2" sx={{ textTransform: "capitalize" }}>{genderFilter || "All"}</Typography>
          </Grid2>
          {slpRequirement && (
            <Grid2 size={{ xs: 12 }}>
              <Typography variant="caption" color="text.secondary">SLP (Skill-based Learning Program) Requirements</Typography>
              <Typography variant="body2">{slpRequirement}</Typography>
            </Grid2>
          )}
          <Grid2 size={{ xs: 12 }}>
            <Typography variant="caption" color="text.secondary" gutterBottom sx={{ display: "block" }}>Selected Branches</Typography>
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
          </Grid2>
        </Grid2>
      </PreviewSection>

      {/* Stipend */}
      <PreviewSection
        title="Stipend Details"
        onEdit={onNavigateToTab ? () => onNavigateToTab(3) : undefined}
        complete={enabledStipends.some((s) => s.baseStipend)}
      >
        <List dense disablePadding>
          {enabledStipends.map((s) => (
             <ListItem key={s.programme} disablePadding sx={{ py: 0.5 }}>
              <ListItemText
                primary={getDisplayName(s.programme)}
                secondary={`Base: ${symbol}${s.baseStipend ? parseInt(s.baseStipend).toLocaleString() : "0"} | HRA: ${symbol}${s.hra ? parseInt(s.hra).toLocaleString() : "0"} | Perks: ${symbol}${s.otherPerks ? parseInt(s.otherPerks).toLocaleString() : "0"} | Total: ${symbol}${s.total ? parseInt(s.total).toLocaleString() : "0"}`}
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
        onEdit={onNavigateToTab ? () => onNavigateToTab(4) : undefined}
        complete={enabledRounds.length > 0}
      >
        <Stack direction="column" gap={2}>
          {enabledRounds.map((round, idx) => (
            <Stack key={round.id} direction="column" spacing={0.5} sx={{ pl: 2, borderLeft: "2px solid", borderColor: "divider", py: 0.5 }}>
              <Stack direction="row" alignItems="center" spacing={1} flexWrap="wrap">
                <Chip
                  label={`${idx + 1}. ${round.type.replace("_", " ")}`}
                  size="small"
                  color="primary"
                  variant="outlined"
                  sx={{ textTransform: "capitalize" }}
                />
                <Chip
                  label={round.mode}
                  size="small"
                  variant="outlined"
                  sx={{ textTransform: "capitalize" }}
                />
                {round.duration && (
                  <Chip
                    label={`${round.duration} mins`}
                    size="small"
                    variant="outlined"
                  />
                )}
                {round.date && (
                  <Typography variant="caption" color="text.secondary">
                    Date: {round.date}
                  </Typography>
                )}
              </Stack>
              {round.infraRequirement && (
                <Typography variant="body2" color="text.secondary">
                  <strong>Infra Requirement:</strong> {round.infraRequirement}
                </Typography>
              )}
              {round.details && (
                <Typography variant="body2" color="text.secondary" sx={{ fontStyle: "italic" }}>
                  <strong>Details:</strong> {round.details}
                </Typography>
              )}
            </Stack>
          ))}
        </Stack>
      </PreviewSection>

      {/* Declaration & Signatory */}
      <PreviewSection
        title="Declaration & Signatory"
        onEdit={onNavigateToTab ? () => onNavigateToTab(5) : undefined}
        complete={allDeclarationsAccepted && !!signatory.name && !!signatory.designation && !!signatory.date}
      >
        <Stack spacing={3}>
          {allDeclarationsAccepted ? (
            <Typography variant="body2" color="success.main" fontWeight={500}>
              ✅ All {Object.keys(declarations).length} declarations have been accepted and agreed to.
            </Typography>
          ) : (
            <Typography variant="body2" color="error.main" fontWeight={500}>
              ❌ Declarations have not been accepted yet. Please go to the Declaration tab to review and accept them.
            </Typography>
          )}
          <Divider />
          
          {readOnly ? (
            <Stack spacing={1}>
              <Typography variant="body2">
                <strong>Signatory:</strong> {signatory.name || "-"} ({signatory.designation || "-"})
              </Typography>
              <Typography variant="body2">
                <strong>Date:</strong> {signatory.date || "-"}
              </Typography>
            </Stack>
          ) : (
            <Paper
              variant="outlined"
              sx={{
                p: 3,
                background: (theme) => alpha(theme.palette.secondary.main, 0.03),
                borderColor: "secondary.light",
              }}
            >
              <Typography variant="subtitle1" fontWeight={600} mb={2} color="secondary.dark">
                ✍️ Authorised Signatory
              </Typography>
              <Typography variant="body2" color="text.secondary" mb={3}>
                Please verify or fill in the details of the authorised representative submitting this form.
              </Typography>

              <Stack spacing={2}>
                <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
                  <TextField
                    fullWidth
                    label="Full Name of Signatory"
                    value={signatory.name}
                    onChange={(e) => onSignatoryChange?.({ ...signatory, name: e.target.value })}
                    required
                    placeholder="Enter full name"
                  />
                  <TextField
                    fullWidth
                    label="Designation"
                    value={signatory.designation}
                    onChange={(e) => onSignatoryChange?.({ ...signatory, designation: e.target.value })}
                    required
                    placeholder="e.g., HR Manager, Campus Recruiter"
                  />
                </Stack>
                <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
                  <TextField
                    type="date"
                    label="Date"
                    value={signatory.date}
                    InputProps={{ readOnly: true }}
                    InputLabelProps={{ shrink: true }}
                    required
                    sx={{ width: { xs: "100%", md: 200 } }}
                  />
                </Stack>
              </Stack>

              <Typography variant="caption" color="text.secondary" mt={2} display="block">
                By submitting this form, you confirm that you are authorised to represent your organisation
                and that all information provided is accurate to the best of your knowledge.
              </Typography>
            </Paper>
          )}
        </Stack>
      </PreviewSection>
    </Box>
  );
}
