## What

## Why

## Checklist

- [ ] Tests green (`ddev exec php artisan test`, `npx vitest run`)
- [ ] `ddev exec vendor/bin/pint --test` clean
- [ ] `npm run lint` clean
- [ ] No debug code (`dd()`, `console.log`)
- [ ] User guide (DE + EN) updated if this changes CMS behavior
- [ ] All CodeRabbit comments addressed
- [ ] PR title follows the commit format below (it becomes the commit message on
      squash-merge)

Commit format (Conventional Commits): `type(scope)?: description`. Types:
`feat`, `fix`, `docs`, `chore`, `refactor`, `test`, `ci`, `perf`. Breaking changes use
`type!:` plus a `BREAKING CHANGE:` footer.
