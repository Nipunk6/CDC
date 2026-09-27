import { useState } from "react";
import {
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  Stack,
  TextField,
  Alert,
} from "@mui/material";
import { useForm } from "react-hook-form";
import * as yup from "yup";
import { yupResolver } from "@hookform/resolvers/yup";
import { adminApi } from "@/lib/adminapi";

type AddAdminModalProps = {
  open: boolean;
  onClose: () => void;
  onSuccess: () => void;
};

type FormData = {
  name: string;
  email: string;
};

const schema = yup.object({
  name: yup.string().required("Name is required"),
  email: yup.string().email("Must be a valid email").required("Email is required"),
});

export default function AddAdminModal({ open, onClose, onSuccess }: AddAdminModalProps) {
  const [error, setError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({
    resolver: yupResolver(schema),
  });

  const onSubmit = async (data: FormData) => {
    try {
      setError(null);
      await adminApi("/admin/manage-admins", {
        method: "POST",
        body: JSON.stringify(data),
      });
      reset();
      onSuccess();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to create admin");
    }
  };

  const handleClose = () => {
    reset();
    setError(null);
    onClose();
  };

  return (
    <Dialog open={open} onClose={handleClose} maxWidth="sm" fullWidth>
      <form onSubmit={handleSubmit(onSubmit)}>
        <DialogTitle>Add New Admin</DialogTitle>
        <DialogContent>
          <DialogContentText sx={{ mb: 3 }}>
            Enter the details for the new administrator. An email will be sent to them with a link to set their password.
          </DialogContentText>

          {error && (
            <Alert severity="error" sx={{ mb: 3 }}>
              {error}
            </Alert>
          )}

          <Stack spacing={3}>
            <TextField
              label="Name"
              fullWidth
              {...register("name")}
              error={!!errors.name}
              helperText={errors.name?.message}
            />
            <TextField
              label="Email Address"
              type="email"
              fullWidth
              {...register("email")}
              error={!!errors.email}
              helperText={errors.email?.message}
            />
          </Stack>
        </DialogContent>
        <DialogActions sx={{ px: 3, pb: 3 }}>
          <Button onClick={handleClose} disabled={isSubmitting}>
            Cancel
          </Button>
          <Button type="submit" variant="contained" disabled={isSubmitting}>
            {isSubmitting ? "Adding..." : "Add Admin"}
          </Button>
        </DialogActions>
      </form>
    </Dialog>
  );
}
