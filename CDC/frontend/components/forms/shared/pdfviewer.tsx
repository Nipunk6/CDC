"use client";
import "@/lib/polyfill";
import React, { useState, useRef, useEffect } from "react";
import { Document, Page, pdfjs } from "react-pdf";
import { Box, Button, CircularProgress, IconButton, Typography, Tooltip } from "@mui/material";
import CheckCircleOutlineIcon from "@mui/icons-material/CheckCircleOutline";
import FitScreenIcon from "@mui/icons-material/FitScreen";
import AddIcon from "@mui/icons-material/Add";
import RemoveIcon from "@mui/icons-material/Remove";

// Basic worker setup
pdfjs.GlobalWorkerOptions.workerSrc = `//unpkg.com/pdfjs-dist@${pdfjs.version}/build/pdf.worker.min.mjs`;

const BASE_WIDTH = 800;
const MIN_ZOOM = 0.5;
const MAX_ZOOM = 2.0;
const ZOOM_STEP = 0.15;

interface PdfViewerProps {
  url: string;
  onReachBottom: () => void;
}

export default function PdfViewer({ url, onReachBottom }: PdfViewerProps) {
  const [numPages, setNumPages] = useState<number>(0);
  const [renderedPages, setRenderedPages] = useState<Record<number, boolean>>({});
  const [hasReachedEnd, setHasReachedEnd] = useState(false);
  const [agreed, setAgreed] = useState(false);
  const [zoom, setZoom] = useState(1.0);
  const scrollRef = useRef<HTMLDivElement>(null);

  // Reset state when document URL changes
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- works correctly; refactor deferred, see D33
    setNumPages(0);
    setRenderedPages({});
    setHasReachedEnd(false);
    setAgreed(false);
    setZoom(1.0);
  }, [url]);

  function onDocumentLoadSuccess({ numPages }: { numPages: number }) {
    setNumPages(numPages);
  }

  const handleScroll = () => {
    if (!scrollRef.current || numPages === 0 || hasReachedEnd) return;
    const totalRendered = Object.values(renderedPages).filter(Boolean).length;
    if (totalRendered < numPages) return;

    const { scrollTop, scrollHeight, clientHeight } = scrollRef.current;
    if (scrollHeight - scrollTop - clientHeight < 50) {
      setHasReachedEnd(true);
    }
  };

  // If content fits on screen without scrolling, show the button immediately
  useEffect(() => {
    if (numPages > 0 && !hasReachedEnd) {
      const totalRendered = Object.values(renderedPages).filter(Boolean).length;
      if (totalRendered === numPages && scrollRef.current) {
        const { scrollHeight, clientHeight } = scrollRef.current;
        if (scrollHeight > 0 && scrollHeight <= clientHeight) {
          // eslint-disable-next-line react-hooks/set-state-in-effect -- works correctly; refactor deferred, see D33
          setHasReachedEnd(true);
        }
      }
    }
  }, [renderedPages, numPages, hasReachedEnd]);

  const handleAgree = () => {
    setAgreed(true);
    onReachBottom();
  };

  const handleZoomIn = () => setZoom((prev) => Math.min(prev + ZOOM_STEP, MAX_ZOOM));
  const handleZoomOut = () => setZoom((prev) => Math.max(prev - ZOOM_STEP, MIN_ZOOM));
  const handleZoomReset = () => setZoom(1.0);

  const zoomPercent = Math.round(zoom * 100);

  return (
    <Box sx={{ height: "100%", display: "flex", flexDirection: "column", position: "relative" }}>
      {/* Zoom Toolbar */}
      <Box
        sx={{
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          gap: 0.5,
          py: 0.75,
          px: 2,
          bgcolor: "white",
          borderBottom: "1px solid",
          borderColor: "divider",
          flexShrink: 0,
          zIndex: 10,
        }}
      >
        <Tooltip title="Zoom Out" arrow>
          <span>
            <IconButton
              size="small"
              onClick={handleZoomOut}
              disabled={zoom <= MIN_ZOOM}
              sx={{
                bgcolor: "grey.100",
                "&:hover": { bgcolor: "grey.200" },
                width: 32,
                height: 32,
              }}
            >
              <RemoveIcon fontSize="small" />
            </IconButton>
          </span>
        </Tooltip>

        <Box
          sx={{
            minWidth: 52,
            textAlign: "center",
            px: 1,
            py: 0.25,
            borderRadius: 1,
            bgcolor: "grey.50",
            border: "1px solid",
            borderColor: "grey.300",
          }}
        >
          <Typography variant="body2" fontWeight={600} fontSize="0.8rem">
            {zoomPercent}%
          </Typography>
        </Box>

        <Tooltip title="Zoom In" arrow>
          <span>
            <IconButton
              size="small"
              onClick={handleZoomIn}
              disabled={zoom >= MAX_ZOOM}
              sx={{
                bgcolor: "grey.100",
                "&:hover": { bgcolor: "grey.200" },
                width: 32,
                height: 32,
              }}
            >
              <AddIcon fontSize="small" />
            </IconButton>
          </span>
        </Tooltip>

        <Box sx={{ width: "1px", height: 20, bgcolor: "divider", mx: 0.5 }} />

        <Tooltip title="Reset Zoom" arrow>
          <IconButton
            size="small"
            onClick={handleZoomReset}
            sx={{
              bgcolor: zoom !== 1.0 ? "primary.50" : "grey.100",
              color: zoom !== 1.0 ? "primary.main" : "text.secondary",
              "&:hover": { bgcolor: zoom !== 1.0 ? "primary.100" : "grey.200" },
              width: 32,
              height: 32,
            }}
          >
            <FitScreenIcon fontSize="small" />
          </IconButton>
        </Tooltip>
      </Box>

      {/* PDF Content */}
      <Box
        ref={scrollRef}
        onScroll={handleScroll}
        sx={{
          flex: 1,
          overflowY: "auto",
          overflowX: "auto",
          display: "flex",
          flexDirection: "column",
          alignItems: "center",
          bgcolor: "#f5f5f5",
          p: 2,
        }}
      >
        <Document
          file={url}
          onLoadSuccess={onDocumentLoadSuccess}
          loading={<CircularProgress />}
          error={<Typography color="error">Failed to load PDF.</Typography>}
        >
          {Array.from(new Array(numPages), (el, index) => (
            <Box key={`page_${index + 1}`} sx={{ mb: 2, boxShadow: 3 }}>
              <Page
                pageNumber={index + 1}
                renderTextLayer={false}
                renderAnnotationLayer={false}
                width={BASE_WIDTH * zoom}
                onRenderSuccess={() => {
                  setRenderedPages((prev) => ({ ...prev, [index + 1]: true }));
                }}
              />
            </Box>
          ))}
        </Document>

      </Box>

      {/* Agreement button — STATIC/FIXED at the bottom */}
      <Box
        sx={{
          width: "100%",
          p: 2.5,
          bgcolor: agreed ? "rgba(46, 125, 50, 0.05)" : "white",
          borderTop: "1px solid",
          borderColor: agreed ? "success.light" : "divider",
          textAlign: "center",
          boxShadow: "0 -4px 12px rgba(0,0,0,0.05)",
          zIndex: 10,
          flexShrink: 0,
        }}
      >
        {agreed ? (
          <Box sx={{ display: "flex", alignItems: "center", justifyContent: "center", gap: 1 }}>
            <CheckCircleOutlineIcon color="success" />
            <Typography variant="body1" fontWeight={700} color="success.main">
              You have agreed to this guideline
            </Typography>
          </Box>
        ) : (
          <Box sx={{ display: "flex", flexDirection: "column", alignItems: "center", gap: 1.5 }}>
            <Typography variant="body2" color="text.secondary" fontWeight={500}>
              {!hasReachedEnd
                ? "Please read the document till the end before proceeding further."
                : "You have finished reading this document. Please confirm below."}
            </Typography>
            <Button
              variant="contained"
              size="large"
              disabled={!hasReachedEnd}
              onClick={handleAgree}
              startIcon={<CheckCircleOutlineIcon />}
              sx={{
                px: 5,
                py: 1.25,
                borderRadius: 2,
                fontWeight: 700,
                fontSize: "0.95rem",
                textTransform: "none",
                bgcolor: "#7B1113",
                color: "white",
                boxShadow: 2,
                "&:hover": {
                  bgcolor: "#5A0C0E",
                  boxShadow: 4,
                },
                "&.Mui-disabled": {
                  bgcolor: "rgba(0, 0, 0, 0.08)",
                  color: "rgba(0, 0, 0, 0.26)",
                }
              }}
            >
              I have read this guideline and I agree
            </Button>
          </Box>
        )}
      </Box>
    </Box>
  );
}
