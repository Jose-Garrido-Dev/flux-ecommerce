import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { storeData } from "@/lib/store-api";
import type { OrderDetail } from "@/lib/types";
import { Container } from "@/components/ui/Container";
import { ButtonLink } from "@/components/ui/Button";
import { OrderView, formatDate } from "@/components/order/OrderView";

export const metadata: Metadata = { title: "Tu pedido", robots: { index: false, follow: false } };

/**
 * Resultado del checkout y seguimiento del pedido con el enlace privado que llega por correo.
 * Por privacidad, sin sesión solo se muestra la comuna y la región, nunca la dirección completa.
 */
export default async function OrderTrackingPage({ params, searchParams }: { params: Promise<{ token: string }>; searchParams: Promise<{ nuevo?: string }> }) {
  const { token } = await params;
  const { nuevo } = await searchParams;
  if (!/^[A-Za-z0-9_-]{20,200}$/.test(token)) notFound();
  const order = await storeData<OrderDetail>(`/orders/${token}`);
  if (!order) notFound();

  return (
    <Container className="py-12">
      {nuevo ? (
        <>
          <p className="label">Pedido {order.number}</p>
          <h1 className="mt-3 font-display text-5xl sm:text-7xl">¡Gracias, {order.first_name}!</h1>
          <p className="mt-4 max-w-2xl text-muted">
            Recibimos tu pedido. Te enviamos un correo con este resumen y un enlace para seguirlo.
            {order.payment.transfer ? " Para confirmarlo, transfiere el total con los datos de abajo." : ""}
          </p>
        </>
      ) : (
        <>
          <p className="label">Realizado el {formatDate(order.placed_at)}</p>
          <h1 className="mt-3 font-display text-5xl sm:text-7xl">Pedido {order.number}</h1>
        </>
      )}
      <div className="mt-10">
        <OrderView order={order} />
      </div>
      <div className="mt-12">
        <ButtonLink href="/catalogo" variant="secondary">
          Seguir comprando
        </ButtonLink>
      </div>
    </Container>
  );
}
