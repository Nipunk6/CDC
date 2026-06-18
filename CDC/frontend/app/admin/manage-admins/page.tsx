"use client";

import { useEffect, useState, useRef } from "react";
import { useSession } from "next-auth/react";
import { useRouter } from "next/navigation";
import {
  Alert,
  Box,
  Button,
  Paper,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from "@mui/material";
import { PersonAdd, Delete } from "@mui/icons-material";
import { adminApi } from "@/lib/adminapi";
import AddAdminModal from "./addadminmodal";

type AdminUser = {
  id: number;
  name: string;
  email: string;
  is_super_admin: boolean;
};

export default function ManageAdminsPage() {
  const { data: session, status } = useSession();
  const router = useRouter();
  const [admins, setAdmins] = useState<AdminUser[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const hasFetched = useRef(false);

  useEffect(() => {
    if (status === "loading") return;

    if (!session?.user?.isSuperAdmin) {
      router.push("/admin");
      return;
    }

    if (!hasFetched.current) {
      hasFetched.current = true;
      fetchAdmins();
    }
  }, [session, status, router]);

  const fetchAdmins = async () => {
    try {
      setLoading(true);
      setError(null);
      const response = await adminApi<{ admins: AdminUser[] }>("/admin/manage-admins");
      setAdmins(response.admins);
    } catch (err: any) {
      setError(err.message || "Failed to load admins.");
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = async (adminId: number) => {
    if (!window.confirm("Are you sure you want to delete this admin?")) return;

    try {
      setError(null);
      setSuccess(null);
      await adminApi(`/admin/manage-admins/${adminId}`, { method: "DELETE" });
      setSuccess("Admin deleted successfully.");
      setAdmins((prev) => prev.filter((a) => a.id !== adminId));
    } catch (err: any) {
      setError(err.message || "Failed to delete admin.");
    }
  };

  if (loading) {
    return <Typography>Loading...</Typography>;
  }

  return (
    <Box sx={{ maxWidth: 1000, mx: "auto" }}>
      <Box sx={{ display: "flex", justifyContent: "space-between", mb: 4, alignItems: "center" }}>
        <Typography variant="h4" fontWeight={700}>
          Manage Admins
        </Typography>
        <Button
          variant="contained"
          startIcon={<PersonAdd />}
          onClick={() => setIsModalOpen(true)}
          sx={{ borderRadius: 2 }}
        >
          Add Admin
        </Button>
      </Box>

      {error && (
        <Alert severity="error" sx={{ mb: 3 }} onClose={() => setError(null)}>
          {error}
        </Alert>
      )}

      {success && (
        <Alert severity="success" sx={{ mb: 3 }} onClose={() => setSuccess(null)}>
          {success}
        </Alert>
      )}

      <TableContainer component={Paper} elevation={0} sx={{ border: "1px solid", borderColor: "divider" }}>
        <Table>
          <TableHead sx={{ bgcolor: "grey.50" }}>
            <TableRow>
              <TableCell sx={{ fontWeight: 600 }}>Name</TableCell>
              <TableCell sx={{ fontWeight: 600 }}>Email</TableCell>
              <TableCell sx={{ fontWeight: 600 }}>Role</TableCell>
              <TableCell align="right" sx={{ fontWeight: 600 }}>Actions</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {admins.map((admin) => (
              <TableRow key={admin.id}>
                <TableCell>{admin.name}</TableCell>
                <TableCell>{admin.email}</TableCell>
                <TableCell>
                  {admin.is_super_admin ? (
                    <Typography variant="body2" color="primary.main" fontWeight={600}>
                      Super Admin
                    </Typography>
                  ) : (
                    <Typography variant="body2" color="text.secondary">
                      Admin
                    </Typography>
                  )}
                </TableCell>
                <TableCell align="right">
                  {!admin.is_super_admin && String(admin.id) !== session?.user?.id && (
                    <Button
                      color="error"
                      size="small"
                      startIcon={<Delete />}
                      onClick={() => handleDelete(admin.id)}
                    >
                      Delete
                    </Button>
                  )}
                </TableCell>
              </TableRow>
            ))}
            {admins.length === 0 && (
              <TableRow>
                <TableCell colSpan={4} align="center" sx={{ py: 3 }}>
                  <Typography color="text.secondary">No admins found.</Typography>
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </TableContainer>

      <AddAdminModal
        open={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        onSuccess={() => {
          setIsModalOpen(false);
          setSuccess("Admin created successfully. An invitation email has been sent.");
          fetchAdmins();
        }}
      />
    </Box>
  );
}
