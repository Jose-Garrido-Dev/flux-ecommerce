import Image from "next/image";
import Link from "next/link";
import type { ProductCard as Card } from "@/lib/types";
import { mediaUrl } from "@/lib/format";
import { Price } from "@/components/ui/Price";
import { Badge } from "@/components/ui/Badge";

const SIZES = "(min-width: 1280px) 22vw, (min-width: 768px) 30vw, 46vw";

export function ProductCard({
  product,
  priority = false,
  headingLevel = 3,
}: {
  product: Card;
  priority?: boolean;
  headingLevel?: 2 | 3;
}) {
  const Heading = headingLevel === 2 ? "h2" : "h3";
  const discount = product.price?.discount_percent;
  return (
    <article className="group relative">
      <div className="card-media relative aspect-[4/5] overflow-hidden rounded-[var(--radius)] bg-subtle">
        {product.image ? (
          <Image
            src={mediaUrl(product.image.path)}
            alt={product.image.alt}
            fill
            sizes={SIZES}
            priority={priority}
            className="main-img object-cover"
          />
        ) : (
          <div className="flex h-full items-center justify-center text-sm text-muted">Sin imagen</div>
        )}
        {product.hover_image && (
          <Image src={mediaUrl(product.hover_image.path)} alt="" fill sizes={SIZES} className="hover-img object-cover" />
        )}
        {discount ? (
          <div className="absolute left-3 top-3">
            <Badge tone="sale">-{discount}%</Badge>
          </div>
        ) : null}
      </div>
      <div className="mt-3 space-y-1">
        {product.brand && <p className="label !text-[0.68rem]">{product.brand.name}</p>}
        <Heading className="text-sm leading-snug">
          <Link href={`/producto/${product.slug}`} className="after:absolute after:inset-0">
            {product.name}
          </Link>
        </Heading>
        <Price price={product.price} />
      </div>
    </article>
  );
}

export function ProductGrid({
  products,
  priorityCount = 0,
  headingLevel = 3,
}: {
  products: Card[];
  priorityCount?: number;
  headingLevel?: 2 | 3;
}) {
  return (
    <ul className="grid grid-cols-2 gap-x-4 gap-y-10 md:grid-cols-3 xl:grid-cols-4">
      {products.map((p, i) => (
        <li key={p.id}>
          <ProductCard product={p} priority={i < priorityCount} headingLevel={headingLevel} />
        </li>
      ))}
    </ul>
  );
}
