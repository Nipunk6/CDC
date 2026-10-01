"use client";

import { Box } from "@mui/material";
import { InfPreview, JnfPreview } from "@/components/forms/shared";

const SALARY_KEYS = [
  "joiningBonus", "retentionBonus", "performanceBonus", "esops", "vestPeriod", "relocationAllowance",
  "medicalAllowance", "deductions", "bondAmount", "bondDuration", "stocks", "ctcBreakup",
];

const noDeclarations = { aipc: true, shortlistCriteria: true, infoVerified: true, consentLogo: true, confirmAccuracy: true, resultsViaCdc: true };
const noSignatory = { name: "", designation: "", date: "" };

const profile = (cp = {}, companyName = "") => ({
  name: cp.name || companyName,
  website: cp.website || "",
  sector: cp.sector || "",
  employeeCount: cp.employeeCount || "",
  postalAddress: "",
  categoryOrgType: cp.categoryOrgType || "",
  dateOfEstablishment: cp.dateOfEstablishment || "",
  annualTurnover: cp.annualTurnover || "",
  linkedinUrl: cp.linkedinUrl || "",
  industrySectorTags: cp.industrySectorTags || "",
  mncHqCountryCity: cp.mncHqCountryCity || "",
  natureOfBusiness: cp.natureOfBusiness || "",
  companyDescription: cp.companyDescription || "",
});

/**
 * Read-only JnfPreview / InfPreview for audiences that are not the submitting company (students, D65).
 * The Phase 1 preview's first child is the "Review Before Submission" banner and its last child is the
 * company's declaration & signatory block; both are hidden here instead of editing the frozen component.
 */
export default function PostingPreview({ formType, formData = {}, companyName, logoUrl }) {
  const common = {
    readOnly: true,
    companyLogoUrl: logoUrl ?? null,
    companyProfile: profile(formData.companyProfile, companyName),
    eligibility: formData.eligibility || [],
    globalCgpa: formData.globalCgpa || "",
    globalBacklogs: formData.globalBacklogs ?? false,
    genderFilter: formData.genderFilter || "all",
    slpRequirement: formData.slpRequirement || "",
    minTenthPercent: formData.minTenthPercent || "",
    minTwelfthPercent: formData.minTwelfthPercent || "",
    graduatingBatch: formData.graduatingBatch || "",
    selectionProcess: { rounds: formData.selectionRounds || [] },
    declarations: noDeclarations,
    signatory: noSignatory,
  };

  return (
    <Box sx={{ "& > div > :first-of-type": { display: "none" }, "& > div > :last-child": { display: "none" } }}>
      {formType === "inf" ? (
        <InfPreview
          {...common}
          internshipDetails={{
            title: formData.internshipTitle || "",
            designation: formData.internshipDesignation || "",
            location: formData.internshipLocation || "",
            workMode: formData.workMode || "onsite",
            expectedHires: formData.expectedHires || "",
            duration: formData.duration || "",
            joiningMonth: formData.joiningMonth || "",
            skills: formData.skills || [],
            description: formData.internshipDescription || "",
            registrationLink: formData.registrationLink || "",
            additionalInfo: formData.additionalInfo || "",
          }}
          stipend={{
            currency: formData.currency || "INR",
            programmeStipends: formData.programmeStipends || [],
            ppoProvision: formData.ppoProvision ?? false,
            ppoCtc: formData.ppoCtc || "",
          }}
        />
      ) : (
        <JnfPreview
          {...common}
          jobDetails={{
            title: formData.jobTitle || "",
            designation: formData.jobDesignation || "",
            location: formData.jobLocation || "",
            workMode: formData.workMode || "onsite",
            expectedHires: formData.expectedHires || "",
            minimumHires: formData.minimumHires || "",
            joiningMonth: formData.joiningMonth || "",
            skills: formData.skills || [],
            description: formData.jobDescription || "",
            registrationLink: formData.registrationLink || "",
            additionalInfo: formData.additionalInfo || "",
          }}
          salary={{
            currency: formData.currency || "INR",
            programmeSalaries: formData.programmeSalaries || [],
            components: Object.fromEntries(SALARY_KEYS.map((key) => [key, formData.salaryComponents?.[key] || ""])),
          }}
        />
      )}
    </Box>
  );
}
