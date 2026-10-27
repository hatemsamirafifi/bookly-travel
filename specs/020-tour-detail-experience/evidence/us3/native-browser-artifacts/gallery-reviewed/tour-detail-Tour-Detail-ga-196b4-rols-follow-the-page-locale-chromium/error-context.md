# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tour-detail.spec.ts >> Tour Detail gallery (Spec 020 US3) >> US3 SSR alt keeps actual language while controls follow the page locale
- Location: tests\e2e\tour-detail.spec.ts:1739:7

# Error details

```
Error: expect(locator).toHaveAttribute(expected) failed

Locator: locator('main#main-content').getByTestId('tour-gallery').getByRole('button', { name: 'Ver imagen 1' }).locator('img')
Expected: "en"
Timeout: 5000ms
Error: element(s) not found

Call log:
  - Expect "toHaveAttribute" with timeout 5000ms
  - waiting for locator('main#main-content').getByTestId('tour-gallery').getByRole('button', { name: 'Ver imagen 1' }).locator('img')

```

```yaml
- banner:
  - link "Saltar al contenido principal":
    - /url: "#main-content"
  - link "Bookly":
    - /url: /es
  - navigation "Navegacion principal":
    - link "Inicio":
      - /url: /es
    - link "Buscar tours":
      - /url: /es/search
    - link "Categorías":
      - /url: /es/categories
    - link "Destinos":
      - /url: /es/destinations
  - button "Buscar tours"
  - combobox "Cambiar idioma":
    - option "English"
    - option "Español" [selected]
    - option "Italiano"
  - link "Iniciar sesion":
    - /url: /es/auth/login
  - link "Registrarse":
    - /url: /es/auth/register
- main:
  - navigation "Ruta de navegación":
    - list:
      - listitem:
        - link "Inicio":
          - /url: /es
      - listitem:
        - link "Walking Tours":
          - /url: /es/categories/walking
      - listitem: Hidden Gems of Rome Walking Tour
  - heading "Hidden Gems of Rome Walking Tour" [level=1]
  - text: "Rome, Italy Valoración: 4.5 de 5"
  - link "(2 Reseñas)":
    - /url: "#reviews"
  - paragraph: El contenido se muestra en inglés
  - paragraph: El itinerario se muestra en inglés
  - button "Ver imagen 1": Esta foto no está disponible.
  - button "Ver imagen 2": Esta foto no está disponible.
  - button "Ver imagen 3": Esta foto no está disponible.
  - button "Ver imagen 4": Esta foto no está disponible.
  - button "Ver imagen 5": Esta foto no está disponible.
  - button "Ver las 5 fotos"
  - navigation "Secciones del tour":
    - link "Sobre Este Tour":
      - /url: "#overview"
    - link "Datos del tour":
      - /url: "#facts"
    - link "Destacados":
      - /url: "#highlights"
    - link "Itinerario":
      - /url: "#itinerary"
    - link "Qué Incluye":
      - /url: "#included"
    - link "Qué No Incluye":
      - /url: "#excluded"
    - link "Punto de Encuentro":
      - /url: "#meeting"
    - link "Política de Cancelación":
      - /url: "#cancellation"
    - link "Información importante":
      - /url: "#important"
    - link "Operador del tour":
      - /url: "#operator"
    - link "Reseñas":
      - /url: "#reviews"
    - link "Más experiencias para explorar":
      - /url: "#related"
  - article:
    - heading "Sobre Este Tour" [level=2]
    - text: Discover the secret corners of Rome that most tourists never see. From hidden courtyards to local artisan shops, this tour takes you off the beaten path through the Eternal City.
    - heading "Datos del tour" [level=2]
    - term: Duración
    - definition: 3 hours
    - term: Tamaño del grupo
    - definition: 1–20 viajeros
    - term: Dificultad
    - definition: Moderada
    - paragraph: "Guía en vivo: Alemán, Inglés, Español"
    - heading "Destacados" [level=2]
    - list:
      - listitem: Hidden courtyards
      - listitem: Local artisan shops
      - listitem: Ancient Roman stories
      - listitem: Off the beaten path
    - heading "Itinerario" [level=2]
    - list:
      - listitem:
        - 'heading "Día 1: Hidden courtyards walk" [level=3]'
        - paragraph: Walk the secret corners of Rome.
      - listitem:
        - 'heading "Día 2: Artisan quarter" [level=3]'
        - paragraph: Meet local artisans.
    - heading "Qué Incluye" [level=3]:
      - button "Qué Incluye"
    - heading "Qué No Incluye" [level=3]:
      - button "Qué No Incluye"
    - heading "Punto de Encuentro" [level=2]
    - paragraph: Colosseum main entrance
    - heading "Política de Cancelación" [level=2]
    - paragraph: Free cancellation up to 24 hours before the tour.
    - heading "Información importante" [level=2]
    - list:
      - listitem: Bring your passport
    - region "Operador del tour":
      - heading "Operador del tour" [level=2]
      - paragraph: Rome Walks
      - paragraph: Operador verificado
      - paragraph: Local guides.
      - text: 3 tours 8 reseñas
    - heading "Reseñas" [level=3]
    - text: "4.5"
    - radiogroup "Star rating":
      - radio "1 star" [disabled]
      - radio "2 stars" [disabled]
      - radio "3 stars" [disabled]
      - radio "4 stars" [disabled]
      - radio "5 stars" [checked] [disabled]
    - paragraph: 2 reseñas
    - text: Test Edited
    - radiogroup "Star rating":
      - radio "1 star" [disabled]
      - radio "2 stars" [disabled]
      - radio "3 stars" [disabled]
      - radio "4 stars" [checked] [disabled]
      - radio "5 stars" [disabled]
    - text: 28 de septiembre de 2026
    - paragraph: Updated comment value here.
    - text: Test
    - radiogroup "Star rating":
      - radio "1 star" [disabled]
      - radio "2 stars" [disabled]
      - radio "3 stars" [disabled]
      - radio "4 stars" [disabled]
      - radio "5 stars" [checked] [disabled]
    - text: 12 de septiembre de 2026
    - paragraph: A wonderful hidden-gems walk through Rome — highly recommended!
  - complementary:
    - text: 45,00 € / persona
    - button "Guardar en lista de deseos"
    - text: Seleccionar Fecha
    - paragraph: Próximo disponible
    - paragraph: 17/10/2026
    - combobox "Seleccionar Fecha":
      - option "Seleccionar Fecha"
      - option "17/10/2026" [selected]
      - option "18/10/2026"
      - option "19/10/2026"
      - option "20/10/2026"
      - option "21/10/2026"
      - option "22/10/2026"
      - option "23/10/2026"
      - option "24/10/2026"
      - option "25/10/2026"
      - option "26/10/2026"
      - option "27/10/2026"
      - option "28/10/2026"
      - option "29/10/2026"
      - option "30/10/2026"
      - option "31/10/2026"
      - option "01/11/2026"
      - option "02/11/2026"
      - option "03/11/2026"
      - option "04/11/2026"
      - option "05/11/2026"
      - option "06/11/2026"
      - option "07/11/2026"
      - option "08/11/2026"
      - option "09/11/2026"
      - option "10/11/2026"
      - option "11/11/2026"
      - option "12/11/2026"
      - option "13/11/2026"
      - option "14/11/2026"
      - option "15/11/2026"
      - option "16/11/2026"
      - option "17/11/2026"
      - option "18/11/2026"
      - option "19/11/2026"
      - option "20/11/2026"
      - option "21/11/2026"
      - option "22/11/2026"
      - option "23/11/2026"
      - option "24/11/2026"
      - option "25/11/2026"
      - option "26/11/2026"
      - option "27/11/2026"
      - option "28/11/2026"
      - option "29/11/2026"
      - option "30/11/2026"
      - option "01/12/2026"
      - option "02/12/2026"
      - option "03/12/2026"
      - option "04/12/2026"
      - option "05/12/2026"
      - option "06/12/2026"
      - option "07/12/2026"
      - option "08/12/2026"
      - option "09/12/2026"
      - option "10/12/2026"
      - option "11/12/2026"
      - option "12/12/2026"
      - option "13/12/2026"
      - option "14/12/2026"
      - option "15/12/2026"
    - text: Participantes
    - group "Participantes":
      - button "Reducir participantes" [disabled]: "-"
      - status: "1"
      - button "Aumentar participantes": +
      - text: 1–20 permitidos
    - paragraph: "Total estimado: 45,00 €"
    - paragraph: Free cancellation up to 24 hours before the tour.
    - paragraph: Inicia sesión para continuar con la reserva. Tu tour, fecha, número de viajeros e idioma se conservan y no se reserva nada hasta que confirmes.
    - link "Reservar Ahora":
      - /url: /es/auth/login?returnUrl=%2Fes%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17
    - link "Crear cuenta":
      - /url: /es/auth/register?returnUrl=%2Fes%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17
  - region "Más experiencias para explorar":
    - heading "Más experiencias para explorar" [level=2]
    - 'link "Roman Forum Small Group Rome, Italy 2 hours Roman Forum Small Group Rating: 4.8 out of 5 (4.8) (11 reseñas) Walking Tours €39.00"':
      - /url: /es/tours/roman-forum-small-group
      - img "Roman Forum Small Group"
      - text: Rome, Italy 2 hours
      - heading "Roman Forum Small Group" [level=2]
      - 'img "Rating: 4.8 out of 5"'
      - text: (4.8) (11 reseñas) Walking Tours €39.00
    - button "Guardar en lista de deseos"
- contentinfo:
  - img "Bookly Travel — Book smarter. Travel better."
  - paragraph: Explorar
  - list:
    - listitem:
      - link "Buscar tours":
        - /url: /es/search
    - listitem:
      - link "Categorías":
        - /url: /es/categories
    - listitem:
      - link "Destinos":
        - /url: /es/destinations
  - paragraph: Empresa
  - list:
    - listitem:
      - link "Política de privacidad":
        - /url: /es/privacy
    - listitem:
      - link "Términos del servicio":
        - /url: /es/terms
  - text: © 2026 Bookly. Todos los derechos reservados.
  - combobox "Cambiar idioma":
    - option "English"
    - option "Español" [selected]
    - option "Italiano"
- complementary "Cookie consent":
  - text: Usamos cookies para mejorar tu experiencia de navegación y analizar nuestro tráfico. Al hacer clic en "Aceptar", aceptas nuestro uso de cookies.
  - button "Rechazar"
  - button "Aceptar"
- alert
```

# Test source

```ts
  1655 |       const gallery = page.locator('main#main-content').getByTestId('tour-gallery');
  1656 |       const tiles = gallery.getByRole('button', { name: /View image \d+/ });
  1657 |       await expect(tiles).toHaveCount(5);
  1658 |       // Exactly one honest placeholder; every slot keeps a nonzero box.
  1659 |       // Mosaic contract with ceil-pixel tolerance (not exact unique counts):
  1660 |       // support quarters share dimensions within 1px, and the cover spans
  1661 |       // two quarter columns plus the 8px grid gap within 2px.
  1662 |       await expect(gallery.getByText('This photo is unavailable.')).toHaveCount(1);
  1663 |       const boxes = await tiles.evaluateAll((els) =>
  1664 |         els.map((el) => {
  1665 |           const rect = el.getBoundingClientRect();
  1666 |           return { width: rect.width, height: rect.height };
  1667 |         }),
  1668 |       );
  1669 |       expect(boxes.every((box) => box.width > 0 && box.height > 0)).toBe(true);
  1670 |       const quarter = boxes[1];
  1671 |       for (const index of [2, 3, 4]) {
  1672 |         expect(Math.abs(boxes[index].width - quarter.width)).toBeLessThanOrEqual(1);
  1673 |         expect(Math.abs(boxes[index].height - quarter.height)).toBeLessThanOrEqual(1);
  1674 |       }
  1675 |       expect(Math.abs(boxes[0].width - (2 * quarter.width + 8))).toBeLessThanOrEqual(2);
  1676 |       expect(Math.abs(boxes[0].height - (2 * quarter.height + 8))).toBeLessThanOrEqual(2);
  1677 |       expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(
  1678 |         await page.evaluate(() => document.documentElement.clientWidth),
  1679 |       );
  1680 | 
  1681 |       // Every photo stays reachable through the counter, then the broken
  1682 |       // third photo is explicitly current: caption, counter and failure all
  1683 |       // asserted together before the rest of the reachability claim.
  1684 |       await tiles.nth(0).click();
  1685 |       const dialog = page.getByRole('dialog', { name: 'Image lightbox' });
  1686 |       await expect(dialog).toBeVisible();
  1687 |       for (let step = 2; step <= 5; step += 1) {
  1688 |         await dialog.getByRole('button', { name: 'Next image' }).click();
  1689 |         await expect(dialog.getByText(`${step} of 5`)).toBeVisible();
  1690 |       }
  1691 |       await dialog.getByRole('button', { name: 'Previous image' }).click();
  1692 |       await dialog.getByRole('button', { name: 'Previous image' }).click();
  1693 |       await expect(dialog.getByText('3 of 5')).toBeVisible();
  1694 |       await expect(dialog.locator('img')).toHaveCount(0);
  1695 |       await expect(dialog.getByText('US3 photo 3', { exact: true })).toBeVisible();
  1696 |       await expect(dialog.getByText('This photo is unavailable.')).toHaveCount(1);
  1697 |       const reopenBoxes = await tiles.evaluateAll((els) =>
  1698 |         els.map((el) => {
  1699 |           const rect = el.getBoundingClientRect();
  1700 |           return { width: rect.width, height: rect.height };
  1701 |         }),
  1702 |       );
  1703 |       expect(reopenBoxes).toEqual(boxes);
  1704 |       await page.keyboard.press('Escape');
  1705 |       await expect(dialog).toHaveCount(0);
  1706 | 
  1707 |       // New SSR identity swaps the broken URL for an EXISTING owned asset
  1708 |       // (plus query): the replacement must actually decode (naturalWidth>0)
  1709 |       // with no failure placeholder. Same-component rerender recovery is
  1710 |       // covered at unit level; the browser claims the new SSR identity only.
  1711 |       current = createFullTourDetail(new Date(), {
  1712 |         images: images.map((image, index) =>
  1713 |           index === 2 ? { ...image, url: 'http://cdn.bookly.test/tours/rome-gallery-2.jpg?us3-recovered' } : image,
  1714 |         ),
  1715 |       });
  1716 |       await page.reload();
  1717 |       const recovered = page.locator('main#main-content').getByTestId('tour-gallery');
  1718 |       await expect(recovered.getByRole('button', { name: /View image \d+/ })).toHaveCount(5);
  1719 |       await expect(recovered.getByText('This photo is unavailable.')).toHaveCount(0);
  1720 |       const recoveredImg = recovered.getByRole('button', { name: 'View image 3' }).locator('img');
  1721 |       await expect
  1722 |         .poll(() => recoveredImg.evaluate((image: HTMLImageElement) => image.complete && image.naturalWidth > 0))
  1723 |         .toBe(true);
  1724 |       await page.getByRole('button', { name: 'View image 1' }).click();
  1725 |       const reopened = page.getByRole('dialog', { name: 'Image lightbox' });
  1726 |       await expect(reopened).toBeVisible();
  1727 |       for (let step = 2; step <= 5; step += 1) {
  1728 |         await reopened.getByRole('button', { name: 'Next image' }).click();
  1729 |         await expect(reopened.getByText(`${step} of 5`)).toBeVisible();
  1730 |       }
  1731 |       await expect(reopened.locator('img')).toHaveAttribute('alt', 'US3 photo 5');
  1732 |       await page.keyboard.press('Escape');
  1733 |       await expect(reopened).toHaveCount(0);
  1734 |     } finally {
  1735 |       await harness.stop();
  1736 |     }
  1737 |   });
  1738 | 
  1739 |   test('US3 SSR alt keeps actual language while controls follow the page locale', async ({ page }) => {
  1740 |     test.setTimeout(180000);
  1741 |     await page.setViewportSize({ width: 1440, height: 900 });
  1742 |     const full = createFullTourDetail(new Date());
  1743 |     const harness = await startSsrHarness(slug, () => full);
  1744 |     try {
  1745 |       const served = await readServedDetail(harness.mockPort, slug, 'es');
  1746 |       expect(served.content_locale).toBe('en');
  1747 | 
  1748 |       await page.goto(`http://127.0.0.1:${harness.nextPort}/es/tours/${slug}`);
  1749 |       const gallery = page.locator('main#main-content').getByTestId('tour-gallery');
  1750 |       await expect(gallery).toBeVisible();
  1751 |       // Page-localized controls; authored EN alt declares lang=en.
  1752 |       await expect(gallery.getByRole('button', { name: 'Ver imagen 1' })).toBeVisible();
  1753 |       await expect(page.getByRole('button', { name: 'Ver las 5 fotos' })).toBeVisible();
  1754 |       const tileImg = gallery.getByRole('button', { name: 'Ver imagen 1' }).locator('img');
> 1755 |       await expect(tileImg).toHaveAttribute('lang', 'en');
       |                             ^ Error: expect(locator).toHaveAttribute(expected) failed
  1756 | 
  1757 |       await gallery.getByRole('button', { name: 'Ver imagen 1' }).click();
  1758 |       const dialog = page.getByRole('dialog', { name: 'Galería ampliada' });
  1759 |       await expect(dialog).toBeVisible();
  1760 |       await expect(dialog.locator('img')).toHaveAttribute('lang', 'en');
  1761 |       await expect(dialog.getByText('1 de 5')).toBeVisible();
  1762 |       const closeLanguage = await page
  1763 |         .getByRole('button', { name: 'Cerrar galería' })
  1764 |         .evaluate((el: HTMLElement) => el.closest('[lang]')?.getAttribute('lang'));
  1765 |       expect(closeLanguage).toBe('es');
  1766 |       await page.keyboard.press('Escape');
  1767 |       await expect(dialog).toHaveCount(0);
  1768 |     } finally {
  1769 |       await harness.stop();
  1770 |     }
  1771 |   });
  1772 | 
  1773 |   test('US3 SSR gallery keeps a stable box and no page overflow at 390 and 1440', async ({ page }) => {
  1774 |     test.setTimeout(180000);
  1775 |     const full = createFullTourDetail(new Date());
  1776 |     const harness = await startSsrHarness(slug, () => full);
  1777 |     try {
  1778 |       for (const width of [390, 1440]) {
  1779 |         await page.setViewportSize({ width, height: 900 });
  1780 |         await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  1781 |         const tiles = page.locator('main#main-content').getByTestId('tour-gallery').getByRole('button', { name: /View image \d+/ });
  1782 |         await expect(tiles).toHaveCount(5);
  1783 |         const boxes = await tiles.evaluateAll((els) =>
  1784 |           els.map((el) => {
  1785 |             const rect = el.getBoundingClientRect();
  1786 |             return { width: rect.width, height: rect.height };
  1787 |           }),
  1788 |         );
  1789 |         // No collapsed zero box anywhere. Rail tiles share one box within
  1790 |         // 1px; the desktop mosaic keeps quarter equality with the cover at
  1791 |         // two quarters plus the 8px grid gap within 2px.
  1792 |         expect(boxes.every((box) => box.width > 0 && box.height > 0)).toBe(true);
  1793 |         if (width === 390) {
  1794 |           for (const box of boxes) {
  1795 |             expect(Math.abs(box.height - boxes[0].height)).toBeLessThanOrEqual(1);
  1796 |           }
  1797 |         } else {
  1798 |           for (const index of [1, 2, 3, 4]) {
  1799 |             expect(Math.abs(boxes[index].width - boxes[1].width)).toBeLessThanOrEqual(1);
  1800 |             expect(Math.abs(boxes[index].height - boxes[1].height)).toBeLessThanOrEqual(1);
  1801 |           }
  1802 |           expect(Math.abs(boxes[0].width - (2 * boxes[1].width + 8))).toBeLessThanOrEqual(2);
  1803 |           expect(Math.abs(boxes[0].height - (2 * boxes[1].height + 8))).toBeLessThanOrEqual(2);
  1804 |         }
  1805 |         expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(
  1806 |           await page.evaluate(() => document.documentElement.clientWidth),
  1807 |         );
  1808 |       }
  1809 |     } finally {
  1810 |       await harness.stop();
  1811 |     }
  1812 |   });
  1813 | });
  1814 | 
  1815 | // Spec 020 US4 T053: reading-navigation browser acceptance through the real
  1816 | // built SSR server. Controlled complete/sparse fixtures (no shared-data
  1817 | // writes) prove native anchor intent, real heading focus clear of the
  1818 | // measured sticky nav, passive aria tracking, horizontal-only rail reveal,
  1819 | // resize, long-locale labels, reduced motion and deep-link restoration.
  1820 | // No route interception (SSR would ignore it), no mocked observers, no
  1821 | // sleeps: every wait targets real nav geometry, aria state or enabled UI
  1822 | // readiness. Touch/gallery scenarios stay with US3; booking/wishlist/catalog
  1823 | // behavior is untouched.
  1824 | test.describe('Tour Detail section navigation (Spec 020 US4 T053)', () => {
  1825 |   const slug = 'hidden-gems-rome-walking-tour';
  1826 |   const fullIds = [
  1827 |     'overview', 'facts', 'highlights', 'itinerary', 'included', 'excluded',
  1828 |     'meeting', 'cancellation', 'important', 'operator', 'reviews', 'related',
  1829 |   ];
  1830 | 
  1831 |   async function navFor(page: Page, name: string) {
  1832 |     const nav = page.getByRole('navigation', { name });
  1833 |     await expect(nav).toBeVisible();
  1834 |     return nav;
  1835 |   }
  1836 | 
  1837 |   function headingFor(page: Page, id: string) {
  1838 |     // Section-scoped inside the rendered main content: Next 16.2 hidden
  1839 |     // streamed trees can duplicate IDs/testids, so a bare `#id` query may
  1840 |     // resolve the wrong tree. .first() here selects within ONE section,
  1841 |     // mirroring the island's own resolveHeading rule (first h1/h2/h3 in the
  1842 |     // section) — it does not paper over ambiguous live UI. Role queries stay
  1843 |     // visible-by-default and need no change.
  1844 |     return page
  1845 |       .locator('main#main-content')
  1846 |       .locator(`#${id}`)
  1847 |       .locator(':is(h1, h2, h3)')
  1848 |       .first();
  1849 |   }
  1850 | 
  1851 |   // Explicit activation by pointer click (keyboard path covered separately
  1852 |   // below): updates the native hash, focuses the real heading, then — only
  1853 |   // once scrolling finishes — requires the observer to agree with the
  1854 |   // explicit target instead of overriding it with another visible heading.
  1855 |   // Returns the focused heading for geometry checks.
```