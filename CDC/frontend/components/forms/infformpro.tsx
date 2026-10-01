"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  FormControl,
  FormControlLabel,
  InputLabel,
  MenuItem,
  Paper,
  Select,
  Snackbar,
  Stack,
  Tab,
  Tabs,
  TextField,
  Typography,
  alpha,
  Radio,
  RadioGroup,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  Chip,
} from "@mui/material";
import WorkIcon from "@mui/icons-material/Work";
import BusinessIcon from "@mui/icons-material/Business";
import SchoolIcon from "@mui/icons-material/School";
import PaidIcon from "@mui/icons-material/Paid";
import AssignmentIcon from "@mui/icons-material/Assignment";
import DescriptionIcon from "@mui/icons-material/Description";
import CheckCircleIcon from "@mui/icons-material/CheckCircle";
import SaveIcon from "@mui/icons-material/Save";
import SendIcon from "@mui/icons-material/Send";
import WarningAmberIcon from "@mui/icons-material/WarningAmber";

import {
  FormSection,
  SkillsTagInput,
  EligibilityGrid,
  eligibilityNumbersValid,
  isValidPercent,
  SelectionProcessBuilder,
  StipendGrid,
  DeclarationChecklist,
  InfPreview,
  defaultProgrammes,
  defaultRounds,
  defaultProgrammeStipends,
  mergeCustomBranchesIntoProgrammes,
  RichTextEditor,
} from "./shared";
import type {
  Currency,
  ProgrammeEligibility,
  ProgrammeBranchGroup,
  ProgrammeBranchStateGroup,
  SelectionRound,
  ProgrammeStipend,
} from "./shared";
import { companyApi } from "@/lib/companyapi";
import SectorAutocomplete from "./shared/sectorautocomplete";
import GraduatingBatchDialog from "./shared/graduatingbatchdialog";

interface InfFormData {
  // Company Profile
  companyProfile: {
    name: string;
    website: string;
    sector: string;
    employeeCount: string;
    postalAddress: string;
    categoryOrgType: string;
    dateOfEstablishment: string;
    annualTurnover: string;
    linkedinUrl: string;
    industrySectorTags: string;
    mncHqCountryCity: string;
    natureOfBusiness: string;
    companyDescription: string;
    logoUrl?: string | null;
  };

  // Internship Details
  internshipTitle: string;
  internshipDesignation: string;
  internshipLocation: string;
  workMode: "onsite" | "remote" | "hybrid";
  expectedHires: string;
  minimumHires: string;
  duration: string;
  joiningMonth: string;
  skills: string[];
  internshipDescription: string;
  additionalInfo: string;
  registrationLink: string;

  // Eligibility
  eligibility: ProgrammeEligibility[];
  globalCgpa: string;
  globalBacklogs: boolean;
  genderFilter: "all" | "male" | "female";
  slpRequirement: string;
  minTenthPercent: string;
  minTwelfthPercent: string;
  graduatingBatch: string;

  // Stipend
  currency: Currency;
  stipendSameForAll: boolean;
  programmeStipends: ProgrammeStipend[];
  ppoProvision: boolean;
  ppoCtc: string;

  // Selection Process
  selectionRounds: SelectionRound[];

  // Declaration
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
}

const initialFormData: InfFormData = {
  companyProfile: {
    name: "",
    website: "",
    sector: "",
    employeeCount: "",
    postalAddress: "",
    categoryOrgType: "",
    dateOfEstablishment: "",
    annualTurnover: "",
    linkedinUrl: "",
    industrySectorTags: "",
    mncHqCountryCity: "",
    natureOfBusiness: "",
    companyDescription: "",
    logoUrl: null,
  },
  internshipTitle: "",
  internshipDesignation: "",
  internshipLocation: "",
  workMode: "onsite",
  expectedHires: "",
  minimumHires: "",
  duration: "",
  joiningMonth: "",
  skills: [],
  internshipDescription: "",
  additionalInfo: "",
  registrationLink: "",
  eligibility: defaultProgrammes,
  globalCgpa: "7.0",
  globalBacklogs: false,
  genderFilter: "all",
  slpRequirement: "",
  minTenthPercent: "",
  minTwelfthPercent: "",
  graduatingBatch: "",
  currency: "INR",
  stipendSameForAll: false,
  programmeStipends: defaultProgrammeStipends,
  ppoProvision: false,
  ppoCtc: "",
  selectionRounds: defaultRounds,
  declarations: {
    aipc: false,
    shortlistCriteria: false,
    infoVerified: false,
    consentLogo: false,
    confirmAccuracy: false,
    resultsViaCdc: false,
  },
  signatory: {
    name: "",
    designation: "",
    date: new Date().toISOString().split("T")[0],
  },
};

const tabs = [
  { label: "Company Profile", icon: <BusinessIcon /> },
  { label: "Internship Details", icon: <WorkIcon /> },
  { label: "Eligibility", icon: <SchoolIcon /> },
  { label: "Stipend", icon: <PaidIcon /> },
  { label: "Selection", icon: <AssignmentIcon /> },
  { label: "Declaration", icon: <DescriptionIcon /> },
  { label: "Preview & Submit", icon: <CheckCircleIcon /> },
];

const hasContent = (html: string | undefined | null): boolean => {
  if (!html) return false;
  return html.replace(/<[^>]*>/g, '').trim().length > 0;
};

type InfFormProProps = {
  initialData?: Partial<InfFormData> & { id?: number };
  onSaved?: (id: number) => void;
  onCancel?: () => void;
};

export default function InfFormPro({ initialData, onSaved, onCancel }: InfFormProProps) {
  const router = useRouter();
  const [activeTab, setActiveTab] = useState(0);
  const [formData, setFormData] = useState<InfFormData>(() => {
    const data = {
      ...initialFormData,
      ...initialData,
    };
    if (data.internshipTitle === "Untitled INF Draft") {
      data.internshipTitle = "";
    }
    if (data.internshipDescription === "Draft in progress." || !hasContent(data.internshipDescription)) {
      data.internshipDescription = "";
    }
    data.signatory.date = new Date().toLocaleDateString('en-CA');
    return data;
  });
  const [draftId, setDraftId] = useState<number | undefined>(initialData?.id);
  const [saving, setSaving] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [snackbar, setSnackbar] = useState<{ open: boolean; message: string; severity: "success" | "error" | "info" }>({
    open: false,
    message: "",
    severity: "info",
  });
  const [showBatchDialog, setShowBatchDialog] = useState(() => !initialData?.graduatingBatch);
  const [showLockWarning, setShowLockWarning] = useState(() => !!initialData?.graduatingBatch);

  const handleCancel = () => {
    if (onCancel) {
      onCancel();
    } else {
      router.push("/company");
    }
  };

  const saveDraftInstantly = async (updatedFormData: InfFormData) => {
    setSaving(true);
    try {
      const normalizedTitle = updatedFormData.internshipTitle.trim();
      const normalizedDescription = hasContent(updatedFormData.internshipDescription) ? updatedFormData.internshipDescription.trim() : "";

      const payload = {
        id: draftId,
        internship_title: normalizedTitle || "Untitled INF Draft",
        internship_description: normalizedDescription || "Draft in progress.",
        internship_location: updatedFormData.internshipLocation,
        form_data: JSON.stringify(updatedFormData),
        status: "draft",
      };

      const response = await companyApi<{ inf: { id: number } }>(
        "/company/infs/autosave",
        { method: "POST", body: JSON.stringify(payload) }
      );
      setDraftId(response.inf.id);
      setSnackbar({ open: true, message: "Draft saved", severity: "info" });
    } catch (e) {
      console.error("Auto-save failed:", e);
      const message = e instanceof Error ? e.message : "Unable to save draft.";
      if (message.includes("cannot be edited in its current status")) {
        return;
      }
      setError(message);
      setSnackbar({ open: true, message, severity: "error" });
    } finally {
      setSaving(false);
    }
  };

  const handleBatchConfirm = (batch: string) => {
    const updatedEligibility = formData.eligibility.map((prog) => ({
      ...prog,
      graduatingBatch: batch,
      graduatingBatches: [batch],
    }));
    const newFormData = {
      ...formData,
      graduatingBatch: batch,
      eligibility: updatedEligibility,
    };
    setFormData(newFormData);
    setShowBatchDialog(false);
    void saveDraftInstantly(newFormData);
  };

  useEffect(() => {
    const fetchCompanyProfile = async () => {
      try {
        const response = await companyApi<{
          company: {
            name: string;
            website: string | null;
            sector: string | null;
            employee_count: number | null;
            postal_address: string | null;
            category_org_type: string | null;
            date_of_establishment: string | null;
            annual_turnover: string | null;
            linkedin_url: string | null;
            industry_sector_tags: string[] | null;
            mnc_hq_country_city: string | null;
            nature_of_business: string | null;
            company_description: string | null;
            logo_url?: string | null;
            hr_name?: string | null;
            hr_designation?: string | null;
          };
        }>("/company/profile");

        setFormData((prev) => {
          return {
            ...prev,
            companyProfile: {
              name: prev.companyProfile.name || response.company.name || "",
              website: prev.companyProfile.website || response.company.website || "",
              sector: prev.companyProfile.sector || response.company.sector || "",
              employeeCount:
                prev.companyProfile.employeeCount ||
                (response.company.employee_count !== null
                  ? String(response.company.employee_count)
                  : ""),
              postalAddress: prev.companyProfile.postalAddress || response.company.postal_address || "",
              categoryOrgType: prev.companyProfile.categoryOrgType || response.company.category_org_type || "",
              dateOfEstablishment:
                prev.companyProfile.dateOfEstablishment ||
                (response.company.date_of_establishment
                  ? response.company.date_of_establishment.split("T")[0]
                  : ""),
              annualTurnover: prev.companyProfile.annualTurnover || response.company.annual_turnover || "",
              linkedinUrl: prev.companyProfile.linkedinUrl || response.company.linkedin_url || "",
              industrySectorTags:
                prev.companyProfile.industrySectorTags ||
                response.company.industry_sector_tags?.join(", ") ||
                "",
              mncHqCountryCity: prev.companyProfile.mncHqCountryCity || response.company.mnc_hq_country_city || "",
              natureOfBusiness: prev.companyProfile.natureOfBusiness || response.company.nature_of_business || "",
              companyDescription: prev.companyProfile.companyDescription || response.company.company_description || "",
              logoUrl: prev.companyProfile.logoUrl || response.company.logo_url || null,
            },
            signatory: {
              name: prev.signatory.name || "",
              designation: prev.signatory.designation || "",
              date: prev.signatory.date || new Date().toLocaleDateString('en-CA'),
            },
          };
        });
      } catch (e) {
        console.error("Failed to load company profile for INF:", e);
      }
    };

    fetchCompanyProfile();
  }, []);

  // Auto-save debounce
  const autoSave = useCallback(async () => {
    if (submitting || submitted || !formData.graduatingBatch) {
      return;
    }

    setSaving(true);
    try {
      const normalizedTitle = formData.internshipTitle.trim();
      const normalizedDescription = hasContent(formData.internshipDescription) ? formData.internshipDescription.trim() : "";

      const payload = {
        id: draftId,
        internship_title: normalizedTitle || "Untitled INF Draft",
        internship_description: normalizedDescription || "Draft in progress.",
        internship_location: formData.internshipLocation,
        form_data: JSON.stringify(formData),
        status: "draft",
      };

      const response = await companyApi<{ inf: { id: number } }>(
        "/company/infs/autosave",
        { method: "POST", body: JSON.stringify(payload) }
      );
      setDraftId(response.inf.id);
      setSnackbar({ open: true, message: "Draft saved", severity: "info" });
    } catch (e) {
      console.error("Auto-save failed:", e);
      const message = e instanceof Error ? e.message : "Unable to save draft.";
      if (message.includes("cannot be edited in its current status")) {
        return;
      }
      setError(message);
      setSnackbar({ open: true, message, severity: "error" });
    } finally {
      setSaving(false);
    }
  }, [formData, draftId, submitting, submitted]);

  // Debounced auto-save
  useEffect(() => {
    if (submitting || submitted || !formData.graduatingBatch) {
      return;
    }

    const timer = setTimeout(autoSave, 3000);
    return () => clearTimeout(timer);
  }, [formData, autoSave, submitting, submitted]);

  useEffect(() => {
    const fetchCustomBranches = async () => {
      try {
        const response = await companyApi<{
          programme_branches: ProgrammeBranchGroup[];
          branch_states: ProgrammeBranchStateGroup[];
        }>("/programme-branches");

        setFormData((prev) => ({
          ...prev,
          eligibility: mergeCustomBranchesIntoProgrammes(
            prev.eligibility.length > 0 ? prev.eligibility : defaultProgrammes,
            response.programme_branches ?? [],
            response.branch_states ?? []
          ),
        }));
      } catch (e) {
        console.error("Failed to load custom eligibility branches:", e);
      }
    };

    fetchCustomBranches();
  }, []);

  const updateFormData = <K extends keyof InfFormData>(field: K, value: InfFormData[K]) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const updateCompanyProfile = <K extends keyof InfFormData["companyProfile"]>(
    field: K,
    value: InfFormData["companyProfile"][K]
  ) => {
    setFormData((prev) => ({
      ...prev,
      companyProfile: {
        ...prev.companyProfile,
        [field]: value,
      },
    }));
  };

  const handleSubmit = async () => {
    // Validate required fields
    if (!formData.internshipTitle || !formData.internshipDescription) {
      setError("Please fill in all required fields");
      setActiveTab(1);
      return;
    }

    const allDeclarations = Object.values(formData.declarations).every(Boolean);
    if (!allDeclarations) {
      setError("Please accept all declarations");
      setActiveTab(5);
      return;
    }

    if (!formData.signatory.name) {
      setError("Please provide signatory details");
      setActiveTab(5);
      return;
    }

    setSubmitting(true);
    setError(null);

    try {
      const payload = {
        internship_title: formData.internshipTitle,
        internship_description: formData.internshipDescription,
        internship_location: formData.internshipLocation,
        stipend: formData.programmeStipends.find((s) => s.baseStipend)?.baseStipend
          ? parseInt(formData.programmeStipends.find((s) => s.baseStipend)!.baseStipend)
          : null,
        internship_duration_weeks: formData.duration ? parseInt(formData.duration) : null,
        vacancies: formData.expectedHires ? parseInt(formData.expectedHires) : null,
        form_data: JSON.stringify(formData),
        status: "submitted",
      };

      const path = draftId ? `/company/infs/${draftId}` : "/company/infs";
      const method = draftId ? "PUT" : "POST";

      const response = await companyApi<{ inf: { id: number } }>(path, {
        method,
        body: JSON.stringify(payload),
      });

      setDraftId(response.inf.id);
      setSubmitted(true);
      setSnackbar({ open: true, message: "INF submitted successfully!", severity: "success" });
      onSaved?.(response.inf.id);
    } catch (e) {
      const message = e instanceof Error ? e.message : "Submission failed";
      setError(message);
    } finally {
      setSubmitting(false);
    }
  };

  const canProceed = useMemo(() => {
    switch (activeTab) {
      case 0:
        return true;
      case 1:
        return !!formData.internshipTitle && hasContent(formData.internshipDescription);
      case 2:
        return (
          formData.eligibility.some((p) => p.branches.some((b) => b.selected)) &&
          !!formData.graduatingBatch &&
          eligibilityNumbersValid(formData.eligibility, formData.minTenthPercent, formData.minTwelfthPercent)
        );
      case 3:
        return formData.programmeStipends.some((s) => s.baseStipend);
      case 4: {
        const todayStr = new Date().toLocaleDateString('en-CA');
        return (
          formData.selectionRounds.some((r) => r.enabled) &&
          formData.selectionRounds.filter((r) => r.enabled).every((r) => !r.date || r.date >= todayStr)
        );
      }
      case 5:
        return Object.values(formData.declarations).every(Boolean);
      default:
        return true;
    }
  }, [activeTab, formData]);

  return (
    <Box>
      {/* Graduating Batch Selection Dialog */}
      <GraduatingBatchDialog
        open={showBatchDialog && !formData.graduatingBatch}
        onConfirm={handleBatchConfirm}
        onBack={handleCancel}
        initialBatch={formData.graduatingBatch || initialData?.graduatingBatch || ""}
        formType="INF"
      />

      {/* Lock warning dialog for existing INF */}
      <Dialog
        open={showLockWarning}
        onClose={() => setShowLockWarning(false)}
        maxWidth="xs"
      >
        <DialogTitle sx={{ display: 'flex', alignItems: 'center', gap: 1, bgcolor: 'warning.light', color: 'warning.contrastText', py: 1.5 }}>
          <WarningAmberIcon /> Lock Notice
        </DialogTitle>
        <DialogContent sx={{ mt: 2 }}>
          <DialogContentText>
            You have selected graduating batch <strong>{formData.graduatingBatch}</strong>.
            <br /><br />
            The graduating batch for this INF is locked and cannot be modified. If you need to hire for a different batch, you will need to create a new INF.
          </DialogContentText>
        </DialogContent>
        <DialogActions sx={{ px: 3, pb: 2 }}>
          <Button onClick={() => setShowLockWarning(false)} variant="contained" color="warning" autoFocus>
            I Understand
          </Button>
        </DialogActions>
      </Dialog>

      {/* Header */}
      <Paper
        sx={{
          p: 2,
          mb: 3,
          background: (theme) =>
            `linear-gradient(135deg, ${theme.palette.secondary.main} 0%, ${theme.palette.secondary.dark} 100%)`,
          color: "white",
        }}
      >
        <Stack direction="row" justifyContent="space-between" alignItems="center">
          <Box>
            {formData.internshipTitle && (
              <Typography variant="h6" fontWeight={700} sx={{ color: "rgba(255, 255, 255, 0.95)", textTransform: "uppercase", fontSize: "1.1rem", mb: 0.5 }}>
                🎓 {formData.internshipTitle}
              </Typography>
            )}
            <Typography variant="h5" fontWeight={600}>
              Internship Notification Form (INF)
            </Typography>
            <Typography variant="body2" sx={{ opacity: 0.9 }}>
              IIT (ISM) Dhanbad — Career Development Centre
            </Typography>
          </Box>
          <Stack direction="row" spacing={2} alignItems="center">
            {formData.graduatingBatch && (
              <Chip
                icon={<SchoolIcon style={{ color: "white" }} />}
                label={`Graduating Batch of ${formData.graduatingBatch}`}
                sx={{
                  bgcolor: "rgba(255, 255, 255, 0.2)",
                  color: "white",
                  fontWeight: 600,
                  fontSize: "0.9rem",
                  px: 1.5,
                  py: 2,
                  border: "1px solid rgba(255, 255, 255, 0.4)",
                }}
              />
            )}
            <Stack direction="row" spacing={1} alignItems="center">
              {saving && (
                <Stack direction="row" spacing={1} alignItems="center">
                  <CircularProgress size={16} sx={{ color: "white" }} />
                  <Typography variant="caption">Saving...</Typography>
                </Stack>
              )}
              {draftId && (
                <Typography variant="caption" sx={{ bgcolor: alpha("#fff", 0.2), px: 1, py: 0.5, borderRadius: 1 }}>
                  Draft #{draftId}
                </Typography>
              )}
            </Stack>
          </Stack>
        </Stack>
      </Paper>

      {formData.graduatingBatch && (
        <Alert
          severity="info"
          icon={<SchoolIcon />}
          sx={{
            mb: 3,
            fontWeight: 500,
            bgcolor: (theme) => alpha(theme.palette.info.main, 0.08),
            border: "1px solid",
            borderColor: "info.light",
          }}
        >
          You are currently Hiring for Graduating Batch <strong>{formData.graduatingBatch}</strong>. This choice is locked for this INF. If you need to hire for a different batch, you will need to create a new INF.
        </Alert>
      )}

      {/* Tab Navigation */}
      <Paper sx={{ mb: 3 }}>
        <Tabs
          value={activeTab}
          onChange={(_, v) => setActiveTab(v)}
          variant="scrollable"
          scrollButtons="auto"
          sx={{
            "& .MuiTab-root": {
              minHeight: 64,
              textTransform: "none",
              fontWeight: 500,
            },
          }}
        >
          {tabs.map((tab) => (
            <Tab
              key={tab.label}
              icon={tab.icon}
              label={tab.label}
              iconPosition="start"
              sx={{
                "&.Mui-selected": { bgcolor: alpha("#ff6f00", 0.08) },
              }}
            />
          ))}
        </Tabs>
      </Paper>

      {error && (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>
          {error}
        </Alert>
      )}

      <Alert severity="info" sx={{ mb: 2 }}>
        Fields marked with * are compulsory. Please review all values carefully before submitting.
      </Alert>

      {/* Tab Content */}
      <Box sx={{ minHeight: 400 }}>
        {/* Tab 0: Company Profile */}
        {activeTab === 0 && (
          <Stack spacing={3}>
            <FormSection
              title="Company Profile"
              subtitle="Enter the company information for this posting"
              icon={<DescriptionIcon />}
            >
              <Stack spacing={3}>
                <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
                  <TextField
                    fullWidth
                    label="Company Name"
                    value={formData.companyProfile.name}
                    onChange={(e) => updateCompanyProfile("name", e.target.value)}
                  />
                  <SectorAutocomplete
                    value={formData.companyProfile.sector}
                    onChange={(val) => updateCompanyProfile("sector", val)}
                    label="Sector"
                  />
                </Stack>

                <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
                  <TextField
                    fullWidth
                    label="Website"
                    value={formData.companyProfile.website}
                    onChange={(e) => updateCompanyProfile("website", e.target.value)}
                  />
                  <TextField
                    fullWidth
                    label="Number of Employees"
                    value={formData.companyProfile.employeeCount}
                    onChange={(e) => updateCompanyProfile("employeeCount", e.target.value)}
                  />
                </Stack>

                <TextField
                  fullWidth
                  multiline
                  rows={2}
                  label="Postal Address"
                  value={formData.companyProfile.postalAddress}
                  onChange={(e) => updateCompanyProfile("postalAddress", e.target.value)}
                />

                <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
                  <TextField
                    fullWidth
                    label="Category / Organization Type"
                    value={formData.companyProfile.categoryOrgType}
                    onChange={(e) => updateCompanyProfile("categoryOrgType", e.target.value)}
                  />
                  <TextField
                    fullWidth
                    type="date"
                    label="Date of Establishment"
                    value={formData.companyProfile.dateOfEstablishment}
                    onChange={(e) => updateCompanyProfile("dateOfEstablishment", e.target.value)}
                    InputLabelProps={{ shrink: true }}
                    inputProps={{ max: new Date().toISOString().split('T')[0] }}
                  />
                </Stack>

                <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
                  <TextField
                    fullWidth
                    label="Annual Turnover"
                    value={formData.companyProfile.annualTurnover}
                    onChange={(e) => updateCompanyProfile("annualTurnover", e.target.value)}
                  />
                  <TextField
                    fullWidth
                    label="LinkedIn URL"
                    value={formData.companyProfile.linkedinUrl}
                    onChange={(e) => updateCompanyProfile("linkedinUrl", e.target.value)}
                  />
                </Stack>

                <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
                  <TextField
                    fullWidth
                    label="Industry Sector Tags"
                    value={formData.companyProfile.industrySectorTags}
                    onChange={(e) => updateCompanyProfile("industrySectorTags", e.target.value)}
                    helperText="Separate with commas"
                  />
                  <TextField
                    fullWidth
                    label="If MNC — HQ Country/City"
                    value={formData.companyProfile.mncHqCountryCity}
                    onChange={(e) => updateCompanyProfile("mncHqCountryCity", e.target.value)}
                  />
                </Stack>

                <TextField
                  fullWidth
                  label="Nature of Business"
                  value={formData.companyProfile.natureOfBusiness}
                  onChange={(e) => updateCompanyProfile("natureOfBusiness", e.target.value)}
                />

                <RichTextEditor
                  label="Company Description"
                  value={formData.companyProfile.companyDescription}
                  onChange={(val) => updateCompanyProfile("companyDescription", val)}
                />
              </Stack>
            </FormSection>
          </Stack>
        )}

        {/* Tab 1: Internship Details */}
        {activeTab === 1 && (
          <Stack spacing={3}>
            <FormSection title="Basic Internship Information" icon={<WorkIcon />} required>
              <Stack spacing={3}>
                <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
                  <TextField
                    fullWidth
                    label="Internship Title / Profile Name"
                    value={formData.internshipTitle}
                    onChange={(e) => {
                      updateFormData("internshipTitle", e.target.value);
                    }}
                    required
                    placeholder="e.g., Software Development Intern"
                    helperText="This will be displayed to students"
                  />
                  <TextField
                    fullWidth
                    label="Designation (Formal)"
                    value={formData.internshipDesignation}
                    onChange={(e) => {
                      updateFormData("internshipDesignation", e.target.value);
                    }}
                    placeholder="e.g., Summer Intern, Research Intern"
                  />
                </Stack>

                <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
                  <TextField
                    fullWidth
                    label="Place of Posting"
                    value={formData.internshipLocation}
                    onChange={(e) => {
                      updateFormData("internshipLocation", e.target.value);
                    }}
                    placeholder="e.g., Bangalore, Mumbai, Remote"
                  />
                  <FormControl fullWidth>
                    <InputLabel>Work Mode</InputLabel>
                    <Select
                      value={formData.workMode}
                      label="Work Mode"
                      onChange={(e) => updateFormData("workMode", e.target.value as "onsite" | "remote" | "hybrid")}
                    >
                      <MenuItem value="onsite">On-site / Office</MenuItem>
                      <MenuItem value="remote">Remote / Work from Home</MenuItem>
                      <MenuItem value="hybrid">Hybrid</MenuItem>
                    </Select>
                  </FormControl>
                </Stack>

                <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
                  <TextField
                    fullWidth
                    type="number"
                    label="Expected Interns"
                    value={formData.expectedHires}
                    onChange={(e) => {
                      updateFormData("expectedHires", e.target.value);
                    }}
                    placeholder="e.g., 10"
                  />
                  <TextField
                    fullWidth
                    type="number"
                    label="Internship Duration (weeks)"
                    value={formData.duration}
                    onChange={(e) => {
                      updateFormData("duration", e.target.value);
                    }}
                    placeholder="e.g., 8"
                    helperText="Duration in weeks"
                  />
                  <TextField
                    fullWidth
                    type="month"
                    label="Tentative Start Month"
                    value={formData.joiningMonth}
                    onChange={(e) => {
                      updateFormData("joiningMonth", e.target.value);
                    }}
                    InputLabelProps={{ shrink: true }}
                    inputProps={{
                      min: (() => {
                        const d = new Date();
                        let m = d.getMonth() + 2;
                        let y = d.getFullYear();
                        if (m > 12) { m = 1; y++; }
                        return `${y}-${String(m).padStart(2, '0')}`;
                      })(),
                    }}
                  />
                </Stack>
              </Stack>
            </FormSection>

            <FormSection title="Internship Description & Requirements" icon={<DescriptionIcon />} required>
              <Stack spacing={3}>
                <RichTextEditor
                  label="Internship Description"
                  value={formData.internshipDescription}
                  onChange={(val) => updateFormData("internshipDescription", val)}
                  placeholder="Describe the internship role, projects, learning opportunities..."
                  helperText={`${formData.internshipDescription.replace(/<[^>]*>?/gm, '').length}/5000 characters`}
                />

                <SkillsTagInput
                  value={formData.skills}
                  onChange={(skills) => updateFormData("skills", skills)}
                  label="Required Skills"
                />

                <TextField
                  fullWidth
                  multiline
                  rows={3}
                  label="Additional Information"
                  value={formData.additionalInfo}
                  onChange={(e) => updateFormData("additionalInfo", e.target.value)}
                  placeholder="Any other relevant details about the internship..."
                />

                <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
                  <TextField
                    fullWidth
                    label="Company Registration Link (if any)"
                    value={formData.registrationLink}
                    onChange={(e) => updateFormData("registrationLink", e.target.value)}
                    placeholder="https://..."
                  />
                </Stack>
              </Stack>
            </FormSection>
          </Stack>
        )}

        {/* Tab 2: Eligibility */}
        {activeTab === 2 && (
          <FormSection
            title="Eligibility Criteria"
            subtitle="Select eligible programmes and branches, set CGPA requirements"
            icon={<SchoolIcon />}
            required
          >
            <Stack spacing={3}>
              {/* Gender Filter */}
              <Paper sx={{ p: 2, bgcolor: "grey.50" }}>
                <Stack direction={{ xs: "column", md: "row" }} spacing={3} alignItems={{ md: "center" }}>
                  <Typography variant="subtitle2" fontWeight={600}>
                    Gender Preference:
                  </Typography>
                  <RadioGroup
                    row
                    value={formData.genderFilter}
                    onChange={(e) => updateFormData("genderFilter", e.target.value as "all" | "male" | "female")}
                  >
                    <FormControlLabel value="all" control={<Radio />} label="All Genders" />
                    <FormControlLabel value="male" control={<Radio />} label="Male Only" />
                    <FormControlLabel value="female" control={<Radio />} label="Female Only" />
                  </RadioGroup>
                </Stack>
              </Paper>

              <EligibilityGrid
                value={formData.eligibility}
                onChange={(v) => updateFormData("eligibility", v)}
                globalCgpa={formData.globalCgpa}
                onGlobalCgpaChange={(v) => updateFormData("globalCgpa", v)}
                globalBacklogs={formData.globalBacklogs}
                onGlobalBacklogsChange={(v) => updateFormData("globalBacklogs", v)}
                graduatingBatch={formData.graduatingBatch}
                batchReadOnly
              />

              {/* Optional school-marks cutoffs (Phase 2) */}
              <Stack direction={{ xs: "column", sm: "row" }} spacing={2}>
                <TextField
                  fullWidth
                  type="number"
                  label="Minimum 10th % (optional)"
                  value={formData.minTenthPercent ?? ""}
                  onChange={(e) => updateFormData("minTenthPercent", e.target.value)}
                  inputProps={{ min: 0, max: 100, step: 0.01 }}
                  error={!isValidPercent(formData.minTenthPercent)}
                  helperText={isValidPercent(formData.minTenthPercent) ? "Leave blank for no cutoff" : "Enter a value between 0 and 100 (up to 2 decimals)"}
                />
                <TextField
                  fullWidth
                  type="number"
                  label="Minimum 12th % (optional)"
                  value={formData.minTwelfthPercent ?? ""}
                  onChange={(e) => updateFormData("minTwelfthPercent", e.target.value)}
                  inputProps={{ min: 0, max: 100, step: 0.01 }}
                  error={!isValidPercent(formData.minTwelfthPercent)}
                  helperText={isValidPercent(formData.minTwelfthPercent) ? "Leave blank for no cutoff" : "Enter a value between 0 and 100 (up to 2 decimals)"}
                />
              </Stack>

              <TextField
                fullWidth
                multiline
                rows={2}
                label="Any Specific Requirements related to SLP (Skill-based Learning Program)"
                value={formData.slpRequirement}
                onChange={(e) => updateFormData("slpRequirement", e.target.value)}
                placeholder="Mention any specific skill or certification requirements..."
              />
            </Stack>
          </FormSection>
        )}

        {/* Tab 3: Stipend */}
        {activeTab === 3 && (
          <FormSection
            title="Stipend Details"
            subtitle="Provide stipend breakdown and PPO information"
            icon={<PaidIcon />}
            required
          >
            <StipendGrid
              currency={formData.currency}
              onCurrencyChange={(v) => updateFormData("currency", v)}
              sameForAll={formData.stipendSameForAll}
              onSameForAllChange={(v) => updateFormData("stipendSameForAll", v)}
              programmeStipends={formData.programmeStipends}
              onProgrammeStipendsChange={(v) => updateFormData("programmeStipends", v)}
              ppoProvision={formData.ppoProvision}
              onPpoProvisionChange={(v) => updateFormData("ppoProvision", v)}
              ppoCtc={formData.ppoCtc}
              onPpoCtcChange={(v) => updateFormData("ppoCtc", v)}
              eligibleProgrammes={formData.eligibility}
            />
          </FormSection>
        )}

        {/* Tab 4: Selection Process */}
        {activeTab === 4 && (
          <Stack spacing={3}>
            <FormSection
              title="Selection Process"
              subtitle="Configure your selection process - tests, interviews, and rounds"
              icon={<AssignmentIcon />}
              required
            >
              <SelectionProcessBuilder
                value={formData.selectionRounds}
                onChange={(v) => updateFormData("selectionRounds", v)}
              />
            </FormSection>
          </Stack>
        )}

        {/* Tab 5: Declaration */}
        {activeTab === 5 && (
          <FormSection
            title="Declaration & Agreement"
            subtitle="Review and accept the terms before submission"
            icon={<DescriptionIcon />}
            required
          >
            <DeclarationChecklist
              formType="inf"
              draftId={draftId}
              declarations={formData.declarations}
              onDeclarationsChange={(v) => updateFormData("declarations", v)}
            />
          </FormSection>
        )}

        {/* Tab 6: Preview & Submit */}
        {activeTab === 6 && (
          <InfPreview
            companyProfile={formData.companyProfile}
            internshipDetails={{
              title: formData.internshipTitle,
              designation: formData.internshipDesignation,
              location: formData.internshipLocation,
              workMode: formData.workMode,
              expectedHires: formData.expectedHires,
              duration: formData.duration,
              joiningMonth: formData.joiningMonth,
              skills: formData.skills,
              description: formData.internshipDescription,
              registrationLink: formData.registrationLink,
              additionalInfo: formData.additionalInfo,
            }}
            eligibility={formData.eligibility}
            globalCgpa={formData.globalCgpa}
            globalBacklogs={formData.globalBacklogs}
            genderFilter={formData.genderFilter}
            slpRequirement={formData.slpRequirement}
            minTenthPercent={formData.minTenthPercent}
            minTwelfthPercent={formData.minTwelfthPercent}
            graduatingBatch={formData.graduatingBatch}
            stipend={{
              currency: formData.currency,
              programmeStipends: formData.programmeStipends,
              ppoProvision: formData.ppoProvision,
              ppoCtc: formData.ppoCtc,
            }}
            selectionProcess={{
              rounds: formData.selectionRounds,
            }}
            declarations={formData.declarations}
            signatory={formData.signatory}
            onSignatoryChange={(v) => updateFormData("signatory", v)}
            companyLogoUrl={formData.companyProfile.logoUrl}
            onNavigateToTab={setActiveTab}
          />
        )}
      </Box>

      {/* Navigation Buttons */}
      <Paper sx={{ p: 2, mt: 3, position: "sticky", bottom: 0, zIndex: 10 }}>
        <Stack direction="row" justifyContent="space-between" alignItems="center">
          <Button
            variant="outlined"
            disabled={activeTab === 0}
            onClick={() => setActiveTab((t) => t - 1)}
          >
            Previous
          </Button>

          <Stack direction="row" spacing={2}>
            <Button
              variant="outlined"
              startIcon={<SaveIcon />}
              onClick={autoSave}
              disabled={saving}
            >
              Save Draft
            </Button>

            {activeTab < 6 ? (
              <Button
                variant="contained"
                color="secondary"
                onClick={() => setActiveTab((t) => t + 1)}
                disabled={!canProceed}
              >
                Next
              </Button>
            ) : (
              <Button
                variant="contained"
                color="secondary"
                startIcon={submitting ? <CircularProgress size={16} color="inherit" /> : <SendIcon />}
                onClick={handleSubmit}
                disabled={submitting}
              >
                {submitting ? "Submitting..." : "Submit INF"}
              </Button>
            )}
          </Stack>
        </Stack>
      </Paper>

      {/* Snackbar */}
      <Snackbar
        open={snackbar.open}
        autoHideDuration={3000}
        onClose={() => setSnackbar((s) => ({ ...s, open: false }))}
      >
        <Alert severity={snackbar.severity} onClose={() => setSnackbar((s) => ({ ...s, open: false }))}>
          {snackbar.message}
        </Alert>
      </Snackbar>
    </Box>
  );
}
