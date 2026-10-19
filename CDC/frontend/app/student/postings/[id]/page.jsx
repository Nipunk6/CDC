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
import EmojiEventsIcon from "@mui/icons-material/EmojiEvents";

import ApplyPanel from "@/components/student/applypanel";
import RoundTrail from "@/components/student/roundtrail";
import PostingPreview from "@/components/shared/postingpreview";
import { compensationText } from "@/components/student/postingcard";
import { studentApi, studentBlobUrl } from "@/lib/studentapi";
import { formatDate } from "@/lib/format";
import PictureAsPdfIcon from "@mui/icons-material/PictureAsPdf";
import SpocContacts from "@/components/student/spoccontacts";

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
         
        >
          <CardContent>
            <Stack direction="row" spacing={2} alignItems="center">
              <Avatar src={posting.company?.logo_url ?? undefined} variant="rounded" sx={{ width: 56, height: 56, bgcolor: "background.paper", color: "primary.main", border: 1, borderColor: "divider" }}>
                {posting.company?.name?.[0]}
              </Avatar>
              <Box sx={{ minWidth: 0 }}>
                <Stack direction="row" spacing={1} alignItems="center">
                  {posting.application?.offer && <EmojiEventsIcon sx={{ color: "warning.main", fontSize: 32 }} titleAccess="Offer received" />}
                  <Typography variant="h5" fontWeight={700} sx={{ wordBreak: "break-word" }}>
                    {posting.title}
                  </Typography>
                </Stack>
                <Typography color="text.secondary">{posting.company?.name}</Typography>
                <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap sx={{ mt: 1 }}>
                  {posting.application?.offer && (
                    <Chip size="small" color="warning" icon={<EmojiEventsIcon />} label={`Your offer: ${posting.application.offer.label}`} />
                  )}
                  <Chip size="small" variant="outlined" label={posting.offer_label ?? (posting.type === "fulltime" ? "Full Time" : "Internship")} />
                  {compensationText(posting.compensation) && <Chip size="small" variant="outlined" label={compensationText(posting.compensation)} />}
                  {posting.location && <Chip size="small" variant="outlined" label={posting.location} />}
                  {posting.visit_date && <Chip size="small" variant="outlined" label={`Date of visit: ${formatDate(posting.visit_date)}`} />}
                </Stack>
              </Box>
            </Stack>
          </CardContent>
        </Card>
      </Box>

      <Grid container spacing={3}>
        <Grid size={{ xs: 12, md: 4 }} sx={{ order: { xs: 1, md: 2 } }}>
          {/* The apply panel and the SPOC card stick together, so the SPOC card never slides under the panel; the
              column scrolls on its own when both are taller than the screen. */}
          <Stack
            spacing={3}
            sx={{ position: { md: "sticky" }, top: { md: 24 }, maxHeight: { md: "calc(100vh - 48px)" }, overflowY: { md: "auto" } }}
          >
            <Card sx={{ flexShrink: 0 }}>
              <CardContent>
                <ApplyPanel posting={posting} onChanged={load} />
              </CardContent>
            </Card>
            {posting.spocs?.length > 0 && <SpocContacts spocs={posting.spocs} sx={{ flexShrink: 0 }} />}
          </Stack>
        </Grid>
        <Grid size={{ xs: 12, md: 8 }} sx={{ order: { xs: 2, md: 1 } }}>
          <Stack spacing={3}>
            {posting.application && (
              <Card>
                <CardContent>
                  <Typography variant="subtitle1" fontWeight={700} gutterBottom>
                    Your progress
                  </Typography>
                  {posting.application.blocked_reason && (
                    <Alert severity="error" sx={{ mb: 1.5 }}>
                      Rejected: {posting.application.blocked_reason}
                    </Alert>
                  )}
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
