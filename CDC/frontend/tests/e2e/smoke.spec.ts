import { expect, test } from "@playwright/test";

test.describe("Public route smoke", () => {
  test("home page renders", async ({ page }) => {
    await page.goto("/");
    await expect(page).toHaveURL(/\/$/);
  });

  test("login page renders auth content", async ({ page }) => {
    await page.goto("/auth/login");
    await expect(page.getByRole("heading", { name: "IIT ISM CDC Portal" })).toBeVisible();
    await expect(page.getByRole("button", { name: "Sign In" })).toBeVisible();
  });

  test("company registration page is reachable", async ({ page }) => {
    await page.goto("/company/register");
    await expect(page).toHaveURL(/\/company\/register/);
  });

  test("pages send a Content-Security-Policy and load without violations (SEC-009)", async ({ page }) => {
    test.setTimeout(90_000);
    const violations: string[] = [];
    page.on("console", (message) => {
      if (message.type() === "error" && /Content Security Policy/i.test(message.text())) violations.push(message.text());
    });

    for (const path of ["/", "/auth/login", "/auth/login/student", "/company/register", "/alumni"]) {
      const response = await page.goto(path);
      const csp = response?.headers()["content-security-policy"] ?? "";
      expect(csp, path).toContain("default-src 'self'");
      expect(csp, path).toContain("object-src 'none'");
      expect(csp, path).toContain("frame-ancestors 'self'");
      await page.waitForLoadState("load");
      await page.waitForTimeout(1000);
    }

    expect(violations).toEqual([]);
  });
});
