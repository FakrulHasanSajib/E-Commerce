import { defineEventHandler } from 'h3';

export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig();
  try {
    const response = await fetch(`${config.public.apiBase}/sitemap.xml`);
    const xml = await response.text();

    event.node.res.setHeader('Content-Type', 'application/xml');
    return xml;
  } catch (e) {
    event.node.res.setHeader('Content-Type', 'application/xml');
    return `<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>http://localhost:3000/</loc></url></urlset>`;
  }
});
