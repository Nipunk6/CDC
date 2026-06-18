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
  Divider,
  Grid2,
  Stack,
  Typography,
} from "@mui/material";
import BusinessIcon from "@mui/icons-material/Business";
import { adminApi } from "@/lib/adminapi";

type ContactBlock = {
  name?: string | null;
  designation?: string | null;
  email?: string | null;
  mobile?: string | null;
  landline?: string | null;
};

type Company = {
  id: number;
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

type CompanySummary = {
  jnf_total: number;
  jnf_pending: number;
  jnf_accepted: number;
  jnf_rejected: number;
  inf_total: number;
  inf_pending: number;
  inf_accepted: number;
  inf_rejected: number;
};

function profileValue(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === "") {
    return "-";
  }

  return String(value);
}

function ContactSummary({ title, contact }: { title: string; contact: ContactBlock | null | undefined }) {
  return (
    <Box>
      <Typography variant="subtitle2" color="text.secondary" sx={{ mb: 1 }}>
        {title}
      </Typography>
      <Grid2 container spacing={1.25}>
        <Grid2 size={{ xs: 12, md: 6 }}>
          <Typography variant="caption" color="text.secondary">Name</Typography>
          <Typography variant="body2">{profileValue(contact?.name ?? null)}</Typography>
        </Grid2>
        <Grid2 size={{ xs: 12, md: 6 }}>
          <Typography variant="caption" color="text.secondary">Designation</Typography>
          <Typography variant="body2">{profileValue(contact?.designation ?? null)}</Typography>
        </Grid2>
        <Grid2 size={{ xs: 12, md: 6 }}>
          <Typography variant="caption" color="text.secondary">Email</Typography>
          <Typography variant="body2">{profileValue(contact?.email ?? null)}</Typography>
        </Grid2>
        <Grid2 size={{ xs: 12, md: 3 }}>
          <Typography variant="caption" color="text.secondary">Mobile</Typography>
          <Typography variant="body2">{profileValue(contact?.mobile ?? null)}</Typography>
        </Grid2>
        <Grid2 size={{ xs: 12, md: 3 }}>
          <Typography variant="caption" color="text.secondary">Landline</Typography>
          <Typography variant="body2">{profileValue(contact?.landline ?? null)}</Typography>
        </Grid2>
      </Grid2>
    </Box>
  );
}

export default function AdminCompanyDetailPage() {
  const params = useParams<{ id: string }>();
  const router = useRouter();
  const [company, setCompany] = useState<Company | null>(null);
  const [summary, setSummary] = useState<CompanySummary | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const run = async () => {
      try {
        const response = await adminApi<{
          company: Company;
          summary: CompanySummary;
        }>(`/admin/companies/${params.id}`);
        setCompany(response.company);
        setSummary(response.summary);
      } catch (e) {
        setError(
          e instanceof Error ? e.message : "Failed to load company details.",
        );
      }
    };

    void run();
  }, [params.id]);

  return (
    <Stack spacing={2.5}>
      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Stack direction="row" spacing={2} alignItems="center">
          {company && (
            <Avatar 
              src={company.logo_url || undefined} 
              sx={{ width: 48, height: 48, bgcolor: "white", color: "primary.main" }}
            >
              {!company.logo_url && <BusinessIcon />}
            </Avatar>
          )}
          <Typography variant="h4" color="primary.main">
            {company ? company.name : "Company Details"}
          </Typography>
        </Stack>
        <Button
          variant="outlined"
          onClick={() => router.push("/admin/companies")}
        >
          Back to Companies
        </Button>
      </Stack>

      {error && <Alert severity="error">{error}</Alert>}

      {summary && (
        <Card>
          <CardContent>
            <Grid2 container spacing={2}>
              {[
                ["JNF Total", summary.jnf_total],
                ["JNF Pending", summary.jnf_pending],
                ["JNF Accepted", summary.jnf_accepted],
                ["JNF Rejected", summary.jnf_rejected],
                ["INF Total", summary.inf_total],
                ["INF Pending", summary.inf_pending],
                ["INF Accepted", summary.inf_accepted],
                ["INF Rejected", summary.inf_rejected],
              ].map(([label, value]) => (
                <Grid2 key={label} size={{ xs: 6, md: 3 }}>
                  <Typography variant="subtitle2" color="text.secondary">
                    {label}
                  </Typography>
                  <Typography variant="h6">{value}</Typography>
                </Grid2>
              ))}
            </Grid2>
          </CardContent>
        </Card>
      )}

      {company && (
        <Card>
          <CardContent>
            <Typography variant="h6" sx={{ mb: 2 }}>
              Basic Company Profile
            </Typography>

            <Grid2 container spacing={2}>
              <Grid2 size={{ xs: 12, md: 6 }}>
                <Typography variant="caption" color="text.secondary">Company Name</Typography>
                <Typography variant="body2">{profileValue(company.name)}</Typography>
              </Grid2>
              <Grid2 size={{ xs: 12, md: 6 }}>
                <Typography variant="caption" color="text.secondary">Sector</Typography>
                <Typography variant="body2">{profileValue(company.sector)}</Typography>
              </Grid2>

              <Grid2 size={{ xs: 12, md: 6 }}>
                <Typography variant="caption" color="text.secondary">Industry</Typography>
                <Typography variant="body2">{profileValue(company.industry)}</Typography>
              </Grid2>
              <Grid2 size={{ xs: 12, md: 6 }}>
                <Typography variant="caption" color="text.secondary">Website</Typography>
                <Typography variant="body2" sx={{ wordBreak: "break-word" }}>{profileValue(company.website)}</Typography>
              </Grid2>

              <Grid2 size={{ xs: 12, md: 6 }}>
                <Typography variant="caption" color="text.secondary">Employee Count</Typography>
                <Typography variant="body2">{profileValue(company.employee_count)}</Typography>
              </Grid2>
              <Grid2 size={{ xs: 12, md: 6 }}>
                <Typography variant="caption" color="text.secondary">HR Name</Typography>
                <Typography variant="body2">{profileValue(company.hr_name)}</Typography>
              </Grid2>

              <Grid2 size={{ xs: 12, md: 6 }}>
                <Typography variant="caption" color="text.secondary">HR Designation</Typography>
                <Typography variant="body2">{profileValue(company.hr_designation)}</Typography>
              </Grid2>
              <Grid2 size={{ xs: 12, md: 6 }}>
                <Typography variant="caption" color="text.secondary">HR Email</Typography>
                <Typography variant="body2">{profileValue(company.hr_email)}</Typography>
              </Grid2>
              <Grid2 size={{ xs: 12, md: 6 }}>
                <Typography variant="caption" color="text.secondary">HR Phone</Typography>
                <Typography variant="body2">{profileValue(company.hr_phone)}</Typography>
              </Grid2>
              <Grid2 size={{ xs: 12, md: 6 }}>
                <Typography variant="caption" color="text.secondary">HR Alternate Phone</Typography>
                <Typography variant="body2">{profileValue(company.hr_alt_phone)}</Typography>
              </Grid2>

              <Grid2 size={12}>
                <Typography variant="caption" color="text.secondary">Postal Address</Typography>
                <Typography variant="body2" sx={{ whiteSpace: "pre-wrap" }}>{profileValue(company.postal_address)}</Typography>
              </Grid2>
            </Grid2>
          </CardContent>
        </Card>
      )}

      {company && (
        <Card>
          <CardContent>
            <Typography variant="h6" sx={{ mb: 2 }}>
              Extended Company Profile
            </Typography>

            <Grid2 container spacing={2}>
              <Grid2 size={{ xs: 12, md: 4 }}>
                <Typography variant="caption" color="text.secondary">Category / Org Type</Typography>
                <Typography variant="body2">{profileValue(company.category_org_type)}</Typography>
              </Grid2>

              <Grid2 size={{ xs: 12, md: 4 }}>
                <Typography variant="caption" color="text.secondary">Date of Establishment</Typography>
                <Typography variant="body2">{profileValue(company.date_of_establishment)}</Typography>
              </Grid2>
              <Grid2 size={{ xs: 12, md: 4 }}>
                <Typography variant="caption" color="text.secondary">Annual Turnover</Typography>
                <Typography variant="body2">{profileValue(company.annual_turnover)}</Typography>
              </Grid2>

              <Grid2 size={{ xs: 12, md: 4 }}>
                <Typography variant="caption" color="text.secondary">MNC HQ (Country/City)</Typography>
                <Typography variant="body2">{profileValue(company.mnc_hq_country_city)}</Typography>
              </Grid2>

              <Grid2 size={{ xs: 12, md: 6 }}>
                <Typography variant="caption" color="text.secondary">LinkedIn URL</Typography>
                <Typography variant="body2" sx={{ wordBreak: "break-word" }}>{profileValue(company.linkedin_url)}</Typography>
              </Grid2>
              <Grid2 size={{ xs: 12, md: 6 }}>
                <Typography variant="caption" color="text.secondary">Industry Tags</Typography>
                <Typography variant="body2">{profileValue(company.industry_sector_tags?.join(", ") ?? null)}</Typography>
              </Grid2>

              <Grid2 size={12}>
                <Typography variant="caption" color="text.secondary">Nature of Business</Typography>
                <Typography variant="body2" sx={{ whiteSpace: "pre-wrap" }}>{profileValue(company.nature_of_business)}</Typography>
              </Grid2>

              <Grid2 size={12}>
                <Typography variant="caption" color="text.secondary">Company Description</Typography>
                <Typography variant="body2" sx={{ whiteSpace: "pre-wrap" }}>{profileValue(company.company_description)}</Typography>
              </Grid2>
            </Grid2>
          </CardContent>
        </Card>
      )}

      {company && (
        <Card>
          <CardContent>
            <Typography variant="h6" sx={{ mb: 2 }}>
              Contact Points
            </Typography>
            <Stack spacing={2}>
              <ContactSummary title="Head Talent Acquisition" contact={company.head_talent_contact} />
              <Divider />
              <ContactSummary title="Primary Contact (PoC 1)" contact={company.primary_contact} />
              <Divider />
              <ContactSummary title="Secondary Contact (PoC 2)" contact={company.secondary_contact} />
            </Stack>
          </CardContent>
        </Card>
      )}
    </Stack>
  );
}
