// Phase 3 PU: the shared left-sidebar shell, checked for every role. Runs against a local stack (Next on :3000, Laravel
// on :8000 with MAIL_MAILER=log). Accounts come from the environment and must be throwaway or demo accounts, e.g.
//   E2E_ADMIN_EMAIL=... E2E_ADMIN_PASSWORD=... E2E_COMPANY_EMAIL=... E2E_COMPANY_PASSWORD=... \
//   E2E_STUDENT_ROLL=... E2E_STUDENT_PASSWORD=... E2E_MENTOR_EMAIL=... E2E_MENTOR_PASSWORD=... \
//   npx playwright test tests/e2e/shell-nav.spec.js --project=chromium
// A role whose account is not given is skipped.
import { expect, test } from "@playwright/test";

const env = process.env;
const ROLES = [
  { role: "admin", login: "admin", label: "Email Address", id: env.E2E_ADMIN_EMAIL, password: env.E2E_ADMIN_PASSWORD, home: "/admin" },
  { role: "company", login: "recruiter", label: "Email Address", id: env.E2E_COMPANY_EMAIL, password: env.E2E_COMPANY_PASSWORD, home: "/company" },
  { role: "student", login: "student", label: "Roll Number", id: env.E2E_STUDENT_ROLL, password: env.E2E_STUDENT_PASSWORD, home: "/student" },
  { role: "mentor", login: "mentor", label: "Email Address", id: env.E2E_MENTOR_EMAIL, password: env.E2E_MENTOR_PASSWORD, home: "/mentor" },
];

async function signIn(page, { login, label, id, password, home }) {
  await page.goto(`/auth/login/${login}`);
  await page.getByLabel(label).fill(id);
  await page.getByLabel("Password", { exact: true }).fill(password);
  await page.getByRole("button", { name: "Sign In" }).click();
  await page.waitForURL((url) => url.pathname.startsWith(home), { timeout: 60000 });
}

const noSidewaysScroll = (page) => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1);

for (const account of ROLES) {
  test.describe(`${account.role} shell`, () => {
    test.skip(!account.id || !account.password, `no ${account.role} account in the environment`);

    test("every sidebar link opens its page and is marked as the current page", async ({ page }) => {
      test.setTimeout(600_000); // the dev server compiles each page on its first visit
      await page.setViewportSize({ width: 1440, height: 900 });
      await signIn(page, account);

      const sidebar = page.locator("aside nav");
      await expect(sidebar).toBeVisible();
      // "Recently visited" repeats pages already listed above it, so each address is visited once.
      const hrefs = [...new Set(await sidebar.locator("a[href]").evaluateAll((links) => links.map((link) => link.getAttribute("href"))))];
      expect(hrefs.length).toBeGreaterThan(0);

      for (const href of hrefs) {
        // A new JNF or INF opens with a "Select Passout Batch" dialog that covers the page; leave it the way a person would.
        const goBack = page.getByRole("dialog").getByRole("button", { name: "Go Back" });
        if (await goBack.isVisible().catch(() => false)) {
          await goBack.click();
          await expect(page.getByRole("dialog")).toBeHidden();
        }
        await sidebar.locator(`a[href="${href}"]`).first().click();
        await page.waitForURL((url) => url.pathname + url.search === href || url.pathname === href.split("?")[0], { timeout: 90000 });
        await expect(sidebar.locator(`a[href="${href}"][aria-current="page"]`), href).toHaveCount(1);
        await expect(sidebar.locator('[aria-current="page"]'), `${href}: one current item`).toHaveCount(1);
        expect(await noSidewaysScroll(page), `${href}: sideways scroll`).toBe(true);

        // The top bar (the page's banner; a page's own <header> inside <main> is not one) says where you are.
        // The only link allowed in it is the notifications bell.
        const topBarLinks = await page.getByRole("banner").locator("a[href]").evaluateAll((links) => links.map((link) => link.getAttribute("href")));
        expect(topBarLinks.filter((link) => !/\/notifications$/.test(link)), `${href}: links in the top bar`).toEqual([]);
      }
    });

    test("the sidebar collapses to a rail, remembers it, and becomes a drawer on a phone", async ({ page }) => {
      test.setTimeout(180_000);
      await page.setViewportSize({ width: 1440, height: 900 });
      await signIn(page, account);

      const aside = page.locator("aside").first();
      await expect(aside).toBeVisible();
      const width = async () => Math.round((await aside.boundingBox()).width);
      expect(await width()).toBe(260);

      await page.getByRole("button", { name: "Collapse the sidebar" }).click();
      await expect.poll(width).toBe(72);
      await page.reload();
      await expect(page.getByRole("button", { name: "Expand the sidebar" })).toBeVisible();
      await expect.poll(width).toBe(72);
      await page.getByRole("button", { name: "Expand the sidebar" }).click();
      await expect.poll(width).toBe(260);

      // Tablet: always the rail. Phone: no sidebar until the menu button opens the drawer.
      await page.setViewportSize({ width: 1024, height: 900 });
      await expect.poll(width).toBe(72);
      expect(await noSidewaysScroll(page)).toBe(true);

      await page.setViewportSize({ width: 390, height: 844 });
      await expect(aside).toBeHidden();
      expect(await noSidewaysScroll(page)).toBe(true);
      await page.getByRole("button", { name: "Open the menu" }).click();
      const drawer = page.locator(".MuiDrawer-root nav");
      await expect(drawer).toBeVisible();
      await expect(drawer.locator('[aria-current="page"]')).toHaveCount(1);
      await expect(page.locator(".MuiDrawer-root").getByRole("button", { name: "Sign out" })).toBeVisible();
      await page.keyboard.press("Escape");
      await expect(drawer).toBeHidden();
    });
  });
}
