"use client";

import { useEffect, useState } from "react";
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  CircularProgress,
  Divider,
  Grid2,
  Stack,
  TextField,
  Typography,
  Avatar,
  IconButton,
} from "@mui/material";
import PhotoCameraIcon from "@mui/icons-material/PhotoCamera";
import BusinessIcon from "@mui/icons-material/Business";
import { companyApi, companyLogoUpload } from "@/lib/companyapi";
import PhoneInputPro from "@/components/forms/shared/phoneinputpro";
import RichTextEditor from "@/components/forms/shared/richtexteditor";
import SectorAutocomplete from "@/components/forms/shared/sectorautocomplete";
import { isValidPhoneNumber } from "libphonenumber-js";

type ContactBlock = {
  name?: string | null;
  designation?: string | null;
  email?: string | null;
  mobile?: string | null;
  landline?: string | null;
};

type CompanyProfileResponse = {
  name: string;
  industry: string | null;
  sector: string | null;
  website: string | null;
  postal_address: string | null;
  employee_count: number | null;
  hr_name: string;
  hr_designation: string | null;
  hr_email: string;
  hr_phone: string | null;
  hr_alt_phone: string | null;
  head_talent_contact: ContactBlock | null;
  primary_contact: ContactBlock | null;
  secondary_contact: ContactBlock | null;
  category_org_type: string | null;
  date_of_establishment: string | null;
  annual_turnover: string | null;
  linkedin_url: string | null;
  industry_sector_tags: string[] | null;
  mnc_hq_country_city: string | null;
  nature_of_business: string | null;
  company_description: string | null;
  logo_url?: string | null;
};

type CompanyProfileForm = {
  name: string;
  sector: string;
  industry: string;
  website: string;
  postal_address: string;
  employee_count: string;
  hr_name: string;
  hr_designation: string;
  hr_email: string;
  hr_phone: string;
  hr_alt_phone: string;
  head_name: string;
  head_designation: string;
  head_email: string;
  head_mobile: string;
  head_landline: string;
  poc1_name: string;
  poc1_designation: string;
  poc1_email: string;
  poc1_mobile: string;
  poc1_landline: string;
  poc2_name: string;
  poc2_designation: string;
  poc2_email: string;
  poc2_mobile: string;
  poc2_landline: string;
  category_org_type: string;
  date_of_establishment: string;
  annual_turnover: string;
  linkedin_url: string;
  industry_sector_tags: string;
  mnc_hq_country_city: string;
  nature_of_business: string;
  company_description: string;
};

function contactValue(value?: string | null): string {
  return value ?? "";
}

function calculateProfileCompletion(form: CompanyProfileForm): number {
  // Secondary Contact (PoC 2) and landline fields are intentionally excluded.
  const fieldsForCompletion = [
    form.name,
    form.sector,
    form.website,
    form.employee_count,
    form.postal_address,
    form.category_org_type,
    form.date_of_establishment,
    form.annual_turnover,
    form.linkedin_url,
    form.industry_sector_tags,
    form.mnc_hq_country_city,
    form.nature_of_business,
    form.company_description,
    form.head_name,
    form.head_designation,
    form.head_email,
    form.head_mobile,
    form.poc1_name,
    form.poc1_designation,
    form.poc1_email,
    form.poc1_mobile,
  ];

  const filledFields = fieldsForCompletion.filter(
    (field) => field && field.toString().trim() !== ""
  ).length;

  return Math.round((filledFields / fieldsForCompletion.length) * 100);
}

export default function CompanyProfilePage() {
  const [form, setForm] = useState<CompanyProfileForm>({
    name: "",
    sector: "",
    industry: "",
    website: "",
    postal_address: "",
    employee_count: "",
    hr_name: "",
    hr_designation: "",
    hr_email: "",
    hr_phone: "",
    hr_alt_phone: "",
    head_name: "",
    head_designation: "",
    head_email: "",
    head_mobile: "",
    head_landline: "",
    poc1_name: "",
    poc1_designation: "",
    poc1_email: "",
    poc1_mobile: "",
    poc1_landline: "",
    poc2_name: "",
    poc2_designation: "",
    poc2_email: "",
    poc2_mobile: "",
    poc2_landline: "",
    category_org_type: "",
    date_of_establishment: "",
    annual_turnover: "",
    linkedin_url: "",
    industry_sector_tags: "",
    mnc_hq_country_city: "",
    nature_of_business: "",
    company_description: "",
  });

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [logoUrl, setLogoUrl] = useState<string | null>(null);
  const [uploadingLogo, setUploadingLogo] = useState(false);
  const completionPercentage = calculateProfileCompletion(form);

  useEffect(() => {
    const run = async () => {
      try {
        const response = await companyApi<{ company: CompanyProfileResponse }>(
          "/company/profile",
        );

        const head = response.company.head_talent_contact ?? {};
        const poc1 = response.company.primary_contact ?? {};
        const poc2 = response.company.secondary_contact ?? {};

        setLogoUrl(response.company.logo_url ?? null);

        setForm({
          name: response.company.name,
          sector: response.company.sector ?? "",
          industry: response.company.industry ?? response.company.sector ?? "",
          website: response.company.website ?? "",
          postal_address: response.company.postal_address ?? "",
          employee_count:
            response.company.employee_count !== null
              ? String(response.company.employee_count)
              : "",
          hr_name: response.company.hr_name,
          hr_designation: response.company.hr_designation ?? "",
          hr_email: response.company.hr_email,
          hr_phone: response.company.hr_phone ?? "",
          hr_alt_phone: response.company.hr_alt_phone ?? "",
          head_name: contactValue(head.name),
          head_designation: contactValue(head.designation),
          head_email: contactValue(head.email),
          head_mobile: contactValue(head.mobile),
          head_landline: contactValue(head.landline),
          poc1_name: contactValue(poc1.name),
          poc1_designation: contactValue(poc1.designation),
          poc1_email: contactValue(poc1.email),
          poc1_mobile: contactValue(poc1.mobile),
          poc1_landline: contactValue(poc1.landline),
          poc2_name: contactValue(poc2.name),
          poc2_designation: contactValue(poc2.designation),
          poc2_email: contactValue(poc2.email),
          poc2_mobile: contactValue(poc2.mobile),
          poc2_landline: contactValue(poc2.landline),
          category_org_type: response.company.category_org_type ?? "",
          date_of_establishment: response.company.date_of_establishment
            ? response.company.date_of_establishment.split("T")[0]
            : "",
          annual_turnover: response.company.annual_turnover ?? "",
          linkedin_url: response.company.linkedin_url ?? "",
          industry_sector_tags: response.company.industry_sector_tags?.join(", ") ?? "",
          mnc_hq_country_city: response.company.mnc_hq_country_city ?? "",
          nature_of_business: response.company.nature_of_business ?? "",
          company_description: response.company.company_description ?? "",
        });
      } catch (e) {
        setError(e instanceof Error ? e.message : "Failed to load profile.");
      } finally {
        setLoading(false);
      }
    };

    void run();
  }, []);

  const onSubmit = async () => {
    setSaving(true);
    setError(null);
    setSuccess(null);

    // Validate phone numbers
    if (!isValidPhoneNumber(form.head_mobile)) {
      setError("Please ensure Head Talent Acquisition mobile number is valid for the selected country.");
      setSaving(false);
      return;
    }
    if (!isValidPhoneNumber(form.poc1_mobile)) {
      setError("Please ensure Primary Contact (PoC 1) mobile number is valid for the selected country.");
      setSaving(false);
      return;
    }
    if (form.poc2_mobile && !isValidPhoneNumber(form.poc2_mobile)) {
      setError("Please ensure Secondary Contact (PoC 2) mobile number is valid for the selected country.");
      setSaving(false);
      return;
    }

    try {
      // Auto-prepend https:// if no protocol is provided
      let website = form.website.trim();
      if (website && !/^https?:\/\//i.test(website)) {
        website = `https://${website}`;
      }

      await companyApi<{ message: string }>("/company/profile", {
        method: "PUT",
        body: JSON.stringify({
          name: form.name,
          sector: form.sector,
          industry: form.industry,
          website: website,
          postal_address: form.postal_address,
          employee_count: form.employee_count ? Number(form.employee_count) : null,
          hr_name: form.hr_name,
          hr_designation: form.hr_designation,
          hr_email: form.hr_email,
          hr_phone: form.hr_phone,
          hr_alt_phone: form.hr_alt_phone,
          head_name: form.head_name,
          head_designation: form.head_designation,
          head_email: form.head_email,
          head_mobile: form.head_mobile,
          head_landline: form.head_landline,
          poc1_name: form.poc1_name,
          poc1_designation: form.poc1_designation,
          poc1_email: form.poc1_email,
          poc1_mobile: form.poc1_mobile,
          poc1_landline: form.poc1_landline,
          poc2_name: form.poc2_name,
          poc2_designation: form.poc2_designation,
          poc2_email: form.poc2_email,
          poc2_mobile: form.poc2_mobile,
          poc2_landline: form.poc2_landline,
          category_org_type: form.category_org_type || null,
          date_of_establishment: form.date_of_establishment || null,
          annual_turnover: form.annual_turnover || null,
          linkedin_url: form.linkedin_url || null,
          industry_sector_tags: form.industry_sector_tags ? form.industry_sector_tags.split(",").map(tag => tag.trim()) : null,
          mnc_hq_country_city: form.mnc_hq_country_city || null,
          nature_of_business: form.nature_of_business || null,
          company_description: form.company_description || null,
        }),
      });
      setSuccess("Profile updated successfully.");
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to update profile.");
    } finally {
      setSaving(false);
    }
  };

  const handleLogoUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setError(null);
    setSuccess(null);

    // Client-side validation: file size limit of 2MB (2 * 1024 * 1024 bytes)
    const MAX_SIZE_BYTES = 2 * 1024 * 1024;
    if (file.size > MAX_SIZE_BYTES) {
      setError("Logo upload failed: The file size exceeds the 2MB limit.");
      e.target.value = "";
      return;
    }

    // Client-side validation: file extension (jpg, jpeg, png, webp; no svg, SEC-004)
    const allowedExtensions = ["jpg", "jpeg", "png", "webp"];
    const fileExtension = file.name.split(".").pop()?.toLowerCase();
    if (!fileExtension || !allowedExtensions.includes(fileExtension)) {
      setError("Logo upload failed: Invalid file format. Only JPG, JPEG, PNG and WEBP are allowed.");
      e.target.value = "";
      return;
    }

    setUploadingLogo(true);
    try {
      const response = await companyLogoUpload(file);
      setLogoUrl(response.company.logo_url ?? null);
      setSuccess("Logo updated successfully.");
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to upload logo.");
    } finally {
      setUploadingLogo(false);
    }
  };

  return (
    <Stack spacing={3} sx={{ pb: 4 }}>
      <Box sx={{ mb: 1 }}>
        <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mb: 1 }}>
          <Stack direction="row" spacing={3} alignItems="center">
            <Box sx={{ position: "relative" }}>
              <Avatar
                src={logoUrl || undefined}
                sx={{ width: 80, height: 80, bgcolor: "white", color: "primary.main", border: "2px solid", borderColor: "primary.main" }}
              >
                {!logoUrl && <BusinessIcon fontSize="large" />}
              </Avatar>
              <input
                accept=".jpg,.jpeg,.png,.webp"
                style={{ display: "none" }}
                id="logo-upload-button"
                type="file"
                onChange={handleLogoUpload}
                disabled={uploadingLogo}
              />
              <label htmlFor="logo-upload-button">
                <IconButton
                  color="primary"
                  aria-label="upload picture"
                  component="span"
                  disabled={uploadingLogo}
                  sx={{
                    position: "absolute",
                    bottom: -8,
                    right: -8,
                    bgcolor: "background.paper",
                    border: "1px solid",
                    borderColor: "divider",
                    "&:hover": { bgcolor: "background.default" }
                  }}
                >
                  {uploadingLogo ? <CircularProgress size={20} /> : <PhotoCameraIcon fontSize="small" />}
                </IconButton>
              </label>
            </Box>

            <Box>
              <Typography variant="h3" sx={{ fontWeight: 700, color: "primary.main", mb: 0.5 }}>
                Company Profile
              </Typography>
              <Typography variant="body2" sx={{ color: "text.secondary" }}>
                Manage and update your company information
              </Typography>
            </Box>
          </Stack>

          <Box sx={{ textAlign: "center" }}>
            <Box sx={{ position: "relative", width: 60, height: 60, ml: "auto" }}>
              <CircularProgress
                variant="determinate"
                value={completionPercentage}
                size={60}
                thickness={4}
                sx={{
                  color: completionPercentage === 100 ? "success.main" : "primary.main",
                }}
              />
              <Box
                sx={{
                  position: "absolute",
                  top: "50%",
                  left: "50%",
                  transform: "translate(-50%, -50%)",
                }}
              >
                <Typography variant="caption" sx={{ fontWeight: 700, display: "block", textAlign: "center" }}>
                  {completionPercentage}%
                </Typography>
              </Box>
            </Box>
            <Typography variant="caption" sx={{ color: "text.secondary", display: "block", mt: 0.5 }}>
              Profile complete
            </Typography>
          </Box>
        </Stack>
      </Box>

      {error && <Alert severity="error" sx={{ borderRadius: 2 }}>{error}</Alert>}
      {success && <Alert severity="success" sx={{ borderRadius: 2 }}>{success}</Alert>}

      <Card sx={{ boxShadow: 2, borderRadius: 2, border: "1px solid", borderColor: "divider" }}>
        <CardContent>
          <Box sx={{ bgcolor: "primary.dark", color: "primary.contrastText", px: 2, py: 1.5, borderRadius: 1, mb: 2.5, ml: -2, mr: -2, mt: -2 }}>
            <Typography fontWeight={700} variant="subtitle1">Company Details</Typography>
          </Box>
          <Grid2 container spacing={2.5}>
            <Grid2 size={{ xs: 12, md: 6 }}>
              <TextField fullWidth label="Company Name *" value={form.name} onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))} disabled={loading} variant="outlined" />
            </Grid2>
            <Grid2 size={{ xs: 12, md: 6 }}>
              <SectorAutocomplete value={form.sector} onChange={(val) => setForm((p) => ({ ...p, sector: val, industry: val }))} disabled={loading} label="Sector *" />
            </Grid2>
            <Grid2 size={{ xs: 12, md: 6 }}>
              <TextField fullWidth label="Website *" placeholder="www.company.com" value={form.website} onChange={(e) => setForm((p) => ({ ...p, website: e.target.value }))} disabled={loading} variant="outlined" helperText="http/https prefix is optional" />
            </Grid2>
            <Grid2 size={{ xs: 12, md: 6 }}>
              <TextField fullWidth label="Number of Employees" placeholder="e.g., 500" value={form.employee_count} onChange={(e) => setForm((p) => ({ ...p, employee_count: e.target.value }))} disabled={loading} variant="outlined" />
            </Grid2>
            <Grid2 size={{ xs: 12 }}>
              <TextField fullWidth label="Postal Address *" multiline minRows={3} value={form.postal_address} onChange={(e) => setForm((p) => ({ ...p, postal_address: e.target.value }))} disabled={loading} variant="outlined" />
            </Grid2>
          </Grid2>
        </CardContent>
      </Card>

      <Card sx={{ boxShadow: 2, borderRadius: 2, border: "1px solid", borderColor: "divider" }}>
        <CardContent>
          <Box sx={{ bgcolor: "secondary.dark", color: "secondary.contrastText", px: 2, py: 1.5, borderRadius: 1, mb: 2.5, ml: -2, mr: -2, mt: -2 }}>
            <Typography fontWeight={700} variant="subtitle1">Extended Profile</Typography>
          </Box>
          <Grid2 container spacing={2.5}>
            <Grid2 size={{ xs: 12, md: 6 }}>
              <TextField fullWidth label="Category / Organization Type" value={form.category_org_type} onChange={(e) => setForm((p) => ({ ...p, category_org_type: e.target.value }))} disabled={loading} variant="outlined" />
            </Grid2>
            <Grid2 size={{ xs: 12, md: 6 }}>
              <TextField fullWidth label="Date of Establishment" type="date" value={form.date_of_establishment} onChange={(e) => setForm((p) => ({ ...p, date_of_establishment: e.target.value }))} disabled={loading} InputLabelProps={{ shrink: true }} variant="outlined" inputProps={{ max: new Date().toISOString().split('T')[0] }} />
            </Grid2>
            <Grid2 size={{ xs: 12, md: 6 }}>
              <TextField fullWidth label="Annual Turnover (NIRF)" placeholder="e.g., $5M - $10M" value={form.annual_turnover} onChange={(e) => setForm((p) => ({ ...p, annual_turnover: e.target.value }))} disabled={loading} variant="outlined" />
            </Grid2>
            <Grid2 size={{ xs: 12, md: 6 }}>
              <TextField fullWidth label="LinkedIn URL" placeholder="https://linkedin.com/company/..." value={form.linkedin_url} onChange={(e) => setForm((p) => ({ ...p, linkedin_url: e.target.value }))} disabled={loading} variant="outlined" />
            </Grid2>
            <Grid2 size={{ xs: 12, md: 6 }}>
              <TextField fullWidth label="Industry Sector Tags" value={form.industry_sector_tags} onChange={(e) => setForm((p) => ({ ...p, industry_sector_tags: e.target.value }))} disabled={loading} placeholder="Technology, Finance, Healthcare" helperText="Separate with commas" variant="outlined" />
            </Grid2>
            <Grid2 size={{ xs: 12, md: 6 }}>
              <TextField fullWidth label="If MNC — HQ Country/City" value={form.mnc_hq_country_city} onChange={(e) => setForm((p) => ({ ...p, mnc_hq_country_city: e.target.value }))} disabled={loading} variant="outlined" />
            </Grid2>
            <Grid2 size={{ xs: 12, md: 6 }}>
              <TextField fullWidth label="Nature of Business" value={form.nature_of_business} onChange={(e) => setForm((p) => ({ ...p, nature_of_business: e.target.value }))} disabled={loading} variant="outlined" />
            </Grid2>
            <Grid2 size={{ xs: 12 }}>
              <RichTextEditor label="Company Description" value={form.company_description} onChange={(val) => setForm((p) => ({ ...p, company_description: val }))} disabled={loading} placeholder="Tell us about your company..." />
            </Grid2>
          </Grid2>
        </CardContent>
      </Card>

      <Card sx={{ boxShadow: 2, borderRadius: 2, border: "1px solid", borderColor: "divider" }}>
        <CardContent>
          <Box sx={{ bgcolor: "primary.dark", color: "primary.contrastText", px: 2, py: 1.5, borderRadius: 1, mb: 2.5, ml: -2, mr: -2, mt: -2 }}>
            <Typography fontWeight={700} variant="subtitle1">Head Talent Acquisition</Typography>
          </Box>
          <Grid2 container spacing={2.5}>
            <Grid2 size={{ xs: 12, md: 6 }}>
              <TextField fullWidth label="Full Name *" value={form.head_name} onChange={(e) => setForm((p) => ({ ...p, head_name: e.target.value }))} disabled={loading} variant="outlined" />
            </Grid2>
            <Grid2 size={{ xs: 12, md: 6 }}>
              <TextField fullWidth label="Designation" value={form.head_designation} onChange={(e) => setForm((p) => ({ ...p, head_designation: e.target.value }))} disabled={loading} variant="outlined" />
            </Grid2>
            <Grid2 size={{ xs: 12, md: 6 }}>
              <TextField fullWidth label="Email Address *" value={form.head_email} onChange={(e) => setForm((p) => ({ ...p, head_email: e.target.value }))} disabled={loading} variant="outlined" />
            </Grid2>
            <Grid2 size={{ xs: 12, md: 6 }}>
              <PhoneInputPro label="Mobile Number *" value={form.head_mobile} onChange={(val) => setForm((p) => ({ ...p, head_mobile: val }))} disabled={loading} />
            </Grid2>
            <Grid2 size={{ xs: 12, md: 6 }}>
              <PhoneInputPro label="Landline (Optional)" value={form.head_landline} onChange={(val) => setForm((p) => ({ ...p, head_landline: val }))} disabled={loading} />
            </Grid2>
          </Grid2>
        </CardContent>
      </Card>

      <Card sx={{ boxShadow: 2, borderRadius: 2, border: "1px solid", borderColor: "divider" }}>
        <CardContent>
          <Box sx={{ bgcolor: "secondary.dark", color: "secondary.contrastText", px: 2, py: 1.5, borderRadius: 1, mb: 2.5, ml: -2, mr: -2, mt: -2 }}>
            <Typography fontWeight={700} variant="subtitle1">Primary Contact (PoC 1)</Typography>
          </Box>
          <Grid2 container spacing={2.5}>
            <Grid2 size={{ xs: 12, md: 6 }}>
              <TextField fullWidth label="Full Name *" value={form.poc1_name} onChange={(e) => setForm((p) => ({ ...p, poc1_name: e.target.value, hr_name: e.target.value }))} disabled={loading} variant="outlined" />
            </Grid2>
            <Grid2 size={{ xs: 12, md: 6 }}>
              <TextField fullWidth label="Designation" value={form.poc1_designation} onChange={(e) => setForm((p) => ({ ...p, poc1_designation: e.target.value, hr_designation: e.target.value }))} disabled={loading} variant="outlined" />
            </Grid2>
            <Grid2 size={{ xs: 12, md: 6 }}>
              <TextField fullWidth label="Email Address *" value={form.poc1_email} onChange={(e) => setForm((p) => ({ ...p, poc1_email: e.target.value, hr_email: e.target.value }))} disabled={loading} variant="outlined" />
            </Grid2>
            <Grid2 size={{ xs: 12, md: 6 }}>
              <PhoneInputPro label="Mobile Number *" value={form.poc1_mobile} onChange={(val) => setForm((p) => ({ ...p, poc1_mobile: val, hr_phone: val }))} disabled={loading} />
            </Grid2>
            <Grid2 size={{ xs: 12, md: 6 }}>
              <PhoneInputPro label="Landline (Optional)" value={form.poc1_landline} onChange={(val) => setForm((p) => ({ ...p, poc1_landline: val, hr_alt_phone: val }))} disabled={loading} />
            </Grid2>
          </Grid2>
        </CardContent>
      </Card>

      <Card sx={{ boxShadow: 2, borderRadius: 2, border: "1px solid", borderColor: "divider" }}>
        <CardContent>
          <Box sx={{ bgcolor: "grey.800", color: "common.white", px: 2, py: 1.5, borderRadius: 1, mb: 2.5, ml: -2, mr: -2, mt: -2 }}>
            <Typography fontWeight={700} variant="subtitle1">Secondary Contact (PoC 2) — Optional</Typography>
          </Box>
          <Grid2 container spacing={2.5}>
            <Grid2 size={{ xs: 12, md: 6 }}>
              <TextField fullWidth label="Full Name" value={form.poc2_name} onChange={(e) => setForm((p) => ({ ...p, poc2_name: e.target.value }))} disabled={loading} variant="outlined" />
            </Grid2>
            <Grid2 size={{ xs: 12, md: 6 }}>
              <TextField fullWidth label="Designation" value={form.poc2_designation} onChange={(e) => setForm((p) => ({ ...p, poc2_designation: e.target.value }))} disabled={loading} variant="outlined" />
            </Grid2>
            <Grid2 size={{ xs: 12, md: 6 }}>
              <TextField fullWidth label="Email Address" value={form.poc2_email} onChange={(e) => setForm((p) => ({ ...p, poc2_email: e.target.value }))} disabled={loading} variant="outlined" />
            </Grid2>
            <Grid2 size={{ xs: 12, md: 6 }}>
              <PhoneInputPro label="Mobile Number" value={form.poc2_mobile} onChange={(val) => setForm((p) => ({ ...p, poc2_mobile: val }))} disabled={loading} />
            </Grid2>
            <Grid2 size={{ xs: 12, md: 6 }}>
              <PhoneInputPro label="Landline (Optional)" value={form.poc2_landline} onChange={(val) => setForm((p) => ({ ...p, poc2_landline: val }))} disabled={loading} />
            </Grid2>
          </Grid2>
        </CardContent>
      </Card>

      <Divider sx={{ my: 1 }} />

      <Button
        variant="contained"
        size="large"
        onClick={onSubmit}
        disabled={saving || loading}
        sx={{
          py: 1.5,
          fontWeight: 600,
          fontSize: "1rem",
          borderRadius: 1.5,
          textTransform: "none",
        }}
      >
        {saving ? "Saving..." : "Save Profile"}
      </Button>
    </Stack>
  );
}
