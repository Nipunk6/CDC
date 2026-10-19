"use client";

import { Box, Stack, Typography } from "@mui/material";

import MonthCalendar from "@/components/shared/monthcalendar";
import { companyApi } from "@/lib/companyapi";

export default function CompanyCalendarPage() {
  return (
    <Stack spacing={3}>
      <Box>
        <Typography variant="h4" color="primary.main" fontWeight={700}>
          Calendar
        </Typography>
        <Typography color="text.secondary">Your own entries. Only you can see them.</Typography>
      </Box>
      <MonthCalendar api={companyApi} />
    </Stack>
  );
}
