# tech4time-website

Official Website of M/s. Tech4TIME — https://tech4time.bd

A purely static, front-end website. No build step, no framework — hand-written semantic HTML5, CSS3 and vanilla JavaScript.

## Project structure

```
.
├── index.html                  Home page
├── about/                      About us
├── services/                   Services
├── company-profile/            Company profile
├── career/                     Careers & job openings
├── resource-certifications/    HRaaS certified resources
├── branding-and-advertisement/ Branding assets & guidelines
├── contact/                    Contact forms
├── privacy-policy/             Privacy policy
├── api.php                     Form submission proxy (backend-owned, do not modify)
├── assets/
│   ├── css/styles.css          Global stylesheet (design tokens, components, responsive)
│   ├── js/main.js              Global script (nav, reveal, counters, forms/captcha)
│   ├── fonts/                  Self-hosted fonts (Inter, Space Grotesk)
│   ├── images/                 Organized by purpose (logos, clients, tech, about, flags, og)
│   └── video/                  Page hero background videos (poster-friendly)
├── robots.txt
└── sitemap.xml
```

## Contact forms

All forms submit to `/api.php/form_container/submit` and load their captcha from `/api.php/form_container/captcha`. This mechanism (field names, form IDs, captcha hash flow) is provided by the existing backend and must not be altered. `api.php` itself is copied verbatim from the production site and must not be modified.

## Branches

- `main` — production deployments only.
- `dev` — all development work happens here; changes are committed and pushed to `dev`.

## Local preview

Any static file server works for previewing pages. For the contact forms to function, PHP is required (the forms proxy through `api.php`):

```bash
php -S localhost:8080
```

## Analytics

Google Analytics 4 (G-VF0QVG9QCC) with the built-in opt-out cookie mechanism is included on every page.
