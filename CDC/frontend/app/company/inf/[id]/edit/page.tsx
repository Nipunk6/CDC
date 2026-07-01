"use client";

import { use, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { Alert, Box, CircularProgress } from "@mui/material";
import InfFormPro from "@/components/forms/infformpro";
import { companyApi } from "@/lib/companyapi";

type Inf = {
  id: number;
  internship_title: string;
  internship_description: string;
  internship_location: string | null;
  stipend: number | null;
  internship_duration_weeks: number | null;
  vacancies: number | null;
  application_deadline: string | null;
  admin_remarks: string | null;
  status: string;
  form_data?: string | null;
};

function parseFormData(data: string | object | null | undefined) {
  if (!data) return null;

  if (typeof data === "string") {
    try {
      return JSON.parse(data);
    } catch {
      return null;
    }
  }

  return data;
}

export default function EditInfPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const router = useRouter();
  const [inf, setInf] = useState<Inf | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const run = async () => {
      try {
        const response = await companyApi<{ inf: Inf }>(`/company/infs/${id}`);
        setInf(response.inf);
      } catch (e) {
        setError(e instanceof Error ? e.message : "Failed to load INF.");
      } finally {
        setLoading(false);
      }
    };

    void run();
  }, [id]);

  useEffect(() => {
    if (inf && inf.status !== "draft" && inf.status !== "under_review") {
      router.replace(`/company/inf/${id}`);
    }
  }, [id, inf, router]);

  if (loading) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight={400}>
        <CircularProgress />
      </Box>
    );
  }

  if (inf && inf.status !== "draft" && inf.status !== "under_review") {
    return (
      <Box sx={{ maxWidth: 1200, mx: "auto" }}>
        <Alert severity="info">This INF cannot be edited in its current status.</Alert>
      </Box>
    );
  }

  const parsedFormData = parseFormData(inf?.form_data);
  const parsedInf =
    parsedFormData && typeof parsedFormData === "object"
      ? (parsedFormData as Record<string, unknown>)
      : null;
  const hasParsedFormData =
    parsedInf !== null &&
    (
      typeof parsedInf.internshipTitle === "string" && parsedInf.internshipTitle.trim() !== ""
    ) &&
    (
      typeof parsedInf.internshipDescription === "string" && parsedInf.internshipDescription.trim() !== ""
    );

  const initialData = hasParsedFormData
    ? { ...parsedFormData, id: inf?.id }
    : {
        id: inf?.id,
        internshipTitle: inf?.internship_title ?? "",
        internshipDescription: inf?.internship_description ?? "",
        internshipLocation: inf?.internship_location ?? "",
        duration: inf?.internship_duration_weeks?.toString() ?? "",
        expectedHires: inf?.vacancies?.toString() ?? "",
      };

  return (
    <Box sx={{ maxWidth: 1200, mx: "auto" }}>
      {error && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {error}
        </Alert>
      )}
      {inf && (
        <InfFormPro
          initialData={initialData}
          onSaved={(savedId) => router.push(`/company/inf/${savedId}`)}
          onCancel={() => router.push("/company")}
        />
      )}
    </Box>
  );
}