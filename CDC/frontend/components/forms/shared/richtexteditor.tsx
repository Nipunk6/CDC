"use client";

import React, { useMemo } from "react";
import dynamic from "next/dynamic";
import { Box, Typography } from "@mui/material";
import "react-quill-new/dist/quill.snow.css";

const ReactQuill = dynamic(() => import("react-quill-new"), { 
  ssr: false,
  loading: () => <Box sx={{ minHeight: 242, bgcolor: "action.hover", borderRadius: 1, animation: "pulse 1.5s infinite" }} />
});

interface RichTextEditorProps {
  value: string;
  onChange: (value: string) => void;
  label?: string;
  placeholder?: string;
  error?: boolean;
  helperText?: React.ReactNode;
  disabled?: boolean;
  required?: boolean;
}

export default function RichTextEditor({
  value,
  onChange,
  label,
  placeholder,
  error,
  helperText,
  disabled,
  required = false,
}: RichTextEditorProps) {
  const modules = useMemo(
    () => ({
      toolbar: [
        [{ header: [1, 2, 3, false] }],
        ["bold", "italic", "underline", "strike"],
        [{ list: "ordered" }, { list: "bullet" }],
        ["link", "clean"],
      ],
    }),
    []
  );

  return (
    <Box sx={{ width: "100%" }}>
      {label && (
        <Typography
          variant="body2"
          sx={{
            mb: 0.5,
            ml: 0.5,
            fontWeight: 500,
            color: error ? "error.main" : "text.primary",
            opacity: disabled ? 0.6 : 1,
          }}
        >
          {label}
          {required && (
            <Typography
              component="span"
              sx={{ color: "error.main", ml: 0.3, lineHeight: 1 }}
              aria-hidden="true"
            >
              *
            </Typography>
          )}
        </Typography>
      )}

      <Box
        sx={{
          border: "1px solid",
          borderColor: error ? "error.main" : "divider",
          borderRadius: 1,
          overflow: "hidden",
          opacity: disabled ? 0.6 : 1,
          pointerEvents: disabled ? "none" : "auto",
          transition: "border-color 0.2s",
          "&:hover": {
            borderColor: error ? "error.main" : "text.primary",
          },
          "&:focus-within": {
            borderColor: error ? "error.main" : "primary.main",
            borderWidth: "1px",
          },
          "& .ql-toolbar": {
            border: "none",
            borderBottom: "1px solid",
            borderColor: "divider",
            bgcolor: "background.default",
            fontFamily: "inherit",
          },
          "& .ql-container": {
            border: "none",
            minHeight: "200px",
            fontSize: "1rem",
            fontFamily: "inherit",
            bgcolor: "background.paper",
          },
          "& .ql-editor": {
            minHeight: "200px",
            "&:focus": {
              outline: "none",
            },
            // Reset common editor tag margins to fit the design system
            "& p": { marginBottom: "0.5em" },
            "& h1, & h2, & h3": { margin: "0.5em 0" },
          },
        }}
      >
        <ReactQuill
          theme="snow"
          value={value}
          onChange={onChange}
          placeholder={placeholder}
          modules={modules}
          readOnly={disabled}
        />
      </Box>

      {helperText && (
        <Typography
          variant="caption"
          sx={{
            mt: 0.5,
            px: 1.5,
            display: "block",
            color: error ? "error.main" : "text.secondary",
            opacity: disabled ? 0.6 : 1,
          }}
        >
          {helperText}
        </Typography>
      )}
    </Box>
  );
}
