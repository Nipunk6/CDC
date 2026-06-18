"use client";

import { useEffect, useMemo, useState } from "react";
import {
  Alert,
  Box,
  Card,
  CardContent,
  Chip,
  LinearProgress,
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
import GroupsIcon from "@mui/icons-material/Groups";
import { adminApi } from "@/lib/adminapi";

type AlumniSubmission = {
  id: number;
  full_name: string;
  email: string;
  country_code: string | null;
  phone_number: string | null;
  phone: string | null;
  graduation_year: number | null;
  programme: string | null;
  department: string | null;
  current_organization: string | null;
  current_designation: string | null;
  city: string | null;
  country: string | null;
  linkedin_url: string | null;
  willing_to_mentor: boolean;
  willing_to_refer: boolean;
  message: string | null;
  general_comments: string | null;
  created_at: string;
};

export default function AdminAlumniOutreachPage() {
  const [submissions, setSubmissions] = useState<AlumniSubmission[]>([]);
  const [query, setQuery] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const run = async () => {
      try {
        const params = new URLSearchParams();
        if (query.trim()) {
          params.set("q", query.trim());
        }

        const suffix = params.toString() ? `?${params.toString()}` : "";
        const response = await adminApi<{ submissions: AlumniSubmission[] }>(`/admin/alumni-outreach${suffix}`);
        setSubmissions(response.submissions ?? []);
      } catch (requestError) {
        setError(requestError instanceof Error ? requestError.message : "Failed to load alumni outreach submissions.");
      } finally {
        setLoading(false);
      }
    };

    setLoading(true);
    setError(null);
    void run();
  }, [query]);

  const mentorCount = useMemo(
    () => submissions.filter((submission) => submission.willing_to_mentor).length,
    [submissions]
  );

  const referralCount = useMemo(
    () => submissions.filter((submission) => submission.willing_to_refer).length,
    [submissions]
  );

  return (
    <Box>
      <Stack direction={{ xs: "column", md: "row" }} justifyContent="space-between" alignItems={{ md: "center" }} spacing={2} mb={3}>
        <Stack direction="row" spacing={1.5} alignItems="center">
          <GroupsIcon color="primary" />
          <Box>
            <Typography variant="h5" fontWeight={700}>
              Alumni Outreach
            </Typography>
            <Typography color="text.secondary">
              Review alumni submissions collected from the public outreach form.
            </Typography>
          </Box>
        </Stack>

        <Stack direction="row" spacing={1}>
          <Chip label={`Total: ${submissions.length}`} color="primary" variant="outlined" />
          <Chip label={`Mentors: ${mentorCount}`} color="success" variant="outlined" />
          <Chip label={`Referrals: ${referralCount}`} color="secondary" variant="outlined" />
        </Stack>
      </Stack>

      <Card sx={{ mb: 2 }}>
        <CardContent>
          <TextField
            label="Search alumni submissions"
            placeholder="Name, email, programme, department, organization"
            fullWidth
            value={query}
            onChange={(event) => setQuery(event.target.value)}
          />
        </CardContent>
      </Card>

      {loading && <LinearProgress sx={{ mb: 2 }} />}
      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}

      <Card>
        <CardContent>
          <TableContainer>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell>Name</TableCell>
                  <TableCell>Phone</TableCell>
                  <TableCell>Academic</TableCell>
                  <TableCell>Current Role</TableCell>
                  <TableCell>Support</TableCell>
                  <TableCell>Mentorship Interests</TableCell>
                  <TableCell>General Comments</TableCell>
                  <TableCell>Submitted</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {submissions.map((submission) => (
                  <TableRow key={submission.id} hover>
                    <TableCell>
                      <Typography fontWeight={600}>{submission.full_name}</Typography>
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2">{submission.email}</Typography>
                      <Typography variant="caption" color="text.secondary">
                        {submission.country_code || submission.phone_number
                          ? `${submission.country_code ?? ""} ${submission.phone_number ?? ""}`.trim()
                          : (submission.phone ?? "-")}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2">{submission.programme ?? "-"}</Typography>
                      <Typography variant="caption" color="text.secondary">
                        {submission.department ?? "-"}
                        {submission.graduation_year ? ` • ${submission.graduation_year}` : ""}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2">{submission.current_designation ?? "-"}</Typography>
                      <Typography variant="caption" color="text.secondary">
                        {submission.current_organization ?? "-"}
                        {(submission.city || submission.country) ? ` • ${[submission.city, submission.country].filter(Boolean).join(", ")}` : ""}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      <Stack direction="row" spacing={0.5}>
                        {submission.willing_to_mentor && <Chip size="small" label="Mentor" color="success" />}
                        {submission.willing_to_refer && <Chip size="small" label="Referral" color="secondary" />}
                        {!submission.willing_to_mentor && !submission.willing_to_refer && (
                          <Chip size="small" label="-" variant="outlined" />
                        )}
                      </Stack>
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2" sx={{ maxWidth: 320, whiteSpace: "pre-wrap" }}>
                        {submission.message ?? "-"}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2" sx={{ maxWidth: 280, whiteSpace: "pre-wrap" }}>
                        {submission.general_comments ?? "-"}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2">
                        {new Date(submission.created_at).toLocaleString()}
                      </Typography>
                    </TableCell>
                  </TableRow>
                ))}
                {!loading && submissions.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={8}>
                      <Typography textAlign="center" color="text.secondary" py={4}>
                        No alumni outreach submissions yet.
                      </Typography>
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </TableContainer>
        </CardContent>
      </Card>
    </Box>
  );
}
