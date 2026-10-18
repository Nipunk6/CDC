"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Box,
  Button,
  Chip,
  LinearProgress,
  Paper,
  Stack,
  Typography,
} from "@mui/material";
import AddIcon from "@mui/icons-material/Add";
import EditIcon from "@mui/icons-material/Edit";
import OpenInNewIcon from "@mui/icons-material/OpenInNew";

import { InfPreview, JnfPreview } from "@/components/forms/shared/formpreview";
import { adminApi } from "@/lib/adminapi";
import { formatDate } from "@/lib/format";

const statusColor = { accepted: "success", submitted: "info", under_review: "warning", rejected: "error", draft: "default" };
const statusLabel = (status) => String(status ?? "").replace("_", " ").toUpperCase();

const parse = (data) => {
  if (!data) return {};
  if (typeof data === "string") {
    try {
      return JSON.parse(data);
    } catch {
      return {};
    }
  }
  return data;
};

const declarationsFrom = (data) => ({
  aipc: false, shortlistCriteria: false, infoVerified: false, consentLogo: false, confirmAccuracy: false, resultsViaCdc: false,
  ...(data.declarations ?? {}),
});

// Common props of InfPreview / JnfPreview, read-only (no section edit links, no signatory inputs).
const sharedProps = (data, logoUrl) => ({
  companyProfile: { name: "", website: "", ...(data.companyProfile ?? {}) },
  eligibility: data.eligibility ?? [],
  globalCgpa: data.globalCgpa ?? "",
  globalBacklogs: Boolean(data.globalBacklogs),
  genderFilter: data.genderFilter ?? "all",
  slpRequirement: data.slpRequirement ?? "",
  minTenthPercent: data.minTenthPercent ?? "",
  minTwelfthPercent: data.minTwelfthPercent ?? "",
  graduatingBatch: data.graduatingBatch ?? "",
  selectionProcess: { rounds: data.selectionRounds ?? [] },
  declarations: declarationsFrom(data),
  signatory: { name: "", designation: "", date: "", ...(data.signatory ?? {}) },
  companyLogoUrl: data.companyProfile?.logoUrl || logoUrl || null,
  readOnly: true,
});

function FormPreview({ formType, data, logoUrl }) {
  if (formType === "inf") {
    return (
      <InfPreview
        {...sharedProps(data, logoUrl)}
        internshipDetails={{
          title: data.internshipTitle ?? "",
          designation: data.internshipDesignation ?? "",
          location: data.internshipLocation ?? "",
          workMode: data.workMode ?? "",
          expectedHires: data.expectedHires ?? "",
          duration: data.duration ?? "",
          joiningMonth: data.joiningMonth ?? "",
          skills: data.skills ?? [],
          description: data.internshipDescription ?? "",
          registrationLink: data.registrationLink ?? "",
          additionalInfo: data.additionalInfo ?? "",
        }}
        stipend={{
          currency: data.currency ?? "INR",
          programmeStipends: data.programmeStipends ?? [],
          ppoProvision: Boolean(data.ppoProvision),
          ppoCtc: data.ppoCtc ?? "",
        }}
      />
    );
  }

  return (
    <JnfPreview
      {...sharedProps(data, logoUrl)}
      jobDetails={{
        title: data.jobTitle ?? "",
        designation: data.jobDesignation ?? "",
        location: data.jobLocation ?? "",
        workMode: data.workMode ?? "",
        expectedHires: data.expectedHires ?? "",
        minimumHires: data.minimumHires ?? "",
        joiningMonth: data.joiningMonth ?? "",
        skills: data.skills ?? [],
        description: data.jobDescription ?? "",
        registrationLink: data.registrationLink ?? "",
        additionalInfo: data.additionalInfo ?? "",
      }}
      salary={{
        currency: data.currency ?? "INR",
        programmeSalaries: data.programmeSalaries ?? [],
        components: data.salaryComponents ?? {},
      }}
    />
  );
}

/**
 * "Add Job Profile" for an existing company: its JNFs/INFs as a full read-only preview, with Edit (the admin
 * form page in edit mode) and the form page (Open for Applications) at the top. The company-side wizard opens
 * only through "Create a new …".
 */
export default function CompanyFormsPreview({ company, formType, cycleId, onCreateNew }) {
  const [state, setState] = useState({ key: null, forms: [], logoUrl: null, error: null });
  const [selectedId, setSelectedId] = useState(null);
  const requestKey = `${company.id}-${formType}`;
  const loading = state.key !== requestKey;
  const label = formType === "inf" ? "INF" : "JNF";

  useEffect(() => {
    let cancelled = false;
    adminApi(`/admin/form-builder/${company.id}/forms?type=${formType}`)
      .then((response) => {
        if (cancelled) return;
        const forms = response.forms ?? [];
        setState({ key: requestKey, forms, logoUrl: response.company?.logo_url ?? null, error: null });
        setSelectedId(forms[0]?.id ?? null);
      })
      .catch((e) => {
        if (cancelled) return;
        setState({ key: requestKey, forms: [], logoUrl: null, error: e instanceof Error ? e.message : "Could not load the company's forms." });
      });
    return () => {
      cancelled = true;
    };
  }, [company.id, formType, requestKey]);

  const createButton = (
    <Button variant="outlined" startIcon={<AddIcon />} onClick={onCreateNew}>
      Create a new {label}
    </Button>
  );

  if (loading) return <LinearProgress sx={{ mt: 3 }} />;
  if (state.error) return <Alert severity="error" sx={{ mt: 3 }}>{state.error}</Alert>;

  if (state.forms.length === 0) {
    return (
      <Paper variant="outlined" sx={{ mt: 3, p: 3, textAlign: "center" }}>
        <Typography fontWeight={600} gutterBottom>
          {company.name} has no {label} yet
        </Typography>
        <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
          Fill one on the company&apos;s behalf. It is accepted when you submit it.
        </Typography>
        {createButton}
      </Paper>
    );
  }

  const selected = state.forms.find((form) => form.id === selectedId) ?? state.forms[0];
  const detailPath = `/admin/${formType}s/${selected.id}`;
  const query = cycleId ? `cycle=${cycleId}` : "";

  return (
    <Box sx={{ mt: 3 }}>
      {state.forms.length > 1 && (
        <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap sx={{ mb: 2 }}>
          {state.forms.map((form) => (
            <Chip
              key={form.id}
              clickable
              color={form.id === selected.id ? "primary" : "default"}
              variant={form.id === selected.id ? "filled" : "outlined"}
              label={`${form.title} · ${statusLabel(form.status)}`}
              onClick={() => setSelectedId(form.id)}
            />
          ))}
        </Stack>
      )}

      <Paper
        variant="outlined"
        sx={{ p: 2, mb: 2, position: "sticky", top: 0, zIndex: 5, bgcolor: "background.paper" }}
      >
        <Stack direction={{ xs: "column", md: "row" }} spacing={2} justifyContent="space-between" alignItems={{ md: "center" }}>
          <Box sx={{ minWidth: 0 }}>
            <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap>
              <Typography variant="h6" fontWeight={700} sx={{ overflowWrap: "anywhere" }}>
                {selected.title}
              </Typography>
              <Chip size="small" color={statusColor[selected.status] ?? "default"} label={statusLabel(selected.status)} />
            </Stack>
            <Typography variant="body2" color="text.secondary">
              {company.name} · {label} · last updated {formatDate(selected.updated_at)}
            </Typography>
          </Box>
          <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
            <Button
              component={Link}
              href={`${detailPath}?${["edit=1", query].filter(Boolean).join("&")}`}
              variant="contained"
              startIcon={<EditIcon />}
            >
              Edit
            </Button>
            <Button component={Link} href={query ? `${detailPath}?${query}` : detailPath} variant="outlined" startIcon={<OpenInNewIcon />}>
              Open form page
            </Button>
            {createButton}
          </Stack>
        </Stack>
        {selected.status === "accepted" && (
          <Typography variant="caption" color="text.secondary" sx={{ display: "block", mt: 1 }}>
            Accepted. Open it for applications from the form page.
          </Typography>
        )}
      </Paper>

      <FormPreview formType={formType} data={parse(selected.form_data)} logoUrl={state.logoUrl} />
    </Box>
  );
}
