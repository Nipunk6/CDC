"use client";

import {
  Box,
  Button,
  Checkbox,
  Chip,
  FormControl,
  FormControlLabel,
  IconButton,
  InputLabel,
  MenuItem,
  Paper,
  Select,
  Stack,
  TextField,
  Typography,
  alpha,
} from "@mui/material";
import AddIcon from "@mui/icons-material/Add";
import DeleteIcon from "@mui/icons-material/Delete";
import DragIndicatorIcon from "@mui/icons-material/DragIndicator";
import CloseIcon from "@mui/icons-material/Close";
import CalendarTodayIcon from "@mui/icons-material/CalendarToday";
import { DragDropContext, Droppable, Draggable, DropResult } from "@hello-pangea/dnd";
import { createPortal } from "react-dom";

export interface SelectionRound {
  id: string;
  type: "ppt" | "resume" | "written_test" | "aptitude_test" | "technical_test" | "group_discussion" | "hr_interview" | "technical_interview" | "psychometric" | "medical" | "other";
  mode: "online" | "offline" | "hybrid" | "not_applicable";
  duration?: number;
  description?: string;
  enabled: boolean;
  infraRequirement?: string;
  date?: string;
  details?: string;
  showDuration?: boolean;
  showDate?: boolean;
  showInfra?: boolean;
  showDetails?: boolean;
}

interface SelectionProcessBuilderProps {
  value: SelectionRound[];
  onChange: (rounds: SelectionRound[]) => void;
}

const roundTypes = [
  { value: "ppt", label: "Pre-Placement Talk", icon: "🎤" },
  { value: "resume", label: "Resume Shortlisting", icon: "📄" },
  { value: "written_test", label: "Written Test", icon: "✍️" },
  { value: "aptitude_test", label: "Aptitude Test", icon: "🧠" },
  { value: "technical_test", label: "Technical Test", icon: "💻" },
  { value: "group_discussion", label: "Group Discussion", icon: "👥" },
  { value: "hr_interview", label: "HR Interview", icon: "🤝" },
  { value: "technical_interview", label: "Technical Interview", icon: "⚙️" },
  { value: "psychometric", label: "Psychometric Test", icon: "🧪" },
  { value: "medical", label: "Medical Test", icon: "🏥" },
  { value: "other", label: "Other", icon: "📋" },
];

const modeOptions = [
  { value: "online", label: "Online", color: "info" },
  { value: "offline", label: "Offline / On-campus", color: "success" },
  { value: "hybrid", label: "Hybrid", color: "warning" },
  { value: "not_applicable", label: "Not Applicable", color: "default" },
];

const defaultRounds: SelectionRound[] = [
  { id: "1", type: "ppt", mode: "offline", enabled: false, showDate: true, showDuration: true, showDetails: true, showInfra: true },
  { id: "2", type: "resume", mode: "not_applicable", enabled: false, showDate: true, showDuration: true, showDetails: true, showInfra: false },
  { id: "3", type: "aptitude_test", mode: "online", enabled: false, showDate: true, showDuration: true, showDetails: true, showInfra: true },
  { id: "4", type: "technical_test", mode: "online", enabled: false, showDate: true, showDuration: true, showDetails: true, showInfra: true },
  { id: "5", type: "group_discussion", mode: "offline", enabled: false, showDate: true, showDuration: true, showDetails: true, showInfra: true },
  { id: "6", type: "technical_interview", mode: "offline", enabled: false, showDate: true, showDuration: true, showDetails: true, showInfra: true },
  { id: "7", type: "hr_interview", mode: "offline", enabled: false, showDate: true, showDuration: true, showDetails: true, showInfra: true },
];

export { defaultRounds };

import { useState, useEffect } from "react";

export default function SelectionProcessBuilder({
  value,
  onChange,
}: SelectionProcessBuilderProps) {
  const [isMounted, setIsMounted] = useState(false);
  useEffect(() => {
    setIsMounted(true);
  }, []);

  const rounds = (value.length > 0 ? value : defaultRounds).map((r) => ({
    ...r,
    showDate: r.showDate ?? true,
    showDuration: r.showDuration ?? true,
    showDetails: r.showDetails ?? true,
    showInfra: r.showInfra ?? true,
  }));

  const toggleRound = (id: string) => {
    const updated = rounds.map((r) =>
      r.id === id ? { ...r, enabled: !r.enabled } : r
    );
    onChange(updated);
  };

  const updateRound = (id: string, fieldOrUpdates: keyof SelectionRound | Partial<SelectionRound>, val?: unknown) => {
    const updated = rounds.map((r) => {
      if (r.id !== id) return r;
      if (typeof fieldOrUpdates === "string") {
        return { ...r, [fieldOrUpdates]: val };
      }
      return { ...r, ...fieldOrUpdates };
    });
    onChange(updated);
  };

  const addCustomRound = () => {
    const newRound: SelectionRound = {
      id: Date.now().toString(),
      type: "other",
      mode: "offline",
      duration: 30,
      description: "Others",
      enabled: true,
      showDate: true,
      showDuration: true,
      showDetails: true,
      showInfra: true,
    };
    onChange([...rounds, newRound]);
  };

  const removeRound = (id: string) => {
    onChange(rounds.filter((r) => r.id !== id));
  };

  const onDragEnd = (result: DropResult) => {
    if (!result.destination) return;

    const enabledRounds = rounds.filter((r) => r.enabled);
    const disabledRounds = rounds.filter((r) => !r.enabled);

    const startIndex = result.source.index;
    const endIndex = result.destination.index;

    const resultRounds = Array.from(enabledRounds);
    const [removed] = resultRounds.splice(startIndex, 1);
    resultRounds.splice(endIndex, 0, removed);

    onChange([...resultRounds, ...disabledRounds]);
  };

  const enabledCount = rounds.filter((r) => r.enabled).length;

  return (
    <Box>
      {/* Quick Toggle Section */}
      <Paper sx={{ p: 2, mb: 3, bgcolor: "grey.50" }}>
        <Typography variant="subtitle2" fontWeight={600} mb={2}>
          Quick Selection (check to include in selection process)
        </Typography>
        <Stack direction="row" flexWrap="wrap" gap={1}>
          {rounds.map((round) => {
            const typeInfo = roundTypes.find((t) => t.value === round.type);
            return (
              <FormControlLabel
                key={round.id}
                control={
                  <Checkbox
                    checked={round.enabled}
                    onChange={() => toggleRound(round.id)}
                    size="small"
                  />
                }
                label={
                  <Typography variant="body2">
                    {typeInfo?.icon} {round.type === "other" ? (round.description || "Custom Round") : typeInfo?.label}
                  </Typography>
                }
                sx={{
                  bgcolor: round.enabled ? alpha("#1976d2", 0.1) : "transparent",
                  borderRadius: 1,
                  px: 1,
                  m: 0,
                  border: "1px solid",
                  borderColor: round.enabled ? "primary.main" : "divider",
                }}
              />
            );
          })}
        </Stack>
        <Stack direction="row" alignItems="center" spacing={1} mt={2}>
          <Chip
            label={`${enabledCount} rounds selected`}
            color={enabledCount > 0 ? "primary" : "default"}
            size="small"
          />
        </Stack>
      </Paper>

      {/* Detailed Configuration */}
      <Typography variant="subtitle2" fontWeight={600} mb={2}>
        Round Details (configure mode & duration for enabled rounds)
      </Typography>
      {!isMounted ? null : (
      <DragDropContext onDragEnd={onDragEnd}>
        <Droppable droppableId="rounds-list">
          {(provided) => (
            <Box
              {...provided.droppableProps}
              ref={provided.innerRef}
            >
              {rounds
                .filter((r) => r.enabled)
                .map((round, index) => {
                  const typeInfo = roundTypes.find((t) => t.value === round.type);

                  return (
                    <Draggable key={round.id} draggableId={round.id} index={index}>
                      {(provided, snapshot) => {
                        const child = (
                          <Box
                            ref={provided.innerRef}
                            {...provided.draggableProps}
                            style={{
                              ...provided.draggableProps.style,
                              paddingBottom: "16px",
                            }}
                          >
                            <Paper
                              sx={{
                                p: 2,
                                border: "1px solid",
                                borderColor: "divider",
                                "&:hover": { borderColor: "primary.light" },
                                bgcolor: "background.paper",
                                ...(snapshot.isDragging && {
                                  boxShadow: 3,
                                  borderColor: "primary.main",
                                }),
                              }}
                            >
                            <Stack direction={{ xs: "column", md: "row" }} spacing={2} alignItems={{ md: "center" }}>
                              <Stack direction="row" alignItems="center" spacing={1} sx={{ width: { md: "280px" }, flexShrink: 0 }}>
                                <Box {...provided.dragHandleProps} sx={{ display: 'flex', alignItems: 'center' }}>
                                  <DragIndicatorIcon sx={{ color: "text.disabled", cursor: "grab" }} />
                                </Box>
                      <Chip
                        label={`Round ${index + 1}`}
                        size="small"
                        color="primary"
                        variant="outlined"
                      />
                      {round.type === "other" ? (
                        <TextField
                          size="small"
                          placeholder="Round Name"
                          value={round.description ?? ""}
                          onChange={(e) => updateRound(round.id, "description", e.target.value)}
                          variant="standard"
                          sx={{ width: 150 }}
                        />
                      ) : (
                        <Typography fontWeight={500}>
                          {typeInfo?.icon} {typeInfo?.label}
                        </Typography>
                      )}
                    </Stack>

                    <FormControl size="small" sx={{ minWidth: 160 }}>
                      <InputLabel>Mode</InputLabel>
                      <Select
                        value={round.mode}
                        label="Mode"
                        onChange={(e) => updateRound(round.id, "mode", e.target.value)}
                      >
                        {modeOptions.map((opt) => (
                          <MenuItem key={opt.value} value={opt.value}>
                            {opt.label}
                          </MenuItem>
                        ))}
                      </Select>
                    </FormControl>

                    {round.showDate && (
                      <TextField
                        size="small"
                        type="date"
                        label="Tentative Date"
                        InputLabelProps={{ shrink: true }}
                        inputProps={{ min: new Date().toLocaleDateString('en-CA') }}
                        value={round.date ?? ""}
                        onChange={(e) => updateRound(round.id, "date", e.target.value)}
                        sx={{ minWidth: 180, maxWidth: 220 }}
                        required
                      />
                    )}

                    {/* Options to add fields */}
                    <Stack direction="row" spacing={1} flexWrap="wrap">
                      {!round.showDate && (
                        <Chip
                          icon={<AddIcon fontSize="small" />}
                          label="Date"
                          onClick={() => updateRound(round.id, "showDate", true)}
                          size="small"
                          variant="outlined"
                          sx={{ cursor: "pointer", bgcolor: "background.paper" }}
                        />
                      )}
                      {!round.showDuration && round.type !== "ppt" && round.type !== "resume" && (
                        <Chip
                          icon={<AddIcon fontSize="small" />}
                          label="Duration"
                          onClick={() => updateRound(round.id, "showDuration", true)}
                          size="small"
                          variant="outlined"
                          sx={{ cursor: "pointer", bgcolor: "background.paper" }}
                        />
                      )}
                      {!round.showDetails && (
                        <Chip
                          icon={<AddIcon fontSize="small" />}
                          label="Description"
                          onClick={() => updateRound(round.id, "showDetails", true)}
                          size="small"
                          variant="outlined"
                          sx={{ cursor: "pointer", bgcolor: "background.paper" }}
                        />
                      )}
                      {!round.showInfra && round.type !== "resume" && (
                        <Chip
                          icon={<AddIcon fontSize="small" />}
                          label="Infra"
                          onClick={() => updateRound(round.id, "showInfra", true)}
                          size="small"
                          variant="outlined"
                          sx={{ cursor: "pointer", bgcolor: "background.paper" }}
                        />
                      )}
                    </Stack>

                    <Box flexGrow={1} />

                    <IconButton
                      size="small"
                      color="error"
                      onClick={() => removeRound(round.id)}
                    >
                      <DeleteIcon fontSize="small" />
                    </IconButton>
                  </Stack>

                  <Stack direction="column" spacing={2} mt={2} pl={{ md: "260px" }}>
                    {/* Render enabled fields */}

                    {round.showDuration && round.type !== "ppt" && round.type !== "resume" && (
                      <Stack direction="row" alignItems="center" spacing={1}>
                        <IconButton
                          size="small"
                          onMouseDown={(e) => e.preventDefault()}
                          onClick={(e) => {
                            e.stopPropagation();
                            updateRound(round.id, { showDuration: false, duration: undefined });
                          }}
                          sx={{ width: 28, height: 28, p: 0.5 }}
                        >
                          <CloseIcon fontSize="small" color="error" />
                        </IconButton>
                        <TextField
                          size="small"
                          type="number"
                          label="Duration (mins)"
                          value={round.duration ?? ""}
                          onChange={(e) =>
                            updateRound(round.id, "duration", e.target.value ? parseInt(e.target.value) : undefined)
                          }
                          sx={{ maxWidth: 300 }}
                        />
                      </Stack>
                    )}

                    {round.showDetails && (
                      <Stack direction="row" alignItems="center" spacing={1}>
                        <IconButton
                          size="small"
                          onMouseDown={(e) => e.preventDefault()}
                          onClick={(e) => {
                            e.stopPropagation();
                            updateRound(round.id, { showDetails: false, details: "" });
                          }}
                          sx={{ width: 28, height: 28, p: 0.5 }}
                        >
                          <CloseIcon fontSize="small" color="error" />
                        </IconButton>
                        <TextField
                          size="small"
                          label="Description"
                          value={round.details ?? ""}
                          onChange={(e) => updateRound(round.id, "details", e.target.value)}
                          sx={{ width: "100%", maxWidth: 600 }}
                        />
                      </Stack>
                    )}

                    {round.showInfra && round.type !== "resume" && (
                      <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap>
                        <IconButton
                          size="small"
                          onMouseDown={(e) => e.preventDefault()}
                          onClick={(e) => {
                            e.stopPropagation();
                            updateRound(round.id, {
                              showInfra: false,
                              infraRequirement: "",
                            });
                          }}
                          sx={{ width: 28, height: 28, p: 0.5 }}
                        >
                          <CloseIcon fontSize="small" color="error" />
                        </IconButton>
                        <TextField
                          size="small"
                          label="Infrastructure Requirement (e.g. Computer labs, lecture halls, interview cabins)"
                          value={round.infraRequirement ?? ""}
                          onChange={(e) => updateRound(round.id, "infraRequirement", e.target.value)}
                          sx={{ width: "100%", maxWidth: 600 }}
                        />
                      </Stack>
                    )}
                  </Stack>
                </Paper>
              </Box>
            );

            if (snapshot.isDragging) {
              return createPortal(child, document.body);
            }
            return child;
          }}
        </Draggable>
                );
              })}
              {provided.placeholder}
            </Box>
          )}
        </Droppable>
      </DragDropContext>
      )}

      <Button
        startIcon={<AddIcon />}
        onClick={addCustomRound}
        sx={{ mt: 2 }}
        variant="outlined"
        size="small"
      >
        Add Custom Round
      </Button>
    </Box>
  );
}
