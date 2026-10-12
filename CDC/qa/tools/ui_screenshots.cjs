// Screenshots of every page of every portal, for before/after comparison (Phase 3 PU.1 and the PU acceptance).
//
//   node CDC/qa/tools/ui_screenshots.cjs <chromium executable> <output dir> <widths, e.g. 390,1440> [roles, e.g. admin,student]
//
// Needs the local stack (Next on :3000, Laravel on :8000 with MAIL_MAILER=log) and the Phase 2 demo data. It signs in
// with the demo accounts of Phase2DemoSeeder (read from the seeder, never printed) in its own headless browser.
// A mentor account, if wanted, is passed as MENTOR_EMAIL / MENTOR_PASSWORD. Output: <role>__<page>__<width>.jpg.
const fs = require("fs");
const path = require("path");
const ROOT = path.resolve(__dirname, "..", "..");
const { chromium } = require(path.join(ROOT, "frontend", "node_modules", "playwright"));
const seeder = fs.readFileSync(path.join(ROOT, "backend", "database", "seeders", "Phase2DemoSeeder.php"), "utf8");
const pick = (re) => (re.exec(seeder) || [])[1];

const BASE = "http://127.0.0.1:3000";
const [exe, outDir, widthArg, roleArg] = process.argv.slice(2);
const widths = String(widthArg || "390,1440").split(",").map(Number);

const ROLES = {
  public: {
    pages: ["/", "/auth/login/admin", "/auth/login/recruiter", "/auth/login/student", "/auth/login/mentor", "/auth/forgot-password", "/auth/reset-password", "/auth/student/set-password", "/company/register", "/alumni"],
  },
  admin: {
    login: ["admin", "Email Address", "admin@cdc-demo.test", pick(/'Demo CDC Admin', 'password' => Hash::make\('([^']+)'\)/)],
    pages: [
      "/admin", "/admin/placement-cycles", "/admin/placement-cycles/1", "/admin/postings", "/admin/postings/new", "/admin/postings/1", "/admin/postings/1/results",
      "/admin/postings/1/rounds/1", "/admin/students", "/admin/students/1", "/admin/students/invitations", "/admin/proposals", "/admin/resumes", "/admin/branch-changes",
      "/admin/events", "/admin/calendar", "/admin/analytics", "/admin/student-categories", "/admin/notices", "/admin/surveys", "/admin/jnfs", "/admin/jnfs/1", "/admin/infs",
      "/admin/infs/1", "/admin/companies", "/admin/companies/1", "/admin/policy-documents", "/admin/alumni-outreach", "/admin/reports", "/admin/excel-templates",
      "/admin/excel-templates/1", "/admin/notifications", "/admin/audit-logs", "/admin/login-logs", "/admin/settings", "/admin/settings?tab=mail", "/admin/users",
      "/admin/manage-admins", "/admin/programme-branches",
    ],
  },
  company: {
    login: ["recruiter", "Email Address", "hr@nimbus.demo", pick(/Hash::make\('(Company@[^']+)'\)/)],
    pages: ["/company", "/company/profile", "/company/jnf/new", "/company/inf/new", "/company/jnf/1", "/company/jnf/1/edit", "/company/submissions", "/company/postings", "/company/postings/1", "/company/notifications"],
  },
  student: {
    login: ["student", "Roll Number", "23JE0104", pick(/\$password = Hash::make\('([^']+)'\)/)],
    pages: ["/student", "/student/postings", "/student/postings/1", "/student/applications", "/student/resumes", "/student/notices", "/student/surveys", "/student/events", "/student/calendar", "/student/notifications", "/student/profile"],
  },
  mentor: {
    login: process.env.MENTOR_EMAIL ? ["mentor", "Email Address", process.env.MENTOR_EMAIL, process.env.MENTOR_PASSWORD] : null,
    pages: ["/mentor"],
  },
};

const slug = (route) => (route === "/" ? "home" : route.replace(/^\//, "").replace(/[/?=&]+/g, "-"));

(async () => {
  fs.mkdirSync(outDir, { recursive: true });
  const browser = await chromium.launch({ executablePath: exe });
  const problems = [];
  const roles = roleArg ? roleArg.split(",") : Object.keys(ROLES);

  for (const width of widths) {
    for (const role of roles) {
      const spec = ROLES[role];
      if (role !== "public" && !spec.login) continue;
      const context = await browser.newContext({ viewport: { width, height: width < 600 ? 844 : width < 1100 ? 1024 : 900 } });
      const page = await context.newPage();
      const errors = [];
      page.on("console", (m) => {
        if (m.type() === "error" && !/Failed to fetch|ClientFetchError|favicon/.test(m.text())) errors.push(m.text().slice(0, 160));
      });
      if (spec.login) {
        const [type, label, identifier, password] = spec.login;
        await page.goto(`${BASE}/auth/login/${type}`);
        await page.getByLabel(label).fill(identifier);
        await page.getByLabel("Password", { exact: true }).fill(password);
        await page.locator('button[type="submit"]').click();
        await page.waitForURL((u) => !u.pathname.startsWith("/auth"), { timeout: 90000 });
      }
      for (const route of spec.pages) {
        errors.length = 0;
        try {
          await page.goto(BASE + route, { waitUntil: "load", timeout: 90000 });
          await page.waitForLoadState("networkidle", { timeout: 8000 }).catch(() => {});
          await page.waitForTimeout(700);
          await page.screenshot({ path: path.join(outDir, `${role}__${slug(route)}__${width}.jpg`), type: "jpeg", quality: 55 });
          const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
          if (overflow > 1) problems.push(`[${width}] ${role} ${route}: page scrolls sideways by ${overflow}px`);
          if (errors.length) problems.push(`[${width}] ${role} ${route}: console error: ${errors[0]}`);
        } catch (e) {
          problems.push(`[${width}] ${role} ${route}: ${String(e.message).split("\n")[0]}`);
        }
      }
      await context.close();
      console.log(`[${width}] ${role}: ${spec.pages.length} page(s)`);
    }
  }
  await browser.close();
  console.log(problems.length ? `PROBLEMS (${problems.length}):\n` + problems.join("\n") : "No sideways scroll and no console errors.");
})().catch((e) => {
  console.error("FAILED:", e.message.split("\n")[0]);
  process.exit(1);
});
