"use client";

import { useEffect, useState } from "react";
import { Alert, Box, Button, Card, CardActionArea, CardContent, Collapse, LinearProgress, Pagination, Stack, Typography } from "@mui/material";
import AttachFileIcon from "@mui/icons-material/AttachFile";

import { plainText } from "@/lib/plaintext";
import { studentApi, studentBlobUrl } from "@/lib/studentapi";
import { formatDateTime } from "@/lib/format";

// Notices from the CDC addressed to this student, newest first, 20 per page; a dot marks unread ones (Superset parity
// S7.1). The unread count covers every page (server-side, M3). Rich text is always shown as plain text (D76).
export default function StudentNoticesPage() {
  const [page, setPage] = useState(1);
  const [notices, setNotices] = useState(null);
  const [meta, setMeta] = useState(null);
  const [unread, setUnread] = useState(0);
  const [open, setOpen] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    studentApi(`/student/notices?page=${page}`)
      .then((r) => {
        setNotices(r.notices ?? []);
        setMeta(r.meta ?? null);
        setUnread(r.unread_count ?? 0);
      })
      .catch((e) => setError(e instanceof Error ? e.message : "Failed to load notices."));
  }, [page]);

  const toggle = (notice) => {
    setOpen((current) => (current === notice.id ? null : notice.id));
    if (!notice.is_read) {
      setNotices((list) => list.map((n) => (n.id === notice.id ? { ...n, is_read: true } : n)));
      setUnread((count) => Math.max(0, count - 1));
      studentApi(`/student/notices/${notice.id}/read`, { method: "POST" }).catch(() => {});
    }
  };

  const changePage = (value) => {
    setOpen(null);
    setPage(value);
    if (typeof window !== "undefined") window.scrollTo({ top: 0, behavior: "smooth" });
  };

  const openAttachment = async (notice) => {
    try {
      const url = await studentBlobUrl(`/student/notices/${notice.id}/attachment`);
      window.open(url, "_blank", "noopener");
      setTimeout(() => URL.revokeObjectURL(url), 60000);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not open the attachment.");
    }
  };

  return (
    <Box sx={{ maxWidth: 860 }}>
      <Typography variant="h5" fontWeight={700}>
        Notices
      </Typography>
      <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
        {notices ? (unread ? `${unread} unread` : "All caught up") : "Notices from the CDC"}
      </Typography>
      {error && (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>
          {error}
        </Alert>
      )}
      {!notices && !error && <LinearProgress />}
      {notices && notices.length === 0 && <Typography color="text.secondary">No notices yet.</Typography>}
      <Stack spacing={1.25}>
        {(notices ?? []).map((notice) => {
          const text = plainText(notice.body);
          const expanded = open === notice.id;
          return (
            <Card key={notice.id} variant="outlined" sx={{ borderLeft: 4, borderLeftColor: notice.is_read ? "divider" : "primary.main" }}>
              <CardActionArea onClick={() => toggle(notice)} aria-expanded={expanded}>
                <CardContent sx={{ pb: expanded ? 1 : 2 }}>
                  <Stack direction="row" spacing={1.25} alignItems="flex-start">
                    <Box
                      aria-label={notice.is_read ? undefined : "Unread"}
                      sx={{ width: 10, height: 10, mt: 0.75, borderRadius: "50%", flexShrink: 0, bgcolor: notice.is_read ? "transparent" : "error.main" }}
                    />
                    <Box sx={{ minWidth: 0, flex: 1 }}>
                      <Typography fontWeight={notice.is_read ? 600 : 800} sx={{ wordBreak: "break-word" }}>
                        {notice.title}
                      </Typography>
                      <Typography variant="caption" color="text.secondary">
                        {formatDateTime(notice.published_at)}
                        {notice.attachment ? " · 1 attachment" : ""}
                      </Typography>
                      {!expanded && text && (
                        <Typography
                          variant="body2"
                          color="text.secondary"
                          sx={{ mt: 0.5, display: "-webkit-box", WebkitLineClamp: 2, WebkitBoxOrient: "vertical", overflow: "hidden", wordBreak: "break-word" }}
                        >
                          {text}
                        </Typography>
                      )}
                    </Box>
                  </Stack>
                </CardContent>
              </CardActionArea>
              <Collapse in={expanded} unmountOnExit>
                <CardContent sx={{ pt: 0, pl: { xs: 4.5, sm: 5 } }}>
                  {text && (
                    <Typography variant="body2" sx={{ whiteSpace: "pre-line", wordBreak: "break-word" }}>
                      {text}
                    </Typography>
                  )}
                  {notice.attachment && (
                    <Button size="small" variant="outlined" startIcon={<AttachFileIcon />} onClick={() => openAttachment(notice)} sx={{ mt: 1.5, textTransform: "none", maxWidth: "100%" }}>
                      <Box component="span" sx={{ overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>
                        {notice.attachment.name}
                      </Box>
                    </Button>
                  )}
                </CardContent>
              </Collapse>
            </Card>
          );
        })}
      </Stack>
      {meta?.last_page > 1 && (
        <Stack alignItems="center" sx={{ mt: 2 }}>
          <Pagination count={meta.last_page} page={meta.current_page} onChange={(_e, value) => changePage(value)} siblingCount={0} />
        </Stack>
      )}
    </Box>
  );
}
