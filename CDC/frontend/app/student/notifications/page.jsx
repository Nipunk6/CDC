"use client";

import { useEffect, useState } from "react";
import { Alert, Button, Card, CardContent, Chip, Skeleton, Stack, Typography } from "@mui/material";
import { formatDateTime } from "@/lib/format";
import { studentApi } from "@/lib/studentapi";

const typeColor = { info: "info", success: "success", warning: "warning", error: "error" };

export default function StudentNotificationsPage() {
  const [notifications, setNotifications] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

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

      <Card>
        <CardContent>
          <Stack spacing={2}>
            {loading && Array.from({ length: 4 }).map((_, index) => <Skeleton key={index} variant="rounded" height={68} />)}
            {!loading && notifications.length === 0 && (
              <Typography color="text.secondary">No notifications yet.</Typography>
            )}
            {!loading &&
              notifications.map((item) => (
                <Stack key={item.id} spacing={0.5} sx={{ opacity: item.read_at ? 0.65 : 1, pb: 1.5, borderBottom: 1, borderColor: "divider" }}>
                  <Stack direction="row" spacing={1} alignItems="center">
                    {!item.read_at && <Chip size="small" color={typeColor[item.type] ?? "default"} label="New" />}
                    <Typography variant="subtitle2">{item.title}</Typography>
                  </Stack>
                  <Typography variant="body2" color="text.secondary" sx={{ wordBreak: "break-word" }}>
                    {item.message}
                  </Typography>
                  <Stack direction="row" justifyContent="space-between" alignItems="center">
                    <Typography variant="caption" color="text.secondary">
                      {formatDateTime(item.created_at)}
                    </Typography>
                    {!item.read_at && (
                      <Button size="small" onClick={() => void markAsRead(item.id)}>
                        Mark Read
                      </Button>
                    )}
                  </Stack>
                </Stack>
              ))}
          </Stack>
        </CardContent>
      </Card>
    </Stack>
  );
}
