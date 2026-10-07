"use client";

import { Suspense, useEffect, useState } from "react";
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
  Link as MuiLink,
  Radio,
  RadioGroup,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import WorkIcon from "@mui/icons-material/Work";
import AddIcon from "@mui/icons-material/Add";
import ArrowForwardIcon from "@mui/icons-material/ArrowForward";

import PageHeader from "@/components/shared/pageheader";
import JnfFormPro from "@/components/forms/jnfformpro";
import InfFormPro from "@/components/forms/infformpro";
import { adminApi } from "@/lib/adminapi";

const emptyCompany = { name: "", hr_name: "", hr_email: "", website: "", sector: "" };

export default function AddNewJobPage() {
  return (
    <Suspense fallback={<LinearProgress />}>
      <AddNewJob />
    </Suspense>
  );
}

// "Add New Job" (S6.1, B2-1): the CDC picks or creates the company, then fills the same JNF/INF
// wizard the companies use. The form is accepted on submit and opened from its detail page.
function AddNewJob() {
  const router = useRouter();
  const params = useSearchParams();
  const cycleParam = params.get("cycle") ?? "";

  const [cycle, setCycle] = useState(null);
  const [formType, setFormType] = useState("jnf");
  const [company, setCompany] = useState(null);
  const [search, setSearch] = useState("");
  const [options, setOptions] = useState([]);
  const [searching, setSearching] = useState(false);
  const [adding, setAdding] = useState(false);
  const [draft, setDraft] = useState(emptyCompany);
  const [savingCompany, setSavingCompany] = useState(false);
  const [error, setError] = useState(null);
  const [started, setStarted] = useState(false);

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

  const createCompany = async () => {
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
      setAdding(false);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not add the company.");
    } finally {
      setSavingCompany(false);
    }
  };

  const detailPath = (id) => `/admin/${formType}s/${id}${cycleParam ? `?cycle=${cycleParam}` : ""}`;
  const backHref = cycleParam ? `/admin/placement-cycles/${cycleParam}` : "/admin/postings";
  const canAddCompany = draft.name.trim() && draft.hr_name.trim() && draft.hr_email.trim();

  return (
    <>
      <PageHeader
        icon={<WorkIcon />}
        title={cycle ? `${cycle.name} / New Job Profile` : "New Job Profile"}
        subtitle={
          started && company
            ? `${company.name} · ${formType === "inf" ? "INF (internship)" : "JNF (full-time)"}. The form is accepted when you submit it; open it for applications next.`
            : "Pick the company, choose JNF or INF, then fill the form on the company's behalf."
        }
        backHref={backHref}
        backLabel="Back"
      />

      {error && (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>
          {error}
        </Alert>
      )}

      {!started && (
        <Card>
          <CardContent>
            <Stack spacing={3}>
              <Box>
                <Typography variant="subtitle1" fontWeight={600} gutterBottom>
                  Company*
                </Typography>
                {!adding && (
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
                    <Typography variant="body2" color="text.secondary" sx={{ pt: { sm: 2 } }}>
                      Not listed?{" "}
                      <MuiLink component="button" type="button" onClick={() => setAdding(true)} sx={{ verticalAlign: "baseline" }}>
                        + Add a new company
                      </MuiLink>
                    </Typography>
                  </Stack>
                )}

                {adding && (
                  <Box sx={{ p: 2, border: 1, borderColor: "divider", borderRadius: 2 }}>
                    <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
                      Adds a company record without a portal login.
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
                    <Stack direction="row" spacing={1} justifyContent="flex-end" sx={{ mt: 2 }}>
                      <Button onClick={() => setAdding(false)} disabled={savingCompany}>
                        Cancel
                      </Button>
                      <Button variant="contained" startIcon={<AddIcon />} onClick={createCompany} disabled={savingCompany || !canAddCompany}>
                        Add company
                      </Button>
                    </Stack>
                  </Box>
                )}

                {company && !adding && (
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

              <Box sx={{ display: "flex", justifyContent: "flex-end" }}>
                <Button variant="contained" endIcon={<ArrowForwardIcon />} disabled={!company || adding} onClick={() => setStarted(true)}>
                  Continue to the form
                </Button>
              </Box>
            </Stack>
          </CardContent>
        </Card>
      )}

      {started && company && (
        <Box sx={{ maxWidth: 1200, mx: "auto" }}>
          {formType === "inf" ? (
            <InfFormPro
              key={`inf-${company.id}`}
              api={adminApi}
              apiPrefix={`/admin/form-builder/${company.id}`}
              onSaved={(id) => router.push(detailPath(id))}
              onCancel={() => setStarted(false)}
            />
          ) : (
            <JnfFormPro
              key={`jnf-${company.id}`}
              api={adminApi}
              apiPrefix={`/admin/form-builder/${company.id}`}
              onSaved={(id) => router.push(detailPath(id))}
              onCancel={() => setStarted(false)}
            />
          )}
        </Box>
      )}
    </>
  );
}
