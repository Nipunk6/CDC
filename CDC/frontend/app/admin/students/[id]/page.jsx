"use client";

import { use, useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Avatar,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  Divider,
  Grid2 as Grid,
  LinearProgress,
  Stack,
  Tab,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Tabs,
  Typography,
} from "@mui/material";
import EditIcon from "@mui/icons-material/Edit";
import PersonIcon from "@mui/icons-material/Person";
import BlockIcon from "@mui/icons-material/Block";
import CheckCircleIcon from "@mui/icons-material/CheckCircle";
import MailIcon from "@mui/icons-material/Mail";

import StudentCategoryChips from "@/components/admin/student/studentcategorychips";
import PageHeader from "@/components/shared/pageheader";
import StudentFormDialog from "@/components/admin/studentformdialog";
import StudentBlocksPanel from "@/components/admin/studentblockspanel";
import StudentPlacements from "@/components/admin/student/studentplacements";
import StudentResumes from "@/components/admin/student/studentresumes";
import StudentNotes from "@/components/admin/student/studentnotes";
import { adminApi } from "@/lib/adminapi";
import { adminBlobUrl } from "@/lib/adminupload";
import { academicExtraFields, formatAcademicExtra } from "@/lib/academicextras";
import { dash, formatDate, formatDateTime, formatMoney, statusColor, titleCase } from "@/lib/format";

const profileFields = [
  ["Roll number", "roll_no"],
  ["Institute email", "institute_email"],
  ["Personal email", "personal_email"],
  ["Contact No.", "phone"],
  ["Programme", "programme"],
  ["Branch", "branch"],
  ["Passout Batch", "graduating_batch"],
  ["CGPA", "current_cgpa"],
  ["Ongoing backlogs", "ongoing_backlogs"],
  ["Total backlogs", "total_backlogs"],
  ["Gender", "gender"],
  ["Date of birth", "date_of_birth"],
  ["Class X Percentage", "tenth_percent"],
  ["Class XII Percentage", "twelfth_percent"],
  ["Social Category", "category"],
  ["PwD", "pwd"],
  ["Home state", "home_state"],
  ["LinkedIn", "linkedin_url"],
  ["GitHub", "github_url"],
];

const show = (value) => (typeof value === "boolean" ? (value ? "Yes" : "No") : dash(value));

const fieldGrid = (fields, render) => (
  <Grid container spacing={2}>
    {fields.map(([label, key]) => (
      <Grid key={key} size={{ xs: 12, sm: 6, md: 4 }}>
        <Typography variant="caption" color="text.secondary">
          {label}
        </Typography>
        <Typography variant="body2" sx={{ wordBreak: "break-word" }}>
          {render(key)}
        </Typography>
      </Grid>
    ))}
  </Grid>
);

const Stat = ({ label, value }) => (
  <Box sx={{ textAlign: "center", minWidth: 72 }}>
    <Typography variant="h5" fontWeight={700}>
      {value}
    </Typography>
    <Typography variant="caption" color="text.secondary">
      {label}
    </Typography>
  </Box>
);

export default function AdminStudentDetailPage({ params }) {
  const { id } = use(params);
  const [student, setStudent] = useState(null);
  const [auditLogs, setAuditLogs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [tab, setTab] = useState("overview");
  const [editOpen, setEditOpen] = useState(false);
  const [photoUrl, setPhotoUrl] = useState(null);

  const load = useCallback(async () => {
    try {
      const response = await adminApi(`/admin/students/${id}`);
      setStudent(response.student);
      setAuditLogs(response.audit_logs ?? []);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load this student.");
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    if (!student?.has_photo) return undefined;
    let url = null;
    adminBlobUrl(`/admin/students/${id}/photo`)
      .then((value) => {
        url = value;
        setPhotoUrl(value);
      })
      .catch(() => setPhotoUrl(null));
    return () => {
      if (url) URL.revokeObjectURL(url);
    };
  }, [id, student?.has_photo]);

  const toggleActive = async () => {
    const action = student.is_active ? "suspend" : "reactivate";
    if (student.is_active && !window.confirm(`Suspend ${student.roll_no}?`)) return;
    try {
      const response = await adminApi(`/admin/students/${id}/${action}`, { method: "PATCH" });
      setSuccess(response.message);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to update the account.");
    }
  };

  const resendInvite = async () => {
    try {
      const response = await adminApi(`/admin/students/${id}/resend-invitation`, { method: "POST" });
      setSuccess(response.message);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to resend the invitation.");
    }
  };

  // S5.4: cancels the set-password link; refused by the server once the student has activated their account.
  const revokeInvite = async () => {
    if (!window.confirm(`Revoke the invitation of ${student.roll_no}? Their set-password link stops working until you resend it.`)) return;
    try {
      const response = await adminApi(`/admin/students/${id}/revoke-invitation`, { method: "POST" });
      setSuccess(response.message);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to revoke the invitation.");
    }
  };

  if (loading) return <LinearProgress />;
  if (!student) return <Alert severity="error">{error ?? "Student not found."}</Alert>;

  const applications = student.applications ?? [];
  const offers = student.offers ?? [];
  const summary = student.summary ?? {};

  return (
    <>
      <PageHeader
        icon={<PersonIcon />}
        title={student.full_name}
        subtitle={`${student.roll_no} · ${student.branch} · ${student.graduating_batch}`}
        backHref="/admin/students"
        backLabel="All Students"
        actions={
          <>
            <Button variant="contained" color="secondary" startIcon={<EditIcon />} onClick={() => setEditOpen(true)}>
              Edit
            </Button>
            {student.invitation_status !== "accepted" && (
              <Button variant="contained" color="secondary" startIcon={<MailIcon />} onClick={resendInvite}>
                Resend Invite
              </Button>
            )}
            {student.invitation_status === "sent" && (
              <Button variant="contained" color="secondary" startIcon={<BlockIcon />} onClick={revokeInvite}>
                Revoke Invite
              </Button>
            )}
            <Button
              variant="contained"
              color={student.is_active ? "error" : "success"}
              startIcon={student.is_active ? <BlockIcon /> : <CheckCircleIcon />}
              onClick={toggleActive}
            >
              {student.is_active ? "Suspend" : "Reactivate"}
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
      {!student.is_active && (
        <Alert severity="error" sx={{ mb: 2 }}>
          This account is suspended. The student cannot log in.
        </Alert>
      )}
      {student.invitation_status === "sent" && (
        <Alert severity="info" sx={{ mb: 2 }}>
          Invitation sent{student.last_invited_at ? ` on ${formatDateTime(student.last_invited_at)}` : ""}. The student has not set a password yet.
        </Alert>
      )}
      {student.invitation_status === "revoked" && (
        <Alert severity="warning" sx={{ mb: 2 }}>
          Invitation revoked. The set-password link no longer works; use Resend Invite to send a new one.
        </Alert>
      )}

      {/* Summary card (Superset parity S4.5): photo, batch, CGPA, Applications and Offers. */}
      <Card sx={{ mb: 2 }}>
        <CardContent>
          <Stack direction={{ xs: "column", sm: "row" }} spacing={2} alignItems={{ xs: "flex-start", sm: "center" }} justifyContent="space-between">
            <Stack direction="row" spacing={2} alignItems="center" sx={{ minWidth: 0 }}>
              <Avatar src={photoUrl ?? undefined} sx={{ width: 72, height: 72, fontSize: 28 }}>
                {student.full_name?.[0]}
              </Avatar>
              <Box sx={{ minWidth: 0 }}>
                <Typography variant="h6" fontWeight={700} sx={{ wordBreak: "break-word" }}>
                  {student.full_name}
                </Typography>
                <Typography variant="body2" color="text.secondary">
                  {student.graduating_batch} Passout Batch | {student.roll_no}
                </Typography>
                <Typography variant="body2" color="text.secondary" sx={{ wordBreak: "break-word" }}>
                  {student.current_semester ? `Semester ${student.current_semester}, ` : ""}
                  {student.programme} · {student.branch}
                </Typography>
                <StudentCategoryChips studentId={student.id} />
              </Box>
            </Stack>
            <Stack direction="row" spacing={3} divider={<Divider orientation="vertical" flexItem />}>
              <Stat label="CGPA" value={dash(summary.cgpa ?? student.current_cgpa)} />
              <Stat label="Applications" value={summary.applications_count ?? 0} />
              <Stat label="Offers" value={summary.offers_count ?? offers.length} />
            </Stack>
          </Stack>
        </CardContent>
      </Card>

      <Card>
        <Tabs value={tab} onChange={(_e, value) => setTab(value)} variant="scrollable" allowScrollButtonsMobile>
          <Tab value="overview" label="Overview" />
          <Tab value="placements" label={`Placements (${student.placements?.length ?? 0})`} />
          <Tab value="applications" label={`Applications (${applications.length})`} />
          <Tab value="offers" label="Offers & Blocks" />
          <Tab value="resumes" label={`Resumes & Documents (${student.resumes?.length ?? 0})`} />
          <Tab value="notes" label="Notes" />
          <Tab value="activity" label="Activity" />
        </Tabs>
        <CardContent>
          {tab === "overview" && (
            <Stack spacing={3}>
              {fieldGrid(profileFields, (key) => (key === "date_of_birth" ? formatDate(student[key]) : show(student[key])))}
              <Box>
                <Typography variant="subtitle1" fontWeight={700} gutterBottom>
                  Academic Details
                </Typography>
                {fieldGrid(academicExtraFields, (key) => formatAcademicExtra(student, key))}
              </Box>
            </Stack>
          )}

          {tab === "placements" && <StudentPlacements student={student} />}

          {tab === "applications" && (
            <TableContainer>
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>Job Profile</TableCell>
                    <TableCell>Company</TableCell>
                    <TableCell>Status</TableCell>
                    <TableCell>Flags</TableCell>
                    <TableCell>Applied</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {applications.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={5}>No applications yet.</TableCell>
                    </TableRow>
                  )}
                  {applications.map((application) => (
                    <TableRow key={application.id}>
                      <TableCell>
                        <Link href={`/admin/postings/${application.job_posting_id}`}>{application.title}</Link>
                      </TableCell>
                      <TableCell>{application.company_name}</TableCell>
                      <TableCell>
                        <Chip size="small" variant="outlined" color={statusColor(application.status)} label={application.status === "waitlisted" ? "On Hold" : titleCase(application.status)} />
                      </TableCell>
                      <TableCell>
                        <Stack direction="row" spacing={0.5}>
                          {application.used_unverified_resume && <Chip size="small" color="warning" label="Unverified resume" />}
                          {application.placed_elsewhere_flag && <Chip size="small" color="error" label="Placed elsewhere" />}
                        </Stack>
                      </TableCell>
                      <TableCell>{formatDateTime(application.applied_at)}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          )}

          {tab === "offers" && (
            <Stack spacing={3}>
              <Box>
                <Typography variant="subtitle1" fontWeight={700} gutterBottom>
                  Offers
                </Typography>
                {offers.length === 0 ? (
                  <Typography color="text.secondary">No offers.</Typography>
                ) : (
                  <TableContainer>
                    <Table size="small">
                      <TableHead>
                        <TableRow>
                          <TableCell>Company</TableCell>
                          <TableCell>Placement</TableCell>
                          <TableCell>Type</TableCell>
                          <TableCell>CTC / Stipend</TableCell>
                          <TableCell>Announced</TableCell>
                        </TableRow>
                      </TableHead>
                      <TableBody>
                        {offers.map((offer) => (
                          <TableRow key={offer.id}>
                            <TableCell>{offer.company_name}</TableCell>
                            <TableCell>{offer.cycle_name}</TableCell>
                            <TableCell>{offer.label ?? titleCase(offer.offer_type)}</TableCell>
                            <TableCell>
                              {offer.ctc_annual ? `${formatMoney(offer.ctc_annual, offer.currency)} p.a.` : ""}
                              {offer.stipend_monthly ? ` ${formatMoney(offer.stipend_monthly, offer.currency)}/month` : ""}
                            </TableCell>
                            <TableCell>{formatDate(offer.announced_at)}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </TableContainer>
                )}
              </Box>
              <StudentBlocksPanel student={student} onChanged={load} />
            </Stack>
          )}

          {tab === "resumes" && <StudentResumes student={student} onChanged={load} />}

          {tab === "notes" && <StudentNotes studentId={student.id} />}

          {tab === "activity" && (
            <TableContainer>
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>When</TableCell>
                    <TableCell>Admin</TableCell>
                    <TableCell>Action</TableCell>
                    <TableCell>Before → After</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {auditLogs.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={4}>No admin changes recorded.</TableCell>
                    </TableRow>
                  )}
                  {auditLogs.map((log) => (
                    <TableRow key={log.id}>
                      <TableCell sx={{ whiteSpace: "nowrap" }}>{formatDateTime(log.created_at)}</TableCell>
                      <TableCell>{log.user?.name ?? log.actor_name ?? "System"}</TableCell>
                      <TableCell>
                        <Chip size="small" variant="outlined" label={log.action} />
                      </TableCell>
                      <TableCell sx={{ fontFamily: "monospace", fontSize: 12, wordBreak: "break-word" }}>
                        {log.before ? JSON.stringify(log.before) : "—"} → {log.after ? JSON.stringify(log.after) : "—"}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          )}
        </CardContent>
      </Card>

      <StudentFormDialog
        open={editOpen}
        student={student}
        onClose={() => setEditOpen(false)}
        onSaved={(response) => {
          setEditOpen(false);
          setSuccess(response.message);
          void load();
        }}
      />
    </>
  );
}
