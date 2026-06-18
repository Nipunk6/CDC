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

type InfFormProps = {
  initialData?: Partial<InfFormValues> & { id?: number };
  onSaved?: (id: number) => void;
};

type InfFormValues = {
  internship_title: string;
  internship_description: string;
  internship_location: string;
  stipend: number | null;
  internship_duration_weeks: number | null;
  vacancies: number | null;
  application_deadline: string;
  admin_remarks: string;
};

const schema: yup.ObjectSchema<InfFormValues> = yup
  .object({
    internship_title: yup
      .string()
      .trim()
      .max(255, "Internship title must be at most 255 characters")
      .required("Internship title is required")
      .defined(),
    internship_description: yup
      .string()
      .trim()
      .max(5000, "Internship description must be at most 5000 characters")
      .required("Internship description is required")
      .defined(),
    internship_location: yup
      .string()
      .trim()
      .max(255, "Location must be at most 255 characters")
      .default("")
      .defined(),
    stipend: yup
      .number()
      .transform((v, o) => (o === "" ? null : v))
      .nullable()
      .integer("Stipend must be a whole number")
      .min(0)
      .defined(),
    internship_duration_weeks: yup
      .number()
      .transform((v, o) => (o === "" ? null : v))
      .nullable()
      .integer("Duration must be a whole number")
      .min(1)
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
  "Part 1: Internship Details",
  "Part 2: Stipend, Duration & Submit",
];

export default function InfForm({ initialData, onSaved }: InfFormProps) {
  const [activeStep, setActiveStep] = useState(0);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [savingDraft, setSavingDraft] = useState(false);
  const [draftId, setDraftId] = useState<number | undefined>(initialData?.id);
  const [uploadedFiles, setUploadedFiles] = useState<
    Array<{ name: string; url: string }>
  >([]);

  const defaultValues = useMemo<InfFormValues>(
    () => ({
      internship_title: initialData?.internship_title ?? "",
      internship_description: initialData?.internship_description ?? "",
      internship_location: initialData?.internship_location ?? "",
      stipend: initialData?.stipend ?? null,
      internship_duration_weeks: initialData?.internship_duration_weeks ?? null,
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
  } = useForm<InfFormValues>({ resolver: yupResolver(schema), defaultValues });

  const watched = watch();

  useEffect(() => {
    if (!isDirty) {
      return;
    }

    if (!watched.internship_title || !watched.internship_description) {
      return;
    }

    const timer = setTimeout(async () => {
      setSavingDraft(true);
      setError(null);

      try {
        const payload = { ...watched, id: draftId };
        const response = await companyApi<{ inf: { id: number } }>(
          "/company/infs/autosave",
          {
            method: "POST",
            body: JSON.stringify(payload),
          },
        );
        setDraftId(response.inf.id);
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

  const onSubmit = async (values: InfFormValues) => {
    setError(null);
    setSuccess(null);

    const payload = { ...values, status: "submitted" };
    const path = draftId ? `/company/infs/${draftId}` : "/company/infs";
    const method = draftId ? "PUT" : "POST";

    try {
      const response = await companyApi<{ inf: { id: number } }>(path, {
        method,
        body: JSON.stringify(payload),
      });

      setDraftId(response.inf.id);
      setSuccess("INF submitted successfully.");
      onSaved?.(response.inf.id);
    } catch (e) {
      const message = e instanceof Error ? e.message : "Failed to submit INF.";
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
                      label="Internship Title"
                      {...register("internship_title")}
                      error={Boolean(errors.internship_title)}
                      helperText={errors.internship_title?.message}
                    />
                  </Grid2>
                  <Grid2 size={{ xs: 12 }}>
                    <TextField
                      fullWidth
                      minRows={5}
                      multiline
                      label="Internship Description"
                      {...register("internship_description")}
                      error={Boolean(errors.internship_description)}
                      helperText={errors.internship_description?.message}
                    />
                  </Grid2>
                  <Grid2 size={{ xs: 12 }}>
                    <TextField
                      fullWidth
                      label="Location"
                      {...register("internship_location")}
                      error={Boolean(errors.internship_location)}
                      helperText={errors.internship_location?.message}
                    />
                  </Grid2>
                </Grid2>
              )}

              {activeStep === 1 && (
                <Stack spacing={2}>
                  <Grid2 container spacing={2}>
                    <Grid2 size={{ xs: 12, md: 4 }}>
                      <TextField
                        fullWidth
                        type="number"
                        label="Stipend"
                        {...register("stipend")}
                        error={Boolean(errors.stipend)}
                        helperText={errors.stipend?.message}
                      />
                    </Grid2>
                    <Grid2 size={{ xs: 12, md: 4 }}>
                      <TextField
                        fullWidth
                        type="number"
                        label="Duration (weeks)"
                        {...register("internship_duration_weeks")}
                        error={Boolean(errors.internship_duration_weeks)}
                        helperText={errors.internship_duration_weeks?.message}
                      />
                    </Grid2>
                    <Grid2 size={{ xs: 12, md: 4 }}>
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
                      {isSubmitting ? "Submitting..." : "Submit INF"}
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
