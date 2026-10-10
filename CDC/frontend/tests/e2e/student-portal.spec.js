// Phase 2 student-portal smoke (QA F-014). Runs against a local stack (Next on :3000, Laravel on :8000 with
// MAIL_MAILER=log). The signed-in steps need throwaway QA accounts passed through the environment, e.g.
//   E2E_STUDENT_ROLL=26QA0001 E2E_STUDENT_PASSWORD=... E2E_ADMIN_EMAIL=... E2E_ADMIN_PASSWORD=... npm run test:e2e:student
// Never point it at real accounts. Browsers must be installed once with `npx playwright install`.
import { expect, test } from "@playwright/test";

const student = { roll: process.env.E2E_STUDENT_ROLL, password: process.env.E2E_STUDENT_PASSWORD };
const admin = { email: process.env.E2E_ADMIN_EMAIL, password: process.env.E2E_ADMIN_PASSWORD };

async function signIn(page, type, identifierLabel, identifier, password) {
  await page.goto(`/auth/login/${type}`);
  await page.getByLabel(identifierLabel).fill(identifier);
  await page.getByLabel("Password", { exact: true }).fill(password);
  await page.getByRole("button", { name: "Sign In" }).click();
  // Signed in once the portal itself is reached (the login URL also contains "/student" or "/admin").
  await page.waitForURL((url) => url.pathname.startsWith(`/${type}`), { timeout: 30000 });
}

test.describe("Student portal smoke", () => {
  test("student login page asks for a roll number", async ({ page }) => {
    await page.goto("/auth/login/student");
    await expect(page.getByLabel("Roll Number")).toBeVisible();
    await expect(page.getByRole("button", { name: "Sign In" })).toBeVisible();
  });

  test("a callbackUrl to another site is ignored after login (SEC-005)", async ({ page, baseURL }) => {
    test.skip(!student.roll || !student.password, "E2E_STUDENT_ROLL / E2E_STUDENT_PASSWORD not set");
    await page.goto(`/auth/login/student?callbackUrl=${encodeURIComponent("https://evil.example/steal")}`);
    await page.getByLabel("Roll Number").fill(student.roll);
    await page.getByLabel("Password", { exact: true }).fill(student.password);
    await page.getByRole("button", { name: "Sign In" }).click();
    await page.waitForURL((url) => url.pathname.startsWith("/student"), { timeout: 30000 });
    expect(new URL(page.url()).origin).toBe(new URL(baseURL ?? "http://127.0.0.1:3000").origin);
  });

  test("student signs in, browses job profiles and opens one", async ({ page }) => {
    test.skip(!student.roll || !student.password, "E2E_STUDENT_ROLL / E2E_STUDENT_PASSWORD not set");
    await signIn(page, "student", "Roll Number", student.roll, student.password);

    await page.goto("/student/postings");
    await expect(page.getByRole("heading", { name: "Job Profiles" })).toBeVisible();
    const firstCard = page.locator('a[href^="/student/postings/"]').first();
    await expect(firstCard).toBeVisible();
    await expect(firstCard).toContainText("IST"); // deadlines are shown in IST (QA F-005)

    await firstCard.click();
    await expect(page.getByText("Application deadline")).toBeVisible();
    await expect(page.getByRole("button", { name: /Apply|Save changes/ }).first()).toBeVisible();
  });

  test("student sees applications, calendar and profile", async ({ page }) => {
    test.skip(!student.roll || !student.password, "E2E_STUDENT_ROLL / E2E_STUDENT_PASSWORD not set");
    await signIn(page, "student", "Roll Number", student.roll, student.password);

    for (const path of ["/student/applications", "/student/calendar", "/student/profile", "/student/resumes"]) {
      const response = await page.goto(path);
      expect(response?.status(), path).toBeLessThan(400);
      await expect(page).toHaveURL(new RegExp(`${path}$`));
      await expect(page.getByRole("alert").filter({ hasText: /failed|error/i })).toHaveCount(0);
    }
  });
});

test.describe("Admin posting smoke", () => {
  test("admin opens a posting and its Eligible list", async ({ page }) => {
    test.skip(!admin.email || !admin.password, "E2E_ADMIN_EMAIL / E2E_ADMIN_PASSWORD not set");
    await signIn(page, "admin", "Email Address", admin.email, admin.password);

    await page.goto("/admin/postings");
    // A real job profile, not the "New Job Profile" button (also under /admin/postings/).
    const firstPosting = page.locator('a[href^="/admin/postings/"]:not([href="/admin/postings/new"])').first();
    await expect(firstPosting).toBeVisible();
    await firstPosting.click();
    await page.getByRole("tab", { name: "Eligible" }).click();
    await expect(page.getByRole("tab", { name: /Not applied/ })).toBeVisible();
  });
});
