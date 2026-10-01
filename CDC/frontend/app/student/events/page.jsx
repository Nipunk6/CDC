"use client";

import { useEffect, useState } from "react";
import { Alert, Box, Grid2 as Grid, LinearProgress, Paper, Stack, Typography } from "@mui/material";

import EventCard from "@/components/shared/eventcard";
import { studentApi } from "@/lib/studentapi";

export default function StudentEventsPage() {
  const [events, setEvents] = useState(null);
  const [error, setError] = useState(null);
  const [now] = useState(() => Date.now());

  useEffect(() => {
    studentApi("/student/events")
      .then((response) => setEvents(response.events ?? []))
      .catch((e) => setError(e.message));
  }, []);

  const upcoming = (events ?? []).filter((e) => new Date(e.starts_at).getTime() >= now);
  const past = (events ?? []).filter((e) => new Date(e.starts_at).getTime() < now).reverse();

  return (
    <Stack spacing={3}>
      <Box>
        <Typography variant="h4" color="primary.main" fontWeight={700}>
          Events
        </Typography>
        <Typography color="text.secondary">Pre-placement talks, workshops and webinars for you.</Typography>
      </Box>
      {error && <Alert severity="error">{error}</Alert>}
      {!events && !error && <LinearProgress />}
      {events && upcoming.length === 0 && (
        <Paper variant="outlined" sx={{ p: 4, textAlign: "center" }}>
          <Typography color="text.secondary">No upcoming events right now.</Typography>
        </Paper>
      )}
      <Grid container spacing={2}>
        {upcoming.map((event) => (
          <Grid key={event.id} size={{ xs: 12, md: 6 }}>
            <EventCard event={event} />
          </Grid>
        ))}
      </Grid>
      {past.length > 0 && (
        <>
          <Typography variant="h6" fontWeight={700}>
            Recent
          </Typography>
          <Grid container spacing={2}>
            {past.map((event) => (
              <Grid key={event.id} size={{ xs: 12, md: 6 }}>
                <EventCard event={event} />
              </Grid>
            ))}
          </Grid>
        </>
      )}
    </Stack>
  );
}
