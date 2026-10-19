"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { Alert, Box, Button, Card, CardContent, Chip, Collapse, Skeleton, Stack, Typography } from "@mui/material";
import ExpandMoreIcon from "@mui/icons-material/ExpandMore";
import { formatDateTime } from "@/lib/format";
import { studentApi } from "@/lib/studentapi";
import JobProfileNotice from "@/components/student/jobprofilenotice";

const typeColor = { info: "info", success: "success", warning: "warning", error: "error" };

// What a notification is about (set by the portal; older ones have none). The Notices page is general; this page is
// personal: only what concerns this student (owner request 2026-10-19).
const CATEGORY_LABELS = {
  new_job: "New job",
  announcement: "From the CDC",
  notice: "Notice",
  message: "Message",
  deadline: "Deadline",
  status: "Job profile",
  schedule: "Schedule",
  application: "Application",
  result: "Result",
  offer: "Offer",
};

const FILTERS = [
  { value: "all", label: "All" },
  { value: "unread", label: "Unread" },
  { value: "jobs", label: "Jobs", categories: ["new_job", "announcement", "deadline", "status", "schedule"] },
  { value: "results", label: "Results", categories: ["result", "offer", "application"] },
  { value: "messages", label: "Messages", categories: ["message", "notice"] },
];

export default function StudentNotificationsPage() {
  const [notifications, setNotifications] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [filter, setFilter] = useState("all");
  const [open, setOpen] = useState(null);

  const broadcast = (items) => {
    const unread = items.filter((n) => !n.read_at).length;
    window.dispatchEvent(new CustomEvent("student-notifications-updated", { detail: unread }));
  };

  useEffect(() => {
    const run = async () => {
      setLoading(true);
      setError(null);
      try {
        const response = await studentApi("/auth/notifications");
        setNotifications(response.notifications ?? []);
      } catch (e) {
        setError(e instanceof Error ? e.message : "Failed to load notifications.");
      } finally {
        setLoading(false);
      }
    };
    void run();
  }, []);

  const markAsRead = async (id) => {
    try {
      await studentApi(`/auth/notifications/${id}/read`, { method: "PATCH" });
      setNotifications((prev) => {
        const next = prev.map((n) => (n.id === id ? { ...n, read_at: new Date().toISOString() } : n));
        broadcast(next);
        return next;
      });
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to update notification.");
    }
  };

  const toggle = (item) => {
    setOpen((current) => (current === item.id ? null : item.id));
    if (!item.read_at) void markAsRead(item.id);
  };

  const chosen = FILTERS.find((f) => f.value === filter);
  const shown = notifications.filter((n) =>
    filter === "unread" ? !n.read_at : chosen?.categories ? chosen.categories.includes(n.category) : true
  );

  const markAllAsRead = async () => {
    try {
      await studentApi("/auth/notifications/read-all", { method: "PATCH" });
      setNotifications((prev) => {
        const next = prev.map((n) => ({ ...n, read_at: n.read_at ?? new Date().toISOString() }));
        broadcast(next);
        return next;
      });
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to update notifications.");
    }
  };

  return (
    <Stack spacing={2.5}>
      <Stack direction={{ xs: "column", sm: "row" }} justifyContent="space-between" alignItems={{ xs: "stretch", sm: "center" }} spacing={1}>
        <Typography variant="h4" color="primary.main" fontWeight={700}>
          Notifications
        </Typography>
        <Button variant="outlined" onClick={() => void markAllAsRead()}>
          Mark All Read
        </Button>
      </Stack>

      {error && <Alert severity="error">{error}</Alert>}

      <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
        {FILTERS.map((f) => (
          <Chip key={f.value} label={f.label} color={filter === f.value ? "primary" : "default"} variant={filter === f.value ? "filled" : "outlined"} onClick={() => setFilter(f.value)} />
        ))}
      </Stack>

      <Card>
        <CardContent>
          <Stack spacing={2}>
            {loading && Array.from({ length: 4 }).map((_, index) => <Skeleton key={index} variant="rounded" height={68} />)}
            {!loading && shown.length === 0 && (
              <Typography color="text.secondary">{notifications.length === 0 ? "No notifications yet." : "Nothing here."}</Typography>
            )}
            {!loading &&
              shown.map((item) => {
                const expandable = Boolean(item.details) || (item.message ?? "").length > 160;
                const expanded = open === item.id;
                return (
                  <Stack key={item.id} spacing={0.5} sx={{ opacity: item.read_at && !expanded ? 0.7 : 1, pb: 1.5, borderBottom: 1, borderColor: "divider" }}>
                    <Box
                      role={expandable ? "button" : undefined}
                      tabIndex={expandable ? 0 : undefined}
                      aria-expanded={expandable ? expanded : undefined}
                      onClick={expandable ? () => toggle(item) : undefined}
                      onKeyDown={expandable ? (e) => (e.key === "Enter" || e.key === " ") && (e.preventDefault(), toggle(item)) : undefined}
                      sx={{ cursor: expandable ? "pointer" : "default" }}
                    >
                      <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap>
                        {!item.read_at && <Chip size="small" color={typeColor[item.type] ?? "default"} label="New" />}
                        {CATEGORY_LABELS[item.category] && <Chip size="small" variant="outlined" label={CATEGORY_LABELS[item.category]} />}
                        <Typography variant="subtitle2" sx={{ flex: 1, minWidth: 0, wordBreak: "break-word" }}>
                          {item.title}
                        </Typography>
                        {expandable && <ExpandMoreIcon fontSize="small" sx={{ transition: "transform .2s", transform: expanded ? "rotate(180deg)" : "none", color: "text.secondary" }} />}
                      </Stack>
                      <Typography
                        variant="body2"
                        color="text.secondary"
                        sx={{
                          mt: 0.5,
                          wordBreak: "break-word",
                          whiteSpace: "pre-line",
                          ...(expanded ? {} : { display: "-webkit-box", WebkitLineClamp: 2, WebkitBoxOrient: "vertical", overflow: "hidden" }),
                        }}
                      >
                        {item.message}
                      </Typography>
                    </Box>
                    {item.details && (
                      <Collapse in={expanded} unmountOnExit>
                        <Box sx={{ mt: 1, p: 1.5, borderRadius: 1, bgcolor: "action.hover" }}>
                          <JobProfileNotice details={item.details} />
                        </Box>
                      </Collapse>
                    )}
                    <Stack direction="row" justifyContent="space-between" alignItems="center" spacing={1}>
                      <Typography variant="caption" color="text.secondary">
                        {formatDateTime(item.created_at)}
                      </Typography>
                      <Stack direction="row" spacing={1}>
                        {item.link && (
                          <Button size="small" component={Link} href={item.link} onClick={() => !item.read_at && void markAsRead(item.id)}>
                            {item.category === "new_job" || item.category === "announcement" ? "View & Apply" : "Open"}
                          </Button>
                        )}
                        {!item.read_at && (
                          <Button size="small" onClick={() => void markAsRead(item.id)}>
                            Mark Read
                          </Button>
                        )}
                      </Stack>
                    </Stack>
                  </Stack>
                );
              })}
          </Stack>
        </CardContent>
      </Card>
    </Stack>
  );
}
