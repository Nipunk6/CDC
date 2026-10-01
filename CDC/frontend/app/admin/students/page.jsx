"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Button,
  Card,
  Chip,
  FormControl,
  IconButton,
  InputLabel,
  LinearProgress,
  MenuItem,
  Pagination,
  Paper,
  Select,
  Stack,
  Switch,
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
import AddIcon from "@mui/icons-material/Add";
import SchoolIcon from "@mui/icons-material/School";
import SearchIcon from "@mui/icons-material/Search";
import UploadFileIcon from "@mui/icons-material/UploadFile";
import SyncIcon from "@mui/icons-material/Sync";
import VisibilityIcon from "@mui/icons-material/Visibility";

import PageHeader from "@/components/shared/pageheader";
import StudentFormDialog from "@/components/admin/studentformdialog";
import SpreadsheetImportDialog from "@/components/admin/spreadsheetimportdialog";
import { adminApi } from "@/lib/adminapi";
import useCatalogue, { shortProgramme } from "@/lib/usecatalogue";
import { dash } from "@/lib/format";

const emptyMeta = { current_page: 1, last_page: 1, per_page: 50, total: 0 };

export default function AdminStudentsPage() {
  const catalogue = useCatalogue(adminApi);
  const [students, setStudents] = useState([]);
  const [meta, setMeta] = useState(emptyMeta);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);

  const [filters, setFilters] = useState({ search: "", programme: "", branch: "", graduating_batch: "", status: "" });
  const [applied, setApplied] = useState(filters);
  const [page, setPage] = useState(1);

  const [addOpen, setAddOpen] = useState(false);
  const [importOpen, setImportOpen] = useState(false);
  const [academicOpen, setAcademicOpen] = useState(false);

  const load = useCallback(async (targetPage, current) => {
    setLoading(true);
    try {
      const query = new URLSearchParams({ page: String(targetPage) });
      Object.entries(current).forEach(([key, value]) => {
        if (String(value).trim()) query.set(key, String(value).trim());
      });
      const response = await adminApi(`/admin/students?${query.toString()}`);
      setStudents(response.students ?? []);
      setMeta(response.meta ?? emptyMeta);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load students.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load(page, applied);
  }, [load, page, applied]);

  const applyFilters = () => {
    setPage(1);
    setApplied({ ...filters });
  };

  const setFilter = (key) => (event) =>
    setFilters((prev) => ({ ...prev, [key]: event.target.value, ...(key === "programme" ? { branch: "" } : {}) }));

  const toggleActive = async (student) => {
    const action = student.is_active ? "suspend" : "reactivate";
    if (
      student.is_active &&
      !window.confirm(`Suspend ${student.roll_no}? They will be signed out and cannot log in until reactivated.`)
    ) {
      return;
    }
    try {
      const response = await adminApi(`/admin/students/${student.id}/${action}`, { method: "PATCH" });
      setSuccess(response.message);
      setStudents((prev) => prev.map((s) => (s.id === student.id ? { ...s, is_active: !s.is_active } : s)));
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to update the account.");
    }
  };

  const branches = filters.programme ? catalogue[filters.programme] ?? [] : [];

  return (
    <>
      <PageHeader
        icon={<SchoolIcon />}
        title="Students"
        subtitle={`${meta.total} student(s)`}
        backHref="/admin"
        backLabel="Back to Dashboard"
        actions={
          <>
            <Button variant="contained" color="secondary" startIcon={<AddIcon />} onClick={() => setAddOpen(true)}>
              Add Student
            </Button>
            <Button variant="contained" color="secondary" startIcon={<UploadFileIcon />} onClick={() => setImportOpen(true)}>
              Import
            </Button>
            <Button variant="contained" color="secondary" startIcon={<SyncIcon />} onClick={() => setAcademicOpen(true)}>
              Update Academics
            </Button>
          </>
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

      <Paper sx={{ p: 2, mb: 2 }}>
        <Stack direction={{ xs: "column", md: "row" }} spacing={1.5} alignItems={{ md: "center" }}>
          <TextField
            size="small"
            label="Search roll no, name or email"
            value={filters.search}
            onChange={setFilter("search")}
            onKeyDown={(event) => event.key === "Enter" && applyFilters()}
            sx={{ minWidth: { md: 260 } }}
          />
          <FormControl size="small" sx={{ minWidth: 180 }}>
            <InputLabel id="f-programme">Programme</InputLabel>
            <Select labelId="f-programme" label="Programme" value={filters.programme} onChange={setFilter("programme")}>
              <MenuItem value="">All</MenuItem>
              {Object.keys(catalogue).map((p) => (
                <MenuItem key={p} value={p}>
                  {p}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
          <FormControl size="small" sx={{ minWidth: 180 }} disabled={!filters.programme}>
            <InputLabel id="f-branch">Branch</InputLabel>
            <Select labelId="f-branch" label="Branch" value={filters.branch} onChange={setFilter("branch")}>
              <MenuItem value="">All</MenuItem>
              {branches.map((b) => (
                <MenuItem key={b} value={b}>
                  {b}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
          <TextField
            size="small"
            label="Batch"
            type="number"
            value={filters.graduating_batch}
            onChange={setFilter("graduating_batch")}
            sx={{ minWidth: 100, width: { md: 110 } }}
          />
          <FormControl size="small" sx={{ minWidth: 130 }}>
            <InputLabel id="f-status">Status</InputLabel>
            <Select labelId="f-status" label="Status" value={filters.status} onChange={setFilter("status")}>
              <MenuItem value="">All</MenuItem>
              <MenuItem value="active">Active</MenuItem>
              <MenuItem value="suspended">Suspended</MenuItem>
            </Select>
          </FormControl>
          <Button variant="contained" startIcon={<SearchIcon />} onClick={applyFilters}>
            Search
          </Button>
        </Stack>
      </Paper>

      <Card>
        {loading && <LinearProgress />}
        <TableContainer>
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell>Roll no</TableCell>
                <TableCell>Name</TableCell>
                <TableCell sx={{ display: { xs: "none", md: "table-cell" } }}>Programme</TableCell>
                <TableCell sx={{ display: { xs: "none", sm: "table-cell" } }}>Branch</TableCell>
                <TableCell>Batch</TableCell>
                <TableCell>CGPA</TableCell>
                <TableCell sx={{ display: { xs: "none", md: "table-cell" } }}>Backlogs</TableCell>
                <TableCell>Active</TableCell>
                <TableCell align="right">View</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {!loading && students.length === 0 && (
                <TableRow>
                  <TableCell colSpan={9}>
                    <Typography color="text.secondary" sx={{ py: 3, textAlign: "center" }}>
                      No students match. Add one, or import a spreadsheet.
                    </Typography>
                  </TableCell>
                </TableRow>
              )}
              {students.map((student) => (
                <TableRow key={student.id} hover>
                  <TableCell sx={{ fontWeight: 600 }}>{student.roll_no}</TableCell>
                  <TableCell>{student.full_name}</TableCell>
                  <TableCell sx={{ display: { xs: "none", md: "table-cell" } }}>{shortProgramme(student.programme)}</TableCell>
                  <TableCell sx={{ display: { xs: "none", sm: "table-cell" } }}>{student.branch}</TableCell>
                  <TableCell>{student.graduating_batch}</TableCell>
                  <TableCell>{dash(student.current_cgpa)}</TableCell>
                  <TableCell sx={{ display: { xs: "none", md: "table-cell" } }}>
                    {student.ongoing_backlogs} / {student.total_backlogs}
                  </TableCell>
                  <TableCell>
                    <Tooltip title={student.is_active ? "Suspend account" : "Reactivate account"}>
                      <Switch size="small" checked={student.is_active} onChange={() => toggleActive(student)} />
                    </Tooltip>
                    {!student.is_active && <Chip size="small" color="error" variant="outlined" label="Suspended" />}
                  </TableCell>
                  <TableCell align="right">
                    <IconButton component={Link} href={`/admin/students/${student.id}`} size="small" color="primary">
                      <VisibilityIcon fontSize="small" />
                    </IconButton>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </TableContainer>
        {meta.last_page > 1 && (
          <Stack alignItems="center" sx={{ py: 2 }}>
            <Pagination count={meta.last_page} page={page} onChange={(_e, value) => setPage(value)} color="primary" />
          </Stack>
        )}
      </Card>

      <StudentFormDialog
        open={addOpen}
        student={null}
        onClose={() => setAddOpen(false)}
        onSaved={(response) => {
          setAddOpen(false);
          setSuccess(response.message);
          void load(page, applied);
        }}
      />

      <SpreadsheetImportDialog
        open={importOpen}
        onClose={() => setImportOpen(false)}
        onDone={() => {
          if (page === 1) void load(1, applied);
          else setPage(1);
        }}
        title="Import Students"
        description="One student per row, columns in the template's order. Check the file first — nothing is created until you confirm. Each new student receives an invitation email."
        endpoint="/admin/students/import"
        templatePath="/admin/students/import/template"
        templateName="student_import_template.xlsx"
        dryRun
      />

      <SpreadsheetImportDialog
        open={academicOpen}
        onClose={() => setAcademicOpen(false)}
        onDone={() => void load(page, applied)}
        title="Update CGPA & Backlogs"
        description="Columns: roll_no, current_cgpa, ongoing_backlogs, total_backlogs. Blank cells keep the current value. Every change is audit-logged."
        endpoint="/admin/students/academics/import"
        templatePath="/admin/students/academics/template"
        templateName="academic_update_template.xlsx"
      />
    </>
  );
}
