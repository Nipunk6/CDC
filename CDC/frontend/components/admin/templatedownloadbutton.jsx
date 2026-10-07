"use client";

import { useState } from "react";
import {
  Alert,
  Autocomplete,
  Button,
  ButtonGroup,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  ListSubheader,
  Menu,
  MenuItem,
  TextField,
  Typography,
} from "@mui/material";
import DownloadIcon from "@mui/icons-material/Download";
import ArrowDropDownIcon from "@mui/icons-material/ArrowDropDown";

import { adminApi, adminDownload } from "@/lib/adminapi";

/**
 * A download with "Excel - Default Template" / "Excel - Custom Template" (Superset parity S3.5). The custom option
 * opens "Select Custom Template" and calls `${path}?template=<id>`.
 */
export default function TemplateDownloadButton({ label, path, fileName, onError, variant = "contained", color = "secondary", size }) {
  const [anchor, setAnchor] = useState(null);
  const [picking, setPicking] = useState(false);
  const [templates, setTemplates] = useState(null);
  const [choice, setChoice] = useState(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  const download = async (template) => {
    setBusy(true);
    try {
      await adminDownload(template ? `${path}${path.includes("?") ? "&" : "?"}template=${template.id}` : path, fileName);
      setPicking(false);
    } catch (e) {
      const message = e instanceof Error ? e.message : "Download failed.";
      if (template) setError(message);
      else onError?.(message);
    } finally {
      setBusy(false);
    }
  };

  const openPicker = async () => {
    setAnchor(null);
    setChoice(null);
    setError(null);
    setPicking(true);
    try {
      const response = await adminApi("/admin/export-templates");
      setTemplates(response.templates);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not load templates.");
    }
  };

  return (
    <>
      <ButtonGroup variant={variant} color={color} size={size} disabled={busy}>
        <Button startIcon={<DownloadIcon />} onClick={() => download(null)}>
          {label}
        </Button>
        <Button aria-label={`${label} options`} onClick={(e) => setAnchor(e.currentTarget)} sx={{ px: 0.5, minWidth: 0 }}>
          <ArrowDropDownIcon />
        </Button>
      </ButtonGroup>
      <Menu anchorEl={anchor} open={Boolean(anchor)} onClose={() => setAnchor(null)}>
        <ListSubheader sx={{ lineHeight: 2.5 }}>{label}</ListSubheader>
        <MenuItem
          onClick={() => {
            setAnchor(null);
            void download(null);
          }}
        >
          Excel - Default Template
        </MenuItem>
        <MenuItem onClick={openPicker}>Excel - Custom Template</MenuItem>
      </Menu>

      <Dialog open={picking} onClose={() => !busy && setPicking(false)} maxWidth="xs" fullWidth>
        <DialogTitle>Select Custom Template</DialogTitle>
        <DialogContent>
          {error && (
            <Alert severity="error" sx={{ mb: 2 }}>
              {error}
            </Alert>
          )}
          {templates?.length === 0 ? (
            <Typography variant="body2" color="text.secondary" sx={{ pt: 1 }}>
              There are no templates yet. Create one under Reports → Excel Templates.
            </Typography>
          ) : (
            <Autocomplete
              sx={{ pt: 1 }}
              options={templates ?? []}
              loading={!templates}
              getOptionLabel={(t) => t.name}
              isOptionEqualToValue={(a, b) => a.id === b.id}
              value={choice}
              onChange={(_e, t) => setChoice(t)}
              renderInput={(p) => <TextField {...p} label="Select template" placeholder="Select an Option" />}
            />
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setPicking(false)} disabled={busy}>
            Cancel
          </Button>
          <Button variant="contained" color="success" disabled={!choice || busy} onClick={() => download(choice)}>
            {busy ? "Preparing..." : "Download Excel File"}
          </Button>
        </DialogActions>
      </Dialog>
    </>
  );
}
