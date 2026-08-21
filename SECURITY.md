# Security policy

## Reporting a vulnerability

Please do not open a public issue for a security problem. Email
**k.doguc@kisame-labs.com** instead, with enough detail to reproduce it, and you will get
an acknowledgement within a few days.

## Scope worth knowing about

Rich editor content is attacker-controlled by nature, so two areas get particular care and
are the most useful places to look:

- **What reaches the page.** Filament sanitises rich content through Symfony's
  `HtmlSanitizer` before rendering, and that sanitiser does not look inside CSS. Everything
  this package writes into a `style` attribute — font sizes, colours, line heights — is
  therefore whitelisted to a shape that cannot carry a second declaration.
- **What reaches a `<style>` element.** The font picker writes `@font-face` rules built
  from file names found on disk. Names and paths that could end the rule, or the element
  around it, are skipped rather than escaped.

Reports about either, or about anything else in the package, are appreciated.
