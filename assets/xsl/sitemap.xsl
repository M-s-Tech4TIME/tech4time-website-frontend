<?xml version="1.0" encoding="UTF-8"?>
<!--
  Tech4TIME — a readable face for sitemap.xml.

  WHY THIS EXISTS
  A sitemap with no stylesheet is served exactly as written, and every browser
  puts its own warning above it: "This XML file does not appear to have any
  style information." Nothing is wrong when that appears — crawlers never see
  it and parse the file fine — but a person who opens the URL has no way to
  know that. This turns the same bytes into a table they can read.

  It changes nothing a crawler sees. The xml-stylesheet instruction in
  sitemap.xml is a rendering hint for browsers; Googlebot and every other
  parser ignore it and read the <urlset> underneath.

  THE CSP APPLIES HERE
  The transformed document inherits sitemap.xml's response headers, and
  .htaccess sets style-src 'self' — so there is no <style> block below and
  there never may be. The look comes from linked files, the same three the
  site's own pages use. Same rule for script-src: there is no script here, and
  the page needs none.

  MIME TYPE
  .htaccess maps .xsl to text/xsl. That mapping is not optional: the site sends
  X-Content-Type-Options: nosniff, so a browser that receives this file as
  text/plain refuses to apply it and shows the raw XML instead.
-->
<xsl:stylesheet version="1.0"
                xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9"
                exclude-result-prefixes="s">

<xsl:output method="html" encoding="UTF-8" indent="yes"
            doctype-system="about:legacy-compat"/>

<xsl:template match="/">
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Sitemap | Tech4TIME</title>

<!-- This page is a view of a machine file. It is not a page of the site and
     must never compete with one in search results. -->
<meta name="robots" content="noindex, follow"/>

<link rel="stylesheet" href="/assets/css/base.css"/>
<link rel="stylesheet" href="/assets/css/theme.css"/>
<link rel="stylesheet" href="/assets/css/sitemap.css"/>
</head>

<body class="sitemap-page">
<main class="sitemap">

  <header class="sitemap__head">
    <p class="sitemap__eyebrow">Tech4TIME</p>
    <h1 class="sitemap__title">XML Sitemap</h1>
    <p class="sitemap__lede">
      This file lists every page on <a href="https://tech4time.bd/">tech4time.bd</a>
      for search engines. You are seeing a readable version of it; a crawler
      reads the XML underneath.
    </p>
    <p class="sitemap__count">
      <xsl:value-of select="count(s:urlset/s:url)"/>
      <xsl:text> pages listed</xsl:text>
    </p>
  </header>

  <div class="sitemap__scroll">
    <table class="sitemap__table">
      <caption class="sitemap__caption">Pages in this sitemap</caption>
      <thead>
        <tr>
          <th scope="col">Page</th>
          <th scope="col">Last modified</th>
          <th scope="col">Changes</th>
          <th scope="col">Priority</th>
        </tr>
      </thead>
      <tbody>
        <xsl:for-each select="s:urlset/s:url">
          <tr>
            <td class="sitemap__loc">
              <a>
                <xsl:attribute name="href"><xsl:value-of select="s:loc"/></xsl:attribute>
                <xsl:value-of select="s:loc"/>
              </a>
            </td>
            <td class="sitemap__meta"><xsl:value-of select="s:lastmod"/></td>
            <td class="sitemap__meta"><xsl:value-of select="s:changefreq"/></td>
            <td class="sitemap__meta"><xsl:value-of select="s:priority"/></td>
          </tr>
        </xsl:for-each>
      </tbody>
    </table>
  </div>

  <footer class="sitemap__foot">
    <p>
      <a href="https://tech4time.bd/">Back to Tech4TIME</a>
    </p>
  </footer>

</main>
</body>
</html>
</xsl:template>

</xsl:stylesheet>
