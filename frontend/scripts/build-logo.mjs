/**
 * Génère les assets de marque à partir du logo source.
 *
 *   node scripts/build-logo.mjs
 *
 * Source : `backend/docs/LOGO/image.png` — un PNG RGB **sans canal alpha**.
 * Son fond blanc est opaque : posé tel quel dans l'interface, il formerait un
 * carré blanc sur le thème sombre. Ce script le détoure, en découpe le
 * monogramme et le verrouillage complet, et en sort les tailles employées par
 * l'app. Aucune dépendance : décodage et encodage PNG à la main via `zlib`.
 *
 * Sorties (toutes en PNG 32 bits, fond transparent) :
 *   src/assets/logo-mark.png        monogramme, bandeau et écrans
 *   src/assets/logo-full.png        verrouillage complet, fonds clairs
 *   src/assets/logo-full-dark.png   idem, encre éclaircie pour fonds sombres
 *   public/favicon.png              onglet
 *   public/apple-touch-icon.png     écran d'accueil iOS
 */
import fs from 'node:fs'
import path from 'node:path'
import zlib from 'node:zlib'
import { fileURLToPath } from 'node:url'

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const SRC = path.resolve(ROOT, '../backend/docs/LOGO/image.png')

/* ------------------------------------------------------------------ PNG */

const CRC_TABLE = (() => {
  const t = new Uint32Array(256)
  for (let n = 0; n < 256; n++) {
    let c = n
    for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1
    t[n] = c >>> 0
  }
  return t
})()

function crc32(buf) {
  let crc = 0xffffffff
  for (let i = 0; i < buf.length; i++) crc = CRC_TABLE[(crc ^ buf[i]) & 0xff] ^ (crc >>> 8)
  return (crc ^ 0xffffffff) >>> 0
}

function paeth(a, b, c) {
  const p = a + b - c
  const pa = Math.abs(p - a), pb = Math.abs(p - b), pc = Math.abs(p - c)
  return pa <= pb && pa <= pc ? a : pb <= pc ? b : c
}

/** Décode un PNG 8 bits RGB ou RGBA. @returns {{width,height,data}} data = RGBA */
function decode(buf) {
  const width = buf.readUInt32BE(16), height = buf.readUInt32BE(20)
  const bitDepth = buf[24], colorType = buf[25]
  if (bitDepth !== 8 || (colorType !== 2 && colorType !== 6)) {
    throw new Error(`PNG non géré : bitDepth=${bitDepth} colorType=${colorType}`)
  }
  const ch = colorType === 2 ? 3 : 4
  const idat = []
  for (let off = 8; off < buf.length; ) {
    const len = buf.readUInt32BE(off)
    const type = buf.toString('ascii', off + 4, off + 8)
    if (type === 'IDAT') idat.push(buf.subarray(off + 8, off + 8 + len))
    if (type === 'IEND') break
    off += 12 + len
  }
  const raw = zlib.inflateSync(Buffer.concat(idat))
  const stride = width * ch
  const out = Buffer.alloc(width * height * 4)
  const prev = Buffer.alloc(stride)
  const cur = Buffer.alloc(stride)
  let p = 0
  for (let y = 0; y < height; y++) {
    const filter = raw[p++]
    raw.copy(cur, 0, p, p + stride)
    p += stride
    for (let i = 0; i < stride; i++) {
      const a = i >= ch ? cur[i - ch] : 0
      const b = prev[i]
      const c = i >= ch ? prev[i - ch] : 0
      let v = cur[i]
      if (filter === 1) v += a
      else if (filter === 2) v += b
      else if (filter === 3) v += (a + b) >> 1
      else if (filter === 4) v += paeth(a, b, c)
      cur[i] = v & 0xff
    }
    for (let x = 0; x < width; x++) {
      const s = x * ch, d = (y * width + x) * 4
      out[d] = cur[s]; out[d + 1] = cur[s + 1]; out[d + 2] = cur[s + 2]
      out[d + 3] = ch === 4 ? cur[s + 3] : 255
    }
    cur.copy(prev)
  }
  return { width, height, data: out }
}

function chunk(type, data) {
  const len = Buffer.alloc(4); len.writeUInt32BE(data.length)
  const td = Buffer.concat([Buffer.from(type, 'ascii'), data])
  const crc = Buffer.alloc(4); crc.writeUInt32BE(crc32(td))
  return Buffer.concat([len, td, crc])
}

function encode({ width, height, data }) {
  const stride = width * 4
  const raw = Buffer.alloc((stride + 1) * height)
  for (let y = 0; y < height; y++) {
    raw[y * (stride + 1)] = 0
    data.copy(raw, y * (stride + 1) + 1, y * stride, (y + 1) * stride)
  }
  const ihdr = Buffer.alloc(13)
  ihdr.writeUInt32BE(width, 0); ihdr.writeUInt32BE(height, 4)
  ihdr[8] = 8; ihdr[9] = 6
  return Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    chunk('IHDR', ihdr),
    chunk('IDAT', zlib.deflateSync(raw, { level: 9 })),
    chunk('IEND', Buffer.alloc(0)),
  ])
}

/* ------------------------------------------------------- Détourage du fond */

const src = decode(fs.readFileSync(SRC))
const { width: W, height: H, data: SRGB } = src

/*
 * Remplissage par diffusion depuis les bords, et non seuil global : un seuil
 * percerait les blancs *intérieurs* de l'illustration — pointillés de la route,
 * contour du scooter, point du repère.
 */
const BG_MIN = 235
const isBg = new Uint8Array(W * H)
const near = (i) => Math.min(SRGB[i * 4], SRGB[i * 4 + 1], SRGB[i * 4 + 2]) >= BG_MIN
const stack = []
const seed = (i) => { if (!isBg[i] && near(i)) { isBg[i] = 1; stack.push(i) } }
for (let x = 0; x < W; x++) { seed(x); seed((H - 1) * W + x) }
for (let y = 0; y < H; y++) { seed(y * W); seed(y * W + W - 1) }
while (stack.length) {
  const i = stack.pop(), x = i % W, y = (i / W) | 0
  if (x > 0) seed(i - 1)
  if (x < W - 1) seed(i + 1)
  if (y > 0) seed(i - W)
  if (y < H - 1) seed(i + W)
}

/* Alpha binaire : le lissage des bords naîtra du sous-échantillonnage. */
const RGBA = Buffer.alloc(W * H * 4)
for (let i = 0; i < W * H; i++) {
  RGBA[i * 4] = SRGB[i * 4]; RGBA[i * 4 + 1] = SRGB[i * 4 + 1]; RGBA[i * 4 + 2] = SRGB[i * 4 + 2]
  RGBA[i * 4 + 3] = isBg[i] ? 0 : 255
}

/* ------------------------------------------------------------- Découpage */

/** Boîte englobante du contenu opaque, entre deux lignes. */
function bbox(yFrom, yTo) {
  let x0 = W, x1 = -1, y0 = H, y1 = -1
  for (let y = yFrom; y <= yTo; y++) {
    for (let x = 0; x < W; x++) {
      if (RGBA[(y * W + x) * 4 + 3] === 0) continue
      if (x < x0) x0 = x
      if (x > x1) x1 = x
      if (y < y0) y0 = y
      if (y > y1) y1 = y
    }
  }
  return { x0, y0, w: x1 - x0 + 1, h: y1 - y0 + 1 }
}

/** Les bandes de lignes entièrement vides séparent monogramme, mot-marque et baseline. */
function emptyBands() {
  const bands = []
  let start = null
  for (let y = 0; y < H; y++) {
    let filled = false
    for (let x = 0; x < W && !filled; x++) if (RGBA[(y * W + x) * 4 + 3]) filled = true
    if (!filled) { if (start === null) start = y }
    else { if (start !== null && y - start > 4) bands.push([start, y - 1]); start = null }
  }
  if (start !== null) bands.push([start, H - 1])
  return bands
}

/** Réduction par moyenne de surface, en alpha prémultiplié. */
function resample(box, outW, outH) {
  // Sans prémultiplication, le blanc des pixels transparents déteindrait sur
  // les bords lors de la moyenne et cernerait le logo d'un liseré clair.
  const { x0, y0, w, h } = box
  const out = Buffer.alloc(outW * outH * 4)
  for (let oy = 0; oy < outH; oy++) {
    const sy0 = y0 + (oy * h) / outH, sy1 = y0 + ((oy + 1) * h) / outH
    for (let ox = 0; ox < outW; ox++) {
      const sx0 = x0 + (ox * w) / outW, sx1 = x0 + ((ox + 1) * w) / outW
      let r = 0, g = 0, b = 0, a = 0, n = 0
      for (let sy = Math.floor(sy0); sy < Math.ceil(sy1); sy++) {
        for (let sx = Math.floor(sx0); sx < Math.ceil(sx1); sx++) {
          n++
          if (sy < 0 || sy >= H || sx < 0 || sx >= W) continue
          const i = (sy * W + sx) * 4, al = RGBA[i + 3] / 255
          r += RGBA[i] * al; g += RGBA[i + 1] * al; b += RGBA[i + 2] * al; a += al
        }
      }
      const d = (oy * outW + ox) * 4
      if (a > 0) {
        out[d] = Math.round(r / a); out[d + 1] = Math.round(g / a); out[d + 2] = Math.round(b / a)
        out[d + 3] = Math.round((a / n) * 255)
      }
    }
  }
  return { width: outW, height: outH, data: out }
}

/**
 * Inscrit la boîte dans un carré transparent, centrée.
 * Le carré est fabriqué ici et non en élargissant la boîte source : élargir
 * revient à recadrer plus large dans l'original, ce qui fait entrer ce qui se
 * trouve autour — le haut des lettres du mot-marque.
 */
function square(box, side, padRatio = 0) {
  const inner = Math.round(side * (1 - padRatio))
  const scale = Math.min(inner / box.w, inner / box.h)
  const w = Math.max(1, Math.round(box.w * scale)), h = Math.max(1, Math.round(box.h * scale))
  const small = resample(box, w, h)
  const out = Buffer.alloc(side * side * 4)
  const ox = ((side - w) / 2) | 0, oy = ((side - h) / 2) | 0
  for (let y = 0; y < h; y++) {
    small.data.copy(out, ((y + oy) * side + ox) * 4, y * w * 4, (y + 1) * w * 4)
  }
  return { width: side, height: side, data: out }
}

/**
 * Éclaircit l'encre sombre du logo pour les fonds sombres.
 *
 * Le mot-marque « ALLO » et la baseline sont d'un vert quasi noir : sur le fond
 * `--bg` (#0b0d0c) leur rapport de contraste tombe à 1.14, ils disparaissent.
 * Seule cette encre est remappée vers `--fg`, le vert de la marque est laissé
 * intact — c'est lui qui porte l'identité.
 *
 * Le traitement s'arrête au bloc de texte (`fromRow`). L'illustration garde son
 * encre d'origine : le scooter y est cerné d'un liseré blanc, qui suffit à le
 * détacher d'un fond sombre. L'éclaircir le ferait au contraire fondre dans ce
 * liseré et lui ferait perdre sa silhouette.
 */
function lightenInk({ width, height, data }, fromRow, ink = [0xf3, 0xf6, 0xf4]) {
  const out = Buffer.from(data)
  for (let i = width * fromRow; i < width * height; i++) {
    const d = i * 4
    if (!out[d + 3]) continue
    const r = out[d], g = out[d + 1], b = out[d + 2]
    /*
     * Deux critères mesurés sur le logo :
     *  - l'encre est sombre (luminance ≤ 60 pour ses aplats) ;
     *  - elle est quasi neutre, `g - b` ne dépasse pas 16, là où le vert de
     *    marque est à 96-128. C'est ce second critère qui protège le dégradé
     *    du « A », dont les tons les plus foncés restent franchement verts.
     */
    const lum = 0.2126 * r + 0.7152 * g + 0.0722 * b
    if (lum > 115 || g - b > 30) continue
    // Aplats remappés à fond ; seuls les pixels de transition sont interpolés,
    // sinon les bords du texte se dédoublent en un liseré sombre.
    const t = lum <= 55 ? 1 : (115 - lum) / 60
    out[d] = Math.round(r + (ink[0] - r) * t)
    out[d + 1] = Math.round(g + (ink[1] - g) * t)
    out[d + 2] = Math.round(b + (ink[2] - b) * t)
  }
  return { width, height, data: out }
}

/**
 * Aplatit l'image sur un fond opaque, coins arrondis.
 *
 * Nécessaire pour les favicons : le contre-poinçon du « A » est ouvert vers le
 * bas, donc le blanc sur lequel repose l'illustration communique avec le fond
 * et disparaît au détourage. Sur un onglet sombre, le scooter — encre foncée —
 * se retrouve alors sur du sombre et devient illisible. Le navigateur
 * n'exposant aucune feuille de style pour l'icône, la tuile est cuite dans le
 * fichier. Dans l'app, le bandeau fait la même chose en CSS, où le thème garde
 * la main.
 */
function flatten({ width, height, data }, bg = [0xff, 0xff, 0xff], radiusRatio = 0) {
  const out = Buffer.alloc(width * height * 4)
  const r = radiusRatio * Math.min(width, height)
  for (let y = 0; y < height; y++) {
    for (let x = 0; x < width; x++) {
      const d = (y * width + x) * 4
      // Distance au coin arrondi le plus proche : hors du rayon, on laisse vide.
      const cx = Math.min(x, width - 1 - x), cy = Math.min(y, height - 1 - y)
      if (r > 0 && cx < r && cy < r && Math.hypot(r - cx, r - cy) > r) continue
      const a = data[d + 3] / 255
      out[d] = Math.round(data[d] * a + bg[0] * (1 - a))
      out[d + 1] = Math.round(data[d + 1] * a + bg[1] * (1 - a))
      out[d + 2] = Math.round(data[d + 2] * a + bg[2] * (1 - a))
      out[d + 3] = 255
    }
  }
  return { width, height, data: out }
}

/* ---------------------------------------------------------------- Sortie */

const bands = emptyBands()
console.log('bandes vides :', bands.map((b) => b.join('–')).join('  '))
// Le monogramme occupe tout ce qui précède la première bande intérieure ;
// le verrouillage complet va jusqu'à la dernière ligne de contenu.
const top = bands[0] ? bands[0][1] + 1 : 0
const bottom = bands.at(-1) ? bands.at(-1)[0] - 1 : H - 1
const markEnd = bands.length > 2 ? bands[1][0] - 1 : bottom

const mark = bbox(top, markEnd)
const full = bbox(top, bottom)
console.log('monogramme', mark, '\nverrouillage', full)

/* 360 px : le verrouillage s'affiche autour de 140 px de large sur les écrans
   d'authentification, ce qui couvre 2,5× de densité. Au-delà le PNG grossit
   vite — un dégradé se comprime mal — pour un gain invisible. */
const fullW = 360
const fullH = Math.round((full.h / full.w) * fullW)
const fullImg = resample(full, fullW, fullH)

// Première ligne du mot-marque, ramenée dans le repère de l'image réduite.
const wordmarkTop = bands[1] ? bands[1][1] + 1 : full.y0 + full.h
const inkFrom = Math.round(((wordmarkTop - full.y0) / full.h) * fullH)

const jobs = [
  // 128 px couvre un affichage à 32 px jusqu'à 4× de densité.
  ['src/assets/logo-mark.png', square(mark, 128)],
  ['src/assets/logo-full.png', fullImg],
  ['src/assets/logo-full-dark.png', lightenInk(fullImg, inkFrom)],
  // Marge interne : le « A » ne doit pas toucher le bord de l'onglet.
  ['public/favicon.png', flatten(square(mark, 128, 0.12), [0xff, 0xff, 0xff], 0.18)],
  // iOS applique son propre masque : l'icône reste un carré plein.
  ['public/apple-touch-icon.png', flatten(square(mark, 180, 0.16))],
]

for (const [rel, img] of jobs) {
  const buf = encode(img)
  fs.writeFileSync(path.join(ROOT, rel), buf)
  console.log(`${rel.padEnd(32)} ${img.width}×${img.height}  ${(buf.length / 1024).toFixed(1)} Ko`)
}
