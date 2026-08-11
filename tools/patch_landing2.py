from pathlib import Path

p = Path('index.html')
s = p.read_text(encoding='utf-8')

anchor = '    <link rel="stylesheet" href="static/fontawesome-free-7.1.0-web/css/all.min.css">\n'
if 'static/landing-layouts.css' not in s:
    if anchor not in s:
        raise SystemExit('fontawesome stylesheet anchor missing')
    s = s.replace(anchor, anchor + '    <link rel="stylesheet" href="static/landing-layouts.css">\n', 1)

anchor = '    <script>\n        ((window, document) => {'
if 'static/landing-layouts.js' not in s:
    if anchor not in s:
        raise SystemExit('main script anchor missing')
    s = s.replace(anchor, '    <script src="static/landing-layouts.js"></script>\n\n' + anchor, 1)

anchor = '                        // 2. 渲染所有内容\n                        Renderer.renderAll();\n'
if 'AppDownLandingLayouts?.apply' not in s:
    if anchor not in s:
        raise SystemExit('renderer anchor missing')
    s = s.replace(anchor, anchor + "                        window.AppDownLandingLayouts?.apply(data?.site?.landing_template || 'classic', { site: data?.site || {}, apps: APP_CONFIG });\n", 1)

anchor = """                            } else if (SITE_DATA) {
                                Renderer.renderFeatures(SITE_DATA.features);
                            }
                        });
"""
if 'AppDownLandingLayouts?.syncActive' not in s:
    if anchor not in s:
        raise SystemExit('tab change anchor missing')
    s = s.replace(anchor, """                            } else if (SITE_DATA) {
                                Renderer.renderFeatures(SITE_DATA.features);
                            }
                            window.AppDownLandingLayouts?.syncActive(newTab);
                        });
""", 1)

p.write_text(s, encoding='utf-8')
print('index.html patched for Landing Templates 2.0')
