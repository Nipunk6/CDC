"use client";

import { Suspense, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import {
  Alert,
  Autocomplete,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  FormControlLabel,
  Grid2,
  LinearProgress,
  Radio,
  RadioGroup,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import WorkIcon from "@mui/icons-material/Work";
import ArrowForwardIcon from "@mui/icons-material/ArrowForward";
import BusinessIcon from "@mui/icons-material/Business";
import AddBusinessIcon from "@mui/icons-material/AddBusiness";

import PageHeader from "@/components/shared/pageheader";
import JnfFormPro from "@/components/forms/jnfformpro";
import InfFormPro from "@/components/forms/infformpro";
import CompanyFormsPreview from "@/components/admin/companyformspreview";
import AcceptedFormsGrid from "@/components/admin/acceptedformsgrid";
import { adminApi } from "@/lib/adminapi";

const emptyCompany = { name: "", hr_name: "", hr_email: "", website: "", sector: "" };

export default function AddNewJobPage() {
  return (
    <Suspense fallback={<LinearProgress />}>
      <AddNewJob />
    </Suspense>
  );
}

// "Add New Job" (S6.1, B2-1). Landing: recently accepted JNFs/INFs as company cards to publish or duplicate.
// ?company=existing picks a company already on the portal; ?company=new enters a new company first. A new form
// or a duplicate opens in the same JNF/INF wizard the companies use; it is accepted on submit.
function AddNewJob() {
  const router = useRouter();
  const params = useSearchParams();
  const cycleParam = params.get("cycle") ?? "";
  const newCompany = params.get("company") === "new";
  const landing = !newCompany && params.get("company") !== "existing";

  const [cycle, setCycle] = useState(null);
  const [formType, setFormType] = useState("jnf");
  const [company, setCompany] = useState(null);
  const [search, setSearch] = useState("");
  const [options, setOptions] = useState([]);
  const [searching, setSearching] = useState(false);
  const [draft, setDraft] = useState(emptyCompany);
  const [savingCompany, setSavingCompany] = useState(false);
  const [error, setError] = useState(null);
  const [started, setStarted] = useState(false);
  // A duplicated form (draft) opened in the wizard: its form data plus id.
  const [initialData, setInitialData] = useState(null);

  useEffect(() => {
    if (!cycleParam) return;
    adminApi(`/admin/placement-cycles/${cycleParam}`)
      .then((response) => {
        setCycle(response.placement_cycle);
        setFormType(response.placement_cycle?.type === "internship" ? "inf" : "jnf");
      })
      .catch(() => setCycle(null));
  }, [cycleParam]);

  useEffect(() => {
    const timer = setTimeout(() => {
      adminApi(`/admin/form-builder/companies?search=${encodeURIComponent(search)}`)
        .then((response) => setOptions(response.companies ?? []))
        .catch(() => setOptions([]))
        .finally(() => setSearching(false));
    }, 250);
    return () => clearTimeout(timer);
  }, [search]);

  // New company: create it, then go straight to the form. Backing out of the form keeps the created company
  // selected (the picker is shown), so it is never created twice.
  const createCompanyAndStart = async () => {
    setSavingCompany(true);
    setError(null);
    try {
      const response = await adminApi("/admin/form-builder/companies", {
        method: "POST",
        body: JSON.stringify(draft),
      });
      setCompany(response.company);
      setOptions((prev) => [response.company, ...prev]);
      setDraft(emptyCompany);
      setStarted(true);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not add the company.");
    } finally {
      setSavingCompany(false);
    }
  };

  const openDuplicate = (response) => {
    setError(null);
    setCompany(response.company);
    setFormType(response.form_type);
    setInitialData({ ...(response.form?.form_data ?? {}), id: response.form?.id });
    setStarted(true);
    window.scrollTo({ top: 0 });
  };

  const closeWizard = () => {
    setStarted(false);
    setInitialData(null);
  };

  const modeHref = (mode) => `/admin/postings/new?company=${mode}${cycleParam ? `&cycle=${cycleParam}` : ""}`;

  const detailPath = (id) => `/admin/${formType}s/${id}${cycleParam ? `?cycle=${cycleParam}` : ""}`;
  const backHref = cycleParam ? `/admin/placement-cycles/${cycleParam}` : "/admin/postings";
  const canAddCompany = draft.name.trim() && draft.hr_name.trim() && draft.hr_email.trim();
  // The new-company fields show until that company exists; then it is picked like any other.
  const enteringCompany = newCompany && !company;

  return (
    <>
      <PageHeader
        icon={<WorkIcon />}
        title={`${cycle ? `${cycle.name} / ` : ""}${landing ? "Add New Job" : newCompany ? "New Company & Job Profile" : "New Job Profile"}`}
        subtitle={
          started && company
            ? `${company.name} · ${formType === "inf" ? "INF (internship)" : "JNF (full-time)"}${initialData ? " · duplicated draft" : ""}. The form is accepted when you submit it; open it for applications next.`
            : landing
              ? "Publish a recently accepted JNF or INF, duplicate one, or fill a new form for a company."
              : newCompany
                ? "Enter the new company, choose JNF or INF, then fill the form on the company's behalf."
                : "Pick a company already on the portal, choose JNF or INF, then fill the form on the company's behalf."
        }
        backHref={landing ? backHref : `/admin/postings/new${cycleParam ? `?cycle=${cycleParam}` : ""}`}
        backLabel="Back"
        actions={
          landing && !started ? (
            <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
              <Button component={Link} href={modeHref("existing")} variant="contained" startIcon={<BusinessIcon />}>
                New form for a company
              </Button>
              <Button component={Link} href={modeHref("new")} variant="outlined" startIcon={<AddBusinessIcon />}>
                Add New Company
              </Button>
            </Stack>
          ) : undefined
        }
      />

      {error && (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>
          {error}
        </Alert>
      )}

      {landing && !started && <AcceptedFormsGrid onDuplicated={openDuplicate} />}

      {!landing && !started && (
        <Card>
          <CardContent>
            <Stack spacing={3}>
              <Box>
                <Typography variant="subtitle1" fontWeight={600} gutterBottom>
                  {enteringCompany ? "New company" : "Company*"}
                </Typography>
                {!enteringCompany && (
                  <Stack direction={{ xs: "column", sm: "row" }} spacing={1.5} alignItems={{ sm: "flex-start" }}>
                    <Autocomplete
                      sx={{ flex: 1, minWidth: 0 }}
                      options={options}
                      value={company}
                      loading={searching}
                      filterOptions={(x) => x}
                      isOptionEqualToValue={(a, b) => a.id === b.id}
                      getOptionLabel={(option) => option.name ?? ""}
                      onChange={(_event, value) => setCompany(value)}
                      onInputChange={(_event, value, reason) => {
                        if (reason === "input") {
                          setSearching(true);
                          setSearch(value);
                        }
                      }}
                      renderOption={(props, option) => {
                        const { key, ...rest } = props;
                        return (
                          <li key={key} {...rest}>
                            <Box sx={{ minWidth: 0 }}>
                              <Typography variant="body2" fontWeight={600} noWrap>
                                {option.name}
                              </Typography>
                              <Typography variant="caption" color="text.secondary" noWrap component="div">
                                {option.hr_email}
                              </Typography>
                            </Box>
                          </li>
                        );
                      }}
                      renderInput={(inputProps) => (
                        <TextField
                          {...inputProps}
                          placeholder="Start typing company name ..."
                          helperText="Suggestions will appear once you start typing company name."
                        />
                      )}
                    />
                  </Stack>
                )}

                {enteringCompany && (
                  <Box>
                    <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
                      Adds a company record without a portal login. The rest of the company profile is filled in the form.
                    </Typography>
                    <Grid2 container spacing={2}>
                      <Grid2 size={{ xs: 12, sm: 6 }}>
                        <TextField fullWidth required label="Company name" value={draft.name} onChange={(e) => setDraft({ ...draft, name: e.target.value })} />
                      </Grid2>
                      <Grid2 size={{ xs: 12, sm: 6 }}>
                        <TextField fullWidth label="Sector" value={draft.sector} onChange={(e) => setDraft({ ...draft, sector: e.target.value })} />
                      </Grid2>
                      <Grid2 size={{ xs: 12, sm: 6 }}>
                        <TextField fullWidth required label="HR name" value={draft.hr_name} onChange={(e) => setDraft({ ...draft, hr_name: e.target.value })} />
                      </Grid2>
                      <Grid2 size={{ xs: 12, sm: 6 }}>
                        <TextField fullWidth required type="email" label="HR email" value={draft.hr_email} onChange={(e) => setDraft({ ...draft, hr_email: e.target.value })} />
                      </Grid2>
                      <Grid2 size={12}>
                        <TextField fullWidth label="Website" placeholder="https://" value={draft.website} onChange={(e) => setDraft({ ...draft, website: e.target.value })} />
                      </Grid2>
                    </Grid2>
                  </Box>
                )}

                {company && (
                  <Chip sx={{ mt: 1.5, maxWidth: "100%" }} color="primary" variant="outlined" label={`${company.name} · ${company.hr_email}`} onDelete={() => setCompany(null)} />
                )}
              </Box>

              <Box>
                <Typography variant="subtitle1" fontWeight={600} gutterBottom>
                  Form
                </Typography>
                <RadioGroup row value={formType} onChange={(e) => setFormType(e.target.value)}>
                  <FormControlLabel value="jnf" control={<Radio />} label="JNF (full-time)" disabled={cycle?.type === "internship"} />
                  <FormControlLabel value="inf" control={<Radio />} label="INF (internship)" disabled={cycle?.type === "fulltime"} />
                </RadioGroup>
                {cycle && (
                  <Typography variant="caption" color="text.secondary">
                    Placement: {cycle.name}. It is pre-selected when you open the profile for applications.
                  </Typography>
                )}
              </Box>

              {enteringCompany && (
                <Box sx={{ display: "flex", justifyContent: "flex-end" }}>
                  <Button variant="contained" endIcon={<ArrowForwardIcon />} disabled={savingCompany || !canAddCompany} onClick={createCompanyAndStart}>
                    {savingCompany ? "Adding company..." : "Add company & fill job details"}
                  </Button>
                </Box>
              )}
            </Stack>
          </CardContent>
        </Card>
      )}

      {/* Existing company: its forms as a read-only preview; the wizard opens only via "Create a new …". */}
      {!landing && !started && company && !enteringCompany && (
        <CompanyFormsPreview company={company} formType={formType} cycleId={cycleParam} onCreateNew={() => setStarted(true)} />
      )}

      {started && company && (
        <Box sx={{ maxWidth: 1200, mx: "auto" }}>
          {formType === "inf" ? (
            <InfFormPro
              key={`inf-${company.id}-${initialData?.id ?? "new"}`}
              api={adminApi}
              apiPrefix={`/admin/form-builder/${company.id}`}
              initialData={initialData ?? undefined}
              onSaved={(id) => router.push(detailPath(id))}
              onCancel={closeWizard}
            />
          ) : (
            <JnfFormPro
              key={`jnf-${company.id}-${initialData?.id ?? "new"}`}
              api={adminApi}
              apiPrefix={`/admin/form-builder/${company.id}`}
              initialData={initialData ?? undefined}
              onSaved={(id) => router.push(detailPath(id))}
              onCancel={closeWizard}
            />
          )}
        </Box>
      )}
    </>
  );
}
