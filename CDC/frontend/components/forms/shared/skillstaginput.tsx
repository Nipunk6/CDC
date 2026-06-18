"use client";

import { ReactNode } from "react";
import { useState, KeyboardEvent } from "react";
import {
  Autocomplete,
  Chip,
  TextField,
  Box,
  Typography,
  Stack,
} from "@mui/material";

interface SkillsTagInputProps {
  value: string[];
  onChange: (skills: string[]) => void;
  suggestions?: string[];
  label?: ReactNode;
  placeholder?: string;
  maxTags?: number;
  error?: boolean;
  helperText?: string;
}

const defaultSuggestions = [
  "Python",
  "JavaScript",
  "TypeScript",
  "Java",
  "C++",
  "C",
  "Go",
  "Rust",
  "React",
  "Angular",
  "Vue.js",
  "Node.js",
  "Django",
  "Flask",
  "Spring Boot",
  "Machine Learning",
  "Deep Learning",
  "Data Science",
  "SQL",
  "MongoDB",
  "PostgreSQL",
  "AWS",
  "Azure",
  "GCP",
  "Docker",
  "Kubernetes",
  "Git",
  "Linux",
  "Problem Solving",
  "Communication",
  "Leadership",
  "Teamwork",
  "Project Management",
  "Agile",
  "MATLAB",
  "AutoCAD",
  "Embedded Systems",
  "IoT",
  "Blockchain",
  "Cybersecurity",
];

export default function SkillsTagInput({
  value,
  onChange,
  suggestions = defaultSuggestions,
  label = "Required Skills",
  placeholder = "Type and press Enter to add skills",
  maxTags = 15,
  error,
  helperText,
}: SkillsTagInputProps) {
  const [inputValue, setInputValue] = useState("");

  const handleDelete = (skillToDelete: string) => {
    onChange(value.filter((skill) => skill !== skillToDelete));
  };

  const handleAddSkill = (skill: string) => {
    const trimmed = skill.trim();
    if (trimmed && !value.includes(trimmed) && value.length < maxTags) {
      onChange([...value, trimmed]);
    }
    setInputValue("");
  };

  const filteredSuggestions = suggestions.filter(
    (s) => !value.includes(s) && s.toLowerCase().includes(inputValue.toLowerCase())
  );

  return (
    <Box>
      <Autocomplete
        multiple
        freeSolo
        options={filteredSuggestions}
        value={value}
        inputValue={inputValue}
        onInputChange={(_, newInputValue) => setInputValue(newInputValue)}
        onChange={(_, newValue) => {
          if (newValue.length <= maxTags) {
            onChange(newValue as string[]);
          }
        }}
        renderTags={(tagValue, getTagProps) =>
          tagValue.map((option, index) => (
            <Chip
              {...getTagProps({ index })}
              key={option}
              label={option}
              size="small"
              color="primary"
              variant="outlined"
              onDelete={() => handleDelete(option)}
              sx={{
                borderRadius: 2,
                "& .MuiChip-label": { fontWeight: 500 },
              }}
            />
          ))
        }
        renderInput={(params) => (
          <TextField
            {...params}
            label={label}
            placeholder={value.length === 0 ? placeholder : ""}
            error={error}
            helperText={helperText}
            onKeyDown={(e: KeyboardEvent<HTMLDivElement>) => {
              if (e.key === "Enter" && inputValue.trim()) {
                e.preventDefault();
                handleAddSkill(inputValue);
              }
            }}
          />
        )}
      />
      <Stack direction="row" justifyContent="space-between" mt={0.5} px={1}>
        <Typography variant="caption" color="text.secondary">
          Press Enter to add custom skills
        </Typography>
        <Typography
          variant="caption"
          color={value.length >= maxTags ? "error" : "text.secondary"}
        >
          {value.length}/{maxTags} skills
        </Typography>
      </Stack>
    </Box>
  );
}
