import { expect, test } from "@playwright/test";

test("login exposes an accessible form", async ({ page }) => {
    await page.goto("/login");

    await expect(page).toHaveTitle(/Iniciar sesión/);
    await expect(page.getByRole("main")).toBeVisible();
    await expect(
        page.getByRole("heading", { name: "AUTOFIX", level: 1 }),
    ).toBeVisible();

    const email = page.getByLabel("Correo electrónico");
    const password = page.getByLabel("Contraseña");

    await expect(email).toHaveAttribute("type", "email");
    await expect(email).toHaveAttribute("autocomplete", "email");
    await expect(password).toHaveAttribute("autocomplete", "current-password");
    await expect(
        page.getByRole("button", { name: "Iniciar Sesión" }),
    ).toBeVisible();
});

test("dashboard redirects unauthenticated visitors to login", async ({
    page,
}) => {
    await page.goto("/dashboard");

    await expect(page).toHaveURL(/\/login$/);
    await expect(
        page.getByRole("heading", { name: "AUTOFIX", level: 1 }),
    ).toBeVisible();
});

test("authenticated users reach the dashboard landmarks", async ({ page }) => {
    const email = process.env.E2E_EMAIL;
    const password = process.env.E2E_PASSWORD;
    test.skip(
        !email || !password,
        "Set E2E_EMAIL and E2E_PASSWORD to run the authenticated smoke test",
    );

    await page.goto("/login");
    await page.getByLabel("Correo electrónico").fill(email!);
    await page.getByLabel("Contraseña").fill(password!);
    await page.getByRole("button", { name: "Iniciar Sesión" }).click();

    await expect(page).toHaveURL(/\/dashboard/);
    await expect(page.getByRole("main")).toHaveAttribute("id", "main-content");
    await page.keyboard.press("Tab");
    await expect(
        page.getByRole("link", { name: "Saltar al contenido principal" }),
    ).toBeFocused();
});
