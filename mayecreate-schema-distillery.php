<?php
/**
 * Plugin Name:       MayeCreate Schema – Distillery
 * Description:       Add-on for MayeCreate Schema: Product markup for spirits and Recipe markup for cocktails, connected to the main schema graph.
 * Version:           1.0.0
 * Author:            MayeCreate Design
 * Author URI:        https://mayecreate.com
 * Requires at least: 6.0
 * Requires PHP:      7.4
 *
 * Products: post type "products", fields from the "Product Fields" group.
 * Recipes:  post type "recipes", fields from the "Recipes" group; Main Spirit links each recipe to its product.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MCSD_VERSION', '1.0.0' );

/* ===========================================================================
 * Setup
 * ======================================================================== */

add_action(
	'admin_notices',
	function () {
		if ( function_exists( 'mcs_build_graph' ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>MayeCreate Schema – Distillery</strong> is an add-on. Install and activate <strong>MayeCreate Schema</strong> too.</p></div>';
	}
);

/**
 * Post types handled by this add-on. Filter to change them.
 */
function mcsd_post_types( $which ) {
	$defaults = array(
		'product' => array( 'products', 'product' ),
		'recipe'  => array( 'recipes', 'recipe', 'cocktails', 'cocktail' ),
	);
	return (array) apply_filters( 'mcsd_' . $which . '_post_types', $defaults[ $which ] );
}

/**
 * ACF field names. Filter to change them.
 */
function mcsd_fields() {
	return apply_filters(
		'mcsd_fields',
		array(
			// Products.
			'image'             => 'intro_area_image',
			'description'       => 'intro_area_text',
			'buy_link'          => 'buy_it_link',
			'proof'             => 'proof',
			'abv'               => 'alcohol_by_volume',
			'bottle_size'       => 'bottle_size',
			'mash'              => array(
				'Corn'   => 'mash_corn',
				'Wheat'  => 'mash_wheat',
				'Barley' => 'mash_barley',
				'Rye'    => 'mash_rye',
			),
			// Recipes.
			'ingredients'       => 'ingredient_list',
			'instructions'      => 'step_instructions',
			'garnish'           => 'garnish_glassware_notes',
			'main_spirit'       => 'main_spirit',
			'recipe_category'   => 'Cocktail',
			'recipe_yield'      => '1 drink',
		)
	);
}

function mcsd_raw( $field, $id ) {
	return ( $field && function_exists( 'get_field' ) ) ? get_field( $field, $id, false ) : null;
}

/* ===========================================================================
 * Page classification
 * ======================================================================== */

add_filter(
	'mcs_context',
	function ( $ctx ) {
		if ( empty( $ctx['post_id'] ) || ! empty( $ctx['home'] ) ) {
			return $ctx;
		}
		// Respect a manual choice in the page's Schema box.
		if ( function_exists( 'mcs_meta' ) && 'auto' !== mcs_meta( $ctx['post_id'], 'page_type', 'auto' ) ) {
			return $ctx;
		}
		$type = get_post_type( $ctx['post_id'] );
		if ( in_array( $type, mcsd_post_types( 'product' ), true ) ) {
			$ctx['kind'] = 'product';
		} elseif ( in_array( $type, mcsd_post_types( 'recipe' ), true ) ) {
			$ctx['kind'] = 'recipe';
		}
		return $ctx;
	}
);

// The page's main entity is the product or recipe; a product's "Buy it" link is a related link.
add_filter(
	'mcs_node_webpage',
	function ( $node, $ctx ) {
		if ( 'product' === $ctx['kind'] ) {
			$node['mainEntity'] = mcs_ref( mcs_id( 'product', $ctx['url'] ) );
			$buy                = esc_url_raw( (string) mcsd_raw( mcsd_fields()['buy_link'], $ctx['post_id'] ) );
			if ( $buy ) {
				$node['relatedLink'] = $buy;
			}
		} elseif ( 'recipe' === $ctx['kind'] ) {
			$node['mainEntity'] = mcs_ref( mcs_id( 'recipe', $ctx['url'] ) );
		}
		return $node;
	},
	10,
	2
);

add_filter(
	'mcs_nodes_for_kind',
	function ( $nodes, $ctx ) {
		if ( 'product' === $ctx['kind'] ) {
			$nodes['product'] = mcsd_node_product( $ctx );
		} elseif ( 'recipe' === $ctx['kind'] ) {
			$nodes['recipe'] = mcsd_node_recipe( $ctx );
		}
		return $nodes;
	},
	10,
	2
);

/* ===========================================================================
 * Product
 * ======================================================================== */

function mcsd_product_id( $post_id ) {
	return mcs_id( 'product', get_permalink( $post_id ) );
}

function mcsd_node_product( $ctx ) {
	$id     = $ctx['post_id'];
	$url    = $ctx['url'];
	$fields = mcsd_fields();
	$org    = mcs_ref( mcs_id( 'organization' ) );

	// Image: the intro image, then the featured image. Placeholders are skipped.
	$image_id = mcs_attachment_id( mcsd_raw( $fields['image'], $id ) );
	if ( ! $image_id || mcs_is_placeholder( $image_id ) ) {
		$image_id = mcs_thumbnail_id( $id );
	}
	$image = $image_id ? wp_get_attachment_url( $image_id ) : '';

	$description = mcs_text( (string) mcsd_raw( $fields['description'], $id ) );
	if ( ! $description ) {
		$description = mcs_description_for( $ctx );
	}

	$details = array();
	$add     = function ( $name, $value, $unit = '' ) use ( &$details ) {
		$value = is_string( $value ) ? mcs_text( $value ) : $value;
		if ( '' === $value || null === $value || false === $value ) {
			return;
		}
		$details[] = array(
			'@type'    => 'PropertyValue',
			'name'     => $name,
			'value'    => $value,
			'unitText' => $unit,
		);
	};
	$add( 'Proof', mcsd_raw( $fields['proof'], $id ) );
	$add( 'Alcohol by volume', mcsd_raw( $fields['abv'], $id ) );
	foreach ( $fields['mash'] as $grain => $field ) {
		$pct = mcsd_raw( $field, $id );
		if ( is_numeric( $pct ) && (float) $pct > 0 ) {
			$add( 'Mash bill: ' . $grain, $pct + 0, '%' );
		}
	}

	$size = mcs_text( (string) mcsd_raw( $fields['bottle_size'], $id ) );

	return apply_filters(
		'mcsd_node_product',
		array(
			'@type'              => 'Product',
			'@id'                => mcs_id( 'product', $url ),
			'name'               => $ctx['name'],
			'description'        => $description,
			'url'                => $url,
			'image'              => $image,
			'brand'              => $org,
			'manufacturer'       => $org,
			'size'               => $size,
			'additionalProperty' => $details,
			'mainEntityOfPage'   => mcs_ref( mcs_id( 'webpage', $url ) ),
		),
		$ctx
	);
}

/* ===========================================================================
 * Recipe
 * ======================================================================== */

/**
 * Split a WYSIWYG or text area into clean lines: list items, paragraphs, line breaks.
 * Leading bullets and step numbers ("1.", "Step 2:", "-", "•") are removed.
 */
function mcsd_lines( $value ) {
	if ( ! is_string( $value ) || '' === trim( $value ) ) {
		return array();
	}
	$value = strip_shortcodes( $value );
	$value = preg_replace( '#<\s*(br|/p|/li|/h[1-6]|/div)\s*/?>#i', "\n", $value );
	$value = wp_strip_all_tags( html_entity_decode( $value, ENT_QUOTES, 'UTF-8' ) );

	$lines = array();
	foreach ( preg_split( '/\r\n|\r|\n/', $value ) as $line ) {
		$line = trim( preg_replace( '/\s+/u', ' ', $line ) );
		$line = preg_replace( '/^(?:step\s*\d+\s*[:.)-]?|\d+\s*[.)]|[-*•–]+)\s*/iu', '', $line );
		$line = trim( $line );
		// Skip headings that only label the list.
		if ( '' === $line || preg_match( '/^(ingredients|instructions|directions|steps|method)\s*:?$/i', $line ) ) {
			continue;
		}
		$lines[] = $line;
	}
	return $lines;
}

/**
 * Product post IDs chosen in a recipe's Main Spirit relationship field.
 */
function mcsd_main_spirits( $recipe_id ) {
	$value = mcsd_raw( mcsd_fields()['main_spirit'], $recipe_id );
	if ( is_string( $value ) ) {
		$value = maybe_unserialize( $value );
	}
	$ids = array();
	foreach ( (array) $value as $item ) {
		$pid = mcs_post_id( $item );
		if ( $pid && 'publish' === get_post_status( $pid ) ) {
			$ids[] = $pid;
		}
	}
	return array_values( array_unique( $ids ) );
}

function mcsd_node_recipe( $ctx ) {
	$id     = $ctx['post_id'];
	$url    = $ctx['url'];
	$fields = mcsd_fields();

	$ingredients = mcsd_lines( (string) mcsd_raw( $fields['ingredients'], $id ) );

	$steps = array();
	foreach ( mcsd_lines( (string) mcsd_raw( $fields['instructions'], $id ) ) as $text ) {
		$steps[] = array(
			'@type' => 'HowToStep',
			'text'  => $text,
		);
	}
	$garnish = implode( ' ', mcsd_lines( (string) mcsd_raw( $fields['garnish'], $id ) ) );
	if ( $garnish ) {
		$steps[] = array(
			'@type' => 'HowToStep',
			'name'  => 'Garnish and glassware',
			'text'  => $garnish,
		);
	}

	$spirits  = mcsd_main_spirits( $id );
	$mentions = array();
	$keywords = array();
	foreach ( $spirits as $pid ) {
		$mentions[] = mcs_ref( mcsd_product_id( $pid ) );
		$keywords[] = mcs_text( get_the_title( $pid ) );
	}
	$keywords[] = strtolower( $fields['recipe_category'] );

	$thumb = mcs_thumbnail_id( $id );

	return apply_filters(
		'mcsd_node_recipe',
		array(
			'@type'              => 'Recipe',
			'@id'                => mcs_id( 'recipe', $url ),
			'name'               => $ctx['name'],
			'description'        => mcs_description_for( $ctx ),
			'url'                => $url,
			'image'              => $thumb ? mcs_ref( mcs_id( 'primaryimage', $url ) ) : null,
			'author'             => mcs_ref( mcs_id( 'organization' ) ),
			'publisher'          => mcs_ref( mcs_id( 'organization' ) ),
			'datePublished'      => get_post_time( 'c', true, $id ),
			'dateModified'       => get_post_modified_time( 'c', true, $id ),
			'recipeCategory'     => $fields['recipe_category'],
			'recipeYield'        => $fields['recipe_yield'],
			'recipeIngredient'   => $ingredients,
			'recipeInstructions' => $steps,
			'keywords'           => implode( ', ', array_unique( array_filter( $keywords ) ) ),
			'mentions'           => $mentions,
			'isPartOf'           => mcs_ref( mcs_id( 'webpage', $url ) ),
			'mainEntityOfPage'   => mcs_ref( mcs_id( 'webpage', $url ) ),
			'inLanguage'         => get_bloginfo( 'language' ),
		),
		$ctx
	);
}
