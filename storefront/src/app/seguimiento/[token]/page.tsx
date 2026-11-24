import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { storeData } from "@/lib/store-api";
import type { ShipmentTracking } from "@/lib/types";
import { Container } from "@/components/ui/Container";
import { ShipmentCard } from "@/components/order/ShipmentCard";

export const metadata: Metadata = { title: "Seguimiento de tu envío", robots: { index: false, follow: false } };

/**
 * Seguimiento público de un despacho con el enlace del correo.
 * Se puede compartir: no muestra nombres, correos, teléfonos ni la dirección (solo comuna y región).
 */
export default async function ShipmentTrackingPage({ params }: { params: Promise<{ token: string }> }) {
  const { token } = await params;
  if (!/^[A-Za-z0-9_-]{20,100}$/.test(token)) notFound();
  const shipment = await storeData<ShipmentTracking>(`/shipments/${token}`);
  if (!shipment) notFound();

  const pickup = shipment.delivery === "pickup";
  const destination = shipment.destination ? [shipment.destination.commune, shipment.destination.region].filter(Boolean).join(", ") : null;

  return (
    <Container className="max-w-3xl py-12">
      <p className="label">Pedido {shipment.order_number}</p>
      <h1 className="mt-3 font-display text-5xl sm:text-6xl">{shipment.status_label}</h1>
      <dl className="mt-6 grid gap-4 text-sm sm:grid-cols-2">
        <div>
          <dt className="text-muted">{pickup ? "Retiro" : "Entrega"}</dt>
          <dd className="mt-0.5 font-medium">{pickup ? (shipment.pickup_address ?? "En la tienda") : (destination ?? "—")}</dd>
        </div>
        {shipment.estimated_days && !shipment.delivered_at && (
          <div>
            <dt className="text-muted">Plazo estimado</dt>
            <dd className="mt-0.5 font-medium">{shipment.estimated_days}</dd>
          </div>
        )}
      </dl>
      <div className="mt-10">
        <ShipmentCard shipment={shipment} />
      </div>
    </Container>
  );
}
