"use client";

import { useEffect, useState } from "react";
import {
  Alert,
  Button,
  Card,
  CardContent,
  Skeleton,
  Stack,
  Typography,
} from "@mui/material";
import { companyApi } from "@/lib/companyapi";

type NotificationItem = {
  id: number;
  title: string;
  message: string;
  type: "info" | "success" | "warning" | "error";
  read_at: string | null;
  created_at: string;
};

export default function CompanyNotificationsPage() {
  const [notifications, setNotifications] = useState<NotificationItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const run = async () => {
      setLoading(true);
      setError(null);

      try {
        const response = await companyApi<{
          notifications: NotificationItem[];
        }>("/auth/notifications");
        setNotifications(response.notifications);
      } catch (e) {
        setError(
          e instanceof Error ? e.message : "Failed to load notifications.",
        );
      } finally {
        setLoading(false);
      }
    };

    void run();
  }, []);

  const markAsRead = async (id: number) => {
    try {
      await companyApi(`/auth/notifications/${id}/read`, { method: "PATCH" });
      setNotifications((prev) =>
        prev.map((n) =>
          n.id === id ? { ...n, read_at: new Date().toISOString() } : n,
        ),
      );
    } catch (e) {
      setError(
        e instanceof Error ? e.message : "Failed to update notification.",
      );
    }
  };

  const markAllAsRead = async () => {
    try {
      await companyApi("/auth/notifications/read-all", { method: "PATCH" });
      setNotifications((prev) =>
        prev.map((n) => ({
          ...n,
          read_at: n.read_at ?? new Date().toISOString(),
        })),
      );
    } catch (e) {
      setError(
        e instanceof Error ? e.message : "Failed to update notifications.",
      );
    }
  };

  return (
    <Stack spacing={2.5}>
      <Stack
        direction={{ xs: "column", sm: "row" }}
        justifyContent="space-between"
        alignItems={{ xs: "stretch", sm: "center" }}
        spacing={1}
      >
        <Typography variant="h4" color="primary.main">
          Notifications
        </Typography>
        <Button
          variant="outlined"
          onClick={() => void markAllAsRead()}
          sx={{ alignSelf: { xs: "stretch", sm: "auto" } }}
        >
          Mark All Read
        </Button>
      </Stack>

      {error && <Alert severity="error">{error}</Alert>}

      <Card>
        <CardContent>
          <Stack spacing={1.5}>
            {loading &&
              Array.from({ length: 4 }).map((_, index) => (
                <Skeleton
                  key={`company-notification-skeleton-${index}`}
                  variant="rounded"
                  height={68}
                />
              ))}

            {!loading && notifications.length === 0 && (
              <Typography color="text.secondary">
                No notifications available right now.
              </Typography>
            )}

            {!loading &&
              notifications.map((item) => (
                <Stack
                  key={item.id}
                  spacing={0.5}
                  sx={{ opacity: item.read_at ? 0.7 : 1 }}
                >
                  <Typography variant="subtitle2">{item.title}</Typography>
                  <Typography
                    variant="body2"
                    color="text.secondary"
                    sx={{ wordBreak: "break-word" }}
                  >
                    {item.message}
                  </Typography>
                  <Stack
                    direction="row"
                    justifyContent="space-between"
                    alignItems="center"
                  >
                    <Typography variant="caption" color="text.secondary">
                      {new Date(item.created_at).toLocaleString()}
                    </Typography>
                    {!item.read_at && (
                      <Button
                        size="small"
                        onClick={() => void markAsRead(item.id)}
                      >
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
