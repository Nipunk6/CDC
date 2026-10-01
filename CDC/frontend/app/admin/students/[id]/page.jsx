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

import PageHeader from "@/components/shared/pageheader";
import StudentFormDialog from "@/components/admin/studentformdialog";
import StudentBlocksPanel from "@/components/admin/studentblockspanel";
import { adminApi } from "@/lib/adminapi";
import { adminBlobUrl } from "@/lib/adminupload";
import { dash, formatDate, formatDateTime, formatMoney, statusColor, titleCase } from "@/lib/format";

const profileFields = [
  ["Roll number", "roll_no"],
  ["Institute email", "institute_email"],
  ["Personal email", "personal_email"],
  ["Phone", "phone"],
  ["Programme", "programme"],
  ["Branch", "branch"],
  ["Graduating batch", "graduating_batch"],
  ["CGPA", "current_cgpa"],
  ["Ongoing backlogs", "ongoing_backlogs"],
  ["Total backlogs", "total_backlogs"],
  ["Gender", "gender"],
  ["Date of birth", "date_of_birth"],
  ["10th %", "tenth_percent"],
  ["12th %", "twelfth_percent"],
  ["Category", "category"],
  ["PwD", "pwd"],
  ["Home state", "home_state"],
  ["LinkedIn", "linkedin_url"],
  ["GitHub", "github_url"],
];

const show = (value) => (typeof value === "boolean" ? (value ? "Yes" : "No") : dash(value));

export default function AdminStudentDetailPage({ params }) {
  const { id } = use(params);
  const [student, setStudent] = useState(null);
  const [auditLogs, setAuditLogs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [tab, setTab] = useState(0);
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
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to resend the invitation.");
    }
  };

  if (loading) return <LinearProgress />;
  if (!student) return <Alert severity="error">{error ?? "Student not found."}</Alert>;

  const applications = student.applications ?? [];
  const offers = student.offers ?? [];

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
            <Button variant="contained" color="secondary" startIcon={<MailIcon />} onClick={resendInvite}>
              Resend Invite
            </Button>
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

      <Card>
        <Tabs value={tab} onChange={(_e, value) => setTab(value)} variant="scrollable" allowScrollButtonsMobile>
          <Tab label="Overview" />
          <Tab label={`Cycles (${student.cycle_enrollments?.length ?? 0})`} />
          <Tab label={`Applications (${applications.length})`} />
          <Tab label="Offers & Blocks" />
          <Tab label="Audit trail" />
        </Tabs>
        <CardContent>
          {tab === 0 && (
            <Stack direction={{ xs: "column", md: "row" }} spacing={3}>
              <Box sx={{ textAlign: "center" }}>
                <Avatar src={photoUrl ?? undefined} sx={{ width: 120, height: 120, mx: "auto", fontSize: 40 }}>
                  {student.full_name?.[0]}
                </Avatar>
              </Box>
              <Grid container spacing={2} sx={{ flex: 1 }}>
                {profileFields.map(([label, key]) => (
                  <Grid key={key} size={{ xs: 12, sm: 6, md: 4 }}>
                    <Typography variant="caption" color="text.secondary">
                      {label}
                    </Typography>
                    <Typography variant="body2" sx={{ wordBreak: "break-word" }}>
                      {key === "date_of_birth" ? formatDate(student[key]) : show(student[key])}
                    </Typography>
                  </Grid>
                ))}
              </Grid>
            </Stack>
          )}

          {tab === 1 && (
            <TableContainer>
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>Cycle</TableCell>
                    <TableCell>Type</TableCell>
                    <TableCell>Cycle status</TableCell>
                    <TableCell>Enrolment</TableCell>
                    <TableCell>Enrolled on</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {(student.cycle_enrollments ?? []).length === 0 && (
                    <TableRow>
                      <TableCell colSpan={5}>Not enrolled in any placement cycle.</TableCell>
                    </TableRow>
                  )}
                  {(student.cycle_enrollments ?? []).map((enrollment) => (
                    <TableRow key={enrollment.id}>
                      <TableCell>
                        <Link href={`/admin/placement-cycles/${enrollment.placement_cycle?.id}`}>
                          {enrollment.placement_cycle?.name}
                        </Link>
                      </TableCell>
                      <TableCell>{titleCase(enrollment.placement_cycle?.type)}</TableCell>
                      <TableCell>
                        <Chip size="small" variant="outlined" color={statusColor(enrollment.placement_cycle?.status)} label={titleCase(enrollment.placement_cycle?.status)} />
                      </TableCell>
                      <TableCell>
                        <Chip size="small" variant="outlined" color={statusColor(enrollment.status)} label={titleCase(enrollment.status)} />
                      </TableCell>
                      <TableCell>{formatDate(enrollment.created_at)}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          )}

          {tab === 2 && (
            <TableContainer>
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>Posting</TableCell>
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
                        <Chip size="small" variant="outlined" color={statusColor(application.status)} label={titleCase(application.status)} />
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

          {tab === 3 && (
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
                          <TableCell>Cycle</TableCell>
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

          {tab === 4 && (
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
