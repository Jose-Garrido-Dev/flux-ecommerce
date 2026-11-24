import type { Metadata } from "next";
import Link from "next/link";
import { notFound, redirect } from "next/navigation";
import { hasSession, storeData } from "@/lib/store-api";
import type { OrderDetail } from "@/lib/types";
import { Container } from "@/components/ui/Container";
import { OrderView, formatDate } from "@/components/order/OrderView";

export const metadata: Metadata = { title: "Detalle del pedido", robots: { index: false, follow: false } };

export default async function AccountOrderPage({ params }: { params: Promise<{ uuid: string }> }) {
  const { uuid } = await params;
  if (!(await hasSession())) redirect(`/cuenta?volver=/cuenta/pedidos/${uuid}`);
  if (!/^[0-9a-f-]{36}$/.test(uuid)) notFound();
  const order = await storeData<OrderDetail>(`/me/orders/${uuid}`);
  if (!order) notFound();

  return (
    <Container className="py-12">
      <Link href="/cuenta" className="text-sm underline underline-offset-4">
        ← Volver a Mi cuenta
      </Link>
      <p className="label mt-8">Realizado el {formatDate(order.placed_at)}</p>
      <h1 className="mt-3 font-display text-5xl sm:text-7xl">Pedido {order.number}</h1>
      <div className="mt-10">
        <OrderView order={order} apiBase={`me/orders/${uuid}`} />
      </div>
    </Container>
  );
}
