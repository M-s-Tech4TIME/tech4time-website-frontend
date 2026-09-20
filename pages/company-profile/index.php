<?php
/**
 * Tech4TIME — company profile page.
 *
 * Renders from a document, as every page here does: what it says changes
 * without a redeploy. The milestones, the
 * client roster, the technology list, the principles and the copy around them
 * live in content/company.json and are edited through the admin at
 * admin.tech4time.bd; this renders them.
 *
 * Rendered on the SERVER, not fetched in the browser — ADR 0003. A page whose
 * client list arrives by JavaScript is one a crawler indexes unreliably.
 *
 * If the data file is missing or unreadable, company_load() falls back field by
 * field to the copy the site was deployed with: stale at worst, never blank.
 * The lists fall back to empty, so the page keeps its shape and loses its
 * contents — the same bargain the contact page makes.
 *
 * EVERY BAND CAN BE HIDDEN, and so can every row inside one. That is what
 * company_band_shown() and company_shown() are for, and it is why almost every
 * section below opens with an if.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/head.php';
require_once __DIR__ . '/../../lib/body.php';
require_once __DIR__ . '/../../lib/company.php';
require_once __DIR__ . '/../../lib/milestones.php';

$data = company_load();

/* THE TIMELINE IS A SECOND DOCUMENT NOW, and this page shows a WINDOW onto it:
   the most recent MILESTONES_WINDOW years, with the whole history on
   /pages/milestones/. Without a bound the band grew by a box and a margin —
   130 to 160px, at every width — for every year the company added, forever.

   milestones_load() reads through to the company document's own milestones
   band until the new screen has been saved once, so this page renders exactly
   what it rendered before until somebody edits it. */
$milestones = milestones_load();
$timeline   = milestones_shown($milestones, 'timeline');
$recent     = milestones_recent($timeline);
?>
<!DOCTYPE html>
<html lang="<?= h(seo_lang()) ?>">
<head>
<?php seo_head('/pages/company-profile/', $data['meta'],
                 ['pages/milestones.css', 'pages/company-profile.css?v=3'],
                 $data['updated']); ?>
<?php seo_jsonld('/pages/company-profile/', $data['meta'], $data['updated']); ?>

<script type="application/ld+json">
<?php /* The timeline as the events it describes, built from exactly the
         rows the band below renders — the window, not the history, and
         nothing at all when the band is hidden. A graph that describes what
         the markup does not carry is a page saying two things about itself. */ ?>
<?= json_encode(company_page_schema($data,
        milestones_band_shown($milestones, 'timeline')
            ? milestones_event_list($recent, (string)$milestones['timeline']['title'])
            : []),
        HEAD_JSON_FLAGS) ?>

</script>
</head>

<body class="page">
<!-- icon-sprite:start -->
<svg class="icon-sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <symbol id="building" viewBox="0 0 384 512"><path d="M48 0C21.5 0 0 21.5 0 48V464c0 26.5 21.5 48 48 48h96V432c0-26.5 21.5-48 48-48s48 21.5 48 48v80h96c26.5 0 48-21.5 48-48V48c0-26.5-21.5-48-48-48H48zM64 240c0-8.8 7.2-16 16-16h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H80c-8.8 0-16-7.2-16-16V240zm112-16h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H176c-8.8 0-16-7.2-16-16V240c0-8.8 7.2-16 16-16zm80 16c0-8.8 7.2-16 16-16h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H272c-8.8 0-16-7.2-16-16V240zM80 96h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H80c-8.8 0-16-7.2-16-16V112c0-8.8 7.2-16 16-16zm80 16c0-8.8 7.2-16 16-16h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H176c-8.8 0-16-7.2-16-16V112zM272 96h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H272c-8.8 0-16-7.2-16-16V112c0-8.8 7.2-16 16-16z"/></symbol>
  <symbol id="calendar-check" viewBox="0 0 448 512"><path d="M128 0c17.7 0 32 14.3 32 32V64H288V32c0-17.7 14.3-32 32-32s32 14.3 32 32V64h48c26.5 0 48 21.5 48 48v48H0V112C0 85.5 21.5 64 48 64H96V32c0-17.7 14.3-32 32-32zM0 192H448V464c0 26.5-21.5 48-48 48H48c-26.5 0-48-21.5-48-48V192zM329 305c9.4-9.4 9.4-24.6 0-33.9s-24.6-9.4-33.9 0l-95 95-47-47c-9.4-9.4-24.6-9.4-33.9 0s-9.4 24.6 0 33.9l64 64c9.4 9.4 24.6 9.4 33.9 0L329 305z"/></symbol>
  <symbol id="check-circle" viewBox="0 0 512 512"><path d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM369 209L241 337c-9.4 9.4-24.6 9.4-33.9 0l-64-64c-9.4-9.4-9.4-24.6 0-33.9s24.6-9.4 33.9 0l47 47L335 175c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9z"/></symbol>
  <symbol id="chevron-left" viewBox="0 0 320 512"><path d="M9.4 233.4c-12.5 12.5-12.5 32.8 0 45.3l192 192c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L77.3 256 246.6 86.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0l-192 192z"/></symbol>
  <symbol id="chevron-right" viewBox="0 0 320 512"><path d="M310.6 233.4c12.5 12.5 12.5 32.8 0 45.3l-192 192c-12.5 12.5-32.8 12.5-45.3 0s-12.5-32.8 0-45.3L242.7 256 73.4 86.6c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0l192 192z"/></symbol>
  <symbol id="clock" viewBox="0 0 512 512"><path d="M256 0a256 256 0 1 1 0 512A256 256 0 1 1 256 0zM232 120V256c0 8 4 15.5 10.7 20l96 64c11 7.4 25.9 4.4 33.3-6.7s4.4-25.9-6.7-33.3L280 243.2V120c0-13.3-10.7-24-24-24s-24 10.7-24 24z"/></symbol>
  <symbol id="cogs" viewBox="0 0 640 512"><path d="M308.5 135.3c7.1-6.3 9.9-16.2 6.2-25c-2.3-5.3-4.8-10.5-7.6-15.5L304 89.4c-3-5-6.3-9.9-9.8-14.6c-5.7-7.6-15.7-10.1-24.7-7.1l-28.2 9.3c-10.7-8.8-23-16-36.2-20.9L199 27.1c-1.9-9.3-9.1-16.7-18.5-17.8C173.9 8.4 167.2 8 160.4 8h-.7c-6.8 0-13.5 .4-20.1 1.2c-9.4 1.1-16.6 8.6-18.5 17.8L115 56.1c-13.3 5-25.5 12.1-36.2 20.9L50.5 67.8c-9-3-19-.5-24.7 7.1c-3.5 4.7-6.8 9.6-9.9 14.6l-3 5.3c-2.8 5-5.3 10.2-7.6 15.6c-3.7 8.7-.9 18.6 6.2 25l22.2 19.8C32.6 161.9 32 168.9 32 176s.6 14.1 1.7 20.9L11.5 216.7c-7.1 6.3-9.9 16.2-6.2 25c2.3 5.3 4.8 10.5 7.6 15.6l3 5.2c3 5.1 6.3 9.9 9.9 14.6c5.7 7.6 15.7 10.1 24.7 7.1l28.2-9.3c10.7 8.8 23 16 36.2 20.9l6.1 29.1c1.9 9.3 9.1 16.7 18.5 17.8c6.7 .8 13.5 1.2 20.4 1.2s13.7-.4 20.4-1.2c9.4-1.1 16.6-8.6 18.5-17.8l6.1-29.1c13.3-5 25.5-12.1 36.2-20.9l28.2 9.3c9 3 19 .5 24.7-7.1c3.5-4.7 6.8-9.5 9.8-14.6l3.1-5.4c2.8-5 5.3-10.2 7.6-15.5c3.7-8.7 .9-18.6-6.2-25l-22.2-19.8c1.1-6.8 1.7-13.8 1.7-20.9s-.6-14.1-1.7-20.9l22.2-19.8zM112 176a48 48 0 1 1 96 0 48 48 0 1 1 -96 0zM504.7 500.5c6.3 7.1 16.2 9.9 25 6.2c5.3-2.3 10.5-4.8 15.5-7.6l5.4-3.1c5-3 9.9-6.3 14.6-9.8c7.6-5.7 10.1-15.7 7.1-24.7l-9.3-28.2c8.8-10.7 16-23 20.9-36.2l29.1-6.1c9.3-1.9 16.7-9.1 17.8-18.5c.8-6.7 1.2-13.5 1.2-20.4s-.4-13.7-1.2-20.4c-1.1-9.4-8.6-16.6-17.8-18.5L583.9 307c-5-13.3-12.1-25.5-20.9-36.2l9.3-28.2c3-9 .5-19-7.1-24.7c-4.7-3.5-9.6-6.8-14.6-9.9l-5.3-3c-5-2.8-10.2-5.3-15.6-7.6c-8.7-3.7-18.6-.9-25 6.2l-19.8 22.2c-6.8-1.1-13.8-1.7-20.9-1.7s-14.1 .6-20.9 1.7l-19.8-22.2c-6.3-7.1-16.2-9.9-25-6.2c-5.3 2.3-10.5 4.8-15.6 7.6l-5.2 3c-5.1 3-9.9 6.3-14.6 9.9c-7.6 5.7-10.1 15.7-7.1 24.7l9.3 28.2c-8.8 10.7-16 23-20.9 36.2L315.1 313c-9.3 1.9-16.7 9.1-17.8 18.5c-.8 6.7-1.2 13.5-1.2 20.4s.4 13.7 1.2 20.4c1.1 9.4 8.6 16.6 17.8 18.5l29.1 6.1c5 13.3 12.1 25.5 20.9 36.2l-9.3 28.2c-3 9-.5 19 7.1 24.7c4.7 3.5 9.5 6.8 14.6 9.8l5.4 3.1c5 2.8 10.2 5.3 15.5 7.6c8.7 3.7 18.6 .9 25-6.2l19.8-22.2c6.8 1.1 13.8 1.7 20.9 1.7s14.1-.6 20.9-1.7l19.8 22.2zM464 304a48 48 0 1 1 0 96 48 48 0 1 1 0-96z"/></symbol>
  <symbol id="comment-alt" viewBox="0 0 512 512"><path d="M64 0C28.7 0 0 28.7 0 64V352c0 35.3 28.7 64 64 64h96v80c0 6.1 3.4 11.6 8.8 14.3s11.9 2.1 16.8-1.5L309.3 416H448c35.3 0 64-28.7 64-64V64c0-35.3-28.7-64-64-64H64z"/></symbol>
  <symbol id="eye" viewBox="0 0 576 512"><path d="M288 32c-80.8 0-145.5 36.8-192.6 80.6C48.6 156 17.3 208 2.5 243.7c-3.3 7.9-3.3 16.7 0 24.6C17.3 304 48.6 356 95.4 399.4C142.5 443.2 207.2 480 288 480s145.5-36.8 192.6-80.6c46.8-43.5 78.1-95.4 93-131.1c3.3-7.9 3.3-16.7 0-24.6c-14.9-35.7-46.2-87.7-93-131.1C433.5 68.8 368.8 32 288 32zM144 256a144 144 0 1 1 288 0 144 144 0 1 1 -288 0zm144-64c0 35.3-28.7 64-64 64c-7.1 0-13.9-1.2-20.3-3.3c-5.5-1.8-11.9 1.6-11.7 7.4c.3 6.9 1.3 13.8 3.2 20.7c13.7 51.2 66.4 81.6 117.6 67.9s81.6-66.4 67.9-117.6c-11.1-41.5-47.8-69.4-88.6-71.1c-5.8-.2-9.2 6.1-7.4 11.7c2.1 6.4 3.3 13.2 3.3 20.3z"/></symbol>
  <symbol id="globe" viewBox="0 0 512 512"><path d="M352 256c0 22.2-1.2 43.6-3.3 64H163.3c-2.2-20.4-3.3-41.8-3.3-64s1.2-43.6 3.3-64H348.7c2.2 20.4 3.3 41.8 3.3 64zm28.8-64H503.9c5.3 20.5 8.1 41.9 8.1 64s-2.8 43.5-8.1 64H380.8c2.1-20.6 3.2-42 3.2-64s-1.1-43.4-3.2-64zm112.6-32H376.7c-10-63.9-29.8-117.4-55.3-151.6c78.3 20.7 142 77.5 171.9 151.6zm-149.1 0H167.7c6.1-36.4 15.5-68.6 27-94.7c10.5-23.6 22.2-40.7 33.5-51.5C239.4 3.2 248.7 0 256 0s16.6 3.2 27.8 13.8c11.3 10.8 23 27.9 33.5 51.5c11.6 26 20.9 58.2 27 94.7zm-209 0H18.6C48.6 85.9 112.2 29.1 190.6 8.4C165.1 42.6 145.3 96.1 135.3 160zM8.1 192H131.2c-2.1 20.6-3.2 42-3.2 64s1.1 43.4 3.2 64H8.1C2.8 299.5 0 278.1 0 256s2.8-43.5 8.1-64zM194.7 446.6c-11.6-26-20.9-58.2-27-94.6H344.3c-6.1 36.4-15.5 68.6-27 94.6c-10.5 23.6-22.2 40.7-33.5 51.5C272.6 508.8 263.3 512 256 512s-16.6-3.2-27.8-13.8c-11.3-10.8-23-27.9-33.5-51.5zM135.3 352c10 63.9 29.8 117.4 55.3 151.6C112.2 482.9 48.6 426.1 18.6 352H135.3zm358.1 0c-30 74.1-93.6 130.9-171.9 151.6c25.5-34.2 45.2-87.7 55.3-151.6H493.4z"/></symbol>
  <symbol id="handshake" viewBox="0 0 640 512"><path d="M323.4 85.2l-96.8 78.4c-16.1 13-19.2 36.4-7 53.1c12.9 17.8 38 21.3 55.3 7.8l99.3-77.2c7-5.4 17-4.2 22.5 2.8s4.2 17-2.8 22.5l-20.9 16.2L512 316.8V128h-.7l-3.9-2.5L434.8 79c-15.3-9.8-33.2-15-51.4-15c-21.8 0-43 7.5-60 21.2zm22.8 124.4l-51.7 40.2C263 274.4 217.3 268 193.7 235.6c-22.2-30.5-16.6-73.1 12.7-96.8l83.2-67.3c-11.6-4.9-24.1-7.4-36.8-7.4C234 64 215.7 69.6 200 80l-72 48V352h28.2l91.4 83.4c19.6 17.9 49.9 16.5 67.8-3.1c5.5-6.1 9.2-13.2 11.1-20.6l17 15.6c19.5 17.9 49.9 16.6 67.8-2.9c4.5-4.9 7.8-10.6 9.9-16.5c19.4 13 45.8 10.3 62.1-7.5c17.9-19.5 16.6-49.9-2.9-67.8l-134.2-123zM16 128c-8.8 0-16 7.2-16 16V352c0 17.7 14.3 32 32 32H64c17.7 0 32-14.3 32-32V128H16zM48 320a16 16 0 1 1 0 32 16 16 0 1 1 0-32zM544 128V352c0 17.7 14.3 32 32 32h32c17.7 0 32-14.3 32-32V144c0-8.8-7.2-16-16-16H544zm32 208a16 16 0 1 1 32 0 16 16 0 1 1 -32 0z"/></symbol>
  <symbol id="headset" viewBox="0 0 512 512"><path d="M256 48C141.1 48 48 141.1 48 256v40c0 13.3-10.7 24-24 24s-24-10.7-24-24V256C0 114.6 114.6 0 256 0S512 114.6 512 256V400.1c0 48.6-39.4 88-88.1 88L313.6 488c-8.3 14.3-23.8 24-41.6 24H240c-26.5 0-48-21.5-48-48s21.5-48 48-48h32c17.8 0 33.3 9.7 41.6 24l110.4 .1c22.1 0 40-17.9 40-40V256c0-114.9-93.1-208-208-208zM144 208h16c17.7 0 32 14.3 32 32V352c0 17.7-14.3 32-32 32H144c-35.3 0-64-28.7-64-64V272c0-35.3 28.7-64 64-64zm224 0c35.3 0 64 28.7 64 64v48c0 35.3-28.7 64-64 64H352c-17.7 0-32-14.3-32-32V240c0-17.7 14.3-32 32-32h16z"/></symbol>
  <symbol id="info-circle" viewBox="0 0 512 512"><path d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM216 336h24V272H216c-13.3 0-24-10.7-24-24s10.7-24 24-24h48c13.3 0 24 10.7 24 24v88h8c13.3 0 24 10.7 24 24s-10.7 24-24 24H216c-13.3 0-24-10.7-24-24s10.7-24 24-24zm40-208a32 32 0 1 1 0 64 32 32 0 1 1 0-64z"/></symbol>
  <symbol id="lightbulb" viewBox="0 0 384 512"><path d="M272 384c9.6-31.9 29.5-59.1 49.2-86.2l0 0c5.2-7.1 10.4-14.2 15.4-21.4c19.8-28.5 31.4-63 31.4-100.3C368 78.8 289.2 0 192 0S16 78.8 16 176c0 37.3 11.6 71.9 31.4 100.3c5 7.2 10.2 14.3 15.4 21.4l0 0c19.8 27.1 39.7 54.4 49.2 86.2H272zM192 512c44.2 0 80-35.8 80-80V416H112v16c0 44.2 35.8 80 80 80zM112 176c0 8.8-7.2 16-16 16s-16-7.2-16-16c0-61.9 50.1-112 112-112c8.8 0 16 7.2 16 16s-7.2 16-16 16c-44.2 0-80 35.8-80 80z"/></symbol>
  <symbol id="pause" viewBox="0 0 24 24"><rect x="6" y="4.5" width="4" height="15" rx="1.4"/><rect x="14" y="4.5" width="4" height="15" rx="1.4"/></symbol>
  <symbol id="play" viewBox="0 0 24 24"><path d="M7.5 4.9v14.2a1 1 0 0 0 1.53.85l11.2-7.1a1 1 0 0 0 0-1.7L9.03 4.05A1 1 0 0 0 7.5 4.9z"/></symbol>
  <symbol id="shield-alt" viewBox="0 0 512 512"><path d="M256 0c4.6 0 9.2 1 13.4 2.9L457.7 82.8c22 9.3 38.4 31 38.3 57.2c-.5 99.2-41.3 280.7-213.6 363.2c-16.7 8-36.1 8-52.8 0C57.3 420.7 16.5 239.2 16 140c-.1-26.2 16.3-47.9 38.3-57.2L242.7 2.9C246.8 1 251.4 0 256 0zm0 66.8V444.8C394 378 431.1 230.1 432 141.4L256 66.8l0 0z"/></symbol>
  <symbol id="user-shield" viewBox="0 0 640 512"><path d="M224 256A128 128 0 1 0 224 0a128 128 0 1 0 0 256zm-45.7 48C79.8 304 0 383.8 0 482.3C0 498.7 13.3 512 29.7 512H418.3c1.8 0 3.5-.2 5.3-.5c-76.3-55.1-99.8-141-103.1-200.2c-16.1-4.8-33.1-7.3-50.7-7.3H178.3zm308.8-78.3l-120 48C358 277.4 352 286.2 352 296c0 63.3 25.9 168.8 134.8 214.2c5.9 2.5 12.6 2.5 18.5 0C614.1 464.8 640 359.3 640 296c0-9.8-6-18.6-15.1-22.3l-120-48c-5.7-2.3-12.1-2.3-17.8 0zM591.4 312c-3.9 50.7-27.2 116.7-95.4 149.7V273.8L591.4 312z"/></symbol>
</svg>
<!-- icon-sprite:end -->

<?php body_header('/pages/company-profile/'); ?>

<main class="page__main" id="main">

  <!-- ========================== Hero banner ========================== -->
  <section class="page-hero">
    <!--hero-circuit:start-->
    <!-- The circuitry around the page title. GENERATED - do not edit this file
         or the copy of it in any page. It is extracted from the company's own
         banner artwork, references/t4t_circuitry_6000_2031_300.svg, by
         tools/build_hero_circuit.py, and tools/propagate_shared.py carries it
         out to every page from here.

         It was drawn by hand once, from a description of that artwork. It is
         no longer a description: every trace, pad and via below is the real
         drawing, clipped and fitted, and the numbers are arithmetic anybody can
         redo rather than an afternoon nobody can.

         aria-hidden, and inside the band but behind it: this is texture around
         the title, and it says nothing.

         SIX LAYERS, ONE SET OF GEOMETRY
         Everything is declared once, in the first layer's <defs>. SVG ids are
         document-scoped, so the other five reference the same paths and are
         mirrored in CSS. That is not tidiness - a duplicate id is a hard
         failure in audit_pages.py, so four corners cannot each carry a copy.

         THE BAND TILES; IT IS NOT STRETCHED. IT USED TO BE.
         The bands used preserveAspectRatio="none", on the reasoning that a band
         runs a fixed height across a box whose width is the screen's and
         stretching a horizontal run only makes it a longer run. That is true of
         a run and false of the drawing around it: pads turn to ovals, vias to
         ellipses, every vertical trace thins and every horizontal one thickens.
         Measured, the two scales agreed at exactly ONE viewport -- 1920px, the
         width the artwork was composed for -- and disagreed everywhere else.

         So the viewBox is BAND_TILES tiles wide and the fit is xMidYMid slice,
         which scales UNIFORMLY and crops rather than distorting. The height
         decides the scale, the width never does, and the trace pitch is the
         same at 768px as at 4K. The corners have always used xMinYMin meet, for
         the same reason turned the other way: a fan of 45 degree elbows must
         not shear, and it must stay pinned to its own corner.

         THE BAND IS A HALF, MIRRORED -- AND THAT IS WHAT A TILE IS
         The reference's band is one run about six times as wide as it is tall.
         So the run fills the left half of a 1440-unit tile and the right half is
         its reflection: every pad stays circular and every mark keeps its drawn
         size. Each tile mirrors about its own centre, and BAND_TILES is ODD so
         the viewBox's centre line is a mirror axis too -- the banner's middle
         is still where the two halves meet.
         hero-circuit__charge--mirrored puts them back in step, and circuit.js
         places each band trace twice per visible tile for the same reason.

         A CHARGE IS ONE <use>, AND NEVER A GROUP OF THEM
         This layer once carried the charge on a <g> wrapping a <use> of a
         *group* of traces, on the reasoning that forty animated elements must
         beat two hundred. That reasoning was wrong, and measurably so.
         stroke-dashoffset is an inherited property: animating it on a group
         makes the browser push the new value down through every <use> shadow
         tree beneath it, every frame. Lighthouse put the page's Style & Layout
         work at 4,683ms against 686ms before it, and the site was reported as
         struggling.

         So: the charge goes directly on the <use> that draws the trace, and it
         is deliberately not on every trace. The density here is the STATIC
         drawing, which costs one rasterisation; movement is the expensive part
         and is spent sparingly - three traces in each cluster and three in each
         band half, 24 against 176 drawn. With scripting, circuit.js paints all
         176 on one canvas and switches these off; these are the fallback, and
         the fallback is what the budget is spent on.

         The cost is close to linear in that number: about 1.1ms of style
         recalculation per second per charge, on top of a floor that is the
         static drawing. If you raise it, measure - tools/check_style_budget.py,
         and read the table in docs/10-development/frontend/motion.md first.

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
      <svg class="hero-circuit__layer hero-circuit__layer--band-top" viewBox="0 0 21600 114" preserveAspectRatio="xMidYMid slice" focusable="false">
        <defs>
        <path id="hc-b0" pathLength="100" d="M717.1 2.2 L684.7 24.8 L684.7 104.2"/>
        <path id="hc-b1" pathLength="100" d="M715.1 21.7 L715.1 109.1"/>
        <path id="hc-b2" pathLength="100" d="M704 2.2 L704 54.1"/>
        <path id="hc-b3" pathLength="100" d="M642.4 2.2 L642.4 26.9 L661.8 38.6 L661.8 99.5"/>
        <path id="hc-b4" pathLength="100" d="M625.3 2.2 L625.3 74.9"/>
        <path id="hc-b5" pathLength="100" d="M578.5 2.2 L575.3 4 L575.3 32.9 L594.7 49 L594.7 109.1"/>
        <path id="hc-b6" pathLength="100" d="M546.4 2.2 L546.4 36.1 L564.1 46.6 L564.1 94.6"/>
        <path id="hc-b7" pathLength="100" d="M533.5 23.6 L533.5 76.7 L498.8 106"/>
        <path id="hc-b8" pathLength="100" d="M499.9 2.2 L499.9 32.9 L514.3 43.1 L514.3 69.3"/>
        <path id="hc-b9" pathLength="100" d="M485.9 2.2 L486.9 2.9 L486.9 32.9 L468.1 47.2 L468.1 92.1"/>
        <path id="hc-b10" pathLength="100" d="M425.1 2.2 L425.1 51.5 L448.6 65.4 L448.6 92.1"/>
        <path id="hc-b11" pathLength="100" d="M443.9 2.2 L443.9 11.8 L407.4 26.3 L407.4 63.9 L429.2 79.8 L429.2 109.1"/>
        <path id="hc-b12" pathLength="100" d="M407.4 2.2 L407.4 8.1"/>
        <path id="hc-b13" pathLength="100" d="M369.3 2.2 L380.3 9.2 L380.3 69.3"/>
        <path id="hc-b14" pathLength="100" d="M325.3 2.2 L324.4 2.9 L324.4 54.1 L347.3 71.2 L347.3 109.1"/>
        <path id="hc-b15" pathLength="100" d="M286.1 2.2 L286.1 9.2 L300.2 18.7 L300.2 92.1"/>
        <path id="hc-b16" pathLength="100" d="M265.6 2.2 L231.9 26.9 L231.9 60.5"/>
        <path id="hc-b17" pathLength="100" d="M244.9 2.2 L279.6 36.1 L279.6 68.1 L253.7 79.8 L253.7 104.2"/>
        <path id="hc-b18" pathLength="100" d="M207.1 2.2 L194.8 11.8 L194.8 85.3 L221.9 109.1"/>
        <path id="hc-b19" pathLength="100" d="M183 2.2 L183 49 L167.1 57.6 L167.1 99.5"/>
        <path id="hc-b20" pathLength="100" d="M147.7 2.2 L147.7 11.8 L100 35.5 L100 104.2"/>
        <path id="hc-b21" pathLength="100" d="M123.8 2.2 L123.8 69.3 L150.6 84.7"/>
        <path id="hc-b22" pathLength="100" d="M59.9 2.2 L59.9 23.8 L84.7 37.3 L84.7 65.4 L66.4 79.8 L66.4 104.2"/>
        <path id="hc-b23" pathLength="100" d="M67 43.1 L44.6 61.3 L44.6 109.1"/>
        <path id="hc-b24" pathLength="100" d="M29.9 2.2 L29.9 43.1 L4.6 59.5 L4.6 84.7 L18.7 92.1 L18.7 109.1"/>
        <path id="hc-b25" pathLength="100" d="M12.2 2.2 L12.2 36.1"/>
        <g id="hc-band-half"><use href="#hc-b0"/><use href="#hc-b1"/><use href="#hc-b2"/><use href="#hc-b3"/><use href="#hc-b4"/><use href="#hc-b5"/><use href="#hc-b6"/><use href="#hc-b7"/><use href="#hc-b8"/><use href="#hc-b9"/><use href="#hc-b10"/><use href="#hc-b11"/><use href="#hc-b12"/><use href="#hc-b13"/><use href="#hc-b14"/><use href="#hc-b15"/><use href="#hc-b16"/><use href="#hc-b17"/><use href="#hc-b18"/><use href="#hc-b19"/><use href="#hc-b20"/><use href="#hc-b21"/><use href="#hc-b22"/><use href="#hc-b23"/><use href="#hc-b24"/><use href="#hc-b25"/></g>
        <g id="hc-band-wires"><use href="#hc-band-half"/><use href="#hc-band-half" transform="translate(1440,0) scale(-1,1)"/></g>
        <g id="hc-band-pads-half"><circle cx="715.2" cy="109.1" r="4.8"/><circle cx="715.2" cy="21.7" r="4.8"/><circle cx="704.1" cy="54.1" r="4.8"/><circle cx="661.9" cy="99.5" r="4.8"/><circle cx="594.8" cy="109.1" r="4.8"/><circle cx="564.2" cy="94.6" r="4.8"/><circle cx="468.2" cy="92.1" r="4.8"/><circle cx="448.7" cy="92.1" r="4.8"/><circle cx="429.3" cy="109.1" r="4.8"/><circle cx="347.4" cy="109.1" r="4.8"/><circle cx="300.3" cy="92.1" r="4.8"/><circle cx="222" cy="109.1" r="4.8"/><circle cx="167.2" cy="99.5" r="4.8"/><circle cx="150.7" cy="84.7" r="4.8"/><circle cx="44.7" cy="109.1" r="4.8"/><circle cx="67.1" cy="43.1" r="4.8"/><circle cx="18.8" cy="109.1" r="4.8"/><circle cx="12.3" cy="36.1" r="4.8"/><path d="M667.9 71.8 L675.5 71.8 L675.5 49.2 L667.9 49.2Z"/><path d="M600.3 54.1 L607.9 54.1 L607.9 46.6 L600.3 46.6Z"/><path d="M600.3 44.5 L607.9 44.5 L607.9 40 L600.3 40Z"/><path d="M600.3 33.4 L607.9 33.4 L607.9 30.6 L600.3 30.6Z"/><path d="M600.3 28.5 L607.9 28.5 L607.9 13.5 L600.3 13.5Z"/><path d="M555.6 14.7 L563.2 14.7 L563.2 9.7 L555.6 9.7Z"/><path d="M555.6 5.8 L563.2 5.8 L563.2 2.3 L555.6 2.3Z"/><path d="M486.7 80.8 L494.3 80.8 L494.3 75.2 L486.7 75.2Z"/><path d="M486.7 68.2 L494.3 68.2 L494.3 63 L486.7 63Z"/><path d="M486.7 59.2 L494.3 59.2 L494.3 40.2 L486.7 40.2Z"/><path d="M486.7 73.4 L494.3 73.4 L494.3 70 L486.7 70Z"/><path d="M453.3 40.6 L460.9 40.6 L460.9 36.5 L453.3 36.5Z"/><path d="M453.3 33.4 L460.9 33.4 L460.9 29.2 L453.3 29.2Z"/><path d="M453.3 24.3 L460.9 24.3 L460.9 21.2 L453.3 21.2Z"/><path d="M453.3 19.8 L460.9 19.8 L460.9 13.1 L453.3 13.1Z"/><path d="M453.3 9.3 L460.9 9.3 L460.9 0 L453.3 0Z"/><path d="M390.1 74.4 L397.7 74.4 L397.7 66.5 L390.1 66.5Z"/><path d="M390.1 64.4 L397.7 64.4 L397.7 55.3 L390.1 55.3Z"/><path d="M390.1 50.8 L397.7 50.8 L397.7 44.9 L390.1 44.9Z"/><path d="M390.1 42.8 L397.7 42.8 L397.7 40.3 L390.1 40.3Z"/><path d="M390.1 38.9 L397.7 38.9 L397.7 33.8 L390.1 33.8Z"/><path d="M354.1 59.2 L361.7 59.2 L361.7 56.7 L354.1 56.7Z"/><path d="M354.1 54.6 L361.7 54.6 L361.7 48.3 L354.1 48.3Z"/><path d="M354.1 47 L361.7 47 L361.7 37.9 L354.1 37.9Z"/><path d="M354.1 29.2 L361.7 29.2 L361.7 23.6 L354.1 23.6Z"/><path d="M354.1 21.2 L361.7 21.2 L361.7 18.5 L354.1 18.5Z"/><path d="M305.9 23.6 L313.4 23.6 L313.4 20.1 L305.9 20.1Z"/><path d="M305.9 17 L313.4 17 L313.4 8.6 L305.9 8.6Z"/><path d="M305.9 6.5 L313.4 6.5 L313.4 1.3 L305.9 1.3Z"/><path d="M254.6 65.4 L262.2 65.4 L262.2 63.3 L254.6 63.3Z"/><path d="M254.6 60.9 L262.2 60.9 L262.2 57.8 L254.6 57.8Z"/><path d="M254.6 56.4 L262.2 56.4 L262.2 49.7 L254.6 49.7Z"/><path d="M254.6 43.8 L262.2 43.8 L262.2 39.3 L254.6 39.3Z"/><path d="M254.6 37.9 L262.2 37.9 L262.2 36 L254.6 36Z"/><path d="M87.7 22.8 L95.3 22.8 L95.3 18.7 L87.7 18.7Z"/><path d="M87.7 17 L95.3 17 L95.3 11.4 L87.7 11.4Z"/><path d="M87.7 10 L95.3 10 L95.3 4.8 L87.7 4.8Z"/><path d="M44.6 46.7 L52.2 46.7 L52.2 39.6 L44.6 39.6Z"/><path d="M44.6 37.9 L52.2 37.9 L52.2 29.9 L44.6 29.9Z"/><path d="M44.6 24.7 L52.2 24.7 L52.2 14.9 L44.6 14.9Z"/><path d="M44.6 12.1 L52.2 12.1 L52.2 5.1 L44.6 5.1Z"/><path d="M155.3 48.5 L162.9 48.5 L162.9 45.2 L155.3 45.2Z"/><path d="M155.3 24 L162.9 24 L162.9 19.1 L155.3 19.1Z"/><path d="M155.3 31.3 L162.9 31.3 L162.9 26.4 L155.3 26.4Z"/><path d="M155.3 43.1 L162.9 43.1 L162.9 36.2 L155.3 36.2Z"/><path d="M25.7 86 L33.3 86 L33.3 80.4 L25.7 80.4Z"/><path d="M25.7 70 L33.3 70 L33.3 64.4 L25.7 64.4Z"/><path d="M25.7 75.5 L33.3 75.5 L33.3 71.3 L25.7 71.3Z"/><path d="M25.7 93.9 L33.3 93.9 L33.3 88.1 L25.7 88.1Z"/><path d="M0 20.3 L7.6 20.3 L7.6 14.9 L0 14.9Z"/><path d="M0 13.1 L7.6 13.1 L7.6 10 L0 10Z"/><path d="M0 7.9 L7.6 7.9 L7.6 5.5 L0 5.5Z"/><path d="M209.6 48.5 L217.2 48.5 L217.2 45.2 L209.6 45.2Z"/><path d="M209.6 42.8 L217.2 42.8 L217.2 36.8 L209.6 36.8Z"/><path d="M209.6 30.6 L217.2 30.6 L217.2 26 L209.6 26Z"/><path d="M209.6 23.3 L217.2 23.3 L217.2 19.1 L209.6 19.1Z"/><path d="M667.9 46.2 L675.5 46.2 L675.5 42.9 L667.9 42.9Z"/><path d="M667.9 40.5 L675.5 40.5 L675.5 37.2 L667.9 37.2Z"/><path d="M667.9 28.3 L675.5 28.3 L675.5 25 L667.9 25Z"/></g>
        <g id="hc-band-pads"><use href="#hc-band-pads-half"/><use href="#hc-band-pads-half" transform="translate(1440,0) scale(-1,1)"/></g>
        <g id="hc-band-rings-half"><circle cx="625.4" cy="79.8" r="4.8"/><circle cx="533.5" cy="18.7" r="4.8"/><circle cx="495.3" cy="109.1" r="4.8"/><circle cx="514.3" cy="74.3" r="4.8"/><circle cx="407.5" cy="13" r="4.8"/><circle cx="380.3" cy="74.3" r="4.8"/><circle cx="253.8" cy="109.1" r="4.8"/><circle cx="232" cy="65.4" r="4.8"/><circle cx="100.1" cy="109.1" r="4.8"/><circle cx="66.5" cy="109.1" r="4.8"/><circle cx="684.8" cy="109.1" r="4.8"/></g>
        <g id="hc-band-rings"><use href="#hc-band-rings-half"/><use href="#hc-band-rings-half" transform="translate(1440,0) scale(-1,1)"/></g>
        <path id="hc-c0" pathLength="100" d="M192.9 2.3 L190.4 26.6"/>
        <path id="hc-c1" pathLength="100" d="M136.8 2.3 L135.7 9.8 L162.7 38"/>
        <path id="hc-c2" pathLength="100" d="M74.5 2.3 L111.3 40.7 L134.5 34 L150.5 50.8"/>
        <path id="hc-c3" pathLength="100" d="M98.2 2.3 L99.2 3.4 L85 36.5 L107.6 60.1 L130.9 55.8 L148.5 74.2"/>
        <path id="hc-c4" pathLength="100" d="M27.9 2.3 L39.2 14.1 L57.8 8 L74.1 25.1"/>
        <path id="hc-c5" pathLength="100" d="M5 57 L57.7 43.5 L93.9 81.3"/>
        <path id="hc-c6" pathLength="100" d="M5 17.9 L25 38.8 L18.7 76.3 L49.5 108.5 L74.2 104.2 L97.1 128"/>
        <path id="hc-c7" pathLength="100" d="M5 103.9 L13 102.1 L57.2 148.3"/>
        <path id="hc-c8" pathLength="100" d="M5 126.8 L10.6 126.6 L29.8 146.7 L20.5 171.1 L35.2 186.4"/>
        <path id="hc-c9" pathLength="100" d="M5 211.6 L18.2 210.4"/>
        <path id="hc-c10" pathLength="100" d="M176.4 2.3 L187.2 13.6 L177.9 37.9 L192.6 53.3"/>
        <path id="hc-c11" pathLength="100" d="M103.9 2.3 L104.2 2.7 L100 33.9 L144.2 80.1 L175.6 77.3"/>
        <path id="hc-c12" pathLength="100" d="M46.7 2.3 L46.5 14.3 L64.1 32.7 L78.7 27.1 L115 65 L110.2 80.9 L135.3 107.2"/>
        <path id="hc-c13" pathLength="100" d="M11.7 25.2 L24.3 38.3 L42 35.2 L70.4 64.8 L54.6 111 L96 154.2"/>
        <path id="hc-c14" pathLength="100" d="M5 64.3 L33.3 57.5 L90 116.7 L116.1 108.7"/>
        <path id="hc-c15" pathLength="100" d="M5 111.8 L22.4 130 L46.1 122.3 L63 139.9 L60.2 160.9 L74.9 176.3"/>
        <path id="hc-c16" pathLength="100" d="M38.5 137.5 L35.4 163.6 L64.1 193.7"/>
        <path id="hc-c17" pathLength="100" d="M5 151.2 L15.1 161.8 L9.1 188.8 L24.3 204.7 L37.6 200 L47.8 210.7"/>
        <g id="hc-corner-wires"><use href="#hc-c0"/><use href="#hc-c1"/><use href="#hc-c2"/><use href="#hc-c3"/><use href="#hc-c4"/><use href="#hc-c5"/><use href="#hc-c6"/><use href="#hc-c7"/><use href="#hc-c8"/><use href="#hc-c9"/><use href="#hc-c10"/><use href="#hc-c11"/><use href="#hc-c12"/><use href="#hc-c13"/><use href="#hc-c14"/><use href="#hc-c15"/><use href="#hc-c16"/><use href="#hc-c17"/></g>
        <g id="hc-corner-pads"><circle cx="162.8" cy="38" r="4.3"/><circle cx="150.6" cy="50.7" r="4.3"/><circle cx="148.6" cy="74.2" r="4.3"/><circle cx="97.1" cy="128" r="4.3"/><circle cx="55.1" cy="146.6" r="4.3"/><circle cx="18.2" cy="210.4" r="4.3"/><path d="M172.4 13.7 L169 10.2 L164.3 15.2 L167.7 18.7Z"/><path d="M164.8 5.8 L161.7 2.5 L156.9 7.5 L160.1 10.8Z"/><path d="M168 9.1 L165.9 6.9 L161.1 11.9 L163.2 14.1Z"/><path d="M127.3 10.3 L124.8 7.7 L120 12.7 L122.5 15.3Z"/><path d="M122.9 5.8 L120.4 3.1 L115.6 8.1 L118.1 10.8Z"/><path d="M69.3 3.9 L65.5 0 L60.7 5 L64.5 8.9Z"/><path d="M44.2 32.9 L40.8 29.3 L36 34.3 L39.4 37.9Z"/><path d="M37.6 26.1 L34.3 22.6 L29.5 27.5 L32.9 31.1Z"/><path d="M32.6 20.8 L26.3 14.2 L21.5 19.2 L27.8 25.8Z"/><path d="M23.8 11.6 L19.7 7.4 L15 12.4 L19 16.6Z"/><path d="M107.9 73.1 L103.1 68.1 L98.3 73.1 L103.1 78.1Z"/><path d="M101.8 66.8 L96.4 61.1 L91.6 66.1 L97 71.8Z"/><path d="M93.6 58.3 L90.1 54.5 L85.3 59.5 L88.9 63.2Z"/><path d="M88.8 53.2 L87.3 51.7 L82.5 56.7 L84 58.2Z"/><path d="M86.5 50.8 L83.4 47.6 L78.6 52.6 L81.7 55.8Z"/><path d="M76.1 87.2 L74.6 85.6 L69.8 90.6 L71.3 92.1Z"/><path d="M73.3 84.3 L69.5 80.4 L64.8 85.4 L68.5 89.3Z"/><path d="M68.7 79.5 L63.3 73.8 L58.5 78.8 L63.9 84.5Z"/><path d="M58 68.3 L54.7 64.8 L49.9 69.8 L53.2 73.3Z"/><path d="M53.2 63.3 L51.6 61.6 L46.8 66.6 L48.4 68.3Z"/><path d="M24.3 96.5 L22.2 94.3 L17.4 99.3 L19.5 101.5Z"/><path d="M20.3 92.4 L15.3 87.1 L10.5 92.1 L15.5 97.3Z"/><path d="M14 85.8 L10.9 82.5 L6.1 87.5 L9.3 90.8Z"/><path d="M9.6 81.2 L6.5 77.9 L1.7 82.9 L4.8 86.2Z"/><path d="M12.9 41.3 L10.6 38.9 L5.9 43.9 L8.1 46.3Z"/><path d="M10 38.3 L4.8 32.8 L0 37.8 L5.2 43.2Z"/><path d="M17.3 156.5 L16 155.1 L11.2 160.1 L12.5 161.5Z"/><path d="M14.5 153.6 L12.6 151.6 L7.9 156.6 L9.8 158.6Z"/><path d="M11.8 150.8 L7.8 146.6 L3 151.6 L7 155.8Z"/><circle cx="175.6" cy="77.3" r="4.3"/><circle cx="135.4" cy="107.1" r="4.3"/><circle cx="116.1" cy="108.7" r="4.3"/><circle cx="64.1" cy="193.7" r="4.3"/><circle cx="38.5" cy="141.8" r="4.3"/><circle cx="47.8" cy="210.7" r="4.3"/><path d="M90.6 153.3 L88.1 150.7 L83.4 155.7 L85.8 158.3Z"/><path d="M76.8 138.9 L69.9 131.7 L65.1 136.6 L72 143.9Z"/><path d="M65.7 127.3 L61.5 122.9 L56.7 127.9 L60.9 132.3Z"/><path d="M87.1 149.6 L83.7 146.1 L78.9 151.1 L82.3 154.6Z"/><path d="M82.9 145.2 L79.7 142 L75 146.9 L78.1 150.2Z"/><path d="M136.2 42.3 L134.2 40.2 L129.4 45.2 L131.4 47.3Z"/><path d="M132.7 38.6 L129.1 34.9 L124.4 39.9 L127.9 43.6Z"/><path d="M125.4 31 L122.6 28.1 L117.9 33.1 L120.6 36Z"/><path d="M121 26.4 L118.5 23.8 L113.7 28.7 L116.2 31.4Z"/></g>
        <g id="hc-corner-rings"><circle cx="190.1" cy="30.9" r="4.3"/><circle cx="77.1" cy="28.1" r="4.3"/><circle cx="96.9" cy="84.4" r="4.3"/><circle cx="38.2" cy="189.5" r="4.3"/><circle cx="195.6" cy="56.3" r="4.3"/><circle cx="99" cy="157.3" r="4.3"/><circle cx="77.8" cy="179.4" r="4.3"/></g>
        </defs>
        <g class="hero-circuit__wires"><use href="#hc-band-wires"/><use href="#hc-band-wires" transform="translate(1440,0)"/><use href="#hc-band-wires" transform="translate(2880,0)"/><use href="#hc-band-wires" transform="translate(4320,0)"/><use href="#hc-band-wires" transform="translate(5760,0)"/><use href="#hc-band-wires" transform="translate(7200,0)"/><use href="#hc-band-wires" transform="translate(8640,0)"/><use href="#hc-band-wires" transform="translate(10080,0)"/><use href="#hc-band-wires" transform="translate(11520,0)"/><use href="#hc-band-wires" transform="translate(12960,0)"/><use href="#hc-band-wires" transform="translate(14400,0)"/><use href="#hc-band-wires" transform="translate(15840,0)"/><use href="#hc-band-wires" transform="translate(17280,0)"/><use href="#hc-band-wires" transform="translate(18720,0)"/><use href="#hc-band-wires" transform="translate(20160,0)"/></g>
        <g class="hero-circuit__pads"><use href="#hc-band-pads"/><use href="#hc-band-pads" transform="translate(1440,0)"/><use href="#hc-band-pads" transform="translate(2880,0)"/><use href="#hc-band-pads" transform="translate(4320,0)"/><use href="#hc-band-pads" transform="translate(5760,0)"/><use href="#hc-band-pads" transform="translate(7200,0)"/><use href="#hc-band-pads" transform="translate(8640,0)"/><use href="#hc-band-pads" transform="translate(10080,0)"/><use href="#hc-band-pads" transform="translate(11520,0)"/><use href="#hc-band-pads" transform="translate(12960,0)"/><use href="#hc-band-pads" transform="translate(14400,0)"/><use href="#hc-band-pads" transform="translate(15840,0)"/><use href="#hc-band-pads" transform="translate(17280,0)"/><use href="#hc-band-pads" transform="translate(18720,0)"/><use href="#hc-band-pads" transform="translate(20160,0)"/></g>
        <g class="hero-circuit__rings"><use href="#hc-band-rings"/><use href="#hc-band-rings" transform="translate(1440,0)"/><use href="#hc-band-rings" transform="translate(2880,0)"/><use href="#hc-band-rings" transform="translate(4320,0)"/><use href="#hc-band-rings" transform="translate(5760,0)"/><use href="#hc-band-rings" transform="translate(7200,0)"/><use href="#hc-band-rings" transform="translate(8640,0)"/><use href="#hc-band-rings" transform="translate(10080,0)"/><use href="#hc-band-rings" transform="translate(11520,0)"/><use href="#hc-band-rings" transform="translate(12960,0)"/><use href="#hc-band-rings" transform="translate(14400,0)"/><use href="#hc-band-rings" transform="translate(15840,0)"/><use href="#hc-band-rings" transform="translate(17280,0)"/><use href="#hc-band-rings" transform="translate(18720,0)"/><use href="#hc-band-rings" transform="translate(20160,0)"/></g>
        <g class="hero-circuit__charges" transform="translate(10080,0)">
          <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--p1" href="#hc-b4"/>
          <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--p2" href="#hc-b12"/>
          <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--p3" href="#hc-b21"/>
          <g transform="translate(1440,0) scale(-1,1)">
            <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--mirrored hero-circuit__charge--p1" href="#hc-b4"/>
            <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--mirrored hero-circuit__charge--p2" href="#hc-b12"/>
            <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--mirrored hero-circuit__charge--p3" href="#hc-b21"/>
          </g>
        </g>
        <g class="hero-circuit__nodes" transform="translate(10080,0)">
          <circle class="hero-circuit__node hero-circuit__node--a" cx="594.8" cy="109.1" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--b" cx="150.7" cy="84.7" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--c" cx="845.2" cy="109.1" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--d" cx="1289.3" cy="84.7" r="3.6"/>
        </g>
      </svg>
      <svg class="hero-circuit__layer hero-circuit__layer--band-bottom" viewBox="0 0 21600 114" preserveAspectRatio="xMidYMid slice" focusable="false">
        <g class="hero-circuit__wires"><use href="#hc-band-wires"/><use href="#hc-band-wires" transform="translate(1440,0)"/><use href="#hc-band-wires" transform="translate(2880,0)"/><use href="#hc-band-wires" transform="translate(4320,0)"/><use href="#hc-band-wires" transform="translate(5760,0)"/><use href="#hc-band-wires" transform="translate(7200,0)"/><use href="#hc-band-wires" transform="translate(8640,0)"/><use href="#hc-band-wires" transform="translate(10080,0)"/><use href="#hc-band-wires" transform="translate(11520,0)"/><use href="#hc-band-wires" transform="translate(12960,0)"/><use href="#hc-band-wires" transform="translate(14400,0)"/><use href="#hc-band-wires" transform="translate(15840,0)"/><use href="#hc-band-wires" transform="translate(17280,0)"/><use href="#hc-band-wires" transform="translate(18720,0)"/><use href="#hc-band-wires" transform="translate(20160,0)"/></g>
        <g class="hero-circuit__pads"><use href="#hc-band-pads"/><use href="#hc-band-pads" transform="translate(1440,0)"/><use href="#hc-band-pads" transform="translate(2880,0)"/><use href="#hc-band-pads" transform="translate(4320,0)"/><use href="#hc-band-pads" transform="translate(5760,0)"/><use href="#hc-band-pads" transform="translate(7200,0)"/><use href="#hc-band-pads" transform="translate(8640,0)"/><use href="#hc-band-pads" transform="translate(10080,0)"/><use href="#hc-band-pads" transform="translate(11520,0)"/><use href="#hc-band-pads" transform="translate(12960,0)"/><use href="#hc-band-pads" transform="translate(14400,0)"/><use href="#hc-band-pads" transform="translate(15840,0)"/><use href="#hc-band-pads" transform="translate(17280,0)"/><use href="#hc-band-pads" transform="translate(18720,0)"/><use href="#hc-band-pads" transform="translate(20160,0)"/></g>
        <g class="hero-circuit__rings"><use href="#hc-band-rings"/><use href="#hc-band-rings" transform="translate(1440,0)"/><use href="#hc-band-rings" transform="translate(2880,0)"/><use href="#hc-band-rings" transform="translate(4320,0)"/><use href="#hc-band-rings" transform="translate(5760,0)"/><use href="#hc-band-rings" transform="translate(7200,0)"/><use href="#hc-band-rings" transform="translate(8640,0)"/><use href="#hc-band-rings" transform="translate(10080,0)"/><use href="#hc-band-rings" transform="translate(11520,0)"/><use href="#hc-band-rings" transform="translate(12960,0)"/><use href="#hc-band-rings" transform="translate(14400,0)"/><use href="#hc-band-rings" transform="translate(15840,0)"/><use href="#hc-band-rings" transform="translate(17280,0)"/><use href="#hc-band-rings" transform="translate(18720,0)"/><use href="#hc-band-rings" transform="translate(20160,0)"/></g>
        <g class="hero-circuit__charges" transform="translate(10080,0)">
          <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--p1" href="#hc-b4"/>
          <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--p2" href="#hc-b12"/>
          <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--p3" href="#hc-b21"/>
          <g transform="translate(1440,0) scale(-1,1)">
            <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--mirrored hero-circuit__charge--p1" href="#hc-b4"/>
            <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--mirrored hero-circuit__charge--p2" href="#hc-b12"/>
            <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--mirrored hero-circuit__charge--p3" href="#hc-b21"/>
          </g>
        </g>
        <g class="hero-circuit__nodes" transform="translate(10080,0)">
          <circle class="hero-circuit__node hero-circuit__node--e" cx="594.8" cy="109.1" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--f" cx="150.7" cy="84.7" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--g" cx="845.2" cy="109.1" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--h" cx="1289.3" cy="84.7" r="3.6"/>
        </g>
      </svg>
      <svg class="hero-circuit__layer hero-circuit__layer--corner-tl" viewBox="0 0 200 215" preserveAspectRatio="xMinYMin meet" focusable="false">
        <g class="hero-circuit__wires"><use href="#hc-corner-wires"/></g>
        <g class="hero-circuit__pads"><use href="#hc-corner-pads"/></g>
        <g class="hero-circuit__rings"><use href="#hc-corner-rings"/></g>
        <g class="hero-circuit__charges">
          <use class="hero-circuit__charge hero-circuit__charge--c1" href="#hc-c7"/>
          <use class="hero-circuit__charge hero-circuit__charge--c2 hero-circuit__charge--back" href="#hc-c10"/>
          <use class="hero-circuit__charge hero-circuit__charge--c3" href="#hc-c12"/>
        </g>
        <g class="hero-circuit__nodes">
          <circle class="hero-circuit__node hero-circuit__node--i" cx="162.8" cy="38" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--j" cx="148.6" cy="74.2" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--k" cx="18.2" cy="210.4" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--l" cx="64.1" cy="193.7" r="3.6"/>
        </g>
      </svg>
      <svg class="hero-circuit__layer hero-circuit__layer--corner-tr" viewBox="0 0 200 215" preserveAspectRatio="xMinYMin meet" focusable="false">
        <g class="hero-circuit__wires"><use href="#hc-corner-wires"/></g>
        <g class="hero-circuit__pads"><use href="#hc-corner-pads"/></g>
        <g class="hero-circuit__rings"><use href="#hc-corner-rings"/></g>
        <g class="hero-circuit__charges">
          <use class="hero-circuit__charge hero-circuit__charge--c1" href="#hc-c7"/>
          <use class="hero-circuit__charge hero-circuit__charge--c2 hero-circuit__charge--back" href="#hc-c10"/>
          <use class="hero-circuit__charge hero-circuit__charge--c3" href="#hc-c12"/>
        </g>
        <g class="hero-circuit__nodes">
          <circle class="hero-circuit__node hero-circuit__node--m" cx="162.8" cy="38" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--n" cx="148.6" cy="74.2" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--o" cx="18.2" cy="210.4" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--p" cx="64.1" cy="193.7" r="3.6"/>
        </g>
      </svg>
      <svg class="hero-circuit__layer hero-circuit__layer--corner-bl" viewBox="0 0 200 215" preserveAspectRatio="xMinYMin meet" focusable="false">
        <g class="hero-circuit__wires"><use href="#hc-corner-wires"/></g>
        <g class="hero-circuit__pads"><use href="#hc-corner-pads"/></g>
        <g class="hero-circuit__rings"><use href="#hc-corner-rings"/></g>
        <g class="hero-circuit__charges">
          <use class="hero-circuit__charge hero-circuit__charge--c1" href="#hc-c7"/>
          <use class="hero-circuit__charge hero-circuit__charge--c2 hero-circuit__charge--back" href="#hc-c10"/>
          <use class="hero-circuit__charge hero-circuit__charge--c3" href="#hc-c12"/>
        </g>
        <g class="hero-circuit__nodes">
          <circle class="hero-circuit__node hero-circuit__node--q" cx="162.8" cy="38" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--r" cx="148.6" cy="74.2" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--s" cx="18.2" cy="210.4" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--t" cx="64.1" cy="193.7" r="3.6"/>
        </g>
      </svg>
      <svg class="hero-circuit__layer hero-circuit__layer--corner-br" viewBox="0 0 200 215" preserveAspectRatio="xMinYMin meet" focusable="false">
        <g class="hero-circuit__wires"><use href="#hc-corner-wires"/></g>
        <g class="hero-circuit__pads"><use href="#hc-corner-pads"/></g>
        <g class="hero-circuit__rings"><use href="#hc-corner-rings"/></g>
        <g class="hero-circuit__charges">
          <use class="hero-circuit__charge hero-circuit__charge--c1" href="#hc-c7"/>
          <use class="hero-circuit__charge hero-circuit__charge--c2 hero-circuit__charge--back" href="#hc-c10"/>
          <use class="hero-circuit__charge hero-circuit__charge--c3" href="#hc-c12"/>
        </g>
        <g class="hero-circuit__nodes">
          <circle class="hero-circuit__node hero-circuit__node--u" cx="162.8" cy="38" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--v" cx="148.6" cy="74.2" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--w" cx="18.2" cy="210.4" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--x" cx="64.1" cy="193.7" r="3.6"/>
        </g>
      </svg>
    </div>
<!--hero-circuit:end-->

<div class="container page-hero__inner">
      <h1 class="page-hero__title"><?= h($data['hero']['title']) ?></h1>
<?php if (trim((string)$data['hero']['subtitle']) !== ''): ?>
      <p class="page-hero__subtitle"><?= h($data['hero']['subtitle']) ?></p>
<?php endif; ?>
    </div>
  </section>

  <!-- ============================ Milestones ============================
       Section order on this page follows the live site rather than the NextJS
       build, which opens with professional excellence and closes with the
       timeline. The timeline styling follows the live site too: a year, a rail
       with a marker, and the entry beside it — and it is
       assets/css/pages/milestones.css, which /pages/milestones/ loads as well.

       A WINDOW, NOT THE WHOLE HISTORY. This band shows the most recent
       MILESTONES_WINDOW years and links to /pages/milestones/ for the rest.
       Unbounded it grew by a box and a margin — 130 to 160px, at EVERY width,
       because the timeline is one entry per row on every screen — for each
       year the company added, with nothing to stop it.

       The document is content/milestones.json, not this page's own. The band
       that used to be here is deprecated and still in the contract; see the
       note on it in company_defaults() and the read-through in
       milestones_load().
       ==================================================================== -->
<?php if (milestones_band_shown($milestones, 'timeline')): ?>
  <section class="section milestones" aria-labelledby="milestones-heading">
    <div class="container">
      <div data-reveal data-reveal-delay class="section__header">
<?php if (trim((string)$milestones['timeline']['eyebrow']) !== ''): ?>
        <span class="section__eyebrow"><?= h($milestones['timeline']['eyebrow']) ?></span>
<?php endif; ?>
        <h2 class="section__title" id="milestones-heading"><?= h($milestones['timeline']['title']) ?></h2>
<?php if (trim((string)$milestones['timeline']['lead']) !== ''): ?>
<?php /* Printed unescaped, which is safe for exactly one reason: it
                 went through rt_sanitise_html() before it was stored, and that
                 function writes its output from an allow-list rather than
                 passing anything through. See lib/html.php. */ ?>
        <div class="section__lead"><?= $milestones['timeline']['lead'] ?></div>
<?php endif; ?>
      </div>

      <ol class="timeline" role="list">
<?php foreach ($recent as $row): ?>
        <li data-reveal data-reveal-delay class="timeline__item">
          <p class="timeline__year"><?= h($row['year']) ?></p>
          <div class="timeline__box">
            <h3 class="timeline__title"><?= h($row['title']) ?></h3>
            <p class="timeline__text"><?= h($row['text']) ?></p>
          </div>
        </li>
<?php endforeach; ?>
      </ol>
<?php if (count($recent) < count($timeline)): ?>
<?php /* Only when something is actually withheld. A "see all" under a list
             that already IS all of it is a link to the page you are on, with
             one extra name for it — and it would appear the day the timeline
             was first published, before there was anything to see. */ ?>
      <p data-reveal data-reveal-delay class="milestones__more">
        <a class="btn btn--secondary"
<?php /* The route out of SEO_ROUTES rather than typed here. It is the
                     constant that DEFINES the address — the canonical, the
                     sitemap entry and the breadcrumb all come from the same
                     line — so a link that spelled it again would be the one
                     copy nothing keeps in step. */ ?>
           href="<?= h(SEO_ROUTES['milestones'][0]) ?>">See all <?= count($timeline) ?> milestones</a>
      </p>
<?php endif; ?>
    </div>
  </section>
<?php endif; ?>

  <!-- =========================== Our Background =========================== -->
<?php if (company_band_shown($data, 'background')): ?>
  <section class="section section--surface background" aria-labelledby="background-heading">
    <div class="container">
      <div data-reveal data-reveal-delay class="section__header">
<?php if (trim((string)$data['background']['eyebrow']) !== ''): ?>
        <span class="section__eyebrow"><?= h($data['background']['eyebrow']) ?></span>
<?php endif; ?>
        <h2 class="section__title" id="background-heading"><?= h($data['background']['title']) ?></h2>
      </div>
<?php if (company_band_shown($data, 'experience')): ?>

      <div class="background__block">
        <h3 data-reveal data-reveal-delay class="background__title"><?= h($data['experience']['title']) ?></h3>
        <div class="stats">
<?php foreach (company_shown($data, 'experience') as $row): ?>
          <div data-reveal data-reveal-delay class="stat">
<?php /* data-count-up is read by animations.js, which parses the
                     figure with /^\s*(\d+)(.*)$/ and animates the leading
                     number. A figure that does not start with a digit renders
                     as written and simply does not count up; the editor
                     refuses one, so this degrades rather than lies. */ ?>
            <p class="stat__figure" data-count-up><?= h($row['figure']) ?></p>
            <p class="stat__label"><?= h($row['label']) ?></p>
          </div>
<?php endforeach; ?>
        </div>
      </div>
<?php endif; ?>
<?php if (company_band_shown($data, 'clients')): ?>

<?php /* THE WALL IS CAPPED, and the rest is behind a native expander.
               Unbounded this was the page's worst growth: .clients is one row
               per eight rem, so fifty logos on a phone — where the grid used
               to collapse to a SINGLE column — was about eight screens of
               nothing but logos. See COMPANY_CLIENTS_WALL for why twelve.

               <details> and not a script, for the reason the certifications
               page gives: the browser already implements it, so it opens with
               JavaScript off, is reachable by keyboard with no roving
               tabindex, and is announced correctly with no aria- attribute.

               NOTHING IN THE TAIL CARRIES data-reveal. A closed <details> has
               no layout box, so IntersectionObserver never reports its
               children as intersecting — a card marked for reveal in there
               would still be at opacity 0 when somebody opened it. That is
               the tab-panel failure tools/apply_reveals.py refuses to create,
               and it would be just as real here. */ ?>
<?php [$wall, $rest] = company_wall(company_shown($data, 'clients'), COMPANY_CLIENTS_WALL); ?>

      <div class="background__block">
        <h3 data-reveal data-reveal-delay class="background__title"><?= h($data['clients']['title']) ?></h3>
        <ul class="clients" role="list" data-reveal-rows>
<?php foreach ($wall as $row): ?>
          <li data-reveal data-reveal-delay class="client-card">
            <?= company_picture($row['image'], 'client-card__logo', (string)$row['name'],
                                   'company.clients') ?>

            <span class="visually-hidden"><?= h($row['name']) ?></span>
          </li>
<?php endforeach; ?>
        </ul>
<?php if ($rest): ?>
        <details class="wall-more">
<?php /* TWO LABELS IN THE MARKUP, one hidden by CSS on each side of
                     [open], rather than one label and content: attr(). Both
                     spellings are then real text: findable, translatable, and
                     in the accessibility tree — a ::before's content is none
                     of those. display:none keeps exactly one of them there at
                     a time, so nothing is announced twice. */ ?>
          <summary class="wall-more__summary">
            <span class="wall-more__shut">See all <?= count($wall) + count($rest) ?> clients</span>
            <span class="wall-more__open">Show fewer</span>
          </summary>
          <ul class="clients wall-more__rest" role="list">
<?php foreach ($rest as $row): ?>
            <li class="client-card">
              <?= company_picture($row['image'], 'client-card__logo', (string)$row['name'],
                                     'company.clients') ?>

              <span class="visually-hidden"><?= h($row['name']) ?></span>
            </li>
<?php endforeach; ?>
          </ul>
        </details>
<?php endif; ?>
      </div>
<?php endif; ?>
<?php if (company_band_shown($data, 'journey')): ?>
<?php $photos = company_shown($data, 'journey'); ?>

      <div data-reveal data-reveal-delay class="background__block">
        <h3 class="background__title"><?= h($data['journey']['title']) ?></h3>
<?php if (trim((string)$data['journey']['lead']) !== ''): ?>
        <div class="background__lead"><?= $data['journey']['lead'] ?></div>
<?php endif; ?>
        <!-- One photograph at a time. The frame stays the size it always
             was — the slider is held to --slider-max rather than run to the
             full width of the band, so the pictures read as they did in the
             row. Without JavaScript the track is that row again. -->
        <div class="slider journey__slider" data-slider data-slider-interval="<?= (int)$data['journey']['interval'] ?>"
             aria-label="Our journey of growth">
          <div class="slider__viewport">
            <div class="slider__track" data-slider-track>
<?php foreach ($photos as $i => $row): ?>
<?php if ($i): ?>

<?php endif; ?>
              <div class="slider__slide">
                <div class="journey__item">
                  <?= company_picture($row['image'], 'journey__image', (string)$row['alt'],
                                     'company.journey') ?>

                </div>
              </div>
<?php endforeach; ?>
            </div>
          </div>

<?php /* The controls are markup, not something slider.js builds. Two
                   reasons, and both are load-bearing: tools/inject_icons.py
                   finds the symbols a page needs by scanning it for a literal
                   href="#name", and a button that only exists once JavaScript
                   has run is a control the no-JS page cannot have.

                   The dots are generated from the slide count rather than
                   written out, because slider.js matches a dot to a slide by
                   index — a hand-written list that drifts breaks the control
                   silently. */ ?>
          <div class="slider__controls">
            <button class="slider__arrow" type="button" data-slider-prev
                    aria-label="Previous photograph">
              <svg class="icon" aria-hidden="true" focusable="false"><use href="#chevron-left"></use></svg>
            </button>

            <div class="slider__dots">
<?php foreach ($photos as $i => $row): ?>
              <button class="slider__dot" type="button" data-slider-to="<?= $i ?>"
                      aria-label="Go to photograph <?= $i + 1 ?>"></button>
<?php endforeach; ?>
            </div>

            <button class="slider__arrow" type="button" data-slider-next
                    aria-label="Next photograph">
              <svg class="icon" aria-hidden="true" focusable="false"><use href="#chevron-right"></use></svg>
            </button>

            <button class="slider__pause" type="button" data-slider-pause
                    aria-label="Pause the slideshow">
              <svg class="icon slider__icon--pause" aria-hidden="true" focusable="false"><use href="#pause"></use></svg>
              <svg class="icon slider__icon--play" aria-hidden="true" focusable="false"><use href="#play"></use></svg>
            </button>
          </div>
        </div>
      </div>
<?php endif; ?>
    </div>
  </section>
<?php endif; ?>

  <!-- ====================== Our Professional Excellence ======================
       The technology list ships as an ordinary grid and is upgraded into the
       rotating sphere by tech-sphere.js. Everything is real markup either way,
       so the names are readable without scripting and to a crawler.
       ======================================================================= -->
<?php if (company_band_shown($data, 'excellence')): ?>
  <section class="section excellence" aria-labelledby="excellence-heading">
    <div class="container">
      <div data-reveal data-reveal-delay class="section__header">
<?php if (trim((string)$data['excellence']['eyebrow']) !== ''): ?>
        <span class="section__eyebrow"><?= h($data['excellence']['eyebrow']) ?></span>
<?php endif; ?>
        <h2 class="section__title" id="excellence-heading"><?= h($data['excellence']['title']) ?></h2>
<?php if (trim((string)$data['excellence']['lead']) !== ''): ?>
        <div class="section__lead"><?= $data['excellence']['lead'] ?></div>
<?php endif; ?>
      </div>
<?php if (company_band_shown($data, 'technology')): ?>

<?php /* data-reveal is on the BLOCK here and on the <h3> in the
               principles block below. That is not an oversight and must not be
               normalised: tools/apply_reveals.py marks a run of cards
               individually only up to MAX_STAGGER of them, and collapses a
               longer run — or a [data-slider] subtree — to one target so the
               cards are not hidden one by one. Fifty logos is a longer run.
               The same rule puts data-reveal on the journey block above. */ ?>
<?php /* THE SAME CAP AND THE SAME EXPANDER AS THE CLIENTS WALL, and the
               same reason: below the sphere's breakpoint this is a plain grid
               with nothing bounding it, and fifty plates was about 1,900px —
               the longest single block on a phone, longer than the timeline
               and the clients wall. See COMPANY_TECHNOLOGY_WALL for why
               eighteen rather than twelve.

               THE SPHERE STILL GETS EVERY LOGO. tech-sphere.js moves the tail
               out of the expander and into the list above when it turns the
               sphere on, and puts it back when it turns it off — so the cap
               follows the state the script already maintains on every resize,
               and place() is never handed a short list to spread over a whole
               sphere. Without the script there is no sphere at any width, and
               the cap is then the only thing acting, which is correct: nothing
               else is solving the length.

               Nothing in the tail carries data-reveal, for the reason the
               clients wall gives. */ ?>
<?php [$tech, $tech_rest] =
          company_wall(company_shown($data, 'technology'), COMPANY_TECHNOLOGY_WALL); ?>

      <div data-reveal data-reveal-delay class="excellence__block">
        <h3 class="background__title"><?= h($data['technology']['title']) ?></h3>
        <div class="tech-sphere" data-tech-sphere>
          <ul class="tech-sphere__list" role="list">
<?php foreach ($tech as $row): ?>
          <li class="tech-sphere__item">
            <span class="tech-sphere__face">
              <?= company_picture($row['image'], 'tech-sphere__logo', (string)$row['name'],
                                   'company.technology') ?>

            </span>
          </li>
<?php endforeach; ?>
          </ul>
<?php if ($tech_rest): ?>
          <details class="wall-more tech-sphere__more">
            <summary class="wall-more__summary">
              <span class="wall-more__shut">See all <?= count($tech) + count($tech_rest) ?> technologies</span>
              <span class="wall-more__open">Show fewer</span>
            </summary>
            <ul class="tech-sphere__list wall-more__rest" role="list" data-tech-spare>
<?php foreach ($tech_rest as $row): ?>
              <li class="tech-sphere__item">
                <span class="tech-sphere__face">
                  <?= company_picture($row['image'], 'tech-sphere__logo', (string)$row['name'],
                                       'company.technology') ?>

                </span>
              </li>
<?php endforeach; ?>
            </ul>
          </details>
<?php endif; ?>
        </div>
      </div>
<?php endif; ?>
<?php if (company_band_shown($data, 'principles')): ?>

      <div class="excellence__block">
        <h3 data-reveal data-reveal-delay class="background__title"><?= h($data['principles']['title']) ?></h3>
        <div class="values">
<?php foreach (company_shown($data, 'principles') as $row): ?>
          <article data-reveal data-reveal-delay class="value-card">
<?php if (trim((string)$row['icon']) !== ''): ?>
            <span class="value-card__icon"><svg class="icon" aria-hidden="true" focusable="false"><use href="#<?= h($row['icon']) ?>"></use></svg></span>
<?php endif; ?>
            <h4 class="value-card__title"><?= h($row['title']) ?></h4>
            <p class="value-card__text"><?= h($row['text']) ?></p>
          </article>
<?php endforeach; ?>
        </div>
      </div>
<?php endif; ?>
    </div>
  </section>
<?php endif; ?>

  <!-- ============================ Closing CTA ============================ -->
<?php if (company_band_shown($data, 'cta')): ?>
  <section class="cta-band" aria-labelledby="cp-cta-heading">
    <div class="container cta-band__inner">
      <h2 data-reveal data-reveal-delay class="cta-band__title" id="cp-cta-heading"><?= h($data['cta']['title']) ?></h2>
<?php if (trim((string)$data['cta']['text']) !== ''): ?>
      <div data-reveal data-reveal-delay class="cta-band__text"><?= $data['cta']['text'] ?></div>
<?php endif; ?>
<?php if (trim((string)$data['cta']['label']) !== '' && rt_safe_href((string)$data['cta']['href']) !== null): ?>
      <a data-reveal data-reveal-delay class="btn btn--primary btn--lg" href="<?= h(rt_safe_href((string)$data['cta']['href'])) ?>">
<?php if (trim((string)$data['cta']['icon']) !== ''): ?>
        <svg class="icon icon--sm" aria-hidden="true" focusable="false"><use href="#<?= h($data['cta']['icon']) ?>"></use></svg>
<?php endif; ?>
        <?= h($data['cta']['label']) ?>

      </a>
<?php endif; ?>
    </div>
  </section>
<?php endif; ?>

<?php /* Every icon a principle card or the button above may carry is named in
         the comment below. Read that comment before deleting it:
         tools/inject_icons.py finds the symbols a page needs by scanning it
         for a literal href="#name", and the names used above are chosen at run
         time, where the scan cannot see them. Keep this in step with
         COMPANY_ICONS in lib/contract.php — inject_icons.py --check says so
         when it drifts.

         <use href="#shield-alt"> <use href="#lightbulb"> <use href="#handshake">
         <use href="#clock"> <use href="#cogs"> <use href="#building">
         <use href="#globe"> <use href="#headset"> <use href="#comment-alt">
         <use href="#calendar-check"> <use href="#check-circle">
         <use href="#user-shield"> <use href="#eye"> <use href="#info-circle">
      */ ?>
</main>

<?php body_footer(); ?>

<?php body_dock('/pages/company-profile/'); ?>

<!-- Deferred so nothing blocks rendering. Order matters only in that main.js
     runs last: each module registers itself on window.Tech4Time, and main.js
     calls their init(). Pages that need no forms can omit forms.js. -->
<script src="/assets/js/theme-toggle.js" defer></script>
<script src="/assets/js/nav.js" defer></script>
<script src="/assets/js/animations.js?v=2" defer></script>
<script src="/assets/js/forms.js?v=2" defer></script>
<script src="/assets/js/dashboard.js" defer></script>
<script src="/assets/js/tech-sphere.js?v=2" defer></script>
<script src="/assets/js/slider.js" defer></script>
<!-- Versioned for the same reason the stylesheets are, and with a sharper
     edge: MODULES in this file is a hardcoded allow list, so a stale copy
     silently skips every module added since — no error, no console line,
     just a feature that is not there. -->
<script src="/assets/js/circuit.js?v=6" defer></script>
<script src="/assets/js/main.js?v=3" defer></script>
</body>
</html>
