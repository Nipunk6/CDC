"use client";

import { useMemo, useSyncExternalStore } from "react";

// "Recently Visited" placements (S8.3): a per-browser convenience only. Storage can be blocked or empty
// (private windows, cleared data), so every access is guarded and the list simply comes back empty.
const KEY = "cdc.admin.recentPlacements";
const LIMIT = 6;
const EVENT = "recent-placements-updated";

function read() {
  try {
    return window.localStorage.getItem(KEY) ?? "";
  } catch {
    return "";
  }
}

function parse(raw) {
  try {
    const ids = JSON.parse(raw || "[]");
    return Array.isArray(ids) ? ids.map(Number).filter(Number.isFinite) : [];
  } catch {
    return [];
  }
}

export function rememberPlacement(id) {
  const numeric = Number(id);
  if (!Number.isFinite(numeric)) return;
  try {
    const next = [numeric, ...parse(read()).filter((existing) => existing !== numeric)].slice(0, LIMIT);
    window.localStorage.setItem(KEY, JSON.stringify(next));
    window.dispatchEvent(new Event(EVENT));
  } catch {
    // ignore: storage unavailable
  }
}

function subscribe(callback) {
  window.addEventListener(EVENT, callback);
  window.addEventListener("storage", callback);
  return () => {
    window.removeEventListener(EVENT, callback);
    window.removeEventListener("storage", callback);
  };
}

/** Ids of the placements this browser opened most recently, newest first. */
export function useRecentPlacementIds() {
  const raw = useSyncExternalStore(subscribe, read, () => "");
  return useMemo(() => parse(raw), [raw]);
}
