"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  Alert,
  AlertTitle,
  Button,
  Card,
  CardContent,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  IconButton,
  LinearProgress,
  MenuItem,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TextField,
  Tooltip,
  Typography,
} from "@mui/material";
import EditIcon from "@mui/icons-material/Edit";
import PersonRemoveIcon from "@mui/icons-material/PersonRemove";
import UploadFileIcon from "@mui/icons-material/UploadFile";

import PageHeader from "@/components/shared/pageheader";
import ConfirmDialog from "@/components/admin/engagement/confirmdialog";
import { splitIdentifiers } from "@/components/admin/posting/bulkshortlistdialog";
import { adminApi } from "@/lib/adminapi";
import { adminUpload } from "@/lib/adminupload";
import { useAccess } from "@/lib/access";

/**
 * SCPT (Student Coordinators for Placement Team) of each placement (P10.1). Members chosen here can be made SPOC of a
 * job profile from its "SPOC" tab.
 */
export default function ScptPage() {
  const canWrite = useAccess().can("scpt", "write");
  const [cycles, setCycles] = useState(null);
  const [cycleId, setCycleId] = useState("");
  const [members, setMembers] = useState(null);
  const [search, setSearch] = useState("");
  const [version, setVersion] = useState(0);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [editing, setEditing] = useState(null); // { id, designation }
  const [removing, setRemoving] = useState(null);
  const [busy, setBusy] = useState(false);

  const reload = useCallback(() => setVersion((v) => v + 1), []);

  useEffect(() => {
    adminApi("/admin/placement-cycles")
      .then((r) => {
        setCycles(r.placement_cycles);
        setCycleId((current) => current || String(r.placement_cycles.find((c) => c.status === "open")?.id ?? r.placement_cycles[0]?.id ?? ""));
      })
      .catch((e) => setError(e.message));
  }, []);

  useEffect(() => {
    if (!cycleId) return undefined;
    let cancelled = false;
    const timer = setTimeout(() => {
      adminApi(`/admin/scpt?${new URLSearchParams({ placement_cycle_id: cycleId, search: search.trim() })}`)
        .then((r) => !cancelled && setMembers(r.members))
        .catch((e) => !cancelled && setError(e.message));
    }, 250);
    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [cycleId, search, version]);

  const saveDesignation = async () => {
    setBusy(true);
    try {
      const response = await adminApi(`/admin/scpt/${editing.id}`, { method: "PATCH", body: JSON.stringify({ designation: editing.designation.trim() || null }) });
      setSuccess(response.message);
      setEditing(null);
      reload();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not save.");
    } finally {
      setBusy(false);
    }
  };

  const remove = async () => {
    setBusy(true);
    try {
      const response = await adminApi(`/admin/scpt/${removing.id}`, { method: "DELETE" });
      setSuccess(response.message);
      setRemoving(null);
      reload();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not remove the member.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <PageHeader
        title="SCPT & SPOCs"
        subtitle="Student Coordinators for Placement Team of each placement. Make them SPOC of a job profile from its SPOC tab."
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
      {!cycles && !error && <LinearProgress />}
      {cycles && cycles.length === 0 && <Alert severity="info">Create a placement first. The SCPT is kept per placement.</Alert>}
      {cycles && cycles.length > 0 && (
        <Stack spacing={2}>
          <Card>
            <CardContent>
              <Stack direction={{ xs: "column", sm: "row" }} spacing={2}>
                <TextField
                  select
                  size="small"
                  label="Placement"
                  value={cycleId}
                  onChange={(e) => {
                    setMembers(null);
                    setCycleId(e.target.value);
                  }}
                  sx={{ minWidth: { sm: 320 } }}
                >
                  {cycles.map((c) => (
                    <MenuItem key={c.id} value={String(c.id)}>
                      {c.name}
                      {c.status === "closed" ? " (closed)" : ""}
                    </MenuItem>
                  ))}
                </TextField>
                <TextField size="small" label="Search by name or Roll" value={search} onChange={(e) => setSearch(e.target.value)} sx={{ minWidth: { sm: 260 } }} />
              </Stack>
            </CardContent>
          </Card>

          {canWrite && cycleId && (
            <AddMembers
              cycleId={cycleId}
              onAdded={(message) => {
                setSuccess(message);
                reload();
              }}
              onError={setError}
            />
          )}

          <Card>
            <CardContent>
              <Typography variant="subtitle1" fontWeight={700} gutterBottom>
                Members {members ? `(${members.length})` : ""}
              </Typography>
              {!members && <LinearProgress />}
              {members && (
                <TableContainer sx={{ border: 1, borderColor: "divider", borderRadius: 1 }}>
                  <Table size="small">
                    <TableHead>
                      <TableRow>
                        <TableCell>Roll Number</TableCell>
                        <TableCell>Name</TableCell>
                        <TableCell sx={{ display: { xs: "none", md: "table-cell" } }}>Branch</TableCell>
                        <TableCell sx={{ display: { xs: "none", sm: "table-cell" } }}>Designation</TableCell>
                        <TableCell sx={{ display: { xs: "none", md: "table-cell" } }}>Contact</TableCell>
                        <TableCell>SPOC for</TableCell>
                        {canWrite && <TableCell align="right" />}
                      </TableRow>
                    </TableHead>
                    <TableBody>
                      {members.length === 0 && (
                        <TableRow>
                          <TableCell colSpan={7}>
                            <Typography variant="body2" color="text.secondary">
                              No SCPT members in this placement yet.
                            </Typography>
                          </TableCell>
                        </TableRow>
                      )}
                      {members.map((m) => (
                        <TableRow key={m.id} hover>
                          <TableCell>
                            <Link href={`/admin/students/${m.student?.id}`}>{m.student?.roll_no}</Link>
                          </TableCell>
                          <TableCell>{m.student?.full_name}</TableCell>
                          <TableCell sx={{ display: { xs: "none", md: "table-cell" } }}>
                            {m.student?.branch}
                            {m.student?.graduating_batch ? ` · ${m.student.graduating_batch}` : ""}
                          </TableCell>
                          <TableCell sx={{ display: { xs: "none", sm: "table-cell" } }}>{m.designation || "—"}</TableCell>
                          <TableCell sx={{ display: { xs: "none", md: "table-cell" } }}>
                            <Typography variant="body2">{m.student?.phone || "—"}</Typography>
                            <Typography variant="caption" color="text.secondary">
                              {m.student?.institute_email}
                            </Typography>
                          </TableCell>
                          <TableCell>
                            {m.spoc_for.length === 0 ? (
                              <Typography variant="body2" color="text.secondary">
                                —
                              </Typography>
                            ) : (
                              <Stack direction="row" spacing={0.5} flexWrap="wrap" useFlexGap>
                                {m.spoc_for.map((p) => (
                                  <Chip
                                    key={p.job_posting_id}
                                    size="small"
                                    variant="outlined"
                                    component={Link}
                                    href={`/admin/postings/${p.job_posting_id}`}
                                    clickable
                                    label={`${p.company ?? ""} · ${p.title ?? ""}`}
                                  />
                                ))}
                              </Stack>
                            )}
                          </TableCell>
                          {canWrite && (
                            <TableCell align="right" sx={{ whiteSpace: "nowrap" }}>
                              <Tooltip title="Edit designation">
                                <IconButton size="small" onClick={() => setEditing({ id: m.id, designation: m.designation ?? "" })} aria-label={`Edit ${m.student?.roll_no}`}>
                                  <EditIcon fontSize="small" />
                                </IconButton>
                              </Tooltip>
                              <Tooltip title="Remove from SCPT">
                                <IconButton size="small" color="error" onClick={() => setRemoving(m)} aria-label={`Remove ${m.student?.roll_no}`}>
                                  <PersonRemoveIcon fontSize="small" />
                                </IconButton>
                              </Tooltip>
                            </TableCell>
                          )}
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </TableContainer>
              )}
            </CardContent>
          </Card>
        </Stack>
      )}

      <Dialog open={Boolean(editing)} onClose={() => !busy && setEditing(null)} maxWidth="xs" fullWidth>
        <DialogTitle>Edit designation</DialogTitle>
        <DialogContent>
          <TextField
            autoFocus
            fullWidth
            label="Designation"
            placeholder="Ex. Coordinator"
            value={editing?.designation ?? ""}
            onChange={(e) => setEditing((d) => ({ ...d, designation: e.target.value }))}
            inputProps={{ maxLength: 100 }}
            sx={{ mt: 1 }}
          />
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setEditing(null)} disabled={busy}>
            Cancel
          </Button>
          <Button variant="contained" onClick={saveDesignation} disabled={busy}>
            Save
          </Button>
        </DialogActions>
      </Dialog>

      <ConfirmDialog
        open={Boolean(removing)}
        title="Remove from SCPT?"
        message={
          removing?.spoc_for.length
            ? `${removing.student?.full_name} is SPOC for ${removing.spoc_for.length} job profile(s). Removing them ends those assignments, and they are told by mail.`
            : `${removing?.student?.full_name ?? ""} will be removed from this placement's SCPT.`
        }
        confirmLabel="Remove"
        color="error"
        busy={busy}
        onConfirm={remove}
        onCancel={() => setRemoving(null)}
      />
    </>
  );
}

function AddMembers({ cycleId, onAdded, onError }) {
  const [text, setText] = useState("");
  const [file, setFile] = useState(null);
  const [designation, setDesignation] = useState("");
  const [report, setReport] = useState(null);
  const [busy, setBusy] = useState(false);

  const add = async () => {
    setBusy(true);
    try {
      let response;
      if (file) {
        const body = new FormData();
        body.append("file", file);
        body.append("placement_cycle_id", cycleId);
        if (designation.trim()) body.append("designation", designation.trim());
        response = await adminUpload("/admin/scpt", body);
      } else {
        response = await adminApi("/admin/scpt", {
          method: "POST",
          body: JSON.stringify({ placement_cycle_id: Number(cycleId), roll_nos: splitIdentifiers(text), designation: designation.trim() || null }),
        });
      }
      setReport(response.errors?.length ? response : null);
      setText("");
      setFile(null);
      onAdded(response.message);
    } catch (e) {
      onError(e instanceof Error ? e.message : "Could not add the students.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <Card>
      <CardContent>
        <Typography variant="subtitle1" fontWeight={700} gutterBottom>
          Add members
        </Typography>
        <Stack spacing={1.5}>
          <TextField
            multiline
            minRows={2}
            label="Roll numbers"
            helperText="Separated by a new line, a comma or a space."
            value={text}
            disabled={Boolean(file)}
            onChange={(e) => setText(e.target.value)}
          />
          <Stack direction={{ xs: "column", sm: "row" }} spacing={1}>
            <TextField size="small" label="Designation (optional)" placeholder="Ex. Coordinator" value={designation} onChange={(e) => setDesignation(e.target.value)} inputProps={{ maxLength: 100 }} />
            <Button variant="outlined" component="label" startIcon={<UploadFileIcon />}>
              {file ? file.name : "…or upload .xlsx/.csv (roll numbers in the first column)"}
              <input hidden type="file" accept=".xlsx,.xls,.csv" onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
            </Button>
            <Button variant="contained" onClick={add} disabled={busy || (!file && splitIdentifiers(text).length === 0)}>
              Add to SCPT
            </Button>
          </Stack>
          {report && (
            <Alert severity="warning" onClose={() => setReport(null)}>
              <AlertTitle>Not added</AlertTitle>
              {report.errors.map((e) => (
                <div key={e.roll_no}>
                  {e.roll_no}: {e.reason}
                </div>
              ))}
            </Alert>
          )}
        </Stack>
      </CardContent>
    </Card>
  );
}
