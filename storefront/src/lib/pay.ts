import { bff } from "./client-api";

export type PayRedirect = { url: string; method: string; fields: Record<string, string> };
type PayResponse = { paid: boolean; redirect: PayRedirect | null };

/**
 * Lleva al cliente a la pasarela: crea el intento de pago en Laravel y envía un formulario POST
 * al sitio de la pasarela (así lo exige Webpay). Si el pedido ya estaba pagado, devuelve "paid".
 */
export async function startPayment(orderToken: string): Promise<{ ok: true; paid: boolean } | { ok: false; message: string }> {
  const res = await bff<PayResponse>(`orders/${orderToken}/pay`, { method: "POST", body: {} });
  if (!res.ok || !res.data) return { ok: false, message: res.message ?? "No pudimos iniciar el pago." };
  if (res.data.paid || !res.data.redirect) return { ok: true, paid: true };

  const { url, method, fields } = res.data.redirect;
  const form = document.createElement("form");
  form.method = method;
  form.action = url;
  for (const [name, value] of Object.entries(fields)) {
    const input = document.createElement("input");
    input.type = "hidden";
    input.name = name;
    input.value = value;
    form.appendChild(input);
  }
  document.body.appendChild(form);
  form.submit();

  return { ok: true, paid: false };
}
