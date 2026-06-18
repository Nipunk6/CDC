"use client";

import { useEffect, useState, use } from "react";
import Link from "next/link";
import {
  Alert,
  Avatar,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  Grid2,
  LinearProgress,
  List,
  ListItem,
  ListItemText,
  Paper,
  Stack,
  Typography,
  alpha,
} from "@mui/material";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";
import BusinessIcon from "@mui/icons-material/Business";
import SchoolIcon from "@mui/icons-material/School";
import PaidIcon from "@mui/icons-material/Paid";
import AssignmentIcon from "@mui/icons-material/Assignment";
import CheckCircleIcon from "@mui/icons-material/CheckCircle";
import WorkIcon from "@mui/icons-material/Work";
import DescriptionIcon from "@mui/icons-material/Description";
import { companyApi } from "@/lib/companyapi";

type FormData = {
  companyProfile?: {
    name?: string;
    website?: string;
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
    logoUrl?: string | null;
  };
  internshipTitle?: string;
  internshipDesignation?: string;
  internshipLocation?: string;
  workMode?: string;
  expectedHires?: string;
  duration?: string;
  joiningMonth?: string;
  skills?: string[];
  internshipDescription?: string;
  additionalInfo?: string;
  registrationLink?: string;
  graduatingBatch?: string;
  eligibility?: Array<{
    programme: string;
    batch?: string;
    branches: Array<{ branch: string; selected: boolean; cgpa: string; backlogs: boolean }>;
  }>;
  globalCgpa?: string;
  globalBacklogs?: boolean;
  genderFilter?: "all" | "male" | "female";
  slpRequirement?: string;
  currency?: string;
  programmeStipends?: Array<{
    programme: string;
    enabled: boolean;
    baseStipend: string;
    hra: string;
    otherAllowances: string;
    total: string;
  }>;
  ppoProvision?: boolean;
  ppoCtc?: string;
  selectionRounds?: Array<{
    id: string;
    type: string;
    enabled: boolean;
    mode: string;
    duration: string;
    details: string;
  }>;
  teamMembers?: string;
  roomsRequired?: string;
  declarations?: Record<string, boolean>;
  signatory?: {
    name: string;
    designation: string;
    date: string;
  };
};

type Inf = {
  id: number;
  internship_title: string;
  internship_description: string;
  internship_location: string | null;
  stipend: number | null;
  internship_duration_weeks: number | null;
  vacancies: number | null;
  application_deadline: string | null;
  status: string;
  admin_remarks: string | null;
  edit_access_requested_at: string | null;
  edit_access_requested_reason: string | null;
  form_data?: FormData | string | null;
  created_at: string;
  updated_at: string;
  company?: {
    name: string;
    logo_url?: string | null;
  };
};

type StatusHistoryEntry = {
  new_status: string;
  remarks: string | null;
};

const getCurrencySymbol = (currency?: string) => {
  switch (currency) {
    case "USD": return "$";
    case "EUR": return "€";
    case "GBP": return "£";
    default: return "₹";
  }
};

const getStatusColor = (status: string) => {
  switch (status) {
    case "accepted": return "success";
    case "submitted": return "warning";
    case "under_review": return "info";
    case "rejected": return "error";
    default: return "default";
  }
};

function SectionCard({ title, icon, children }: { title: string; icon: React.ReactNode; children: React.ReactNode }) {
  return (
    <Card sx={{ mb: 2 }}>
      <Box sx={{ px: 2, py: 1.5, bgcolor: alpha("#9c27b0", 0.05), borderBottom: "1px solid", borderColor: "divider" }}>
        <Stack direction="row" spacing={1} alignItems="center">
          {icon}
          <Typography variant="subtitle1" fontWeight={600}>{title}</Typography>
        </Stack>
      </Box>
      <CardContent>{children}</CardContent>
    </Card>
  );
}

function DataRow({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <Grid2 size={{ xs: 12, sm: 6, md: 4 }}>
      <Typography variant="caption" color="text.secondary" display="block">{label}</Typography>
      <Typography variant="body2" fontWeight={500}>{value || "-"}</Typography>
    </Grid2>
  );
}

export default function ViewInfPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const [inf, setInf] = useState<Inf | null>(null);
  const [statusHistory, setStatusHistory] = useState<StatusHistoryEntry[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [requestingEditAccess, setRequestingEditAccess] = useState(false);

  useEffect(() => {
    const run = async () => {
      try {
        const response = await companyApi<{ inf: Inf; status_history: StatusHistoryEntry[] }>(
          `/company/infs/${id}`,
        );
        setInf(response.inf);
        setStatusHistory(response.status_history ?? []);
      } catch (e) {
        setError(e instanceof Error ? e.message : "Failed to load INF.");
      } finally {
        setLoading(false);
      }
    };

    void run();
  }, [id]);

  const latestUnderReviewRemark = statusHistory.find(
    (entry) => entry.new_status === "under_review" && Boolean(entry.remarks?.trim()),
  )?.remarks;

  const reviewMessage = inf?.admin_remarks?.trim() || latestUnderReviewRemark?.trim() || null;

  const requestEditAccess = async () => {
    if (!inf) return;

    const reason = window.prompt("Briefly explain why you need edit access again:");
    if (reason === null) return;

    const trimmedReason = reason.trim();
    if (!trimmedReason) {
      setError("Please provide a brief reason.");
      return;
    }

    setError(null);
    setSuccess(null);
    setRequestingEditAccess(true);

    try {
      const response = await companyApi<{ inf: Inf }>(`/company/infs/${inf.id}/request-edit-access`, {
        method: "POST",
        body: JSON.stringify({ reason: trimmedReason }),
      });

      setInf(response.inf);
      setSuccess("Edit access request submitted.");
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to request edit access.");
    } finally {
      setRequestingEditAccess(false);
    }
  };

  if (loading) {
    return (
      <Box sx={{ py: 4 }}>
        <LinearProgress />
        <Typography textAlign="center" mt={2}>Loading INF details...</Typography>
      </Box>
    );
  }

  // Parse form_data
  const formData: FormData = inf?.form_data
    ? typeof inf.form_data === "string"
      ? JSON.parse(inf.form_data)
      : inf.form_data
    : {};

  const symbol = getCurrencySymbol(formData.currency);
  const selectedBranches = (formData.eligibility ?? []).flatMap((p) =>
    p.branches.filter((b) => b.selected).map((b) => `${b.branch} (${p.programme})`)
  );
  const enabledStipends = (formData.programmeStipends ?? []).filter((s) => s.enabled);
  const enabledRounds = (formData.selectionRounds ?? []).filter((r) => r.enabled);
  const companyProfile = formData.companyProfile;

  return (
    <Box>
      {/* Header */}
      <Paper
        sx={{
          p: 2,
          mb: 3,
          background: (theme) =>
            `linear-gradient(135deg, ${theme.palette.secondary.main} 0%, ${theme.palette.secondary.dark} 100%)`,
          color: "white",
          borderRadius: 2,
        }}
      >
        <Stack direction={{ xs: "column", md: "row" }} justifyContent="space-between" alignItems={{ md: "center" }} spacing={2}>
          <Stack direction="row" spacing={2} alignItems="center">
            <Avatar 
              src={inf?.company?.logo_url || formData.companyProfile?.logoUrl || undefined}
              sx={{ width: 48, height: 48, bgcolor: "white", color: "secondary.main" }}
            >
              {!(inf?.company?.logo_url || formData.companyProfile?.logoUrl) && <SchoolIcon />}
            </Avatar>
            <Box>
              <Typography variant="h5" fontWeight={700}>
                {formData.internshipTitle || inf?.internship_title || "INF Details"}
              </Typography>
              <Typography variant="body2" sx={{ opacity: 0.9 }}>
                Submitted {inf?.created_at ? new Date(inf.created_at).toLocaleDateString() : "-"}
              </Typography>
            </Box>
          </Stack>
          <Stack direction="row" spacing={1} alignItems="center">
            <Chip
              label={(inf?.status ?? "").replace("_", " ").toUpperCase()}
              color={getStatusColor(inf?.status ?? "") as "success" | "warning" | "info" | "error" | "default"}
              sx={{ color: "white", fontWeight: 600 }}
            />
            <Button
              variant="outlined"
              startIcon={<ArrowBackIcon />}
              component={Link}
              href="/company"
              sx={{ color: "white", borderColor: "white" }}
            >
              Back
            </Button>
          </Stack>
        </Stack>
      </Paper>

      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
      {success && <Alert severity="success" sx={{ mb: 2 }}>{success}</Alert>}

      {/* Action bar */}
      <Stack direction="row" spacing={2} sx={{ mb: 3 }}>
        {inf?.status === "submitted" && (
          inf.edit_access_requested_at ? (
            <Alert severity="info" sx={{ flex: 1 }}>
              Edit access request submitted on {new Date(inf.edit_access_requested_at).toLocaleString()}.
              {inf.edit_access_requested_reason ? ` Reason: ${inf.edit_access_requested_reason}` : ""}
            </Alert>
          ) : (
            <Button
              variant="outlined"
              onClick={() => void requestEditAccess()}
              disabled={requestingEditAccess}
            >
              {requestingEditAccess ? "Requesting..." : "Request Edit Access"}
            </Button>
          )
        )}
        {inf?.status === "under_review" && reviewMessage && (
          <Alert severity="info" sx={{ flex: 1 }}>
            <strong>Admin Review Message:</strong> {reviewMessage}
          </Alert>
        )}
        {(inf?.status === "draft" || inf?.status === "under_review") && (
          <Button
            component={Link}
            href={`/company/inf/${inf?.id}/edit`}
            variant="contained"
          >
            Edit INF
          </Button>
        )}
      </Stack>

      {/* Company Profile */}
      {companyProfile && (
        <SectionCard title="Company Profile" icon={<BusinessIcon color="secondary" />}>
          <Grid2 container spacing={2}>
            <DataRow label="Company Name" value={companyProfile.name} />
            <DataRow label="Sector" value={companyProfile.sector} />
            <DataRow label="Website" value={companyProfile.website} />
            <DataRow label="Number of Employees" value={companyProfile.employeeCount} />
            <DataRow label="Category / Organization Type" value={companyProfile.categoryOrgType} />
            <DataRow label="Date of Establishment" value={companyProfile.dateOfEstablishment} />
            <DataRow label="Annual Turnover" value={companyProfile.annualTurnover} />
            <DataRow label="LinkedIn URL" value={companyProfile.linkedinUrl} />
            <DataRow label="Nature of Business" value={companyProfile.natureOfBusiness} />
            {companyProfile.companyDescription && (
              <Grid2 size={12}>
                <Typography variant="caption" color="text.secondary" display="block">Company Description</Typography>
                <Typography variant="body2" mt={0.5} component="div" dangerouslySetInnerHTML={{ __html: companyProfile.companyDescription }} />
              </Grid2>
            )}
          </Grid2>
        </SectionCard>
      )}

      {/* Internship Details */}
      <SectionCard title="Internship Details" icon={<SchoolIcon color="secondary" />}>
        <Grid2 container spacing={2}>
          <DataRow label="Internship Title" value={formData.internshipTitle || inf?.internship_title} />
          <DataRow label="Designation" value={formData.internshipDesignation} />
          <DataRow label="Location" value={formData.internshipLocation || inf?.internship_location} />
          <DataRow label="Work Mode" value={formData.workMode} />
          <DataRow label="Duration" value={formData.duration ? `${formData.duration} weeks` : (inf?.internship_duration_weeks ? `${inf.internship_duration_weeks} weeks` : null)} />
          <DataRow label="Expected Hires" value={formData.expectedHires || inf?.vacancies} />
          <DataRow label="Joining Month" value={formData.joiningMonth} />
          <DataRow
            label="Registration Link"
            value={formData.registrationLink ? (
              <a href={formData.registrationLink} target="_blank" rel="noopener noreferrer">
                {formData.registrationLink}
              </a>
            ) : null}
          />
          <Grid2 size={12}>
            <Typography variant="caption" color="text.secondary" display="block">Skills Required</Typography>
            <Stack direction="row" flexWrap="wrap" gap={0.5} mt={0.5}>
              {(formData.skills ?? []).length > 0 ? (
                formData.skills?.map((skill) => (
                  <Chip key={skill} label={skill} size="small" variant="outlined" />
                ))
              ) : (
                <Typography variant="body2" color="text.secondary">Not specified</Typography>
              )}
            </Stack>
          </Grid2>
          <Grid2 size={12}>
            <Typography variant="caption" color="text.secondary" display="block">Internship Description</Typography>
            <Paper variant="outlined" sx={{ p: 2, mt: 0.5, bgcolor: "grey.50" }}>
              <Typography variant="body2" component="div" dangerouslySetInnerHTML={{ __html: formData.internshipDescription || inf?.internship_description || "Not provided" }} />
            </Paper>
          </Grid2>
          {formData.additionalInfo && (
            <Grid2 size={12}>
              <Typography variant="caption" color="text.secondary" display="block">Additional Information</Typography>
              <Typography variant="body2" mt={0.5}>{formData.additionalInfo}</Typography>
            </Grid2>
          )}
        </Grid2>
      </SectionCard>

      {/* Eligibility */}
      <SectionCard title="Eligibility Criteria" icon={<WorkIcon color="secondary" />}>
        <Grid2 container spacing={2}>
          <DataRow label="Graduating Batch" value={formData.graduatingBatch || (formData.eligibility && formData.eligibility[0] ? formData.eligibility[0].batch : "-")} />
          <DataRow label="Minimum CGPA" value={formData.globalCgpa} />
          <DataRow label="Backlogs Allowed" value={formData.globalBacklogs ? "Yes" : "No"} />
          <DataRow label="Gender Preference" value={formData.genderFilter?.toUpperCase()} />
          <DataRow label="SLP Requirement" value={formData.slpRequirement} />
          <Grid2 size={12}>
            <Typography variant="caption" color="text.secondary" display="block">
              Eligible Branches ({selectedBranches.length} selected)
            </Typography>
            <Stack direction="row" flexWrap="wrap" gap={0.5} mt={0.5}>
              {selectedBranches.length > 0 ? (
                selectedBranches.map((branch) => (
                  <Chip key={branch} label={branch} size="small" color="secondary" variant="outlined" />
                ))
              ) : (
                <Typography variant="body2" color="text.secondary">No branches selected</Typography>
              )}
            </Stack>
          </Grid2>
        </Grid2>
      </SectionCard>

      {/* Stipend */}
      <SectionCard title="Stipend Details" icon={<PaidIcon color="secondary" />}>
        <Grid2 container spacing={2}>
          <DataRow label="Currency" value={formData.currency || "INR"} />
          <DataRow label="PPO Provision" value={formData.ppoProvision ? "Yes" : "No"} />
          {formData.ppoProvision && formData.ppoCtc && (
            <DataRow label="PPO CTC" value={`${symbol}${parseInt(formData.ppoCtc).toLocaleString()}`} />
          )}
          <Grid2 size={12}>
            <Typography variant="caption" color="text.secondary" display="block" mb={1}>
              Programme-wise Stipend
            </Typography>
            {enabledStipends.length > 0 ? (
              <List dense disablePadding>
                {enabledStipends.map((s) => (
                  <ListItem key={s.programme} disablePadding sx={{ py: 0.5 }}>
                    <ListItemText
                      primary={s.programme}
                      secondary={`Base: ${symbol}${s.baseStipend ? parseInt(s.baseStipend).toLocaleString() : "-"}/month | HRA: ${symbol}${s.hra ? parseInt(s.hra).toLocaleString() : "0"} | Total: ${symbol}${s.total ? parseInt(s.total).toLocaleString() : "-"}/month`}
                    />
                  </ListItem>
                ))}
              </List>
            ) : (
              <Typography variant="body2" color="text.secondary">No stipend details provided</Typography>
            )}
          </Grid2>
        </Grid2>
      </SectionCard>

      {/* Selection Process */}
      <SectionCard title="Selection Process" icon={<AssignmentIcon color="secondary" />}>
        <Grid2 container spacing={2}>
          <DataRow label="Team Members Required" value={formData.teamMembers} />
          <DataRow label="Rooms Required" value={formData.roomsRequired} />
          <Grid2 size={12}>
            <Typography variant="caption" color="text.secondary" display="block" mb={1}>
              Selection Rounds ({enabledRounds.length})
            </Typography>
            {enabledRounds.length > 0 ? (
              <Stack spacing={1}>
                {enabledRounds.map((round, idx) => (
                  <Paper key={round.id} variant="outlined" sx={{ p: 1.5 }}>
                    <Stack direction="row" justifyContent="space-between" alignItems="center">
                      <Stack direction="row" spacing={1} alignItems="center">
                        <Chip label={`Round ${idx + 1}`} size="small" color="secondary" />
                        <Typography variant="body2" fontWeight={500}>
                          {round.type.replace("_", " ")}
                        </Typography>
                      </Stack>
                      <Stack direction="row" spacing={1}>
                        <Chip label={round.mode} size="small" variant="outlined" />
                        {round.duration && <Chip label={round.duration} size="small" variant="outlined" />}
                      </Stack>
                    </Stack>
                    {round.details && (
                      <Typography variant="body2" color="text.secondary" mt={1}>
                        {round.details}
                      </Typography>
                    )}
                  </Paper>
                ))}
              </Stack>
            ) : (
              <Typography variant="body2" color="text.secondary">No selection rounds specified</Typography>
            )}
          </Grid2>
        </Grid2>
      </SectionCard>

      {/* Declaration */}
      {formData.signatory && (
        <SectionCard title="Declaration & Signatory" icon={<DescriptionIcon color="secondary" />}>
          <Grid2 container spacing={2}>
            <DataRow label="Signatory Name" value={formData.signatory.name} />
            <DataRow label="Designation" value={formData.signatory.designation} />
            <DataRow label="Date" value={formData.signatory.date} />
            <Grid2 size={12}>
              <Typography variant="caption" color="text.secondary" display="block">Declarations</Typography>
              <Typography variant="body2" color="success.main" mt={0.5}>
                ✅ All declarations accepted
              </Typography>
            </Grid2>
          </Grid2>
        </SectionCard>
      )}
    </Box>
  );
}
