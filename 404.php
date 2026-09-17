<?php
/**
 * Tech4TIME — the page that is served when there is no page.
 *
 * PHP, and not HTML, for one reason: its <head> is emitted by lib/head.php
 * like every other page's, so the site has ONE head and not seventeen. Its
 * words are content too — title, description and the crawl directive live in
 * content/seo.json, in the notfound record, because this is the one page with
 * no content document of its own and it never will have one.
 *
 * IT HAS NO CANONICAL AND NO og:url, DELIBERATELY. This page is served at
 * every address that does not exist, so it has no address of its own to claim
 * as the right one. seo_head() omits both when it is passed an empty route.
 *
 * TWO CALLERS, AND THE STATUS LINE HAS TO BE RIGHT FOR BOTH.
 *   - Apache, through ErrorDocument 404 in .htaccess, which has already set
 *     the status; setting it again changes nothing.
 *   - A service page whose service has been hidden or removed, which requires
 *     this file after setting the same code. Without the line below, asking
 *     for this file directly would answer 200 with an apology on it, and a
 *     crawler would index the apology.
 *
 * require_once, not require: a service page reaching here has already loaded
 * lib/head.php through lib/services.php's siblings, and a plain require would
 * redeclare every function in it.
 */

declare(strict_types=1);

http_response_code(404);

require_once __DIR__ . '/lib/head.php';
require_once __DIR__ . '/lib/body.php';
?>
<!DOCTYPE html>
<html lang="<?= h(seo_lang()) ?>">
<head>
<?php seo_head('', seo_notfound(), ['pages/error.css']); ?>
<?php seo_jsonld('', seo_notfound()); ?>
</head>

<body class="page">
<!-- icon-sprite:start -->
<svg class="icon-sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <symbol id="briefcase" viewBox="0 0 512 512"><path d="M184 48H328c4.4 0 8 3.6 8 8V96H176V56c0-4.4 3.6-8 8-8zm-56 8V96H64C28.7 96 0 124.7 0 160v96H192 320 512V160c0-35.3-28.7-64-64-64H384V56c0-30.9-25.1-56-56-56H184c-30.9 0-56 25.1-56 56zM512 288H320v32c0 17.7-14.3 32-32 32H224c-17.7 0-32-14.3-32-32V288H0V416c0 35.3 28.7 64 64 64H448c35.3 0 64-28.7 64-64V288z"/></symbol>
  <symbol id="cloud" viewBox="0 0 640 512"><path d="M0 336c0 79.5 64.5 144 144 144H512c70.7 0 128-57.3 128-128c0-61.9-44-113.6-102.4-125.4c4.1-10.7 6.4-22.4 6.4-34.6c0-53-43-96-96-96c-19.7 0-38.1 6-53.3 16.2C367 64.2 315.3 32 256 32C167.6 32 96 103.6 96 192c0 2.7 .1 5.4 .2 8.1C40.2 219.8 0 273.2 0 336z"/></symbol>
  <symbol id="code" viewBox="0 0 640 512"><path d="M392.8 1.2c-17-4.9-34.7 5-39.6 22l-128 448c-4.9 17 5 34.7 22 39.6s34.7-5 39.6-22l128-448c4.9-17-5-34.7-22-39.6zm80.6 120.1c-12.5 12.5-12.5 32.8 0 45.3L562.7 256l-89.4 89.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0l112-112c12.5-12.5 12.5-32.8 0-45.3l-112-112c-12.5-12.5-32.8-12.5-45.3 0zm-306.7 0c-12.5-12.5-32.8-12.5-45.3 0l-112 112c-12.5 12.5-12.5 32.8 0 45.3l112 112c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L77.3 256l89.4-89.4c12.5-12.5 12.5-32.8 0-45.3z"/></symbol>
  <symbol id="envelope" viewBox="0 0 512 512"><path d="M48 64C21.5 64 0 85.5 0 112c0 15.1 7.1 29.3 19.2 38.4L236.8 313.6c11.4 8.5 27 8.5 38.4 0L492.8 150.4c12.1-9.1 19.2-23.3 19.2-38.4c0-26.5-21.5-48-48-48H48zM0 176V384c0 35.3 28.7 64 64 64H448c35.3 0 64-28.7 64-64V176L294.4 339.2c-22.8 17.1-54 17.1-76.8 0L0 176z"/></symbol>
  <symbol id="home" viewBox="0 0 576 512"><path d="M575.8 255.5c0 18-15 32.1-32 32.1h-32l.7 160.2c0 2.7-.2 5.4-.5 8.1V472c0 22.1-17.9 40-40 40H456c-1.1 0-2.2 0-3.3-.1c-1.4 .1-2.8 .1-4.2 .1H416 392c-22.1 0-40-17.9-40-40V448 384c0-17.7-14.3-32-32-32H256c-17.7 0-32 14.3-32 32v64 24c0 22.1-17.9 40-40 40H160 128.1c-1.5 0-3-.1-4.5-.2c-1.2 .1-2.4 .2-3.6 .2H104c-22.1 0-40-17.9-40-40V360c0-.9 0-1.9 .1-2.8V287.6H32c-18 0-32-14-32-32.1c0-9 3-17 10-24L266.4 8c7-7 15-8 22-8s15 2 21 7L564.8 231.5c8 7 12 15 11 24z"/></symbol>
  <symbol id="shield-alt" viewBox="0 0 512 512"><path d="M256 0c4.6 0 9.2 1 13.4 2.9L457.7 82.8c22 9.3 38.4 31 38.3 57.2c-.5 99.2-41.3 280.7-213.6 363.2c-16.7 8-36.1 8-52.8 0C57.3 420.7 16.5 239.2 16 140c-.1-26.2 16.3-47.9 38.3-57.2L242.7 2.9C246.8 1 251.4 0 256 0zm0 66.8V444.8C394 378 431.1 230.1 432 141.4L256 66.8l0 0z"/></symbol>
</svg>
<!-- icon-sprite:end -->

<?php body_header(''); ?>

<main class="page__main" id="main">
  <section class="section error">
    <div class="container error__inner">
      <p class="error__code" aria-hidden="true">404</p>

      <h1 class="error__title">This page has run out of time</h1>

      <p class="error__lead">
        The page you were looking for does not exist, or it has moved. Nothing is
        broken on your end — let us point you somewhere useful.
      </p>

      <div class="error__actions">
        <a class="btn btn--primary btn--lg" href="/">
          <svg class="icon" aria-hidden="true" focusable="false"><use href="#home"></use></svg>
          Back to home
        </a>
        <a class="btn btn--secondary btn--lg" href="/pages/contact/">
          <svg class="icon" aria-hidden="true" focusable="false"><use href="#envelope"></use></svg>
          Contact us
        </a>
      </div>

      <nav class="error__suggestions" aria-labelledby="error-suggestions-heading">
        <h2 class="error__suggestions-title" id="error-suggestions-heading">
          Popular destinations
        </h2>
        <ul class="error__links">
          <li>
            <a class="card card--interactive error__link" href="/pages/services/cybersecurity/">
              <span class="icon-tile">
                <svg class="icon" aria-hidden="true" focusable="false"><use href="#shield-alt"></use></svg>
              </span>
              <span class="error__link-title">Cybersecurity</span>
              <span class="error__link-text">SOC, penetration testing, incident response and compliance.</span>
            </a>
          </li>
          <li>
            <a class="card card--interactive error__link" href="/pages/services/software-development/">
              <span class="icon-tile">
                <svg class="icon" aria-hidden="true" focusable="false"><use href="#code"></use></svg>
              </span>
              <span class="error__link-title">Software Development</span>
              <span class="error__link-text">Custom applications, web, mobile and DevSecOps delivery.</span>
            </a>
          </li>
          <li>
            <a class="card card--interactive error__link" href="/pages/services/cloud-infrastructure/">
              <span class="icon-tile">
                <svg class="icon" aria-hidden="true" focusable="false"><use href="#cloud"></use></svg>
              </span>
              <span class="error__link-title">Cloud &amp; Infrastructure</span>
              <span class="error__link-text">OpenStack, private and hybrid cloud, migration and operations.</span>
            </a>
          </li>
          <li>
            <a class="card card--interactive error__link" href="/pages/careers/">
              <span class="icon-tile">
                <svg class="icon" aria-hidden="true" focusable="false"><use href="#briefcase"></use></svg>
              </span>
              <span class="error__link-title">Careers</span>
              <span class="error__link-text">Open roles across our security, engineering and cloud teams.</span>
            </a>
          </li>
        </ul>
      </nav>
    </div>
  </section>
</main>

<?php body_footer(); ?>

<?php body_dock(''); ?>

<!-- Deferred so nothing blocks rendering. Order matters only in that main.js
     runs last: each module registers itself on window.Tech4Time, and main.js
     calls their init(). Pages that need no forms can omit forms.js. -->
<script src="/assets/js/theme-toggle.js" defer></script>
<script src="/assets/js/nav.js" defer></script>
<script src="/assets/js/animations.js?v=2" defer></script>
<!-- Versioned for the same reason the stylesheets are, and with a sharper
     edge: MODULES in this file is a hardcoded allow list, so a stale copy
     silently skips every module added since — no error, no console line,
     just a feature that is not there. -->
<script src="/assets/js/main.js?v=3" defer></script>
</body>
</html>
