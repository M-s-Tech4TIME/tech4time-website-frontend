<?php
/**
 * Tech4TIME — the cloud infrastructure service page.
 *
 * PHP, and not HTML, because its content is edited at admin.tech4time.bd and
 * arrives here as content/services.json. Rendered on the server, on this
 * request, from a file on this disk: no fetch, no framework, and the page
 * works with JavaScript switched off. See ADR 0003 and ADR 0010.
 *
 * ONE DOCUMENT, SEVEN PAGES. The services index and all six detail pages are
 * rows of one file, because a seventh service has to be addable from the
 * editor and CONTRACT_DOCUMENTS is a constant in code. The bands below are
 * drawn by lib/services.php, which all six share; what is left in this file is
 * this page's own head and the shared chrome around it.
 *
 * The header, footer, dock and hero circuit are shared markup and stay
 * literal; tools/check_shared_markup.py holds them byte-identical to
 * tools/templates/. The scroll-reveal markers are emitted by the renderer,
 * because tools/apply_reveals.py reports and skips any page that builds part
 * of itself with a loop.
 *
 * A service that has been hidden, or removed from the document, answers 404
 * here rather than rendering an empty page.
 */

declare(strict_types=1);

require __DIR__ . '/../../../lib/services.php';

$data    = services_load();
$service = services_by_slug($data, 'cloud-infrastructure');

if ($service === null || $service['status'] === 'hidden') {
    http_response_code(404);
    require __DIR__ . '/../../../404.html';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= h($service['meta']['title']) ?></title>
<meta name="description" content="<?= h($service['meta']['description']) ?>">
<link rel="canonical" href="https://tech4time.bd/pages/services/cloud-infrastructure/">

<!-- Crawling. Large image previews and full snippets are allowed so rich
     results can use the branded share card. -->
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">

<!-- Security. NOTE: X-Frame-Options and X-Content-Type-Options are ignored in
     <meta> by every browser — they are set for real in .htaccess, which is the
     authoritative source. Referrer-Policy and CSP genuinely do work here, and
     are kept as defence in depth in case the host strips response headers. -->
<meta name="referrer" content="strict-origin-when-cross-origin">
<meta http-equiv="Content-Security-Policy" content="default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'">

<!-- Open Graph -->
<meta property="og:type" content="website">
<meta property="og:locale" content="en_US">
<meta property="og:site_name" content="Tech4TIME">
<meta property="og:title" content="<?= h($service['meta']['share_title']) ?>">
<meta property="og:description" content="<?= h($service['meta']['description']) ?>">
<meta property="og:url" content="https://tech4time.bd/pages/services/cloud-infrastructure/">
<meta property="og:image" content="https://tech4time.bd/assets/images/og/tech4time-og.png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Tech4TIME — Orchestrating Technology with Time">

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= h($service['meta']['share_title']) ?>">
<meta name="twitter:description" content="<?= h($service['meta']['description']) ?>">
<meta name="twitter:image" content="https://tech4time.bd/assets/images/og/tech4time-og.png">
<meta name="twitter:image:alt" content="Tech4TIME — Orchestrating Technology with Time">

<!-- Icons -->
<link rel="icon" href="/assets/images/favicon/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon/favicon-16.png">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon/favicon-32.png">
<link rel="icon" type="image/png" sizes="48x48" href="/assets/images/favicon/favicon-48.png">
<link rel="icon" type="image/png" sizes="96x96" href="/assets/images/favicon/favicon-96.png">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/images/favicon/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
<meta name="theme-color" media="(prefers-color-scheme: light)" content="#fafafa">
<meta name="theme-color" media="(prefers-color-scheme: dark)" content="#0b0b0c">

<!-- Fonts. Preloaded because the latin subset is on the critical render path;
     the -ext subset is not preloaded since most pages never reference it. -->
<link rel="preload" href="/assets/fonts/inter-latin.woff2" as="font" type="font/woff2" crossorigin>

<!-- Styles, in cascade order.

     THE VERSION QUERY IS THE CACHE BUST, AND IT IS NOT DECORATION
     Filenames are not content-hashed — there is no build step to hash them —
     and .htaccess caches CSS for a year. A changed stylesheet does not reach
     anybody who has been here before unless this string changes with it, so
     bump it in the same breath as the file. Forget, and the release is for
     new visitors only, which looks like nothing at all from here.
     docs/20-deployment/routine-deploys.md, "Cache busting" -->
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/theme.css">
<link rel="stylesheet" href="/assets/css/layout.css?v=4">
<link rel="stylesheet" href="/assets/css/components.css">
<link rel="stylesheet" href="/assets/css/animations.css">
<link rel="stylesheet" href="/assets/css/pages/service-detail.css">

<!-- Colour mode, applied before first paint to avoid a flash of the wrong
     theme. Deliberately NOT deferred; see the comment in the file itself. -->
<script src="/assets/js/theme-init.js"></script>

<!-- Base structured data, identical on every page. Per-page BreadcrumbList and
     any page-specific schema (Service, JobPosting, ContactPage…) go in their own
     block after this one.

     Contact details, addresses and social profiles are taken from the NextJS
     Footer component, which carries the live values. The Organization schema in
     the NextJS root layout lists placeholder social URLs (facebook.com/tech4time,
     twitter.com/tech4time, linkedin.com/company/tech4time, github.com/tech4time)
     that do not match the real profiles the footer links to; the real ones are
     used here. -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Organization",
      "@id": "https://tech4time.bd/#organization",
      "name": "Tech4TIME",
      "alternateName": "M/s. Tech4TIME",
      "url": "https://tech4time.bd/",
      "logo": {
        "@type": "ImageObject",
        "url": "https://tech4time.bd/assets/images/logo/logo-light-540.png",
        "width": 540,
        "height": 192
      },
      "image": "https://tech4time.bd/assets/images/og/tech4time-og.png",
      "description": "Open-Source and enterprise-grade cybersecurity, software development, cloud infrastructure and IT solutions. Orchestrate, build, maintain and protect your business.",
      "slogan": "Orchestrating Technology with Time",
      "foundingDate": "2018-05-15",
      "email": "info@tech4time.bd",
      "areaServed": "Worldwide",
      "knowsLanguage": "en",
      "address": [
        {
          "@type": "PostalAddress",
          "streetAddress": "278/3, Manikdi",
          "addressLocality": "Dhaka",
          "postalCode": "1206",
          "addressCountry": "BD"
        },
        {
          "@type": "PostalAddress",
          "streetAddress": "68100 Batu Caves",
          "addressRegion": "Selangor",
          "addressCountry": "MY"
        },
        {
          "@type": "PostalAddress",
          "streetAddress": "367, Avenue Louise",
          "addressLocality": "Brussels",
          "addressCountry": "BE"
        }
      ],
      "contactPoint": [
        {
          "@type": "ContactPoint",
          "telephone": "+8801320571562",
          "email": "info@tech4time.bd",
          "contactType": "customer service",
          "areaServed": "BD",
          "availableLanguage": [
            "English",
            "Bengali"
          ]
        },
        {
          "@type": "ContactPoint",
          "telephone": "+8801881873463",
          "email": "info@tech4time.bd",
          "contactType": "customer service",
          "areaServed": "BD",
          "availableLanguage": [
            "English",
            "Bengali"
          ]
        },
        {
          "@type": "ContactPoint",
          "telephone": "+8801847313835",
          "email": "info@tech4time.bd",
          "contactType": "customer service",
          "areaServed": "BD",
          "availableLanguage": [
            "English",
            "Bengali"
          ]
        },
        {
          "@type": "ContactPoint",
          "telephone": "+60198527096",
          "email": "info@tech4time.bd",
          "contactType": "customer service",
          "areaServed": "MY",
          "availableLanguage": [
            "English"
          ]
        }
      ],
      "sameAs": [
        "https://www.linkedin.com/company/tech4time-bd/",
        "https://github.com/M-s-Tech4TIME"
      ]
    },
    {
      "@type": "WebSite",
      "@id": "https://tech4time.bd/#website",
      "url": "https://tech4time.bd/",
      "name": "Tech4TIME",
      "description": "Cybersecurity, software development, cloud infrastructure and HR solutions.",
      "publisher": { "@id": "https://tech4time.bd/#organization" },
      "inLanguage": "en"
    },
    {
      "@type": "ProfessionalService",
      "@id": "https://tech4time.bd/#service",
      "name": "Tech4TIME",
      "url": "https://tech4time.bd/",
      "image": "https://tech4time.bd/assets/images/og/tech4time-og.png",
      "parentOrganization": { "@id": "https://tech4time.bd/#organization" },
      "priceRange": "$$",
      "areaServed": "Worldwide",
      "openingHoursSpecification": [
        {
          "@type": "OpeningHoursSpecification",
          "dayOfWeek": ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday"],
          "opens": "09:00",
          "closes": "18:00",
          "description": "Bangladesh office"
        },
        {
          "@type": "OpeningHoursSpecification",
          "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
          "opens": "09:00",
          "closes": "18:00",
          "description": "Malaysia office"
        }
      ],
      "serviceType": [
        "Cybersecurity Services",
        "Software Development",
        "Cloud Infrastructure",
        "IT Consulting",
        "Managed Services",
        "Human Resources as a Service",
        "DevOps Services",
        "Security Operations Center"
      ],
      "knowsAbout": [
        "Cybersecurity",
        "Penetration Testing",
        "Security Operations Center",
        "Incident Response",
        "Digital Forensics",
        "Software Development",
        "DevSecOps",
        "Cloud Computing",
        "OpenStack",
        "Kubernetes",
        "IT Staffing",
        "HR as a Service"
      ]
    }
  ]
}
</script>

<script type="application/ld+json">
<?= services_json_ld(services_breadcrumbs($service, 'https://tech4time.bd')) ?>
</script>

<script type="application/ld+json">
<?= services_json_ld(services_schema($service, 'https://tech4time.bd')) ?>
</script>
</head>

<body class="page">
<!-- icon-sprite:start -->
<svg class="icon-sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <symbol id="arrow-up" viewBox="0 0 384 512"><path d="M214.6 41.4c-12.5-12.5-32.8-12.5-45.3 0l-160 160c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L160 141.2V448c0 17.7 14.3 32 32 32s32-14.3 32-32V141.2L329.4 246.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3l-160-160z"/></symbol>
  <symbol id="building" viewBox="0 0 384 512"><path d="M48 0C21.5 0 0 21.5 0 48V464c0 26.5 21.5 48 48 48h96V432c0-26.5 21.5-48 48-48s48 21.5 48 48v80h96c26.5 0 48-21.5 48-48V48c0-26.5-21.5-48-48-48H48zM64 240c0-8.8 7.2-16 16-16h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H80c-8.8 0-16-7.2-16-16V240zm112-16h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H176c-8.8 0-16-7.2-16-16V240c0-8.8 7.2-16 16-16zm80 16c0-8.8 7.2-16 16-16h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H272c-8.8 0-16-7.2-16-16V240zM80 96h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H80c-8.8 0-16-7.2-16-16V112c0-8.8 7.2-16 16-16zm80 16c0-8.8 7.2-16 16-16h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H176c-8.8 0-16-7.2-16-16V112zM272 96h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H272c-8.8 0-16-7.2-16-16V112c0-8.8 7.2-16 16-16z"/></symbol>
  <symbol id="clock" viewBox="0 0 512 512"><path d="M256 0a256 256 0 1 1 0 512A256 256 0 1 1 256 0zM232 120V256c0 8 4 15.5 10.7 20l96 64c11 7.4 25.9 4.4 33.3-6.7s4.4-25.9-6.7-33.3L280 243.2V120c0-13.3-10.7-24-24-24s-24 10.7-24 24z"/></symbol>
  <symbol id="cogs" viewBox="0 0 640 512"><path d="M308.5 135.3c7.1-6.3 9.9-16.2 6.2-25c-2.3-5.3-4.8-10.5-7.6-15.5L304 89.4c-3-5-6.3-9.9-9.8-14.6c-5.7-7.6-15.7-10.1-24.7-7.1l-28.2 9.3c-10.7-8.8-23-16-36.2-20.9L199 27.1c-1.9-9.3-9.1-16.7-18.5-17.8C173.9 8.4 167.2 8 160.4 8h-.7c-6.8 0-13.5 .4-20.1 1.2c-9.4 1.1-16.6 8.6-18.5 17.8L115 56.1c-13.3 5-25.5 12.1-36.2 20.9L50.5 67.8c-9-3-19-.5-24.7 7.1c-3.5 4.7-6.8 9.6-9.9 14.6l-3 5.3c-2.8 5-5.3 10.2-7.6 15.6c-3.7 8.7-.9 18.6 6.2 25l22.2 19.8C32.6 161.9 32 168.9 32 176s.6 14.1 1.7 20.9L11.5 216.7c-7.1 6.3-9.9 16.2-6.2 25c2.3 5.3 4.8 10.5 7.6 15.6l3 5.2c3 5.1 6.3 9.9 9.9 14.6c5.7 7.6 15.7 10.1 24.7 7.1l28.2-9.3c10.7 8.8 23 16 36.2 20.9l6.1 29.1c1.9 9.3 9.1 16.7 18.5 17.8c6.7 .8 13.5 1.2 20.4 1.2s13.7-.4 20.4-1.2c9.4-1.1 16.6-8.6 18.5-17.8l6.1-29.1c13.3-5 25.5-12.1 36.2-20.9l28.2 9.3c9 3 19 .5 24.7-7.1c3.5-4.7 6.8-9.5 9.8-14.6l3.1-5.4c2.8-5 5.3-10.2 7.6-15.5c3.7-8.7 .9-18.6-6.2-25l-22.2-19.8c1.1-6.8 1.7-13.8 1.7-20.9s-.6-14.1-1.7-20.9l22.2-19.8zM112 176a48 48 0 1 1 96 0 48 48 0 1 1 -96 0zM504.7 500.5c6.3 7.1 16.2 9.9 25 6.2c5.3-2.3 10.5-4.8 15.5-7.6l5.4-3.1c5-3 9.9-6.3 14.6-9.8c7.6-5.7 10.1-15.7 7.1-24.7l-9.3-28.2c8.8-10.7 16-23 20.9-36.2l29.1-6.1c9.3-1.9 16.7-9.1 17.8-18.5c.8-6.7 1.2-13.5 1.2-20.4s-.4-13.7-1.2-20.4c-1.1-9.4-8.6-16.6-17.8-18.5L583.9 307c-5-13.3-12.1-25.5-20.9-36.2l9.3-28.2c3-9 .5-19-7.1-24.7c-4.7-3.5-9.6-6.8-14.6-9.9l-5.3-3c-5-2.8-10.2-5.3-15.6-7.6c-8.7-3.7-18.6-.9-25 6.2l-19.8 22.2c-6.8-1.1-13.8-1.7-20.9-1.7s-14.1 .6-20.9 1.7l-19.8-22.2c-6.3-7.1-16.2-9.9-25-6.2c-5.3 2.3-10.5 4.8-15.6 7.6l-5.2 3c-5.1 3-9.9 6.3-14.6 9.9c-7.6 5.7-10.1 15.7-7.1 24.7l9.3 28.2c-8.8 10.7-16 23-20.9 36.2L315.1 313c-9.3 1.9-16.7 9.1-17.8 18.5c-.8 6.7-1.2 13.5-1.2 20.4s.4 13.7 1.2 20.4c1.1 9.4 8.6 16.6 17.8 18.5l29.1 6.1c5 13.3 12.1 25.5 20.9 36.2l-9.3 28.2c-3 9-.5 19 7.1 24.7c4.7 3.5 9.5 6.8 14.6 9.8l5.4 3.1c5 2.8 10.2 5.3 15.5 7.6c8.7 3.7 18.6 .9 25-6.2l19.8-22.2c6.8 1.1 13.8 1.7 20.9 1.7s14.1-.6 20.9-1.7l19.8 22.2zM464 304a48 48 0 1 1 0 96 48 48 0 1 1 0-96z"/></symbol>
  <symbol id="comment-alt" viewBox="0 0 512 512"><path d="M64 0C28.7 0 0 28.7 0 64V352c0 35.3 28.7 64 64 64h96v80c0 6.1 3.4 11.6 8.8 14.3s11.9 2.1 16.8-1.5L309.3 416H448c35.3 0 64-28.7 64-64V64c0-35.3-28.7-64-64-64H64z"/></symbol>
  <symbol id="envelope" viewBox="0 0 512 512"><path d="M48 64C21.5 64 0 85.5 0 112c0 15.1 7.1 29.3 19.2 38.4L236.8 313.6c11.4 8.5 27 8.5 38.4 0L492.8 150.4c12.1-9.1 19.2-23.3 19.2-38.4c0-26.5-21.5-48-48-48H48zM0 176V384c0 35.3 28.7 64 64 64H448c35.3 0 64-28.7 64-64V176L294.4 339.2c-22.8 17.1-54 17.1-76.8 0L0 176z"/></symbol>
  <symbol id="github" viewBox="0 0 496 512"><path d="M165.9 397.4c0 2-2.3 3.6-5.2 3.6-3.3.3-5.6-1.3-5.6-3.6 0-2 2.3-3.6 5.2-3.6 3-.3 5.6 1.3 5.6 3.6zm-31.1-4.5c-.7 2 1.3 4.3 4.3 4.9 2.6 1 5.6 0 6.2-2s-1.3-4.3-4.3-5.2c-2.6-.7-5.5.3-6.2 2.3zm44.2-1.7c-2.9.7-4.9 2.6-4.6 4.9.3 2 2.9 3.3 5.9 2.6 2.9-.7 4.9-2.6 4.6-4.6-.3-1.9-3-3.2-5.9-2.9zM244.8 8C106.1 8 0 113.3 0 252c0 110.9 69.8 205.8 169.5 239.2 12.8 2.3 17.3-5.6 17.3-12.1 0-6.2-.3-40.4-.3-61.4 0 0-70 15-84.7-29.8 0 0-11.4-29.1-27.8-36.6 0 0-22.9-15.7 1.6-15.4 0 0 24.9 2 38.6 25.8 21.9 38.6 58.6 27.5 72.9 20.9 2.3-16 8.8-27.1 16-33.7-55.9-6.2-112.3-14.3-112.3-110.5 0-27.5 7.6-41.3 23.6-58.9-2.6-6.5-11.1-33.3 2.6-67.9 20.9-6.5 69 27 69 27 20-5.6 41.5-8.5 62.8-8.5s42.8 2.9 62.8 8.5c0 0 48.1-33.6 69-27 13.7 34.7 5.2 61.4 2.6 67.9 16 17.7 25.8 31.5 25.8 58.9 0 96.5-58.9 104.2-114.8 110.5 9.2 7.9 17 22.9 17 46.4 0 33.7-.3 75.4-.3 83.6 0 6.5 4.6 14.4 17.3 12.1C428.2 457.8 496 362.9 496 252 496 113.3 383.5 8 244.8 8zM97.2 352.9c-1.3 1-1 3.3.7 5.2 1.6 1.6 3.9 2.3 5.2 1 1.3-1 1-3.3-.7-5.2-1.6-1.6-3.9-2.3-5.2-1zm-10.8-8.1c-.7 1.3.3 2.9 2.3 3.9 1.6 1 3.6.7 4.3-.7.7-1.3-.3-2.9-2.3-3.9-2-.6-3.6-.3-4.3.7zm32.4 35.6c-1.6 1.3-1 4.3 1.3 6.2 2.3 2.3 5.2 2.6 6.5 1 1.3-1.3.7-4.3-1.3-6.2-2.2-2.3-5.2-2.6-6.5-1zm-11.4-14.7c-1.6 1-1.6 3.6 0 5.9 1.6 2.3 4.3 3.3 5.6 2.3 1.6-1.3 1.6-3.9 0-6.2-1.4-2.3-4-3.3-5.6-2z"/></symbol>
  <symbol id="grid-dots" viewBox="0 0 24 24"><circle cx="5" cy="5" r="2.1"/><circle cx="12" cy="5" r="2.1"/><circle cx="19" cy="5" r="2.1"/><circle cx="5" cy="12" r="2.1"/><circle cx="12" cy="12" r="2.1"/><circle cx="19" cy="12" r="2.1"/><circle cx="5" cy="19" r="2.1"/><circle cx="12" cy="19" r="2.1"/><circle cx="19" cy="19" r="2.1"/></symbol>
  <symbol id="home" viewBox="0 0 576 512"><path d="M575.8 255.5c0 18-15 32.1-32 32.1h-32l.7 160.2c0 2.7-.2 5.4-.5 8.1V472c0 22.1-17.9 40-40 40H456c-1.1 0-2.2 0-3.3-.1c-1.4 .1-2.8 .1-4.2 .1H416 392c-22.1 0-40-17.9-40-40V448 384c0-17.7-14.3-32-32-32H256c-17.7 0-32 14.3-32 32v64 24c0 22.1-17.9 40-40 40H160 128.1c-1.5 0-3-.1-4.5-.2c-1.2 .1-2.4 .2-3.6 .2H104c-22.1 0-40-17.9-40-40V360c0-.9 0-1.9 .1-2.8V287.6H32c-18 0-32-14-32-32.1c0-9 3-17 10-24L266.4 8c7-7 15-8 22-8s15 2 21 7L564.8 231.5c8 7 12 15 11 24z"/></symbol>
  <symbol id="linkedin" viewBox="0 0 448 512"><path d="M416 32H31.9C14.3 32 0 46.5 0 64.3v383.4C0 465.5 14.3 480 31.9 480H416c17.6 0 32-14.5 32-32.3V64.3c0-17.8-14.4-32.3-32-32.3zM135.4 416H69V202.2h66.5V416zm-33.2-243c-21.3 0-38.5-17.3-38.5-38.5S80.9 96 102.2 96c21.2 0 38.5 17.3 38.5 38.5 0 21.3-17.2 38.5-38.5 38.5zm282.1 243h-66.4V312c0-24.8-.5-56.7-34.5-56.7-34.6 0-39.9 27-39.9 54.9V416h-66.4V202.2h63.7v29.2h.9c8.9-16.8 30.6-34.5 62.9-34.5 67.2 0 79.7 44.3 79.7 101.9V416z"/></symbol>
  <symbol id="map-marker-alt" viewBox="0 0 384 512"><path d="M215.7 499.2C267 435 384 279.4 384 192C384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2c12.3 15.3 35.1 15.3 47.4 0zM192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128z"/></symbol>
  <symbol id="moon" viewBox="0 0 384 512"><path d="M223.5 32C100 32 0 132.3 0 256S100 480 223.5 480c60.6 0 115.5-24.2 155.8-63.4c5-4.9 6.3-12.5 3.1-18.7s-10.1-9.7-17-8.5c-9.8 1.7-19.8 2.6-30.1 2.6c-96.9 0-175.5-78.8-175.5-176c0-65.8 36-123.1 89.3-153.3c6.1-3.5 9.2-10.5 7.7-17.3s-7.3-11.9-14.3-12.5c-6.3-.5-12.6-.8-19-.8z"/></symbol>
  <symbol id="phone" viewBox="0 0 512 512"><path d="M164.9 24.6c-7.7-18.6-28-28.5-47.4-23.2l-88 24C12.1 30.2 0 46 0 64C0 311.4 200.6 512 448 512c18 0 33.8-12.1 38.6-29.5l24-88c5.3-19.4-4.6-39.7-23.2-47.4l-96-40c-16.3-6.8-35.2-2.1-46.3 11.6L304.7 368C234.3 334.7 177.3 277.7 144 207.3L193.3 167c13.7-11.2 18.4-30 11.6-46.3l-40-96z"/></symbol>
  <symbol id="sun" viewBox="0 0 512 512"><path d="M361.5 1.2c5 2.1 8.6 6.6 9.6 11.9L391 121l107.9 19.8c5.3 1 9.8 4.6 11.9 9.6s1.5 10.7-1.6 15.2L446.9 256l62.3 90.3c3.1 4.5 3.7 10.2 1.6 15.2s-6.6 8.6-11.9 9.6L391 391 371.1 498.9c-1 5.3-4.6 9.8-9.6 11.9s-10.7 1.5-15.2-1.6L256 446.9l-90.3 62.3c-4.5 3.1-10.2 3.7-15.2 1.6s-8.6-6.6-9.6-11.9L121 391 13.1 371.1c-5.3-1-9.8-4.6-11.9-9.6s-1.5-10.7 1.6-15.2L65.1 256 2.8 165.7c-3.1-4.5-3.7-10.2-1.6-15.2s6.6-8.6 11.9-9.6L121 121 140.9 13.1c1-5.3 4.6-9.8 9.6-11.9s10.7-1.5 15.2 1.6L256 65.1 346.3 2.8c4.5-3.1 10.2-3.7 15.2-1.6zM160 256a96 96 0 1 1 192 0 96 96 0 1 1 -192 0zm224 0a128 128 0 1 0 -256 0 128 128 0 1 0 256 0z"/></symbol>
  <symbol id="times" viewBox="0 0 384 512"><path d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z"/></symbol>
</svg>
<!-- icon-sprite:end -->
<?= services_sprite(services_icons_used($data, $service)) ?>

<a class="skip-link" href="#main">Skip to main content</a>

<header class="site-header" id="top">
  <div class="container site-header__inner">
    <!-- Two logo lockups, one per mode, toggled in CSS.
         A <picture media="(prefers-color-scheme: …)"> cannot be used here: that
         media query only ever reflects the OS setting, so it would ignore a
         visitor who picked the opposite mode with the header toggle. The hidden
         variant is lazy-loaded so browsers skip fetching it. -->
    <a class="site-header__brand" href="/" aria-label="Tech4TIME — home">
      <picture class="site-header__logo-wrap site-header__logo-wrap--light">
        <source
          srcset="/assets/images/logo/logo-light-180.webp 180w, /assets/images/logo/logo-light-360.webp 360w, /assets/images/logo/logo-light-540.webp 540w"
          sizes="(max-width: 48em) 140px, 180px"
          type="image/webp">
        <img
          class="site-header__logo"
          src="/assets/images/logo/logo-light-360.png"
          srcset="/assets/images/logo/logo-light-180.png 180w, /assets/images/logo/logo-light-360.png 360w, /assets/images/logo/logo-light-540.png 540w"
          sizes="(max-width: 48em) 140px, 180px"
          alt="Tech4TIME"
          width="360"
          height="128"
          fetchpriority="high"
          decoding="async">
      </picture>
      <picture class="site-header__logo-wrap site-header__logo-wrap--dark">
        <source
          srcset="/assets/images/logo/logo-dark-180.webp 180w, /assets/images/logo/logo-dark-360.webp 360w, /assets/images/logo/logo-dark-540.webp 540w"
          sizes="(max-width: 48em) 140px, 180px"
          type="image/webp">
        <img
          class="site-header__logo"
          src="/assets/images/logo/logo-dark-360.png"
          srcset="/assets/images/logo/logo-dark-180.png 180w, /assets/images/logo/logo-dark-360.png 360w, /assets/images/logo/logo-dark-540.png 540w"
          sizes="(max-width: 48em) 140px, 180px"
          alt="Tech4TIME"
          width="360"
          height="128"
          loading="lazy"
          decoding="async">
      </picture>
    </a>

    <!-- No data-nav-drawer here. This nav is the desktop navigation and
         nothing opens or closes it; below 64em it is hidden and the dock
         panel is the thing that opens. nav.js binds to the first
         [data-nav-drawer] in the document, so leaving the attribute on this
         element pointed the menu button at a display:none nav — it dutifully
         set data-open="true" on something nobody could see. -->
    <nav class="site-nav" id="site-nav" aria-label="Main">
      <ul class="site-nav__list">
        <li class="site-nav__item"><a class="nav-link" href="/">Home</a></li>
        <li class="site-nav__item"><a class="nav-link" href="/pages/about/">About Us</a></li>
        <li class="site-nav__item"><a class="nav-link" href="/pages/services/" aria-current="page">Services</a></li>
        <li class="site-nav__item"><a class="nav-link" href="/pages/company-profile/">Company Profile</a></li>
        <li class="site-nav__item"><a class="nav-link" href="/pages/careers/">Careers</a></li>
        <li class="site-nav__item"><a class="nav-link" href="/pages/contact/">Contact Us</a></li>
      </ul>
    </nav>

    <div class="site-header__actions">
      <button
        class="btn btn--icon"
        type="button"
        data-theme-toggle
        aria-label="Switch to dark mode"
        aria-pressed="false">
        <svg class="icon theme-toggle__icon--moon" aria-hidden="true" focusable="false"><use href="#moon"></use></svg>
        <svg class="icon theme-toggle__icon--sun" aria-hidden="true" focusable="false"><use href="#sun"></use></svg>
      </button>
    </div>
  </div>
</header>

<main class="page__main" id="main">

  <!-- ========================== Hero banner ========================== -->
  <section class="page-hero">
    <!--hero-circuit:start-->
    <!-- The circuitry around the page title, in the same language as the
         company's own printed material: clusters emerging from all four
         corners, and a chevron band running the full width of the top and the
         bottom edge. Drawn rather than photographed, so it tints itself from
         the theme tokens and costs the Largest Contentful Paint nothing.

         aria-hidden, and inside the band but behind it: this is texture around
         the title, and it says nothing.

         SIX LAYERS, ONE SET OF GEOMETRY
         Everything is declared once, in the first layer's <defs>. SVG ids are
         document-scoped, so the other five reference the same paths and are
         mirrored in CSS. That is not tidiness - a duplicate id is a hard
         failure in audit_pages.py, so four corners cannot each carry a copy.

         The bands use preserveAspectRatio="none" because they run the width of
         the viewport at a fixed height, and stretching a horizontal run just
         makes it a longer run, which is what more circuit board looks like.
         The corners use xMinYMin meet instead: a cluster of 45 degree elbows
         must not shear, and it has to stay pinned to its own corner.

         A CHARGE IS ONE <use>, AND NEVER A GROUP OF THEM
         This layer once carried the charge on a <g> wrapping a <use> of a
         *group* of traces, on the reasoning that forty animated elements must
         beat two hundred. That reasoning was wrong, and measurably so.
         stroke-dashoffset is an inherited property: animating it on a group
         makes the browser push the new value down through every <use> shadow
         tree beneath it, every frame. Lighthouse put the page's Style & Layout
         work at 4,683ms against 686ms before it, and the site was reported as
         struggling. Flattening it to one <use> per charged trace cut that by
         about 1,500ms on its own; charging one trace in five rather than all
         of them cut another 1,000ms.

         So: the charge goes directly on the <use> that draws the trace, and it
         is deliberately not on every trace. The density here is the STATIC
         drawing, which costs one rasterisation; movement is the expensive part
         and is spent sparingly - three traces in each cluster and three in each
         band half, 24 against 216 drawn.

         The cost is close to linear in that number: about 1.1ms of style
         recalculation per second per charge, on top of a 25ms floor that is the
         static drawing. 24 charges measure 39ms/s, which is BELOW the 52ms/s
         this page cost before the circuitry was ever replaced. 48 would be
         83ms/s and 216 about 300ms/s, which is what was shipped and reported.
         If you raise it, measure - tools/check_style_budget.py, and read the
         table in docs/10-development/frontend/motion.md first.

         THE FOUR CORNERS SHARE THREE DURATIONS, AND THAT IS ALSO MEASURED
         Everywhere else on this site a shared duration is the fault being
         avoided. Here it is deliberate: twelve distinct durations give twelve
         distinct computed styles, and Chrome can then share none of them
         between elements. That measured 55ms of style recalculation per second
         against 35ms for the same twenty-four charges on three shared ones -
         and the four clusters are mirror images of each other, so sharing a
         phase reads as the board lighting symmetrically rather than as four
         copies of one loop. Within a cluster the three still differ.

         The two bands are the other deliberate exception: one speed, opposite
         directions, because they are one current going round. -->
    <div class="hero-circuit" aria-hidden="true">
      <svg class="hero-circuit__layer hero-circuit__layer--band-top" viewBox="0 0 1440 120" preserveAspectRatio="none" focusable="false">
        <defs>
        <path id="hc-c0" pathLength="100" d="M12 0L12 34L32 54L32 84L58 84L58 102"/>
        <path id="hc-c1" pathLength="100" d="M30 0L30 18L56 44L56 88"/>
        <path id="hc-c2" pathLength="100" d="M48 0L48 46L70 46L86 62L114 62"/>
        <path id="hc-c3" pathLength="100" d="M66 0L66 26L84 44L84 68L122 68"/>
        <path id="hc-c4" pathLength="100" d="M84 0L84 36L104 36L104 66L118 80"/>
        <path id="hc-c5" pathLength="100" d="M104 0L104 16L126 38L160 38L160 58"/>
        <path id="hc-c6" pathLength="100" d="M124 0L124 28L140 28L140 44L158 62"/>
        <path id="hc-c7" pathLength="100" d="M146 0L146 20L162 36L162 70"/>
        <path id="hc-c8" pathLength="100" d="M170 0L170 40L196 40L196 54"/>
        <path id="hc-c9" pathLength="100" d="M196 0L196 24L216 44L238 44"/>
        <path id="hc-c10" pathLength="100" d="M224 0L224 32L242 32"/>
        <path id="hc-c11" pathLength="100" d="M0 12L34 12L54 32L84 32L84 58L102 58"/>
        <path id="hc-c12" pathLength="100" d="M0 30L18 30L44 56L88 56"/>
        <path id="hc-c13" pathLength="100" d="M0 48L46 48L46 70L62 86L62 114"/>
        <path id="hc-c14" pathLength="100" d="M0 66L26 66L44 84L68 84L68 122"/>
        <path id="hc-c15" pathLength="100" d="M0 84L36 84L36 104L66 104L80 118"/>
        <path id="hc-c16" pathLength="100" d="M0 104L16 104L38 126L38 160L58 160"/>
        <path id="hc-c17" pathLength="100" d="M0 124L28 124L28 140L44 140L62 158"/>
        <path id="hc-c18" pathLength="100" d="M0 146L20 146L36 162L70 162"/>
        <path id="hc-c19" pathLength="100" d="M0 170L40 170L40 196L54 196"/>
        <path id="hc-c20" pathLength="100" d="M32 54L52 54L64 66"/>
        <path id="hc-c21" pathLength="100" d="M54 32L54 52L66 64"/>
        <path id="hc-c22" pathLength="100" d="M84 68L84 86L98 86"/>
        <path id="hc-c23" pathLength="100" d="M68 84L86 84L86 98"/>
        <path id="hc-c24" pathLength="100" d="M126 38L126 60L114 72"/>
        <path id="hc-c25" pathLength="100" d="M38 126L60 126L72 114"/>
        <path id="hc-c26" pathLength="100" d="M54 84L68 98L84 98"/>
        <path id="hc-c27" pathLength="100" d="M84 54L98 68L98 84"/>
        <path id="hc-c28" pathLength="100" d="M162 36L182 36L182 48"/>
        <path id="hc-c29" pathLength="100" d="M36 162L36 182L48 182"/>
        <g id="hc-corner-wires"><use href="#hc-c0"/><use href="#hc-c1"/><use href="#hc-c2"/><use href="#hc-c3"/><use href="#hc-c4"/><use href="#hc-c5"/><use href="#hc-c6"/><use href="#hc-c7"/><use href="#hc-c8"/><use href="#hc-c9"/><use href="#hc-c10"/><use href="#hc-c11"/><use href="#hc-c12"/><use href="#hc-c13"/><use href="#hc-c14"/><use href="#hc-c15"/><use href="#hc-c16"/><use href="#hc-c17"/><use href="#hc-c18"/><use href="#hc-c19"/><use href="#hc-c20"/><use href="#hc-c21"/><use href="#hc-c22"/><use href="#hc-c23"/><use href="#hc-c24"/><use href="#hc-c25"/><use href="#hc-c26"/><use href="#hc-c27"/><use href="#hc-c28"/><use href="#hc-c29"/></g>
        <g id="hc-corner-pads"><circle cx="58" cy="102" r="3.4"/><circle cx="56" cy="88" r="3.4"/><circle cx="114" cy="62" r="3.4"/><circle cx="122" cy="68" r="3.4"/><circle cx="118" cy="80" r="3.4"/><circle cx="160" cy="58" r="3.4"/><circle cx="158" cy="62" r="3.4"/><circle cx="162" cy="70" r="3.4"/><circle cx="196" cy="54" r="3.4"/><circle cx="238" cy="44" r="3.4"/><circle cx="242" cy="32" r="3.4"/><circle cx="102" cy="58" r="3.4"/><circle cx="88" cy="56" r="3.4"/><circle cx="62" cy="114" r="3.4"/><circle cx="68" cy="122" r="3.4"/><circle cx="80" cy="118" r="3.4"/><circle cx="58" cy="160" r="3.4"/><circle cx="62" cy="158" r="3.4"/><circle cx="70" cy="162" r="3.4"/><circle cx="54" cy="196" r="3.4"/><circle cx="64" cy="66" r="3.4"/><circle cx="66" cy="64" r="3.4"/><circle cx="98" cy="86" r="3.4"/><circle cx="86" cy="98" r="3.4"/><circle cx="114" cy="72" r="3.4"/><circle cx="72" cy="114" r="3.4"/><circle cx="84" cy="98" r="3.4"/><circle cx="98" cy="84" r="3.4"/><circle cx="182" cy="48" r="3.4"/><circle cx="48" cy="182" r="3.4"/><rect x="88" y="92" width="10" height="6" rx="1"/><rect x="146" y="74" width="6" height="10" rx="1"/><rect x="54" y="126" width="10" height="6" rx="1"/><rect x="118" y="110" width="6" height="10" rx="1"/><rect x="200" y="60" width="10" height="6" rx="1"/><rect x="36" y="100" width="10" height="6" rx="1"/><rect x="74" y="148" width="6" height="10" rx="1"/><rect x="172" y="92" width="10" height="6" rx="1"/><rect x="108" y="154" width="10" height="6" rx="1"/><rect x="30" y="168" width="6" height="10" rx="1"/></g>
        <g id="hc-corner-rings"><circle cx="140" cy="46" r="5"/><circle cx="46" cy="140" r="5"/><circle cx="206" cy="96" r="4.2"/><circle cx="96" cy="206" r="4.2"/><circle cx="176" cy="128" r="4"/><circle cx="128" cy="176" r="4"/><path d="M180 14v15"/><path d="M189 14v15"/><path d="M198 14v15"/><path d="M207 14v15"/><path d="M216 14v15"/><path d="M225 14v15"/><path d="M14 180h15"/><path d="M14 189h15"/><path d="M14 198h15"/><path d="M14 207h15"/><path d="M14 216h15"/><path d="M14 225h15"/><path d="M138 100v12"/><path d="M146 100v12"/><path d="M154 100v12"/><path d="M162 100v12"/><path d="M170 100v12"/><path d="M100 138h12"/><path d="M100 146h12"/><path d="M100 154h12"/><path d="M100 162h12"/><path d="M100 170h12"/><path d="M222 62v11"/><path d="M230 62v11"/><path d="M238 62v11"/><path d="M246 62v11"/></g>
        <path id="hc-b0" pathLength="100" d="M-100 132L-18 -12"/>
        <path id="hc-b1" pathLength="100" d="M-52 132L-14 66L44 66L82 -12"/>
        <path id="hc-b2" pathLength="100" d="M-4 132L78 -12"/>
        <path id="hc-b3" pathLength="100" d="M44 132L78 72L78 40L104 -12"/>
        <path id="hc-b4" pathLength="100" d="M92 132L174 -12"/>
        <path id="hc-b5" pathLength="100" d="M130 66l22 13"/>
        <path id="hc-b6" pathLength="100" d="M140 132L222 -12"/>
        <path id="hc-b7" pathLength="100" d="M188 132L239 43"/>
        <path id="hc-b8" pathLength="100" d="M236 132L274 66L332 66L370 -12"/>
        <path id="hc-b9" pathLength="100" d="M284 132L366 -12"/>
        <path id="hc-b10" pathLength="100" d="M332 132L366 72L366 40L392 -12"/>
        <path id="hc-b11" pathLength="100" d="M380 132L462 -12"/>
        <path id="hc-b12" pathLength="100" d="M428 132L466 66L524 66L562 -12"/>
        <path id="hc-b13" pathLength="100" d="M476 132L558 -12"/>
        <path id="hc-b14" pathLength="100" d="M524 132L558 72L558 40L584 -12"/>
        <path id="hc-b15" pathLength="100" d="M572 132L654 -12"/>
        <path id="hc-b16" pathLength="100" d="M610 66l22 13"/>
        <path id="hc-b17" pathLength="100" d="M620 132L702 -12"/>
        <path id="hc-b18" pathLength="100" d="M668 132L719 43"/>
        <path id="hc-b19" pathLength="100" d="M646 132L720 2"/>
        <path id="hc-b20" pathLength="100" d="M598 132L672 2L720 2"/>
        <path id="hc-b21" pathLength="100" d="M0 26h150l22 20h206"/>
        <path id="hc-b22" pathLength="100" d="M0 62h84l26-18h150"/>
        <path id="hc-b23" pathLength="100" d="M0 100h96l24-20h180"/>
        <g id="hc-band-half"><use href="#hc-b0"/><use href="#hc-b1"/><use href="#hc-b2"/><use href="#hc-b3"/><use href="#hc-b4"/><use href="#hc-b5"/><use href="#hc-b6"/><use href="#hc-b7"/><use href="#hc-b8"/><use href="#hc-b9"/><use href="#hc-b10"/><use href="#hc-b11"/><use href="#hc-b12"/><use href="#hc-b13"/><use href="#hc-b14"/><use href="#hc-b15"/><use href="#hc-b16"/><use href="#hc-b17"/><use href="#hc-b18"/><use href="#hc-b19"/><use href="#hc-b20"/><use href="#hc-b21"/><use href="#hc-b22"/><use href="#hc-b23"/></g>
        <g id="hc-band-wires"><use href="#hc-band-half"/><use href="#hc-band-half" transform="translate(1440,0) scale(-1,1)"/></g>
        <g id="hc-band-pads-half"><rect x="-79" y="85" width="8" height="8" rx="1"/><rect x="65" y="85" width="8" height="8" rx="1"/><rect x="209" y="85" width="8" height="8" rx="1"/><rect x="353" y="85" width="8" height="8" rx="1"/><rect x="497" y="85" width="8" height="8" rx="1"/><rect x="641" y="85" width="8" height="8" rx="1"/><rect x="14" y="16" width="9" height="9" rx="1"/><rect x="14" y="38" width="9" height="9" rx="1"/><rect x="14" y="60" width="9" height="9" rx="1"/><rect x="14" y="82" width="9" height="9" rx="1"/><path d="M-4 40L4 48L-4 56L-12 48Z"/><path d="M140 40L148 48L140 56L132 48Z"/><path d="M284 40L292 48L284 56L276 48Z"/><path d="M428 40L436 48L428 56L420 48Z"/><path d="M572 40L580 48L572 56L564 48Z"/><path d="M716 40L724 48L716 56L708 48Z"/><circle cx="62" cy="17" r="3.2"/><circle cx="152" cy="79" r="3.4"/><circle cx="239" cy="43" r="4"/><circle cx="254" cy="17" r="3.2"/><circle cx="446" cy="17" r="3.2"/><circle cx="632" cy="79" r="3.4"/><circle cx="638" cy="17" r="3.2"/><circle cx="719" cy="43" r="4"/></g>
        <g id="hc-band-pads"><use href="#hc-band-pads-half"/><use href="#hc-band-pads-half" transform="translate(1440,0) scale(-1,1)"/></g>
        <g id="hc-band-rings-half"><circle cx="206" cy="46" r="4.6"/><circle cx="474" cy="66" r="4.6"/><circle cx="120" cy="80" r="4"/><circle cx="352" cy="30" r="4.4"/></g>
        <g id="hc-band-rings"><use href="#hc-band-rings-half"/><use href="#hc-band-rings-half" transform="translate(1440,0) scale(-1,1)"/></g>
        </defs>
        <g class="hero-circuit__wires"><use href="#hc-band-wires"/></g>
        <g class="hero-circuit__pads"><use href="#hc-band-pads"/></g>
        <g class="hero-circuit__rings"><use href="#hc-band-rings"/></g>
        <g class="hero-circuit__charges">
          <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--p1" href="#hc-b0"/>
          <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--p2" href="#hc-b8"/>
          <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--p3" href="#hc-b16"/>
          <g transform="translate(1440,0) scale(-1,1)">
            <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--mirrored hero-circuit__charge--p1" href="#hc-b0"/>
            <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--mirrored hero-circuit__charge--p2" href="#hc-b8"/>
            <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--mirrored hero-circuit__charge--p3" href="#hc-b16"/>
          </g>
        </g>
        <g class="hero-circuit__nodes">
          <circle class="hero-circuit__node hero-circuit__node--a" cx="206" cy="46" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--b" cx="474" cy="66" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--c" cx="966" cy="66" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--d" cx="1234" cy="46" r="3.6"/>
        </g>
      </svg>
      <svg class="hero-circuit__layer hero-circuit__layer--band-bottom" viewBox="0 0 1440 120" preserveAspectRatio="none" focusable="false">
        <g class="hero-circuit__wires"><use href="#hc-band-wires"/></g>
        <g class="hero-circuit__pads"><use href="#hc-band-pads"/></g>
        <g class="hero-circuit__rings"><use href="#hc-band-rings"/></g>
        <g class="hero-circuit__charges">
          <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--p1" href="#hc-b0"/>
          <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--p2" href="#hc-b8"/>
          <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--p3" href="#hc-b16"/>
          <g transform="translate(1440,0) scale(-1,1)">
            <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--mirrored hero-circuit__charge--p1" href="#hc-b0"/>
            <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--mirrored hero-circuit__charge--p2" href="#hc-b8"/>
            <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--mirrored hero-circuit__charge--p3" href="#hc-b16"/>
          </g>
        </g>
        <g class="hero-circuit__nodes">
          <circle class="hero-circuit__node hero-circuit__node--e" cx="206" cy="46" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--f" cx="474" cy="66" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--g" cx="966" cy="66" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--h" cx="1234" cy="46" r="3.6"/>
        </g>
      </svg>
      <svg class="hero-circuit__layer hero-circuit__layer--corner-tl" viewBox="0 0 260 200" preserveAspectRatio="xMinYMin meet" focusable="false">
        <g class="hero-circuit__wires"><use href="#hc-corner-wires"/></g>
        <g class="hero-circuit__pads"><use href="#hc-corner-pads"/></g>
        <g class="hero-circuit__rings"><use href="#hc-corner-rings"/></g>
        <g class="hero-circuit__charges">
          <use class="hero-circuit__charge hero-circuit__charge--c1" href="#hc-c0"/>
          <use class="hero-circuit__charge hero-circuit__charge--c2 hero-circuit__charge--back" href="#hc-c10"/>
          <use class="hero-circuit__charge hero-circuit__charge--c3" href="#hc-c20"/>
        </g>
        <g class="hero-circuit__nodes">
          <circle class="hero-circuit__node hero-circuit__node--i" cx="56" cy="44" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--j" cx="104" cy="36" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--k" cx="44" cy="56" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--l" cx="36" cy="104" r="3.6"/>
        </g>
      </svg>
      <svg class="hero-circuit__layer hero-circuit__layer--corner-tr" viewBox="0 0 260 200" preserveAspectRatio="xMinYMin meet" focusable="false">
        <g class="hero-circuit__wires"><use href="#hc-corner-wires"/></g>
        <g class="hero-circuit__pads"><use href="#hc-corner-pads"/></g>
        <g class="hero-circuit__rings"><use href="#hc-corner-rings"/></g>
        <g class="hero-circuit__charges">
          <use class="hero-circuit__charge hero-circuit__charge--c1" href="#hc-c0"/>
          <use class="hero-circuit__charge hero-circuit__charge--c2 hero-circuit__charge--back" href="#hc-c10"/>
          <use class="hero-circuit__charge hero-circuit__charge--c3" href="#hc-c20"/>
        </g>
        <g class="hero-circuit__nodes">
          <circle class="hero-circuit__node hero-circuit__node--m" cx="56" cy="44" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--n" cx="104" cy="36" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--o" cx="44" cy="56" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--p" cx="36" cy="104" r="3.6"/>
        </g>
      </svg>
      <svg class="hero-circuit__layer hero-circuit__layer--corner-bl" viewBox="0 0 260 200" preserveAspectRatio="xMinYMin meet" focusable="false">
        <g class="hero-circuit__wires"><use href="#hc-corner-wires"/></g>
        <g class="hero-circuit__pads"><use href="#hc-corner-pads"/></g>
        <g class="hero-circuit__rings"><use href="#hc-corner-rings"/></g>
        <g class="hero-circuit__charges">
          <use class="hero-circuit__charge hero-circuit__charge--c1" href="#hc-c0"/>
          <use class="hero-circuit__charge hero-circuit__charge--c2 hero-circuit__charge--back" href="#hc-c10"/>
          <use class="hero-circuit__charge hero-circuit__charge--c3" href="#hc-c20"/>
        </g>
        <g class="hero-circuit__nodes">
          <circle class="hero-circuit__node hero-circuit__node--q" cx="56" cy="44" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--r" cx="104" cy="36" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--s" cx="44" cy="56" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--t" cx="36" cy="104" r="3.6"/>
        </g>
      </svg>
      <svg class="hero-circuit__layer hero-circuit__layer--corner-br" viewBox="0 0 260 200" preserveAspectRatio="xMinYMin meet" focusable="false">
        <g class="hero-circuit__wires"><use href="#hc-corner-wires"/></g>
        <g class="hero-circuit__pads"><use href="#hc-corner-pads"/></g>
        <g class="hero-circuit__rings"><use href="#hc-corner-rings"/></g>
        <g class="hero-circuit__charges">
          <use class="hero-circuit__charge hero-circuit__charge--c1" href="#hc-c0"/>
          <use class="hero-circuit__charge hero-circuit__charge--c2 hero-circuit__charge--back" href="#hc-c10"/>
          <use class="hero-circuit__charge hero-circuit__charge--c3" href="#hc-c20"/>
        </g>
        <g class="hero-circuit__nodes">
          <circle class="hero-circuit__node hero-circuit__node--u" cx="56" cy="44" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--v" cx="104" cy="36" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--w" cx="44" cy="56" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--x" cx="36" cy="104" r="3.6"/>
        </g>
      </svg>
    </div>
<!--hero-circuit:end-->

<div class="container page-hero__inner">
      <h1 class="page-hero__title"><?= h($service['hero']['title']) ?></h1>
      <p class="page-hero__subtitle"><?= h($service['hero']['subtitle']) ?></p>
    </div>
  </section>

  <!-- ========================= Core capability ========================= -->
<?= services_core_band($service) ?>
<?= services_layers_band($service) ?>
<?= services_cta($service['cta'], 'page-cta-heading') ?>
</main>

<footer class="site-footer">
  <div class="container">
    <div class="site-footer__main">
      <!-- Brand -->
      <div class="site-footer__brand">
        <a href="/" aria-label="Tech4TIME — home">
          <picture class="site-footer__logo-wrap site-footer__logo-wrap--light">
            <source srcset="/assets/images/logo/logo-light-360.webp" type="image/webp">
            <img class="site-footer__logo" src="/assets/images/logo/logo-light-360.png"
                 alt="Tech4TIME" width="360" height="128" loading="lazy" decoding="async">
          </picture>
          <picture class="site-footer__logo-wrap site-footer__logo-wrap--dark">
            <source srcset="/assets/images/logo/logo-dark-360.webp" type="image/webp">
            <img class="site-footer__logo" src="/assets/images/logo/logo-dark-360.png"
                 alt="Tech4TIME" width="360" height="128" loading="lazy" decoding="async">
          </picture>
        </a>
        <p class="site-footer__tagline">Orchestrating Technology with Time</p>
        <p class="site-footer__description">
          Open-Source &amp; Enterprise-grade cybersecurity, software development, and IT
          solutions. Orchestrate, build, maintain and protect your business with our
          profound solutions.
        </p>
        <ul class="site-footer__social">
          <li>
            <a class="btn btn--icon" href="https://www.linkedin.com/company/tech4time-bd/"
               target="_blank" rel="noopener noreferrer" aria-label="Tech4TIME on LinkedIn">
              <svg class="icon" aria-hidden="true" focusable="false"><use href="#linkedin"></use></svg>
            </a>
          </li>
          <li>
            <a class="btn btn--icon" href="https://github.com/M-s-Tech4TIME"
               target="_blank" rel="noopener noreferrer" aria-label="Tech4TIME on GitHub">
              <svg class="icon" aria-hidden="true" focusable="false"><use href="#github"></use></svg>
            </a>
          </li>
        </ul>
      </div>

      <!-- Quick links -->
      <nav class="site-footer__section" aria-labelledby="footer-links-heading">
        <h2 class="site-footer__heading" id="footer-links-heading">Quick Links</h2>
        <ul class="site-footer__list">
          <li><a class="site-footer__link" href="/">Home</a></li>
          <li><a class="site-footer__link" href="/pages/about/">About Us</a></li>
          <li><a class="site-footer__link" href="/pages/company-profile/">Company Profile</a></li>
          <li><a class="site-footer__link" href="/pages/careers/">Careers</a></li>
          <li><a class="site-footer__link" href="/pages/resource-certifications/">Resource Certifications</a></li>
          <li><a class="site-footer__link" href="/pages/branding-and-advertisement/">Branding &amp; Advertisement</a></li>
          <li><a class="site-footer__link" href="/pages/contact/">Contact Us</a></li>
        </ul>
      </nav>

      <!-- Services -->
      <nav class="site-footer__section" aria-labelledby="footer-services-heading">
        <h2 class="site-footer__heading" id="footer-services-heading">Our Services</h2>
        <ul class="site-footer__list">
          <li><a class="site-footer__link" href="/pages/services/">All Services</a></li>
          <li><a class="site-footer__link" href="/pages/services/cybersecurity/">Cybersecurity</a></li>
          <li><a class="site-footer__link" href="/pages/services/software-development/">Software Development</a></li>
          <li><a class="site-footer__link" href="/pages/services/cloud-infrastructure/">Cloud Infrastructure</a></li>
          <li><a class="site-footer__link" href="/pages/services/hr-solutions/">Human Resource Provision</a></li>
          <li><a class="site-footer__link" href="/pages/services/it-equipment-supply/">IT Equipment Supply</a></li>
          <li><a class="site-footer__link" href="/pages/services/it-consultancy-training/">IT Consultancy &amp; Training</a></li>
        </ul>
      </nav>

      <!-- Contact -->
      <div class="site-footer__section">
        <h2 class="site-footer__heading">Contact Info</h2>
        <address class="site-footer__contact">
          <div class="contact-item">
            <svg class="icon contact-item__icon" aria-hidden="true" focusable="false"><use href="#phone"></use></svg>
            <div>
              <span class="contact-item__label">Bangladesh</span>
              <a href="tel:+8801320571562">+880 1320571562</a><br>
              <a href="tel:+8801881873463">+880 1881873463</a><br>
              <a href="tel:+8801847313835">+880 1847313835</a>
              <span class="contact-item__note">Sunday – Thursday</span>

              <span class="contact-item__label">Malaysia</span>
              <a href="tel:+60198527096">+60 198527096</a>
              <span class="contact-item__note">Monday – Friday</span>
            </div>
          </div>

          <div class="contact-item">
            <svg class="icon contact-item__icon" aria-hidden="true" focusable="false"><use href="#envelope"></use></svg>
            <a href="mailto:info@tech4time.bd">info@tech4time.bd</a>
          </div>

          <div class="contact-item">
            <svg class="icon contact-item__icon" aria-hidden="true" focusable="false"><use href="#map-marker-alt"></use></svg>
            <div>
              <span class="contact-item__label">Bangladesh</span>
              278/3, Manikdi, Dhaka - 1206<br>
              <span class="contact-item__label">Malaysia</span>
              68100 Batu Caves, Selangor, Malaysia<br>
              <span class="contact-item__label">Belgium</span>
              367, Avenue Louise, Brussels, Belgium
            </div>
          </div>

          <div class="contact-item">
            <svg class="icon contact-item__icon" aria-hidden="true" focusable="false"><use href="#clock"></use></svg>
            <div>
              <span class="contact-item__label">Bangladesh Office</span>
              Sun – Thu: 9:00 AM – 6:00 PM
              <span class="contact-item__label">Malaysia Office</span>
              Mon – Fri: 9:00 AM – 6:00 PM
            </div>
          </div>
        </address>
      </div>
    </div>

    <div class="site-footer__bottom">
      <div>
        <p class="site-footer__copyright">
          &copy; <span data-current-year>2026</span> Tech4TIME. All rights reserved.
        </p>
        <ul class="site-footer__legal">
          <li><a class="site-footer__link" href="/pages/privacy-policy/">Privacy Policy</a></li>
        </ul>
      </div>

      <button class="btn btn--icon" type="button" data-back-to-top aria-label="Back to top">
        <svg class="icon" aria-hidden="true" focusable="false"><use href="#arrow-up"></use></svg>
      </button>
    </div>
  </div>
</footer>

<!--dock:start-->
<!-- The small-screen navigation: a floating bar within thumb reach, and a card
     of sections that rises above it. It replaces the header hamburger below
     64em, where the header nav is hidden.

     It sits here, a sibling of <header> and <footer>, and NOT inside the
     header. That is deliberate: .site-header paints a backdrop-filter, and an
     element with one becomes the containing block for its position:fixed
     descendants — which is what clamped the old drawer to the header's own
     box. Keeping this outside the header keeps its containing block the
     viewport. See the note in layout.css.

     The four destinations in the bar are real links, so they still work with
     JavaScript disabled. Only the panel needs script, and the footer carries
     the same links again for that case. -->
<div class="dock" data-dock>
  <div class="dock__panel" id="dock-panel" data-nav-drawer data-open="false">

    <!-- Circuit traces down both edges of the card, drawn rather than
         photographed: about a kilobyte instead of a hundred, it tints itself
         from the theme tokens, and it needs no art direction when the palette
         changes.

         aria-hidden on both: they say nothing, they are texture beside the
         list. The animation is described where it is written, in
         components.css.

         The path data is declared once, in the left column's <defs>. SVG ids
         are document-scoped, so the right column references the same paths and
         is flipped in CSS — one set of geometry, two sides. The two run on
         different durations so the mirror image never moves in lockstep with
         its twin. -->
    <div class="dock__circuit dock__circuit--left" aria-hidden="true">
      <svg viewBox="0 0 80 320" preserveAspectRatio="xMidYMid slice" focusable="false">
        <defs>
          <path id="dock-t-a" d="M10 0v72h34v56h28"/>
          <path id="dock-t-b" d="M10 320v-72h28v-52"/>
          <path id="dock-t-c" d="M34 0v44h32v60"/>
          <path id="dock-t-d" d="M10 128h20v48h32v56"/>
          <path id="dock-t-e" d="M72 320v-44H26v-62"/>
          <path id="dock-t-f" d="M66 0v32H10v64"/>
          <path id="dock-t-g" d="M46 320v-28H10"/>
          <path id="dock-t-h" d="M72 152H50V88"/>
        </defs>

        <g class="dock__wires" fill="none" stroke-linecap="round" stroke-linejoin="round">
          <use href="#dock-t-a"/><use href="#dock-t-b"/>
          <use href="#dock-t-c"/><use href="#dock-t-d"/>
          <use href="#dock-t-e"/><use href="#dock-t-f"/>
          <use href="#dock-t-g"/><use href="#dock-t-h"/>
        </g>

        <g class="dock__charges" fill="none" stroke-linecap="round" stroke-linejoin="round">
          <use class="dock__charge dock__charge--a" href="#dock-t-a"/>
          <use class="dock__charge dock__charge--b" href="#dock-t-b"/>
          <use class="dock__charge dock__charge--c" href="#dock-t-c"/>
          <use class="dock__charge dock__charge--d" href="#dock-t-d"/>
          <use class="dock__charge dock__charge--e" href="#dock-t-e"/>
          <use class="dock__charge dock__charge--f" href="#dock-t-f"/>
          <use class="dock__charge dock__charge--g" href="#dock-t-g"/>
          <use class="dock__charge dock__charge--h" href="#dock-t-h"/>
        </g>

        <g class="dock__nodes">
          <circle class="dock__node dock__node--a" cx="44" cy="72" r="3.5"/>
          <circle class="dock__node dock__node--b" cx="44" cy="128" r="3"/>
          <circle class="dock__node dock__node--c" cx="66" cy="104" r="3.5"/>
          <circle class="dock__node dock__node--d" cx="30" cy="176" r="3"/>
          <circle class="dock__node dock__node--e" cx="62" cy="232" r="3.5"/>
          <circle class="dock__node dock__node--f" cx="26" cy="214" r="3"/>
          <circle class="dock__node dock__node--g" cx="10" cy="96" r="3.5"/>
          <circle class="dock__node dock__node--h" cx="50" cy="88" r="3"/>
          <circle class="dock__node dock__node--i" cx="38" cy="248" r="3"/>
          <circle class="dock__node dock__node--j" cx="10" cy="292" r="3.5"/>
        </g>
      </svg>
    </div>

    <div class="dock__circuit dock__circuit--right" aria-hidden="true">
      <svg viewBox="0 0 80 320" preserveAspectRatio="xMidYMid slice" focusable="false">
        <g class="dock__wires" fill="none" stroke-linecap="round" stroke-linejoin="round">
          <use href="#dock-t-a"/><use href="#dock-t-b"/>
          <use href="#dock-t-c"/><use href="#dock-t-d"/>
          <use href="#dock-t-e"/><use href="#dock-t-f"/>
          <use href="#dock-t-g"/><use href="#dock-t-h"/>
        </g>

        <g class="dock__charges" fill="none" stroke-linecap="round" stroke-linejoin="round">
          <use class="dock__charge dock__charge--k" href="#dock-t-a"/>
          <use class="dock__charge dock__charge--l" href="#dock-t-b"/>
          <use class="dock__charge dock__charge--m" href="#dock-t-c"/>
          <use class="dock__charge dock__charge--n" href="#dock-t-d"/>
          <use class="dock__charge dock__charge--o" href="#dock-t-e"/>
          <use class="dock__charge dock__charge--p" href="#dock-t-f"/>
          <use class="dock__charge dock__charge--q" href="#dock-t-g"/>
          <use class="dock__charge dock__charge--r" href="#dock-t-h"/>
        </g>

        <g class="dock__nodes">
          <circle class="dock__node dock__node--k" cx="44" cy="72" r="3.5"/>
          <circle class="dock__node dock__node--l" cx="44" cy="128" r="3"/>
          <circle class="dock__node dock__node--m" cx="66" cy="104" r="3.5"/>
          <circle class="dock__node dock__node--n" cx="30" cy="176" r="3"/>
          <circle class="dock__node dock__node--o" cx="62" cy="232" r="3.5"/>
          <circle class="dock__node dock__node--p" cx="26" cy="214" r="3"/>
          <circle class="dock__node dock__node--q" cx="10" cy="96" r="3.5"/>
          <circle class="dock__node dock__node--r" cx="50" cy="88" r="3"/>
        </g>
      </svg>
    </div>

    <nav class="dock__nav" aria-label="Site sections">
      <ul class="dock__list">
        <li>
          <a class="dock__item" href="/">
            <span class="dock__item-title">Home</span>
            <span class="dock__item-desc">Start here</span>
          </a>
        </li>
        <li>
          <a class="dock__item" href="/pages/about/">
            <span class="dock__item-title">About Us</span>
            <span class="dock__item-desc">Who we are and how we work</span>
          </a>
        </li>
        <li>
          <a class="dock__item" href="/pages/services/" aria-current="page">
            <span class="dock__item-title">Services</span>
            <span class="dock__item-desc">Security, development, cloud and people</span>
          </a>
        </li>
        <li>
          <a class="dock__item" href="/pages/company-profile/">
            <span class="dock__item-title">Company Profile</span>
            <span class="dock__item-desc">Milestones, clients and the technology we use</span>
          </a>
        </li>
        <li>
          <a class="dock__item" href="/pages/careers/">
            <span class="dock__item-title">Careers</span>
            <span class="dock__item-desc">Open roles, and speculative applications</span>
          </a>
        </li>
        <li>
          <a class="dock__item" href="/pages/contact/">
            <span class="dock__item-title">Contact Us</span>
            <span class="dock__item-desc">Reach us any way you prefer</span>
          </a>
        </li>
      </ul>
    </nav>
  </div>

  <nav class="dock__bar" aria-label="Quick navigation">
    <a class="dock__key" href="/">
      <svg class="icon" aria-hidden="true" focusable="false"><use href="#home"></use></svg>
      <span class="dock__key-label">Home</span>
    </a>
    <a class="dock__key" href="/pages/services/" aria-current="page">
      <svg class="icon" aria-hidden="true" focusable="false"><use href="#cogs"></use></svg>
      <span class="dock__key-label">Services</span>
    </a>

    <!-- The one emphasised destination. It is a link like its neighbours, not
         a button: it goes to a page, and a <button> would be a lie to anything
         reading the markup rather than looking at it. The emphasis is the
         filled disc. -->
    <a class="dock__key dock__key--contact" href="/pages/contact/">
      <span class="dock__key-disc">
        <svg class="icon" aria-hidden="true" focusable="false"><use href="#comment-alt"></use></svg>
      </span>
      <span class="dock__key-label">Contact</span>
    </a>

    <a class="dock__key" href="/pages/company-profile/">
      <svg class="icon" aria-hidden="true" focusable="false"><use href="#building"></use></svg>
      <span class="dock__key-label">Profile</span>
    </a>
    <button
      class="dock__key dock__key--menu"
      type="button"
      data-nav-toggle
      aria-controls="dock-panel"
      aria-expanded="false">
      <svg class="icon dock__icon--open" aria-hidden="true" focusable="false"><use href="#grid-dots"></use></svg>
      <svg class="icon dock__icon--close" aria-hidden="true" focusable="false"><use href="#times"></use></svg>
      <span class="dock__key-label">Menu</span>
    </button>
  </nav>
</div>
<!--dock:end-->

<!-- Deferred so nothing blocks rendering. Order matters only in that main.js
     runs last: each module registers itself on window.Tech4Time, and main.js
     calls their init(). Pages that need no forms can omit forms.js. -->
<script src="/assets/js/theme-toggle.js" defer></script>
<script src="/assets/js/nav.js" defer></script>
<script src="/assets/js/animations.js" defer></script>
<script src="/assets/js/forms.js" defer></script>
<script src="/assets/js/dashboard.js" defer></script>
<script src="/assets/js/tech-sphere.js" defer></script>
<!-- Versioned for the same reason the stylesheets are, and with a sharper
     edge: MODULES in this file is a hardcoded allow list, so a stale copy
     silently skips every module added since — no error, no console line,
     just a feature that is not there. -->
<script src="/assets/js/circuit.js?v=2" defer></script>
<script src="/assets/js/main.js?v=3" defer></script>
</body>
</html>
