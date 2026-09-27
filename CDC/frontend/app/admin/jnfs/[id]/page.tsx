"use client";

import { useEffect, useState } from "react";
import { useParams, useRouter } from "next/navigation";
import {
  Alert,
  Avatar,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  Divider,
  Grid2,
  LinearProgress,
  List,
  ListItem,
  ListItemText,
  Paper,
  Stack,
  TextField,
  Typography,
  alpha,
  Checkbox,
  FormControlLabel,
} from "@mui/material";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";
import BusinessIcon from "@mui/icons-material/Business";
import WorkIcon from "@mui/icons-material/Work";
import SchoolIcon from "@mui/icons-material/School";
import PaidIcon from "@mui/icons-material/Paid";
import AssignmentIcon from "@mui/icons-material/Assignment";
import CheckCircleIcon from "@mui/icons-material/CheckCircle";
import PendingIcon from "@mui/icons-material/Pending";
import CancelIcon from "@mui/icons-material/Cancel";
import HourglassEmptyIcon from "@mui/icons-material/HourglassEmpty";
import DownloadIcon from "@mui/icons-material/Download";
import EditIcon from "@mui/icons-material/Edit";
import { adminApi, adminDownload } from "@/lib/adminapi";
import { JnfPreview, stripHtml } from "@/components/forms/shared/formpreview";
import SelectionProcessBuilder from "@/components/forms/shared/selectionprocessbuilder";
import type { SelectionRound } from "@/components/forms/shared/selectionprocessbuilder";
import type { ProgrammeEligibility } from "@/components/forms/shared/eligibilitygrid";

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
  jobTitle?: string;
  jobDesignation?: string;
  jobLocation?: string;
  workMode?: string;
  expectedHires?: string;
  minimumHires?: string;
  joiningMonth?: string;
  skills?: string[];
  jobDescription?: string;
  additionalInfo?: string;
  registrationLink?: string;
  graduatingBatch?: string;
  eligibility?: ProgrammeEligibility[];
  globalCgpa?: string;
  globalBacklogs?: boolean;
  genderFilter?: "all" | "male" | "female";
  slpRequirement?: string;
  currency?: string;
  programmeSalaries?: Array<{
    programme: string;
    enabled: boolean;
    ctcAnnual: string;
    baseSalary: string;
    takeHome: string;
  }>;
  salaryComponents?: {
    joiningBonus?: string;
    relocationBonus?: string;
    retentionBonus?: string;
    performanceBonus?: string;
    esops?: string;
    vestPeriod?: string;
    stocks?: string;
    relocationAllowance?: string;
    medicalAllowance?: string;
    deductions?: string;
    bondAmount?: string;
    bondDuration?: string;
    bondYears?: string;
    ctcBreakup?: string;
  };
  selectionRounds?: SelectionRound[];
  teamMembers?: string;
  roomsRequired?: string;
  declarations?: Record<string, boolean>;
  signatory?: {
    name: string;
    designation: string;
    date: string;
  };
};

type Jnf = {
  id: number;
  job_title: string;
  job_description: string;
  job_location: string | null;
  ctc_min: number | null;
  ctc_max: number | null;
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
    hr_name: string; 
    hr_email: string;
    website?: string;
    industry?: string;
    address?: string;
    logo_url?: string | null;
  };
};

type StatusHistoryEntry = {
  id: number;
  old_status: string | null;
  new_status: string;
  remarks: string | null;
  created_at: string;
  author_email?: string | null;
  changedBy?: {
    name?: string;
    email?: string;
    role?: string;
  } | null;
};

const getHistoryDisplayEmail = (entry: StatusHistoryEntry) => entry.author_email || "";

const getStatusColor = (status: string) => {
  switch (status) {
    case "accepted": return "success";
    case "submitted": return "warning";
    case "under_review": return "info";
    case "rejected": return "error";
    default: return "default";
  }
};

const getStatusIcon = (status: string) => {
  switch (status) {
    case "accepted": return <CheckCircleIcon />;
    case "submitted": return <HourglassEmptyIcon />;
    case "under_review": return <PendingIcon />;
    case "rejected": return <CancelIcon />;
    default: return null;
  }
};

const getCurrencySymbol = (currency?: string) => {
  switch (currency) {
    case "USD": return "$";
    case "EUR": return "€";
    case "GBP": return "£";
    default: return "₹";
  }
};

function SectionCard({ title, icon, children }: { title: string; icon: React.ReactNode; children: React.ReactNode }) {
  return (
    <Card sx={{ mb: 2 }}>
      <Box sx={{ px: 2, py: 1.5, bgcolor: alpha("#1976d2", 0.05), borderBottom: "1px solid", borderColor: "divider" }}>
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

export default function AdminJnfDetailPage() {
  const params = useParams<{ id: string }>();
  const router = useRouter();
  const [jnf, setJnf] = useState<Jnf | null>(null);
  const [statusHistory, setStatusHistory] = useState<StatusHistoryEntry[]>([]);
  const [reviewMarked, setReviewMarked] = useState(false);
  const [reviewedByEmail, setReviewedByEmail] = useState<string | null>(null);
  const [remarks, setRemarks] = useState("");
  const [draftNote, setDraftNote] = useState("");
  const [addingNote, setAddingNote] = useState(false);
  const [editingLatestRemark, setEditingLatestRemark] = useState(false);
  const [editingRemarkEntryId, setEditingRemarkEntryId] = useState<number | null>(null);
  const [editedRemark, setEditedRemark] = useState("");
  const [canEditLatestRemark, setCanEditLatestRemark] = useState(false);
  const [latestEditableRemarkId, setLatestEditableRemarkId] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [updating, setUpdating] = useState(false);
  const [editMode, setEditMode] = useState(false);
  const [editFormData, setEditFormData] = useState<FormData>({});
  const [savingEdit, setSavingEdit] = useState(false);

  useEffect(() => {
    const run = async () => {
      try {
        const response = await adminApi<{
          jnf: Jnf;
          status_history: StatusHistoryEntry[];
          review_marked?: boolean;
          reviewed_by_email?: string | null;
          can_edit_latest_remark?: boolean;
          latest_editable_remark_id?: number | null;
        }>(`/admin/jnfs/${params.id}`);
        setJnf(response.jnf);
        setRemarks("");
        setStatusHistory(response.status_history ?? []);
        setReviewMarked(response.review_marked === true);
        setReviewedByEmail(response.reviewed_by_email ?? null);
        setCanEditLatestRemark(response.can_edit_latest_remark === true);
        setLatestEditableRemarkId(response.latest_editable_remark_id ?? null);
      } catch (e) {
        setError(e instanceof Error ? e.message : "Failed to load JNF.");
      } finally {
        setLoading(false);
      }
    };
    void run();
  }, [params.id]);

  const addDraftNote = async () => {
    const note = draftNote.trim();
    if (!note) {
      setError("Please write a note before adding.");
      return;
    }

    setError(null);
    setSuccess(null);
    setAddingNote(true);

    try {
      await adminApi<{ message: string }>(`/admin/jnfs/${params.id}/notes`, {
        method: "POST",
        body: JSON.stringify({ note }),
      });
      setDraftNote("");
      setSuccess("Draft note added.");

      const refreshed = await adminApi<{
        jnf: Jnf;
        status_history: StatusHistoryEntry[];
        review_marked?: boolean;
        reviewed_by_email?: string | null;
        can_edit_latest_remark?: boolean;
        latest_editable_remark_id?: number | null;
      }>(`/admin/jnfs/${params.id}`);

      setStatusHistory(refreshed.status_history ?? []);
      setReviewMarked(refreshed.review_marked === true);
      setReviewedByEmail(refreshed.reviewed_by_email ?? null);
      setJnf(refreshed.jnf);
      setCanEditLatestRemark(refreshed.can_edit_latest_remark === true);
      setLatestEditableRemarkId(refreshed.latest_editable_remark_id ?? null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to add note.");
    } finally {
      setAddingNote(false);
    }
  };

  const updateStatus = async (status: "draft" | "under_review" | "accepted" | "rejected") => {
    setError(null);
    setSuccess(null);

    const isDraftReviewAction = status === "under_review" && (jnf?.status === "draft" || reviewMarked);

    if (['under_review', 'rejected'].includes(status) && !isDraftReviewAction && !remarks.trim()) {
      setError('Admin remarks are required for review or rejection.');
      return;
    }

    const confirmationMessageByStatus: Partial<Record<"draft" | "under_review" | "accepted" | "rejected", string>> = {
      under_review: isDraftReviewAction ? "Are you sure you want to mark this draft for review?" : "Are you sure you want to grant edit access for this JNF?",
      accepted: "Are you sure you want to accept this JNF?",
      rejected: "Are you sure you want to reject this JNF?",
      draft: "Are you sure you want to remove this draft from being marked for review?",
    };

    const confirmationMessage = confirmationMessageByStatus[status];
    if (confirmationMessage && !window.confirm(confirmationMessage)) {
      return;
    }

    setUpdating(true);

    try {
      const response = await adminApi<{ jnf: Jnf }>(`/admin/jnfs/${params.id}/status`, {
        method: "PATCH",
        body: JSON.stringify({ status, admin_remarks: isDraftReviewAction ? null : (remarks || null) }),
      });
      setJnf(response.jnf);
      setSuccess(`JNF marked as ${status.replace("_", " ")}.`);
      const refreshed = await adminApi<{
        jnf: Jnf;
        status_history: StatusHistoryEntry[];
        review_marked?: boolean;
        reviewed_by_email?: string | null;
        can_edit_latest_remark?: boolean;
        latest_editable_remark_id?: number | null;
      }>(`/admin/jnfs/${params.id}`);
      setStatusHistory(refreshed.status_history ?? []);
      setReviewMarked(refreshed.review_marked === true);
      setReviewedByEmail(refreshed.reviewed_by_email ?? null);
      setJnf(refreshed.jnf);
      setRemarks("");
      setCanEditLatestRemark(refreshed.can_edit_latest_remark === true);
      setLatestEditableRemarkId(refreshed.latest_editable_remark_id ?? null);
      setEditingRemarkEntryId(null);
      setEditedRemark("");
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to update JNF status.");
    } finally {
      setUpdating(false);
    }
  };

  const editLatestRemark = async () => {
    const remark = editedRemark.trim();
    if (!remark) {
      setError("Remark cannot be empty.");
      return;
    }

    setError(null);
    setSuccess(null);
    setEditingLatestRemark(true);

    try {
      await adminApi<{ message: string; remark: string; jnf: Jnf }>(`/admin/jnfs/${params.id}/remarks/latest`, {
        method: "PATCH",
        body: JSON.stringify({ remark }),
      });

      const refreshed = await adminApi<{
        jnf: Jnf;
        status_history: StatusHistoryEntry[];
        review_marked?: boolean;
        reviewed_by_email?: string | null;
        can_edit_latest_remark?: boolean;
        latest_editable_remark_id?: number | null;
      }>(`/admin/jnfs/${params.id}`);

      setJnf(refreshed.jnf);
      setRemarks(refreshed.jnf.admin_remarks ?? remark);
      setStatusHistory(refreshed.status_history ?? []);
      setReviewMarked(refreshed.review_marked === true);
      setReviewedByEmail(refreshed.reviewed_by_email ?? null);
      setCanEditLatestRemark(refreshed.can_edit_latest_remark === true);
      setLatestEditableRemarkId(refreshed.latest_editable_remark_id ?? null);
      setEditingRemarkEntryId(null);
      setEditedRemark("");
      setSuccess("Latest remark updated.");
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to edit latest remark.");
    } finally {
      setEditingLatestRemark(false);
    }
  };

  if (loading) {
    return (
      <Box sx={{ py: 4 }}>
        <LinearProgress />
        <Typography textAlign="center" mt={2}>Loading JNF details...</Typography>
      </Box>
    );
  }

  // Parse form_data
  const formData: FormData = jnf?.form_data
    ? typeof jnf.form_data === "string"
      ? JSON.parse(jnf.form_data)
      : jnf.form_data
    : {};

  const displayData = editMode ? editFormData : formData;
  const symbol = getCurrencySymbol(formData.currency);
  const selectedBranches = (displayData.eligibility ?? []).flatMap((p) =>
    p.branches.filter((b) => b.selected).map((b) => `${b.branch} (${p.programme})`)
  );
  const enabledSalaries = (formData.programmeSalaries ?? []).filter((s) => s.enabled);
  const editableSalaries = editMode
    ? (editFormData.programmeSalaries ?? formData.programmeSalaries ?? [])
    : (formData.programmeSalaries ?? []);
  const enabledRounds = (formData.selectionRounds ?? []).filter((r) => r.enabled);
  const companyProfile = formData.companyProfile;
  const isDraftFlow = (jnf?.status ?? "") === "draft" || reviewMarked;
  const draftNotes = statusHistory.filter((entry) => typeof entry.remarks === "string" && entry.remarks.startsWith("NOTE:"));
  const reviewNotes = statusHistory.filter((entry) => entry.remarks && !entry.remarks.startsWith("NOTE:"));

  const startEditing = () => {
    const data = JSON.parse(JSON.stringify(formData));
    if (data.jobDescription) {
      data.jobDescription = stripHtml(data.jobDescription);
    }
    if (data.companyProfile?.companyDescription) {
      data.companyProfile.companyDescription = stripHtml(data.companyProfile.companyDescription);
    }
    setEditFormData(data);
    setEditMode(true);
  };

  const cancelEditing = () => {
    setEditMode(false);
    setEditFormData({});
  };

  const updateEditField = (key: keyof FormData, value: unknown) => {
    setEditFormData((prev) => ({ ...prev, [key]: value }));
  };

  const toggleBranch = (progIdx: number, branchIdx: number) => {
    setEditFormData((prev) => {
      const elig = JSON.parse(JSON.stringify(prev.eligibility ?? []));
      if (elig[progIdx]?.branches?.[branchIdx]) {
        elig[progIdx].branches[branchIdx].selected = !elig[progIdx].branches[branchIdx].selected;
      }
      return { ...prev, eligibility: elig };
    });
  };

  const saveEdits = async () => {
    setSavingEdit(true);
    setError(null);
    setSuccess(null);
    try {
      await adminApi(`/admin/jnfs/${params.id}/form-data`, {
        method: "PATCH",
        body: JSON.stringify({ form_data: editFormData }),
      });
      setEditMode(false);
      setSuccess("Form data updated. Company has been notified.");
      // Refresh
      const refreshed = await adminApi<{
        jnf: Jnf;
        status_history: StatusHistoryEntry[];
        review_marked?: boolean;
        reviewed_by_email?: string | null;
        can_edit_latest_remark?: boolean;
        latest_editable_remark_id?: number | null;
      }>(`/admin/jnfs/${params.id}`);
      setJnf(refreshed.jnf);
      setStatusHistory(refreshed.status_history ?? []);
      setReviewMarked(refreshed.review_marked === true);
      setReviewedByEmail(refreshed.reviewed_by_email ?? null);
      setCanEditLatestRemark(refreshed.can_edit_latest_remark === true);
      setLatestEditableRemarkId(refreshed.latest_editable_remark_id ?? null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to save edits.");
    } finally {
      setSavingEdit(false);
    }
  };

  return (
    <Box>
      {/* Header */}
      <Paper
        sx={{
          p: 2,
          mb: 3,
          background: (theme) =>
            `linear-gradient(135deg, ${theme.palette.primary.main} 0%, ${theme.palette.primary.dark} 100%)`,
          color: "white",
          borderRadius: 2,
        }}
      >
        <Stack direction={{ xs: "column", md: "row" }} justifyContent="space-between" alignItems={{ md: "center" }} spacing={2}>
          <Stack direction="row" spacing={2} alignItems="center">
            <Avatar 
              src={jnf?.company?.logo_url || undefined}
              sx={{ width: 48, height: 48, bgcolor: "white", color: "primary.main" }}
            >
              {!jnf?.company?.logo_url && <WorkIcon />}
            </Avatar>
            <Box>
              <Typography variant="h5" fontWeight={700}>
                {formData.jobTitle || jnf?.job_title || "JNF Review"}
              </Typography>
              <Typography variant="body2" sx={{ opacity: 0.9 }}>
                {jnf?.company?.name ?? "Unknown Company"} • Submitted {jnf?.created_at ? new Date(jnf.created_at).toLocaleDateString() : "-"}
              </Typography>
            </Box>
          </Stack>
          <Stack direction="row" spacing={1} alignItems="center" flexWrap="nowrap">
            <Chip
              icon={getStatusIcon(jnf?.status ?? "") || undefined}
              label={(jnf?.status ?? "").replace("_", " ").toUpperCase()}
              color={getStatusColor(jnf?.status ?? "") as "success" | "warning" | "info" | "error" | "default"}
              sx={{ color: "white", fontWeight: 600 }}
            />
            {jnf?.status === "submitted" && Boolean(jnf?.edit_access_requested_at) && (
              <Chip
                label="EDIT ACCESS REQUIRED"
                color="error"
                variant="filled"
                sx={{ fontWeight: 700, color: "white", fontSize: '0.85rem' }}
              />
            )}
            <Button
              variant="outlined"
              startIcon={<ArrowBackIcon />}
              onClick={() => router.push("/admin/jnfs")}
              sx={{ color: "white", borderColor: "white" }}
            >
              Back
            </Button>
            {jnf?.status === "accepted" && (
              <Button
                variant="contained"
                color="success"
                startIcon={<DownloadIcon />}
                onClick={() => void adminDownload(`/admin/jnfs/${jnf.id}/csv`, `accepted-jnf-${jnf.id}.csv`)}
              >
                Download CSV
              </Button>
            )}
          </Stack>
        </Stack>
      </Paper>

      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
      {success && <Alert severity="success" sx={{ mb: 2 }}>{success}</Alert>}
      {reviewMarked && (
        <Alert severity="info" sx={{ mb: 2 }}>
          Draft marked for review.
          {reviewedByEmail ? ` Reviewed by: ${reviewedByEmail}` : ""}
        </Alert>
      )}
      {jnf?.status === "submitted" && jnf.edit_access_requested_at && (
        <Alert severity="error" sx={{ mb: 2 }}>
          Edit access required by company.
          {jnf.edit_access_requested_reason ? ` Reason: ${jnf.edit_access_requested_reason}` : ""}
        </Alert>
      )}

      <Grid2 container spacing={3} sx={{ alignItems: "flex-start" }}>
        {/* Main Content */}
        <Grid2
          size={{ xs: 12, md: 8 }}
          sx={{
            maxHeight: { md: "calc(100vh - 160px)" },
            overflowY: { md: "auto" },
            pr: { md: 1 },
          }}
        >
          {editMode ? (
            <>
              {/* Company Info */}
              <SectionCard title="Company Information" icon={<BusinessIcon color="primary" />}>
                <Grid2 container spacing={2}>
                  <DataRow label="Company Name" value={jnf?.company?.name} />
                  <DataRow label="HR Name" value={jnf?.company?.hr_name} />
                  <DataRow label="HR Email" value={jnf?.company?.hr_email} />
                  <DataRow label="Industry" value={jnf?.company?.industry} />
                  <DataRow label="Website" value={jnf?.company?.website} />
                </Grid2>
              </SectionCard>

              <SectionCard title="Submitted Company Profile" icon={<BusinessIcon color="primary" />}>
                <Grid2 container spacing={2}>
                  <DataRow label="Company Name" value={companyProfile?.name} />
                  <DataRow label="Sector" value={companyProfile?.sector} />
                  <DataRow label="Website" value={companyProfile?.website} />
                  <DataRow label="Number of Employees" value={companyProfile?.employeeCount} />
                  <DataRow label="Category / Organization Type" value={companyProfile?.categoryOrgType} />
                  <DataRow label="Date of Establishment" value={companyProfile?.dateOfEstablishment} />
                  <DataRow label="Annual Turnover" value={companyProfile?.annualTurnover} />
                  <DataRow label="LinkedIn URL" value={companyProfile?.linkedinUrl} />
                  <DataRow label="Industry Sector Tags" value={companyProfile?.industrySectorTags} />
                  <DataRow label="MNC HQ Country/City" value={companyProfile?.mncHqCountryCity} />
                  <DataRow label="Nature of Business" value={companyProfile?.natureOfBusiness} />
                  <Grid2 size={12}>
                    <Typography variant="caption" color="text.secondary" display="block">
                      Postal Address
                    </Typography>
                    <Typography variant="body2" mt={0.5}>
                      {companyProfile?.postalAddress || "-"}
                    </Typography>
                  </Grid2>
                  <Grid2 size={12}>
                    <Typography variant="caption" color="text.secondary" display="block">
                      Company Description
                    </Typography>
                    <Typography variant="body2" mt={0.5} style={{ whiteSpace: "pre-line" }}>
                      {stripHtml(companyProfile?.companyDescription || "-")}
                    </Typography>
                  </Grid2>
                </Grid2>
              </SectionCard>

              {/* Job Details */}
              <SectionCard title="Job Details" icon={<WorkIcon color="primary" />}>
                <Stack spacing={2}>
                  <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
                    <TextField fullWidth label="Job Title" size="small" value={editFormData.jobTitle ?? ""} onChange={(e) => updateEditField("jobTitle", e.target.value)} />
                    <TextField fullWidth label="Designation" size="small" value={editFormData.jobDesignation ?? ""} onChange={(e) => updateEditField("jobDesignation", e.target.value)} />
                  </Stack>
                  <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
                    <TextField fullWidth label="Location" size="small" value={editFormData.jobLocation ?? ""} onChange={(e) => updateEditField("jobLocation", e.target.value)} />
                    <TextField fullWidth label="Work Mode" size="small" value={editFormData.workMode ?? ""} onChange={(e) => updateEditField("workMode", e.target.value)} />
                  </Stack>
                  <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
                    <TextField fullWidth label="Expected Hires" size="small" value={editFormData.expectedHires ?? ""} onChange={(e) => updateEditField("expectedHires", e.target.value)} />
                    <TextField fullWidth label="Minimum Hires" size="small" value={editFormData.minimumHires ?? ""} onChange={(e) => updateEditField("minimumHires", e.target.value)} />
                  </Stack>
                  <TextField fullWidth label="Joining Month" size="small" value={editFormData.joiningMonth ?? ""} onChange={(e) => updateEditField("joiningMonth", e.target.value)} />
                  <TextField fullWidth label="Registration Link" size="small" value={editFormData.registrationLink ?? ""} onChange={(e) => updateEditField("registrationLink", e.target.value)} />
                  <TextField fullWidth label="Job Description" size="small" multiline minRows={3} value={editFormData.jobDescription ?? ""} onChange={(e) => updateEditField("jobDescription", e.target.value)} />
                  <TextField fullWidth label="Additional Info" size="small" multiline minRows={2} value={editFormData.additionalInfo ?? ""} onChange={(e) => updateEditField("additionalInfo", e.target.value)} />
                </Stack>
              </SectionCard>

              {/* Eligibility */}
              <SectionCard title="Eligibility Criteria" icon={<SchoolIcon color="primary" />}>
                <Stack spacing={2}>
                  <TextField fullWidth size="small" label="Cutoff CGPA" value={editFormData.globalCgpa ?? ""} onChange={(e) => updateEditField("globalCgpa", e.target.value)} />
                  <FormControlLabel
                    control={
                      <Checkbox
                        checked={editFormData.globalBacklogs ?? false}
                        onChange={(e) => updateEditField("globalBacklogs", e.target.checked)}
                      />
                    }
                    label="Backlogs Allowed"
                  />
                  <TextField fullWidth size="small" label="Gender Preference" value={editFormData.genderFilter ?? ""} onChange={(e) => updateEditField("genderFilter", e.target.value)} />
                  <TextField fullWidth size="small" label="SLP Requirement" value={editFormData.slpRequirement ?? ""} onChange={(e) => updateEditField("slpRequirement", e.target.value)} />
                  <Typography variant="subtitle2" mt={1}>Eligible Branches</Typography>
                  {(editFormData.eligibility ?? formData.eligibility ?? []).map((prog, pIdx) => (
                    <Box key={prog.programme} sx={{ mb: 2, pl: 1, borderLeft: "2px solid", borderColor: "primary.main" }}>
                      <Typography variant="body2" fontWeight={600} color="primary" mb={1}>{prog.programme}</Typography>
                      <Stack direction="row" flexWrap="wrap" gap={1}>
                        {prog.branches.map((branch: any, bIdx: number) => (
                          <FormControlLabel
                            key={branch.branch}
                            control={
                              <Checkbox
                                size="small"
                                checked={branch.selected}
                                onChange={() => toggleBranch(pIdx, bIdx)}
                              />
                            }
                            label={<Typography variant="body2">{branch.branch}</Typography>}
                            sx={{ minWidth: 180 }}
                          />
                        ))}
                      </Stack>
                    </Box>
                  ))}
                </Stack>
              </SectionCard>

              {/* Compensation */}
              <SectionCard title="Compensation Details" icon={<PaidIcon color="primary" />}>
                <Stack spacing={2}>
                  <TextField
                    fullWidth
                    size="small"
                    label="Currency"
                    value={editFormData.currency ?? "INR"}
                    onChange={(e) => updateEditField("currency", e.target.value)}
                  />
                  <Typography variant="subtitle2">Programme-wise Salary</Typography>
                  {(editFormData.programmeSalaries ?? formData.programmeSalaries ?? []).map((s, idx) => (
                    <Box key={s.programme}>
                      <Stack direction="row" alignItems="center" spacing={1} mb={1}>
                        <FormControlLabel
                          control={
                            <Checkbox
                              size="small"
                              checked={s.enabled}
                              onChange={(e) => {
                                const updated = JSON.parse(JSON.stringify(editFormData.programmeSalaries ?? formData.programmeSalaries ?? []));
                                updated[idx].enabled = e.target.checked;
                                updateEditField("programmeSalaries", updated);
                              }}
                            />
                          }
                          label={<Typography variant="body2" fontWeight={600}>{s.programme}</Typography>}
                        />
                      </Stack>
                      {s.enabled && (
                        <Stack direction={{ xs: "column", md: "row" }} spacing={1} pl={2}>
                          <TextField
                            fullWidth size="small" label="CTC Annual"
                            value={s.ctcAnnual ?? ""}
                            onChange={(e) => {
                              const updated = JSON.parse(JSON.stringify(editFormData.programmeSalaries ?? formData.programmeSalaries ?? []));
                              updated[idx].ctcAnnual = e.target.value;
                              updateEditField("programmeSalaries", updated);
                            }}
                          />
                          <TextField
                            fullWidth size="small" label="Base Salary"
                            value={s.baseSalary ?? ""}
                            onChange={(e) => {
                              const updated = JSON.parse(JSON.stringify(editFormData.programmeSalaries ?? formData.programmeSalaries ?? []));
                              updated[idx].baseSalary = e.target.value;
                              updateEditField("programmeSalaries", updated);
                            }}
                          />
                          <TextField
                            fullWidth size="small" label="Take Home"
                            value={s.takeHome ?? ""}
                            onChange={(e) => {
                              const updated = JSON.parse(JSON.stringify(editFormData.programmeSalaries ?? formData.programmeSalaries ?? []));
                              updated[idx].takeHome = e.target.value;
                              updateEditField("programmeSalaries", updated);
                            }}
                          />
                        </Stack>
                      )}
                    </Box>
                  ))}
                  <Typography variant="subtitle2" mt={1}>Additional Compensation Components</Typography>
                  <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
                    <TextField fullWidth size="small" label="Joining Bonus"
                      value={editFormData.salaryComponents?.joiningBonus ?? ""}
                      onChange={(e) => updateEditField("salaryComponents", { ...(editFormData.salaryComponents ?? {}), joiningBonus: e.target.value })}
                    />
                    <TextField fullWidth size="small" label="Retention Bonus"
                      value={editFormData.salaryComponents?.retentionBonus ?? ""}
                      onChange={(e) => updateEditField("salaryComponents", { ...(editFormData.salaryComponents ?? {}), retentionBonus: e.target.value })}
                    />
                    <TextField fullWidth size="small" label="Performance/Variable Bonus"
                      value={editFormData.salaryComponents?.performanceBonus ?? ""}
                      onChange={(e) => updateEditField("salaryComponents", { ...(editFormData.salaryComponents ?? {}), performanceBonus: e.target.value })}
                    />
                  </Stack>
                  <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
                    <TextField fullWidth size="small" label="ESOPs / Stock Options"
                      value={editFormData.salaryComponents?.esops ?? ""}
                      onChange={(e) => updateEditField("salaryComponents", { ...(editFormData.salaryComponents ?? {}), esops: e.target.value })}
                    />
                    <TextField fullWidth size="small" label="Vesting Period"
                      value={editFormData.salaryComponents?.vestPeriod ?? ""}
                      onChange={(e) => updateEditField("salaryComponents", { ...(editFormData.salaryComponents ?? {}), vestPeriod: e.target.value })}
                    />
                    <TextField fullWidth size="small" label="Stocks/RSUs"
                      value={editFormData.salaryComponents?.stocks ?? ""}
                      onChange={(e) => updateEditField("salaryComponents", { ...(editFormData.salaryComponents ?? {}), stocks: e.target.value })}
                    />
                  </Stack>
                  <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
                    <TextField fullWidth size="small" label="Relocation Allowance"
                      value={editFormData.salaryComponents?.relocationAllowance ?? ""}
                      onChange={(e) => updateEditField("salaryComponents", { ...(editFormData.salaryComponents ?? {}), relocationAllowance: e.target.value })}
                    />
                    <TextField fullWidth size="small" label="Relocation Bonus (Legacy)"
                      value={editFormData.salaryComponents?.relocationBonus ?? ""}
                      onChange={(e) => updateEditField("salaryComponents", { ...(editFormData.salaryComponents ?? {}), relocationBonus: e.target.value })}
                    />
                    <TextField fullWidth size="small" label="Medical Allowance / Insurance"
                      value={editFormData.salaryComponents?.medicalAllowance ?? ""}
                      onChange={(e) => updateEditField("salaryComponents", { ...(editFormData.salaryComponents ?? {}), medicalAllowance: e.target.value })}
                    />
                  </Stack>
                  <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
                    <TextField fullWidth size="small" label="Deductions (PF, Tax, etc.)"
                      value={editFormData.salaryComponents?.deductions ?? ""}
                      onChange={(e) => updateEditField("salaryComponents", { ...(editFormData.salaryComponents ?? {}), deductions: e.target.value })}
                    />
                    <TextField fullWidth size="small" label="Bond Amount (if any)"
                      value={editFormData.salaryComponents?.bondAmount ?? ""}
                      onChange={(e) => updateEditField("salaryComponents", { ...(editFormData.salaryComponents ?? {}), bondAmount: e.target.value })}
                    />
                    <TextField fullWidth size="small" label="Bond Duration"
                      value={editFormData.salaryComponents?.bondDuration ?? ""}
                      onChange={(e) => updateEditField("salaryComponents", { ...(editFormData.salaryComponents ?? {}), bondDuration: e.target.value })}
                    />
                    <TextField fullWidth size="small" label="Bond (Years) (Legacy)"
                      value={editFormData.salaryComponents?.bondYears ?? ""}
                      onChange={(e) => updateEditField("salaryComponents", { ...(editFormData.salaryComponents ?? {}), bondYears: e.target.value })}
                    />
                  </Stack>
                  <TextField fullWidth size="small" label="Detailed CTC Breakup (Optional)" multiline minRows={2}
                    value={editFormData.salaryComponents?.ctcBreakup ?? ""}
                    onChange={(e) => updateEditField("salaryComponents", { ...(editFormData.salaryComponents ?? {}), ctcBreakup: e.target.value })}
                  />
                </Stack>
              </SectionCard>

              <SectionCard title="Selection Process" icon={<AssignmentIcon color="primary" />}>
                <SelectionProcessBuilder
                  value={editFormData.selectionRounds ?? []}
                  onChange={(rounds) => updateEditField("selectionRounds", rounds)}
                />
              </SectionCard>
            </>
          ) : (
            <>
              {/* System/HR Info (Admin only) */}
              <SectionCard title="Company Information" icon={<BusinessIcon color="primary" />}>
                <Grid2 container spacing={2}>
                  <DataRow label="Company Name" value={jnf?.company?.name} />
                  <DataRow label="HR Name" value={jnf?.company?.hr_name} />
                  <DataRow label="HR Email" value={jnf?.company?.hr_email} />
                  <DataRow label="Industry" value={jnf?.company?.industry} />
                  <DataRow label="Website" value={jnf?.company?.website} />
                </Grid2>
              </SectionCard>

              {/* Standardized JnfPreview component */}
              <JnfPreview
                readOnly={true}
                companyLogoUrl={jnf?.company?.logo_url || formData.companyProfile?.logoUrl || null}
                companyProfile={{
                  name: formData.companyProfile?.name || jnf?.company?.name || "",
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
                jobDetails={{
                  title: formData.jobTitle || jnf?.job_title || "",
                  designation: formData.jobDesignation || "",
                  location: formData.jobLocation || jnf?.job_location || "",
                  workMode: formData.workMode || "onsite",
                  expectedHires: formData.expectedHires || jnf?.vacancies?.toString() || "",
                  minimumHires: formData.minimumHires || "",
                  joiningMonth: formData.joiningMonth || "",
                  skills: formData.skills || [],
                  description: formData.jobDescription || jnf?.job_description || "",
                  registrationLink: formData.registrationLink || "",
                  additionalInfo: formData.additionalInfo || "",
                }}
                eligibility={formData.eligibility || []}
                globalCgpa={formData.globalCgpa || "7.0"}
                globalBacklogs={formData.globalBacklogs ?? false}
                genderFilter={formData.genderFilter || "all"}
                slpRequirement={formData.slpRequirement || ""}
                graduatingBatch={formData.graduatingBatch || jnf?.graduating_batch || ""}
                salary={{
                  currency: (formData.currency as any) || "INR",
                  programmeSalaries: formData.programmeSalaries || [],
                  components: {
                    joiningBonus: formData.salaryComponents?.joiningBonus || "",
                    retentionBonus: formData.salaryComponents?.retentionBonus || "",
                    performanceBonus: formData.salaryComponents?.performanceBonus || "",
                    esops: formData.salaryComponents?.esops || "",
                    vestPeriod: formData.salaryComponents?.vestPeriod || "",
                    relocationAllowance: formData.salaryComponents?.relocationAllowance || "",
                    medicalAllowance: formData.salaryComponents?.medicalAllowance || "",
                    deductions: formData.salaryComponents?.deductions || "",
                    bondAmount: formData.salaryComponents?.bondAmount || "",
                    bondDuration: formData.salaryComponents?.bondDuration || "",
                    stocks: formData.salaryComponents?.stocks || "",
                    ctcBreakup: formData.salaryComponents?.ctcBreakup || "",
                  },
                }}
                selectionProcess={{
                  rounds: formData.selectionRounds || [],
                }}
                declarations={(formData.declarations as any) || {
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
            </>
          )}

        </Grid2>

        {/* Sidebar - Admin Actions */}
        <Grid2
          size={{ xs: 12, md: 4 }}
          sx={{
            maxHeight: { md: "calc(100vh - 160px)" },
            overflowY: { md: "auto" },
            pl: { md: 1 },
          }}
        >
          <Card sx={{ my: 2 }}>
            <Box sx={{ px: 2, py: 1.5, bgcolor: "primary.main", color: "white" }}>
              <Typography variant="subtitle1" fontWeight={600}>Admin Actions</Typography>
            </Box>
            <CardContent>
              <Stack spacing={2}>
                <Box>
                  <Typography variant="caption" color="text.secondary">Current Status</Typography>
                  <Chip
                    icon={getStatusIcon(jnf?.status ?? "") || undefined}
                    label={(jnf?.status ?? "").replace("_", " ").toUpperCase()}
                    color={getStatusColor(jnf?.status ?? "") as "success" | "warning" | "info" | "error" | "default"}
                    sx={{ mt: 0.5, width: "100%" }}
                  />
                </Box>

                {/* Edit button — always visible for admin */}
                <>
                  <Divider />
                  {editMode ? (
                    <Stack spacing={1}>
                      <Button variant="contained" onClick={() => void saveEdits()} disabled={savingEdit} fullWidth startIcon={<CheckCircleIcon />}>
                        {savingEdit ? "Saving..." : "Save Changes"}
                      </Button>
                      <Button variant="outlined" onClick={cancelEditing} disabled={savingEdit} fullWidth>
                        Cancel Editing
                      </Button>
                    </Stack>
                  ) : (
                    <Button variant="outlined" onClick={startEditing} fullWidth startIcon={<EditIcon />}>
                      Edit Details
                    </Button>
                  )}
                </>

                <Divider />

                {!isDraftFlow && (
                  <TextField
                    label="Admin Remarks"
                    multiline
                    minRows={4}
                    value={remarks}
                    onChange={(e) => setRemarks(e.target.value)}
                    placeholder="Add notes or feedback for the company..."
                    fullWidth
                  />
                )}

                {!isDraftFlow && (
                  <Button
                    variant="outlined"
                    onClick={() => void updateStatus("under_review")}
                    disabled={updating || reviewMarked || jnf?.status === "under_review"}
                    fullWidth
                  >
                    Grant Edit Access
                  </Button>
                )}

                {isDraftFlow && !reviewMarked && (
                  <Button
                    variant="outlined"
                    color="primary"
                    onClick={() => void updateStatus("under_review")}
                    disabled={updating}
                    fullWidth
                  >
                    Mark for Review
                  </Button>
                )}

                {isDraftFlow && reviewMarked && (
                  <Button
                    variant="outlined"
                    color="warning"
                    onClick={() => void updateStatus("draft")}
                    disabled={updating}
                    fullWidth
                  >
                    Remove from Marked for Review
                  </Button>
                )}

                {!reviewMarked && jnf?.status !== "draft" && (
                  <>
                    <Button
                      variant="contained"
                      color="success"
                      onClick={() => void updateStatus("accepted")}
                      disabled={updating || jnf?.status === "accepted"}
                      fullWidth
                      startIcon={<CheckCircleIcon />}
                    >
                      Accept JNF
                    </Button>

                    <Button
                      variant="contained"
                      color="error"
                      onClick={() => void updateStatus("rejected")}
                      disabled={updating || jnf?.status === "rejected"}
                      fullWidth
                      startIcon={<CancelIcon />}
                    >
                      Reject JNF
                    </Button>
                  </>
                )}

                {updating && <LinearProgress />}

                {isDraftFlow && (
                  <>
                    <Divider />

                    <Box>
                      <Typography variant="caption" color="text.secondary">Draft Notes</Typography>
                      <Stack spacing={1} mt={1}>
                        <TextField
                          label="+ Add Note"
                          multiline
                          minRows={3}
                          value={draftNote}
                          onChange={(e) => setDraftNote(e.target.value)}
                          placeholder="Write an internal draft note..."
                          fullWidth
                        />
                        <Button
                          variant="outlined"
                          onClick={() => void addDraftNote()}
                          disabled={addingNote || !draftNote.trim()}
                          fullWidth
                        >
                          {addingNote ? "Adding Note..." : "+ Add Note"}
                        </Button>
                      </Stack>

                      <Stack spacing={1} mt={2}>
                        {draftNotes.map((entry) => (
                          <Paper key={entry.id} variant="outlined" sx={{ p: 1 }}>
                            <Typography variant="caption" color="text.secondary" display="block">
                              {getHistoryDisplayEmail(entry)} • {new Date(entry.created_at).toLocaleString()}
                            </Typography>
                            <Typography variant="body2">{(entry.remarks || "").replace(/^NOTE:\s*/, "")}</Typography>
                          </Paper>
                        ))}
                        {draftNotes.length === 0 && (
                          <Typography variant="body2" color="text.secondary">No notes added yet.</Typography>
                        )}
                      </Stack>
                    </Box>
                  </>
                )}

                {!isDraftFlow && (
                  <>
                    <Divider />

                    <Box>
                      <Typography variant="caption" color="text.secondary">Admin Review Notes</Typography>
                      <Box sx={{ maxHeight: 320, overflowY: "auto", pr: 0.5, mt: 1 }}>
                        <Stack spacing={1}>
                          {reviewNotes.map((entry, index) => (
                            <Paper key={entry.id} variant="outlined" sx={{ p: 1 }}>
                              <Stack direction="row" justifyContent="space-between" alignItems="center" spacing={1}>
                                <Typography variant="caption" color="text.secondary" display="block">
                                  {getHistoryDisplayEmail(entry)} • {new Date(entry.created_at).toLocaleString()}
                                </Typography>
                                {index === 0 && canEditLatestRemark && latestEditableRemarkId === entry.id && (
                                  <Button
                                    size="small"
                                    variant="text"
                                    onClick={() => {
                                      setEditingRemarkEntryId(entry.id);
                                      setEditedRemark(entry.remarks ?? "");
                                    }}
                                    disabled={updating || editingLatestRemark}
                                  >
                                    Edit
                                  </Button>
                                )}
                              </Stack>
                              {editingRemarkEntryId === entry.id ? (
                                <Stack spacing={1} mt={1}>
                                  <TextField
                                    multiline
                                    minRows={3}
                                    value={editedRemark}
                                    onChange={(e) => setEditedRemark(e.target.value)}
                                    placeholder="Edit remark..."
                                    fullWidth
                                  />
                                  <Stack direction="row" spacing={1} justifyContent="flex-end">
                                    <Button
                                      size="small"
                                      variant="text"
                                      onClick={() => {
                                        setEditingRemarkEntryId(null);
                                        setEditedRemark("");
                                      }}
                                      disabled={editingLatestRemark}
                                    >
                                      Cancel
                                    </Button>
                                    <Button
                                      size="small"
                                      variant="contained"
                                      onClick={() => void editLatestRemark()}
                                      disabled={editingLatestRemark || !editedRemark.trim()}
                                    >
                                      {editingLatestRemark ? "Saving..." : "Save"}
                                    </Button>
                                  </Stack>
                                </Stack>
                              ) : (
                                <Typography variant="body2">{entry.remarks}</Typography>
                              )}
                            </Paper>
                          ))}
                          {reviewNotes.length === 0 && (
                            <Typography variant="body2" color="text.secondary">No admin remarks yet.</Typography>
                          )}
                        </Stack>
                      </Box>
                    </Box>
                  </>
                )}
              </Stack>
            </CardContent>
          </Card>
        </Grid2>
      </Grid2>
    </Box>
  );
}
