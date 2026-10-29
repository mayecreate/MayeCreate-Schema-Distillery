# MayeCreate Schema – Distillery

Add-on for **MayeCreate Schema** (install and activate that first). Adds Product markup for spirits and Recipe markup for cocktails, connected to the same organization, website and page nodes.

## Products (post type `products`)

| Field | Schema |
|---|---|
| Post title | `name` |
| `intro_area_image` (falls back to featured image) | `image` |
| `intro_area_text` | `description` |
| `bottle_size` | `size` |
| `proof`, `alcohol_by_volume`, `mash_corn`/`mash_wheat`/`mash_barley`/`mash_rye` | `additionalProperty` (mash bill as %; zero/empty grains skipped) |
| The organization | `brand`, `manufacturer` |
| `buy_it_link` | `relatedLink` on the WebPage |

No `offers`: the products have no price, and Google requires one. Expect Search Console to list products as not eligible for product snippets; that is not a site error. FAQs are not marked up (Google retired FAQ rich results for most sites).

## Recipes (post type `recipes`)

| Field | Schema |
|---|---|
| Post title | `name` |
| Featured image | `image` (required by Google for recipe results) |
| `ingredient_list` (WYSIWYG) | `recipeIngredient`, one per list item / line |
| `step_instructions` (text area) | `recipeInstructions`, one `HowToStep` per line; "1.", "Step 2:", bullets removed |
| `garnish_glassware_notes` | Final step, "Garnish and glassware" |
| `main_spirit` (relationship to products) | `mentions` the product's `#product` node; spirit names added to `keywords` |
| The organization | `author`, `publisher` |
| Fixed | `recipeCategory` "Cocktail", `recipeYield` "1 drink" |

Prep/cook times are intentionally left out: they aren't shown on the page. Add a visible field and map it with the `mcsd_node_recipe` filter if wanted.

**Writing tip:** put each ingredient and each step on its own line (or list item). One long paragraph becomes one step.

## Breadcrumbs

Set the Products and Recipes listing pages to **Page type: Collection** with **Post type: Products / Recipes** in their Schema box (or give them the slugs `products` / `recipes`), so items get Home > Products > item.

## Developer filters

- `mcsd_fields`: change field names, category or yield.
- `mcsd_product_post_types`, `mcsd_recipe_post_types`: change post types.
- `mcsd_node_product`, `mcsd_node_recipe`: change the output.
- A page's Schema box *Page type* set to anything but Automatic overrides this add-on.
