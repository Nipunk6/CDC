"use client";

import { useEffect, useState } from "react";
import { getSession } from "next-auth/react";
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControl,
  FormControlLabel,
  Checkbox,
  IconButton,
  InputLabel,
  MenuItem,
  Paper,
  Select,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TextField,
  Typography,
} from "@mui/material";
import AddIcon from "@mui/icons-material/Add";
import EditIcon from "@mui/icons-material/Edit";
import DeleteIcon from "@mui/icons-material/Delete";
import LinkIcon from "@mui/icons-material/Link";
import PictureAsPdfIcon from "@mui/icons-material/PictureAsPdf";
import VisibilityIcon from "@mui/icons-material/Visibility";
import VisibilityOffIcon from "@mui/icons-material/VisibilityOff";
import { adminApi } from "@/lib/adminapi";

interface PolicyDocument {
  id: number;
  title: string;
  type: "pdf" | "link";
  url: string;
  is_visible_jnf: boolean;
  is_visible_inf: boolean;
  created_at: string;
}

export default function AdminPolicyDocumentsPage() {
  const [documents, setDocuments] = useState<PolicyDocument[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  // Form dialog state
  const [openDialog, setOpenDialog] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [title, setTitle] = useState("");
  const [type, setType] = useState<"pdf" | "link">("link");
  const [url, setUrl] = useState("");
  const [file, setFile] = useState<File | null>(null);
  const [isVisibleJnf, setIsVisibleJnf] = useState(true);
  const [isVisibleInf, setIsVisibleInf] = useState(true);
  const [saving, setSaving] = useState(false);

  const fetchDocuments = async () => {
    setLoading(true);
    try {
      const data = await adminApi<PolicyDocument[]>("/admin/policy-documents");
      setDocuments(data);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load policy documents.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    void fetchDocuments();
  }, []);

  const handleOpenAdd = () => {
    resetForm();
    setEditingId(null);
    setOpenDialog(true);
  };

  const handleOpenEdit = (doc: PolicyDocument) => {
    setTitle(doc.title);
    setType(doc.type);
    setUrl(doc.type === "link" ? doc.url : "");
    setFile(null);
    setIsVisibleJnf(doc.is_visible_jnf);
    setIsVisibleInf(doc.is_visible_inf);
    setEditingId(doc.id);
    setOpenDialog(true);
  };

  const resetForm = () => {
    setTitle("");
    setType("link");
    setUrl("");
    setFile(null);
    setIsVisibleJnf(true);
    setIsVisibleInf(true);
  };

  const handleSaveDocument = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!title.trim()) {
      setError("Document title is required.");
      return;
    }
    if (type === "link" && !url.trim()) {
      setError("External Link URL is required for Link type.");
      return;
    }
    if (type === "pdf" && !file && !editingId) {
      setError("Please select a PDF file to upload.");
      return;
    }

    setSaving(true);
    setError(null);
    setSuccess(null);

    try {
      const session = await getSession();
      const token = session?.accessToken;

      const formData = new FormData();
      formData.append("title", title.trim());
      formData.append("type", type);
      formData.append("is_visible_jnf", isVisibleJnf ? "1" : "0");
      formData.append("is_visible_inf", isVisibleInf ? "1" : "0");
      
      if (type === "link") {
        formData.append("url", url.trim());
      } else if (type === "pdf" && file) {
        formData.append("file", file);
      }

      const isEdit = Boolean(editingId);
      const endpoint = isEdit
        ? `${process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api"}/admin/policy-documents/${editingId}`
        : `${process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api"}/admin/policy-documents`;

      if (isEdit) {
        formData.append("_method", "PUT");
      }

      const res = await fetch(endpoint, {
        method: "POST", // POST is used for file-uploads + Method Spoofing
        headers: {
          Accept: "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: formData,
      });

      if (!res.ok) {
        const data = (await res.json().catch(() => ({}))) as { message?: string };
        throw new Error(data.message ?? "Failed to save policy document.");
      }

      setSuccess(isEdit ? "Policy document updated successfully." : "Policy document created successfully.");
      setOpenDialog(false);
      resetForm();
      await fetchDocuments();
    } catch (e) {
      setError(e instanceof Error ? e.message : "An error occurred.");
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (id: number, docTitle: string) => {
    if (!window.confirm(`Are you sure you want to delete "${docTitle}"?`)) {
      return;
    }

    setError(null);
    setSuccess(null);

    try {
      await adminApi(`/admin/policy-documents/${id}`, {
        method: "DELETE",
      });
      setSuccess("Policy document deleted successfully.");
      await fetchDocuments();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to delete policy document.");
    }
  };

  const handleToggleVisibility = async (doc: PolicyDocument, formType: "jnf" | "inf") => {
    setError(null);
    setSuccess(null);

    const isVisibleJnfUpdated = formType === "jnf" ? !doc.is_visible_jnf : doc.is_visible_jnf;
    const isVisibleInfUpdated = formType === "inf" ? !doc.is_visible_inf : doc.is_visible_inf;

    try {
      await adminApi(`/admin/policy-documents/${doc.id}`, {
        method: "PUT",
        body: JSON.stringify({
          title: doc.title,
          type: doc.type,
          url: doc.url,
          is_visible_jnf: isVisibleJnfUpdated,
          is_visible_inf: isVisibleInfUpdated,
        }),
      });
      setSuccess(`Updated visibility for "${doc.title}".`);
      await fetchDocuments();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to update visibility.");
    }
  };

  return (
    <Stack spacing={3}>
      <Box sx={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <Box>
          <Typography variant="h4" fontWeight={700} gutterBottom>
            Guidelines & Policy Manager
          </Typography>
          <Typography color="text.secondary">
            Manage recruiter agreement guidelines, links, and PDF files. Configure which files show up in recruiter JNF/INF forms.
          </Typography>
        </Box>
        <Button variant="contained" startIcon={<AddIcon />} onClick={handleOpenAdd}>
          Add Document
        </Button>
      </Box>

      {error && (
        <Alert severity="error" onClose={() => setError(null)}>
          {error}
        </Alert>
      )}

      {success && (
        <Alert severity="success" onClose={() => setSuccess(null)}>
          {success}
        </Alert>
      )}

      <Paper sx={{ width: "100%", overflow: "hidden" }}>
        <TableContainer sx={{ overflowX: "auto" }}>
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>Title</TableCell>
                <TableCell>Type</TableCell>
                <TableCell>Destination</TableCell>
                <TableCell align="center">JNF Visibility</TableCell>
                <TableCell align="center">INF Visibility</TableCell>
                <TableCell align="right">Actions</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {loading ? (
                <TableRow>
                  <TableCell colSpan={6} align="center">
                    <Typography py={3} color="text.secondary">Loading guidelines list...</Typography>
                  </TableCell>
                </TableRow>
              ) : documents.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={6} align="center">
                    <Typography py={3} color="text.secondary">No guideline documents found. Click Add Document to get started.</Typography>
                  </TableCell>
                </TableRow>
              ) : (
                documents.map((doc) => (
                  <TableRow key={doc.id} hover>
                    <TableCell>
                      <Typography fontWeight={500}>{doc.title}</Typography>
                    </TableCell>
                    <TableCell>
                      <Chip
                        icon={doc.type === "pdf" ? <PictureAsPdfIcon fontSize="small" /> : <LinkIcon fontSize="small" />}
                        label={doc.type.toUpperCase()}
                        color={doc.type === "pdf" ? "error" : "primary"}
                        size="small"
                        variant="outlined"
                      />
                    </TableCell>
                    <TableCell sx={{ maxWidth: 300, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>
                      <a href={doc.url} target="_blank" rel="noopener noreferrer" style={{ textDecoration: "none", color: "#1976d2" }}>
                        {doc.url}
                      </a>
                    </TableCell>
                    <TableCell align="center">
                      <IconButton onClick={() => handleToggleVisibility(doc, "jnf")} color={doc.is_visible_jnf ? "success" : "default"}>
                        {doc.is_visible_jnf ? <VisibilityIcon /> : <VisibilityOffIcon />}
                      </IconButton>
                    </TableCell>
                    <TableCell align="center">
                      <IconButton onClick={() => handleToggleVisibility(doc, "inf")} color={doc.is_visible_inf ? "success" : "default"}>
                        {doc.is_visible_inf ? <VisibilityIcon /> : <VisibilityOffIcon />}
                      </IconButton>
                    </TableCell>
                    <TableCell align="right">
                      <IconButton onClick={() => handleOpenEdit(doc)} color="primary" size="small">
                        <EditIcon fontSize="small" />
                      </IconButton>
                      <IconButton onClick={() => handleDelete(doc.id, doc.title)} color="error" size="small">
                        <DeleteIcon fontSize="small" />
                      </IconButton>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </TableContainer>
      </Paper>

      {/* Form Dialog */}
      <Dialog open={openDialog} onClose={() => !saving && setOpenDialog(false)} maxWidth="sm" fullWidth>
        <form onSubmit={handleSaveDocument}>
          <DialogTitle>{editingId ? "Edit Policy Document" : "Add Policy Document"}</DialogTitle>
          <DialogContent dividers>
            <Stack spacing={3} sx={{ mt: 1 }}>
              <TextField
                fullWidth
                label="Document Title"
                placeholder="e.g., AIPC Guidelines 2026"
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                required
                disabled={saving}
              />

              <FormControl fullWidth>
                <InputLabel>Type</InputLabel>
                <Select
                  value={type}
                  label="Type"
                  onChange={(e) => setType(e.target.value as "pdf" | "link")}
                  disabled={saving}
                >
                  <MenuItem value="link">External URL Link</MenuItem>
                  <MenuItem value="pdf">PDF File Upload</MenuItem>
                </Select>
              </FormControl>

              {type === "link" ? (
                <TextField
                  fullWidth
                  label="Link URL"
                  placeholder="https://example.com/guidelines"
                  value={url}
                  onChange={(e) => setUrl(e.target.value)}
                  required
                  disabled={saving}
                />
              ) : (
                <Box>
                  <Typography variant="body2" color="text.secondary" gutterBottom>
                    Upload Guideline PDF File
                  </Typography>
                  <input
                    type="file"
                    accept="application/pdf"
                    onChange={(e) => setFile(e.target.files?.[0] || null)}
                    disabled={saving}
                    style={{ display: "block", marginTop: "8px" }}
                  />
                  {editingId && !file && (
                    <Typography variant="caption" color="text.secondary" sx={{ display: "block", mt: 1 }}>
                      Leave empty to keep existing PDF document.
                    </Typography>
                  )}
                </Box>
              )}

              <Stack direction="row" spacing={3}>
                <FormControlLabel
                  control={
                    <Checkbox
                      checked={isVisibleJnf}
                      onChange={(e) => setIsVisibleJnf(e.target.checked)}
                      disabled={saving}
                    />
                  }
                  label="Visible in recruiter JNFs"
                />
                <FormControlLabel
                  control={
                    <Checkbox
                      checked={isVisibleInf}
                      onChange={(e) => setIsVisibleInf(e.target.checked)}
                      disabled={saving}
                    />
                  }
                  label="Visible in recruiter INFs"
                />
              </Stack>
            </Stack>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setOpenDialog(false)} disabled={saving}>
              Cancel
            </Button>
            <Button type="submit" variant="contained" disabled={saving}>
              {saving ? "Saving..." : "Save"}
            </Button>
          </DialogActions>
        </form>
      </Dialog>
    </Stack>
  );
}
