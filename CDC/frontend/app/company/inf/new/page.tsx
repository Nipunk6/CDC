"use client";

import { useRouter } from "next/navigation";
import { Box } from "@mui/material";
import InfFormPro from "@/components/forms/infformpro";

export default function NewInfPage() {
  const router = useRouter();

  return (
    <Box sx={{ maxWidth: 1200, mx: "auto" }}>
      <InfFormPro
        onSaved={(id) => router.push(`/company/inf/${id}`)}
        onCancel={() => router.push("/company")}
      />
    </Box>
  );
}
