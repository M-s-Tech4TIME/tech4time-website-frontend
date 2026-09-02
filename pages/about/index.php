<?php
/**
 * Tech4TIME — the about page.
 *
 * PHP, and not HTML, because its content is edited at admin.tech4time.bd and
 * arrives here as content/about.json. Rendered on the server, on this request,
 * from a file on this disk: no fetch, no framework, and the page works with
 * JavaScript switched off. See ADR 0003 and ADR 0010.
 *
 * Everything editable goes through h(). The one exception is a story
 * section's prose, which is sanitised HTML — printed bare, with the comment
 * beside it that says why that is safe.
 *
 * The header, footer, dock and hero circuit are shared markup and stay
 * literal; tools/check_shared_markup.py holds them byte-identical to
 * tools/templates/. The scroll-reveal markers below are hand-maintained,
 * because tools/apply_reveals.py reports and skips any page that builds part
 * of itself with a loop, which this one now does.
 */

declare(strict_types=1);

require __DIR__ . '/../../lib/about.php';

$data = about_load();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= h($data['meta']['title']) ?></title>
<meta name="description" content="<?= h($data['meta']['description']) ?>">
<link rel="canonical" href="https://tech4time.bd/pages/about/">

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
<meta property="og:title" content="<?= h($data['meta']['share_title']) ?>">
<meta property="og:description" content="<?= h($data['meta']['description']) ?>">
<meta property="og:url" content="https://tech4time.bd/pages/about/">
<meta property="og:image" content="https://tech4time.bd/assets/images/og/tech4time-og.png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Tech4TIME — Orchestrating Technology with Time">

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= h($data['meta']['share_title']) ?>">
<meta name="twitter:description" content="<?= h($data['meta']['description']) ?>">
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

<!-- Styles, in cascade order -->
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/theme.css">
<link rel="stylesheet" href="/assets/css/layout.css">
<link rel="stylesheet" href="/assets/css/components.css">
<link rel="stylesheet" href="/assets/css/animations.css">
<link rel="stylesheet" href="/assets/css/pages/about.css">

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

<!-- Breadcrumb trail for this page. -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    { "@type": "ListItem", "position": 1, "name": "Home", "item": "https://tech4time.bd/" },
    { "@type": "ListItem", "position": 2, "name": <?= json_encode($data['hero']['title'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>, "item": "https://tech4time.bd/pages/about/" }
  ]
}
</script>

<!-- Page type, tied to the Organization it describes. Generated, so the
     name and the description cannot drift from the <head> above. -->
<script type="application/ld+json">
<?= json_encode(about_page_schema($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
</script>
</head>

<body class="page">
<!-- icon-sprite:start -->
<svg class="icon-sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <symbol id="arrow-right" viewBox="0 0 448 512"><path d="M438.6 278.6c12.5-12.5 12.5-32.8 0-45.3l-160-160c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L338.8 224 32 224c-17.7 0-32 14.3-32 32s14.3 32 32 32l306.7 0L233.4 393.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0l160-160z"/></symbol>
  <symbol id="arrow-up" viewBox="0 0 384 512"><path d="M214.6 41.4c-12.5-12.5-32.8-12.5-45.3 0l-160 160c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L160 141.2V448c0 17.7 14.3 32 32 32s32-14.3 32-32V141.2L329.4 246.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3l-160-160z"/></symbol>
  <symbol id="building" viewBox="0 0 384 512"><path d="M48 0C21.5 0 0 21.5 0 48V464c0 26.5 21.5 48 48 48h96V432c0-26.5 21.5-48 48-48s48 21.5 48 48v80h96c26.5 0 48-21.5 48-48V48c0-26.5-21.5-48-48-48H48zM64 240c0-8.8 7.2-16 16-16h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H80c-8.8 0-16-7.2-16-16V240zm112-16h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H176c-8.8 0-16-7.2-16-16V240c0-8.8 7.2-16 16-16zm80 16c0-8.8 7.2-16 16-16h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H272c-8.8 0-16-7.2-16-16V240zM80 96h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H80c-8.8 0-16-7.2-16-16V112c0-8.8 7.2-16 16-16zm80 16c0-8.8 7.2-16 16-16h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H176c-8.8 0-16-7.2-16-16V112zM272 96h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H272c-8.8 0-16-7.2-16-16V112c0-8.8 7.2-16 16-16z"/></symbol>
  <symbol id="check-circle" viewBox="0 0 512 512"><path d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM369 209L241 337c-9.4 9.4-24.6 9.4-33.9 0l-64-64c-9.4-9.4-9.4-24.6 0-33.9s24.6-9.4 33.9 0l47 47L335 175c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9z"/></symbol>
  <symbol id="chevron-left" viewBox="0 0 320 512"><path d="M9.4 233.4c-12.5 12.5-12.5 32.8 0 45.3l192 192c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L77.3 256 246.6 86.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0l-192 192z"/></symbol>
  <symbol id="chevron-right" viewBox="0 0 320 512"><path d="M310.6 233.4c12.5 12.5 12.5 32.8 0 45.3l-192 192c-12.5 12.5-32.8 12.5-45.3 0s-12.5-32.8 0-45.3L242.7 256 73.4 86.6c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0l192 192z"/></symbol>
  <symbol id="clock" viewBox="0 0 512 512"><path d="M256 0a256 256 0 1 1 0 512A256 256 0 1 1 256 0zM232 120V256c0 8 4 15.5 10.7 20l96 64c11 7.4 25.9 4.4 33.3-6.7s4.4-25.9-6.7-33.3L280 243.2V120c0-13.3-10.7-24-24-24s-24 10.7-24 24z"/></symbol>
  <symbol id="cloud" viewBox="0 0 640 512"><path d="M0 336c0 79.5 64.5 144 144 144H512c70.7 0 128-57.3 128-128c0-61.9-44-113.6-102.4-125.4c4.1-10.7 6.4-22.4 6.4-34.6c0-53-43-96-96-96c-19.7 0-38.1 6-53.3 16.2C367 64.2 315.3 32 256 32C167.6 32 96 103.6 96 192c0 2.7 .1 5.4 .2 8.1C40.2 219.8 0 273.2 0 336z"/></symbol>
  <symbol id="code" viewBox="0 0 640 512"><path d="M392.8 1.2c-17-4.9-34.7 5-39.6 22l-128 448c-4.9 17 5 34.7 22 39.6s34.7-5 39.6-22l128-448c4.9-17-5-34.7-22-39.6zm80.6 120.1c-12.5 12.5-12.5 32.8 0 45.3L562.7 256l-89.4 89.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0l112-112c12.5-12.5 12.5-32.8 0-45.3l-112-112c-12.5-12.5-32.8-12.5-45.3 0zm-306.7 0c-12.5-12.5-32.8-12.5-45.3 0l-112 112c-12.5 12.5-12.5 32.8 0 45.3l112 112c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L77.3 256l89.4-89.4c12.5-12.5 12.5-32.8 0-45.3z"/></symbol>
  <symbol id="cogs" viewBox="0 0 640 512"><path d="M308.5 135.3c7.1-6.3 9.9-16.2 6.2-25c-2.3-5.3-4.8-10.5-7.6-15.5L304 89.4c-3-5-6.3-9.9-9.8-14.6c-5.7-7.6-15.7-10.1-24.7-7.1l-28.2 9.3c-10.7-8.8-23-16-36.2-20.9L199 27.1c-1.9-9.3-9.1-16.7-18.5-17.8C173.9 8.4 167.2 8 160.4 8h-.7c-6.8 0-13.5 .4-20.1 1.2c-9.4 1.1-16.6 8.6-18.5 17.8L115 56.1c-13.3 5-25.5 12.1-36.2 20.9L50.5 67.8c-9-3-19-.5-24.7 7.1c-3.5 4.7-6.8 9.6-9.9 14.6l-3 5.3c-2.8 5-5.3 10.2-7.6 15.6c-3.7 8.7-.9 18.6 6.2 25l22.2 19.8C32.6 161.9 32 168.9 32 176s.6 14.1 1.7 20.9L11.5 216.7c-7.1 6.3-9.9 16.2-6.2 25c2.3 5.3 4.8 10.5 7.6 15.6l3 5.2c3 5.1 6.3 9.9 9.9 14.6c5.7 7.6 15.7 10.1 24.7 7.1l28.2-9.3c10.7 8.8 23 16 36.2 20.9l6.1 29.1c1.9 9.3 9.1 16.7 18.5 17.8c6.7 .8 13.5 1.2 20.4 1.2s13.7-.4 20.4-1.2c9.4-1.1 16.6-8.6 18.5-17.8l6.1-29.1c13.3-5 25.5-12.1 36.2-20.9l28.2 9.3c9 3 19 .5 24.7-7.1c3.5-4.7 6.8-9.5 9.8-14.6l3.1-5.4c2.8-5 5.3-10.2 7.6-15.5c3.7-8.7 .9-18.6-6.2-25l-22.2-19.8c1.1-6.8 1.7-13.8 1.7-20.9s-.6-14.1-1.7-20.9l22.2-19.8zM112 176a48 48 0 1 1 96 0 48 48 0 1 1 -96 0zM504.7 500.5c6.3 7.1 16.2 9.9 25 6.2c5.3-2.3 10.5-4.8 15.5-7.6l5.4-3.1c5-3 9.9-6.3 14.6-9.8c7.6-5.7 10.1-15.7 7.1-24.7l-9.3-28.2c8.8-10.7 16-23 20.9-36.2l29.1-6.1c9.3-1.9 16.7-9.1 17.8-18.5c.8-6.7 1.2-13.5 1.2-20.4s-.4-13.7-1.2-20.4c-1.1-9.4-8.6-16.6-17.8-18.5L583.9 307c-5-13.3-12.1-25.5-20.9-36.2l9.3-28.2c3-9 .5-19-7.1-24.7c-4.7-3.5-9.6-6.8-14.6-9.9l-5.3-3c-5-2.8-10.2-5.3-15.6-7.6c-8.7-3.7-18.6-.9-25 6.2l-19.8 22.2c-6.8-1.1-13.8-1.7-20.9-1.7s-14.1 .6-20.9 1.7l-19.8-22.2c-6.3-7.1-16.2-9.9-25-6.2c-5.3 2.3-10.5 4.8-15.6 7.6l-5.2 3c-5.1 3-9.9 6.3-14.6 9.9c-7.6 5.7-10.1 15.7-7.1 24.7l9.3 28.2c-8.8 10.7-16 23-20.9 36.2L315.1 313c-9.3 1.9-16.7 9.1-17.8 18.5c-.8 6.7-1.2 13.5-1.2 20.4s.4 13.7 1.2 20.4c1.1 9.4 8.6 16.6 17.8 18.5l29.1 6.1c5 13.3 12.1 25.5 20.9 36.2l-9.3 28.2c-3 9-.5 19 7.1 24.7c4.7 3.5 9.5 6.8 14.6 9.8l5.4 3.1c5 2.8 10.2 5.3 15.5 7.6c8.7 3.7 18.6 .9 25-6.2l19.8-22.2c6.8 1.1 13.8 1.7 20.9 1.7s14.1-.6 20.9-1.7l19.8 22.2zM464 304a48 48 0 1 1 0 96 48 48 0 1 1 0-96z"/></symbol>
  <symbol id="comment-alt" viewBox="0 0 512 512"><path d="M64 0C28.7 0 0 28.7 0 64V352c0 35.3 28.7 64 64 64h96v80c0 6.1 3.4 11.6 8.8 14.3s11.9 2.1 16.8-1.5L309.3 416H448c35.3 0 64-28.7 64-64V64c0-35.3-28.7-64-64-64H64z"/></symbol>
  <symbol id="envelope" viewBox="0 0 512 512"><path d="M48 64C21.5 64 0 85.5 0 112c0 15.1 7.1 29.3 19.2 38.4L236.8 313.6c11.4 8.5 27 8.5 38.4 0L492.8 150.4c12.1-9.1 19.2-23.3 19.2-38.4c0-26.5-21.5-48-48-48H48zM0 176V384c0 35.3 28.7 64 64 64H448c35.3 0 64-28.7 64-64V176L294.4 339.2c-22.8 17.1-54 17.1-76.8 0L0 176z"/></symbol>
  <symbol id="eye" viewBox="0 0 576 512"><path d="M288 32c-80.8 0-145.5 36.8-192.6 80.6C48.6 156 17.3 208 2.5 243.7c-3.3 7.9-3.3 16.7 0 24.6C17.3 304 48.6 356 95.4 399.4C142.5 443.2 207.2 480 288 480s145.5-36.8 192.6-80.6c46.8-43.5 78.1-95.4 93-131.1c3.3-7.9 3.3-16.7 0-24.6c-14.9-35.7-46.2-87.7-93-131.1C433.5 68.8 368.8 32 288 32zM144 256a144 144 0 1 1 288 0 144 144 0 1 1 -288 0zm144-64c0 35.3-28.7 64-64 64c-7.1 0-13.9-1.2-20.3-3.3c-5.5-1.8-11.9 1.6-11.7 7.4c.3 6.9 1.3 13.8 3.2 20.7c13.7 51.2 66.4 81.6 117.6 67.9s81.6-66.4 67.9-117.6c-11.1-41.5-47.8-69.4-88.6-71.1c-5.8-.2-9.2 6.1-7.4 11.7c2.1 6.4 3.3 13.2 3.3 20.3z"/></symbol>
  <symbol id="github" viewBox="0 0 496 512"><path d="M165.9 397.4c0 2-2.3 3.6-5.2 3.6-3.3.3-5.6-1.3-5.6-3.6 0-2 2.3-3.6 5.2-3.6 3-.3 5.6 1.3 5.6 3.6zm-31.1-4.5c-.7 2 1.3 4.3 4.3 4.9 2.6 1 5.6 0 6.2-2s-1.3-4.3-4.3-5.2c-2.6-.7-5.5.3-6.2 2.3zm44.2-1.7c-2.9.7-4.9 2.6-4.6 4.9.3 2 2.9 3.3 5.9 2.6 2.9-.7 4.9-2.6 4.6-4.6-.3-1.9-3-3.2-5.9-2.9zM244.8 8C106.1 8 0 113.3 0 252c0 110.9 69.8 205.8 169.5 239.2 12.8 2.3 17.3-5.6 17.3-12.1 0-6.2-.3-40.4-.3-61.4 0 0-70 15-84.7-29.8 0 0-11.4-29.1-27.8-36.6 0 0-22.9-15.7 1.6-15.4 0 0 24.9 2 38.6 25.8 21.9 38.6 58.6 27.5 72.9 20.9 2.3-16 8.8-27.1 16-33.7-55.9-6.2-112.3-14.3-112.3-110.5 0-27.5 7.6-41.3 23.6-58.9-2.6-6.5-11.1-33.3 2.6-67.9 20.9-6.5 69 27 69 27 20-5.6 41.5-8.5 62.8-8.5s42.8 2.9 62.8 8.5c0 0 48.1-33.6 69-27 13.7 34.7 5.2 61.4 2.6 67.9 16 17.7 25.8 31.5 25.8 58.9 0 96.5-58.9 104.2-114.8 110.5 9.2 7.9 17 22.9 17 46.4 0 33.7-.3 75.4-.3 83.6 0 6.5 4.6 14.4 17.3 12.1C428.2 457.8 496 362.9 496 252 496 113.3 383.5 8 244.8 8zM97.2 352.9c-1.3 1-1 3.3.7 5.2 1.6 1.6 3.9 2.3 5.2 1 1.3-1 1-3.3-.7-5.2-1.6-1.6-3.9-2.3-5.2-1zm-10.8-8.1c-.7 1.3.3 2.9 2.3 3.9 1.6 1 3.6.7 4.3-.7.7-1.3-.3-2.9-2.3-3.9-2-.6-3.6-.3-4.3.7zm32.4 35.6c-1.6 1.3-1 4.3 1.3 6.2 2.3 2.3 5.2 2.6 6.5 1 1.3-1.3.7-4.3-1.3-6.2-2.2-2.3-5.2-2.6-6.5-1zm-11.4-14.7c-1.6 1-1.6 3.6 0 5.9 1.6 2.3 4.3 3.3 5.6 2.3 1.6-1.3 1.6-3.9 0-6.2-1.4-2.3-4-3.3-5.6-2z"/></symbol>
  <symbol id="graduation-cap" viewBox="0 0 640 512"><path d="M320 32c-8.1 0-16.1 1.4-23.7 4.1L15.8 137.4C6.3 140.9 0 149.9 0 160s6.3 19.1 15.8 22.6l57.9 20.9C57.3 229.3 48 259.8 48 291.9v28.1c0 28.4-10.8 57.7-22.3 80.8c-6.5 13-13.9 25.8-22.5 37.6C0 442.7-.9 448.3 .9 453.4s6 8.9 11.2 10.2l64 16c4.2 1.1 8.7 .3 12.4-2s6.3-6.1 7.1-10.4c8.6-42.8 4.3-81.2-2.1-108.7C90.3 344.3 86 329.8 80 316.5V291.9c0-30.2 10.2-58.7 27.9-81.5c12.9-15.5 29.6-28 49.2-35.7l157-61.7c8.2-3.2 17.5 .8 20.7 9s-.8 17.5-9 20.7l-157 61.7c-12.4 4.9-23.3 12.4-32.2 21.6l159.6 57.6c7.6 2.7 15.6 4.1 23.7 4.1s16.1-1.4 23.7-4.1L624.2 182.6c9.5-3.4 15.8-12.5 15.8-22.6s-6.3-19.1-15.8-22.6L343.7 36.1C336.1 33.4 328.1 32 320 32zM128 408c0 35.3 86 72 192 72s192-36.7 192-72L496.7 262.6 354.5 314c-11.1 4-22.8 6-34.5 6s-23.5-2-34.5-6L143.3 262.6 128 408z"/></symbol>
  <symbol id="grid-dots" viewBox="0 0 24 24"><circle cx="5" cy="5" r="2.1"/><circle cx="12" cy="5" r="2.1"/><circle cx="19" cy="5" r="2.1"/><circle cx="5" cy="12" r="2.1"/><circle cx="12" cy="12" r="2.1"/><circle cx="19" cy="12" r="2.1"/><circle cx="5" cy="19" r="2.1"/><circle cx="12" cy="19" r="2.1"/><circle cx="19" cy="19" r="2.1"/></symbol>
  <symbol id="handshake" viewBox="0 0 640 512"><path d="M323.4 85.2l-96.8 78.4c-16.1 13-19.2 36.4-7 53.1c12.9 17.8 38 21.3 55.3 7.8l99.3-77.2c7-5.4 17-4.2 22.5 2.8s4.2 17-2.8 22.5l-20.9 16.2L512 316.8V128h-.7l-3.9-2.5L434.8 79c-15.3-9.8-33.2-15-51.4-15c-21.8 0-43 7.5-60 21.2zm22.8 124.4l-51.7 40.2C263 274.4 217.3 268 193.7 235.6c-22.2-30.5-16.6-73.1 12.7-96.8l83.2-67.3c-11.6-4.9-24.1-7.4-36.8-7.4C234 64 215.7 69.6 200 80l-72 48V352h28.2l91.4 83.4c19.6 17.9 49.9 16.5 67.8-3.1c5.5-6.1 9.2-13.2 11.1-20.6l17 15.6c19.5 17.9 49.9 16.6 67.8-2.9c4.5-4.9 7.8-10.6 9.9-16.5c19.4 13 45.8 10.3 62.1-7.5c17.9-19.5 16.6-49.9-2.9-67.8l-134.2-123zM16 128c-8.8 0-16 7.2-16 16V352c0 17.7 14.3 32 32 32H64c17.7 0 32-14.3 32-32V128H16zM48 320a16 16 0 1 1 0 32 16 16 0 1 1 0-32zM544 128V352c0 17.7 14.3 32 32 32h32c17.7 0 32-14.3 32-32V144c0-8.8-7.2-16-16-16H544zm32 208a16 16 0 1 1 32 0 16 16 0 1 1 -32 0z"/></symbol>
  <symbol id="home" viewBox="0 0 576 512"><path d="M575.8 255.5c0 18-15 32.1-32 32.1h-32l.7 160.2c0 2.7-.2 5.4-.5 8.1V472c0 22.1-17.9 40-40 40H456c-1.1 0-2.2 0-3.3-.1c-1.4 .1-2.8 .1-4.2 .1H416 392c-22.1 0-40-17.9-40-40V448 384c0-17.7-14.3-32-32-32H256c-17.7 0-32 14.3-32 32v64 24c0 22.1-17.9 40-40 40H160 128.1c-1.5 0-3-.1-4.5-.2c-1.2 .1-2.4 .2-3.6 .2H104c-22.1 0-40-17.9-40-40V360c0-.9 0-1.9 .1-2.8V287.6H32c-18 0-32-14-32-32.1c0-9 3-17 10-24L266.4 8c7-7 15-8 22-8s15 2 21 7L564.8 231.5c8 7 12 15 11 24z"/></symbol>
  <symbol id="layer-group" viewBox="0 0 576 512"><path d="M264.5 5.2c14.9-6.9 32.1-6.9 47 0l218.6 101c8.5 3.9 13.9 12.4 13.9 21.8s-5.4 17.9-13.9 21.8l-218.6 101c-14.9 6.9-32.1 6.9-47 0L45.9 149.8C37.4 145.8 32 137.3 32 128s5.4-17.9 13.9-21.8L264.5 5.2zM476.9 209.6l53.2 24.6c8.5 3.9 13.9 12.4 13.9 21.8s-5.4 17.9-13.9 21.8l-218.6 101c-14.9 6.9-32.1 6.9-47 0L45.9 277.8C37.4 273.8 32 265.3 32 256s5.4-17.9 13.9-21.8l53.2-24.6 152 70.2c23.4 10.8 50.4 10.8 73.8 0l152-70.2zm-152 198.2l152-70.2 53.2 24.6c8.5 3.9 13.9 12.4 13.9 21.8s-5.4 17.9-13.9 21.8l-218.6 101c-14.9 6.9-32.1 6.9-47 0L45.9 405.8C37.4 401.8 32 393.3 32 384s5.4-17.9 13.9-21.8l53.2-24.6 152 70.2c23.4 10.8 50.4 10.8 73.8 0z"/></symbol>
  <symbol id="lightbulb" viewBox="0 0 384 512"><path d="M272 384c9.6-31.9 29.5-59.1 49.2-86.2l0 0c5.2-7.1 10.4-14.2 15.4-21.4c19.8-28.5 31.4-63 31.4-100.3C368 78.8 289.2 0 192 0S16 78.8 16 176c0 37.3 11.6 71.9 31.4 100.3c5 7.2 10.2 14.3 15.4 21.4l0 0c19.8 27.1 39.7 54.4 49.2 86.2H272zM192 512c44.2 0 80-35.8 80-80V416H112v16c0 44.2 35.8 80 80 80zM112 176c0 8.8-7.2 16-16 16s-16-7.2-16-16c0-61.9 50.1-112 112-112c8.8 0 16 7.2 16 16s-7.2 16-16 16c-44.2 0-80 35.8-80 80z"/></symbol>
  <symbol id="linkedin" viewBox="0 0 448 512"><path d="M416 32H31.9C14.3 32 0 46.5 0 64.3v383.4C0 465.5 14.3 480 31.9 480H416c17.6 0 32-14.5 32-32.3V64.3c0-17.8-14.4-32.3-32-32.3zM135.4 416H69V202.2h66.5V416zm-33.2-243c-21.3 0-38.5-17.3-38.5-38.5S80.9 96 102.2 96c21.2 0 38.5 17.3 38.5 38.5 0 21.3-17.2 38.5-38.5 38.5zm282.1 243h-66.4V312c0-24.8-.5-56.7-34.5-56.7-34.6 0-39.9 27-39.9 54.9V416h-66.4V202.2h63.7v29.2h.9c8.9-16.8 30.6-34.5 62.9-34.5 67.2 0 79.7 44.3 79.7 101.9V416z"/></symbol>
  <symbol id="lock" viewBox="0 0 448 512"><path d="M144 144v48H304V144c0-44.2-35.8-80-80-80s-80 35.8-80 80zM80 192V144C80 64.5 144.5 0 224 0s144 64.5 144 144v48h16c35.3 0 64 28.7 64 64V448c0 35.3-28.7 64-64 64H64c-35.3 0-64-28.7-64-64V256c0-35.3 28.7-64 64-64H80z"/></symbol>
  <symbol id="map-marker-alt" viewBox="0 0 384 512"><path d="M215.7 499.2C267 435 384 279.4 384 192C384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2c12.3 15.3 35.1 15.3 47.4 0zM192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128z"/></symbol>
  <symbol id="moon" viewBox="0 0 384 512"><path d="M223.5 32C100 32 0 132.3 0 256S100 480 223.5 480c60.6 0 115.5-24.2 155.8-63.4c5-4.9 6.3-12.5 3.1-18.7s-10.1-9.7-17-8.5c-9.8 1.7-19.8 2.6-30.1 2.6c-96.9 0-175.5-78.8-175.5-176c0-65.8 36-123.1 89.3-153.3c6.1-3.5 9.2-10.5 7.7-17.3s-7.3-11.9-14.3-12.5c-6.3-.5-12.6-.8-19-.8z"/></symbol>
  <symbol id="pause" viewBox="0 0 24 24"><rect x="6" y="4.5" width="4" height="15" rx="1.4"/><rect x="14" y="4.5" width="4" height="15" rx="1.4"/></symbol>
  <symbol id="phone" viewBox="0 0 512 512"><path d="M164.9 24.6c-7.7-18.6-28-28.5-47.4-23.2l-88 24C12.1 30.2 0 46 0 64C0 311.4 200.6 512 448 512c18 0 33.8-12.1 38.6-29.5l24-88c5.3-19.4-4.6-39.7-23.2-47.4l-96-40c-16.3-6.8-35.2-2.1-46.3 11.6L304.7 368C234.3 334.7 177.3 277.7 144 207.3L193.3 167c13.7-11.2 18.4-30 11.6-46.3l-40-96z"/></symbol>
  <symbol id="play" viewBox="0 0 24 24"><path d="M7.5 4.9v14.2a1 1 0 0 0 1.53.85l11.2-7.1a1 1 0 0 0 0-1.7L9.03 4.05A1 1 0 0 0 7.5 4.9z"/></symbol>
  <symbol id="project-diagram" viewBox="0 0 576 512"><path d="M0 80C0 53.5 21.5 32 48 32h96c26.5 0 48 21.5 48 48V96H384V80c0-26.5 21.5-48 48-48h96c26.5 0 48 21.5 48 48v96c0 26.5-21.5 48-48 48H432c-26.5 0-48-21.5-48-48V160H192v16c0 1.7-.1 3.4-.3 5L272 288h96c26.5 0 48 21.5 48 48v96c0 26.5-21.5 48-48 48H272c-26.5 0-48-21.5-48-48V336c0-1.7 .1-3.4 .3-5L144 224H48c-26.5 0-48-21.5-48-48V80z"/></symbol>
  <symbol id="server" viewBox="0 0 512 512"><path d="M64 32C28.7 32 0 60.7 0 96v64c0 35.3 28.7 64 64 64H448c35.3 0 64-28.7 64-64V96c0-35.3-28.7-64-64-64H64zm280 72a24 24 0 1 1 0 48 24 24 0 1 1 0-48zm48 24a24 24 0 1 1 48 0 24 24 0 1 1 -48 0zM64 288c-35.3 0-64 28.7-64 64v64c0 35.3 28.7 64 64 64H448c35.3 0 64-28.7 64-64V352c0-35.3-28.7-64-64-64H64zm280 72a24 24 0 1 1 0 48 24 24 0 1 1 0-48zm56 24a24 24 0 1 1 48 0 24 24 0 1 1 -48 0z"/></symbol>
  <symbol id="shield-alt" viewBox="0 0 512 512"><path d="M256 0c4.6 0 9.2 1 13.4 2.9L457.7 82.8c22 9.3 38.4 31 38.3 57.2c-.5 99.2-41.3 280.7-213.6 363.2c-16.7 8-36.1 8-52.8 0C57.3 420.7 16.5 239.2 16 140c-.1-26.2 16.3-47.9 38.3-57.2L242.7 2.9C246.8 1 251.4 0 256 0zm0 66.8V444.8C394 378 431.1 230.1 432 141.4L256 66.8l0 0z"/></symbol>
  <symbol id="sun" viewBox="0 0 512 512"><path d="M361.5 1.2c5 2.1 8.6 6.6 9.6 11.9L391 121l107.9 19.8c5.3 1 9.8 4.6 11.9 9.6s1.5 10.7-1.6 15.2L446.9 256l62.3 90.3c3.1 4.5 3.7 10.2 1.6 15.2s-6.6 8.6-11.9 9.6L391 391 371.1 498.9c-1 5.3-4.6 9.8-9.6 11.9s-10.7 1.5-15.2-1.6L256 446.9l-90.3 62.3c-4.5 3.1-10.2 3.7-15.2 1.6s-8.6-6.6-9.6-11.9L121 391 13.1 371.1c-5.3-1-9.8-4.6-11.9-9.6s-1.5-10.7 1.6-15.2L65.1 256 2.8 165.7c-3.1-4.5-3.7-10.2-1.6-15.2s6.6-8.6 11.9-9.6L121 121 140.9 13.1c1-5.3 4.6-9.8 9.6-11.9s10.7-1.5 15.2 1.6L256 65.1 346.3 2.8c4.5-3.1 10.2-3.7 15.2-1.6zM160 256a96 96 0 1 1 192 0 96 96 0 1 1 -192 0zm224 0a128 128 0 1 0 -256 0 128 128 0 1 0 256 0z"/></symbol>
  <symbol id="times" viewBox="0 0 384 512"><path d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z"/></symbol>
  <symbol id="trophy" viewBox="0 0 576 512"><path d="M400 0H176c-26.5 0-48.1 21.8-47.1 48.2c.2 5.3 .4 10.6 .7 15.8H24C10.7 64 0 74.7 0 88c0 92.6 33.5 157 78.5 200.7c44.3 43.1 98.3 64.8 138.1 75.8c23.4 6.5 39.4 26 39.4 45.6c0 20.9-17 37.9-37.9 37.9H192c-17.7 0-32 14.3-32 32s14.3 32 32 32H384c17.7 0 32-14.3 32-32s-14.3-32-32-32H357.9C337 448 320 431 320 410.1c0-19.6 15.9-39.2 39.4-45.6c39.9-11 93.9-32.7 138.2-75.8C542.5 245 576 180.6 576 88c0-13.3-10.7-24-24-24H446.4c.3-5.2 .5-10.4 .7-15.8C448.1 21.8 426.5 0 400 0zM48.9 112h84.4c9.1 90.1 29.2 150.3 51.9 190.6c-24.9-11-50.8-26.5-73.2-48.3c-32-31.1-58-76-63-142.3zM464.1 254.3c-22.4 21.8-48.3 37.3-73.2 48.3c22.7-40.3 42.8-100.5 51.9-190.6h84.4c-5.1 66.3-31.1 111.2-63 142.3z"/></symbol>
  <symbol id="users" viewBox="0 0 640 512"><path d="M144 0a80 80 0 1 1 0 160A80 80 0 1 1 144 0zM512 0a80 80 0 1 1 0 160A80 80 0 1 1 512 0zM0 298.7C0 239.8 47.8 192 106.7 192h42.7c15.9 0 31 3.5 44.6 9.7c-1.3 7.2-1.9 14.7-1.9 22.3c0 38.2 16.8 72.5 43.3 96c-.2 0-.4 0-.7 0H21.3C9.6 320 0 310.4 0 298.7zM405.3 320c-.2 0-.4 0-.7 0c26.6-23.5 43.3-57.8 43.3-96c0-7.6-.7-15-1.9-22.3c13.6-6.3 28.7-9.7 44.6-9.7h42.7C592.2 192 640 239.8 640 298.7c0 11.8-9.6 21.3-21.3 21.3H405.3zM224 224a96 96 0 1 1 192 0 96 96 0 1 1 -192 0zM128 485.3C128 411.7 187.7 352 261.3 352H378.7C452.3 352 512 411.7 512 485.3c0 14.7-11.9 26.7-26.7 26.7H154.7c-14.7 0-26.7-11.9-26.7-26.7z"/></symbol>
</svg>
<!-- icon-sprite:end -->

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
        <li class="site-nav__item"><a class="nav-link" href="/pages/about/" aria-current="page">About Us</a></li>
        <li class="site-nav__item"><a class="nav-link" href="/pages/services/">Services</a></li>
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
         mirrored in CSS. That is not tidiness — a duplicate id is a hard
         failure in audit_pages.py, so four corners cannot each carry a copy.

         The bands use preserveAspectRatio="none" because they run the width of
         the viewport at a fixed height, and stretching a horizontal run just
         makes it a longer run, which is what more circuit board looks like.
         The corners use xMinYMin meet instead: a cluster of 45 degree elbows
         must not shear, and it has to stay pinned to its own corner.

         DENSITY IS FREE, MOTION IS NOT
         A static trace is rasterised once. A charge animates stroke-dashoffset,
         which is not compositor-accelerated and repaints its path every frame,
         on fourteen pages, above the fold, for as long as the tab is open. So
         the drawing is about three times denser than the one it replaces while
         the number of moving parts is nearly unchanged: 24 charges and 24
         nodes against the previous 36 in total.

         Every charge runs on its own prime number of seconds, as the dock's
         does, so the six layers never settle back into an arrangement anyone
         has already seen. -->
    <div class="hero-circuit" aria-hidden="true">
      <svg class="hero-circuit__layer hero-circuit__layer--band-top" viewBox="0 0 1440 120" preserveAspectRatio="none" focusable="false">
        <defs>
        <path id="hc-c0" pathLength="100" d="M16 0L16 30L34 48L34 74L68 74L68 90"/>
        <path id="hc-c1" pathLength="100" d="M38 0L38 18L62 42L62 82"/>
        <path id="hc-c2" pathLength="100" d="M60 0L60 44L86 44L104 62L134 62"/>
        <path id="hc-c3" pathLength="100" d="M82 0L82 26L98 42L98 64L142 64"/>
        <path id="hc-c4" pathLength="100" d="M104 0L104 34L126 34L126 62"/>
        <path id="hc-c5" pathLength="100" d="M126 0L126 16L146 36L180 36"/>
        <path id="hc-c6" pathLength="100" d="M152 0L152 24L170 24L170 38"/>
        <path id="hc-c7" pathLength="100" d="M0 16L30 16L48 34L74 34L74 68L90 68"/>
        <path id="hc-c8" pathLength="100" d="M0 38L18 38L42 62L82 62"/>
        <path id="hc-c9" pathLength="100" d="M0 60L44 60L44 86L62 86L82 106"/>
        <path id="hc-c10" pathLength="100" d="M0 82L26 82L42 98L64 98L64 142"/>
        <path id="hc-c11" pathLength="100" d="M0 104L34 104L34 126L62 126"/>
        <path id="hc-c12" pathLength="100" d="M0 126L16 126L36 146L36 180"/>
        <path id="hc-c13" pathLength="100" d="M0 152L24 152L24 170L38 170"/>
        <g id="hc-corner-wires"><use href="#hc-c0"/><use href="#hc-c1"/><use href="#hc-c2"/><use href="#hc-c3"/><use href="#hc-c4"/><use href="#hc-c5"/><use href="#hc-c6"/><use href="#hc-c7"/><use href="#hc-c8"/><use href="#hc-c9"/><use href="#hc-c10"/><use href="#hc-c11"/><use href="#hc-c12"/><use href="#hc-c13"/></g>
        <g id="hc-corner-pads"><circle cx="68" cy="90" r="3.2"/><circle cx="62" cy="82" r="3.2"/><circle cx="134" cy="62" r="3.2"/><circle cx="142" cy="64" r="3.2"/><circle cx="126" cy="62" r="3.2"/><circle cx="180" cy="36" r="3.2"/><circle cx="170" cy="38" r="3.2"/><circle cx="90" cy="68" r="3.2"/><circle cx="82" cy="62" r="3.2"/><circle cx="82" cy="106" r="3.2"/><circle cx="64" cy="142" r="3.2"/><circle cx="62" cy="126" r="3.2"/><circle cx="36" cy="180" r="3.2"/><circle cx="38" cy="170" r="3.2"/><rect x="128" y="60" width="9" height="6" rx="1"/><rect x="94" y="118" width="6" height="9" rx="1"/><rect x="166" y="140" width="9" height="6" rx="1"/><rect x="60" y="150" width="6" height="9" rx="1"/><rect x="196" y="76" width="9" height="6" rx="1"/><rect x="44" y="92" width="9" height="6" rx="1"/></g>
        <g id="hc-corner-rings"><circle cx="150" cy="40" r="5"/><circle cx="40" cy="150" r="5"/><circle cx="206" cy="118" r="4.2"/><circle cx="118" cy="206" r="4.2"/><path d="M188 22v14"/><path d="M197 22v14"/><path d="M206 22v14"/><path d="M215 22v14"/><path d="M224 22v14"/><path d="M22 188h14"/><path d="M22 197h14"/><path d="M22 206h14"/><path d="M22 215h14"/><path d="M22 224h14"/><path d="M150 96v11"/><path d="M158 96v11"/><path d="M166 96v11"/><path d="M174 96v11"/></g>
        <path id="hc-b0" pathLength="100" d="M-86 132L-4 -12"/>
        <path id="hc-b1" pathLength="100" d="M-12 132L26 66L98 66L136 -12"/>
        <path id="hc-b2" pathLength="100" d="M62 132L144 -12"/>
        <path id="hc-b3" pathLength="100" d="M136 132L170 72L170 38L198 -12"/>
        <path id="hc-b4" pathLength="100" d="M210 132L292 -12"/>
        <path id="hc-b5" pathLength="100" d="M248 66l26 15"/>
        <path id="hc-b6" pathLength="100" d="M284 132L366 -12"/>
        <path id="hc-b7" pathLength="100" d="M358 132L409 43"/>
        <path id="hc-b8" pathLength="100" d="M432 132L470 66L542 66L580 -12"/>
        <path id="hc-b9" pathLength="100" d="M506 132L588 -12"/>
        <path id="hc-b10" pathLength="100" d="M580 132L614 72L614 38L642 -12"/>
        <path id="hc-b11" pathLength="100" d="M646 132L720 2"/>
        <path id="hc-b12" pathLength="100" d="M598 132L672 2L720 2"/>
        <path id="hc-b13" pathLength="100" d="M0 26h150l22 20h206"/>
        <path id="hc-b14" pathLength="100" d="M0 100h96l24-20h180"/>
        <g id="hc-band-half"><use href="#hc-b0"/><use href="#hc-b1"/><use href="#hc-b2"/><use href="#hc-b3"/><use href="#hc-b4"/><use href="#hc-b5"/><use href="#hc-b6"/><use href="#hc-b7"/><use href="#hc-b8"/><use href="#hc-b9"/><use href="#hc-b10"/><use href="#hc-b11"/><use href="#hc-b12"/><use href="#hc-b13"/><use href="#hc-b14"/></g>
        <g id="hc-band-wires"><use href="#hc-band-half"/><use href="#hc-band-half" transform="translate(1440,0) scale(-1,1)"/></g>
        <g id="hc-band-pads-half"><rect x="-65" y="85" width="8" height="8" rx="1"/><rect x="157" y="85" width="8" height="8" rx="1"/><rect x="379" y="85" width="8" height="8" rx="1"/><rect x="601" y="85" width="8" height="8" rx="1"/><rect x="14" y="16" width="9" height="9" rx="1"/><rect x="14" y="38" width="9" height="9" rx="1"/><rect x="14" y="60" width="9" height="9" rx="1"/><rect x="14" y="82" width="9" height="9" rx="1"/><path d="M36 40L44 48L36 56L28 48Z"/><path d="M258 40L266 48L258 56L250 48Z"/><path d="M480 40L488 48L480 56L472 48Z"/><circle cx="126" cy="20" r="3.2"/><circle cx="274" cy="81" r="3.4"/><circle cx="409" cy="43" r="4"/><circle cx="422" cy="20" r="3.2"/></g>
        <g id="hc-band-pads"><use href="#hc-band-pads-half"/><use href="#hc-band-pads-half" transform="translate(1440,0) scale(-1,1)"/></g>
        <g id="hc-band-rings-half"><circle cx="206" cy="46" r="4.6"/><circle cx="474" cy="66" r="4.6"/><circle cx="120" cy="80" r="4"/></g>
        <g id="hc-band-rings"><use href="#hc-band-rings-half"/><use href="#hc-band-rings-half" transform="translate(1440,0) scale(-1,1)"/></g>
        </defs>
        <g class="hero-circuit__wires"><use href="#hc-band-wires"/></g>
        <g class="hero-circuit__pads"><use href="#hc-band-pads"/></g>
        <g class="hero-circuit__rings"><use href="#hc-band-rings"/></g>
        <g class="hero-circuit__charges">
          <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--p1" href="#hc-b0"/>
          <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--p2" href="#hc-b5"/>
          <g transform="translate(1440,0) scale(-1,1)">
            <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--mirrored hero-circuit__charge--p3" href="#hc-b2"/>
            <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--mirrored hero-circuit__charge--p4" href="#hc-b8"/>
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
          <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--p2" href="#hc-b5"/>
          <g transform="translate(1440,0) scale(-1,1)">
            <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--mirrored hero-circuit__charge--p3" href="#hc-b2"/>
            <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--mirrored hero-circuit__charge--p4" href="#hc-b8"/>
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
          <use class="hero-circuit__charge hero-circuit__charge--a" href="#hc-c0"/>
          <use class="hero-circuit__charge hero-circuit__charge--b" href="#hc-c3"/>
          <use class="hero-circuit__charge hero-circuit__charge--c" href="#hc-c7"/>
          <use class="hero-circuit__charge hero-circuit__charge--d" href="#hc-c10"/>
        </g>
        <g class="hero-circuit__nodes">
          <circle class="hero-circuit__node hero-circuit__node--i" cx="60" cy="44" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--j" cx="104" cy="34" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--k" cx="44" cy="104" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--l" cx="34" cy="60" r="3.6"/>
        </g>
      </svg>
      <svg class="hero-circuit__layer hero-circuit__layer--corner-tr" viewBox="0 0 260 200" preserveAspectRatio="xMinYMin meet" focusable="false">
        <g class="hero-circuit__wires"><use href="#hc-corner-wires"/></g>
        <g class="hero-circuit__pads"><use href="#hc-corner-pads"/></g>
        <g class="hero-circuit__rings"><use href="#hc-corner-rings"/></g>
        <g class="hero-circuit__charges">
          <use class="hero-circuit__charge hero-circuit__charge--e" href="#hc-c0"/>
          <use class="hero-circuit__charge hero-circuit__charge--f" href="#hc-c3"/>
          <use class="hero-circuit__charge hero-circuit__charge--g" href="#hc-c7"/>
          <use class="hero-circuit__charge hero-circuit__charge--h" href="#hc-c10"/>
        </g>
        <g class="hero-circuit__nodes">
          <circle class="hero-circuit__node hero-circuit__node--m" cx="60" cy="44" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--n" cx="104" cy="34" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--o" cx="44" cy="104" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--p" cx="34" cy="60" r="3.6"/>
        </g>
      </svg>
      <svg class="hero-circuit__layer hero-circuit__layer--corner-bl" viewBox="0 0 260 200" preserveAspectRatio="xMinYMin meet" focusable="false">
        <g class="hero-circuit__wires"><use href="#hc-corner-wires"/></g>
        <g class="hero-circuit__pads"><use href="#hc-corner-pads"/></g>
        <g class="hero-circuit__rings"><use href="#hc-corner-rings"/></g>
        <g class="hero-circuit__charges">
          <use class="hero-circuit__charge hero-circuit__charge--i" href="#hc-c0"/>
          <use class="hero-circuit__charge hero-circuit__charge--j" href="#hc-c3"/>
          <use class="hero-circuit__charge hero-circuit__charge--k" href="#hc-c7"/>
          <use class="hero-circuit__charge hero-circuit__charge--l" href="#hc-c10"/>
        </g>
        <g class="hero-circuit__nodes">
          <circle class="hero-circuit__node hero-circuit__node--q" cx="60" cy="44" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--r" cx="104" cy="34" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--s" cx="44" cy="104" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--t" cx="34" cy="60" r="3.6"/>
        </g>
      </svg>
      <svg class="hero-circuit__layer hero-circuit__layer--corner-br" viewBox="0 0 260 200" preserveAspectRatio="xMinYMin meet" focusable="false">
        <g class="hero-circuit__wires"><use href="#hc-corner-wires"/></g>
        <g class="hero-circuit__pads"><use href="#hc-corner-pads"/></g>
        <g class="hero-circuit__rings"><use href="#hc-corner-rings"/></g>
        <g class="hero-circuit__charges">
          <use class="hero-circuit__charge hero-circuit__charge--m" href="#hc-c0"/>
          <use class="hero-circuit__charge hero-circuit__charge--n" href="#hc-c3"/>
          <use class="hero-circuit__charge hero-circuit__charge--o" href="#hc-c7"/>
          <use class="hero-circuit__charge hero-circuit__charge--p" href="#hc-c10"/>
        </g>
        <g class="hero-circuit__nodes">
          <circle class="hero-circuit__node hero-circuit__node--u" cx="60" cy="44" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--v" cx="104" cy="34" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--w" cx="44" cy="104" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--x" cx="34" cy="60" r="3.6"/>
        </g>
      </svg>
    </div>
<!--hero-circuit:end-->

<div class="container page-hero__inner">
      <h1 class="page-hero__title"><?= h($data['hero']['title']) ?></h1>
      <p class="page-hero__subtitle"><?= h($data['hero']['subtitle']) ?></p>
    </div>
  </section>
<?php if (about_band_shown($data, 'story')): ?>
<?php /* The five image-and-prose sections. One loop, so a sixth can be added
         and Vision can move above Mission from the editor.

         The surface alternates by POSITION, not by a stored field: it is a
         rhythm down the page, so a reordered or added section keeps the
         stripe rather than carrying a stale copy of it. Same argument for the
         heading ids, which are minted from the row id and are what this
         section's aria-labelledby points at. */ ?>
<?php foreach (about_shown($data, 'story') as $i => $row): ?>
<?php $anchor = $row['id'] . '-heading'; ?>

  <!-- ============================ <?= h($row['heading']) ?> ============================ -->
  <section class="section<?= $i % 2 ? ' section--surface' : '' ?>" aria-labelledby="<?= h($anchor) ?>">
    <div class="container">
      <div data-reveal data-reveal-delay class="about-section__header">
        <h2 class="about-section__title" id="<?= h($anchor) ?>"><?= h($row['heading']) ?></h2>
        <div class="about-section__rule" aria-hidden="true"></div>
      </div>

      <div class="about-split<?= $row['side'] === 'right' ? ' about-split--reverse' : '' ?>">
        <div data-reveal data-reveal-delay class="about-split__media">
          <?= $row['layout'] === 'logo'
                ? about_logo_lockup($row, 'about-split__image about-split__image--contain')
                : about_photograph($row) ?>

        </div>
        <div class="about-split__text">
<?php /* Printed bare, and safe only because rt_sanitise_html() ran on save and
         again on receipt — see contract_sanitise(). about_reveal_paragraphs()
         then puts back the per-paragraph scroll markers, which cannot live in
         the content and cannot live in the template either; the comment on
         that function says why. */ ?>
          <?= about_reveal_paragraphs((string)$row['body']) ?>

        </div>
      </div>
    </div>
  </section>
<?php endforeach; ?>
<?php endif; ?>
<?php if (about_band_shown($data, 'specialties')): ?>
<?php $specialties = about_shown($data, 'specialties'); ?>

  <!-- ======================== Our Specialities ======================== -->
  <section class="section section--surface specialties" aria-labelledby="specialties-heading">
    <div class="container">
      <div data-reveal data-reveal-delay class="about-section__header">
        <h2 class="about-section__title" id="specialties-heading"><?= h($data['specialties']['title']) ?></h2>
        <div class="about-section__rule" aria-hidden="true"></div>
      </div>

      <!-- One specialty at a time, in a slideshow. Without JavaScript the
           track is still the grid it was and all of them are on screen at
           once; see .slider__track in components.css. -->
      <div data-reveal data-reveal-delay class="slider specialties__slider" data-slider
           data-slider-interval="<?= (int)$data['specialties']['interval'] ?>" aria-label="Our specialities">
        <div class="slider__viewport">
          <div class="slider__track" data-slider-track>
<?php foreach ($specialties as $row): ?>
            <div class="slider__slide">
              <article class="specialty-card">
                <span class="specialty-card__icon">
                  <svg class="icon" aria-hidden="true" focusable="false"><use href="#<?= h($row['icon']) ?>"></use></svg>
                </span>
                <h3 class="specialty-card__title"><?= h($row['title']) ?></h3>
                <p class="specialty-card__text">
                  <?= h($row['text']) ?>

                </p>
              </article>
            </div>
<?php endforeach; ?>
          </div>
        </div>

        <!-- Hidden until slider.js marks the slider ready, so nobody is
             offered a control that cannot do anything. -->
        <div class="slider__controls">
          <button class="slider__arrow" type="button" data-slider-prev
                  aria-label="Previous specialty">
            <svg class="icon" aria-hidden="true" focusable="false"><use href="#chevron-left"></use></svg>
          </button>

<?php /* The dots are generated from the slide count rather than written out,
           because slider.js matches a dot to a slide by index — a hand-written
           list that drifts breaks the control silently. */ ?>
          <div class="slider__dots">
<?php foreach ($specialties as $i => $_row): ?>
            <button class="slider__dot" type="button" data-slider-to="<?= $i ?>"
                    aria-label="Go to specialty <?= $i + 1 ?>"></button>
<?php endforeach; ?>
          </div>

          <button class="slider__arrow" type="button" data-slider-next
                  aria-label="Next specialty">
            <svg class="icon" aria-hidden="true" focusable="false"><use href="#chevron-right"></use></svg>
          </button>

          <!-- Both icons ship and CSS shows whichever matches the state, the
               same way the dock's menu button carries its grid and its close
               mark. -->
          <button class="slider__pause" type="button" data-slider-pause
                  aria-label="Pause the slideshow">
            <svg class="icon slider__icon--pause" aria-hidden="true" focusable="false"><use href="#pause"></use></svg>
            <svg class="icon slider__icon--play" aria-hidden="true" focusable="false"><use href="#play"></use></svg>
          </button>
        </div>
      </div>
    </div>
  </section>
<?php endif; ?>
<?php if (about_band_shown($data, 'whyus')): ?>

  <!-- ============================ Why Us? ============================ -->
  <section class="section why-us" aria-labelledby="why-us-heading">
    <div class="container">
      <div data-reveal data-reveal-delay class="about-section__header">
        <h2 class="about-section__title" id="why-us-heading"><?= h($data['whyus']['title']) ?></h2>
        <div class="about-section__rule" aria-hidden="true"></div>
      </div>

      <ul class="why-us__grid">
<?php foreach (about_shown($data, 'whyus') as $row): ?>
        <li data-reveal data-reveal-delay class="why-us-card">
          <span class="why-us-card__icon">
            <svg class="icon" aria-hidden="true" focusable="false"><use href="#<?= h($row['icon']) ?>"></use></svg>
          </span>
          <div>
            <h3 class="why-us-card__title"><?= h($row['title']) ?></h3>
            <p class="why-us-card__text"><?= h($row['text']) ?></p>
          </div>
        </li>
<?php endforeach; ?>
      </ul>
    </div>
  </section>
<?php endif; ?>
<?php if (about_band_shown($data, 'cta')): ?>

  <!-- ============================== CTA ============================== -->
  <section class="cta-band" aria-labelledby="about-cta-heading">
    <div class="container cta-band__inner">
      <h2 data-reveal data-reveal-delay class="cta-band__title" id="about-cta-heading"><?= h($data['cta']['title']) ?></h2>
      <a data-reveal data-reveal-delay class="btn btn--primary btn--lg" href="<?= h($data['cta']['href']) ?>">
        <?= h($data['cta']['label']) ?>

        <svg class="icon" aria-hidden="true" focusable="false"><use href="#<?= h($data['cta']['icon']) ?>"></use></svg>
      </a>
    </div>
  </section>
<?php endif; ?>

<?php /* Every icon a specialty card, a why-us card or the button above may
         carry is named in the comment below. Read that comment before deleting
         it: tools/inject_icons.py finds the symbols a page needs by scanning it
         for a literal href="#name", and the names used above are chosen at run
         time, where the scan cannot see them. Keep this in step with
         ABOUT_ICONS in lib/contract.php — inject_icons.py --check says so when
         it drifts.

         <use href="#shield-alt"> <use href="#code"> <use href="#cloud">
         <use href="#users"> <use href="#server"> <use href="#graduation-cap">
         <use href="#trophy"> <use href="#layer-group"> <use href="#lightbulb">
         <use href="#handshake"> <use href="#cogs"> <use href="#lock">
         <use href="#project-diagram"> <use href="#eye"> <use href="#arrow-right">
         <use href="#check-circle">
      */ ?>
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
          <a class="dock__item" href="/pages/about/" aria-current="page">
            <span class="dock__item-title">About Us</span>
            <span class="dock__item-desc">Who we are and how we work</span>
          </a>
        </li>
        <li>
          <a class="dock__item" href="/pages/services/">
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
    <a class="dock__key" href="/pages/services/">
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
<script src="/assets/js/slider.js" defer></script>
<script src="/assets/js/main.js" defer></script>
</body>
</html>
