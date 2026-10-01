"use client";

import { useEffect, useState } from "react";
import { Alert, Box, Button, Stack } from "@mui/material";

import QuestionBuilder, { cleanQuestions, toEditable } from "@/components/admin/questionbuilder";
import { adminApi } from "@/lib/adminapi";

export default function QuestionsTab({ posting, onChanged, onMessage }) {
  const [questions, setQuestions] = useState(toEditable(posting.questions));
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(false);
  const locked = posting.deadline_passed || ["completed", "cancelled"].includes(posting.status);

  useEffect(() => {
    setQuestions(toEditable(posting.questions));
  }, [posting.questions]);

  const save = async () => {
    const cleaned = cleanQuestions(questions);
    if (typeof cleaned === "string") {
      setError(cleaned);
      return;
    }
    setBusy(true);
    setError(null);
    try {
      await adminApi(`/admin/postings/${posting.id}`, { method: "PATCH", body: JSON.stringify({ questions: cleaned }) });
      onMessage?.("Questions saved.");
      await onChanged();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to save questions.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <Stack spacing={2}>
      {locked && <Alert severity="info">Questions are frozen after the application deadline.</Alert>}
      {!locked && posting.stats?.applied > 0 && (
        <Alert severity="warning">
          {posting.stats.applied} student(s) have already applied. Removing a question also hides their answers to it.
        </Alert>
      )}
      {error && <Alert severity="error">{error}</Alert>}
      <QuestionBuilder value={questions} onChange={setQuestions} disabled={locked || busy} />
      <Box>
        <Button variant="contained" onClick={save} disabled={locked || busy}>
          Save Questions
        </Button>
      </Box>
    </Stack>
  );
}
