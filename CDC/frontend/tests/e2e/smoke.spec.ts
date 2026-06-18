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
});
