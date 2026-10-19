"use client";

import { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react";
import { usePathname } from "next/navigation";

import { adminApi } from "@/lib/adminapi";

// Staff roles: the signed-in admin's own access (GET /admin/me/access), re-read on every page change so a super
// admin's change applies at once. The server enforces everything; this only hides what would be refused.

export const LEVEL_RANK = { none: 0, read: 1, verify: 2, write: 3 };
export const LEVEL_LABELS = { none: "None", read: "Read", verify: "Read and verify", write: "Read and write" };

// Admin pages and the permission each needs to open (longest prefix wins). "super" = super admins only.
const PAGE_PERMISSIONS = [
  ["/admin/users", "super"],
  ["/admin/manage-admins", "super"],
  ["/admin/notifications", null],
  ["/admin/analytics", "dashboard"],
  ["/admin/calendar", "calendar"],
  ["/admin/reports", "reports"],
  ["/admin/placement-cycles", "placement_cycles"],
  ["/admin/postings", "job_profiles"],
  ["/admin/proposals", "proposals"],
  ["/admin/events", "events"],
  ["/admin/jnfs", "form_reviews"],
  ["/admin/infs", "form_reviews"],
  ["/admin/companies", "companies"],
  ["/admin/company-history", "companies"],
  ["/admin/policy-documents", "policy_documents"],
  ["/admin/alumni-outreach", "alumni_outreach"],
  ["/admin/students/invitations", "student_invitations"],
  ["/admin/students", "students"],
  ["/admin/resumes", "resumes"],
  ["/admin/branch-changes", "branch_changes"],
  ["/admin/student-categories", "student_categories"],
  ["/admin/scpt", "scpt"],
  ["/admin/notices", "notices"],
  ["/admin/surveys", "surveys"],
  ["/admin/settings", "settings"],
  ["/admin/programme-branches", "settings"],
  ["/admin/excel-templates", "excel_templates"],
  ["/admin/audit-logs", "audit_log"],
  ["/admin/login-logs", "login_log"],
];

/** The permission a path needs: a key, "super", null (any admin) or "dashboard" for the home page. */
export function pagePermission(pathname) {
  if (pathname === "/admin" || pathname === "/admin/") return "dashboard";
  const match = PAGE_PERMISSIONS.filter(([prefix]) => pathname === prefix || pathname.startsWith(`${prefix}/`)).sort((a, b) => b[0].length - a[0].length)[0];
  return match ? match[1] : null;
}

const AccessContext = createContext(null);

export function AccessProvider({ children }) {
  const pathname = usePathname();
  const [state, setState] = useState({ loading: true, access: null, catalogue: [], error: null });

  const load = useCallback(async () => {
    try {
      const response = await adminApi("/admin/me/access");
      setState({ loading: false, access: response.access, catalogue: response.catalogue ?? [], error: null });
    } catch (e) {
      setState((prev) => ({ ...prev, loading: false, error: e instanceof Error ? e.message : "Could not load your access." }));
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load, pathname]);

  const value = useMemo(() => {
    const access = state.access;
    const isSuper = Boolean(access?.super);
    const can = (permission, level = "read") => {
      if (!access) return false;
      if (isSuper) return true;
      if (permission === "super") return false;
      if (permission === null || permission === undefined) return true;
      return (LEVEL_RANK[access.levels?.[permission] ?? "none"] ?? 0) >= (LEVEL_RANK[level] ?? 1);
    };
    return { ...state, isSuper, can, reload: load };
  }, [state, load]);

  return <AccessContext.Provider value={value}>{children}</AccessContext.Provider>;
}

/**
 * @typedef {object} AccessValue
 * @property {boolean} loading
 * @property {any} access
 * @property {any[]} [catalogue]
 * @property {string|null} [error]
 * @property {boolean} isSuper
 * @property {(permission?: string|null, level?: string) => boolean} can
 * @property {() => any} reload
 */

/**
 * The signed-in admin's access. Outside the admin shell everything is allowed.
 * @returns {AccessValue}
 */
export function useAccess() {
  /** @type {AccessValue} */
  const fallback = { loading: false, access: null, catalogue: [], error: null, isSuper: true, can: () => true, reload: () => {} };
  return useContext(AccessContext) ?? fallback;
}
