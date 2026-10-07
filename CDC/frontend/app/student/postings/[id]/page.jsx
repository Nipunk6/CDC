"use client";

import { use, useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Avatar,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  Grid2 as Grid,
  LinearProgress,
  Stack,
  Typography,
} from "@mui/material";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";

import ApplyPanel from "@/components/student/applypanel";
import RoundTrail from "@/components/student/roundtrail";
import PostingPreview from "@/components/shared/postingpreview";
import { compensationText } from "@/components/student/postingcard";
import { studentApi, studentBlobUrl } from "@/lib/studentapi";
import { formatDate } from "@/lib/format";
import PictureAsPdfIcon from "@mui/icons-material/PictureAsPdf";

export default function StudentPostingDetailPage({ params }) {
  const { id } = use(params);
  const [posting, setPosting] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const load = useCallback(async () => {
    try {
      const response = await studentApi(`/student/postings/${id}`);
      setPosting(response.posting);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load this job profile.");
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    void load();
  }, [load]);

  if (loading) return <LinearProgress />;
  if (!posting) return <Alert severity="error">{error ?? "Job profile not found."}</Alert>;

  return (
    <Stack spacing={3}>
      {error && (
        <Alert severity="error" onClose={() => setError(null)}>
          {error}
        </Alert>
      )}
      <Box>
        <Button component={Link} href="/student/postings" startIcon={<ArrowBackIcon />} sx={{ mb: 1 }}>
          Job Profiles
        </Button>
        <Card
          sx={{
            background: (theme) => `linear-gradient(135deg, ${theme.palette.primary.main} 0%, ${theme.palette.primary.dark} 100%)`,
            color: "white",
          }}
        >
          <CardContent>
            <Stack direction="row" spacing={2} alignItems="center">
              <Avatar src={posting.company?.logo_url ?? undefined} variant="rounded" sx={{ width: 56, height: 56, bgcolor: "white", color: "primary.main" }}>
                {posting.company?.name?.[0]}
              </Avatar>
              <Box sx={{ minWidth: 0 }}>
                <Typography variant="h5" fontWeight={700} sx={{ wordBreak: "break-word" }}>
                  {posting.title}
                </Typography>
                <Typography sx={{ opacity: 0.9 }}>{posting.company?.name}</Typography>
                <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap sx={{ mt: 1 }}>
                  <Chip size="small" sx={{ bgcolor: "white" }} label={posting.offer_label ?? (posting.type === "fulltime" ? "Full Time" : "Internship")} />
                  {compensationText(posting.compensation) && <Chip size="small" sx={{ bgcolor: "white" }} label={compensationText(posting.compensation)} />}
                  {posting.location && <Chip size="small" sx={{ bgcolor: "white" }} label={posting.location} />}
                  {posting.visit_date && <Chip size="small" sx={{ bgcolor: "white" }} label={`Date of visit: ${formatDate(posting.visit_date)}`} />}
                </Stack>
              </Box>
            </Stack>
          </CardContent>
        </Card>
      </Box>

      <Grid container spacing={3}>
        <Grid size={{ xs: 12, md: 4 }} sx={{ order: { xs: 1, md: 2 } }}>
          <Card sx={{ position: { md: "sticky" }, top: { md: 24 } }}>
            <CardContent>
              <ApplyPanel posting={posting} onChanged={load} />
            </CardContent>
          </Card>
        </Grid>
        <Grid size={{ xs: 12, md: 8 }} sx={{ order: { xs: 2, md: 1 } }}>
          <Stack spacing={3}>
            {posting.application && (
              <Card>
                <CardContent>
                  <Typography variant="subtitle1" fontWeight={700} gutterBottom>
                    Your progress
                  </Typography>
                  <RoundTrail trail={posting.trail} status={posting.application.status} />
                </CardContent>
              </Card>
            )}
            {posting.documents?.length > 0 && (
              <Card>
                <CardContent>
                  <Typography variant="subtitle1" fontWeight={700} gutterBottom>
                    Attached Documents
                  </Typography>
                  <Stack spacing={1}>
                    {posting.documents.map((doc) => (
                      <Button
                        key={doc.id}
                        variant="outlined"
                        startIcon={<PictureAsPdfIcon />}
                        sx={{ justifyContent: "flex-start", textTransform: "none" }}
                        onClick={async () => {
                          try {
                            const url = await studentBlobUrl(`/student/postings/${posting.id}/documents/${doc.id}`);
                            window.open(url, "_blank", "noopener");
                            setTimeout(() => URL.revokeObjectURL(url), 60000);
                          } catch (e) {
                            setError(e instanceof Error ? e.message : "Could not open the document.");
                          }
                        }}
                      >
                        {doc.title}
                      </Button>
                    ))}
                  </Stack>
                </CardContent>
              </Card>
            )}
            <PostingPreview formType={posting.form_type} formData={posting.form_data} companyName={posting.company?.name} logoUrl={posting.company?.logo_url} />
          </Stack>
        </Grid>
      </Grid>
    </Stack>
  );
}
