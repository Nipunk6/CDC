"use client";

import { Autocomplete, Box, Stack, TextField, Typography, createFilterOptions } from "@mui/material";
import { SyntheticEvent, useState } from "react";

const SECTOR_OPTIONS = [
  "Information Technology",
  "Software / SaaS",
  "Analytics / Data Science",
  "Consulting",
  "Finance / Banking",
  "Investment Banking",
  "Insurance",
  "FinTech",
  "Manufacturing",
  "Automobile",
  "Aerospace / Defence",
  "Energy / Oil & Gas",
  "Power / Utilities",
  "Mining / Metals",
  "Construction / Infrastructure",
  "Real Estate",
  "FMCG",
  "Retail / E-commerce",
  "Healthcare / Pharma",
  "Biotechnology",
  "Education / EdTech",
  "Telecommunications",
  "Media / Entertainment",
  "Government / PSU",
  "Research & Development",
  "Logistics / Supply Chain",
  "Semiconductors / VLSI",
  "Robotics / AI / ML",
  "Blockchain / Web3",
  "Agriculture / AgriTech",
  "Legal",
  "Non-Profit / NGO",
  "Other",
];

const filter = createFilterOptions<string>();

interface SectorAutocompleteProps {
  value: string;
  onChange: (value: string) => void;
  label?: string;
  disabled?: boolean;
  error?: boolean;
  helperText?: string;
  fullWidth?: boolean;
  InputProps?: Record<string, unknown>;
}

export default function SectorAutocomplete({
  value,
  onChange,
  label = "Sector",
  disabled = false,
  error,
  helperText,
  fullWidth = true,
  InputProps,
}: SectorAutocompleteProps) {
  // Track whether user chose "Other" from dropdown to show custom input
  const isOtherSelected = value === "Other" || (value !== "" && !SECTOR_OPTIONS.slice(0, -1).some(
    (opt) => opt.toLowerCase() === value.toLowerCase()
  ) && value !== "Other");

  // If the user previously entered a custom value (not "Other" literal and not in list),
  // store it separately
  const [customSector, setCustomSector] = useState(() => {
    if (value && value !== "Other" && !SECTOR_OPTIONS.slice(0, -1).some(
      (opt) => opt.toLowerCase() === value.toLowerCase()
    )) {
      return value;
    }
    return "";
  });

  // Determine the displayed autocomplete value
  const autocompleteValue = (() => {
    if (value === "Other") return "Other";
    if (value && !SECTOR_OPTIONS.some((opt) => opt.toLowerCase() === value.toLowerCase())) {
      // Custom value → show "Other" in dropdown
      return "Other";
    }
    return value || null;
  })();

  return (
    <Box sx={{ width: fullWidth ? "100%" : "auto" }}>
      <Autocomplete
        value={autocompleteValue}
        onChange={(_event: SyntheticEvent, newValue: string | null) => {
          if (newValue === "Other") {
            // When "Other" is selected, clear the value but show custom input
            setCustomSector("");
            onChange("Other");
          } else if (newValue !== null) {
            setCustomSector("");
            onChange(newValue);
          } else {
            setCustomSector("");
            onChange("");
          }
        }}
        options={SECTOR_OPTIONS}
        filterOptions={(options, params) => {
          return filter(options, params);
        }}
        selectOnFocus
        clearOnBlur
        handleHomeEndKeys
        disabled={disabled}
        fullWidth={fullWidth}
        renderInput={(params) => (
          <TextField
            {...params}
            label={label}
            error={error && !isOtherSelected}
            helperText={isOtherSelected ? undefined : helperText}
            placeholder="Select a sector"
            InputProps={{
              ...params.InputProps,
              sx: { borderRadius: 2, ...((InputProps?.sx as object) ?? {}) },
            }}
          />
        )}
      />
      {/* Show custom text field when "Other" is selected */}
      {(autocompleteValue === "Other") && (
        <TextField
          fullWidth
          label="Specify your sector"
          value={customSector}
          onChange={(e) => {
            const val = e.target.value;
            setCustomSector(val);
            if (val.trim()) {
              onChange(val);
            } else {
              onChange("Other");
            }
          }}
          error={error}
          helperText={helperText || "Please specify your sector"}
          placeholder="e.g., Hospitality, Fashion, etc."
          sx={{ mt: 1.5 }}
          InputProps={{ sx: { borderRadius: 2 } }}
          autoFocus
          disabled={disabled}
        />
      )}
    </Box>
  );
}

export { SECTOR_OPTIONS };
