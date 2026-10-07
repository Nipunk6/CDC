"use client";

import { useCallback, useEffect, useState } from "react";
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  Checkbox,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControl,
  FormControlLabel,
  InputLabel,
  LinearProgress,
  MenuItem,
  Pagination,
  Select,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import CampaignIcon from "@mui/icons-material/Campaign";
import AddIcon from "@mui/icons-material/Add";
import AttachFileIcon from "@mui/icons-material/AttachFile";

import PageHeader from "@/components/shared/pageheader";
import ConfirmDialog from "@/components/admin/engagement/confirmdialog";
import NoticeComposer from "@/components/admin/engagement/noticecomposer";
import { stripHtml } from "@/components/forms/shared";
import { adminApi } from "@/lib/adminapi";
import { adminBlobUrl } from "@/lib/adminupload";
import { formatDateTime } from "@/lib/format";

export default function AdminNoticesPage() {
  const [status, setStatus] = useState("all");
  const [search, setSearch] = useState("");
  const [term, setTerm] = useState("");
  const [page, setPage] = useState(1);
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [composer, setComposer] = useState(null); // { notice } | null
  const [publishing, setPublishing] = useState(null); // { notice, sendEmail }
  const [busy, setBusy] = useState(false);
  const [removing, setRemoving] = useState({ open: false, notice: null }); // Delete confirmation (the notice stays while it closes)

  useEffect(() => {
    const timer = setTimeout(() => {
      setTerm(search.trim());
      setPage(1);
    }, 300);
    return () => clearTimeout(timer);
  }, [search]);

  const load = useCallback(() => {
    const params = new URLSearchParams({ status, page: String(page) });
    if (term) params.set("search", term);
    return adminApi(`/admin/notices?${params}`)
      .then(setData)
      .catch((e) => setError(e instanceof Error ? e.message : "Failed to load notices."));
  }, [status, term, page]);

  useEffect(() => {
    load();
  }, [load]);

  const closeRemove = () => setRemoving((r) => ({ ...r, open: false }));

  const remove = async () => {
    setBusy(true);
    try {
      setSuccess((await adminApi(`/admin/notices/${removing.notice.id}`, { method: "DELETE" })).message);
      closeRemove();
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not delete the notice.");
      closeRemove();
    } finally {
      setBusy(false);
    }
  };

  const publish = async () => {
    setBusy(true);
    try {
      const response = await adminApi(`/admin/notices/${publishing.notice.id}/publish`, { method: "POST", body: JSON.stringify({ send_email: publishing.sendEmail }) });
      setSuccess(response.message);
      setPublishing(null);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not publish the notice.");
      setPublishing(null);
    } finally {
      setBusy(false);
    }
  };

  const openAttachment = async (notice) => {
    try {
      const url = await adminBlobUrl(`/admin/notices/${notice.id}/attachment`);
      window.open(url, "_blank", "noopener");
      setTimeout(() => URL.revokeObjectURL(url), 60000);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not open the attachment.");
    }
  };

  const notices = data?.notices ?? [];

  return (
    <>
      <PageHeader
        icon={<CampaignIcon />}
        title="Notices"
        subtitle="Notice board for students. Publish to an audience; optionally email it in BCC batches. Companies never see notices."
        backHref="/admin"
        backLabel="Back to Dashboard"
        actions={
          <Button variant="contained" color="secondary" startIcon={<AddIcon />} onClick={() => setComposer({ notice: null })}>
            Create Notice
          </Button>
        }
      />
      {error && (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>
          {error}
        </Alert>
      )}
      {success && (
        <Alert severity="success" sx={{ mb: 2 }} onClose={() => setSuccess(null)}>
          {success}
        </Alert>
      )}

      <Stack direction={{ xs: "column", sm: "row" }} spacing={1.5} sx={{ mb: 2 }}>
        <FormControl size="small" sx={{ minWidth: 160 }}>
          <InputLabel id="notice-status">Status</InputLabel>
          <Select
            labelId="notice-status"
            label="Status"
            value={status}
            onChange={(e) => {
              setStatus(e.target.value);
              setPage(1);
            }}
          >
            <MenuItem value="all">All</MenuItem>
            <MenuItem value="draft">Draft</MenuItem>
            <MenuItem value="published">Published</MenuItem>
          </Select>
        </FormControl>
        <TextField size="small" label="Search" placeholder="Start typing notice title..." value={search} onChange={(e) => setSearch(e.target.value)} sx={{ flex: 1, maxWidth: { sm: 420 } }} />
      </Stack>

      {!data && <LinearProgress />}
      {data && notices.length === 0 && <Typography color="text.secondary">No notices.</Typography>}
      <Stack spacing={1.5}>
        {notices.map((notice) => (
          <Card key={notice.id}>
            <CardContent>
              <Stack direction={{ xs: "column", md: "row" }} justifyContent="space-between" spacing={2}>
                <Box sx={{ minWidth: 0 }}>
                  <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap>
                    <Typography fontWeight={700} sx={{ wordBreak: "break-word" }}>
                      {notice.title}
                    </Typography>
                    {notice.published_at ? <Chip size="small" color="success" label="Published" /> : <Chip size="small" variant="outlined" label="Draft" />}
                    {notice.emailed_at && <Chip size="small" variant="outlined" label="Emailed" />}
                  </Stack>
                  {stripHtml(notice.body) && (
                    <Typography
                      variant="body2"
                      color="text.secondary"
                      sx={{ mt: 0.5, display: "-webkit-box", WebkitLineClamp: 2, WebkitBoxOrient: "vertical", overflow: "hidden", wordBreak: "break-word" }}
                    >
                      {stripHtml(notice.body)}
                    </Typography>
                  )}
                  <Typography variant="caption" color="text.secondary" component="div" sx={{ mt: 0.5 }}>
                    Audience: {notice.audiences.map((a) => a.label).join(" · ") || "none yet"} ({notice.audience_count} student(s))
                    {notice.published_at ? ` · Read by ${notice.read_count}` : ""}
                  </Typography>
                  <Typography variant="caption" color="text.secondary" component="div">
                    {notice.published_at ? `Published ${formatDateTime(notice.published_at)}` : `Created ${formatDateTime(notice.created_at)}`}
                    {notice.created_by ? ` by ${notice.created_by}` : ""}
                  </Typography>
                  {notice.attachment && (
                    <Button size="small" startIcon={<AttachFileIcon />} onClick={() => openAttachment(notice)} sx={{ mt: 0.5, textTransform: "none" }}>
                      {notice.attachment.name}
                    </Button>
                  )}
                </Box>
                <Stack direction="row" spacing={1} alignItems="flex-start" flexWrap="wrap" useFlexGap>
                  {!notice.published_at && (
                    <Button variant="contained" size="small" onClick={() => setPublishing({ notice, sendEmail: false })} disabled={notice.audiences.length === 0}>
                      Publish
                    </Button>
                  )}
                  <Button size="small" onClick={() => setComposer({ notice })}>
                    Edit
                  </Button>
                  <Button size="small" color="error" onClick={() => setRemoving({ open: true, notice })}>
                    Delete
                  </Button>
                </Stack>
              </Stack>
            </CardContent>
          </Card>
        ))}
      </Stack>
      {data?.meta?.last_page > 1 && (
        <Stack alignItems="center" sx={{ mt: 2 }}>
          <Pagination count={data.meta.last_page} page={data.meta.current_page} onChange={(_e, value) => setPage(value)} />
        </Stack>
      )}

      {composer && (
        <NoticeComposer
          key={composer.notice?.id ?? "new"}
          open
          notice={composer.notice}
          onClose={() => setComposer(null)}
          onDone={(message) => {
            setComposer(null);
            setSuccess(message);
            void load();
          }}
        />
      )}

      <ConfirmDialog
        open={removing.open}
        title="Delete this notice?"
        message={`Delete "${removing.notice?.title ?? ""}"? Students will no longer see it.`}
        confirmLabel="Delete"
        color="error"
        busy={busy}
        onConfirm={remove}
        onCancel={closeRemove}
      />

      <Dialog open={Boolean(publishing)} onClose={() => !busy && setPublishing(null)} maxWidth="xs" fullWidth>
        <DialogTitle>Publish this notice?</DialogTitle>
        <DialogContent>
          {publishing && (
            <Stack spacing={1}>
              <Typography>
                “{publishing.notice.title}” will appear on the Notices page of {publishing.notice.audience_count} student(s).
              </Typography>
              <FormControlLabel
                control={<Checkbox checked={publishing.sendEmail} onChange={(e) => setPublishing((p) => ({ ...p, sendEmail: e.target.checked }))} />}
                label="Also email it (BCC batches)"
              />
            </Stack>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setPublishing(null)} disabled={busy}>
            Cancel
          </Button>
          <Button variant="contained" onClick={publish} disabled={busy}>
            Publish
          </Button>
        </DialogActions>
      </Dialog>
    </>
  );
}
