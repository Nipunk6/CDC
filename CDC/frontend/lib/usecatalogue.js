"use client";

import { useEffect, useState } from "react";
import { defaultProgrammes } from "@/components/forms/shared";

const builtIn = () =>
  Object.fromEntries(defaultProgrammes.map((p) => [p.programme, p.branches.map((b) => b.branch)]));

// Programme → branches, built-ins plus admin-added custom branches (mirrors App\Support\ProgrammeCatalogue).
export default function useCatalogue(api) {
  const [catalogue, setCatalogue] = useState(builtIn);

  useEffect(() => {
    let cancelled = false;
    const run = async () => {
      try {
        const response = await api("/programme-branches");
        const merged = builtIn();
        (response.programme_branches ?? []).forEach((group) => {
          merged[group.programme] = Array.from(new Set([...(merged[group.programme] ?? []), ...group.branches]));
        });
        if (!cancelled) setCatalogue(merged);
      } catch {
        // built-ins are enough to keep the form usable
      }
    };
    void run();
    return () => {
      cancelled = true;
    };
  }, [api]);

  return catalogue;
}

// "B.Tech (4 Year) / ..." → "B.Tech" for compact table cells.
export const shortProgramme = (programme) => String(programme ?? "").split(/[(/]/)[0].trim() || programme;
