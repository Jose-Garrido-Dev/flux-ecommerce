/**
 * Genera las imágenes del catálogo de DEMOSTRACIÓN (estilo foto de estudio)
 * a partir de ilustraciones SVG, usando Chromium (Playwright).
 * Solo se usa al desarrollar Flux; el resultado se guarda en
 * backend/database/demo/images y lo copia `php artisan flux:demo`.
 *
 * Uso: node infra/demo-art/generate.js
 */
const { chromium } = require("playwright");
const fs = require("fs");
const path = require("path");

const OUT = path.resolve(__dirname, "../../backend/database/demo/images");
const W = 800, H = 1000;

const PALETTE = {
  negro: "#26262a", blanco: "#eeece6", arena: "#cdb48f", oliva: "#6e7447",
  azul: "#283a5c", terracota: "#b2593a",
};

// Producto → forma y colores (debe coincidir con DemoCatalogCommand)
const PRODUCTS = [
  ["polera-esencial", "tee", ["negro", "blanco", "arena"]],
  ["polera-cuello-redondo-pesada", "tee", ["blanco", "oliva"]],
  ["polera-manga-larga-rib", "longtee", ["negro", "terracota"]],
  ["polera-lino-verano", "tee", ["arena", "blanco"]],
  ["poleron-canguro", "hoodie", ["negro", "oliva", "azul"]],
  ["poleron-cierre-completo", "zip", ["arena", "azul"]],
  ["sweater-merino-liviano", "sweater", ["arena", "negro"]],
  ["pantalon-chino-recto", "pants", ["arena", "oliva", "azul"]],
  ["pantalon-lino-cordon", "pants", ["blanco", "arena"]],
  ["jogger-felpa", "jogger", ["negro", "oliva"]],
  ["bolso-tote-lona", "tote", ["arena", "negro"]],
  ["mochila-urbana", "backpack", ["negro", "terracota"]],
  ["rinonera-compacta", "fanny", ["negro", "oliva"]],
  ["gorro-lana-doblado", "beanie", ["negro", "terracota", "oliva", "arena"]],
  ["jockey-seis-paneles", "cap", ["azul", "arena"]],
  ["taza-esmaltada-350-ml", "mug", ["blanco", "terracota", "oliva"]],
  ["set-de-2-tazas-espresso", "espresso", ["negro"]],
  ["cojin-lino-lavado", "cushion", ["arena", "terracota", "oliva"]],
  ["manta-tejida", "blanket", ["arena"]],
  ["individuales-lino-4", "placemats", ["blanco", "arena"]],
];

const CATEGORIES = {
  ropa: [["hoodie", "oliva"], ["tee", "arena"], ["pants", "azul"]],
  accesorios: [["backpack", "terracota"], ["beanie", "negro"], ["cap", "arena"]],
  hogar: [["mug", "terracota"], ["cushion", "oliva"], ["blanket", "arena"]],
};

// ---------- utilidades de color ----------
const hex = (h) => h.match(/\w\w/g).map((x) => parseInt(x, 16));
const toHex = (rgb) => "#" + rgb.map((v) => Math.max(0, Math.min(255, Math.round(v))).toString(16).padStart(2, "0")).join("");
const mix = (a, b, t) => toHex(hex(a).map((v, i) => v + (hex(b)[i] - v) * t));
const shade = (c, t) => (t < 0 ? mix(c, "#000000", -t) : mix(c, "#ffffff", t));
const lum = (c) => { const [r, g, b] = hex(c); return (0.299 * r + 0.587 * g + 0.114 * b) / 255; };

// Fondos de estudio: tonos cálidos que contrastan con el producto
const BACKDROPS = ["#efe7dc", "#e6e2d6", "#ede3e0", "#dfe3dc", "#e9e4da", "#e3dfe6"];
function backdrop(color, i) {
  const b = BACKDROPS[i % BACKDROPS.length];
  return lum(color) > 0.8 ? mix(b, "#cfc6b8", 0.35) : b;
}

// ---------- formas (viewBox 400x500, producto centrado) ----------
const SHAPES = {
  tee: (c) => `
    <path d="M150 70 Q200 104 250 70 L318 96 L372 168 L322 200 L300 176 L300 438 Q200 452 100 438 L100 176 L78 200 L28 168 L82 96 Z" fill="url(#body)"/>
    <path d="M150 70 Q200 104 250 70 Q200 122 150 70Z" fill="${shade(c, -0.35)}"/>
    <path d="M100 176 L100 438 Q140 444 160 445 L150 180Z" fill="#000" opacity=".07"/>
    <path d="M78 200 L28 168 L42 150 L90 186Z" fill="${shade(c, -0.15)}" opacity=".6"/>`,
  longtee: (c) => `
    <path d="M152 70 Q200 102 248 70 L318 96 L352 330 L314 340 L300 190 L300 438 Q200 452 100 438 L100 190 L86 340 L48 330 L82 96 Z" fill="url(#body)"/>
    <path d="M152 70 Q200 102 248 70 Q200 118 152 70Z" fill="${shade(c, -0.35)}"/>
    ${[0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12].map((i) => `<line x1="${112 + i * 14.6}" y1="110" x2="${112 + i * 14.6}" y2="440" stroke="#000" stroke-opacity=".05" stroke-width="3"/>`).join("")}
    <rect x="46" y="318" width="40" height="22" rx="4" fill="${shade(c, -0.12)}" transform="rotate(4 66 329)"/>
    <rect x="314" y="318" width="40" height="22" rx="4" fill="${shade(c, -0.12)}" transform="rotate(-4 334 329)"/>`,
  hoodie: (c) => `
    <path d="M140 92 Q200 20 260 92 L250 120 Q200 80 150 120Z" fill="${shade(c, -0.2)}"/>
    <path d="M140 92 L80 112 L46 340 L86 352 L104 196 L104 430 Q200 446 296 430 L296 196 L314 352 L354 340 L320 112 L260 92 Q200 150 140 92Z" fill="url(#body)"/>
    <path d="M150 104 Q200 160 250 104 Q200 130 150 104Z" fill="${shade(c, -0.4)}"/>
    <path d="M140 300 L260 300 L282 380 L118 380Z" fill="${shade(c, -0.1)}" stroke="${shade(c, -0.25)}" stroke-width="2"/>
    <line x1="186" y1="132" x2="182" y2="214" stroke="${shade(c, 0.5)}" stroke-width="4" stroke-linecap="round"/>
    <line x1="214" y1="132" x2="218" y2="214" stroke="${shade(c, 0.5)}" stroke-width="4" stroke-linecap="round"/>
    <rect x="102" y="410" width="196" height="24" rx="6" fill="${shade(c, -0.15)}"/>
    <rect x="44" y="332" width="44" height="24" rx="6" fill="${shade(c, -0.15)}" transform="rotate(4 66 344)"/>
    <rect x="312" y="332" width="44" height="24" rx="6" fill="${shade(c, -0.15)}" transform="rotate(-4 334 344)"/>`,
  zip: (c) => `
    <path d="M140 92 Q200 22 260 92 L250 120 Q200 84 150 120Z" fill="${shade(c, -0.2)}"/>
    <path d="M140 92 L80 112 L46 340 L86 352 L104 196 L104 430 Q200 446 296 430 L296 196 L314 352 L354 340 L320 112 L260 92 Q200 150 140 92Z" fill="url(#body)"/>
    <path d="M150 104 Q200 160 250 104 Q200 130 150 104Z" fill="${shade(c, -0.4)}"/>
    <line x1="200" y1="128" x2="200" y2="438" stroke="${shade(c, -0.35)}" stroke-width="5"/>
    <line x1="200" y1="128" x2="200" y2="438" stroke="#d9d4c8" stroke-width="1.5" stroke-dasharray="3 3"/>
    <rect x="195" y="150" width="10" height="22" rx="3" fill="#cfc8b8"/>
    <path d="M126 300 L166 300 L172 370 L124 372Z M274 300 L234 300 L228 370 L276 372Z" fill="#000" opacity=".08"/>
    <rect x="102" y="410" width="196" height="24" rx="6" fill="${shade(c, -0.15)}"/>`,
  sweater: (c) => `
    <path d="M154 78 Q200 104 246 78 L320 102 L354 336 L314 346 L298 196 L298 430 Q200 446 102 430 L102 196 L86 346 L46 336 L80 102 Z" fill="url(#body)"/>
    <path d="M154 78 Q200 104 246 78 Q200 124 154 78Z" fill="${shade(c, -0.3)}" stroke="${shade(c, -0.18)}" stroke-width="8"/>
    ${Array.from({ length: 22 }, (_, i) => `<line x1="${106 + i * 9}" y1="412" x2="${106 + i * 9}" y2="436" stroke="#000" stroke-opacity=".12" stroke-width="2"/>`).join("")}
    ${Array.from({ length: 8 }, (_, i) => `<path d="M${118 + i * 22} 150 q11 14 0 28 q-11 14 0 28 q11 14 0 28 q-11 14 0 28 q11 14 0 28 q-11 14 0 28 q11 14 0 28 q-11 14 0 28 q11 14 0 28" fill="none" stroke="#000" stroke-opacity=".06" stroke-width="3"/>`).join("")}
    <rect x="44" y="326" width="44" height="24" rx="5" fill="${shade(c, -0.15)}" transform="rotate(4 66 338)"/>
    <rect x="312" y="326" width="44" height="24" rx="5" fill="${shade(c, -0.15)}" transform="rotate(-4 334 338)"/>`,
  pants: (c) => `
    <path d="M118 56 H282 L304 452 H214 L200 176 L186 452 H96 Z" fill="url(#body)"/>
    <rect x="116" y="50" width="168" height="26" rx="4" fill="${shade(c, -0.12)}"/>
    ${[132, 168, 232, 268].map((x) => `<rect x="${x - 4}" y="48" width="8" height="30" rx="2" fill="${shade(c, -0.25)}"/>`).join("")}
    <line x1="200" y1="76" x2="200" y2="176" stroke="${shade(c, -0.3)}" stroke-width="2.5"/>
    <path d="M130 84 Q150 120 170 84" fill="none" stroke="${shade(c, -0.25)}" stroke-width="2.5"/>
    <path d="M270 84 Q250 120 230 84" fill="none" stroke="${shade(c, -0.25)}" stroke-width="2.5"/>
    <line x1="150" y1="180" x2="140" y2="450" stroke="#000" stroke-opacity=".08" stroke-width="3"/>
    <line x1="250" y1="180" x2="260" y2="450" stroke="#000" stroke-opacity=".08" stroke-width="3"/>`,
  jogger: (c) => `
    <path d="M116 60 H284 L296 410 Q290 440 262 440 H226 L200 180 L174 440 H138 Q110 440 104 410 Z" fill="url(#body)"/>
    <rect x="112" y="50" width="176" height="34" rx="12" fill="${shade(c, -0.12)}"/>
    <path d="M190 84 q-8 40 -20 50 M210 84 q8 40 20 50" fill="none" stroke="${shade(c, 0.6)}" stroke-width="4" stroke-linecap="round"/>
    <rect x="136" y="420" width="92" height="22" rx="8" fill="${shade(c, -0.18)}" transform="translate(-28 0)"/>
    <rect x="200" y="420" width="92" height="22" rx="8" fill="${shade(c, -0.18)}" transform="translate(28 0)"/>`,
  tote: (c) => `
    <path d="M150 150 Q150 50 200 50 Q250 50 250 150" fill="none" stroke="${shade(c, -0.3)}" stroke-width="16"/>
    <path d="M90 160 H310 L330 450 H70 Z" fill="url(#body)"/>
    <path d="M150 160 V200 M250 160 V200" stroke="${shade(c, -0.3)}" stroke-width="16"/>
    <rect x="160" y="290" width="80" height="44" rx="3" fill="${shade(c, 0.35)}" opacity=".7"/>
    <path d="M90 160 H310 L312 182 H88Z" fill="#000" opacity=".08"/>`,
  backpack: (c) => `
    <path d="M160 80 Q160 40 200 40 Q240 40 240 80" fill="none" stroke="${shade(c, -0.35)}" stroke-width="12"/>
    <rect x="96" y="70" width="208" height="380" rx="70" fill="url(#body)"/>
    <rect x="130" y="270" width="140" height="140" rx="36" fill="${shade(c, -0.1)}" stroke="${shade(c, -0.3)}" stroke-width="3"/>
    <path d="M140 290 H260" stroke="${shade(c, -0.4)}" stroke-width="4"/>
    <rect x="248" y="282" width="22" height="10" rx="3" fill="#c9c2b2"/>
    <path d="M120 150 Q200 120 280 150" fill="none" stroke="${shade(c, -0.3)}" stroke-width="4"/>
    <rect x="186" y="136" width="28" height="12" rx="3" fill="#c9c2b2"/>`,
  fanny: (c) => `
    <path d="M30 250 Q200 330 370 250" fill="none" stroke="${shade(c, -0.3)}" stroke-width="18"/>
    <path d="M90 230 Q90 170 200 170 Q310 170 310 230 L300 320 Q200 350 100 320 Z" fill="url(#body)"/>
    <path d="M100 222 Q200 200 300 222" fill="none" stroke="${shade(c, -0.45)}" stroke-width="5"/>
    <rect x="280" y="214" width="20" height="12" rx="3" fill="#c9c2b2"/>
    <rect x="186" y="250" width="28" height="16" rx="4" fill="${shade(c, 0.3)}" opacity=".6"/>`,
  beanie: (c) => `
    <path d="M100 330 Q100 120 200 112 Q300 120 300 330 Z" fill="url(#body)"/>
    ${Array.from({ length: 14 }, (_, i) => `<line x1="${110 + i * 14}" y1="${200 - Math.sin((i / 13) * Math.PI) * 60}" x2="${110 + i * 14}" y2="330" stroke="#000" stroke-opacity=".08" stroke-width="5"/>`).join("")}
    <rect x="88" y="300" width="224" height="96" rx="20" fill="${shade(c, -0.08)}"/>
    ${Array.from({ length: 16 }, (_, i) => `<line x1="${98 + i * 13.5}" y1="306" x2="${98 + i * 13.5}" y2="390" stroke="#000" stroke-opacity=".1" stroke-width="5"/>`).join("")}
    <rect x="176" y="334" width="48" height="28" rx="3" fill="${shade(c, 0.55)}"/>`,
  cap: (c) => `
    <path d="M100 300 Q96 150 200 140 Q304 150 300 300 Z" fill="url(#body)"/>
    <path d="M200 140 L200 300 M150 150 Q140 220 130 300 M250 150 Q260 220 270 300" stroke="#000" stroke-opacity=".12" stroke-width="3" fill="none"/>
    <circle cx="200" cy="142" r="8" fill="${shade(c, -0.2)}"/>
    <path d="M96 296 Q200 330 330 300 Q380 300 372 330 Q300 370 140 346 Q96 336 96 296Z" fill="${shade(c, -0.12)}"/>`,
  mug: (c) => `
    <path d="M292 200 Q356 200 356 270 Q356 340 288 340" fill="none" stroke="${shade(c, -0.08)}" stroke-width="26"/>
    <path d="M110 150 H300 V380 Q300 420 260 420 H150 Q110 420 110 380 Z" fill="url(#body)"/>
    <ellipse cx="205" cy="150" rx="95" ry="18" fill="${shade(c, -0.25)}"/>
    <ellipse cx="205" cy="152" rx="84" ry="12" fill="#3b2a20"/>
    <rect x="110" y="380" width="190" height="40" rx="20" fill="#d8d1c3" opacity=".45"/>`,
  espresso: (c) => `
    <ellipse cx="135" cy="380" rx="95" ry="20" fill="${shade(c, 0.1)}"/>
    <ellipse cx="275" cy="320" rx="95" ry="20" fill="${shade(c, 0.1)}"/>
    <g transform="translate(-70 70)"><path d="M262 220 Q306 220 306 262 Q306 300 262 300" fill="none" stroke="${shade(c, -0.05)}" stroke-width="16"/>
    <path d="M150 210 H270 V320 Q270 360 230 360 H190 Q150 360 150 320 Z" fill="url(#body)"/><ellipse cx="210" cy="210" rx="60" ry="12" fill="#2a1d16"/></g>
    <g transform="translate(70 10)"><path d="M262 220 Q306 220 306 262 Q306 300 262 300" fill="none" stroke="${shade(c, -0.05)}" stroke-width="16"/>
    <path d="M150 210 H270 V320 Q270 360 230 360 H190 Q150 360 150 320 Z" fill="url(#body)"/><ellipse cx="210" cy="210" rx="60" ry="12" fill="#2a1d16"/></g>`,
  cushion: (c) => `
    <path d="M70 130 Q200 100 330 130 Q360 260 330 390 Q200 420 70 390 Q40 260 70 130Z" fill="url(#body)"/>
    <path d="M70 130 Q200 160 330 130 M70 390 Q200 360 330 390 M70 130 Q100 260 70 390 M330 130 Q300 260 330 390" fill="none" stroke="#000" stroke-opacity=".08" stroke-width="4"/>
    <circle cx="200" cy="260" r="8" fill="${shade(c, -0.25)}"/>
    <path d="M60 128 l20 2 M320 128 l20 2 M60 392 l20 -2 M320 392 l20 -2" stroke="${shade(c, -0.2)}" stroke-width="6" stroke-linecap="round"/>`,
  blanket: (c) => `
    ${[0, 1, 2, 3].map((i) => `<rect x="${70 + i * 2}" y="${330 - i * 58}" width="260" height="64" rx="18" fill="${shade(c, -0.05 * i)}" />
    <path d="M${78 + i * 2} ${360 - i * 58} H${322 + i * 2}" stroke="#000" stroke-opacity=".08" stroke-width="3"/>`).join("")}
    ${Array.from({ length: 22 }, (_, i) => `<line x1="${82 + i * 11.5}" y1="394" x2="${82 + i * 11.5}" y2="${414 + (i % 3) * 3}" stroke="${shade(c, -0.15)}" stroke-width="3" stroke-linecap="round"/>`).join("")}
    ${Array.from({ length: 12 }, (_, i) => `<path d="M${90 + i * 20} 160 l10 14 l10 -14" fill="none" stroke="#000" stroke-opacity=".07" stroke-width="3"/>`).join("")}`,
  placemats: (c) => `
    <rect x="70" y="190" width="270" height="190" rx="10" fill="${shade(c, -0.08)}" transform="rotate(-7 205 285)"/>
    <rect x="66" y="180" width="270" height="190" rx="10" fill="${shade(c, -0.04)}" transform="rotate(4 201 275)"/>
    <rect x="64" y="170" width="270" height="190" rx="10" fill="url(#body)"/>
    <rect x="80" y="186" width="238" height="158" rx="6" fill="none" stroke="#000" stroke-opacity=".08" stroke-width="2" stroke-dasharray="6 5"/>`,
};

function productSvg(shape, color, { scale = 1, dx = 0, dy = 0, bg, w = 400, h = 500, seed = 0 }) {
  const top = shade(color, 0.16), bottom = shade(color, -0.18);
  const bgTop = shade(bg, 0.25), bgBot = shade(bg, -0.06);
  return `
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${w} ${h}" width="${W}" height="${(W * h) / w}">
    <defs>
      <radialGradient id="bg" cx="50%" cy="38%" r="75%"><stop offset="0" stop-color="${bgTop}"/><stop offset="1" stop-color="${bgBot}"/></radialGradient>
      <linearGradient id="floor" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="${shade(bg, -0.03)}"/><stop offset="1" stop-color="${shade(bg, -0.1)}"/></linearGradient>
      <linearGradient id="body" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="${top}"/><stop offset=".55" stop-color="${color}"/><stop offset="1" stop-color="${bottom}"/></linearGradient>
      <linearGradient id="sheen" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#fff" stop-opacity=".22"/><stop offset=".45" stop-color="#fff" stop-opacity="0"/></linearGradient>
      <filter id="blur" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="14"/></filter>
      <filter id="soft" x="-20%" y="-20%" width="140%" height="140%"><feDropShadow dx="0" dy="10" stdDeviation="12" flood-color="#000" flood-opacity=".14"/></filter>
    </defs>
    <rect width="${w}" height="${h}" fill="url(#bg)"/>
    <rect y="${h * 0.78}" width="${w}" height="${h * 0.22}" fill="url(#floor)"/>
    <circle cx="${w * (0.15 + (seed % 3) * 0.3)}" cy="${h * 0.18}" r="${w * 0.35}" fill="#fff" opacity=".18" filter="url(#blur)"/>
    <g transform="translate(${dx} ${dy}) translate(200 250) scale(${scale}) translate(-200 -250)">
      <ellipse cx="200" cy="462" rx="150" ry="16" fill="#000" opacity=".22" filter="url(#blur)"/>
      <g filter="url(#soft)">${SHAPES[shape](color)}</g>
    </g>
  </svg>`;
}

function categorySvg(items, bg) {
  const w = 600, h = 450;
  const slots = [[-185, 40, 0.6], [185, 40, 0.6], [0, 20, 0.74]];
  return `
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${w} ${h}" width="${W * 1.5}" height="${(W * 1.5 * h) / w}">
    <defs>
      <radialGradient id="bg" cx="50%" cy="35%" r="80%"><stop offset="0" stop-color="${shade(bg, 0.25)}"/><stop offset="1" stop-color="${shade(bg, -0.08)}"/></radialGradient>
      <filter id="blur" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="12"/></filter>
      <filter id="soft" x="-20%" y="-20%" width="140%" height="140%"><feDropShadow dx="0" dy="8" stdDeviation="10" flood-color="#000" flood-opacity=".16"/></filter>
    </defs>
    <rect width="${w}" height="${h}" fill="url(#bg)"/>
    ${items.map(([shape, colorName], i) => {
      const c = PALETTE[colorName];
      const [dx, dy, s] = slots[i];
      return `<defs><linearGradient id="body${i}" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="${shade(c, 0.16)}"/><stop offset=".55" stop-color="${c}"/><stop offset="1" stop-color="${shade(c, -0.18)}"/></linearGradient></defs>
      <g transform="translate(${100 + dx} ${-30 + dy}) translate(200 250) scale(${s}) translate(-200 -250)">
        <ellipse cx="200" cy="462" rx="150" ry="16" fill="#000" opacity=".25" filter="url(#blur)"/>
        <g filter="url(#soft)">${SHAPES[shape](c).replaceAll("url(#body)", `url(#body${i})`)}</g>
      </g>`;
    }).join("")}
  </svg>`;
}

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch();
  const page = await browser.newPage({ deviceScaleFactor: 1 });
  const render = async (svg, file, width, height) => {
    await page.setViewportSize({ width, height });
    await page.setContent(`<html><body style="margin:0">${svg}</body></html>`);
    await page.screenshot({ path: path.join(OUT, file), type: "jpeg", quality: 80, clip: { x: 0, y: 0, width, height } });
  };

  let n = 0;
  for (const [slug, shape, colors] of PRODUCTS) {
    for (const [ci, colorName] of colors.entries()) {
      const c = PALETTE[colorName];
      const bg = backdrop(c, n + ci);
      await render(productSvg(shape, c, { bg, seed: n }), `demo-${slug}-${colorName}-a.jpg`, W, H);
      // Segunda toma: acercamiento con otro fondo (se ve al pasar el cursor)
      await render(productSvg(shape, c, { bg: backdrop(c, n + ci + 3), scale: 1.65, dx: -40, dy: 90, seed: n + 1 }), `demo-${slug}-${colorName}-b.jpg`, W, H);
    }
    n++;
  }
  const catBg = { ropa: "#e7ddcf", accesorios: "#dfe2db", hogar: "#ecdcd3" };
  for (const [slug, items] of Object.entries(CATEGORIES)) {
    await render(categorySvg(items, catBg[slug]), `cat-${slug}.jpg`, W * 1.5, W * 1.5 * 0.75);
  }
  await browser.close();
  console.log("Imágenes generadas en", OUT);
})();
