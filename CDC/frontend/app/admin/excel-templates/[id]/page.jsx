"use client";

import { use, useCallback, useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { DragDropContext, Draggable, Droppable } from "@hello-pangea/dnd";
import {
  Alert,
  Autocomplete,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  Divider,
  FormControl,
  IconButton,
  InputLabel,
  LinearProgress,
  MenuItem,
  Select,
  Stack,
  TextField,
  Tooltip,
  Typography,
} from "@mui/material";
import TableChartIcon from "@mui/icons-material/TableChart";
import DragIndicatorIcon from "@mui/icons-material/DragIndicator";
import DeleteIcon from "@mui/icons-material/Delete";
import AddIcon from "@mui/icons-material/Add";
import CheckCircleIcon from "@mui/icons-material/CheckCircle";
import InfoOutlinedIcon from "@mui/icons-material/InfoOutlined";

import PageHeader from "@/components/shared/pageheader";
import { adminApi } from "@/lib/adminapi";

const SAVE_LABEL = { saved: "All changes saved", dirty: "Saving…", saving: "Saving…", error: "Could not save — retrying on the next change" };

const identity = (c) => (c.cycle_id ? `${c.key}:${c.cycle_id}` : c.key);

/**
 * Template editor (Superset parity S3.3): Column Key | Display Name in Report, drag to reorder, searchable field picker
 * that hides fields already used, Placement Cycle Specific Information, autosave.
 */
export default function ExcelTemplateEditorPage({ params }) {
  const { id } = use(params);
  const router = useRouter();
  const [template, setTemplate] = useState(null);
  const [columns, setColumns] = useState([]);
  const [name, setName] = useState("");
  const [editingName, setEditingName] = useState(false);
  const [catalogue, setCatalogue] = useState(null);
  const [cycle, setCycle] = useState("");
  const [state, setState] = useState("saved");
  const [error, setError] = useState(null);
  const timer = useRef(null);
  const pending = useRef(null); // the unsaved payload while a debounced save is waiting (fix L26)

  useEffect(() => {
    Promise.all([adminApi(`/admin/export-templates/${id}`), adminApi("/admin/export-templates/fields")])
      .then(([t, f]) => {
        setTemplate(t.template);
        setColumns(t.template.columns);
        setName(t.template.name);
        setCatalogue(f);
      })
      .catch((e) => setError(e instanceof Error ? e.message : "Failed to load the template."));
  }, [id]);

  const save = useCallback(
    async (patch) => {
      setState("saving");
      try {
        const response = await adminApi(`/admin/export-templates/${id}`, { method: "PATCH", body: JSON.stringify(patch) });
        setTemplate(response.template);
        setState("saved");
      } catch (e) {
        setState("error");
        setError(e instanceof Error ? e.message : "Saving failed.");
      }
    },
    [id]
  );

  // Autosave: every change is saved shortly after the last edit.
  const change = (next) => {
    setColumns(next);
    setState("dirty");
    clearTimeout(timer.current);
    pending.current = { columns: next.map((c) => ({ key: c.key, label: c.label, ...(c.cycle_id ? { cycle_id: c.cycle_id } : {}) })) };
    timer.current = setTimeout(() => {
      const payload = pending.current;
      pending.current = null;
      if (payload) void save(payload);
    }, 700);
  };

  // Leaving the page flushes a waiting autosave instead of dropping the last edits (fix L26); closing the tab while a
  // save is still waiting asks first.
  useEffect(() => {
    const warn = (event) => {
      if (pending.current) {
        event.preventDefault();
        event.returnValue = "";
      }
    };
    window.addEventListener("beforeunload", warn);
    return () => {
      window.removeEventListener("beforeunload", warn);
      clearTimeout(timer.current);
      if (pending.current) {
        const payload = pending.current;
        pending.current = null;
        void adminApi(`/admin/export-templates/${id}`, { method: "PATCH", body: JSON.stringify(payload), keepalive: true }).catch(() => {});
      }
    };
  }, [id]);

  if (!template || !catalogue) return error ? <Alert severity="error">{error}</Alert> : <LinearProgress />;

  const fields = Object.fromEntries(catalogue.fields.map((f) => [f.key, f]));
  const cycles = Object.fromEntries(catalogue.cycles.map((c) => [c.id, c]));
  const used = new Set(columns.map(identity));
  const options = catalogue.fields.filter((f) => !f.cycle && !used.has(f.key));
  const cycleFields = catalogue.fields.filter((f) => f.cycle);

  const onDragEnd = (result) => {
    if (!result.destination || result.destination.index === result.source.index) return;
    const next = [...columns];
    const [moved] = next.splice(result.source.index, 1);
    next.splice(result.destination.index, 0, moved);
    change(next);
  };

  const addCycle = () => {
    const additions = cycleFields
      .map((f) => ({ key: f.key, label: `${f.label} (${cycles[cycle]?.name ?? "Placement"})`, cycle_id: Number(cycle) }))
      .filter((c) => !used.has(identity(c)));
    change([...columns, ...additions]);
    setCycle("");
  };

  const rename = () => {
    setEditingName(false);
    if (name.trim() && name.trim() !== template.name) void save({ name: name.trim() });
    else setName(template.name);
  };

  const remove = async () => {
    if (!window.confirm(`Delete the template "${template.name}"? Downloads that use it will no longer offer it.`)) return;
    try {
      await adminApi(`/admin/export-templates/${id}`, { method: "DELETE" });
      router.push("/admin/excel-templates");
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not delete the template.");
    }
  };

  return (
    <>
      <PageHeader icon={<TableChartIcon />} title="Excel Templates" subtitle={`Custom Excel Templates / ${template.name}`} backHref="/admin/excel-templates" backLabel="All Templates" />
      {error && (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>
          {error}
        </Alert>
      )}
      <Card>
        <CardContent>
          <Stack direction={{ xs: "column", sm: "row" }} justifyContent="space-between" alignItems={{ sm: "center" }} spacing={1} sx={{ mb: 2 }}>
            {editingName ? (
              <TextField
                autoFocus
                size="small"
                value={name}
                onChange={(e) => setName(e.target.value)}
                onBlur={rename}
                onKeyDown={(e) => e.key === "Enter" && rename()}
                inputProps={{ maxLength: 120 }}
                sx={{ minWidth: { sm: 320 } }}
              />
            ) : (
              <Tooltip title="Click on title to edit">
                <Typography variant="h6" fontWeight={700} onClick={() => setEditingName(true)} sx={{ cursor: "text" }}>
                  {template.name}
                </Typography>
              </Tooltip>
            )}
            <Chip
              size="small"
              icon={state === "saved" ? <CheckCircleIcon /> : <InfoOutlinedIcon />}
              color={state === "saved" ? "success" : state === "error" ? "error" : "warning"}
              variant="outlined"
              label={SAVE_LABEL[state]}
            />
          </Stack>

          <Box sx={{ border: 1, borderColor: "divider", borderRadius: 1, overflow: "hidden" }}>
            <Stack direction="row" sx={{ px: 1.5, py: 1, bgcolor: "action.hover" }}>
              <Box sx={{ width: 32 }} />
              <Typography variant="body2" fontWeight={700} sx={{ flex: 1 }}>
                Column Key
              </Typography>
              <Typography variant="body2" fontWeight={700} sx={{ flex: 1.3 }}>
                Display Name in Report
              </Typography>
              <Box sx={{ width: 40 }} />
            </Stack>
            <DragDropContext onDragEnd={onDragEnd}>
              <Droppable droppableId="columns">
                {(provided) => (
                  <Box ref={provided.innerRef} {...provided.droppableProps}>
                    {columns.map((column, index) => (
                      <Draggable key={identity(column)} draggableId={identity(column)} index={index}>
                        {(drag, snapshot) => (
                          <Stack
                            ref={drag.innerRef}
                            {...drag.draggableProps}
                            direction="row"
                            alignItems="center"
                            spacing={1}
                            sx={{ px: 1.5, py: 0.75, borderTop: 1, borderColor: "divider", bgcolor: snapshot.isDragging ? "action.selected" : "background.paper" }}
                          >
                            <Box {...drag.dragHandleProps} sx={{ width: 24, display: "flex", color: "text.secondary" }} aria-label="Drag to reorder">
                              <DragIndicatorIcon fontSize="small" />
                            </Box>
                            <Box sx={{ flex: 1, minWidth: 0 }}>
                              <Typography variant="body2" noWrap>
                                {fields[column.key]?.label ?? column.key}
                              </Typography>
                              <Typography variant="caption" color="text.secondary" noWrap component="div">
                                {column.cycle_id ? `${cycles[column.cycle_id]?.name ?? "Placement"} · ` : ""}
                                {fields[column.key]?.audience === "admin" ? "CDC only" : fields[column.key]?.audience === "contact" ? "Contact" : "Company-safe"}
                              </Typography>
                            </Box>
                            <TextField
                              size="small"
                              value={column.label}
                              onChange={(e) => change(columns.map((c, i) => (i === index ? { ...c, label: e.target.value } : c)))}
                              sx={{ flex: 1.3 }}
                              inputProps={{ maxLength: 150, "aria-label": `Display name for ${fields[column.key]?.label ?? column.key}` }}
                            />
                            <IconButton color="error" onClick={() => change(columns.filter((_, i) => i !== index))} aria-label="Remove column">
                              <DeleteIcon fontSize="small" />
                            </IconButton>
                          </Stack>
                        )}
                      </Draggable>
                    ))}
                    {provided.placeholder}
                  </Box>
                )}
              </Droppable>
            </DragDropContext>
            <Stack direction="row" alignItems="center" spacing={1} sx={{ px: 1.5, py: 1, borderTop: 1, borderColor: "divider" }}>
              <AddIcon fontSize="small" color="action" />
              <Autocomplete
                size="small"
                options={options}
                groupBy={(o) => o.group}
                getOptionLabel={(o) => o.label}
                value={null}
                blurOnSelect
                onChange={(_e, option) => option && change([...columns, { key: option.key, label: option.label.replace(/ \(one column each\)$/, "") }])}
                renderInput={(p) => <TextField {...p} placeholder="Choose a field to add" />}
                noOptionsText="No results match"
                sx={{ flex: 1 }}
              />
            </Stack>
          </Box>

          <Typography variant="subtitle1" fontWeight={700} sx={{ mt: 3 }}>
            Placement Cycle Specific Information
          </Typography>
          <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
            Adds the student&apos;s enrolment, offers, best CTC and active blocks in one placement. CDC only; never included in company downloads.
          </Typography>
          <Stack direction={{ xs: "column", sm: "row" }} spacing={1}>
            <FormControl size="small" sx={{ minWidth: 260 }}>
              <InputLabel id="cycle-section">Add Placement Cycle Section</InputLabel>
              <Select labelId="cycle-section" label="Add Placement Cycle Section" value={cycle} onChange={(e) => setCycle(e.target.value)}>
                {catalogue.cycles.map((c) => (
                  <MenuItem key={c.id} value={c.id}>
                    {c.name}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
            <Button variant="outlined" startIcon={<AddIcon />} disabled={!cycle} onClick={addCycle}>
              Add Placement
            </Button>
          </Stack>

          <Divider sx={{ my: 3 }} />
          <Stack direction="row" justifyContent="space-between">
            <Button color="error" variant="outlined" startIcon={<DeleteIcon />} onClick={remove}>
              Delete template
            </Button>
            <Button variant="contained" color="success" disabled={state === "saved"} onClick={() => save({ columns })}>
              {state === "saved" ? "Template Saved" : "Save now"}
            </Button>
          </Stack>
        </CardContent>
      </Card>
    </>
  );
}
