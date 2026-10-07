"use client";

import { Suspense, useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import {
  Alert,
  Avatar,
  Box,
  Button,
  Card,
  Chip,
  IconButton,
  LinearProgress,
  Link as MuiLink,
  Pagination,
  Stack,
  Switch,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Tooltip,
  Typography,
} from "@mui/material";
import AddIcon from "@mui/icons-material/Add";
import MailOutlineIcon from "@mui/icons-material/MailOutline";
import SchoolIcon from "@mui/icons-material/School";
import UploadFileIcon from "@mui/icons-material/UploadFile";
import SyncIcon from "@mui/icons-material/Sync";
import VisibilityIcon from "@mui/icons-material/Visibility";

import PageHeader from "@/components/shared/pageheader";
import StudentFormDialog from "@/components/admin/studentformdialog";
import SpreadsheetImportDialog from "@/components/admin/spreadsheetimportdialog";
import StudentFilterPanel from "@/components/admin/studentfilterpanel";
import StudentQuickView from "@/components/admin/studentquickview";
import TemplateDownloadButton from "@/components/admin/templatedownloadbutton";
import PendingRequestsBanner from "@/components/admin/pendingrequestsbanner";
import { initials } from "@/components/admin/studentsearch";
import { adminApi } from "@/lib/adminapi";
import useCatalogue, { shortProgramme } from "@/lib/usecatalogue";
import { dash } from "@/lib/format";
import { filtersFromParams, filtersToQuery, hasFilters } from "@/lib/studentfilters";

const emptyMeta = { current_page: 1, last_page: 1, per_page: 50, total: 0 };
const thisYear = new Date().getFullYear();
const IMPORT_BATCHES = Array.from({ length: 9 }, (_v, i) => thisYear - 3 + i);

export default function AdminStudentsPage() {
  return (
    <Suspense fallback={<LinearProgress />}>
      <StudentsDirectory />
    </Suspense>
  );
}

// Filters and page live in the URL (S4.1), so a filtered list can be bookmarked or shared.
function StudentsDirectory() {
  const router = useRouter();
  const params = useSearchParams();
  const queryString = params.toString();
  const applied = useMemo(() => filtersFromParams(new URLSearchParams(queryString)), [queryString]);
  const page = Math.max(1, Number(new URLSearchParams(queryString).get("page")) || 1);
  const filtered = hasFilters(applied);

  const catalogue = useCatalogue(adminApi);
  const [cycles, setCycles] = useState(null);
  const [reloadTick, setReloadTick] = useState(0);
  const [result, setResult] = useState({ key: null, students: [], meta: emptyMeta, summary: null });
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [quickView, setQuickView] = useState(null);

  const [addOpen, setAddOpen] = useState(false);
  const [importOpen, setImportOpen] = useState(false);
  const [academicOpen, setAcademicOpen] = useState(false);

  const requestKey = `${queryString}#${reloadTick}`;
  const loading = result.key !== requestKey;
  const { students, meta, summary } = result;
  const apiQuery = filtersToQuery(applied, { api: true });
  const countLabel =
    result.key === null
      ? "Loading students..."
      : `${meta.total} ${filtered ? "filtered " : ""}${meta.total === 1 ? "student" : "students"}`;

  useEffect(() => {
    let cancelled = false;
    adminApi(`/admin/students?${filtersToQuery(applied, { api: true, extra: { page } })}`)
      .then((response) => {
        if (cancelled) return;
        setResult({
          key: requestKey,
          students: response.students ?? [],
          meta: response.meta ?? emptyMeta,
          summary: response.invitation_summary ?? null,
        });
        setError(null);
      })
      .catch((e) => {
        if (cancelled) return;
        setResult((prev) => ({ ...prev, key: requestKey }));
        setError(e instanceof Error ? e.message : "Failed to load students.");
      });
    return () => {
      cancelled = true;
    };
  }, [applied, page, requestKey]);

  useEffect(() => {
    adminApi("/admin/placement-cycles")
      .then((response) => setCycles(response.placement_cycles ?? []))
      .catch(() => setCycles([]));
  }, []);

  const navigate = useCallback(
    (filters, targetPage = 1) => {
      const query = filtersToQuery(filters, { extra: { page: targetPage > 1 ? targetPage : "" } });
      router.replace(query ? `/admin/students?${query}` : "/admin/students", { scroll: false });
    },
    [router]
  );

  const reload = () => setReloadTick((tick) => tick + 1);

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
      setResult((prev) => ({
        ...prev,
        students: prev.students.map((s) => (s.id === student.id ? { ...s, is_active: !s.is_active } : s)),
      }));
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to update the account.");
    }
  };

  return (
    <>
      <PageHeader
        icon={<SchoolIcon />}
        title="Students"
        subtitle={
          summary
            ? `${summary.registered} students registered · total ${summary.invited} students invited`
            : countLabel
        }
        backHref="/admin"
        backLabel="Back to Dashboard"
        actions={
          <>
            <Button variant="contained" color="secondary" startIcon={<AddIcon />} onClick={() => setAddOpen(true)}>
              Add Student
            </Button>
            <Button variant="contained" color="secondary" startIcon={<MailOutlineIcon />} component={Link} href="/admin/students/invitations">
              Invitations
            </Button>
            <Tooltip title="Upload Student CSV for Student Invitations">
              <Button variant="contained" color="secondary" startIcon={<UploadFileIcon />} onClick={() => setImportOpen(true)}>
                Import
              </Button>
            </Tooltip>
            <Button variant="contained" color="secondary" startIcon={<SyncIcon />} onClick={() => setAcademicOpen(true)}>
              Update Academics
            </Button>
          </>
        }
      />

      <PendingRequestsBanner />

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

      <StudentFilterPanel
        key={queryString.replace(/(^|&)page=\d+/, "")}
        initial={applied}
        onApply={(filters) => navigate(filters)}
        catalogue={catalogue}
        statusLabel="Account"
        statusOptions={[
          ["active", "Active"],
          ["suspended", "Suspended"],
        ]}
        cycles={cycles}
        actions={
          <TemplateDownloadButton
            label="Download as Excel"
            path={apiQuery ? `/admin/students/export?${apiQuery}` : "/admin/students/export"}
            fileName="students.xlsx"
            onError={setError}
            variant="outlined"
            color="primary"
          />
        }
      />

      <Card>
        <Stack direction="row" alignItems="center" justifyContent="space-between" sx={{ px: 2, py: 1.5 }}>
          <Typography variant="subtitle1" fontWeight={700}>
            {countLabel}
          </Typography>
        </Stack>
        {loading && <LinearProgress />}
        <TableContainer>
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell>Name</TableCell>
                <TableCell>Roll Number</TableCell>
                <TableCell sx={{ display: { xs: "none", lg: "table-cell" } }}>Email</TableCell>
                <TableCell sx={{ display: { xs: "none", md: "table-cell" } }}>Mobile No.</TableCell>
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
                  <TableCell colSpan={11}>
                    <Typography color="text.secondary" sx={{ py: 3, textAlign: "center" }}>
                      {filtered ? "Could not find any students" : "No students yet. Add one, or import a spreadsheet."}
                    </Typography>
                  </TableCell>
                </TableRow>
              )}
              {students.map((student) => (
                <TableRow key={student.id} hover>
                  <TableCell>
                    <Stack direction="row" spacing={1} alignItems="center" sx={{ minWidth: 0 }}>
                      <Avatar sx={{ width: 28, height: 28, fontSize: 12, bgcolor: "primary.main", display: { xs: "none", sm: "flex" } }}>
                        {initials(student.full_name)}
                      </Avatar>
                      <Tooltip title="Quick view">
                        <MuiLink
                          component="button"
                          type="button"
                          underline="hover"
                          onClick={() => setQuickView(student.id)}
                          sx={{ fontWeight: 600, textAlign: "left" }}
                        >
                          {student.full_name}
                        </MuiLink>
                      </Tooltip>
                    </Stack>
                  </TableCell>
                  <TableCell sx={{ whiteSpace: "nowrap" }}>{student.roll_no}</TableCell>
                  <TableCell sx={{ display: { xs: "none", lg: "table-cell" }, wordBreak: "break-all" }}>{student.institute_email}</TableCell>
                  <TableCell sx={{ display: { xs: "none", md: "table-cell" }, whiteSpace: "nowrap" }}>{dash(student.phone)}</TableCell>
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
                    <Tooltip title="Open student page">
                      <IconButton component={Link} href={`/admin/students/${student.id}`} size="small" color="primary">
                        <VisibilityIcon fontSize="small" />
                      </IconButton>
                    </Tooltip>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </TableContainer>
        <Box sx={{ px: 2, py: 1.5, display: "flex", flexDirection: { xs: "column", sm: "row" }, gap: 1, alignItems: "center", justifyContent: "space-between" }}>
          <Typography variant="body2" color="text.secondary">
            Showing Page {meta.current_page} of {meta.last_page} ({meta.total} records)
          </Typography>
          {meta.last_page > 1 && (
            <Pagination
              count={meta.last_page}
              page={page}
              onChange={(_e, value) => navigate(applied, value)}
              color="primary"
              size="small"
              showFirstButton
              showLastButton
            />
          )}
        </Box>
      </Card>

      <StudentQuickView studentId={quickView} onClose={() => setQuickView(null)} />

      <StudentFormDialog
        open={addOpen}
        student={null}
        onClose={() => setAddOpen(false)}
        onSaved={(response) => {
          setAddOpen(false);
          setSuccess(response.message);
          reload();
        }}
      />

      <SpreadsheetImportDialog
        open={importOpen}
        onClose={() => setImportOpen(false)}
        onDone={() => {
          if (page === 1) reload();
          else navigate(applied);
        }}
        title="Bulk Student Invitations"
        description="One student per row. Columns are matched by their header names (our template, an older template, or Superset's sample CSV); a file without a header row must follow the template's order. Pick a batch to fill rows that leave Passout Batch blank. Check the file first — nothing is created until you confirm. Each new student receives an invitation email to their institute address (@iitism.ac.in)."
        endpoint="/admin/students/import"
        templatePath="/admin/students/import/template"
        templateName="student_import_template.xlsx"
        batchOptions={IMPORT_BATCHES}
        dryRun
      />

      <SpreadsheetImportDialog
        open={academicOpen}
        onClose={() => setAcademicOpen(false)}
        onDone={reload}
        title="Update CGPA & Backlogs"
        description="Columns: roll_no, current_cgpa, ongoing_backlogs, total_backlogs, plus the optional academic details in the template (semester, course dates, lateral entry, Class X / XII board and year, previous degree). Blank cells keep the current value. Every change is audit-logged."
        endpoint="/admin/students/academics/import"
        templatePath="/admin/students/academics/template"
        templateName="academic_update_template.xlsx"
      />
    </>
  );
}
