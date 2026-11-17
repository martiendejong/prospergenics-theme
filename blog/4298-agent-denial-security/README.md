# Blog draft: "Als een AI-agent 'nee' hoort - weigeringen als veiligheidsgrens"

Task 4298 | Run: run-31b36bb66946

## Status

DRAFT - requires explicit approval before publication.

## Files

- `artikel-nl.html` - Dutch article, ready for copy-paste into the WordPress block editor
- `architecture-visual.svg` - Architecture diagram (refusal -> event -> policy decision -> stop / approved alternative)

## Publicatie-instructies

1. Open `artikel-nl.html` and copy the `<article>...</article> content
2. In WordPress, create new post of type `pg_article` (or standard post if the type is not active)
3. Add title: "Als een AI-agent 'nee' hoort - weigeringen als veiligheidsgrens"
4. Paste as HTML block
5. Upload `architecture-visual.svg` as media and insert after section 4
6. Set excerpt (for social preview): "Waarom elke externe weigering - een HTTP 403, een CAPTCHA, een bot-block - een geregeld beveiligingsevenement moet worden in plaats van een cue voor een alternatieve route."
7. Set og:image to a 1200x630px banner (needs design)

## Review checklist

- [ ] Facts verified against cited sources
- [ ] No sensitive technical details disclosed
- [ ] Social share preview (og:title / og:description / og:image) configured
- [ ] English LinkedIn summary (separate task, after Dutch approval)
- [ ] Architecture visual reviewed for accuracy

## Related

- Lango #4295 - canonical ExternalDenialSecurityEvent implementation
- Lango #4296 - incident response SLOs
- Lango #4297 - red-team and release-gate tests
