# Wraps the game source (src/hra.html) into the full web/index.html: head, meta and share tags.
# python3 tools/build.py src/hra.html web/index.html
import sys, re
src, dst = sys.argv[1], sys.argv[2]
page = open(src, encoding='utf-8').read()
title = re.search(r'<title>.*?</title>', page, re.S).group(0)
links = ''.join(re.findall(r'<link [^>]*>\n?', page))
style = re.search(r'<style>.*?</style>', page, re.S).group(0)
body = page
for part in (title, style):
    body = body.replace(part, '', 1)
body = re.sub(r'<link [^>]*>\n?', '', body).strip()
head = f'''<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
{title}
<meta name="description" content="Vtipné hádanky pro děti: koukni na otázku, máš jeden tip, za trefu jsou body. Série, hodnosti a dvířka s odpovědí.">
<meta name="theme-color" content="#FBF8EF">
<link rel="icon" href="favicon.svg" type="image/svg+xml">
<meta property="og:type" content="website">
<meta property="og:url" content="https://avisproc.cz/">
<meta property="og:title" content="A víš proč?">
<meta property="og:description" content="Vtipné hádanky pro děti. Jeden tip, body za trefu.">
<meta property="og:image" content="https://avisproc.cz/og.jpg">
<meta property="og:locale" content="cs_CZ">
<meta name="twitter:card" content="summary_large_image">
<style>:root{{padding-top:env(safe-area-inset-top);padding-bottom:env(safe-area-inset-bottom)}}body{{margin:0}}img{{max-width:100%}}[hidden]{{display:none!important}}</style>
{links}{style}
</head>
<body>
{body}
</body>
</html>
'''
open(dst, 'w', encoding='utf-8').write(head)
print('written', len(head), 'bytes')
