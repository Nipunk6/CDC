"use client";

import { useState } from "react";
import {
  Alert,
  Box,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControl,
  IconButton,
  InputLabel,
  MenuItem,
  Select,
  Stack,
  Typography,
  alpha,
} from "@mui/material";
import SchoolIcon from "@mui/icons-material/School";
import WarningAmberIcon from "@mui/icons-material/WarningAmber";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";

interface GraduatingBatchDialogProps {
  open: boolean;
  onConfirm: (batch: string) => void;
  onBack?: () => void;
  initialBatch?: string;
  formType?: "JNF" | "INF";
}

export default function GraduatingBatchDialog({
  open,
  onConfirm,
  onBack,
  initialBatch = "",
  formType = "JNF",
}: GraduatingBatchDialogProps) {
  const [selectedBatch, setSelectedBatch] = useState<string>(initialBatch);

  const currentYear = new Date().getFullYear();
  const yearOptions = Array.from({ length: 4 }, (_, i) =>
    (currentYear + i).toString()
  );

  const handleConfirm = () => {
    if (selectedBatch) {
      onConfirm(selectedBatch);
    }
  };

  return (
    <Dialog
      open={open}
      maxWidth="sm"
      fullWidth
      disableEscapeKeyDown
      onClose={(_event, reason) => {
        // Prevent closing by backdrop click
        if (reason === "backdropClick" || reason === "escapeKeyDown") {
          return;
        }
      }}
      PaperProps={{
        sx: {
          borderRadius: 3,
          overflow: "hidden",
        },
      }}
    >
      {/* Header */}
      <DialogTitle
        sx={{
          background: (theme) =>
            `linear-gradient(135deg, ${theme.palette.primary.main} 0%, ${theme.palette.primary.dark} 100%)`,
          color: "white",
          display: "flex",
          alignItems: "center",
          gap: 1.5,
          py: 2.5,
          px: 3,
          position: "relative",
        }}
      >
        <SchoolIcon />
        <Box sx={{ pr: onBack ? 16 : 0 }}>
          <Typography variant="h6" fontWeight={700}>
            Select Passout Batch
          </Typography>
          <Typography variant="body2" sx={{ opacity: 0.9 }}>
            {formType === "JNF"
              ? "Job Notification Form"
              : "Internship Notification Form"}
          </Typography>
        </Box>
        {onBack && (
          <Button
            onClick={onBack}
            startIcon={<ArrowBackIcon />}
            sx={{
              position: "absolute",
              right: 16,
              top: "50%",
              transform: "translateY(-50%)",
              color: "white",
              textTransform: "none",
              fontWeight: 600,
              fontSize: "0.875rem",
              borderRadius: 2,
              px: 1.5,
              py: 0.75,
              border: "1px solid rgba(255, 255, 255, 0.3)",
              "&:hover": {
                bgcolor: (theme) => alpha(theme.palette.common.white, 0.15),
                borderColor: "white",
              },
            }}
          >
            Go Back
          </Button>
        )}
      </DialogTitle>

      <DialogContent sx={{ pt: 3, pb: 1 }}>
        <Stack spacing={3} sx={{ mt: 1 }}>
          <Typography variant="body1" color="text.secondary">
            Please select the passout batch you are hiring for. This will
            apply to all eligible programmes in this {formType}.
          </Typography>

          <FormControl fullWidth>
            <InputLabel id="graduating-batch-select-label">
              Passout Batch (Year) *
            </InputLabel>
            <Select
              labelId="graduating-batch-select-label"
              value={selectedBatch}
              label="Passout Batch (Year) *"
              onChange={(e) => setSelectedBatch(e.target.value)}
              sx={{
                fontSize: "1.1rem",
                "& .MuiSelect-select": {
                  py: 1.5,
                },
              }}
            >
              {yearOptions.map((year) => (
                <MenuItem key={year} value={year}>
                  <Stack direction="row" spacing={1} alignItems="center">
                    <SchoolIcon fontSize="small" color="primary" />
                    <Typography fontWeight={500}>
                      Batch of {year}
                    </Typography>
                  </Stack>
                </MenuItem>
              ))}
            </Select>
          </FormControl>

          {/* Warning about irreversible choice */}
          <Alert
            severity="warning"
            icon={<WarningAmberIcon />}
            sx={{
              bgcolor: (theme) => alpha(theme.palette.warning.main, 0.1),
              border: "1px solid",
              borderColor: "warning.light",
              "& .MuiAlert-message": { width: "100%" },
            }}
          >
            <Typography variant="subtitle2" fontWeight={700} gutterBottom>
              This choice cannot be changed later
            </Typography>
            <Typography variant="body2" color="text.secondary">
              Once you proceed, the passout batch for this {formType} will be
              locked and cannot be modified. If you need to hire for a different
              batch, you will need to create a new {formType}.
            </Typography>
          </Alert>
        </Stack>
      </DialogContent>

      <DialogActions sx={{ px: 3, py: 2.5 }}>
        <Button
          variant="contained"
          size="large"
          onClick={handleConfirm}
          disabled={!selectedBatch}
          fullWidth
          sx={{
            py: 1.5,
            fontWeight: 600,
            fontSize: "1rem",
          }}
        >
          Confirm & Continue
        </Button>
      </DialogActions>
    </Dialog>
  );
}
