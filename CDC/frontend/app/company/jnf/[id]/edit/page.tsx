"use client";

import { useEffect, useState, use } from "react";
import { useRouter } from "next/navigation";
import { Alert, Box, CircularProgress, Stack } from "@mui/material";
import JnfFormPro from "@/components/forms/jnfformpro";
import { companyApi } from "@/lib/companyapi";

type Jnf = {
  id: number;
  job_title: string;
  job_description: string;
  job_location: string | null;
  ctc_min: number | null;
  ctc_max: number | null;
  vacancies: number | null;
  application_deadline: string | null;
  admin_remarks: string | null;
  status: string;
  form_data?: string | null;
};

export default function EditJnfPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const [jnf, setJnf] = useState<Jnf | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const router = useRouter();

  useEffect(() => {
    const run = async () => {
      try {
        const response = await companyApi<{ jnf: Jnf }>(
          `/company/jnfs/${id}`,
        );
        setJnf(response.jnf);
      } catch (e) {
        setError(e instanceof Error ? e.message : "Failed to load JNF.");
      } finally {
        setLoading(false);
      }
    };

    void run();
  }, [id]);

  useEffect(() => {
    if (jnf && jnf.status !== "draft" && jnf.status !== "under_review") {
      router.replace(`/company/jnf/${id}`);
    }
  }, [jnf, router, id]);

  if (loading) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight={400}>
        <CircularProgress />
      </Box>
    );
  }

  if (jnf && jnf.status !== "draft" && jnf.status !== "under_review") {
    return (
      <Box sx={{ maxWidth: 1200, mx: "auto" }}>
        <Alert severity="info">This JNF cannot be edited in its current status.</Alert>
      </Box>
    );
  }

  // Parse form_data if available, otherwise use legacy fields
  // form_data may already be parsed by Laravel's cast or still be a string
  const parseFormData = (data: string | object | null | undefined) => {
    if (!data) return null;
    if (typeof data === "string") {
      try {
        return JSON.parse(data);
      } catch {
        return null;
      }
    }
    return data;
  };

  const parsedFormData = parseFormData(jnf?.form_data);
  const parsedJnf =
    parsedFormData && typeof parsedFormData === "object"
      ? (parsedFormData as Record<string, unknown>)
      : null;
  const hasParsedFormData =
    parsedJnf !== null &&
    (
      typeof parsedJnf.jobTitle === "string" && parsedJnf.jobTitle.trim() !== ""
    ) &&
    (
      typeof parsedJnf.jobDescription === "string" && parsedJnf.jobDescription.trim() !== ""
    );

  const initialData = hasParsedFormData
    ? { ...parsedFormData, id: jnf?.id }
    : {
        id: jnf?.id,
        jobTitle: jnf?.job_title ?? "",
        jobDescription: jnf?.job_description ?? "",
        jobLocation: jnf?.job_location ?? "",
        expectedHires: jnf?.vacancies?.toString() ?? "",
      };

  return (
    <Box sx={{ maxWidth: 1200, mx: "auto" }}>
      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
      {jnf && (
        <JnfFormPro
          initialData={initialData}
          onSaved={() => router.push("/company")}
          onCancel={() => router.push("/company")}
        />
      )}
    </Box>
  );
}
