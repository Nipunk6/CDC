import { expect, test } from "@playwright/test";

type NavTiming = {
  domContentLoadedEventEnd: number;
  loadEventEnd: number;
  responseStart: number;
};

const routes = ["/", "/auth/login", "/company/register"];

for (const route of routes) {
  test(`navigation timings remain within baseline for ${route}`, async ({ page }) => {
    await page.goto(route);

    const nav = await page.evaluate(() => {
      const entry = performance.getEntriesByType("navigation")[0] as PerformanceNavigationTiming | undefined;
      if (!entry) {
        return null;
      }

      return {
        domContentLoadedEventEnd: entry.domContentLoadedEventEnd,
        loadEventEnd: entry.loadEventEnd,
        responseStart: entry.responseStart,
      };
    });

    expect(nav).not.toBeNull();

    const timings = nav as NavTiming;

    // Keep broad limits to avoid flaky CI while still catching major regressions.
    expect(timings.responseStart).toBeLessThan(3000);
    expect(timings.domContentLoadedEventEnd).toBeLessThan(6000);
    expect(timings.loadEventEnd).toBeLessThan(10000);
  });
}
