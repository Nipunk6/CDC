"use client";

import { useEffect, useMemo, useState } from "react";
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  IconButton,
  FormControl,
  InputLabel,
  MenuItem,
  Paper,
  Select,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import PlaylistAddIcon from "@mui/icons-material/PlaylistAdd";
import SchoolIcon from "@mui/icons-material/School";
import ToggleOnIcon from "@mui/icons-material/ToggleOn";
import ToggleOffIcon from "@mui/icons-material/ToggleOff";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";

import { adminApi } from "@/lib/adminapi";
import { defaultProgrammes } from "@/components/forms/shared";

type AdminCatalogueResponse = {
  custom_branches: Array<{
    programme: string;
    branches: Array<{
      id: number;
      branch_name: string;
      is_active: boolean;
    }>;
  }>;
  branch_states: Array<{
    programme: string;
    branches: Array<{
      branch_name: string;
      is_active: boolean;
    }>;
  }>;
};

const programmeOptions = defaultProgrammes.map((programme) => programme.programme);

export default function AdminProgrammeBranchesPage() {
  const [view, setView] = useState<"custom" | "existing">("custom");
  const [selectedProgramme, setSelectedProgramme] = useState(programmeOptions[0] ?? "");
  const [branchName, setBranchName] = useState("");
  const [saving, setSaving] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [customGroups, setCustomGroups] = useState<AdminCatalogueResponse["custom_branches"]>([]);
  const [existingGroups, setExistingGroups] = useState<AdminCatalogueResponse["branch_states"]>([]);

  const customCount = useMemo(
    () =>
      customGroups.reduce(
        (total, group) => total + (Array.isArray(group.branches) ? group.branches.length : 0),
        0
      ),
    [customGroups]
  );

  const existingCount = useMemo(
    () =>
      existingGroups.reduce(
        (total, group) => total + (Array.isArray(group.branches) ? group.branches.length : 0),
        0
      ),
    [existingGroups]
  );

  const loadBranches = async () => {
    setLoading(true);
    try {
      const response = await adminApi<AdminCatalogueResponse>("/admin/programme-branches");
      setCustomGroups(response.custom_branches ?? []);
      setExistingGroups(response.branch_states ?? []);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load branch catalogue.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadBranches();
  }, []);

  const handleAddBranch = async () => {
    if (!selectedProgramme || !branchName.trim()) {
      setError("Please select a programme and enter a branch name.");
      return;
    }

    setSaving(true);
    setError(null);
    setSuccess(null);

    try {
      await adminApi("/admin/programme-branches", {
        method: "POST",
        body: JSON.stringify({
          programme_name: selectedProgramme,
          branch_name: branchName.trim(),
        }),
      });
      setSuccess("Custom branch added successfully.");
      setBranchName("");
      await loadBranches();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to add custom branch.");
    } finally {
      setSaving(false);
    }
  };

  const handleDeleteCustomBranch = async (id: number, label: string) => {
    if (!window.confirm(`Delete ${label}?`)) {
      return;
    }

    setSaving(true);
    setError(null);
    setSuccess(null);

    try {
      await adminApi(`/admin/programme-branches/${id}`, {
        method: "DELETE",
      });
      setSuccess("Custom branch deleted successfully.");
      await loadBranches();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to delete custom branch.");
    } finally {
      setSaving(false);
    }
  };

  const handleToggleExistingBranch = async (
    programmeName: string,
    branchNameValue: string,
    nextActive: boolean
  ) => {
    setSaving(true);
    setError(null);
    setSuccess(null);

    try {
      await adminApi("/admin/programme-branches/status", {
        method: "PATCH",
        body: JSON.stringify({
          programme_name: programmeName,
          branch_name: branchNameValue,
          is_active: nextActive,
        }),
      });
      setSuccess(
        `${branchNameValue} marked as ${nextActive ? "active" : "inactive"}.`
      );
      await loadBranches();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to update branch status.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <Stack spacing={3}>
      <Box>
        <Typography variant="h4" fontWeight={700} gutterBottom>
          Programme Branch Manager
        </Typography>
        <Typography color="text.secondary">
          Add custom branches or activate/deactivate built-in branches from one place.
        </Typography>
      </Box>

      <Paper sx={{ p: 1.5 }}>
        <Stack direction={{ xs: "column", sm: "row" }} spacing={1}>
          <Button
            variant={view === "custom" ? "contained" : "outlined"}
            onClick={() => setView("custom")}
          >
            Custom Branches ({customCount})
          </Button>
          <Button
            variant={view === "existing" ? "contained" : "outlined"}
            onClick={() => setView("existing")}
          >
            Existing Branches
          </Button>
        </Stack>
      </Paper>

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

      {view === "custom" ? (
        <Paper sx={{ p: 3 }}>
          <Stack spacing={2}>
            <Stack direction="row" justifyContent="space-between" alignItems="center">
              <Typography variant="h6" fontWeight={600}>
                Custom Branches
              </Typography>
              <Chip color="primary" label={`${customCount} custom branches`} />
            </Stack>

            <Stack spacing={2}>
              <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
                <FormControl fullWidth>
                  <InputLabel>Select Course</InputLabel>
                  <Select
                    value={selectedProgramme}
                    label="Select Course"
                    onChange={(e) => setSelectedProgramme(String(e.target.value))}
                  >
                    {programmeOptions.map((programme) => (
                      <MenuItem key={programme} value={programme}>
                        {programme}
                      </MenuItem>
                    ))}
                  </Select>
                </FormControl>

                <TextField
                  fullWidth
                  label="Custom Branch Name"
                  placeholder="e.g., Artificial Intelligence and Data Science"
                  value={branchName}
                  onChange={(e) => setBranchName(e.target.value)}
                />
              </Stack>

              <Box>
                <Button
                  variant="contained"
                  startIcon={<PlaylistAddIcon />}
                  onClick={handleAddBranch}
                  disabled={saving}
                >
                  {saving ? "Adding..." : "Add Branch"}
                </Button>
              </Box>
            </Stack>

            {loading ? (
              <Typography color="text.secondary">Loading catalogue...</Typography>
            ) : customGroups.length === 0 ? (
              <Typography color="text.secondary">No custom branches added yet.</Typography>
            ) : (
              <Stack spacing={2}>
                {customGroups.map((group) => (
                  <Card key={group.programme} variant="outlined">
                    <CardContent>
                      <Stack spacing={1.5}>
                        <Stack direction="row" alignItems="center" spacing={1}>
                          <SchoolIcon color="primary" fontSize="small" />
                          <Typography fontWeight={600}>{group.programme}</Typography>
                        </Stack>
                        <Stack direction="row" flexWrap="wrap" gap={1}>
                          {(Array.isArray(group.branches) ? group.branches : []).map((branch) => (
                            <Chip
                              key={`${group.programme}-${branch.id}`}
                              label={branch.branch_name}
                              size="small"
                              color={branch.is_active ? "success" : "default"}
                              variant={branch.is_active ? "filled" : "outlined"}
                              onDelete={() =>
                                handleDeleteCustomBranch(
                                  branch.id,
                                  `${group.programme} - ${branch.branch_name}`
                                )
                              }
                              deleteIcon={<DeleteOutlineIcon fontSize="small" />}
                            />
                          ))}
                        </Stack>
                      </Stack>
                    </CardContent>
                  </Card>
                ))}
              </Stack>
            )}
          </Stack>
        </Paper>
      ) : (
        <Paper sx={{ p: 3 }}>
          <Stack spacing={2}>
            <Stack direction="row" justifyContent="space-between" alignItems="center">
              <Typography variant="h6" fontWeight={600}>
                Existing Branches
              </Typography>
              <Chip color="secondary" label="Existing branch states" />
            </Stack>

            {loading ? (
              <Typography color="text.secondary">Loading branch states...</Typography>
            ) : (
              <Stack spacing={2}>
                {defaultProgrammes.map((programme) => {
                  const matchedGroup = existingGroups.find(
                    (group) => group.programme === programme.programme
                  );
                  const stateMap = new Map(
                    (Array.isArray(matchedGroup?.branches) ? matchedGroup?.branches : []).map((branch) => [branch.branch_name, branch.is_active])
                  );

                  return (
                    <Card key={programme.programme} variant="outlined">
                      <CardContent>
                        <Stack spacing={1.5}>
                          <Stack direction="row" alignItems="center" spacing={1}>
                            <SchoolIcon color="secondary" fontSize="small" />
                            <Typography fontWeight={600}>{programme.programme}</Typography>
                          </Stack>
                          <Stack direction="row" flexWrap="wrap" gap={1}>
                            {programme.branches.map((branch) => {
                              const isActive = stateMap.get(branch.branch) ?? true;

                              return (
                                <Chip
                                  key={`${programme.programme}-${branch.branch}`}
                                  label={`${branch.branch} (${isActive ? "Active" : "Inactive"})`}
                                  color={isActive ? "success" : "default"}
                                  variant={isActive ? "filled" : "outlined"}
                                  icon={isActive ? <ToggleOnIcon /> : <ToggleOffIcon />}
                                  onClick={() =>
                                    handleToggleExistingBranch(
                                      programme.programme,
                                      branch.branch,
                                      !isActive
                                    )
                                  }
                                  sx={{ cursor: "pointer" }}
                                />
                              );
                            })}
                          </Stack>
                        </Stack>
                      </CardContent>
                    </Card>
                  );
                })}
              </Stack>
            )}
          </Stack>
        </Paper>
      )}
    </Stack>
  );
}
