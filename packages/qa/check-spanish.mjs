#!/usr/bin/env node
// Revisa que el texto visible de páginas HTML esté en español (regla del proyecto: nada de inglés en pantalla).
// Uso: node packages/qa/check-spanish.mjs pagina1.html pagina2.html ...
import { readFileSync } from "node:fs";

const { ingles, permitidas } = JSON.parse(readFileSync(new URL("./palabras-ingles.json", import.meta.url)));
const english = new Set(ingles.filter((w) => !permitidas.includes(w)));
let problems = 0;

for (const file of process.argv.slice(2)) {
  let html = readFileSync(file, "utf8").replace(/<(script|style|svg|template)\b[^>]*>[\s\S]*?<\/\1>/gi, " ");
  const texts = [...html.matchAll(/>([^<>]+)</g)].map((m) => m[1]);
  texts.push(...[...html.matchAll(/\s(?:placeholder|title|aria-label|alt)="([^"]*)"/gi)].map((m) => m[1]));
  for (const raw of texts) {
    const text = raw.replace(/&[a-z#0-9]+;/gi, " ").trim();
    if (!text) continue;
    const hit = text.toLowerCase().split(/[^\p{L}]+/u).find((w) => english.has(w));
    if (hit) {
      problems++;
      console.error(`${file}: "${text.slice(0, 80)}" (palabra "${hit}")`);
    }
  }
}
if (problems) {
  console.error(`\n${problems} textos en inglés visibles.`);
  process.exit(1);
}
console.log("Sin textos en inglés.");
