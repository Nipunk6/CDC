"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import {
  Alert,
  Button,
  Card,
  CardContent,
  CardHeader,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  IconButton,
  LinearProgress,
  List,
  ListItem,
  ListItemIcon,
  ListItemText,
  Stack,
  TextField,
  Tooltip,
  Typography,
} from "@mui/material";
import TableChartIcon from "@mui/icons-material/TableChart";
import DescriptionIcon from "@mui/icons-material/Description";
import EditIcon from "@mui/icons-material/Edit";
import ContentCopyIcon from "@mui/icons-material/ContentCopy";
import AddIcon from "@mui/icons-material/Add";

import PageHeader from "@/components/shared/pageheader";
import { adminApi } from "@/lib/adminapi";
import { formatDateTime } from "@/lib/format";

/**
 * Excel Templates library (Superset parity S3): named column layouts used by "Excel - Custom Template" downloads.
 * Rendered by its own page and inside the Admin hub's Excel Templates tab (fix L14, `showHeader={false}`).
 */
export default function ExcelTemplateLibrary({ showHeader = true }) {
  const router = useRouter();
  const [templates, setTemplates] = useState(null);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [adding, setAdding] = useState(false);
  const [name, setName] = useState("");
  const [busy, setBusy] = useState(false);

  const [version, setVersion] = useState(0);
  const load = useCallback(() => setVersion((v) => v + 1), []);

  useEffect(() => {
    let cancelled = false;
    adminApi("/admin/export-templates")
      .then((response) => !cancelled && setTemplates(response.templates))
      .catch((e) => !cancelled && setError(e instanceof Error ? e.message : "Failed to load templates."));
    return () => {
      cancelled = true;
    };
  }, [version]);

  const create = async () => {
    setBusy(true);
    setError(null);
    try {
      const response = await adminApi("/admin/export-templates", { method: "POST", body: JSON.stringify({ name, type: "STUDENT_LIST" }) });
      router.push(`/admin/excel-templates/${response.template.id}`);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not create the template.");
      setBusy(false);
    }
  };

  const duplicate = async (template) => {
    setError(null);
    try {
      const response = await adminApi(`/admin/export-templates/${template.id}/duplicate`, { method: "POST" });
      setSuccess(`${response.message} "${response.template.name}" was added.`);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not duplicate the template.");
    }
  };

  return (
    <>
      {showHeader && (
        <PageHeader
          icon={<TableChartIcon />}
          title="Excel Templates"
          subtitle="Choose which columns, in which order and with which headings, appear when you download a student or applicant list with Excel - Custom Template."
        />
      )}
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
      <Card>
        <CardHeader
          title="Available Templates"
          titleTypographyProps={{ variant: "subtitle1", fontWeight: 700 }}
          action={
            <Button
              variant="contained"
              startIcon={<AddIcon />}
              onClick={() => {
                setName("");
                setAdding(true);
              }}
            >
              Add New
            </Button>
          }
        />
        <CardContent sx={{ pt: 0 }}>
          {!templates && !error && <LinearProgress />}
          {templates?.length === 0 && (
            <Typography variant="body2" color="text.secondary" sx={{ py: 3 }}>
              No templates yet. Add one, pick its columns, and it becomes available in every &quot;Excel - Custom Template&quot; download.
            </Typography>
          )}
          <List disablePadding>
            {(templates ?? []).map((t) => (
              <ListItem
                key={t.id}
                divider
                secondaryAction={
                  <Stack direction="row" spacing={0.5}>
                    <Button component={Link} href={`/admin/excel-templates/${t.id}`} size="small" startIcon={<EditIcon fontSize="small" />}>
                      Edit
                    </Button>
                    <Tooltip title="Duplicate">
                      <IconButton size="small" onClick={() => duplicate(t)} aria-label={`Duplicate ${t.name}`}>
                        <ContentCopyIcon fontSize="small" />
                      </IconButton>
                    </Tooltip>
                  </Stack>
                }
                sx={{ pr: { xs: 16, sm: 18 } }}
              >
                <ListItemIcon sx={{ minWidth: 40 }}>
                  <DescriptionIcon sx={{ color: "success.main" }} />
                </ListItemIcon>
                <ListItemText
                  primary={t.name}
                  primaryTypographyProps={{ fontWeight: 600, noWrap: true }}
                  secondary={`${t.columns.length} column(s) · ${t.type} · updated ${formatDateTime(t.updated_at)}`}
                />
              </ListItem>
            ))}
          </List>
        </CardContent>
      </Card>

      <Dialog open={adding} onClose={() => !busy && setAdding(false)} maxWidth="xs" fullWidth>
        <DialogTitle>Add Excel Template</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ pt: 1 }}>
            <TextField
              autoFocus
              label="Template Name"
              placeholder="Ex. Wipro Format, Standard Format"
              value={name}
              onChange={(e) => setName(e.target.value)}
              required
              inputProps={{ maxLength: 120 }}
            />
            <TextField label="Template Type" value="STUDENT_LIST" disabled helperText="Used for applicants, eligible lists, stage shortlists and student lists." />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setAdding(false)} disabled={busy}>
            Cancel
          </Button>
          <Button variant="contained" startIcon={<AddIcon />} onClick={create} disabled={busy || !name.trim()}>
            Create
          </Button>
        </DialogActions>
      </Dialog>
    </>
  );
}
