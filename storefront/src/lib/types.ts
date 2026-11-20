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
  /** Ausente en respuestas de un backend anterior a la fase 4: se asume disponible. */
  in_stock?: boolean;
};

export type StockStatus = { availability: "in_stock" | "low_stock" | "out_of_stock" | "backorder"; quantity: number | null };

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
  stock?: StockStatus;
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
  seo: { title: string; description: string | null; canonical_url?: string | null };
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

/* ---------- Carrito, checkout, pedidos y cuenta (Fase 5) ---------- */

export type CartLine = {
  sku: string;
  product_slug: string;
  name: string;
  variant_label: string | null;
  image: Image | null;
  quantity: number;
  unit_price: number;
  compare_at: number | null;
  previous_unit_price: number | null;
  line_total: number;
  tax_amount: number;
  purchasable: boolean;
  /** null = sin límite de stock (no se controla o admite pedidos sin stock) */
  max_quantity: number | null;
  stock: StockStatus | null;
};

export type Cart = {
  items: CartLine[];
  count: number;
  subtotal: number;
  tax_total: number;
  warnings: string[];
  currency: string;
};

export type ShippingOption = {
  code: string;
  name: string;
  type: "flat" | "pickup";
  description: string | null;
  amount: number;
  free_over: number | null;
  estimated_days: string | null;
  pickup_address: string | null;
};

export type PaymentOption = { code: string; name: string; description: string; type?: "redirect" | "manual"; test_mode?: boolean };

export type CheckoutOptions = { shipping_methods: ShippingOption[]; payment_methods: PaymentOption[]; subtotal: number };

export type GeoRegion = { code: string; name: string; communes: string[] };

export type TransferInstructions = {
  bank_name: string | null;
  account_type: string | null;
  account_number: string | null;
  account_holder: string | null;
  account_rut: string | null;
  account_email: string | null;
  instructions: string | null;
  hours: number;
};

export type OrderAddress = {
  recipient?: string;
  phone?: string;
  street?: string;
  number?: string;
  apartment?: string | null;
  commune: string | null;
  region: string | null;
  reference?: string | null;
};

export type OrderDetail = {
  number: number;
  uuid: string | null;
  placed_at: string | null;
  status: string;
  status_label: string;
  payment_status: string;
  payment_status_label: string;
  first_name: string;
  email: string | null;
  items: { name: string; variant_label: string | null; sku: string; image: { path: string; alt: string } | null; quantity: number; unit_price: number; line_total: number }[];
  subtotal: number;
  shipping_total: number;
  tax_total: number;
  grand_total: number;
  shipping: { method: string | null; type: string | null; estimated_days: string | null; address: OrderAddress | null };
  payment: {
    method: string;
    method_code: string | null;
    status: string | null;
    status_label: string | null;
    expires_at: string | null;
    transfer: TransferInstructions | null;
    /** Pago con pasarela que se puede iniciar o reintentar */
    can_pay: boolean;
    failure_reason: string | null;
    card_last4: string | null;
    payment_type: string | null;
    installments: number | null;
    authorization_code: string | null;
    refunded_amount: number;
  };
  document_type: string | null;
  events: { type: string; label: string; at: string | null }[];
};

export type OrderSummary = {
  uuid: string;
  number: number;
  placed_at: string | null;
  status: string;
  status_label: string;
  payment_status_label: string;
  grand_total: number;
};

export type CustomerProfile = {
  first_name: string;
  last_name: string;
  email: string;
  phone: string | null;
  rut: string | null;
  marketing_opt_in: boolean;
  email_verified: boolean;
};

export type CustomerAddress = {
  id: number;
  label: string | null;
  recipient: string;
  phone: string;
  street: string;
  number: string;
  apartment: string | null;
  region_code: string | null;
  region: string | null;
  commune: string;
  reference: string | null;
  is_default: boolean;
};
