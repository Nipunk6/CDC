"use client";

import { useEffect, useMemo, useState } from "react";
import { yupResolver } from "@hookform/resolvers/yup";
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  CircularProgress,
  Grid2,
  Stack,
  Step,
  StepLabel,
  Stepper,
  TextField,
  Typography,
} from "@mui/material";
import { useForm } from "react-hook-form";
import * as yup from "yup";
import { companyApi, companyFileUpload } from "@/lib/companyapi";

type JnfFormProps = {
  initialData?: Partial<JnfFormValues> & { id?: number };
  onSaved?: (id: number) => void;
};

type JnfFormValues = {
  job_title: string;
  job_description: string;
  job_location: string;
  ctc_min: number | null;
  ctc_max: number | null;
  vacancies: number | null;
  application_deadline: string;
  admin_remarks: string;
};

const schema: yup.ObjectSchema<JnfFormValues> = yup
  .object({
    job_title: yup
      .string()
      .trim()
      .max(255, "Job title must be at most 255 characters")
      .required("Job title is required")
      .defined(),
    job_description: yup
      .string()
      .trim()
      .max(5000, "Job description must be at most 5000 characters")
      .required("Job description is required")
      .defined(),
    job_location: yup
      .string()
      .trim()
      .max(255, "Job location must be at most 255 characters")
      .default("")
      .defined(),
    ctc_min: yup
      .number()
      .transform((v, o) => (o === "" ? null : v))
      .nullable()
      .integer("CTC min must be a whole number")
      .min(0)
      .defined(),
    ctc_max: yup
      .number()
      .transform((v, o) => (o === "" ? null : v))
      .nullable()
      .integer("CTC max must be a whole number")
      .min(0)
      .when("ctc_min", {
        is: (v: number | null | undefined) => v !== null && v !== undefined,
        then: (rule) =>
          rule.min(yup.ref("ctc_min"), "CTC max must be >= CTC min"),
      })
      .defined(),
    vacancies: yup
      .number()
      .transform((v, o) => (o === "" ? null : v))
      .nullable()
      .integer("Vacancies must be a whole number")
      .min(1)
      .defined(),
    application_deadline: yup
      .string()
      .default("")
      .test(
        "valid-date",
        "Application deadline must be a valid date",
        (value) => !value || !Number.isNaN(Date.parse(value)),
      )
      .defined(),
    admin_remarks: yup
      .string()
      .trim()
      .max(2000, "Remarks must be at most 2000 characters")
      .default("")
      .defined(),
  })
  .defined();

const steps = [
  "Part 1: Job Details",
  "Part 2: Compensation & Hiring",
  "Part 3: Attach & Submit",
];

export default function JnfForm({ initialData, onSaved }: JnfFormProps) {
  const [activeStep, setActiveStep] = useState(0);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [savingDraft, setSavingDraft] = useState(false);
  const [draftId, setDraftId] = useState<number | undefined>(initialData?.id);
  const [uploadedFiles, setUploadedFiles] = useState<
    Array<{ name: string; url: string }>
  >([]);

  const defaultValues = useMemo<JnfFormValues>(
    () => ({
      job_title: initialData?.job_title ?? "",
      job_description: initialData?.job_description ?? "",
      job_location: initialData?.job_location ?? "",
      ctc_min: initialData?.ctc_min ?? null,
      ctc_max: initialData?.ctc_max ?? null,
      vacancies: initialData?.vacancies ?? null,
      application_deadline: initialData?.application_deadline
        ? String(initialData.application_deadline).slice(0, 10)
        : "",
      admin_remarks: initialData?.admin_remarks ?? "",
    }),
    [initialData],
  );

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting, isDirty },
    watch,
  } = useForm<JnfFormValues>({ resolver: yupResolver(schema), defaultValues });

  const watched = watch();

  useEffect(() => {
    if (!isDirty) {
      return;
    }

    if (!watched.job_title || !watched.job_description) {
      return;
    }

    const timer = setTimeout(async () => {
      setSavingDraft(true);
      setError(null);

      try {
        const payload = { ...watched, id: draftId };
        const response = await companyApi<{ jnf: { id: number } }>(
          "/company/jnfs/autosave",
          {
            method: "POST",
            body: JSON.stringify(payload),
          },
        );
        setDraftId(response.jnf.id);
      } catch (e) {
        const message = e instanceof Error ? e.message : "Auto-save failed.";
        setError(message);
      } finally {
        setSavingDraft(false);
      }
    }, 2000);

    return () => clearTimeout(timer);
  }, [watched, isDirty, draftId]);

  const onUpload = async (file: File) => {
    try {
      const response = await companyFileUpload(file);
      setUploadedFiles((prev) => [
        ...prev,
        { name: response.file.name, url: response.file.url },
      ]);
    } catch (e) {
      const message = e instanceof Error ? e.message : "File upload failed.";
      setError(message);
    }
  };

  const onSubmit = async (values: JnfFormValues) => {
    setError(null);
    setSuccess(null);

    const payload = { ...values, status: "submitted" };
    const path = draftId ? `/company/jnfs/${draftId}` : "/company/jnfs";
    const method = draftId ? "PUT" : "POST";

    try {
      const response = await companyApi<{ jnf: { id: number } }>(path, {
        method,
        body: JSON.stringify(payload),
      });

      setDraftId(response.jnf.id);
      setSuccess("JNF submitted successfully.");
      onSaved?.(response.jnf.id);
    } catch (e) {
      const message = e instanceof Error ? e.message : "Failed to submit JNF.";
      setError(message);
    }
  };

  return (
    <Card>
      <CardContent>
        <Stack spacing={3}>
          <Stepper activeStep={activeStep} alternativeLabel>
            {steps.map((label) => (
              <Step key={label}>
                <StepLabel>{label}</StepLabel>
              </Step>
            ))}
          </Stepper>

          {error && <Alert severity="error">{error}</Alert>}
          {success && <Alert severity="success">{success}</Alert>}

          <Box component="form" onSubmit={handleSubmit(onSubmit)}>
            <Stack spacing={2.5}>
              {activeStep === 0 && (
                <Grid2 container spacing={2}>
                  <Grid2 size={{ xs: 12 }}>
                    <TextField
                      fullWidth
                      label="Job Title"
                      {...register("job_title")}
                      error={Boolean(errors.job_title)}
                      helperText={errors.job_title?.message}
                    />
                  </Grid2>
                  <Grid2 size={{ xs: 12 }}>
                    <TextField
                      fullWidth
                      minRows={5}
                      multiline
                      label="Job Description"
                      {...register("job_description")}
                      error={Boolean(errors.job_description)}
                      helperText={errors.job_description?.message}
                    />
                  </Grid2>
                  <Grid2 size={{ xs: 12 }}>
                    <TextField
                      fullWidth
                      label="Job Location"
                      {...register("job_location")}
                      error={Boolean(errors.job_location)}
                      helperText={errors.job_location?.message}
                    />
                  </Grid2>
                </Grid2>
              )}

              {activeStep === 1 && (
                <Grid2 container spacing={2}>
                  <Grid2 size={{ xs: 12, md: 6 }}>
                    <TextField
                      fullWidth
                      type="number"
                      label="CTC Min"
                      {...register("ctc_min")}
                      error={Boolean(errors.ctc_min)}
                      helperText={errors.ctc_min?.message}
                    />
                  </Grid2>
                  <Grid2 size={{ xs: 12, md: 6 }}>
                    <TextField
                      fullWidth
                      type="number"
                      label="CTC Max"
                      {...register("ctc_max")}
                      error={Boolean(errors.ctc_max)}
                      helperText={errors.ctc_max?.message}
                    />
                  </Grid2>
                  <Grid2 size={{ xs: 12, md: 6 }}>
                    <TextField
                      fullWidth
                      type="number"
                      label="Vacancies"
                      {...register("vacancies")}
                      error={Boolean(errors.vacancies)}
                      helperText={errors.vacancies?.message}
                    />
                  </Grid2>
                  <Grid2 size={{ xs: 12, md: 6 }}>
                    <TextField
                      fullWidth
                      type="date"
                      label="Application Deadline"
                      InputLabelProps={{ shrink: true }}
                      {...register("application_deadline")}
                      error={Boolean(errors.application_deadline)}
                      helperText={errors.application_deadline?.message}
                    />
                  </Grid2>
                </Grid2>
              )}

              {activeStep === 2 && (
                <Stack spacing={2}>
                  <TextField
                    fullWidth
                    multiline
                    minRows={3}
                    label="Notes / Remarks"
                    {...register("admin_remarks")}
                    error={Boolean(errors.admin_remarks)}
                    helperText={errors.admin_remarks?.message}
                  />
                  <Button variant="outlined" component="label">
                    Upload Attachment
                    <input
                      hidden
                      type="file"
                      onChange={(e) => {
                        const file = e.target.files?.[0];
                        if (file) {
                          void onUpload(file);
                        }
                      }}
                    />
                  </Button>
                  {uploadedFiles.map((file) => (
                    <Typography
                      variant="body2"
                      key={`${file.name}-${file.url}`}
                    >
                      Uploaded:{" "}
                      <a href={file.url} target="_blank" rel="noreferrer">
                        {file.name}
                      </a>
                    </Typography>
                  ))}
                </Stack>
              )}

              <Stack
                direction="row"
                justifyContent="space-between"
                alignItems="center"
                mt={1}
              >
                <Button
                  disabled={activeStep === 0}
                  onClick={() => setActiveStep((s) => s - 1)}
                >
                  Previous
                </Button>

                <Stack direction="row" spacing={1} alignItems="center">
                  {savingDraft && (
                    <Stack direction="row" spacing={1} alignItems="center">
                      <CircularProgress size={16} />
                      <Typography variant="caption">Auto-saving...</Typography>
                    </Stack>
                  )}

                  {activeStep < steps.length - 1 ? (
                    <Button
                      variant="contained"
                      onClick={() => setActiveStep((s) => s + 1)}
                    >
                      Next
                    </Button>
                  ) : (
                    <Button
                      variant="contained"
                      color="secondary"
                      type="submit"
                      disabled={isSubmitting}
                    >
                      {isSubmitting ? "Submitting..." : "Submit JNF"}
                    </Button>
                  )}
                </Stack>
              </Stack>
            </Stack>
          </Box>
        </Stack>
      </CardContent>
    </Card>
  );
}
