"use client";

import {
  Box,
  Checkbox,
  FormControlLabel,
  Link,
  Paper,
  Stack,
  TextField,
  Typography,
  alpha,
  Divider,
  Dialog,
  DialogTitle,
  DialogContent,
  IconButton,
  CircularProgress,
} from "@mui/material";
import CloseIcon from "@mui/icons-material/Close";
import DownloadIcon from "@mui/icons-material/Download";
import PictureAsPdfIcon from "@mui/icons-material/PictureAsPdf";
import LinkIcon from "@mui/icons-material/Link";
import { useState, useEffect } from "react";
import { companyApi } from "@/lib/companyapi";
import dynamic from "next/dynamic";

const PdfViewer = dynamic(() => import("./pdfviewer"), {
  ssr: false,
  loading: () => <CircularProgress />,
});

interface PolicyDocument {
  id: number;
  title: string;
  type: "pdf" | "link";
  url: string;
}

interface DeclarationChecklistProps {
  formType: "jnf" | "inf";
  draftId?: number;
  declarations: {
    aipc: boolean;
    shortlistCriteria: boolean;
    infoVerified: boolean;
    consentLogo: boolean;
    confirmAccuracy: boolean;
    resultsViaCdc: boolean;
  };
  onDeclarationsChange: (declarations: DeclarationChecklistProps["declarations"]) => void;
}

const declarationTexts = {
  aipc: "I have thoroughly read the guidelines and agree to abide by them during the entire placement/internship process.",
  shortlistCriteria: "Shortlisting criteria will be provided and final shortlist will be shared within 24–48 hours after the written test.",
  infoVerified: "The information provided in this form is verified and correct. No new clauses will be added in the final offer letter.",
  consentLogo: "I consent to share company name, logo, and email with national ranking agencies (NIRF) and media for promotional purposes.",
  confirmAccuracy: "I confirm the accuracy of the job/internship profile and agree to adhere to all Terms & Conditions. Strict action may be taken in case of any discrepancy.",
  resultsViaCdc: "Results and communication will be shared through CDC and not directly to students.",
};

export default function DeclarationChecklist({
  formType,
  draftId,
  declarations,
  onDeclarationsChange,
}: DeclarationChecklistProps) {
  const [documents, setDocuments] = useState<PolicyDocument[]>([]);
  const [loading, setLoading] = useState(true);
  const [pdfUrl, setPdfUrl] = useState<{url: string; title: string; docId: number} | null>(null);
  const [readDocs, setReadDocs] = useState<Record<number, boolean>>({});

  const storageKey = draftId ? `cdc_read_guidelines_${formType}_${draftId}` : `cdc_read_guidelines_${formType}_temp`;

  useEffect(() => {
    if (typeof window !== "undefined") {
      try {
        const saved = localStorage.getItem(storageKey);
        if (saved) {
          setReadDocs(JSON.parse(saved));
        } else {
          setReadDocs({});
        }
      } catch (e) {
        console.error("Failed to load read guidelines", e);
      }
    }
  }, [storageKey]);

  const handleReadDoc = (docId: number) => {
    setReadDocs((prev) => {
      const updated = { ...prev, [docId]: true };
      if (typeof window !== "undefined") {
        try {
          localStorage.setItem(storageKey, JSON.stringify(updated));
        } catch (e) {
          console.error("Failed to save read guidelines", e);
        }
      }
      return updated;
    });
  };

  useEffect(() => {
    const fetchDocs = async () => {
      try {
        const data = await companyApi<PolicyDocument[]>(`/company/policy-documents?form_type=${formType}`);
        setDocuments(data);
      } catch (err) {
        console.error("Failed to load policy documents:", err);
      } finally {
        setLoading(false);
      }
    };
    void fetchDocs();
  }, [formType]);

  const toggleDeclaration = (key: keyof typeof declarations) => {
    onDeclarationsChange({ ...declarations, [key]: !declarations[key] });
  };


  const handleOpenPdf = (e: React.MouseEvent, doc: PolicyDocument) => {
    e.preventDefault();
    setPdfUrl({ url: doc.url, title: doc.title, docId: doc.id });
    handleReadDoc(doc.id);
  };

  const handleLinkClick = (doc: PolicyDocument) => {
    handleReadDoc(doc.id);
  };

  const handleReachBottom = () => {
    if (pdfUrl) {
      handleReadDoc(pdfUrl.docId);
      setPdfUrl(null);
    }
  };

  const allChecked = Object.values(declarations).every(Boolean);
  const canCheck = documents.length === 0 || documents.every((doc) => readDocs[doc.id]);

  return (
    <Box>
      {/* Declaration Checkboxes */}
      <Paper sx={{ p: 3, mb: 3 }}>
        <Typography variant="subtitle1" fontWeight={600} mb={2} color="primary">
          📋 Declaration & Agreement
        </Typography>

        {/* Policy Links Moved to Top */}
        <Box sx={{ bgcolor: alpha("#ff9800", 0.1), p: 2, borderRadius: 1, mb: 3, border: '1px solid', borderColor: 'warning.light' }}>
          <Typography variant="body2" color="text.primary" fontWeight={500} mb={1}>
            ⚠️ You must read all guidelines below to proceed further.
          </Typography>
          {loading ? (
            <CircularProgress size={20} sx={{ mt: 1 }} />
          ) : documents.length === 0 ? (
            <Typography variant="body2" color="text.secondary">No guidelines required for this form.</Typography>
          ) : (
            <Stack direction="row" spacing={2} flexWrap="wrap" useFlexGap gap={2} sx={{ mt: 1.5 }}>
              {documents.map((doc) => {
                const isRead = Boolean(readDocs[doc.id]);
                return (
                  <Box
                    key={doc.id}
                    component="a"
                    href={doc.type === "link" ? doc.url : "#"}
                    target={doc.type === "link" ? "_blank" : undefined}
                    rel={doc.type === "link" ? "noopener noreferrer" : undefined}
                    onClick={
                      doc.type === "link"
                        ? () => handleLinkClick(doc)
                        : (e) => handleOpenPdf(e, doc)
                    }
                    sx={{
                      display: "flex",
                      alignItems: "center",
                      gap: 1.5,
                      px: 2.5,
                      py: 1.5,
                      borderRadius: 2,
                      border: "1.5px solid",
                      borderColor: isRead ? "success.light" : "primary.main",
                      bgcolor: isRead ? "rgba(46, 125, 50, 0.03)" : "rgba(123, 17, 19, 0.02)",
                      color: isRead ? "success.main" : "primary.main",
                      textDecoration: "none",
                      cursor: "pointer",
                      boxShadow: "0 2px 6px rgba(0,0,0,0.02)",
                      transition: "all 0.2s ease-in-out",
                      "&:hover": {
                        transform: "translateY(-1px)",
                        boxShadow: "0 4px 12px rgba(0,0,0,0.06)",
                        bgcolor: isRead ? "rgba(46, 125, 50, 0.08)" : "rgba(123, 17, 19, 0.06)",
                      },
                    }}
                  >
                    {doc.type === "pdf" ? (
                      <PictureAsPdfIcon sx={{ fontSize: 20 }} />
                    ) : (
                      <LinkIcon sx={{ fontSize: 20 }} />
                    )}
                    <Box sx={{ display: "flex", flexDirection: "column", alignItems: "flex-start" }}>
                      <Typography variant="body2" fontWeight={600} color={isRead ? "success.dark" : "primary.dark"} sx={{ textAlign: 'left' }}>
                        {doc.title}
                      </Typography>
                      <Typography variant="caption" color="text.secondary" sx={{ fontSize: "0.7rem", fontWeight: 500, textAlign: 'left' }}>
                        {isRead ? "✓ Read & Agreed" : "⚠ Action Required (Click to read)"}
                      </Typography>
                    </Box>
                  </Box>
                );
              })}
            </Stack>
          )}
        </Box>

        <Typography variant="body2" color="text.secondary" mb={3}>
          Please read and agree to the following declarations before submitting your form.
        </Typography>

        <Stack spacing={2}>
          {Object.entries(declarationTexts).map(([key, text]) => (
            <FormControlLabel
              key={key}
              control={
                <Checkbox
                  checked={declarations[key as keyof typeof declarations]}
                  onChange={() => toggleDeclaration(key as keyof typeof declarations)}
                  disabled={!canCheck}
                  color="primary"
                />
              }
              label={
                <Typography variant="body2" color="text.primary">
                  {text}
                </Typography>
              }
              sx={{
                alignItems: "flex-start",
                bgcolor: declarations[key as keyof typeof declarations]
                  ? alpha("#1976d2", 0.05)
                  : "transparent",
                borderRadius: 1,
                p: 1,
                m: 0,
                border: "1px solid",
                borderColor: declarations[key as keyof typeof declarations]
                  ? "primary.light"
                  : "divider",
              }}
            />
          ))}
        </Stack>

        {!allChecked && (
          <Typography variant="caption" color="error" mt={2} display="block">
            * All declarations must be accepted to submit the form
          </Typography>
        )}
      </Paper>



      {/* PDF Modal */}
      <Dialog 
        open={Boolean(pdfUrl)} 
        onClose={() => setPdfUrl(null)}
        maxWidth="lg"
        fullWidth
        PaperProps={{
          sx: { height: '90vh' }
        }}
      >
        <DialogTitle sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          {pdfUrl?.title || "Document"}
          <Box sx={{ display: 'flex', gap: 0.5 }}>
            <IconButton
              onClick={() => {
                if (pdfUrl) {
                  const downloadUrl = pdfUrl.url.startsWith("http")
                    ? `/api/proxy-pdf?url=${encodeURIComponent(pdfUrl.url)}`
                    : pdfUrl.url;
                  const link = document.createElement('a');
                  link.href = downloadUrl;
                  link.download = `${pdfUrl.title || 'document'}.pdf`;
                  document.body.appendChild(link);
                  link.click();
                  document.body.removeChild(link);
                }
              }}
              size="small"
              title="Download PDF"
              sx={{ color: 'primary.main' }}
            >
              <DownloadIcon />
            </IconButton>
            <IconButton onClick={() => setPdfUrl(null)} size="small">
              <CloseIcon />
            </IconButton>
          </Box>
        </DialogTitle>
        <DialogContent dividers sx={{ p: 0, bgcolor: "#f5f5f5" }}>
          {pdfUrl && (
            <PdfViewer
              url={
                pdfUrl.url.startsWith("http")
                  ? `/api/proxy-pdf?url=${encodeURIComponent(pdfUrl.url)}`
                  : pdfUrl.url
              }
              onReachBottom={handleReachBottom}
            />
          )}
        </DialogContent>
      </Dialog>
    </Box>
  );
}
