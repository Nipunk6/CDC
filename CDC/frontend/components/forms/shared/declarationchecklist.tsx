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
  declarations: {
    aipc: boolean;
    shortlistCriteria: boolean;
    infoVerified: boolean;
    consentLogo: boolean;
    confirmAccuracy: boolean;
    resultsViaCdc: boolean;
  };
  onDeclarationsChange: (declarations: DeclarationChecklistProps["declarations"]) => void;
  signatory: {
    name: string;
    designation: string;
    date: string;
  };
  onSignatoryChange: (signatory: DeclarationChecklistProps["signatory"]) => void;
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
  declarations,
  onDeclarationsChange,
  signatory,
  onSignatoryChange,
}: DeclarationChecklistProps) {
  const [documents, setDocuments] = useState<PolicyDocument[]>([]);
  const [loading, setLoading] = useState(true);
  const [pdfUrl, setPdfUrl] = useState<{url: string; title: string; docId: number} | null>(null);
  const [readDocs, setReadDocs] = useState<Record<number, boolean>>({});

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

  const updateSignatory = (field: keyof typeof signatory, value: string) => {
    onSignatoryChange({ ...signatory, [field]: value });
  };

  const handleOpenPdf = (e: React.MouseEvent, doc: PolicyDocument) => {
    e.preventDefault();
    setPdfUrl({ url: doc.url, title: doc.title, docId: doc.id });
  };

  const handleLinkClick = (doc: PolicyDocument) => {
    setReadDocs((prev) => ({ ...prev, [doc.id]: true }));
  };

  const handleReachBottom = () => {
    if (pdfUrl) {
      setReadDocs((prev) => ({ ...prev, [pdfUrl.docId]: true }));
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
            ⚠️ You must read and scroll/click all guidelines below to unlock the declaration checkboxes.
          </Typography>
          {loading ? (
            <CircularProgress size={20} sx={{ mt: 1 }} />
          ) : documents.length === 0 ? (
            <Typography variant="body2" color="text.secondary">No guidelines required for this form.</Typography>
          ) : (
            <Stack direction="row" spacing={3} flexWrap="wrap" useFlexGap gap={2}>
              {documents.map((doc) => {
                const isRead = Boolean(readDocs[doc.id]);
                if (doc.type === "link") {
                  return (
                    <Link
                      key={doc.id}
                      href={doc.url}
                      target="_blank"
                      rel="noopener noreferrer"
                      onClick={() => handleLinkClick(doc)}
                      variant="body2"
                      sx={{ cursor: "pointer", display: 'flex', alignItems: 'center', gap: 0.5, color: isRead ? 'success.main' : 'primary.main', textDecoration: 'none' }}
                    >
                      📄 {doc.title} {isRead && "✓"}
                    </Link>
                  );
                } else {
                  return (
                    <Link
                      key={doc.id}
                      href="#"
                      onClick={(e) => handleOpenPdf(e, doc)}
                      variant="body2"
                      sx={{ cursor: "pointer", display: 'flex', alignItems: 'center', gap: 0.5, color: isRead ? 'success.main' : 'primary.main', textDecoration: 'none' }}
                    >
                      📄 {doc.title} {isRead && "✓"}
                    </Link>
                  );
                }
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

      <Divider sx={{ my: 3 }} />

      {/* Self-Declaration / Signatory */}
      <Paper
        sx={{
          p: 3,
          background: (theme) => alpha(theme.palette.secondary.main, 0.05),
          border: "1px solid",
          borderColor: "secondary.light",
        }}
      >
        <Typography variant="subtitle1" fontWeight={600} mb={2} color="secondary.dark">
          ✍️ Authorised Signatory
        </Typography>
        <Typography variant="body2" color="text.secondary" mb={3}>
          Please provide details of the authorised representative submitting this form.
        </Typography>

        <Stack spacing={2}>
          <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
            <TextField
              fullWidth
              label="Full Name of Signatory"
              value={signatory.name}
              onChange={(e) => updateSignatory("name", e.target.value)}
              required
              placeholder="Enter full name"
            />
            <TextField
              fullWidth
              label="Designation"
              value={signatory.designation}
              onChange={(e) => updateSignatory("designation", e.target.value)}
              required
              placeholder="e.g., HR Manager, Campus Recruiter"
            />
          </Stack>
          <Stack direction={{ xs: "column", md: "row" }} spacing={2}>
            <TextField
              type="date"
              label="Date"
              value={signatory.date}
              onChange={(e) => updateSignatory("date", e.target.value)}
              InputLabelProps={{ shrink: true }}
              required
              sx={{ width: { xs: "100%", md: 200 } }}
            />
          </Stack>
        </Stack>

        <Typography variant="caption" color="text.secondary" mt={2} display="block">
          By submitting this form, you confirm that you are authorised to represent your organisation
          and that all information provided is accurate to the best of your knowledge.
        </Typography>
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
