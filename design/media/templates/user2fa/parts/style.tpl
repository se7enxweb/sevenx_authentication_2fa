{* The parts of the two-factor pages the media design has no furniture for: the code field, the choice of method, the
   QR code with its key, the steps, and the social login buttons. Everything else is the design's own account
   furniture from stylesheets/account.css of sevenx_themes_media (full-page-header, acc-card, acc-notice, btn,
   form-control). Scoped to .tfa-m; the design has no dark mode, so neither do these pages.
   Included once by each page. Guide: extension/sevenx_authentication_2fa/doc/two-factor-pages.md *}
{literal}
<style>
.tfa-m { --tfa-ink: hsl(0, 0%, 0%); --tfa-text: #212529; --tfa-muted: hsl(0, 0%, 36%); --tfa-line: #dee2e6; --tfa-soft: #F8F9FC; --tfa-yellow: #FED82F; --tfa-bad: #b02a37; color: var(--tfa-text); }
.tfa-m *, .tfa-m *::before, .tfa-m *::after { box-sizing: border-box; }
.tfa-m [hidden] { display: none !important; }
.tfa-m .tfa-narrow { max-width: 34rem; margin-left: auto; margin-right: auto; }
.tfa-m .acc-card > h2 { overflow-wrap: anywhere; }
.tfa-m .form-label { display: block; margin: 0 0 .5rem; font-weight: 700; }
.tfa-m .tfa-code.form-control {
    max-width: 16rem; height: auto; padding: .7rem 1rem;
    font: 700 1.6rem/1.2 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; letter-spacing: .3em; text-align: center;
    font-variant-numeric: tabular-nums;
}
.tfa-m .tfa-code[aria-invalid="true"] { border-color: var(--tfa-bad); box-shadow: inset 0 0 0 1px var(--tfa-bad); }
.tfa-m .tfa-hint { margin: .5rem 0 0; font-size: .875rem; line-height: 1.6; color: var(--tfa-muted); }
.tfa-m .tfa-left { margin: .75rem 0 0; font-size: 1rem; color: var(--tfa-muted); }

/* the state at the top of the setup page */
.tfa-m .tfa-state { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem 1.5rem; }
.tfa-m .tfa-state p { margin: 0; }
.tfa-m .tfa-pill { display: inline-block; padding: .2rem .75rem; border-radius: 999px; background: var(--tfa-ink); color: #fff; font-size: .875rem; font-weight: 700; }
.tfa-m .tfa-pill.is-on { background: var(--tfa-yellow); color: var(--tfa-ink); }

/* the choice of method: big rows with a radio */
.tfa-m fieldset.tfa-methods { margin: 0; padding: 0; border: 0; min-width: 0; }
.tfa-m .tfa-methods legend { margin: 0 0 .75rem; padding: 0; font-size: 1.25rem; font-weight: 700; color: var(--tfa-ink); }
.tfa-m .tfa-method { display: flex; gap: .9rem; align-items: flex-start; margin: 0 0 .75rem; padding: 1rem 1.15rem; border: 2px solid var(--tfa-line); background: #fff; cursor: pointer; font-weight: 400; }
.tfa-m .tfa-method:has(input:checked) { border-color: var(--tfa-ink); box-shadow: inset .375rem 0 0 var(--tfa-yellow); }
.tfa-m .tfa-method input { flex: none; width: 1.25rem; height: 1.25rem; margin: .2rem 0 0; accent-color: var(--tfa-ink); }
.tfa-m .tfa-method:has(input:focus-visible) { box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--tfa-ink); }
.tfa-m .tfa-method strong { display: block; color: var(--tfa-ink); }
.tfa-m .tfa-method span span { display: block; margin-top: .15rem; font-size: 1rem; color: var(--tfa-muted); }

/* the authenticator enrolment */
.tfa-m ol.tfa-steps { margin: 0; padding: 0; list-style: none; counter-reset: tfastep; }
.tfa-m .tfa-step { position: relative; margin: 0 0 1.5rem; padding: 0 0 0 3rem; counter-increment: tfastep; }
.tfa-m .tfa-step::before { content: counter(tfastep); position: absolute; left: 0; top: 0; display: flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: 50%; background: var(--tfa-yellow); color: var(--tfa-ink); font-weight: 700; }
.tfa-m .tfa-step h3 { margin: .2rem 0 .35rem; padding: 0; border: 0; background: none; font-size: 1.125rem; font-weight: 700; color: var(--tfa-ink); text-transform: none; letter-spacing: normal; }
.tfa-m .tfa-step > p { margin: 0 0 .75rem; color: var(--tfa-muted); font-size: 1rem; }
.tfa-m .tfa-pair { display: flex; flex-wrap: wrap; gap: 1.25rem 2rem; align-items: flex-start; }
.tfa-m .tfa-qr { flex: none; margin: 0; padding: .75rem; background: #fff; border: 1px solid var(--tfa-line); line-height: 0; }
.tfa-m .tfa-qr img { display: block; width: 180px; height: 180px; image-rendering: pixelated; }
.tfa-m dl.tfa-key { flex: 1 1 14rem; min-width: 0; margin: 0; }
.tfa-m .tfa-key dt { font-size: .8125rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--tfa-muted); }
.tfa-m .tfa-key dd { margin: .15rem 0 .9rem; overflow-wrap: anywhere; }
.tfa-m .tfa-secret { display: inline-block; padding: .35rem .6rem; background: var(--tfa-soft); border: 1px dashed #adb5bd; font: 700 1.05rem/1.5 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; letter-spacing: .08em; color: var(--tfa-ink); user-select: all; overflow-wrap: anywhere; }

/* the removal, behind a confirmation that works without javascript */
.tfa-m details.tfa-confirm > summary { display: inline-block; cursor: pointer; font-weight: 700; color: var(--tfa-bad); }
.tfa-m details.tfa-confirm > summary::-webkit-details-marker { color: var(--tfa-bad); }
.tfa-m details.tfa-confirm[open] > summary { margin-bottom: 1rem; }

/* the social login buttons on the login and registration pages */
.exp-2fa-social { margin: 2rem 0 0; }
.exp-2fa-social .exp-2fa-or { display: flex; align-items: center; gap: .75rem; margin: 0 0 1rem; color: hsl(0, 0%, 36%); font-size: 1rem; }
.exp-2fa-social .exp-2fa-or::before, .exp-2fa-social .exp-2fa-or::after { content: ""; flex: 1 1 auto; height: 1px; background: #dee2e6; }
.exp-2fa-social ul { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 14rem), 1fr)); gap: .75rem; margin: 0; padding: 0; list-style: none; }
.full-form-content .exp-2fa-social a, .exp-2fa-social a { display: flex; align-items: center; justify-content: center; gap: .6rem; min-height: 3.25rem; padding: .6rem 1rem; border: 2px solid hsl(0, 0%, 0%); background: #fff; color: hsl(0, 0%, 0%); font-weight: 700; text-decoration: none; }
.full-form-content .exp-2fa-social a:hover, .exp-2fa-social a:hover { background: hsl(0, 0%, 0%); color: #fff; }
.exp-2fa-social a:focus-visible { outline: 2px transparent solid; box-shadow: 0 0 0 2px #fff, 0 0 0 4px hsl(0, 0%, 0%); }
.exp-2fa-social .exp-2fa-mark { display: inline-flex; align-items: center; justify-content: center; min-width: 1.6rem; height: 1.6rem; padding: 0 .25rem; background: #FED82F; color: hsl(0, 0%, 0%); font-size: .8125rem; font-weight: 700; }

@media (max-width: 575.98px) {
    .tfa-m .tfa-step { padding-left: 2.5rem; }
    .tfa-m .tfa-qr img { width: 160px; height: 160px; }
    .tfa-m .tfa-code.form-control { max-width: none; }
}
</style>
{/literal}
