"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  Alert,
  AlertTitle,
  Button,
  Card,
  CardContent,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Grid2 as Grid,
  IconButton,
  LinearProgress,
  List,
  ListItemButton,
  ListItemText,
  Pagination,
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
import CategoryIcon from "@mui/icons-material/Category";
import AddIcon from "@mui/icons-material/Add";
import EditIcon from "@mui/icons-material/Edit";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import PersonRemoveIcon from "@mui/icons-material/PersonRemove";
import UploadFileIcon from "@mui/icons-material/UploadFile";

import PageHeader from "@/components/shared/pageheader";
import { adminApi } from "@/lib/adminapi";
import { adminUpload } from "@/lib/adminupload";
import { splitIdentifiers } from "@/components/admin/posting/bulkshortlistdialog";

/**
 * Student Categories for Placement (Superset parity S8.4): CDC-defined categories such as a minor or a double major,
 * assigned to students and usable as "Allowed Student Categories" in a job profile's eligibility.
 */
export default function StudentCategoriesPage() {
  const [categories, setCategories] = useState(null);
  const [version, setVersion] = useState(0);
  const [selectedId, setSelectedId] = useState(null);
  const [editing, setEditing] = useState(null); // { id?, title, description }
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [busy, setBusy] = useState(false);

  const reload = useCallback(() => setVersion((v) => v + 1), []);

  useEffect(() => {
    let cancelled = false;
    adminApi("/admin/student-categories")
      .then((r) => {
        if (cancelled) return;
        setCategories(r.categories);
        setSelectedId((current) => current ?? r.categories[0]?.id ?? null);
      })
      .catch((e) => !cancelled && setError(e.message));
    return () => {
      cancelled = true;
    };
  }, [version]);

  const save = async () => {
    setBusy(true);
    setError(null);
    try {
      const body = JSON.stringify({ title: editing.title.trim(), description: editing.description.trim() || null });
      const response = editing.id
        ? await adminApi(`/admin/student-categories/${editing.id}`, { method: "PATCH", body })
        : await adminApi("/admin/student-categories", { method: "POST", body });
      setSuccess(response.message);
      setEditing(null);
      if (!editing.id) setSelectedId(response.category.id);
      reload();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not save the category.");
    } finally {
      setBusy(false);
    }
  };

  const remove = async (category) => {
    if (!window.confirm(`Delete "${category.title}"? Its ${category.students_count} student assignment(s) are removed.`)) return;
    try {
      const response = await adminApi(`/admin/student-categories/${category.id}`, { method: "DELETE" });
      setSuccess(response.message);
      setSelectedId(null);
      reload();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not delete the category.");
    }
  };

  const selected = categories?.find((c) => c.id === selectedId) ?? null;

  return (
    <>
      <PageHeader
        icon={<CategoryIcon />}
        title="Student Categories"
        subtitle="Categories such as a minor or a double major. A job profile can require one of them in its eligibility."
        actions={
          <Button
            variant="contained"
            startIcon={<AddIcon />}
            onClick={() => setEditing({ title: "", description: "" })}
            sx={{ bgcolor: "common.white", color: "primary.main", "&:hover": { bgcolor: "grey.100" } }}
          >
            Add Category
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
      {!categories && !error && <LinearProgress />}
      {categories && (
        <Grid container spacing={2}>
          <Grid size={{ xs: 12, md: 4 }}>
            <Card>
              <CardContent sx={{ p: 0, "&:last-child": { pb: 0 } }}>
                {categories.length === 0 ? (
                  <Typography variant="body2" color="text.secondary" sx={{ p: 2 }}>
                    No categories yet. Add one, then assign students to it.
                  </Typography>
                ) : (
                  <List disablePadding>
                    {categories.map((c) => (
                      <ListItemButton key={c.id} selected={c.id === selectedId} onClick={() => setSelectedId(c.id)} divider>
                        <ListItemText primary={c.title} secondary={`${c.students_count} student(s)`} primaryTypographyProps={{ fontWeight: 600 }} />
                      </ListItemButton>
                    ))}
                  </List>
                )}
              </CardContent>
            </Card>
          </Grid>
          <Grid size={{ xs: 12, md: 8 }}>
            {selected && (
              <CategoryMembers
                key={selected.id}
                category={selected}
                onEdit={() => setEditing({ id: selected.id, title: selected.title, description: selected.description ?? "" })}
                onDelete={() => remove(selected)}
                onChanged={(message) => {
                  setSuccess(message);
                  reload();
                }}
                onError={setError}
              />
            )}
          </Grid>
        </Grid>
      )}

      <Dialog open={Boolean(editing)} onClose={() => !busy && setEditing(null)} maxWidth="sm" fullWidth>
        <DialogTitle>{editing?.id ? "Edit Category" : "Add Category"}</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ pt: 1 }}>
            <TextField
              autoFocus
              label="Title"
              placeholder="Ex. Minor in Data Science"
              value={editing?.title ?? ""}
              onChange={(e) => setEditing((d) => ({ ...d, title: e.target.value }))}
              inputProps={{ maxLength: 120 }}
              required
            />
            <TextField
              label="Description"
              value={editing?.description ?? ""}
              onChange={(e) => setEditing((d) => ({ ...d, description: e.target.value }))}
              multiline
              minRows={2}
              inputProps={{ maxLength: 1000 }}
            />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setEditing(null)} disabled={busy}>
            Cancel
          </Button>
          <Button variant="contained" onClick={save} disabled={busy || !editing?.title.trim()}>
            Save
          </Button>
        </DialogActions>
      </Dialog>
    </>
  );
}

function CategoryMembers({ category, onEdit, onDelete, onChanged, onError }) {
  const [data, setData] = useState(null);
  const [query, setQuery] = useState({ search: "", page: 1 });
  const [search, setSearch] = useState("");
  const [version, setVersion] = useState(0);
  const [text, setText] = useState("");
  const [file, setFile] = useState(null);
  const [report, setReport] = useState(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    const timer = setTimeout(() => setQuery((q) => (q.search === search ? q : { search, page: 1 })), 300);
    return () => clearTimeout(timer);
  }, [search]);

  useEffect(() => {
    let cancelled = false;
    adminApi(`/admin/student-categories/${category.id}/students?${new URLSearchParams({ search: query.search, page: String(query.page) })}`)
      .then((r) => !cancelled && setData(r))
      .catch((e) => !cancelled && onError(e.message));
    return () => {
      cancelled = true;
    };
  }, [category.id, query, version, onError]);

  const assign = async () => {
    setBusy(true);
    try {
      let response;
      if (file) {
        const body = new FormData();
        body.append("file", file);
        response = await adminUpload(`/admin/student-categories/${category.id}/students`, body);
      } else {
        response = await adminApi(`/admin/student-categories/${category.id}/students`, { method: "POST", body: JSON.stringify({ roll_nos: splitIdentifiers(text) }) });
      }
      setReport(response.errors?.length ? response : null);
      setText("");
      setFile(null);
      setVersion((v) => v + 1);
      onChanged(response.message);
    } catch (e) {
      onError(e instanceof Error ? e.message : "Could not add the students.");
    } finally {
      setBusy(false);
    }
  };

  const unassign = async (student) => {
    try {
      const response = await adminApi(`/admin/student-categories/${category.id}/students/${student.id}`, { method: "DELETE" });
      setVersion((v) => v + 1);
      onChanged(response.message);
    } catch (e) {
      onError(e instanceof Error ? e.message : "Could not remove the student.");
    }
  };

  return (
    <Card>
      <CardContent>
        <Stack direction="row" justifyContent="space-between" alignItems="flex-start" spacing={1}>
          <div>
            <Typography variant="h6" fontWeight={700}>
              {category.title}
            </Typography>
            {category.description && (
              <Typography variant="body2" color="text.secondary">
                {category.description}
              </Typography>
            )}
          </div>
          <Stack direction="row">
            <Tooltip title="Edit">
              <IconButton onClick={onEdit} aria-label="Edit category">
                <EditIcon fontSize="small" />
              </IconButton>
            </Tooltip>
            <Tooltip title="Delete">
              <IconButton color="error" onClick={onDelete} aria-label="Delete category">
                <DeleteOutlineIcon fontSize="small" />
              </IconButton>
            </Tooltip>
          </Stack>
        </Stack>

        <Stack spacing={1.5} sx={{ my: 2 }}>
          <Typography variant="subtitle2" fontWeight={700}>
            Add students
          </Typography>
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
            <Button variant="outlined" component="label" startIcon={<UploadFileIcon />}>
              {file ? file.name : "…or upload .xlsx/.csv (roll numbers in the first column)"}
              <input hidden type="file" accept=".xlsx,.xls,.csv" onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
            </Button>
            <Button variant="contained" onClick={assign} disabled={busy || (!file && splitIdentifiers(text).length === 0)}>
              Add to category
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

        <TextField size="small" label="Search by name or Roll" value={search} onChange={(e) => setSearch(e.target.value)} sx={{ mb: 1.5, maxWidth: { sm: 320 } }} fullWidth />
        {!data && <LinearProgress />}
        {data && (
          <>
            <TableContainer sx={{ border: 1, borderColor: "divider", borderRadius: 1 }}>
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>Roll Number</TableCell>
                    <TableCell>Name</TableCell>
                    <TableCell sx={{ display: { xs: "none", sm: "table-cell" } }}>Branch</TableCell>
                    <TableCell align="right" />
                  </TableRow>
                </TableHead>
                <TableBody>
                  {data.students.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={4}>
                        <Typography variant="body2" color="text.secondary">
                          No students in this category.
                        </Typography>
                      </TableCell>
                    </TableRow>
                  )}
                  {data.students.map((s) => (
                    <TableRow key={s.id} hover>
                      <TableCell>
                        <Link href={`/admin/students/${s.id}`}>{s.roll_no}</Link>
                      </TableCell>
                      <TableCell>{s.full_name}</TableCell>
                      <TableCell sx={{ display: { xs: "none", sm: "table-cell" } }}>{s.branch}</TableCell>
                      <TableCell align="right">
                        <Tooltip title="Remove from category">
                          <IconButton size="small" onClick={() => unassign(s)} aria-label={`Remove ${s.roll_no}`}>
                            <PersonRemoveIcon fontSize="small" />
                          </IconButton>
                        </Tooltip>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
            <Stack direction={{ xs: "column", sm: "row" }} justifyContent="space-between" alignItems="center" spacing={1} sx={{ mt: 1.5 }}>
              <Typography variant="caption" color="text.secondary">
                Showing page {data.meta.current_page} of {data.meta.last_page} ({data.meta.total} records)
              </Typography>
              {data.meta.last_page > 1 && <Pagination count={data.meta.last_page} page={data.meta.current_page} onChange={(_e, page) => setQuery((q) => ({ ...q, page }))} />}
            </Stack>
          </>
        )}
      </CardContent>
    </Card>
  );
}
