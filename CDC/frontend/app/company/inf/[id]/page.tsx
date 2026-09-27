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
import { InfPreview } from "@/components/forms/shared/formpreview";
import type { ProgrammeEligibility } from "@/components/forms/shared/eligibilitygrid";
import type { ProgrammeStipend } from "@/components/forms/shared/stipendgrid";
import type { Currency } from "@/components/forms/shared/currencyselector";
import type { SelectionRound } from "@/components/forms/shared/selectionprocessbuilder";

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
    about?: string;
    industry?: string;
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
  eligibility?: ProgrammeEligibility[];
  globalCgpa?: string;
  globalBacklogs?: boolean;
  genderFilter?: "all" | "male" | "female";
  slpRequirement?: string;
  currency?: string;
  programmeStipends?: ProgrammeStipend[];
  ppoProvision?: boolean;
  ppoCtc?: string;
  selectionRounds?: SelectionRound[];
  teamMembers?: string;
  roomsRequired?: string;
  declarations?: {
    aipc: boolean;
    shortlistCriteria: boolean;
    infoVerified: boolean;
    consentLogo: boolean;
    confirmAccuracy: boolean;
    resultsViaCdc: boolean;
  };
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
  graduating_batch?: string | null;
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

      {/* Standardized Preview Layout */}
      <InfPreview
        readOnly={true}
        companyLogoUrl={inf?.company?.logo_url || formData.companyProfile?.logoUrl || null}
        companyProfile={{
          name: formData.companyProfile?.name || inf?.company?.name || "",
          website: formData.companyProfile?.website || "",
          about: formData.companyProfile?.about || "",
          industry: formData.companyProfile?.industry || "",
          sector: formData.companyProfile?.sector || "",
          employeeCount: formData.companyProfile?.employeeCount || "",
          postalAddress: formData.companyProfile?.postalAddress || "",
          categoryOrgType: formData.companyProfile?.categoryOrgType || "",
          dateOfEstablishment: formData.companyProfile?.dateOfEstablishment || "",
          annualTurnover: formData.companyProfile?.annualTurnover || "",
          linkedinUrl: formData.companyProfile?.linkedinUrl || "",
          industrySectorTags: formData.companyProfile?.industrySectorTags || "",
          mncHqCountryCity: formData.companyProfile?.mncHqCountryCity || "",
          natureOfBusiness: formData.companyProfile?.natureOfBusiness || "",
          companyDescription: formData.companyProfile?.companyDescription || "",
        }}
        internshipDetails={{
          title: formData.internshipTitle || inf?.internship_title || "",
          designation: formData.internshipDesignation || "",
          location: formData.internshipLocation || inf?.internship_location || "",
          workMode: formData.workMode || "onsite",
          expectedHires: formData.expectedHires || inf?.vacancies?.toString() || "",
          duration: formData.duration || inf?.internship_duration_weeks?.toString() || "",
          joiningMonth: formData.joiningMonth || "",
          skills: formData.skills || [],
          description: formData.internshipDescription || inf?.internship_description || "",
          registrationLink: formData.registrationLink || "",
          additionalInfo: formData.additionalInfo || "",
        }}
        eligibility={formData.eligibility || []}
        globalCgpa={formData.globalCgpa || "7.0"}
        globalBacklogs={formData.globalBacklogs ?? false}
        genderFilter={formData.genderFilter || "all"}
        slpRequirement={formData.slpRequirement || ""}
        graduatingBatch={formData.graduatingBatch || inf?.graduating_batch || ""}
        stipend={{
          currency: (formData.currency as Currency) || "INR",
          programmeStipends: formData.programmeStipends || [],
          ppoProvision: formData.ppoProvision ?? false,
          ppoCtc: formData.ppoCtc || "",
        }}
        selectionProcess={{
          rounds: formData.selectionRounds || [],
        }}
        declarations={formData.declarations || {
          aipc: false,
          shortlistCriteria: false,
          infoVerified: false,
          consentLogo: false,
          confirmAccuracy: false,
          resultsViaCdc: false,
        }}
        signatory={formData.signatory || {
          name: "",
          designation: "",
          date: "",
        }}
      />
    </Box>
  );
}
