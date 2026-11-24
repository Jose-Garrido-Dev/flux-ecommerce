import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { storeData } from "@/lib/store-api";
import type { OrderDetail } from "@/lib/types";
import { Container } from "@/components/ui/Container";
import { ButtonLink } from "@/components/ui/Button";
import { Alert } from "@/components/ui/Field";
import { OrderView, formatDate } from "@/components/order/OrderView";

export const metadata: Metadata = { title: "Tu pedido", robots: { index: false, follow: false } };

const RESULT: Record<string, { tone: "ok" | "error" | "info"; text: string }> = {
  rechazado: { tone: "error", text: "El banco rechazó el pago. No se hizo ningún cargo. Puedes intentarlo de nuevo." },
  anulado: { tone: "info", text: "El pago no se completó. No se hizo ningún cargo. Puedes intentarlo de nuevo." },
  pendiente: { tone: "info", text: "Todavía no tenemos la confirmación del pago. Si pagaste, se actualiza solo en unos minutos." },
  error: { tone: "error", text: "No pudimos iniciar el pago. Tu pedido quedó guardado: inténtalo de nuevo desde aquí." },
};

/**
 * Resultado del checkout y seguimiento del pedido con el enlace privado que llega por correo.
 * Por privacidad, sin sesión solo se muestra la comuna y la región, nunca la dirección completa.
 * Lo que dice esta página sale del estado real del pedido en el servidor, no del parámetro ?pago.
 */
export default async function OrderTrackingPage({
  params,
  searchParams,
}: {
  params: Promise<{ token: string }>;
  searchParams: Promise<{ nuevo?: string; pago?: string }>;
}) {
  const { token } = await params;
  const { nuevo, pago } = await searchParams;
  if (!/^[A-Za-z0-9_-]{20,200}$/.test(token)) notFound();
  const order = await storeData<OrderDetail>(`/orders/${token}`);
  if (!order) notFound();

  const paid = order.payment_status === "paid";
  const thanks = Boolean(nuevo) || (pago === "aprobado" && paid);
  const notice = !paid && pago ? RESULT[pago] : undefined;

  return (
    <Container className="py-12">
      {thanks ? (
        <>
          <p className="label">Pedido {order.number}</p>
          <h1 className="mt-3 font-display text-5xl sm:text-7xl">¡Gracias, {order.first_name}!</h1>
          <p className="mt-4 max-w-2xl text-muted">
            {paid
              ? "Recibimos tu pago y ya estamos preparando tu pedido. Te enviamos un correo con el resumen y un enlace para seguirlo."
              : "Recibimos tu pedido. Te enviamos un correo con este resumen y un enlace para seguirlo."}
            {order.payment.transfer ? " Para confirmarlo, transfiere el total con los datos de abajo." : ""}
          </p>
        </>
      ) : (
        <>
          <p className="label">Realizado el {formatDate(order.placed_at)}</p>
          <h1 className="mt-3 font-display text-5xl sm:text-7xl">Pedido {order.number}</h1>
        </>
      )}
      {notice && (
        <div className="mt-8 max-w-3xl">
          <Alert tone={notice.tone}>{notice.text}</Alert>
        </div>
      )}
      <div className="mt-10">
        <OrderView order={order} orderToken={token} apiBase={`orders/${token}`} />
      </div>
      <div className="mt-12">
        <ButtonLink href="/catalogo" variant="secondary">
          Seguir comprando
        </ButtonLink>
      </div>
    </Container>
  );
}
