"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Box,
  Card,
  CardContent,
  Chip,
  Divider,
  Grid2 as Grid,
  LinearProgress,
  Link as MuiLink,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from "@mui/material";

import StatTile from "@/components/shared/stattile";
import { adminApi } from "@/lib/adminapi";
import { formatDate, formatDateTime, formatMoney, postingStatusLabel, statusColor } from "@/lib/format";

const CONTACT_LABELS = {
  hr_name: "HR name",
  hr_designation: "HR designation",
  hr_email: "HR email",
  hr_phone: "HR phone",
  hr_alt_phone: "HR alternate phone",
  head_talent_contact: "Head of Talent",
  primary_contact: "Primary contact",
  secondary_contact: "Secondary contact",
};

const showValue = (value) => {
  if (value === null || value === undefined || value === "") return "—";
  if (typeof value === "object") {
    const parts = [value.name, value.designation, value.email, value.mobile ?? value.phone].filter(Boolean);
    return parts.length ? parts.join(" · ") : "—";
  }
  return String(value);
};

const compensation = (c, type) => {
  if (!c) return "—";
  if (type === "internship" || c.stipend_monthly) {
    return c.stipend_monthly ? `${formatMoney(c.stipend_monthly, c.currency)} / month${c.ppo ? " · PPO" : ""}` : "—";
  }
  return c.ctc_annual ? `${formatMoney(c.ctc_annual, c.currency)} / year` : "—";
};

/**
 * Admin → company page → "History & Contacts" (Company History, P3-D55): academic year → placement → job profile, the
 * HR contact saved when each job profile was opened, the current contacts, and the log of contact changes.
 */
export default function CompanyHistoryPanel({ companyId }) {
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    let cancelled = false;
    adminApi(`/admin/companies/${companyId}/history`)
      .then((r) => !cancelled && setData(r))
      .catch((e) => !cancelled && setError(e instanceof Error ? e.message : "Could not load the history."));
    return () => {
      cancelled = true;
    };
  }, [companyId]);

  if (error) return <Alert severity="error">{error}</Alert>;
  if (!data) return <LinearProgress />;

  const { summary, history, contacts } = data;

  return (
    <Stack spacing={2.5}>
      <Grid container spacing={2}>
        <Grid size={{ xs: 6, md: 3 }}>
          <StatTile label="Years came" value={summary.years.length} />
        </Grid>
        <Grid size={{ xs: 6, md: 3 }}>
          <StatTile label="Job profiles opened" value={summary.job_profiles} />
        </Grid>
        <Grid size={{ xs: 6, md: 3 }}>
          <StatTile label="Applicants" value={summary.applicants} />
        </Grid>
        <Grid size={{ xs: 6, md: 3 }}>
          <StatTile label="Offers" value={summary.offers} />
        </Grid>
      </Grid>

      <Card>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            Recruitment history
          </Typography>
          {history.length === 0 && (
            <Typography color="text.secondary">This company has not had a job profile opened for students yet.</Typography>
          )}
          <Stack spacing={3}>
            {history.map((year) => (
              <Box key={year.year}>
                <Stack direction="row" spacing={1} alignItems="center" sx={{ mb: 1 }}>
                  <Typography variant="subtitle1" fontWeight={700}>
                    {year.year}
                  </Typography>
                  {!year.counted && <Chip size="small" variant="outlined" label="Only cancelled job profiles" />}
                </Stack>
                <Stack spacing={2}>
                  {year.placements.map((group) => (
                    <Box key={group.placement_cycle?.id}>
                      <Stack direction="row" spacing={1} alignItems="center" sx={{ mb: 1 }}>
                        <Typography variant="subtitle2" fontWeight={700}>
                          {group.placement_cycle?.name}
                        </Typography>
                        <Chip size="small" variant="outlined" color={group.placement_cycle?.type === "internship" ? "secondary" : "primary"} label={group.placement_cycle?.type === "internship" ? "Internship" : "Full Time"} />
                      </Stack>
                      <TableContainer sx={{ border: 1, borderColor: "divider", borderRadius: 1 }}>
                        <Table size="small">
                          <TableHead>
                            <TableRow>
                              <TableCell>Job profile</TableCell>
                              <TableCell>Status</TableCell>
                              <TableCell sx={{ display: { xs: "none", md: "table-cell" } }}>Opened</TableCell>
                              <TableCell>Package</TableCell>
                              <TableCell align="right">Applicants</TableCell>
                              <TableCell align="right">Offers</TableCell>
                              <TableCell sx={{ display: { xs: "none", md: "table-cell" } }}>Stages</TableCell>
                              <TableCell sx={{ display: { xs: "none", sm: "table-cell" } }}>HR when opened</TableCell>
                            </TableRow>
                          </TableHead>
                          <TableBody>
                            {group.job_profiles.map((j) => (
                              <TableRow key={j.id} hover>
                                <TableCell>
                                  <MuiLink component={Link} href={`/admin/postings/${j.id}`} underline="hover" fontWeight={600}>
                                    {j.title}
                                  </MuiLink>
                                  <Typography variant="caption" color="text.secondary" display="block">
                                    {j.offer_label}
                                    {j.signatory?.name ? ` · signed by ${j.signatory.name}${j.signatory.designation ? `, ${j.signatory.designation}` : ""}` : ""}
                                  </Typography>
                                </TableCell>
                                <TableCell>
                                  <Chip size="small" variant="outlined" color={statusColor(j.status)} label={postingStatusLabel(j.status)} />
                                </TableCell>
                                <TableCell sx={{ display: { xs: "none", md: "table-cell" } }}>{formatDate(j.opened_at)}</TableCell>
                                <TableCell>{compensation(j.compensation, j.type)}</TableCell>
                                <TableCell align="right">{j.applicants}</TableCell>
                                <TableCell align="right">{j.offers}</TableCell>
                                <TableCell sx={{ display: { xs: "none", md: "table-cell" } }}>
                                  {j.stages_completed} / {j.stages} done
                                </TableCell>
                                <TableCell sx={{ display: { xs: "none", sm: "table-cell" } }}>
                                  {j.hr_at_opening ? (
                                    <>
                                      <Typography variant="body2">{j.hr_at_opening.name ?? "—"}</Typography>
                                      <Typography variant="caption" color="text.secondary">
                                        {[j.hr_at_opening.email, j.hr_at_opening.phone].filter(Boolean).join(" · ")}
                                      </Typography>
                                    </>
                                  ) : (
                                    <Typography variant="caption" color="text.secondary">
                                      Not recorded (opened before contact history began)
                                    </Typography>
                                  )}
                                </TableCell>
                              </TableRow>
                            ))}
                          </TableBody>
                        </Table>
                      </TableContainer>
                    </Box>
                  ))}
                </Stack>
              </Box>
            ))}
          </Stack>
          {data.accepted_not_opened.length > 0 && (
            <Alert severity="info" sx={{ mt: 2 }}>
              Accepted but never opened for students:{" "}
              {data.accepted_not_opened.map((f, i) => (
                <span key={`${f.form_type}-${f.id}`}>
                  {i > 0 && ", "}
                  <MuiLink component={Link} href={`/admin/${f.form_type}s/${f.id}`}>
                    {f.title || `${f.form_type.toUpperCase()} #${f.id}`}
                  </MuiLink>
                </span>
              ))}
            </Alert>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            Current contacts
          </Typography>
          <Grid container spacing={2}>
            <Grid size={{ xs: 12, md: 6 }}>
              <ContactCard title="HR" person={contacts.hr} />
            </Grid>
            {contacts.head_talent && (
              <Grid size={{ xs: 12, md: 6 }}>
                <ContactCard title="Head of Talent" person={contacts.head_talent} />
              </Grid>
            )}
            {contacts.primary && (
              <Grid size={{ xs: 12, md: 6 }}>
                <ContactCard title="Primary contact" person={contacts.primary} />
              </Grid>
            )}
            {contacts.secondary && (
              <Grid size={{ xs: 12, md: 6 }}>
                <ContactCard title="Secondary contact" person={contacts.secondary} />
              </Grid>
            )}
          </Grid>
          <Divider sx={{ my: 2 }} />
          <Typography variant="subtitle2" fontWeight={700} gutterBottom>
            Recruiter login accounts
          </Typography>
          {contacts.accounts.length === 0 ? (
            <Typography variant="body2" color="text.secondary">
              No recruiter accounts.
            </Typography>
          ) : (
            <Stack spacing={0.5}>
              {contacts.accounts.map((a) => (
                <Typography key={a.id} variant="body2">
                  {a.name} · <MuiLink href={`mailto:${a.email}`}>{a.email}</MuiLink>
                  {!a.is_active && " (suspended)"}
                </Typography>
              ))}
            </Stack>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            Contact changes
          </Typography>
          {data.contact_changes.length === 0 ? (
            <Typography variant="body2" color="text.secondary">
              No changes recorded. Changes are recorded from 20 Oct 2026; earlier contacts were overwritten.
            </Typography>
          ) : (
            <Stack spacing={1.5} divider={<Divider flexItem />}>
              {data.contact_changes.map((change, index) => (
                <Box key={index}>
                  <Typography variant="caption" color="text.secondary">
                    {formatDateTime(change.at)} · {change.source === "company" ? "by the company" : `by ${change.by ?? "the CDC"} (CDC)`}
                  </Typography>
                  {Object.keys(change.after).map((field) => (
                    <Typography key={field} variant="body2" sx={{ wordBreak: "break-word" }}>
                      <strong>{CONTACT_LABELS[field] ?? field}:</strong> {showValue(change.before[field])} → {showValue(change.after[field])}
                    </Typography>
                  ))}
                </Box>
              ))}
            </Stack>
          )}
        </CardContent>
      </Card>
    </Stack>
  );
}

function ContactCard({ title, person }) {
  return (
    <Box sx={{ border: 1, borderColor: "divider", borderRadius: 1, p: 1.5, height: "100%" }}>
      <Typography variant="caption" color="text.secondary">
        {title}
      </Typography>
      <Typography fontWeight={600}>{person?.name || "—"}</Typography>
      {person?.designation && (
        <Typography variant="body2" color="text.secondary">
          {person.designation}
        </Typography>
      )}
      {person?.email && (
        <Typography variant="body2" sx={{ wordBreak: "break-all" }}>
          <MuiLink href={`mailto:${person.email}`}>{person.email}</MuiLink>
        </Typography>
      )}
      {person?.phone && (
        <Typography variant="body2">
          <MuiLink href={`tel:${person.phone}`}>{person.phone}</MuiLink>
          {person.alt_phone ? ` · ${person.alt_phone}` : ""}
        </Typography>
      )}
    </Box>
  );
}
