<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:sm="http://www.sitemaps.org/schemas/sitemap/0.9"
    xmlns:xhtml="http://www.w3.org/1999/xhtml"
    exclude-result-prefixes="sm xhtml">
  <xsl:output method="html" encoding="UTF-8" indent="yes"/>
  <xsl:template match="/">
    <html lang="cs">
      <head>
        <meta charset="UTF-8"/>
        <meta name="robots" content="noindex"/>
        <title>Sitemap</title>
        <style>
          body { font: 14px/1.5 system-ui, sans-serif; margin: 24px; color: #222; background: #fff; }
          h1 { font-size: 20px; margin: 0 0 4px; }
          p { color: #666; margin: 0 0 16px; }
          table { border-collapse: collapse; width: 100%; }
          th, td { text-align: left; padding: 6px 10px; border-bottom: 1px solid #e5e5e5; vertical-align: top; }
          th { background: #f5f5f5; font-weight: 600; }
          td.n { white-space: nowrap; color: #666; }
          a { color: #0b5cad; text-decoration: none; word-break: break-all; }
          a:hover { text-decoration: underline; }
          .alt { font-size: 12px; color: #888; }
        </style>
      </head>
      <body>
        <h1>XML Sitemap</h1>
        <p>URL: <xsl:value-of select="count(sm:urlset/sm:url)"/></p>
        <table>
          <tr><th>URL</th><th>Alternates</th><th>Priority</th><th>Lastmod</th></tr>
          <xsl:for-each select="sm:urlset/sm:url">
            <tr>
              <td><a href="{sm:loc}"><xsl:value-of select="sm:loc"/></a></td>
              <td class="alt">
                <xsl:for-each select="xhtml:link">
                  <xsl:value-of select="@hreflang"/><xsl:text> </xsl:text>
                </xsl:for-each>
              </td>
              <td class="n"><xsl:value-of select="sm:priority"/></td>
              <td class="n"><xsl:value-of select="sm:lastmod"/></td>
            </tr>
          </xsl:for-each>
        </table>
      </body>
    </html>
  </xsl:template>
</xsl:stylesheet>
