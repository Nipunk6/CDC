"use client";
import "@/lib/polyfill";
import React, { useState, useRef, useEffect } from "react";
import { Document, Page, pdfjs } from "react-pdf";
import { Box, CircularProgress, Typography } from "@mui/material";

// Basic worker setup
pdfjs.GlobalWorkerOptions.workerSrc = `//unpkg.com/pdfjs-dist@${pdfjs.version}/build/pdf.worker.min.mjs`;

interface PdfViewerProps {
  url: string;
  onReachBottom: () => void;
}

export default function PdfViewer({ url, onReachBottom }: PdfViewerProps) {
  const [numPages, setNumPages] = useState<number>(0);
  const [renderedPages, setRenderedPages] = useState<Record<number, boolean>>({});
  const scrollRef = useRef<HTMLDivElement>(null);

  // Reset page count and rendered pages when document URL changes
  useEffect(() => {
    setNumPages(0);
    setRenderedPages({});
  }, [url]);

  function onDocumentLoadSuccess({ numPages }: { numPages: number }) {
    setNumPages(numPages);
  }

  const handleScroll = () => {
    if (!scrollRef.current || numPages === 0) return;
    const totalRendered = Object.values(renderedPages).filter(Boolean).length;
    if (totalRendered < numPages) return;

    const { scrollTop, scrollHeight, clientHeight } = scrollRef.current;
    if (scrollHeight - scrollTop - clientHeight < 50) {
      onReachBottom();
    }
  };

  useEffect(() => {
    if (numPages > 0) {
      const totalRendered = Object.values(renderedPages).filter(Boolean).length;
      if (totalRendered === numPages && scrollRef.current) {
        const { scrollHeight, clientHeight } = scrollRef.current;
        // If everything fits on screen without scroll, auto-unlock
        if (scrollHeight > 0 && scrollHeight <= clientHeight) {
          onReachBottom();
        }
      }
    }
  }, [renderedPages, numPages]);

  return (
    <Box
      ref={scrollRef}
      onScroll={handleScroll}
      sx={{ height: "100%", overflowY: "auto", display: "flex", flexDirection: "column", alignItems: "center", bgcolor: "#f5f5f5", p: 2 }}
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
              width={800}
              onRenderSuccess={() => {
                setRenderedPages((prev) => ({ ...prev, [index + 1]: true }));
              }}
            />
          </Box>
        ))}
      </Document>
    </Box>
  );
}
