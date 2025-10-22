# Archive SEO Examples

## Sample Output Matrix

| URL | Title | Meta Description | Canonical | Robots |
| --- | --- | --- | --- | --- |
| `/category/vehicles/` | `GTA 6 Vehicle Mods | GTA6-Mods.com` | `Explore 2,547 vehicle mods for GTA 6. Premium community creations and regular updates. Community-curated, quality checked and updated frequently.` | `https://gta6-mods.com/category/vehicles/` | `index, follow` |
| `/category/vehicles/tag/car/` | `Car Vehicle Mods for GTA 6 | GTA6-Mods.com` | `Browse 156 car vehicle mods for GTA 6. Community-curated and refreshed often.` | `https://gta6-mods.com/category/vehicles/tag/car/` | `index, follow` |
| `/category/vehicles/most-downloaded/tag/bmw+audi/` | `BMW and Audi Vehicle Mods – This Week's Most Popular | GTA6-Mods.com` | `This week's most popular vehicle mods for GTA 6. 89 additions curated from the community.` | `https://gta6-mods.com/category/vehicles/most-downloaded/tag/bmw+audi/` | `index, follow` |
| `/category/vehicles/most-downloaded/year/tag/bmw+audi/` | `BMW and Audi Vehicle Mods – This Year's Most Popular | GTA6-Mods.com` | `This year's most popular vehicle mods for GTA 6. 312 releases curated from the community.` | `https://gta6-mods.com/category/vehicles/most-downloaded/year/tag/bmw+audi/` | `index, follow` |
| `/tag/bmw/category/vehicles/most-downloaded/` | `BMW Vehicle Mods for GTA 6 – This Week's Most Popular | GTA6-Mods.com` | `This week's most popular BMW vehicle mods for GTA 6. Community favorites refreshed often.` | `https://gta6-mods.com/tag/bmw/category/vehicles/most-downloaded/` | `index, follow` |
| `/search/weapon/category/weapons/latest-uploads/` | `"weapon" – GTA 6 Mod Search in Weapons – Latest Results | GTA6-Mods.com` | `Search results for "weapon" in GTA 6 mods. 47 results found across weapons categories. Community-approved quality and ongoing updates.` | `https://gta6-mods.com/search/weapon/category/weapons/latest-uploads/` | `index, follow` |
| `/category/vehicles/featured/week/` | `This Week's Featured Vehicle Mods for GTA 6 | GTA6-Mods.com` | `This week's featured vehicle mods for GTA 6. 42 curated drops.` | `https://gta6-mods.com/category/vehicles/featured/week/` | `index, follow` |
| `/category/vehicles/page/2/` | `GTA 6 Vehicle Mods | GTA6-Mods.com – Page 2` | `Explore 2,547 vehicle mods for GTA 6. Premium community creations and regular updates. Community-curated, quality checked and updated frequently.` | `https://gta6-mods.com/category/vehicles/page/2/` | `noindex, follow` |

## Performance Notes

- SEO payloads are cached per filter combination in the `gta6mods_seo` object cache group for one hour to minimise regeneration under high load.
- Archive mod cards are memoised for 15 minutes via transients; the SEO layer reuses these datasets to avoid duplicate database queries.
- Social images and structured data reuse the denormalised archive index so heavy JOINs are avoided on high-traffic listings.

## Edge Case Handling

- Empty result sets fall back to "No Mods Found" messaging with guidance to adjust filters while keeping canonical URLs stable.
- Pagination beyond the first page automatically applies `noindex, follow` robots directives while still emitting prev/next links for crawlability.
- Missing thumbnails degrade gracefully to the category fallback image so social shares and structured data remain valid.
