"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { Chip, Stack } from "@mui/material";

import { adminApi } from "@/lib/adminapi";

/** The student's Student Categories (S8.4) as chips; managed on the Student Categories page. */
export default function StudentCategoryChips({ studentId }) {
  const [categories, setCategories] = useState([]);

  useEffect(() => {
    let cancelled = false;
    adminApi(`/admin/students/${studentId}/categories`)
      .then((r) => !cancelled && setCategories(r.categories ?? []))
      .catch(() => {});
    return () => {
      cancelled = true;
    };
  }, [studentId]);

  if (categories.length === 0) return null;

  return (
    <Stack direction="row" spacing={0.5} flexWrap="wrap" useFlexGap sx={{ mt: 0.75 }}>
      {categories.map((c) => (
        <Chip key={c.id} size="small" color="secondary" variant="outlined" label={c.title} component={Link} href="/admin/student-categories" clickable />
      ))}
    </Stack>
  );
}
