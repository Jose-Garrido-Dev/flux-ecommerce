/**
 * Prueba real de punta a punta contra el ambiente de integración de Transbank (Webpay Plus).
 * Recorre la tienda en un navegador: pedido → "Pagar con Webpay" → formulario de Transbank →
 * simulador del banco → regreso a la tienda, y revisa en la base de datos que el pedido quedó como corresponde.
 *
 * Casos: rechazado y luego aprobado en el mismo pedido (reintento), anulado por el cliente,
 * regreso duplicado sin efecto doble y reembolso parcial real.
 * Deja capturas, HTML y un resumen en ./evidencia (se sube como artefacto en GitHub Actions).
 *
 * Requiere backend en API_URL, tienda en STORE_URL y Webpay verificada en integración
 * (php artisan payments:verify --integration --enable). Tarjetas y claves de prueba públicas de Transbank.
 */
import { chromium } from "playwright";
import { execFileSync } from "node:child_process";
import { mkdirSync, writeFileSync } from "node:fs";
import { randomUUID } from "node:crypto";
import path from "node:path";

const API = process.env.API_URL ?? "http://127.0.0.1:8000";
const STORE = process.env.STORE_URL ?? "http://127.0.0.1:3000";
const OUT = path.resolve(process.env.EVIDENCE_DIR ?? "evidencia");
const HELPER = new URL("./webpay-datos.php", import.meta.url).pathname;

const VISA_OK = { number: "4051885600446623", cvv: "123" };
const MASTERCARD_RECHAZO = { number: "5186059559590568", cvv: "123" };
const RUT = "11.111.111-1";
const CLAVE = "123";

const resumen = { inicio: new Date().toISOString(), casos: [] };
mkdirSync(OUT, { recursive: true });

function datos(...args) {
  try {
    return execFileSync("php", [HELPER, ...args], { encoding: "utf8", stdio: ["ignore", "pipe", "pipe"] }).trim();
  } catch (e) {
    throw new Error(`php webpay-datos.php ${args.join(" ")}: ${String(e.stderr || e.stdout || e.message).trim().slice(0, 500)}`);
  }
}
function json(texto) {
  try {
    return JSON.parse(texto);
  } catch {
    throw new Error(`respuesta no válida del ayudante: ${texto.slice(0, 500)}`);
  }
}
const estado = (numero) => json(datos("estado", numero));

function assert(cond, msg) {
  if (!cond) throw new Error(`Falló: ${msg}`);
}

async function api(method, url, body, headers = {}) {
  const res = await fetch(`${API}/api/storefront/v1${url}`, {
    method,
    headers: { Accept: "application/json", "Content-Type": "application/json", ...headers },
    body: body ? JSON.stringify(body) : undefined,
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(`${method} ${url} → ${res.status} ${JSON.stringify(json)}`);
  return json;
}

async function nuevoPedido(sku) {
  const cart = await api("POST", "/cart/items", { sku, quantity: 1 });
  const { data } = await api(
    "POST",
    "/checkout",
    {
      email: "prueba-webpay@ejemplo.cl", first_name: "Prueba", last_name: "Integración", phone: "912345678",
      shipping_method: "retiro", payment_method: "webpay_plus", accepts_terms: true,
    },
    { "X-Cart-Token": cart.token, "Idempotency-Key": randomUUID() },
  );
  assert(data.next === "pay", `el checkout debe pedir pagar con la pasarela (next=${data.next})`);
  return data;
}

/** Guarda captura y HTML del paso actual. */
async function evidencia(page, caso, paso) {
  const dir = path.join(OUT, caso);
  mkdirSync(dir, { recursive: true });
  const n = String((evidencia.n = (evidencia.n ?? 0) + 1)).padStart(2, "0");
  console.log(`  · ${caso} ${n} ${paso}: ${page.url()}`);
  await page.screenshot({ path: path.join(dir, `${n}-${paso}.png`), fullPage: true }).catch(() => {});
  for (const frame of page.frames()) {
    const html = await frame.content().catch(() => "");
    if (html) writeFileSync(path.join(dir, `${n}-${paso}${frame === page.mainFrame() ? "" : "-frame"}.html`), html);
  }
}

/** Busca el primer selector visible en la página o en sus iframes. */
async function find(page, selectors, timeout = 20000) {
  const end = Date.now() + timeout;
  while (Date.now() < end) {
    for (const frame of page.frames()) {
      for (const sel of selectors) {
        const loc = frame.locator(sel).first();
        if (await loc.isVisible().catch(() => false)) return loc;
      }
    }
    await page.waitForTimeout(400);
  }
  return null;
}

/** Lista campos y botones visibles en cada marco (para ajustar selectores desde el registro de Actions). */
async function describir(page) {
  const out = [];
  for (const frame of page.frames()) {
    const items = await frame
      .evaluate(() =>
        [...document.querySelectorAll("input, button, select, a, [role=button]")]
          .filter((e) => e.offsetParent !== null)
          .slice(0, 60)
          .map((e) => `${e.tagName.toLowerCase()}#${e.id}.${String(e.className).slice(0, 40)} name=${e.getAttribute("name")} type=${e.getAttribute("type")} ph=${e.getAttribute("placeholder")} aria=${e.getAttribute("aria-label")} txt=${(e.innerText || e.value || "").trim().slice(0, 40)}`),
      )
      .catch(() => []);
    out.push(`  [marco] ${frame.url()}`, ...items.map((i) => `    ${i}`));
  }
  return out.join("\n");
}

async function fill(page, selectors, value, label) {
  const loc = await find(page, selectors);
  if (!loc) console.log(`No se encontró ${label} en ${page.url()}:\n${await describir(page)}`);
  assert(loc, `no se encontró el campo ${label}`);
  await loc.click();
  await loc.fill("");
  await loc.pressSequentially(value, { delay: 40 });
}

async function click(page, selectors, label, timeout) {
  const loc = await find(page, selectors, timeout);
  if (!loc) console.log(`No se encontró ${label} en ${page.url()}:\n${await describir(page)}`);
  assert(loc, `no se encontró ${label}`);
  await loc.click();
}

const hasText = (re) => [`button:text-matches("${re}", "i")`, `a:text-matches("${re}", "i")`, `input[type=submit][value*="${re}" i]`, `[role=button]:text-matches("${re}", "i")`];

/** Desde la página del pedido en la tienda hasta el formulario de Webpay. */
async function irAWebpay(page, caso, token, boton) {
  await page.goto(`${STORE}/pedido/${token}`);
  await evidencia(page, caso, "pedido-en-la-tienda");
  await page.getByRole("button", { name: boton }).click();
  await page.waitForURL(/transbank\.cl/, { timeout: 60000 });
  await page.waitForLoadState("networkidle").catch(() => {});
  await evidencia(page, caso, "formulario-webpay");
}

async function pagarConTarjeta(page, caso, tarjeta, decisionBanco) {
  // Algunas versiones del formulario piden elegir primero el medio de pago
  const medio = await find(page, ["#card-number", "input[name=cardNumber]", 'input[autocomplete="cc-number"]'], 5000);
  if (!medio) {
    await click(page, ["#tarjetas", "button.payment-options__method-items-option", ...hasText("Tarjeta")], "la opción de pago con tarjeta", 10000);
    await page.waitForTimeout(1000);
    await evidencia(page, caso, "opcion-tarjetas");
  }
  await fill(page, ["#card-number", "input[name=cardNumber]", 'input[autocomplete="cc-number"]', 'input[placeholder*="número" i]', 'input[placeholder*="numero" i]'], tarjeta.number, "número de tarjeta");
  // Webpay valida el número al salir del campo y recién ahí muestra vencimiento y CVV
  await page.keyboard.press("Tab");
  await page.waitForTimeout(1500);
  await evidencia(page, caso, "tarjeta-ingresada");
  // Fecha y CVV aparecen después de validar el número
  for (const [sel, valor, nombre] of [["#card-exp", "1230", "vencimiento"], ["#card-cvv", tarjeta.cvv, "CVV"]]) {
    const todos = page.locator(sel);
    await todos.first().waitFor({ state: "attached", timeout: 30000 }).catch(() => {});
    const n = await todos.count();
    const visibles = page.locator(`${sel}:visible`);
    const campo = (await visibles.count()) > 0 ? visibles.first() : todos.first();
    console.log(`    ${nombre}: ${n} elemento(s), caja ${JSON.stringify(await campo.boundingBox().catch(() => null))}`);
    assert(n > 0, `no apareció el campo ${nombre}`);
    await campo.click({ force: true });
    await page.keyboard.type(valor, { delay: 60 });
    await page.keyboard.press("Tab");
  }
  await page.waitForTimeout(800);
  // Cuotas: si aparece un selector, se deja la opción por defecto
  await evidencia(page, caso, "datos-tarjeta");
  await click(page, ["button.submit", "button[type=submit]", ...hasText("Pagar"), ...hasText("Continuar")], "el botón Pagar");

  // Simulador del banco de pruebas: RUT y clave, luego Aceptar o Rechazar
  const rut = await find(page, ["#rutClient", "input[name=rutClient]", 'input[name*="rut" i]'], 45000);
  if (!rut) console.log(`Sin simulador del banco en ${page.url()}:\n${await describir(page)}`);
  if (rut) {
    await evidencia(page, caso, "banco-autenticacion");
    await rut.fill(RUT.replace(/[.-]/g, ""));
    await fill(page, ["#passwordClient", "input[name=passwordClient]", 'input[type=password]'], CLAVE, "clave del banco");
    await click(page, ['input[type=submit]', "button[type=submit]", ...hasText("Aceptar")], "el botón del banco");
    const select = await find(page, ["#vci", "select"], 20000);
    if (select) {
      await evidencia(page, caso, "banco-decision");
      const opciones = await select.locator("option").allTextContents();
      const elegida = opciones.find((o) => new RegExp(decisionBanco, "i").test(o));
      if (elegida) await select.selectOption({ label: elegida });
      await click(page, ['input[type=submit]', "button[type=submit]", ...hasText("Continuar")], "el botón Continuar del banco");
    }
  }
}

async function volverALaTienda(page, caso) {
  try {
    await page.waitForURL((u) => u.toString().startsWith(STORE), { timeout: 90000 });
  } catch (e) {
    await evidencia(page, caso, "sin-regreso");
    console.log(`No volvió a la tienda. Página actual ${page.url()}:\n${await describir(page)}`);
    throw e;
  }
  await page.waitForLoadState("networkidle").catch(() => {});
  await evidencia(page, caso, "regreso-a-la-tienda");
  return new URL(page.url());
}

async function caso(nombre, fn) {
  const t0 = Date.now();
  const registro = { caso: nombre };
  resumen.casos.push(registro);
  try {
    Object.assign(registro, await fn());
    registro.resultado = "ok";
  } catch (e) {
    registro.resultado = "falló";
    registro.error = String(e?.message ?? e);
  }
  registro.segundos = Math.round((Date.now() - t0) / 1000);
  console.log(`${registro.resultado === "ok" ? "✓" : "✗"} ${nombre}${registro.error ? `: ${registro.error}` : ""}`);
}

const browser = await chromium.launch();
const sku = datos("preparar");

await caso("rechazado y luego aprobado en el mismo pedido", async () => {
  const page = await browser.newPage({ locale: "es-CL" });
  const pedido = await nuevoPedido(sku);
  const c = "1-rechazado-y-aprobado";

  await irAWebpay(page, c, pedido.token, /Pagar con Webpay/);
  await pagarConTarjeta(page, c, MASTERCARD_RECHAZO, "Rechaz");
  let vuelta = await volverALaTienda(page, c);
  assert(vuelta.searchParams.get("pago") === "rechazado", `se esperaba pago=rechazado y llegó ${vuelta.search}`);
  await page.getByRole("alert").filter({ hasText: "El banco rechazó el pago" }).waitFor();
  let e = estado(pedido.number);
  assert(e.pedido.payment_status === "pending" && e.pagos.at(-1).status === "failed", "el intento rechazado debe quedar como Rechazado y el pedido pendiente");

  await irAWebpay(page, c, pedido.token, /Intentar pagar de nuevo/);
  await pagarConTarjeta(page, c, VISA_OK, "Acept");
  vuelta = await volverALaTienda(page, c);
  assert(vuelta.searchParams.get("pago") === "aprobado", `se esperaba pago=aprobado y llegó ${vuelta.search}`);
  await page.getByRole("heading", { name: /¡Gracias/ }).waitFor();
  e = estado(pedido.number);
  const pago = e.pagos.at(-1);
  assert(e.pedido.payment_status === "paid" && e.pedido.status === "processing", "el pedido debe quedar pagado y en preparación");
  assert(pago.attempt === 2 && pago.gateway_order === `${pedido.number}-2`, "el reintento usa una orden de compra nueva");
  assert(pago.card_last4 === "6623" && pago.authorization_code, "se guardan los 4 últimos dígitos y el código de autorización");
  assert(!JSON.stringify(e.transacciones).includes(VISA_OK.number), "nunca se guarda el número completo de la tarjeta");

  // Regreso duplicado: el mismo token_ws enviado otra vez no paga dos veces
  const dup = await fetch(`${API}/api/pagos/webpay_plus/retorno`, {
    method: "POST", redirect: "manual",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({ token_ws: pago.external_id }),
  });
  assert(dup.status === 303 && dup.headers.get("location").includes("pago=aprobado"), "el regreso duplicado responde aprobado");
  const e2 = estado(pedido.number);
  assert(e2.eventos.filter((t) => t === "payment_confirmed").length === 1, "el pago se registra una sola vez");

  // Reembolso parcial real en Transbank
  const reembolso = json(datos("reembolso", pedido.number, "1000"));
  const e3 = estado(pedido.number);
  assert(e3.pedido.payment_status === "partially_refunded" && e3.pedido.refunded_total === 1000, "reembolso parcial aplicado");
  await page.close();
  return { pedido: pedido.number, total: pedido.grand_total, reembolso, estado: e3 };
});

await caso("anulado por el cliente en Webpay", async () => {
  const page = await browser.newPage({ locale: "es-CL" });
  const pedido = await nuevoPedido(sku);
  const c = "2-anulado";
  await irAWebpay(page, c, pedido.token, /Pagar con Webpay/);
  await click(page, ["button.btn-return", ...hasText("Anular"), 'a[href*="anular" i]'], "el enlace Anular compra");
  await page.waitForTimeout(1500);
  if (!page.url().startsWith(STORE)) {
    await evidencia(page, c, "confirmar-anulacion");
    // Webpay pide confirmar en una ventana propia
    const modal = page.locator("app-modal-error, [class*=modal__content]").first();
    if (await modal.isVisible().catch(() => false)) {
      console.log(`    ventana de Webpay: ${(await modal.innerText()).replace(/\s+/g, " ").slice(0, 300)}`);
      const botones = modal.locator("button, a");
      const textos = await botones.allInnerTexts();
      console.log(`    botones: ${JSON.stringify(textos)}`);
      const i = textos.findIndex((t) => /anular|s[ií]|aceptar|confirmar|volver/i.test(t));
      if (i >= 0) await botones.nth(i).click();
    }
  }
  const vuelta = await volverALaTienda(page, c);
  assert(vuelta.searchParams.get("pago") === "anulado", `se esperaba pago=anulado y llegó ${vuelta.search}`);
  await page.getByText("El pago no se completó").first().waitFor();
  const e = estado(pedido.number);
  assert(e.pedido.payment_status === "pending" && e.pagos.at(-1).status === "aborted", "el intento anulado queda como No completado");
  await page.close();
  return { pedido: pedido.number, estado: e };
});

await browser.close();
resumen.fin = new Date().toISOString();
writeFileSync(path.join(OUT, "resumen.json"), JSON.stringify(resumen, null, 2));
const fallidos = resumen.casos.filter((c) => c.resultado !== "ok");
console.log(fallidos.length ? `${fallidos.length} caso(s) fallaron. Revisa la evidencia.` : "Todos los casos pasaron contra Transbank (integración).");
process.exit(fallidos.length ? 1 : 0);
