{{-- Minimal Mono — shared tokens + primitives. Included into each page's
     <style> (no @vite, keeps critical CSS inline). Page-specific rules stay
     local to each page. Single source of truth — edit here, not per page. --}}
:root{
    --ink:#0a0a0a; --muted:#6b7280; --faint:#9ca3af; --line:#e7e7e7; --alt:#fafafa;
    --success:#16a34a; --danger:#dc2626; --maxw:1040px;
}
*{box-sizing:border-box}
html{-webkit-text-size-adjust:100%}
body{margin:0;background:#fff;color:var(--ink);
    font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
    line-height:1.5;letter-spacing:-.011em;-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility}
a{color:inherit;text-decoration:none}
.wrap{width:100%;max-width:var(--maxw);margin:0 auto;padding:0 24px}
:focus-visible{outline:2px solid var(--ink);outline-offset:2px;border-radius:6px}

/* sticky header + brand mark */
.site-head{position:sticky;top:0;z-index:10;background:rgba(255,255,255,.82);backdrop-filter:blur(10px);border-bottom:1px solid var(--line)}
.site-head .wrap{height:64px;display:flex;align-items:center;justify-content:space-between;gap:16px}
.brand{display:inline-flex;align-items:center;gap:9px;font-weight:700;font-size:17px;color:var(--ink);letter-spacing:-.02em}
.brand .mark{width:26px;height:26px;border-radius:7px;background:var(--ink);display:inline-flex;align-items:center;justify-content:center;flex:none}

.linkarrow{display:inline-flex;align-items:center;gap:6px;color:var(--muted);font-size:14px;font-weight:600;transition:color .15s ease}
.linkarrow:hover{color:var(--ink)}
.linkarrow svg{transition:transform .15s ease}
.linkarrow:hover svg{transform:translateX(3px)}

.kicker{font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin:0}

/* buttons */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:11px 18px;border-radius:10px;font-size:14px;font-weight:600;border:1px solid var(--ink);cursor:pointer;transition:opacity .15s ease,border-color .15s ease,background .15s ease,color .15s ease}
.btn-solid{background:var(--ink);color:#fff}
.btn-solid:hover{opacity:.88}
.btn-line{background:#fff;color:var(--ink);border-color:var(--line)}
.btn-line:hover{border-color:var(--ink)}
.btn-white{background:#fff;color:var(--ink);border-color:#fff}
.btn-white:hover{opacity:.88}
.btn:disabled{cursor:not-allowed;background:#fff;color:var(--faint);border-color:var(--line);opacity:1}

/* cards + data-preview rows */
.card{border:1px solid var(--line);border-radius:14px;padding:26px;background:#fff;transition:border-color .15s ease}
.card:hover{border-color:var(--ink)}
.rows{margin:0}
.row{display:flex;align-items:baseline;justify-content:space-between;gap:16px;padding:14px 0;border-top:1px dashed var(--line)}
.row:first-of-type{border-top:0;padding-top:0}
.row .k{color:var(--muted);font-size:13px;font-weight:600;flex:none}
.row .v{color:var(--ink);font-weight:600;text-align:right;min-width:0;overflow-wrap:anywhere}

/* footer base */
.site-foot{border-top:1px solid var(--line)}
.site-foot p,.site-foot .copy{margin:0;color:var(--faint);font-size:13px;letter-spacing:.04em}
