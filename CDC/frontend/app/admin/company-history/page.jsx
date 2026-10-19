"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Avatar,
  Box,
  Card,
  CardContent,
  Chip,
  LinearProgress,
  Link as MuiLink,
  MenuItem,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TextField,
  Typography,
} from "@mui/material";

import PageHeader from "@/components/shared/pageheader";
import TemplateDownloadButton from "@/components/admin/templatedownloadbutton";
import { adminApi } from "@/lib/adminapi";

/**
 * Admin → Company History (P3-D55): every company with the academic years and placements it came for (a job profile
 * opened for students), its totals and its current HR contact. Read only.
 */
export default function CompanyHistoryPage() {
  const [filters, setFilters] = useState({ year: "", type: "", status: "", q: "" });
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);

  const query = new URLSearchParams(Object.entries(filters).filter(([, v]) => v !== "")).toString();

  useEffect(() => {
    let cancelled = false;
    const timer = setTimeout(() => {
      adminApi(`/admin/company-history${query ? `?${query}` : ""}`)
        .then((r) => !cancelled && setData(r))
        .catch((e) => !cancelled && setError(e instanceof Error ? e.message : "Could not load the history."));
    }, 250);
    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [query]);

  const set = (key) => (e) => setFilters((f) => ({ ...f, [key]: e.target.value }));

  return (
    <>
      <PageHeader
        title="Company History"
        subtitle="Which years and placements each company came for, with its job profiles, offers and HR contact."
        actions={<TemplateDownloadButton label="Download Excel" path={`/admin/company-history/export${query ? `?${query}` : ""}`} fileName="company-history.xlsx" onError={setError} />}
      />
      {error && (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>
          {error}
        </Alert>
      )}
      <Card sx={{ mb: 2 }}>
        <CardContent>
          <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
            <TextField select size="small" label="Academic year" value={filters.year} onChange={set("year")} sx={{ minWidth: 160 }}>
              <MenuItem value="">All years</MenuItem>
              {(data?.years ?? []).map((y) => (
                <MenuItem key={y} value={y}>
                  {y}
                </MenuItem>
              ))}
            </TextField>
            <TextField select size="small" label="Kind" value={filters.type} onChange={set("type")} sx={{ minWidth: 160 }}>
              <MenuItem value="">Full Time and Internship</MenuItem>
              <MenuItem value="fulltime">Full Time</MenuItem>
              <MenuItem value="internship">Internship</MenuItem>
            </TextField>
            <TextField select size="small" label="Show" value={filters.status} onChange={set("status")} sx={{ minWidth: 220 }}>
              <MenuItem value="">All companies</MenuItem>
              <MenuItem value="came">Came{filters.year ? ` in ${filters.year}` : ""}</MenuItem>
              <MenuItem value="not_returned">Came before, not {filters.year ? `in ${filters.year}` : "in the latest year"}</MenuItem>
              <MenuItem value="never">Never came</MenuItem>
            </TextField>
            <TextField size="small" label="Search company or HR" value={filters.q} onChange={set("q")} sx={{ minWidth: { md: 240 } }} />
          </Stack>
        </CardContent>
      </Card>

      {!data && !error && <LinearProgress />}
      {data && (
        <Card>
          <CardContent>
            <Typography variant="body2" color="text.secondary" sx={{ mb: 1.5 }}>
              {data.companies.length} compan{data.companies.length === 1 ? "y" : "ies"}. A company &quot;came&quot; in a year when one of its job profiles was opened
              for students in a placement of that year.
            </Typography>
            <TableContainer sx={{ border: 1, borderColor: "divider", borderRadius: 1 }}>
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>Company</TableCell>
                    <TableCell>Years and placements</TableCell>
                    <TableCell align="right" sx={{ display: { xs: "none", md: "table-cell" } }}>Job profiles</TableCell>
                    <TableCell align="right" sx={{ display: { xs: "none", md: "table-cell" } }}>Applicants</TableCell>
                    <TableCell align="right">Offers</TableCell>
                    <TableCell sx={{ display: { xs: "none", sm: "table-cell" } }}>HR contact</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {data.companies.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={6}>
                        <Typography variant="body2" color="text.secondary">
                          No company matches these filters.
                        </Typography>
                      </TableCell>
                    </TableRow>
                  )}
                  {data.companies.map((c) => (
                    <TableRow key={c.id} hover>
                      <TableCell>
                        <Stack direction="row" spacing={1.5} alignItems="center">
                          <Avatar src={c.logo_url ?? undefined} variant="rounded" sx={{ width: 32, height: 32, bgcolor: "background.paper", color: "primary.main", border: 1, borderColor: "divider" }}>
                            {c.name?.[0]}
                          </Avatar>
                          <Box sx={{ minWidth: 0 }}>
                            <MuiLink component={Link} href={`/admin/companies/${c.id}?tab=history`} underline="hover" fontWeight={600}>
                              {c.name}
                            </MuiLink>
                            {c.sector && (
                              <Typography variant="caption" color="text.secondary" display="block">
                                {c.sector}
                              </Typography>
                            )}
                          </Box>
                        </Stack>
                      </TableCell>
                      <TableCell>
                        {c.visits.length === 0 ? (
                          <Typography variant="body2" color="text.secondary">
                            Not yet{c.accepted_not_opened ? ` · ${c.accepted_not_opened} accepted form(s) not opened` : ""}
                          </Typography>
                        ) : (
                          <Stack spacing={0.5}>
                            {c.visits.map((v) => (
                              <Stack key={v.year} direction="row" spacing={0.5} flexWrap="wrap" useFlexGap alignItems="center">
                                <Typography variant="body2" fontWeight={600} sx={{ mr: 0.5 }}>
                                  {v.year}
                                </Typography>
                                {v.placements.map((p) => (
                                  <Chip key={p.id} size="small" variant="outlined" color={p.type === "internship" ? "secondary" : "primary"} label={p.name} />
                                ))}
                              </Stack>
                            ))}
                          </Stack>
                        )}
                      </TableCell>
                      <TableCell align="right" sx={{ display: { xs: "none", md: "table-cell" } }}>{c.job_profiles}</TableCell>
                      <TableCell align="right" sx={{ display: { xs: "none", md: "table-cell" } }}>{c.applicants}</TableCell>
                      <TableCell align="right">{c.offers}</TableCell>
                      <TableCell sx={{ display: { xs: "none", sm: "table-cell" } }}>
                        <Typography variant="body2">{c.hr.name || "—"}</Typography>
                        <Typography variant="caption" color="text.secondary" sx={{ wordBreak: "break-all" }}>
                          {[c.hr.email, c.hr.phone].filter(Boolean).join(" · ")}
                        </Typography>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          </CardContent>
        </Card>
      )}
    </>
  );
}
