"use client";

import { useEffect, useState } from "react";
import { Autocomplete, Chip, TextField } from "@mui/material";

import { adminApi } from "@/lib/adminapi";

/**
 * "Allowed Student Categories" (Superset parity S8.4): pick the CDC categories a student needs (any one of them).
 * `value` is a list of category ids; empty = no restriction.
 */
export default function StudentCategoryPicker({ value = [], onChange, disabled = false }) {
  const [categories, setCategories] = useState(null);

  useEffect(() => {
    let cancelled = false;
    adminApi("/admin/student-categories")
      .then((r) => !cancelled && setCategories(r.categories ?? []))
      .catch(() => !cancelled && setCategories([]));
    return () => {
      cancelled = true;
    };
  }, []);

  const options = categories ?? [];
  const selected = options.filter((c) => value.includes(c.id));

  return (
    <Autocomplete
      multiple
      disabled={disabled}
      loading={categories === null}
      options={options}
      value={selected}
      getOptionLabel={(c) => c.title}
      isOptionEqualToValue={(a, b) => a.id === b.id}
      onChange={(_e, chosen) => onChange(chosen.map((c) => c.id))}
      renderTags={(tags, getTagProps) => tags.map((c, index) => <Chip size="small" label={c.title} {...getTagProps({ index })} key={c.id} />)}
      renderInput={(params) => (
        <TextField
          {...params}
          label="Allowed Student Categories"
          placeholder={selected.length ? "" : "Any student (no restriction)"}
          helperText={
            options.length === 0 && categories !== null
              ? "No student categories yet. Create them under Placement → Student Categories."
              : "Optional. Only students in at least one of these categories are eligible."
          }
        />
      )}
    />
  );
}
