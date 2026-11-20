import type { Metadata } from "next";
import { Container } from "@/components/ui/Container";
import { CartPageView } from "@/components/cart/CartPageView";

export const metadata: Metadata = { title: "Carrito", robots: { index: false } };

export default function CartPage() {
  return (
    <Container className="py-12">
      <h1 className="font-display text-6xl sm:text-7xl">Carrito</h1>
      <CartPageView />
    </Container>
  );
}
