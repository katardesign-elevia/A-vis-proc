# A víš proč?

Vtipné hádanky pro děti (10–13 let) — **https://avisproc.cz**
Design and Concept by Lea Kantorová © 2026

## Co je kde

| | |
|---|---|
| `src/hra.html` | celá hra: vzhled, logika a seznam hádanek (`POOL`) |
| `web/` | to, co leží na hostingu ve složce `avisproc.cz/web`: obrázky, žebříček (`api/zebricek.php`), favicon, `og.jpg` |
| `tools/build.py` | složí `web/index.html` ze `src/hra.html` |
| `tools/test-riddles.js` | kontrola před nasazením (obrázky, klíče odpovědí, chyby ve skriptu) |
| `tools/mockapi.py` | lokální server se zkušebním žebříčkem |

Jedna hra = sada 10 hádanek z `POOL`. „Nové karty“ vybírají ty, které hráč nejdéle neviděl.

## Nasazení

Každý push do `main` spustí GitHub Actions: sestaví stránku, zkontroluje hádanky a přes FTPS nahraje jen změněné soubory.
Soubory se živými daty (`api/zebricek-data.php`, `api/zebricek-limity.php`) a heslo správce (`api/spravce-heslo.php`)
existují jen na hostingu — nasazení je nikdy nenahraje ani nesmaže.

Jednorázové nastavení v repozitáři: **Settings → Secrets and variables → Actions**
- *Variables*: `FTP_SERVER`, `FTP_USERNAME`, `FTP_DIR` (např. `avisproc.cz/web/`)
- *Secret*: `FTP_PASSWORD`

## Nová hádanka

1. Obrázek 1536×1024 (otázka vlevo, odpověď vpravo, bílý pruh uprostřed) jako `.webp` do `web/obrazky/`.
2. Záznam do `POOL` v `src/hra.html`: `id`, `src`, `split` (sloupce bílého pruhu), `otazka`, `odpoved`,
   `klic` (co se počítá jako správně), `skoro` („Skoro!“), `vazne` (pravda, ale ne vtip), `emoji`.
3. Anglickou verzi do `en: {...}` téhož záznamu (`otazka`, `odpoved`, `klic`, `skoro`, `vazne`).
4. `node tools/test-riddles.js src/hra.html web`

## Čeština / English

Přepínač CZ | EN je na úvodní stránce (odkaz `?lang=en` otevře rovnou angličtinu). Texty hry jsou v `TXT` v `src/hra.html`.
Anglický režim hraje jen hádanky, které už mají anglický obrázek: obrázek do `web/obrazky/en/<id>.webp`
a do `en: {...}` přidat `src` a `split`. Dokud žádný není, anglická verze ukazuje „English riddles coming soon“.
Každý jazyk si pamatuje svou rozehranou hru zvlášť; žebříček je společný.

## Lokálně

```bash
python3 tools/build.py src/hra.html web/index.html
python3 tools/mockapi.py web 8767
```

Pak http://localhost:8767 (žebříček jen se zkušebními daty).

## Správce žebříčku

Mazání záznamů na https://avisproc.cz/?spravce zapne soubor `api/spravce-heslo.php` na hostingu s jediným řádkem
`<?php return 'heslo-aspon-10-znaku';`. Bez něj je mazání vypnuté.
