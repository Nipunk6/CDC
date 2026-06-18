"use client";

import { useEffect, useState } from "react";
import {
  Alert,
  Button,
  Card,
  CardContent,
  Tab,
  Tabs,
  Skeleton,
  Stack,
  Typography,
} from "@mui/material";
import { adminApi } from "@/lib/adminapi";

type NotificationItem = {
  id: number;
  title: string;
  message: string;
  type: "info" | "success" | "warning" | "error";
  read_at: string | null;
  created_at: string;
};

type NotificationSection = "company" | "alumni_outreach" | "admin_action";

const COMPANY_NOTIFICATION_TITLES = new Set([
  "New Company Registration",
  "New JNF Submission",
  "New INF Submission",
  "JNF Edit Access Request",
  "INF Edit Access Request",
]);

const ALUMNI_NOTIFICATION_TITLES = new Set([
  "Alumni Outreach Submission",
]);

const isCompanyNotification = (item: NotificationItem) =>
  COMPANY_NOTIFICATION_TITLES.has(item.title);

const isAlumniNotification = (item: NotificationItem) =>
  ALUMNI_NOTIFICATION_TITLES.has(item.title);

export default function AdminNotificationsPage() {
  const [notifications, setNotifications] = useState<NotificationItem[]>([]);
  const [activeSection, setActiveSection] = useState<NotificationSection>("company");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    window.dispatchEvent(
      new CustomEvent("admin-notifications-updated", {
        detail: notifications.filter((item) => !item.read_at).length,
      }),
    );
  }, [notifications]);

  useEffect(() => {
    const run = async () => {
      setLoading(true);
      setError(null);

      try {
        const response = await adminApi<{ notifications: NotificationItem[]; unread_count: number }>(
          "/auth/notifications",
        );
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
      await adminApi(`/auth/notifications/${id}/read`, { method: "PATCH" });
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
      const unreadInSection = displayedNotifications.filter((n) => !n.read_at);
      await Promise.all(
        unreadInSection.map((n) =>
          adminApi(`/auth/notifications/${n.id}/read`, { method: "PATCH" }),
        ),
      );
      setNotifications((prev) =>
        prev.map((n) => ({
          ...n,
          read_at:
            unreadInSection.some((u) => u.id === n.id)
              ? n.read_at ?? new Date().toISOString()
              : n.read_at,
        })),
      );
    } catch (e) {
      setError(
        e instanceof Error ? e.message : "Failed to update notifications.",
      );
    }
  };

  const companyNotifications = notifications.filter((item) => isCompanyNotification(item));
  const alumniNotifications = notifications.filter((item) => isAlumniNotification(item));
  const adminActionNotifications = notifications.filter((item) => !isCompanyNotification(item));

  const companyUnreadCount = companyNotifications.filter((n) => !n.read_at).length;
  const alumniUnreadCount = alumniNotifications.filter((n) => !n.read_at).length;
  const adminActionUnreadCount = adminActionNotifications
    .filter((item) => !isAlumniNotification(item))
    .filter((n) => !n.read_at).length;

  const displayedNotifications =
    activeSection === "company"
      ? companyNotifications
      : activeSection === "alumni_outreach"
        ? alumniNotifications
        : adminActionNotifications.filter((item) => !isAlumniNotification(item));

  const unreadInSectionCount = displayedNotifications.filter((n) => !n.read_at).length;
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
          disabled={loading || unreadInSectionCount === 0}
          sx={{ alignSelf: { xs: "stretch", sm: "auto" } }}
        >
          Mark All Read ({unreadInSectionCount})
        </Button>
      </Stack>

      <Tabs
        value={activeSection}
        onChange={(_, value: NotificationSection) => setActiveSection(value)}
        variant="fullWidth"
      >
        <Tab
          value="company"
          label={`Company Notifications (${companyUnreadCount})`}
        />
        <Tab
          value="alumni_outreach"
          label={`Alumni Outreach (${alumniUnreadCount})`}
        />
        <Tab
          value="admin_action"
          label={`Admin Actions (${adminActionUnreadCount})`}
        />
      </Tabs>

      {error && <Alert severity="error">{error}</Alert>}

      <Card>
        <CardContent>
          <Stack spacing={1.5}>
            {loading &&
              Array.from({ length: 4 }).map((_, index) => (
                <Skeleton
                  key={`admin-notification-skeleton-${index}`}
                  variant="rounded"
                  height={68}
                />
              ))}

            {!loading && displayedNotifications.length === 0 && (
              <Typography color="text.secondary">
                {activeSection === "company"
                  ? "No company notifications available right now."
                  : activeSection === "alumni_outreach"
                    ? "No alumni outreach notifications available right now."
                  : "No admin action notifications available right now."}
              </Typography>
            )}

            {!loading &&
              displayedNotifications.map((item) => (
                <Stack
                  key={item.id}
                  spacing={0.5}
                  sx={{ opacity: item.read_at ? 0.7 : 1 }}
                >
                  <Typography variant="subtitle2">{item.title}</Typography>
                  <Typography variant="body2" color="text.secondary" sx={{ wordBreak: "break-word" }}>
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
