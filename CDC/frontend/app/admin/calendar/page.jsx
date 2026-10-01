"use client";

import CalendarMonthIcon from "@mui/icons-material/CalendarMonth";

import PageHeader from "@/components/shared/pageheader";
import MonthCalendar from "@/components/shared/monthcalendar";
import { adminApi } from "@/lib/adminapi";

export default function AdminCalendarPage() {
  return (
    <>
      <PageHeader icon={<CalendarMonthIcon />} title="Calendar" subtitle="Events, application deadlines and scheduled selection rounds." backHref="/admin" backLabel="Back to Dashboard" />
      <MonthCalendar api={adminApi} endpoint="/admin/calendar" />
    </>
  );
}
