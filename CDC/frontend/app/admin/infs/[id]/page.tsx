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
import SchoolIcon from "@mui/icons-material/School";
import PaidIcon from "@mui/icons-material/Paid";
import AssignmentIcon from "@mui/icons-material/Assignment";
import CheckCircleIcon from "@mui/icons-material/CheckCircle";
import PendingIcon from "@mui/icons-material/Pending";
import CancelIcon from "@mui/icons-material/Cancel";
import HourglassEmptyIcon from "@mui/icons-material/HourglassEmpty";
import WorkIcon from "@mui/icons-material/Work";
import DownloadIcon from "@mui/icons-material/Download";
import EditIcon from "@mui/icons-material/Edit";
import { adminApi, adminDownload } from "@/lib/adminapi";

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
  eligibility?: Array<{
    programme: string;
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

export default function AdminInfDetailPage() {
  const params = useParams<{ id: string }>();
  const router = useRouter();
  const [inf, setInf] = useState<Inf | null>(null);
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
          inf: Inf;
          status_history: StatusHistoryEntry[];
          review_marked?: boolean;
          reviewed_by_email?: string | null;
          can_edit_latest_remark?: boolean;
          latest_editable_remark_id?: number | null;
        }>(`/admin/infs/${params.id}`);
        setInf(response.inf);
        setRemarks("");
        setStatusHistory(response.status_history ?? []);
        setReviewMarked(response.review_marked === true);
        setReviewedByEmail(response.reviewed_by_email ?? null);
        setCanEditLatestRemark(response.can_edit_latest_remark === true);
        setLatestEditableRemarkId(response.latest_editable_remark_id ?? null);
      } catch (e) {
        setError(e instanceof Error ? e.message : "Failed to load INF.");
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
      await adminApi<{ message: string }>(`/admin/infs/${params.id}/notes`, {
        method: "POST",
        body: JSON.stringify({ note }),
      });
      setDraftNote("");
      setSuccess("Draft note added.");

      const refreshed = await adminApi<{
        inf: Inf;
        status_history: StatusHistoryEntry[];
        review_marked?: boolean;
        reviewed_by_email?: string | null;
        can_edit_latest_remark?: boolean;
        latest_editable_remark_id?: number | null;
      }>(`/admin/infs/${params.id}`);

      setStatusHistory(refreshed.status_history ?? []);
      setReviewMarked(refreshed.review_marked === true);
      setReviewedByEmail(refreshed.reviewed_by_email ?? null);
      setInf(refreshed.inf);
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

    const isDraftReviewAction = status === "under_review" && (inf?.status === "draft" || reviewMarked);

    if (['under_review', 'rejected'].includes(status) && !isDraftReviewAction && !remarks.trim()) {
      setError('Admin remarks are required for review or rejection.');
      return;
    }

    const confirmationMessageByStatus: Partial<Record<"draft" | "under_review" | "accepted" | "rejected", string>> = {
      under_review: isDraftReviewAction ? "Are you sure you want to mark this draft for review?" : "Are you sure you want to grant edit access for this INF?",
      accepted: "Are you sure you want to accept this INF?",
      rejected: "Are you sure you want to reject this INF?",
      draft: "Are you sure you want to remove this draft from being marked for review?",
    };

    const confirmationMessage = confirmationMessageByStatus[status];
    if (confirmationMessage && !window.confirm(confirmationMessage)) {
      return;
    }

    setUpdating(true);

    try {
      const response = await adminApi<{ inf: Inf }>(`/admin/infs/${params.id}/status`, {
        method: "PATCH",
        body: JSON.stringify({ status, admin_remarks: isDraftReviewAction ? null : (remarks || null) }),
      });
      setInf(response.inf);
      setSuccess(`INF marked as ${status.replace("_", " ")}.`);
      const refreshed = await adminApi<{
        inf: Inf;
        status_history: StatusHistoryEntry[];
        review_marked?: boolean;
        reviewed_by_email?: string | null;
        can_edit_latest_remark?: boolean;
        latest_editable_remark_id?: number | null;
      }>(`/admin/infs/${params.id}`);
      setStatusHistory(refreshed.status_history ?? []);
      setReviewMarked(refreshed.review_marked === true);
      setReviewedByEmail(refreshed.reviewed_by_email ?? null);
      setInf(refreshed.inf);
      setRemarks("");
      setCanEditLatestRemark(refreshed.can_edit_latest_remark === true);
      setLatestEditableRemarkId(refreshed.latest_editable_remark_id ?? null);
      setEditingRemarkEntryId(null);
      setEditedRemark("");
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to update INF status.");
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
      await adminApi<{ message: string; remark: string; inf: Inf }>(`/admin/infs/${params.id}/remarks/latest`, {
        method: "PATCH",
        body: JSON.stringify({ remark }),
      });

      const refreshed = await adminApi<{
        inf: Inf;
        status_history: StatusHistoryEntry[];
        review_marked?: boolean;
        reviewed_by_email?: string | null;
        can_edit_latest_remark?: boolean;
        latest_editable_remark_id?: number | null;
      }>(`/admin/infs/${params.id}`);

      setInf(refreshed.inf);
      setRemarks(refreshed.inf.admin_remarks ?? remark);
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

  const displayData = editMode ? editFormData : formData;
  const symbol = getCurrencySymbol(formData.currency);
  const selectedBranches = (displayData.eligibility ?? []).flatMap((p) =>
    p.branches.filter((b) => b.selected).map((b) => `${b.branch} (${p.programme})`)
  );
  const enabledStipends = (formData.programmeStipends ?? []).filter((s) => s.enabled);
  const enabledRounds = (formData.selectionRounds ?? []).filter((r) => r.enabled);
  const companyProfile = formData.companyProfile;
  const isDraftFlow = (inf?.status ?? "") === "draft" || reviewMarked;
  const draftNotes = statusHistory.filter((entry) => typeof entry.remarks === "string" && entry.remarks.startsWith("NOTE:"));
  const reviewNotes = statusHistory.filter((entry) => entry.remarks && !entry.remarks.startsWith("NOTE:"));

  const startEditing = () => {
    setEditFormData(JSON.parse(JSON.stringify(formData)));
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
      await adminApi(`/admin/infs/${params.id}/form-data`, {
        method: "PATCH",
        body: JSON.stringify({ form_data: editFormData }),
      });
      setEditMode(false);
      setSuccess("Form data updated. Company has been notified.");
      const refreshed = await adminApi<{
        inf: Inf;
        status_history: StatusHistoryEntry[];
        review_marked?: boolean;
        reviewed_by_email?: string | null;
        can_edit_latest_remark?: boolean;
        latest_editable_remark_id?: number | null;
      }>(`/admin/infs/${params.id}`);
      setInf(refreshed.inf);
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
            `linear-gradient(135deg, ${theme.palette.secondary.main} 0%, ${theme.palette.secondary.dark} 100%)`,
          color: "white",
          borderRadius: 2,
        }}
      >
        <Stack direction={{ xs: "column", md: "row" }} justifyContent="space-between" alignItems={{ md: "center" }} spacing={2}>
          <Stack direction="row" spacing={2} alignItems="center">
            <Avatar 
              src={inf?.company?.logo_url || undefined}
              sx={{ width: 48, height: 48, bgcolor: "white", color: "secondary.main" }}
            >
              {!inf?.company?.logo_url && <SchoolIcon />}
            </Avatar>
            <Box>
              <Typography variant="h5" fontWeight={700}>
                {formData.internshipTitle || inf?.internship_title || "INF Review"}
              </Typography>
              <Typography variant="body2" sx={{ opacity: 0.9 }}>
                {inf?.company?.name ?? "Unknown Company"} • Submitted {inf?.created_at ? new Date(inf.created_at).toLocaleDateString() : "-"}
              </Typography>
            </Box>
          </Stack>
          <Stack direction="row" spacing={1} alignItems="center" flexWrap="nowrap">
            <Chip
              icon={getStatusIcon(inf?.status ?? "") || undefined}
              label={(inf?.status ?? "").replace("_", " ").toUpperCase()}
              color={getStatusColor(inf?.status ?? "") as "success" | "warning" | "info" | "error" | "default"}
              sx={{ color: "white", fontWeight: 600 }}
            />
            {inf?.status === "submitted" && Boolean(inf?.edit_access_requested_at) && (
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
              onClick={() => router.push("/admin/infs")}
              sx={{ color: "white", borderColor: "white" }}
            >
              Back
            </Button>
            {inf?.status === "accepted" && (
              <Button
                variant="contained"
                color="success"
                startIcon={<DownloadIcon />}
                onClick={() => void adminDownload(`/admin/infs/${inf.id}/csv`, `accepted-inf-${inf.id}.csv`)}
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
      {inf?.status === "submitted" && inf.edit_access_requested_at && (
        <Alert severity="error" sx={{ mb: 2 }}>
          Edit access required by company.
          {inf.edit_access_requested_reason ? ` Reason: ${inf.edit_access_requested_reason}` : ""}
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
          {/* Company Info */}
          <SectionCard title="Company Information" icon={<BusinessIcon color="secondary" />}>
            <Grid2 container spacing={2}>
              <DataRow label="Company Name" value={inf?.company?.name} />
              <DataRow label="HR Name" value={inf?.company?.hr_name} />
              <DataRow label="HR Email" value={inf?.company?.hr_email} />
              <DataRow label="Industry" value={inf?.company?.industry} />
              <DataRow label="Website" value={inf?.company?.website} />
            </Grid2>
          </SectionCard>

          <SectionCard title="Submitted Company Profile" icon={<BusinessIcon color="secondary" />}>
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
                <Typography variant="body2" mt={0.5} component="div" dangerouslySetInnerHTML={{ __html: companyProfile?.companyDescription || "-" }} />
              </Grid2>
            </Grid2>
          </SectionCard>

          {/* Internship Details */}
          <SectionCard title="Internship Details" icon={<SchoolIcon color="secondary" />}>
            {editMode ? (
              <Stack spacing={2}>
                <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
                  <TextField fullWidth label="Internship Title" size="small" value={editFormData.internshipTitle ?? ""} onChange={(e) => updateEditField("internshipTitle", e.target.value)} />
                  <TextField fullWidth label="Designation" size="small" value={editFormData.internshipDesignation ?? ""} onChange={(e) => updateEditField("internshipDesignation", e.target.value)} />
                </Stack>
                <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
                  <TextField fullWidth label="Location" size="small" value={editFormData.internshipLocation ?? ""} onChange={(e) => updateEditField("internshipLocation", e.target.value)} />
                  <TextField fullWidth label="Work Mode" size="small" value={editFormData.workMode ?? ""} onChange={(e) => updateEditField("workMode", e.target.value)} />
                </Stack>
                <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
                  <TextField fullWidth label="Duration (weeks)" size="small" value={editFormData.duration ?? ""} onChange={(e) => updateEditField("duration", e.target.value)} />
                  <TextField fullWidth label="Expected Hires" size="small" value={editFormData.expectedHires ?? ""} onChange={(e) => updateEditField("expectedHires", e.target.value)} />
                </Stack>
                <TextField fullWidth label="Joining Month" size="small" value={editFormData.joiningMonth ?? ""} onChange={(e) => updateEditField("joiningMonth", e.target.value)} />
                <TextField fullWidth label="Registration Link" size="small" value={editFormData.registrationLink ?? ""} onChange={(e) => updateEditField("registrationLink", e.target.value)} />
                <TextField fullWidth label="Internship Description" size="small" multiline minRows={3} value={editFormData.internshipDescription ?? ""} onChange={(e) => updateEditField("internshipDescription", e.target.value)} />
                <TextField fullWidth label="Additional Info" size="small" multiline minRows={2} value={editFormData.additionalInfo ?? ""} onChange={(e) => updateEditField("additionalInfo", e.target.value)} />
              </Stack>
            ) : (
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
            )}
          </SectionCard>

          {/* Eligibility */}
          <SectionCard title="Eligibility Criteria" icon={<WorkIcon color="secondary" />}>
            {editMode ? (
              <Stack spacing={2}>
                <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
                  <TextField fullWidth label="Minimum CGPA" size="small" value={editFormData.globalCgpa ?? ""} onChange={(e) => updateEditField("globalCgpa", e.target.value)} />
                  <TextField fullWidth label="Gender Filter" size="small" value={editFormData.genderFilter ?? "all"} onChange={(e) => updateEditField("genderFilter", e.target.value)} />
                </Stack>
                <FormControlLabel control={<Checkbox checked={editFormData.globalBacklogs ?? false} onChange={(e) => updateEditField("globalBacklogs", e.target.checked)} />} label="Backlogs Allowed" />
                <Typography variant="subtitle2" mt={1}>Eligible Branches ({selectedBranches.length} selected)</Typography>
                {(editFormData.eligibility ?? []).map((prog, progIdx) => (
                  <Box key={prog.programme}>
                    <Typography variant="body2" fontWeight={600} mt={1}>{prog.programme}</Typography>
                    <Stack direction="row" flexWrap="wrap" gap={0}>
                      {prog.branches.map((branch, branchIdx) => (
                        <FormControlLabel
                          key={branch.branch}
                          control={<Checkbox size="small" checked={branch.selected} onChange={() => toggleBranch(progIdx, branchIdx)} />}
                          label={<Typography variant="body2">{branch.branch}</Typography>}
                          sx={{ minWidth: 180 }}
                        />
                      ))}
                    </Stack>
                  </Box>
                ))}
              </Stack>
            ) : (
              <Grid2 container spacing={2}>
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
            )}
          </SectionCard>

          {/* Stipend */}
          <SectionCard title="Stipend Details" icon={<PaidIcon color="secondary" />}>
            {editMode ? (
              <Stack spacing={2}>
                <TextField
                  fullWidth
                  size="small"
                  label="Currency"
                  value={editFormData.currency ?? "INR"}
                  onChange={(e) => updateEditField("currency", e.target.value)}
                />
                <FormControlLabel
                  control={
                    <Checkbox
                      checked={editFormData.ppoProvision ?? false}
                      onChange={(e) => updateEditField("ppoProvision", e.target.checked)}
                    />
                  }
                  label="PPO Provision"
                />
                {editFormData.ppoProvision && (
                  <TextField
                    fullWidth
                    size="small"
                    label="PPO CTC"
                    value={editFormData.ppoCtc ?? ""}
                    onChange={(e) => updateEditField("ppoCtc", e.target.value)}
                  />
                )}
                <Typography variant="subtitle2">Programme-wise Stipend</Typography>
                {(editFormData.programmeStipends ?? formData.programmeStipends ?? []).map((s, idx) => (
                  <Box key={s.programme}>
                    <Stack direction="row" alignItems="center" spacing={1} mb={1}>
                      <FormControlLabel
                        control={
                          <Checkbox
                            size="small"
                            checked={s.enabled}
                            onChange={(e) => {
                              const updated = JSON.parse(JSON.stringify(editFormData.programmeStipends ?? formData.programmeStipends ?? []));
                              updated[idx].enabled = e.target.checked;
                              updateEditField("programmeStipends", updated);
                            }}
                          />
                        }
                        label={<Typography variant="body2" fontWeight={600}>{s.programme}</Typography>}
                      />
                    </Stack>
                    {s.enabled && (
                      <Stack direction={{ xs: "column", md: "row" }} spacing={1} pl={2}>
                        <TextField
                          fullWidth size="small" label="Base Stipend /month"
                          value={s.baseStipend ?? ""}
                          onChange={(e) => {
                            const updated = JSON.parse(JSON.stringify(editFormData.programmeStipends ?? formData.programmeStipends ?? []));
                            updated[idx].baseStipend = e.target.value;
                            updated[idx].total = String((parseInt(e.target.value) || 0) + (parseInt(updated[idx].hra) || 0) + (parseInt(updated[idx].otherAllowances) || 0));
                            updateEditField("programmeStipends", updated);
                          }}
                        />
                        <TextField
                          fullWidth size="small" label="HRA"
                          value={s.hra ?? ""}
                          onChange={(e) => {
                            const updated = JSON.parse(JSON.stringify(editFormData.programmeStipends ?? formData.programmeStipends ?? []));
                            updated[idx].hra = e.target.value;
                            updated[idx].total = String((parseInt(updated[idx].baseStipend) || 0) + (parseInt(e.target.value) || 0) + (parseInt(updated[idx].otherAllowances) || 0));
                            updateEditField("programmeStipends", updated);
                          }}
                        />
                        <TextField
                          fullWidth size="small" label="Other Allowances"
                          value={s.otherAllowances ?? ""}
                          onChange={(e) => {
                            const updated = JSON.parse(JSON.stringify(editFormData.programmeStipends ?? formData.programmeStipends ?? []));
                            updated[idx].otherAllowances = e.target.value;
                            updated[idx].total = String((parseInt(updated[idx].baseStipend) || 0) + (parseInt(updated[idx].hra) || 0) + (parseInt(e.target.value) || 0));
                            updateEditField("programmeStipends", updated);
                          }}
                        />
                      </Stack>
                    )}
                  </Box>
                ))}
              </Stack>
            ) : (
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
            )}
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
            <SectionCard title="Declaration & Signatory" icon={<CheckCircleIcon color="secondary" />}>
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
            <Box sx={{ px: 2, py: 1.5, bgcolor: "secondary.main", color: "white" }}>
              <Typography variant="subtitle1" fontWeight={600}>Admin Actions</Typography>
            </Box>
            <CardContent>
              <Stack spacing={2}>
                <Box>
                  <Typography variant="caption" color="text.secondary">Current Status</Typography>
                  <Chip
                    icon={getStatusIcon(inf?.status ?? "") || undefined}
                    label={(inf?.status ?? "").replace("_", " ").toUpperCase()}
                    color={getStatusColor(inf?.status ?? "") as "success" | "warning" | "info" | "error" | "default"}
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
                    disabled={updating || reviewMarked || inf?.status === "under_review"}
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

                {!reviewMarked && inf?.status !== "draft" && (
                  <>
                    <Button
                      variant="contained"
                      color="success"
                      onClick={() => void updateStatus("accepted")}
                      disabled={updating || inf?.status === "accepted"}
                      fullWidth
                      startIcon={<CheckCircleIcon />}
                    >
                      Accept INF
                    </Button>

                    <Button
                      variant="contained"
                      color="error"
                      onClick={() => void updateStatus("rejected")}
                      disabled={updating || inf?.status === "rejected"}
                      fullWidth
                      startIcon={<CancelIcon />}
                    >
                      Reject INF
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
