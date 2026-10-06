{* The look of the two-factor pages in every design without its own (the administration designs admin4, admin3,
   admin2, admin and the Admin UI, and the public designs other than media): the second step at sign-in (user2fa/verify),
   setting up and changing it (user2fa/setup), the social login's answers (user2fa/callback), the social login
   buttons of the login page and the 2FA field of a user object. Included once by each of them.

   In the visual language of the redesigned admin pages (sections, URLs, cronjobs): everything is scoped to
   .exp-2fa, takes admin4's tokens where they exist (--a4-*) and has values of its own for the older designs
   (admin, admin2, admin3) and the Admin UI, which reach this file through their design fallback. No shared
   stylesheet is changed.

   Two frames:
   - .exp-2fa (the signed in page, in the admin's main card): figures, panels, fields, buttons;
   - .exp-2fa.is-signin (verify, callback and a first setup, drawn by loginpagelayout.tpl before anyone is signed
     in): the sign-in form's own look, light and dark (html[data-a4-theme="dark"]).
   Guide: extension/sevenx_authentication_2fa/doc/two-factor-pages.md *}
{literal}
<style>
.exp-2fa {
    --tf-ink: var(--a4-ink, #1f2430);
    --tf-muted: var(--a4-muted, #5d6573);
    --tf-line: var(--a4-line, #e3e6eb);
    --tf-soft: var(--a4-soft, #f6f7f9);
    --tf-card: #fff;
    --tf-field: #fff;
    --tf-field-line: #8f96a3;
    --tf-accent: #c2410c;          /* white text on it is 5.2:1 */
    --tf-accent-hover: #9a3412;
    --tf-ring: rgba(194, 65, 12, 0.45);
    --tf-ok: #166534;   --tf-ok-bg: #e7f5ea;   --tf-ok-line: #b7dfc1;
    --tf-warn: #8a4b00; --tf-warn-bg: #fff3df; --tf-warn-line: #f3d19c;
    --tf-bad: #b91c1c;  --tf-bad-bg: #fdecec;  --tf-bad-line: #f1b4b4;
    --tf-info: #1e4fa8; --tf-info-bg: #e8effd; --tf-info-line: #bcd0f5;
    --tf-radius: 12px;
    --tf-mono: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    color: var(--tf-ink);
    font-size: 14px;
    line-height: 1.5;
}
.exp-2fa *, .exp-2fa *::before, .exp-2fa *::after { box-sizing: border-box; }
.exp-2fa [hidden] { display: none !important; }
.exp-2fa .box-content { padding-bottom: 20px; }
.exp-2fa h1.context-title { margin: 0; overflow-wrap: anywhere; }
.exp-2fa h2.exp-h2 { margin: 0 0 4px; padding: 0; border: 0; background: none; font-size: 16px; font-weight: 650; color: var(--tf-ink); }
.exp-2fa h3 { margin: 0; padding: 0; border: 0; background: none; font-size: 15px; font-weight: 650; color: var(--tf-ink); }
.exp-2fa p { margin: 0; }
.exp-2fa p + p { margin-top: 8px; }
.exp-2fa code { font-family: var(--tf-mono); font-size: 12.5px; overflow-wrap: anywhere; }
.exp-2fa a { color: var(--tf-accent-hover); }
.exp-2fa a:hover { color: var(--tf-ink); }
.exp-2fa :focus-visible { outline: 3px solid var(--tf-ring); outline-offset: 2px; }
.exp-2fa .exp-sr { position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0; }
.exp-2fa .exp-muted, .exp-2fa .exp-meta { color: var(--tf-muted); }
.exp-2fa .exp-meta { font-size: 13px; }
.exp-2fa .exp-title-row { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 12px; }
.exp-2fa .exp-intro { margin: 8px 0 18px; max-width: 78ch; color: var(--tf-muted); }
.exp-2fa .exp-section { margin: 0 0 22px; }

/* Messages */
.exp-2fa .exp-feedback { margin: 0 0 14px; padding: 10px 14px; border: 1px solid; border-left-width: 4px; border-radius: 10px; }
.exp-2fa .exp-feedback.is-ok { border-color: var(--tf-ok-line); border-left-color: var(--tf-ok); background: var(--tf-ok-bg); color: var(--tf-ok); }
.exp-2fa .exp-feedback.is-bad { border-color: var(--tf-bad-line); border-left-color: var(--tf-bad); background: var(--tf-bad-bg); color: var(--tf-bad); }
.exp-2fa .exp-feedback.is-warn { border-color: var(--tf-warn-line); border-left-color: var(--tf-warn); background: var(--tf-warn-bg); color: var(--tf-warn); }
.exp-2fa .exp-feedback.is-info { border-color: var(--tf-info-line); border-left-color: var(--tf-info); background: var(--tf-info-bg); color: var(--tf-info); }
.exp-2fa .exp-feedback strong { color: inherit; }
.exp-2fa .exp-feedback p + p { margin-top: 4px; }

/* Figures: the state at a glance */
.exp-2fa .exp-figures { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 150px), 1fr)); gap: 10px; margin: 0 0 20px; padding: 0; list-style: none; }
.exp-2fa .exp-figure { display: flex; flex-direction: column; gap: 2px; margin: 0; padding: 12px 14px; border: 1px solid var(--tf-line); border-radius: var(--tf-radius); background: var(--tf-card); min-width: 0; }
.exp-2fa .exp-figure strong { font-size: 18px; line-height: 1.25; font-weight: 700; color: var(--tf-ink); overflow-wrap: anywhere; }
.exp-2fa .exp-figure span { font-size: 12.5px; color: var(--tf-muted); }
.exp-2fa .exp-figure.is-ok strong { color: var(--tf-ok); }
.exp-2fa .exp-figure.is-attention strong { color: var(--tf-warn); }

/* Panels and the method choice */
.exp-2fa .exp-panel { margin: 0 0 18px; padding: 16px 18px; border: 1px solid var(--tf-line); border-radius: var(--tf-radius); background: var(--tf-card); }
.exp-2fa .exp-panel.is-danger { border-color: var(--tf-bad-line); }
.exp-2fa .exp-panel-head { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 4px 12px; margin: 0 0 10px; }
.exp-2fa fieldset.exp-methods { display: block; min-width: 0; margin: 0; padding: 0; border: 0; background: none; box-shadow: none; border-radius: 0; }
.exp-2fa fieldset.exp-methods > legend { display: block; float: none; width: auto; margin: 0 0 10px; padding: 0; border: 0; background: none; font-size: 16px; font-weight: 650; color: var(--tf-ink); }
.exp-2fa .exp-method-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 230px), 1fr)); gap: 10px; }
.exp-2fa .exp-method { position: relative; display: flex; gap: 10px; align-items: flex-start; margin: 0; padding: 12px 14px; border: 1px solid #c9ced6; border-radius: 10px; background: var(--tf-card); cursor: pointer; font-weight: 400; }
.exp-2fa .exp-method, .exp-2fa .exp-method * { white-space: normal; overflow-wrap: anywhere; }
.exp-2fa .exp-method > span { flex: 1 1 auto; min-width: 0; }
.exp-2fa .exp-method input { flex: 0 0 auto; width: 18px; height: 18px; margin: 2px 0 0; accent-color: var(--tf-accent); }
.exp-2fa .exp-method strong { display: block; font-weight: 650; color: var(--tf-ink); }
.exp-2fa .exp-method span span { display: block; font-size: 13px; color: var(--tf-muted); }
.exp-2fa .exp-method:has(input:checked) { border-color: var(--tf-accent); box-shadow: inset 0 0 0 1px var(--tf-accent); }
.exp-2fa .exp-method:has(input:focus-visible) { outline: 3px solid var(--tf-ring); outline-offset: 2px; }

/* The authenticator enrolment: steps with the QR code and the key */
.exp-2fa .exp-steps { display: grid; grid-template-columns: minmax(0, 1fr); gap: 14px; margin: 0; padding: 0; list-style: none; counter-reset: tfstep; }
.exp-2fa .exp-step { position: relative; margin: 0; padding: 0 0 0 40px; counter-increment: tfstep; }
.exp-2fa .exp-step::before { content: counter(tfstep); position: absolute; left: 0; top: 0; display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: var(--tf-soft); border: 1px solid var(--tf-line); color: var(--tf-ink); font-weight: 700; font-size: 13px; }
.exp-2fa .exp-step h3 { margin: 3px 0 4px; }
.exp-2fa .exp-pair { display: flex; flex-wrap: wrap; gap: 14px 22px; align-items: flex-start; margin-top: 8px; }
.exp-2fa .exp-qr { flex: 0 0 auto; margin: 0; padding: 10px; border: 1px solid var(--tf-line); border-radius: 12px; background: #fff; line-height: 0; }
.exp-2fa .exp-qr img { display: block; width: 180px; height: 180px; image-rendering: pixelated; }
.exp-2fa .exp-key { flex: 1 1 220px; min-width: 0; }
.exp-2fa .exp-key dt { margin: 0; font-size: 12px; font-weight: 650; text-transform: uppercase; letter-spacing: .04em; color: var(--tf-muted); }
.exp-2fa .exp-key dd { margin: 2px 0 10px; overflow-wrap: anywhere; }
.exp-2fa .exp-secret { display: inline-block; padding: 6px 10px; border: 1px dashed #c9ced6; border-radius: 8px; background: var(--tf-soft); font: 600 15px/1.5 var(--tf-mono); letter-spacing: .06em; color: var(--tf-ink); user-select: all; overflow-wrap: anywhere; }

/* Fields and buttons */
.exp-2fa .exp-field { display: flex; flex-direction: column; gap: 5px; min-width: 0; margin: 0; }
.exp-2fa .exp-field > label { padding: 0; font-size: 13px; font-weight: 650; color: var(--tf-ink); }
.exp-2fa .exp-help { font-size: 13px; color: var(--tf-muted); max-width: 72ch; }
.exp-2fa .exp-field-error { font-size: 13px; font-weight: 650; color: var(--tf-bad); }
.exp-2fa input.exp-code {
    width: 100%; max-width: 15ch; min-height: 44px; margin: 0; padding: 6px 12px; border: 1px solid var(--tf-field-line); border-radius: 10px;
    background: var(--tf-field); color: var(--tf-ink); box-shadow: none;
    font: 600 20px/1.2 var(--tf-mono); letter-spacing: .18em; font-variant-numeric: tabular-nums;
}
.exp-2fa input.exp-code:focus { border-color: var(--tf-accent); outline: 3px solid var(--tf-ring); outline-offset: 0; }
.exp-2fa input.exp-code[aria-invalid="true"] { border-color: var(--tf-bad); box-shadow: inset 0 0 0 1px var(--tf-bad); }
.exp-2fa .exp-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-height: 36px; max-width: 100%; margin: 0;
    padding: 6px 14px; border: 1px solid #c9ced6; border-radius: 9px; background: #fff; color: var(--tf-ink);
    font: 600 13.5px/1.2 inherit; font-family: inherit; cursor: pointer; white-space: nowrap; text-decoration: none;
}
.exp-2fa a.exp-btn { color: var(--tf-ink); }
.exp-2fa .exp-btn:hover:not([disabled]) { border-color: var(--tf-accent); color: var(--tf-accent-hover); }
.exp-2fa .exp-btn[disabled] { opacity: .55; cursor: not-allowed; }
.exp-2fa .exp-btn-primary, .exp-2fa a.exp-btn-primary { border-color: var(--tf-accent); background: var(--tf-accent); color: #fff; }
.exp-2fa .exp-btn-primary:hover:not([disabled]) { border-color: var(--tf-accent-hover); background: var(--tf-accent-hover); color: #fff; }
.exp-2fa .exp-btn-outline-danger { border-color: var(--tf-bad); color: var(--tf-bad); }
.exp-2fa .exp-btn-outline-danger:hover:not([disabled]) { background: var(--tf-bad); border-color: var(--tf-bad); color: #fff; }
.exp-2fa .exp-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.exp-2fa .exp-bottombar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 12px; margin: 18px 0 0; padding: 12px 14px; border: 1px solid var(--tf-line); border-radius: var(--tf-radius); background: var(--tf-soft); }
.exp-2fa .exp-bottombar .exp-meta { flex: 1 1 260px; }
.exp-2fa .exp-confirm-row { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px 14px; margin-top: 12px; }

/* A confirmation that opens in place: works without javascript */
.exp-2fa details.exp-confirm { margin: 0; padding: 0; border: 1px solid var(--tf-bad-line); border-radius: var(--tf-radius); background: var(--tf-card); }
.exp-2fa details.exp-confirm > summary { display: inline-flex; align-items: center; gap: 6px; min-height: 36px; padding: 6px 14px; color: var(--tf-bad); font-weight: 650; cursor: pointer; list-style: none; }
.exp-2fa details.exp-confirm > summary::-webkit-details-marker { display: none; }
.exp-2fa details.exp-confirm > summary::before { content: "\25B8"; }
.exp-2fa details.exp-confirm[open] > summary::before { content: "\25BE"; }
.exp-2fa details.exp-confirm > div { padding: 0 14px 14px; }

/* The social login buttons (login page) */
.exp-2fa-social { margin: 18px 0 0; }
.exp-2fa-social .exp-2fa-or { display: flex; align-items: center; gap: 10px; margin: 0 0 12px; font-size: 13px; color: var(--a4-muted, #5d6573); }
.exp-2fa-social .exp-2fa-or::before, .exp-2fa-social .exp-2fa-or::after { content: ""; flex: 1 1 auto; height: 1px; background: var(--a4-line, #e3e6eb); }
.exp-2fa-social ul { display: grid; grid-template-columns: minmax(0, 1fr); gap: 8px; margin: 0; padding: 0; list-style: none; }
.exp-2fa-social a { display: flex; align-items: center; justify-content: center; gap: 8px; min-height: 44px; padding: 8px 14px; border: 1px solid #c9ced6; border-radius: 10px; background: #fff; color: #1f2430; font-weight: 600; text-decoration: none; }
.exp-2fa-social a:hover { border-color: #c2410c; color: #9a3412; }
.exp-2fa-social a:focus-visible { outline: 3px solid rgba(194, 65, 12, 0.45); outline-offset: 2px; }
.exp-2fa-social .exp-2fa-mark { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 6px; background: #1f2430; color: #fff; font: 700 12px/1 system-ui, sans-serif; }
html[data-a4-theme="dark"] body.a4-login-page .exp-2fa-social a { background: #1c2230; color: #e8ebf0; border-color: #364055; }
html[data-a4-theme="dark"] body.a4-login-page .exp-2fa-social a:hover { border-color: #fb923c; color: #fdba74; }
html[data-a4-theme="dark"] body.a4-login-page .exp-2fa-social .exp-2fa-mark { background: #e8ebf0; color: #141922; }

/* ---- The sign-in frame: before the user is signed in, in loginpagelayout.tpl ---------------------------------- */
.exp-2fa.is-signin { font-size: 15px; }
.exp-2fa.is-signin .exp-2fa-icon { display: flex; align-items: center; justify-content: center; width: 52px; height: 52px; margin: 0 auto 14px; border-radius: 14px; background: rgba(194, 65, 12, 0.10); color: var(--tf-accent); }
.exp-2fa.is-signin h1.exp-2fa-title { margin: 0 0 6px; padding: 0; border: 0; background: none; font-size: 26px; line-height: 1.2; font-weight: 700; letter-spacing: -.01em; text-align: center; color: var(--tf-ink); }
.exp-2fa.is-signin .exp-2fa-sub { margin: 0 0 22px; text-align: center; color: var(--tf-muted); font-size: 14px; }
.exp-2fa.is-signin .exp-2fa-sub strong { color: var(--tf-ink); }
.exp-2fa.is-signin form { display: flex; flex-direction: column; margin: 0; }
.exp-2fa.is-signin .exp-field > label { font-size: 14px; font-weight: 600; }
.exp-2fa.is-signin input.exp-code { max-width: none; height: 52px; text-align: center; font-size: 24px; letter-spacing: .32em; }
.exp-2fa.is-signin .exp-btn-primary.is-wide { width: 100%; height: 48px; margin: 18px 0 0; border: 0; border-radius: 10px; font: 600 16px/1 inherit; font-family: inherit; box-shadow: 0 6px 16px rgba(194, 65, 12, 0.22); }
.exp-2fa.is-signin .exp-btn.is-wide { width: 100%; min-height: 44px; margin-top: 10px; border-radius: 10px; }
.exp-2fa.is-signin .exp-links { margin: 18px 0 0; text-align: center; font-size: 14px; color: var(--tf-muted); }
.exp-2fa.is-signin .exp-links a { color: var(--tf-accent-hover); font-weight: 600; text-decoration: none; }
.exp-2fa.is-signin .exp-links a:hover { text-decoration: underline; }
.exp-2fa.is-signin .exp-links p + p { margin-top: 6px; }
.exp-2fa.is-signin .exp-feedback { font-size: 14px; }
.exp-2fa.is-signin .exp-attempts { margin: 8px 0 0; font-size: 13px; color: var(--tf-muted); }
.exp-2fa.is-signin .exp-panel { padding: 14px; }
.exp-2fa.is-signin .exp-pair { justify-content: center; }
.exp-2fa.is-signin .exp-key { flex-basis: 100%; text-align: center; }
.exp-2fa.is-signin .exp-steps { margin-bottom: 4px; }
.exp-2fa.is-signin .exp-bottombar { margin: 18px 0 0; padding: 0; border: 0; background: none; }
.exp-2fa.is-signin .exp-bottombar .exp-actions { width: 100%; }
.exp-2fa.is-signin .exp-bottombar .exp-btn-primary.is-wide { margin: 0; }
/* a first setup is longer than the screen: admin4 sets its dark sign-in background on body, so the page below
   the first screen would show the light html background */
html[data-a4-theme="dark"]:has(.exp-2fa.is-signin) { background: #141922; }
/* the old admin designs' login page has no box of its own: give the frame one */
body:not(.a4-login-page) .exp-2fa.is-signin { max-width: 420px; margin: 24px auto; padding: 24px 22px; border: 1px solid var(--tf-line); border-radius: 16px; background: #fff; }

/* admin4's dark sign-in page: dark surfaces, light text (its own tokens: --a4-ink, --a4-muted, --a4-line) */
html[data-a4-theme="dark"] body.a4-login-page .exp-2fa {
    --tf-card: #1c2230; --tf-soft: #222a39; --tf-field: #1c2230; --tf-field-line: #4a5568;
    --tf-accent: #fb923c; --tf-accent-hover: #fdba74; --tf-ring: rgba(251, 146, 60, 0.45);
    --tf-ok: #86efac; --tf-ok-bg: rgba(22, 101, 52, 0.25); --tf-ok-line: rgba(134, 239, 172, 0.35);
    --tf-warn: #fcd34d; --tf-warn-bg: rgba(138, 75, 0, 0.28); --tf-warn-line: rgba(252, 211, 77, 0.35);
    --tf-bad: #fca5a5; --tf-bad-bg: rgba(185, 28, 28, 0.22); --tf-bad-line: rgba(252, 165, 165, 0.35);
    --tf-info: #93c5fd; --tf-info-bg: rgba(30, 79, 168, 0.25); --tf-info-line: rgba(147, 197, 253, 0.35);
}
html[data-a4-theme="dark"] body.a4-login-page .exp-2fa .exp-btn { background: #1c2230; color: #e8ebf0; border-color: #364055; }
html[data-a4-theme="dark"] body.a4-login-page .exp-2fa .exp-btn-primary { background: #c2410c; border-color: #c2410c; color: #fff; }
html[data-a4-theme="dark"] body.a4-login-page .exp-2fa .exp-btn-primary:hover:not([disabled]) { background: #9a3412; border-color: #9a3412; color: #fff; }
html[data-a4-theme="dark"] body.a4-login-page .exp-2fa .exp-method { border-color: #364055; }
html[data-a4-theme="dark"] body.a4-login-page .exp-2fa .exp-secret { border-color: #4a5568; }
html[data-a4-theme="dark"] body.a4-login-page .exp-2fa .exp-2fa-icon { background: rgba(251, 146, 60, 0.14); }

@media (max-width: 600px) {
    .exp-2fa .exp-panel { padding: 14px 12px; }
    .exp-2fa .exp-step { padding-left: 36px; }
    .exp-2fa .exp-bottombar .exp-actions { width: 100%; }
    .exp-2fa .exp-bottombar .exp-btn { flex: 1 1 auto; white-space: normal; }
    .exp-2fa .exp-btn { white-space: normal; text-align: center; }
    .exp-2fa .exp-qr img { width: 160px; height: 160px; }
    body:not(.a4-login-page) .exp-2fa.is-signin { margin: 12px; padding: 18px 14px; }
}
</style>
{/literal}
