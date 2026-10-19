"use client";

import { useEffect, useSyncExternalStore } from "react";
import { usePathname, useRouter } from "next/navigation";

// "Back" returns to the page you came from. The shell records the pages visited in this tab since it loaded, so a
// Back control can tell whether the previous history entry is a page of this portal (then it steps back, like the
// browser's button) or not (a direct link, a reload, a new tab: then it goes to the page's usual parent instead).
// Only paths are recorded: tabs, filters and pages that live in the query string do not count as separate pages.
const visited = [];
const listeners = new Set();

function notify() {
  listeners.forEach((listener) => listener());
}

function record(pathname) {
  if (!pathname || visited[visited.length - 1] === pathname) return;
  if (visited.length >= 2 && visited[visited.length - 2] === pathname) visited.pop(); // went back a page
  else visited.push(pathname);
  notify();
}

function subscribe(listener) {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

const hasPrevious = () => visited.length >= 2;

/** Called once by the app shell: keeps the record of visited pages up to date. */
export function useTrackNavigation() {
  const pathname = usePathname();
  useEffect(() => {
    record(pathname);
  }, [pathname]);
}

/**
 * Back for a page whose parent is `fallbackHref`. Returns whether there is a previous page in this tab to return to,
 * and a click handler that steps back to it (and otherwise lets the link go to `fallbackHref`).
 */
export function useBack(fallbackHref) {
  const router = useRouter();
  const canGoBack = useSyncExternalStore(subscribe, hasPrevious, () => false);

  const onClick = (event) => {
    if (!hasPrevious() || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    router.back();
  };

  return { canGoBack, href: fallbackHref, onClick };
}
