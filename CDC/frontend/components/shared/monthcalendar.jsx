"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  Checkbox,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControlLabel,
  IconButton,
  InputAdornment,
  LinearProgress,
  MenuItem,
  Popover,
  Snackbar,
  Stack,
  Switch,
  TextField,
  ToggleButton,
  ToggleButtonGroup,
  Tooltip,
  Typography,
  useMediaQuery,
  useTheme,
} from "@mui/material";
import ChevronLeftIcon from "@mui/icons-material/ChevronLeft";
import ChevronRightIcon from "@mui/icons-material/ChevronRight";
import AddIcon from "@mui/icons-material/Add";
import EditIcon from "@mui/icons-material/EditOutlined";
import DeleteIcon from "@mui/icons-material/DeleteOutline";
import HideIcon from "@mui/icons-material/VisibilityOffOutlined";
import ShowIcon from "@mui/icons-material/VisibilityOutlined";
import RestoreIcon from "@mui/icons-material/RestoreOutlined";
import OpenIcon from "@mui/icons-material/OpenInNew";
import SearchIcon from "@mui/icons-material/Search";
import AlarmIcon from "@mui/icons-material/AlarmOutlined";
import CheckIcon from "@mui/icons-material/Check";

import { fromLocalInput, toLocalInput } from "@/lib/format";

const typeColors = { event: "#1e3a8a", deadline: "#7B1113", round: "#b45309", visit: "#047857", personal: "#6d28d9" };
const typeLabels = { event: "Event", deadline: "Application deadline", round: "Selection stage", visit: "Date of visit", personal: "My entry" };
// Colours a user can give their own entries (CalendarEntry::COLORS).
const entryColors = { purple: "#6d28d9", blue: "#2563eb", green: "#16a34a", orange: "#ea580c", red: "#dc2626", teal: "#0d9488", grey: "#6b7280" };
const reminderOptions = [
  [null, "No reminder"],
  [0, "At the time"],
  [10, "10 minutes before"],
  [30, "30 minutes before"],
  [60, "1 hour before"],
  [1440, "1 day before"],
  [10080, "1 week before"],
];
const VIEWS = { month: "Month", week: "Week", day: "Day", schedule: "Schedule" };
const ENTRIES = "/calendar/entries";
const OVERRIDES = "/calendar/overrides";
const WEEKDAYS = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
const HOURS = Array.from({ length: 24 }, (_, h) => h);
const IST = "Asia/Kolkata";
const VIEW_STORAGE = "cdc-calendar-view";

const monthKey = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}`;
const dayKey = (date) => `${monthKey(date)}-${String(date.getDate()).padStart(2, "0")}`;
// Items are placed by their IST date and time (the server windows months in IST), whatever the browser's timezone.
const istDayKey = (value) => new Date(value).toLocaleDateString("en-CA", { timeZone: IST });
const istHHMM = (value) => new Date(value).toLocaleTimeString("en-GB", { hour: "2-digit", minute: "2-digit", hourCycle: "h23", timeZone: IST });
const istHour = (value) => Number(istHHMM(value).slice(0, 2));
const time = (value) => new Date(value).toLocaleTimeString("en-IN", { hour: "2-digit", minute: "2-digit", timeZone: IST });
const longDay = (date) => date.toLocaleDateString("en-IN", { weekday: "long", day: "numeric", month: "long" });
const atIst = (date, hhmm) => `${dayKey(date)}T${hhmm}:00+05:30`;
const addDays = (date, n) => new Date(date.getFullYear(), date.getMonth(), date.getDate() + n);
const startOfWeek = (date) => addDays(date, -((date.getDay() + 6) % 7));
const sameInstant = (a, b) => new Date(a).getTime() === new Date(b).getTime();
const message = (e, fallback) => (e instanceof Error ? e.message : fallback);
const colorOf = (item) => (item.type === "personal" ? entryColors[item.color] ?? entryColors.purple : typeColors[item.type]);
const uid = (item) => (item.type === "personal" ? `personal:${item.id}` : item.key ?? `${item.type}:${item.at}:${item.title}`);

/** The days a view shows, and the months whose items it needs. */
function rangeOf(view, focus) {
  if (view === "week") {
    const start = startOfWeek(focus);
    return Array.from({ length: 7 }, (_, i) => addDays(start, i));
  }
  if (view === "day") return [focus];
  const days = new Date(focus.getFullYear(), focus.getMonth() + 1, 0).getDate();
  return Array.from({ length: days }, (_, i) => new Date(focus.getFullYear(), focus.getMonth(), i + 1));
}

/**
 * Lays the user's overrides over the automatic items: renamed, moved, annotated, hidden or done, in their calendar
 * only. A null title or time means "as the placement data says". An item the user moved here from another month was
 * not fetched with this month, so it is shown from the override itself.
 */
function applyOverrides(autoItems, overrides, months) {
  const byKey = new Map(overrides.map((o) => [o.key, o]));
  const seen = new Set();
  const merged = autoItems.map((item) => {
    const o = item.key ? byKey.get(item.key) : null;
    if (!o) return item;
    seen.add(o.key);
    return {
      ...item,
      title: o.title ?? item.title,
      at: o.at ?? item.at,
      note: o.note,
      hidden: o.hidden,
      done: o.done,
      remind_minutes: o.remind_minutes,
      edited: Boolean(o.title || o.at || o.note),
      customised: true,
      original: { title: item.title, at: item.at },
    };
  });
  overrides
    .filter((o) => !seen.has(o.key) && o.at && months.includes(istDayKey(o.at).slice(0, 7)))
    .forEach((o) =>
      merged.push({ key: o.key, type: o.type, title: o.title ?? o.label ?? "Calendar item", at: o.at, note: o.note, hidden: o.hidden, done: o.done, remind_minutes: o.remind_minutes, link: o.link, subtitle: "", edited: true, customised: true, original: null }),
    );
  return merged;
}

/**
 * The user's calendar (spec M8.3), in the spirit of Google Calendar. `api(path)` fetches `${endpoint}?month=YYYY-MM`
 * → { items: [{key, type, at, title, subtitle, link, draft}] }: the automatic items. Around those the user can:
 * add, edit, colour, delete and drag their own entries (/calendar/entries); edit, move, hide or reset automatic items
 * in their own view (/calendar/overrides), never changing the placement data; mark anything done; set reminders
 * (in-app notifications); switch between month, week, day and schedule views; search; and filter by type.
 * Without an `endpoint` the calendar holds only the user's own entries.
 */
export default function MonthCalendar({ api, endpoint }) {
  const theme = useTheme();
  const compact = useMediaQuery(theme.breakpoints.down("sm"));
  const [view, setView] = useState("month");
  const [focus, setFocus] = useState(() => {
    const now = new Date();
    return new Date(now.getFullYear(), now.getMonth(), now.getDate());
  });
  const [items, setItems] = useState(null);
  const [error, setError] = useState(null);
  const [popover, setPopover] = useState(null); // { anchor, day } for a day's list, { anchor, uid } for one item
  const [reload, setReload] = useState(0);
  const [showHidden, setShowHidden] = useState(false);
  const [hiddenTypes, setHiddenTypes] = useState([]);
  const [search, setSearch] = useState("");
  const [form, setForm] = useState(null); // the add/edit dialog
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState(null);
  const [toast, setToast] = useState(null); // { text, undo }
  const dragged = useRef(null);
  const timeGrid = useRef(null);

  useEffect(() => {
    try {
      const saved = window.localStorage.getItem(VIEW_STORAGE);
      if (saved && VIEWS[saved]) setView(saved);
    } catch {
      // No storage (private window): the month view it is.
    }
  }, []);
  const changeView = useCallback((next) => {
    if (!next) return;
    setView(next);
    try {
      window.localStorage.setItem(VIEW_STORAGE, next);
    } catch {
      // Remembering the view is a convenience only.
    }
  }, []);

  const days = useMemo(() => rangeOf(view, focus), [view, focus]);
  const months = useMemo(() => [...new Set(days.map(monthKey))], [days]);
  const monthsKey = months.join(",");

  useEffect(() => {
    // Ignore responses for a range the user has already navigated away from.
    let current = true;
    const list = monthsKey.split(",");
    // One failing source does not hide the others.
    Promise.allSettled([
      ...list.map((m) => (endpoint ? api(`${endpoint}?month=${m}`) : Promise.resolve({ items: [] }))),
      ...list.map((m) => api(`${ENTRIES}?month=${m}`)),
      endpoint ? api(OVERRIDES) : Promise.resolve({ overrides: [] }),
    ]).then((results) => {
      if (!current) return;
      const value = (result, field) => (result.status === "fulfilled" ? result.value[field] ?? [] : []);
      const auto = results.slice(0, list.length).flatMap((r) => value(r, "items"));
      const own = results.slice(list.length, list.length * 2).flatMap((r) => value(r, "items"));
      const overrides = value(results[results.length - 1], "overrides");
      const failed = results.find((r) => r.status === "rejected");
      setItems([...applyOverrides(auto, overrides, list), ...own].sort((a, b) => new Date(a.at) - new Date(b.at)));
      setError(failed ? message(failed.reason, "Failed to load the calendar.") : null);
    });
    return () => {
      current = false;
    };
  }, [api, endpoint, monthsKey, reload]);

  // Week and day views open at 8 am, like Google Calendar.
  useEffect(() => {
    if ((view === "week" || view === "day") && timeGrid.current) timeGrid.current.scrollTop = 8 * 48;
  }, [view]);

  const refresh = () => setReload((n) => n + 1);

  const run = async (action, fallback, toastText, undo) => {
    setSaving(true);
    setFormError(null);
    try {
      await action();
      setForm(null);
      setPopover(null);
      if (toastText) setToast({ text: toastText, undo });
      refresh();
    } catch (e) {
      if (form) setFormError(message(e, fallback));
      else setError(message(e, fallback));
    } finally {
      setSaving(false);
    }
  };

  // Automatic items: only the fields sent change; `label` and `link` let a moved item be shown in its new month.
  const override = (item, changes) =>
    api(OVERRIDES, { method: "PUT", body: JSON.stringify({ key: item.key, label: item.original?.title ?? item.title, link: item.link ?? null, ...changes }) });
  const patchEntry = (item, changes) => api(`${ENTRIES}/${item.id}`, { method: "PATCH", body: JSON.stringify(changes) });
  const change = (item, changes) => (item.type === "personal" ? patchEntry(item, changes) : override(item, changes));

  const toggleDone = (item) => {
    const done = !item.done;
    run(() => change(item, { done }), "Could not update the item.", done ? "Marked as done" : "Marked as not done", () => run(() => change(item, { done: !done }), "Could not undo."));
  };
  const setHidden = (item, hidden) =>
    run(() => override(item, { hidden }), "Could not update the item.", hidden ? "Hidden from your calendar" : "Shown in your calendar again", () => run(() => override(item, { hidden: !hidden }), "Could not undo."));
  const reset = (item) => run(() => api(`${OVERRIDES}?key=${encodeURIComponent(item.key)}`, { method: "DELETE" }), "Could not reset the item.", "Reset to the original");
  const remove = (item) => {
    if (!window.confirm(`Delete "${item.title}" from your calendar?`)) return;
    run(() => api(`${ENTRIES}/${item.id}`, { method: "DELETE" }), "Could not delete the entry.", "Entry deleted");
  };
  const moveTo = (item, startsAt) => {
    if (sameInstant(item.at, startsAt)) return;
    const before = new Date(item.at).toISOString();
    run(() => change(item, { starts_at: startsAt }), "Could not move the item.", "Moved", () => run(() => change(item, { starts_at: before }), "Could not undo."));
  };

  const openForm = (item, day, hour) => {
    setPopover(null);
    setFormError(null);
    if (!item) {
      // A new entry starts on the chosen day and hour (09:00 today when none was picked).
      const hh = String(hour ?? 9).padStart(2, "0");
      setForm({ kind: "personal", item: null, title: "", starts_at: `${dayKey(day ?? new Date())}T${hh}:00`, note: "", color: "purple", remind_minutes: null });
    } else {
      setForm({
        kind: item.type === "personal" ? "personal" : "auto",
        item,
        title: item.title,
        starts_at: toLocalInput(item.at),
        note: item.note ?? "",
        color: item.color ?? "purple",
        remind_minutes: item.remind_minutes ?? null,
      });
    }
  };

  const save = () => {
    if (!form.title.trim() || !form.starts_at) {
      setFormError("Add a title and a date and time.");
      return;
    }
    const startsAt = fromLocalInput(form.starts_at);
    const common = { note: form.note.trim() || null, remind_minutes: form.remind_minutes };
    run(() => {
      if (form.kind === "auto") {
        // Unchanged title or time stay null, so they follow the placement data if it changes later.
        const original = form.item.original ?? { title: null, at: null };
        return override(form.item, {
          ...common,
          title: form.title.trim() === original.title ? null : form.title.trim(),
          starts_at: original.at && sameInstant(original.at, startsAt) ? null : startsAt,
        });
      }
      const fields = { ...common, title: form.title.trim(), starts_at: startsAt, color: form.color };
      return form.item ? patchEntry(form.item, fields) : api(ENTRIES, { method: "POST", body: JSON.stringify(fields) });
    }, "Could not save.");
  };

  const visible = useMemo(() => {
    const q = search.trim().toLowerCase();
    return (items ?? []).filter(
      (item) =>
        (showHidden || !item.hidden) &&
        !hiddenTypes.includes(item.type) &&
        (!q || [item.title, item.subtitle, item.note].some((text) => text?.toLowerCase().includes(q))),
    );
  }, [items, showHidden, hiddenTypes, search]);
  const hiddenCount = (items ?? []).filter((item) => item.hidden).length;

  const byDay = useMemo(() => {
    const map = {};
    visible.forEach((item) => {
      (map[istDayKey(item.at)] ??= []).push(item);
    });
    return map;
  }, [visible]);

  const step = useCallback(
    (delta) => {
      setFocus((f) => (view === "week" ? addDays(f, 7 * delta) : view === "day" ? addDays(f, delta) : new Date(f.getFullYear(), f.getMonth() + delta, 1)));
    },
    [view],
  );
  const goToday = useCallback(() => {
    const now = new Date();
    setFocus(new Date(now.getFullYear(), now.getMonth(), now.getDate()));
  }, []);

  // Google Calendar's keys: t today, j/k next/previous, d/w/m/a views. Not while typing.
  useEffect(() => {
    const onKey = (e) => {
      if (e.metaKey || e.ctrlKey || e.altKey || form || /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName) || e.target.isContentEditable) return;
      const views = { d: "day", w: "week", m: "month", a: "schedule" };
      if (e.key === "t") goToday();
      else if (e.key === "j" || e.key === "n") step(1);
      else if (e.key === "k" || e.key === "p") step(-1);
      else if (views[e.key]) changeView(views[e.key]);
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [step, form, goToday, changeView]);

  const todayKey = dayKey(new Date());
  const heading =
    view === "day"
      ? longDay(focus)
      : view === "week"
        ? `${days[0].toLocaleDateString("en-IN", { day: "numeric", month: "short" })} – ${days[6].toLocaleDateString("en-IN", { day: "numeric", month: "short", year: "numeric" })}`
        : focus.toLocaleDateString("en-IN", { month: "long", year: "numeric" });

  const popItem = popover?.uid ? (items ?? []).find((item) => uid(item) === popover.uid) : null;
  const popDayItems = popover?.day ? byDay[dayKey(popover.day)] ?? [] : [];

  // Drag an item onto a day (keeps its time) or an hour (keeps its minutes).
  const dropProps = (date, hour) => ({
    onDragOver: (e) => {
      if (dragged.current) e.preventDefault();
    },
    onDrop: (e) => {
      e.preventDefault();
      const item = dragged.current;
      dragged.current = null;
      if (!item || !date) return;
      const hhmm = istHHMM(item.at);
      moveTo(item, atIst(date, hour == null ? hhmm : `${String(hour).padStart(2, "0")}:${hhmm.slice(3)}`));
    },
  });

  const chip = (item, i, { showTime = true, wrap = false } = {}) => (
    <Typography
      key={`${uid(item)}-${i}`}
      variant="caption"
      noWrap={!wrap}
      draggable={!saving}
      onDragStart={(e) => {
        dragged.current = item;
        e.dataTransfer.effectAllowed = "move";
        e.dataTransfer.setData("text/plain", uid(item));
      }}
      onDragEnd={() => {
        dragged.current = null;
      }}
      onClick={(e) => {
        e.stopPropagation();
        setPopover({ anchor: e.currentTarget, uid: uid(item) });
      }}
      title={`${time(item.at)} ${item.title}`}
      sx={{
        display: "block",
        px: 0.5,
        borderRadius: 0.5,
        color: "white",
        bgcolor: colorOf(item),
        opacity: item.hidden ? 0.35 : item.done ? 0.5 : item.draft ? 0.55 : 1,
        textDecoration: item.done || item.hidden ? "line-through" : undefined,
        cursor: "grab",
        "&:hover": { filter: "brightness(1.15)" },
      }}
    >
      {item.done && <CheckIcon sx={{ fontSize: 11, mr: 0.25, verticalAlign: "-1px" }} />}
      {showTime && `${time(item.at)} `}
      {item.title}
    </Typography>
  );

  const actions = (item) => {
    const personal = item.type === "personal";
    return (
      <Stack direction="row" alignItems="center" flexWrap="wrap" useFlexGap>
        <FormControlLabel
          sx={{ mr: 1 }}
          control={<Checkbox size="small" checked={Boolean(item.done)} onChange={() => toggleDone(item)} disabled={saving} />}
          label={<Typography variant="body2">{item.done ? "Done" : "Mark as done"}</Typography>}
        />
        <Tooltip title="Edit">
          <IconButton size="small" aria-label="Edit" onClick={() => openForm(item)} disabled={saving}>
            <EditIcon fontSize="small" />
          </IconButton>
        </Tooltip>
        {personal ? (
          <Tooltip title="Delete">
            <IconButton size="small" aria-label="Delete" onClick={() => remove(item)} disabled={saving}>
              <DeleteIcon fontSize="small" />
            </IconButton>
          </Tooltip>
        ) : (
          <>
            <Tooltip title={item.hidden ? "Show in my calendar" : "Hide from my calendar"}>
              <IconButton size="small" aria-label={item.hidden ? "Show" : "Hide"} onClick={() => setHidden(item, !item.hidden)} disabled={saving}>
                {item.hidden ? <ShowIcon fontSize="small" /> : <HideIcon fontSize="small" />}
              </IconButton>
            </Tooltip>
            {item.customised && (
              <Tooltip title="Reset my changes">
                <IconButton size="small" aria-label="Reset my changes" onClick={() => reset(item)} disabled={saving}>
                  <RestoreIcon fontSize="small" />
                </IconButton>
              </Tooltip>
            )}
            {item.link && (
              <Tooltip title="Open">
                <IconButton size="small" aria-label="Open" component={Link} href={item.link} onClick={() => setPopover(null)}>
                  <OpenIcon fontSize="small" />
                </IconButton>
              </Tooltip>
            )}
          </>
        )}
      </Stack>
    );
  };

  const details = (item) => {
    const tags = [item.draft && "draft", item.edited && "edited by you", item.hidden && "hidden"].filter(Boolean);
    const reminder = reminderOptions.find(([m]) => m === item.remind_minutes && m !== null);
    return (
      <Stack spacing={0.25} sx={{ minWidth: 0 }}>
        <Stack direction="row" spacing={1} alignItems="center">
          <Box sx={{ width: 12, height: 12, borderRadius: 0.5, bgcolor: colorOf(item), flexShrink: 0 }} />
          <Typography variant="subtitle2" fontWeight={700} sx={{ textDecoration: item.done ? "line-through" : undefined, wordBreak: "break-word" }}>
            {item.title}
          </Typography>
        </Stack>
        <Typography variant="body2" color="text.secondary">
          {longDay(new Date(item.at))} · {time(item.at)}
        </Typography>
        <Typography variant="caption" color="text.secondary">
          {[typeLabels[item.type], item.subtitle, ...tags].filter(Boolean).join(" · ")}
        </Typography>
        {item.note && (
          <Typography variant="body2" sx={{ whiteSpace: "pre-wrap" }}>
            {item.note}
          </Typography>
        )}
        {reminder && (
          <Typography variant="caption" color="text.secondary">
            <AlarmIcon sx={{ fontSize: 13, verticalAlign: "-2px", mr: 0.5 }} />
            {reminder[1]}
          </Typography>
        )}
      </Stack>
    );
  };

  const monthGrid = () => {
    const offset = (days[0].getDay() + 6) % 7; // Monday first
    const cells = [...Array.from({ length: offset }, () => null), ...days];
    while (cells.length % 7 !== 0) cells.push(null);
    return (
      <Box sx={{ display: "grid", gridTemplateColumns: "repeat(7, minmax(0, 1fr))", border: 1, borderColor: "divider", borderRadius: 1, overflow: "hidden" }}>
        {WEEKDAYS.map((d) => (
          <Box key={d} sx={{ p: 0.75, textAlign: "center", bgcolor: "grey.50", borderBottom: 1, borderColor: "divider" }}>
            <Typography variant="caption" fontWeight={700}>
              {compact ? d[0] : d}
            </Typography>
          </Box>
        ))}
        {cells.map((date, index) => {
          const dayItems = date ? byDay[dayKey(date)] ?? [] : [];
          const isToday = date && dayKey(date) === todayKey;
          return (
            <Box
              key={index}
              {...dropProps(date)}
              // An empty spot starts a new entry on that day; on a phone the dots open the day's list.
              onClick={(e) => date && (compact && dayItems.length ? setPopover({ anchor: e.currentTarget, day: date }) : openForm(null, date))}
              sx={{
                minHeight: { xs: 56, md: 112 },
                p: 0.5,
                borderRight: (index + 1) % 7 === 0 ? 0 : 1,
                borderBottom: 1,
                borderColor: "divider",
                bgcolor: date ? "background.paper" : "grey.50",
                cursor: date ? "pointer" : "default",
                "&:hover": date ? { bgcolor: "grey.50" } : undefined,
                minWidth: 0,
              }}
            >
              {date && (
                <>
                  <Typography
                    variant="caption"
                    fontWeight={isToday ? 800 : 500}
                    onClick={(e) => {
                      e.stopPropagation();
                      setFocus(date);
                      changeView("day");
                    }}
                    sx={{ px: 0.6, py: 0.1, borderRadius: "50%", ...(isToday ? { bgcolor: "primary.main", color: "white" } : { "&:hover": { bgcolor: "grey.200" } }) }}
                  >
                    {date.getDate()}
                  </Typography>
                  {compact ? (
                    <Stack direction="row" spacing={0.25} flexWrap="wrap" useFlexGap sx={{ mt: 0.5 }}>
                      {dayItems.slice(0, 6).map((item, i) => (
                        <Box key={i} sx={{ width: 7, height: 7, borderRadius: "50%", bgcolor: colorOf(item), opacity: item.done || item.hidden ? 0.35 : 1 }} />
                      ))}
                    </Stack>
                  ) : (
                    <Stack spacing={0.25} sx={{ mt: 0.5 }}>
                      {dayItems.slice(0, 3).map((item, i) => chip(item, i))}
                      {dayItems.length > 3 && (
                        <Typography
                          variant="caption"
                          color="text.secondary"
                          onClick={(e) => {
                            e.stopPropagation();
                            setPopover({ anchor: e.currentTarget, day: date });
                          }}
                          sx={{ "&:hover": { textDecoration: "underline" } }}
                        >
                          +{dayItems.length - 3} more
                        </Typography>
                      )}
                    </Stack>
                  )}
                </>
              )}
            </Box>
          );
        })}
      </Box>
    );
  };

  // Week and day: a column per day, a row per hour. Items sit in their hour (the portal has no end times).
  const timeGridView = () => (
    <Box ref={timeGrid} sx={{ maxHeight: 640, overflow: "auto", border: 1, borderColor: "divider", borderRadius: 1 }}>
      <Box sx={{ display: "grid", gridTemplateColumns: `52px repeat(${days.length}, minmax(${days.length > 1 ? 90 : 0}px, 1fr))`, minWidth: days.length > 1 ? 700 : 0 }}>
        <Box sx={{ position: "sticky", top: 0, zIndex: 2, bgcolor: "grey.50", borderBottom: 1, borderColor: "divider" }} />
        {days.map((date) => {
          const isToday = dayKey(date) === todayKey;
          return (
            <Box
              key={dayKey(date)}
              onClick={() => {
                setFocus(date);
                changeView("day");
              }}
              sx={{ position: "sticky", top: 0, zIndex: 2, p: 0.75, textAlign: "center", bgcolor: "grey.50", borderBottom: 1, borderLeft: 1, borderColor: "divider", cursor: "pointer" }}
            >
              <Typography variant="caption" color={isToday ? "primary.main" : "text.secondary"} fontWeight={700} sx={{ display: "block" }}>
                {date.toLocaleDateString("en-IN", { weekday: "short" })}
              </Typography>
              <Typography variant="h6" lineHeight={1.2} color={isToday ? "primary.main" : undefined} fontWeight={isToday ? 800 : 500}>
                {date.getDate()}
              </Typography>
            </Box>
          );
        })}
        {HOURS.map((hour) => (
          <Box key={hour} sx={{ display: "contents" }}>
            <Box sx={{ minHeight: 48, pr: 0.75, textAlign: "right", borderBottom: 1, borderColor: "divider" }}>
              <Typography variant="caption" color="text.secondary">
                {new Date(2000, 0, 1, hour).toLocaleTimeString("en-IN", { hour: "numeric" })}
              </Typography>
            </Box>
            {days.map((date) => {
              const hourItems = (byDay[dayKey(date)] ?? []).filter((item) => istHour(item.at) === hour);
              return (
                <Box
                  key={dayKey(date)}
                  {...dropProps(date, hour)}
                  onClick={() => openForm(null, date, hour)}
                  sx={{ minHeight: 48, p: 0.25, borderBottom: 1, borderLeft: 1, borderColor: "divider", cursor: "pointer", minWidth: 0, "&:hover": { bgcolor: "grey.50" } }}
                >
                  <Stack spacing={0.25}>{hourItems.map((item, i) => chip(item, i, { wrap: days.length === 1 }))}</Stack>
                </Box>
              );
            })}
          </Box>
        ))}
      </Box>
    </Box>
  );

  // Schedule: the month as a list, day by day, with every action at hand.
  const scheduleView = () => {
    const withItems = days.filter((date) => (byDay[dayKey(date)] ?? []).length > 0);
    if (items && withItems.length === 0) {
      return (
        <Typography color="text.secondary" sx={{ py: 4, textAlign: "center" }}>
          Nothing on your calendar this month.
        </Typography>
      );
    }
    return (
      <Stack divider={<Box sx={{ borderTop: 1, borderColor: "divider" }} />}>
        {withItems.map((date) => (
          <Stack key={dayKey(date)} direction={{ xs: "column", sm: "row" }} spacing={2} sx={{ py: 1.5 }}>
            <Box sx={{ width: { sm: 120 }, flexShrink: 0 }}>
              <Typography variant="subtitle2" fontWeight={700} color={dayKey(date) === todayKey ? "primary.main" : undefined}>
                {date.toLocaleDateString("en-IN", { weekday: "short", day: "numeric", month: "short" })}
              </Typography>
            </Box>
            <Stack spacing={1.25} sx={{ flex: 1, minWidth: 0 }}>
              {byDay[dayKey(date)].map((item) => (
                <Stack key={uid(item)} direction={{ xs: "column", md: "row" }} spacing={1} justifyContent="space-between" sx={{ borderLeft: 3, borderColor: colorOf(item), pl: 1.25, opacity: item.hidden ? 0.6 : 1 }}>
                  {details(item)}
                  {actions(item)}
                </Stack>
              ))}
            </Stack>
          </Stack>
        ))}
      </Stack>
    );
  };

  return (
    <Card>
      <CardContent>
        <Stack direction={{ xs: "column", md: "row" }} spacing={1.5} alignItems={{ md: "center" }} justifyContent="space-between" sx={{ mb: 2 }}>
          <Stack direction="row" spacing={1} alignItems="center">
            <Button variant="contained" startIcon={<AddIcon />} onClick={() => openForm(null, view === "month" || view === "schedule" ? null : focus)}>
              Create
            </Button>
            <Button variant="outlined" onClick={goToday}>
              Today
            </Button>
            <IconButton onClick={() => step(-1)} aria-label="Previous">
              <ChevronLeftIcon />
            </IconButton>
            <IconButton onClick={() => step(1)} aria-label="Next">
              <ChevronRightIcon />
            </IconButton>
            <Typography variant="h6" fontWeight={700} noWrap>
              {heading}
            </Typography>
          </Stack>
          <Stack direction="row" spacing={1} alignItems="center">
            <TextField
              size="small"
              placeholder="Search"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              slotProps={{ input: { startAdornment: <InputAdornment position="start"><SearchIcon fontSize="small" /></InputAdornment> } }}
              sx={{ width: { xs: "100%", md: 180 } }}
            />
            <ToggleButtonGroup size="small" exclusive value={view} onChange={(_e, next) => changeView(next)}>
              {Object.entries(VIEWS).map(([key, label]) => (
                <ToggleButton key={key} value={key} sx={{ px: 1.25 }}>
                  {label}
                </ToggleButton>
              ))}
            </ToggleButtonGroup>
          </Stack>
        </Stack>

        <Stack direction="row" spacing={0.5} flexWrap="wrap" useFlexGap alignItems="center" sx={{ mb: 1 }}>
          {Object.entries(typeLabels)
            .filter(([type]) => endpoint || type === "personal")
            .map(([type, label]) => {
              const off = hiddenTypes.includes(type);
              return (
                <FormControlLabel
                  key={type}
                  sx={{ mr: 1 }}
                  control={
                    <Checkbox
                      size="small"
                      checked={!off}
                      onChange={() => setHiddenTypes((list) => (off ? list.filter((t) => t !== type) : [...list, type]))}
                      sx={{ color: typeColors[type], "&.Mui-checked": { color: typeColors[type] } }}
                    />
                  }
                  label={<Typography variant="caption">{label}</Typography>}
                />
              );
            })}
          {hiddenCount > 0 && (
            <FormControlLabel
              sx={{ ml: { md: "auto" } }}
              control={<Switch size="small" checked={showHidden} onChange={(e) => setShowHidden(e.target.checked)} />}
              label={<Typography variant="caption">Show hidden ({hiddenCount})</Typography>}
            />
          )}
        </Stack>
        <Typography variant="caption" color="text.secondary" sx={{ display: "block", mb: 1 }}>
          Click an empty spot to add an entry, click an item to edit, hide or mark it done, or drag it to another day. Your changes show only in your calendar.
        </Typography>

        {error && (
          <Alert severity="error" onClose={() => setError(null)} sx={{ mb: 1 }}>
            {error}
          </Alert>
        )}
        {(!items || saving) && <LinearProgress sx={{ mb: 1 }} />}

        {view === "month" ? monthGrid() : view === "schedule" ? scheduleView() : timeGridView()}

        <Popover
          open={Boolean(popover && (popItem || popover.day))}
          anchorEl={popover?.anchor}
          onClose={() => setPopover(null)}
          anchorOrigin={{ vertical: "bottom", horizontal: "center" }}
          transformOrigin={{ vertical: "top", horizontal: "center" }}
        >
          <Box sx={{ p: 1.5, width: 360, maxWidth: "calc(100vw - 32px)" }}>
            {popItem ? (
              <Stack spacing={1}>
                {details(popItem)}
                {actions(popItem)}
              </Stack>
            ) : (
              <Stack spacing={1}>
                <Typography variant="subtitle2" fontWeight={700}>
                  {popover?.day && longDay(popover.day)}
                </Typography>
                {popDayItems.map((item, i) => chip(item, i, { wrap: true }))}
                <Button size="small" startIcon={<AddIcon />} onClick={() => openForm(null, popover?.day)} sx={{ alignSelf: "flex-start" }}>
                  Add entry on this day
                </Button>
              </Stack>
            )}
          </Box>
        </Popover>

        <Dialog open={Boolean(form)} onClose={() => !saving && setForm(null)} fullWidth maxWidth="xs">
          <DialogTitle>{form?.kind === "auto" ? `Edit ${typeLabels[form.item.type].toLowerCase()}` : form?.item ? "Edit entry" : "Add entry"}</DialogTitle>
          <DialogContent>
            <Stack spacing={2} sx={{ pt: 1 }}>
              <Typography variant="body2" color="text.secondary">
                {form?.kind === "auto"
                  ? "Your changes show only in your calendar. The placement data behind this item is not changed."
                  : "Only you can see the entries you add to your calendar."}
              </Typography>
              {form?.kind === "auto" && form.item.original && (
                <Typography variant="caption" color="text.secondary">
                  Original: {time(form.item.original.at)}, {new Date(form.item.original.at).toLocaleDateString("en-IN", { day: "numeric", month: "short", timeZone: IST })} · {form.item.original.title}
                </Typography>
              )}
              {formError && <Alert severity="error">{formError}</Alert>}
              <TextField
                label="Title"
                required
                autoFocus
                value={form?.title ?? ""}
                onChange={(e) => setForm((d) => ({ ...d, title: e.target.value }))}
                slotProps={{ htmlInput: { maxLength: form?.kind === "auto" ? 255 : 150 } }}
              />
              <TextField
                required
                type="datetime-local"
                label="Date and time (IST)"
                value={form?.starts_at ?? ""}
                onChange={(e) => setForm((d) => ({ ...d, starts_at: e.target.value }))}
                slotProps={{ inputLabel: { shrink: true } }}
              />
              <TextField
                select
                label="Reminder"
                value={form?.remind_minutes ?? "none"}
                onChange={(e) => setForm((d) => ({ ...d, remind_minutes: e.target.value === "none" ? null : Number(e.target.value) }))}
                helperText="Sent as a portal notification."
              >
                {reminderOptions.map(([minutes, label]) => (
                  <MenuItem key={label} value={minutes ?? "none"}>
                    {label}
                  </MenuItem>
                ))}
              </TextField>
              {form?.kind === "personal" && (
                <Box>
                  <Typography variant="caption" color="text.secondary">
                    Colour
                  </Typography>
                  <Stack direction="row" spacing={1} sx={{ mt: 0.5 }}>
                    {Object.entries(entryColors).map(([name, hex]) => (
                      <Tooltip key={name} title={name}>
                        <Box
                          component="button"
                          type="button"
                          aria-label={`Colour ${name}`}
                          onClick={() => setForm((d) => ({ ...d, color: name }))}
                          sx={{ width: 24, height: 24, borderRadius: "50%", bgcolor: hex, border: 0, cursor: "pointer", outline: form.color === name ? `2px solid ${hex}` : "none", outlineOffset: 2, display: "grid", placeItems: "center" }}
                        >
                          {form.color === name && <CheckIcon sx={{ fontSize: 14, color: "white" }} />}
                        </Box>
                      </Tooltip>
                    ))}
                  </Stack>
                </Box>
              )}
              <TextField
                label="Note (optional)"
                multiline
                minRows={2}
                value={form?.note ?? ""}
                onChange={(e) => setForm((d) => ({ ...d, note: e.target.value }))}
                slotProps={{ htmlInput: { maxLength: 2000 } }}
              />
            </Stack>
          </DialogContent>
          <DialogActions sx={{ flexWrap: "wrap" }}>
            {form?.kind === "personal" && form.item && (
              <Button color="error" startIcon={<DeleteIcon />} onClick={() => remove(form.item)} disabled={saving} sx={{ mr: "auto" }}>
                Delete
              </Button>
            )}
            {form?.kind === "auto" && (
              <Stack direction="row" sx={{ mr: "auto" }}>
                <Button color="error" startIcon={<HideIcon />} onClick={() => setHidden(form.item, true)} disabled={saving || form.item.hidden}>
                  Hide
                </Button>
                {form.item.customised && (
                  <Button startIcon={<RestoreIcon />} onClick={() => reset(form.item)} disabled={saving}>
                    Reset
                  </Button>
                )}
              </Stack>
            )}
            <Button onClick={() => setForm(null)} disabled={saving}>
              Cancel
            </Button>
            <Button variant="contained" onClick={save} disabled={saving}>
              Save
            </Button>
          </DialogActions>
        </Dialog>

        <Snackbar
          open={Boolean(toast)}
          autoHideDuration={5000}
          onClose={(_e, reason) => reason !== "clickaway" && setToast(null)}
          message={toast?.text}
          action={
            toast?.undo && (
              <Button
                color="secondary"
                size="small"
                onClick={() => {
                  const undo = toast.undo;
                  setToast(null);
                  undo();
                }}
              >
                Undo
              </Button>
            )
          }
        />
      </CardContent>
    </Card>
  );
}
