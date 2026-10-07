"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Box,
  Card,
  CardActionArea,
  CardContent,
  Chip,
  LinearProgress,
  Paper,
  Stack,
  Typography,
} from "@mui/material";
import WorkIcon from "@mui/icons-material/Work";

import PageHeader from "@/components/shared/pageheader";
import EventCard from "@/components/shared/eventcard";
import { companyApi } from "@/lib/companyapi";
import { formatDateTime, postingStatusLabel, statusColor } from "@/lib/format";

export default function CompanyPostingsPage() {
  const [postings, setPostings] = useState(null);
  const [events, setEvents] = useState([]);
  const [error, setError] = useState(null);

  useEffect(() => {
    companyApi("/company/postings")
      .then((response) => setPostings(response.postings ?? []))
      .catch((e) => setError(e.message));
    companyApi("/company/events")
      .then((response) => setEvents(response.events ?? []))
      .catch(() => setEvents([]));
  }, []);

  return (
    <>
      <PageHeader icon={<WorkIcon />} title="Job Profiles" subtitle="Your accepted JNFs/INFs that the CDC has opened to students." backHref="/company" backLabel="Dashboard" />
      {error && <Alert severity="error">{error}</Alert>}
      {!postings && !error && <LinearProgress />}
      {postings && postings.length === 0 && (
        <Paper sx={{ p: 5, textAlign: "center" }}>
          <Typography color="text.secondary">No job profiles yet. Once the CDC accepts your form and opens it to students, it appears here.</Typography>
        </Paper>
      )}
      <Stack spacing={1.5}>
        {(postings ?? []).map((posting) => (
          <Card key={posting.id}>
            <CardActionArea component={Link} href={`/company/postings/${posting.id}`}>
              <CardContent>
                <Stack direction={{ xs: "column", sm: "row" }} justifyContent="space-between" spacing={2}>
                  <Box sx={{ minWidth: 0 }}>
                    <Typography fontWeight={700} sx={{ wordBreak: "break-word" }}>
                      {posting.title}
                    </Typography>
                    <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap sx={{ mt: 0.5 }}>
                      <Chip size="small" variant="outlined" label={`${posting.form_type.toUpperCase()} · ${posting.type === "fulltime" ? "Full Time" : "Internship"}`} />
                      <Chip size="small" variant="outlined" color={statusColor(posting.status)} label={postingStatusLabel(posting)} />
                    </Stack>
                    <Typography variant="caption" color="text.secondary">
                      Applications close {formatDateTime(posting.application_deadline)}
                    </Typography>
                  </Box>
                  <Box sx={{ textAlign: { sm: "center" } }}>
                    <Typography variant="h5" fontWeight={700}>
                      {posting.applicant_count}
                    </Typography>
                    <Typography variant="caption" color="text.secondary">
                      Applicants
                    </Typography>
                  </Box>
                </Stack>
              </CardContent>
            </CardActionArea>
          </Card>
        ))}
      </Stack>

      {events.length > 0 && (
        <Box sx={{ mt: 4 }}>
          <Typography variant="h6" fontWeight={700} gutterBottom>
            Your campus events
          </Typography>
          <Stack spacing={1.5}>
            {events.map((event) => (
              <EventCard key={event.id} event={event} />
            ))}
          </Stack>
        </Box>
      )}
    </>
  );
}
