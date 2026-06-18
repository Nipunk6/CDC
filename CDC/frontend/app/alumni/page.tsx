"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  Checkbox,
  Container,
  FormControlLabel,
  Grid2,
  MenuItem,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import SchoolIcon from "@mui/icons-material/School";
import CheckCircleOutlineIcon from "@mui/icons-material/CheckCircleOutline";

const apiBase = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api";

type AlumniPayload = {
  full_name: string;
  email: string;
  phone: string;
  graduation_year: string;
  programme: string;
  department: string;
  current_organization: string;
  current_designation: string;
  city: string;
  country: string;
  linkedin_url: string;
  willing_to_mentor: boolean;
  willing_to_refer: boolean;
  message: string;
  general_comments: string;
};

import PhoneInputPro from "@/components/forms/shared/phoneinputpro";
import RichTextEditor from "@/components/forms/shared/richtexteditor";
import { isValidPhoneNumber } from "libphonenumber-js";

const degreeOptions = [
  "BE / BTech / BArch",
  "ME / MTech / MArch",
  "Integrated Dual Degree",
  "MSc",
  "PhD",
  "Other",
];

const initialForm: AlumniPayload = {
  full_name: "",
  email: "",
  phone: "",
  graduation_year: "",
  programme: "",
  department: "",
  current_organization: "",
  current_designation: "",
  city: "",
  country: "",
  linkedin_url: "",
  willing_to_mentor: false,
  willing_to_refer: false,
  message: "",
  general_comments: "",
};

export default function AlumniPage() {
  const [form, setForm] = useState<AlumniPayload>(initialForm);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [linkedinError, setLinkedinError] = useState<string | null>(null);

  const currentYear = useMemo(() => new Date().getFullYear(), []);

  // Clear stale state on every mount (guards against Next.js router cache restoring
  // a previous success/error state when the user navigates back to this page)
  useEffect(() => {
    setSuccess(null);
    setError(null);
    setLinkedinError(null);
  }, []);

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);
    setSuccess(null);
    setSubmitting(true);

    // Rich-text fields output HTML even when visually empty — strip tags before checking
    const stripHtml = (html: string) => html.replace(/<[^>]*>/g, "").trim();
    if (!stripHtml(form.message)) {
      setError("Areas of Interest is required.");
      setSubmitting(false);
      return;
    }

    if (!isValidPhoneNumber(form.phone)) {
      setError("Please enter a valid phone number for the selected country.");
      setSubmitting(false);
      return;
    }

    // LinkedIn validation: 'NA' (case-insensitive) is allowed; otherwise must be a valid URL
    const linkedinVal = form.linkedin_url.trim();
    if (linkedinVal.toLowerCase() !== "na" && linkedinVal !== "") {
      try {
        const parsed = new URL(linkedinVal);
        if (parsed.protocol !== "http:" && parsed.protocol !== "https:") {
          throw new Error("Invalid protocol");
        }
      } catch {
        setError("LinkedIn URL must be a valid URL (e.g. https://linkedin.com/in/yourname) or \"NA\".");
        setSubmitting(false);
        return;
      }
    }

    try {
      const splitIndex = form.phone.indexOf(" ");
      const country_code = splitIndex > -1 ? form.phone.substring(0, splitIndex) : form.phone;
      const phone_number = splitIndex > -1 ? form.phone.substring(splitIndex + 1) : "";

      const payload = {
        ...form,
        country_code,
        phone_number,
        phone: form.phone,
        graduation_year: form.graduation_year ? Number(form.graduation_year) : null,
      };

      const response = await fetch(`${apiBase}/alumni-outreach`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify(payload),
      });

      const body = (await response.json().catch(() => ({}))) as { message?: string };

      if (!response.ok) {
        throw new Error(body.message ?? "Failed to submit outreach form.");
      }

      setSuccess(body.message ?? "Your response has been recorded.");
      setForm(initialForm);
    } catch (submitError) {
      setError(submitError instanceof Error ? submitError.message : "Failed to submit outreach form.");
    } finally {
      setSubmitting(false);
    }
  };

  const setField = <K extends keyof AlumniPayload>(key: K, value: AlumniPayload[K]) => {
    setForm((current) => ({ ...current, [key]: value }));
  };

  return (
    <Box sx={{ minHeight: "100vh", display: "flex", flexDirection: "column", bgcolor: "background.default" }}>
      <Container maxWidth="md" sx={{ py: 6, flex: 1 }}>
        <Stack spacing={3}>
          <Stack spacing={1}>
            <Button component={Link} href="/" sx={{ alignSelf: "flex-start", px: 0 }}>
              Back to Home
            </Button>
            <Stack direction="row" spacing={1.5} alignItems="center">
              <SchoolIcon color="primary" />
              <Typography variant="h4" fontWeight={700}>
                IIT (ISM) Alumni Outreach Form
              </Typography>
            </Stack>
            <Typography color="text.secondary">
              This form is for IIT (ISM) alumni interested in mentoring and outreach.
            </Typography>
          </Stack>

          {/* ── Success state: replace the form entirely ── */}
          {success ? (
            <Card>
              <CardContent>
                <Stack spacing={3} alignItems="center" sx={{ py: 4, textAlign: "center" }}>
                  <CheckCircleOutlineIcon sx={{ fontSize: 72, color: "success.main" }} />
                  <Typography variant="h5" fontWeight={700}>
                    Submission Received!
                  </Typography>
                  <Typography color="text.secondary" sx={{ maxWidth: 480 }}>
                    {success}
                  </Typography>
                  <Typography variant="body2" color="text.secondary">
                    A confirmation email has been sent to your registered email address. Our CDC team will be in touch soon.
                  </Typography>
                  <Button component={Link} href="/" variant="contained" size="large">
                    Back to Home
                  </Button>
                </Stack>
              </CardContent>
            </Card>
          ) : (
            <Card>
              <CardContent>
              <Box component="form" onSubmit={handleSubmit}>
                <Grid2 container spacing={2}>
                  <Grid2 size={{ xs: 12, md: 6 }}>
                    <TextField
                      label="Name"
                      fullWidth
                      required
                      value={form.full_name ?? ""}
                      onChange={(event) => setField("full_name", event.target.value)}
                    />
                  </Grid2>
                  <Grid2 size={{ xs: 12, md: 6 }}>
                    <TextField
                      label="Email"
                      type="email"
                      fullWidth
                      required
                      value={form.email ?? ""}
                      onChange={(event) => setField("email", event.target.value)}
                    />
                  </Grid2>
                  <Grid2 size={{ xs: 12, md: 6 }}>
                    <PhoneInputPro
                      label="Mobile Number"
                      required
                      value={form.phone ?? ""}
                      onChange={(val) => setField("phone", val)}
                    />
                  </Grid2>
                  <Grid2 size={{ xs: 12, md: 6 }}>
                    <TextField
                      label="Graduation Year"
                      type="number"
                      inputProps={{ min: 1950, max: currentYear + 5 }}
                      fullWidth
                      required
                      value={form.graduation_year ?? ""}
                      onChange={(event) => setField("graduation_year", event.target.value)}
                    />
                  </Grid2>
                  <Grid2 size={{ xs: 12, md: 6 }}>
                    <TextField
                      label="Degree at IIT (ISM)"
                      select
                      fullWidth
                      required
                      value={form.programme ?? ""}
                      onChange={(event) => setField("programme", event.target.value)}
                    >
                      {degreeOptions.map((option) => (
                        <MenuItem key={option} value={option}>{option}</MenuItem>
                      ))}
                    </TextField>
                  </Grid2>
                  <Grid2 size={{ xs: 12, md: 6 }}>
                    <TextField
                      label="Branch"
                      fullWidth
                      required
                      value={form.department ?? ""}
                      onChange={(event) => setField("department", event.target.value)}
                    />
                  </Grid2>
                  <Grid2 size={{ xs: 12, md: 6 }}>
                    <TextField
                      label="Current Organization"
                      fullWidth
                      required
                      value={form.current_organization ?? ""}
                      onChange={(event) => setField("current_organization", event.target.value)}
                      helperText="If not applicable, write NA"
                    />
                  </Grid2>
                  <Grid2 size={{ xs: 12, md: 6 }}>
                    <TextField
                      label="Current Job / Designation"
                      fullWidth
                      required
                      value={form.current_designation ?? ""}
                      onChange={(event) => setField("current_designation", event.target.value)}
                      helperText="If not applicable, write NA"
                    />
                  </Grid2>
                  <Grid2 size={{ xs: 12, md: 6 }}>
                    <TextField
                      label="City"
                      fullWidth
                      required
                      value={form.city ?? ""}
                      onChange={(event) => setField("city", event.target.value)}
                    />
                  </Grid2>
                  <Grid2 size={{ xs: 12, md: 6 }}>
                    <TextField
                      label="Country"
                      fullWidth
                      required
                      value={form.country ?? ""}
                      onChange={(event) => setField("country", event.target.value)}
                    />
                  </Grid2>
                  <Grid2 size={{ xs: 12 }}>
                    <TextField
                      label="LinkedIn URL"
                      fullWidth
                      value={form.linkedin_url ?? ""}
                      error={Boolean(linkedinError)}
                      helperText={linkedinError ?? "Enter your LinkedIn profile URL, or type \"NA\" if not applicable"}
                      onChange={(event) => {
                        const val = event.target.value;
                        setField("linkedin_url", val);
                        if (val.trim() === "" || val.trim().toLowerCase() === "na") {
                          setLinkedinError(null);
                        } else {
                          try {
                            const parsed = new URL(val.trim());
                            if (parsed.protocol !== "http:" && parsed.protocol !== "https:") {
                              throw new Error();
                            }
                            setLinkedinError(null);
                          } catch {
                            setLinkedinError('Must be a valid URL (e.g. https://linkedin.com/in/yourname) or "NA"');
                          }
                        }
                      }}
                    />
                  </Grid2>
                  <Grid2 size={{ xs: 12 }}>
                    <RichTextEditor
                      label="Areas of Interest"
                      required
                      value={form.message ?? ""}
                      onChange={(val) => setField("message", val)}
                      placeholder="Tell us about your areas of interest, expertise, or how you'd like to contribute..."
                    />
                  </Grid2>
                  <Grid2 size={{ xs: 12 }}>
                    <RichTextEditor
                      label="General Comments"
                      value={form.general_comments ?? ""}
                      onChange={(val) => setField("general_comments", val)}
                      placeholder="Any additional comments or information you'd like to share..."
                    />
                  </Grid2>
                  <Grid2 size={{ xs: 12 }}>
                    <Stack direction={{ xs: "column", md: "row" }} spacing={1}>
                      <FormControlLabel
                        control={
                          <Checkbox
                            checked={Boolean(form.willing_to_mentor)}
                            onChange={(event) => setField("willing_to_mentor", event.target.checked)}
                          />
                        }
                        label="I am willing to mentor students"
                      />
                      <FormControlLabel
                        control={
                          <Checkbox
                            checked={Boolean(form.willing_to_refer)}
                            onChange={(event) => setField("willing_to_refer", event.target.checked)}
                          />
                        }
                        label="I am willing to support referrals"
                      />
                    </Stack>
                  </Grid2>
                </Grid2>

                {error && <Alert severity="error" sx={{ mt: 2 }}>{error}</Alert>}

                <Stack direction="row" spacing={2} sx={{ mt: 3 }}>
                  <Button type="submit" variant="contained" disabled={submitting}>
                    {submitting ? "Submitting..." : "Submit Alumni Form"}
                  </Button>
                  <Button component={Link} href="/" variant="outlined">
                    Cancel
                  </Button>
                </Stack>
              </Box>
            </CardContent>
            </Card>
          )} {/* end success ? ... : form */}
        </Stack>
      </Container>

      <Box
        component="footer"
        sx={{
          py: 2,
          bgcolor: "#1a1a2e",
          color: "grey.500",
          borderTop: "1px solid rgba(255,255,255,0.1)",
          mt: "auto"
        }}
      >
        <Container maxWidth="xl">
          <Typography
            variant="body2"
            textAlign="center"
            sx={{
              fontSize: "0.7rem",
              letterSpacing: "0.05em",
              textTransform: "uppercase",
              fontWeight: 500,
            }}
          >
            Copyright © {new Date().getFullYear()} All Rights Reserved || Designed & Developed by - The Batch of Mathematics and Computing BTech-2028
          </Typography>
          <Typography
            variant="body2"
            textAlign="center"
            sx={{
              fontSize: "0.6rem",
              color: "#1a1a2e", // Match background color to hide
              userSelect: "text",
              mt: 0.5,
              cursor: "default"
            }}
          >
            Special contribution- Saurabh Pathak, Rohit Garg, Nipun Kansal, Nehmeet Patel
          </Typography>
        </Container>
      </Box>
    </Box>
  );
}
