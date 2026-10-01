"use client";

import { Box, Stack, Typography } from "@mui/material";

import MonthCalendar from "@/components/shared/monthcalendar";
import { studentApi } from "@/lib/studentapi";

export default function StudentCalendarPage() {
  return (
    <Stack spacing={3}>
      <Box>
        <Typography variant="h4" color="primary.main" fontWeight={700}>
          Calendar
        </Typography>
        <Typography color="text.secondary">Deadlines of openings in your cycles, your selection rounds and events for you.</Typography>
      </Box>
      <MonthCalendar api={studentApi} endpoint="/student/calendar" />
    </Stack>
  );
}
