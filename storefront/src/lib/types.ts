/** Tipos del contrato público (packages/api-contract/openapi.yaml). */

export type PublicStore = {
  name: string;
  tagline: string | null;
  currency: string;
  locale: string;
  announcements: string[];
  contact: { email: string | null; phone: string | null };
  social: Partial<Record<"instagram" | "facebook" | "tiktok", string>>;
};

export type Image = { path: string; alt: string; width: number | null; height: number | null };

export type Price = {
  currency: string;
  amount: number;
  compare_at: number | null;
  discount_percent: number | null;
  sale_ends_at: string | null;
  varies?: boolean;
};

export type ProductCard = {
  id: string;
  slug: string;
  name: string;
  brand: { name: string; slug: string } | null;
  image: Image | null;
  hover_image: Image | null;
  price: Price | null;
  featured: boolean;
  is_new: boolean;
  swatches: { label: string; color_hex: string }[];
  variant_count: number;
};

export type OptionAxis = {
  code: string;
  name: string;
  display: "swatch" | "pill" | "select";
  values: { value: string; label: string; color_hex: string | null }[];
};

export type Variant = {
  sku: string;
  is_default: boolean;
  options: Record<string, string>;
  price: Price | null;
  image: Image | null;
};

export type Crumb = { name: string; slug: string };

export type ProductDetail = ProductCard & {
  short_description: string | null;
  description_html: string | null;
  images: (Image & { variant_sku: string | null })[];
  breadcrumbs: Crumb[];
  tax: { included: boolean; label: string };
  options: OptionAxis[];
  variants: Variant[];
  specifications: { name: string; value: string }[];
  seo: { title: string; description: string | null };
};

export type Facet = {
  code: string;
  name: string;
  display: string;
  values: { value: string; label: string; color_hex: string | null; count: number; selected: boolean }[];
};

export type ProductList = {
  data: ProductCard[];
  meta: { page: number; per_page: number; total: number; last_page: number };
  facets: Facet[];
};

export type CategoryNode = { name: string; slug: string; image: Image | null; children: CategoryNode[] };

export type CategoryDetail = {
  name: string;
  slug: string;
  image: Image | null;
  description: string | null;
  breadcrumbs: Crumb[];
  children: { name: string; slug: string; image: Image | null }[];
};

export type BrandCard = { name: string; slug: string; logo: Image | null };

export type BrandDetail = BrandCard & { description: string | null };

export type HomeData = {
  categories: { name: string; slug: string; image: Image | null }[];
  featured: ProductCard[];
  newest: ProductCard[];
  on_sale: ProductCard[];
  brands: BrandCard[];
};
